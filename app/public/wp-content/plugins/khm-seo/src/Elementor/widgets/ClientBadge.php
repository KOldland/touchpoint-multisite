<?php
/**
 * Client Badge Widget
 *
 * Elementor widget for displaying client/entity badges with selection.
 * Shows entity information in a badge format for branding and attribution.
 *
 * @package KHM_SEO\Elementor\Widgets
 * @since 2.0.0
 */

namespace KHM_SEO\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * ClientBadge Widget Class
 */
class ClientBadge extends Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'khm-client-badge';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __( 'KHM Client Badge', 'khm-seo' );
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-badge';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return array( 'khm-seo' );
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return array( 'client', 'badge', 'entity', 'branding', 'attribution' );
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Tab
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __( 'Content', 'khm-seo' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'entity_id',
            array(
                'label' => __( 'Entity', 'khm-seo' ),
                'type' => 'khm_entity_autocomplete',
                'placeholder' => __( 'Search for an entity...', 'khm-seo' ),
                'description' => __( 'Select the entity to display in the badge', 'khm-seo' ),
            )
        );

        $this->add_control(
            'badge_text',
            array(
                'label' => __( 'Badge Text', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Featured Client', 'khm-seo' ),
                'placeholder' => __( 'Enter badge text', 'khm-seo' ),
                'condition' => array(
                    'entity_id!' => '',
                ),
            )
        );

        $this->add_control(
            'show_entity_name',
            array(
                'label' => __( 'Show Entity Name', 'khm-seo' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => array(
                    'entity_id!' => '',
                ),
            )
        );

        $this->add_control(
            'link_to_entity',
            array(
                'label' => __( 'Link to Entity Page', 'khm-seo' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => array(
                    'entity_id!' => '',
                ),
            )
        );

        $this->add_control(
            'badge_style',
            array(
                'label' => __( 'Badge Style', 'khm-seo' ),
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => array(
                    'default' => __( 'Default', 'khm-seo' ),
                    'rounded' => __( 'Rounded', 'khm-seo' ),
                    'square' => __( 'Square', 'khm-seo' ),
                    'minimal' => __( 'Minimal', 'khm-seo' ),
                ),
            )
        );

        $this->end_controls_section();

        // Style Tab
        $this->start_controls_section(
            'style_section',
            array(
                'label' => __( 'Style', 'khm-seo' ),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'badge_background',
            array(
                'label' => __( 'Background Color', 'khm-seo' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#007cba',
                'selectors' => array(
                    '{{WRAPPER}} .khm-client-badge' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'badge_color',
            array(
                'label' => __( 'Text Color', 'khm-seo' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .khm-client-badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .khm-client-badge',
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'badge_border',
                'selector' => '{{WRAPPER}} .khm-client-badge',
            )
        );

        $this->add_control(
            'badge_border_radius',
            array(
                'label' => __( 'Border Radius', 'khm-seo' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors' => array(
                    '{{WRAPPER}} .khm-client-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'badge_box_shadow',
                'selector' => '{{WRAPPER}} .khm-client-badge',
            )
        );

        $this->add_control(
            'badge_padding',
            array(
                'label' => __( 'Padding', 'khm-seo' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em' ),
                'selectors' => array(
                    '{{WRAPPER}} .khm-client-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        if ( empty( $settings['entity_id'] ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="khm-client-badge-placeholder">' . __( 'Please select an entity', 'khm-seo' ) . '</div>';
            }
            return;
        }

        $entity = khm_seo()->geo->get_entity_manager()->get_entity( $settings['entity_id'] );
        if ( ! $entity ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div class="khm-client-badge-placeholder">' . __( 'Entity not found', 'khm-seo' ) . '</div>';
            }
            return;
        }

        $entity_url = get_permalink( $entity->id );
        $badge_classes = array( 'khm-client-badge', 'khm-badge-style-' . $settings['badge_style'] );

        $this->add_render_attribute( 'badge', 'class', $badge_classes );

        if ( 'yes' === $settings['link_to_entity'] ) {
            $this->add_render_attribute( 'badge', 'href', $entity_url );
            $tag = 'a';
        } else {
            $tag = 'div';
        }

        ?>
        <<?php echo $tag; ?> <?php echo $this->get_render_attribute_string( 'badge' ); ?>>
            <?php if ( ! empty( $settings['badge_text'] ) ) : ?>
                <span class="khm-badge-text"><?php echo esc_html( $settings['badge_text'] ); ?></span>
            <?php endif; ?>

            <?php if ( 'yes' === $settings['show_entity_name'] ) : ?>
                <span class="khm-entity-name"><?php echo esc_html( $entity->canonical ); ?></span>
            <?php endif; ?>
        </<?php echo $tag; ?>>
        <?php
    }

    /**
     * Get widget script dependencies
     */
    public function get_script_depends() {
        return array( 'khm-geo-elementor-frontend' );
    }

    /**
     * Get widget style dependencies
     */
    public function get_style_depends() {
        return array( 'khm-geo-elementor-frontend' );
    }
}
