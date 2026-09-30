<?php
/**
 * History: the job list, the log, or a single job.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

use DesignsLabz\Relocate\Admin\Admin;

defined( 'ABSPATH' ) || exit;

if ( isset( $args['error'] ) ) {
	wp_admin_notice( esc_html( $args['error'] ), array( 'type' => 'error' ) );
	printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( Admin::url( 'history' ) ), esc_html__( 'Back to all jobs', 'designslabz-relocate' ) );
	return;
}

if ( 'job' === $args['view'] ) {
	require __DIR__ . '/job.php';
	return;
}

if ( null !== $args['deleted'] ) {
	wp_admin_notice(
		esc_html(
			/* translators: %s: number of jobs. */
			sprintf( _n( '%s job deleted.', '%s jobs deleted.', $args['deleted'], 'designslabz-relocate' ), number_format_i18n( $args['deleted'] ) )
		),
		array(
			'type'        => 'success',
			'dismissible' => true,
		)
	);
}

if ( $args['kept'] ) {
	wp_admin_notice(
		esc_html(
			/* translators: %s: number of jobs. */
			sprintf( _n( '%s job was not deleted because it is still running.', '%s jobs were not deleted because they are still running.', $args['kept'], 'designslabz-relocate' ), number_format_i18n( $args['kept'] ) )
		),
		array( 'type' => 'warning' )
	);
}

$is_log = 'log' === $args['view'];
?>
<h2 class="dlz-title"><?php esc_html_e( 'History', 'designslabz-relocate' ); ?></h2>

<nav class="dlz-subnav" aria-label="<?php esc_attr_e( 'History views', 'designslabz-relocate' ); ?>">
	<a href="<?php echo esc_url( Admin::url( 'history' ) ); ?>" class="<?php echo $is_log ? '' : 'is-active'; ?>"<?php echo $is_log ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'Jobs', 'designslabz-relocate' ); ?></a>
	<a href="<?php echo esc_url( Admin::url( 'history', array( 'view' => 'log' ) ) ); ?>" class="<?php echo $is_log ? 'is-active' : ''; ?>"<?php echo $is_log ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Log', 'designslabz-relocate' ); ?></a>
</nav>

<?php if ( $is_log && $args['table']->job_filter() ) : ?>
	<p class="dlz-filter-note">
		<?php
		/* translators: %d: job number. */
		echo esc_html( sprintf( __( 'Showing entries for job #%d.', 'designslabz-relocate' ), $args['table']->job_filter() ) );
		?>
		<a href="<?php echo esc_url( Admin::url( 'history', array( 'view' => 'log' ) ) ); ?>"><?php esc_html_e( 'Show all entries', 'designslabz-relocate' ); ?></a>
	</p>
<?php endif; ?>

<div class="dlz-card dlz-card-flush">
	<?php $args['table']->views(); ?>
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGE . '-history' ); ?>">
		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Keeps the current filters when searching.
		foreach ( array( 'view', 'type', 'level', 'log_job' ) as $keep ) {
			if ( isset( $_GET[ $keep ] ) ) {
				printf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( $keep ), esc_attr( sanitize_key( wp_unslash( $_GET[ $keep ] ) ) ) );
			}
		}
		// phpcs:enable

		$args['table']->search_box( $is_log ? __( 'Search log', 'designslabz-relocate' ) : __( 'Search jobs', 'designslabz-relocate' ), 'dlz-history' );
		$args['table']->display();
		?>
	</form>
</div>
