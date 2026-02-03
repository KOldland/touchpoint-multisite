<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Security/CredentialVault.php';
require_once dirname( __DIR__ ) . '/src/Services/TokenRepository.php';
require_once dirname( __DIR__ ) . '/src/Services/AuditLogger.php';
require_once dirname( __DIR__ ) . '/src/Services/ScheduleQueueProcessor.php';
require_once dirname( __DIR__ ) . '/src/Adapters/ManualExportAdapter.php';

class SmmaQueueSmokeTest extends TestCase {
    public function test_queue_processor_dispatches_manual_export() {
        $GLOBALS['kh_test_wp_query_posts'] = array( 55 );

        update_post_meta( 55, '_kh_smma_schedule_status', 'pending' );
        update_post_meta( 55, '_kh_smma_scheduled_at', time() );
        update_post_meta( 55, '_kh_smma_payload', array( 'message' => 'Smoke test message' ) );
        update_post_meta( 55, '_kh_smma_delivery_mode', 'manual_export' );
        update_post_meta( 55, '_kh_smma_account_id', 0 );
        update_post_meta( 55, '_kh_smma_campaign_id', 0 );

        $vault = new \KH_SMMA\Security\CredentialVault( 'test-key' );
        $tokens = new \KH_SMMA\Services\TokenRepository( new \wpdb(), $vault );
        $logger = new \KH_SMMA\Services\AuditLogger( new \wpdb() );

        $adapter = new \KH_SMMA\Adapters\ManualExportAdapter();
        $adapter->register();

        $processor = new \KH_SMMA\Services\ScheduleQueueProcessor( $tokens, $logger );
        $processor->process_due_schedules();

        $status = get_post_meta( 55, '_kh_smma_schedule_status', true );
        $bundle = get_post_meta( 55, '_kh_smma_export_bundle', true );

        $this->assertSame( 'awaiting_manual_export', $status );
        $this->assertIsArray( $bundle );
        $this->assertSame( 55, $bundle['schedule_id'] );
    }
}
