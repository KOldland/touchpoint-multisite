<?php

namespace KH\EditorialAuthor\Core;

use KH\EditorialAuthor\API\AuthorEndpoints;

class AuthorPlugin {
    private static $instance = null;
    private $orchestrator = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    public function get_orchestrator() {
        if (null === $this->orchestrator) {
            $this->orchestrator = new \KH\EditorialAuthor\Agents\AuthorOrchestrator();
        }
        return $this->orchestrator;
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'get_orchestrator']);
        add_action('rest_api_init', [$this, 'register_endpoints']);
        add_action('admin_menu', [$this, 'init_admin']);
    }

    public function init_admin() {
        if (is_admin() && class_exists('\KH\EditorialAuthor\Admin\AuthorWorkspace')) {
            $workspace = new \KH\EditorialAuthor\Admin\AuthorWorkspace();
            $workspace->init();
        }
    }

    public function register_endpoints() {
        $endpoints = new AuthorEndpoints();
        $endpoints->register_routes();
    }
}
