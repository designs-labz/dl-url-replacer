<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Database;

/**
 * What a job needs to know to page through a table and search it.
 */
final class TableLayout {

	/**
	 * @param string                $name    Table name.
	 * @param array<string, bool>   $key     Row key columns in index order => whether the column is an integer.
	 *                                       Empty when the table has no key that can identify a single row.
	 * @param array<string, string> $columns Text columns to search => collation. Never includes key columns.
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $key,
		public readonly array $columns
	) {}

	public function without( string $column ): self {
		$columns = $this->columns;
		unset( $columns[ $column ] );

		return new self( $this->name, $this->key, $columns );
	}
}
