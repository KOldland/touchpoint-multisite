<?php

namespace KH\EditorialAuthor\Integration;

use WP_Error;

class PlannerBridge {

    /**
     * Fetch the planning context (dossier, briefs, policy) from the Planner Session CPT
     */
    public function get_session_context($session_id) {
        $post = get_post($session_id);
        if (!$post || $post->post_type !== 'planner_session') {
            return new WP_Error('invalid_session', 'Invalid planner session ID.');
        }

        // New standard: context is stored in namespaced meta
        $meta = get_post_meta($session_id, '_kh_planner_data', true);
        $author_policy = get_post_meta($session_id, '_kh_author_policy', true);
        $citations = $this->get_verified_citations($session_id);

        return [
            'session_id'    => $session_id,
            'title'         => $post->post_title,
            'planner_data'  => $meta,
            'author_policy' => $author_policy,
            'dossier'       => get_post_meta($session_id, '_kh_research_dossier', true),
            'citations'     => $citations,
        ];
    }

    /**
     * Fetch citations associated with a session
     */
    public function get_verified_citations($session_id) {
        // Citations should also be in post meta for the session
        $citations = get_post_meta($session_id, '_kh_verified_citations', true);
        return is_array($citations) ? $citations : [];
    }
}
