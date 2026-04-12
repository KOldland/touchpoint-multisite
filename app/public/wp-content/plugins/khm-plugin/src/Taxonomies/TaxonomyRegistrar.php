<?php

namespace KHM\Taxonomies;

class TaxonomyRegistrar {

    public function register(): void {
        add_action('init', [$this, 'register_taxonomies'], 5);
        add_action('init', [$this, 'register_term_meta'], 6);
    }

    public function register_taxonomies(): void {
        register_taxonomy('tp_category', ['post', 'planner_session'], [
            'labels' => [
                'name' => __('Categories', 'khm-membership'),
                'singular_name' => __('Category', 'khm-membership'),
                'search_items' => __('Search Categories', 'khm-membership'),
                'all_items' => __('All Categories', 'khm-membership'),
                'parent_item' => __('Parent Category', 'khm-membership'),
                'parent_item_colon' => __('Parent Category:', 'khm-membership'),
                'edit_item' => __('Edit Category', 'khm-membership'),
                'update_item' => __('Update Category', 'khm-membership'),
                'add_new_item' => __('Add New Category', 'khm-membership'),
                'new_item_name' => __('New Category Name', 'khm-membership'),
                'menu_name' => __('Categories', 'khm-membership'),
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'hierarchical' => true,
            'query_var' => true,
            'rewrite' => false,
        ]);

        register_taxonomy('tp_topic', ['post', 'planner_session'], [
            'labels' => [
                'name' => __('Topics', 'khm-membership'),
                'singular_name' => __('Topic', 'khm-membership'),
                'search_items' => __('Search Topics', 'khm-membership'),
                'all_items' => __('All Topics', 'khm-membership'),
                'edit_item' => __('Edit Topic', 'khm-membership'),
                'update_item' => __('Update Topic', 'khm-membership'),
                'add_new_item' => __('Add New Topic', 'khm-membership'),
                'new_item_name' => __('New Topic Name', 'khm-membership'),
                'menu_name' => __('Topics', 'khm-membership'),
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'hierarchical' => false,
            'query_var' => true,
            'rewrite' => false,
        ]);
    }

    public function register_term_meta(): void {
        register_term_meta('tp_category', 'tp_category_research_policy', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => [$this, 'sanitize_research_policy'],
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_term_meta('tp_topic', 'tp_topic_created_by', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => 'absint',
            'auth_callback' => '__return_true',
        ]);

        register_term_meta('tp_topic', 'tp_topic_created_at', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => '__return_true',
        ]);

        register_term_meta('tp_topic', 'tp_topic_created_source', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => '__return_true',
        ]);
    }

    public function sanitize_research_policy($value): array {
        if (!is_array($value)) {
            return [];
        }

        $array_keys = ['priority_domains', 'blocked_domains', 'blocked_keywords', 'preferred_sources'];
        $normalized = [];

        foreach ($array_keys as $key) {
            $items = is_array($value[$key] ?? null) ? $value[$key] : [];
            $items = array_values(array_filter(array_map('sanitize_text_field', $items)));
            $normalized[$key] = $items;
        }

        $normalized['recency_months'] = max(1, min(60, (int) ($value['recency_months'] ?? 24)));
        $source_mix = is_array($value['source_mix_minimums'] ?? null) ? $value['source_mix_minimums'] : [];
        $normalized['source_mix_minimums'] = [
            'academic' => max(0, min(100, (int) ($source_mix['academic'] ?? 10))),
            'analyst' => max(0, min(100, (int) ($source_mix['analyst'] ?? 20))),
            'industry' => max(0, min(100, (int) ($source_mix['industry'] ?? 30))),
            'case_study' => max(0, min(100, (int) ($source_mix['case_study'] ?? 10))),
        ];

        return $normalized;
    }
}
