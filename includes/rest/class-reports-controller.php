<?php
/**
 * REST routes for CSP violation reports.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva\Rest;

use Jcore\Turva\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the reports screen and receives the browsers' violation reports.
 */
final class Reports_Controller extends Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reports';

	/**
	 * Registers the report routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			$this->route(
				\WP_REST_Server::READABLE,
				'get_items',
				array(
					'status' => array(
						'type'    => 'string',
						'enum'    => array( 'new', 'archived' ),
						'default' => 'new',
					),
				)
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/archive',
			$this->route(
				\WP_REST_Server::CREATABLE,
				'archive_all',
				array(
					'processed_only' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				)
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/mark-processed',
			$this->route( \WP_REST_Server::CREATABLE, 'mark_all_processed', $this->status_arg() )
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/delete',
			$this->route( \WP_REST_Server::CREATABLE, 'delete_all', $this->status_arg() )
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				$this->route(
					\WP_REST_Server::EDITABLE,
					'update_item',
					array(
						'processed' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					)
				),
				$this->route( \WP_REST_Server::DELETABLE, 'delete_item' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/archive',
			$this->route( \WP_REST_Server::CREATABLE, 'archive_item' )
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/unarchive',
			$this->route( \WP_REST_Server::CREATABLE, 'unarchive_item' )
		);

		// Public: this is the `report-uri` the CSP header points browsers at.
		register_rest_route(
			$this->namespace,
			'/csp-report',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive_report' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /reports — the reports in one view, newest first.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s ORDER BY last_seen DESC',
				Database::table( 'reports' ),
				$request->get_param( 'status' )
			)
		);

		$uris = $this->uris_for( wp_list_pluck( $rows, 'id' ) );
		foreach ( $rows as $row ) {
			$row->uris = $uris[ $row->id ] ?? array();
		}

		return rest_ensure_response( array_map( array( $this, 'prepare_report' ), $rows ) );
	}

	/**
	 * PUT/PATCH /reports/{id} — flips the processed flag.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			Database::table( 'reports' ),
			array( 'processed' => (int) $request->get_param( 'processed' ) ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Database::table( 'reports' ), $id )
		);

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Report not found.', array( 'status' => 404 ) );
		}

		$row->uris = $this->uris_for( array( $id ) )[ $id ] ?? array();

		return rest_ensure_response( $this->prepare_report( $row ) );
	}

	/**
	 * POST /reports/archive — archives the whole new queue.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function archive_all( $request ): \WP_REST_Response {
		global $wpdb;

		$where        = array( 'status' => 'new' );
		$where_format = array( '%s' );

		if ( $request->get_param( 'processed_only' ) ) {
			$where['processed'] = 1;
			$where_format[]     = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			Database::table( 'reports' ),
			array(
				'status'    => 'archived',
				'processed' => 1,
			),
			$where,
			array( '%s', '%d' ),
			$where_format
		);

		return rest_ensure_response( array( 'archived' => true ) );
	}

	/**
	 * POST /reports/mark-processed — marks everything in one view as processed.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function mark_all_processed( $request ): \WP_REST_Response {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			Database::table( 'reports' ),
			array( 'processed' => 1 ),
			array( 'status' => $request->get_param( 'status' ) ),
			array( '%d' ),
			array( '%s' )
		);

		return rest_ensure_response( array( 'processed' => true ) );
	}

	/**
	 * POST /reports/delete — empties one view, URIs included.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function delete_all( $request ): \WP_REST_Response {
		global $wpdb;

		$status = $request->get_param( 'status' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$report_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM %i WHERE status = %s', Database::table( 'reports' ), $status )
		);

		if ( ! empty( $report_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $report_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %d.
					"DELETE FROM %i WHERE report_id IN ($placeholders)",
					array_merge( array( Database::table( 'report_uris' ) ), $report_ids )
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare( 'DELETE FROM %i WHERE status = %s', Database::table( 'reports' ), $status )
			);
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * POST /reports/{id}/archive — archives one report.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function archive_item( $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		if ( ! $this->set_status( $id, 'archived', 1 ) ) {
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
	 * POST /reports/{id}/unarchive — puts one report back in the new queue.
	 *
	 * Processed is reset too, so it surfaces as unread again.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function unarchive_item( $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );

		if ( ! $this->set_status( $id, 'new', 0 ) ) {
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
	 * DELETE /reports/{id} — removes one report and its URIs.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( Database::table( 'report_uris' ), array( 'report_id' => $id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete( Database::table( 'reports' ), array( 'id' => $id ), array( '%d' ) );

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
	 * POST /csp-report — the public endpoint browsers post violations to.
	 *
	 * Upserts on (directive, blocked URI), counting repeats rather than
	 * storing a row per violation.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function receive_report( $request ): \WP_REST_Response {
		global $wpdb;

		$data   = json_decode( $request->get_body(), true );
		$report = $data['csp-report'] ?? $data ?? array();

		$violated = sanitize_text_field( $report['violated-directive'] ?? $report['effective-directive'] ?? '' );
		$blocked  = sanitize_text_field( $report['blocked-uri'] ?? '' );
		$document = sanitize_text_field( $report['document-uri'] ?? '' );

		if ( ! $violated || ! $blocked ) {
			return rest_ensure_response( array( 'received' => false ) );
		}

		// Some services put a per-request ID in the query string, which would
		// otherwise make every violation look like a new one.
		$blocked = explode( '?', $blocked )[0];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i
					(violated_directive, blocked_uri, report_count, status, first_seen, last_seen)
				VALUES (%s, %s, 1, 'new', NOW(), NOW())
				ON DUPLICATE KEY UPDATE
					report_count = report_count + 1,
					status       = 'new',
					processed    = 0,
					last_seen    = NOW()",
				Database::table( 'reports' ),
				$violated,
				$blocked
			)
		);

		$report_id = (int) $wpdb->insert_id;
		if ( ! $report_id ) {
			// After ON DUPLICATE KEY UPDATE, insert_id is not the existing row
			// everywhere, so look the row up.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$report_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE violated_directive = %s AND blocked_uri = %s',
					Database::table( 'reports' ),
					$violated,
					$blocked
				)
			);
		}

		if ( $report_id && $document ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i
						(report_id, uri, last_seen)
					VALUES (%d, %s, NOW())
					ON DUPLICATE KEY UPDATE
						last_seen = NOW()',
					Database::table( 'report_uris' ),
					$report_id,
					$document
				)
			);
		}

		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * Moves one report between the new and archived views.
	 *
	 * @param int    $id        Report ID.
	 * @param string $status    Target status.
	 * @param int    $processed Processed flag to set alongside it.
	 *
	 * @return bool False when no row matched.
	 */
	private function set_status( int $id, string $status, int $processed ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update(
			Database::table( 'reports' ),
			array(
				'status'    => $status,
				'processed' => $processed,
			),
			array( 'id' => $id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Loads the document URIs of several reports at once.
	 *
	 * @param int[] $report_ids Report IDs.
	 *
	 * @return array<int, string[]> URIs keyed by report ID, newest first.
	 */
	private function uris_for( array $report_ids ): array {
		global $wpdb;

		if ( empty( $report_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $report_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %d.
				"SELECT report_id, uri FROM %i WHERE report_id IN ($placeholders) ORDER BY last_seen DESC",
				array_merge( array( Database::table( 'report_uris' ) ), $report_ids )
			)
		);

		$uris = array();
		foreach ( $rows as $row ) {
			$uris[ (int) $row->report_id ][] = $row->uri;
		}

		return $uris;
	}

	/**
	 * The status argument the bulk routes share.
	 *
	 * @return array<string, mixed>
	 */
	private function status_arg(): array {
		return array(
			'status' => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array( 'new', 'archived' ),
			),
		);
	}

	/**
	 * Casts a raw row so the REST response is properly typed.
	 *
	 * @param object $row Raw database row, with `uris` already attached.
	 *
	 * @return array<string, mixed>
	 */
	private function prepare_report( object $row ): array {
		$uris = $row->uris ?? array();

		return array(
			'id'                 => (int) $row->id,
			'violated_directive' => $row->violated_directive,
			'blocked_uri'        => $row->blocked_uri,
			'uris'               => $uris,
			// Kept for clients that still read a single URI.
			'document_uri'       => $uris[0] ?? '',
			'report_count'       => (int) $row->report_count,
			'status'             => $row->status,
			'processed'          => (bool) (int) $row->processed,
			'first_seen'         => $row->first_seen,
			'last_seen'          => $row->last_seen,
		);
	}
}
