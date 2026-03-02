<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Widget_Base;
use KHM\Services\LevelRepository;

/**
 * Membership Checkout Button Widget
 *
 * Renders a button that opens a modal for membership checkout.
 * Uses Stripe Checkout (hosted) instead of embedded Stripe Elements.
 *
 * Data Flow:
 * 1. Widget renders button with data-membership-level-id attribute
 * 2. JS reads membership data from button on click
 * 3. Modal opens showing tier info (name, price, benefits)
 * 4. "Proceed to Checkout" button triggers AJAX to create Stripe Checkout Session
 * 5. User redirects to Stripe-hosted checkout page
 * 6. Stripe webhook activates membership after successful payment
 */
class MembershipCheckoutButton_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_membership_checkout_button';
    }

    public function get_title() {
        return __('Membership Checkout Button', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-button';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['membership', 'checkout', 'subscribe', 'join', 'khm', 'button', 'modal'];
    }

    public function show_in_panel() {
        return true;
    }

    /**
     * Register Elementor controls (editor settings).
     */
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Button Content', 'khm-membership'),
            ]
        );

        // Membership Tier Dropdown
        $this->add_control(
            'membership_level_id',
            [
                'label' => __('Membership Tier', 'khm-membership'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_membership_tier_options(),
                'default' => '',
                'description' => __('Select the membership tier this button will trigger', 'khm-membership'),
            ]
        );

        $this->add_control(
            'use_level_defaults',
            [
                'label' => __('Use level defaults', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'khm-membership'),
                'label_off' => __('No', 'khm-membership'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'override_cta_text',
            [
                'label' => __('CTA Text Override', 'khm-membership'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'condition' => [
                    'use_level_defaults!' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'override_price_inclusive',
            [
                'label' => __('Price Inclusive Override', 'khm-membership'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'khm-membership'),
                'label_off' => __('No', 'khm-membership'),
                'return_value' => 'yes',
                'condition' => [
                    'use_level_defaults!' => 'yes',
                ],
            ]
        );

        // Button Text
        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'khm-membership'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Join Now', 'khm-membership'),
                'placeholder' => __('Join Pro', 'khm-membership'),
            ]
        );

        // Button Icon (optional)
        $this->add_control(
            'button_icon',
            [
                'label' => __('Button Icon', 'khm-membership'),
                'type' => Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
            ]
        );

        $this->end_controls_section();

        // Style Section - Button
        $this->start_controls_section(
            'section_style_button',
            [
                'label' => __('Button Style', 'khm-membership'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .khm-checkout-trigger',
            ]
        );

        // Button Colors (Normal State)
        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'khm-membership'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .khm-checkout-trigger' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => __('Background Color', 'khm-membership'),
                'type' => Controls_Manager::COLOR,
                'default' => '#6b0b0b',
                'selectors' => [
                    '{{WRAPPER}} .khm-checkout-trigger' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        // Button Colors (Hover State)
        $this->add_control(
            'button_hover_heading',
            [
                'label' => __('Hover', 'khm-membership'),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'button_hover_text_color',
            [
                'label' => __('Hover Text Color', 'khm-membership'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .khm-checkout-trigger:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background_color',
            [
                'label' => __('Hover Background Color', 'khm-membership'),
                'type' => Controls_Manager::COLOR,
                'default' => '#4a0808',
                'selectors' => [
                    '{{WRAPPER}} .khm-checkout-trigger:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        // Border
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .khm-checkout-trigger',
                'separator' => 'before',
            ]
        );

        // Border Radius
        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'khm-membership'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .khm-checkout-trigger' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        // Padding
        $this->add_control(
            'button_padding',
            [
                'label' => __('Padding', 'khm-membership'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .khm-checkout-trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '12',
                    'right' => '24',
                    'bottom' => '12',
                    'left' => '24',
                    'unit' => 'px',
                ],
            ]
        );

        $this->end_controls_section();

        // Advanced Section
        $this->start_controls_section(
            'section_advanced',
            [
                'label' => __('Advanced', 'khm-membership'),
            ]
        );

        $this->add_control(
            'css_classes',
            [
                'label' => __('Additional CSS Classes', 'khm-membership'),
                'type' => Controls_Manager::TEXT,
                'description' => __('Add custom CSS class names for additional styling', 'khm-membership'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render the widget output on the frontend.
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $level_id = $settings['membership_level_id'] ?? '';

        // Validate membership level
        if (empty($level_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p style="color: #d00;">' . esc_html__('⚠️ Please select a membership tier in the widget settings.', 'khm-membership') . '</p>';
            }
            return;
        }

        // Get membership level data
        $levels_repo = new LevelRepository();
        $level = $levels_repo->get((int) $level_id, true);

        if (!$level) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p style="color: #d00;">' . esc_html__('⚠️ Selected membership tier not found.', 'khm-membership') . '</p>';
            }
            return;
        }

        // Enqueue modal assets
        $this->enqueue_modal_assets();

        // Prepare data attributes
        $button_text = $settings['button_text'] ?? __('Join Now', 'khm-membership');
        $css_classes = $settings['css_classes'] ?? '';
        $use_defaults = ($settings['use_level_defaults'] ?? 'yes') === 'yes';

        $level_meta = function_exists('khm_get_level_meta') ? khm_get_level_meta((int) $level_id) : [];
        $presentation = is_array($level_meta) && isset($level_meta['presentation']) && is_array($level_meta['presentation'])
            ? $level_meta['presentation']
            : [];

        $level_cta_text = $presentation['cta_text'] ?? '';
        $level_price_inclusive = isset($presentation['price_inclusive']) ? (bool) $presentation['price_inclusive'] : null;

        $cta_text = $use_defaults ? ($level_cta_text ?: $button_text) : ($settings['override_cta_text'] ?: $button_text);
        $price_inclusive = $use_defaults ? $level_price_inclusive : (($settings['override_price_inclusive'] ?? '') === 'yes');

        // Calculate billing interval display
        $interval = 'month';
        if (!empty($level->cycle_period)) {
            $interval = strtolower($level->cycle_period);
        }

        // Format price
        $price_display = '';
        if ($level->billing_amount > 0) {
            $price_display = '$' . number_format($level->billing_amount, 2);
        }

        /**
         * Data attributes passed to JavaScript:
         * - data-membership-level-id: The tier ID
         * - data-membership-level-name: Display name (e.g., "Pro")
         * - data-membership-price: Numeric price (e.g., "29.00")
         * - data-membership-price-display: Formatted price (e.g., "$29.00")
         * - data-membership-interval: Billing interval (e.g., "month")
         * - data-purchase-type: Always "subscription" for memberships
         */
        ?>
        <button
            class="khm-checkout-trigger <?php echo esc_attr($css_classes); ?>"
            data-membership-level-id="<?php echo esc_attr($level->id); ?>"
            data-membership-level-name="<?php echo esc_attr($level->name); ?>"
            data-membership-price="<?php echo esc_attr($level->billing_amount); ?>"
            data-membership-price-display="<?php echo esc_attr($price_display); ?>"
            data-membership-interval="<?php echo esc_attr($interval); ?>"
            data-purchase-type="subscription"
            data-membership-description="<?php echo esc_attr($level->description ?? ''); ?>"
            data-membership-monthly-credits="<?php echo esc_attr($level->monthly_credits ?? 0); ?>"
            data-level-cta-text="<?php echo esc_attr((string) $cta_text); ?>"
            data-level-price-inclusive="<?php echo esc_attr($price_inclusive ? '1' : '0'); ?>">

            <?php
            // Render icon if set
            if (!empty($settings['button_icon']['value'])) {
                \Elementor\Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']);
            }
            ?>

            <?php echo esc_html($cta_text); ?>
        </button>
        <?php
    }

    /**
     * Get membership tier options for the dropdown control.
     *
     * @return array Associative array of [level_id => level_name]
     */
    private function get_membership_tier_options(): array {
        $levels_repo = new LevelRepository();
        $name_map = $levels_repo->getNameMap();

        if (empty($name_map)) {
            return ['' => __('No membership tiers found', 'khm-membership')];
        }

        // Add a default "Select tier" option
        return ['' => __('— Select Membership Tier —', 'khm-membership')] + $name_map;
    }

    /**
     * Enqueue modal JavaScript and CSS.
     */
    private function enqueue_modal_assets() {
        $plugin_url = plugin_dir_url(dirname(dirname(__DIR__)));
        $plugin_path = plugin_dir_path(dirname(dirname(__DIR__)));

        // Enqueue CSS
        $css_path = $plugin_path . 'assets/css/membership-modal.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'khm-membership-modal',
                $plugin_url . 'assets/css/membership-modal.css',
                [],
                filemtime($css_path)
            );
        }

        // Enqueue JavaScript
        $js_path = $plugin_path . 'assets/js/membership-modal.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'khm-membership-modal',
                $plugin_url . 'assets/js/membership-modal.js',
                ['jquery'],
                filemtime($js_path),
                true
            );

            // Localize script with AJAX configuration
            wp_localize_script('khm-membership-modal', 'khmMembershipModal', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('khm_membership_checkout_nonce'),
                'isLoggedIn' => is_user_logged_in(),
                'strings' => [
                    'error_generic' => __('An error occurred. Please try again.', 'khm-membership'),
                    'loading' => __('Loading...', 'khm-membership'),
                    'processing' => __('Processing...', 'khm-membership'),
                ],
            ]);
        }
    }
}
