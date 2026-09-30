<?php
/**
 * History tab: the job list, the log, or a single job.
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
?>
<ul class="subsubsub">
	<li><a href="<?php echo esc_url( Admin::url( 'history' ) ); ?>"<?php echo 'jobs' === $args['view'] ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Jobs', 'designslabz-relocate' ); ?></a> |</li>
	<li><a href="<?php echo esc_url( Admin::url( 'history', array( 'view' => 'log' ) ) ); ?>"<?php echo 'log' === $args['view'] ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Log', 'designslabz-relocate' ); ?></a></li>
</ul>
<br class="clear">

<?php
$args['table']->views();
$args['table']->display();
