<?php
/**
 * Plugin Name:       CR Relocate DB
 * Plugin URI:        https://github.com/designs-labz/dl-relocate-db
 * Description:       Safely search and replace URLs and text across your WordPress database, with serialized data support, dry runs and an operation history.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            CraftRoq
 * Author URI:        https://craftroq.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       cr-relocate-db
 * Domain Path:       /languages
 *
 * @package CraftRoq\Relocate
 */

defined( 'ABSPATH' ) || exit;

// WordPress checks "Requires PHP" on activation only. A later PHP downgrade would
// otherwise fatal the whole site on the first enum or readonly property.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			wp_admin_notice(
				esc_html__( 'CR Relocate DB requires PHP 8.1 or newer and is not running.', 'cr-relocate-db' ),
				array( 'type' => 'error' )
			);
		}
	);
	return;
}

if ( is_multisite() ) {
	add_action(
		'admin_notices',
		function () {
			wp_admin_notice(
				esc_html__( 'CR Relocate DB does not support WordPress Multisite yet, so it is not running on this network.', 'cr-relocate-db' ),
				array( 'type' => 'warning' )
			);
		}
	);
	return;
}

spl_autoload_register(
	function ( string $class_name ): void {
		$prefix = 'CraftRoq\\Relocate\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$path = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

register_activation_hook(
	__FILE__,
	function (): void {
		global $wpdb;
		( new CraftRoq\Relocate\Installer( $wpdb ) )->maybe_upgrade();
	}
);

register_deactivation_hook( __FILE__, array( CraftRoq\Relocate\Jobs\Cleanup::class, 'unschedule' ) );

add_action(
	'plugins_loaded',
	function (): void {
		global $wpdb;
		( new CraftRoq\Relocate\Plugin( __FILE__, $wpdb ) )->register();
	}
);
