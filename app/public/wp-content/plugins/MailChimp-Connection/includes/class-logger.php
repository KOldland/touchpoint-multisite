<?php

defined('ABSPATH') or exit;

/**
 * TouchPoint MailChimp Logger
 * 
 * Handles logging for debugging and monitoring
 */
class TouchPoint_MailChimp_Logger {
    
    private static $instance = null;
    private $enabled = false;
    private $log_file = '';
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->enabled = get_option('tmc_debug_mode', false);
        $this->log_file = WP_CONTENT_DIR . '/touchpoint-mailchimp.log';
    }
    
    /**
     * Log a message
     */
    public static function log($message, $level = 'info') {
        $instance = self::instance();
        
        if (!$instance->enabled) {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted_message = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);
        
        // Write to file
        if (is_writable(dirname($instance->log_file))) {
            file_put_contents($instance->log_file, $formatted_message, FILE_APPEND | LOCK_EX);
        }
        
        // Also use WordPress error_log if available
        if (function_exists('error_log')) {
            error_log('TouchPoint MailChimp: ' . $message);
        }
    }
    
    /**
     * Log info message
     */
    public static function info($message) {
        self::log($message, 'info');
    }
    
    /**
     * Log warning message
     */
    public static function warning($message) {
        self::log($message, 'warning');
    }
    
    /**
     * Log error message
     */
    public static function error($message) {
        self::log($message, 'error');
    }
    
    /**
     * Log debug message
     */
    public static function debug($message) {
        self::log($message, 'debug');
    }
    
    /**
     * Get log contents
     */
    public function get_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $logs = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($logs === false) {
            return array();
        }
        
        // Return last X lines
        return array_slice($logs, -$lines);
    }
    
    /**
     * Clear log file
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }
    
    /**
     * Get log file size
     */
    public function get_log_size() {
        if (file_exists($this->log_file)) {
            return filesize($this->log_file);
        }
        return 0;
    }
    
    /**
     * Check if logging is enabled
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Enable logging
     */
    public function enable() {
        $this->enabled = true;
        update_option('tmc_debug_mode', true);
    }
    
    /**
     * Disable logging
     */
    public function disable() {
        $this->enabled = false;
        update_option('tmc_debug_mode', false);
    }
}