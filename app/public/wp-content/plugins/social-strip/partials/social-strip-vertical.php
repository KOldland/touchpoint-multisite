<?php
$widget_data = $data;
$post_id = $widget_data['post_id'];
$icon_base = $widget_data['icon_base'];
?>

<div class="kss-social-strip kss-vertical" data-post-id="<?= esc_attr($post_id); ?>">
    
    <?php if ($widget_data['is_logged_in']): ?>
        <?php 
        $has_downloaded = $widget_data['credits']['has_downloaded'] ?? false;
        $download_title = $has_downloaded 
            ? 'Redownload (already downloaded)' 
            : 'Download (' . $widget_data['credits']['required'] . ' credit' . ($widget_data['credits']['required'] == 1 ? '' : 's') . ') - ' . $widget_data['credits']['available'] . ' remaining';
        ?>
        <!-- Credit Download Button - always clickable for logged-in users, modal will handle eligibility -->
        <button class="kss-download-credit kss-icon <?= $has_downloaded ? 'downloaded' : ''; ?>" 
                data-post-id="<?= esc_attr($post_id); ?>" 
                title="<?= esc_attr($download_title); ?>">
            <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
        </button>
    <?php else: ?>
        <!-- Login Required for Download -->
        <button class="kss-icon disabled" title="Login required for download">
            <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
        </button>
    <?php endif; ?>

    <?php if ($widget_data['is_logged_in']): ?>
        <!-- Save to Library Button -->
        <button class="kss-save-button <?= $widget_data['library']['is_saved'] ? 'saved' : ''; ?>" 
                data-post-id="<?= esc_attr($post_id); ?>" 
                title="<?= $widget_data['library']['is_saved'] ? 'Saved to Library' : 'Save to Library'; ?>">
            <img src="<?= esc_url($icon_base . 'bookmark.png'); ?>" alt="Save">
        </button>
    <?php else: ?>
        <!-- Login Required for Save -->
        <button class="kss-save-button disabled" title="Login required to save">
            <img src="<?= esc_url($icon_base . 'bookmark.png'); ?>" alt="Save">
        </button>
    <?php endif; ?>

    <?php $is_purchased = $widget_data['purchase']['is_purchased'] ?? false; ?>
    <!-- Buy Button -->
    <button class="kss-buy-button <?= $is_purchased ? 'purchased' : ''; ?>"
            data-post-id="<?= esc_attr($post_id); ?>"
            data-title="<?= esc_attr(get_the_title($post_id)); ?>"
            data-image="<?= esc_url(get_the_post_thumbnail_url($post_id, 'medium') ?: ''); ?>"
            data-purchased="<?= $is_purchased ? '1' : '0'; ?>"
            title="<?= $is_purchased ? 'Purchased' : 'Buy (' . $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2) . ')'; ?>">
        <img src="<?= esc_url($icon_base . 'buy.png'); ?>" alt="Buy PDF">
    </button>
    
    <!-- Gift Button -->
    <button class="kss-gift-button" 
            data-post-id="<?= esc_attr($post_id); ?>" 
            title="Send as Gift (<?= $widget_data['pricing']['currency'] . number_format($widget_data['gift']['price'], 2); ?>)">
        <img src="<?= esc_url($icon_base . 'gift.png'); ?>" alt="Gift Article">
    </button>

    <!-- Share Button (always available) -->
    <button class="ssm-share-trigger" 
            data-title="<?= esc_attr($widget_data['share']['title']); ?>" 
            data-url="<?= esc_url($widget_data['share']['url']); ?>" 
            title="Share">
        <img src="<?= esc_url($icon_base . 'share.png'); ?>" alt="Share Article">
    </button>
    
</div>
