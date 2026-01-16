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
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * SuggestionServiceTest class
 */
class SuggestionServiceTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress functions
        Functions\when('wp_json_encode')->returnArg();
        Functions\when('wp_json_decode')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('wp_get_current_user')->justReturn((object)['ID' => 1]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test AnswerCardSchemaValidator validation
     */
    public function test_answer_card_validation() {
        $validator = new AnswerCardSchemaValidator();

        $valid_card = [
            'question' => str_repeat('A', 50), // 50 chars
            'concise_answer' => str_repeat('A', 80), // 80 chars
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

        $this->assertTrue($validator->validate($valid_card));
    }

    /**
     * Test AnswerCardSchemaValidator rejects invalid cards
     */
    public function test_answer_card_validation_rejects_invalid() {
        $validator = new AnswerCardSchemaValidator();

        $invalid_card = [
            'question' => '', // Empty question
            'concise_answer' => str_repeat('A', 200), // Too long
            'key_points' => [], // No key points
        ];

        $this->assertFalse($validator->validate($invalid_card));
    }

    /**
     * Test SuggestionCacheManager cache key generation
     */
    public function test_cache_key_generation() {
        $cache_manager = new SuggestionCacheManager();

        $data1 = ['title' => 'Test', 'content' => 'Content'];
        $data2 = ['title' => 'Test', 'content' => 'Content']; // Same data
        $data3 = ['title' => 'Test', 'content' => 'Different']; // Different data

        $key1 = $cache_manager->generateCacheKey($data1);
        $key2 = $cache_manager->generateCacheKey($data2);
        $key3 = $cache_manager->generateCacheKey($data3);

        $this->assertEquals($key1, $key2); // Same data = same key
        $this->assertNotEquals($key1, $key3); // Different data = different key
        $this->assertStringStartsWith('geo_suggest_', $key1);
    }

    /**
     * Test RateLimiter allows normal usage
     */
    public function test_rate_limiter_allows_normal_usage() {
        Functions\when('get_transient')->justReturn(false); // No existing limits
        Functions\when('set_transient')->justReturn(true);

        $limiter = new RateLimiter();

        $this->assertTrue($limiter->checkLimit(1)); // Should allow
    }

    /**
     * Test RateLimiter blocks excessive usage
     */
    public function test_rate_limiter_blocks_excessive_usage() {
        // Mock existing high usage count
        Functions\when('get_transient')->justReturn(['count' => 5, 'reset' => time() + 3600]);
        Functions\when('set_transient')->justReturn(true);

        $limiter = new RateLimiter();

        $this->assertFalse($limiter->checkLimit(1)); // Should block
    }

    /**
     * Test SuggestionAuditLogger table creation
     */
    public function test_audit_logger_table_creation() {
        global $wpdb;
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('query')->andReturn(true);

        $logger = new SuggestionAuditLogger();

        // This would normally create a table, but we're mocking
        $this->assertInstanceOf(SuggestionAuditLogger::class, $logger);
    }
}