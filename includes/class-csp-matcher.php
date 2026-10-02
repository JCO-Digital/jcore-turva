<?php
/**
 * Checks whether a reported violation would be allowed by the current policy.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSP Level 3 source-expression matching, limited to what violation reports need.
 *
 * @see https://www.w3.org/TR/CSP3/#match-url-to-source-expression
 */
class Csp_Matcher {

	/**
	 * Fetch directives that fall back to another directive when they are missing.
	 * Anything not listed here falls back to nothing.
	 */
	private const FALLBACKS = array(
		'script-src-elem' => array( 'script-src', 'default-src' ),
		'script-src-attr' => array( 'script-src', 'default-src' ),
		'script-src'      => array( 'default-src' ),
		'style-src-elem'  => array( 'style-src', 'default-src' ),
		'style-src-attr'  => array( 'style-src', 'default-src' ),
		'style-src'       => array( 'default-src' ),
		'worker-src'      => array( 'child-src', 'script-src', 'default-src' ),
		'frame-src'       => array( 'child-src', 'default-src' ),
		'child-src'       => array( 'default-src' ),
		'connect-src'     => array( 'default-src' ),
		'font-src'        => array( 'default-src' ),
		'img-src'         => array( 'default-src' ),
		'manifest-src'    => array( 'default-src' ),
		'media-src'       => array( 'default-src' ),
		'object-src'      => array( 'default-src' ),
	);

	/**
	 * Browser `blocked-uri` shorthands that stand for a scheme. Mirrors
	 * BLOCKED_URI_TO_SOURCE in src/security/utils.js.
	 */
	private const SCHEME_KEYWORDS = array( 'data', 'blob', 'filesystem' );

	/**
	 * Default ports, for comparing a URL without an explicit port.
	 */
	private const DEFAULT_PORTS = array(
		'http'  => 80,
		'https' => 443,
		'ws'    => 80,
		'wss'   => 443,
		'ftp'   => 21,
	);

	/**
	 * Whether the resource behind a report is allowed by the policy.
	 *
	 * @param string                       $violated_directive The report's violated or effective directive.
	 * @param string                       $blocked_uri        The report's blocked URI or keyword (`inline`, `eval`, …).
	 * @param array<string, string[]>|null $policy             Sources by directive; defaults to the stored policy.
	 * @param string|null                  $self_origin        Origin `'self'` stands for; defaults to the site URL.
	 *
	 * @return bool False when the policy is empty, since then nothing is enforced.
	 */
	public static function is_allowed( string $violated_directive, string $blocked_uri, ?array $policy = null, ?string $self_origin = null ): bool {
		$policy = $policy ?? Csp::get_policy();
		if ( empty( $policy ) ) {
			return false;
		}

		$directive = strtolower( strtok( trim( $violated_directive ), " \t" ) );
		$sources   = self::effective_sources( $directive, $policy );
		if ( null === $sources ) {
			// No directive restricts this kind of resource.
			return true;
		}

		$sources = array_map( 'trim', $sources );
		$keyword = strtolower( $blocked_uri );

		if ( 'inline' === $keyword ) {
			return self::allows_inline( $sources );
		}
		if ( 'eval' === $keyword ) {
			return in_array( "'unsafe-eval'", $sources, true );
		}
		if ( 'wasm-eval' === $keyword ) {
			return in_array( "'wasm-unsafe-eval'", $sources, true ) || in_array( "'unsafe-eval'", $sources, true );
		}
		if ( in_array( $keyword, self::SCHEME_KEYWORDS, true ) ) {
			$blocked_uri = $keyword . ':';
		}

		$url = self::parse_url( $blocked_uri );
		if ( ! $url ) {
			return false;
		}

		// 'strict-dynamic' makes browsers ignore host and scheme sources for scripts.
		if ( str_starts_with( $directive, 'script-src' ) && in_array( "'strict-dynamic'", $sources, true ) ) {
			return false;
		}

		$site = self::parse_url( $self_origin ?? home_url( '/' ) );

		foreach ( $sources as $source ) {
			if ( self::matches( $source, $url, $site ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The source list that applies to a directive, following the fallback chain.
	 *
	 * @param string                  $directive Directive name.
	 * @param array<string, string[]> $policy    Sources by directive.
	 *
	 * @return string[]|null Null when neither the directive nor a fallback is set.
	 */
	private static function effective_sources( string $directive, array $policy ): ?array {
		foreach ( array_merge( array( $directive ), self::FALLBACKS[ $directive ] ?? array() ) as $candidate ) {
			if ( isset( $policy[ $candidate ] ) ) {
				return $policy[ $candidate ];
			}
		}

		return null;
	}

	/**
	 * Whether inline code is allowed. Browsers ignore 'unsafe-inline' when a nonce,
	 * a hash or 'strict-dynamic' is present.
	 *
	 * @param string[] $sources Source list.
	 *
	 * @return bool
	 */
	private static function allows_inline( array $sources ): bool {
		if ( ! in_array( "'unsafe-inline'", $sources, true ) ) {
			return false;
		}

		foreach ( $sources as $source ) {
			if ( "'strict-dynamic'" === $source || preg_match( "/^'(nonce|sha256|sha384|sha512)-/i", $source ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Splits a URL into lowercased scheme and host, an int port and a path.
	 *
	 * @param string $uri URL, or a bare scheme such as `data:`.
	 *
	 * @return array{scheme: string, host: string, port: ?int, path: string}|null
	 */
	private static function parse_url( string $uri ): ?array {
		if ( preg_match( '/^([a-z][a-z0-9+.-]*):(?!\/\/)/i', $uri, $m ) ) {
			// data:, blob:, … carry no host.
			return array(
				'scheme' => strtolower( $m[1] ),
				'host'   => '',
				'port'   => null,
				'path'   => '',
			);
		}

		$parts = wp_parse_url( $uri );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}

		return array(
			'scheme' => strtolower( $parts['scheme'] ),
			'host'   => strtolower( $parts['host'] ),
			'port'   => isset( $parts['port'] ) ? (int) $parts['port'] : null,
			'path'   => rawurldecode( $parts['path'] ?? '' ),
		);
	}

	/**
	 * Whether one source expression matches a URL.
	 *
	 * @param string     $source Source expression.
	 * @param array      $url    Parsed URL.
	 * @param array|null $site   Parsed origin of the protected site.
	 *
	 * @return bool
	 */
	private static function matches( string $source, array $url, ?array $site ): bool {
		$lower = strtolower( $source );

		if ( '*' === $lower ) {
			// '*' leaves out data:, blob: and other schemes without a host.
			return isset( self::DEFAULT_PORTS[ $url['scheme'] ] ) || ( $site && $url['scheme'] === $site['scheme'] );
		}

		if ( "'self'" === $lower ) {
			return $site
				&& self::scheme_matches( $site['scheme'], $url['scheme'] )
				&& $url['host'] === $site['host']
				&& self::port_of( $url ) === self::port_of( $site );
		}

		if ( str_starts_with( $lower, "'" ) ) {
			// Other keywords, nonces and hashes never match a URL.
			return false;
		}

		if ( preg_match( '/^([a-z][a-z0-9+.-]*):$/', $lower, $m ) ) {
			return self::scheme_matches( $m[1], $url['scheme'] );
		}

		if ( ! preg_match( '~^(?:([a-z][a-z0-9+.-]*)://)?(\*|(?:\*\.)?[^/:]+)(?::(\d+|\*))?(/.*)?$~', $lower, $m ) ) {
			return false;
		}

		$scheme = $m[1];
		$host   = $m[2];
		$port   = $m[3] ?? '';
		$path   = $m[4] ?? '';

		if ( '' === $url['host'] ) {
			return false;
		}

		if ( '' !== $scheme ) {
			if ( ! self::scheme_matches( $scheme, $url['scheme'] ) ) {
				return false;
			}
		} elseif ( ! self::scheme_matches( $site['scheme'] ?? 'https', $url['scheme'] ) ) {
			return false;
		}

		if ( '*' !== $host ) {
			if ( str_starts_with( $host, '*.' ) ) {
				// *.example.com matches subdomains, not example.com itself.
				if ( ! str_ends_with( $url['host'], substr( $host, 1 ) ) ) {
					return false;
				}
			} elseif ( $url['host'] !== $host ) {
				return false;
			}
		}

		if ( '*' !== $port ) {
			$url_port = self::port_of( $url );
			if ( '' === $port ) {
				// No port means the scheme's default; an http source also allows https on 443.
				$allowed = array( self::DEFAULT_PORTS[ '' !== $scheme ? $scheme : $url['scheme'] ] ?? null, self::DEFAULT_PORTS[ $url['scheme'] ] ?? null );
				if ( ! in_array( $url_port, $allowed, true ) ) {
					return false;
				}
			} elseif ( (int) $port !== $url_port && ! ( 80 === (int) $port && 443 === $url_port ) ) {
				return false;
			}
		}

		if ( '' === $path || '/' === $path ) {
			return true;
		}

		$path = rawurldecode( $path );

		// A trailing slash matches the directory and everything below it, otherwise only the exact path.
		return str_ends_with( $path, '/' )
			? str_starts_with( $url['path'], $path )
			: $url['path'] === $path;
	}

	/**
	 * Whether a URL scheme satisfies a source scheme, including secure upgrades.
	 *
	 * @param string $source_scheme Scheme from the source expression.
	 * @param string $url_scheme    Scheme of the URL.
	 *
	 * @return bool
	 */
	private static function scheme_matches( string $source_scheme, string $url_scheme ): bool {
		if ( $source_scheme === $url_scheme ) {
			return true;
		}

		$upgrades = array(
			'http'  => array( 'https', 'ws', 'wss' ),
			'https' => array( 'wss' ),
			'ws'    => array( 'wss' ),
		);

		return in_array( $url_scheme, $upgrades[ $source_scheme ] ?? array(), true );
	}

	/**
	 * The URL's port, or the scheme's default when none is given.
	 *
	 * @param array $url Parsed URL.
	 *
	 * @return int|null
	 */
	private static function port_of( array $url ): ?int {
		return $url['port'] ?? self::DEFAULT_PORTS[ $url['scheme'] ] ?? null;
	}
}
