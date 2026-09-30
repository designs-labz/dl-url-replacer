<?php
/**
 * Database: server details and a searchable, sortable list of tables.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

use DesignsLabz\Relocate\Admin\Admin;

defined( 'ABSPATH' ) || exit;

if ( isset( $args['error'] ) ) {
	wp_admin_notice( esc_html( $args['error'] ), array( 'type' => 'error' ) );
	return;
}

$server = $args['server'];
$tiles  = array(
	array( 'database', __( 'Tables', 'dl-relocate-db' ), number_format_i18n( $args['count'] ) ),
	array( 'chart-pie', __( 'Total size', 'dl-relocate-db' ), (string) size_format( $args['size'], 1 ) ),
	array( 'editor-table', __( 'Rows (approx.)', 'dl-relocate-db' ), number_format_i18n( $args['rows'] ) ),
	array( 'admin-site-alt3', __( 'Server', 'dl-relocate-db' ), $server['version'] ),
);
?>
<h2 class="dlz-title"><?php esc_html_e( 'Database', 'dl-relocate-db' ); ?></h2>

<ul class="dlz-tiles">
	<?php foreach ( $tiles as [ $icon, $label, $value ] ) : ?>
		<li class="dlz-tile">
			<span class="dlz-tile-icon dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<span class="dlz-tile-value"><?php echo esc_html( $value ); ?></span>
			<span class="dlz-tile-label"><?php echo esc_html( $label ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>

<section class="dlz-card" aria-labelledby="dlz-db-info">
	<h3 id="dlz-db-info" class="dlz-card-title"><?php esc_html_e( 'Database information', 'dl-relocate-db' ); ?></h3>
	<dl class="dlz-summary">
		<dt><?php esc_html_e( 'Database name', 'dl-relocate-db' ); ?></dt>
		<dd><code><?php echo esc_html( $server['name'] ); ?></code></dd>
		<dt><?php esc_html_e( 'Connection charset', 'dl-relocate-db' ); ?></dt>
		<dd><?php echo esc_html( trim( $server['charset'] . ' / ' . $server['collate'], ' /' ) ); ?></dd>
		<dt><?php esc_html_e( 'Table prefix', 'dl-relocate-db' ); ?></dt>
		<dd><code><?php echo esc_html( $server['prefix'] ); ?></code></dd>
	</dl>
	<p class="description"><?php esc_html_e( 'Row counts and sizes come from the database server’s statistics. For InnoDB tables they are estimates and can be out of date.', 'dl-relocate-db' ); ?></p>
</section>

<div class="dlz-card dlz-card-flush">
	<?php $args['list']->views(); ?>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGE . '-database' ); ?>">
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Keeps the current filter when searching.
		if ( isset( $_GET['group'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<input type="hidden" name="group" value="%s">', esc_attr( sanitize_key( wp_unslash( $_GET['group'] ) ) ) );
		}

		$args['list']->search_box( __( 'Search tables', 'dl-relocate-db' ), 'dlz-tables' );
		$args['list']->display();
		?>
	</form>
</div>
