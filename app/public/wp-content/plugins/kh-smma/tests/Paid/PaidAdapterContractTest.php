<?php

use PHPUnit\Framework\TestCase;
use JsonSchema\Validator;
use JsonSchema\SchemaStorage;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Uri\UriResolver;

/**
 * PAID-01 — Schema validation tests for paid adapter contracts.
 *
 * Validates that the manifest and execute response schemas are well-formed
 * and that canonical golden fixtures conform to them. Also tests structural
 * enforcement of key semantic rules (idempotency_key, partial_success shape).
 */
class PaidAdapterContractTest extends TestCase {

    private const CONTRACTS_DIR = __DIR__ . '/../../../../../../../docs/contracts';
    private const FIXTURES_DIR  = __DIR__ . '/../fixtures/golden';

    private Validator $validator;

    protected function setUp(): void {
        $this->validator = new Validator();
    }

    // -------------------------------------------------------------------------
    // Manifest schema — valid cases
    // -------------------------------------------------------------------------

    public function test_valid_manifest_passes_schema(): void {
        $manifest = $this->minimal_manifest();
        $errors   = $this->validate_against_schema( $manifest, 'paid_adapter_manifest.json' );
        $this->assertEmpty( $errors, 'Valid manifest should pass: ' . implode( '; ', $errors ) );
    }

    public function test_golden_manifest_fixture_passes_schema(): void {
        $fixture = json_decode( (string) file_get_contents( self::FIXTURES_DIR . '/paid_adapter_dry_run_manifest.json' ) );
        $errors  = $this->validate_against_schema( $fixture, 'paid_adapter_manifest.json' );
        $this->assertEmpty( $errors, 'Golden manifest fixture should pass schema: ' . implode( '; ', $errors ) );
    }

    // -------------------------------------------------------------------------
    // Manifest schema — invalid cases
    // -------------------------------------------------------------------------

    public function test_manifest_missing_idempotency_key_fails_schema(): void {
        $manifest = $this->minimal_manifest();
        unset( $manifest->meta->idempotency_key );
        $errors = $this->validate_against_schema( $manifest, 'paid_adapter_manifest.json' );
        $this->assertNotEmpty( $errors, 'Missing idempotency_key should fail schema validation' );
    }

    public function test_manifest_invalid_channel_enum_fails_schema(): void {
        $manifest = $this->minimal_manifest();
        $manifest->operations[0]->channel = 'tiktok'; // not in enum
        $errors = $this->validate_against_schema( $manifest, 'paid_adapter_manifest.json' );
        $this->assertNotEmpty( $errors, 'Invalid channel value should fail schema validation' );
    }

    public function test_manifest_missing_operations_fails_schema(): void {
        $manifest             = $this->minimal_manifest();
        $manifest->operations = [];
        $errors               = $this->validate_against_schema( $manifest, 'paid_adapter_manifest.json' );
        $this->assertNotEmpty( $errors, 'Empty operations array (minItems:1 violated) should fail' );
    }

    // -------------------------------------------------------------------------
    // Execute response schema — valid cases
    // -------------------------------------------------------------------------

    public function test_valid_execute_response_passes_schema(): void {
        $response = $this->success_execute_response();
        $errors   = $this->validate_against_schema( $response, 'paid_adapter_execute.json' );
        $this->assertEmpty( $errors, 'Valid execute response should pass: ' . implode( '; ', $errors ) );
    }

    public function test_golden_execute_response_fixture_passes_schema(): void {
        $fixture = json_decode( (string) file_get_contents( self::FIXTURES_DIR . '/paid_adapter_execute_response.json' ) );
        $errors  = $this->validate_against_schema( $fixture, 'paid_adapter_execute.json' );
        $this->assertEmpty( $errors, 'Golden execute response fixture should pass schema: ' . implode( '; ', $errors ) );
    }

    public function test_partial_success_response_passes_schema(): void {
        $response = (object) [
            'manifest_id'         => 'man_20260303_002',
            'status'              => 'partial_success',
            'operation_results'   => [
                (object) [
                    'operation_id'            => 'op_1',
                    'operation_id_on_channel' => 'g_op_99001',
                    'result'                  => 'created',
                    'actual_spend'            => 75.00,
                    'currency'                => 'AUD',
                    'error'                   => null,
                ],
                (object) [
                    'operation_id'            => 'op_2',
                    'operation_id_on_channel' => '',
                    'result'                  => 'failed',
                    'actual_spend'            => 0,
                    'currency'                => 'AUD',
                    'error'                   => (object) [
                        'code'      => 'BUDGET_EXCEEDED',
                        'message'   => 'Daily budget cap reached.',
                        'retryable' => true,
                    ],
                ],
            ],
            'total_actual_spend'  => 75.00,
            'currency'            => 'AUD',
            'errors'              => null,
            'timestamp'           => '2026-03-03T12:10:00Z',
        ];
        $errors = $this->validate_against_schema( $response, 'paid_adapter_execute.json' );
        $this->assertEmpty( $errors, 'partial_success response should pass schema: ' . implode( '; ', $errors ) );
    }

    // -------------------------------------------------------------------------
    // Execute response schema — invalid cases
    // -------------------------------------------------------------------------

    public function test_execute_response_missing_status_fails_schema(): void {
        $response = $this->success_execute_response();
        unset( $response->status );
        $errors = $this->validate_against_schema( $response, 'paid_adapter_execute.json' );
        $this->assertNotEmpty( $errors, 'Missing status should fail schema validation' );
    }

    public function test_execute_response_invalid_status_enum_fails_schema(): void {
        $response         = $this->success_execute_response();
        $response->status = 'unknown';
        $errors           = $this->validate_against_schema( $response, 'paid_adapter_execute.json' );
        $this->assertNotEmpty( $errors, 'Invalid status enum value should fail schema validation' );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function minimal_manifest(): object {
        return (object) [
            'manifest_id' => 'man_test_001',
            'campaign'    => (object) [
                'campaign_id' => 'camp_test',
                'title'       => 'Test Campaign',
            ],
            'operations'  => [
                (object) [
                    'operation_id' => 'op_1',
                    'type'         => 'CREATE_CAMPAIGN',
                    'channel'      => 'linkedin',
                    'targeting'    => (object) [ 'geo' => ['AU'] ],
                    'creative'     => (object) [
                        'headline' => 'Test Headline',
                        'body'     => 'Test body copy.',
                    ],
                    'bid'          => (object) [
                        'type'     => 'CPM',
                        'amount'   => 10,
                        'currency' => 'AUD',
                    ],
                    'start_time'   => '2026-04-01T10:00:00Z',
                    'end_time'     => '2026-04-07T10:00:00Z',
                ],
            ],
            'meta' => (object) [
                'sponsor_id'       => 'sp_test',
                'schedule_id'      => 'sch_test',
                'idempotency_key'  => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            ],
        ];
    }

    private function success_execute_response(): object {
        return (object) [
            'manifest_id'        => 'man_test_001',
            'status'             => 'success',
            'operation_results'  => [
                (object) [
                    'operation_id'            => 'op_1',
                    'operation_id_on_channel' => 'li_op_12345',
                    'result'                  => 'created',
                    'actual_spend'            => 118.40,
                    'currency'                => 'AUD',
                    'error'                   => null,
                ],
            ],
            'total_actual_spend' => 118.40,
            'currency'           => 'AUD',
            'errors'             => null,
            'timestamp'          => '2026-03-03T12:05:00Z',
        ];
    }

    /**
     * Validate $data against a schema file in docs/contracts/.
     *
     * @return string[] List of error messages (empty on success).
     */
    private function validate_against_schema( object $data, string $schema_filename ): array {
        $schema_path = self::CONTRACTS_DIR . '/' . $schema_filename;
        $this->assertFileExists( $schema_path, "Schema file {$schema_filename} not found in docs/contracts/" );

        $schema = json_decode( (string) file_get_contents( $schema_path ) );
        $this->assertNotNull( $schema, "Schema file {$schema_filename} is not valid JSON" );

        $this->validator->validate( $data, $schema );

        if ( $this->validator->isValid() ) {
            return [];
        }

        $messages = [];
        foreach ( $this->validator->getErrors() as $error ) {
            $messages[] = sprintf( '[%s] %s', $error['property'], $error['message'] );
        }

        // Reset validator state for the next call
        $this->validator = new Validator();

        return $messages;
    }
}
