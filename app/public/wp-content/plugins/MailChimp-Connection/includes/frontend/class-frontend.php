<?php

defined('ABSPATH') or exit;

/**
 * Frontend bootstrap for TouchPoint MailChimp.
 *
 * This is a lightweight placeholder to avoid missing-class fatals.
 * Extend as needed if frontend form handling is added later.
 */
class TouchPoint_MailChimp_Frontend {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Placeholder: enqueue hooks or frontend logic here when available.
    }
}
