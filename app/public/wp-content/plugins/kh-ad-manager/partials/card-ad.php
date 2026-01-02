<?php
    if (!isset($context) || !is_array($context)) return;
    
    $headline     = $context['headline'] ?? '';
    $subheading   = $context['subheading'] ?? '';
    $body         = $context['body'] ?? '';
    $button_text  = $context['button_text'] ?? '';
    $button_url   = $context['button_url'] ?? '';
    $bg_color     = $context['bg_color'] ?? '#000066';
    $text_color   = $context['text_color'] ?? '#ffffff';
    $slot         = $context['slot'] ?? 'slide-in';
    
    $btn_bg   = $text_color;
    $btn_text = $bg_color;
    
    // Layout class: either 'ad-modal' or 'ad-slidein'
    $container_class = $slot === 'pop-up' ? 'ad-modal' : 'ad-slidein';
?>

<div class="<?= esc_attr($container_class) ?>">
    <div class="card-ad-wrapper" style="background:<?= esc_attr($bg_color) ?>; color:<?= esc_attr($text_color) ?>;">
        <?php if ($headline): ?>
        <h2 class="card-ad-headline"><?= esc_html($headline) ?></h2>
        <?php endif; ?>
        
        <?php if ($subheading): ?>
        <h3 class="card-ad-subheading"><?= esc_html($subheading) ?></h3>
        <?php endif; ?>
        
        <?php if ($body): ?>
        <div class="card-ad-body"><?= wpautop(esc_html($body)) ?></div>
        <?php endif; ?>
        
        <?php if ($button_text && $button_url): ?>
        <a href="<?= esc_url($button_url) ?>" class="card-ad-button" data-kh-ad-click="<?= esc_attr($context['ad_id'] ?? '') ?>"
            style="background:<?= esc_attr($btn_bg) ?>; color:<?= esc_attr($btn_text) ?>;">
            <?= esc_html($button_text) ?>
        </a>
        <?php endif; ?>
    </div>
</div>
