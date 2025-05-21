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

		$type = sanitize_text_field($_POST['type']);
		$find = esc_sql($_POST['find']);
		$replace = esc_sql($_POST['replace']);

		$tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
		$results = [];

		foreach ($tables as $tableRow)
		{
			$table = $tableRow[0];
			$columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A);
			foreach ($columns as $column)
			{
				if (strpos($column['Type'], 'text') !== false || strpos($column['Type'], 'char') !== false)
				{
					$query = "UPDATE `$table` SET `{$column['Field']}` = REPLACE(`{$column['Field']}`, %s, %s)";
					$updated = $wpdb->query($wpdb->prepare($query, $find, $replace));
					if ($updated)
					{
						$results[] = ['table' => $table, 'column' => $column['Field'], 'rows_affected' => $updated];
					}
				}
			}
		}

		// Log to file
		file_put_contents(plugin_dir_path(__FILE__) . '../logs/log-' . date('Y-m-d') . '.txt', print_r($results, true) , FILE_APPEND);

		// Send JSON response
		if (empty($results))
		{
			wp_send_json_error(['message' => 'No replacements made.']);
		}
		else
		{
			wp_send_json_success(['message' => 'Replacements completed successfully.', 'results' => $results]);
		}
	}
}
new AjaxHandler();
