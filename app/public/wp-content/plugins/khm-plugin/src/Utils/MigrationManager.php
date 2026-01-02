<?php
/**
 * TouchPoint Migration Management Utility
 * Provides easy interface for running and managing database migrations
 * 
 * @package TouchPointMarketing
 * @version 1.0.0
 */

namespace KHM\Utils;

use KHM\Services\Migration;

// Ensure WordPress constants are available (after namespace)
if (!defined('ABSPATH')) {
    // Try to find WordPress root
    $wp_root_paths = [
        dirname(__DIR__, 5) . '/wp-config.php',
        dirname(__DIR__, 4) . '/wp-config.php',
        dirname(__DIR__, 3) . '/wp-config.php'
    ];
    
    foreach ($wp_root_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            break;
        }
    }
}

class MigrationManager {
    
    private $wpdb;
    private $migration_service;
    private $migration_dir;
    private $backup_dir;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->migration_dir = dirname(__DIR__) . '/db/migrations';
        $this->backup_dir = dirname(__DIR__) . '/db/backups';
        
        // Ensure directories exist
        if (!file_exists($this->backup_dir)) {
            if (!\wp_mkdir_p($this->backup_dir)) {
                throw new \Exception("Could not create backup directory");
            }
        }
        
        // Initialize migration service with MySQL PDO
        try {
            $dsn = "mysql:host=" . \DB_HOST . ";dbname=" . \DB_NAME . ";charset=utf8mb4";
            $pdo = new \PDO($dsn, \DB_USER, \DB_PASSWORD, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            $this->migration_service = new Migration($pdo, $this->migration_dir, $this->backup_dir);
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Run all pending migrations
     * 
     * @param bool $dry_run Whether to simulate without executing
     * @return array Migration results
     */
    public function runMigrations($dry_run = true) {
        $this->migration_service->setDryRun($dry_run);
        return $this->migration_service->run();
    }
    
    /**
     * Run specific migrations
     * 
     * @param array $migration_files Specific migration files to run
     * @param bool $dry_run Whether to simulate without executing
     * @return array Migration results
     */
    public function runSpecificMigrations(array $migration_files, $dry_run = true) {
        $this->migration_service->setDryRun($dry_run);
        return $this->migration_service->run($migration_files);
    }
    
    /**
     * Get list of all migration files
     * 
     * @return array List of migration files
     */
    public function getMigrationFiles() {
        $pattern = $this->migration_dir . '/*.sql';
        $files = glob($pattern);
        
        // Sort files by name (which includes dates)
        sort($files);
        
        return array_map('basename', $files);
    }
    
    /**
     * Get list of applied migrations
     * 
     * @return array List of applied migration names
     */
    public function getAppliedMigrations() {
        $table_name = $this->wpdb->prefix . 'khm_migrations';
        
        // Check if migrations table exists
        $table_exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            \DB_NAME,
            $table_name
        ));
        
        if (!$table_exists) {
            return [];
        }
        
        $results = $this->wpdb->get_results(
            "SELECT migration, applied_at FROM {$table_name} ORDER BY applied_at ASC"
        );
        
        return $results;
    }
    
    /**
     * Get pending migrations
     * 
     * @return array List of migration files not yet applied
     */
    public function getPendingMigrations() {
        $all_files = $this->getMigrationFiles();
        $applied = array_column($this->getAppliedMigrations(), 'migration');
        
        return array_diff($all_files, $applied);
    }
    
    /**
     * Get migration status report
     * 
     * @return array Comprehensive status report
     */
    public function getStatusReport() {
        $all_files = $this->getMigrationFiles();
        $applied = $this->getAppliedMigrations();
        $pending = $this->getPendingMigrations();
        
        return [
            'total_migrations' => count($all_files),
            'applied_migrations' => count($applied),
            'pending_migrations' => count($pending),
            'last_migration' => !empty($applied) ? end($applied) : null,
            'next_migration' => !empty($pending) ? reset($pending) : null,
            'all_files' => $all_files,
            'applied_list' => $applied,
            'pending_list' => $pending,
            'backup_directory' => $this->backup_dir,
            'migration_directory' => $this->migration_dir
        ];
    }
    
    /**
     * Validate database schema
     * 
     * @return array Schema validation results
     */
    public function validateSchema() {
        $required_tables = [
            'khm_membership_levels',
            'khm_membership_levelmeta', 
            'khm_memberships_users',
            'khm_membership_orders',
            'khm_user_credits',
            'khm_credit_usage',
            'khm_article_products',
            'khm_shopping_cart',
            'khm_purchases',
            'khm_member_library',
            'khm_library_categories',
            'khm_gifts',
            'khm_gift_redemptions',
            'khm_email_queue',
            'khm_email_logs',
            'khm_discount_codes',
            'khm_discount_codes_levels',
            'khm_discount_codes_uses',
            'khm_migrations'
        ];
        
        $results = [
            'missing_tables' => [],
            'existing_tables' => [],
            'table_health' => []
        ];
        
        foreach ($required_tables as $table) {
            $prefixed_table = $this->wpdb->prefix . $table;
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                \DB_NAME,
                $prefixed_table
            ));
            
            if ($exists) {
                $results['existing_tables'][] = $table;
                
                // Get table info
                $row_count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$prefixed_table}");
                $table_size = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb' 
                     FROM information_schema.tables 
                     WHERE table_schema = %s AND table_name = %s",
                    \DB_NAME,
                    $prefixed_table
                ));
                
                $results['table_health'][$table] = [
                    'rows' => intval($row_count),
                    'size_mb' => floatval($table_size),
                    'status' => 'healthy'
                ];
            } else {
                $results['missing_tables'][] = $table;
            }
        }
        
        $results['schema_complete'] = empty($results['missing_tables']);
        $results['completion_percentage'] = round(
            (count($results['existing_tables']) / count($required_tables)) * 100, 
            2
        );
        
        return $results;
    }
    
    /**
     * Create a manual backup of all KHM tables
     * 
     * @return string|false Backup file path on success, false on failure
     */
    public function createBackup() {
        $backup_file = $this->backup_dir . '/manual_backup_' . date('Y_m_d_H_i_s') . '.sql';
        
        $tables = [
            'khm_membership_levels',
            'khm_membership_levelmeta',
            'khm_memberships_users', 
            'khm_membership_orders',
            'khm_user_credits',
            'khm_credit_usage',
            'khm_article_products',
            'khm_shopping_cart',
            'khm_purchases',
            'khm_member_library',
            'khm_library_categories',
            'khm_gifts',
            'khm_gift_redemptions',
            'khm_email_queue',
            'khm_email_logs',
            'khm_discount_codes',
            'khm_discount_codes_levels',
            'khm_discount_codes_uses'
        ];
        
        $backup_content = "-- TouchPoint Marketing Suite Database Backup\n";
        $backup_content .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Source: " . home_url() . "\n\n";
        
        $backup_content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        foreach ($tables as $table) {
            $prefixed_table = $this->wpdb->prefix . $table;
            
            // Check if table exists
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                \DB_NAME,
                $prefixed_table
            ));
            
            if (!$exists) continue;
            
            // Get table structure
            $create_table = $this->wpdb->get_row("SHOW CREATE TABLE `{$prefixed_table}`", ARRAY_A);
            $backup_content .= "\n-- Table structure for {$table}\n";
            $backup_content .= "DROP TABLE IF EXISTS `{$prefixed_table}`;\n";
            $backup_content .= $create_table['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $this->wpdb->get_results("SELECT * FROM `{$prefixed_table}`", ARRAY_A);
            
            if (!empty($rows)) {
                $backup_content .= "-- Data for table {$table}\n";
                
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        $values[] = is_null($value) ? 'NULL' : "'" . \esc_sql($value) . "'";
                    }
                    
                    $backup_content .= "INSERT INTO `{$prefixed_table}` VALUES (" . implode(', ', $values) . ");\n";
                }
                
                $backup_content .= "\n";
            }
        }
        
        $backup_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        $result = file_put_contents($backup_file, $backup_content);
        
        return $result !== false ? $backup_file : false;
    }
    
    /**
     * Get backup files list
     * 
     * @return array List of backup files with metadata
     */
    public function getBackupFiles() {
        $pattern = $this->backup_dir . '/*.sql';
        $files = glob($pattern);
        
        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'created' => filemtime($file),
                'created_formatted' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by creation time, newest first
        usort($backups, function($a, $b) {
            return $b['created'] - $a['created'];
        });
        
        return $backups;
    }
    
    /**
     * Initialize TouchPoint Marketing Suite database
     * Creates all required tables and seeds initial data
     * 
     * @param bool $force Whether to force recreation of existing tables
     * @return array Results of initialization
     */
    public function initializeTouchPoint($force = false) {
        $results = ['steps' => [], 'errors' => [], 'success' => true];
        
        try {
            // Step 1: Create backup if tables exist
            if (!$force) {
                $schema = $this->validateSchema();
                if ($schema['completion_percentage'] > 0) {
                    $backup_file = $this->createBackup();
                    $results['steps'][] = "Backup created: " . ($backup_file ? basename($backup_file) : 'Failed');
                }
            }
            
            // Step 2: Run core table migrations
            $core_migrations = [
                '0001_create_khm_tables.sql',
                '2025_11_04_create_credit_system_tables.sql',
                '2025_11_07_create_touchpoint_core_tables.sql'
            ];
            
            $this->migration_service->setDryRun(false);
            $migration_results = $this->migration_service->run($core_migrations);
            $results['steps'][] = "Core migrations executed: " . count($core_migrations) . " files";
            
            // Step 3: Seed initial data
            $seed_result = $this->migration_service->run(['2025_11_07_seed_touchpoint_initial_data.sql']);
            $results['steps'][] = "Initial data seeded";
            
            // Step 4: Validate final schema
            $final_schema = $this->validateSchema();
            $results['steps'][] = "Schema validation: {$final_schema['completion_percentage']}% complete";
            $results['schema'] = $final_schema;
            
            if ($final_schema['completion_percentage'] < 100) {
                $results['errors'][] = "Schema incomplete. Missing tables: " . implode(', ', $final_schema['missing_tables']);
                $results['success'] = false;
            }
            
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            $results['success'] = false;
        }
        
        return $results;
    }
}