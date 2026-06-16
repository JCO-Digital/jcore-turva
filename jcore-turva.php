<?php
/**
 * Plugin Name:       JCORE Turva
 * Description:       Security header management - CSP, Permissions Policy, and violation reporting.
 * Version:           1.9.1
 * Requires at least: 6.7
 * Tested up to:      7.0
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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

define( 'JCORE_TURVA_PLUGIN_FILE', __FILE__ );
define( 'JCORE_TURVA_PLUGIN_DIR', __DIR__ );
define( 'JCORE_TURVA_BUILD_DIR', __DIR__ . '/build' );

spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'Jcore\\Turva\\' ) ) {
			return;
		}
		$name = substr( $class_name, strlen( 'Jcore\\Turva\\' ) );
		$name = strtolower( str_replace( '_', '-', $name ) );
		$file = __DIR__ . "/includes/class-{$name}.php";
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( Database::class, 'install' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'init' ) );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=jcore-turva' ) . '">' . __( 'Settings', 'jcore-turva' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

add_filter(
	'jcore_plugins_loaded',
	static function ( array $plugins ): array {
		$plugins['jcore-turva'] = __DIR__;
		return $plugins;
	}
);
