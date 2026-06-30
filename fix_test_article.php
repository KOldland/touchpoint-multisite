<?php
require_once __DIR__ . '/app/public/wp-load.php';
global $wpdb;

$table = $wpdb->base_prefix . 'content_registry';

// Get the test article
$article = $wpdb->get_row("SELECT * FROM $table WHERE slug = 'test-article'");

if (!$article) {
    echo "No test article found\n";
    exit;
}

echo "Article found: ID={$article->id}, target_blog_id={$article->target_blog_id}\n";

// Check if wp_post_id is already set
if (!empty($article->wp_post_id)) {
    echo "wp_post_id already set: {$article->wp_post_id}\n";
    exit;
}

// Use blog_id 1 (main site) since 2 doesn't exist
$target_blog_id = 1;

// Switch to the target blog
switch_to_blog($target_blog_id);

// Create a WordPress post
$post_id = wp_insert_post([
    'post_title' => $article->title,
    'post_content' => $article->content_body ?: 'Test content for the article',
    'post_excerpt' => $article->excerpt ?: '',
    'post_status' => 'draft',
    'post_type' => 'post',
]);

// Restore blog
restore_current_blog();

if (is_wp_error($post_id)) {
    echo "Error creating post: " . $post_id->get_error_message() . "\n";
    exit;
}

// Update the registry with both wp_post_id and correct target_blog_id
$wpdb->update(
    $table,
    ['wp_post_id' => $post_id, 'target_blog_id' => $target_blog_id],
    ['id' => $article->id]
);

echo "Created post ID: $post_id for article ID: {$article->id}\n";
echo "Updated target_blog_id to: $target_blog_id\n";
