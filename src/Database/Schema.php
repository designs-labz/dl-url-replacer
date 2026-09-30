<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Database;

use DesignsLabz\Relocate\Installer;
use RuntimeException;

/**
 * Reads table information for the current database.
 */
final class Schema {

	private const TEXT_TYPES = array( 'char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json' );

	private const INTEGER_TYPES = array( 'tinyint', 'smallint', 'mediumint', 'int', 'bigint' );

	// Key values are sent back to the database as the paging cursor, so they must compare exactly.
	// Binary, bit and floating point columns are left out: they would not survive the round trip.
	private const KEY_TYPES = array( 'tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'char', 'varchar', 'decimal', 'date', 'datetime', 'timestamp' );

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

		/* translators: %s: database error message. */
		$this->check_error( __( 'Could not read the table list from the database: %s', 'designslabz-relocate' ) );

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
	 * Tables a job may search: everything except this plugin's own tables,
	 * which hold the search values themselves.
	 *
	 * @return array<string, Table> Keyed by table name.
	 * @throws RuntimeException When the table list cannot be read.
	 */
	public function searchable_tables(): array {
		$tables = array();

		foreach ( $this->tables() as $table ) {
			if ( ! str_starts_with( $table->name, $this->wpdb->prefix . Installer::TABLE_PREFIX ) ) {
				$tables[ $table->name ] = $table;
			}
		}

		return $tables;
	}

	/**
	 * @return TableLayout|null Null when the table does not exist.
	 * @throws RuntimeException When the table cannot be inspected.
	 */
	public function describe( string $table ): ?TableLayout {
		$columns = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT COLUMN_NAME AS name, DATA_TYPE AS type, COLLATION_NAME AS collation
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
				ORDER BY ORDINAL_POSITION',
				$table
			)
		);

		/* translators: %s: database error message. */
		$this->check_error( __( 'Could not read the columns of a table: %s', 'designslabz-relocate' ) );

		if ( ! $columns ) {
			return null;
		}

		$types = array();
		foreach ( $columns as $column ) {
			$types[ (string) $column->name ] = strtolower( (string) $column->type );
		}

		$key = array();
		foreach ( $this->key_columns( $table ) as $column ) {
			if ( ! in_array( $types[ $column ] ?? '', self::KEY_TYPES, true ) ) {
				$key = array();
				break;
			}
			$key[ $column ] = in_array( $types[ $column ], self::INTEGER_TYPES, true );
		}

		$searchable = array();
		foreach ( $columns as $column ) {
			$name = (string) $column->name;

			// Key columns are never changed: they locate the row being updated.
			if ( in_array( $types[ $name ], self::TEXT_TYPES, true ) && ! isset( $key[ $name ] ) ) {
				$searchable[ $name ] = (string) $column->collation;
			}
		}

		return new TableLayout( $table, $key, $searchable );
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

	/**
	 * The primary key, or else the first unique index whose columns cannot be NULL
	 * (a unique index allows any number of NULL rows, so it cannot identify one).
	 *
	 * @return string[]
	 */
	private function key_columns( string $table ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT INDEX_NAME AS index_name, COLUMN_NAME AS name, NULLABLE AS nullable
				FROM information_schema.STATISTICS
				WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND NON_UNIQUE = 0
				ORDER BY INDEX_NAME, SEQ_IN_INDEX',
				$table
			)
		);

		/* translators: %s: database error message. */
		$this->check_error( __( 'Could not read the indexes of a table: %s', 'designslabz-relocate' ) );

		$indexes  = array();
		$nullable = array();
		foreach ( $rows as $row ) {
			$indexes[ (string) $row->index_name ][] = (string) $row->name;
			if ( 'YES' === $row->nullable ) {
				$nullable[ (string) $row->index_name ] = true;
			}
		}

		if ( isset( $indexes['PRIMARY'] ) ) {
			return $indexes['PRIMARY'];
		}

		foreach ( $indexes as $index => $columns ) {
			if ( ! isset( $nullable[ $index ] ) ) {
				return $columns;
			}
		}

		return array();
	}

	/**
	 * @param string $message Translated message with a %s placeholder for the database error.
	 * @throws RuntimeException When the last query failed.
	 */
	private function check_error( string $message ): void {
		if ( '' !== $this->wpdb->last_error ) {
			throw new RuntimeException( sprintf( $message, $this->wpdb->last_error ) );
		}
	}
}
