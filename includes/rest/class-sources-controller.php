<?php
/**
 * REST routes for the CSP and Permissions-Policy source rows.
 *
 * @package Jcore\Turva
 */

namespace Jcore\Turva\Rest;

use Jcore\Turva\Compat;
use Jcore\Turva\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the rows the two policy headers are built from.
 */
final class Sources_Controller extends Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'sources';

	/**
	 * Registers the source routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				$this->route(
					\WP_REST_Server::READABLE,
					'get_items',
					array(
						'header_type' => array(
							'type'              => 'string',
							'enum'              => array( 'csp', 'permissions' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					)
				),
				$this->route( \WP_REST_Server::CREATABLE, 'create_item', $this->source_args( true ) ),
				$this->route(
					\WP_REST_Server::DELETABLE,
					'delete_items',
					array(
						'ids' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'integer' ),
						),
					)
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			$this->route(
				\WP_REST_Server::CREATABLE,
				'import_items',
				array(
					'header_type' => array(
						'type'              => 'string',
						'enum'              => array( 'csp', 'permissions' ),
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'directives'  => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'directive' => array( 'type' => 'string' ),
								'source'    => array( 'type' => 'string' ),
							),
						),
					),
					'action'      => array(
						'type'              => 'string',
						'enum'              => array( 'merge', 'replace' ),
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				)
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				$this->route( \WP_REST_Server::EDITABLE, 'update_item', $this->source_args( false ) ),
				$this->route( \WP_REST_Server::DELETABLE, 'delete_item' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/jcore2/policies',
			$this->route(
				\WP_REST_Server::READABLE,
				'get_jcore2_policies',
				array(
					'header_type' => array(
						'type'              => 'string',
						'enum'              => array( 'csp', 'permissions' ),
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				)
			)
		);
	}

	/**
	 * GET /sources — every source, optionally filtered by header type.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ): \WP_REST_Response {
		global $wpdb;

		$header_type = $request->get_param( 'header_type' );

		if ( $header_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE header_type = %s ORDER BY directive, id',
					Database::table( 'sources' ),
					$header_type
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY header_type, directive, id',
					Database::table( 'sources' )
				)
			);
		}

		return rest_ensure_response( array_map( array( $this, 'prepare_source' ), $rows ) );
	}

	/**
	 * POST /sources — adds one source row.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			Database::table( 'sources' ),
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

		$row = $this->get_source( (int) $wpdb->insert_id );
		if ( ! $row ) {
			return new \WP_Error( 'db_insert_failed', 'Could not read the created source.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( $this->prepare_source( $row ) );
	}

	/**
	 * PUT/PATCH /sources/{id} — updates the value or the enabled flag.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id   = (int) $request->get_param( 'id' );
		$data = array_filter(
			array(
				'source'  => $request->get_param( 'source' ),
				'enabled' => $request->has_param( 'enabled' ) ? (int) $request->get_param( 'enabled' ) : null,
			),
			static fn( $value ) => null !== $value
		);

		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', 'No updatable fields provided.', array( 'status' => 400 ) );
		}

		$formats = array_map( static fn( $key ) => 'enabled' === $key ? '%d' : '%s', array_keys( $data ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( Database::table( 'sources' ), $data, array( 'id' => $id ), $formats, array( '%d' ) );

		$row = $this->get_source( $id );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Source not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->prepare_source( $row ) );
	}

	/**
	 * DELETE /sources — removes several rows at once.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_items( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$ids = array_map( 'intval', (array) $request->get_param( 'ids' ) );
		if ( empty( $ids ) ) {
			return new \WP_Error( 'invalid_ids', 'No IDs provided.', array( 'status' => 400 ) );
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %d.
				"DELETE FROM %i WHERE id IN ($placeholders)",
				array_merge( array( Database::table( 'sources' ) ), $ids )
			)
		);

		if ( false === $deleted ) {
			return new \WP_Error( 'db_delete_failed', 'Could not delete sources.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'deleted' => $deleted,
			)
		);
	}

	/**
	 * DELETE /sources/{id} — removes one row.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->delete( Database::table( 'sources' ), array( 'id' => $id ), array( '%d' ) );

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
	 * POST /sources/import — merges or replaces a whole policy at once.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function import_items( $request ): \WP_REST_Response {
		global $wpdb;

		$header_type = $request->get_param( 'header_type' );
		$directives  = $request->get_param( 'directives' );
		$action      = $request->get_param( 'action' );
		$table       = Database::table( 'sources' );

		if ( 'replace' === $action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'header_type' => $header_type ), array( '%s' ) );
		}

		foreach ( $directives as $item ) {
			$directive = sanitize_text_field( $item['directive'] );
			$source    = sanitize_text_field( $item['source'] );

			if ( 'merge' === $action ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM %i WHERE header_type = %s AND directive = %s AND source = %s',
						$table,
						$header_type,
						$directive,
						$source
					)
				);

				if ( $exists ) {
					continue;
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'header_type' => $header_type,
					'directive'   => $directive,
					'source'      => $source,
					'enabled'     => 1,
				),
				array( '%s', '%s', '%s', '%d' )
			);
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * GET /jcore2/policies — the JCORE 2 theme policy as importable pairs.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_jcore2_policies( $request ): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'available'  => Compat::is_jcore2_detected(),
				'directives' => Compat::get_jcore2_directives( $request->get_param( 'header_type' ) ),
			)
		);
	}

	/**
	 * Reads one source row.
	 *
	 * @param int $id Row ID.
	 *
	 * @return object|null
	 */
	private function get_source( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Database::table( 'sources' ), $id )
		);
	}

	/**
	 * Casts a raw row so the REST response is properly typed.
	 *
	 * @param object $row Raw database row.
	 *
	 * @return array<string, mixed>
	 */
	private function prepare_source( object $row ): array {
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
	 * Argument schema shared by the create and update routes.
	 *
	 * @param bool $require_all Whether every field is required, as on create.
	 *
	 * @return array<string, mixed>
	 */
	private function source_args( bool $require_all ): array {
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
