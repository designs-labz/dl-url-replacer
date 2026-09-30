<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Replace;

/**
 * The outcome of replacing within one value.
 *
 * When $skipped is set, $value is the original, untouched value and $skipped
 * says why it could not be changed safely.
 */
final class ReplaceResult {

	/** Looks like serialized PHP but does not parse, or would not unserialize after the change. */
	public const INVALID_SERIALIZED = 'invalid_serialized';

	/** A match sits inside a custom Serializable (C:) payload, whose format is private to its class. */
	public const UNSUPPORTED_SERIALIZED = 'unsupported_serialized';

	/** The value is valid JSON and the replacement would make it invalid. */
	public const BROKEN_JSON = 'broken_json';

	public function __construct(
		public readonly string $value,
		public readonly int $count,
		public readonly ?string $skipped = null
	) {}
}
