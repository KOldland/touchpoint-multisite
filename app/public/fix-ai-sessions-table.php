<?php
/**
 * Database Migration: Add missing columns to wp_ai_sessions table
 * 
 * Run this file once to fix the "Unknown column 'status'" error
 */

// Load WordPress
require_once __DIR__ . '/wp-load.php';

global $wpdb;

$table = $wpdb->prefix . 'ai_sessions';

// Check if status column exists
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'status'");

if (empty($columns)) {
    echo "Adding 'status' column to $table...\n";
    $wpdb->query("ALTER TABLE $table ADD COLUMN status ENUM('queued', 'active', 'completed', 'failed') DEFAULT 'queued' AFTER idempotency_key");
    echo "✓ Status column added\n";
} else {
    echo "✓ Status column already exists\n";
}

// Check if meta column exists
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'meta'");

if (empty($columns)) {
    echo "Adding 'meta' column to $table...\n";
    $wpdb->query("ALTER TABLE $table ADD COLUMN meta JSON NULL AFTER updated_at");
    echo "✓ Meta column added\n";
} else {
    echo "✓ Meta column already exists\n";
}

// Fix wp_ai_jobs table
$jobs_table = $wpdb->prefix . 'ai_jobs';

// Check if phase column exists
$columns = $wpdb->get_results("SHOW COLUMNS FROM $jobs_table LIKE 'phase'");

if (empty($columns)) {
    echo "Adding 'phase' column to $jobs_table...\n";
    $wpdb->query("ALTER TABLE $jobs_table ADD COLUMN phase VARCHAR(50) NULL AFTER session_id");
    echo "✓ Phase column added\n";
} else {
    echo "✓ Phase column already exists\n";
}

echo "\nDatabase migration completed successfully!\n";
echo "You can now delete this file.\n";
