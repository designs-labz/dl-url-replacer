<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate\Tests\Unit;

use CraftRoq\Relocate\Replace\Replacement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReplacementTest extends TestCase {

	public function test_rejects_empty_search(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( array( array( '', 'new' ) ) );
	}

	public function test_rejects_identical_values(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( array( array( 'same', 'same' ) ) );
	}

	public function test_allows_case_only_change(): void {
		$replacement = new Replacement( array( array( 'Craftroq', 'CraftRoq' ) ) );
		$this->assertSame( 'Craftroq', $replacement->pairs[0]['search'] );
	}

	public function test_allows_empty_replacement(): void {
		$replacement = new Replacement( array( array( 'remove me', '' ) ) );
		$this->assertSame( '', $replacement->pairs[0]['replace'] );
	}

	public function test_rejects_invalid_utf8(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( array( array( "bad\xC3\x28", 'new' ) ) );
	}

	public function test_plain_text_has_a_single_pair(): void {
		$replacement = new Replacement( array( array( 'Hello', 'Goodbye' ) ) );

		$this->assertSame(
			array(
				array(
					'search'  => 'Hello',
					'replace' => 'Goodbye',
				),
			),
			$replacement->pairs
		);
	}

	public function test_adds_json_escaped_pairs(): void {
		$replacement = new Replacement( array( array( 'https://old.test/café', 'https://new.test/café' ) ) );

		$this->assertSame(
			array(
				array(
					'search'  => 'https://old.test/café',
					'replace' => 'https://new.test/café',
				),
				array(
					'search'  => 'https:\/\/old.test\/café',
					'replace' => 'https:\/\/new.test\/café',
				),
				array(
					'search'  => 'https:\/\/old.test\/caf\u00e9',
					'replace' => 'https:\/\/new.test\/caf\u00e9',
				),
			),
			$replacement->pairs
		);
	}

	public function test_url_variants_cover_other_scheme_and_protocol_relative(): void {
		$searches = array_column( ( new Replacement( array( array( 'https://staging.test', 'https://example.test' ) ), url_variants: true ) )->pairs, 'replace', 'search' );

		$this->assertSame( 'https://example.test', $searches['https://staging.test'] );
		$this->assertSame( 'https://example.test', $searches['http://staging.test'] );
		$this->assertSame( '//example.test', $searches['//staging.test'] );
	}

	public function test_url_variants_skip_pairs_that_change_nothing(): void {
		$searches = array_column( ( new Replacement( array( array( 'http://example.test', 'https://example.test' ) ), url_variants: true ) )->pairs, 'search' );

		$this->assertNotContains( 'https://example.test', $searches );
		$this->assertNotContains( '//example.test', $searches );
	}

	public function test_several_pairs_each_get_their_json_forms(): void {
		$replacement = new Replacement(
			array(
				array( 'https://old.test', 'https://new.test' ),
				array( '/home/old', '/home/new' ),
			)
		);

		$this->assertSame(
			array( 'https://old.test', '/home/old', 'https:\/\/old.test', '\/home\/old' ),
			array_column( $replacement->pairs, 'search' )
		);
	}

	public function test_rejects_the_same_search_twice(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( array( array( 'old', 'a' ), array( 'old', 'b' ) ) );
	}

	public function test_rejects_searches_that_only_differ_in_case_when_ignoring_case(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( array( array( 'Old', 'a' ), array( 'old', 'b' ) ), false );
	}

	public function test_rejects_an_empty_list(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( array() );
	}

	public function test_url_variants_ignore_non_urls(): void {
		$replacement = new Replacement( array( array( 'staging.test', 'example.test' ) ), url_variants: true );

		$this->assertCount( 1, $replacement->pairs );
	}
}
