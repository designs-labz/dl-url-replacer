<?php
/**
 * Settings tab.
 *
 * @package DesignsLabz\Relocate
 */

use DesignsLabz\Relocate\Settings;

defined( 'ABSPATH' ) || exit;

// Only options-general.php pages print these automatically.
settings_errors();
?>
<form action="options.php" method="post">
	<?php
	settings_fields( Settings::OPTION );
	do_settings_sections( Settings::OPTION );
	submit_button();
	?>
</form>
