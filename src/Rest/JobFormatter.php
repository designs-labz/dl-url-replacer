<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Rest;

use DesignsLabz\Relocate\Admin\Admin;
use DesignsLabz\Relocate\Database\Schema;
use DesignsLabz\Relocate\Jobs\BeforeImage;
use DesignsLabz\Relocate\Jobs\Job;
use DesignsLabz\Relocate\Jobs\JobRepository;
use DesignsLabz\Relocate\Jobs\JobStatus;
use DesignsLabz\Relocate\Jobs\Report;
use DesignsLabz\Relocate\Replace\ReplaceResult;
use RuntimeException;

/**
 * Turns a job into the data the admin screens render, for REST responses and
 * for the job page, which embeds the same data so one renderer serves both.
 */
final class JobFormatter {

	public function __construct(
		private \wpdb $wpdb,
		private JobRepository $jobs,
		private Schema $schema,
		private BeforeImage $before_images
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function format( Job $job ): array {
		$data = array(
			'id'            => $job->id,
			'parent_id'     => $job->parent_id,
			'dry_run'       => $job->dry_run,
			'search'        => $job->search,
			'replace'       => $job->replace,
			'tables'        => $job->settings['tables'],
			'status'        => $job->status->value,
			'status_label'  => $job->status->label(),
			'finished'      => $job->status->is_finished(),
			'interrupted'   => $job->is_interrupted(),
			'progress'      => $job->progress(),
			'current_table' => $job->current_table(),
			'tables_done'   => min( $job->state['table_index'], count( $job->settings['tables'] ) ),
			'tables_total'  => count( $job->settings['tables'] ),
			'totals'        => $job->report->totals(),
			'error'         => $job->error_message,
		);

		if ( $job->status->is_finished() ) {
			$data['report'] = array(
				'tables'  => $this->tables( $job->report ),
				'samples' => $job->report->samples(),
			);
		}

		if ( $job->dry_run ) {
			$data['executable'] = JobStatus::Completed === $job->status
				&& $data['totals']['rows_changed'] > 0
				&& null === $this->jobs->child_id( $job->id );

			// Only needed for the confirmation dialog.
			if ( $data['executable'] ) {
				$data['untransactional_tables'] = $this->untransactional( $job->settings['tables'] );
				$data['touches_site_address']   = in_array( $this->wpdb->options, $job->settings['tables'], true );
			}
		} else {
			$data['before_image_url']     = $this->before_images->path( $job->before_image ) ? Admin::before_image_url( $job->id ) : null;
			$data['site_address_changed'] = ! empty( $job->state['site_address_changed'] );
			$data['login_url']            = $data['site_address_changed'] ? wp_login_url( Admin::url() ) : null;
		}

		return $data;
	}

	public static function note_label( string $note ): string {
		return match ( $note ) {
			Report::NOTE_MISSING_TABLE => __( 'Skipped: the table no longer exists.', 'designslabz-relocate' ),
			Report::NOTE_NO_KEY        => __( 'Skipped: the table has no primary key or suitable unique key, so its rows cannot be updated one at a time safely.', 'designslabz-relocate' ),
			Report::NOTE_NO_COLUMNS    => __( 'Skipped: the table has no text columns to search.', 'designslabz-relocate' ),
			default                    => $note,
		};
	}

	public static function skip_label( string $reason ): string {
		return match ( $reason ) {
			ReplaceResult::INVALID_SERIALIZED     => __( 'Serialized data that is already corrupt, or would not read back correctly after the change', 'designslabz-relocate' ),
			ReplaceResult::UNSUPPORTED_SERIALIZED => __( 'Match inside a custom serialized object format that cannot be edited safely', 'designslabz-relocate' ),
			ReplaceResult::BROKEN_JSON            => __( 'The change would turn valid JSON into invalid JSON', 'designslabz-relocate' ),
			default                               => __( 'The value could not be searched', 'designslabz-relocate' ),
		};
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function tables( Report $report ): array {
		$tables = array();

		foreach ( $report->tables() as $name => $stats ) {
			$skipped = array();
			foreach ( $stats['skipped'] as $reason => $count ) {
				$skipped[] = array(
					'reason' => self::skip_label( $reason ),
					'count'  => $count,
				);
			}

			$tables[] = array(
				'name'         => $name,
				'rows_scanned' => $stats['rows_scanned'],
				'rows_changed' => $stats['rows_changed'],
				'replacements' => $stats['replacements'],
				'columns'      => $stats['columns'],
				'skipped'      => $skipped,
				'note'         => null === $stats['note'] ? null : self::note_label( $stats['note'] ),
			);
		}

		return $tables;
	}

	/**
	 * Selected tables whose storage engine cannot roll a batch back.
	 *
	 * @param list<string> $tables
	 * @return list<string>
	 */
	private function untransactional( array $tables ): array {
		try {
			$available = $this->schema->searchable_tables();
		} catch ( RuntimeException ) {
			return array();
		}

		return array_values(
			array_filter(
				$tables,
				fn( string $name ): bool => isset( $available[ $name ] ) && 0 !== strcasecmp( $available[ $name ]->engine, 'InnoDB' )
			)
		);
	}
}
