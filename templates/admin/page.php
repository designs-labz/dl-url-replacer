<?php
/**
 * Page shell: branded header with section navigation, then the section.
 *
 * @package DesignsLabz\Relocate
 *
 * @var array $args
 */

use DesignsLabz\Relocate\Admin\Admin;
use DesignsLabz\Relocate\Plugin;

defined( 'ABSPATH' ) || exit;

$icons = array(
	'dashboard'      => 'dashboard',
	'search-replace' => 'search',
	'history'        => 'backup',
	'database'       => 'database',
	'settings'       => 'admin-generic',
);
?>
<div class="wrap dlz-relocate">
	<header class="dlz-header">
		<div class="dlz-brand">
			<span class="dlz-brand-mark dashicons dashicons-migrate" aria-hidden="true"></span>
			<h1 class="dlz-brand-name">
				<?php esc_html_e( 'DesignsLabz Relocate', 'designslabz-relocate' ); ?>
				<span class="dlz-version"><?php echo esc_html( Plugin::VERSION ); ?></span>
			</h1>
		</div>
		<nav class="dlz-nav" aria-label="<?php esc_attr_e( 'DesignsLabz Relocate sections', 'designslabz-relocate' ); ?>">
			<?php foreach ( $args['sections'] as $section => $label ) : ?>
				<?php $active = $section === $args['current']; ?>
				<a href="<?php echo esc_url( Admin::url( $section ) ); ?>" class="dlz-nav-link<?php echo $active ? ' is-active' : ''; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
					<span class="dashicons dashicons-<?php echo esc_attr( $icons[ $section ] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</header>
	<hr class="wp-header-end">

	<main class="dlz-main">
		<?php require __DIR__ . '/' . $args['current'] . '.php'; ?>
	</main>
</div>
