<?php
require_once __DIR__ . '/wp-load.php';

$export_dir = __DIR__ . '/elementor-exports';
if (! is_dir($export_dir)) mkdir($export_dir, 0755, true);

// Query: include drafts/published/any status, and look for "Recovered:" in title
$args = [
    'post_type' => 'elementor_library',
    'posts_per_page' => -1,
    'post_status' => 'any',    // include drafts
    's' => 'Recovered:',
];

$posts = get_posts($args);

if (empty($posts)) {
    echo "No elementor_library posts found with 'Recovered:' in the title. Exiting.\n";
    exit(0);
}

$exported = 0;
foreach ($posts as $post) {
    $post_id = $post->ID;
    $title = $post->post_title;
    $slug = sanitize_title($title);
    $filename = "{$export_dir}/{$post_id}-{$slug}.json";

    echo "Exporting {$post_id} — {$title} -> {$filename}\n";

    $json_output = null;

    // Try Elementor's official exporter if available
    if ( class_exists('\Elementor\Plugin') ) {
        $templates_manager = \Elementor\Plugin::instance()->templates_manager ?? null;
        if ( $templates_manager && method_exists($templates_manager, 'export_template') ) {
            try {
                // export_template expects an array of args (not an int)
                $json_output = $templates_manager->export_template( ['id' => $post_id] );
            } catch ( \Throwable $e ) {
                // catch Throwable (TypeError, Error, Exception)
                echo "  Elementor export_template() threw: " . $e->getMessage() . "\n";
                $json_output = null;
            }
        }
    }

    // Fallback: wrap the recovered postmeta into a JSON wrapper
    if ( ! $json_output ) {
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        $decoded = null;
        if ( is_string( $elementor_data ) ) {
            $decoded = json_decode( $elementor_data, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) $decoded = $elementor_data;
        } else {
            $decoded = $elementor_data;
        }

        $wrapper = [
            'exported_with' => 'custom-exporter-v1',
            'elementor_version' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '',
            'post_id' => $post_id,
            'title' => $title,
            'type' => get_post_meta( $post_id, '_elementor_template_type', true ),
            'conditions' => get_post_meta( $post_id, '_elementor_conditions', true ),
            'data' => $decoded,
        ];

        $json_output = json_encode( $wrapper, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    }

    if ( false === file_put_contents( $filename, $json_output ) ) {
        echo "  ERROR: Failed to write {$filename}\n";
    } else {
        echo "  Wrote {$filename}\n";
        $exported++;
    }
}

echo "Done. Exported {$exported} templates to {$export_dir}\n";
