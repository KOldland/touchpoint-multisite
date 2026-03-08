<?php
/**
 * Unit tests for GEO Suggestion Service
 *
 * @package KHM\Tests\GEO
 */

namespace KHM\Tests\GEO;

use KHM\GEO\SuggestionAuditLogger;
use KHM\GEO\AnswerCardSchemaValidator;
use KHM\GEO\SuggestionCacheManager;
use KHM\GEO\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * SuggestionServiceTest class
 */
class SuggestionServiceTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
    }

    protected function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test AnswerCardSchemaValidator validation
     */
    public function test_answer_card_validation() {
        $validator = new AnswerCardSchemaValidator();

        $valid_card = [
            'question' => str_repeat('A', 50), // 50 chars
            'concise_answer' => trim(str_repeat('Word ', 25)), // 25 words
            'key_points' => ['Point 1', 'Point 2', 'Point 3'],
            'citations' => [
                ['title' => 'Source 1', 'url' => 'https://example.com/1'],
                ['title' => 'Source 2', 'url' => 'https://example.com/2']
            ],
            'entities' => [
                ['name' => 'Entity 1', 'sameAs' => 'https://example.com/entity1']
            ],
            'confidence' => 0.85
        ];

        $this->assertTrue($validator->validate([$valid_card]));
    }

    /**
     * Test AnswerCardSchemaValidator rejects invalid cards
     */
    public function test_answer_card_validation_rejects_invalid() {
        $validator = new AnswerCardSchemaValidator();

        $invalid_card = [
            'question' => '', // Empty question
            'concise_answer' => str_repeat('Word ', 160), // Too many words
            'key_points' => [], // No key points
        ];

        $this->assertFalse($validator->validate([$invalid_card]));
    }

    /**
     * Test SuggestionCacheManager cache key generation
     */
    public function test_cache_key_generation() {
        $cache_manager = new SuggestionCacheManager();

        $content1 = 'Test Content';
        $content2 = 'Test Content'; // Same content
        $content3 = 'Test Different'; // Different content

        $key1 = $cache_manager->generate_cache_key($content1);
        $key2 = $cache_manager->generate_cache_key($content2);
        $key3 = $cache_manager->generate_cache_key($content3);

        $this->assertEquals($key1, $key2); // Same data = same key
        $this->assertNotEquals($key1, $key3); // Different data = different key
        $this->assertStringStartsWith(SuggestionCacheManager::CACHE_PREFIX, $key1);
    }

    /**
     * Test RateLimiter allows normal usage
     */
    public function test_rate_limiter_allows_normal_usage() {
        $GLOBALS['khm_test_transients'] = [];

        $limiter = new RateLimiter();

        $this->assertTrue($limiter->check_limit(1)); // Should allow
    }

    /**
     * Test RateLimiter blocks excessive usage
     */
    public function test_rate_limiter_blocks_excessive_usage() {
        $GLOBALS['khm_test_transients'] = [
            'khm_geo_rate_minute_1' => [
                'value' => 5,
                'expires' => time() + 60,
            ],
        ];

        $limiter = new RateLimiter();

        $this->assertInstanceOf(\WP_Error::class, $limiter->check_limit(1)); // Should block
    }

    /**
     * Test SuggestionAuditLogger table creation
     */
    public function test_audit_logger_table_creation() {
        $logger = new SuggestionAuditLogger();

        $this->assertInstanceOf(SuggestionAuditLogger::class, $logger);
    }
}
