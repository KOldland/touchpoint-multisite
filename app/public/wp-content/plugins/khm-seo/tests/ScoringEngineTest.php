<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type = 'mysql' ) {
        return '2026-01-01 00:00:00';
    }
}

require_once dirname( __DIR__ ) . '/src/GEO/Scoring/ScoringEngine.php';

class ScoringEngineTest extends TestCase {
    public function test_calculate_score_includes_confidence_reasons() {
        $engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();

        $settings = array(
            'question'  => 'What is GEO?',
            'answer'    => 'GEO is the practice of optimizing content for AI responses.',
            'citations' => array(
                array(
                    'title' => 'Example Study',
                    'url'   => 'https://example.com',
                ),
            ),
            'entities'  => array( 'GEO' ),
            'evidence'  => array(
                'tier'           => 'tier3',
                'confidence'     => 0.4,
                'source_passage' => '',
            ),
        );

        $result = $engine->calculate_score( $settings, array() );

        $this->assertArrayHasKey( 'reasons', $result );
        $codes = array_column( $result['reasons'], 'code' );

        $this->assertContains( 'only_tier3', $codes );
        $this->assertContains( 'no_source_passage', $codes );
        $this->assertContains( 'missing_author', $codes );
        $this->assertContains( 'missing_year', $codes );
        $this->assertContains( 'few_anchor_entities', $codes );
    }

    public function test_score_citations_reflects_tier_weighting() {
        $engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();
        $method = new \ReflectionMethod( $engine, 'score_citations' );
        $method->setAccessible( true );

        $tier1_score = $method->invoke( $engine, array(
            'citations' => array(
                array(
                    'tier' => 'tier1',
                    'author' => 'Author',
                    'year' => '2020',
                    'publisher' => 'Publisher',
                ),
            ),
        ) );

        $tier3_score = $method->invoke( $engine, array(
            'citations' => array(
                array(
                    'tier' => 'tier3',
                ),
            ),
        ) );

        $this->assertGreaterThan( $tier3_score, $tier1_score );
    }

    public function test_get_citation_contributions_returns_indexed_entries() {
        $engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();

        $contributions = $engine->get_citation_contributions( array(
            array( 'tier' => 'tier1' ),
            array( 'tier' => 'tier2' ),
        ) );

        $this->assertCount( 2, $contributions );
        $this->assertSame( 0, $contributions[0]['idx'] );
        $this->assertSame( 1, $contributions[1]['idx'] );
        $this->assertArrayHasKey( 'contribution', $contributions[0] );
    }

    public function test_unresolved_entities_add_reason() {
        $engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();

        $settings = array(
            'question' => 'What is GEO?',
            'answer' => 'Answer text',
            'entities' => array( 'McKinsey', 'GEO', 'OpenAI', 'Anthropic' ),
        );

        $result = $engine->calculate_score( $settings, array() );
        $codes = array_column( $result['reasons'], 'code' );

        $this->assertContains( 'entities_unresolved', $codes );
    }

    public function test_sponsor_boost_applies_when_eligible() {
        $engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();

        $settings = array(
            'question'       => 'What is GEO?',
            'answer'         => 'Answer text.',
            'sponsor_toggle' => true,
            'sponsor_boost'  => 0.05,
            'citations'      => array(
                array(
                    'tier'             => 'tier1',
                    'author'           => 'Author',
                    'year'             => '2024',
                    'publisher'        => 'Publisher',
                    'sponsor_id'       => 1,
                    'sponsor_approved' => true,
                ),
            ),
            'evidence'       => array(
                'confidence' => 0.9,
            ),
        );

        $result = $engine->calculate_score( $settings, array() );
        $this->assertArrayHasKey( 'citation_contributions', $result );
        $this->assertSame( 0.05, $result['citation_contributions'][0]['sponsor_boost'] );
    }

    public function test_sponsor_boost_is_blocked_when_not_approved() {
        $engine = new \KHM_SEO\GEO\Scoring\ScoringEngine();

        $settings = array(
            'question'       => 'What is GEO?',
            'answer'         => 'Answer text.',
            'sponsor_toggle' => true,
            'sponsor_boost'  => 0.05,
            'citations'      => array(
                array(
                    'tier'             => 'tier1',
                    'author'           => 'Author',
                    'year'             => '2024',
                    'publisher'        => 'Publisher',
                    'sponsor_id'       => 1,
                    'sponsor_approved' => false,
                ),
            ),
            'evidence'       => array(
                'confidence' => 0.9,
            ),
        );

        $result = $engine->calculate_score( $settings, array() );
        $this->assertArrayHasKey( 'citation_contributions', $result );
        $this->assertEquals( 0.0, $result['citation_contributions'][0]['sponsor_boost'] );
    }
}
