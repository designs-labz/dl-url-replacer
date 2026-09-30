<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Database;

/**
 * A database table as reported by information_schema.
 *
 * Row counts and sizes are the server's statistics: exact for MyISAM,
 * estimates for InnoDB.
 */
final class Table {

	public function __construct(
		public readonly string $name,
		public readonly string $engine,
		public readonly int $approx_rows,
		public readonly int $data_size,
		public readonly int $index_size,
		public readonly string $collation,
		public readonly bool $prefixed
	) {}

	public function size(): int {
		return $this->data_size + $this->index_size;
	}
}
