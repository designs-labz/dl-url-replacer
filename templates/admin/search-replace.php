<?php
/**
 * Search & Replace tab: the form, progress and results regions.
 *
 * Results are filled in by assets/js/search-replace.js.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

defined( 'ABSPATH' ) || exit;

if ( isset( $args['error'] ) ) {
	wp_admin_notice( esc_html( $args['error'] ), array( 'type' => 'error' ) );
	return;
}

/** @var array<string, \DesignsLabz\Relocate\Database\Table> $tables */
$tables = $args['tables'];

$groups = array(
	'core' => array_filter( $tables, fn( $table ) => $table->prefixed ),
	'other'     => array_filter( $tables, fn( $table ) => ! $table->prefixed ),
);
?>
<form id="dlz-search-replace" class="dlz-form">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="dlz-search"><?php esc_html_e( 'Search for', 'designslabz-relocate' ); ?></label></th>
			<td>
				<input type="text" id="dlz-search" name="search" class="large-text code" required spellcheck="false" autocomplete="off" placeholder="https://staging.example.com" aria-describedby="dlz-search-description">
				<p class="description" id="dlz-search-description"><?php esc_html_e( 'Matched exactly as typed, including any spaces.', 'designslabz-relocate' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dlz-replace"><?php esc_html_e( 'Replace with', 'designslabz-relocate' ); ?></label></th>
			<td>
				<input type="text" id="dlz-replace" name="replace" class="large-text code" spellcheck="false" autocomplete="off" placeholder="https://example.com" aria-describedby="dlz-replace-description">
				<p class="description" id="dlz-replace-description"><?php esc_html_e( 'Leave empty to remove the matched text.', 'designslabz-relocate' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Options', 'designslabz-relocate' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Options', 'designslabz-relocate' ); ?></legend>
					<label><input type="checkbox" name="case_insensitive" value="1"> <?php esc_html_e( 'Ignore upper and lower case', 'designslabz-relocate' ); ?></label><br>
					<label><input type="checkbox" name="whole_words" value="1"> <?php esc_html_e( 'Match whole words only', 'designslabz-relocate' ); ?></label><br>
					<label><input type="checkbox" name="url_variants" value="1"> <?php esc_html_e( 'For a URL, also match its http:// and protocol-relative (//) versions', 'designslabz-relocate' ); ?></label><br>
					<label><input type="checkbox" name="skip_guids" value="1" checked> <?php esc_html_e( 'Leave post GUIDs unchanged (recommended)', 'designslabz-relocate' ); ?></label>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Tables', 'designslabz-relocate' ); ?></th>
			<td>
				<fieldset class="dlz-picker">
					<legend class="screen-reader-text"><?php esc_html_e( 'Tables to search', 'designslabz-relocate' ); ?></legend>
					<p class="dlz-picker-actions">
						<button type="button" class="button-link" data-dlz-select="all"><?php esc_html_e( 'Select all', 'designslabz-relocate' ); ?></button>
						<span aria-hidden="true">|</span>
						<button type="button" class="button-link" data-dlz-select="core"><?php esc_html_e( 'WordPress tables only', 'designslabz-relocate' ); ?></button>
						<span aria-hidden="true">|</span>
						<button type="button" class="button-link" data-dlz-select="none"><?php esc_html_e( 'Select none', 'designslabz-relocate' ); ?></button>
					</p>

					<?php foreach ( $groups as $group => $group_tables ) : ?>
						<?php
						if ( ! $group_tables ) {
							continue;
						}

						$heading = 'core' === $group
							/* translators: %s: database table prefix, e.g. wp_. */
							? sprintf( __( 'WordPress tables (prefix %s)', 'designslabz-relocate' ), $args['prefix'] )
							: __( 'Other tables in this database', 'designslabz-relocate' );
						?>
						<details class="dlz-picker-group" <?php echo 'core' === $group ? 'open' : ''; ?>>
							<summary><?php echo esc_html( $heading ); ?> <span class="dlz-picker-count">(<?php echo esc_html( number_format_i18n( count( $group_tables ) ) ); ?>)</span></summary>
							<ul class="dlz-picker-list">
								<?php foreach ( $group_tables as $table ) : ?>
									<li>
										<label>
											<input type="checkbox" name="tables[]" value="<?php echo esc_attr( $table->name ); ?>" data-group="<?php echo esc_attr( $group ); ?>" <?php checked( 'core' === $group ); ?>>
											<code><?php echo esc_html( $table->name ); ?></code>
											<span class="dlz-picker-meta">
												<?php
												printf(
													/* translators: 1: approximate row count, 2: table size. */
													esc_html__( '%1$s rows, %2$s', 'designslabz-relocate' ),
													esc_html( number_format_i18n( $table->approx_rows ) ),
													esc_html( size_format( $table->size(), 1 ) )
												);
												?>
											</span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Run dry run', 'designslabz-relocate' ); ?></button>
		<span class="description"><?php esc_html_e( 'A dry run changes nothing. It shows what would be replaced.', 'designslabz-relocate' ); ?></span>
	</p>
</form>

<div id="dlz-notices"></div>

<div id="dlz-progress" class="dlz-progress" hidden>
	<h2 id="dlz-progress-heading"><?php esc_html_e( 'Dry run in progress', 'designslabz-relocate' ); ?></h2>
	<progress id="dlz-progress-bar" max="100" value="0" aria-labelledby="dlz-progress-heading"></progress>
	<p id="dlz-progress-text"></p>
	<button type="button" class="button" id="dlz-cancel"><?php esc_html_e( 'Cancel', 'designslabz-relocate' ); ?></button>
</div>

<div id="dlz-results" class="dlz-results" hidden></div>
