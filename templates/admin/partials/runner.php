<?php
/**
 * Progress, results and confirmation dialog, driven by assets/js/search-replace.js.
 *
 * Shared by Search & Replace and the job page. Pass $args['job_data'] to
 * render an existing job straight away.
 *
 * @package CraftRoq\Relocate
 *
 * @var array $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="crq-runner"<?php echo isset( $args['job_data'] ) ? ' data-job="' . esc_attr( (string) wp_json_encode( $args['job_data'] ) ) . '"' : ''; ?>>
	<div id="crq-notices"></div>

	<section id="crq-progress" class="crq-card crq-progress" hidden aria-labelledby="crq-progress-heading">
		<div class="crq-progress-head">
			<div>
				<h3 id="crq-progress-heading" class="crq-card-title"><?php esc_html_e( 'Dry run in progress', 'cr-relocate-db' ); ?></h3>
				<p id="crq-progress-text" class="crq-progress-text"></p>
			</div>
			<span id="crq-progress-percent" class="crq-progress-percent" aria-hidden="true">0%</span>
		</div>

		<div id="crq-progress-bar" class="crq-bar is-indeterminate" role="progressbar" aria-labelledby="crq-progress-heading" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
			<div class="crq-bar-fill"></div>
		</div>

		<dl class="crq-progress-stats">
			<div><dt><?php esc_html_e( 'Rows scanned', 'cr-relocate-db' ); ?></dt><dd id="crq-stat-scanned">0</dd></div>
			<div><dt id="crq-stat-changed-label"><?php esc_html_e( 'Rows to change', 'cr-relocate-db' ); ?></dt><dd id="crq-stat-changed">0</dd></div>
			<div><dt><?php esc_html_e( 'Replacements', 'cr-relocate-db' ); ?></dt><dd id="crq-stat-replacements">0</dd></div>
			<div><dt><?php esc_html_e( 'Elapsed', 'cr-relocate-db' ); ?></dt><dd id="crq-stat-elapsed">0:00</dd></div>
			<div><dt><?php esc_html_e( 'Remaining', 'cr-relocate-db' ); ?></dt><dd id="crq-stat-remaining">—</dd></div>
		</dl>

		<details class="crq-progress-details">
			<summary><?php esc_html_e( 'Tables', 'cr-relocate-db' ); ?> <span id="crq-progress-table-count"></span></summary>
			<ol id="crq-progress-tables" class="crq-progress-tables"></ol>
		</details>

		<p class="crq-progress-actions">
			<button type="button" class="button" id="crq-cancel"><?php esc_html_e( 'Cancel', 'cr-relocate-db' ); ?></button>
		</p>
	</section>

	<div id="crq-results" class="crq-results" hidden></div>

	<dialog id="crq-confirm" class="crq-dialog" aria-labelledby="crq-confirm-title">
		<form method="dialog">
			<h2 id="crq-confirm-title"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Replace in the database?', 'cr-relocate-db' ); ?></h2>
			<div id="crq-confirm-summary"></div>
			<ul id="crq-confirm-warnings" class="crq-warnings"></ul>
			<p>
				<label class="crq-option">
					<input type="checkbox" id="crq-confirm-before-image" checked>
					<span><?php esc_html_e( 'Save the original value of everything that changes to a downloadable file (recommended)', 'cr-relocate-db' ); ?></span>
				</label>
			</p>
			<p>
				<label class="crq-option">
					<input type="checkbox" id="crq-confirm-backup">
					<span><?php esc_html_e( 'I have a recent backup of this database. I understand the replacement is written straight to the database and is not undone automatically.', 'cr-relocate-db' ); ?></span>
				</label>
			</p>
			<p class="crq-dialog-actions">
				<button type="submit" value="cancel" class="button" formnovalidate><?php esc_html_e( 'Cancel', 'cr-relocate-db' ); ?></button>
				<button type="submit" value="confirm" class="button button-primary" id="crq-confirm-submit" disabled><?php esc_html_e( 'Replace now', 'cr-relocate-db' ); ?></button>
			</p>
		</form>
	</dialog>
</div>
