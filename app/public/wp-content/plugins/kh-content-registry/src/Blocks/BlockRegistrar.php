<?php
/**
 * BlockRegistrar
 *
 * Registers ACF Gutenberg blocks for the content registry lifecycle:
 * - acf/summary: Summary block (headline, summary_text, key_points)
 * - acf/commentary: Commentary block (commentary_text, source_name, source_url)
 * - acf/framework: Framework block (article_idea, overview, context, application, writer_guidance, observations)
 *
 * Each block stores data as post meta (ACF convention) and inserts a block comment
 * marker into post_content for Gutenberg editor compatibility.
 */

namespace KH\ContentRegistry\Blocks;

defined( 'ABSPATH' ) || exit;

class BlockRegistrar {

    /**
     * Hook into ACF init to register field groups.
     */
    public static function init() {
        add_action( 'acf/init', [ self::class, 'register_summary_block' ] );
        add_action( 'acf/init', [ self::class, 'register_commentary_block' ] );
        add_action( 'acf/init', [ self::class, 'register_framework_block' ] );
        add_action( 'acf/init', [ self::class, 'register_block_types' ] );
    }

    /**
     * Register Gutenberg block types with ACF (required for blocks to appear in editor).
     */
    public static function register_block_types() {
        if ( ! function_exists( 'acf_register_block_type' ) ) {
            return;
        }

        $templates_dir = dirname( __DIR__, 2 ) . '/templates/';

        acf_register_block_type( [
            'name'            => 'summary',
            'title'           => __( 'Summary', 'kh-content-registry' ),
            'description'     => __( 'Article summary with headline and key points.', 'kh-content-registry' ),
            'render_template' => $templates_dir . 'block-summary.php',
            'category'        => 'layout',
            'icon'            => 'editor-ul',
            'mode'            => 'preview',
            'supports'        => [ 'mode' => false ],
        ] );

        acf_register_block_type( [
            'name'            => 'commentary',
            'title'           => __( 'Commentary', 'kh-content-registry' ),
            'description'     => __( 'Expert commentary with source attribution.', 'kh-content-registry' ),
            'render_template' => $templates_dir . 'block-commentary.php',
            'category'        => 'layout',
            'icon'            => 'admin-comments',
            'mode'            => 'preview',
            'supports'        => [ 'mode' => false ],
        ] );

        acf_register_block_type( [
            'name'            => 'framework',
            'title'           => __( 'Framework', 'kh-content-registry' ),
            'description'     => __( 'Article framework with guidance sections.', 'kh-content-registry' ),
            'render_template' => $templates_dir . 'block-framework.php',
            'category'        => 'layout',
            'icon'            => 'editor-table',
            'mode'            => 'preview',
            'supports'        => [ 'mode' => false ],
        ] );

    }

    /**
     * Register the Summary block ACF field group.
     */
    public static function register_summary_block() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( [
            'key'      => 'group_kh_summary_block',
            'title'    => 'Summary Block',
            'fields'   => [
                [
                    'key'   => 'field_kh_summary_headline',
                    'label' => 'Headline',
                    'name'  => 'kh_summary_headline',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_kh_summary_text',
                    'label' => 'Summary Text',
                    'name'  => 'kh_summary_text',
                    'type'  => 'textarea',
                ],
                [
                    'key'      => 'field_kh_summary_key_points',
                    'label'    => 'Key Points',
                    'name'     => 'kh_summary_key_points',
                    'type'     => 'repeater',
                    'sub_fields' => [
                        [
                            'key'   => 'field_kh_summary_key_point_bullet',
                            'label' => 'Bullet',
                            'name'  => 'bullet',
                            'type'  => 'text',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/summary',
                    ],
                ],
            ],
        ] );
    }

    /**
     * Register the Commentary block ACF field group.
     */
    public static function register_commentary_block() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( [
            'key'      => 'group_kh_commentary_block',
            'title'    => 'Commentary Block',
            'fields'   => [
                [
                    'key'   => 'field_kh_commentary_text',
                    'label' => 'Commentary',
                    'name'  => 'kh_commentary_text',
                    'type'  => 'textarea',
                ],
                [
                    'key'   => 'field_kh_commentary_source_name',
                    'label' => 'Source Name',
                    'name'  => 'kh_commentary_source_name',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_kh_commentary_source_url',
                    'label' => 'Source URL',
                    'name'  => 'kh_commentary_source_url',
                    'type'  => 'url',
                ],
                [
                    'key'   => 'field_kh_commentary_source_company',
                    'label' => 'Source Company',
                    'name'  => 'kh_commentary_source_company',
                    'type'  => 'text',
                ],
                [
                    'key'   => 'field_kh_commentary_source_title',
                    'label' => 'Source Title',
                    'name'  => 'kh_commentary_source_title',
                    'type'  => 'text',
                ],
                [
                    'key'      => 'field_kh_commentary_citations',
                    'label'    => 'Citations',
                    'name'     => 'kh_commentary_citations',
                    'type'     => 'repeater',
                    'layout'   => 'table',
                    'sub_fields' => [
                        [
                            'key'   => 'field_kh_commentary_citation_title',
                            'label' => 'Title',
                            'name'  => 'title',
                            'type'  => 'text',
                        ],
                        [
                            'key'   => 'field_kh_commentary_citation_text',
                            'label' => 'Text',
                            'name'  => 'text',
                            'type'  => 'textarea',
                        ],
                        [
                            'key'   => 'field_kh_commentary_citation_url',
                            'label' => 'URL',
                            'name'  => 'url',
                            'type'  => 'url',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/commentary',
                    ],
                ],
            ],
        ] );
    }


    /**
     * Register the Framework block ACF field group.
     */
    public static function register_framework_block() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( [
            'key'      => 'group_kh_framework_block',
            'title'    => 'Framework Block',
            'fields'   => [
                [
                    'key'   => 'field_kh_framework_article_idea',
                    'label' => 'Article Idea',
                    'name'  => 'kh_framework_article_idea',
                    'type'  => 'textarea',
                ],
                [
                    'key'   => 'field_kh_framework_overview',
                    'label' => 'Overview',
                    'name'  => 'kh_framework_overview',
                    'type'  => 'textarea',
                ],
                [
                    'key'   => 'field_kh_framework_context',
                    'label' => 'Context',
                    'name'  => 'kh_framework_context',
                    'type'  => 'textarea',
                ],
                [
                    'key'   => 'field_kh_framework_application',
                    'label' => 'Application',
                    'name'  => 'kh_framework_application',
                    'type'  => 'textarea',
                ],
                [
                    'key'   => 'field_kh_framework_writer_guidance',
                    'label' => 'Writer Guidance',
                    'name'  => 'kh_framework_writer_guidance',
                    'type'  => 'textarea',
                ],
                [
                    'key'   => 'field_kh_framework_observations',
                    'label' => 'Observations',
                    'name'  => 'kh_framework_observations',
                    'type'  => 'textarea',
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'block',
                        'operator' => '==',
                        'value'    => 'acf/framework',
                    ],
                ],
            ],
        ] );
    }

    /**
     * Map of field names to their ACF field keys for each block type.
     */
    private static function get_field_keys( string $block_name ): array {
        $maps = [
            'acf/summary' => [
                'kh_summary_headline' => 'field_kh_summary_headline',
                'kh_summary_text'     => 'field_kh_summary_text',
                'kh_summary_key_points' => 'field_kh_summary_key_points',
            ],
            'acf/commentary' => [
                'kh_commentary_text'         => 'field_kh_commentary_text',
                'kh_commentary_source_name'  => 'field_kh_commentary_source_name',
                'kh_commentary_source_url'   => 'field_kh_commentary_source_url',
                'kh_commentary_source_company' => 'field_kh_commentary_source_company',
                'kh_commentary_source_title'   => 'field_kh_commentary_source_title',
                'kh_commentary_citations'      => 'field_kh_commentary_citations',
            ],

            'acf/framework' => [
                'kh_framework_article_idea'    => 'field_kh_framework_article_idea',
                'kh_framework_overview'        => 'field_kh_framework_overview',
                'kh_framework_context'         => 'field_kh_framework_context',
                'kh_framework_application'     => 'field_kh_framework_application',
                'kh_framework_writer_guidance' => 'field_kh_framework_writer_guidance',
                'kh_framework_observations'    => 'field_kh_framework_observations',
            ],

        ];
        return $maps[$block_name] ?? [];
    }

    public static function build_block_marker( string $block_name, array $data ): string {
        // Note: ACF reads field values from post meta, not the block JSON data.
        // The block JSON data is used for the editor form.
        // For ACF blocks with local field groups, the field key references (_field_name)
        // help ACF map the block data to the correct field group in the editor.
        $field_keys = self::get_field_keys( $block_name );
        foreach ( $field_keys as $field_name => $field_key ) {
            $data[ "_$field_name" ] = $field_key;
        }
        $block_json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return sprintf(
            '<!-- wp:%s {"name":"%s","data":%s,"mode":"preview"} /-->',
            $block_name,
            $block_name,
            $block_json
        );
    }

    /**
     * Insert or replace a block marker in post content.
     *
     * @param string $content     The current post content.
     * @param string $block_name  The ACF block name (e.g. 'acf/summary').
     * @param string $block_marker The block comment to insert.
     * @return string Updated post content.
     */
    public static function upsert_block_in_content( string $content, string $block_name, string $block_marker ): string {
        // Escape block name for regex: acf/summary -> acf\/summary
        $escaped_name = preg_quote( $block_name, '/' );
        // Match the entire block comment (including the closing /-->)
        $pattern = '/<!-- wp:' . $escaped_name . ' .*? \/-->\s*/s';

        // Remove ANY existing blocks of this type (handles duplicates)
        $cleaned_content = preg_replace( $pattern, '', $content );

        // Prepend the single correct block
        return $block_marker . "\n\n" . $cleaned_content;
    }

    /**
     * Store block data as post meta (ACF-compatible format).
     *
     * @param int    $post_id    The WordPress post ID.
     * @param string $prefix     Meta key prefix (e.g. 'kh_summary').
     * @param array  $data       Flat key-value data.
     */
    public static function store_block_meta( int $post_id, string $prefix, array $data ) {
        foreach ( $data as $key => $value ) {
            $meta_key = "{$prefix}_{$key}";
            if ( is_array( $value ) ) {
                // Detect associative vs sequential array
                $is_assoc = array_keys( $value ) !== range( 0, count( $value ) - 1 );

                if ( $is_assoc ) {
                    // Group field: each key is a sub-field, store as prefix_key_subkey
                    foreach ( $value as $sub_key => $sub_value ) {
                        update_post_meta( $post_id, "{$prefix}_{$key}_{$sub_key}", sanitize_text_field( $sub_value ) );
                    }
                } else {
                    // Repeater field: each item is an assoc array of sub_fields
                    // e.g. key_points => [['bullet' => 'a'], ['bullet' => 'b']]
                    $count = 0;
                    foreach ( $value as $item ) {
                        if ( is_array( $item ) ) {
                            foreach ( $item as $sub_key => $sub_value ) {
                                update_post_meta( $post_id, "{$prefix}_{$key}_{$count}_{$sub_key}", sanitize_text_field( $sub_value ) );
                            }
                            $count++;
                        } elseif ( is_string( $item ) ) {
                            // Simple string array: store each item with incrementing index
                            update_post_meta( $post_id, "{$prefix}_{$key}_{$count}_bullet", sanitize_text_field( $item ) );
                            $count++;
                        }
                    }
                    // ACF uses the base key to store the row count
                    update_post_meta( $post_id, "{$prefix}_{$key}", $count );
                }
            } else {
                update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $value ) );
            }
        }
    }
}