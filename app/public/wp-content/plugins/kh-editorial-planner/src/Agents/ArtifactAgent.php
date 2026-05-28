<?php

namespace KH\Planner\Agents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ArtifactAgent
 * 
 * Responsible for generating the Research Dossier - a human-readable 
 * "Source of Truth" document containing the results of all research phases.
 */
class ArtifactAgent {

    /**
     * Generate and save the research dossier for a session.
     * 
     * @param int $post_id The planner_session ID.
     * @return bool Success or failure.
     */
    public function generate_dossier( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return false;

        $phase1 = get_post_meta( $post_id, 'kh_planner_phase1_result', true );
        $phase2 = get_post_meta( $post_id, 'kh_planner_phase2_result', true );
        $phase3 = get_post_meta( $post_id, 'kh_planner_phase3_result', true );
        $phase4 = get_post_meta( $post_id, 'kh_planner_phase4_result', true );
        $synopses = get_post_meta( $post_id, 'kh_planner_final_synopses', true );

        $content = $this->assemble_markdown( $post->post_title, $phase1, $phase2, $phase3, $phase4, $synopses );

        // Save to post content so it's readable in the WordPress editor
        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => $content,
        ] );

        update_post_meta( $post_id, 'kh_planner_dossier_updated', current_time( 'mysql' ) );

        return true;
    }

    /**
     * Assemble the research data into a structured Markdown document.
     */
    private function assemble_markdown( $title, $p1, $p2, $p3, $p4, $syn ) {
        $md = "# Research Dossier: $title\n\n";
        $md .= "> Generated on: " . current_time( 'F j, Y, g:i a' ) . "\n\n";

        if ( ! empty( $p1['executive_summary'] ) ) {
            $md .= "## Phase 1: Discovery Summary\n";
            $md .= $p1['executive_summary'] . "\n\n";
            
            if ( ! empty( $p1['trends'] ) ) {
                $md .= "### Key Trends Identified\n";
                foreach ( $p1['trends'] as $trend ) {
                    $md .= "- **" . ( $trend['title'] ?? 'Untitled' ) . "**: " . ( $trend['why_it_matters'] ?? '' ) . "\n";
                }
                $md .= "\n";
            }
        }

        if ( ! empty( $p2['ranked_keywords'] ) ) {
            $md .= "## Phase 2: Strategic Keywords\n";
            $md .= "| Keyword | Volume | Priority |\n";
            $md .= "| :--- | :--- | :--- |\n";
            foreach ( array_slice( $p2['ranked_keywords'], 0, 10 ) as $kw ) {
                $md .= "| " . $kw['keyword'] . " | " . ( $kw['search_volume'] ?? 'N/A' ) . " | " . ( $kw['priority_score'] ?? '0' ) . " |\n";
            }
            $md .= "\n";
        }

        if ( ! empty( $p3['prioritized_topics'] ) ) {
            $md .= "## Phase 3: Topic Framing\n";
            foreach ( $p3['prioritized_topics'] as $topic ) {
                $md .= "### " . $topic['topic'] . "\n";
                $md .= "**Strategic Intent:** " . ( $topic['why_now'] ?? '' ) . "\n\n";
                if ( ! empty( $topic['key_findings'] ) ) {
                    $md .= "**Key Findings:**\n";
                    foreach ( $topic['key_findings'] as $finding ) {
                        $md .= "- $finding\n";
                    }
                }
                $md .= "\n";
            }
        }

        if ( ! empty( $syn['synopses'] ) ) {
            $md .= "## Final Article Synopses\n";
            foreach ( $syn['synopses'] as $s ) {
                $md .= "### ARTICLE: " . ( $s['headline'] ?? 'Untitled' ) . "\n";
                $md .= "**Summary:** " . ( $s['summary'] ?? '' ) . "\n\n";
                $md .= "**Key Points:**\n";
                foreach ( (array) ( $s['key_points'] ?? [] ) as $kp ) {
                    $md .= "- $kp\n";
                }
                $md .= "\n---\n\n";
            }
        }

        return $md;
    }
}
