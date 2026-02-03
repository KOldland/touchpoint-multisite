<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Services/FeatureFlags.php';

class SmmaSmokeTest extends TestCase {
    public function test_feature_flags_default_state() {
        $flags = new \KH_SMMA\Services\FeatureFlags();
        $defaults = $flags->get_defaults();

        $this->assertArrayHasKey( 'smma', $defaults );
        $this->assertArrayHasKey( 'smma_paid_adapters', $defaults );
    }
}
