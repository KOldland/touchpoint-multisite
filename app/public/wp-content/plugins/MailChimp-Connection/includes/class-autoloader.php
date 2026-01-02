<?php

defined('ABSPATH') or exit;

/**
 * TouchPoint MailChimp Autoloader
 * 
 * PSR-4 compliant autoloader for TouchPoint MailChimp classes
 */
class TouchPoint_MailChimp_Autoloader {
    
    /**
     * The namespace prefix
     */
    private static $prefix = 'TouchPoint_MailChimp_';
    
    /**
     * Base directory for the namespace prefix
     */
    private static $base_dir = '';
    
    /**
     * Register the autoloader
     */
    public static function register() {
        self::$base_dir = TMC_PLUGIN_DIR . 'includes/';
        spl_autoload_register(array(__CLASS__, 'load_class'));
    }
    
    /**
     * Load a class file
     *
     * @param string $class The fully-qualified class name
     * @return mixed The mapped file name on success, or boolean false on failure
     */
    public static function load_class($class) {
        // Does the class use the namespace prefix?
        $len = strlen(self::$prefix);
        if (strncmp(self::$prefix, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, $len);
        
        // Replace namespace separators with directory separators
        // and convert to lowercase with hyphens
        $relative_class = strtolower(str_replace('_', '-', $relative_class));
        
        // Build the file path
        $file = self::$base_dir . 'class-' . $relative_class . '.php';
        
        // Check for file in includes directory
        if (file_exists($file)) {
            require $file;
            return $file;
        }
        
        // Check in admin directory
        $admin_file = self::$base_dir . 'admin/class-' . $relative_class . '.php';
        if (file_exists($admin_file)) {
            require $admin_file;
            return $admin_file;
        }
        
        // Check in frontend directory
        $frontend_file = self::$base_dir . 'frontend/class-' . $relative_class . '.php';
        if (file_exists($frontend_file)) {
            require $frontend_file;
            return $frontend_file;
        }
        
        // Check in integrations directory
        $integration_file = self::$base_dir . 'integrations/class-' . $relative_class . '.php';
        if (file_exists($integration_file)) {
            require $integration_file;
            return $integration_file;
        }
        
        return false;
    }
}