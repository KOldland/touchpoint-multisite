<?php
/**
 * Migration: Port existing WordPress posts to content_registry
 * 
 * This migration copies all existing posts from all sites in the network
 * to the centralized content_registry table.
 */

defined('ABSPATH') || exit;

function run_port_existing_posts_migration() {
    global $wpdb;
    
    $table_name = $wpdb->base_prefix . 'content_registry';
    
    // Get all sites in the network
    $sites = get_sites(['number' => 100]);
    
    foreach ($sites as $site) {
        $blog_id = $site->blog_id;
        
        // Switch to the site to get its posts
        if (function_exists('switch_to_blog')) {
            switch_to_blog($blog_id);
        }
        
        // Get all posts from this site
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'any',
            'numberposts' => -1,
            'post_parent' => 0, // Only parent posts, not children
        ]);
        
        foreach ($posts as $post) {
            // Check if this post already has a registry entry
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE wp_post_id = %d",
                    $post->ID
                )
            );
            
            if ($existing) {
                continue; // Skip if already exists
            }
            
            // Map post status to article status
            $status_map = [
                'draft' => 'Draft',
                'publish' => 'Live',
                'future' => 'Scheduled',
                'private' => 'Draft',
            ];
            $article_status = $status_map[$post->post_status] ?? 'Draft';
            
            // Get excerpt
            $excerpt = $post->post_excerpt ?: wp_trim_words($post->post_content, 55);
            
            // Insert into content_registry
            $wpdb->insert($table_name, [
                'target_blog_id' => $blog_id,
                'slug' => $post->post_name,
                'article_status' => $article_status,
                'title' => $post->post_title,
                'content_body' => $post->post_content,
                'excerpt' => $excerpt,
                'wp_post_id' => $post->ID,
                'created_at' => $post->post_date,
                'updated_at' => $post->post_modified,
            ]);
        }
        
        // Restore current blog
        if (function_exists('restore_current_blog')) {
            restore_current_blog();
        }
    }
    
    echo "Migration complete. Ported posts from " . count($sites) . " sites.\n";
}

// Run the migration
run_port_existing_posts_migration();