<?php
namespace KHM\Migrations;

defined('ABSPATH') || exit;

/**
 * Phase 5 Migration: Create Tech.Connect Relational Tables
 *
 * These tables enable the 4-step discovery traversal:
 * Article Category → Problem ID → Solution ID → Sponsor ID
 *
 * Tables created:
 *   - tc_business_problems    (Problem definitions)
 *   - tc_solutions            (Solution catalog)
 *   - tc_category_problems    (Category ↔ Problem bridge)
 *   - tc_solution_problems    (Solution ↔ Problem bridge)
 */
class CreateTechConnectTables {

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tc_business_problems (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                description text,
                brand_id bigint(20) UNSIGNED DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY brand_id (brand_id),
                KEY title (title(100))
            ) $charset_collate;",

            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tc_solutions (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                solution_type enum('software','consultancy','service') DEFAULT 'software',
                description text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY solution_type (solution_type),
                KEY title (title(100))
            ) $charset_collate;",

            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tc_category_problems (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                category_id bigint(20) UNSIGNED NOT NULL,
                problem_id bigint(20) UNSIGNED NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY cat_prob (category_id, problem_id),
                KEY category_id (category_id),
                KEY problem_id (problem_id)
            ) $charset_collate;",

            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tc_solution_problems (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                solution_id bigint(20) UNSIGNED NOT NULL,
                problem_id bigint(20) UNSIGNED NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY sol_prob (solution_id, problem_id),
                KEY solution_id (solution_id),
                KEY problem_id (problem_id)
            ) $charset_collate;",
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }
}