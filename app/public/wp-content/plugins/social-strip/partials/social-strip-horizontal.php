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

<hr class="kss-divider">
<div class="kss-social-strip kss-horizontal">

    <?php if ($widget_data['features']['can_download']): ?>
        <div class="kss-action">
            <button class="kss-download-credit kss-icon"
                    data-post-id="<?= esc_attr($post_id); ?>"
                    title="Download (1 credit) - <?= $widget_data['credits']['available']; ?> remaining">
                <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
            </button>
            <span class="kss-label">Download PDF (1 credit)</span>
        </div>
    <?php elseif (!$widget_data['is_logged_in']): ?>
        <div class="kss-action">
            <button class="kss-icon disabled" title="Login required for download">
                <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
            </button>
            <span class="kss-label">Login to Download</span>
        </div>
    <?php else: ?>
        <div class="kss-action">
            <button class="kss-icon disabled" title="Insufficient credits">
                <img src="<?= esc_url($icon_base . 'download.png'); ?>" alt="Download">
            </button>
            <span class="kss-label">Insufficient Credits</span>
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

    <?php if ($widget_data['features']['can_buy']): ?>
        <div class="kss-action">
            <button class="kss-buy-button kss-add-to-cart"
                    data-post-id="<?= esc_attr($post_id); ?>"
                    title="Buy (<?= $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2); ?>)">
                <img src="<?= esc_url($icon_base . 'buy.png'); ?>" alt="Buy PDF">
            </button>
            <span class="kss-label">Buy PDF (<?= $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2); ?>)</span>
        </div>

        <?php if ($widget_data['features']['can_gift']): ?>
            <div class="kss-action">
                <button class="kss-gift-button"
                        data-post-id="<?= esc_attr($post_id); ?>"
                        title="Send as Gift (<?= $widget_data['pricing']['currency'] . number_format($widget_data['pricing']['member_price'], 2); ?>)">
                    <img src="<?= esc_url($icon_base . 'gift.png'); ?>" alt="Gift Article">
                </button>
                <span class="kss-label">Send Article as a Gift</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="kss-action">
        <button class="ssm-share-trigger"
                data-title="<?= esc_attr($widget_data['share']['title']); ?>"
                data-url="<?= esc_url($widget_data['share']['url']); ?>"
                title="Share">
            <img src="<?= esc_url($icon_base . 'share.png'); ?>" alt="Share Article">
        </button>
        <span class="kss-label">Share via Email & Socials</span>
    </div>

    <?php if ($widget_data['features']['show_member_benefits']): ?>
        <div class="kss-member-benefits">
            <span class="kss-member-label"><?= esc_html($widget_data['membership']['level']); ?> Member Benefits</span>
        </div>
    <?php endif; ?>

</div>
