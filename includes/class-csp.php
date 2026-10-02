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
	 * Directives whose Google sources get every regional TLD when Google
	 * multi-domain support is on.
	 */
	public const GOOGLE_EXPANDED_DIRECTIVES = array( 'connect-src', 'img-src' );

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
			foreach ( self::GOOGLE_EXPANDED_DIRECTIVES as $directive ) {
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
		$expanded = array();
		foreach ( $sources as $source ) {
			$expanded[] = $source;
			$match      = self::match_google_domain( $source );

			if ( $match ) {
				foreach ( self::google_suffixes() as $s ) {
					$new_source = $match['prefix'] . ( $match['dotless'] ? ltrim( $s, '.' ) : $s );
					if ( $new_source !== $source ) {
						$expanded[] = $new_source;
					}
				}
			}
		}
		return array_unique( $expanded );
	}

	/**
	 * Splits sources into ones to keep and ones the Google multi-domain
	 * expansion would generate anyway. Of each regional family (e.g.
	 * *.google.com, *.google.de, *.google.fi) only one source is kept,
	 * preferring the .google.com one; a family already present in
	 * $existing is dropped entirely.
	 *
	 * @param string[] $sources  Sources for a single directive.
	 * @param string[] $existing Sources already stored for that directive.
	 * @return array{keep: string[], redundant: array<string, string>} Redundant
	 *         sources map to the source that covers them.
	 */
	public static function filter_redundant_google_sources( array $sources, array $existing = array() ): array {
		$taken = array();
		foreach ( $existing as $source ) {
			$key = self::google_family_key( $source );
			if ( null !== $key ) {
				$taken[ $key ] = $source;
			}
		}

		// The .google.com variant is the most readable representative.
		foreach ( $sources as $source ) {
			$match = self::match_google_domain( $source );
			$key   = self::google_family_key( $source );
			if ( $match && ! isset( $taken[ $key ] ) && 'google.com' === ltrim( $match['suffix'], '.' ) ) {
				$taken[ $key ] = $source;
			}
		}

		$keep      = array();
		$redundant = array();
		foreach ( $sources as $source ) {
			$key = self::google_family_key( $source );
			if ( null === $key ) {
				$keep[] = $source;
				continue;
			}
			if ( ! isset( $taken[ $key ] ) ) {
				$taken[ $key ] = $source;
			}
			if ( $taken[ $key ] === $source ) {
				$keep[] = $source;
			} else {
				$redundant[ $source ] = $taken[ $key ];
			}
		}

		return array(
			'keep'      => $keep,
			'redundant' => $redundant,
		);
	}

	/**
	 * Identifies the regional family a Google source belongs to: sources that
	 * differ only in their Google TLD share a key.
	 *
	 * @param string $source A CSP source.
	 * @return string|null Null when the source isn't a Google domain.
	 */
	private static function google_family_key( string $source ): ?string {
		$match = self::match_google_domain( $source );
		return $match ? ( $match['dotless'] ? 'host:' : 'sub:' ) . $match['prefix'] : null;
	}

	/**
	 * Matches a source against the Google domain list.
	 *
	 * @param string $source A CSP source.
	 * @return array{prefix: string, suffix: string, dotless: bool}|null
	 */
	private static function match_google_domain( string $source ): ?array {
		foreach ( self::google_suffixes() as $suffix ) {
			// Check if source ends with the suffix (e.g. *.google.com ends with .google.com).
			if ( str_ends_with( $source, $suffix ) ) {
				return array(
					'prefix'  => substr( $source, 0, -strlen( $suffix ) ),
					'suffix'  => $suffix,
					'dotless' => false,
				);
			}
			// Check if source matches the domain (e.g. google.com, //google.com, https://google.com).
			$dotless = ltrim( $suffix, '.' );
			if ( $source === $dotless || str_ends_with( $source, '//' . $dotless ) ) {
				return array(
					'prefix'  => substr( $source, 0, -strlen( $dotless ) ),
					'suffix'  => $dotless,
					'dotless' => true,
				);
			}
		}
		return null;
	}

	/**
	 * Google domain suffixes, longest first so e.g. .google.com.au wins over
	 * .google.com.
	 *
	 * @return string[]
	 */
	private static function google_suffixes(): array {
		static $suffixes = null;
		if ( null === $suffixes ) {
			$suffixes = Google_Domains::SUFFIXES;
			usort(
				$suffixes,
				function ( $a, $b ) {
					return strlen( $b ) <=> strlen( $a );
				}
			);
		}
		return $suffixes;
	}
}
