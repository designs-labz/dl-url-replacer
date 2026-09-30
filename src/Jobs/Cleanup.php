<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Jobs;

use DesignsLabz\Relocate\Logger;
use DesignsLabz\Relocate\Settings;
use RuntimeException;

/**
 * Deletes finished jobs, their before-image files and log entries once they
 * are older than the retention setting. Runs daily on WP-Cron; nothing here
 * is urgent, so a late run does no harm.
 */
final class Cleanup {

	public const HOOK = 'dlz_relocate_cleanup';

	public function __construct(
		private JobRepository $jobs,
		private BeforeImage $before_images,
		private Logger $logger,
		private Settings $settings
	) {}

	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function run(): void {
		$days = $this->settings->retention_days();

		if ( 0 === $days ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		try {
			$files = $this->jobs->delete_finished_before( $cutoff );
		} catch ( RuntimeException $e ) {
			$this->logger->error( 'Clean-up failed.', array( 'error' => $e->getMessage() ) );
			return;
		}

		foreach ( $files as $file ) {
			$this->before_images->delete( $file );
		}

		$this->logger->delete_before( $cutoff );
	}
}
