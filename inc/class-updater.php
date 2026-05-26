<?php
/**
 * Updater class for JCore Turva.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

use stdClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin updates via JCore Update API.
 */
class Updater {
	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Current version.
	 *
	 * @var string
	 */
	private string $current_version;

	/**
	 * Update API URL.
	 *
	 * @var string
	 */
	private string $update_url;

	/**
	 * License key.
	 *
	 * @var string
	 */
	private string $license_key;

	/**
	 * Plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param string $slug            Plugin slug.
	 * @param string $version         Current version.
	 * @param string $update_url      Update API URL.
	 * @param string $plugin_file     Main plugin file path.
	 * @param string $license_key     Optional license key.
	 */
	public function __construct( string $slug, string $version, string $update_url, string $plugin_file, string $license_key = '' ) {
		$this->slug            = $slug;
		$this->current_version = $version;
		$this->update_url      = $update_url;
		$this->plugin_file     = $plugin_file;
		$this->license_key     = $license_key;

		// Hook into update checks.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );

		// Hook into plugin details popup.
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 20, 3 );
	}

	/**
	 * Checks for updates.
	 *
	 * @param object $transient Site transient for updates.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->request();

		if ( $remote && version_compare( $this->current_version, $remote->new_version, '<' ) ) {
			$res              = new stdClass();
			$res->slug        = $this->slug;
			$res->plugin      = plugin_basename( $this->plugin_file );
			$res->new_version = $remote->new_version;
			$res->tested      = $remote->tested;
			$res->package     = $remote->package;
			$res->url         = $remote->url;

			$transient->response[ $res->plugin ] = $res;
		}

		return $transient;
	}

	/**
	 * Provides plugin information for the popup.
	 *
	 * @param object|bool $res    Response object.
	 * @param string      $action The action being performed.
	 * @param object      $args   Arguments.
	 * @return object|bool
	 */
	public function plugin_popup( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || $args->slug !== $this->slug ) {
			return $res;
		}

		$remote = $this->request();

		if ( ! $remote ) {
			return $res;
		}

		$res                = new stdClass();
		$res->name          = 'JCore Turva';
		$res->slug          = $this->slug;
		$res->version       = $remote->new_version;
		$res->tested        = $remote->tested;
		$res->requires      = $remote->requires;
		$res->requires_php  = $remote->requires_php;
		$res->download_link = $remote->package;
		$res->sections      = (array) $remote->sections;
		$res->last_updated  = gmdate( 'Y-m-d H:i:s' );

		return $res;
	}

	/**
	 * Makes a request to the update API.
	 *
	 * @return object|false
	 */
	private function request() {
		$url = add_query_arg(
			array(
				'slug'        => $this->slug,
				'version'     => $this->current_version,
				'license_key' => $this->license_key,
			),
			$this->update_url
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}
}
