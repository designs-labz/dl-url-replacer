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
	 * @param array<string, mixed> $context Extra details, stored as JSON.
	 */
	private function log( string $level, string $message, array $context, ?int $job_id ): void {
		$context = $context ? (string) wp_json_encode( $context, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES ) : null;

		$inserted = $this->wpdb->insert(
			$this->wpdb->prefix . Installer::LOG_TABLE,
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
