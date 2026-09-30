<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Tests\Integration;

use DesignsLabz\Relocate\Installer;
use DesignsLabz\Relocate\Jobs\BeforeImage;
use DesignsLabz\Relocate\Jobs\Cleanup;
use DesignsLabz\Relocate\Jobs\Job;
use DesignsLabz\Relocate\Jobs\JobRepository;
use DesignsLabz\Relocate\Jobs\JobStatus;
use DesignsLabz\Relocate\Jobs\Report;
use DesignsLabz\Relocate\Logger;
use DesignsLabz\Relocate\Settings;
use WP_UnitTestCase;

final class HistoryTest extends WP_UnitTestCase {

	private JobRepository $jobs;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$this->jobs = new JobRepository( $wpdb );
	}

	public function tear_down(): void {
		global $wpdb;
		( new BeforeImage( $wpdb ) )->delete_all();

		parent::tear_down();
	}

	public function test_pages_are_newest_first_and_filter_by_type(): void {
		foreach ( range( 1, 25 ) as $i ) {
			$this->job( 0 === $i % 5 ? false : true );
		}

		[ $first, $total ] = $this->jobs->page( 1, 10 );
		[ $third ]         = $this->jobs->page( 3, 10 );
		[ $live, $lives ]  = $this->jobs->page( 1, 10, false );

		$this->assertSame( 25, $total );
		$this->assertCount( 10, $first );
		$this->assertCount( 5, $third );
		$this->assertGreaterThan( $first[1]->id, $first[0]->id );
		$this->assertSame( 5, $lives );
		$this->assertCount( 5, $live );
		$this->assertFalse( $live[0]->dry_run );
	}

	public function test_a_quiet_unfinished_job_is_interrupted(): void {
		$running = $this->job( true, JobStatus::Running );
		$stale   = $this->job( true, JobStatus::Running );
		$done    = $this->job( true, JobStatus::Completed );
		$failed  = $this->job( false, JobStatus::Failed );
		$this->age( $stale, 120 );
		$this->age( $done, 120 );

		$this->assertFalse( $this->jobs->find( $running->id )->is_interrupted() );
		$this->assertTrue( $this->jobs->find( $stale->id )->is_interrupted() );
		$this->assertFalse( $this->jobs->find( $done->id )->is_interrupted() );

		$attention = array_map( fn( Job $job ): int => $job->id, $this->jobs->needing_attention() );
		sort( $attention );

		$this->assertSame( array( $stale->id, $failed->id ), $attention );
	}

	public function test_cleanup_removes_old_finished_jobs_their_files_and_log_entries(): void {
		global $wpdb;

		$images = new BeforeImage( $wpdb );
		$logger = new Logger( $wpdb );

		$old               = $this->job( false, JobStatus::Completed );
		$old->before_image = $images->create( $old );
		$this->jobs->save( $old );
		$this->age( $old, 40 * DAY_IN_SECONDS );

		$old_unfinished = $this->job( false, JobStatus::Running );
		$this->age( $old_unfinished, 40 * DAY_IN_SECONDS );

		$recent = $this->job( true, JobStatus::Completed );

		$logger->info( 'Old entry.' );
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET created_at = %s', $wpdb->prefix . Installer::LOG_TABLE, gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ) );
		$logger->info( 'New entry.' );

		$path = $images->path( $old->before_image );
		$this->assertFileExists( $path );

		$this->cleanup()->run();

		$this->assertNull( $this->jobs->find( $old->id ) );
		$this->assertFileDoesNotExist( $path );
		$this->assertNotNull( $this->jobs->find( $old_unfinished->id ), 'Unfinished jobs are never deleted.' );
		$this->assertNotNull( $this->jobs->find( $recent->id ) );
		$this->assertSame( array( 'New entry.' ), array_column( $logger->entries( 1, 10 )[0], 'message' ) );
	}

	public function test_cleanup_does_nothing_when_retention_is_off(): void {
		update_option( Settings::OPTION, array( 'retention_days' => 0 ) );

		$old = $this->job( true, JobStatus::Completed );
		$this->age( $old, 400 * DAY_IN_SECONDS );

		$this->cleanup()->run();

		$this->assertNotNull( $this->jobs->find( $old->id ) );
	}

	public function test_cleanup_is_scheduled_daily(): void {
		Cleanup::unschedule();
		$this->cleanup()->schedule();

		$this->assertSame( 'daily', wp_get_schedule( Cleanup::HOOK ) );
	}

	private function cleanup(): Cleanup {
		global $wpdb;

		return new Cleanup( $this->jobs, new BeforeImage( $wpdb ), new Logger( $wpdb ), new Settings() );
	}

	private function job( bool $dry_run, JobStatus $status = JobStatus::Completed ): Job {
		$job = $this->jobs->create(
			new Job(
				0,
				null,
				$dry_run,
				$status,
				'old',
				'new',
				array(
					'case_sensitive' => true,
					'whole_words'    => false,
					'url_variants'   => false,
					'skip_guids'     => true,
					'tables'         => array( 'wptests_posts' ),
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

		return $job;
	}

	private function age( Job $job, int $seconds ): void {
		global $wpdb;

		$wpdb->update( $wpdb->prefix . Installer::JOBS_TABLE, array( 'updated_at' => gmdate( 'Y-m-d H:i:s', time() - $seconds ) ), array( 'id' => $job->id ) );
	}
}
