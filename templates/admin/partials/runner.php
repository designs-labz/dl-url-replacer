<?php
/**
 * Progress, results and confirmation dialog, driven by assets/js/search-replace.js.
 *
 * Shared by Search & Replace and the job page. Pass $args['job_data'] to
 * render an existing job straight away.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="dlz-runner"<?php echo isset( $args['job_data'] ) ? ' data-job="' . esc_attr( (string) wp_json_encode( $args['job_data'] ) ) . '"' : ''; ?>>
	<div id="dlz-notices"></div>

	<section id="dlz-progress" class="dlz-card dlz-progress" hidden aria-labelledby="dlz-progress-heading">
		<div class="dlz-progress-head">
			<div>
				<h3 id="dlz-progress-heading" class="dlz-card-title"><?php esc_html_e( 'Dry run in progress', 'designslabz-relocate' ); ?></h3>
				<p id="dlz-progress-text" class="dlz-progress-text"></p>
			</div>
			<span id="dlz-progress-percent" class="dlz-progress-percent" aria-hidden="true">0%</span>
		</div>

		<div id="dlz-progress-bar" class="dlz-bar is-indeterminate" role="progressbar" aria-labelledby="dlz-progress-heading" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
			<div class="dlz-bar-fill"></div>
		</div>

		<dl class="dlz-progress-stats">
			<div><dt><?php esc_html_e( 'Rows scanned', 'designslabz-relocate' ); ?></dt><dd id="dlz-stat-scanned">0</dd></div>
			<div><dt id="dlz-stat-changed-label"><?php esc_html_e( 'Rows to change', 'designslabz-relocate' ); ?></dt><dd id="dlz-stat-changed">0</dd></div>
			<div><dt><?php esc_html_e( 'Replacements', 'designslabz-relocate' ); ?></dt><dd id="dlz-stat-replacements">0</dd></div>
			<div><dt><?php esc_html_e( 'Elapsed', 'designslabz-relocate' ); ?></dt><dd id="dlz-stat-elapsed">0:00</dd></div>
			<div><dt><?php esc_html_e( 'Remaining', 'designslabz-relocate' ); ?></dt><dd id="dlz-stat-remaining">—</dd></div>
		</dl>

		<details class="dlz-progress-details">
			<summary><?php esc_html_e( 'Tables', 'designslabz-relocate' ); ?> <span id="dlz-progress-table-count"></span></summary>
			<ol id="dlz-progress-tables" class="dlz-progress-tables"></ol>
		</details>

		<p class="dlz-progress-actions">
			<button type="button" class="button" id="dlz-cancel"><?php esc_html_e( 'Cancel', 'designslabz-relocate' ); ?></button>
		</p>
	</section>

	<div id="dlz-results" class="dlz-results" hidden></div>

	<dialog id="dlz-confirm" class="dlz-dialog" aria-labelledby="dlz-confirm-title">
		<form method="dialog">
			<h2 id="dlz-confirm-title"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Replace in the database?', 'designslabz-relocate' ); ?></h2>
			<div id="dlz-confirm-summary"></div>
			<ul id="dlz-confirm-warnings" class="dlz-warnings"></ul>
			<p>
				<label class="dlz-option">
					<input type="checkbox" id="dlz-confirm-before-image" checked>
					<span><?php esc_html_e( 'Save the original value of everything that changes to a downloadable file (recommended)', 'designslabz-relocate' ); ?></span>
				</label>
			</p>
			<p>
				<label class="dlz-option">
					<input type="checkbox" id="dlz-confirm-backup">
					<span><?php esc_html_e( 'I have a recent backup of this database. I understand the replacement is written straight to the database and is not undone automatically.', 'designslabz-relocate' ); ?></span>
				</label>
			</p>
			<p class="dlz-dialog-actions">
				<button type="submit" value="cancel" class="button" formnovalidate><?php esc_html_e( 'Cancel', 'designslabz-relocate' ); ?></button>
				<button type="submit" value="confirm" class="button button-primary" id="dlz-confirm-submit" disabled><?php esc_html_e( 'Replace now', 'designslabz-relocate' ); ?></button>
			</p>
		</form>
	</dialog>
</div>
