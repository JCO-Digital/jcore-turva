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

		$settings            = get_option( 'jcore_turva_settings', array() );
		$google_multi_domain = ! empty( $settings['google_multi_domain'] );

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
			if ( $google_multi_domain ) {
				$sources = self::expand_google_domains( $sources );
			}
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

	/**
	 * Expands Google domains to include all regional TLDs if a Google domain is present.
	 *
	 * @param array $sources List of CSP sources for a directive.
	 * @return array Expanded list of sources.
	 */
	private static function expand_google_domains( array $sources ): array {
		$suffixes = Google_Domains::SUFFIXES;
		// Sort by length DESC to match longest first (e.g. .google.com.au vs .google.com).
		usort(
			$suffixes,
			function ( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			}
		);

		$expanded = array();
		foreach ( $sources as $source ) {
			$expanded[]     = $source;
			$matched_suffix = null;
			$is_dotless     = false;

			foreach ( $suffixes as $suffix ) {
				// Check if source ends with the suffix (e.g. *.google.com ends with .google.com).
				if ( str_ends_with( $source, $suffix ) ) {
					$matched_suffix = $suffix;
					break;
				}
				// Check if source is exactly the domain without the leading dot (e.g. google.com)
				// or ends with the protocol + dotless domain (e.g. https://google.com).
				$dotless = ltrim( $suffix, '.' );
				if ( $source === $dotless || str_ends_with( $source, '://' . $dotless ) ) {
					$matched_suffix = $dotless;
					$is_dotless     = true;
					break;
				}
			}

			if ( $matched_suffix ) {
				$prefix = substr( $source, 0, -strlen( $matched_suffix ) );
				foreach ( $suffixes as $s ) {
					$new_source = $prefix . ( $is_dotless ? ltrim( $s, '.' ) : $s );
					if ( $new_source !== $source ) {
						$expanded[] = $new_source;
					}
				}
			}
		}
		return array_unique( $expanded );
	}
}
