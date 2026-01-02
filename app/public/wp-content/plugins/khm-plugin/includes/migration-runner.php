<?php
/**
 * TouchPoint Migration Runner - WordPress Admin Interface
 * Simple interface for running database migrations from WordPress admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TouchPoint Migration Runner Class
 */
class KHM_Migration_Runner {
    
    private $migration_dir;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->migration_dir = dirname(__FILE__) . '/../db/migrations';
    }
    
    /**
     * Run all pending TouchPoint migrations
     */
    public function run_touchpoint_migrations() {
        $results = [
            'success' => false,
            'message' => '',
            'migrations_run' => [],
            'errors' => []
        ];
        
        try {
            // Check if migrations table exists, if not create it
            $this->ensure_migrations_table();
            
            // Get TouchPoint specific migrations
            $touchpoint_migrations = [
                '2025_11_07_create_touchpoint_core_tables.sql',
                '2025_11_07_seed_touchpoint_initial_data.sql'
            ];
            
            $applied_migrations = $this->get_applied_migrations();
            
            foreach ($touchpoint_migrations as $migration_file) {
                if (!in_array($migration_file, $applied_migrations)) {
                    $result = $this->run_single_migration($migration_file);
                    
                    if ($result['success']) {
                        $results['migrations_run'][] = $migration_file;
                        $this->record_migration($migration_file);
                    } else {
                        $results['errors'][] = "Failed to run {$migration_file}: " . $result['error'];
                        break; // Stop on first error
                    }
                }
            }
            
            if (empty($results['errors'])) {
                $results['success'] = true;
                $results['message'] = 'All TouchPoint migrations completed successfully!';
            } else {
                $results['message'] = 'Some migrations failed. See errors below.';
            }
            
        } catch (Exception $e) {
            $results['message'] = 'Migration failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Get database schema status
     */
    public function get_schema_status() {
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
            'khm_discount_codes_uses'
        ];
        
        $existing_tables = [];
        $missing_tables = [];
        
        foreach ($required_tables as $table) {
            $prefixed_table = $this->wpdb->prefix . $table;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '{$prefixed_table}'") == $prefixed_table) {
                $existing_tables[] = $table;
            } else {
                $missing_tables[] = $table;
            }
        }
        
        $completion_percentage = round((count($existing_tables) / count($required_tables)) * 100, 2);
        
        return [
            'total_tables' => count($required_tables),
            'existing_tables' => $existing_tables,
            'missing_tables' => $missing_tables,
            'completion_percentage' => $completion_percentage,
            'schema_complete' => empty($missing_tables)
        ];
    }
    
    /**
     * Ensure migrations tracking table exists
     */
    private function ensure_migrations_table() {
        $table_name = $this->wpdb->prefix . 'khm_migrations';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            migration varchar(255) NOT NULL,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY migration_name (migration)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get list of applied migrations
     */
    private function get_applied_migrations() {
        $table_name = $this->wpdb->prefix . 'khm_migrations';
        
        $results = $this->wpdb->get_col("SELECT migration FROM {$table_name} ORDER BY applied_at ASC");
        
        return $results ? $results : [];
    }
    
    /**
     * Run a single migration file
     */
    private function run_single_migration($migration_file) {
        $result = ['success' => false, 'error' => ''];
        
        $file_path = $this->migration_dir . '/' . $migration_file;
        
        if (!file_exists($file_path)) {
            $result['error'] = "Migration file not found: {$migration_file}";
            return $result;
        }
        
        $sql_content = file_get_contents($file_path);
        
        if ($sql_content === false) {
            $result['error'] = "Could not read migration file: {$migration_file}";
            return $result;
        }
        
        // Split SQL statements (basic splitting on semicolon)
        $statements = $this->split_sql_statements($sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            
            // Skip empty statements and comments
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            $result_query = $this->wpdb->query($statement);
            
            if ($result_query === false) {
                $result['error'] = "SQL Error in {$migration_file}: " . $this->wpdb->last_error;
                return $result;
            }
        }
        
        $result['success'] = true;
        return $result;
    }
    
    /**
     * Record that a migration has been applied
     */
    private function record_migration($migration_file) {
        $table_name = $this->wpdb->prefix . 'khm_migrations';
        
        $this->wpdb->insert(
            $table_name,
            ['migration' => $migration_file],
            ['%s']
        );
    }
    
    /**
     * Basic SQL statement splitter
     */
    private function split_sql_statements($sql) {
        // Remove comments and empty lines
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split on semicolons not inside quotes
        $statements = [];
        $current_statement = '';
        $in_quotes = false;
        $quote_char = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (!$in_quotes && ($char === '"' || $char === "'")) {
                $in_quotes = true;
                $quote_char = $char;
            } elseif ($in_quotes && $char === $quote_char) {
                $in_quotes = false;
                $quote_char = '';
            } elseif (!$in_quotes && $char === ';') {
                if (trim($current_statement) !== '') {
                    $statements[] = trim($current_statement);
                }
                $current_statement = '';
                continue;
            }
            
            $current_statement .= $char;
        }
        
        // Add final statement if not empty
        if (trim($current_statement) !== '') {
            $statements[] = trim($current_statement);
        }
        
        return $statements;
    }
}

// Hook to add admin menu if we're in WordPress admin
if (is_admin()) {
    add_action('admin_menu', function() {
        add_management_page(
            'TouchPoint Migrations',
            'TouchPoint DB',
            'manage_options',
            'touchpoint-migrations',
            'touchpoint_migrations_admin_page'
        );
    });
}

/**
 * Admin page for running migrations
 */
function touchpoint_migrations_admin_page() {
    $migration_runner = new KHM_Migration_Runner();
    
    // Handle form submission
    if (isset($_POST['run_migrations']) && wp_verify_nonce($_POST['_wpnonce'], 'touchpoint_migrations')) {
        $results = $migration_runner->run_touchpoint_migrations();
        
        if ($results['success']) {
            echo '<div class="notice notice-success"><p>' . esc_html($results['message']) . '</p></div>';
            
            if (!empty($results['migrations_run'])) {
                echo '<div class="notice notice-info"><p>Migrations executed: ' . implode(', ', $results['migrations_run']) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($results['message']) . '</p></div>';
            
            if (!empty($results['errors'])) {
                echo '<div class="notice notice-error">';
                foreach ($results['errors'] as $error) {
                    echo '<p>' . esc_html($error) . '</p>';
                }
                echo '</div>';
            }
        }
    }
    
    // Get current schema status
    $schema_status = $migration_runner->get_schema_status();
    
    ?>
    <div class="wrap">
        <h1>TouchPoint Marketing Suite - Database Management</h1>
        
        <h2>Schema Status</h2>
        <div class="card">
            <p><strong>Database Schema Completion:</strong> <?php echo $schema_status['completion_percentage']; ?>%</p>
            <p><strong>Total Required Tables:</strong> <?php echo $schema_status['total_tables']; ?></p>
            <p><strong>Existing Tables:</strong> <?php echo count($schema_status['existing_tables']); ?></p>
            
            <?php if (!empty($schema_status['missing_tables'])): ?>
                <p><strong>Missing Tables:</strong></p>
                <ul>
                    <?php foreach ($schema_status['missing_tables'] as $table): ?>
                        <li><?php echo esc_html($table); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <?php if (!$schema_status['schema_complete']): ?>
            <h2>Run TouchPoint Migrations</h2>
            <div class="card">
                <p>Your TouchPoint Marketing Suite database schema is incomplete. Run the migrations below to set up all required tables and initial data.</p>
                
                <form method="post">
                    <?php wp_nonce_field('touchpoint_migrations'); ?>
                    <p>
                        <input type="submit" name="run_migrations" class="button button-primary" value="Run TouchPoint Migrations" />
                    </p>
                </form>
            </div>
        <?php else: ?>
            <div class="notice notice-success">
                <p><strong>✅ TouchPoint Marketing Suite database schema is complete!</strong></p>
            </div>
        <?php endif; ?>
        
        <h3>About TouchPoint Marketing Suite Database</h3>
        <div class="card">
            <p>The TouchPoint Marketing Suite requires the following database components:</p>
            <ul>
                <li><strong>Membership System:</strong> User levels, subscriptions, and billing</li>
                <li><strong>Credit System:</strong> Monthly credit allocation and usage tracking</li>
                <li><strong>eCommerce:</strong> Product management, shopping cart, and purchases</li>
                <li><strong>Library System:</strong> Personal article collections and categories</li>
                <li><strong>Gift System:</strong> Article gifting and redemption</li>
                <li><strong>Email System:</strong> Enhanced email queue and logging</li>
                <li><strong>Affiliate System:</strong> Commission tracking and payouts</li>
                <li><strong>Discount System:</strong> Promotional codes and usage tracking</li>
            </ul>
        </div>
    </div>
    <?php
}