<?php
declare( strict_types=1 );

namespace CraftRoq\Relocate;

use CraftRoq\Relocate\Admin\Admin;
use CraftRoq\Relocate\Cli\Command;
use CraftRoq\Relocate\Database\Schema;
use CraftRoq\Relocate\Jobs\BeforeImage;
use CraftRoq\Relocate\Jobs\Cleanup;
use CraftRoq\Relocate\Jobs\JobRepository;
use CraftRoq\Relocate\Jobs\JobRunner;
use CraftRoq\Relocate\Jobs\JobStarter;
use CraftRoq\Relocate\Rest\JobFormatter;
use CraftRoq\Relocate\Rest\JobsController;
use WP_CLI;

/**
 * Builds the plugin's objects and hooks them into WordPress.
 */
final class Plugin {

	public const VERSION = '0.1.0';

	/**
	 * Meta capability required for everything this plugin does.
	 *
	 * Mapped to manage_options + unfiltered_html: a search and replace can write
	 * arbitrary markup into the database, so it must not bypass a site's
	 * DISALLOW_UNFILTERED_HTML hardening.
	 */
	public const CAPABILITY = 'crq_relocate_manage';

	public function __construct(
		private string $file,
		private \wpdb $wpdb
	) {}

	public function register(): void {
		add_filter( 'map_meta_cap', array( $this, 'map_capability' ), 10, 3 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$settings  = new Settings();
		$schema    = new Schema( $this->wpdb );
		$logger    = new Logger( $this->wpdb );
		$jobs      = new JobRepository( $this->wpdb );
		$images    = new BeforeImage( $this->wpdb );
		$runner    = new JobRunner( $this->wpdb, $schema, $jobs, $settings, $logger, $images );
		$formatter = new JobFormatter( $this->wpdb, $jobs, $schema, $images );

		( new Installer( $this->wpdb ) )->register();
		( new Cleanup( $jobs, $images, $logger, $settings ) )->register();
		$starter = new JobStarter( $jobs, $runner, $schema, $images, $logger );

		( new JobsController( $jobs, $runner, $starter, $formatter, $logger ) )->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'crq', new Command( new Installer( $this->wpdb ), $schema, $jobs, $runner, $starter, $formatter ) );
		}

		if ( is_admin() ) {
			$settings->register();
			( new Admin( $this->file, $schema, $jobs, $images, $formatter, $logger, $settings ) )->register();
		}
	}

	/**
	 * @param string[] $caps    Primitive capabilities WordPress would check.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @return string[]
	 */
	public function map_capability( array $caps, string $cap, int $user_id ): array {
		if ( self::CAPABILITY !== $cap ) {
			return $caps;
		}

		return array_merge(
			map_meta_cap( 'manage_options', $user_id ),
			map_meta_cap( 'unfiltered_html', $user_id )
		);
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'cr-relocate-db', false, dirname( plugin_basename( $this->file ) ) . '/languages' );
	}
}
