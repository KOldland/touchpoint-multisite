<?php
namespace KH\Attribution\Api;

/**
 * AttributionEndpoints
 * 
 * Handles the registration of all REST API routes for the Attribution engine.
 */
class AttributionEndpoints {
    private $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function register() {
        // 1. Click tracking endpoint
        register_rest_route('kh-attribution/v1', '/track/click', array(
            'methods' => 'POST',
            'callback' => array($this->manager, 'handle_click_tracking'),
            'permission_callback' => '__return_true',
            'args' => array(
                'affiliate_id' => array('required' => true, 'type' => 'integer'),
                'url'          => array('required' => true, 'type' => 'string'),
                'referrer'     => array('required' => false, 'type' => 'string'),
                'utm_source'   => array('required' => false, 'type' => 'string'),
                'utm_medium'   => array('required' => false, 'type' => 'string'),
                'utm_campaign' => array('required' => false, 'type' => 'string'),
                'client_data'  => array('required' => false, 'type' => 'object')
            )
        ));

        // 2. Conversion tracking endpoint
        register_rest_route('kh-attribution/v1', '/track/conversion', array(
            'methods' => 'POST',
            'callback' => array($this->manager, 'handle_conversion_tracking'),
            'permission_callback' => '__return_true',
            'args' => array(
                'conversion_id'    => array('required' => true, 'type' => 'string'),
                'value'            => array('required' => true, 'type' => 'number'),
                'currency'         => array('required' => false, 'type' => 'string', 'default' => 'USD'),
                'attribution_data' => array('required' => false, 'type' => 'object')
            )
        ));

        // 3. Attribution lookup endpoint
        register_rest_route('kh-attribution/v1', '/attribution/lookup', array(
            'methods' => 'GET',
            'callback' => array($this->manager, 'handle_attribution_lookup'),
            'permission_callback' => array($this->manager, 'check_admin_permissions'),
            'args' => array(
                'conversion_id' => array('required' => true, 'type' => 'string'),
                'explain'       => array('required' => false, 'type' => 'boolean', 'default' => false)
            )
        ));
    }
}