<?php
/**
 * KH Events Dependency Injection Container
 *
 * Simple container for managing dependencies and service providers
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Container {

    private static $instance = null;
    private $bindings = [];
    private $instances = [];
    private $providers = [];

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bind a service to the container
     */
    public function bind($abstract, $concrete = null, $shared = false) {
        if (null === $concrete) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Get a service from the container
     */
    public function get($abstract) {
        // If it's a shared instance and already resolved
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // If it's bound
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];

            // If concrete is a closure, call it
            if ($concrete instanceof Closure) {
                $object = $concrete($this);
            } else {
                if (is_callable([$concrete, 'instance'])) {
                    $object = call_user_func([$concrete, 'instance']);
                } else {
                    $object = new $concrete();
                }
            }

            // If shared, store the instance
            if ($binding['shared']) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        // Try to instantiate directly
        if (class_exists($abstract)) {
            if (is_callable([$abstract, 'instance'])) {
                $object = call_user_func([$abstract, 'instance']);
            } else {
                $object = new $abstract();
            }

            // Store as shared by default for classes
            $this->instances[$abstract] = $object;
            return $object;
        }

        throw new Exception("Service {$abstract} not found in container");
    }

    /**
     * Check if a service is bound
     */
    public function bound($abstract) {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Register a service provider
     */
    public function register($provider) {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        if (!$provider instanceof KH_Events_Service_Provider_Interface) {
            throw new Exception('Provider must implement KH_Events_Service_Provider_Interface');
        }

        $this->providers[] = $provider;
        $provider->register();
    }

    /**
     * Boot all registered service providers
     */
    public function boot() {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    /**
     * Get all registered providers
     */
    public function get_providers() {
        return $this->providers;
    }
}
