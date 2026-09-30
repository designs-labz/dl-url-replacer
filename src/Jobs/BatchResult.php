<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Jobs;

/**
 * What one window of rows from one table produced.
 *
 * @phpstan-type ChangedRow array{key: array<string, string>, before: array<string, string>, after: array<string, string>, counts: array<string, int>}
 * @phpstan-type SkippedValue array{key: array<string, string>, column: string, reason: string}
 */
final class BatchResult {

	/**
	 * @param int                        $scanned  Rows in the window.
	 * @param array<string, string>|null $last_key Key to continue after, or null when the table is finished.
	 * @param list<ChangedRow>           $rows     Rows with at least one changed value.
	 * @param list<SkippedValue>         $skipped  Values that matched but could not be changed safely.
	 */
	public function __construct(
		public readonly int $scanned,
		public readonly ?array $last_key,
		public readonly array $rows,
		public readonly array $skipped
	) {}
}
