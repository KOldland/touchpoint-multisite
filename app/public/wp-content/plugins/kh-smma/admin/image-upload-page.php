<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap kh-smma-images-page">
    <h1><?php esc_html_e( 'Image Uploads & Layout Preview', 'kh-smma' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Select images from the WordPress media library, preview recommended layouts, and save a demo compose mapping.', 'kh-smma' ); ?>
    </p>

    <div class="kh-smma-images-toolbar">
        <label for="kh-smma-images-reference">
            <?php esc_html_e( 'Reference ID', 'kh-smma' ); ?>
        </label>
        <input type="text" id="kh-smma-images-reference" value="phase3-demo-post-1" />

        <button type="button" class="button button-primary" id="kh-smma-images-open-media">
            <?php esc_html_e( 'Select Images', 'kh-smma' ); ?>
        </button>
        <button type="button" class="button" id="kh-smma-images-load-layouts">
            <?php esc_html_e( 'Refresh Layouts', 'kh-smma' ); ?>
        </button>
        <button type="button" class="button" id="kh-smma-images-save-compose">
            <?php esc_html_e( 'Save Compose', 'kh-smma' ); ?>
        </button>
    </div>

    <div class="kh-smma-images-shell">
        <section class="kh-smma-images-panel" aria-labelledby="kh-smma-images-selected-title">
            <div class="kh-smma-images-panel__header">
                <h2 id="kh-smma-images-selected-title"><?php esc_html_e( 'Selected Images', 'kh-smma' ); ?></h2>
                <p><?php esc_html_e( 'Drag cards to reorder them before assigning to layout slots.', 'kh-smma' ); ?></p>
            </div>
            <div id="kh-smma-images-selected" class="kh-smma-images-selected" aria-live="polite"></div>
        </section>

        <section class="kh-smma-images-panel" aria-labelledby="kh-smma-images-layouts-title">
            <div class="kh-smma-images-panel__header">
                <h2 id="kh-smma-images-layouts-title"><?php esc_html_e( 'Recommended Layouts', 'kh-smma' ); ?></h2>
                <p><?php esc_html_e( 'Recommendations are loaded from deterministic fixtures for the demo.', 'kh-smma' ); ?></p>
            </div>
            <div id="kh-smma-images-layouts" class="kh-smma-images-layouts"></div>
        </section>
    </div>

    <section class="kh-smma-images-preview" aria-labelledby="kh-smma-images-preview-title">
        <div class="kh-smma-images-panel__header">
            <h2 id="kh-smma-images-preview-title"><?php esc_html_e( 'Compose Preview', 'kh-smma' ); ?></h2>
            <p><?php esc_html_e( 'Preview the selected layout using the fixture-backed compose response.', 'kh-smma' ); ?></p>
        </div>
        <div id="kh-smma-images-preview-frame" class="kh-smma-images-preview__frame" tabindex="0"></div>
        <div id="kh-smma-images-preview-meta" class="kh-smma-images-preview__meta"></div>
    </section>

    <p id="kh-smma-images-status" class="kh-smma-images-status" aria-live="polite"></p>
</div>
