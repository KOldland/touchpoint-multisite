<?php

namespace KH\EditorialAuthor\Integration;

use WP_Error;

class PlannerBridge {

    /**
     * Fetch the planning context (dossier, briefs, policy) from the Planner Session CPT.
     * Supports filtering by a specific article_id if provided.
     */
    public function get_session_context($session_id, $article_id = null) {
        $post = get_post($session_id);
        if (!$post || $post->post_type !== 'planner_session') {
            return new WP_Error('invalid_session', 'Invalid planner session ID.');
        }

        // New standard: context is stored in namespaced meta
        $meta = get_post_meta($session_id, '_kh_planner_data', true);
        $author_policy = get_post_meta($session_id, '_kh_author_policy', true);
        $session_citations = $this->get_verified_citations($session_id);

        $context = [
            'session_id'    => $session_id,
            'title'         => $post->post_title,
            'planner_data'  => $meta,
            'author_policy' => $author_policy,
            'dossier'       => get_post_meta($session_id, '_kh_research_dossier', true),
            'citations'     => $session_citations,
            'article_id'    => $article_id,
        ];

        // If a specific article is requested, isolate it and prune irrelevant session data
        if ($article_id && !empty($meta['articles']) && is_array($meta['articles'])) {
            $found = false;
            foreach ($meta['articles'] as $article) {
                if (($article['id'] ?? '') == $article_id) {
                    $context['target_article'] = $article;
                    
                    // Prioritize article-specific citations if they exist
                    if (!empty($article['citations']) && is_array($article['citations'])) {
                        $context['citations'] = $article['citations'];
                    }

                    $found = true;
                    break;
                }
            }

            // Critical: Remove the full articles list from planner_data to prevent LLM context bleed
            if ($found) {
                unset($context['planner_data']['articles']);
            }
        }

        return $context;
    }

    /**
     * Fetch citations associated with a session
     */
    public function get_verified_citations($session_id) {
        // Citations should also be in post meta for the session
        $citations = get_post_meta($session_id, '_kh_verified_citations', true);
        return is_array($citations) ? $citations : [];
    }

    /**
     * Link a created post ID back to the specific article in the planner session.
     */
    public function link_article_to_post($session_id, $article_id, $post_id) {
        $meta = get_post_meta($session_id, '_kh_planner_data', true);
        if (!$meta || empty($meta['articles'])) return false;

        $found = false;
        foreach ($meta['articles'] as &$article) {
            if (($article['id'] ?? '') == $article_id) {
                $article['wp_post_id'] = $post_id;
                $article['status'] = 'drafted';
                $found = true;
                break;
            }
        }

        if ($found) {
            return update_post_meta($session_id, '_kh_planner_data', $meta);
        }

        return false;
    }
}
