<?php
declare(strict_types=1);

/**
 * @return array<int,array<string,mixed>>
 */
function khm_test_get_email_queue_rows(): array {
    global $wpdb;
    $table = $wpdb->prefix . 'khm_email_queue';
    $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
    return is_array($rows) ? $rows : [];
}

function khm_test_email_queue_count(): int {
    return count(khm_test_get_email_queue_rows());
}

function khm_test_seed_email_queue_tables(): void {
    global $wpdb;
    $wpdb->query("CREATE TABLE {$wpdb->prefix}khm_email_logs (id bigint unsigned)");
    $wpdb->query("CREATE TABLE {$wpdb->prefix}khm_email_queue (id bigint unsigned)");
}

function khm_test_clear_email_queue_tables(): void {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}khm_email_logs");
    $wpdb->query("DELETE FROM {$wpdb->prefix}khm_email_queue");
}
