<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/src/GEO/Entity/WikidataResolver.php';

class WikidataResolverTest extends TestCase {
    public function test_rank_candidates_prefers_exact_label_match() {
        $resolver = new \KHM_SEO\GEO\Entity\WikidataResolver();
        $candidates = array(
            array(
                'qid' => 'Q1',
                'label' => 'Example',
                'description' => 'Something else',
                'aliases' => array( 'Sample' ),
                'score_base' => 0.2,
                'term' => 'Example',
                'instance_of' => array( 'Q43229' ),
                'sitelink_count' => 30,
            ),
            array(
                'qid' => 'Q2',
                'label' => 'Example Corp',
                'description' => 'Example company',
                'aliases' => array( 'Example' ),
                'score_base' => 0.2,
                'term' => 'Example',
                'instance_of' => array(),
                'sitelink_count' => 5,
            ),
        );

        $ranked = $resolver->rankCandidates( $candidates, 'company' );

        $this->assertSame( 'Q1', $ranked[0]['qid'] );
        $this->assertGreaterThan( $ranked[1]['score'], $ranked[0]['score'] );
    }
}
