<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// ---------------------------------------------------------------------------
// Minimal WordPress stubs required to load kh-smma/SmmaGenerator.php
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public function __construct( $code = '', $message = '' ) {}
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter() {}
}
if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $post_id, $key, $single = false ) {
        return $GLOBALS['__test_post_meta'][ $post_id ][ $key ] ?? ( $single ? '' : array() );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}
if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = array() ) {
        return new WP_Error( 'not_available', 'HTTP requests disabled in tests' );
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) { return 0; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) { return ''; }
}
if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( $path = '' ) { return 'http://example.com/wp-json/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( $action = '' ) { return 'test-nonce'; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ) { return json_encode( $data ); }
}

// ---------------------------------------------------------------------------
// Load SmmaGenerator (path relative to khm-seo/tests/)
// ---------------------------------------------------------------------------

$smma_file = dirname( __DIR__, 2 ) . '/kh-smma/src/Services/SmmaGenerator.php';
if ( file_exists( $smma_file ) ) {
    require_once $smma_file;
}

// ---------------------------------------------------------------------------
// Test suite
// ---------------------------------------------------------------------------

class CrossPluginIntegrationTest extends TestCase {

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Instantiate SmmaGenerator without triggering constructor side-effects.
     */
    private function make_generator(): \KH_SMMA\Services\SmmaGenerator {
        return $this->getMockBuilder( \KH_SMMA\Services\SmmaGenerator::class )
                    ->disableOriginalConstructor()
                    ->getMock();
    }

    /**
     * Invoke a private method on an object via reflection.
     */
    private function invoke_private( object $obj, string $method, array $args = [] ): mixed {
        $ref = new \ReflectionMethod( $obj, $method );
        $ref->setAccessible( true );
        return $ref->invoke( $obj, ...$args );
    }

    // -----------------------------------------------------------------------
    // 1. get_sponsor_context_for_geo returns [] when khm_seo() is undefined
    // -----------------------------------------------------------------------

    public function test_sponsor_context_returns_empty_when_khm_seo_unavailable(): void {
        // Ensure khm_seo() is NOT defined (it is not in this test environment)
        $this->assertFalse(
            function_exists( 'khm_seo' ),
            'khm_seo() should not exist in the test environment — precondition for this test'
        );

        if ( ! class_exists( \KH_SMMA\Services\SmmaGenerator::class ) ) {
            $this->markTestSkipped( 'kh-smma/SmmaGenerator not found — skipping cross-plugin test' );
        }

        $gen = $this->make_generator();

        $result = $this->invoke_private( $gen, 'get_sponsor_context_for_geo', array( 1, array( 'AU' ) ) );

        $this->assertSame( array(), $result, 'Expected empty array when khm_seo() is unavailable' );
    }

    // -----------------------------------------------------------------------
    // 2. get_sponsor_context_for_geo returns [] when getSponsorPolicyForPost
    //    returns no policy (no active sponsor)
    // -----------------------------------------------------------------------

    public function test_sponsor_context_returns_empty_when_no_policy_found(): void {
        if ( ! class_exists( \KH_SMMA\Services\SmmaGenerator::class ) ) {
            $this->markTestSkipped( 'kh-smma/SmmaGenerator not found — skipping cross-plugin test' );
        }

        // Define khm_seo() returning a fake plugin object whose geo manager
        // returns null for every policy lookup.
        if ( ! function_exists( 'khm_seo' ) ) {
            $geo_manager = new class {
                public function getSponsorPolicyForPost( int $post_id, string $geo ): ?array {
                    return null; // No sponsor for this content
                }
            };
            $plugin_stub = new class( $geo_manager ) {
                private $gm;
                public function __construct( $gm ) { $this->gm = $gm; }
                public function get_geo_manager() { return $this->gm; }
            };
            function khm_seo() {
                global $__test_khm_seo_stub;
                return $__test_khm_seo_stub;
            }
            $GLOBALS['__test_khm_seo_stub'] = $plugin_stub;
        }

        $gen = $this->make_generator();
        $result = $this->invoke_private( $gen, 'get_sponsor_context_for_geo', array( 42, array( 'US', 'AU' ) ) );

        $this->assertSame( array(), $result, 'Expected empty array when no sponsor policy exists for post' );
    }

    // -----------------------------------------------------------------------
    // 3. Without kh_ad_manager_get_sponsor_meta, sponsor context still returns
    //    core fields (sponsor_id, policy) without fatalling
    // -----------------------------------------------------------------------

    public function test_sponsor_context_returns_core_fields_when_ad_manager_absent(): void {
        if ( ! class_exists( \KH_SMMA\Services\SmmaGenerator::class ) ) {
            $this->markTestSkipped( 'kh-smma/SmmaGenerator not found — skipping cross-plugin test' );
        }

        $this->assertFalse(
            function_exists( 'kh_ad_manager_get_sponsor_meta' ),
            'kh_ad_manager_get_sponsor_meta should not be defined in test env — precondition'
        );

        // Build a fresh plugin stub with a GEO manager that returns a policy
        $geo_manager_with_policy = new class {
            public function getSponsorPolicyForPost( int $post_id, string $geo ): ?array {
                return array( 'sponsor_id' => 7, 'policy' => 'co-brand' );
            }
        };
        $plugin_stub = new class( $geo_manager_with_policy ) {
            private $gm;
            public function __construct( $gm ) { $this->gm = $gm; }
            public function get_geo_manager() { return $this->gm; }
        };
        $GLOBALS['__test_khm_seo_stub'] = $plugin_stub;

        // Ensure khm_seo() function exists (defined in test 2; safe to skip if not)
        if ( ! function_exists( 'khm_seo' ) ) {
            $this->markTestSkipped( 'khm_seo() function not available — run the full test suite' );
        }

        $gen = $this->make_generator();
        $result = $this->invoke_private( $gen, 'get_sponsor_context_for_geo', array( 42, array( 'US' ) ) );

        $this->assertIsArray( $result );
        $this->assertSame( 7, $result['sponsor_id'] );
        $this->assertSame( 'co-brand', $result['policy'] );
        // Ad-manager extended fields must simply be absent — not a fatal
        $this->assertArrayNotHasKey( 'allowed_claims', $result );
        $this->assertArrayNotHasKey( 'sponsor_assets', $result );
    }

    // -----------------------------------------------------------------------
    // Teardown
    // -----------------------------------------------------------------------

    protected function tearDown(): void {
        unset( $GLOBALS['__test_post_meta'], $GLOBALS['__test_khm_seo_stub'] );
        parent::tearDown();
    }
}
