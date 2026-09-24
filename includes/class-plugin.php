<?php
/**
 * Plugin bootstrap.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

use Jcore\Update\Config\UpdateConfig;
use Jcore\Update\Hooks\PluginUpdateHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires every component to its hooks.
 */
final class Plugin {

	/**
	 * The single instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers every hook. Called once on `plugins_loaded`.
	 *
	 * @return void
	 */
	public function boot(): void {
		Database::maybe_upgrade();

		$this->register_updater();

		Compat::init();
		Login::init();

		add_action( 'send_headers', array( Headers::class, 'send' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			Admin\Menu::register();
			add_filter( 'plugin_action_links_' . plugin_basename( JCORE_TURVA_FILE ), array( $this, 'action_links' ) );
		}
	}

	/**
	 * Hooks the plugin into the J&Co Digital update service.
	 *
	 * The library is vendored into the release; a source checkout without a
	 * `composer install` simply runs without update checks.
	 *
	 * @return void
	 */
	private function register_updater(): void {
		if ( ! class_exists( UpdateConfig::class ) ) {
			return;
		}

		$config = new UpdateConfig(
			pluginFile: JCORE_TURVA_FILE,
			slug: 'jcore-turva',
			version: JCORE_TURVA_VERSION,
			apiBaseUrl: 'https://update.jcore.fi/v1',
		);

		( new PluginUpdateHooks( $config ) )->register();
	}

	/**
	 * Registers the REST routes the settings screen talks to.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		( new Rest\Sources_Controller() )->register_routes();
		( new Rest\Settings_Controller() )->register_routes();
		( new Rest\Reports_Controller() )->register_routes();
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 *
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( Admin\Menu::url() ),
				esc_html__( 'Settings', 'jcore-turva' )
			)
		);

		return $links;
	}
}
