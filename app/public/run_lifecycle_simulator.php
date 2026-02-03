<?php
define('ABSPATH', __DIR__ . '/');
require_once 'wp-load.php';

echo 'Running Lifecycle Simulator...' . PHP_EOL;

try {
    $plugin = new \KH_SMMA\Plugin();
    $plugin->register_autoloader();
    $plugin->bootstrap_services();
    
    // Run the lifecycle simulator
    $simulator = $plugin->lifecycle_simulator;
    if ($simulator) {
        $result = $simulator->run();
        echo 'Lifecycle Simulator completed. Result: ' . print_r($result, true) . PHP_EOL;
    } else {
        echo 'Lifecycle Simulator not available.' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error running Lifecycle Simulator: ' . $e->getMessage() . PHP_EOL;
}
