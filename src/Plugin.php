<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate;

use DesignsLabz\Relocate\Admin\Admin;
use DesignsLabz\Relocate\Database\Schema;

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
	public const CAPABILITY = 'dlz_relocate_manage';

	public function __construct(
		private string $file,
		private \wpdb $wpdb
	) {}

	public function register(): void {
		add_filter( 'map_meta_cap', array( $this, 'map_capability' ), 10, 3 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Installer( $this->wpdb ) )->register();

		if ( is_admin() ) {
			( new Settings() )->register();
			( new Admin( $this->file, new Schema( $this->wpdb ) ) )->register();
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
		load_plugin_textdomain( 'designslabz-relocate', false, dirname( plugin_basename( $this->file ) ) . '/languages' );
	}
}
