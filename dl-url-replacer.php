<?php
/**
 * Plugin Name:           DL URL and Text Replacer
 * Plugin URI:            https://github.com/team-designslabz/dl-url-replacer
 * Description:           Easily replace URLs and custom text within your WordPress database. Includes AJAX-based progress, detailed logging, and a modern UI for efficient management.
 * Version:               1.0.0
 * Requires PHP:          7.4
 * Requires at least:     6.1
 * Tested up to:          6.8.2
 * Author:                DesignsLabz
 * Author URI:            https://designslabz.com/
 * License:               GPL-3.0-or-later
 * License URI:           https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:           dl-url-replacer
 * GitHub Plugin URI:     https://github.com/team-designslabz/dl-url-replacer
 * GitHub Branch:         main
 */

defined("ABSPATH") || exit();

// Define plugin version
/**
 * Plugin version.
 *
 * @since 1.0.0
 *
 * @var string
 */
define( 'DL_URL_REPLACER_VERSION', '1.0.0' );

// Load main class
require_once plugin_dir_path(__FILE__) . "includes/class-dl-url-replacer.php";

// Initialize plugin
/**
 * Initialize the DL URL Replacer plugin.
 *
 * @return void
 */
function dl_url_replacer_init()
{
	$plugin = new \DesignsLabz\URLReplacer\Main();
	$plugin->run();
}
add_action("plugins_loaded", "dl_url_replacer_init");

// Load AJAX handler
require_once plugin_dir_path(__FILE__) . "includes/class-ajax-handler.php";
