<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
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
            'entities' => array( 'McKinsey', 'GEO' ),
        );

        $result = $engine->calculate_score( $settings, array() );
        $codes = array_column( $result['reasons'], 'code' );

        $this->assertContains( 'entities_unresolved', $codes );
    }
}
