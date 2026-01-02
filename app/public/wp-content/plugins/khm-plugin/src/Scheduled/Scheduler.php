<?php

namespace KHM\Scheduled;

/**
 * Scheduler
 *
 * Registers and manages WP-Cron events for KHM.
 */
class Scheduler {

    public const HOOK_DAILY = 'khm_daily_tasks';

    /**
     * Register actions
     */
    public function register(): void {
        add_action('init', [ $this, 'maybe_schedule' ]);
    }

    /**
     * Schedule on plugin activation
     */
    public static function activate(): void {
        // If cron disabled or option disabled, do not schedule
        if ( ! self::is_enabled() ) {
            return;
        }

        self::schedule_daily_at_configured_time();
    }

    /**
     * Clear schedules on deactivation
     */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled(self::HOOK_DAILY);
        if ( $timestamp ) {
            wp_unschedule_event($timestamp, self::HOOK_DAILY);
        }
    }

    /**
     * Ensure event is scheduled if enabled and not already scheduled
     */
    public function maybe_schedule(): void {
        if ( ! self::is_enabled() ) {
            // If disabled but scheduled, unschedule
            self::deactivate();
            return;
        }

        if ( ! wp_next_scheduled(self::HOOK_DAILY) ) {
            self::schedule_daily_at_configured_time();
        }
    }

    /**
     * Schedule daily event at configured time
     */
    private static function schedule_daily_at_configured_time(): void {
        $time_string = get_option('khm_cron_time', '02:00');
        $tz = wp_timezone();

        // Compute next occurrence in site timezone
        $now = new \DateTime('now', $tz);
        $today_target = \DateTime::createFromFormat('H:i', $time_string, $tz);
        if ( ! $today_target ) {
            $today_target = new \DateTime('02:00', $tz);
        }
        $target = ( clone $now )->setTime( (int) $today_target->format('H'), (int) $today_target->format('i'));
        if ( $target <= $now ) {
            $target->modify('+1 day');
        }

        // Convert to UTC timestamp for WP-Cron
        $utc = new \DateTimeZone('UTC');
        $target_utc = ( clone $target )->setTimezone($utc);
        $timestamp = $target_utc->getTimestamp();

        wp_schedule_event($timestamp, 'daily', self::HOOK_DAILY);
    }

    /**
     * Check if scheduled tasks are enabled
     */
    public static function is_enabled(): bool {
        return (bool) get_option('khm_cron_enabled', true);
    }
}
