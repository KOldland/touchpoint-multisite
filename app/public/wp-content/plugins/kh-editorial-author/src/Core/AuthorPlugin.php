<?php

namespace KH\EditorialAuthor\Core;

use KH\EditorialAuthor\API\AuthorEndpoints;

class AuthorPlugin {
    private static $instance = null;
    private $orchestrator = null;
    private $author_sync = null;

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

    /**
     * Get the AuthorSyncProvider instance.
     */
    public function get_author_sync() {
        if (null === $this->author_sync) {
            $this->author_sync = new \KH\EditorialAuthor\Integration\AuthorSyncProvider();
        }
        return $this->author_sync;
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'get_orchestrator']);
                add_action('rest_api_init', [$this, 'register_endpoints']);
        add_action('admin_menu', [$this, 'init_admin']);

        // Register blocks
        require_once dirname(__DIR__) . '/Blocks/answer-card/answer-card.php';
        if (function_exists('\KH\EditorialAuthor\Blocks\AnswerCard\register_answercard_block')) {
            add_action('init', '\KH\EditorialAuthor\Blocks\AnswerCard\register_answercard_block');
        }
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
