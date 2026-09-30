<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Admin;

use DesignsLabz\Relocate\Database\Schema;
use DesignsLabz\Relocate\Jobs\BeforeImage;
use DesignsLabz\Relocate\Jobs\JobRepository;
use DesignsLabz\Relocate\Plugin;
use RuntimeException;

/**
 * The Tools → DesignsLabz Relocate screen.
 */
final class Admin {

	public const PAGE = 'designslabz-relocate';

	private const DOWNLOAD_ACTION = 'dlz_relocate_before_image';

	private string $hook_suffix = '';

	public function __construct(
		private string $file,
		private Schema $schema,
		private JobRepository $jobs,
		private BeforeImage $before_images
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'download_before_image' ) );
	}

	/**
	 * Raw URL, not HTML-escaped (wp_nonce_url() would add &amp;): it is sent as JSON.
	 */
	public static function before_image_url( int $job_id ): string {
		return add_query_arg(
			array(
				'action'   => self::DOWNLOAD_ACTION,
				'job'      => $job_id,
				'_wpnonce' => wp_create_nonce( self::DOWNLOAD_ACTION . '_' . $job_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Streams a job's before-image file. The files are never linked directly.
	 */
	public function download_before_image(): void {
		$job_id = isset( $_GET['job'] ) ? absint( $_GET['job'] ) : 0;

		check_admin_referer( self::DOWNLOAD_ACTION . '_' . $job_id );

		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to download this file.', 'designslabz-relocate' ), '', array( 'response' => 403 ) );
		}

		$job  = $this->jobs->find( $job_id );
		$path = $job ? $this->before_images->path( $job->before_image ) : null;

		if ( ! $path ) {
			wp_die( esc_html__( 'That file no longer exists.', 'designslabz-relocate' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/gzip' );
		header( 'Content-Disposition: attachment; filename="relocate-job-' . $job_id . '-original-values.sql.gz"' );
		header( 'Content-Length: ' . filesize( $path ) );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a large file without loading it into memory.
		exit;
	}

	public static function url( string $tab = '' ): string {
		$args = array(
			'page' => self::PAGE,
			'tab'  => $tab,
		);

		return add_query_arg( array_filter( $args ), admin_url( 'tools.php' ) );
	}

	public function add_page(): void {
		$this->hook_suffix = (string) add_management_page(
			__( 'DesignsLabz Relocate', 'designslabz-relocate' ),
			__( 'DesignsLabz Relocate', 'designslabz-relocate' ),
			Plugin::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'dlz-relocate-admin', plugins_url( 'assets/css/admin.css', $this->file ), array( 'dashicons' ), Plugin::VERSION );

		if ( 'search-replace' === $this->current_tab( $this->tabs() ) ) {
			wp_enqueue_script(
				'dlz-relocate-search-replace',
				plugins_url( 'assets/js/search-replace.js', $this->file ),
				array( 'wp-api-fetch', 'wp-i18n', 'wp-a11y' ),
				Plugin::VERSION,
				array( 'in_footer' => true )
			);
			wp_set_script_translations( 'dlz-relocate-search-replace', 'designslabz-relocate', dirname( $this->file ) . '/languages' );
		}
	}

	public function render_page(): void {
		$tabs    = $this->tabs();
		$current = $this->current_tab( $tabs );

		$this->template(
			'page',
			array(
				'tabs'    => $tabs,
				'current' => $current,
			) + $this->tab_args( $current )
		);
	}

	/**
	 * @return array<string, string> Tab slug => label.
	 */
	private function tabs(): array {
		return array(
			'search-replace' => __( 'Search & Replace', 'designslabz-relocate' ),
			'database'       => __( 'Database', 'designslabz-relocate' ),
			'settings'       => __( 'Settings', 'designslabz-relocate' ),
		);
	}

	/**
	 * @param array<string, string> $tabs Available tabs.
	 */
	private function current_tab( array $tabs ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function tab_args( string $tab ): array {
		try {
			return match ( $tab ) {
				'search-replace' => array(
					'tables' => $this->schema->searchable_tables(),
					'prefix' => $this->schema->server_info()['prefix'],
				),
				'database'       => array(
					'tables' => $this->schema->tables(),
					'server' => $this->schema->server_info(),
				),
				default          => array(),
			};
		} catch ( RuntimeException $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * @param array<string, mixed> $args Variables for the template, available as $args.
	 */
	private function template( string $name, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Read by the template.
		require dirname( $this->file ) . '/templates/admin/' . $name . '.php';
	}
}
