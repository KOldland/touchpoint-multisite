<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter() {}
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta() {
        return array();
    }
}

require_once dirname( __DIR__, 2 ) . '/src/Blocks/answer-card/answer-card.php';

class SponsorSchemaGateTest extends TestCase {
    public function test_sponsor_not_exposed_without_approval() {
        $card = array(
            'expose_in_schema' => true,
            'sponsor_toggle' => true,
            'sponsor_requires_approval' => true,
            'sponsor_approved' => false,
            'sponsor_justification' => '',
            'evidence' => array( 'confidence' => 0.9 ),
        );

        $this->assertFalse( \KHM\Blocks\AnswerCard\can_expose_sponsor_in_schema( $card ) );
    }

    public function test_sponsor_exposed_with_approval_and_justification() {
        $card = array(
            'expose_in_schema' => true,
            'sponsor_toggle' => true,
            'sponsor_requires_approval' => true,
            'sponsor_approved' => true,
            'sponsor_justification' => 'Reviewed',
            'evidence' => array( 'confidence' => 0.5 ),
        );

        $this->assertTrue( \KHM\Blocks\AnswerCard\can_expose_sponsor_in_schema( $card ) );
    }
}
