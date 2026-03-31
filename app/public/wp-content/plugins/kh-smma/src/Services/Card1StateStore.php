<?php
namespace KH_SMMA\Services;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Card1StateStore {
	private const OPT_KEY = 'kh_smma_card1_state';

	/** @var wpdb|null */
	private $db;

	/** @var bool */
	private $use_option_fallback = false;

	/** @var string */
	private $table_generate_requests;

	/** @var string */
	private $table_variants;

	/** @var string */
	private $table_variant_revisions;

	/** @var string */
	private $table_schedules;

	/** @var string */
	private $table_schedule_queue;

	public function __construct( wpdb $db = null ) {
		global $wpdb;
		$this->db = $db ?: ( $wpdb ?? null );
		$this->use_option_fallback = ! $this->db || ! method_exists( $this->db, 'query' ) || (string) getenv( 'KH_SMMA_TEST_MODE' ) === 'ci';

		$prefix = $this->db ? $this->db->prefix : 'wp_';
		$this->table_generate_requests = $prefix . 'smma_generate_requests';
		$this->table_variants = $prefix . 'variants';
		$this->table_variant_revisions = $prefix . 'variant_revisions';
		$this->table_schedules = $prefix . 'smma_schedules';
		$this->table_schedule_queue = $prefix . 'smma_schedule_queue';
	}

	public function install(): void {
		if ( $this->use_option_fallback || ! $this->db ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $this->db->get_charset_collate();

		$sql_generate = "CREATE TABLE {$this->table_generate_requests} (
			request_id varchar(64) NOT NULL,
			post_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			prompt_hash varchar(128) NOT NULL DEFAULT '',
			model varchar(128) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			PRIMARY KEY  (request_id),
			KEY post_id (post_id),
			KEY user_id (user_id)
		) {$charset};";

		$sql_variants = "CREATE TABLE {$this->table_variants} (
			variant_id varchar(64) NOT NULL,
			originating_generate_request_id varchar(64) NOT NULL,
			approval_status varchar(32) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			latest_revision_id varchar(64) NOT NULL DEFAULT '',
			linkedin_payload longtext NULL,
			google_payload longtext NULL,
			PRIMARY KEY  (variant_id),
			KEY request_id (originating_generate_request_id)
		) {$charset};";

		$sql_revisions = "CREATE TABLE {$this->table_variant_revisions} (
			revision_id varchar(64) NOT NULL,
			variant_id varchar(64) NOT NULL,
			editor_user_id varchar(64) NOT NULL DEFAULT '',
			idempotency_key varchar(128) NOT NULL DEFAULT '',
			diff_json longtext NULL,
			full_text longtext NULL,
			asset_hints_json longtext NULL,
			metadata_json longtext NULL,
			compliance_status varchar(16) NOT NULL DEFAULT 'WARN',
			compliance_reasons_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (revision_id),
			UNIQUE KEY uniq_variant_idem (variant_id,idempotency_key),
			KEY variant_id (variant_id)
		) {$charset};";

		$sql_schedules = "CREATE TABLE {$this->table_schedules} (
			schedule_id varchar(64) NOT NULL,
			variant_id varchar(64) NOT NULL,
			sponsor_id varchar(64) NOT NULL,
			schedule_time datetime NOT NULL,
			boost_options_json longtext NULL,
			status varchar(64) NOT NULL DEFAULT 'queued',
			approval_required tinyint(1) NOT NULL DEFAULT 0,
			approval_status varchar(32) NOT NULL DEFAULT 'approved',
			compliance_status varchar(16) NOT NULL DEFAULT 'OK',
			compliance_reason text NULL,
			idempotency_key varchar(128) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			mode varchar(32) NOT NULL DEFAULT 'sandbox',
			manifest_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (schedule_id),
			UNIQUE KEY uniq_schedule_idem (created_by,idempotency_key,variant_id),
			KEY variant_id (variant_id),
			KEY sponsor_id (sponsor_id),
			KEY schedule_time (schedule_time),
			KEY status (status),
			KEY approval_status (approval_status),
			KEY compliance_status (compliance_status)
		) {$charset};";

		$sql_queue = "CREATE TABLE {$this->table_schedule_queue} (
			queue_id varchar(64) NOT NULL,
			schedule_id varchar(64) NOT NULL,
			idempotency_key varchar(128) NOT NULL DEFAULT '',
			queue_payload_json longtext NULL,
			status varchar(32) NOT NULL DEFAULT 'queued',
			attempt_count int(11) NOT NULL DEFAULT 0,
			last_error text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (queue_id),
			KEY schedule_id (schedule_id),
			KEY status_attempt (status,attempt_count)
		) {$charset};";

		\dbDelta( $sql_generate );
		\dbDelta( $sql_variants );
		\dbDelta( $sql_revisions );
		\dbDelta( $sql_schedules );
		\dbDelta( $sql_queue );
	}

	private function load_state(): array {
		$state = get_option(
			self::OPT_KEY,
			array(
				'generate_requests' => array(),
				'variants' => array(),
				'variant_revisions' => array(),
				'schedules' => array(),
				'schedule_queue' => array(),
				'idempotency' => array(),
			)
		);

		return is_array( $state ) ? $state : array();
	}

	private function save_state( array $state ): void {
		update_option( self::OPT_KEY, $state );
	}

	private function encode( $value ): string {
		return wp_json_encode( $value );
	}

	private function decode( $value ): array {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public function create_generate_request( array $request ): string {
		$request_id = $request['request_id'] ?? 'gen_' . wp_generate_uuid4();
		$request['request_id'] = $request_id;

		if ( $this->use_option_fallback || ! $this->db ) {
			$state = $this->load_state();
			$state['generate_requests'][ $request_id ] = $request;
			$this->save_state( $state );
			return $request_id;
		}

		$this->db->replace(
			$this->table_generate_requests,
			array(
				'request_id' => $request_id,
				'post_id' => (string) ( $request['post_id'] ?? '' ),
				'user_id' => (int) ( $request['user_id'] ?? 0 ),
				'prompt_hash' => (string) ( $request['prompt_hash'] ?? '' ),
				'model' => (string) ( $request['model'] ?? '' ),
				'status' => (string) ( $request['status'] ?? 'success' ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return $request_id;
	}

	public function upsert_variant( string $request_id, array $variant, array $google_draft = array() ): string {
		$variant_id = $variant['variant_id'] ?? 'var_' . wp_generate_uuid4();

		if ( $this->use_option_fallback || ! $this->db ) {
			$state = $this->load_state();
			$state['variants'][ $variant_id ] = array(
				'variant_id' => $variant_id,
				'originating_generate_request_id' => $request_id,
				'approval_status' => strtolower( (string) ( $variant['compliance']['status'] ?? 'pass' ) ),
				'created_at' => gmdate( 'c' ),
				'latest_revision_id' => $state['variants'][ $variant_id ]['latest_revision_id'] ?? '',
				'linkedIn' => $variant,
				'google' => $google_draft,
			);
			$this->save_state( $state );
			return $variant_id;
		}

		$existing = $this->db->get_row(
			$this->db->prepare( "SELECT latest_revision_id FROM {$this->table_variants} WHERE variant_id = %s", $variant_id ),
			ARRAY_A
		);
		$replace_result = $this->db->replace(
			$this->table_variants,
			array(
				'variant_id' => $variant_id,
				'originating_generate_request_id' => $request_id,
				'approval_status' => strtolower( (string) ( $variant['compliance']['status'] ?? 'pass' ) ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'latest_revision_id' => (string) ( $existing['latest_revision_id'] ?? '' ),
				'linkedin_payload' => $this->encode( $variant ),
				'google_payload' => $this->encode( $google_draft ),
			)
		);
		if ( false === $replace_result ) {
			$state = $this->load_state();
			$state['variants'][ $variant_id ] = array(
				'variant_id' => $variant_id,
				'originating_generate_request_id' => $request_id,
				'approval_status' => strtolower( (string) ( $variant['compliance']['status'] ?? 'pass' ) ),
				'created_at' => gmdate( 'c' ),
				'latest_revision_id' => $state['variants'][ $variant_id ]['latest_revision_id'] ?? '',
				'linkedIn' => $variant,
				'google' => $google_draft,
			);
			$this->save_state( $state );
		}

		return $variant_id;
	}

	public function get_variant( string $variant_id ): array {
		if ( $this->use_option_fallback || ! $this->db ) {
			$state = $this->load_state();
			return $state['variants'][ $variant_id ] ?? array();
		}

		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table_variants} WHERE variant_id = %s", $variant_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) || empty( $row ) ) {
			$state = $this->load_state();
			return $state['variants'][ $variant_id ] ?? array();
		}

		return array(
			'variant_id' => $row['variant_id'],
			'originating_generate_request_id' => $row['originating_generate_request_id'],
			'approval_status' => $row['approval_status'],
			'created_at' => $row['created_at'],
			'latest_revision_id' => $row['latest_revision_id'],
			'linkedIn' => $this->decode( $row['linkedin_payload'] ?? '' ),
			'google' => $this->decode( $row['google_payload'] ?? '' ),
		);
	}

	public function apply_variant_edit( string $variant_id, string $idempotency_key, array $edit, array $compliance ): array {
		if ( $this->use_option_fallback || ! $this->db ) {
			$state = $this->load_state();
			$idem_scope = 'variant_edit:' . $variant_id . ':' . $idempotency_key;
			if ( isset( $state['idempotency'][ $idem_scope ] ) ) {
				return array(
					'idempotent' => true,
					'revision' => $state['idempotency'][ $idem_scope ],
				);
			}

			$variant = $state['variants'][ $variant_id ] ?? array();
			if ( empty( $variant ) ) {
				return array();
			}

		$old_text = (string) ( $variant['linkedIn']['text'] ?? '' );
		$new_text = (string) ( $edit['text'] ?? $old_text );
		$edit_reason = (string) ( $edit['edit_reason'] ?? ( $edit['metadata']['edit_reason'] ?? '' ) );
		$revision_id = 'rev_' . wp_generate_uuid4();
		$revision = array(
				'revision_id' => $revision_id,
				'variant_id' => $variant_id,
				'editor_user_id' => (string) ( $edit['editor_user_id'] ?? '' ),
			'diff' => array( 'previous_text' => $old_text, 'updated_text' => $new_text, 'edit_reason' => $edit_reason ),
			'full_text' => $new_text,
				'asset_hints' => $edit['asset_hints'] ?? array(),
			'metadata' => array_merge( (array) ( $edit['metadata'] ?? array() ), array( 'edit_reason' => $edit_reason ) ),
				'compliance_status' => $compliance['status'] ?? 'WARN',
				'compliance_reasons' => $compliance['reasons'] ?? array(),
				'created_at' => gmdate( 'c' ),
			);

			$state['variant_revisions'][ $revision_id ] = $revision;
			$variant['latest_revision_id'] = $revision_id;
			$variant['linkedIn']['text'] = $new_text;
			$variant['linkedIn']['asset_hints'] = $edit['asset_hints'] ?? array();
			$variant['linkedIn']['compliance'] = $compliance;
			$variant['approval_status'] = strtolower( (string) ( $compliance['status'] ?? 'warn' ) );
			$state['variants'][ $variant_id ] = $variant;
			$state['idempotency'][ $idem_scope ] = $revision;
			$this->save_state( $state );

			return array( 'idempotent' => false, 'revision' => $revision );
		}

		$existing = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table_variant_revisions} WHERE variant_id = %s AND idempotency_key = %s",
				$variant_id,
				$idempotency_key
			),
			ARRAY_A
		);
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			return array(
				'idempotent' => true,
				'revision' => array(
					'revision_id' => $existing['revision_id'],
					'variant_id' => $existing['variant_id'],
					'editor_user_id' => $existing['editor_user_id'],
					'diff' => $this->decode( $existing['diff_json'] ?? '' ),
					'full_text' => (string) ( $existing['full_text'] ?? '' ),
					'asset_hints' => $this->decode( $existing['asset_hints_json'] ?? '' ),
					'metadata' => $this->decode( $existing['metadata_json'] ?? '' ),
					'compliance_status' => $existing['compliance_status'],
					'compliance_reasons' => $this->decode( $existing['compliance_reasons_json'] ?? '' ),
					'created_at' => $existing['created_at'],
				),
			);
		}

		$variant = $this->get_variant( $variant_id );
		if ( empty( $variant ) ) {
			return array();
		}

		$old_text = (string) ( $variant['linkedIn']['text'] ?? '' );
		$new_text = (string) ( $edit['text'] ?? $old_text );
		$edit_reason = (string) ( $edit['edit_reason'] ?? ( $edit['metadata']['edit_reason'] ?? '' ) );
		$revision_id = 'rev_' . wp_generate_uuid4();

		$this->db->insert(
			$this->table_variant_revisions,
			array(
				'revision_id' => $revision_id,
				'variant_id' => $variant_id,
				'editor_user_id' => (string) ( $edit['editor_user_id'] ?? '' ),
				'idempotency_key' => $idempotency_key,
				'diff_json' => $this->encode( array( 'previous_text' => $old_text, 'updated_text' => $new_text, 'edit_reason' => $edit_reason ) ),
				'full_text' => $new_text,
				'asset_hints_json' => $this->encode( $edit['asset_hints'] ?? array() ),
				'metadata_json' => $this->encode( array_merge( (array) ( $edit['metadata'] ?? array() ), array( 'edit_reason' => $edit_reason ) ) ),
				'compliance_status' => (string) ( $compliance['status'] ?? 'WARN' ),
				'compliance_reasons_json' => $this->encode( $compliance['reasons'] ?? array() ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		$linkedin = $variant['linkedIn'];
		$linkedin['text'] = $new_text;
		$linkedin['asset_hints'] = $edit['asset_hints'] ?? array();
		$linkedin['compliance'] = $compliance;
		$this->db->update(
			$this->table_variants,
			array(
				'latest_revision_id' => $revision_id,
				'approval_status' => strtolower( (string) ( $compliance['status'] ?? 'warn' ) ),
				'linkedin_payload' => $this->encode( $linkedin ),
			),
			array( 'variant_id' => $variant_id )
		);

		return array(
			'idempotent' => false,
			'revision' => array(
				'revision_id' => $revision_id,
				'variant_id' => $variant_id,
				'editor_user_id' => (string) ( $edit['editor_user_id'] ?? '' ),
				'diff' => array( 'previous_text' => $old_text, 'updated_text' => $new_text, 'edit_reason' => $edit_reason ),
				'full_text' => $new_text,
				'asset_hints' => $edit['asset_hints'] ?? array(),
				'metadata' => array_merge( (array) ( $edit['metadata'] ?? array() ), array( 'edit_reason' => $edit_reason ) ),
				'compliance_status' => $compliance['status'] ?? 'WARN',
				'compliance_reasons' => $compliance['reasons'] ?? array(),
				'created_at' => gmdate( 'c' ),
			),
		);
	}

	public function create_schedule( string $idempotency_key, int $user_id, array $schedule ): array {
		if ( $this->use_option_fallback || ! $this->db ) {
			$state = $this->load_state();
			$scope = 'schedule:' . $user_id . ':' . $idempotency_key . ':' . (string) ( $schedule['variant_id'] ?? '' );
			if ( isset( $state['idempotency'][ $scope ] ) ) {
				return array( 'idempotent' => true, 'schedule' => $state['idempotency'][ $scope ] );
			}

			$schedule_id = 'sch_' . wp_generate_uuid4();
			$row = array(
				'schedule_id' => $schedule_id,
				'variant_id' => (string) ( $schedule['variant_id'] ?? '' ),
				'sponsor_id' => (string) ( $schedule['sponsor_id'] ?? '' ),
				'schedule_time' => (string) ( $schedule['schedule_time'] ?? '' ),
				'boost_options' => $schedule['boost_options'] ?? array(),
				'status' => (string) ( $schedule['status'] ?? 'queued' ),
				'approval_required' => ! empty( $schedule['approval_required'] ),
				'approval_status' => (string) ( $schedule['approval_status'] ?? 'approved' ),
				'compliance_status' => (string) ( $schedule['compliance_status'] ?? 'OK' ),
				'compliance_reason' => (string) ( $schedule['compliance_reason'] ?? '' ),
				'idempotency_key' => $idempotency_key,
				'created_by' => $user_id,
				'created_at' => gmdate( 'c' ),
				'mode' => (string) ( $schedule['mode'] ?? 'sandbox' ),
				'manifest' => $schedule['manifest'] ?? array(),
			);

			$state['schedules'][ $schedule_id ] = $row;
			$state['schedule_queue'][] = array(
				'queue_id' => 'q_' . wp_generate_uuid4(),
				'schedule_id' => $schedule_id,
				'idempotency_key' => $idempotency_key,
				'queue_payload' => array(
					'manifest' => $row['manifest'],
					'idempotency_key' => $idempotency_key,
				),
				'status' => 'queued',
				'attempt_count' => 0,
				'last_error' => '',
				'created_at' => gmdate( 'c' ),
			);
			$state['idempotency'][ $scope ] = $row;
			$this->save_state( $state );
			return array( 'idempotent' => false, 'schedule' => $row );
		}

		$variant_id = (string) ( $schedule['variant_id'] ?? '' );
		$existing = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table_schedules} WHERE created_by = %d AND idempotency_key = %s AND variant_id = %s",
				$user_id,
				$idempotency_key,
				$variant_id
			),
			ARRAY_A
		);
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			return array(
				'idempotent' => true,
				'schedule' => array(
					'schedule_id' => $existing['schedule_id'],
					'variant_id' => $existing['variant_id'],
					'sponsor_id' => $existing['sponsor_id'],
					'schedule_time' => gmdate( 'c', strtotime( $existing['schedule_time'] ) ),
					'boost_options' => $this->decode( $existing['boost_options_json'] ?? '' ),
					'status' => $existing['status'],
					'approval_required' => ! empty( $existing['approval_required'] ),
					'approval_status' => (string) ( $existing['approval_status'] ?? 'approved' ),
					'compliance_status' => (string) ( $existing['compliance_status'] ?? 'OK' ),
					'compliance_reason' => (string) ( $existing['compliance_reason'] ?? '' ),
					'idempotency_key' => $existing['idempotency_key'],
					'created_by' => (int) $existing['created_by'],
					'created_at' => gmdate( 'c', strtotime( $existing['created_at'] ) ),
					'mode' => $existing['mode'],
					'manifest' => $this->decode( $existing['manifest_json'] ?? '' ),
				),
			);
		}

		$schedule_id = 'sch_' . wp_generate_uuid4();
		$row = array(
			'schedule_id' => $schedule_id,
			'variant_id' => $variant_id,
			'sponsor_id' => (string) ( $schedule['sponsor_id'] ?? '' ),
			'schedule_time' => (string) ( $schedule['schedule_time'] ?? gmdate( 'c' ) ),
			'boost_options' => $schedule['boost_options'] ?? array(),
			'status' => (string) ( $schedule['status'] ?? 'queued' ),
			'approval_required' => ! empty( $schedule['approval_required'] ),
			'approval_status' => (string) ( $schedule['approval_status'] ?? 'approved' ),
			'compliance_status' => (string) ( $schedule['compliance_status'] ?? 'OK' ),
			'compliance_reason' => (string) ( $schedule['compliance_reason'] ?? '' ),
			'idempotency_key' => $idempotency_key,
			'created_by' => $user_id,
			'created_at' => gmdate( 'c' ),
			'mode' => (string) ( $schedule['mode'] ?? 'sandbox' ),
			'manifest' => $schedule['manifest'] ?? array(),
		);

		$this->db->insert(
			$this->table_schedules,
			array(
				'schedule_id' => $row['schedule_id'],
				'variant_id' => $row['variant_id'],
				'sponsor_id' => $row['sponsor_id'],
				'schedule_time' => gmdate( 'Y-m-d H:i:s', strtotime( $row['schedule_time'] ) ),
				'boost_options_json' => $this->encode( $row['boost_options'] ),
				'status' => $row['status'],
				'approval_required' => $row['approval_required'] ? 1 : 0,
				'approval_status' => $row['approval_status'],
				'compliance_status' => $row['compliance_status'],
				'compliance_reason' => $row['compliance_reason'],
				'idempotency_key' => $row['idempotency_key'],
				'created_by' => $row['created_by'],
				'mode' => $row['mode'],
				'manifest_json' => $this->encode( $row['manifest'] ),
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$this->db->insert(
			$this->table_schedule_queue,
			array(
				'queue_id' => 'q_' . wp_generate_uuid4(),
				'schedule_id' => $row['schedule_id'],
				'idempotency_key' => $idempotency_key,
				'queue_payload_json' => $this->encode(
					array(
						'manifest' => $row['manifest'],
						'idempotency_key' => $idempotency_key,
					)
				),
				'status' => 'queued',
				'attempt_count' => 0,
				'last_error' => '',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		return array( 'idempotent' => false, 'schedule' => $row );
	}

	public function get_schedule( string $schedule_id ): array {
		if ( '' === $schedule_id ) {
			return array();
		}

		if ( $this->use_option_fallback || ! $this->db ) {
			$state = $this->load_state();
			return (array) ( $state['schedules'][ $schedule_id ] ?? array() );
		}

		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table_schedules} WHERE schedule_id = %s", $schedule_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) || empty( $row ) ) {
			return array();
		}

		return array(
			'schedule_id' => (string) $row['schedule_id'],
			'variant_id' => (string) $row['variant_id'],
			'sponsor_id' => (string) $row['sponsor_id'],
			'schedule_time' => gmdate( 'c', strtotime( (string) $row['schedule_time'] ) ),
			'boost_options' => $this->decode( $row['boost_options_json'] ?? '' ),
			'status' => (string) $row['status'],
			'approval_required' => ! empty( $row['approval_required'] ),
			'approval_status' => (string) ( $row['approval_status'] ?? 'approved' ),
			'compliance_status' => (string) ( $row['compliance_status'] ?? 'OK' ),
			'compliance_reason' => (string) ( $row['compliance_reason'] ?? '' ),
			'idempotency_key' => (string) $row['idempotency_key'],
			'created_by' => (int) $row['created_by'],
			'mode' => (string) ( $row['mode'] ?? 'sandbox' ),
			'manifest' => $this->decode( $row['manifest_json'] ?? '' ),
			'created_at' => gmdate( 'c', strtotime( (string) $row['created_at'] ) ),
		);
	}

	public function reevaluate_dispatch_eligibility( string $schedule_id ): array {
		$schedule = $this->get_schedule( $schedule_id );
		if ( empty( $schedule ) ) {
			return array();
		}

		$approval_required = ! empty( $schedule['approval_required'] );
		$approval_status = strtolower( (string) ( $schedule['approval_status'] ?? 'pending' ) );
		if ( 'auto_approved' === $approval_status ) {
			$approval_status = 'approved';
		}
		if ( 'denied' === $approval_status ) {
			$approval_status = 'rejected';
		}

		$eligible = false;
		$status = 'pending_approval';
		if ( ! $approval_required || 'approved' === $approval_status ) {
			$scheduled_at = strtotime( (string) ( $schedule['schedule_time'] ?? '' ) );
			$status = ( false !== $scheduled_at && $scheduled_at <= time() ) ? 'queued_for_execution' : 'queued';
			$eligible = true;
		} elseif ( 'rejected' === $approval_status ) {
			$status = 'rejected';
		}

		if ( $this->use_option_fallback || ! $this->db ) {
			$state = $this->load_state();
			if ( isset( $state['schedules'][ $schedule_id ] ) ) {
				$state['schedules'][ $schedule_id ]['status'] = $status;
				$this->save_state( $state );
			}
		} else {
			$this->db->update(
				$this->table_schedules,
				array( 'status' => $status ),
				array( 'schedule_id' => $schedule_id )
			);
		}

		return array(
			'schedule_id' => $schedule_id,
			'eligible' => $eligible,
			'status' => $status,
			'approval_status' => $approval_status,
			'reason' => $eligible ? '' : 'approval_required',
		);
	}
}
