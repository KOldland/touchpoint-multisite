<?php

namespace KHM\Connect;

defined( 'ABSPATH' ) || exit;

use KHM\Migrations\AddConnectBuyerDirectoryColumns;

/**
 * CRUD for the connect_rfqs table.
 */
class ConnectRfqRepository {

	private function table(): string {
		return AddConnectBuyerDirectoryColumns::rfqs_table_name();
	}

	/**
	 * Insert a new RFQ record.
	 *
	 * @param array<string,mixed> $data
	 * @return int Inserted ID or 0 on failure
	 */
	public function create( array $data ): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table(),
			[
				'user_id'                 => (int) $data['user_id'],
				'expertise'               => wp_json_encode( (array) ( $data['expertise'] ?? [] ) ),
				'industry'                => wp_json_encode( (array) ( $data['industry'] ?? [] ) ),
				'budget_min'              => isset( $data['budget_min'] ) && is_numeric( $data['budget_min'] ) ? (int) $data['budget_min'] : null,
				'budget_max'              => isset( $data['budget_max'] ) && is_numeric( $data['budget_max'] ) ? (int) $data['budget_max'] : null,
				'company_size'            => isset( $data['company_size'] ) && is_numeric( $data['company_size'] ) ? (int) $data['company_size'] : null,
				'deployment_needed'       => sanitize_key( (string) ( $data['deployment_needed'] ?? '' ) ),
				'pilot_required'          => ! empty( $data['pilot_required'] ) ? 1 : 0,
				'criteria_priority_order' => wp_json_encode( (array) ( $data['criteria_priority_order'] ?? [] ) ),
				'challenge'               => isset( $data['challenge'] ) ? sanitize_text_field( (string) $data['challenge'] ) : null,
				'solution_types'          => wp_json_encode( (array) ( $data['solution_types'] ?? [] ) ),
				'sector'                  => isset( $data['sector'] ) ? sanitize_text_field( (string) $data['sector'] ) : null,
				'region'                  => isset( $data['region'] ) ? sanitize_key( (string) $data['region'] ) : null,
				'company_size_band'       => isset( $data['company_size_band'] ) ? sanitize_key( (string) $data['company_size_band'] ) : null,
				'integrations'            => wp_json_encode( (array) ( $data['integrations'] ?? [] ) ),
				'integrations_other'      => isset( $data['integrations_other'] ) ? sanitize_textarea_field( (string) $data['integrations_other'] ) : null,
				'delivery_model'          => isset( $data['delivery_model'] ) ? sanitize_key( (string) $data['delivery_model'] ) : null,
				'engagement_model'        => isset( $data['engagement_model'] ) ? sanitize_key( (string) $data['engagement_model'] ) : null,
				'free_trial'              => ! empty( $data['free_trial'] ) ? 1 : 0,
				'provider_type'           => isset( $data['provider_type'] ) ? sanitize_key( (string) $data['provider_type'] ) : null,
				'partner_posture'         => isset( $data['partner_posture'] ) ? sanitize_key( (string) $data['partner_posture'] ) : null,
				'deployment_mode'         => isset( $data['deployment_mode'] ) ? sanitize_key( (string) $data['deployment_mode'] ) : null,
				'onboarding_style'        => isset( $data['onboarding_style'] ) ? sanitize_key( (string) $data['onboarding_style'] ) : null,
				'installation_preference' => isset( $data['installation_preference'] ) ? sanitize_key( (string) $data['installation_preference'] ) : null,
				'proof_of_commitment'     => isset( $data['proof_of_commitment'] ) ? sanitize_key( (string) $data['proof_of_commitment'] ) : null,
				'status'                  => 'active',
				'created_at'              => current_time( 'mysql', true ),
				'updated_at'              => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return false !== $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Fetch an RFQ by ID (any user).
	 */
	public function get( int $id ): ?array {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Fetch an RFQ by ID, scoped to a specific user.
	 */
	public function get_for_user( int $user_id, int $id ): ?array {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d AND user_id = %d LIMIT 1",
				$id,
				$user_id
			),
			ARRAY_A
		);
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Count active RFQs for a user. Used to enforce the 3-RFQ cap.
	 */
	public function count_active_for_user( int $user_id ): int {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d AND status = 'active'",
				$user_id
			)
		);
	}

	// ─── Hydration ─────────────────────────────────────────────────────────────

	private function hydrate( array $row ): array {
		return [
			'id'                      => (int) $row['id'],
			'user_id'                 => (int) $row['user_id'],
			'expertise'               => $this->decode_json( (string) ( $row['expertise'] ?? '' ) ),
			'industry'                => $this->decode_json( (string) ( $row['industry'] ?? '' ) ),
			'budget_min'              => isset( $row['budget_min'] ) ? (int) $row['budget_min'] : null,
			'budget_max'              => isset( $row['budget_max'] ) ? (int) $row['budget_max'] : null,
			'company_size'            => isset( $row['company_size'] ) ? (int) $row['company_size'] : null,
			'deployment_needed'       => (string) ( $row['deployment_needed'] ?? '' ),
			'pilot_required'          => ! empty( $row['pilot_required'] ),
			'criteria_priority_order' => $this->decode_json( (string) ( $row['criteria_priority_order'] ?? '' ) ),
			'challenge'               => isset( $row['challenge'] ) ? (string) $row['challenge'] : null,
			'solution_types'          => $this->decode_json( (string) ( $row['solution_types'] ?? '' ) ),
			'sector'                  => isset( $row['sector'] ) ? (string) $row['sector'] : null,
			'region'                  => isset( $row['region'] ) ? (string) $row['region'] : null,
			'company_size_band'       => isset( $row['company_size_band'] ) ? (string) $row['company_size_band'] : null,
			'integrations'            => $this->decode_json( (string) ( $row['integrations'] ?? '' ) ),
			'integrations_other'      => isset( $row['integrations_other'] ) ? (string) $row['integrations_other'] : null,
			'delivery_model'          => isset( $row['delivery_model'] ) ? (string) $row['delivery_model'] : null,
			'engagement_model'        => isset( $row['engagement_model'] ) ? (string) $row['engagement_model'] : null,
			'free_trial'              => ! empty( $row['free_trial'] ),
			'provider_type'           => isset( $row['provider_type'] ) ? (string) $row['provider_type'] : null,
			'partner_posture'         => isset( $row['partner_posture'] ) ? (string) $row['partner_posture'] : null,
			'deployment_mode'         => isset( $row['deployment_mode'] ) ? (string) $row['deployment_mode'] : null,
			'onboarding_style'        => isset( $row['onboarding_style'] ) ? (string) $row['onboarding_style'] : null,
			'installation_preference' => isset( $row['installation_preference'] ) ? (string) $row['installation_preference'] : null,
			'proof_of_commitment'     => isset( $row['proof_of_commitment'] ) ? (string) $row['proof_of_commitment'] : null,
			'status'                  => (string) ( $row['status'] ?? 'active' ),
			'created_at'              => (string) ( $row['created_at'] ?? '' ),
		];
	}

	private function decode_json( string $value ): array {
		if ( '' === $value ) {
			return [];
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
