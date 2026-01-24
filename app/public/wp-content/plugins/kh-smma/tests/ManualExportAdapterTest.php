<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Adapters/ManualExportAdapter.php';
require_once dirname( __DIR__ ) . '/src/Services/ScheduleQueueProcessor.php';

class ManualExportAdapterTest extends TestCase {
    public function test_manual_export_bundle_is_created() {
        $adapter = new \KH_SMMA\Adapters\ManualExportAdapter();

        $payload = array( 'message' => 'Test message' );
        $context = array( 'provider' => 'manual', 'delivery' => 'manual_export' );

        $result = $adapter->handle_manual_queue( null, 42, $payload, $context );

        $this->assertSame( 'awaiting_manual_export', $result );
        $bundle = get_post_meta( 42, '_kh_smma_export_bundle', true );
        $this->assertIsArray( $bundle );
        $this->assertSame( 42, $bundle['schedule_id'] );
        $this->assertSame( $payload, $bundle['payload'] );
    }
}
