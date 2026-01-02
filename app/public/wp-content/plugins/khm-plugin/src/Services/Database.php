<?php
namespace KHM\Services;

class Database {

    // Simple DB helper - in WP environment we'd use global $wpdb
    public static function now() {
        return gmdate('Y-m-d H:i:s');
    }

    // Placeholder: implement actual DB interactions or use WP's $wpdb
}
