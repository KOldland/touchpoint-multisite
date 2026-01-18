<?php
/**
 * AnswerCard Schema Validator
 *
 * Validates LLM responses against the AnswerCard JSON schema.
 *
 * @package KHM\GEO
 */

namespace KHM\GEO;

defined( 'ABSPATH' ) || exit;

/**
 * AnswerCard Schema Validator Class
 */
class AnswerCardSchemaValidator {

    /**
     * Validation errors
     *
     * @var array
     */
    private $errors = array();

    /**
     * Validate an array of answer cards
     *
     * @param array $cards Array of card data.
     * @return bool True if valid, false otherwise.
     */
    public function validate( $cards ) {
        $this->errors = array();

        if ( ! is_array( $cards ) ) {
            $this->errors[] = 'Response must be an array of cards';
            return false;
        }

        if ( empty( $cards ) ) {
            $this->errors[] = 'At least one card is required';
            return false;
        }

        foreach ( $cards as $index => $card ) {
            $this->validate_card( $card, $index );
        }

        return empty( $this->errors );
    }

    /**
     * Validate a single card
     *
     * @param array $card  Card data.
     * @param int   $index Card index.
     * @return void
     */
    private function validate_card( $card, $index ) {
        $prefix = "Card {$index}: ";

        // Required: question
        if ( empty( $card['question'] ) ) {
            $this->errors[] = $prefix . 'question is required';
        } elseif ( strlen( $card['question'] ) > 500 ) {
            $this->errors[] = $prefix . 'question must be 500 characters or less';
        }

        // Required: concise_answer
        if ( empty( $card['concise_answer'] ) ) {
            $this->errors[] = $prefix . 'concise_answer is required';
        } else {
            $word_count = str_word_count( strip_tags( $card['concise_answer'] ) );
            if ( $word_count < 20 ) {
                $this->errors[] = $prefix . 'concise_answer should be at least 20 words';
            }
            if ( $word_count > 150 ) {
                $this->errors[] = $prefix . 'concise_answer should be 150 words or less';
            }
        }

        // Required: key_points (array)
        if ( ! isset( $card['key_points'] ) || ! is_array( $card['key_points'] ) ) {
            $this->errors[] = $prefix . 'key_points must be an array';
        } elseif ( count( $card['key_points'] ) < 2 ) {
            $this->errors[] = $prefix . 'at least 2 key_points are required';
        } else {
            foreach ( $card['key_points'] as $kp_index => $point ) {
                if ( ! is_string( $point ) || empty( trim( $point ) ) ) {
                    $this->errors[] = $prefix . "key_points[{$kp_index}] must be a non-empty string";
                }
            }
        }

        // Optional but validated: citations
        if ( isset( $card['citations'] ) ) {
            if ( ! is_array( $card['citations'] ) ) {
                $this->errors[] = $prefix . 'citations must be an array';
            } else {
                foreach ( $card['citations'] as $cit_index => $citation ) {
                    if ( ! is_array( $citation ) ) {
                        $this->errors[] = $prefix . "citations[{$cit_index}] must be an object";
                        continue;
                    }
                    if ( empty( $citation['url'] ) ) {
                        $this->errors[] = $prefix . "citations[{$cit_index}].url is required";
                    } elseif ( ! filter_var( $citation['url'], FILTER_VALIDATE_URL ) ) {
                        $this->errors[] = $prefix . "citations[{$cit_index}].url must be a valid URL";
                    }
                }
            }
        }

        // Optional but validated: entities
        if ( isset( $card['entities'] ) ) {
            if ( ! is_array( $card['entities'] ) ) {
                $this->errors[] = $prefix . 'entities must be an array';
            } else {
                foreach ( $card['entities'] as $ent_index => $entity ) {
                    if ( is_string( $entity ) ) {
                        // Simple string entity is OK
                        continue;
                    }
                    if ( ! is_array( $entity ) ) {
                        $this->errors[] = $prefix . "entities[{$ent_index}] must be a string or object";
                        continue;
                    }
                    if ( empty( $entity['name'] ) ) {
                        $this->errors[] = $prefix . "entities[{$ent_index}].name is required";
                    }
                }
            }
        }

        // Optional: confidence (0-1)
        if ( isset( $card['confidence'] ) ) {
            $conf = $card['confidence'];
            if ( ! is_numeric( $conf ) || $conf < 0 || $conf > 1 ) {
                $this->errors[] = $prefix . 'confidence must be a number between 0 and 1';
            }
        }
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Get errors as string
     *
     * @return string
     */
    public function get_errors_string() {
        return implode( '; ', $this->errors );
    }

    /**
     * Normalize card data to expected format
     *
     * @param array $card Raw card data.
     * @return array Normalized card.
     */
    public function normalize( $card ) {
        $normalized = array(
            'question'       => sanitize_text_field( $card['question'] ?? '' ),
            'concise_answer' => wp_kses_post( $card['concise_answer'] ?? '' ),
            'key_points'     => array(),
            'citations'      => array(),
            'entities'       => array(),
            'confidence'     => floatval( $card['confidence'] ?? ( $card['evidence']['confidence'] ?? 0.5 ) ),
            'notes'          => sanitize_text_field( $card['notes'] ?? '' ),
            'preferred_summary' => ! empty( $card['preferred_summary'] ),
            'evidence'       => array(
                'tier'            => sanitize_text_field( $card['evidence']['tier'] ?? '' ),
                'confidence'      => floatval( $card['evidence']['confidence'] ?? 0.5 ),
                'context_heading' => sanitize_text_field( $card['evidence']['context_heading'] ?? '' ),
                'source_passage'  => wp_kses_post( $card['evidence']['source_passage'] ?? '' ),
                'anchor_entities' => is_array( $card['evidence']['anchor_entities'] ?? null )
                                     ? array_map( 'sanitize_text_field', $card['evidence']['anchor_entities'] )
                                     : array(),
            ),
        );

        // Normalize key points
        if ( isset( $card['key_points'] ) && is_array( $card['key_points'] ) ) {
            foreach ( $card['key_points'] as $point ) {
                if ( is_string( $point ) && ! empty( trim( $point ) ) ) {
                    $normalized['key_points'][] = sanitize_text_field( $point );
                }
            }
        }

        // Normalize citations - preserve all metadata fields
        if ( isset( $card['citations'] ) && is_array( $card['citations'] ) ) {
            foreach ( $card['citations'] as $citation ) {
                if ( is_array( $citation ) && ! empty( $citation['url'] ) ) {
                    $normalized['citations'][] = array(
                        'title'     => sanitize_text_field( $citation['title'] ?? '' ),
                        'url'       => esc_url_raw( $citation['url'] ),
                        'author'    => sanitize_text_field( $citation['author'] ?? '' ),
                        'publisher' => sanitize_text_field( $citation['publisher'] ?? '' ),
                        'year'      => sanitize_text_field( $citation['year'] ?? '' ),
                        'tier'      => sanitize_text_field( $citation['tier'] ?? '' ),
                        'doi'       => sanitize_text_field( $citation['doi'] ?? '' ),
                        'keywords'  => is_array( $citation['keywords'] ?? null ) 
                                       ? array_map( 'sanitize_text_field', $citation['keywords'] ) 
                                       : array(),
                    );
                }
            }
        }

        // Normalize entities
        if ( isset( $card['entities'] ) && is_array( $card['entities'] ) ) {
            foreach ( $card['entities'] as $entity ) {
                if ( is_string( $entity ) ) {
                    $normalized['entities'][] = array(
                        'name'   => sanitize_text_field( $entity ),
                        'sameAs' => '',
                    );
                } elseif ( is_array( $entity ) && ! empty( $entity['name'] ) ) {
                    $normalized['entities'][] = array(
                        'name'   => sanitize_text_field( $entity['name'] ),
                        'sameAs' => esc_url_raw( $entity['sameAs'] ?? '' ),
                    );
                }
            }
        }

        return $normalized;
    }
}
