<?php
/**
 * Search & Replace: the form, then progress and results (see partials/runner.php).
 *
 * @package CraftRoq\Relocate
 *
 * @var array $args
 */

defined( 'ABSPATH' ) || exit;

if ( isset( $args['error'] ) ) {
	wp_admin_notice( esc_html( $args['error'] ), array( 'type' => 'error' ) );
	return;
}

$max_pairs = \CraftRoq\Relocate\Jobs\JobStarter::max_pairs();

/** @var array<string, \CraftRoq\Relocate\Database\Table> $tables */
$tables  = $args['tables'];
$columns = $args['columns'];

$groups = array(
	'core'  => array_filter( $tables, fn( $table ) => $table->prefixed ),
	'other' => array_filter( $tables, fn( $table ) => ! $table->prefixed ),
);

$options = array(
	'case_insensitive' => array( false, __( 'Ignore upper and lower case', 'cr-relocate-db' ), __( '“Example” also matches “EXAMPLE” and “example”.', 'cr-relocate-db' ) ),
	'whole_words'      => array( false, __( 'Match whole words only', 'cr-relocate-db' ), __( '“cat” matches “cat.” but not “concatenate”.', 'cr-relocate-db' ) ),
	'url_variants'     => array( false, __( 'Include other versions of the URL', 'cr-relocate-db' ), __( 'For https://old.com, also replace http://old.com and //old.com.', 'cr-relocate-db' ) ),
	'skip_guids'       => array( true, __( 'Leave post GUIDs unchanged', 'cr-relocate-db' ), __( 'Recommended. Feed readers use GUIDs to recognise posts they have already seen.', 'cr-relocate-db' ) ),
);
?>
<div class="crq-title-row">
	<div>
		<h2 class="crq-title"><?php esc_html_e( 'Search & Replace', 'cr-relocate-db' ); ?></h2>
		<p class="crq-intro"><?php esc_html_e( 'Preview first, then apply. Nothing is changed until you review the dry run and confirm.', 'cr-relocate-db' ); ?></p>
	</div>
	<ol id="crq-steps" class="crq-steps" aria-label="<?php esc_attr_e( 'Progress', 'cr-relocate-db' ); ?>">
		<li class="is-current" aria-current="step"><span class="crq-steps-number">1</span> <?php esc_html_e( 'Choose', 'cr-relocate-db' ); ?></li>
		<li><span class="crq-steps-number">2</span> <?php esc_html_e( 'Preview', 'cr-relocate-db' ); ?></li>
		<li><span class="crq-steps-number">3</span> <?php esc_html_e( 'Apply', 'cr-relocate-db' ); ?></li>
	</ol>
</div>

<form id="crq-search-replace" class="crq-form crq-layout">
	<div class="crq-layout-main">
	<section class="crq-card" aria-labelledby="crq-step-find">
		<h3 id="crq-step-find" class="crq-card-title"><span class="crq-step" aria-hidden="true">1</span> <?php esc_html_e( 'What to find', 'cr-relocate-db' ); ?></h3>
		<div class="crq-pair-head" aria-hidden="true">
			<span><?php esc_html_e( 'Search for', 'cr-relocate-db' ); ?></span>
			<span></span>
			<span><?php esc_html_e( 'Replace with', 'cr-relocate-db' ); ?></span>
			<span></span>
		</div>

		<ol id="crq-pairs" class="crq-pairs" data-max="<?php echo esc_attr( (string) $max_pairs ); ?>">
			<li class="crq-pair">
				<label class="screen-reader-text" for="crq-search-1"><?php esc_html_e( 'Search for', 'cr-relocate-db' ); ?></label>
				<input type="text" id="crq-search-1" name="search[]" value="<?php echo esc_attr( $args['prefill']['search'] ); ?>" class="large-text code" required spellcheck="false" autocomplete="off" placeholder="https://staging.example.com" aria-describedby="crq-pairs-help">
				<span class="crq-pair-arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				<label class="screen-reader-text" for="crq-replace-1"><?php esc_html_e( 'Replace with', 'cr-relocate-db' ); ?></label>
				<input type="text" id="crq-replace-1" name="replace[]" value="<?php echo esc_attr( $args['prefill']['replace'] ); ?>" class="large-text code" spellcheck="false" autocomplete="off" placeholder="https://example.com" aria-describedby="crq-pairs-help">
				<span class="crq-pair-remove-slot"></span>
			</li>
		</ol>

		<template id="crq-pair-template">
			<li class="crq-pair">
				<label class="screen-reader-text" data-for="search"></label>
				<input type="text" name="search[]" class="large-text code" required spellcheck="false" autocomplete="off" aria-describedby="crq-pairs-help">
				<span class="crq-pair-arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				<label class="screen-reader-text" data-for="replace"></label>
				<input type="text" name="replace[]" class="large-text code" spellcheck="false" autocomplete="off" aria-describedby="crq-pairs-help">
				<button type="button" class="button crq-pair-remove"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"></span></button>
			</li>
		</template>

		<div class="crq-pairs-footer">
			<button type="button" id="crq-add-pair" class="button">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add another', 'cr-relocate-db' ); ?>
			</button>
			<span id="crq-pairs-count" class="crq-pairs-count" aria-live="polite"></span>
		</div>

		<p class="description" id="crq-pairs-help">
			<?php
			printf(
				/* translators: %d: maximum number of pairs. */
				esc_html( _n( 'Matched exactly as typed, including any spaces. Leave a replacement empty to remove the matched text. Up to %d pair.', 'Matched exactly as typed, including any spaces. Leave a replacement empty to remove the matched text. Up to %d pairs, all replaced together in one pass.', $max_pairs, 'cr-relocate-db' ) ),
				(int) $max_pairs
			);
			?>
		</p>
	</section>

	<section class="crq-card" aria-labelledby="crq-step-options">
		<h3 id="crq-step-options" class="crq-card-title"><span class="crq-step" aria-hidden="true">2</span> <?php esc_html_e( 'Options', 'cr-relocate-db' ); ?></h3>
		<fieldset class="crq-options">
			<legend class="screen-reader-text"><?php esc_html_e( 'Options', 'cr-relocate-db' ); ?></legend>
			<?php foreach ( $options as $name => [ $default, $label, $help ] ) : ?>
				<label class="crq-option">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $default ); ?>>
					<span>
						<strong><?php echo esc_html( $label ); ?></strong>
						<span class="description"><?php echo esc_html( $help ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
	</section>

	<section class="crq-card" aria-labelledby="crq-step-tables">
		<h3 id="crq-step-tables" class="crq-card-title"><span class="crq-step" aria-hidden="true">3</span> <?php esc_html_e( 'Tables and columns', 'cr-relocate-db' ); ?></h3>

		<div class="crq-toolbar">
			<label class="screen-reader-text" for="crq-table-filter"><?php esc_html_e( 'Filter tables', 'cr-relocate-db' ); ?></label>
			<input type="search" id="crq-table-filter" class="crq-filter" placeholder="<?php esc_attr_e( 'Filter tables…', 'cr-relocate-db' ); ?>" autocomplete="off">
			<span class="crq-toolbar-actions">
				<button type="button" class="button" data-crq-select="all"><?php esc_html_e( 'Select all', 'cr-relocate-db' ); ?></button>
				<button type="button" class="button" data-crq-select="core"><?php esc_html_e( 'WordPress tables', 'cr-relocate-db' ); ?></button>
				<button type="button" class="button" data-crq-select="none"><?php esc_html_e( 'Select none', 'cr-relocate-db' ); ?></button>
			</span>
			<span id="crq-table-count" class="crq-toolbar-count" aria-live="polite"></span>
		</div>

		<fieldset class="crq-picker">
			<legend class="screen-reader-text"><?php esc_html_e( 'Tables to search', 'cr-relocate-db' ); ?></legend>

			<?php foreach ( $groups as $group => $group_tables ) : ?>
				<?php
				if ( ! $group_tables ) {
					continue;
				}

				$heading = 'core' === $group
					/* translators: %s: database table prefix, e.g. wp_. */
					? sprintf( __( 'WordPress tables (prefix %s)', 'cr-relocate-db' ), $args['prefix'] )
					: __( 'Other tables in this database', 'cr-relocate-db' );
				?>
				<details class="crq-picker-group" <?php echo 'core' === $group ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $heading ); ?> <span class="crq-picker-count">(<?php echo esc_html( number_format_i18n( count( $group_tables ) ) ); ?>)</span></summary>
					<div class="crq-picker-head" aria-hidden="true">
						<span><?php esc_html_e( 'Table', 'cr-relocate-db' ); ?></span>
						<span><?php esc_html_e( 'Rows', 'cr-relocate-db' ); ?></span>
						<span><?php esc_html_e( 'Size', 'cr-relocate-db' ); ?></span>
						<span><?php esc_html_e( 'Columns', 'cr-relocate-db' ); ?></span>
					</div>
					<ul class="crq-picker-list">
						<?php foreach ( $group_tables as $table ) : ?>
							<?php $table_columns = $columns[ $table->name ] ?? array(); ?>
							<li class="crq-picker-row" data-table="<?php echo esc_attr( $table->name ); ?>">
								<label class="crq-picker-table">
									<input type="checkbox" name="tables[]" value="<?php echo esc_attr( $table->name ); ?>" data-group="<?php echo esc_attr( $group ); ?>" <?php checked( 'core' === $group ); ?>>
									<code><?php echo esc_html( $table->name ); ?></code>
								</label>
								<span class="crq-picker-num">
									<?php
									/* translators: %s: approximate number of rows. */
									echo esc_html( sprintf( __( '%s rows', 'cr-relocate-db' ), number_format_i18n( $table->approx_rows ) ) );
									?>
								</span>
								<span class="crq-picker-num"><?php echo esc_html( (string) size_format( $table->size(), 1 ) ); ?></span>
								<?php if ( $table_columns ) : ?>
									<details class="crq-columns">
										<summary>
											<?php
											printf(
												/* translators: 1: columns selected, 2: text columns in the table. */
												esc_html__( '%1$s of %2$s columns', 'cr-relocate-db' ),
												'<span class="crq-columns-selected">' . esc_html( number_format_i18n( count( $table_columns ) ) ) . '</span>',
												esc_html( number_format_i18n( count( $table_columns ) ) )
											);
											?>
											<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
										</summary>
										<fieldset class="crq-columns-menu">
											<?php /* translators: %s: table name. */ ?>
											<legend class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Columns of %s to search', 'cr-relocate-db' ), $table->name ) ); ?></legend>
											<?php foreach ( $table_columns as $column ) : ?>
												<label><input type="checkbox" name="columns[<?php echo esc_attr( $table->name ); ?>][]" value="<?php echo esc_attr( $column ); ?>" checked> <code><?php echo esc_html( $column ); ?></code></label>
											<?php endforeach; ?>
										</fieldset>
									</details>
								<?php else : ?>
									<span class="crq-picker-none"><?php esc_html_e( 'No text columns', 'cr-relocate-db' ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endforeach; ?>
			<p id="crq-table-none" class="crq-empty" hidden><?php esc_html_e( 'No tables match the filter.', 'cr-relocate-db' ); ?></p>
		</fieldset>
	</section>

	</div>

	<aside class="crq-layout-side" aria-labelledby="crq-summary-title">
		<div class="crq-card crq-summary-card">
			<h3 id="crq-summary-title" class="crq-card-title"><?php esc_html_e( 'Summary', 'cr-relocate-db' ); ?></h3>
			<dl id="crq-form-summary" class="crq-kv">
				<div><dt><?php esc_html_e( 'Replacements', 'cr-relocate-db' ); ?></dt><dd data-summary="pairs">—</dd></div>
				<div><dt><?php esc_html_e( 'Tables', 'cr-relocate-db' ); ?></dt><dd data-summary="tables">—</dd></div>
				<div><dt><?php esc_html_e( 'Columns left out', 'cr-relocate-db' ); ?></dt><dd data-summary="columns">—</dd></div>
				<div><dt><?php esc_html_e( 'Matching', 'cr-relocate-db' ); ?></dt><dd data-summary="options">—</dd></div>
			</dl>
			<button type="submit" class="button button-primary crq-button-lg crq-button-block">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'Run dry run', 'cr-relocate-db' ); ?>
			</button>
			<p class="crq-summary-note">
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<?php esc_html_e( 'A dry run only reads the database. You confirm before anything is written.', 'cr-relocate-db' ); ?>
			</p>
		</div>
	</aside>
</form>

<?php require __DIR__ . '/partials/runner.php'; ?>
