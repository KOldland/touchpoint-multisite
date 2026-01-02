<?php
class KH_Events_Webhook_Manager {
    public static function get_webhooks() {
        return get_option('kh_events_webhooks', array());
    }

    public function trigger_webhook($event, $data) {
        $webhooks = $this->get_webhooks();
        foreach ($webhooks as $webhook) {
            if (in_array($event, $webhook['events'] ?? array()) && ($webhook['active'] ?? false)) {
                $this->deliver_webhook($webhook, $event, $data);
            }
        }
    }

    private function deliver_webhook($webhook, $event, $data) {
        $payload = array(
            'event' => $event,
            'timestamp' => time(),
            'data' => $data,
            'source' => 'kh-events-plugin'
        );
        $signature = hash_hmac('sha256', wp_json_encode($payload), $webhook['secret']);
        $args = array(
            'body' => wp_json_encode($payload),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-KH-Events-Signature' => 'sha256=' . $signature,
                'X-KH-Events-Event' => $event,
                'User-Agent' => 'KH-Events-Webhook/1.0'
            ),
            'timeout' => 30,
            'blocking' => false
        );
        wp_remote_post($webhook['url'], $args);
    }
}
?>