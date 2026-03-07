<?php
namespace KH_SMMA\Variants;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariantRepository {
	/** @var wpdb */
	private $db;

	/** @var string */
	private $table;

	public function __construct( wpdb $db ) {
		$this->db = $db;
		$this->table = $db->prefix . 'variants';
	}

	public function save_generated_variant( string $request_id, array $variant, array $google_payload = array() ): string {
		$variant_id = (string) ( $variant['variant_id'] ?? ( 'var_' . wp_generate_uuid4() ) );
		$this->db->replace(
			$this->table,
			array(
				'variant_id' => $variant_id,
				'originating_generate_request_id' => $request_id,
				'approval_status' => strtolower( (string) ( $variant['compliance_status'] ?? 'pass' ) ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'latest_revision_id' => '',
				'linkedin_payload' => wp_json_encode( $variant ),
				'google_payload' => wp_json_encode( $google_payload ),
			)
		);

		return $variant_id;
	}

	public function get_variant( string $variant_id ): array {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE variant_id = %s", $variant_id ),
			ARRAY_A
		);

		if ( ! is_array( $row ) || empty( $row ) ) {
			return array();
		}

		$payload = json_decode( (string) ( $row['linkedin_payload'] ?? '' ), true );
		return array(
			'variant_id' => $row['variant_id'],
			'latest_revision_id' => (string) ( $row['latest_revision_id'] ?? '' ),
			'approval_status' => (string) ( $row['approval_status'] ?? '' ),
			'payload' => is_array( $payload ) ? $payload : array(),
		);
	}
}
