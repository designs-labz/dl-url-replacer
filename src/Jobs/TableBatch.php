<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Jobs;

use DesignsLabz\Relocate\Database\TableLayout;
use DesignsLabz\Relocate\Replace\Replacement;
use DesignsLabz\Relocate\Replace\Replacer;
use RuntimeException;

/**
 * Reads one window of a table and works out what would change in it.
 *
 * Windows are ranges of the row key rather than LIMIT/OFFSET pages, so each
 * one costs the same no matter how far into a large table it is, and rows
 * added or removed meanwhile do not shift later windows. Within a window only
 * rows whose text columns LIKE-match a search value are fetched in full.
 */
final class TableBatch {

	public function __construct(
		private \wpdb $wpdb,
		private Replacement $replacement,
		private Replacer $replacer,
		private int $size
	) {}

	/**
	 * @param array<string, string>|null $after Key of the last row already processed, or null to start.
	 * @throws RuntimeException On a database error.
	 */
	public function scan( TableLayout $layout, ?array $after ): BatchResult {
		$keys = $this->window_keys( $layout, $after );

		if ( ! $keys ) {
			return new BatchResult( 0, null, array(), array() );
		}

		$last    = end( $keys );
		$changed = array();
		$skipped = array();

		foreach ( $this->matching_rows( $layout, $after, $last ) as $row ) {
			$key    = array_intersect_key( $row, $layout->key );
			$before = array();
			$values = array();
			$counts = array();

			foreach ( array_keys( $layout->columns ) as $column ) {
				if ( null === $row[ $column ] ) {
					continue;
				}

				try {
					$result = $this->replacer->replace( $row[ $column ] );
				} catch ( RuntimeException ) {
					$skipped[] = array(
						'key'    => $key,
						'column' => $column,
						'reason' => 'search_error',
					);
					continue;
				}

				if ( null !== $result->skipped ) {
					$skipped[] = array(
						'key'    => $key,
						'column' => $column,
						'reason' => $result->skipped,
					);
				} elseif ( $result->value !== $row[ $column ] ) {
					$before[ $column ] = $row[ $column ];
					$values[ $column ] = $result->value;
					$counts[ $column ] = $result->count;
				}
			}

			if ( $counts ) {
				$changed[] = array(
					'key'    => $key,
					'before' => $before,
					'after'  => $values,
					'counts' => $counts,
				);
			}
		}

		// A short window means the end of the table was reached.
		return new BatchResult( count( $keys ), count( $keys ) < $this->size ? null : $last, $changed, $skipped );
	}

	/**
	 * @param array<string, string>|null $after
	 * @return list<array<string, string>>
	 */
	private function window_keys( TableLayout $layout, ?array $after ): array {
		$columns = array_keys( $layout->key );
		$list    = implode( ', ', array_fill( 0, count( $columns ), '%i' ) );
		$sql     = 'SELECT ' . $list . ' FROM %i';
		$args    = array_merge( $columns, array( $layout->name ) );

		if ( null !== $after ) {
			[ $condition, $condition_args ] = $this->compare( $layout, $after, '>' );

			$sql .= ' WHERE ' . $condition;
			$args = array_merge( $args, $condition_args );
		}

		$sql .= ' ORDER BY ' . $list . ' LIMIT %d';
		$args = array_merge( $args, $columns, array( $this->size ) );

		return $this->query( $sql, $args );
	}

	/**
	 * @param array<string, string>|null $after
	 * @param array<string, string>      $last
	 * @return list<array<string, string|null>>
	 */
	private function matching_rows( TableLayout $layout, ?array $after, array $last ): array {
		$columns = array_merge( array_keys( $layout->key ), array_keys( $layout->columns ) );

		[ $until, $until_args ] = $this->compare( $layout, $last, '<=' );
		[ $like, $like_args ]   = $this->like( $layout );

		$sql  = 'SELECT ' . implode( ', ', array_fill( 0, count( $columns ), '%i' ) ) . ' FROM %i WHERE ' . $until . ' AND ' . $like;
		$args = array_merge( $columns, array( $layout->name ), $until_args, $like_args );

		if ( null !== $after ) {
			[ $from, $from_args ] = $this->compare( $layout, $after, '>' );

			$sql .= ' AND ' . $from;
			$args = array_merge( $args, $from_args );
		}

		return $this->query( $sql, $args );
	}

	/**
	 * Row-key comparison written out column by column, e.g. for (a, b) > (1, 2):
	 * (a > 1) OR (a = 1 AND b > 2). Works on MySQL and MariaDB and uses the index.
	 *
	 * @param array<string, string> $values
	 * @param '>'|'<='              $operator
	 * @return array{0: string, 1: list<string>}
	 */
	private function compare( TableLayout $layout, array $values, string $operator ): array {
		$strict  = '>' === $operator ? '>' : '<';
		$columns = array_keys( $layout->key );
		$last    = count( $columns ) - 1;
		$clauses = array();
		$args    = array();
		$equal   = array();
		$eq_args = array();

		foreach ( $columns as $index => $column ) {
			$placeholder = $layout->key[ $column ] ? '%d' : '%s';
			$op          = $index === $last ? $operator : $strict;

			$clauses[] = '(' . implode( ' AND ', array_merge( $equal, array( "%i {$op} {$placeholder}" ) ) ) . ')';
			$args      = array_merge( $args, $eq_args, array( $column, $values[ $column ] ) );

			$equal[] = "%i = {$placeholder}";
			$eq_args = array_merge( $eq_args, array( $column, $values[ $column ] ) );
		}

		return array( '(' . implode( ' OR ', $clauses ) . ')', $args );
	}

	/**
	 * Cheap pre-filter so only rows that could match are pulled into PHP. With a
	 * case-insensitive (_ci) collation LIKE matches a superset of what the
	 * Replacer will, which is fine. Case-sensitive (_bin, _cs, JSON) columns are
	 * lowercased when the search ignores case, or they would be missed.
	 *
	 * @return array{0: string, 1: list<string>}
	 */
	private function like( TableLayout $layout ): array {
		$parts = array();
		$args  = array();

		foreach ( $layout->columns as $column => $collation ) {
			$lower = ! $this->replacement->case_sensitive
				&& ( '' === $collation || str_ends_with( $collation, '_bin' ) || str_ends_with( $collation, '_cs' ) );

			foreach ( $this->replacement->pairs as $pair ) {
				$parts[] = $lower ? 'LOWER(%i) LIKE LOWER(%s)' : '%i LIKE %s';
				$args[]  = $column;
				$args[]  = '%' . $this->wpdb->esc_like( $pair['search'] ) . '%';
			}
		}

		return array( '(' . implode( ' OR ', $parts ) . ')', $args );
	}

	/**
	 * @param list<string|int> $args
	 * @return list<array<string, string|null>>
	 * @throws RuntimeException On a database error.
	 */
	private function query( string $sql, array $args ): array {
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $args ), ARRAY_A );

		if ( '' !== $this->wpdb->last_error ) {
			throw new RuntimeException( $this->wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escaped where it is displayed.
		}

		return $rows;
	}
}
