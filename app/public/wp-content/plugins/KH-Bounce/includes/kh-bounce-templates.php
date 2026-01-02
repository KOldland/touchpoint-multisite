<?php
/**
 * KH Bounce template helper.
 */
class KH_Bounce_Templates {

    /**
     * Return list of available templates.
     *
     * @return array
     */
    public static function all() {
        return array(
            'classic' => array(
                'label'       => __( 'Classic Center Modal', 'kh-bounce' ),
                'description' => __( 'Centered card with bold headline, supporting text, and CTA row.', 'kh-bounce' ),
            ),
            'minimal' => array(
                'label'       => __( 'Minimal Slide Up', 'kh-bounce' ),
                'description' => __( 'Discrete bottom-aligned bar for subtle prompts.', 'kh-bounce' ),
            ),
            'edge' => array(
                'label'       => __( 'Edge Spotlight', 'kh-bounce' ),
                'description' => __( 'Tall panel docked to the right edge with accent border.', 'kh-bounce' ),
            ),
        );
    }

    /**
     * Render template body markup.
     *
     * @param string $template
     * @param array  $settings
     * @param array  $args
     *
     * @return string
     */
    public static function render( $template, $settings, $args = array() ) {
        $defaults = array(
            'context' => 'frontend',
        );
        $args = wp_parse_args( $args, $defaults );

        $title    = isset( $settings['title'] ) ? $settings['title'] : '';
        $text     = isset( $settings['text'] ) ? $settings['text'] : '';
        $cta      = isset( $settings['cta_label'] ) ? $settings['cta_label'] : '';
        $cta_url  = isset( $settings['cta_url'] ) ? $settings['cta_url'] : '';
        $dismiss  = isset( $settings['dismiss_label'] ) ? $settings['dismiss_label'] : __( 'Dismiss', 'kh-bounce' );
        $title_id = isset( $settings['title_id'] ) ? $settings['title_id'] : '';
        $text_id  = isset( $settings['text_id'] ) ? $settings['text_id'] : '';
        $is_admin_preview = ( 'admin' === $args['context'] );

        ob_start();
        ?>
        <div class="kh-bounce-template-inner template-<?php echo esc_attr( $template ); ?>">
            <header class="kh-bounce-template-head">
                <p class="kh-bounce-tagline"><?php echo esc_html__( 'Marketing Suite', 'kh-bounce' ); ?></p>
                <h3 class="kh-bounce-title" <?php echo $title_id ? 'id="' . esc_attr( $title_id ) . '"' : ''; ?>><?php echo esc_html( $title ); ?></h3>
            </header>
            <div class="kh-bounce-text" <?php echo $text_id ? 'id="' . esc_attr( $text_id ) . '"' : ''; ?>><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
            <div class="kh-bounce-actions">
                <?php if ( ! empty( $cta_url ) && ! $is_admin_preview ) : ?>
                    <a class="kh-bounce-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta ); ?></a>
                <?php else : ?>
                    <button class="kh-bounce-cta" type="button"><?php echo esc_html( $cta ); ?></button>
                <?php endif; ?>
                <button class="kh-bounce-dismiss" type="button"><?php echo esc_html( $dismiss ); ?></button>
            </div>
        </div>
        <?php
        return trim( ob_get_clean() );
    }
}
