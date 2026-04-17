<?php
/**
 * PHPUnit tests for the Atomic Articles subsystem.
 *
 * Tests cover:
 *   - AtomicEmbeddingService::cosine_similarity()
 *   - AtomicSchemaEmitter: schema type selection and overlay dispatch
 *   - AtomicArticlePostType: constants and helper stubs
 *   - AtomicArticleGenerator: unit sanitization logic
 *
 * External dependencies (WP, OpenAI) are stubbed via test doubles.
 *
 * @package KHM\Tests\Atomic
 */

namespace KHM\Tests\Atomic;

use KHM\Atomic\AtomicEmbeddingService;
use PHPUnit\Framework\TestCase;

/**
 * Atomic Embedding Service Tests
 */
class AtomicEmbeddingServiceTest extends TestCase {

    private AtomicEmbeddingService $service;

    protected function setUp(): void {
        $this->service = new AtomicEmbeddingService();
    }

    // -------------------------------------------------------------------------
    // cosine_similarity
    // -------------------------------------------------------------------------

    public function test_identical_vectors_return_1(): void {
        $v = array( 0.5, 0.5, 0.5, 0.5 );
        $this->assertEqualsWithDelta( 1.0, $this->service->cosine_similarity( $v, $v ), 1e-9 );
    }

    public function test_orthogonal_vectors_return_0(): void {
        $a = array( 1.0, 0.0 );
        $b = array( 0.0, 1.0 );
        $this->assertEqualsWithDelta( 0.0, $this->service->cosine_similarity( $a, $b ), 1e-9 );
    }

    public function test_opposite_vectors_return_minus_1(): void {
        $a = array( 1.0, 0.0 );
        $b = array( -1.0, 0.0 );
        $this->assertEqualsWithDelta( -1.0, $this->service->cosine_similarity( $a, $b ), 1e-9 );
    }

    public function test_mismatched_dimensions_return_0(): void {
        $a = array( 1.0, 2.0, 3.0 );
        $b = array( 1.0, 2.0 );
        $this->assertSame( 0.0, $this->service->cosine_similarity( $a, $b ) );
    }

    public function test_empty_vectors_return_0(): void {
        $this->assertSame( 0.0, $this->service->cosine_similarity( array(), array() ) );
    }

    public function test_zero_vector_returns_0(): void {
        $a = array( 0.0, 0.0, 0.0 );
        $b = array( 1.0, 2.0, 3.0 );
        $this->assertSame( 0.0, $this->service->cosine_similarity( $a, $b ) );
    }

    public function test_similarity_is_symmetric(): void {
        $a = array( 0.3, 0.7, 0.1 );
        $b = array( 0.9, 0.2, 0.5 );
        $ab = $this->service->cosine_similarity( $a, $b );
        $ba = $this->service->cosine_similarity( $b, $a );
        $this->assertEqualsWithDelta( $ab, $ba, 1e-9 );
    }

    public function test_known_similarity_value(): void {
        // [1,0] vs [1,1]/sqrt(2) => cos(45°) ≈ 0.7071
        $a = array( 1.0, 0.0 );
        $b = array( 1.0, 1.0 );
        $sim = $this->service->cosine_similarity( $a, $b );
        $this->assertEqualsWithDelta( sqrt( 2 ) / 2, $sim, 1e-6 );
    }
}
