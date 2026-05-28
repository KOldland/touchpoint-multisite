<?php

namespace KH\Editorial\Planner\Agents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PolicyAgent
 * 
 * Manages editorial policies, channel-specific rules, and sponsor guardrails.
 */
class PolicyAgent {

    /**
     * Resolve session exclusions based on channel and sponsor context.
     * 
     * @param int $post_id The planner_session ID.
     * @return array Exclusions and channel metadata.
     */
    public function resolve_exclusions( $post_id ) {
        $channel = get_post_meta( $post_id, 'kh_planner_channel', true ) ?: 'house';
        $mode    = get_post_meta( $post_id, 'kh_planner_mode', true ) ?: 'standard';
        
        $excluded_names = [];
        $excluded_types = [];
        $keep_name      = '';

        // Example: If channel is 'sponsored', we might want to exclude competitors
        if ( $channel === 'sponsored' ) {
            $sponsor_name = get_post_meta( $post_id, 'kh_planner_sponsor_name', true );
            $sponsor_type = get_post_meta( $post_id, 'kh_planner_sponsor_type', true );
            
            if ( ! empty( $sponsor_type ) ) {
                $excluded_types[] = $sponsor_type; // Exclude others of the same type
            }
            $keep_name = $sponsor_name;
        }

        // Quote Club Mode: Vendor Agnostic
        if ( $mode === 'quote_club' ) {
            $excluded_types = [ 'vendor', 'software', 'consultant' ];
        }

        return [
            'channel'        => $channel,
            'mode'           => $mode,
            'excluded_names' => array_filter( array_unique( $excluded_names ) ),
            'excluded_types' => array_filter( array_unique( $excluded_types ) ),
            'keep_name'      => $keep_name
        ];
    }

    /**
     * Formats the exclusions into a string for LLM prompts.
     */
    public function get_exclusion_directives( $post_id ) {
        $policy = $this->resolve_exclusions( $post_id );
        $directives = [];

        if ( ! empty( $policy['excluded_names'] ) ) {
            $directives[] = "DO NOT cite or mention these specific entities: " . implode( ', ', $policy['excluded_names'] ) . ".";
        }

        if ( ! empty( $policy['excluded_types'] ) ) {
            $exception = ! empty( $policy['keep_name'] ) ? " (Exception: {$policy['keep_name']})" : "";
            $directives[] = "Exclude all " . implode( ', ', $policy['excluded_types'] ) . " providers from citations$exception.";
        }

        if ( $policy['mode'] === 'quote_club' ) {
            $directives[] = "CITATION RULE: All content must remain vendor-agnostic. Use only independent industry bodies or academic sources.";
        }

        return implode( ' ', $directives );
    }
}
