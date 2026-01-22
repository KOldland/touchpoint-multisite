<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/src/Sponsors/SponsorController.php';

class SponsorSelectionTest extends TestCase {
    public function test_rank_docs_by_topic_prefers_matching_titles() {
        $controller = new \KHM\Sponsors\SponsorController();
        $method = new \ReflectionMethod( $controller, 'rank_docs_by_topic' );
        $method->setAccessible( true );

        $docs = array(
            array( 'title' => 'Last-mile delivery insights', 'authors' => 'A', 'publisher' => 'P', 'pub_date' => '2024-01-01' ),
            array( 'title' => 'Unrelated manufacturing report', 'authors' => 'B', 'publisher' => 'P', 'pub_date' => '2024-01-01' ),
            array( 'title' => 'Cost implications of last-mile logistics', 'authors' => 'C', 'publisher' => 'P', 'pub_date' => '2024-01-01' ),
        );

        $ranked = $method->invoke( $controller, $docs, 'last-mile delivery' );

        $this->assertGreaterThanOrEqual( $ranked[1]['score'], $ranked[0]['score'] );
        $this->assertGreaterThanOrEqual( $ranked[2]['score'], $ranked[1]['score'] );
    }

    public function test_selection_filters_to_top_three() {
        $controller = new \KHM\Sponsors\SponsorController();
        $method = new \ReflectionMethod( $controller, 'rank_docs_by_topic' );
        $method->setAccessible( true );

        $docs = array(
            array( 'title' => 'Last-mile delivery A', 'authors' => 'A', 'publisher' => 'P', 'pub_date' => '2024-01-01' ),
            array( 'title' => 'Last-mile delivery B', 'authors' => 'B', 'publisher' => 'P', 'pub_date' => '2024-01-01' ),
            array( 'title' => 'Last-mile delivery C', 'authors' => 'C', 'publisher' => 'P', 'pub_date' => '2024-01-01' ),
            array( 'title' => 'Last-mile delivery D', 'authors' => 'D', 'publisher' => 'P', 'pub_date' => '2024-01-01' ),
        );

        $ranked = $method->invoke( $controller, $docs, 'last-mile delivery' );
        $filtered = array_filter( $ranked, function( $doc ) {
            return ( $doc['score'] ?? 0 ) >= 0.35;
        } );
        $selected = array_slice( array_values( $filtered ), 0, 3 );

        $this->assertCount( 3, $selected );
    }
}
