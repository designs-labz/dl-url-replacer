<?php
/**
 * Removes plugin data on uninstall, if the site owner opted in.
 *
 * @package DesignsLabz\Relocate
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Installer.php';
require_once __DIR__ . '/src/Jobs/BeforeImage.php';

if ( ( new DesignsLabz\Relocate\Settings() )->delete_data_on_uninstall() ) {
	global $wpdb;
	( new DesignsLabz\Relocate\Installer( $wpdb ) )->uninstall();
}
