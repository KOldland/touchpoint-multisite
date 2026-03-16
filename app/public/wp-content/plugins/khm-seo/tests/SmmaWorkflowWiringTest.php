<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

class SmmaWorkflowWiringTest extends TestCase {

    public function test_auto_linker_initializes_pattern_link_state(): void {
        $source = file_get_contents( dirname( __DIR__ ) . '/src/GEO/AutoLink/AutoLinker.php' );

        $this->assertStringContainsString(
            "'linked' => false",
            $source,
            'AutoLinker patterns should initialise linked state to avoid undefined array key warnings.'
        );
    }

    public function test_admin_manager_localizes_smma_rest_configuration(): void {
        $source = file_get_contents( dirname( __DIR__ ) . '/src/Admin/AdminManager.php' );

        $this->assertStringContainsString(
            "'smma'     => array(",
            $source,
            'AdminManager should expose SMMA configuration to the SEO admin script.'
        );

        $this->assertStringContainsString(
            "'rest_url' => esc_url_raw( rest_url( 'kh-smma/v1/' ) )",
            $source,
            'AdminManager should provide the SMMA REST base URL.'
        );

        $this->assertStringContainsString(
            "'rest_nonce' => wp_create_nonce( 'wp_rest' )",
            $source,
            'AdminManager should provide a REST nonce for SMMA actions.'
        );
    }

    public function test_admin_js_initializes_smma_workflow_handlers(): void {
        $source = file_get_contents( dirname( __DIR__ ) . '/assets/js/admin.js' );

        $this->assertStringContainsString(
            'initSmmaWorkflow();',
            $source,
            'SEO admin JavaScript should initialize SMMA workflow handlers.'
        );

        $this->assertStringContainsString(
            "$(document).on('click', '.khm-smma-promote-btn', handlePromoteClick);",
            $source,
            'SEO admin JavaScript should wire Promote actions.'
        );

        $this->assertStringContainsString(
            "$(document).on('click', '.khm-smma-approve-btn', handleApproveClick);",
            $source,
            'SEO admin JavaScript should wire approval actions.'
        );
    }
}