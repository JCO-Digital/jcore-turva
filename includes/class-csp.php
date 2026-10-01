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
	 * Object cache key for get_policy().
	 */
	private const POLICY_CACHE_KEY = 'csp_policy';

	/**
	 * Directives Turva manages. Keep in sync with CSP_DIRECTIVES in
	 * src/security/constants.js.
	 */
	public const DIRECTIVES = array(
		'default-src',
		'script-src',
		'script-src-elem',
		'script-src-attr',
		'style-src',
		'style-src-elem',
		'style-src-attr',
		'img-src',
		'font-src',
		'connect-src',
		'media-src',
		'object-src',
		'frame-src',
		'worker-src',
		'manifest-src',
		'child-src',
		'prefetch-src',
		'base-uri',
		'form-action',
		'frame-ancestors',
		'upgrade-insecure-requests',
	);

	/**
	 * Builds and returns the full CSP header value, or empty string if no directives are configured.
	 */
	public static function build_header(): string {
		$directives = self::get_policy();

		if ( empty( $directives ) ) {
			return '';
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

	/**
	 * Returns the enabled CSP sources grouped by directive, exactly as they are sent.
	 *
	 * Cached in the object cache until a source changes, since the public report
	 * endpoint needs it on every request.
	 *
	 * @return array<string, string[]>
	 */
	public static function get_policy(): array {
		global $wpdb;

		$cached = wp_cache_get( self::POLICY_CACHE_KEY, 'jcore_turva' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$settings            = get_option( Database::SETTINGS_OPTION, array() );
		$google_multi_domain = ! empty( $settings['google_multi_domain'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT directive, source FROM %i WHERE header_type = %s AND enabled = 1 ORDER BY directive, id',
				Database::table( 'sources' ),
				'csp'
			)
		);

		$directives = array();
		foreach ( $rows as $row ) {
			$directives[ $row->directive ][] = $row->source;
		}

		if ( $google_multi_domain ) {
			foreach ( array( 'connect-src', 'img-src' ) as $directive ) {
				if ( isset( $directives[ $directive ] ) ) {
					$directives[ $directive ] = self::expand_google_domains( $directives[ $directive ] );
				}
			}
		}

		wp_cache_set( self::POLICY_CACHE_KEY, $directives, 'jcore_turva' );

		return $directives;
	}

	/**
	 * Drops the cached policy. Call after any change to the sources or settings.
	 */
	public static function flush_policy_cache(): void {
		wp_cache_delete( self::POLICY_CACHE_KEY, 'jcore_turva' );
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
				// Check if source matches the domain (e.g. google.com, //google.com, https://google.com).
				$dotless = ltrim( $suffix, '.' );
				if ( $source === $dotless || str_ends_with( $source, '//' . $dotless ) ) {
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
