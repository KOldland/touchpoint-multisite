<?php
namespace KH_SMMA\Integration;

use function absint;
use function add_filter;
use function apply_filters;
use function get_current_user_id;
use function get_post;
use function get_permalink;
use function get_post_thumbnail_id;
use function wp_get_attachment_image_url;
use function wp_strip_all_tags;
use function wp_trim_words;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MarketingSuiteBridge {
    public function register() {
        add_filter( 'kh_smma_marketing_assets', array( $this, 'inject_library_assets' ) );
        add_filter( 'kh_smma_resolve_asset_content', array( $this, 'resolve_asset_content' ), 10, 2 );
    }

    public function inject_library_assets( $assets ) {
        $library_assets = $this->get_library_assets();
        if ( empty( $library_assets ) ) {
            return $assets;
        }

        foreach ( $library_assets as $asset_id => $label ) {
            $assets[ $asset_id ] = $label;
        }

        return $assets;
    }

    public function resolve_asset_content( $content, $asset_id ) {
        if ( strpos( $asset_id, 'library_' ) !== 0 ) {
            return $content;
        }

        $post_id = absint( substr( $asset_id, strlen( 'library_' ) ) );
        if ( ! $post_id ) {
            return $content;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return $content;
        }

        $message = $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 60 );
        $message = apply_filters( 'kh_smma_library_asset_message', $message, $post );

        $asset = array(
            'message' => $message ?: '',
            'link'    => get_permalink( $post ),
        );

        $thumb_id = get_post_thumbnail_id( $post );
        if ( $thumb_id ) {
            $asset['media'] = array(
                'type' => 'image',
                'url'  => wp_get_attachment_image_url( $thumb_id, 'large' ),
            );
        }

        return $asset;
    }

    private function get_library_assets() {
        if ( ! class_exists( '\\KHM\\Services\\PluginRegistry' ) ) {
            return array();
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return array();
        }

        try {
            $items = \KHM\Services\PluginRegistry::call_service( 'get_member_library', $user_id, array( 'limit' => 25 ) );
        } catch ( \Throwable $e ) {
            $items = array();
        }

        if ( empty( $items ) ) {
            return array();
        }

        $assets = array();
        foreach ( $items as $item ) {
            if ( empty( $item->post_id ) ) {
                continue;
            }

            $assets[ 'library_' . $item->post_id ] = sprintf(
                /* translators: %s: library asset title */
                __( 'Library: %s', 'kh-smma' ),
                $item->post_title ?: __( 'Untitled asset', 'kh-smma' )
            );
        }

        return $assets;
    }
}
