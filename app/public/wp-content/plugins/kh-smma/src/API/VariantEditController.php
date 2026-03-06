<?php
namespace KH_SMMA\API;

use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Variants\VariantRepository;
use KH_SMMA\Variants\VariantRevisionRepository;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariantEditController {
	/** @var VariantRepository */
	private $variants;

	/** @var VariantRevisionRepository */
	private $revisions;

	/** @var AuditLogger */
	private $audit;

	public function __construct( VariantRepository $variants, VariantRevisionRepository $revisions, AuditLogger $audit ) {
		$this->variants = $variants;
		$this->revisions = $revisions;
		$this->audit = $audit;
	}

	public function handle( WP_REST_Request $request ) {
		$variant_id = sanitize_text_field( (string) $request->get_param( 'variant_id' ) );
		$idempotency = sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' ) );
		$payload = $request->get_json_params();

		if ( '' === $variant_id || '' === $idempotency ) {
			return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'variant_id and Idempotency-Key are required.', array( 'status' => 400 ) );
		}

		$current = $this->variants->get_variant( $variant_id );
		if ( empty( $current ) ) {
			return new WP_Error( 'SMMA_ERR_SCHEMA_INVALID', 'Unknown variant_id.', array( 'status' => 400 ) );
		}

		$prev = (string) ( $current['payload']['text'] ?? '' );
		$next = (string) ( $payload['text'] ?? $prev );
		$editor = (string) ( $payload['editor_user_id'] ?? get_current_user_id() );
		$reason = (string) ( $payload['edit_reason'] ?? '' );

		$revision = $this->revisions->create_revision( $variant_id, $editor, $prev, $next, $idempotency, $reason );
		$this->audit->log(
			'smma_variant_edit',
			array(
				'object_type' => 'variant',
				'details' => array(
					'variant_id' => $variant_id,
					'revision_id' => $revision['revision_id'] ?? '',
					'editor_user_id' => $editor,
				),
				'user_id' => get_current_user_id(),
			)
		);

		return rest_ensure_response(
			array(
				'variant_id' => $variant_id,
				'revision' => $revision,
			)
		);
	}
}
