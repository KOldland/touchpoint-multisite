<?php
namespace KH_SMMA\Variants;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariantRevisionRepository {
	/** @var wpdb */
	private $db;

	/** @var string */
	private $table;

	/** @var string */
	private $variant_table;

	public function __construct( wpdb $db ) {
		$this->db = $db;
		$this->table = $db->prefix . 'variant_revisions';
		$this->variant_table = $db->prefix . 'variants';
	}

	public function create_revision(
		string $variant_id,
		string $editor_user_id,
		string $previous_text,
		string $updated_text,
		string $idempotency_key,
		string $edit_reason = ''
	): array {
		$existing = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE variant_id = %s AND idempotency_key = %s",
				$variant_id,
				$idempotency_key
			),
			ARRAY_A
		);
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			return $this->map_row( $existing );
		}

		$revision_id = 'rev_' . wp_generate_uuid4();
		$this->db->insert(
			$this->table,
			array(
				'revision_id' => $revision_id,
				'variant_id' => $variant_id,
				'editor_user_id' => $editor_user_id,
				'idempotency_key' => $idempotency_key,
				'diff_json' => wp_json_encode(
					array(
						'previous_text' => $previous_text,
						'updated_text' => $updated_text,
						'edit_reason' => $edit_reason,
					)
				),
				'full_text' => $updated_text,
				'asset_hints_json' => wp_json_encode( array() ),
				'metadata_json' => wp_json_encode( array( 'edit_reason' => $edit_reason ) ),
				'compliance_status' => 'WARN',
				'compliance_reasons_json' => wp_json_encode( array() ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		$this->db->update(
			$this->variant_table,
			array( 'latest_revision_id' => $revision_id ),
			array( 'variant_id' => $variant_id )
		);

		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE revision_id = %s", $revision_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $this->map_row( $row ) : array(
			'revision_id' => $revision_id,
			'variant_id' => $variant_id,
			'editor_user_id' => $editor_user_id,
			'edited_at' => gmdate( 'c' ),
			'previous_text' => $previous_text,
			'updated_text' => $updated_text,
			'edit_reason' => $edit_reason,
		);
	}

	private function map_row( array $row ): array {
		$diff = json_decode( (string) ( $row['diff_json'] ?? '' ), true );
		$meta = json_decode( (string) ( $row['metadata_json'] ?? '' ), true );
		return array(
			'variant_id' => (string) ( $row['variant_id'] ?? '' ),
			'revision_id' => (string) ( $row['revision_id'] ?? '' ),
			'editor_user_id' => (string) ( $row['editor_user_id'] ?? '' ),
			'edited_at' => gmdate( 'c', strtotime( (string) ( $row['created_at'] ?? 'now' ) ) ),
			'previous_text' => (string) ( $diff['previous_text'] ?? '' ),
			'updated_text' => (string) ( $diff['updated_text'] ?? ( $row['full_text'] ?? '' ) ),
			'edit_reason' => (string) ( $diff['edit_reason'] ?? $meta['edit_reason'] ?? '' ),
		);
	}
}
