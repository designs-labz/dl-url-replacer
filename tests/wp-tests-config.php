<?php
/**
 * WordPress test suite configuration, read from environment variables.
 *
 * The suite drops and recreates its tables, so point it at a database used
 * for nothing else.
 *
 * @package CraftRoq\Relocate
 */

function crq_relocate_tests_env( string $name, string $fallback ): string {
	$value = getenv( $name );

	return false === $value || '' === $value ? $fallback : $value;
}

define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/' );

define( 'DB_NAME', crq_relocate_tests_env( 'WP_TESTS_DB_NAME', 'relocate_tests' ) );
define( 'DB_USER', crq_relocate_tests_env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', crq_relocate_tests_env( 'WP_TESTS_DB_PASSWORD', '' ) );
define( 'DB_HOST', crq_relocate_tests_env( 'WP_TESTS_DB_HOST', '127.0.0.1' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
// The suite runs this in a shell command unquoted, and PHP paths can contain spaces.
define( 'WP_PHP_BINARY', escapeshellarg( PHP_BINARY ) );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
