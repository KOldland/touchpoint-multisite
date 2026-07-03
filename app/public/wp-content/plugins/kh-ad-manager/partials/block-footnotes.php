<?php
/**
 * Render template for acf/footnotes block.
 *
 * Displays citation footnotes with reference text and links.
 * Used by ACF block registration for backend/frontend rendering.
 *
 * @param array $block The block settings and attributes.
 * @param string $content The block inner HTML (empty for ACF blocks).
 * @param bool $is_preview True during AJAX preview.
 * @param int $post_id The post ID this block is on.
 */

// Load ACF field values
$footnotes = get_field( 'footnotes' ) ?: [];

if ( empty( $footnotes ) ) {
    return; // Nothing to render
}
?>
<div class="kh-footnotes-block">
    <ol class="kh-footnotes-list">
    <?php foreach ( $footnotes as $note ): ?>
        <li class="kh-footnote-item">
            <span class="kh-footnote-text"><?php echo esc_html( $note['reference_text'] ?? '' ); ?></span>
            <?php if ( ! empty( $note['reference_link'] ) ): ?>
                <a href="<?php echo esc_url( $note['reference_link'] ); ?>" 
                   class="kh-footnote-link" 
                   target="_blank" 
                   rel="noopener noreferrer">
                    <?php esc_html_e( 'Source', 'kh-ad-manager' ); ?>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ol>
</div>