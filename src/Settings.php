<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate;

/**
 * Plugin settings, stored as a single option and edited through the Settings API.
 */
final class Settings {

	public const OPTION = 'dlz_relocate_settings';

	private const DEFAULTS = array(
		'batch_size'  => 500,
		'delete_data' => false,
	);

	private const MIN_BATCH_SIZE = 50;
	private const MAX_BATCH_SIZE = 5000;

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'option_page_capability_' . self::OPTION, fn() => Plugin::CAPABILITY );
	}

	public function register_setting(): void {
		register_setting(
			self::OPTION,
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => self::DEFAULTS,
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		add_settings_section( 'processing', __( 'Processing', 'designslabz-relocate' ), '__return_false', self::OPTION );

		add_settings_field(
			'batch_size',
			__( 'Rows per batch', 'designslabz-relocate' ),
			array( $this, 'render_batch_size_field' ),
			self::OPTION,
			'processing',
			array( 'label_for' => 'dlz-relocate-batch-size' )
		);

		add_settings_section( 'data', __( 'Data', 'designslabz-relocate' ), '__return_false', self::OPTION );

		add_settings_field(
			'delete_data',
			__( 'Uninstall', 'designslabz-relocate' ),
			array( $this, 'render_delete_data_field' ),
			self::OPTION,
			'data'
		);
	}

	/**
	 * @param mixed $input Raw submitted value.
	 * @return array{batch_size: int, delete_data: bool}
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'batch_size'  => min( self::MAX_BATCH_SIZE, max( self::MIN_BATCH_SIZE, absint( $input['batch_size'] ?? self::DEFAULTS['batch_size'] ) ) ),
			'delete_data' => ! empty( $input['delete_data'] ),
		);
	}

	public function render_batch_size_field(): void {
		printf(
			'<input type="number" id="dlz-relocate-batch-size" name="%1$s[batch_size]" value="%2$d" min="%3$d" max="%4$d" step="50" class="small-text" aria-describedby="dlz-relocate-batch-size-description"><p class="description" id="dlz-relocate-batch-size-description">%5$s</p>',
			esc_attr( self::OPTION ),
			(int) $this->batch_size(),
			(int) self::MIN_BATCH_SIZE,
			(int) self::MAX_BATCH_SIZE,
			esc_html__( 'How many rows are read from a table at a time. Lower it if processing runs out of memory on tables with very large rows, such as page builder content.', 'designslabz-relocate' )
		);
	}

	public function render_delete_data_field(): void {
		printf(
			'<fieldset><legend class="screen-reader-text">%1$s</legend><label for="dlz-relocate-delete-data"><input type="checkbox" id="dlz-relocate-delete-data" name="%2$s[delete_data]" value="1" %3$s> %4$s</label><p class="description">%5$s</p></fieldset>',
			esc_html__( 'Uninstall', 'designslabz-relocate' ),
			esc_attr( self::OPTION ),
			checked( $this->delete_data_on_uninstall(), true, false ),
			esc_html__( 'Delete all plugin data when the plugin is deleted', 'designslabz-relocate' ),
			esc_html__( 'Removes the operation history, logs and settings. Your site content is not affected.', 'designslabz-relocate' )
		);
	}

	public function batch_size(): int {
		return (int) $this->all()['batch_size'];
	}

	public function delete_data_on_uninstall(): bool {
		return (bool) $this->all()['delete_data'];
	}

	/**
	 * @return array{batch_size: int, delete_data: bool}
	 */
	private function all(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : array() );
	}
}
