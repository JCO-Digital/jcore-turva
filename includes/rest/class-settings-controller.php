<?php
/**
 * REST routes for the general settings.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva\Rest;

use Jcore\Turva\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the settings array the header builders consult.
 */
final class Settings_Controller extends Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Setting keys accepted from the client. Anything else is ignored.
	 */
	private const ALLOWED_KEYS = array(
		'hsts',
		'hsts_max_age',
		'nosniff',
		'xss_protection',
		'referrer_policy',
		'referrer_value',
		'csp_mode',
		'csp_test_mode',
		'google_multi_domain',
		'disable_jcore2',
	);

	/**
	 * Registers the settings routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				$this->route( \WP_REST_Server::READABLE, 'get_item' ),
				$this->route( \WP_REST_Server::CREATABLE, 'update_item' ),
			)
		);
	}

	/**
	 * GET /settings — the stored settings array.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ): \WP_REST_Response {
		return rest_ensure_response( get_option( Database::SETTINGS_OPTION, array() ) );
	}

	/**
	 * POST /settings — merges the supplied keys into the stored settings.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_item( $request ): \WP_REST_Response {
		$settings = get_option( Database::SETTINGS_OPTION, array() );

		foreach ( self::ALLOWED_KEYS as $key ) {
			if ( $request->has_param( $key ) ) {
				$settings[ $key ] = $request->get_param( $key );
			}
		}

		update_option( Database::SETTINGS_OPTION, $settings );

		return rest_ensure_response( $settings );
	}
}
