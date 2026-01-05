<?php
$widget_data = $data;
$post_id = $widget_data['post_id'];
$icon_base = $widget_data['icon_base'];
?>

<div class="kss-social-strip kss-vertical" data-post-id="<?= esc_attr($post_id); ?>">
    
    <?php if ($widget_data['is_logged_in'] && $widget_data['credits']['can_download']): ?>
        <!-- Credit Download Button -->
        <button class="kss-download-credit kss-icon" 
                data-post-id="<?= esc_attr($post_id); ?>" 
                title="Download (<?= esc_attr($widget_data['credits']['required']); ?> credit<?= $widget_data['credits']['required'] == 1 ? '' : 's'; ?>) - <?= $widget_data['credits']['available']; ?> remaining">
            <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
        </button>
    <?php elseif (!$widget_data['is_logged_in']): ?>
        <!-- Login Required for Download -->
        <button class="kss-icon disabled" title="Login required for download">
            <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
        </button>
    <?php else: ?>
        <!-- Insufficient Credits -->
        <button class="kss-icon disabled" title="Download (<?= esc_attr($widget_data['credits']['required']); ?> credit<?= $widget_data['credits']['required'] == 1 ? '' : 's'; ?>) - <?= $widget_data['credits']['available']; ?> remaining">
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

    <!-- Buy Button -->
    <button class="kss-buy-button kss-add-to-cart" 
            data-post-id="<?= esc_attr($post_id); ?>" 
            title="Buy (<?= $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2); ?>)">
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
    
    <?php if ($widget_data['is_logged_in'] && $widget_data['membership']['is_member']): ?>
        <!-- Member Status Indicator -->
        <div class="kss-member-indicator">
            <?= esc_html($widget_data['membership']['level']); ?> Member
        </div>
    <?php elseif (!$widget_data['is_logged_in']): ?>
        <!-- Guest Status Indicator -->
        <div class="kss-guest-indicator">
            Login for member benefits
        </div>
    <?php endif; ?>
    
</div>
