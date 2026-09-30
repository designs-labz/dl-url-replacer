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

$max_pairs = \DesignsLabz\Relocate\Jobs\JobStarter::max_pairs();

/** @var array<string, \DesignsLabz\Relocate\Database\Table> $tables */
$tables  = $args['tables'];
$columns = $args['columns'];

$groups = array(
	'core'  => array_filter( $tables, fn( $table ) => $table->prefixed ),
	'other' => array_filter( $tables, fn( $table ) => ! $table->prefixed ),
);

$options = array(
	'case_insensitive' => array( false, __( 'Ignore upper and lower case', 'dl-relocate-db' ), __( '“Example” also matches “EXAMPLE” and “example”.', 'dl-relocate-db' ) ),
	'whole_words'      => array( false, __( 'Match whole words only', 'dl-relocate-db' ), __( '“cat” matches “cat.” but not “concatenate”.', 'dl-relocate-db' ) ),
	'url_variants'     => array( false, __( 'Include other versions of the URL', 'dl-relocate-db' ), __( 'For https://old.com, also replace http://old.com and //old.com.', 'dl-relocate-db' ) ),
	'skip_guids'       => array( true, __( 'Leave post GUIDs unchanged', 'dl-relocate-db' ), __( 'Recommended. Feed readers use GUIDs to recognise posts they have already seen.', 'dl-relocate-db' ) ),
);
?>
<h2 class="dlz-title"><?php esc_html_e( 'Search & Replace', 'dl-relocate-db' ); ?></h2>
<p class="dlz-intro"><?php esc_html_e( 'Start with a dry run: it changes nothing and shows exactly what would be replaced. You can apply it afterwards.', 'dl-relocate-db' ); ?></p>

<form id="dlz-search-replace" class="dlz-form">
	<section class="dlz-card" aria-labelledby="dlz-step-find">
		<h3 id="dlz-step-find" class="dlz-card-title"><span class="dlz-step" aria-hidden="true">1</span> <?php esc_html_e( 'What to find', 'dl-relocate-db' ); ?></h3>
		<div class="dlz-pair-head" aria-hidden="true">
			<span><?php esc_html_e( 'Search for', 'dl-relocate-db' ); ?></span>
			<span></span>
			<span><?php esc_html_e( 'Replace with', 'dl-relocate-db' ); ?></span>
			<span></span>
		</div>

		<ol id="dlz-pairs" class="dlz-pairs" data-max="<?php echo esc_attr( (string) $max_pairs ); ?>">
			<li class="dlz-pair">
				<label class="screen-reader-text" for="dlz-search-1"><?php esc_html_e( 'Search for', 'dl-relocate-db' ); ?></label>
				<input type="text" id="dlz-search-1" name="search[]" value="<?php echo esc_attr( $args['prefill']['search'] ); ?>" class="large-text code" required spellcheck="false" autocomplete="off" placeholder="https://staging.example.com" aria-describedby="dlz-pairs-help">
				<span class="dlz-pair-arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				<label class="screen-reader-text" for="dlz-replace-1"><?php esc_html_e( 'Replace with', 'dl-relocate-db' ); ?></label>
				<input type="text" id="dlz-replace-1" name="replace[]" value="<?php echo esc_attr( $args['prefill']['replace'] ); ?>" class="large-text code" spellcheck="false" autocomplete="off" placeholder="https://example.com" aria-describedby="dlz-pairs-help">
				<span class="dlz-pair-remove-slot"></span>
			</li>
		</ol>

		<template id="dlz-pair-template">
			<li class="dlz-pair">
				<label class="screen-reader-text" data-for="search"></label>
				<input type="text" name="search[]" class="large-text code" required spellcheck="false" autocomplete="off" aria-describedby="dlz-pairs-help">
				<span class="dlz-pair-arrow dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
				<label class="screen-reader-text" data-for="replace"></label>
				<input type="text" name="replace[]" class="large-text code" spellcheck="false" autocomplete="off" aria-describedby="dlz-pairs-help">
				<button type="button" class="button dlz-pair-remove"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"></span></button>
			</li>
		</template>

		<div class="dlz-pairs-footer">
			<button type="button" id="dlz-add-pair" class="button">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Add another', 'dl-relocate-db' ); ?>
			</button>
			<span id="dlz-pairs-count" class="dlz-pairs-count" aria-live="polite"></span>
		</div>

		<p class="description" id="dlz-pairs-help">
			<?php
			printf(
				/* translators: %d: maximum number of pairs. */
				esc_html( _n( 'Matched exactly as typed, including any spaces. Leave a replacement empty to remove the matched text. Up to %d pair.', 'Matched exactly as typed, including any spaces. Leave a replacement empty to remove the matched text. Up to %d pairs, all replaced together in one pass.', $max_pairs, 'dl-relocate-db' ) ),
				(int) $max_pairs
			);
			?>
		</p>
	</section>

	<section class="dlz-card" aria-labelledby="dlz-step-options">
		<h3 id="dlz-step-options" class="dlz-card-title"><span class="dlz-step" aria-hidden="true">2</span> <?php esc_html_e( 'Options', 'dl-relocate-db' ); ?></h3>
		<fieldset class="dlz-options">
			<legend class="screen-reader-text"><?php esc_html_e( 'Options', 'dl-relocate-db' ); ?></legend>
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
		<h3 id="dlz-step-tables" class="dlz-card-title"><span class="dlz-step" aria-hidden="true">3</span> <?php esc_html_e( 'Tables and columns', 'dl-relocate-db' ); ?></h3>

		<div class="dlz-toolbar">
			<label class="screen-reader-text" for="dlz-table-filter"><?php esc_html_e( 'Filter tables', 'dl-relocate-db' ); ?></label>
			<input type="search" id="dlz-table-filter" class="dlz-filter" placeholder="<?php esc_attr_e( 'Filter tables…', 'dl-relocate-db' ); ?>" autocomplete="off">
			<span class="dlz-toolbar-actions">
				<button type="button" class="button" data-dlz-select="all"><?php esc_html_e( 'Select all', 'dl-relocate-db' ); ?></button>
				<button type="button" class="button" data-dlz-select="core"><?php esc_html_e( 'WordPress tables', 'dl-relocate-db' ); ?></button>
				<button type="button" class="button" data-dlz-select="none"><?php esc_html_e( 'Select none', 'dl-relocate-db' ); ?></button>
			</span>
			<span id="dlz-table-count" class="dlz-toolbar-count" aria-live="polite"></span>
		</div>

		<fieldset class="dlz-picker">
			<legend class="screen-reader-text"><?php esc_html_e( 'Tables to search', 'dl-relocate-db' ); ?></legend>

			<?php foreach ( $groups as $group => $group_tables ) : ?>
				<?php
				if ( ! $group_tables ) {
					continue;
				}

				$heading = 'core' === $group
					/* translators: %s: database table prefix, e.g. wp_. */
					? sprintf( __( 'WordPress tables (prefix %s)', 'dl-relocate-db' ), $args['prefix'] )
					: __( 'Other tables in this database', 'dl-relocate-db' );
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
										esc_html__( '%1$s rows · %2$s', 'dl-relocate-db' ),
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
												esc_html__( 'Columns: %1$s of %2$s', 'dl-relocate-db' ),
												'<span class="dlz-columns-selected">' . esc_html( number_format_i18n( count( $table_columns ) ) ) . '</span>',
												esc_html( number_format_i18n( count( $table_columns ) ) )
											);
											?>
										</summary>
										<fieldset>
											<?php /* translators: %s: table name. */ ?>
											<legend class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Columns of %s to search', 'dl-relocate-db' ), $table->name ) ); ?></legend>
											<?php foreach ( $table_columns as $column ) : ?>
												<label><input type="checkbox" name="columns[<?php echo esc_attr( $table->name ); ?>][]" value="<?php echo esc_attr( $column ); ?>" checked> <code><?php echo esc_html( $column ); ?></code></label>
											<?php endforeach; ?>
										</fieldset>
									</details>
								<?php else : ?>
									<span class="dlz-picker-meta"><?php esc_html_e( 'No text columns', 'dl-relocate-db' ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endforeach; ?>
			<p id="dlz-table-none" class="dlz-empty" hidden><?php esc_html_e( 'No tables match the filter.', 'dl-relocate-db' ); ?></p>
		</fieldset>
	</section>

	<div class="dlz-actions">
		<button type="submit" class="button button-primary dlz-button-lg">
			<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
			<?php esc_html_e( 'Run dry run', 'dl-relocate-db' ); ?>
		</button>
		<span class="description"><?php esc_html_e( 'Nothing is changed until you review the results and confirm.', 'dl-relocate-db' ); ?></span>
	</div>
</form>

<?php require __DIR__ . '/partials/runner.php'; ?>
