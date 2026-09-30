<?php
/**
 * Database tab: server details and table list.
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

/** @var \DesignsLabz\Relocate\Database\Table[] $tables */
$tables = $args['tables'];
$server = $args['server'];

$groups = array(
	array(
		/* translators: %s: database table prefix, e.g. wp_. */
		'label'  => sprintf( __( 'WordPress tables (prefix %s)', 'designslabz-relocate' ), $server['prefix'] ),
		'tables' => array_filter( $tables, fn( $table ) => $table->prefixed ),
	),
	array(
		'label'  => __( 'Other tables in this database', 'designslabz-relocate' ),
		'tables' => array_filter( $tables, fn( $table ) => ! $table->prefixed ),
	),
);
?>
<h2><?php esc_html_e( 'Database information', 'designslabz-relocate' ); ?></h2>
<dl class="dlz-summary">
	<dt><?php esc_html_e( 'Server version', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo esc_html( $server['version'] ); ?></dd>

	<dt><?php esc_html_e( 'Database name', 'designslabz-relocate' ); ?></dt>
	<dd><code><?php echo esc_html( $server['name'] ); ?></code></dd>

	<dt><?php esc_html_e( 'Connection charset', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo esc_html( trim( $server['charset'] . ' / ' . $server['collate'], ' /' ) ); ?></dd>

	<dt><?php esc_html_e( 'Table prefix', 'designslabz-relocate' ); ?></dt>
	<dd><code><?php echo esc_html( $server['prefix'] ); ?></code></dd>

	<dt><?php esc_html_e( 'Tables', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo esc_html( number_format_i18n( count( $tables ) ) ); ?></dd>

	<dt><?php esc_html_e( 'Total size', 'designslabz-relocate' ); ?></dt>
	<dd><?php echo esc_html( size_format( array_sum( array_map( fn( $table ) => $table->size(), $tables ) ), 1 ) ); ?></dd>
</dl>

<p class="description">
	<?php esc_html_e( 'Row counts and sizes come from the database server’s statistics. For InnoDB tables they are estimates and can be out of date.', 'designslabz-relocate' ); ?>
</p>

<?php foreach ( $groups as $group ) : ?>
	<?php
	if ( ! $group['tables'] ) {
		continue;
	}
	?>
	<h2><?php echo esc_html( $group['label'] ); ?></h2>
	<div class="dlz-table-scroll">
		<table class="wp-list-table widefat striped dlz-tables">
			<caption class="screen-reader-text"><?php echo esc_html( $group['label'] ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Table', 'designslabz-relocate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Engine', 'designslabz-relocate' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Rows (approx.)', 'designslabz-relocate' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Size', 'designslabz-relocate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Collation', 'designslabz-relocate' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $group['tables'] as $table ) : ?>
					<tr>
						<th scope="row"><code><?php echo esc_html( $table->name ); ?></code></th>
						<td><?php echo esc_html( $table->engine ? $table->engine : '—' ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $table->approx_rows ) ); ?></td>
						<td class="num"><?php echo esc_html( size_format( $table->size(), 1 ) ); ?></td>
						<td><?php echo esc_html( $table->collation ? $table->collation : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endforeach; ?>
