<?php
/**
 * KH Events Service Provider Interface
 *
 * Based on TEC's service provider pattern for better modularity
 */

if (!defined('ABSPATH')) {
    exit;
}

interface KH_Events_Service_Provider_Interface {
    /**
     * Register the service provider
     */
    public function register();

    /**
     * Boot the service provider (called after all providers are registered)
     */
    public function boot();
}