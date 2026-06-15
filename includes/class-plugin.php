<?php
/**
 * Plugin bootstrap — admin pages and asset enqueueing.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Hooks\PluginUpdateHooks;
use Jcore\Update\Support\PluginHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Wires up actions and registers the admin page.
 */
class Plugin {

	private static bool $initialized = false;

	/**
	 * Registers all hooks. Called once on plugins_loaded.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		Database::maybe_upgrade();

		$config = new UpdateConfig(
			pluginFile: JCORE_TURVA_PLUGIN_FILE,
			slug: 'jcore-turva',
			version: PluginHelper::getVersion( __FILE__ ),
			apiBaseUrl: 'https://update.jcore.fi/v1',
		);
		( new PluginUpdateHooks( $config ) )->register();

		add_action( 'init', array( self::class, 'load_textdomain' ) );
		add_action( 'send_headers', array( Headers::class, 'send' ) );
		Rest_Api::register();

		if ( is_admin() ) {
			add_action( 'admin_menu', array( self::class, 'add_menu_page' ) );
			add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		}
	}

	/**
	 * Loads the plugin textdomain for PHP translations.
	 */
	public static function load_textdomain(): void {
		$loaded = load_plugin_textdomain(
			'jcore-turva',
			false,
			dirname( plugin_basename( JCORE_TURVA_PLUGIN_FILE ) ) . '/languages'
		);
		if ( ! $loaded ) {
			// Fallback for some environments.
			load_plugin_textdomain( 'jcore-turva', false, 'jcore-turva/languages' );
		}
	}

	/**
	 * Registers the Settings > Security sub-page.
	 */
	public static function add_menu_page(): void {
		add_options_page(
			__( 'Security', 'jcore-turva' ),
			__( 'Security', 'jcore-turva' ),
			'manage_options',
			'jcore-turva',
			array( self::class, 'render_page' ),
		);
	}

	/**
	 * Outputs the React app mount point.
	 */
	public static function render_page(): void {
		echo '<div id="jcore-turva-app"></div>';
	}

	/**
	 * Enqueues the React app only on our admin page.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'settings_page_jcore-turva' !== $hook ) {
			return;
		}

		$asset_file = JCORE_TURVA_BUILD_DIR . '/security.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'jcore-turva-security',
			plugins_url( 'build/security.js', JCORE_TURVA_PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true,
		);

		wp_set_script_translations( 'jcore-turva-security', 'jcore-turva', JCORE_TURVA_PLUGIN_DIR . '/languages' );

		$css_file = JCORE_TURVA_BUILD_DIR . '/style-security.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'jcore-turva-security',
				plugins_url( 'build/style-security.css', JCORE_TURVA_PLUGIN_FILE ),
				array( 'wp-components' ),
				$asset['version'],
			);
		}

		wp_localize_script(
			'jcore-turva-security',
			'jcoreTurva',
			array(
				'apiUrl' => rest_url( 'jcore-turva/v1' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
