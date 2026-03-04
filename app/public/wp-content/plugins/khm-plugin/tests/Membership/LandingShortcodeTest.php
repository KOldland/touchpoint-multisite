<?php

namespace KHM\Tests\Membership;

use KHM\Membership\LandingPageShortcode;
use PHPUnit\Framework\TestCase;

class LandingShortcodeTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $_GET = [];
    }

    protected function tearDown(): void {
        $_GET = [];
        global $khm_test_filters;
        $khm_test_filters = [];
        parent::tearDown();
    }

    public function test_renders_schedule_and_sponsor_branding_with_sanitized_blurb(): void {
        add_filter( 'khm_membership_landing_schedule_data', function ( $schedule ) {
            $schedule['title'] = 'Schedule title';
            $schedule['recommended_post_time'] = '2026-03-10T14:00:00Z';
            $schedule['boost_copy'] = 'Boost this post copy.';
            return $schedule;
        } );

        add_filter( 'khm_membership_landing_sponsor_data', function ( $sponsor ) {
            $sponsor['name'] = 'Sponsor Name';
            $sponsor['logo_url'] = 'https://example.com/logo.png';
            $sponsor['accent_color'] = '#123456';
            $sponsor['blurb'] = '<p>Safe blurb</p><script>alert(1)</script>';
            return $sponsor;
        } );

        $_GET['utm_source'] = 'newsletter';

        $shortcode = new LandingPageShortcode();
        $html = $shortcode->render_shortcode([
            'schedule_id' => 'sch_123',
            'sponsor_id' => 'sp_456',
        ]);

        $this->assertStringContainsString( 'Schedule title', $html );
        $this->assertStringContainsString( 'https://example.com/logo.png', $html );
        $this->assertStringContainsString( 'Safe blurb', $html );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( 'Referred by Sponsor Name', $html );
    }
}
