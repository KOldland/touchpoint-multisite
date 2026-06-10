<?php

namespace Cookiez\Modules\Elementor\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Cookiez\Modules\Elementor\Documents\Template_Defaults\Cookie_Consent_Template_Defaults;
use Cookiez\Modules\Elementor\Documents\Template_Defaults\Preferences_Banner_Template_Defaults;
use Cookiez\Modules\Elementor\Classes\Route_Base;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class Create_Template extends Route_Base {

	private const DOCUMENT_TYPE_COOKIE_CONSENT = 'cookie-consent';
	private const DOCUMENT_TYPE_PREFERENCES_BANNER = 'preferences-banner';
	private const ELEMENTOR_LIBRARY_CPT = 'elementor_library';
	private const ELEMENTOR_TYPE_META_KEY = '_elementor_template_type';
	private const ELEMENTOR_TAXONOMY_TYPE_SLUG = 'elementor_library_type';

	private const ALLOWED_TEMPLATE_TYPES = [
		self::DOCUMENT_TYPE_COOKIE_CONSENT,
		self::DOCUMENT_TYPE_PREFERENCES_BANNER,
	];

	public string $path = 'create-template';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'create-template';
	}

	public function POST( WP_REST_Request $request ): WP_REST_Response {
		$template_type = $this->get_requested_template_type( $request );

		$template_ids = $this->find_template_ids( $template_type );
		$template_count = count( $template_ids );

		if ( 1 < $template_count ) {
			return $this->respond_success_json( [
				'editUrl' => $this->get_templates_admin_url( $template_type ),
			] );
		}

		if ( 1 === $template_count ) {
			$post_id = $template_ids[0];
			$this->set_template_defaults( $template_type, $post_id );

			return $this->respond_success_json( [
				'editUrl' => $this->get_elementor_edit_url( $post_id ),
			] );
		}

		$post_id = $this->create_template( $template_type );

		if ( is_wp_error( $post_id ) ) {
			return $this->respond_error_json( [
				'message' => $post_id->get_error_message(),
				'code'    => 'template_creation_failed',
			], 500 );
		}

		$this->set_template_defaults( $template_type, $post_id );

		return $this->respond_success_json( [
			'editUrl' => $this->get_elementor_edit_url( $post_id ),
		] );
	}

	private function get_requested_template_type( WP_REST_Request $request ): string {
		$raw = $request->get_param( 'template_type' );
		if ( is_string( $raw ) && in_array( $raw, self::ALLOWED_TEMPLATE_TYPES, true ) ) {
			return $raw;
		}
		return self::DOCUMENT_TYPE_COOKIE_CONSENT;
	}

	private function set_template_defaults( string $template_type, int $post_id ): void {
		if ( self::DOCUMENT_TYPE_PREFERENCES_BANNER === $template_type ) {
			Preferences_Banner_Template_Defaults::set_default_template_data( $post_id );
			return;
		}
		Cookie_Consent_Template_Defaults::set_default_template_data( $post_id );
	}

	/**
	 * @return int[]
	 */
	private function find_template_ids( string $template_type ): array {
		$query = new WP_Query( [
			'post_type'              => self::ELEMENTOR_LIBRARY_CPT,
			'posts_per_page'         => 2,
			'post_status'            => 'any',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'   => self::ELEMENTOR_TYPE_META_KEY,
					'value' => $template_type,
				],
			],
		] );

		return array_map( 'intval', $query->get_posts() );
	}

	/**
	 * @return int|\WP_Error
	 */
	private function create_template( string $template_type ) {
		$post_id = wp_insert_post( [
			'post_title'  => $this->get_template_post_title( $template_type ),
			'post_status' => 'publish',
			'post_type'   => self::ELEMENTOR_LIBRARY_CPT,
		] );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::ELEMENTOR_TYPE_META_KEY, $template_type );

		wp_set_object_terms( $post_id, $template_type, self::ELEMENTOR_TAXONOMY_TYPE_SLUG );

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		return $post_id;
	}

	private function get_template_post_title( string $template_type ): string {
		if ( self::DOCUMENT_TYPE_PREFERENCES_BANNER === $template_type ) {
			return esc_html__( 'Preferences Banner', 'cookiez' );
		}
		return esc_html__( 'Cookie Consent', 'cookiez' );
	}

	private function get_elementor_edit_url( int $post_id ): string {
		return admin_url( "post.php?post={$post_id}&action=elementor" );
	}

	private function get_templates_admin_url( string $template_type ): string {
		return admin_url( 'edit.php?post_type=elementor_library&tabs_group=popup&elementor_library_type=' . rawurlencode( $template_type ) );
	}
}
