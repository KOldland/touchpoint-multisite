<?php
/**
 * KH Events Service Provider Functions
 *
 * Helper functions for registering and managing service providers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register a service provider with the container
 *
 * @param string|KH_Events_Service_Provider_Interface $provider
 */
function kh_events_register_provider($provider) {
    $container = KH_Events_Container::instance();
    $container->register($provider);
}

/**
 * Get a service from the container
 *
 * @param string $service
 * @return mixed
 */
function kh_events_get_service($service) {
    $container = KH_Events_Container::instance();
    return $container->get($service);
}

/**
 * Bind a service to the container
 *
 * @param string $abstract
 * @param mixed $concrete
 * @param bool $shared
 */
function kh_events_bind_service($abstract, $concrete = null, $shared = false) {
    $container = KH_Events_Container::instance();
    $container->bind($abstract, $concrete, $shared);
}

/**
 * Check if a service is bound in the container
 *
 * @param string $abstract
 * @return bool
 */
function kh_events_service_bound($abstract) {
    $container = KH_Events_Container::instance();
    return $container->bound($abstract);
}