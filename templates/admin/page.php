<?php
/**
 * Admin page wrapper with tab navigation.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dlz-relocate">
	<h1><?php esc_html_e( 'DesignsLabz Relocate', 'designslabz-relocate' ); ?></h1>
	<hr class="wp-header-end">

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'DesignsLabz Relocate sections', 'designslabz-relocate' ); ?>">
		<?php foreach ( $args['tabs'] as $slug => $label ) : ?>
			<?php $active = $slug === $args['current']; ?>
			<a href="<?php echo esc_url( \DesignsLabz\Relocate\Admin\Admin::url( $slug ) ); ?>" class="nav-tab<?php echo $active ? ' nav-tab-active' : ''; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php require __DIR__ . '/' . $args['current'] . '.php'; ?>
</div>
