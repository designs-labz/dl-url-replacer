<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate;

/**
 * Writes diagnostic entries to the plugin's log table.
 *
 * Messages are for developers and stay in English. Never pass database cell
 * contents in the context: identify rows by table, column and key instead.
 */
final class Logger {

	public const ERROR   = 'error';
	public const WARNING = 'warning';
	public const INFO    = 'info';

	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * @param array<string, mixed> $context Extra details, stored as JSON.
	 */
	public function error( string $message, array $context = array(), ?int $job_id = null ): void {
		$this->log( self::ERROR, $message, $context, $job_id );
	}

	/**
	 * @param array<string, mixed> $context Extra details, stored as JSON.
	 */
	public function warning( string $message, array $context = array(), ?int $job_id = null ): void {
		$this->log( self::WARNING, $message, $context, $job_id );
	}

	/**
	 * @param array<string, mixed> $context Extra details, stored as JSON.
	 */
	public function info( string $message, array $context = array(), ?int $job_id = null ): void {
		$this->log( self::INFO, $message, $context, $job_id );
	}

	/**
	 * Newest first.
	 *
	 * @return array{0: list<object>, 1: int} The page of entries and the total number of matching entries.
	 */
	public function entries( int $page, int $per_page, ?int $job_id = null, ?string $level = null ): array {
		$conditions = array();
		$args       = array( $this->table() );

		if ( null !== $job_id ) {
			$conditions[] = 'job_id = %d';
			$args[]       = $job_id;
		}

		if ( null !== $level ) {
			$conditions[] = 'level = %s';
			$args[]       = $level;
		}

		$where = $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '';

		$entries = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders and values are built together.
			$this->wpdb->prepare(
				'SELECT * FROM %i' . $where . ' ORDER BY id DESC LIMIT %d OFFSET %d',
				array_merge( $args, array( $per_page, max( 0, $page - 1 ) * $per_page ) )
			)
		);
		$total = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM %i' . $where, $args ) );

		return array( $entries, $total );
	}

	/**
	 * @param string $cutoff UTC datetime.
	 */
	public function delete_before( string $cutoff ): void {
		$this->wpdb->query( $this->wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $this->table(), $cutoff ) );
	}

	private function table(): string {
		return $this->wpdb->prefix . Installer::LOG_TABLE;
	}

	/**
	 * @param array<string, mixed> $context Extra details, stored as JSON.
	 */
	private function log( string $level, string $message, array $context, ?int $job_id ): void {
		$context = $context ? (string) wp_json_encode( $context, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES ) : null;

		$inserted = $this->wpdb->insert(
			$this->table(),
			array(
				'job_id'     => $job_id,
				'level'      => $level,
				'message'    => $message,
				'context'    => $context,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		// If the log table itself is unavailable, the PHP error log is the only place left.
		if ( false === $inserted || ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'DesignsLabz Relocate [%s]%s %s %s', $level, $job_id ? " job {$job_id}:" : '', $message, (string) $context ) );
		}
	}
}
