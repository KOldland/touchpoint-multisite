<?php

namespace KHM\Services;

class PressReleaseService {

    private CreditService $credits;

    public function __construct(CreditService $credits) {
        $this->credits = $credits;
    }

    /**
     * Create a new press release draft.
     */
    public function create_draft(int $sponsor_id, int $user_id, string $title, string $content, ?int $commentary_id = null): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        return $wpdb->insert(
            $table,
            [
                'sponsor_id'    => $sponsor_id,
                'user_id'       => $user_id,
                'commentary_id' => $commentary_id,
                'title'         => sanitize_text_field($title),
                'content'       => wp_kses_post($content),
                'excerpt'       => wp_trim_words(wp_strip_all_tags($content), 30, '…'),
                'status'        => 'draft',
                'created_at'    => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        ) ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing press release.
     */
    public function update_draft(int $id, int $user_id, string $title, string $content): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d AND status = %s",
            $id, $user_id, 'draft'
        ));

        if (!$row) {
            return false;
        }

        return (bool) $wpdb->update(
            $table,
            [
                'title'      => sanitize_text_field($title),
                'content'    => wp_kses_post($content),
                'excerpt'    => wp_trim_words(wp_strip_all_tags($content), 30, '…'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Get a draft for viewing/editing (owner-only).
     */
    public function get_draft(int $id, int $user_id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d AND status = %s",
            $id, $user_id, 'draft'
        ), ARRAY_A);
    }

    /**
     * Submit a draft for publication (consume 1 press-release credit).
     */
    public function submit_draft(int $id, int $user_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d AND status = %s",
            $id, $user_id, 'draft'
        ), ARRAY_A);

        if (!$row) {
            return ['success' => false, 'error' => 'not_found'];
        }

        // Consume a press-release credit.
        if (!$this->credits->usePressReleaseCredit($user_id)) {
            $balance = $this->credits->getPressReleaseCredits($user_id);
            return [
                'success'               => false,
                'error'                 => 'insufficient_press_release_credits',
                'credits_available'    => $balance,
            ];
        }

        $wpdb->update(
            $table,
            [
                'status'           => 'submitted',
                'submission_date'  => current_time('mysql'),
                'updated_at'       => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        do_action('khm_press_release_submitted', $id, (int) $row['sponsor_id'], $user_id);

        return [
            'success'                  => true,
            'id'                       => $id,
            'status'                   => 'submitted',
            'credits_remaining'        => $this->credits->getPressReleaseCredits($user_id),
        ];
    }

    /**
     * Get a press release by ID (with author/sponsor details).
     */
    public function get_press_release(int $id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT pr.*, u.display_name, u.user_email, s.name AS sponsor_name
             FROM {$table} pr
             LEFT JOIN {$wpdb->users} u ON u.ID = pr.user_id
             LEFT JOIN {$wpdb->prefix}khm_sponsors s ON s.id = pr.sponsor_id
             WHERE pr.id = %d",
            $id
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * List press releases for a sponsor (paginated).
     */
    public function list_by_sponsor(int $sponsor_id, int $page = 1, int $per_page = 20, ?string $status = null): array {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';
        $offset = ($page - 1) * $per_page;

        if ($status) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE sponsor_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $sponsor_id, $status, $per_page, $offset
            ), ARRAY_A);
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE sponsor_id = %d AND status = %s",
                $sponsor_id, $status
            ));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE sponsor_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $sponsor_id, $per_page, $offset
            ), ARRAY_A);
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE sponsor_id = %d",
                $sponsor_id
            ));
        }

        return [
            'items' => $rows ?: [],
            'meta'  => [
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => $total,
            ],
        ];
    }

    /**
     * List all submitted press releases (for editorial review).
     */
    public function list_submitted(int $page = 1, int $per_page = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';
        $offset = ($page - 1) * $per_page;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pr.*, u.display_name, s.name AS sponsor_name
             FROM {$table} pr
             LEFT JOIN {$wpdb->users} u ON u.ID = pr.user_id
             LEFT JOIN {$wpdb->prefix}khm_sponsors s ON s.id = pr.sponsor_id
             WHERE pr.status = %s
             ORDER BY pr.submission_date DESC
             LIMIT %d OFFSET %d",
            'submitted', $per_page, $offset
        ), ARRAY_A);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = %s",
            'submitted'
        ));

        return [
            'items' => $rows ?: [],
            'meta'  => [
                'page'     => $page,
                'per_page' => $per_page,
                'total'    => $total,
            ],
        ];
    }

    /**
     * Approve and publish a submitted press release.
     */
    public function approve_and_publish(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = %s",
            $id, 'submitted'
        ), ARRAY_A);

        if (!$row) {
            return false;
        }

        $wpdb->update(
            $table,
            [
                'status'         => 'published',
                'published_date' => current_time('mysql'),
                'updated_at'     => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        do_action('khm_press_release_published', $id, (int) $row['sponsor_id'], (int) $row['user_id']);

        return true;
    }

    /**
     * Reject a submitted press release (refund credit to sponsor).
     */
    public function reject(int $id, string $reason = ''): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = %s",
            $id, 'submitted'
        ), ARRAY_A);

        if (!$row) {
            return false;
        }

        // Refund the press-release credit.
        $this->credits->refundPressReleaseCredit((int) $row['user_id']);

        $wpdb->update(
            $table,
            [
                'status'           => 'rejected',
                'rejection_reason' => sanitize_textarea_field($reason),
                'updated_at'       => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        do_action('khm_press_release_rejected', $id, (int) $row['sponsor_id'], (int) $row['user_id'], $reason);

        return true;
    }

    /**
     * Delete a draft (owner-only).
     */
    public function delete_draft(int $id, int $user_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_press_releases';

        return (bool) $wpdb->delete(
            $table,
            ['id' => $id, 'user_id' => $user_id, 'status' => 'draft'],
            ['%d', '%d', '%s']
        );
    }
}
