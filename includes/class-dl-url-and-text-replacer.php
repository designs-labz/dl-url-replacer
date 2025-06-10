<?php
namespace DesignsLabz\URLReplacer;

defined('ABSPATH') || exit;

/**
 * Main class for DL URL Replacer
 */
class Main
{
	/**
	 * public function.
	 *
	 * @return void
	 */
	public function run()
	{
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	/**
	 * public function.
	 *
	 * @return void
	 */
	public function add_menu()
	{
		add_menu_page(__('DL URL Replacer', 'dl-url-and-text-replacer') , __('DL URL Replacer', 'dl-url-and-text-replacer') , 'manage_options', 'dl-url-and-text-replacer', [$this, 'render_admin_page'], 'dashicons-admin-network', 80);
	}

	/**
	 * public function.
	 *
	 * @return void
	 */
	public function render_admin_page()
	{
		include plugin_dir_path(__FILE__) . '../admin/views/main.php';
	}

	/**
	 * public function.
	 *
	 * @return void
	 */
	public function enqueue_assets($hook)
	{
		if (strpos($hook, 'dl-url-and-text-replacer') === false) return;

		wp_enqueue_style( 'dl-url-and-text-replacer-style', plugin_dir_url( __FILE__ ) . '../admin/assets/css/style.css', array(), DL_URL_REPLACER_VERSION );

		wp_enqueue_script( 'dl-url-and-text-replacer-script', plugin_dir_url( __FILE__ ) . '../admin/assets/js/script.bundle.js', array( 'jquery' ), DL_URL_REPLACER_VERSION, true );

		wp_localize_script('dl-url-and-text-replacer-script', 'dlUrlReplacerAjax', ['ajax_url' => admin_url('admin-ajax.php') ]);
	}
}
