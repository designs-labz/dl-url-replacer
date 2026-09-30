<?php
/**
 * Progress, results and confirmation dialog, driven by assets/js/search-replace.js.
 *
 * Shared by the Search & Replace tab and the job page. Pass $args['job_data']
 * to render an existing job straight away.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="dlz-runner"<?php echo isset( $args['job_data'] ) ? ' data-job="' . esc_attr( (string) wp_json_encode( $args['job_data'] ) ) . '"' : ''; ?>>
	<div id="dlz-notices"></div>

	<div id="dlz-progress" class="dlz-progress" hidden>
		<h2 id="dlz-progress-heading"><?php esc_html_e( 'Dry run in progress', 'designslabz-relocate' ); ?></h2>
		<progress id="dlz-progress-bar" max="100" value="0" aria-labelledby="dlz-progress-heading"></progress>
		<p id="dlz-progress-text"></p>
		<button type="button" class="button" id="dlz-cancel"><?php esc_html_e( 'Cancel', 'designslabz-relocate' ); ?></button>
	</div>

	<div id="dlz-results" class="dlz-results" hidden></div>

	<dialog id="dlz-confirm" class="dlz-dialog" aria-labelledby="dlz-confirm-title">
		<form method="dialog">
			<h2 id="dlz-confirm-title"><?php esc_html_e( 'Replace in the database?', 'designslabz-relocate' ); ?></h2>
			<div id="dlz-confirm-summary"></div>
			<ul id="dlz-confirm-warnings" class="dlz-warnings"></ul>
			<p>
				<label>
					<input type="checkbox" id="dlz-confirm-before-image" checked>
					<?php esc_html_e( 'Save the original value of everything that changes to a downloadable file (recommended)', 'designslabz-relocate' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" id="dlz-confirm-backup">
					<?php esc_html_e( 'I have a recent backup of this database. I understand the replacement is written straight to the database and is not undone automatically.', 'designslabz-relocate' ); ?>
				</label>
			</p>
			<p class="dlz-dialog-actions">
				<button type="submit" value="cancel" class="button" formnovalidate><?php esc_html_e( 'Cancel', 'designslabz-relocate' ); ?></button>
				<button type="submit" value="confirm" class="button button-primary" id="dlz-confirm-submit" disabled><?php esc_html_e( 'Replace now', 'designslabz-relocate' ); ?></button>
			</p>
		</form>
	</dialog>
</div>
