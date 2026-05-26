<?php
/**
 * Database setup and migrations.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles table creation and default data seeding.
 */
class Database {

	private const DB_VERSION        = '1.1';
	private const DB_VERSION_OPTION = 'jcore_turva_db_version';

	/**
	 * Runs install() if the stored DB version is behind the current one.
	 * Called on every plugins_loaded so schema changes deploy without reactivation.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates tables and seeds defaults. Run on plugin activation and upgrade.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}jcore_security_sources (
			  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
			  header_type VARCHAR(20)  NOT NULL DEFAULT '',
			  directive   VARCHAR(50)  NOT NULL DEFAULT '',
			  source      VARCHAR(500) NOT NULL DEFAULT '',
			  enabled     TINYINT(1)   NOT NULL DEFAULT 1,
			  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  PRIMARY KEY  (id),
			  KEY header_directive_enabled (header_type, directive, enabled)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}jcore_security_reports (
			  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
			  violated_directive VARCHAR(100) NOT NULL DEFAULT '',
			  blocked_uri        VARCHAR(255) NOT NULL DEFAULT '',
			  document_uri       VARCHAR(255) NOT NULL DEFAULT '',
			  report_count       INT UNSIGNED NOT NULL DEFAULT 1,
			  status             VARCHAR(10)  NOT NULL DEFAULT 'new',
			  processed          TINYINT(1)   NOT NULL DEFAULT 0,
			  first_seen         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  last_seen          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  PRIMARY KEY  (id),
			  UNIQUE KEY dedup (violated_directive, blocked_uri, document_uri)
			) {$charset};"
		);

		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::seed_defaults();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		}
	}

	/**
	 * Seeds the default CSP rule and general settings on first install.
	 */
	private static function seed_defaults(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'jcore_security_sources';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'header_type' => 'csp',
					'directive'   => 'default-src',
					'source'      => "'self'",
					'enabled'     => 1,
				),
				array( '%s', '%s', '%s', '%d' )
			);
		}

		if ( ! get_option( 'jcore_turva_settings' ) ) {
			update_option(
				'jcore_turva_settings',
				array(
					'hsts'            => true,
					'hsts_max_age'    => 31536000,
					'nosniff'         => true,
					'xss_protection'  => false,
					'referrer_policy' => false,
					'referrer_value'  => 'strict-origin-when-cross-origin',
					'csp_test_mode'   => false,
				),
				false
			);
		}
	}
}
