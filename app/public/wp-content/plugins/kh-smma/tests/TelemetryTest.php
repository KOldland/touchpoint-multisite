<?php
namespace KH_SMMA\Tests;

use PHPUnit\Framework\TestCase;
use KH_SMMA\Services\ScheduleQueueProcessor;

class TelemetryTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions
		if ( ! function_exists( 'wp_json_encode' ) ) {
			function wp_json_encode( $data ) {
				return json_encode( $data );
			}
		}

		if ( ! function_exists( 'get_current_user_id' ) ) {
			function get_current_user_id() {
				return 1;
			}
		}

		if ( ! function_exists( 'update_post_meta' ) ) {
			function update_post_meta( $post_id, $key, $value ) {
				global $_test_post_meta;
				if ( ! isset( $_test_post_meta[ $post_id ] ) ) {
					$_test_post_meta[ $post_id ] = array();
				}
				$_test_post_meta[ $post_id ][ $key ] = $value;
				return true;
			}
		}

		if ( ! function_exists( 'get_post_meta' ) ) {
			function get_post_meta( $post_id, $key, $single = false ) {
				global $_test_post_meta;
				if ( ! isset( $_test_post_meta[ $post_id ][ $key ] ) ) {
					return $single ? '' : array();
				}
				return $single ? $_test_post_meta[ $post_id ][ $key ] : array( $_test_post_meta[ $post_id ][ $key ] );
			}
		}

		if ( ! function_exists( 'time' ) ) {
			function time() {
				return \time();
			}
		}

		if ( ! function_exists( '__' ) ) {
			function __( $text, $domain = '' ) {
				return $text;
			}
		}

		if ( ! function_exists( 'do_action' ) ) {
			function do_action( $hook, ...$args ) {
				// No-op for tests
			}
		}

		global $_test_post_meta;
		$_test_post_meta = array();
	}

	/**
	 * Test that schedule telemetry has required structure
	 */
	public function test_schedule_telemetry_structure() {
		$schedule_id = 101;
		$telemetry_data = array(
			'mode'            => 'schedule',
			'provider'        => 'linkedin',
			'sponsor_id'      => 42,
			'payload_preview' => array(
				'variant_id' => 'v-test-001',
				'text'       => 'Sample variant text',
			),
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		// Assert required fields
		$this->assertIsArray( $stored, 'Telemetry should be stored as array' );
		$this->assertArrayHasKey( 'timestamp', $stored, 'Telemetry must include timestamp' );
		$this->assertArrayHasKey( 'mode', $stored, 'Telemetry must include mode' );
		$this->assertArrayHasKey( 'provider', $stored, 'Telemetry must include provider' );

		// Assert field types
		$this->assertIsInt( $stored['timestamp'], 'Timestamp must be integer' );
		$this->assertIsString( $stored['mode'], 'Mode must be string' );
		$this->assertIsString( $stored['provider'], 'Provider must be string' );

		// Assert values match
		$this->assertEquals( 'schedule', $stored['mode'] );
		$this->assertEquals( 'linkedin', $stored['provider'] );
		$this->assertEquals( 42, $stored['sponsor_id'] );
		$this->assertIsArray( $stored['payload_preview'] );
	}

	/**
	 * Test telemetry for generate mode
	 */
	public function test_generate_mode_telemetry() {
		$post_id = 201;
		$telemetry_data = array(
			'mode'     => 'generate',
			'provider' => 'smma',
			'request'  => array(
				'post_id'  => $post_id,
				'variants' => 3,
				'phase_tag' => 'Attention',
			),
		);

		ScheduleQueueProcessor::log_telemetry( $post_id, $telemetry_data );

		$stored = get_post_meta( $post_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'generate', $stored['mode'] );
		$this->assertEquals( 'smma', $stored['provider'] );
		$this->assertArrayHasKey( 'request', $stored );
		$this->assertEquals( 3, $stored['request']['variants'] );
		$this->assertEquals( 'Attention', $stored['request']['phase_tag'] );
		$this->assertIsInt( $stored['timestamp'] );
		$this->assertGreaterThan( 0, $stored['timestamp'] );
	}

	/**
	 * Test telemetry for approve mode
	 */
	public function test_approve_mode_telemetry() {
		$schedule_id = 301;
		$approver_id = 5;
		$notes = 'Approved for campaign launch';

		$telemetry_data = array(
			'mode'        => 'approve',
			'provider'    => 'smma',
			'approver_id' => $approver_id,
			'notes'       => $notes,
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'approve', $stored['mode'] );
		$this->assertEquals( $approver_id, $stored['approver_id'] );
		$this->assertEquals( $notes, $stored['notes'] );
		$this->assertArrayHasKey( 'timestamp', $stored );
	}

	/**
	 * Test telemetry for reject mode
	 */
	public function test_reject_mode_telemetry() {
		$schedule_id = 401;
		$rejected_by = 7;
		$reason = 'Content does not align with brand guidelines';

		$telemetry_data = array(
			'mode'             => 'reject',
			'provider'         => 'smma',
			'rejected_by'      => $rejected_by,
			'rejection_reason' => $reason,
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'reject', $stored['mode'] );
		$this->assertEquals( $rejected_by, $stored['rejected_by'] );
		$this->assertEquals( $reason, $stored['rejection_reason'] );
		$this->assertIsInt( $stored['timestamp'] );
	}

	/**
	 * Test telemetry for variant_edit mode with diff
	 */
	public function test_variant_edit_mode_telemetry_with_diff() {
		$schedule_id = 501;
		$editor_id = 3;
		$diff = array(
			'original' => 'Old text here',
			'updated'  => 'New improved text here',
			'size'     => 15,
		);

		$telemetry_data = array(
			'mode'              => 'variant_edit',
			'provider'          => 'smma',
			'editor_id'         => $editor_id,
			'diff'              => $diff,
			'compliance_result' => array(
				'passed' => true,
				'level'  => 'OK',
			),
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'variant_edit', $stored['mode'] );
		$this->assertEquals( $editor_id, $stored['editor_id'] );
		$this->assertIsArray( $stored['diff'] );
		$this->assertEquals( 15, $stored['diff']['size'] );
		$this->assertArrayHasKey( 'compliance_result', $stored );
		$this->assertTrue( $stored['compliance_result']['passed'] );
	}

	/**
	 * Test telemetry for sandbox mode
	 */
	public function test_sandbox_mode_telemetry() {
		$schedule_id = 601;
		$payload = array(
			'variant_id' => 'v-sandbox-001',
			'message'    => 'Test message for sandbox',
		);

		$telemetry_data = array(
			'mode'            => 'sandbox',
			'provider'        => 'linkedin',
			'payload_preview' => $payload,
			'note'            => 'Sandbox enabled – payload logged without hitting the API.',
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'sandbox', $stored['mode'] );
		$this->assertEquals( 'linkedin', $stored['provider'] );
		$this->assertArrayHasKey( 'payload_preview', $stored );
		$this->assertEquals( 'v-sandbox-001', $stored['payload_preview']['variant_id'] );
		$this->assertStringContainsString( 'Sandbox enabled', $stored['note'] );
	}

	/**
	 * Test telemetry for error scenarios
	 */
	public function test_error_telemetry() {
		$schedule_id = 701;
		$error_message = 'API rate limit exceeded';

		$telemetry_data = array(
			'mode'     => 'live',
			'provider' => 'linkedin',
			'error'    => $error_message,
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'live', $stored['mode'] );
		$this->assertArrayHasKey( 'error', $stored );
		$this->assertEquals( $error_message, $stored['error'] );
		$this->assertIsInt( $stored['timestamp'] );
	}

	/**
	 * Test telemetry for manual export mode
	 */
	public function test_manual_export_telemetry() {
		$schedule_id = 801;
		$sponsor_id = 12;
		$budget = array(
			'platform' => 'LinkedIn',
			'daily'    => 50.00,
			'total'    => 500.00,
		);

		$telemetry_data = array(
			'mode'               => 'manual',
			'provider'           => 'manual',
			'sponsor_id'         => $sponsor_id,
			'recommended_budget' => $budget,
			'note'               => 'Manual export bundle generated with sponsor metadata.',
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $telemetry_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'manual', $stored['mode'] );
		$this->assertEquals( $sponsor_id, $stored['sponsor_id'] );
		$this->assertIsArray( $stored['recommended_budget'] );
		$this->assertEquals( 50.00, $stored['recommended_budget']['daily'] );
		$this->assertEquals( 500.00, $stored['recommended_budget']['total'] );
		$this->assertEquals( 'LinkedIn', $stored['recommended_budget']['platform'] );
	}

	/**
	 * Test telemetry timestamp is recent
	 */
	public function test_telemetry_timestamp_is_recent() {
		$schedule_id = 901;
		$before = time();

		ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
			'mode'     => 'test',
			'provider' => 'test',
		) );

		$after = time();
		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertGreaterThanOrEqual( $before, $stored['timestamp'] );
		$this->assertLessThanOrEqual( $after, $stored['timestamp'] );
	}

	/**
	 * Test that empty schedule_id is rejected
	 */
	public function test_telemetry_rejects_empty_schedule_id() {
		ScheduleQueueProcessor::log_telemetry( 0, array( 'mode' => 'test' ) );

		$stored = get_post_meta( 0, '_kh_smma_last_telemetry', true );
		$this->assertEmpty( $stored, 'Should not store telemetry for schedule_id=0' );
	}

	/**
	 * Test telemetry data merge preserves custom fields
	 */
	public function test_telemetry_preserves_custom_fields() {
		$schedule_id = 1001;
		$custom_data = array(
			'mode'               => 'custom',
			'provider'           => 'custom_provider',
			'custom_field_1'     => 'value1',
			'custom_field_2'     => array( 'nested' => 'data' ),
			'numeric_field'      => 42,
			'boolean_field'      => true,
		);

		ScheduleQueueProcessor::log_telemetry( $schedule_id, $custom_data );

		$stored = get_post_meta( $schedule_id, '_kh_smma_last_telemetry', true );

		$this->assertEquals( 'custom', $stored['mode'] );
		$this->assertEquals( 'value1', $stored['custom_field_1'] );
		$this->assertEquals( array( 'nested' => 'data' ), $stored['custom_field_2'] );
		$this->assertEquals( 42, $stored['numeric_field'] );
		$this->assertTrue( $stored['boolean_field'] );
		$this->assertArrayHasKey( 'timestamp', $stored );
	}
}
