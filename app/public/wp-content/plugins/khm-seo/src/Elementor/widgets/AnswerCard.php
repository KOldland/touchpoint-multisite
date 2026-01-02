<?php
/**
 * AnswerCard Widget
 *
 * Elementor widget for displaying answer cards with entity autocomplete.
 * Provides structured content display with automatic entity linking.
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
 * AnswerCard Widget Class
 */
class AnswerCard extends Widget_Base {
    /**
     * Detect Elementor editor context.
     *
     * @return bool
     */
    private function is_editor() {
        return class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }

    /**
     * Get widget name
     */
    public function get_name() {
        return 'khm-answer-card';
    }
    /**
     * Auto-populate content from page analysis
     */
    protected function auto_populate_content( $settings ) {
        if ( defined( 'KHM_SEO_DISABLE_ANSWERCARD_AUTOPOPULATE' ) && KHM_SEO_DISABLE_ANSWERCARD_AUTOPOPULATE ) {
            return;
        }
        if ( 'yes' !== $settings['enable_auto_population'] ) {
            return;
        }

        if ( ! function_exists( 'khm_seo' ) || ! isset( khm_seo()->geo ) ) {
            return;
        }

        $content_analyzer = khm_seo()->geo->get_entity_manager()->get_content_analyzer();
        if ( ! $content_analyzer ) {
            return;
        }

        // Get current page content
        global $post;
        $post_id = $post ? $post->ID : 0;
        if ( ! $post_id ) {
            return;
        }

        $content = $post ? $post->post_content : '';
        if ( empty( $content ) ) {
            return;
        }

        // Analyze content for Q/A pairs
        $qa_pairs = $content_analyzer->extract_qa_pairs( $content );

        if ( empty( $qa_pairs ) ) {
            return;
        }

        // Find best match based on population source
        $best_match = null;
        $highest_confidence = 0;

        foreach ( $qa_pairs as $qa_pair ) {
            $confidence = $qa_pair['confidence'] ?? 0;

            if ( $confidence > $highest_confidence ) {
                $highest_confidence = $confidence;
                $best_match = $qa_pair;
            }
        }

        if ( ! $best_match ) {
            return;
        }

        // Auto-populate fields if not locked
        if ( 'yes' !== $settings['lock_question'] && empty( $settings['question'] ) ) {
            $this->set_settings( 'question', $best_match['question'] );
        }

        if ( 'yes' !== $settings['lock_answer'] && empty( $settings['answer'] ) ) {
            $this->set_settings( 'answer', $best_match['answer'] );
        }

        if ( 'yes' !== $settings['lock_confidence'] ) {
            $this->set_settings( 'confidence_score', $best_match['confidence'] );
        }

        if ( 'yes' !== $settings['lock_citations'] && empty( $settings['citations'] ) ) {
            $this->set_settings( 'citations', $best_match['citations'] ?? array() );
        }
    }

    /**
     * Render quality score display
     */
    protected function render_quality_score_display() {
        if ( defined( 'KHM_SEO_DISABLE_ANSWERCARD_QUALITY' ) && KHM_SEO_DISABLE_ANSWERCARD_QUALITY ) {
            return '';
        }
        // Keep this lightweight in the editor to avoid blocking UI.
        if ( $this->is_editor() ) {
            return '<div class="khm-quality-score-placeholder" style="padding:10px; border:1px dashed #ccc; color:#666; font-size:12px;">Quality score available on save/preview.</div>';
        }

        if ( ! function_exists( 'khm_seo' ) || ! isset( khm_seo()->geo ) ) {
            return '<div class="khm-quality-score-error">Scoring engine not available</div>';
        }

        $settings = $this->get_settings_for_display();

        // Get scoring engine
        $scoring_engine = $this->get_scoring_engine();
        if ( ! $scoring_engine ) {
            return '<div class="khm-quality-score-error">Scoring engine not available</div>';
        }

        // Calculate score
        global $post;
        $context = array(
            'post_id' => $post ? $post->ID : 0,
            'target_keywords' => array(), // Could be populated from SEO analysis
        );

        $score_data = $scoring_engine->calculate_score( $settings, $context );
        $quality_display = $scoring_engine->get_quality_display( $score_data['quality_level'] );

        $html = '<div class="khm-quality-score-container" style="padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa;">';

        // Score header
        $html .= '<div class="khm-quality-score-header" style="display: flex; align-items: center; margin-bottom: 10px;">';
        $html .= '<span style="font-weight: 600; margin-right: 10px;">Quality Score:</span>';
        $html .= '<span class="khm-quality-badge" data-quality="' . esc_attr( $score_data['quality_level'] ) . '" style="background: ' . esc_attr( $quality_display['bg_color'] ) . '; color: ' . esc_attr( $quality_display['color'] ) . '; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500;">';
        $html .= esc_html( $quality_display['label'] ) . ' (' . round( $score_data['total_score'] * 100 ) . '%)';
        $html .= '</span>';
        $html .= '</div>';

        // Score breakdown
        $html .= '<div class="khm-quality-breakdown" style="margin-bottom: 15px;">';
        $html .= '<div style="font-size: 12px; color: #666; margin-bottom: 5px;">Score Breakdown:</div>';

        $criteria_labels = array(
            'content_completeness' => 'Content Completeness',
            'confidence_score' => 'Confidence Score',
            'citation_quality' => 'Citation Quality',
            'entity_linkage' => 'Entity Linkage',
            'content_quality' => 'Content Quality',
            'seo_optimization' => 'SEO Optimization',
        );

        foreach ( $score_data['scores'] as $criterion => $score ) {
            $percentage = round( $score * 100 );
            $weight = \KHM_SEO\GEO\Scoring\ScoringEngine::CRITERIA_WEIGHTS[$criterion] ?? 0;
            $weighted_score = round( $score * $weight * 100 );

            $html .= '<div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 2px;">';
            $html .= '<span>' . esc_html( $criteria_labels[$criterion] ?? $criterion ) . '</span>';
            $html .= '<span>' . $percentage . '% (' . $weighted_score . 'pts)</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // Recommendations
        if ( ! empty( $score_data['recommendations'] ) ) {
            $html .= '<div class="khm-quality-recommendations">';
            $html .= '<div style="font-size: 12px; color: #666; margin-bottom: 5px;">Recommendations:</div>';
            $html .= '<ul style="margin: 0; padding-left: 15px; font-size: 11px; color: #555;">';
            foreach ( $score_data['recommendations'] as $recommendation ) {
                $html .= '<li>' . esc_html( $recommendation ) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Publish status
        $publish_status = $score_data['is_publishable'] ? 'Ready to publish' : 'Needs improvement';
        $status_color = $score_data['is_publishable'] ? '#2e7d32' : '#d32f2f';

        $html .= '<div class="khm-publish-status" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">';
        $html .= '<span style="font-size: 12px; color: ' . esc_attr( $status_color ) . ';">';
        $html .= esc_html( $publish_status );
        $html .= '</span>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get scoring engine instance
     */
    protected function get_scoring_engine() {
        static $scoring_engine = null;

        if ( $scoring_engine === null ) {
            if ( function_exists( 'khm_seo' ) && isset( khm_seo()->geo ) ) {
                $entity_manager = khm_seo()->geo->get_entity_manager();
                if ( $entity_manager ) {
                    $scoring_engine = $entity_manager->get_scoring_engine();
                }
            }
        }

        return $scoring_engine;
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __( 'KHM Answer Card', 'khm-seo' );
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-info-box';
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
        return array( 'answer', 'card', 'faq', 'entity', 'seo' );
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Minimal stub in the editor to avoid triggering heavy preview logic.
        if ( $this->is_editor() ) {
            $this->start_controls_section(
                'content_section_stub',
                array(
                    'label' => __( 'Answer Card (editor preview)', 'khm-seo' ),
                    'tab' => Controls_Manager::TAB_CONTENT,
                )
            );

            $this->add_control(
                'editor_notice',
                array(
                    'type' => Controls_Manager::RAW_HTML,
                    'raw' => '<div style="padding:10px; border:1px dashed #ccc; color:#666; font-size:12px;">This widget is simplified in the editor to keep the preview stable. Content and styling render on the frontend.</div>',
                )
            );

            $this->end_controls_section();
            return;
        }

        // Content Tab
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __( 'Content', 'khm-seo' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'question',
            array(
                'label' => __( 'Question', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'What is SEO?', 'khm-seo' ),
                'placeholder' => __( 'Enter your question', 'khm-seo' ),
                'label_block' => true,
            )
        );

        $this->add_control(
            'answer',
            array(
                'label' => __( 'Answer', 'khm-seo' ),
                'type' => Controls_Manager::WYSIWYG,
                'default' => __( 'SEO stands for Search Engine Optimization...', 'khm-seo' ),
                'placeholder' => __( 'Enter your answer', 'khm-seo' ),
            )
        );

        $this->add_control(
            'bullets',
            array(
                'label' => __( 'Bullet Points', 'khm-seo' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => array(
                    array(
                        'name' => 'bullet',
                        'label' => __( 'Bullet Point', 'khm-seo' ),
                        'type' => Controls_Manager::TEXT,
                        'placeholder' => __( 'Enter bullet point', 'khm-seo' ),
                        'default' => '',
                    ),
                ),
                'title_field' => '{{{ bullet }}}',
            )
        );

        $this->add_control(
            'citations',
            array(
                'label' => __( 'Citations', 'khm-seo' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => array(
                    array(
                        'name' => 'citation',
                        'label' => __( 'Citation', 'khm-seo' ),
                        'type' => Controls_Manager::TEXT,
                        'placeholder' => __( 'Enter citation URL or text', 'khm-seo' ),
                        'default' => '',
                    ),
                ),
                'title_field' => '{{{ citation }}}',
            )
        );

        if ( ! defined( 'KHM_SEO_DISABLE_ANSWERCARD_AUTOCOMPLETE' ) || ! KHM_SEO_DISABLE_ANSWERCARD_AUTOCOMPLETE ) {
            $this->add_control(
                'entity_id',
                array(
                    'label' => __( 'Related Entity', 'khm-seo' ),
                    'type' => 'khm_entity_autocomplete',
                    'placeholder' => __( 'Search for an entity...', 'khm-seo' ),
                    'description' => __( 'Link this answer card to a GEO entity for enhanced SEO', 'khm-seo' ),
                )
            );
        }

        $this->add_control(
            'show_entity_link',
            array(
                'label' => __( 'Show Entity Link', 'khm-seo' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => array(
                    'entity_id!' => '',
                ),
            )
        );

        $this->add_control(
            'entity_link_text',
            array(
                'label' => __( 'Entity Link Text', 'khm-seo' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Learn more', 'khm-seo' ),
                'condition' => array(
                    'entity_id!' => '',
                    'show_entity_link' => 'yes',
                ),
            )
        );

        $this->add_control(
            'show_actions',
            array(
                'label' => __( 'Show Action Buttons', 'khm-seo' ),
                'type' => Controls_Manager::SWITCHER,
                'default' => '',
                'description' => __( 'Show save to notes and email buttons', 'khm-seo' ),
            )
        );

        $this->add_control(
            'actions',
            array(
                'label' => __( 'Actions', 'khm-seo' ),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => array(
                    'save_notes' => __( 'Save to Notes', 'khm-seo' ),
                    'email' => __( 'Email Card', 'khm-seo' ),
                ),
                'default' => array( 'save_notes', 'email' ),
                'condition' => array(
                    'show_actions' => 'yes',
                ),
            )
        );

        if ( ! defined( 'KHM_SEO_DISABLE_ANSWERCARD_AUTOPOPULATE' ) || ! KHM_SEO_DISABLE_ANSWERCARD_AUTOPOPULATE ) {
            // Auto-population Section
            $this->start_controls_section(
                'auto_population_section',
                array(
                    'label' => __( 'Auto-Population', 'khm-seo' ),
                    'tab' => Controls_Manager::TAB_CONTENT,
                )
            );

            $this->add_control(
                'enable_auto_population',
                array(
                    'label' => __( 'Enable Auto-Population', 'khm-seo' ),
                    'type' => Controls_Manager::SWITCHER,
                    'default' => '',
                    'description' => __( 'Automatically populate Q/A from page headings and content', 'khm-seo' ),
                )
            );

            $this->add_control(
                'auto_populate_from',
                array(
                    'label' => __( 'Populate From', 'khm-seo' ),
                    'type' => Controls_Manager::SELECT,
                    'default' => 'nearest_heading',
                    'options' => array(
                        'nearest_heading' => __( 'Nearest H2/H3 Heading', 'khm-seo' ),
                        'specific_heading' => __( 'Specific Heading', 'khm-seo' ),
                        'custom_content' => __( 'Custom Content Block', 'khm-seo' ),
                    ),
                    'condition' => array(
                        'enable_auto_population' => 'yes',
                    ),
                )
            );

            $this->add_control(
                'specific_heading',
                array(
                    'label' => __( 'Heading Text', 'khm-seo' ),
                    'type' => Controls_Manager::TEXT,
                    'placeholder' => __( 'Enter heading to populate from', 'khm-seo' ),
                    'condition' => array(
                        'enable_auto_population' => 'yes',
                        'auto_populate_from' => 'specific_heading',
                    ),
                )
            );

            $this->add_control(
                'lock_question',
                array(
                    'label' => __( 'Lock Question', 'khm-seo' ),
                    'type' => Controls_Manager::SWITCHER,
                    'default' => '',
                    'description' => __( 'Prevent auto-updates to the question field', 'khm-seo' ),
                    'condition' => array(
                        'enable_auto_population' => 'yes',
                    ),
                )
            );

            $this->add_control(
                'lock_citations',
                array(
                    'label' => __( 'Lock Citations', 'khm-seo' ),
                    'type' => Controls_Manager::SWITCHER,
                    'default' => '',
                    'description' => __( 'Prevent auto-updates to the citations field', 'khm-seo' ),
                    'condition' => array(
                        'enable_auto_population' => 'yes',
                    ),
                )
            );

            $this->add_control(
                'confidence_score',
                array(
                    'label' => __( 'Confidence Score', 'khm-seo' ),
                    'type' => Controls_Manager::NUMBER,
                    'min' => 0,
                    'max' => 1,
                    'step' => 0.01,
                    'default' => 0.8,
                    'description' => __( 'Confidence score from content analysis (0-1)', 'khm-seo' ),
                    'condition' => array(
                        'enable_auto_population' => 'yes',
                    ),
                )
            );

            $this->add_control(
                'lock_confidence',
                array(
                    'label' => __( 'Lock Confidence Score', 'khm-seo' ),
                    'type' => Controls_Manager::SWITCHER,
                    'default' => '',
                    'description' => __( 'Prevent auto-updates to the confidence score', 'khm-seo' ),
                    'condition' => array(
                        'enable_auto_population' => 'yes',
                    ),
                )
            );

            if ( ! defined( 'KHM_SEO_DISABLE_ANSWERCARD_QUALITY' ) || ! KHM_SEO_DISABLE_ANSWERCARD_QUALITY ) {
                $this->add_control(
                    'show_quality_score',
                    array(
                        'label' => __( 'Show Quality Score', 'khm-seo' ),
                        'type' => Controls_Manager::SWITCHER,
                        'default' => 'yes',
                        'description' => __( 'Display quality score and recommendations in editor', 'khm-seo' ),
                    )
                );

                $this->add_control(
                    'quality_score_display',
                    array(
                        'label' => __( 'Quality Score', 'khm-seo' ),
                        'type' => Controls_Manager::RAW_HTML,
                        'raw' => $this->render_quality_score_display(),
                        'condition' => array(
                            'show_quality_score' => 'yes',
                        ),
                    )
                );
            }

            $this->end_controls_section();
        }

        // Style Tab
        $this->start_controls_section(
            'style_section',
            array(
                'label' => __( 'Style', 'khm-seo' ),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_background',
            array(
                'label' => __( 'Background Color', 'khm-seo' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .khm-answer-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .khm-answer-card',
            )
        );

        $this->add_control(
            'card_border_radius',
            array(
                'label' => __( 'Border Radius', 'khm-seo' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors' => array(
                    '{{WRAPPER}} .khm-answer-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .khm-answer-card',
            )
        );

        $this->add_control(
            'question_color',
            array(
                'label' => __( 'Question Color', 'khm-seo' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .khm-answer-card-question' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'question_typography',
                'selector' => '{{WRAPPER}} .khm-answer-card-question',
            )
        );

        $this->add_control(
            'answer_color',
            array(
                'label' => __( 'Answer Color', 'khm-seo' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .khm-answer-card-answer' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            array(
                'name' => 'answer_typography',
                'selector' => '{{WRAPPER}} .khm-answer-card-answer',
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Keep editor lightweight: render a placeholder instead of full markup.
        if ( $this->is_editor() ) {
            echo '<div class="khm-answer-card-placeholder" style="padding:12px; border:1px dashed #ccc; color:#555; font-size:13px;">KHM Answer Card will render on the live site.</div>';
            return;
        }

        // If spinner persists, bail out early to isolate render path.
        if ( defined( 'KHM_SEO_DISABLE_ANSWERCARD_RENDER' ) && KHM_SEO_DISABLE_ANSWERCARD_RENDER ) {
            return;
        }

        // If all features are off and this is editor, bail early with a stub to prevent hanging.
        if ( $this->is_editor()
            && ( defined( 'KHM_SEO_DISABLE_ANSWERCARD_AUTOCOMPLETE' ) && KHM_SEO_DISABLE_ANSWERCARD_AUTOCOMPLETE )
            && ( defined( 'KHM_SEO_DISABLE_ANSWERCARD_AUTOPOPULATE' ) && KHM_SEO_DISABLE_ANSWERCARD_AUTOPOPULATE )
            && ( defined( 'KHM_SEO_DISABLE_ANSWERCARD_QUALITY' ) && KHM_SEO_DISABLE_ANSWERCARD_QUALITY )
        ) {
            echo '<div class="khm-answer-card-placeholder" style="padding:10px; border:1px dashed #ccc; color:#666;">KHM Answer Card (placeholder)</div>';
            return;
        }

        // Auto-populate if enabled and not locked
        if ( 'yes' === $settings['enable_auto_population'] && $this->is_editor() ) {
            try {
                $this->auto_populate_content( $settings );
            } catch ( \Exception $e ) {
                // Fail silently in editor to avoid blocking the UI.
            }
        }

        $entity_url = '';
        $entity_title = '';
        if ( ( ! defined( 'KHM_SEO_DISABLE_ANSWERCARD_AUTOCOMPLETE' ) || ! KHM_SEO_DISABLE_ANSWERCARD_AUTOCOMPLETE ) && ! empty( $settings['entity_id'] ) && function_exists( 'khm_seo' ) && isset( khm_seo()->geo ) ) {
            $entity_manager = khm_seo()->geo->get_entity_manager();
            if ( $entity_manager ) {
                $entity = $entity_manager->get_entity( $settings['entity_id'] );
                if ( $entity ) {
                    $entity_url = get_permalink( $entity->id );
                    $entity_title = $entity->canonical;
                }
            }
        }

        // Generate stable anchor ID
        $anchor_id = 'khm-answer-' . substr( md5( $settings['question'] . $this->get_id() ), 0, 8 );

        $this->add_render_attribute( 'card', 'class', 'khm-answer-card' );
        $this->add_render_attribute( 'summary', 'class', 'khm-answer-card-summary' );
        $this->add_render_attribute( 'question', 'class', 'khm-answer-card-question' );
        $this->add_render_attribute( 'answer', 'class', 'khm-answer-card-answer' );

        // Add collapsible attributes
        $this->add_render_attribute( 'card', 'id', $anchor_id );

        ?>
        <div <?php echo $this->get_render_attribute_string( 'card' ); ?>>
            <details class="khm-answer-card-details">
                <summary <?php echo $this->get_render_attribute_string( 'summary' ); ?>>
                    <?php if ( ! empty( $settings['question'] ) ) : ?>
                        <span <?php echo $this->get_render_attribute_string( 'question' ); ?>>
                            <?php echo esc_html( $settings['question'] ); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ( 'yes' === $settings['show_confidence_score'] && ! empty( $settings['confidence_score'] ) ) : ?>
                        <span class="khm-confidence-score" data-score="<?php echo esc_attr( $settings['confidence_score'] ); ?>">
                            <?php echo esc_html( $this->format_confidence_score( $settings['confidence_score'] ) ); ?>
                        </span>
                    <?php endif; ?>
                </summary>

                <div class="khm-answer-card-content">
                    <?php if ( ! empty( $settings['answer'] ) ) : ?>
                        <div <?php echo $this->get_render_attribute_string( 'answer' ); ?>>
                            <?php echo wp_kses_post( $settings['answer'] ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $settings['bullets'] ) && is_array( $settings['bullets'] ) ) : ?>
                        <ul class="khm-answer-card-bullets">
                            <?php foreach ( $settings['bullets'] as $bullet ) : ?>
                                <li><?php echo esc_html( $bullet ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ( ! empty( $settings['citations'] ) && is_array( $settings['citations'] ) ) : ?>
                        <div class="khm-answer-card-citations">
                            <strong><?php _e( 'Sources:', 'khm-seo' ); ?></strong>
                            <ul>
                                <?php foreach ( $settings['citations'] as $citation ) : ?>
                                    <li><?php echo esc_html( $citation ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $entity_url ) && 'yes' === $settings['show_entity_link'] ) : ?>
                        <div class="khm-answer-card-entity-link">
                            <a href="<?php echo esc_url( $entity_url ); ?>" class="khm-entity-link">
                                <?php echo esc_html( $settings['entity_link_text'] ); ?>
                                <span class="khm-entity-title"><?php echo esc_html( $entity_title ); ?></span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $settings['actions'] ) && 'yes' === $settings['show_actions'] ) : ?>
                        <div class="khm-answer-card-actions">
                            <button class="khm-action-btn khm-save-notes" data-card-id="<?php echo esc_attr( $anchor_id ); ?>">
                                <?php _e( 'Save to Notes', 'khm-seo' ); ?>
                            </button>
                            <button class="khm-action-btn khm-email-card" data-card-id="<?php echo esc_attr( $anchor_id ); ?>">
                                <?php _e( 'Email', 'khm-seo' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Format confidence score for display
     */
    protected function format_confidence_score( $score ) {
        $score = floatval( $score );

        if ( $score >= 0.9 ) {
            return 'High (' . round( $score * 100 ) . '%)';
        } elseif ( $score >= 0.7 ) {
            return 'Medium (' . round( $score * 100 ) . '%)';
        } else {
            return 'Low (' . round( $score * 100 ) . '%)';
        }
    }

    /**
     * Get widget script dependencies
     */
    public function get_script_depends() {
        // No explicit JS dependency; style is enqueued via get_style_depends.
        return array();
    }

    /**
     * Get widget style dependencies
     */
    public function get_style_depends() {
        if ( $this->is_editor() ) {
            return array();
        }
        if ( defined( 'KHM_SEO_DISABLE_ANSWERCARD_STYLE' ) && KHM_SEO_DISABLE_ANSWERCARD_STYLE ) {
            return array();
        }
        return array( 'khm-geo-elementor-frontend' );
    }
}
