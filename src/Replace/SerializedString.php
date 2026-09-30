<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Replace;

use Closure;
use UnexpectedValueException;

/**
 * Rewrites the string values inside serialized PHP data without unserializing it.
 *
 * Unserializing database content can instantiate arbitrary classes, and
 * re-serializing can change floats, references and private property layout.
 * Instead this walks the serialized text, copies structure byte for byte and
 * only rewrites string values, recalculating their byte lengths. Array keys,
 * property names, class names and enums are left alone.
 */
final class SerializedString {

	private const SCALAR = '/\G(?:N|b:[01]|i:[+-]?\d+|d:(?:[+-]?(?:INF|NAN|(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?))|[rR]:\d+);/';

	private int $pos = 0;

	private int $count = 0;

	private ?string $skipped = null;

	/**
	 * @param Closure(string): ReplaceResult $replace Applied to every string value.
	 */
	private function __construct(
		private string $data,
		private Closure $replace
	) {}

	/**
	 * Cheap check for the types worth parsing: arrays, objects and serialized strings.
	 */
	public static function looks_serialized( string $value ): bool {
		return 1 === preg_match( '/^(?:a:\d+:\{|[OC]:\d+:"|s:\d+:")/', $value );
	}

	/**
	 * @param Closure(string): ReplaceResult $replace Applied to every string value.
	 * @return ReplaceResult|null Null when $data is not one complete, well-formed serialized value.
	 */
	public static function rewrite( string $data, Closure $replace ): ?ReplaceResult {
		$parser = new self( $data, $replace );

		try {
			$output = $parser->value();
		} catch ( UnexpectedValueException ) {
			return null;
		}

		if ( strlen( $data ) !== $parser->pos ) {
			return null;
		}

		if ( null !== $parser->skipped ) {
			return new ReplaceResult( $data, 0, $parser->skipped );
		}

		return new ReplaceResult( $output, $parser->count );
	}

	private function value(): string {
		return match ( $this->data[ $this->pos ] ?? '' ) {
			's'                          => $this->string_value(),
			'a'                          => $this->array_value(),
			'O'                          => $this->object_value(),
			'C'                          => $this->custom_object(),
			'E'                          => $this->enum_value(),
			'N', 'b', 'i', 'd', 'r', 'R' => $this->consume( self::SCALAR )[0],
			default                      => throw new UnexpectedValueException(),
		};
	}

	private function string_value(): string {
		$content = $this->quoted( 's' );
		$this->expect( ';' );

		$result = ( $this->replace )( $content );

		if ( null !== $result->skipped ) {
			$this->skipped ??= $result->skipped;
			return '';
		}

		$this->count += $result->count;

		return 's:' . strlen( $result->value ) . ':"' . $result->value . '";';
	}

	private function array_value(): string {
		$header = $this->consume( '/\Ga:(\d+):\{/' );

		return $header[0] . $this->members( (int) $header[1] );
	}

	private function object_value(): string {
		$start = $this->pos;
		$this->quoted( 'O' );
		$header = $this->consume( '/\G:(\d+):\{/' );

		return substr( $this->data, $start, $this->pos - $start ) . $this->members( (int) $header[1] );
	}

	/**
	 * C:<len>:"<class>":<len>:{<payload>} is written by the class's own serialize()
	 * method, so there is no safe way to edit it. Copy it and flag any match inside.
	 */
	private function custom_object(): string {
		$start = $this->pos;
		$this->quoted( 'C' );
		$header = $this->consume( '/\G:(\d+):\{/' );
		$this->bytes( (int) $header[1] );
		$this->expect( '}' );

		$token = substr( $this->data, $start, $this->pos - $start );

		if ( ( $this->replace )( substr( $token, strlen( $token ) - (int) $header[1] - 1, (int) $header[1] ) )->count > 0 ) {
			$this->skipped ??= ReplaceResult::UNSUPPORTED_SERIALIZED;
		}

		return $token;
	}

	private function enum_value(): string {
		$start = $this->pos;
		$this->quoted( 'E' );
		$this->expect( ';' );

		return substr( $this->data, $start, $this->pos - $start );
	}

	private function members( int $count ): string {
		$output = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$output .= $this->key() . $this->value();
		}

		$this->expect( '}' );

		return $output . '}';
	}

	private function key(): string {
		$start = $this->pos;

		if ( 'i' === ( $this->data[ $this->pos ] ?? '' ) ) {
			$this->consume( '/\Gi:[+-]?\d+;/' );
		} else {
			$this->quoted( 's' );
			$this->expect( ';' );
		}

		return substr( $this->data, $start, $this->pos - $start );
	}

	/**
	 * Reads <type>:<length>:"<bytes>" and returns the bytes.
	 */
	private function quoted( string $type ): string {
		$header  = $this->consume( '/\G' . $type . ':(\d+):"/' );
		$content = $this->bytes( (int) $header[1] );
		$this->expect( '"' );

		return $content;
	}

	private function bytes( int $length ): string {
		$bytes = substr( $this->data, $this->pos, $length );

		if ( strlen( $bytes ) !== $length ) {
			throw new UnexpectedValueException();
		}

		$this->pos += $length;

		return $bytes;
	}

	/**
	 * @return string[]
	 */
	private function consume( string $pattern ): array {
		if ( 1 !== preg_match( $pattern, $this->data, $matches, 0, $this->pos ) ) {
			throw new UnexpectedValueException();
		}

		$this->pos += strlen( $matches[0] );

		return $matches;
	}

	private function expect( string $text ): void {
		if ( substr( $this->data, $this->pos, strlen( $text ) ) !== $text ) {
			throw new UnexpectedValueException();
		}

		$this->pos += strlen( $text );
	}
}
