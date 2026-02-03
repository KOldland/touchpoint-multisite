<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Services/FeatureFlags.php';
require_once dirname( __DIR__ ) . '/src/Security/CredentialVault.php';
require_once dirname( __DIR__ ) . '/src/Services/TokenRepository.php';
require_once dirname( __DIR__ ) . '/src/Adapters/LinkedInAdsAdapter.php';
require_once dirname( __DIR__ ) . '/src/Adapters/GoogleAdsAdapter.php';
require_once dirname( __DIR__ ) . '/src/Services/ScheduleQueueProcessor.php';

class PaidAdaptersTest extends TestCase {
    public function test_linkedin_ads_falls_back_when_disabled() {
        update_option( 'kh_smma_feature_flags', array( 'smma' => true, 'smma_paid_adapters' => false ) );
        $flags = new \KH_SMMA\Services\FeatureFlags();

        $repo = ( new \ReflectionClass( \KH_SMMA\Services\TokenRepository::class ) )->newInstanceWithoutConstructor();
        $adapter = new \KH_SMMA\Adapters\LinkedInAdsAdapter( $repo, $flags );

        $payload = array( 'message' => 'Test message' );
        $context = array( 'provider' => 'linkedin_ads' );

        $result = $adapter->handle_dispatch( null, 101, $payload, $context );
        $this->assertSame( 'awaiting_manual_export', $result );
        $this->assertIsArray( get_post_meta( 101, '_kh_smma_export_bundle', true ) );
    }

    public function test_google_ads_falls_back_when_disabled() {
        update_option( 'kh_smma_feature_flags', array( 'smma' => true, 'smma_paid_adapters' => false ) );
        $flags = new \KH_SMMA\Services\FeatureFlags();

        $repo = ( new \ReflectionClass( \KH_SMMA\Services\TokenRepository::class ) )->newInstanceWithoutConstructor();
        $adapter = new \KH_SMMA\Adapters\GoogleAdsAdapter( $repo, $flags );

        $payload = array( 'message' => 'Test message' );
        $context = array( 'provider' => 'google_ads' );

        $result = $adapter->handle_dispatch( null, 202, $payload, $context );
        $this->assertSame( 'awaiting_manual_export', $result );
        $this->assertIsArray( get_post_meta( 202, '_kh_smma_export_bundle', true ) );
    }
}
