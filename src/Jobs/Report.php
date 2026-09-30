<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Jobs;

/**
 * Per-table results of a job, plus a few before/after examples for the preview.
 *
 * @phpstan-type TableStats array{rows_scanned: int, rows_changed: int, replacements: int, columns: array<string, int>, skipped: array<string, int>, note: string|null}
 * @phpstan-type Sample array{table: string, column: string, key: string, before: string, after: string}
 */
final class Report {

	public const NOTE_MISSING_TABLE = 'missing_table';
	public const NOTE_NO_KEY        = 'no_key';
	public const NOTE_NO_COLUMNS    = 'no_columns';

	private const MAX_SAMPLES   = 20;
	private const CONTEXT_BYTES = 60;
	private const MAX_SNIPPET   = 300;

	/**
	 * @param array<string, TableStats> $tables
	 * @param list<Sample>              $samples
	 */
	public function __construct(
		private array $tables = array(),
		private array $samples = array()
	) {}

	/**
	 * @param array<string, mixed> $data As produced by to_array().
	 */
	public static function from_array( array $data ): self {
		return new self( $data['tables'] ?? array(), $data['samples'] ?? array() );
	}

	/**
	 * @return array{tables: array<string, TableStats>, samples: list<Sample>}
	 */
	public function to_array(): array {
		return array(
			'tables'  => $this->tables,
			'samples' => $this->samples,
		);
	}

	public function add( string $table, BatchResult $batch ): void {
		$stats = $this->stats( $table );

		$stats['rows_scanned'] += $batch->scanned;

		foreach ( $batch->rows as $row ) {
			++$stats['rows_changed'];

			foreach ( $row['counts'] as $column => $count ) {
				$stats['replacements']      += $count;
				$stats['columns'][ $column ] = ( $stats['columns'][ $column ] ?? 0 ) + $count;
			}

			if ( count( $this->samples ) < self::MAX_SAMPLES ) {
				$this->add_sample( $table, $row );
			}
		}

		foreach ( $batch->skipped as $skipped ) {
			$stats['skipped'][ $skipped['reason'] ] = ( $stats['skipped'][ $skipped['reason'] ] ?? 0 ) + 1;
		}

		$this->tables[ $table ] = $stats;
	}

	public function note( string $table, string $note ): void {
		$stats         = $this->stats( $table );
		$stats['note'] = $note;

		$this->tables[ $table ] = $stats;
	}

	/**
	 * @return array{rows_scanned: int, rows_changed: int, replacements: int, skipped: int}
	 */
	public function totals(): array {
		$totals = array(
			'rows_scanned' => 0,
			'rows_changed' => 0,
			'replacements' => 0,
			'skipped'      => 0,
		);

		foreach ( $this->tables as $stats ) {
			$totals['rows_scanned'] += $stats['rows_scanned'];
			$totals['rows_changed'] += $stats['rows_changed'];
			$totals['replacements'] += $stats['replacements'];
			$totals['skipped']      += array_sum( $stats['skipped'] );
		}

		return $totals;
	}

	/**
	 * @return array<string, TableStats>
	 */
	public function tables(): array {
		return $this->tables;
	}

	/**
	 * @return list<Sample>
	 */
	public function samples(): array {
		return $this->samples;
	}

	/**
	 * @return TableStats
	 */
	private function stats( string $table ): array {
		return $this->tables[ $table ] ?? array(
			'rows_scanned' => 0,
			'rows_changed' => 0,
			'replacements' => 0,
			'columns'      => array(),
			'skipped'      => array(),
			'note'         => null,
		);
	}

	/**
	 * @param array{key: array<string, string>, before: array<string, string>, after: array<string, string>, counts: array<string, int>} $row
	 */
	private function add_sample( string $table, array $row ): void {
		$column = (string) array_key_first( $row['after'] );

		[ $before, $after ] = self::snippets( $row['before'][ $column ], $row['after'][ $column ] );

		$key = array();
		foreach ( $row['key'] as $name => $value ) {
			$key[] = $name . ' = ' . $value;
		}

		$this->samples[] = array(
			'table'  => $table,
			'column' => $column,
			'key'    => implode( ', ', $key ),
			'before' => $before,
			'after'  => $after,
		);
	}

	/**
	 * The changed part of a value with a little context either side.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function snippets( string $before, string $after ): array {
		$shortest = min( strlen( $before ), strlen( $after ) );
		$prefix   = strspn( $before ^ $after, "\0" );
		$suffix   = min( strspn( strrev( $before ) ^ strrev( $after ), "\0" ), $shortest - $prefix );
		$start    = max( 0, $prefix - self::CONTEXT_BYTES );

		return array(
			self::cut( $before, $start, strlen( $before ) - $suffix + self::CONTEXT_BYTES ),
			self::cut( $after, $start, strlen( $after ) - $suffix + self::CONTEXT_BYTES ),
		);
	}

	/**
	 * Byte range of $text, moved to UTF-8 character boundaries and capped in length.
	 */
	private static function cut( string $text, int $start, int $end ): string {
		$length = strlen( $text );
		$end    = min( $length, $end, $start + self::MAX_SNIPPET );

		while ( $start > 0 && 0x80 === ( ord( $text[ $start ] ) & 0xC0 ) ) {
			--$start;
		}
		while ( $end < $length && 0x80 === ( ord( $text[ $end ] ) & 0xC0 ) ) {
			++$end;
		}

		return ( $start > 0 ? '…' : '' ) . substr( $text, $start, $end - $start ) . ( $end < $length ? '…' : '' );
	}
}
