<?php
/**
 * Boots WordPress with the plugin loaded for the integration suite.
 *
 * @package CraftRoq\Relocate
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . dirname( __DIR__ ) . '/wp-tests-config.php' );

$tests_dir = getenv( 'WP_PHPUNIT__DIR' );

require $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__, 2 ) . '/cr-relocate-db.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';

( new CraftRoq\Relocate\Installer( $GLOBALS['wpdb'] ) )->maybe_upgrade();
