<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Services/FeatureFlags.php';
require_once dirname( __DIR__ ) . '/src/Services/SmmaGenerator.php';
require_once dirname( __DIR__ ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__ ) . '/src/API/RestController.php';
require_once dirname( __DIR__ ) . '/src/Services/ScheduleQueueProcessor.php';

class SmmaRestSmokeTest extends TestCase {
    public function test_generate_and_schedule_flow() {
        update_option( 'kh_smma_feature_flags', array( 'smma' => true, 'smma_paid_adapters' => false ) );

        $flags = new \KH_SMMA\Services\FeatureFlags();
        $generator = new \KH_SMMA\Services\SmmaGenerator();
        $logger = new \KH_SMMA\Services\AuditLogger( new \wpdb() );

        $controller = new \KH_SMMA\API\RestController( $flags, $generator, $logger );

        $request = new \WP_REST_Request(
            array(
                'post_id' => 123,
                'num_variants' => 1,
                'phase_tag' => 'Attention',
                'tone' => 'Authority',
                'geo_targets' => array( 'GB' ),
            ),
            array( 'X-WP-Nonce' => 'nonce' )
        );

        $response = $controller->handle_generate( $request );
        $this->assertArrayHasKey( 'variants', $response );
        $this->assertCount( 1, $response['variants'] );

        $schedule_request = new \WP_REST_Request(
            array(
                'post_id' => 123,
                'selected_variants' => array( 'v-test' ),
                'schedule' => array(
                    array( 'variant_id' => 'v-test', 'scheduled_at' => time(), 'geo' => 'GB' ),
                ),
                'boost' => false,
            ),
            array( 'X-WP-Nonce' => 'nonce' )
        );

        $schedule_response = $controller->handle_schedule( $schedule_request );
        $this->assertArrayHasKey( 'created', $schedule_response );
        $this->assertNotEmpty( $schedule_response['created'] );
    }
}
