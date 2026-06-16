<?php
/**
 * Builds the Permissions-Policy header value from stored directives.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries the sources table and compiles the Permissions-Policy header string.
 */
class Permissions {

	/**
	 * Builds and returns the full Permissions-Policy header value, or empty string if no directives exist.
	 */
	public static function build_header(): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT directive, source FROM %i WHERE header_type = %s AND enabled = 1 ORDER BY directive, id',
				$wpdb->prefix . 'jcore_security_sources',
				'permissions'
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
			$parts[] = $directive . '=(' . implode( ' ', $sources ) . ')';
		}

		return implode( ', ', $parts );
	}
}
