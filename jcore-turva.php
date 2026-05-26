<?php
/**
 * Plugin Name:       JCORE Turva
 * Description:       Security header management — CSP, Permissions Policy, and violation reporting.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            J&Co Digital
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

define( 'JCORE_TURVA_VERSION', '1.0.0' );
define( 'JCORE_TURVA_PLUGIN_FILE', __FILE__ );
define( 'JCORE_TURVA_PLUGIN_DIR', __DIR__ );
define( 'JCORE_TURVA_BUILD_DIR', __DIR__ . '/build' );

spl_autoload_register(
	static function ( string $class ): void {
		if ( ! str_starts_with( $class, 'Jcore\\Turva\\' ) ) {
			return;
		}
		$name = substr( $class, strlen( 'Jcore\\Turva\\' ) );
		$name = strtolower( str_replace( '_', '-', $name ) );
		$file = __DIR__ . "/inc/class-{$name}.php";
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( Database::class, 'install' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'init' ) );

add_filter(
	'jcore_plugins_loaded',
	static function ( array $plugins ): array {
		$plugins['jcore-turva'] = __DIR__;
		return $plugins;
	}
);
