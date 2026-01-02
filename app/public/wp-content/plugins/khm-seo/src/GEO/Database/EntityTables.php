<?php
/**
 * Entity Database Tables Manager
 * 
 * Handles database table creation, updates, and management for the Entity & Glossary Registry system.
 * Supports single source of truth for canonical terms/entities with aliases, policies, and auto-linking.
 * 
 * @package KHM_SEO\GEO\Database
 * @since 2.0.0
 * @version 2.0.0
 */

namespace KHM_SEO\GEO\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Entity Database Tables Manager Class
 * Handles all entity-related database operations
 */
class EntityTables {
    
    /**
     * @var string Current database version
     */
    const DB_VERSION = '2.0.0';
    
    /**
     * @var string Database version option key
     */
    const DB_VERSION_OPTION = 'khm_seo_geo_entities_db_version';
    
    /**
     * @var array Table names mapping
     */
    private $tables = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        // Define table names with WordPress prefix
        $this->tables = array(
            'entities' => $wpdb->prefix . 'geo_entities',
            'aliases' => $wpdb->prefix . 'geo_entity_aliases', 
            'link_rules' => $wpdb->prefix . 'geo_entity_link_rules',
            'scopes' => $wpdb->prefix . 'geo_entity_scopes',
            'page_entities' => $wpdb->prefix . 'geo_page_entities'
        );
    }
    
    /**
     * Get table name by type
     * 
     * @param string $type Table type
     * @return string Table name
     */
    public function get_table_name( $type ) {
        return isset( $this->tables[ $type ] ) ? $this->tables[ $type ] : '';
    }
    
    /**
     * Get all table names
     * 
     * @return array All table names
     */
    public function get_all_table_names() {
        return $this->tables;
    }
    
    /**
     * Install/update database tables
     */
    public function install_tables() {
        $current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
        
        if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
            $this->create_entities_table();
            $this->create_aliases_table();
            $this->create_link_rules_table();
            $this->create_scopes_table();
            $this->create_page_entities_table();
            
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        }
    }
    
    /**
     * Create main entities table
     * 
     * Stores canonical entities with their core properties
     */
    private function create_entities_table() {
        global $wpdb;
        
        $table_name = $this->tables['entities'];
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope ENUM('global', 'client', 'site') NOT NULL DEFAULT 'site',
            type ENUM('Organization', 'Product', 'Technology', 'Metric', 'Acronym', 'Term', 'Person', 'Place', 'Thing') NOT NULL DEFAULT 'Term',
            canonical VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            definition TEXT,
            same_as JSON,
            preferred_capitalization VARCHAR(255),
            status ENUM('active', 'deprecated') NOT NULL DEFAULT 'active',
            replacement_entity_id BIGINT UNSIGNED NULL,
            owner_user_id BIGINT UNSIGNED,
            review_cadence_days INT UNSIGNED DEFAULT 365,
            last_reviewed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_canonical_scope (canonical, scope),
            UNIQUE KEY idx_slug_scope (slug, scope),
            KEY idx_scope (scope),
            KEY idx_type (type),
            KEY idx_status (status),
            KEY idx_owner (owner_user_id),
            KEY idx_last_reviewed (last_reviewed_at),
            KEY idx_replacement (replacement_entity_id),
            FOREIGN KEY (replacement_entity_id) REFERENCES $table_name(id) ON DELETE SET NULL,
            FOREIGN KEY (owner_user_id) REFERENCES {$wpdb->users}(ID) ON DELETE SET NULL
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Create entity aliases table
     * 
     * Stores alternative terms/spellings for entities
     */
    private function create_aliases_table() {
        global $wpdb;
        
        $table_name = $this->tables['aliases'];
        $entities_table = $this->tables['entities'];
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_id BIGINT UNSIGNED NOT NULL,
            alias VARCHAR(255) NOT NULL,
            is_banned BOOLEAN DEFAULT FALSE,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_alias_entity (alias, entity_id),
            KEY idx_entity_id (entity_id),
            KEY idx_alias (alias),
            KEY idx_banned (is_banned),
            FOREIGN KEY (entity_id) REFERENCES $entities_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Create entity link rules table
     * 
     * Defines how entities should be linked in content
     */
    private function create_link_rules_table() {
        global $wpdb;
        
        $table_name = $this->tables['link_rules'];
        $entities_table = $this->tables['entities'];
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_id BIGINT UNSIGNED NOT NULL,
            internal_url VARCHAR(2048),
            link_mode ENUM('first_only', 'all', 'manual', 'never') NOT NULL DEFAULT 'first_only',
            nofollow BOOLEAN DEFAULT FALSE,
            new_tab BOOLEAN DEFAULT FALSE,
            aria_label VARCHAR(255),
            skip_in_headings BOOLEAN DEFAULT TRUE,
            skip_in_quotes BOOLEAN DEFAULT TRUE,
            skip_in_code BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_entity_id (entity_id),
            KEY idx_link_mode (link_mode),
            FOREIGN KEY (entity_id) REFERENCES $entities_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Create entity scopes table
     * 
     * Maps entities to clients/sites with inheritance
     */
    private function create_scopes_table() {
        global $wpdb;
        
        $table_name = $this->tables['scopes'];
        $entities_table = $this->tables['entities'];
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_id BIGINT UNSIGNED NOT NULL,
            client_id VARCHAR(100),
            site_id BIGINT UNSIGNED,
            inherit_from_parent BOOLEAN DEFAULT TRUE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_entity_client_site (entity_id, client_id, site_id),
            KEY idx_entity_id (entity_id),
            KEY idx_client_id (client_id),
            KEY idx_site_id (site_id),
            KEY idx_active (is_active),
            FOREIGN KEY (entity_id) REFERENCES $entities_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Create page entities relationship table
     * 
     * Maps posts/pages to entities with roles
     */
    private function create_page_entities_table() {
        global $wpdb;
        
        $table_name = $this->tables['page_entities'];
        $entities_table = $this->tables['entities'];
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            role ENUM('primary', 'about', 'mentions') NOT NULL DEFAULT 'mentions',
            confidence DECIMAL(3,2) DEFAULT 1.00,
            detected_by ENUM('manual', 'ai', 'auto_link') DEFAULT 'manual',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_post_entity_role (post_id, entity_id, role),
            KEY idx_post_id (post_id),
            KEY idx_entity_id (entity_id),
            KEY idx_role (role),
            KEY idx_confidence (confidence),
            KEY idx_detected_by (detected_by),
            FOREIGN KEY (entity_id) REFERENCES $entities_table(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Uninstall tables (cleanup)
     */
    public function uninstall_tables() {
        global $wpdb;
        
        // Drop in reverse order due to foreign key constraints
        $tables = array_reverse( $this->tables );
        
        foreach ( $tables as $table_name ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        }
        
        delete_option( self::DB_VERSION_OPTION );
    }
    
    /**
     * Get table creation status
     * 
     * @return array Status of each table
     */
    public function check_tables_status() {
        global $wpdb;
        
        $status = array();
        
        foreach ( $this->tables as $type => $table_name ) {
            $result = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) );
            
            $status[ $type ] = ( $result === $table_name );
        }
        
        return $status;
    }
    
    /**
     * Get database statistics
     * 
     * @return array Database statistics
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = array();
        
        foreach ( $this->tables as $type => $table_name ) {
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
            $stats[ $type ] = intval( $count );
        }
        
        // Add some useful aggregations
        $stats['total_entities'] = $stats['entities'];
        $stats['total_aliases'] = $stats['aliases'];
        $stats['active_entities'] = $wpdb->get_var( 
            $wpdb->prepare( "SELECT COUNT(*) FROM {$this->tables['entities']} WHERE status = %s", 'active' )
        );
        $stats['deprecated_entities'] = $wpdb->get_var( 
            $wpdb->prepare( "SELECT COUNT(*) FROM {$this->tables['entities']} WHERE status = %s", 'deprecated' )
        );
        
        return $stats;
    }
}