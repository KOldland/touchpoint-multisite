<?php
namespace KHM\Bootstrap;

class CronBootstrap {
    public static function init() {
        add_filter( 'cron_schedules', [__CLASS__, 'register_cron_schedules'] );
    }

    public static function register_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['khm_five_minutes'] ) ) {
            $schedules['khm_five_minutes'] = array(
                'interval' => 300,
                'display'  => 'Every 5 Minutes',
            );
        }

        if ( ! isset( $schedules['khm_thirty_seconds'] ) ) {
            $schedules['khm_thirty_seconds'] = array(
                'interval' => 30,
                'display'  => 'Every 30 Seconds',
            );
        }

        if ( ! isset( $schedules['every_five_minutes'] ) ) {
            $schedules['every_five_minutes'] = array(
                'interval' => 300,
                'display'  => 'Every 5 Minutes',
            );
        }

        return $schedules;
    }
}
