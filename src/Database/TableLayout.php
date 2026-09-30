<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Database;

/**
 * What a job needs to know to page through a table and search it.
 */
final class TableLayout {

	/**
	 * @param string                      $name    Table name.
	 * @param array<string, bool>         $key     Row key columns in index order => whether the column is an integer.
	 *                                             Empty when the table has no key that can identify a single row.
	 * @param array<string, string>       $columns Text columns to search => collation. Never includes key columns.
	 * @param array<string, list<string>> $only    Limit the rows to those where column is one of the values.
	 * @param array<string, list<string>> $except  Leave out rows where column is one of the values.
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $key,
		public readonly array $columns,
		public readonly array $only = array(),
		public readonly array $except = array()
	) {}

	public function without( string $column ): self {
		$columns = $this->columns;
		unset( $columns[ $column ] );

		return new self( $this->name, $this->key, $columns, $this->only, $this->except );
	}

	/**
	 * @param list<string> $values
	 */
	public function only( string $column, array $values ): self {
		return new self( $this->name, $this->key, $this->columns, array( $column => $values ) + $this->only, $this->except );
	}

	/**
	 * @param list<string> $values
	 */
	public function except( string $column, array $values ): self {
		return new self( $this->name, $this->key, $this->columns, $this->only, array( $column => $values ) + $this->except );
	}
}
