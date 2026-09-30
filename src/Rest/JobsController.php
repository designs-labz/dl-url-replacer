<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Rest;

use DesignsLabz\Relocate\Database\Schema;
use DesignsLabz\Relocate\Jobs\Job;
use DesignsLabz\Relocate\Jobs\JobRepository;
use DesignsLabz\Relocate\Jobs\JobRunner;
use DesignsLabz\Relocate\Jobs\JobStatus;
use DesignsLabz\Relocate\Jobs\Report;
use DesignsLabz\Relocate\Logger;
use DesignsLabz\Relocate\Plugin;
use DesignsLabz\Relocate\Replace\Replacement;
use DesignsLabz\Relocate\Replace\ReplaceResult;
use InvalidArgumentException;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes the admin screen uses to create and drive jobs.
 *
 * Cookie-authenticated requests must carry the wp_rest nonce (wp.apiFetch
 * adds it), which is what protects these routes against CSRF.
 */
final class JobsController {

	public const NAMESPACE = 'dlz-relocate/v1';

	public function __construct(
		private JobRepository $jobs,
		private JobRunner $runner,
		private Schema $schema,
		private Logger $logger
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/jobs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_job' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'search'         => array(
						'type'     => 'string',
						'required' => true,
					),
					'replace'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'case_sensitive' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'whole_words'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'url_variants'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'skip_guids'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'tables'         => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'required'    => true,
						'uniqueItems' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_job' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_job' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_job' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( Plugin::CAPABILITY );
	}

	public function create_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$search  = (string) $request['search'];
		$replace = (string) $request['replace'];

		if ( '' === $search ) {
			return $this->error( 'dlz_relocate_empty_search', __( 'Enter the text or URL to search for.', 'designslabz-relocate' ) );
		}

		if ( $search === $replace ) {
			return $this->error( 'dlz_relocate_same_values', __( 'The search and replacement values are the same, so there is nothing to change.', 'designslabz-relocate' ) );
		}

		$settings = array(
			'case_sensitive' => (bool) $request['case_sensitive'],
			'whole_words'    => (bool) $request['whole_words'],
			'url_variants'   => (bool) $request['url_variants'],
			'skip_guids'     => (bool) $request['skip_guids'],
			'tables'         => array(),
		);

		try {
			new Replacement( $search, $replace, $settings['case_sensitive'], $settings['whole_words'], $settings['url_variants'] );
		} catch ( InvalidArgumentException ) {
			return $this->error( 'dlz_relocate_invalid_values', __( 'The search and replacement values must be valid UTF-8 text.', 'designslabz-relocate' ) );
		}

		try {
			$available = $this->schema->searchable_tables();
		} catch ( RuntimeException $e ) {
			return $this->error( 'dlz_relocate_database_error', $e->getMessage(), 500 );
		}

		$requested = array_map( 'strval', (array) $request['tables'] );
		$unknown   = array_diff( $requested, array_keys( $available ) );

		if ( ! $requested ) {
			return $this->error( 'dlz_relocate_no_tables', __( 'Select at least one table to search.', 'designslabz-relocate' ) );
		}

		if ( $unknown ) {
			return $this->error(
				'dlz_relocate_invalid_tables',
				/* translators: %s: comma-separated table names. */
				sprintf( __( 'These tables do not exist or cannot be searched: %s', 'designslabz-relocate' ), implode( ', ', $unknown ) )
			);
		}

		// Keep the database's own order, so jobs always walk tables the same way.
		$settings['tables'] = array_values( array_intersect( array_keys( $available ), $requested ) );

		$job = new Job(
			0,
			null,
			true,
			JobStatus::Pending,
			$search,
			$replace,
			$settings,
			array(
				'table_index' => 0,
				'last_key'    => null,
				'total_rows'  => array_sum( array_map( fn( string $name ): int => $available[ $name ]->approx_rows, $settings['tables'] ) ),
			),
			new Report(),
			get_current_user_id(),
			gmdate( 'Y-m-d H:i:s' )
		);

		try {
			$this->jobs->create( $job );
		} catch ( RuntimeException $e ) {
			$this->logger->error( 'Could not create a job.', array( 'error' => $e->getMessage() ) );
			return $this->error( 'dlz_relocate_database_error', __( 'The job could not be saved to the database.', 'designslabz-relocate' ), 500 );
		}

		$this->logger->info( 'Dry run created.', array( 'tables' => count( $settings['tables'] ) ), $job->id );

		return new WP_REST_Response( $this->prepare( $job ), 201 );
	}

	public function get_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = $this->jobs->find( (int) $request['id'] );

		return $job ? rest_ensure_response( $this->prepare( $job ) ) : $this->not_found();
	}

	public function run_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = $this->jobs->find( (int) $request['id'] );

		if ( ! $job ) {
			return $this->not_found();
		}

		try {
			$job = $this->runner->step( $job );
		} catch ( RuntimeException $e ) {
			$this->logger->error( 'Could not save job progress.', array( 'error' => $e->getMessage() ), (int) $request['id'] );
			return $this->error( 'dlz_relocate_database_error', __( 'The job’s progress could not be saved to the database.', 'designslabz-relocate' ), 500 );
		}

		if ( ! $job ) {
			return $this->error( 'dlz_relocate_job_busy', __( 'This job is already being processed in another browser tab.', 'designslabz-relocate' ), 409 );
		}

		return rest_ensure_response( $this->prepare( $job ) );
	}

	public function cancel_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = $this->jobs->find( (int) $request['id'] );

		if ( ! $job ) {
			return $this->not_found();
		}

		try {
			$job = $this->runner->cancel( $job );
		} catch ( RuntimeException $e ) {
			$this->logger->error( 'Could not cancel job.', array( 'error' => $e->getMessage() ), (int) $request['id'] );
			return $this->error( 'dlz_relocate_database_error', __( 'The job could not be cancelled.', 'designslabz-relocate' ), 500 );
		}

		if ( ! $job ) {
			return $this->error( 'dlz_relocate_job_busy', __( 'The job is busy. Try cancelling again in a few seconds.', 'designslabz-relocate' ), 409 );
		}

		return rest_ensure_response( $this->prepare( $job ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function prepare( Job $job ): array {
		$data = array(
			'id'            => $job->id,
			'dry_run'       => $job->dry_run,
			'status'        => $job->status->value,
			'status_label'  => $job->status->label(),
			'finished'      => $job->status->is_finished(),
			'progress'      => $job->progress(),
			'current_table' => $job->current_table(),
			'tables_done'   => min( $job->state['table_index'], count( $job->settings['tables'] ) ),
			'tables_total'  => count( $job->settings['tables'] ),
			'totals'        => $job->report->totals(),
			'error'         => $job->error_message,
		);

		if ( $job->status->is_finished() ) {
			$data['report'] = array(
				'tables'  => $this->prepare_tables( $job->report ),
				'samples' => $job->report->samples(),
			);
		}

		return $data;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function prepare_tables( Report $report ): array {
		$tables = array();

		foreach ( $report->tables() as $name => $stats ) {
			$skipped = array();
			foreach ( $stats['skipped'] as $reason => $count ) {
				$skipped[] = array(
					'reason' => $this->skip_label( $reason ),
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
				'note'         => null === $stats['note'] ? null : $this->note_label( $stats['note'] ),
			);
		}

		return $tables;
	}

	private function note_label( string $note ): string {
		return match ( $note ) {
			Report::NOTE_MISSING_TABLE => __( 'Skipped: the table no longer exists.', 'designslabz-relocate' ),
			Report::NOTE_NO_KEY        => __( 'Skipped: the table has no primary key or suitable unique key, so its rows cannot be updated one at a time safely.', 'designslabz-relocate' ),
			Report::NOTE_NO_COLUMNS    => __( 'Skipped: the table has no text columns to search.', 'designslabz-relocate' ),
			default                    => $note,
		};
	}

	private function skip_label( string $reason ): string {
		return match ( $reason ) {
			ReplaceResult::INVALID_SERIALIZED     => __( 'Serialized data that is already corrupt, or would not read back correctly after the change', 'designslabz-relocate' ),
			ReplaceResult::UNSUPPORTED_SERIALIZED => __( 'Match inside a custom serialized object format that cannot be edited safely', 'designslabz-relocate' ),
			ReplaceResult::BROKEN_JSON            => __( 'The change would turn valid JSON into invalid JSON', 'designslabz-relocate' ),
			default                               => __( 'The value could not be searched', 'designslabz-relocate' ),
		};
	}

	private function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function not_found(): WP_Error {
		return $this->error( 'dlz_relocate_job_not_found', __( 'That job does not exist.', 'designslabz-relocate' ), 404 );
	}
}
