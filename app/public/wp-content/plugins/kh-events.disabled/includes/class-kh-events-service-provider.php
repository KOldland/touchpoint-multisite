<?php
/**
 * Base Service Provider Class for KH Events
 *
 * Provides common functionality for all service providers
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class KH_Events_Service_Provider implements KH_Events_Service_Provider_Interface {

    /**
     * The container instance
     */
    protected $container;

    /**
     * Constructor
     */
    public function __construct($container = null) {
        $this->container = $container ?: KH_Events_Container::instance();
    }

    /**
     * Register the service provider
     */
    abstract public function register();

    /**
     * Boot the service provider
     */
    public function boot() {
        // Default implementation - can be overridden
    }

    /**
     * Get a service from the container
     */
    protected function get($service) {
        return $this->container->get($service);
    }

    /**
     * Bind a service to the container
     */
    protected function bind($abstract, $concrete = null, $shared = false) {
        return $this->container->bind($abstract, $concrete, $shared);
    }

    /**
     * Check if a service is bound
     */
    protected function bound($abstract) {
        return $this->container->bound($abstract);
    }
}