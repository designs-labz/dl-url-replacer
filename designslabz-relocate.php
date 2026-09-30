<?php
/**
 * Plugin Name:       DesignsLabz Relocate
 * Plugin URI:        https://github.com/team-designslabz/dl-url-replacer
 * Description:       Safely search and replace URLs and text across your WordPress database, with serialized data support, dry runs and an operation history.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            DesignsLabz
 * Author URI:        https://designslabz.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       designslabz-relocate
 * Domain Path:       /languages
 *
 * @package DesignsLabz\Relocate
 */

defined( 'ABSPATH' ) || exit;

// WordPress checks "Requires PHP" on activation only. A later PHP downgrade would
// otherwise fatal the whole site on the first enum or readonly property.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			wp_admin_notice(
				esc_html__( 'DesignsLabz Relocate requires PHP 8.1 or newer and is not running.', 'designslabz-relocate' ),
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
				esc_html__( 'DesignsLabz Relocate does not support WordPress Multisite yet, so it is not running on this network.', 'designslabz-relocate' ),
				array( 'type' => 'warning' )
			);
		}
	);
	return;
}

spl_autoload_register(
	function ( string $class_name ): void {
		$prefix = 'DesignsLabz\\Relocate\\';

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
		( new DesignsLabz\Relocate\Installer( $wpdb ) )->maybe_upgrade();
	}
);

add_action(
	'plugins_loaded',
	function (): void {
		global $wpdb;
		( new DesignsLabz\Relocate\Plugin( __FILE__, $wpdb ) )->register();
	}
);
