<?php
/**
 * The Settings > Security screen and the React app it hosts.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva\Admin;

use Jcore\Turva\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings page and enqueues its assets.
 */
final class Menu {

	/**
	 * Page slug, also the script and style handle.
	 */
	public const SLUG = 'jcore-turva';

	/**
	 * Hook suffix `add_options_page()` returns for this screen.
	 */
	private const HOOK = 'settings_page_' . self::SLUG;

	/**
	 * Build entry the screen runs on.
	 */
	private const ENTRY = 'security';

	/**
	 * Hooks the page and its assets.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

	/**
	 * Returns the admin URL of the settings screen.
	 *
	 * @return string
	 */
	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	/**
	 * Registers the Settings > Security sub-page.
	 *
	 * @return void
	 */
	public static function add_page(): void {
		add_options_page(
			__( 'Security', 'jcore-turva' ),
			__( 'Security', 'jcore-turva' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Outputs the mount point the React app renders into.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		require JCORE_TURVA_PATH . 'views/admin/page.php';
	}

	/**
	 * Enqueues the React app, on this screen only.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( self::HOOK !== $hook ) {
			return;
		}

		$asset_file = JCORE_TURVA_PATH . 'build/' . self::ENTRY . '.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			self::SLUG,
			JCORE_TURVA_URL . 'build/' . self::ENTRY . '.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( self::SLUG, 'jcore-turva', JCORE_TURVA_PATH . 'languages' );

		wp_add_inline_script(
			self::SLUG,
			sprintf(
				'window.jcoreTurva = %s;',
				wp_json_encode( array( 'jcore2Detected' => Compat::is_jcore2_detected() ) )
			),
			'before'
		);

		$style_file = JCORE_TURVA_PATH . 'build/style-' . self::ENTRY . '.css';
		if ( is_readable( $style_file ) ) {
			wp_enqueue_style(
				self::SLUG,
				JCORE_TURVA_URL . 'build/style-' . self::ENTRY . '.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}
	}
}
