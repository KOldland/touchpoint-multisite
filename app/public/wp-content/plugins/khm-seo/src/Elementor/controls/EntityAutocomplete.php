<?php
/**
 * Entity Autocomplete Control
 *
 * Custom Elementor control for entity selection with autocomplete functionality.
 * Provides search and selection of GEO entities in the Elementor editor.
 *
 * @package KHM_SEO\Elementor\Controls
 * @since 2.0.0
 */

namespace KHM_SEO\Elementor\Controls;

use Elementor\Base_Data_Control;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Entity Autocomplete Control Class
 */
class EntityAutocomplete extends Base_Data_Control {

    /**
     * Get control type
     */
    public function get_type() {
        return 'khm_entity_autocomplete';
    }

    /**
     * Get default settings
     */
    protected function get_default_settings() {
        return array(
            'placeholder' => __( 'Search for an entity...', 'khm-seo' ),
            'multiple' => false,
        );
    }

    /**
     * Render control output
     */
    public function content_template() {
        $control_uid = $this->get_control_uid();
        ?>
        <div class="elementor-control-field">
            <label for="<?php echo $control_uid; ?>" class="elementor-control-title">{{{ data.label }}}</label>
            <div class="elementor-control-input-wrapper">
                <div class="khm-entity-autocomplete-wrapper">
                    <input id="<?php echo $control_uid; ?>"
                           type="text"
                           class="khm-entity-autocomplete-input"
                           placeholder="{{ data.placeholder }}"
                           data-setting="{{ data.name }}" />
                    <input type="hidden"
                           class="khm-entity-autocomplete-value"
                           data-setting="{{ data.name }}" />
                    <div class="khm-entity-autocomplete-results"></div>
                </div>
            </div>
            <# if ( data.description ) { #>
                <div class="elementor-control-field-description">{{{ data.description }}}</div>
            <# } #>
        </div>
        <?php
    }

    /**
     * Get default value
     */
    public function get_default_value() {
        return '';
    }

    /**
     * Get value for editor
     */
    public function get_value( $control, $widget ) {
        $value = parent::get_value( $control, $widget );

        if ( empty( $value ) ) {
            return '';
        }

        // If we have an entity ID, get the entity data for display
        if ( is_numeric( $value ) && function_exists( 'khm_seo' ) && isset( khm_seo()->geo ) ) {
            $entity_manager = khm_seo()->geo->get_entity_manager();
            if ( $entity_manager ) {
                $entity = $entity_manager->get_entity( $value );
                if ( $entity ) {
                    return array(
                        'id' => $entity->id,
                        'text' => $entity->canonical,
                        'type' => $entity->type,
                    );
                }
            }
        }

        return $value;
    }

    /**
     * Enqueue control scripts and styles
     */
    public function enqueue() {
        wp_enqueue_script(
            'khm-entity-autocomplete-control',
            KHM_SEO_PLUGIN_URL . 'assets/js/entity-autocomplete-control.js',
            array( 'jquery', 'underscore', 'backbone' ),
            KHM_SEO_VERSION,
            true
        );

        wp_enqueue_style(
            'khm-entity-autocomplete-control',
            KHM_SEO_PLUGIN_URL . 'assets/css/entity-autocomplete-control.css',
            array(),
            KHM_SEO_VERSION
        );
    }
}
