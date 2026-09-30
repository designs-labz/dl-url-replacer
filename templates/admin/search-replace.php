<?php
/**
 * Search & Replace: the form, then progress and results (see partials/runner.php).
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
$tables  = $args['tables'];
$columns = $args['columns'];

$groups = array(
	'core'  => array_filter( $tables, fn( $table ) => $table->prefixed ),
	'other' => array_filter( $tables, fn( $table ) => ! $table->prefixed ),
);

$options = array(
	'case_insensitive' => array( false, __( 'Ignore upper and lower case', 'designslabz-relocate' ), __( '“Example” also matches “EXAMPLE” and “example”.', 'designslabz-relocate' ) ),
	'whole_words'      => array( false, __( 'Match whole words only', 'designslabz-relocate' ), __( '“cat” matches “cat.” but not “concatenate”.', 'designslabz-relocate' ) ),
	'url_variants'     => array( false, __( 'Include other versions of the URL', 'designslabz-relocate' ), __( 'For https://old.com, also replace http://old.com and //old.com.', 'designslabz-relocate' ) ),
	'skip_guids'       => array( true, __( 'Leave post GUIDs unchanged', 'designslabz-relocate' ), __( 'Recommended. Feed readers use GUIDs to recognise posts they have already seen.', 'designslabz-relocate' ) ),
);
?>
<h2 class="dlz-title"><?php esc_html_e( 'Search & Replace', 'designslabz-relocate' ); ?></h2>
<p class="dlz-intro"><?php esc_html_e( 'Start with a dry run: it changes nothing and shows exactly what would be replaced. You can apply it afterwards.', 'designslabz-relocate' ); ?></p>

<form id="dlz-search-replace" class="dlz-form">
	<section class="dlz-card" aria-labelledby="dlz-step-find">
		<h3 id="dlz-step-find" class="dlz-card-title"><span class="dlz-step" aria-hidden="true">1</span> <?php esc_html_e( 'What to find', 'designslabz-relocate' ); ?></h3>
		<div class="dlz-pair">
			<p class="dlz-field">
				<label for="dlz-search"><?php esc_html_e( 'Search for', 'designslabz-relocate' ); ?></label>
				<input type="text" id="dlz-search" name="search" value="<?php echo esc_attr( $args['prefill']['search'] ); ?>" class="large-text code" required spellcheck="false" autocomplete="off" placeholder="https://staging.example.com" aria-describedby="dlz-search-description">
				<span class="description" id="dlz-search-description"><?php esc_html_e( 'Matched exactly as typed, including any spaces.', 'designslabz-relocate' ); ?></span>
			</p>
			<span class="dlz-pair-arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
			<p class="dlz-field">
				<label for="dlz-replace"><?php esc_html_e( 'Replace with', 'designslabz-relocate' ); ?></label>
				<input type="text" id="dlz-replace" name="replace" value="<?php echo esc_attr( $args['prefill']['replace'] ); ?>" class="large-text code" spellcheck="false" autocomplete="off" placeholder="https://example.com" aria-describedby="dlz-replace-description">
				<span class="description" id="dlz-replace-description"><?php esc_html_e( 'Leave empty to remove the matched text.', 'designslabz-relocate' ); ?></span>
			</p>
		</div>
	</section>

	<section class="dlz-card" aria-labelledby="dlz-step-options">
		<h3 id="dlz-step-options" class="dlz-card-title"><span class="dlz-step" aria-hidden="true">2</span> <?php esc_html_e( 'Options', 'designslabz-relocate' ); ?></h3>
		<fieldset class="dlz-options">
			<legend class="screen-reader-text"><?php esc_html_e( 'Options', 'designslabz-relocate' ); ?></legend>
			<?php foreach ( $options as $name => [ $default, $label, $help ] ) : ?>
				<label class="dlz-option">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $default ); ?>>
					<span>
						<strong><?php echo esc_html( $label ); ?></strong>
						<span class="description"><?php echo esc_html( $help ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
	</section>

	<section class="dlz-card" aria-labelledby="dlz-step-tables">
		<h3 id="dlz-step-tables" class="dlz-card-title"><span class="dlz-step" aria-hidden="true">3</span> <?php esc_html_e( 'Tables and columns', 'designslabz-relocate' ); ?></h3>

		<div class="dlz-toolbar">
			<label class="screen-reader-text" for="dlz-table-filter"><?php esc_html_e( 'Filter tables', 'designslabz-relocate' ); ?></label>
			<input type="search" id="dlz-table-filter" class="dlz-filter" placeholder="<?php esc_attr_e( 'Filter tables…', 'designslabz-relocate' ); ?>" autocomplete="off">
			<span class="dlz-toolbar-actions">
				<button type="button" class="button" data-dlz-select="all"><?php esc_html_e( 'Select all', 'designslabz-relocate' ); ?></button>
				<button type="button" class="button" data-dlz-select="core"><?php esc_html_e( 'WordPress tables', 'designslabz-relocate' ); ?></button>
				<button type="button" class="button" data-dlz-select="none"><?php esc_html_e( 'Select none', 'designslabz-relocate' ); ?></button>
			</span>
			<span id="dlz-table-count" class="dlz-toolbar-count" aria-live="polite"></span>
		</div>

		<fieldset class="dlz-picker">
			<legend class="screen-reader-text"><?php esc_html_e( 'Tables to search', 'designslabz-relocate' ); ?></legend>

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
							<?php $table_columns = $columns[ $table->name ] ?? array(); ?>
							<li class="dlz-picker-row" data-table="<?php echo esc_attr( $table->name ); ?>">
								<label class="dlz-picker-table">
									<input type="checkbox" name="tables[]" value="<?php echo esc_attr( $table->name ); ?>" data-group="<?php echo esc_attr( $group ); ?>" <?php checked( 'core' === $group ); ?>>
									<code><?php echo esc_html( $table->name ); ?></code>
								</label>
								<span class="dlz-picker-meta">
									<?php
									printf(
										/* translators: 1: approximate row count, 2: table size. */
										esc_html__( '%1$s rows · %2$s', 'designslabz-relocate' ),
										esc_html( number_format_i18n( $table->approx_rows ) ),
										esc_html( (string) size_format( $table->size(), 1 ) )
									);
									?>
								</span>
								<?php if ( $table_columns ) : ?>
									<details class="dlz-columns">
										<summary data-total="<?php echo esc_attr( (string) count( $table_columns ) ); ?>">
											<?php
											printf(
												/* translators: 1: columns selected, 2: text columns in the table. */
												esc_html__( 'Columns: %1$s of %2$s', 'designslabz-relocate' ),
												'<span class="dlz-columns-selected">' . esc_html( number_format_i18n( count( $table_columns ) ) ) . '</span>',
												esc_html( number_format_i18n( count( $table_columns ) ) )
											);
											?>
										</summary>
										<fieldset>
											<?php /* translators: %s: table name. */ ?>
											<legend class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Columns of %s to search', 'designslabz-relocate' ), $table->name ) ); ?></legend>
											<?php foreach ( $table_columns as $column ) : ?>
												<label><input type="checkbox" name="columns[<?php echo esc_attr( $table->name ); ?>][]" value="<?php echo esc_attr( $column ); ?>" checked> <code><?php echo esc_html( $column ); ?></code></label>
											<?php endforeach; ?>
										</fieldset>
									</details>
								<?php else : ?>
									<span class="dlz-picker-meta"><?php esc_html_e( 'No text columns', 'designslabz-relocate' ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endforeach; ?>
			<p id="dlz-table-none" class="dlz-empty" hidden><?php esc_html_e( 'No tables match the filter.', 'designslabz-relocate' ); ?></p>
		</fieldset>
	</section>

	<div class="dlz-actions">
		<button type="submit" class="button button-primary dlz-button-lg">
			<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
			<?php esc_html_e( 'Run dry run', 'designslabz-relocate' ); ?>
		</button>
		<span class="description"><?php esc_html_e( 'Nothing is changed until you review the results and confirm.', 'designslabz-relocate' ); ?></span>
	</div>
</form>

<?php require __DIR__ . '/partials/runner.php'; ?>
