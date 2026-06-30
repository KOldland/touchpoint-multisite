<?php
/**
 * Migration: Import meta data and parent-child relationships
 * 
 * This migration:
 * 1. Reads SEO, GEO, SMMA meta from WordPress posts
 * 2. Creates parent-child relationships in content_registry
 * 3. Updates existing registry entries with meta data
 */

defined('ABSPATH') || exit;

function run_import_meta_and_relationships_migration() {
    global $wpdb;
    
    $table_name = $wpdb->base_prefix . 'content_registry';
    
    // Get all sites
    $sites = get_sites(['number' => 100]);
    
    foreach ($sites as $site) {
        $blog_id = $site->blog_id;
        
        switch_to_blog($blog_id);
        
        // Get all posts with atomic_article_ids meta (parent posts)
        $parent_posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_key' => '_atomic_article_ids',
        ]);
        
        foreach ($parent_posts as $parent_post) {
            // Check if this post has a registry entry
            $registry = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE wp_post_id = %d",
                    $parent_post->ID
                )
            );
            
            if (!$registry) {
                continue;
            }
            
            // Get atomic article IDs
            $atomic_ids = get_post_meta($parent_post->ID, '_atomic_article_ids', true);
            
            if ($atomic_ids && is_array($atomic_ids)) {
                // Update the registry with atomic count
                $wpdb->update(
                    $table_name,
                    ['atomic_count' => count($atomic_ids)],
                    ['id' => $registry->id]
                );
                
                // Create child entries for each atomic article
                foreach ($atomic_ids as $index => $child_post_id) {
                    $child_post = get_post($child_post_id);
                    if (!$child_post) {
                        continue;
                    }
                    
                    // Check if child already exists
                    $existing_child = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$table_name} WHERE wp_post_id = %d",
                            $child_post_id
                        )
                    );
                    
                    if ($existing_child) {
                        // Update to link to parent
                        $wpdb->update(
                            $table_name,
                            ['parent_post_id' => $registry->id],
                            ['id' => $existing_child]
                        );
                    } else {
                        // Create new child entry
                        $wpdb->insert($table_name, [
                            'target_blog_id' => $blog_id,
                            'slug' => $child_post->post_name,
                            'article_status' => 'Live',
                            'title' => $child_post->post_title,
                            'content_body' => $child_post->post_content,
                            'excerpt' => $child_post->post_excerpt ?: wp_trim_words($child_post->post_content, 55),
                            'wp_post_id' => $child_post_id,
                            'parent_post_id' => $registry->id,
                            'created_at' => $child_post->post_date,
                            'updated_at' => $child_post->post_modified,
                        ]);
                    }
                }
            }
            
            // Import SEO meta
            $seo_score = get_post_meta($parent_post->ID, '_khm_seo_score', true);
            if ($seo_score) {
                $seo_metadata = $registry->seo_metadata ? json_decode($registry->seo_metadata, true) : [];
                $seo_metadata['score'] = (int) $seo_score;
                $wpdb->update(
                    $table_name,
                    ['seo_metadata' => wp_json_encode($seo_metadata)],
                    ['id' => $registry->id]
                );
            }
            
            // Import GEO meta
            $geo_score = get_post_meta($parent_post->ID, '_khm_geo_score', true);
            if ($geo_score) {
                $geo_flags = $registry->geo_flags ? json_decode($registry->geo_flags, true) : [];
                $geo_flags['score'] = (int) $geo_score;
                $wpdb->update(
                    $table_name,
                    ['geo_flags' => wp_json_encode($geo_flags)],
                    ['id' => $registry->id]
                );
            }
            
            // Import SMMA meta
            $smma_status = get_post_meta($parent_post->ID, '_kh_smma_social_queue_status', true);
            if ($smma_status) {
                $smma_flags = $registry->smma_flags ? json_decode($registry->smma_flags, true) : [];
                $smma_flags['status'] = $smma_status;
                $wpdb->update(
                    $table_name,
                    ['smma_flags' => wp_json_encode($smma_flags)],
                    ['id' => $registry->id]
                );
            }
        }
        
        restore_current_blog();
    }
    
    echo "Migration complete.\n";
}

run_import_meta_and_relationships_migration();