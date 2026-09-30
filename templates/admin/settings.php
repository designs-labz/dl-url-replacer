<?php
/**
 * Settings.
 *
 * @package CraftRoq\Relocate
 */

use CraftRoq\Relocate\Settings;

defined( 'ABSPATH' ) || exit;

// Only options-general.php pages print these automatically.
settings_errors();
?>
<h2 class="crq-title"><?php esc_html_e( 'Settings', 'cr-relocate-db' ); ?></h2>

<form action="options.php" method="post" class="crq-card crq-settings">
	<?php
	settings_fields( Settings::OPTION );
	do_settings_sections( Settings::OPTION );
	submit_button();
	?>
</form>
