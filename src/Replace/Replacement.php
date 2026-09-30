<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Replace;

use InvalidArgumentException;

/**
 * What to search for, what to replace it with, and how to match.
 *
 * Besides the value the user typed, the search also covers the forms that
 * value takes inside JSON (escaped slashes, \uXXXX escapes) and, for URLs
 * when requested, the other scheme and the protocol-relative form.
 */
final class Replacement {

	/**
	 * Every search/replace pair to apply, the user's own pair first.
	 *
	 * @var list<array{search: string, replace: string}>
	 */
	public readonly array $pairs;

	/**
	 * @throws InvalidArgumentException When the values cannot be used.
	 */
	public function __construct(
		public readonly string $search,
		public readonly string $replace,
		public readonly bool $case_sensitive = true,
		public readonly bool $whole_words = false,
		public readonly bool $url_variants = false
	) {
		if ( '' === $search ) {
			throw new InvalidArgumentException( 'The search value cannot be empty.' );
		}

		if ( $search === $replace ) {
			throw new InvalidArgumentException( 'The search and replacement values are identical.' );
		}

		if ( ! self::is_utf8( $search ) || ! self::is_utf8( $replace ) ) {
			throw new InvalidArgumentException( 'The search and replacement values must be valid UTF-8.' );
		}

		$this->pairs = $this->build_pairs();
	}

	/**
	 * @return list<array{search: string, replace: string}>
	 */
	private function build_pairs(): array {
		$pairs = array_merge(
			array(
				array(
					'search'  => $this->search,
					'replace' => $this->replace,
				),
			),
			$this->url_variants ? $this->url_pairs() : array()
		);

		// JSON as written by json_encode(), with and without JSON_UNESCAPED_UNICODE.
		foreach ( $pairs as $pair ) {
			foreach ( array( JSON_UNESCAPED_UNICODE, 0 ) as $flags ) {
				$pairs[] = array(
					'search'  => self::json_escape( $pair['search'], $flags ),
					'replace' => self::json_escape( $pair['replace'], $flags ),
				);
			}
		}

		$unique = array();
		foreach ( $pairs as $pair ) {
			if ( $pair['search'] !== $pair['replace'] && ! isset( $unique[ $pair['search'] ] ) ) {
				$unique[ $pair['search'] ] = $pair;
			}
		}

		return array_values( $unique );
	}

	/**
	 * For https://old.test also match http://old.test, and //old.test when the
	 * replacement is itself a URL with a scheme.
	 *
	 * @return list<array{search: string, replace: string}>
	 */
	private function url_pairs(): array {
		if ( ! preg_match( '#^(https?):(//.+)#i', $this->search, $search ) ) {
			return array();
		}

		$pairs = array(
			array(
				'search'  => ( 0 === strcasecmp( $search[1], 'https' ) ? 'http:' : 'https:' ) . $search[2],
				'replace' => $this->replace,
			),
		);

		if ( preg_match( '#^https?:(//.+)#i', $this->replace, $replace ) ) {
			$pairs[] = array(
				'search'  => $search[2],
				'replace' => $replace[1],
			);
		}

		return $pairs;
	}

	private static function json_escape( string $text, int $flags ): string {
		// Plain json_encode() keeps this class free of WordPress; the input is already known to be valid UTF-8.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return substr( (string) json_encode( $text, $flags ), 1, -1 );
	}

	private static function is_utf8( string $text ): bool {
		return 1 === preg_match( '//u', $text );
	}
}
