<?php

namespace KH\Dashboards\Api;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API endpoints for dashboard summary stats.
 * Phase 1 — placeholder. Endpoints will be added in Phase 2.
 */
class StatsController {

    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Endpoints will be registered here in Phase 2
    }
}