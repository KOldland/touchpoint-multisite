<?php

namespace KHM\PublicFrontend;

use KHM\Services\AccessControlService;

/**
 * ContentFilter
 *
 * Hooks into WordPress content filters to protect posts and pages.
 * Automatically restricts content based on membership level requirements.
 */
class ContentFilter {

    private AccessControlService $access_control;

    public function __construct( AccessControlService $access_control ) {
        $this->access_control = $access_control;
    }

    /**
     * Register WordPress hooks
     */
    public function register(): void {
        // Filter post content
        add_filter('the_content', [ $this, 'filter_content' ], 10);

        // Filter post excerpt
        add_filter('the_excerpt', [ $this, 'filter_excerpt' ], 10);

        // Filter in REST API
        add_filter('rest_prepare_post', [ $this, 'filter_rest_post' ], 10, 2);
        add_filter('rest_prepare_page', [ $this, 'filter_rest_post' ], 10, 2);

        // Enqueue protection styles
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_styles' ]);
    }

    /**
     * Filter post content
     *
     * @param string $content
     * @return string
     */
    public function filter_content( string $content ): string {
        // Check if we should filter content
        if ( ! $this->access_control->should_filter_content() ) {
            return $content;
        }

        // Get current post
        $post = get_post();
        if ( ! $post ) {
            return $content;
        }

        // Filter content based on access
        return $this->access_control->filter_content($content, $post->ID);
    }

    /**
     * Filter post excerpt
     *
     * @param string $excerpt
     * @return string
     */
    public function filter_excerpt( string $excerpt ): string {
        // Check if we should filter content
        if ( ! $this->access_control->should_filter_content() ) {
            return $excerpt;
        }

        $post = get_post();
        if ( ! $post ) {
            return $excerpt;
        }

        // Check if post is protected
        $user_id = get_current_user_id();
        $has_access = $this->access_control->has_access($user_id, $post->ID);

        if ( ! $has_access ) {
            // Return teaser for protected content
            $excerpt = wp_trim_words($excerpt, 20);
            $excerpt .= ' <span class="khm-excerpt-protected">['
                    . esc_html__('Members Only', 'khm-membership') . ']</span>';
        }

        return $excerpt;
    }

    /**
     * Filter REST API responses
     *
     * @param \WP_REST_Response $response
     * @param \WP_Post $post
     * @return \WP_REST_Response
     */
    public function filter_rest_post( $response, $post ) {
        $user_id = get_current_user_id();
        $has_access = $this->access_control->has_access($user_id, $post->ID);

        if ( ! $has_access ) {
            // Remove or truncate content
            $data = $response->get_data();

            if ( isset($data['content']['rendered']) ) {
                $data['content']['rendered'] = $this->access_control->filter_content(
                    $data['content']['rendered'],
                    $post->ID,
                    $user_id
                );
            }

            if ( isset($data['excerpt']['rendered']) ) {
                $data['excerpt']['rendered'] = wp_trim_words($data['excerpt']['rendered'], 20);
            }

            $response->set_data($data);
        }

        return $response;
    }

    /**
     * Enqueue protection styles
     */
    public function enqueue_styles(): void {
        wp_enqueue_style(
            'khm-protection',
            plugins_url('public/css/protection.css', dirname(__DIR__, 2)),
            [],
            '1.0.0'
        );
    }
}
