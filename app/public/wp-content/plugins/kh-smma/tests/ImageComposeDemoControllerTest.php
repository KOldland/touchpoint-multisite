<?php

use KH_SMMA\API\RestController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestHelpers.php';
require_once dirname( __DIR__ ) . '/src/API/RestController.php';

class ImageComposeDemoControllerTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['kh_test_post_meta'] = array();
        $GLOBALS['kh_test_options'] = array();
    }

    private function controller(): RestController {
        $reflection = new ReflectionClass( RestController::class );
        return $reflection->newInstanceWithoutConstructor();
    }

    public function test_demo_compose_round_trip_uses_option_store(): void {
        $controller = $this->controller();

        $save_request = new WP_REST_Request( array(
            'reference_id'      => 'phase3-demo-post-1',
            'layout_id'         => 'layout_grid_2x2',
            'preview_url'       => 'https://example.test/uploads/smma/previews/compose_001.jpg',
            'composed_image_id' => 'cmp_001',
            'mapping'           => array(
                array(
                    'image_id'   => 'img_hero_001',
                    'slot_index' => 0,
                ),
            ),
        ) );

        $saved = $controller->handle_demo_compose_post( $save_request );

        $this->assertSame( 'ok', $saved['status'] );
        $this->assertSame( 'layout_grid_2x2', $saved['layout_id'] );
        $this->assertCount( 1, $saved['mapping'] );

        $load_request = new WP_REST_Request( array(
            'reference_id' => 'phase3-demo-post-1',
        ) );

        $loaded = $controller->handle_demo_compose_get( $load_request );

        $this->assertSame( 'ok', $loaded['status'] );
        $this->assertSame( 'https://example.test/uploads/smma/previews/compose_001.jpg', $loaded['preview_url'] );
        $this->assertSame( 'img_hero_001', $loaded['mapping'][0]['image_id'] );
    }
}
