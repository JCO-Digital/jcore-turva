<?php
/**
 * Login form hardening.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces login errors that reveal whether an account exists.
 *
 * Core tells the visitor whether the username was unknown or the password was
 * wrong for an existing account, which lets anyone enumerate user names. When
 * the option is on, both cases collapse into one generic message. Error codes
 * are kept so login limiters hooked on `wp_login_failed` still see them.
 */
class Login {

	/**
	 * Setting key that turns the feature on.
	 */
	public const SETTING = 'hide_login_errors';

	/**
	 * Error codes whose messages reveal whether the account exists.
	 */
	private const LEAKY_CODES = array(
		'invalid_username',
		'invalid_email',
		'incorrect_password',
		'invalidcombo',
	);

	/**
	 * Registers the login hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// After core's username, email and cookie callbacks at 20, 25 and 30.
		add_filter( 'authenticate', array( self::class, 'mask_errors' ), 99 );
	}

	/**
	 * Whether login errors are hidden. Defaults to off.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$settings = get_option( Database::SETTINGS_OPTION, array() );

		/**
		 * Filters whether login errors are replaced with a generic message.
		 *
		 * @param bool $enabled True to hide the account-revealing details.
		 */
		return (bool) apply_filters( 'jcore_turva_hide_login_errors', ! empty( $settings[ self::SETTING ] ) );
	}

	/**
	 * Swaps account-revealing error messages for a generic one.
	 *
	 * Runs on `authenticate`, so it covers wp-login.php, XML-RPC and every
	 * other caller of `wp_signon()`.
	 *
	 * @param \WP_User|\WP_Error|null $user Result of the earlier callbacks.
	 *
	 * @return \WP_User|\WP_Error|null
	 */
	public static function mask_errors( $user ) {
		if ( ! is_wp_error( $user ) || ! self::is_enabled() ) {
			return $user;
		}

		$leaky = array_intersect( $user->get_error_codes(), self::LEAKY_CODES );
		if ( empty( $leaky ) ) {
			return $user;
		}

		foreach ( $leaky as $code ) {
			$user->remove( $code );
		}

		// Keep the first leaky code so limiters and loggers still recognise the failure.
		$user->add( reset( $leaky ), self::message() );

		return $user;
	}

	/**
	 * The generic message shown for a failed login.
	 *
	 * @return string
	 */
	public static function message(): string {
		$message = sprintf(
			'<strong>%s</strong> %s <a href="%s">%s</a>',
			esc_html__( 'Error:', 'jcore-turva' ),
			esc_html__( 'The username or password you entered is incorrect.', 'jcore-turva' ),
			esc_url( wp_lostpassword_url() ),
			esc_html__( 'Lost your password?', 'jcore-turva' )
		);

		/**
		 * Filters the generic login error message.
		 *
		 * @param string $message HTML message shown instead of the core error.
		 */
		return (string) apply_filters( 'jcore_turva_login_error_message', $message );
	}
}
