<?php

namespace Cookiez\Modules\Elementor\Documents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Template_Defaults_Base {

	abstract protected static function get_default_template_data(): array;

	public static function set_default_template_data( int $post_id ): void {
		if ( self::is_elementor_data_empty( get_post_meta( $post_id, '_elementor_data', true ) ) ) {
			update_post_meta(
				$post_id,
				'_elementor_data',
				wp_slash( wp_json_encode( static::get_default_template_data() ) )
			);
		}

		$conditions = get_post_meta( $post_id, '_elementor_conditions', true );
		if ( ! is_array( $conditions ) || empty( $conditions ) ) {
			update_post_meta( $post_id, '_elementor_conditions', [ 'include/general' ] );
		}
	}

	private static function is_elementor_data_empty( $raw ): bool {
		if ( null === $raw || false === $raw || '' === $raw ) {
			return true;
		}

		if ( is_array( $raw ) ) {
			return empty( $raw );
		}

		if ( ! is_string( $raw ) ) {
			return true;
		}

		$decoded = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return true;
		}

		return ! is_array( $decoded ) || empty( $decoded );
	}

	protected static function generate_element_id(): string {
		return bin2hex( random_bytes( 4 ) );
	}
}
