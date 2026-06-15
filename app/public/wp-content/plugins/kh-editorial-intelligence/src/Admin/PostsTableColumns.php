<?php

namespace KH\Editorial\Admin;

use KH\Editorial\Database\AllocationTable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Customises the Posts list table (edit.php) columns.
 *
 * Removes: Comments, Categories (editorial_category), Tags
 * Adds:    Author (multi-author), SEO Score, GEO Score, Distribution
 */
class PostsTableColumns {

    public function init(): void {
        // Only on hub site
        if ( get_current_blog_id() !== 1 ) {
            return;
        }

        add_filter( 'manage_posts_columns', [ $this, 'override_columns' ], 20 );
        add_action( 'manage_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_action( 'admin_head-edit.php', [ $this, 'column_styles' ] );
    }

    /**
     * Replace the default column set.
     */
    public function override_columns( array $columns ): array {
        // Build our curated set — only the columns we keep.
        $new = [];

        // Bulk checkbox
        if ( isset( $columns['cb'] ) ) {
            $new['cb'] = $columns['cb'];
        }

        // Title
        $new['title'] = $columns['title'] ?? __( 'Title' );

        // Author — custom column pulling from multi-author panel
        $new['kh_author'] = __( 'Author', 'kh-editorial-intelligence' );

        // Content Type taxonomy column — keep if it exists
        if ( isset( $columns['taxonomy-content_type'] ) ) {
            $new['taxonomy-content_type'] = $columns['taxonomy-content_type'];
        } else {
            $new['content_type'] = __( 'Content Type', 'kh-editorial-intelligence' );
        }

        // SEO Score
        $new['kh_seo_score'] = __( 'SEO', 'kh-editorial-intelligence' );

        // GEO Score
        $new['kh_geo_score'] = __( 'GEO', 'kh-editorial-intelligence' );

        // Distribution
        $new['kh_distribution'] = __( 'Distribution', 'kh-editorial-intelligence' );

        // Date
        if ( isset( $columns['date'] ) ) {
            $new['date'] = $columns['date'];
        }

        return $new;
    }

    /**
     * Render custom column content.
     */
    public function render_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'kh_author':
                $this->render_author_column( $post_id );
                break;

            case 'kh_seo_score':
                $this->render_score_column( $post_id, '_khm_seo_score' );
                break;

            case 'kh_geo_score':
                $this->render_score_column( $post_id, '_khm_geo_score' );
                break;

            case 'kh_distribution':
                $this->render_distribution_column( $post_id );
                break;

            case 'content_type':
                // Fallback if taxonomy column isn't registered — show "Article" as default
                $this->render_content_type_column( $post_id );
                break;
        }
    }

    /**
     * Render the multi-author column.
     */
    private function render_author_column( int $post_id ): void {
        if ( function_exists( 'kh_get_post_authors' ) ) {
            $authors = \kh_get_post_authors( $post_id );
            if ( ! empty( $authors ) ) {
                $names = [];
                foreach ( $authors as $author_post ) {
                    if ( is_object( $author_post ) && isset( $author_post->ID ) ) {
                        $name = function_exists( 'get_field' )
                            ? get_field( 'author_name', $author_post->ID )
                            : '';
                        $names[] = $name ?: get_the_title( $author_post->ID );
                    }
                }
                echo esc_html( implode( ', ', array_filter( $names ) ) );
                return;
            }
        }
        // Fallback to WordPress post_author
        $author_id = get_post_field( 'post_author', $post_id );
        $user      = get_user_by( 'ID', $author_id );
        echo esc_html( $user ? $user->display_name : '—' );
    }

    /**
     * Render a numeric score badge (SEO or GEO).
     */
    private function render_score_column( int $post_id, string $meta_key ): void {
        $score = get_post_meta( $post_id, $meta_key, true );

        if ( $score === '' || $score === null ) {
            echo '<span style="color: #999;">—</span>';
            return;
        }

        $score = (int) $score;
        $color = '#999';

        if ( $score >= 80 ) {
            $color = '#00a32a'; // green
        } elseif ( $score >= 50 ) {
            $color = '#dba617'; // amber
        } elseif ( $score > 0 ) {
            $color = '#d63638'; // red
        }

        printf(
            '<span style="display: inline-block; min-width: 32px; padding: 2px 8px; border-radius: 12px; background: %s; color: #fff; font-weight: 600; text-align: center; font-size: 12px;">%d</span>',
            esc_attr( $color ),
            $score
        );
    }

    /**
     * Render the distribution (cross-site clone) column.
     */
    private function render_distribution_column( int $post_id ): void {
        $allocations = AllocationTable::get_for_post( $post_id );

        if ( empty( $allocations ) ) {
            echo '<span style="color: #999;">Hub Only</span>';
            return;
        }

        $badges = [];
        foreach ( $allocations as $alloc ) {
            $blog_id   = (int) $alloc['blog_id'];
            $site_name = $this->get_site_label( $blog_id );
            $rewritten = (bool) ( $alloc['rewrite_applied'] ?? false );
            $badge     = $rewritten
                ? sprintf( '<span style="background:#dba617;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;margin-right:4px;">%s (RW)</span>', esc_html( $site_name ) )
                : sprintf( '<span style="background:#2271b1;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;margin-right:4px;">%s</span>', esc_html( $site_name ) );
            $badges[] = $badge;
        }

        echo implode( '', $badges );
    }

    /**
     * Render content type — default to "Article" for posts without a term.
     */
    private function render_content_type_column( int $post_id ): void {
        $terms = get_the_terms( $post_id, 'content_type' );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            echo esc_html( $terms[0]->name );
            return;
        }
        echo esc_html__( 'Article', 'kh-editorial-intelligence' );
    }

    /**
     * Map blog_id to a human-readable label.
     */
    private function get_site_label( int $blog_id ): string {
        $map = [
            1 => 'Hub',
            2 => 'UK',
        ];

        if ( isset( $map[ $blog_id ] ) ) {
            return $map[ $blog_id ];
        }

        $details = get_blog_details( $blog_id );
        if ( $details ) {
            return $details->blogname;
        }

        return 'Site ' . $blog_id;
    }

    /**
     * Inline admin styles for column widths and badges.
     */
    public function column_styles(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'edit-post' !== $screen->id ) {
            return;
        }
        ?>
        <style>
            .fixed .column-kh_seo_score,
            .fixed .column-kh_geo_score {
                width: 70px;
                text-align: center;
            }
            .fixed .column-kh_distribution {
                width: 180px;
            }
            .fixed .column-kh_author {
                width: 140px;
            }
            .fixed .column-content_type,
            .fixed .column-taxonomy-content_type {
                width: 100px;
            }
        </style>
        <?php
    }
}