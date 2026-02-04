<?php
/**
 * Service Provider interface for KH Events.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface KH_Events_Service_Provider_Interface {
    /**
     * Register bindings/services with the container.
     */
    public function register();

    /**
     * Boot any runtime hooks after registration.
     */
    public function boot();
}
