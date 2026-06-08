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

	private const DB_VERSION        = '1.2';
	private const DB_VERSION_OPTION = 'jcore_turva_db_version';

	/**
	 * Runs install() if the stored DB version is behind the current one.
	 * Called on every plugins_loaded so schema changes deploy without reactivation.
	 */
	public static function maybe_upgrade(): void {
		$old_version = get_option( self::DB_VERSION_OPTION );
		if ( $old_version !== self::DB_VERSION ) {
			if ( $old_version && version_compare( $old_version, '1.2', '<' ) ) {
				// We need the new table to exist before migrating.
				self::install();
				self::migrate_to_1_2();
			}
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
			  report_count       INT UNSIGNED NOT NULL DEFAULT 1,
			  status             VARCHAR(10)  NOT NULL DEFAULT 'new',
			  processed          TINYINT(1)   NOT NULL DEFAULT 0,
			  first_seen         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  last_seen          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  PRIMARY KEY  (id),
			  UNIQUE KEY dedup (violated_directive, blocked_uri)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}jcore_security_report_uris (
			  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
			  report_id  INT UNSIGNED NOT NULL,
			  uri        VARCHAR(512) NOT NULL DEFAULT '',
			  last_seen  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			  PRIMARY KEY  (id),
			  KEY report_id (report_id),
			  UNIQUE KEY report_uri (report_id, uri(191))
			) {$charset};"
		);

		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::seed_defaults();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		}
	}

	/**
	 * Migrates data from 1.1 to 1.2.
	 *
	 * Moves document_uri to the new jcore_security_report_uris table and merges
	 * duplicate reports that differ only by document_uri.
	 */
	private static function migrate_to_1_2(): void {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'jcore_security_reports';
		$uris_table    = $wpdb->prefix . 'jcore_security_report_uris';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$has_column = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM `' . $reports_table . '` LIKE %s', 'document_uri' ) );
		if ( empty( $has_column ) ) {
			return;
		}

		// 1. Copy all existing document_uri values to the new table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"INSERT IGNORE INTO `{$uris_table}` (report_id, uri, last_seen)
			 SELECT id, document_uri, last_seen FROM `{$reports_table}`
			 WHERE document_uri != ''"
		);

		// 2. Find duplicates that will now conflict under the new UNIQUE KEY (violated_directive, blocked_uri).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$duplicates = $wpdb->get_results(
			"SELECT violated_directive, blocked_uri, COUNT(*) as c
			 FROM `{$reports_table}`
			 GROUP BY violated_directive, blocked_uri
			 HAVING c > 1"
		);

		foreach ( $duplicates as $dup ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, report_count, first_seen, last_seen, status, processed FROM `{$reports_table}`
					 WHERE violated_directive = %s AND blocked_uri = %s
					 ORDER BY first_seen ASC",
					$dup->violated_directive,
					$dup->blocked_uri
				)
			);

			if ( count( $rows ) < 2 ) {
				continue;
			}

			$primary     = array_shift( $rows );
			$total_count = (int) $primary->report_count;
			$first_seen  = $primary->first_seen;
			$last_seen   = $primary->last_seen;
			$status      = $primary->status;
			$processed   = (int) $primary->processed;

			foreach ( $rows as $row ) {
				$total_count += (int) $row->report_count;
				if ( $row->first_seen < $first_seen ) {
					$first_seen = $row->first_seen;
				}
				if ( $row->last_seen > $last_seen ) {
					$last_seen = $row->last_seen;
				}
				if ( 'new' === $row->status ) {
					$status = 'new';
				}
				if ( ! $row->processed ) {
					$processed = 0;
				}

				// Update URIs to point to the primary report ID.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE IGNORE `{$uris_table}` SET report_id = %d WHERE report_id = %d",
						$primary->id,
						$row->id
					)
				);
				// Delete the now-redundant URIs from the old report ID that couldn't be moved due to duplicates.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->delete( $uris_table, array( 'report_id' => $row->id ), array( '%d' ) );

				// Delete the duplicate report row.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->delete( $reports_table, array( 'id' => $row->id ), array( '%d' ) );
			}

			// Update the primary report row with aggregated values.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$reports_table,
				array(
					'report_count' => $total_count,
					'first_seen'   => $first_seen,
					'last_seen'    => $last_seen,
					'status'       => $status,
					'processed'    => $processed,
				),
				array( 'id' => $primary->id ),
				array( '%d', '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);
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
					'hsts'                => true,
					'hsts_max_age'        => 31536000,
					'nosniff'             => true,
					'xss_protection'      => false,
					'referrer_policy'     => false,
					'referrer_value'      => 'strict-origin-when-cross-origin',
					'csp_mode'            => 'enabled',
					'csp_test_mode'       => false,
					'google_multi_domain' => false,
				),
				false
			);
		}
	}
}
