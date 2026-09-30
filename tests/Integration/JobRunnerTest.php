<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Tests\Integration;

use DesignsLabz\Relocate\Database\Schema;
use DesignsLabz\Relocate\Jobs\BeforeImage;
use DesignsLabz\Relocate\Jobs\Job;
use DesignsLabz\Relocate\Jobs\JobRepository;
use DesignsLabz\Relocate\Jobs\JobRunner;
use DesignsLabz\Relocate\Jobs\JobStatus;
use DesignsLabz\Relocate\Jobs\Report;
use DesignsLabz\Relocate\Logger;
use DesignsLabz\Relocate\Replace\Replacer;
use DesignsLabz\Relocate\Settings;
use WP_UnitTestCase;

/**
 * Runs dry-run jobs against real tables.
 *
 * Fixture tables are created once per class, outside the per-test transaction:
 * the test framework turns CREATE TABLE into temporary tables otherwise, and
 * those are invisible to information_schema.
 */
final class JobRunnerTest extends WP_UnitTestCase {

	private const OLD = 'http://old.test';
	private const NEW = 'https://new.example';

	private const CONTENT_ROWS = 3000;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		global $wpdb;

		$content = self::table( 'content' );
		$wpdb->query(
			"CREATE TABLE {$content} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
				body longtext COLLATE utf8mb4_unicode_ci,
				meta longtext COLLATE utf8mb4_unicode_ci,
				code varchar(100) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
				PRIMARY KEY (id)
			) DEFAULT CHARSET=utf8mb4"
		);

		$values = array();
		for ( $i = 1; $i <= self::CONTENT_ROWS; $i++ ) {
			$body = 0 === $i % 3 ? 'See ' . self::OLD . "/post-{$i} and " . self::OLD . '/über' : "Plain row {$i} with no link";
			$meta = match ( $i % 7 ) {
				0       => serialize(
					array(
						'url'    => self::OLD . "/{$i}",
						'nested' => array( self::OLD ),
					)
				),
				1       => wp_json_encode( array( 'link' => self::OLD . "/{$i}" ) ),
				2       => 'a:1:{i:0;s:99:"' . self::OLD . '";}',
				default => null,
			};
			$code = 0 === $i % 10 ? 'HTTP://OLD.TEST' : "code-{$i}";

			$values[] = $wpdb->prepare( '(%d, %s, %s, %s, %s)', $i, "Title {$i}", $body, $meta, $code );

			if ( count( $values ) === 500 || self::CONTENT_ROWS === $i ) {
				$wpdb->query( "INSERT INTO {$content} (id, title, body, meta, code) VALUES " . implode( ',', $values ) );
				$values = array();
			}
		}

		// Prepared values write NULL for meta as an empty string; put the NULLs back.
		$wpdb->query( "UPDATE {$content} SET meta = NULL WHERE meta = ''" );

		$pairs = self::table( 'pairs' );
		$wpdb->query(
			"CREATE TABLE {$pairs} (
				group_id int(11) NOT NULL,
				name varchar(50) NOT NULL,
				value text,
				PRIMARY KEY (group_id, name)
			) DEFAULT CHARSET=utf8mb4"
		);
		for ( $group = 1; $group <= 50; $group++ ) {
			$rows = array();
			foreach ( array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h' ) as $name ) {
				$rows[] = $wpdb->prepare( '(%d, %s, %s)', $group, $name, "{$group}{$name} " . self::OLD );
			}
			$wpdb->query( "INSERT INTO {$pairs} VALUES " . implode( ',', $rows ) );
		}

		$unique = self::table( 'unique' );
		$wpdb->query( "CREATE TABLE {$unique} (slug varchar(50) NOT NULL, value text, UNIQUE KEY slug (slug)) DEFAULT CHARSET=utf8mb4" );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$unique} VALUES ('one', %s), ('two', 'nothing')", self::OLD ) );

		$no_key = self::table( 'nokey' );
		$wpdb->query( "CREATE TABLE {$no_key} (value text) DEFAULT CHARSET=utf8mb4" );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$no_key} VALUES (%s)", self::OLD ) );
	}

	public static function tear_down_after_class(): void {
		global $wpdb;

		foreach ( array( 'content', 'pairs', 'unique', 'nokey' ) as $name ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $name ) );
		}

		parent::tear_down_after_class();
	}

	public function set_up(): void {
		parent::set_up();
		update_option( Settings::OPTION, array( 'batch_size' => 100 ) );
		add_filter( 'dlz_relocate_step_seconds', '__return_zero' );
	}

	public function test_dry_run_matches_a_full_scan_and_changes_nothing(): void {
		$tables   = array( self::table( 'content' ), self::table( 'pairs' ), self::table( 'unique' ) );
		$checksum = $this->checksums( $tables );
		$job      = $this->create_job( $tables );

		[ $job, $steps ] = $this->run_to_end( $job );

		$this->assertSame( JobStatus::Completed, $job->status );
		$this->assertSame( 100, $job->progress() );
		$this->assertGreaterThan( 30, $steps, 'The job should have been processed in many separate steps.' );
		$this->assertSame( $this->expected_totals( $job ), $job->report->totals() );
		$this->assertSame( $checksum, $this->checksums( $tables ), 'A dry run must not modify any table.' );
	}

	public function test_counts_per_column_and_skipped_values(): void {
		[ $job ] = $this->run_to_end( $this->create_job( array( self::table( 'content' ) ) ) );

		$stats = $job->report->tables()[ self::table( 'content' ) ];

		$this->assertSame( self::CONTENT_ROWS, $stats['rows_scanned'] );
		$this->assertSame( 2000, $stats['replacements'] - $stats['columns']['meta'] );
		$this->assertArrayNotHasKey( 'code', $stats['columns'], 'Case-sensitive search must not match HTTP://OLD.TEST.' );
		$this->assertSame( array( 'invalid_serialized' => 429 ), $stats['skipped'] );
		$this->assertCount( 20, $job->report->samples() );
	}

	public function test_excluded_columns_are_not_searched(): void {
		[ $job ] = $this->run_to_end( $this->create_job( array( self::table( 'content' ) ), array( 'exclude_columns' => array( self::table( 'content' ) => array( 'body' ) ) ) ) );

		$stats = $job->report->tables()[ self::table( 'content' ) ];

		$this->assertArrayNotHasKey( 'body', $stats['columns'] );
		$this->assertArrayHasKey( 'meta', $stats['columns'] );
	}

	public function test_case_insensitive_search_finds_matches_in_binary_collation_columns(): void {
		[ $job ] = $this->run_to_end( $this->create_job( array( self::table( 'content' ) ), array( 'case_sensitive' => false ) ) );

		$this->assertSame( 300, $job->report->tables()[ self::table( 'content' ) ]['columns']['code'] );
	}

	public function test_composite_primary_key_table_is_walked_completely(): void {
		[ $job ] = $this->run_to_end( $this->create_job( array( self::table( 'pairs' ) ) ) );

		$stats = $job->report->tables()[ self::table( 'pairs' ) ];

		$this->assertSame( 400, $stats['rows_scanned'] );
		$this->assertSame( 400, $stats['rows_changed'] );
	}

	public function test_table_with_only_a_unique_key_is_processed(): void {
		[ $job ] = $this->run_to_end( $this->create_job( array( self::table( 'unique' ) ) ) );

		$this->assertSame( 1, $job->report->tables()[ self::table( 'unique' ) ]['rows_changed'] );
	}

	public function test_table_without_a_key_is_skipped_with_a_note(): void {
		[ $job ] = $this->run_to_end( $this->create_job( array( self::table( 'nokey' ) ) ) );

		$this->assertSame( JobStatus::Completed, $job->status );
		$this->assertSame( Report::NOTE_NO_KEY, $job->report->tables()[ self::table( 'nokey' ) ]['note'] );
	}

	public function test_missing_table_is_skipped_with_a_note(): void {
		[ $job ] = $this->run_to_end( $this->create_job( array( self::table( 'gone' ) ) ) );

		$this->assertSame( Report::NOTE_MISSING_TABLE, $job->report->tables()[ self::table( 'gone' ) ]['note'] );
	}

	public function test_post_guids_are_skipped_unless_asked(): void {
		global $wpdb;

		$post_id = self::factory()->post->create( array( 'post_content' => 'Nothing here' ) );
		$wpdb->update( $wpdb->posts, array( 'guid' => self::OLD . '/?p=' . $post_id ), array( 'ID' => $post_id ) );

		[ $skipped ]  = $this->run_to_end( $this->create_job( array( $wpdb->posts ) ) );
		[ $included ] = $this->run_to_end( $this->create_job( array( $wpdb->posts ), array( 'skip_guids' => false ) ) );

		$this->assertSame( 0, $skipped->report->totals()['replacements'] );
		$this->assertSame( 1, $included->report->tables()[ $wpdb->posts ]['columns']['guid'] );
	}

	public function test_database_error_fails_the_job_and_is_logged(): void {
		global $wpdb;

		$table = self::table( 'content' );
		$break = fn( string $query ): string => str_contains( $query, 'LIKE' ) && str_contains( $query, $table ) ? $query . ' BROKEN SQL' : $query;
		add_filter( 'query', $break );

		$suppress = $wpdb->suppress_errors( true );
		[ $job ]  = $this->run_to_end( $this->create_job( array( $table ) ) );
		$wpdb->suppress_errors( $suppress );

		$this->assertSame( JobStatus::Failed, $job->status );
		$this->assertStringContainsString( $table, (string) $job->error_message );
		$this->assertSame(
			'Job failed.',
			$wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$wpdb->prefix}dlz_relocate_log WHERE job_id = %d AND level = 'error'", $job->id ) )
		);
	}

	public function test_a_job_locked_by_another_connection_is_not_processed(): void {
		global $wpdb;

		$job   = $this->create_job( array( self::table( 'unique' ) ) );
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$name  = $wpdb->prefix . 'dlz_relocate_jobs:' . $job->id;

		$this->assertSame( '1', $other->get_var( $other->prepare( 'SELECT GET_LOCK(SHA1(CONCAT(DATABASE(), %s)), 0)', $name ) ) );
		$this->assertNull( $this->runner()->step( $job ) );

		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(SHA1(CONCAT(DATABASE(), %s)))', $name ) );
		$this->assertNotNull( $this->runner()->step( $job ) );
	}

	public function test_cancel_stops_the_job(): void {
		$job = $this->create_job( array( self::table( 'content' ) ) );
		$job = $this->runner()->step( $job );
		$job = $this->runner()->cancel( $job );

		$this->assertSame( JobStatus::Cancelled, $job->status );
		$this->assertSame( JobStatus::Cancelled, $this->runner()->step( $job )->status, 'A cancelled job must not run again.' );
	}

	/**
	 * @param list<string>         $tables
	 * @param array<string, mixed> $settings
	 */
	private function create_job( array $tables, array $settings = array() ): Job {
		global $wpdb;

		return ( new JobRepository( $wpdb ) )->create(
			new Job(
				0,
				null,
				true,
				JobStatus::Pending,
				self::OLD,
				self::NEW,
				array_merge(
					array(
						'case_sensitive' => true,
						'whole_words'    => false,
						'url_variants'   => false,
						'skip_guids'     => true,
						'tables'         => $tables,
					),
					$settings
				),
				array(
					'table_index' => 0,
					'last_key'    => null,
					'total_rows'  => 0,
				),
				new Report(),
				1,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * A new runner for every step, and no time budget (see set_up): one window per step,
	 * the way separate REST requests resume from the saved position.
	 *
	 * @return array{0: Job, 1: int}
	 */
	private function run_to_end( Job $job ): array {
		$steps = 0;

		while ( ! $job->status->is_finished() ) {
			$job = $this->runner()->step( $job );
			$this->assertNotNull( $job );
			++$steps;
		}

		return array( $job, $steps );
	}

	private function runner(): JobRunner {
		global $wpdb;

		$schema = new Schema( $wpdb );

		return new JobRunner( $wpdb, $schema, new JobRepository( $wpdb ), new Settings(), new Logger( $wpdb ), new BeforeImage( $wpdb ) );
	}

	/**
	 * What the job should report, worked out by running the Replacer over every
	 * value of every row in one go, with no windows or LIKE pre-filtering.
	 *
	 * @return array{rows_scanned: int, rows_changed: int, replacements: int, skipped: int}
	 */
	private function expected_totals( Job $job ): array {
		global $wpdb;

		$replacer = new Replacer( $job->replacement() );
		$schema   = new Schema( $wpdb );
		$totals   = array_fill_keys( array( 'rows_scanned', 'rows_changed', 'replacements', 'skipped' ), 0 );

		foreach ( $job->settings['tables'] as $table ) {
			$columns = array_keys( $schema->describe( $table )->columns );

			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A ) as $row ) {
				++$totals['rows_scanned'];
				$changed = false;

				foreach ( $columns as $column ) {
					if ( null === $row[ $column ] ) {
						continue;
					}

					$result = $replacer->replace( $row[ $column ] );

					if ( null !== $result->skipped ) {
						++$totals['skipped'];
					} elseif ( $result->value !== $row[ $column ] ) {
						$changed                 = true;
						$totals['replacements'] += $result->count;
					}
				}

				$totals['rows_changed'] += (int) $changed;
			}
		}

		return $totals;
	}

	/**
	 * @param list<string> $tables
	 * @return array<string, string>
	 */
	private function checksums( array $tables ): array {
		global $wpdb;

		$checksums = array();
		foreach ( $tables as $table ) {
			$checksums[ $table ] = (string) $wpdb->get_var( $wpdb->prepare( 'CHECKSUM TABLE %i', $table ), 1 );
		}

		return $checksums;
	}

	private static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'dlz_fixture_' . $name;
	}
}
