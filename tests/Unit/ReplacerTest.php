<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Tests\Unit;

use CraftRoq\Relocate\Replace\Replacement;
use CraftRoq\Relocate\Replace\Replacer;
use CraftRoq\Relocate\Replace\ReplaceResult;
use PHPUnit\Framework\TestCase;

/**
 * Plain text, URLs, JSON and Unicode. Serialized data is in SerializedDataTest.
 */
final class ReplacerTest extends TestCase {

	private function replace( string $value, string $search, string $replace, bool $case_sensitive = true, bool $whole_words = false, bool $url_variants = false ): ReplaceResult {
		return ( new Replacer( new Replacement( array( array( $search, $replace ) ), $case_sensitive, $whole_words, $url_variants ) ) )->replace( $value );
	}

	public function test_plain_text(): void {
		$result = $this->replace( 'The cat sat on the cat mat.', 'cat', 'dog' );

		$this->assertSame( 'The dog sat on the dog mat.', $result->value );
		$this->assertSame( 2, $result->count );
		$this->assertNull( $result->skipped );
	}

	public function test_no_match_returns_value_unchanged(): void {
		$result = $this->replace( 'Nothing here', 'cat', 'dog' );

		$this->assertSame( 'Nothing here', $result->value );
		$this->assertSame( 0, $result->count );
	}

	public function test_url_in_html(): void {
		$result = $this->replace(
			'<a href="https://staging.test/about">About</a><img src="https://staging.test/logo.png">',
			'https://staging.test',
			'https://example.test'
		);

		$this->assertSame( '<a href="https://example.test/about">About</a><img src="https://example.test/logo.png">', $result->value );
		$this->assertSame( 2, $result->count );
	}

	public function test_is_case_sensitive_by_default(): void {
		$this->assertSame( 0, $this->replace( 'Hello HELLO', 'hello', 'bye' )->count );
	}

	public function test_case_insensitive(): void {
		$result = $this->replace( 'Hello HELLO hello', 'hello', 'bye', case_sensitive: false );

		$this->assertSame( 'bye bye bye', $result->value );
		$this->assertSame( 3, $result->count );
	}

	public function test_case_insensitive_folds_multibyte_letters(): void {
		$result = $this->replace( 'ÉCOLE école', 'école', 'school', case_sensitive: false );

		$this->assertSame( 'school school', $result->value );
	}

	public function test_whole_words(): void {
		$result = $this->replace( 'cat concatenate cat. cats', 'cat', 'dog', whole_words: true );

		$this->assertSame( 'dog concatenate dog. cats', $result->value );
		$this->assertSame( 2, $result->count );
	}

	public function test_whole_words_treat_accented_letters_as_letters(): void {
		$this->assertSame( 0, $this->replace( 'café', 'caf', 'shop', whole_words: true )->count );
	}

	public function test_whole_words_only_bound_word_characters(): void {
		$result = $this->replace( 'see example.test/page', 'example.test/', 'example.org/', whole_words: true );

		$this->assertSame( 'see example.org/page', $result->value );
	}

	public function test_multibyte_content(): void {
		$result = $this->replace( 'Grüße aus München — 日本語テキスト', 'München', 'Köln' );

		$this->assertSame( 'Grüße aus Köln — 日本語テキスト', $result->value );
	}

	public function test_invalid_utf8_value_is_still_replaced_byte_for_byte(): void {
		$value  = "caf\xE9 Hello \xFF";
		$result = $this->replace( $value, 'hello', 'Bye', case_sensitive: false );

		$this->assertSame( "caf\xE9 Bye \xFF", $result->value );
	}

	public function test_replacement_containing_search_is_applied_once(): void {
		$result = $this->replace( 'example.test', 'example.test', 'www.example.test' );

		$this->assertSame( 'www.example.test', $result->value );
		$this->assertSame( 1, $result->count );
	}

	public function test_several_pairs_are_applied_in_one_pass_without_chaining(): void {
		$replacer = new Replacer( new Replacement( array( array( 'apple', 'banana' ), array( 'banana', 'cherry' ) ) ) );
		$result   = $replacer->replace( 'apple banana' );

		$this->assertSame( 'banana cherry', $result->value );
		$this->assertSame( 2, $result->count );
	}

	public function test_the_longest_overlapping_search_wins(): void {
		$replacer = new Replacer(
			new Replacement(
				array(
					array( 'example.test', 'example.org' ),
					array( 'www.example.test', 'example.org' ),
				)
			)
		);

		$this->assertSame( 'example.org and example.org', $replacer->replace( 'www.example.test and example.test' )->value );
	}

	public function test_json_with_escaped_slashes(): void {
		$value  = json_encode( array( 'url' => 'https://staging.test/page' ) );
		$result = $this->replace( (string) $value, 'https://staging.test', 'https://example.test' );

		$this->assertSame( json_encode( array( 'url' => 'https://example.test/page' ) ), $result->value );
		$this->assertSame( 1, $result->count );
	}

	public function test_json_with_unicode_escapes(): void {
		$value  = json_encode( array( 'title' => 'Café Olé' ) );
		$result = $this->replace( (string) $value, 'Café', 'Bar' );

		$this->assertSame( json_encode( array( 'title' => 'Bar Olé' ) ), $result->value );
	}

	public function test_json_escapes_quotes_in_replacement(): void {
		$value  = json_encode( array( 'html' => '<a href="https://old.test">x</a>' ) );
		$result = $this->replace( (string) $value, 'href="https://old.test"', 'href="https://new.test" rel="nofollow"' );

		$this->assertSame( array( 'html' => '<a href="https://new.test" rel="nofollow">x</a>' ), json_decode( $result->value, true ) );
	}

	public function test_skips_replacement_that_would_break_json(): void {
		$value  = '{"label":"old"}';
		$result = $this->replace( $value, 'old', 'say "hi"' );

		$this->assertSame( $value, $result->value );
		$this->assertSame( 0, $result->count );
		$this->assertSame( ReplaceResult::BROKEN_JSON, $result->skipped );
	}

	public function test_url_variants(): void {
		$result = $this->replace(
			'https://staging.test/a http://staging.test/b //staging.test/c',
			'https://staging.test',
			'https://example.test',
			url_variants: true
		);

		$this->assertSame( 'https://example.test/a https://example.test/b //example.test/c', $result->value );
		$this->assertSame( 3, $result->count );
	}

	public function test_large_value(): void {
		$value  = str_repeat( 'Lorem ipsum https://old.test dolor. ', 50000 );
		$result = $this->replace( $value, 'https://old.test', 'https://new.test' );

		$this->assertSame( 50000, $result->count );
		$this->assertSame( str_replace( 'https://old.test', 'https://new.test', $value ), $result->value );
	}
}
