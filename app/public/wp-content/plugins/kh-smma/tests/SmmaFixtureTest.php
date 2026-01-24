<?php

use PHPUnit\Framework\TestCase;

class SmmaFixtureTest extends TestCase {
    public function test_fixture_schema_is_valid() {
        $fixture_path = __DIR__ . '/fixtures/smma_variant_fixture.json';
        $contents = file_get_contents( $fixture_path );
        $variant = json_decode( $contents, true );

        $required = array(
            'variant_id',
            'channel',
            'text',
            'phase_tag',
            'tone',
            'recommended_post_time_gmt',
            'time_window',
            'geo_recommendations',
            'asset_hints',
            'sponsor_flag',
            'sponsor_mode',
            'sponsor_asset',
            'compliance_notes',
            'explainability',
            'audit',
        );

        foreach ( $required as $key ) {
            $this->assertArrayHasKey( $key, $variant );
        }

        $this->assertIsArray( $variant['geo_recommendations'] );
        $this->assertIsArray( $variant['asset_hints'] );
        $this->assertIsArray( $variant['audit'] );
    }
}
