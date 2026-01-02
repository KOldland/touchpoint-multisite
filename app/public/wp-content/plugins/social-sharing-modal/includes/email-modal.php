<?php

// Render the modal HTML via shortcode
function ssm_modal_shortcode() {
    ob_start(); ?>
    <div id="ssm-modal" class="ssm-hidden">
        <div class="ssm-content">
            <button class="ssm-close" style="float:right;">&times;</button>
            <h2>Share this article</h2>
            <form method="post" action="mailto:?subject=Check out this article&body=<?php echo esc_url(get_permalink()); ?>">
                <label for="email">Recipient Email:</label><br>
                <input type="email" name="email" required>
                <input type="submit" value="Send">
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ssm_modal', 'ssm_modal_shortcode');
