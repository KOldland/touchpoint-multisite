<?php
/**
 * Framework Generator Citation QA Gutenberg Block
 */

namespace Dual_GPT\Blocks\CitationQA;

defined('ABSPATH') || exit;

/**
 * Register the Citation QA block
 */
function register_citation_qa_block() {
    $block_dir = __DIR__;

    register_block_type($block_dir, array(
        'render_callback' => __NAMESPACE__ . '\\render_citation_qa_block',
    ));
}
add_action('init', __NAMESPACE__ . '\\register_citation_qa_block');

/**
 * Render callback for the Citation QA block
 */
function render_citation_qa_block($attributes) {
    $session_id = $attributes['sessionId'] ?? '';
    $brief_id = $attributes['briefId'] ?? '';

    if (empty($session_id) || empty($brief_id)) {
        return '<p>Citation QA: Please configure session ID and brief ID.</p>';
    }

    // Only show in editor
    if (!is_admin()) {
        return '';
    }

    ob_start();
    ?>
    <div class="fg-citation-qa-block" data-session-id="<?php echo esc_attr($session_id); ?>" data-brief-id="<?php echo esc_attr($brief_id); ?>">
        <p>Citation QA Block - Session: <?php echo esc_html($session_id); ?>, Brief: <?php echo esc_html($brief_id); ?></p>
        <button class="button fg-open-qa-modal">Open Citation QA</button>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue block assets
 */
function enqueue_citation_qa_assets() {
    $asset_file = __DIR__ . '/build/index.asset.php';
    if (file_exists($asset_file)) {
        $assets = include $asset_file;
        wp_enqueue_script(
            'fg-citation-qa-editor',
            plugins_url('build/index.js', __FILE__),
            $assets['dependencies'],
            $assets['version'],
            true
        );
        wp_enqueue_style(
            'fg-citation-qa-editor',
            plugins_url('build/index.css', __FILE__),
            array(),
            filemtime(__DIR__ . '/build/index.css')
        );
    }
}
add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_citation_qa_assets');