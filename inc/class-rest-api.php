<?php
/**
 * REST API endpoint registration and handlers.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all REST routes for the plugin.
 */
class Rest_Api {

	private const NAMESPACE = 'jcore-turva/v1';

	/**
	 * Hooks route registration into rest_api_init.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers all REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/sources',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_sources' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
					'args'                => array(
						'header_type' => array(
							'type'              => 'string',
							'enum'              => array( 'csp', 'permissions' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'create_source' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
					'args'                => self::source_args( true ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/sources/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_source' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
					'args'                => self::source_args( false ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_source' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_settings' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'save_settings' ),
					'permission_callback' => array( self::class, 'admin_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_reports' ),
				'permission_callback' => array( self::class, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( self::class, 'update_report' ),
				'permission_callback' => array( self::class, 'admin_permission' ),
				'args'                => array(
					'processed' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports/(?P<id>\d+)/archive',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'archive_report' ),
				'permission_callback' => array( self::class, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports/(?P<id>\d+)/unarchive',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'unarchive_report' ),
				'permission_callback' => array( self::class, 'admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'delete_report' ),
				'permission_callback' => array( self::class, 'admin_permission' ),
			)
		);

		// Public — receives CSP violation reports from browsers.
		register_rest_route(
			self::NAMESPACE,
			'/csp-report',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'receive_csp_report' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission callback for all admin-only endpoints.
	 */
	public static function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /sources — returns sources, optionally filtered by header_type.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function get_sources( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$header_type = $request->get_param( 'header_type' );

		if ( $header_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$wpdb->prefix}jcore_security_sources` WHERE header_type = %s ORDER BY directive, id",
					$header_type
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				"SELECT * FROM `{$wpdb->prefix}jcore_security_sources` ORDER BY header_type, directive, id" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}

		return rest_ensure_response( array_map( array( self::class, 'prepare_source' ), $rows ) );
	}

	/**
	 * POST /sources — creates a new source record.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function create_source( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'jcore_security_sources',
			array(
				'header_type' => $request->get_param( 'header_type' ),
				'directive'   => $request->get_param( 'directive' ),
				'source'      => $request->get_param( 'source' ),
				'enabled'     => (int) $request->get_param( 'enabled' ),
			),
			array( '%s', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'db_insert_failed', 'Could not create source.', array( 'status' => 500 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}jcore_security_sources` WHERE id = %d", $wpdb->insert_id )
		);

		return rest_ensure_response( self::prepare_source( $row ) );
	}

	/**
	 * PUT/PATCH /sources/{id} — updates source or enabled flag.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function update_source( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id   = (int) $request->get_param( 'id' );
		$data = array_filter(
			array(
				'source'  => $request->get_param( 'source' ),
				'enabled' => $request->has_param( 'enabled' ) ? (int) $request->get_param( 'enabled' ) : null,
			),
			static fn( $v ) => null !== $v
		);

		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', 'No updatable fields provided.', array( 'status' => 400 ) );
		}

		$formats = array_map( static fn( $k ) => 'enabled' === $k ? '%d' : '%s', array_keys( $data ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'jcore_security_sources',
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}jcore_security_sources` WHERE id = %d", $id )
		);

		return rest_ensure_response( self::prepare_source( $row ) );
	}

	/**
	 * DELETE /sources/{id} — removes a source record.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function delete_source( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'jcore_security_sources',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			return new \WP_Error( 'not_found', 'Source not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * GET /settings — returns the general settings option.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( get_option( 'jcore_turva_settings', array() ) );
	}

	/**
	 * POST /settings — persists general settings.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$allowed = array( 'hsts', 'hsts_max_age', 'nosniff', 'xss_protection', 'referrer_policy', 'referrer_value', 'csp_test_mode' );
		$current = get_option( 'jcore_turva_settings', array() );
		foreach ( $allowed as $key ) {
			if ( $request->has_param( $key ) ) {
				$current[ $key ] = $request->get_param( $key );
			}
		}
		update_option( 'jcore_turva_settings', $current );
		return rest_ensure_response( $current );
	}

	/**
	 * GET /reports — returns violation reports filtered by status.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function get_reports( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$status = in_array( $request->get_param( 'status' ), array( 'new', 'archived' ), true )
			? $request->get_param( 'status' )
			: 'new';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}jcore_security_reports` WHERE status = %s ORDER BY last_seen DESC",
				$status
			)
		);

		return rest_ensure_response( array_map( array( self::class, 'prepare_report' ), $rows ) );
	}

	/**
	 * PUT /reports/{id} — updates the processed flag on a report.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function update_report( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id        = (int) $request->get_param( 'id' );
		$processed = (int) $request->get_param( 'processed' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'jcore_security_reports',
			array( 'processed' => $processed ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}jcore_security_reports` WHERE id = %d", $id )
		);

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Report not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( self::prepare_report( $row ) );
	}

	/**
	 * POST /reports/{id}/archive — marks a report as archived.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function archive_report( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update(
			$wpdb->prefix . 'jcore_security_reports',
			array( 'status' => 'archived' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'not_found', 'Report not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'archived' => true,
				'id'       => $id,
			)
		);
	}

	/**
	 * POST /reports/{id}/unarchive — restores a report to new status.
	 *
	 * Also resets processed so it surfaces as unread in the new queue.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function unarchive_report( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update(
			$wpdb->prefix . 'jcore_security_reports',
			array(
				'status'    => 'new',
				'processed' => 0,
			),
			array( 'id' => $id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'not_found', 'Report not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'restored' => true,
				'id'       => $id,
			)
		);
	}

	/**
	 * DELETE /reports/{id} — permanently removes a report record.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function delete_report( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'jcore_security_reports',
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			return new \WP_Error( 'not_found', 'Report not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * POST /csp-report — public endpoint that receives browser CSP violation reports.
	 *
	 * Upserts into the reports table, incrementing count on duplicates.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 */
	public static function receive_csp_report( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$data   = json_decode( $request->get_body(), true );
		$report = $data['csp-report'] ?? $data ?? array();

		$violated = sanitize_text_field( $report['violated-directive'] ?? $report['effective-directive'] ?? '' );
		$blocked  = sanitize_text_field( $report['blocked-uri'] ?? '' );
		$document = sanitize_text_field( $report['document-uri'] ?? '' );

		if ( ! $violated || ! $blocked ) {
			return rest_ensure_response( array( 'received' => false ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `{$wpdb->prefix}jcore_security_reports`
					(violated_directive, blocked_uri, document_uri, report_count, status, first_seen, last_seen)
				VALUES (%s, %s, %s, 1, 'new', NOW(), NOW())
				ON DUPLICATE KEY UPDATE
					report_count = report_count + 1,
					processed    = 0,
					last_seen    = NOW()",
				$violated,
				$blocked,
				$document
			)
		);

		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * Casts database row types for a report record so the REST response is properly typed.
	 *
	 * @param object $row Raw database row.
	 */
	private static function prepare_report( object $row ): array {
		return array(
			'id'                 => (int) $row->id,
			'violated_directive' => $row->violated_directive,
			'blocked_uri'        => $row->blocked_uri,
			'document_uri'       => $row->document_uri,
			'report_count'       => (int) $row->report_count,
			'status'             => $row->status,
			'processed'          => (bool) (int) $row->processed,
			'first_seen'         => $row->first_seen,
			'last_seen'          => $row->last_seen,
		);
	}

	/**
	 * Casts database row types for a source record so the REST response is properly typed.
	 *
	 * @param object $row Raw database row.
	 */
	private static function prepare_source( object $row ): array {
		return array(
			'id'          => (int) $row->id,
			'header_type' => $row->header_type,
			'directive'   => $row->directive,
			'source'      => $row->source,
			'enabled'     => (bool) (int) $row->enabled,
			'created_at'  => $row->created_at,
		);
	}

	/**
	 * Returns REST arg definitions for source create/update endpoints.
	 *
	 * @param bool $require_all Whether all fields are required (true for create).
	 */
	private static function source_args( bool $require_all ): array {
		return array(
			'header_type' => array(
				'type'              => 'string',
				'enum'              => array( 'csp', 'permissions' ),
				'required'          => $require_all,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'directive'   => array(
				'type'              => 'string',
				'required'          => $require_all,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'source'      => array(
				'type'              => 'string',
				'required'          => $require_all,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'enabled'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
	}
}
