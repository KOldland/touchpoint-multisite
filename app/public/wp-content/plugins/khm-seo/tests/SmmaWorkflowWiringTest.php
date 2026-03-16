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

    public function test_seo_agent_editor_panel_wiring_is_present(): void {
        $admin_source = file_get_contents( dirname( __DIR__ ) . '/src/Admin/AdminManager.php' );
        $js_source = file_get_contents( dirname( __DIR__ ) . '/assets/js/admin.js' );
        $agent_source = file_get_contents( dirname( __DIR__, 2 ) . '/khm-seo-agent/src/API/Rest_Api.php' );
        $seo_tools_source = file_get_contents( dirname( __DIR__, 2 ) . '/dual-gpt-wordpress-plugin/includes/tools/class-seo-tools.php' );

        $this->assertStringContainsString(
            "id=\"khm-seo-run-agent-btn\"",
            $admin_source,
            'SEO meta box should render a Run SEO Agent button for editor workflow.'
        );

        $this->assertStringContainsString(
            "'seoAgent' => array(",
            $admin_source,
            'AdminManager should localize SEO Agent runtime config.'
        );

        $this->assertStringContainsString(
            'initSeoAgentMetaBox();',
            $js_source,
            'SEO admin JavaScript should initialize the SEO Agent meta box workflow.'
        );

        $this->assertStringContainsString(
            "seoAgentRequest('audit'",
            $js_source,
            'SEO admin JavaScript should call the SEO Agent audit endpoint.'
        );

        $this->assertStringContainsString(
            'set_schema_config',
            $agent_source,
            'SEO Agent prompt and fallback logic should support schema config actions.'
        );

        $this->assertStringContainsString(
            'build_deterministic_payload',
            $agent_source,
            'SEO Agent should synthesize deterministic fallback actions when the model returns no apply actions.'
        );

        $this->assertStringContainsString(
            "case 'set_schema_config':",
            $seo_tools_source,
            'Dual-GPT SEO tools should be able to apply schema configuration actions.'
        );
    }
}