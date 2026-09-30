<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Tests\Unit;

use DesignsLabz\Relocate\Replace\Replacement;
use DesignsLabz\Relocate\Replace\Replacer;
use DesignsLabz\Relocate\Replace\ReplaceResult;
use DesignsLabz\Relocate\Tests\Unit\Fixtures\Status;
use DesignsLabz\Relocate\Tests\Unit\Fixtures\Widget;
use PHPUnit\Framework\TestCase;

final class SerializedDataTest extends TestCase {

	private const OLD = 'http://old.test';
	private const NEW = 'https://new.example';

	private Replacer $replacer;

	protected function setUp(): void {
		$this->replacer = new Replacer( new Replacement( array( array( self::OLD, self::NEW ) ) ) );
	}

	/**
	 * @return array<string, array{0: mixed, 1: mixed}>
	 */
	public static function structures(): array {
		return array(
			'string'            => array( self::OLD . '/page', self::NEW . '/page' ),
			'flat array'        => array(
				array(
					'home'  => self::OLD,
					'count' => 3,
				),
				array(
					'home'  => self::NEW,
					'count' => 3,
				),
			),
			'nested array'      => array(
				array(
					'a'    => array( 'b' => array( 'c' => self::OLD . '/deep' ) ),
					'list' => array( self::OLD, 'x' ),
				),
				array(
					'a'    => array( 'b' => array( 'c' => self::NEW . '/deep' ) ),
					'list' => array( self::NEW, 'x' ),
				),
			),
			'scalars kept'      => array(
				array( null, true, false, -42, 0.1, 1.0E+25, -INF, self::OLD ),
				array( null, true, false, -42, 0.1, 1.0E+25, -INF, self::NEW ),
			),
			'multibyte'         => array(
				array(
					'title' => 'Grüße 日本',
					'url'   => self::OLD . '/über',
				),
				array(
					'title' => 'Grüße 日本',
					'url'   => self::NEW . '/über',
				),
			),
			'keys untouched'    => array(
				array( self::OLD => self::OLD ),
				array( self::OLD => self::NEW ),
			),
			'stdClass'          => array(
				(object) array(
					'url' => self::OLD,
					7     => 'x',
				),
				(object) array(
					'url' => self::NEW,
					7     => 'x',
				),
			),
			'enum'              => array(
				array( Status::Active, self::OLD ),
				array( Status::Active, self::NEW ),
			),
			'double serialized' => array(
				array( 'inner' => serialize( array( 'url' => self::OLD ) ) ),
				array( 'inner' => serialize( array( 'url' => self::NEW ) ) ),
			),
			'json in array'     => array(
				array( 'data' => json_encode( array( 'url' => self::OLD . '/x' ) ) ),
				array( 'data' => json_encode( array( 'url' => self::NEW . '/x' ) ) ),
			),
		);
	}

	/**
	 * @dataProvider structures
	 *
	 * @param mixed $before Value before replacement.
	 * @param mixed $after  Expected value after replacement.
	 */
	public function test_rewrites_serialized_structures( mixed $before, mixed $after ): void {
		$result = $this->replacer->replace( serialize( $before ) );

		$this->assertNull( $result->skipped );
		$this->assertSame( serialize( $after ), $result->value );
	}

	public function test_object_with_private_and_protected_properties(): void {
		$result = $this->replacer->replace( serialize( new Widget( self::OLD ) ) );

		$this->assertSame( serialize( new Widget( self::NEW ) ), $result->value );
		$this->assertSame( 4, $result->count );
	}

	public function test_references_are_preserved(): void {
		$result = $this->replacer->replace( serialize( $this->with_reference( self::OLD ) ) );

		$this->assertSame( serialize( $this->with_reference( self::NEW ) ), $result->value );
		$this->assertStringContainsString( 'R:', $result->value );
	}

	public function test_counts_replacements_in_nested_data(): void {
		$value = serialize( array( self::OLD, array( self::OLD . ' ' . self::OLD ), serialize( array( self::OLD ) ) ) );

		$this->assertSame( 4, $this->replacer->replace( $value )->count );
	}

	public function test_skips_corrupt_serialized_data(): void {
		$value  = 'a:1:{i:0;s:99:"' . self::OLD . '";}';
		$result = $this->replacer->replace( $value );

		$this->assertSame( $value, $result->value );
		$this->assertSame( ReplaceResult::INVALID_SERIALIZED, $result->skipped );
	}

	public function test_skips_trailing_garbage(): void {
		$value = serialize( array( self::OLD ) ) . 'extra';

		$this->assertSame( ReplaceResult::INVALID_SERIALIZED, $this->replacer->replace( $value )->skipped );
	}

	public function test_skips_custom_serializable_payload_with_a_match(): void {
		$value  = 'a:1:{i:0;C:3:"Foo":15:{' . self::OLD . '}}';
		$result = $this->replacer->replace( $value );

		$this->assertSame( $value, $result->value );
		$this->assertSame( ReplaceResult::UNSUPPORTED_SERIALIZED, $result->skipped );
	}

	public function test_replaces_around_custom_serializable_without_a_match(): void {
		$result = $this->replacer->replace( 'a:2:{i:0;C:3:"Foo":5:{hello}i:1;s:15:"' . self::OLD . '";}' );

		$this->assertSame( 'a:2:{i:0;C:3:"Foo":5:{hello}i:1;s:19:"' . self::NEW . '";}', $result->value );
	}

	public function test_skips_the_whole_value_when_nested_json_would_break(): void {
		$replacer = new Replacer( new Replacement( array( array( 'old', 'say "hi"' ) ) ) );
		$value    = serialize(
			array(
				'plain' => 'old',
				'json'  => '{"label":"old"}',
			)
		);
		$result   = $replacer->replace( $value );

		$this->assertSame( $value, $result->value );
		$this->assertSame( ReplaceResult::BROKEN_JSON, $result->skipped );
	}

	public function test_plain_text_that_only_resembles_serialized_data(): void {
		$result = $this->replacer->replace( 'a: ' . self::OLD );

		$this->assertSame( 'a: ' . self::NEW, $result->value );
	}

	/**
	 * Random nested arrays of awkward strings must round-trip exactly as if
	 * the replacement had been done on the unserialized values.
	 */
	public function test_random_structures_round_trip(): void {
		mt_srand( 20260930 );

		for ( $i = 0; $i < 300; $i++ ) {
			$data     = $this->random_array( 0 );
			$expected = $data;
			$count    = 0;

			array_walk_recursive(
				$expected,
				function ( &$value ) use ( &$count ): void {
					if ( is_string( $value ) ) {
						$count += substr_count( $value, self::OLD );
						$value  = str_replace( self::OLD, self::NEW, $value );
					}
				}
			);

			$result = $this->replacer->replace( serialize( $data ) );

			$this->assertNull( $result->skipped, "Iteration {$i}" );
			$this->assertSame( serialize( $expected ), $result->value, "Iteration {$i}" );
			$this->assertSame( $count, $result->count, "Iteration {$i}" );
		}
	}

	/**
	 * @return array<mixed>
	 */
	private function random_array( int $depth ): array {
		$array = array();

		for ( $i = mt_rand( 0, 5 ); $i > 0; $i-- ) {
			$key           = mt_rand( 0, 1 ) ? mt_rand( -5, 100 ) : $this->random_string();
			$array[ $key ] = $depth < 3 && 0 === mt_rand( 0, 3 ) ? $this->random_array( $depth + 1 ) : $this->random_scalar();
		}

		return $array;
	}

	private function random_scalar(): mixed {
		return match ( mt_rand( 0, 7 ) ) {
			0       => mt_rand( PHP_INT_MIN, PHP_INT_MAX ),
			1       => mt_rand() / 7,
			2       => null,
			3       => (bool) mt_rand( 0, 1 ),
			default => $this->random_string(),
		};
	}

	private function random_string(): string {
		$pieces = array( self::OLD, self::OLD . '/', 'ü', '日本', '"', ';', '{', '}', ':', 's:5:"', 'OLD.test', ' ', "\0", "\n", 'x' );
		$string = 'v';

		for ( $i = mt_rand( 0, 6 ); $i > 0; $i-- ) {
			$string .= $pieces[ mt_rand( 0, count( $pieces ) - 1 ) ];
		}

		return $string;
	}

	/**
	 * @return array<string, string>
	 */
	private function with_reference( string $url ): array {
		$data      = array( 'a' => $url );
		$data['b'] = &$data['a'];

		return $data;
	}
}
