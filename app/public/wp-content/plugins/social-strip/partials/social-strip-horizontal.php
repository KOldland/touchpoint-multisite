<?php
$widget_data = $data;
$post_id = $widget_data['post_id'];
$icon_base = $widget_data['icon_base'];
?>

<hr class="kss-divider">
<div class="kss-social-strip kss-horizontal">

    <?php if ($widget_data['is_logged_in']): ?>
        <?php 
        $has_downloaded = $widget_data['credits']['has_downloaded'] ?? false;
        $download_title = $has_downloaded 
            ? 'Redownload (already downloaded)' 
            : 'Download (' . $widget_data['credits']['required'] . ' credit' . ($widget_data['credits']['required'] == 1 ? '' : 's') . ') - ' . $widget_data['credits']['available'] . ' remaining';
        $download_label = $has_downloaded 
            ? 'Redownload PDF' 
            : 'Download PDF (' . $widget_data['credits']['required'] . ' credit' . ($widget_data['credits']['required'] == 1 ? '' : 's') . ')';
        ?>
        <div class="kss-action">
            <button class="kss-download-credit kss-icon <?= $has_downloaded ? 'downloaded' : ''; ?>"
                    data-post-id="<?= esc_attr($post_id); ?>"
                    title="<?= esc_attr($download_title); ?>">
                <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
            </button>
            <span class="kss-label"><?= esc_html($download_label); ?></span>
        </div>
    <?php else: ?>
        <div class="kss-action">
            <button class="kss-icon disabled" title="Login required for download">
                <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
            </button>
            <span class="kss-label">Login to Download</span>
        </div>
    <?php endif; ?>

    <?php if ($widget_data['features']['can_save']): ?>
        <div class="kss-action">
            <button class="kss-save-button <?= $widget_data['library']['is_saved'] ? 'saved' : ''; ?>"
                    data-post-id="<?= esc_attr($post_id); ?>"
                    title="<?= $widget_data['library']['is_saved'] ? 'Saved to Library' : 'Save to Library'; ?>">
                <img src="<?= esc_url($icon_base . 'bookmark.png'); ?>" alt="Save to Library">
            </button>
            <span class="kss-label">Save to Online Library</span>
        </div>
    <?php else: ?>
        <div class="kss-action">
            <button class="kss-save-button disabled" title="Login required to save">
                <img src="<?= esc_url($icon_base . 'bookmark.png'); ?>" alt="Save to Library">
            </button>
            <span class="kss-label">Login to Save</span>
        </div>
    <?php endif; ?>

    <?php $is_purchased = $widget_data['purchase']['is_purchased'] ?? false; ?>
    <div class="kss-action">
        <button class="kss-buy-button <?= $is_purchased ? 'purchased' : ''; ?>"
                data-post-id="<?= esc_attr($post_id); ?>"
                data-title="<?= esc_attr(get_the_title($post_id)); ?>"
                data-image="<?= esc_url(get_the_post_thumbnail_url($post_id, 'medium') ?: ''); ?>"
                data-purchased="<?= $is_purchased ? '1' : '0'; ?>"
                title="<?= $is_purchased ? 'Purchased' : 'Buy (' . $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2) . ')'; ?>">
            <img src="<?= esc_url($icon_base . 'buy.png'); ?>" alt="Buy PDF">
        </button>
        <span class="kss-label"><?= $is_purchased ? 'Purchased' : 'Buy PDF (' . $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2) . ')'; ?></span>
    </div>

    <div class="kss-action">
        <button class="kss-gift-button"
                data-post-id="<?= esc_attr($post_id); ?>"
                title="Send as Gift (<?= $widget_data['pricing']['currency'] . number_format($widget_data['gift']['price'], 2); ?>)">
            <img src="<?= esc_url($icon_base . 'gift.png'); ?>" alt="Gift Article">
        </button>
        <span class="kss-label">Send Article as a Gift (<?= $widget_data['pricing']['currency'] . number_format($widget_data['gift']['price'], 2); ?>)</span>
    </div>

    <div class="kss-action">
        <button class="ssm-share-trigger"
                data-title="<?= esc_attr($widget_data['share']['title']); ?>"
                data-url="<?= esc_url($widget_data['share']['url']); ?>"
                data-excerpt="<?= esc_attr($widget_data['share']['excerpt']); ?>"
                data-image="<?= esc_url($widget_data['share']['image'] ?? get_the_post_thumbnail_url($post_id, 'medium')); ?>"
                data-post-id="<?= esc_attr($post_id); ?>"
                title="Share">
            <img src="<?= esc_url($icon_base . 'share.png'); ?>" alt="Share Article">
        </button>
        <span class="kss-label">Share via Email & Socials</span>
    </div>

</div>
