<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Admin;

use DesignsLabz\Relocate\Jobs\Job;
use DesignsLabz\Relocate\Jobs\JobRepository;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The list of past jobs on the History screen: searchable, sortable, paged,
 * with single and bulk delete.
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
			'cb'           => '<input type="checkbox">',
			'job'          => __( 'Job', 'dl-relocate-db' ),
			'values'       => __( 'Search → Replace', 'dl-relocate-db' ),
			'tables'       => __( 'Tables', 'dl-relocate-db' ),
			'rows_changed' => __( 'Rows changed', 'dl-relocate-db' ),
			'replacements' => __( 'Replacements', 'dl-relocate-db' ),
			'status'       => __( 'Status', 'dl-relocate-db' ),
		);
	}

	public function prepare_items(): void {
		[ $orderby, $order ] = $this->sorting();

		[ $this->items, $total ] = $this->jobs->page( $this->get_pagenum(), self::PER_PAGE, $this->type_filter(), $orderby, $order, $this->search_term() );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'job' );
	}

	public function no_items(): void {
		if ( '' !== $this->search_term() ) {
			esc_html_e( 'No jobs match your search.', 'dl-relocate-db' );
			return;
		}

		esc_html_e( 'No jobs yet. Run a dry run from Search & Replace to get started.', 'dl-relocate-db' );
	}

	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'job'          => array( 'id', true ),
			'rows_changed' => array( 'rows_changed', true ),
			'replacements' => array( 'replacements', true ),
			'status'       => array( 'status', false ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array( 'delete' => __( 'Delete', 'dl-relocate-db' ) );
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$current = $this->type_filter();
		$views   = array(
			'all'  => array( null, __( 'All', 'dl-relocate-db' ) ),
			'dry'  => array( true, __( 'Dry runs', 'dl-relocate-db' ) ),
			'live' => array( false, __( 'Replacements', 'dl-relocate-db' ) ),
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

	/**
	 * @param Job $job Row item.
	 */
	protected function column_cb( $job ): string {
		if ( ! Admin::can_delete( $job ) ) {
			return '';
		}

		return sprintf(
			'<label class="screen-reader-text" for="dlz-job-%1$d">%2$s</label><input type="checkbox" id="dlz-job-%1$d" name="job_ids[]" value="%1$d">',
			(int) $job->id,
			/* translators: %s: job title, e.g. Dry run #12. */
			esc_html( sprintf( __( 'Select %s', 'dl-relocate-db' ), Admin::job_title( $job ) ) )
		);
	}

	protected function column_job( Job $job ): string {
		$actions = array(
			'view' => sprintf( '<a href="%1$s">%2$s</a>', esc_url( Admin::job_url( $job->id ) ), esc_html__( 'View', 'dl-relocate-db' ) ),
		);

		if ( Admin::can_delete( $job ) ) {
			$actions['delete'] = sprintf(
				'<a href="%1$s" class="dlz-delete-job" data-job="%2$s">%3$s</a>',
				esc_url( Admin::delete_url( $job->id ) ),
				esc_attr( Admin::job_title( $job ) ),
				esc_html__( 'Delete', 'dl-relocate-db' )
			);
		}

		return sprintf(
			'<strong><a href="%1$s" class="row-title">%2$s</a></strong><br><span class="description">%3$s</span>%4$s',
			esc_url( Admin::job_url( $job->id ) ),
			esc_html( Admin::job_title( $job ) ),
			esc_html( Admin::format_date( $job->created_at ) ),
			$this->row_actions( $actions )
		);
	}

	protected function column_values( Job $job ): string {
		return sprintf(
			'<span class="dlz-from"><code>%1$s</code></span><span class="dlz-to"><span aria-hidden="true">→</span><span class="screen-reader-text">%2$s</span> <code>%3$s</code></span>',
			esc_html( self::excerpt( $job->search ) ),
			esc_html__( 'replaced with', 'dl-relocate-db' ),
			esc_html( '' === $job->replace ? __( '(nothing)', 'dl-relocate-db' ) : self::excerpt( $job->replace ) )
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

	/**
	 * @return array{0: string, 1: string}
	 */
	private function sorting(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable

		return array( in_array( $orderby, JobRepository::SORTABLE, true ) ? $orderby : 'id', 'asc' === $order ? 'asc' : 'desc' );
	}

	private function search_term(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search.
		return isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';
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
