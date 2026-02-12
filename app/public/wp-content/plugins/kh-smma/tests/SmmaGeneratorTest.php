<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/Services/SmmaGenerator.php';
require_once dirname( __DIR__ ) . '/src/Services/GoogleAdDraftService.php';

class SmmaGeneratorTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        // Clear filters between tests
        $GLOBALS['kh_test_filters'] = array();
    }

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

    public function test_generate_includes_google_ad_draft_field() {
        $generator = new \KH_SMMA\Services\SmmaGenerator();

        $result = $generator->generate( array(
            'post_id' => 123,
            'phase_tag' => 'Attention',
            'tone' => 'Authority',
            'num_variants' => 2,
            'geo_targets' => array( 'GB' ),
        ) );

        $this->assertArrayHasKey( 'google_ad_draft', $result );
    }

    public function test_google_ad_draft_is_empty_array_by_default() {
        $generator = new \KH_SMMA\Services\SmmaGenerator();

        $result = $generator->generate( array(
            'post_id' => 123,
            'phase_tag' => 'Attention',
            'tone' => 'Authority',
            'num_variants' => 2,
            'geo_targets' => array( 'GB' ),
        ) );

        $this->assertIsArray( $result['google_ad_draft'] );
        $this->assertEmpty( $result['google_ad_draft'] );
    }

    public function test_generate_google_ads_flag_can_disable_google_ad_generation() {
        $generator = new \KH_SMMA\Services\SmmaGenerator();

        $result = $generator->generate( array(
            'post_id' => 123,
            'phase_tag' => 'Attention',
            'tone' => 'Authority',
            'num_variants' => 2,
            'geo_targets' => array( 'GB' ),
            'generate_google_ads' => false,
        ) );

        $this->assertArrayHasKey( 'google_ad_draft', $result );
        $this->assertIsArray( $result['google_ad_draft'] );
        $this->assertEmpty( $result['google_ad_draft'] );
    }

    public function test_kh_smma_google_ad_draft_filter_is_invoked() {
        $filter_invoked = false;
        $filter_input = null;

        add_filter( 'kh_smma_google_ad_draft', function( $draft, $input ) use ( &$filter_invoked, &$filter_input ) {
            $filter_invoked = true;
            $filter_input = $input;
            return array(
                'headline' => 'Test Headline',
                'description' => 'Test Description',
            );
        }, 10, 2 );

        $generator = new \KH_SMMA\Services\SmmaGenerator();

        $result = $generator->generate( array(
            'post_id' => 123,
            'phase_tag' => 'Attention',
            'tone' => 'Authority',
            'num_variants' => 2,
            'geo_targets' => array( 'GB' ),
        ) );

        $this->assertTrue( $filter_invoked, 'kh_smma_google_ad_draft filter was not invoked' );
        $this->assertNotNull( $filter_input );
        $this->assertSame( 123, $filter_input['post_id'] );
        $this->assertArrayHasKey( 'google_ad_draft', $result );
        $this->assertSame( 'Test Headline', $result['google_ad_draft']['headline'] );
        $this->assertSame( 'Test Description', $result['google_ad_draft']['description'] );
    }

    public function test_google_ad_draft_filter_returns_empty_array_on_non_array_response() {
        add_filter( 'kh_smma_google_ad_draft', function() {
            return 'invalid-non-array-response';
        } );

        $generator = new \KH_SMMA\Services\SmmaGenerator();

        $result = $generator->generate( array(
            'post_id' => 123,
            'phase_tag' => 'Attention',
            'tone' => 'Authority',
            'num_variants' => 2,
            'geo_targets' => array( 'GB' ),
        ) );

        $this->assertArrayHasKey( 'google_ad_draft', $result );
        $this->assertIsArray( $result['google_ad_draft'] );
        $this->assertEmpty( $result['google_ad_draft'] );
    }

    public function test_google_ad_draft_filter_not_invoked_when_generate_google_ads_is_false() {
        $filter_invoked = false;

        add_filter( 'kh_smma_google_ad_draft', function( $draft, $input ) use ( &$filter_invoked ) {
            $filter_invoked = true;
            return $draft;
        }, 10, 2 );

        $generator = new \KH_SMMA\Services\SmmaGenerator();

        $result = $generator->generate( array(
            'post_id' => 123,
            'phase_tag' => 'Attention',
            'tone' => 'Authority',
            'num_variants' => 2,
            'geo_targets' => array( 'GB' ),
            'generate_google_ads' => false,
        ) );

        $this->assertFalse( $filter_invoked, 'kh_smma_google_ad_draft filter should not be invoked when generate_google_ads is false' );
        $this->assertArrayHasKey( 'google_ad_draft', $result );
        $this->assertEmpty( $result['google_ad_draft'] );
    }
}
