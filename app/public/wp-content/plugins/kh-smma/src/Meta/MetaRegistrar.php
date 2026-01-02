<?php
namespace KH_SMMA\Meta;

use function add_action;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers structured meta for KH SMMA custom post types.
 */
class MetaRegistrar {
    /**
     * Entry point.
     */
    public function register() {
        add_action( 'init', array( $this, 'register_account_meta' ) );
        add_action( 'init', array( $this, 'register_campaign_meta' ) );
        add_action( 'init', array( $this, 'register_schedule_meta' ) );
    }

    public function register_account_meta() {
        $fields = array(
            '_kh_smma_provider'         => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            '_kh_smma_auth_method'      => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            '_kh_smma_credentials'      => array( 'type' => 'object', 'single' => true ),
            '_kh_smma_status'           => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'disconnected' ),
            '_kh_smma_membership_types' => array( 'type' => 'array', 'single' => true ),
            '_kh_smma_last_synced'      => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_sandbox_mode'     => array( 'type' => 'boolean', 'single' => true, 'default' => false ),
            '_kh_smma_require_approval' => array( 'type' => 'boolean', 'single' => true, 'default' => false ),
        );

        $this->register_meta_group( 'kh_smma_account', $fields );
    }

    public function register_campaign_meta() {
        $fields = array(
            '_kh_smma_campaign_objective' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            '_kh_smma_target_accounts'    => array( 'type' => 'array', 'single' => true ),
            '_kh_smma_utm_params'         => array( 'type' => 'array', 'single' => true ),
            '_kh_smma_budget_cap'         => array( 'type' => 'number', 'single' => true ),
            '_kh_smma_start_at'           => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_end_at'             => array( 'type' => 'integer', 'single' => true ),
        );

        $this->register_meta_group( 'kh_smma_campaign', $fields );
    }

    public function register_schedule_meta() {
        $fields = array(
            '_kh_smma_schedule_status'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'pending' ),
            '_kh_smma_campaign_id'       => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_account_id'        => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_payload'           => array( 'type' => 'array', 'single' => true ),
            '_kh_smma_media_assets'      => array( 'type' => 'array', 'single' => true ),
            '_kh_smma_scheduled_at'      => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_delivery_mode'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'auto' ),
            '_kh_smma_last_error'        => array( 'type' => 'string', 'single' => true ),
            '_kh_smma_result_metrics'    => array( 'type' => 'array', 'single' => true ),
            '_kh_smma_processed_at'      => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_last_telemetry'    => array( 'type' => 'array', 'single' => true ),
            '_kh_smma_approval_status'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'pending' ),
            '_kh_smma_approved_by'       => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_approved_at'       => array( 'type' => 'integer', 'single' => true ),
            '_kh_smma_approval_note'     => array( 'type' => 'string', 'single' => true ),
        );

        $this->register_meta_group( 'kh_smma_schedule', $fields );
    }

    private function register_meta_group( $post_type, $fields ) {
        foreach ( $fields as $key => $args ) {
            $defaults = array(
                'object_subtype'    => $post_type,
                'single'            => isset( $args['single'] ) ? $args['single'] : true,
                'type'              => isset( $args['type'] ) ? $args['type'] : 'string',
                'auth_callback'     => '__return_true',
                'sanitize_callback' => isset( $args['sanitize_callback'] ) ? $args['sanitize_callback'] : null,
                'show_in_rest'      => false,
            );

            \register_post_meta( $post_type, $key, \wp_parse_args( $args, $defaults ) );
        }
    }
}
