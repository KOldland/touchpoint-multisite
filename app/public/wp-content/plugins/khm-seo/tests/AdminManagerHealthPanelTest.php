<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/**
 * Structural test to keep dependency-health panel wiring stable.
 *
 * This intentionally validates source-level signals rather than full WordPress
 * render output, which keeps the test deterministic in a no-WP bootstrap.
 */
class AdminManagerHealthPanelTest extends TestCase {

    public function test_boost_visibility_page_contains_dependency_health_guard_logic(): void {
        $source = file_get_contents( dirname( __DIR__ ) . '/src/Admin/AdminManager.php' );

        $this->assertStringContainsString(
            "class_exists( 'KH_SMMA\\Services\\SmmaGenerator' )",
            $source,
            'Boost Visibility should check KH SMMA dependency state.'
        );

        $this->assertStringContainsString(
            "class_exists( 'KHM_SEO_AGENT\\API\\Rest_Api' )",
            $source,
            'Boost Visibility should check SEO Agent dependency state.'
        );

        $this->assertStringContainsString(
            "function_exists( 'kh_ad_manager_get_sponsor_meta' )",
            $source,
            'Boost Visibility should check Ad Manager dependency state.'
        );

        $this->assertStringContainsString(
            "notice notice-warning inline",
            $source,
            'Boost Visibility should render warning notice styling when deps are missing.'
        );

        $this->assertStringContainsString(
            "Plugin bundle status",
            $source,
            'Boost Visibility should display a plugin bundle status heading.'
        );
    }
}
