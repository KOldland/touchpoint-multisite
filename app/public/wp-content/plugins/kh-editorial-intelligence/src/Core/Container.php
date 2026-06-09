<?php
namespace KH\Editorial\Core;

/**
 * Container
 * 
 * Simple Service Container for Dependency Injection.
 */
class Container {
    private static $instances = [];
    private static $registry = [];

    /**
     * Register a service factory.
     */
    public static function bind($key, callable $factory) {
        self::$registry[$key] = $factory;
    }

    /**
     * Get a service instance (Singleton).
     */
    public static function get($key) {
        if (!isset(self::$instances[$key])) {
            if (!isset(self::$registry[$key])) {
                throw new \Exception("Service not found in container: {$key}");
            }
            self::$instances[$key] = call_user_func(self::$registry[$key]);
        }
        return self::$instances[$key];
    }

    /**
     * Check if a service is bound.
     */
    public static function has($key) {
        return isset(self::$registry[$key]);
    }
    
    /**
     * Reset the container (Clear all instances).
     * Primarily for testing or long-running processes.
     */
    public static function reset() {
        self::$instances = [];
    }

    /**
     * Boot the default services.
     */
    public static function boot() {
        self::bind('MembershipService', function() {
            return new \KH\Editorial\Services\Membership\LegacyMembershipBridge();
        });

        self::bind('CurrencyService', function() {
            return new \KH\Editorial\Services\GEO\CurrencyService();
        });

        self::bind('SocialBridge', function() {
            return new \KH\Editorial\Bridge\SocialBridge(
                self::get('MembershipService'),
                self::get('CurrencyService')
            );
        });

        self::bind('SEOAgent', function() {
            return new \KH\Editorial\Services\AI\SEOAgent();
        });

        self::bind('AIStorage', function() {
            return new \KH\Editorial\Services\AI\AIStorage();
        });

        self::bind('SEOToolkit', function() {
            return (new \KH\Editorial\Providers\Tools\SEOToolkit())
                ->set_storage(self::get('AIStorage'))
                ->set_agent(self::get('SEOAgent'));
        });

        self::bind('RecommendationAgent', function() {
            return new \KH\Editorial\Services\AI\RecommendationAgent();
        });
    }
}
