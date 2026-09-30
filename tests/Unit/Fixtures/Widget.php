<?php
declare( strict_types=1 );

namespace DesignsLabz\Relocate\Tests\Unit\Fixtures;

/**
 * An object with every property visibility, as plugins store in options.
 */
final class Widget {

	public string $link;

	protected string $image;

	/** @var array<string, mixed> */
	private array $settings;

	public function __construct( string $base_url ) {
		$this->link     = $base_url . '/about';
		$this->image    = $base_url . '/logo.png';
		$this->settings = array(
			'status' => Status::Active,
			'ratio'  => 0.1,
			'links'  => array( $base_url . '/a', $base_url . '/b' ),
		);
	}
}
