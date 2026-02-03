<?php

namespace KHM_SEO_AGENT\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Seo_Tools {
    public function get_page_content( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array( 'error' => 'Post not found.' );
        }

        $images = $this->extract_images( $post->post_content );

        return array(
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'meta' => array(
                'seo_title' => get_post_meta( $post->ID, '_khm_seo_title', true ),
                'meta_description' => get_post_meta( $post->ID, '_khm_seo_description', true ),
                'focus_keyword' => get_post_meta( $post->ID, '_khm_seo_focus_keyword', true ),
                'schema_flags' => get_post_meta( $post->ID, '_khm_seo_schema_config', true ),
            ),
            'images' => $images,
            'taxonomies' => $this->get_taxonomies( $post->ID ),
        );
    }

    public function analyze_content( $content, $keyword = '', $post_id = 0 ) {
        if ( ! function_exists( 'khm_seo' ) || ! khm_seo() ) {
            return array( 'error' => 'KHM SEO is not available.' );
        }

        $analysis_engine = khm_seo()->get_analysis_engine();
        if ( ! $analysis_engine ) {
            return array( 'error' => 'KHM SEO analysis engine is not available.' );
        }

        $data = array(
            'post_id' => (int) $post_id,
            'title' => $post_id ? get_the_title( $post_id ) : '',
            'content' => $content,
            'meta_description' => $post_id ? get_post_meta( $post_id, '_khm_seo_description', true ) : '',
            'focus_keyword' => $keyword,
        );

        return $analysis_engine->analyze( $data );
    }

    private function extract_images( $content ) {
        $images = array();
        if ( preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
            $ids = array_unique( array_map( 'intval', $matches[1] ) );
            foreach ( $ids as $id ) {
                $images[] = array(
                    'id' => $id,
                    'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ),
                );
            }
        }

        return $images;
    }

    private function get_taxonomies( $post_id ) {
        $taxonomies = array();
        $post_type = get_post_type( $post_id );
        $taxes = get_object_taxonomies( $post_type );
        foreach ( $taxes as $tax ) {
            $terms = get_the_terms( $post_id, $tax );
            $taxonomies[ $tax ] = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
        }

        return $taxonomies;
    }
}
