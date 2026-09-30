<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Admin;

use DesignsLabz\Relocate\Logger;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The plugin's log on the History tab.
 */
final class LogTable extends \WP_List_Table {

	private const PER_PAGE = 50;

	public function __construct( private Logger $logger ) {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'created_at' => __( 'Time', 'designslabz-relocate' ),
			'level'      => __( 'Level', 'designslabz-relocate' ),
			'job_id'     => __( 'Job', 'designslabz-relocate' ),
			'message'    => __( 'Message', 'designslabz-relocate' ),
		);
	}

	public function prepare_items(): void {
		[ $this->items, $total ] = $this->logger->entries( $this->get_pagenum(), self::PER_PAGE, null, $this->level_filter() );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array(), 'message' );
	}

	public function no_items(): void {
		esc_html_e( 'The log is empty.', 'designslabz-relocate' );
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$current = $this->level_filter();
		$links   = array();

		foreach ( array( '' => __( 'All', 'designslabz-relocate' ) ) + self::levels() as $level => $label ) {
			$args = array( 'view' => 'log' ) + ( '' === $level ? array() : array( 'level' => $level ) );

			$links[ '' === $level ? 'all' : $level ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( Admin::url( 'history', $args ) ),
				( '' === $level ? null : $level ) === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}

		return $links;
	}

	/**
	 * @param object $entry Log row.
	 */
	protected function column_created_at( object $entry ): string {
		return esc_html( Admin::format_date( $entry->created_at ) );
	}

	/**
	 * @param object $entry Log row.
	 */
	protected function column_level( object $entry ): string {
		return self::level_badge( (string) $entry->level );
	}

	/**
	 * @param object $entry Log row.
	 */
	protected function column_job_id( object $entry ): string {
		return $entry->job_id
			? sprintf( '<a href="%1$s">#%2$d</a>', esc_url( Admin::job_url( (int) $entry->job_id ) ), (int) $entry->job_id )
			: '—';
	}

	/**
	 * @param object $entry Log row.
	 */
	protected function column_message( object $entry ): string {
		return self::message( $entry );
	}

	/**
	 * Message plus its context, collapsed. Shared with the job page.
	 *
	 * @param object $entry Log row.
	 * @return string Escaped HTML.
	 */
	public static function message( object $entry ): string {
		$html    = esc_html( (string) $entry->message );
		$context = json_decode( (string) $entry->context, true );

		if ( is_array( $context ) && $context ) {
			$html .= sprintf(
				'<details class="dlz-log-context"><summary>%1$s</summary><pre>%2$s</pre></details>',
				esc_html__( 'Details', 'designslabz-relocate' ),
				esc_html( (string) wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
			);
		}

		return $html;
	}

	/**
	 * @return string Escaped HTML.
	 */
	public static function level_badge( string $level ): string {
		[ $icon, $label ] = match ( $level ) {
			Logger::ERROR   => array( 'warning', __( 'Error', 'designslabz-relocate' ) ),
			Logger::WARNING => array( 'flag', __( 'Warning', 'designslabz-relocate' ) ),
			default         => array( 'info-outline', __( 'Info', 'designslabz-relocate' ) ),
		};

		return sprintf(
			'<span class="dlz-badge dlz-badge-%1$s"><span class="dashicons dashicons-%2$s" aria-hidden="true"></span> %3$s</span>',
			esc_attr( $level ),
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function levels(): array {
		return array(
			Logger::ERROR   => __( 'Errors', 'designslabz-relocate' ),
			Logger::WARNING => __( 'Warnings', 'designslabz-relocate' ),
			Logger::INFO    => __( 'Information', 'designslabz-relocate' ),
		);
	}

	private function level_filter(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';

		return isset( self::levels()[ $level ] ) ? $level : null;
	}
}
