<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// ---------------------------------------------------------------------------
// WordPress function stubs — only defined if not already present (WP not boot)
// ---------------------------------------------------------------------------

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action, $query_arg = false, $die = true ) {
        return 1; // Nonce always passes in tests
    }
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    /**
     * Stub: throw a typed exception so tests can assert the call happened
     * with the expected status code.
     */
    function wp_send_json_error( $data = null, $status_code = null ) {
        throw new KhmSeoTestJsonErrorException(
            is_array( $data ) ? ( $data['message'] ?? '' ) : (string) $data,
            (int) $status_code
        );
    }
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null, $status_code = null ) {
        // no-op in tests
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

/**
 * Controllable current_user_can stub.
 * Set $GLOBALS['__test_khm_can_edit_posts'] before invoking AJAX methods.
 */
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) {
        if ( 'edit_posts' === $capability ) {
            return (bool) ( $GLOBALS['__test_khm_can_edit_posts'] ?? true );
        }
        return false;
    }
}

/**
 * Exception thrown by the wp_send_json_error stub so tests can assert
 * the status code and message without sending a real HTTP response.
 */
class KhmSeoTestJsonErrorException extends \RuntimeException {
    public int $statusCode;

    public function __construct( string $message, int $statusCode ) {
        parent::__construct( $message, $statusCode );
        $this->statusCode = $statusCode;
    }
}

require_once dirname( __DIR__ ) . '/src/GEO/GEOManager.php';

class PluginWiringTest extends TestCase {

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a GEOManager with a no-op entity_manager stub wired in,
     * bypassing the real constructor's DB dependencies.
     */
    private function make_geo_manager(): \KHM_SEO\GEO\GEOManager {
        /** @var \KHM_SEO\GEO\GEOManager $mgr */
        $mgr = $this->getMockBuilder( \KHM_SEO\GEO\GEOManager::class )
                    ->disableOriginalConstructor()
                    ->onlyMethods( array() ) // execute all real methods
                    ->getMock();

        $entity_mock = new class {
            public function get_entity( $id ) {
                return $id ? array( 'id' => $id ) : null;
            }
            public function get_entity_aliases( $id ) { return array(); }
            public function set_entity_aliases( $id, $aliases ) {}
        };

        $ref = new \ReflectionProperty( \KHM_SEO\GEO\GEOManager::class, 'entity_manager' );
        $ref->setAccessible( true );
        $ref->setValue( $mgr, $entity_mock );

        return $mgr;
    }

    // -----------------------------------------------------------------------
    // 1. ajax_remove_alias — unauthorized request must return 403 and halt
    // -----------------------------------------------------------------------

    public function test_ajax_remove_alias_rejects_user_without_edit_posts(): void {
        $GLOBALS['__test_khm_can_edit_posts'] = false;

        $_POST['entity_id'] = '5';
        $_POST['alias']     = 'test-alias';

        $mgr = $this->make_geo_manager();

        $this->expectException( KhmSeoTestJsonErrorException::class );
        $this->expectExceptionCode( 403 );
        $this->expectExceptionMessage( 'Insufficient permissions' );

        $mgr->ajax_remove_alias();
    }

    public function test_ajax_remove_alias_allows_user_with_edit_posts(): void {
        $GLOBALS['__test_khm_can_edit_posts'] = true;

        // Use entity_id=0 to trigger the early "missing entity" path after auth passes
        $_POST['entity_id'] = '0';
        $_POST['alias']     = '';

        $mgr = $this->make_geo_manager();

        try {
            $mgr->ajax_remove_alias();
            $this->assertTrue( true );
        } catch ( KhmSeoTestJsonErrorException $e ) {
            // Auth passed — any error here must NOT be a 403
            $this->assertNotEquals(
                403,
                $e->statusCode,
                'Authorized user must not receive a 403 Insufficient permissions error'
            );
        }
    }

    // -----------------------------------------------------------------------
    // 2. ajax_add_alias — unauthorized request must return 403 and halt
    // -----------------------------------------------------------------------

    public function test_ajax_add_alias_rejects_user_without_edit_posts(): void {
        $GLOBALS['__test_khm_can_edit_posts'] = false;

        $_POST['entity_id'] = '5';
        $_POST['alias']     = 'new-alias';

        $mgr = $this->make_geo_manager();

        $this->expectException( KhmSeoTestJsonErrorException::class );
        $this->expectExceptionCode( 403 );
        $this->expectExceptionMessage( 'Insufficient permissions' );

        $mgr->ajax_add_alias();
    }

    public function test_ajax_add_alias_allows_user_with_edit_posts(): void {
        $GLOBALS['__test_khm_can_edit_posts'] = true;

        // Use entity_id=0 to trigger the early "missing entity" path after auth passes
        $_POST['entity_id'] = '0';
        $_POST['alias']     = '';

        $mgr = $this->make_geo_manager();

        try {
            $mgr->ajax_add_alias();
            $this->assertTrue( true );
        } catch ( KhmSeoTestJsonErrorException $e ) {
            // Auth passed — any error here must NOT be a 403
            $this->assertNotEquals(
                403,
                $e->statusCode,
                'Authorized user must not receive a 403 Insufficient permissions error'
            );
        }
    }

    // -----------------------------------------------------------------------
    // 3. Plugin.php structural check: output_head_tags NOT hooked to wp_head
    // -----------------------------------------------------------------------

    public function test_plugin_init_does_not_register_output_head_tags_on_wp_head(): void {
        $source = file_get_contents( dirname( __DIR__ ) . '/src/Core/Plugin.php' );

        // The wp_head / output_head_tags hook must not appear in Plugin::init()
        // We isolate the init() method body by extracting the text between
        // "function init()" and the next "public function" declaration.
        if ( preg_match( '/function init\(\)(.*?)^\s{4}public\s+function\s/ms', $source, $m ) ) {
            $init_body = $m[1];
            $this->assertStringNotContainsString(
                "add_action( 'wp_head'",
                $init_body,
                'Plugin::init() must not register output_head_tags on wp_head — output is handled by individual managers to avoid duplicate output.'
            );
        } else {
            $this->fail( 'Could not locate Plugin::init() body in Plugin.php' );
        }
    }

    // -----------------------------------------------------------------------
    // 4. Plugin.php structural check: elementor hook not duplicated in init()
    // -----------------------------------------------------------------------

    public function test_plugin_init_does_not_duplicate_elementor_hook(): void {
        $source = file_get_contents( dirname( __DIR__ ) . '/src/Core/Plugin.php' );

        if ( preg_match( '/function init\(\)(.*?)^\s{4}public\s+function\s/ms', $source, $m ) ) {
            $init_body = $m[1];
            $this->assertStringNotContainsString(
                "add_action( 'elementor/widgets/register'",
                $init_body,
                'Plugin::init() must not register the Elementor widget hook — it belongs in init_components() to avoid double registration.'
            );
        } else {
            $this->fail( 'Could not locate Plugin::init() body in Plugin.php' );
        }
    }

    // -----------------------------------------------------------------------
    // Teardown: clean global test flags
    // -----------------------------------------------------------------------

    protected function tearDown(): void {
        unset( $GLOBALS['__test_khm_can_edit_posts'] );
        unset( $_POST['entity_id'], $_POST['alias'] );
        parent::tearDown();
    }
}
