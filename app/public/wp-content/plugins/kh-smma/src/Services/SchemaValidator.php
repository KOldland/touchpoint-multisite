<?php
namespace KH_SMMA\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema Validator
 *
 * Validates JSON structures against expected schemas for:
 * - LinkedIn variants
 * - Google Ads drafts
 * - Compliance check responses
 *
 * Enforces strict validation per the SMMA API specification.
 */
class SchemaValidator {
    /**
     * Validate LinkedIn variant schema.
     *
     * Required fields:
     * - variant_id (string)
     * - text (string)
     * - channel (string)
     *
     * Optional but expected fields:
     * - rationale, asset_hints, recommended_post_time_gmt, geo_recommendations,
     *   compliance_notes, approval_required, audit
     *
     * @param array $variant Variant object to validate
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_linkedin_variant( array $variant ) {
        // Check required fields
        if ( empty( $variant['variant_id'] ) || ! is_string( $variant['variant_id'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant missing required field: variant_id (string)' );
        }

        if ( ! isset( $variant['text'] ) || ! is_string( $variant['text'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant missing required field: text (string)' );
        }

        if ( empty( $variant['channel'] ) || ! is_string( $variant['channel'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant missing required field: channel (string)' );
        }

        // Validate optional fields if present
        if ( isset( $variant['recommended_post_time_gmt'] ) && ! is_int( $variant['recommended_post_time_gmt'] ) && ! is_numeric( $variant['recommended_post_time_gmt'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant field recommended_post_time_gmt must be a timestamp (int)' );
        }

        if ( isset( $variant['asset_hints'] ) && ! is_array( $variant['asset_hints'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant field asset_hints must be an array' );
        }

        if ( isset( $variant['geo_recommendations'] ) && ! is_array( $variant['geo_recommendations'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant field geo_recommendations must be an array' );
        }

        if ( isset( $variant['approval_required'] ) && ! is_bool( $variant['approval_required'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant field approval_required must be a boolean' );
        }

        if ( isset( $variant['audit'] ) && ! is_array( $variant['audit'] ) ) {
            return new WP_Error( 'invalid_schema', 'LinkedIn variant field audit must be an array' );
        }

        return true;
    }

    /**
     * Validate Google Ads draft schema.
     *
     * Required structure:
     * {
     *   "ad_groups": [
     *     {
     *       "keyword_cluster": "string",
     *       "headlines": ["string", ...],
     *       "descriptions": ["string", ...],
     *       "final_url": "string",
     *       "cpc_suggestion": float
     *     }
     *   ]
     * }
     *
     * @param array $draft Google Ads draft to validate
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_google_ad_draft( array $draft ) {
        // Check top-level structure
        if ( ! isset( $draft['ad_groups'] ) || ! is_array( $draft['ad_groups'] ) ) {
            return new WP_Error( 'invalid_schema', 'Google Ads draft missing required field: ad_groups (array)' );
        }

        if ( empty( $draft['ad_groups'] ) ) {
            return new WP_Error( 'invalid_schema', 'Google Ads draft ad_groups array cannot be empty' );
        }

        // Validate each ad group
        foreach ( $draft['ad_groups'] as $index => $group ) {
            $group_label = 'ad_groups[' . $index . ']';

            if ( ! is_array( $group ) ) {
                return new WP_Error( 'invalid_schema', $group_label . ' must be an array' );
            }

            // Required fields
            if ( ! isset( $group['keyword_cluster'] ) || ! is_string( $group['keyword_cluster'] ) ) {
                return new WP_Error( 'invalid_schema', $group_label . ' missing required field: keyword_cluster (string)' );
            }

            if ( ! isset( $group['headlines'] ) || ! is_array( $group['headlines'] ) ) {
                return new WP_Error( 'invalid_schema', $group_label . ' missing required field: headlines (array)' );
            }

            if ( count( $group['headlines'] ) < 3 ) {
                return new WP_Error( 'invalid_schema', $group_label . ' headlines must contain at least 3 items' );
            }

            foreach ( $group['headlines'] as $h_index => $headline ) {
                if ( ! is_string( $headline ) ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.headlines[' . $h_index . '] must be a string' );
                }
                if ( mb_strlen( $headline ) > 30 ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.headlines[' . $h_index . '] exceeds 30 character limit' );
                }
            }

            if ( ! isset( $group['descriptions'] ) || ! is_array( $group['descriptions'] ) ) {
                return new WP_Error( 'invalid_schema', $group_label . ' missing required field: descriptions (array)' );
            }

            if ( count( $group['descriptions'] ) < 2 ) {
                return new WP_Error( 'invalid_schema', $group_label . ' descriptions must contain at least 2 items' );
            }

            foreach ( $group['descriptions'] as $d_index => $description ) {
                if ( ! is_string( $description ) ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.descriptions[' . $d_index . '] must be a string' );
                }
                if ( mb_strlen( $description ) > 90 ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.descriptions[' . $d_index . '] exceeds 90 character limit' );
                }
            }

            if ( ! isset( $group['final_url'] ) || ! is_string( $group['final_url'] ) ) {
                return new WP_Error( 'invalid_schema', $group_label . ' missing required field: final_url (string)' );
            }

            // Validate URL format
            if ( ! filter_var( $group['final_url'], FILTER_VALIDATE_URL ) ) {
                return new WP_Error( 'invalid_schema', $group_label . '.final_url is not a valid URL' );
            }

            // Optional: final_url_with_utm
            if ( isset( $group['final_url_with_utm'] ) ) {
                if ( ! is_string( $group['final_url_with_utm'] ) ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.final_url_with_utm must be a string' );
                }
                if ( ! filter_var( $group['final_url_with_utm'], FILTER_VALIDATE_URL ) ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.final_url_with_utm is not a valid URL' );
                }
            }

            // cpc_suggestion
            if ( isset( $group['cpc_suggestion'] ) ) {
                if ( ! is_numeric( $group['cpc_suggestion'] ) ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.cpc_suggestion must be a number' );
                }
                if ( (float) $group['cpc_suggestion'] < 0 ) {
                    return new WP_Error( 'invalid_schema', $group_label . '.cpc_suggestion cannot be negative' );
                }
            }
        }

        return true;
    }

    /**
     * Validate compliance check response schema.
     *
     * Expected structure:
     * {
     *   "pass": boolean,
     *   "level": "OK"|"WARN"|"FAIL",
     *   "flags": [...],
     *   "suggested_edits": [...],
     *   "confidence": float
     * }
     *
     * @param array $response Compliance response to validate
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_compliance_response( array $response ) {
        if ( ! isset( $response['pass'] ) || ! is_bool( $response['pass'] ) ) {
            return new WP_Error( 'invalid_schema', 'Compliance response missing required field: pass (boolean)' );
        }

        if ( ! isset( $response['level'] ) || ! is_string( $response['level'] ) ) {
            return new WP_Error( 'invalid_schema', 'Compliance response missing required field: level (string)' );
        }

        $valid_levels = array( 'OK', 'WARN', 'FAIL' );
        if ( ! in_array( $response['level'], $valid_levels, true ) ) {
            return new WP_Error( 'invalid_schema', 'Compliance response level must be one of: OK, WARN, FAIL' );
        }

        if ( isset( $response['flags'] ) && ! is_array( $response['flags'] ) ) {
            return new WP_Error( 'invalid_schema', 'Compliance response field flags must be an array' );
        }

        if ( isset( $response['suggested_edits'] ) && ! is_array( $response['suggested_edits'] ) ) {
            return new WP_Error( 'invalid_schema', 'Compliance response field suggested_edits must be an array' );
        }

        if ( isset( $response['confidence'] ) ) {
            if ( ! is_numeric( $response['confidence'] ) ) {
                return new WP_Error( 'invalid_schema', 'Compliance response field confidence must be a number' );
            }
            $confidence = (float) $response['confidence'];
            if ( $confidence < 0 || $confidence > 1 ) {
                return new WP_Error( 'invalid_schema', 'Compliance response field confidence must be between 0 and 1' );
            }
        }

        return true;
    }

    /**
     * Validate batch of LinkedIn variants.
     *
     * @param array $variants Array of variant objects
     * @return true|WP_Error True if all valid, WP_Error for first failure
     */
    public function validate_linkedin_variants( array $variants ) {
        if ( empty( $variants ) ) {
            return new WP_Error( 'invalid_schema', 'Variants array cannot be empty' );
        }

        foreach ( $variants as $index => $variant ) {
            $result = $this->validate_linkedin_variant( $variant );
            if ( is_wp_error( $result ) ) {
                return new WP_Error(
                    $result->get_error_code(),
                    'Variant [' . $index . ']: ' . $result->get_error_message()
                );
            }
        }

        return true;
    }

    /**
     * Validate complete generation response.
     *
     * Expected structure:
     * {
     *   "linkedin_variants": [...],
     *   "google_ad_draft": {...},
     *   "audit": {...}
     * }
     *
     * @param array $response Generation response to validate
     * @return true|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_generation_response( array $response ) {
        // Validate LinkedIn variants if present
        if ( isset( $response['linkedin_variants'] ) ) {
            if ( ! is_array( $response['linkedin_variants'] ) ) {
                return new WP_Error( 'invalid_schema', 'Generation response field linkedin_variants must be an array' );
            }

            if ( ! empty( $response['linkedin_variants'] ) ) {
                $variants_check = $this->validate_linkedin_variants( $response['linkedin_variants'] );
                if ( is_wp_error( $variants_check ) ) {
                    return $variants_check;
                }
            }
        }

        // Validate Google Ads draft if present
        if ( isset( $response['google_ad_draft'] ) && ! empty( $response['google_ad_draft'] ) ) {
            if ( ! is_array( $response['google_ad_draft'] ) ) {
                return new WP_Error( 'invalid_schema', 'Generation response field google_ad_draft must be an array' );
            }

            $draft_check = $this->validate_google_ad_draft( $response['google_ad_draft'] );
            if ( is_wp_error( $draft_check ) ) {
                return $draft_check;
            }
        }

        return true;
    }
}
