<?php

namespace KH\EditorialAuthor\Core;

use KH\EditorialAuthor\API\AuthorEndpoints;

class AuthorPlugin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }

    public function register_endpoints() {
        $endpoints = new AuthorEndpoints();
        $endpoints->register_routes();
    }
}
