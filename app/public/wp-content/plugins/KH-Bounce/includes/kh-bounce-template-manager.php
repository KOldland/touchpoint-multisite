<?php
/**
 * Handles template metadata and markup rendering.
 */
class KH_Bounce_Template_Manager {

    /**
     * Return template definitions.
     *
     * @return array
     */
    public static function get_templates() {
        return array(
            'classic' => array(
                'label'       => __( 'Classic (center modal)', 'kh-bounce' ),
                'description' => __( 'Full-screen dimmed overlay with a card-based hero layout.', 'kh-bounce' ),
            ),
            'minimal' => array(
                'label'       => __( 'Minimal (bottom slide-up)', 'kh-bounce' ),
                'description' => __( 'Bottom anchored toast with concise copy for lighter interruptions.', 'kh-bounce' ),
            ),
        );
    }

    /**
     * Render the full modal markup for the frontend.
     */
    public static function render_modal( array $settings ) {
        echo self::get_modal_markup( $settings );
    }

    /**
     * Get markup string for modal wrapper + template contents.
     */
    public static function get_modal_markup( array $settings ) {
        $template = $settings['template'];
        $inner    = self::get_inner_markup( $template, $settings, false );

        if ( empty( $inner ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="kh-bounce-modal underlay" data-template="<?php echo esc_attr( $template ); ?>">
            <div class="kh-bounce-modal-flex kh-bounce-modal-flex-activated">
                <?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Return markup used in the admin preview cards.
     */
    public static function get_preview_markup( $template ) {
        $sample = self::sample_settings();
        $inner  = self::get_inner_markup( $template, $sample, true );
        if ( empty( $inner ) ) {
            return '';
        }
        return sprintf( '<div id="wbounce-%1$s" class="hidden-by-default kh-bounce-preview-wrapper template-%1$s">%2$s</div>', esc_attr( $template ), $inner );
    }

    /**
     * Build the inner modal markup for a given template.
     */
    protected static function get_inner_markup( $template, array $settings, $is_preview = false ) {
        $template = sanitize_key( $template );

        switch ( $template ) {
            case 'minimal':
                return self::minimal_markup( $settings, $is_preview );
            case 'classic':
            default:
                return self::classic_markup( $settings, $is_preview );
        }
    }

    protected static function classic_markup( array $settings, $is_preview ) {
        $title   = isset( $settings['title'] ) ? $settings['title'] : '';
        $text    = isset( $settings['text'] ) ? $settings['text'] : '';
        $cta     = isset( $settings['cta_label'] ) ? $settings['cta_label'] : '';
        $cta_url = isset( $settings['cta_url'] ) ? $settings['cta_url'] : '#';
        $dismiss = isset( $settings['dismiss_label'] ) ? $settings['dismiss_label'] : __( 'No thanks', 'kh-bounce' );

        ob_start();
        ?>
        <div class="kh-bounce-modal-sub template-classic">
            <?php if ( ! $is_preview ) : ?>
                <button type="button" class="kh-bounce-close" aria-label="<?php esc_attr_e( 'Close modal', 'kh-bounce' ); ?>">&times;</button>
            <?php endif; ?>
            <span class="kh-bounce-eyebrow"><?php esc_html_e( 'Exclusive strategy drop', 'kh-bounce' ); ?></span>
            <h3 class="kh-bounce-title"><?php echo esc_html( $title ); ?></h3>
            <p class="kh-bounce-text"><?php echo wp_kses_post( nl2br( $text ) ); ?></p>
            <div class="kh-bounce-actions">
                <a class="kh-bounce-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta ); ?></a>
                <button class="kh-bounce-dismiss" type="button"><?php echo esc_html( $dismiss ); ?></button>
            </div>
            <p class="kh-bounce-footnote"><?php esc_html_e( 'No spam. Actionable ideas only.', 'kh-bounce' ); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    protected static function minimal_markup( array $settings, $is_preview ) {
        $title   = isset( $settings['title'] ) ? $settings['title'] : '';
        $text    = isset( $settings['text'] ) ? $settings['text'] : '';
        $cta     = isset( $settings['cta_label'] ) ? $settings['cta_label'] : '';
        $cta_url = isset( $settings['cta_url'] ) ? $settings['cta_url'] : '#';
        $dismiss = isset( $settings['dismiss_label'] ) ? $settings['dismiss_label'] : __( 'No thanks', 'kh-bounce' );

        ob_start();
        ?>
        <div class="kh-bounce-modal-sub template-minimal">
            <?php if ( ! $is_preview ) : ?>
                <button type="button" class="kh-bounce-close" aria-label="<?php esc_attr_e( 'Close modal', 'kh-bounce' ); ?>">&times;</button>
            <?php endif; ?>
            <div class="kh-bounce-minimal-inner">
                <div class="kh-bounce-minimal-copy">
                    <span class="kh-bounce-eyebrow"><?php esc_html_e( 'Don\'t bounce without this', 'kh-bounce' ); ?></span>
                    <h4 class="kh-bounce-title"><?php echo esc_html( $title ); ?></h4>
                    <p class="kh-bounce-text"><?php echo wp_kses_post( nl2br( $text ) ); ?></p>
                </div>
                <div class="kh-bounce-minimal-actions">
                    <a class="kh-bounce-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta ); ?></a>
                    <button class="kh-bounce-dismiss" type="button"><?php echo esc_html( $dismiss ); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    protected static function sample_settings() {
        return array(
            'template'      => 'classic',
            'title'         => __( 'Unlock the Marketing ROI Playbook', 'kh-bounce' ),
            'text'          => __( 'Discover five high-leverage experiments we used to drive a 132% lift last quarter. We will send the breakdown straight to your inbox.', 'kh-bounce' ),
            'cta_label'     => __( 'Send me the playbook', 'kh-bounce' ),
            'cta_url'       => '#',
            'dismiss_label' => __( 'I\'ll explore later', 'kh-bounce' ),
        );
    }
}
