<?php
    add_action('add_meta_boxes', function() {
        add_meta_box(
            'ad_preview_box',
            'Ad Preview',
            function($post) {
                // This div holds the ad preview HTML, but is hidden
                echo '<div id="hidden-ad-preview-content" style="display:none;">';
                if (function_exists('kh_render_ad')) {
                    // --- DYNAMIC OVERLAY LOGIC FOR PREVIEW ---
                    $ad_format = kh_ad_get_meta($post->ID, 'ad_format');
                    $ad_slot_obj = kh_ad_get_meta($post->ID, 'ad_slot_selector'); // Tax field, usually array/object
                    $ad_slot_slug = '';
                    if (is_array($ad_slot_obj) && isset($ad_slot_obj[0]->slug)) {
                        $ad_slot_slug = $ad_slot_obj[0]->slug;
                    } elseif (is_object($ad_slot_obj) && isset($ad_slot_obj->slug)) {
                        $ad_slot_slug = $ad_slot_obj->slug;
                    } elseif (is_string($ad_slot_obj)) {
                        $ad_slot_slug = $ad_slot_obj;
                    }
                    
                    // Match the preview "chrome" for your dynamic types
                    $is_modal  = in_array($ad_slot_slug, ['pop-up', 'exit-overlay', 'central-popup']);
                    $is_slide  = in_array($ad_slot_slug, ['slide-in', 'bottom-slidein']);
                    $is_ticker = in_array($ad_slot_slug, ['ticker', 'top-ticker']);
                    
                    if ($is_modal) {
                        echo '<div class="ad-modal" style="display:flex;align-items:center;justify-content:center;background:rgba(20,20,20,0.85);min-height:400px;position:relative;">';
                        kh_render_ad($post->ID);
                        echo '<button class="ad-close" style="position:absolute;top:20px;right:20px;font-size:2em;background:none;border:none;color:#fff;opacity:0.7;cursor:pointer;">✕</button>';
                        echo '</div>';
                    } elseif ($is_slide) {
                        echo '<div class="ad-slidein" style="display:flex;align-items:center;justify-content:center;">';
                        kh_render_ad($post->ID);
                        echo '<button class="ad-close" style="position:absolute;top:8px;right:12px;font-size:1.5em;background:none;border:none;color:#222;opacity:0.6;cursor:pointer;">✕</button>';
                        echo '</div>';
                    } elseif ($is_ticker) {
                        echo '<div class="ad-ticker" style="background:#232323;color:#fff;display:flex;align-items:center;justify-content:center;height:90px;position:relative;">';
                        kh_render_ad($post->ID);
                        echo '<button class="ad-close" style="position:absolute;top:8px;right:16px;font-size:1.2em;background:none;border:none;color:#fff;opacity:0.5;cursor:pointer;">✕</button>';
                        echo '</div>';
                    } else {
                        // Normal (display) preview
                        kh_render_ad($post->ID);
                    }
                } else {
                    echo '<em>Renderer function not found. Check plugin load order.</em>';
                }
                echo '</div>
                <button type="button" class="button button-primary" id="ad-preview-modal-btn" style="width:100%;margin:6px 0;">Preview Ad</button>
                <div id="ad-preview-modal-bg" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.7);z-index:9999;">
                    <div id="ad-preview-modal" style="background:#fff;padding:30px 40px;max-width:90vw;max-height:90vh;overflow:auto;margin:40px auto;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,0.2);"></div>
                </div>
            ';
            },
            'ad_unit',
            'side',
            'low'
        );
    });
