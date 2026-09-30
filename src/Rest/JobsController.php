<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Rest;

use CraftRoq\Relocate\Jobs\Job;
use CraftRoq\Relocate\Jobs\JobRepository;
use CraftRoq\Relocate\Jobs\JobException;
use CraftRoq\Relocate\Jobs\JobRunner;
use CraftRoq\Relocate\Jobs\JobStarter;
use CraftRoq\Relocate\Logger;
use CraftRoq\Relocate\Plugin;
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

	public const NAMESPACE = 'crq-relocate/v1';

	public function __construct(
		private JobRepository $jobs,
		private JobRunner $runner,
		private JobStarter $starter,
		private JobFormatter $formatter,
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
					// Either a list of pairs, or a single search and replace.
					'pairs'           => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'search'  => array(
									'type'     => 'string',
									'required' => true,
								),
								'replace' => array(
									'type'     => 'string',
									'required' => true,
								),
							),
							'additionalProperties' => false,
						),
					),
					'search'          => array( 'type' => 'string' ),
					'replace'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'case_sensitive'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'whole_words'     => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'url_variants'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'skip_guids'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'tables'          => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'required'    => true,
						'uniqueItems' => true,
					),
					'exclude_columns' => array(
						'description'          => 'Columns not to search, by table.',
						'type'                 => 'object',
						'default'              => array(),
						'additionalProperties' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
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
			'/jobs/(?P<id>\d+)/execute',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'execute_job' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					// Explicit, so a stray request cannot start a destructive job.
					'confirmed'    => array(
						'type'     => 'boolean',
						'required' => true,
					),
					'before_image' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/resume',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resume_job' ),
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
		return $this->started(
			fn(): Job => $this->starter->dry_run(
				$this->pairs( $request ),
				array(
					'case_sensitive' => (bool) $request['case_sensitive'],
					'whole_words'    => (bool) $request['whole_words'],
					'url_variants'   => (bool) $request['url_variants'],
					'skip_guids'     => (bool) $request['skip_guids'],
				),
				array_values( array_map( 'strval', (array) $request['tables'] ) ),
				(array) $request['exclude_columns']
			)
		);
	}

	public function get_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = $this->jobs->find( (int) $request['id'] );

		return $job ? rest_ensure_response( $this->formatter->format( $job ) ) : $this->not_found();
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
			return $this->error( 'crq_relocate_database_error', __( 'The job’s progress could not be saved to the database.', 'cr-relocate-db' ), 500 );
		}

		if ( ! $job ) {
			return $this->error( 'crq_relocate_job_busy', __( 'This job is already being processed in another browser tab.', 'cr-relocate-db' ), 409 );
		}

		return rest_ensure_response( $this->formatter->format( $job ) );
	}

	/**
	 * Starts the live replacement a completed dry run previewed.
	 */
	public function execute_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$dry_run = $this->jobs->find( (int) $request['id'] );

		if ( ! $dry_run ) {
			return $this->not_found();
		}

		if ( true !== $request['confirmed'] ) {
			return $this->error( 'crq_relocate_not_confirmed', __( 'Confirm the replacement before starting it.', 'cr-relocate-db' ) );
		}

		return $this->started( fn(): Job => $this->starter->replacement( $dry_run, (bool) $request['before_image'] ) );
	}

	public function resume_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = $this->jobs->find( (int) $request['id'] );

		if ( ! $job ) {
			return $this->not_found();
		}

		try {
			$job = $this->runner->resume( $job );
		} catch ( RuntimeException $e ) {
			$this->logger->error( 'Could not resume job.', array( 'error' => $e->getMessage() ), (int) $request['id'] );
			return $this->error( 'crq_relocate_database_error', __( 'The job could not be resumed.', 'cr-relocate-db' ), 500 );
		}

		if ( ! $job ) {
			return $this->error( 'crq_relocate_job_busy', __( 'This job is already being processed in another browser tab.', 'cr-relocate-db' ), 409 );
		}

		return rest_ensure_response( $this->formatter->format( $job ) );
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
			return $this->error( 'crq_relocate_database_error', __( 'The job could not be cancelled.', 'cr-relocate-db' ), 500 );
		}

		if ( ! $job ) {
			return $this->error( 'crq_relocate_job_busy', __( 'The job is busy. Try cancelling again in a few seconds.', 'cr-relocate-db' ), 409 );
		}

		return rest_ensure_response( $this->formatter->format( $job ) );
	}

	/**
	 * @return list<array{0: string, 1: string}>
	 */
	private function pairs( WP_REST_Request $request ): array {
		if ( is_array( $request['pairs'] ) && $request['pairs'] ) {
			return array_values(
				array_map(
					fn( array $pair ): array => array( (string) $pair['search'], (string) $pair['replace'] ),
					$request['pairs']
				)
			);
		}

		return array( array( (string) $request['search'], (string) $request['replace'] ) );
	}

	/**
	 * @param callable(): Job $start
	 */
	private function started( callable $start ): WP_REST_Response|WP_Error {
		try {
			return new WP_REST_Response( $this->formatter->format( $start() ), 201 );
		} catch ( JobException $e ) {
			return $this->error( $e->error_code, $e->getMessage(), $e->status );
		}
	}

	private function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private function not_found(): WP_Error {
		return $this->error( 'crq_relocate_job_not_found', __( 'That job does not exist.', 'cr-relocate-db' ), 404 );
	}
}
