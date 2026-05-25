<?php
/**
 * Builds the Content-Security-Policy header value from stored directives.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries the sources table and compiles the CSP header string.
 */
class Csp {

	/**
	 * Builds and returns the full CSP header value, or empty string if no directives are configured.
	 */
	public static function build_header(): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT directive, source FROM `{$wpdb->prefix}jcore_security_sources` WHERE header_type = %s AND enabled = 1 ORDER BY directive, id",
				'csp'
			)
		);

		if ( empty( $rows ) ) {
			return '';
		}

		$directives = array();
		foreach ( $rows as $row ) {
			$directives[ $row->directive ][] = $row->source;
		}

		$parts = array();
		foreach ( $directives as $directive => $sources ) {
			// upgrade-insecure-requests is a boolean flag with no source list.
			if ( 'upgrade-insecure-requests' === $directive ) {
				$parts[] = $directive;
			} else {
				$parts[] = $directive . ' ' . implode( ' ', $sources );
			}
		}

		$parts[] = 'report-uri ' . rest_url( 'jcore-turva/v1/csp-report' );

		return implode( '; ', $parts );
	}
}
