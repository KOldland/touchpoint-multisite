<?php

namespace KHM\REST;

use KHM\Services\SponsorService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for sponsor advert creatives.
 *
 * Namespace : khm/v1
 * Routes    : /adverts          (GET list, POST create)
 *             /adverts/{id}     (GET single, POST update)
 *             /adverts/{id}/click (POST — public, records click)
 *             /advertise        (GET — public, serves an approved creative)
 */
class SponsorAdvertController {

	// -------------------------------------------------------------------------
	// Route registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// Sponsor-facing CRUD
		register_rest_route( 'khm/v1', '/adverts', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_adverts' ],
				'permission_callback' => [ $this, 'require_sponsor' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_advert' ],
				'permission_callback' => [ $this, 'require_sponsor' ],
			],
		] );

		register_rest_route( 'khm/v1', '/adverts/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_advert' ],
				'permission_callback' => [ $this, 'require_sponsor' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_advert' ],
				'permission_callback' => [ $this, 'require_sponsor' ],
			],
		] );

		// Public click-tracker (no auth — just increments counter).
		register_rest_route( 'khm/v1', '/adverts/(?P<id>\d+)/click', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'record_click' ],
			'permission_callback' => '__return_true',
		] );

		// Public ad-serve endpoint (S17) — returns one approved creative.
		register_rest_route( 'khm/v1', '/advertise', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'serve_advert' ],
			'permission_callback' => '__return_true',
		] );
	}

	// -------------------------------------------------------------------------
	// Permission
	// -------------------------------------------------------------------------

	public function require_sponsor( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( in_array( 'khm_sponsor', (array) $user->roles, true ) ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** Resolve the current sponsor record from the logged-in user. */
	private function get_sponsor(): array|false {
		$user_id = get_current_user_id();
		$sponsor = SponsorService::get_user_sponsor( $user_id );
		return $sponsor ?: false;
	}

	/** Map a DB row to the public shape returned by the API. */
	private function row_to_api( object $row ): array {
		return [
			'id'               => (int) $row->id,
			'sponsor_id'       => (int) $row->sponsor_id,
			'title'            => $row->title,
			'placement'        => $row->placement,
			'media_url'        => $row->media_url,
			'media_id'         => $row->media_id ? (int) $row->media_id : null,
			'click_url'        => $row->click_url,
			'alt_text'         => $row->alt_text,
			'status'           => $row->status,
			'rejection_reason' => $row->rejection_reason,
			'impressions'      => (int) $row->impressions,
			'clicks'           => (int) $row->clicks,
			'weight'           => (int) $row->weight,
			'created_at'       => $row->created_at,
			'updated_at'       => $row->updated_at,
			'start_date'       => property_exists($row, 'start_date') ? $row->start_date : null,
			'end_date'         => property_exists($row, 'end_date') ? $row->end_date : null,
		];
	}

	// -------------------------------------------------------------------------
	// CRUD handlers
	// -------------------------------------------------------------------------

	/** GET /adverts — list creatives owned by current sponsor. */
	public function list_adverts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$sponsor = $this->get_sponsor();
		if ( ! $sponsor ) {
			return new WP_Error( 'no_sponsor', 'No sponsor profile found.', [ 'status' => 403 ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE sponsor_id = %d ORDER BY created_at DESC", $sponsor['id'] )
		);

		return new WP_REST_Response( [ 'success' => true, 'adverts' => array_map( [ $this, 'row_to_api' ], $rows ) ], 200 );
	}

	/** POST /adverts — create a new creative. */
	public function create_advert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$sponsor = $this->get_sponsor();
		if ( ! $sponsor ) {
			return new WP_Error( 'no_sponsor', 'No sponsor profile found.', [ 'status' => 403 ] );
		}

		$allowed_placements = [ 'commentary', 'press-release', 'overview', 'sidebar' ];
		$placement          = sanitize_text_field( $request->get_param( 'placement' ) ?: 'commentary' );
		if ( ! in_array( $placement, $allowed_placements, true ) ) {
			return new WP_Error( 'invalid_placement', 'Invalid placement value.', [ 'status' => 400 ] );
		}

		$title     = sanitize_text_field( $request->get_param( 'title' ) ?: '' );
		$click_url = esc_url_raw( $request->get_param( 'click_url' ) ?: '' );
		$alt_text  = sanitize_text_field( $request->get_param( 'alt_text' ) ?: '' );
		$media_id  = absint( $request->get_param( 'media_id' ) ?: 0 );

		// Resolve media URL from the WP attachment if a media_id is supplied.
		$media_url = '';
		if ( $media_id ) {
			$media_url = wp_get_attachment_url( $media_id );
			if ( ! $media_url ) {
				return new WP_Error( 'invalid_media', 'Attachment not found.', [ 'status' => 400 ] );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, [
			'sponsor_id' => $sponsor['id'],
			'user_id'    => get_current_user_id(),
			'title'      => $title,
			'placement'  => $placement,
			'media_url'  => $media_url ?: null,
			'media_id'   => $media_id ?: null,
			'click_url'  => $click_url ?: null,
			'alt_text'   => $alt_text ?: null,
			'status'     => 'draft',
			'start_date' => sanitize_text_field( $request->get_param( 'start_date' ) ?: '' ) ?: null,
			'end_date'   => sanitize_text_field( $request->get_param( 'end_date' ) ?: '' ) ?: null,
			'created_at' => $now,
			'updated_at' => $now,
		], [ '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', 'Failed to create advert.', [ 'status' => 500 ] );
		}

		$id  = $wpdb->insert_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );

		// Notify admin of new submission.
		wp_mail(
			get_option( 'admin_email' ),
			'[QuoteClub] New sponsor advert submitted',
			sprintf(
				"Sponsor ID %d submitted a new advert creative (ID %d).\n\nTitle: %s\nPlacement: %s\n\nReview in WP admin.",
				$sponsor['id'], $id, $title, $placement
			)
		);

		return new WP_REST_Response( [ 'success' => true, 'advert' => $this->row_to_api( $row ) ], 201 );
	}

	/** GET /adverts/{id} — get single creative (must own it). */
	public function get_advert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$sponsor = $this->get_sponsor();
		if ( ! $sponsor ) {
			return new WP_Error( 'no_sponsor', 'No sponsor profile found.', [ 'status' => 403 ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';
		$id    = absint( $request->get_param( 'id' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND sponsor_id = %d", $id, $sponsor['id'] ) );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Advert not found.', [ 'status' => 404 ] );
		}

		return new WP_REST_Response( [ 'success' => true, 'advert' => $this->row_to_api( $row ) ], 200 );
	}

	/** POST /adverts/{id} — update title, click_url, alt_text, placement (can only edit draft). */
	public function update_advert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$sponsor = $this->get_sponsor();
		if ( ! $sponsor ) {
			return new WP_Error( 'no_sponsor', 'No sponsor profile found.', [ 'status' => 403 ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';
		$id    = absint( $request->get_param( 'id' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND sponsor_id = %d", $id, $sponsor['id'] ) );
		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Advert not found.', [ 'status' => 404 ] );
		}

		// Sponsors can only edit drafts; approved/paused states require admin.
		$editable_statuses = [ 'draft', 'rejected' ];
		$action            = sanitize_text_field( $request->get_param( 'action' ) ?: '' );

		if ( 'submit' === $action ) {
			// Sponsor submits draft for review.
			if ( ! in_array( $row->status, $editable_statuses, true ) ) {
				return new WP_Error( 'not_editable', 'Only drafts or rejected creatives can be submitted.', [ 'status' => 400 ] );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update( $table, [ 'status' => 'pending', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
			wp_mail(
				get_option( 'admin_email' ),
				'[QuoteClub] Sponsor advert ready for review',
				sprintf( "Advert ID %d from sponsor %d is pending review.", $id, $sponsor['id'] )
			);
		} elseif ( 'pause' === $action && current_user_can( 'manage_options' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update( $table, [ 'status' => 'paused', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
		} else {
			// Regular field update — only in draft/rejected.
			if ( ! in_array( $row->status, $editable_statuses, true ) ) {
				return new WP_Error( 'not_editable', 'Only draft or rejected creatives can be edited.', [ 'status' => 400 ] );
			}

			$allowed_placements = [ 'commentary', 'press-release', 'overview', 'sidebar' ];
			$placement          = sanitize_text_field( $request->get_param( 'placement' ) ?: $row->placement );
			if ( ! in_array( $placement, $allowed_placements, true ) ) {
				return new WP_Error( 'invalid_placement', 'Invalid placement value.', [ 'status' => 400 ] );
			}

			$media_id  = absint( $request->get_param( 'media_id' ) ?: $row->media_id );
			$media_url = $row->media_url;
			if ( $media_id && $media_id !== (int) $row->media_id ) {
				$media_url = wp_get_attachment_url( $media_id );
				if ( ! $media_url ) {
					return new WP_Error( 'invalid_media', 'Attachment not found.', [ 'status' => 400 ] );
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update( $table, [
				'title'      => sanitize_text_field( $request->get_param( 'title' ) ?: $row->title ),
				'placement'  => $placement,
				'click_url'  => esc_url_raw( $request->get_param( 'click_url' ) ?: $row->click_url ),
				'alt_text'   => sanitize_text_field( $request->get_param( 'alt_text' ) ?: $row->alt_text ),
				'media_id'   => $media_id ?: null,
				'media_url'  => $media_url ?: null,
				'start_date' => sanitize_text_field( $request->get_param( 'start_date' ) ) ?: ( isset( $row->start_date ) ? $row->start_date : null ),
				'end_date'   => sanitize_text_field( $request->get_param( 'end_date' ) ) ?: ( isset( $row->end_date ) ? $row->end_date : null ),
				'updated_at' => current_time( 'mysql', true ),
			], [ 'id' => $id ], [ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ], [ '%d' ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
		return new WP_REST_Response( [ 'success' => true, 'advert' => $this->row_to_api( $row ) ], 200 );
	}

	// -------------------------------------------------------------------------
	// Public — click tracker (S17)
	// -------------------------------------------------------------------------

	/** POST /adverts/{id}/click — increment click counter. */
	public function record_click( WP_REST_Request $request ): WP_REST_Response {
		$id = absint( $request->get_param( 'id' ) );
		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET clicks = clicks + 1 WHERE id = %d AND status = 'approved'", $id ) );
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// Public — ad server (S17)
	// -------------------------------------------------------------------------

	/**
	 * GET /advertise?placement=commentary&commentary_id=123
	 *
	 * Returns one approved creative for the requested placement.
	 * Selection is weighted by the `weight` column (higher = more likely).
	 * A tie-break on impressions (fewest first) keeps exposure balanced.
	 */
	public function serve_advert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$allowed_placements = [ 'commentary', 'press-release', 'overview', 'sidebar' ];
		$placement          = sanitize_text_field( $request->get_param( 'placement' ) ?: 'commentary' );
		if ( ! in_array( $placement, $allowed_placements, true ) ) {
			return new WP_Error( 'invalid_placement', 'Invalid placement.', [ 'status' => 400 ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'khm_sponsor_adverts';

		// Weighted random: pull candidates ordered by weight DESC, impressions ASC,
		// then pick the first row (simple weighted round-robin).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE status = 'approved' AND placement = %s AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date > NOW()) ORDER BY weight DESC, impressions ASC LIMIT 1",
				$placement
			)
		);

		if ( ! $row ) {
			return new WP_REST_Response( [ 'success' => true, 'advert' => null ], 200 );
		}

		// Increment impression counter.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET impressions = impressions + 1 WHERE id = %d", $row->id ) );

		return new WP_REST_Response( [
			'success' => true,
			'advert'  => [
				'id'        => (int) $row->id,
				'title'     => $row->title,
				'media_url' => $row->media_url,
				'click_url' => $row->click_url,
				'alt_text'  => $row->alt_text,
				'placement' => $row->placement,
			],
		], 200 );
	}
}
