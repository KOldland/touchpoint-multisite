<?php
namespace KH\Attribution\Providers;

use KH\Attribution\Database\AttributionSchema;
use KH\Attribution\Api\AttributionEndpoints;
use KH\Attribution\Assets\AttributionAssets;
use KH\Attribution\Services\Attribution\AttributionStorage;
use KH\Attribution\Services\Attribution\CommissionCalculator;
use KHM_Attribution_Performance_Manager;
use KHM_Attribution_Async_Manager;
use KHM_Attribution_Query_Builder;
use KH\Attribution\Services\Attribution\KHM_Advanced_Attribution_Manager;

/**
 * Bootstrap Provider
 * 
 * Orchestrates the initialization of the Attribution module.
 */
class Bootstrap {
    public static function init() {
        // 1. Ensure DB Schema exists
        AttributionSchema::ensure_tables_exist();

        // 2. Initialize Infrastructure / Services
        $performance = new \KHM_Attribution_Performance_Manager();
        $async       = new \KHM_Attribution_Async_Manager();
        $query       = new \KHM_Attribution_Query_Builder();
        $storage     = new AttributionStorage(30); // 30-day window
        $calculator  = new CommissionCalculator();

        // 3. Initialize Manager (The Orchestrator)
        // Injecting all 5 required dependencies
        $manager = new KHM_Advanced_Attribution_Manager($performance, $async, $query, $storage, $calculator);

        // 4. Register API Endpoints
        $endpoints = new AttributionEndpoints($manager);
        add_action('rest_api_init', [$endpoints, 'register']);

        // 5. Register Assets
        $assets = new AttributionAssets($manager);
        $assets->register();
    }
}