<?php
/**
 * Compatibility with other JCORE security implementations.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects and disables the JCORE 2 theme security module.
 *
 * The JCORE 2 theme ships a `\jcore\Security` class that sends its own HSTS,
 * nosniff, XSS, Referrer-Policy, CSP and Permissions-Policy headers, and adds a
 * second "Security" page under Settings. Turva replaces all of that, and two
 * Content-Security-Policy headers are intersected by the browser, so the theme
 * policy would keep blocking resources Turva allows.
 */
class Compat {

	/**
	 * Fully qualified name of the JCORE 2 theme security class.
	 */
	private const JCORE2_CLASS = 'jcore\Security';

	/**
	 * Theme methods to unhook, keyed by the hook they are attached to.
	 */
	private const JCORE2_HOOKS = array(
		'send_headers'  => 'send_headers',
		'acf/init'      => 'add_menu_page',
		'acf/save_post' => 'save_settings',
	);

	/**
	 * Whether the JCORE 2 security module was found on this site.
	 */
	private static bool $jcore2_detected = false;

	/**
	 * Whether any JCORE 2 callback was actually removed.
	 */
	private static bool $jcore2_disabled = false;

	/**
	 * Registers the compatibility hooks.
	 */
	public static function init(): void {
		// After the theme's `after_setup_theme` callbacks have registered everything.
		add_action( 'after_setup_theme', array( self::class, 'maybe_disable_jcore2' ), 99 );
		// Safety net in case a theme registers its header callback later than that.
		add_action( 'send_headers', array( self::class, 'maybe_disable_jcore2' ), 0 );
	}

	/**
	 * Removes the JCORE 2 security callbacks when the module is present.
	 */
	public static function maybe_disable_jcore2(): void {
		if ( ! class_exists( self::JCORE2_CLASS ) ) {
			return;
		}

		self::$jcore2_detected = true;

		if ( ! self::is_jcore2_disable_enabled() ) {
			return;
		}

		foreach ( self::JCORE2_HOOKS as $hook => $method ) {
			if ( self::unhook( $hook, $method ) ) {
				self::$jcore2_disabled = true;
			}
		}
	}

	/**
	 * Whether the JCORE 2 security module exists on this site.
	 */
	public static function is_jcore2_detected(): bool {
		return self::$jcore2_detected;
	}

	/**
	 * Whether Turva actually unhooked the JCORE 2 module.
	 */
	public static function is_jcore2_disabled(): bool {
		return self::$jcore2_disabled;
	}

	/**
	 * Whether the JCORE 2 takeover is enabled. Defaults to on.
	 */
	public static function is_jcore2_disable_enabled(): bool {
		$settings = get_option( 'jcore_turva_settings', array() );
		$enabled  = ! isset( $settings['disable_jcore2'] ) || ! empty( $settings['disable_jcore2'] );

		/**
		 * Filters whether the JCORE 2 theme security module is disabled by Turva.
		 *
		 * @param bool $enabled True to unhook the theme module.
		 */
		return (bool) apply_filters( 'jcore_turva_disable_jcore2', $enabled );
	}

	/**
	 * Option the JCORE 2 theme stores its compiled CSP policy array in.
	 */
	private const JCORE2_CSP_OPTION = 'jcore_security_csp_data';

	/**
	 * Directives the JCORE 2 theme hardcodes in `build_policy_string()`.
	 *
	 * The theme calls it with `$form = "'self' https:"` and the default frame value.
	 */
	private const JCORE2_FIXED_CSP = array(
		'base-uri'        => "'self'",
		'object-src'      => "'none'",
		'form-action'     => "'self' https:",
		'frame-ancestors' => "'self'",
	);

	/**
	 * Reads the JCORE 2 policy and returns it as importable directive/source pairs.
	 *
	 * The theme's `report-uri` and `report-to` are deliberately left out — Turva
	 * appends its own reporting endpoint when it builds the header.
	 *
	 * @param string $header_type Either `csp` or `permissions`.
	 *
	 * @return array<int, array{directive: string, source: string}>
	 */
	public static function get_jcore2_directives( string $header_type ): array {
		if ( ! class_exists( self::JCORE2_CLASS ) ) {
			return array();
		}

		return 'permissions' === $header_type
			? self::jcore2_permissions_directives()
			: self::jcore2_csp_directives();
	}

	/**
	 * Builds the CSP directive list from the theme's stored or freshly built policy.
	 *
	 * @return array<int, array{directive: string, source: string}>
	 */
	private static function jcore2_csp_directives(): array {
		$policy = get_option( self::JCORE2_CSP_OPTION );

		// The theme only writes the option once its settings are saved.
		if ( ! is_array( $policy ) || empty( $policy ) ) {
			$policy = self::call_jcore2( 'build_policy_array' );
		}

		if ( ! is_array( $policy ) ) {
			return array();
		}

		$directives = self::JCORE2_FIXED_CSP;
		foreach ( $policy as $name => $sources ) {
			// Theme policy keys omit the `-src` suffix: `script` => `script-src`.
			$directives[ $name . '-src' ] = $sources;
		}

		$pairs = array();
		foreach ( $directives as $directive => $sources ) {
			foreach ( preg_split( '/\s+/', trim( (string) $sources ), -1, PREG_SPLIT_NO_EMPTY ) as $source ) {
				$pairs[] = array(
					'directive' => $directive,
					'source'    => $source,
				);
			}
		}

		return $pairs;
	}

	/**
	 * Builds the Permissions-Policy directive list from the theme settings.
	 *
	 * Theme values look like `()`, `*`, `(self)` or `(self "https://example.com")`.
	 * An empty allowlist becomes a single empty source, which is how Turva renders
	 * `feature=()` — dropping it would silently re-allow the feature.
	 *
	 * @return array<int, array{directive: string, source: string}>
	 */
	private static function jcore2_permissions_directives(): array {
		$permissions = self::call_jcore2( 'build_permissions_array' );

		if ( ! is_array( $permissions ) ) {
			return array();
		}

		$pairs = array();
		foreach ( $permissions as $feature => $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			if ( str_starts_with( $value, '(' ) && str_ends_with( $value, ')' ) ) {
				$value = substr( $value, 1, -1 );
			}

			$sources = preg_split( '/\s+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY );
			// An empty allowlist denies the feature and must survive the import.
			foreach ( empty( $sources ) ? array( '' ) : $sources as $source ) {
				$pairs[] = array(
					'directive' => $feature,
					'source'    => $source,
				);
			}
		}

		return $pairs;
	}

	/**
	 * Calls a static method on the theme security class if it is callable.
	 *
	 * @param string $method Method name.
	 */
	private static function call_jcore2( string $method ): mixed {
		$callback = array( self::JCORE2_CLASS, $method );

		return is_callable( $callback ) ? call_user_func( $callback ) : null;
	}

	/**
	 * Removes every JCORE 2 callback for a method from a hook, at any priority.
	 *
	 * @param string $hook   Hook name.
	 * @param string $method Static method name on the security class.
	 *
	 * @return int Number of callbacks removed.
	 */
	private static function unhook( string $hook, string $method ): int {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof \WP_Hook ) {
			return 0;
		}

		$targets = array();
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( self::is_jcore2_callback( $callback['function'], $method ) ) {
					$targets[] = array( $priority, $callback['function'] );
				}
			}
		}

		foreach ( $targets as list( $priority, $function ) ) {
			remove_action( $hook, $function, $priority );
		}

		return count( $targets );
	}

	/**
	 * Checks whether a registered callback is the given JCORE 2 security method.
	 *
	 * Matches both the `Class::method` string and `array( Class, 'method' )` forms,
	 * and any child theme class extending the theme security class.
	 *
	 * @param mixed  $function The registered callback.
	 * @param string $method   Static method name to match.
	 */
	private static function is_jcore2_callback( mixed $function, string $method ): bool {
		if ( is_string( $function ) && str_contains( $function, '::' ) ) {
			list( $class, $callback_method ) = explode( '::', $function, 2 );
		} elseif ( is_array( $function ) && 2 === count( $function ) ) {
			list( $class, $callback_method ) = $function;
			$class                           = is_object( $class ) ? get_class( $class ) : (string) $class;
		} else {
			return false;
		}

		if ( $callback_method !== $method ) {
			return false;
		}

		$class = ltrim( (string) $class, '\\' );

		return 0 === strcasecmp( $class, self::JCORE2_CLASS ) || is_subclass_of( $class, self::JCORE2_CLASS );
	}
}
