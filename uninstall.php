<?php
/**
 * Removes everything the plugin stored when it is deleted.
 *
 * @package Jcore\Turva
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Drops the plugin's tables and options on the current site.
 *
 * Names are spelled out rather than read from the classes so this file stands
 * alone, as uninstall.php runs without the plugin loaded.
 *
 * @return void
 */
function jcore_turva_uninstall_site(): void {
	global $wpdb;

	foreach ( array( 'jcore_security_report_uris', 'jcore_security_reports', 'jcore_security_sources' ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
	}

	delete_option( 'jcore_turva_settings' );
	delete_option( 'jcore_turva_db_version' );
	delete_transient( 'jcore_turva_upgrading' );
}

if ( is_multisite() ) {
	$jcore_turva_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $jcore_turva_site_ids as $jcore_turva_site_id ) {
		switch_to_blog( (int) $jcore_turva_site_id );
		jcore_turva_uninstall_site();
		restore_current_blog();
	}
} else {
	jcore_turva_uninstall_site();
}
