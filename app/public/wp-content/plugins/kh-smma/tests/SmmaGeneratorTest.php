<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Services/SmmaGenerator.php';

class SmmaGeneratorTest extends TestCase {
    public function test_fallback_variants_have_required_fields() {
        $generator = new \KH_SMMA\Services\SmmaGenerator();

        $result = $generator->generate( array(
            'post_id' => 123,
            'phase_tag' => 'Attention',
            'tone' => 'Authority',
            'num_variants' => 2,
            'geo_targets' => array( 'GB' ),
        ) );

        $this->assertArrayHasKey( 'variants', $result );
        $this->assertCount( 2, $result['variants'] );

        $variant = $result['variants'][0];
        $this->assertArrayHasKey( 'variant_id', $variant );
        $this->assertArrayHasKey( 'channel', $variant );
        $this->assertArrayHasKey( 'text', $variant );
        $this->assertArrayHasKey( 'phase_tag', $variant );
        $this->assertArrayHasKey( 'tone', $variant );
        $this->assertArrayHasKey( 'audit', $variant );
        $this->assertArrayHasKey( 'compliance_notes', $variant );
        $this->assertSame( 'linkedin', $variant['channel'] );
        $this->assertNotEmpty( $variant['text'] );
    }
}
