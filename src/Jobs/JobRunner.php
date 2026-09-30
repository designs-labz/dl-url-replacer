<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Jobs;

use DesignsLabz\Relocate\Database\Schema;
use DesignsLabz\Relocate\Database\TableLayout;
use DesignsLabz\Relocate\Installer;
use DesignsLabz\Relocate\Logger;
use DesignsLabz\Relocate\Replace\Replacer;
use DesignsLabz\Relocate\Settings;
use RuntimeException;

/**
 * Advances a job one step at a time.
 *
 * A step works through windows of rows until it runs out of time or memory,
 * saving the job's position after every window. Whoever calls it (the admin
 * screen over REST, later WP-CLI) just keeps calling until the job finishes,
 * and an interrupted job carries on from its last saved window.
 */
final class JobRunner {

	private const STEP_SECONDS = 10.0;

	// Enough to diagnose a problem without flooding the log on a large site.
	private const MAX_LOGGED_SKIPS = 50;

	/** @var array<string, TableLayout|null> */
	private array $layouts = array();

	public function __construct(
		private \wpdb $wpdb,
		private Schema $schema,
		private JobRepository $jobs,
		private Settings $settings,
		private Logger $logger,
		private float $step_seconds = self::STEP_SECONDS
	) {}

	/**
	 * @return Job|null The job as saved after the step, or null when another request is processing it.
	 * @throws RuntimeException When the job cannot be saved.
	 */
	public function step( Job $job ): ?Job {
		$id = $job->id;

		if ( ! $this->lock( $id, 0 ) ) {
			return null;
		}

		try {
			// Re-read under the lock: another request may have moved the job on.
			$job = $this->jobs->find( $id );

			if ( null === $job || $job->status->is_finished() ) {
				return $job;
			}

			$this->run( $job );

			return $job;
		} finally {
			$this->unlock( $id );
		}
	}

	/**
	 * @return Job|null The cancelled job, or null when it is still locked by a running step.
	 * @throws RuntimeException When the job cannot be saved.
	 */
	public function cancel( Job $job ): ?Job {
		// Wait for a step in progress to finish rather than overwrite what it saves.
		$id = $job->id;

		if ( ! $this->lock( $id, (int) ceil( $this->step_seconds ) + 5 ) ) {
			return null;
		}

		try {
			$job = $this->jobs->find( $id );

			if ( null !== $job && ! $job->status->is_finished() ) {
				$job->finish( JobStatus::Cancelled );
				$this->jobs->save( $job );
				$this->logger->info( 'Job cancelled.', array(), $job->id );
			}

			return $job;
		} finally {
			$this->unlock( $id );
		}
	}

	private function run( Job $job ): void {
		if ( JobStatus::Pending === $job->status ) {
			$job->status     = JobStatus::Running;
			$job->started_at = gmdate( 'Y-m-d H:i:s' );
		}

		$replacement = $job->replacement();
		$batch       = new TableBatch( $this->wpdb, $replacement, new Replacer( $replacement ), $this->settings->batch_size() );
		$deadline    = microtime( true ) + $this->step_seconds();

		try {
			while ( null !== $job->current_table() ) {
				$this->process_window( $job, $batch, $job->current_table() );
				$this->jobs->save( $job );

				if ( microtime( true ) >= $deadline || $this->memory_is_low() ) {
					return;
				}
			}

			$job->finish( JobStatus::Completed );
			$this->jobs->save( $job );
			$this->logger->info( 'Job completed.', $job->report->totals(), $job->id );
		} catch ( RuntimeException $e ) {
			$job->finish(
				JobStatus::Failed,
				/* translators: 1: table name, 2: database error message. */
				sprintf( __( 'The job stopped while processing %1$s: %2$s', 'designslabz-relocate' ), (string) $job->current_table(), $e->getMessage() )
			);
			$this->jobs->save( $job );
			$this->logger->error(
				'Job failed.',
				array(
					'table' => $job->current_table(),
					'error' => $e->getMessage(),
				),
				$job->id
			);
		}
	}

	private function process_window( Job $job, TableBatch $batch, string $table ): void {
		$layout = $this->layout( $job, $table );

		if ( null === $layout ) {
			$this->next_table( $job );
			return;
		}

		$result = $batch->scan( $layout, $job->state['last_key'] );

		$this->log_skipped( $job, $table, $result );
		$job->report->add( $table, $result );

		if ( null === $result->last_key ) {
			$this->next_table( $job );
		} else {
			$job->state['last_key'] = $result->last_key;
		}
	}

	/**
	 * The table's layout, or null (with a note on the report) when it cannot be processed.
	 */
	private function layout( Job $job, string $table ): ?TableLayout {
		if ( ! array_key_exists( $table, $this->layouts ) ) {
			$layout = $this->schema->describe( $table );

			if ( $layout && $job->settings['skip_guids'] && $this->wpdb->posts === $table ) {
				$layout = $layout->without( 'guid' );
			}

			$this->layouts[ $table ] = $layout;
		}

		$layout = $this->layouts[ $table ];
		$note   = match ( true ) {
			null === $layout    => Report::NOTE_MISSING_TABLE,
			! $layout->key      => Report::NOTE_NO_KEY,
			! $layout->columns  => Report::NOTE_NO_COLUMNS,
			default             => null,
		};

		if ( null !== $note ) {
			$job->report->note( $table, $note );
			return null;
		}

		return $layout;
	}

	private function next_table( Job $job ): void {
		++$job->state['table_index'];
		$job->state['last_key'] = null;
	}

	private function log_skipped( Job $job, string $table, BatchResult $result ): void {
		$already = $job->report->totals()['skipped'];

		foreach ( array_slice( $result->skipped, 0, max( 0, self::MAX_LOGGED_SKIPS - $already ) ) as $skipped ) {
			$this->logger->warning(
				'Value left unchanged.',
				array(
					'table'  => $table,
					'column' => $skipped['column'],
					'key'    => $skipped['key'],
					'reason' => $skipped['reason'],
				),
				$job->id
			);
		}
	}

	private function step_seconds(): float {
		$limit = (int) ini_get( 'max_execution_time' );

		return $limit > 0 ? min( $this->step_seconds, $limit / 2 ) : $this->step_seconds;
	}

	private function memory_is_low(): bool {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );

		return $limit > 0 && memory_get_usage() > $limit * 0.6;
	}

	/**
	 * A named database lock per job. MySQL releases it by itself if the request
	 * dies, so a crashed step never leaves a job stuck.
	 */
	private function lock( int $job_id, int $timeout ): bool {
		return '1' === $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT GET_LOCK(SHA1(CONCAT(DATABASE(), %s)), %d)', $this->lock_name( $job_id ), $timeout ) );
	}

	private function unlock( int $job_id ): void {
		$this->wpdb->query( $this->wpdb->prepare( 'SELECT RELEASE_LOCK(SHA1(CONCAT(DATABASE(), %s)))', $this->lock_name( $job_id ) ) );
	}

	private function lock_name( int $job_id ): string {
		return $this->wpdb->prefix . Installer::JOBS_TABLE . ':' . $job_id;
	}
}
