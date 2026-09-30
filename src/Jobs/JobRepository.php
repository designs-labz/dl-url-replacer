<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Jobs;

use DesignsLabz\Relocate\Installer;
use RuntimeException;

/**
 * Loads and saves jobs in the jobs table.
 */
final class JobRepository {

	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * @throws RuntimeException When the job cannot be stored.
	 */
	public function create( Job $job ): Job {
		$row                 = $this->to_row( $job );
		$row['created_at']   = $job->created_at;
		$row['search']       = $job->search;
		$row['replace_with'] = $job->replace;
		$row['dry_run']      = (int) $job->dry_run;
		$row['parent_id']    = $job->parent_id;
		$row['user_id']      = $job->user_id;

		if ( false === $this->wpdb->insert( $this->table(), $row ) ) {
			throw new RuntimeException( 'Could not create the job: ' . $this->wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where it is displayed.
		}

		$job->id = (int) $this->wpdb->insert_id;

		return $job;
	}

	public function find( int $id ): ?Job {
		$row = $this->wpdb->get_row( $this->wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $id ) );

		return $row ? $this->from_row( $row ) : null;
	}

	/**
	 * Whether a live job was already started from this dry run.
	 */
	public function has_child( int $parent_id ): bool {
		return null !== $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT id FROM %i WHERE parent_id = %d LIMIT 1', $this->table(), $parent_id ) );
	}

	/**
	 * A live job that has not finished yet. Failed jobs do not count: they may
	 * be resumed, but they should not block every future replacement.
	 */
	public function active_live_job(): ?Job {
		$id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT id FROM %i WHERE dry_run = 0 AND status IN (%s, %s) ORDER BY id DESC LIMIT 1',
				$this->table(),
				JobStatus::Pending->value,
				JobStatus::Running->value
			)
		);

		return null === $id ? null : $this->find( (int) $id );
	}

	/**
	 * Saves the parts of a job that change while it runs.
	 *
	 * @throws RuntimeException When the job cannot be saved.
	 */
	public function save( Job $job ): void {
		if ( false === $this->wpdb->update( $this->table(), $this->to_row( $job ), array( 'id' => $job->id ) ) ) {
			throw new RuntimeException( 'Could not save the job: ' . $this->wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where it is displayed.
		}
	}

	/**
	 * @return array<string, string|int|null>
	 */
	private function to_row( Job $job ): array {
		$totals = $job->report->totals();

		return array(
			'status'        => $job->status->value,
			'settings'      => $this->encode( $job->settings ),
			'state'         => $this->encode( $job->state ),
			'report'        => $this->encode( $job->report->to_array() ),
			'rows_scanned'  => $totals['rows_scanned'],
			'rows_changed'  => $totals['rows_changed'],
			'replacements'  => $totals['replacements'],
			'error_count'   => $totals['skipped'],
			'error_message' => $job->error_message,
			'started_at'    => $job->started_at,
			'finished_at'   => $job->finished_at,
			'before_image'  => $job->before_image,
			'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	private function from_row( object $row ): Job {
		return new Job(
			(int) $row->id,
			null === $row->parent_id ? null : (int) $row->parent_id,
			(bool) $row->dry_run,
			JobStatus::tryFrom( (string) $row->status ) ?? JobStatus::Failed,
			(string) $row->search,
			(string) $row->replace_with,
			$this->decode( $row->settings ),
			$this->decode( $row->state ),
			Report::from_array( $this->decode( $row->report ) ),
			(int) $row->user_id,
			(string) $row->created_at,
			$row->started_at,
			$row->finished_at,
			$row->error_message,
			(string) $row->before_image
		);
	}

	/**
	 * @param array<mixed> $data
	 */
	private function encode( array $data ): string {
		// Sample snippets come straight from the database and may not be valid UTF-8.
		return (string) wp_json_encode( $data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * @return array<mixed>
	 */
	private function decode( ?string $json ): array {
		$data = json_decode( (string) $json, true );

		return is_array( $data ) ? $data : array();
	}

	private function table(): string {
		return $this->wpdb->prefix . Installer::JOBS_TABLE;
	}
}
