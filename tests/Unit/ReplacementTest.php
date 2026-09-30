<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Tests\Unit;

use DesignsLabz\Relocate\Replace\Replacement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReplacementTest extends TestCase {

	public function test_rejects_empty_search(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( '', 'new' );
	}

	public function test_rejects_identical_values(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( 'same', 'same' );
	}

	public function test_allows_case_only_change(): void {
		$replacement = new Replacement( 'Designslabz', 'DesignsLabz' );
		$this->assertSame( 'Designslabz', $replacement->pairs[0]['search'] );
	}

	public function test_allows_empty_replacement(): void {
		$replacement = new Replacement( 'remove me', '' );
		$this->assertSame( '', $replacement->pairs[0]['replace'] );
	}

	public function test_rejects_invalid_utf8(): void {
		$this->expectException( InvalidArgumentException::class );
		new Replacement( "bad\xC3\x28", 'new' );
	}

	public function test_plain_text_has_a_single_pair(): void {
		$replacement = new Replacement( 'Hello', 'Goodbye' );

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
		$replacement = new Replacement( 'https://old.test/café', 'https://new.test/café' );

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
		$searches = array_column( ( new Replacement( 'https://staging.test', 'https://example.test', url_variants: true ) )->pairs, 'replace', 'search' );

		$this->assertSame( 'https://example.test', $searches['https://staging.test'] );
		$this->assertSame( 'https://example.test', $searches['http://staging.test'] );
		$this->assertSame( '//example.test', $searches['//staging.test'] );
	}

	public function test_url_variants_skip_pairs_that_change_nothing(): void {
		$searches = array_column( ( new Replacement( 'http://example.test', 'https://example.test', url_variants: true ) )->pairs, 'search' );

		$this->assertNotContains( 'https://example.test', $searches );
		$this->assertNotContains( '//example.test', $searches );
	}

	public function test_url_variants_ignore_non_urls(): void {
		$replacement = new Replacement( 'staging.test', 'example.test', url_variants: true );

		$this->assertCount( 1, $replacement->pairs );
	}
}
