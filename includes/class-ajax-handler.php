<?php
namespace DesignsLabz\URLReplacer;

defined('ABSPATH') || exit;

class AjaxHandler
{
	/**
	 * public function.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action('wp_ajax_dl_run_replacement', [$this, 'run_replacement']);
	}

	/**
	 * public function.
	 *
	 * @return void
	 */
	public function run_replacement()
	{
		global $wpdb;

		$type    = sanitize_text_field(wp_unslash($_POST['type']));
		$find    = sanitize_text_field(esc_sql(wp_unslash($_POST['find'])));
		$replace = sanitize_text_field(esc_sql(wp_unslash($_POST['replace'])));


		$cache_key = 'dl_url_replacer_tables';
		$tables = wp_cache_get($cache_key);

		if ($tables === false) {
			$tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
			wp_cache_set($cache_key, $tables, '', 3600); // Cache for 1 hour
		}


		foreach ($tables as $tableRow)
		{
			$table = $tableRow[0];
			$columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A);
			foreach ($columns as $column)
			{
				if (strpos($column['Type'], 'text') !== false || strpos($column['Type'], 'char') !== false)
				{
					$updated = $wpdb->query(
						$wpdb->prepare(
							"UPDATE `$table` SET `{$column['Field']}` = REPLACE(`{$column['Field']}`, %s, %s)",
							$find,
							$replace
						)
					);
					if ($updated)
					{
						$results[] = ['table' => $table, 'column' => $column['Field'], 'rows_affected' => $updated];
					}
				}
			}
		}

		// Log to file with PHPCS-compliant date function and safe output
		$log_file = plugin_dir_path(__FILE__) . '../logs/log-' . gmdate('Y-m-d') . '.txt';
		file_put_contents($log_file, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), FILE_APPEND);

		wp_send_json_success($results);
	}
}
new AjaxHandler();
