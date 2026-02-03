<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use KHM\Services\MembershipRepository;
use KHM\Services\AnswerCardLibraryService;

/**
 * Portal Section Summaries Widget
 *
 * Displays saved section summaries in the member portal.
 */
class PortalAnswerCards_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_answercards';
    }

    public function get_title() {
        return __( 'Portal Section Summaries', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-editor-help';
    }

    public function get_categories() {
        return [ 'touchpoint', 'theme-elements' ];
    }

    public function get_keywords() {
        return [ 'portal', 'answercard', 'geo', 'member' ];
    }

    public function show_in_panel() {
        return true;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Section Summaries Display', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label' => __( 'Items per page', 'khm-membership' ),
                'type' => Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 50,
            ]
        );

        $this->add_control(
            'show_date',
            [
                'label' => __( 'Show Saved Date', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'allow_share',
            [
                'label' => __( 'Allow Share', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __( 'Style', 'khm-membership' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'accent_color',
            [
                'label' => __( 'Accent Color', 'khm-membership' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#6b0b0b',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if ( ! is_user_logged_in() ) {
            echo '<p class="khm-portal-login-required">' . esc_html__( 'Please log in to view your section summaries.', 'khm-membership' ) . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();

        $memberships_repo = new MembershipRepository();
        $answercard_library = new AnswerCardLibraryService( $memberships_repo );
        $items = $answercard_library->get_member_answercards( $user_id, [
            'limit' => (int) $settings['per_page'],
        ] );

        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();
        $accent_color = $settings['accent_color'] ?? '#6b0b0b';
        ?>
        <div class="khm-portal-answercards" style="--khm-accent: <?php echo esc_attr( $accent_color ); ?>">
            <h3><?php esc_html_e( 'Saved Section Summaries', 'khm-membership' ); ?></h3>
            <div class="khm-section-divider" aria-hidden="true"></div>

            <?php if ( ! empty( $items ) ) : ?>
                <div class="khm-downloads-list khm-answercards-list">
                    <?php foreach ( $items as $item ) :
                        $post = get_post( $item->post_id );
                        if ( ! $post ) {
                            continue;
                        }
                        $card = $this->find_answercard( $item->post_id, $item->answer_card_id );
                        $question = $card['question'] ?? get_the_title( $item->post_id );
                        ?>
                        <div class="khm-download-item khm-answercard-item" data-answer-card-id="<?php echo esc_attr( $item->answer_card_id ); ?>">
                            <div class="khm-download-info">
                                <h4 class="khm-download-title">
                                    <a href="<?php echo esc_url( get_permalink( $item->post_id ) ); ?>">
                                        <?php echo esc_html( $question ); ?>
                                    </a>
                                </h4>
                                <div class="khm-download-meta">
                                    <?php if ( $settings['show_date'] === 'yes' ) : ?>
                                        <span class="khm-download-date">
                                            <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="khm-download-actions">
                                <?php if ( $settings['allow_share'] === 'yes' ) : ?>
                                    <button class="khm-icon-btn khm-answercard-share-btn" data-post-id="<?php echo esc_attr( $item->post_id ); ?>" data-title="<?php echo esc_attr( $question ); ?>" data-url="<?php echo esc_url( get_permalink( $item->post_id ) ); ?>" title="<?php esc_attr_e( 'Share section summary', 'khm-membership' ); ?>" aria-label="<?php esc_attr_e( 'Share section summary', 'khm-membership' ); ?>">
                                        <span class="khm-btn-icon dashicons dashicons-email"></span>
                                    </button>
                                <?php endif; ?>

                                <button class="khm-icon-btn khm-answercard-remove-btn" data-post-id="<?php echo esc_attr( $item->post_id ); ?>" data-answer-card-id="<?php echo esc_attr( $item->answer_card_id ); ?>" data-title="<?php echo esc_attr( $question ); ?>" title="<?php esc_attr_e( 'Remove from saved section summaries', 'khm-membership' ); ?>" aria-label="<?php esc_attr_e( 'Remove from saved section summaries', 'khm-membership' ); ?>">
                                    <span class="khm-btn-icon dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="khm-empty-state">
                    <span class="khm-empty-icon dashicons dashicons-info-outline"></span>
                    <p><?php esc_html_e( 'No section summaries saved yet.', 'khm-membership' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function find_answercard( int $post_id, string $answer_card_id ): array {
        $cards = get_post_meta( $post_id, '_geo_answercards', true );
        if ( ! is_array( $cards ) ) {
            return [];
        }
        foreach ( $cards as $card ) {
            if ( ! empty( $card['answer_card_id'] ) && $card['answer_card_id'] === $answer_card_id ) {
                return $card;
            }
        }
        return [];
    }

    private function enqueue_portal_styles(): void {
        $css_path = plugin_dir_path( dirname( dirname( __DIR__ ) ) ) . 'assets/css/portal-widgets.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style(
                'khm-portal-widgets',
                plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/css/portal-widgets.css',
                [],
                filemtime( $css_path )
            );
        }
    }

    private function enqueue_portal_scripts(): void {
        $js_path = plugin_dir_path( dirname( dirname( __DIR__ ) ) ) . 'assets/js/portal-widgets.js';
        if ( file_exists( $js_path ) ) {
            wp_enqueue_script(
                'khm-portal-widgets',
                plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/js/portal-widgets.js',
                [ 'jquery' ],
                filemtime( $js_path ),
                true
            );

            wp_localize_script( 'khm-portal-widgets', 'khmPortalWidgets', [
                'restUrl' => esc_url_raw( rest_url( 'khm/v1/portal/' ) ),
                'downloadRestUrl' => esc_url_raw( rest_url( 'khm/v1/download/' ) ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'shareNonce' => wp_create_nonce( 'khm_library_nonce' ),
            ] );
        }
    }
}
