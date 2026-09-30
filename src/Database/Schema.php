<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Database;

use RuntimeException;

/**
 * Reads table information for the current database.
 */
final class Schema {

	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * @return Table[]
	 * @throws RuntimeException When the table list cannot be read.
	 */
	public function tables(): array {
		$rows = $this->wpdb->get_results(
			"SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS approx_rows,
				DATA_LENGTH AS data_size, INDEX_LENGTH AS index_size, TABLE_COLLATION AS collation
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
			ORDER BY TABLE_NAME"
		);

		if ( '' !== $this->wpdb->last_error ) {
			/* translators: %s: database error message. */
			$message = sprintf( __( 'Could not read the table list from the database: %s', 'designslabz-relocate' ), $this->wpdb->last_error );

			throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where it is displayed.
		}

		return array_map(
			fn( object $row ): Table => new Table(
				(string) $row->name,
				(string) $row->engine,
				(int) $row->approx_rows,
				(int) $row->data_size,
				(int) $row->index_size,
				(string) $row->collation,
				str_starts_with( (string) $row->name, $this->wpdb->prefix )
			),
			$rows
		);
	}

	/**
	 * @return array{version: string, name: string, charset: string, collate: string, prefix: string}
	 */
	public function server_info(): array {
		return array(
			'version' => (string) $this->wpdb->get_var( 'SELECT VERSION()' ),
			'name'    => (string) $this->wpdb->get_var( 'SELECT DATABASE()' ),
			'charset' => (string) $this->wpdb->charset,
			'collate' => (string) $this->wpdb->collate,
			'prefix'  => $this->wpdb->prefix,
		);
	}
}
