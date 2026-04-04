<?php

namespace KHM\Services;

use KHM\Sponsors\SponsorMigration;

class SponsorService {
    public static function get_user_sponsor(int $user_id): ?array {
        if ($user_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = SponsorMigration::sponsors_table_name();
        $sponsors = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A);

        foreach ((array) $sponsors as $sponsor) {
            $team_members = json_decode((string) ($sponsor['team_members'] ?? ''), true);
            if (!is_array($team_members)) {
                continue;
            }

            foreach ($team_members as $member) {
                if ((int) ($member['user_id'] ?? 0) === $user_id) {
                    return $sponsor;
                }
            }
        }

        return null;
    }

    public static function is_sponsor_team_member(int $user_id, int $sponsor_id): bool {
        if ($user_id <= 0 || $sponsor_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = SponsorMigration::sponsors_table_name();
        $sponsor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $sponsor_id), ARRAY_A);
        if (!is_array($sponsor)) {
            return false;
        }

        $team_members = json_decode((string) ($sponsor['team_members'] ?? ''), true);
        if (!is_array($team_members)) {
            return false;
        }

        foreach ($team_members as $member) {
            if ((int) ($member['user_id'] ?? 0) === $user_id) {
                return true;
            }
        }

        return false;
    }

    public static function add_team_member(int $sponsor_id, int $user_id, array $member): bool {
        if ($sponsor_id <= 0 || $user_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = SponsorMigration::sponsors_table_name();
        $sponsor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $sponsor_id), ARRAY_A);
        if (!is_array($sponsor)) {
            return false;
        }

        $team_members = json_decode((string) ($sponsor['team_members'] ?? ''), true);
        if (!is_array($team_members)) {
            $team_members = [];
        }

        foreach ($team_members as $existing) {
            if ((int) ($existing['user_id'] ?? 0) === $user_id) {
                return true;
            }
        }

        $team_members[] = [
            'first_name' => sanitize_text_field((string) ($member['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($member['last_name'] ?? '')),
            'job_title' => sanitize_text_field((string) ($member['job_title'] ?? 'Member')),
            'work_email' => sanitize_email((string) ($member['work_email'] ?? '')),
            'user_id' => $user_id,
            'membership_level' => sanitize_text_field((string) ($member['membership_level'] ?? 'sponsor')),
        ];

        $updated = $wpdb->update(
            $table,
            [
                'team_members' => wp_json_encode(array_values($team_members)),
                'updated_at' => current_time('mysql'),
            ],
            [ 'id' => $sponsor_id ],
            ['%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }
}
