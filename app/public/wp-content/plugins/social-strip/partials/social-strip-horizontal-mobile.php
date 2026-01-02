<?php
// Get enhanced KHM data if available
$enhanced_data = function_exists('kss_get_enhanced_widget_data')
    ? kss_get_enhanced_widget_data($data['post_id'])
    : [];

// Merge with original data
$widget_data = array_merge($data, $enhanced_data);
$post_id = $widget_data['post_id'];
$icon_base = $widget_data['icon_base'];
?>

<div class="kss-social-strip kss-horizontal kss-horizontal-mobile">
    <?php if ($widget_data['features']['can_download']): ?>
        <button class="kss-download-credit kss-icon"
                data-post-id="<?= esc_attr($post_id); ?>"
                title="Download (1 credit)">
            <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
        </button>
    <?php elseif (!$widget_data['is_logged_in']): ?>
        <button class="kss-icon disabled" title="Login required">
            <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
        </button>
    <?php else: ?>
        <button class="kss-icon disabled" title="Insufficient credits">
            <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
        </button>
    <?php endif; ?>

    <?php if ($widget_data['features']['can_save']): ?>
        <button class="kss-save-button <?= $widget_data['library']['is_saved'] ? 'saved' : ''; ?>"
                data-post-id="<?= esc_attr($post_id); ?>"
                title="Save to Library">
            <img src="<?= esc_url($icon_base . 'bookmark.png'); ?>" alt="Save">
        </button>
    <?php else: ?>
        <button class="kss-save-button disabled" title="Login required">
            <img src="<?= esc_url($icon_base . 'bookmark.png'); ?>" alt="Save">
        </button>
    <?php endif; ?>

    <?php if ($widget_data['features']['can_buy']): ?>
        <button class="kss-buy-button kss-add-to-cart"
                data-post-id="<?= esc_attr($post_id); ?>"
                title="Buy (<?= $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2); ?>)">
            <img src="<?= esc_url($icon_base . 'buy.png'); ?>" alt="Buy PDF">
        </button>

        <?php if ($widget_data['features']['can_gift']): ?>
            <button class="kss-gift-button"
                    data-post-id="<?= esc_attr($post_id); ?>"
                    title="Send as Gift (<?= $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2); ?>)">
                <img src="<?= esc_url($icon_base . 'gift.png'); ?>" alt="Gift Article">
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <button class="ssm-share-trigger"
            data-title="<?= esc_attr($widget_data['share']['title']); ?>"
            data-url="<?= esc_url($widget_data['share']['url']); ?>"
            title="Share">
        <img src="<?= esc_url($icon_base . 'share.png'); ?>" alt="Share Article">
    </button>
</div>
