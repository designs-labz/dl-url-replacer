<?php
/**
 * Settings.
 *
 * @package DesignsLabz\Relocate
 */

use DesignsLabz\Relocate\Settings;

defined( 'ABSPATH' ) || exit;

// Only options-general.php pages print these automatically.
settings_errors();
?>
<h2 class="dlz-title"><?php esc_html_e( 'Settings', 'dl-relocate-db' ); ?></h2>

<form action="options.php" method="post" class="dlz-card dlz-settings">
	<?php
	settings_fields( Settings::OPTION );
	do_settings_sections( Settings::OPTION );
	submit_button();
	?>
</form>
