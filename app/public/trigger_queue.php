<?php
define('ABSPATH', __DIR__ . '/');
require_once 'wp-load.php';

echo 'Manually triggering kh_smma_process_queue...' . PHP_EOL;
do_action('kh_smma_process_queue');
echo 'Queue processing triggered.' . PHP_EOL;
