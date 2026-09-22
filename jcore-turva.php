<?php
/**
 * Plugin Name:       JCORE Turva
 * Plugin URI:        https://github.com/JCO-Digital/jcore-turva
 * Description:       Security header management - CSP, Permissions Policy, and violation reporting.
 * Version:           1.12.2
 * Requires at least: 6.7
 * Tested up to:      7.1
 * Requires PHP:      8.2
 * Author:            J&Co Digital Oy
 * Author URI:        https://jco.fi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jcore-turva
 * Domain Path:       /languages
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JCORE_TURVA_VERSION', '1.12.2' );
define( 'JCORE_TURVA_FILE', __FILE__ );
define( 'JCORE_TURVA_PATH', plugin_dir_path( __FILE__ ) );
define( 'JCORE_TURVA_URL', plugin_dir_url( __FILE__ ) );

// The update library is vendored into the release, but a source checkout has
// no vendor directory until `composer install` has run.
if ( is_readable( JCORE_TURVA_PATH . 'vendor/autoload.php' ) ) {
	require_once JCORE_TURVA_PATH . 'vendor/autoload.php';
}

/**
 * Autoloads classes from the Jcore\Turva namespace.
 *
 * Maps `Jcore\Turva\Foo\Bar_Baz` to `includes/foo/class-bar-baz.php`, the file
 * naming the WordPress coding standards ask for.
 *
 * @param string $class_name Fully qualified class name.
 *
 * @return void
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$parts = array_map(
			static function ( string $part ): string {
				return strtolower( str_replace( '_', '-', $part ) );
			},
			$parts
		);

		$parts[ array_key_last( $parts ) ] = 'class-' . end( $parts );

		$file = JCORE_TURVA_PATH . 'includes/' . implode( '/', $parts ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( Database::class, 'install' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);

// Registered at file scope, not in Plugin::boot(): other JCORE components read
// this list while plugins are still loading.
add_filter(
	'jcore_plugins_loaded',
	static function ( array $plugins ): array {
		$plugins['jcore-turva'] = __DIR__;

		return $plugins;
	}
);
