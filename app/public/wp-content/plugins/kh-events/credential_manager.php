<?php
class KH_Events_Credential_Manager {
    public static function get_api_key($type = 'primary') {
        $keys = get_option('kh_events_api_keys', array());
        return $keys[$type] ?? '';
    }

    public function get_social_credentials($platform) {
        return get_option('kh_events_' . $platform . '_settings', array());
    }

    public function get_hubspot_credentials() {
        return get_option('kh_events_hubspot_settings', array());
    }
}
?>