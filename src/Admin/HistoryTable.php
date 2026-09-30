<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Admin;

use DesignsLabz\Relocate\Jobs\Job;
use DesignsLabz\Relocate\Jobs\JobRepository;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The list of past jobs on the History tab.
 */
final class HistoryTable extends \WP_List_Table {

	private const PER_PAGE = 20;

	public function __construct( private JobRepository $jobs ) {
		parent::__construct(
			array(
				'singular' => 'job',
				'plural'   => 'jobs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'job'          => __( 'Job', 'designslabz-relocate' ),
			'values'       => __( 'Search → Replace', 'designslabz-relocate' ),
			'tables'       => __( 'Tables', 'designslabz-relocate' ),
			'rows_changed' => __( 'Rows changed', 'designslabz-relocate' ),
			'replacements' => __( 'Replacements', 'designslabz-relocate' ),
			'status'       => __( 'Status', 'designslabz-relocate' ),
		);
	}

	public function prepare_items(): void {
		[ $this->items, $total ] = $this->jobs->page( $this->get_pagenum(), self::PER_PAGE, $this->type_filter() );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array(), 'job' );
	}

	public function no_items(): void {
		esc_html_e( 'No jobs yet. Run a dry run from the Search & Replace tab to get started.', 'designslabz-relocate' );
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$current = $this->type_filter();
		$views   = array(
			'all'  => array( null, __( 'All', 'designslabz-relocate' ) ),
			'dry'  => array( true, __( 'Dry runs', 'designslabz-relocate' ) ),
			'live' => array( false, __( 'Replacements', 'designslabz-relocate' ) ),
		);

		$links = array();
		foreach ( $views as $key => [ $dry_run, $label ] ) {
			$links[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( Admin::url( 'history', 'all' === $key ? array() : array( 'type' => $key ) ) ),
				$current === $dry_run ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}

		return $links;
	}

	protected function column_job( Job $job ): string {
		return sprintf(
			'<strong><a href="%1$s">%2$s</a></strong><br>%3$s',
			esc_url( Admin::job_url( $job->id ) ),
			esc_html( Admin::job_title( $job ) ),
			esc_html( Admin::format_date( $job->created_at ) )
		);
	}

	protected function column_values( Job $job ): string {
		return sprintf(
			'<code>%1$s</code> <span aria-hidden="true">→</span><span class="screen-reader-text">%2$s</span> <code>%3$s</code>',
			esc_html( self::excerpt( $job->search ) ),
			esc_html__( 'replaced with', 'designslabz-relocate' ),
			esc_html( '' === $job->replace ? __( '(nothing)', 'designslabz-relocate' ) : self::excerpt( $job->replace ) )
		);
	}

	protected function column_tables( Job $job ): string {
		return esc_html( number_format_i18n( count( $job->settings['tables'] ) ) );
	}

	protected function column_rows_changed( Job $job ): string {
		return esc_html( number_format_i18n( $job->report->totals()['rows_changed'] ) );
	}

	protected function column_replacements( Job $job ): string {
		return esc_html( number_format_i18n( $job->report->totals()['replacements'] ) );
	}

	protected function column_status( Job $job ): string {
		return Admin::status_badge( $job );
	}

	private function type_filter(): ?bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';

		return match ( $type ) {
			'dry'   => true,
			'live'  => false,
			default => null,
		};
	}

	private static function excerpt( string $text ): string {
		return mb_strlen( $text ) > 60 ? mb_substr( $text, 0, 59 ) . '…' : $text;
	}
}
