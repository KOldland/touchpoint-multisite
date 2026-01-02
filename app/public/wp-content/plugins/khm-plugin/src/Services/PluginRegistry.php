<?php

namespace KHM\Services;

/**
 * Plugin Registry for Touchpoint Marketing Suite
 *
 * Central hub for managing integration between KHM Membership
 * and other marketing suite plugins (Social Strip, Ad Server, Affiliate, etc.)
 */
class PluginRegistry {

    /**
     * Registered plugins
     *
     * @var array
     */
    private static array $plugins = [];

    /**
     * Available services that KHM provides to other plugins
     *
     * @var array
     */
    private static array $services = [];

    /**
     * Register a marketing suite plugin with KHM
     *
     * @param string $plugin_slug Unique plugin identifier
     * @param array $config Plugin configuration
     * @return bool
     */
    public static function register_plugin(string $plugin_slug, array $config): bool {
        // Validate required config
        $required_fields = ['name', 'version', 'capabilities'];
        foreach ($required_fields as $field) {
            if (!isset($config[$field])) {
                error_log("KHM Plugin Registry: Missing required field '{$field}' for plugin '{$plugin_slug}'");
                return false;
            }
        }

        // Store plugin configuration
        self::$plugins[$plugin_slug] = array_merge([
            'slug' => $plugin_slug,
            'registered_at' => current_time('mysql'),
            'status' => 'active'
        ], $config);

        // Fire registration hook
        do_action('khm_plugin_registered', $plugin_slug, $config);
        do_action("khm_plugin_registered_{$plugin_slug}", $config);

        error_log("KHM Plugin Registry: Successfully registered plugin '{$plugin_slug}'");
        return true;
    }

    /**
     * Unregister a plugin
     *
     * @param string $plugin_slug
     */
    public static function unregister_plugin(string $plugin_slug): void {
        if (isset(self::$plugins[$plugin_slug])) {
            unset(self::$plugins[$plugin_slug]);
            do_action('khm_plugin_unregistered', $plugin_slug);
        }
    }

    /**
     * Check if a plugin is registered
     *
     * @param string $plugin_slug
     * @return bool
     */
    public static function is_plugin_registered(string $plugin_slug): bool {
        return isset(self::$plugins[$plugin_slug]);
    }

    /**
     * Get registered plugin info
     *
     * @param string $plugin_slug
     * @return array|null
     */
    public static function get_plugin(string $plugin_slug): ?array {
        return self::$plugins[$plugin_slug] ?? null;
    }

    /**
     * Get all registered plugins
     *
     * @return array
     */
    public static function get_all_plugins(): array {
        return self::$plugins;
    }

    /**
     * Register a service that KHM provides
     *
     * @param string $service_name
     * @param callable $callback
     */
    public static function register_service(string $service_name, callable $callback): void {
        self::$services[$service_name] = $callback;
        do_action('khm_service_registered', $service_name);
    }

    /**
     * Call a KHM service from other plugins
     *
     * @param string $service_name
     * @param mixed ...$args
     * @return mixed
     */
    public static function call_service(string $service_name, ...$args) {
        if (!isset(self::$services[$service_name])) {
            throw new \InvalidArgumentException("Service '{$service_name}' not found");
        }

        return call_user_func(self::$services[$service_name], ...$args);
    }

    /**
     * Get available services
     *
     * @return array
     */
    public static function get_available_services(): array {
        return array_keys(self::$services);
    }

    /**
     * Check if plugins have specific capability
     *
     * @param string $capability
     * @return array Plugins that have this capability
     */
    public static function get_plugins_with_capability(string $capability): array {
        $plugins = [];
        foreach (self::$plugins as $slug => $config) {
            if (in_array($capability, $config['capabilities'] ?? [], true)) {
                $plugins[] = $slug;
            }
        }
        return $plugins;
    }

    /**
     * Send data to all plugins with specific capability
     *
     * @param string $capability
     * @param string $method
     * @param mixed $data
     */
    public static function broadcast_to_capability(string $capability, string $method, $data): void {
        $plugins = self::get_plugins_with_capability($capability);
        
        foreach ($plugins as $plugin_slug) {
            $hook_name = "khm_broadcast_{$plugin_slug}_{$method}";
            do_action($hook_name, $data);
        }
    }
}