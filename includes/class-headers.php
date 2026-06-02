<?php
/**
 * Sends all security-related HTTP headers.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into send_headers and outputs each enabled security header.
 */
class Headers {

	/**
	 * Sends security headers. Skipped on REST API requests.
	 */
	public static function send(): void {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$settings = get_option( 'jcore_turva_settings', array() );

		if ( ! empty( $settings['hsts'] ) ) {
			$max_age = (int) ( $settings['hsts_max_age'] ?? 31536000 );
			header( "Strict-Transport-Security: max-age={$max_age}; includeSubDomains" );
		}

		if ( ! empty( $settings['nosniff'] ) ) {
			header( 'X-Content-Type-Options: nosniff' );
		}

		if ( ! empty( $settings['xss_protection'] ) ) {
			header( 'X-XSS-Protection: 1; mode=block' );
		}

		if ( ! empty( $settings['referrer_policy'] ) ) {
			$value = sanitize_text_field( $settings['referrer_value'] ?? 'strict-origin-when-cross-origin' );
			header( "Referrer-Policy: {$value}" );
		}

		$csp = Csp::build_header();
		if ( $csp ) {
			$mode        = $settings['csp_mode'] ?? 'enabled';
			$test_mode   = ! empty( $settings['csp_test_mode'] ) && current_user_can( 'manage_options' );
			$header_name = ( 'report-only' === $mode || ( 'enabled' === $mode && $test_mode ) )
				? 'Content-Security-Policy-Report-Only'
				: 'Content-Security-Policy';

			if ( 'disabled' !== $mode ) {
				header( "{$header_name}: {$csp}" );
			}
		}

		$permissions = Permissions::build_header();
		if ( $permissions ) {
			header( "Permissions-Policy: {$permissions}" );
		}
	}
}
