<?php
/**
 * Pre-Publish Validation Manager
 *
 * Handles validation checks before AnswerCard content is published,
 * ensuring quality standards are met and providing feedback to editors.
 *
 * @package KHM_SEO\GEO\Validation
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Validation;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * ValidationManager Class
 */
class ValidationManager {

    /**
     * Minimum quality score required for publishing
     */
    const MIN_PUBLISH_SCORE = 0.60; // 60%

    /**
     * Validation rules
     */
    const VALIDATION_RULES = array(
        'required_fields' => array(
            'question' => 'Question is required',
            'answer' => 'Answer is required',
        ),
        'minimum_lengths' => array(
            'question' => 10,
            'answer' => 50,
        ),
        'quality_thresholds' => array(
            'min_score' => 0.60,
            'min_confidence' => 0.50,
        ),
    );

    /**
     * Constructor - Initialize hooks
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Elementor-specific hooks
        add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_validation_scripts' ) );

        // AJAX validation endpoint
        add_action( 'wp_ajax_khm_validate_answer_card', array( $this, 'ajax_validate_answer_card' ) );

        // Admin notices for validation failures
        add_action( 'admin_notices', array( $this, 'display_validation_notices' ) );

        // Save post validation
        add_action( 'save_post', array( $this, 'validate_on_save' ), 5 );

        // Elementor save validation
        add_action( 'elementor/editor/after_save', array( $this, 'validate_elementor_save' ), 10, 2 );
    }

    /**
     * Enqueue validation scripts for Elementor editor
     */
    public function enqueue_validation_scripts() {
        wp_enqueue_script(
            'khm-answer-card-validation',
            plugin_dir_url( KHM_SEO_PLUGIN_FILE ) . 'assets/js/answer-card-validation.js',
            array( 'jquery' ),
            KHM_SEO_VERSION,
            true
        );

        wp_localize_script( 'khm-answer-card-validation', 'khmValidation', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'khm_validate_answer_card' ),
            'messages' => array(
                'validating' => __( 'Validating AnswerCard...', 'khm-seo' ),
                'validation_passed' => __( '✓ AnswerCard passed validation', 'khm-seo' ),
                'validation_failed' => __( '✗ AnswerCard failed validation', 'khm-seo' ),
                'publish_anyway' => __( 'Publish Anyway', 'khm-seo' ),
            ),
        ) );
    }

    /**
     * AJAX handler for AnswerCard validation
     */
    public function ajax_validate_answer_card() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'khm_validate_answer_card' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        // Get validation data
        $settings = $_POST['settings'] ?? array();
        $post_id = intval( $_POST['post_id'] ?? 0 );

        // Perform validation
        $validation_result = $this->validate_answer_card( $settings, $post_id );

        if ( $validation_result['valid'] ) {
            wp_send_json_success( array(
                'message' => 'AnswerCard is ready for publishing',
                'score' => $validation_result['score'],
                'quality_level' => $validation_result['quality_level'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => 'AnswerCard requires improvements before publishing',
                'errors' => $validation_result['errors'],
                'warnings' => $validation_result['warnings'],
                'recommendations' => $validation_result['recommendations'],
                'score' => $validation_result['score'],
                'quality_level' => $validation_result['quality_level'],
            ) );
        }
    }

    /**
     * Validate AnswerCard settings
     *
     * @param array $settings Widget settings
     * @param int $post_id Post ID
     * @return array Validation result
     */
    public function validate_answer_card( $settings, $post_id = 0 ) {
        $errors = array();
        $warnings = array();

        // Get scoring engine
        $scoring_engine = $this->get_scoring_engine();
        if ( ! $scoring_engine ) {
            $errors[] = 'Scoring system not available';
            return array(
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
                'score' => 0,
                'quality_level' => 'unknown',
                'recommendations' => array(),
            );
        }

        // Perform scoring
        $context = array( 'post_id' => $post_id );
        $score_data = $scoring_engine->calculate_score( $settings, $context );

        // Check required fields
        foreach ( self::VALIDATION_RULES['required_fields'] as $field => $message ) {
            if ( empty( $settings[$field] ) ) {
                $errors[] = $message;
            }
        }

        // Check minimum lengths
        foreach ( self::VALIDATION_RULES['minimum_lengths'] as $field => $min_length ) {
            $value = $settings[$field] ?? '';
            if ( ! empty( $value ) && strlen( trim( $value ) ) < $min_length ) {
                $errors[] = sprintf(
                    '%s must be at least %d characters long',
                    ucfirst( $field ),
                    $min_length
                );
            }
        }

        // Check quality thresholds
        if ( $score_data['total_score'] < self::VALIDATION_RULES['quality_thresholds']['min_score'] ) {
            $errors[] = sprintf(
                'Quality score too low (%.1f%%). Minimum required: %.1f%%',
                $score_data['total_score'] * 100,
                self::VALIDATION_RULES['quality_thresholds']['min_score'] * 100
            );
        }

        if ( ( $settings['confidence_score'] ?? 0 ) < self::VALIDATION_RULES['quality_thresholds']['min_confidence'] ) {
            $warnings[] = sprintf(
                'Low confidence score (%.1f%%). Consider reviewing content accuracy.',
                ( $settings['confidence_score'] ?? 0 ) * 100
            );
        }

        // Additional validation rules
        $this->perform_additional_validation( $settings, $errors, $warnings );

        return array(
            'valid' => empty( $errors ),
            'errors' => $errors,
            'warnings' => $warnings,
            'score' => $score_data['total_score'],
            'quality_level' => $score_data['quality_level'],
            'recommendations' => $score_data['recommendations'],
        );
    }

    /**
     * Perform additional validation checks
     */
    private function perform_additional_validation( $settings, &$errors, &$warnings ) {
        // Check for question quality
        $question = $settings['question'] ?? '';
        if ( ! empty( $question ) ) {
            // Question should start with question word
            if ( ! preg_match( '/^(what|how|why|when|where|who|which|can|does|is|are|do)/i', $question ) ) {
                $warnings[] = 'Question should start with a question word (What, How, Why, etc.)';
            }

            // Question should not be too long
            if ( strlen( $question ) > 100 ) {
                $warnings[] = 'Question is quite long. Consider making it more concise.';
            }
        }

        // Check answer quality
        $answer = $settings['answer'] ?? '';
        if ( ! empty( $answer ) ) {
            // Check for minimum structure
            $word_count = str_word_count( strip_tags( $answer ) );
            if ( $word_count < 20 ) {
                $warnings[] = 'Answer seems too brief. Consider providing more comprehensive information.';
            }

            // Check for potential keyword stuffing
            $question_words = str_word_count( $question );
            if ( $question_words > 0 && $word_count / $question_words > 50 ) {
                $warnings[] = 'Answer may contain keyword stuffing. Ensure content flows naturally.';
            }
        }

        // Check citations
        $citations = $settings['citations'] ?? array();
        if ( empty( $citations ) ) {
            $warnings[] = 'Consider adding citations to support your answer and improve credibility.';
        } else {
            // Validate citation URLs
            $valid_urls = 0;
            foreach ( $citations as $citation ) {
                if ( filter_var( $citation, FILTER_VALIDATE_URL ) ) {
                    $valid_urls++;
                }
            }
            if ( $valid_urls === 0 ) {
                $warnings[] = 'No valid URLs found in citations. Consider adding web sources.';
            }
        }

        // Check entity linkage
        if ( empty( $settings['entity_id'] ) ) {
            $warnings[] = 'Consider linking to a relevant GEO entity for enhanced SEO.';
        }
    }

    /**
     * Validate on post save
     */
    public function validate_on_save( $post_id ) {
        // Skip if not a valid post
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // Check if post contains AnswerCard widgets
        if ( $this->post_has_answer_cards( $post_id ) ) {
            // Store validation status in post meta
            update_post_meta( $post_id, '_khm_answer_card_validation', time() );
        }
    }

    /**
     * Validate Elementor save
     */
    public function validate_elementor_save( $post_id, $editor_data ) {
        // Check for AnswerCard widgets in the data
        $has_answer_cards = $this->elementor_data_has_answer_cards( $editor_data );

        if ( $has_answer_cards ) {
            // Mark post as needing validation
            update_post_meta( $post_id, '_khm_answer_card_needs_validation', '1' );
        }
    }

    /**
     * Display validation notices in admin
     */
    public function display_validation_notices() {
        global $post;

        if ( ! $post || ! $this->post_has_answer_cards( $post->ID ) ) {
            return;
        }

        $needs_validation = get_post_meta( $post->ID, '_khm_answer_card_needs_validation', true );

        if ( $needs_validation ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>KHM SEO:</strong> This post contains AnswerCard widgets that may need validation before publishing.</p>';
            echo '<p><a href="#" class="button" id="khm-validate-answer-cards">Validate AnswerCards</a></p>';
            echo '</div>';
        }
    }

    /**
     * Check if post contains AnswerCard widgets
     */
    private function post_has_answer_cards( $post_id ) {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return false;
        }

        $document = \Elementor\Plugin::$instance->documents->get( $post_id );
        if ( ! $document ) {
            return false;
        }

        $elements = $document->get_elements_data();
        return $this->elements_contain_answer_cards( $elements );
    }

    /**
     * Check if Elementor data contains AnswerCard widgets
     */
    private function elementor_data_has_answer_cards( $editor_data ) {
        return $this->elements_contain_answer_cards( $editor_data );
    }

    /**
     * Recursively check elements for AnswerCard widgets
     */
    private function elements_contain_answer_cards( $elements ) {
        foreach ( $elements as $element ) {
            if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'khm-answer-card' ) {
                return true;
            }

            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                if ( $this->elements_contain_answer_cards( $element['elements'] ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get scoring engine instance
     */
    private function get_scoring_engine() {
        static $scoring_engine = null;

        if ( $scoring_engine === null ) {
            $entity_manager = khm_seo()->geo->get_entity_manager();
            if ( $entity_manager ) {
                $scoring_engine = $entity_manager->get_scoring_engine();
            }
        }

        return $scoring_engine;
    }

    /**
     * Get validation settings
     */
    public function get_validation_settings() {
        return array(
            'min_publish_score' => self::MIN_PUBLISH_SCORE,
            'rules' => self::VALIDATION_RULES,
        );
    }

    /**
     * Override validation for specific post (admin only)
     */
    public function override_validation( $post_id, $user_id = null ) {
        if ( ! current_user_can( 'publish_posts' ) ) {
            return false;
        }

        $user_id = $user_id ?: get_current_user_id();

        update_post_meta( $post_id, '_khm_validation_override', array(
            'user_id' => $user_id,
            'timestamp' => time(),
            'reason' => 'Admin override',
        ) );

        return true;
    }
}