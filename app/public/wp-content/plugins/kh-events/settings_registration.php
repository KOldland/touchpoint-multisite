<?php
register_activation_hook(__FILE__, 'kh_events_register_default_settings');

function kh_events_register_default_settings() {
    add_option('kh_events_api_keys', array(
        'primary' => 'kh_6da8ea3550440425b4a4da924d473bc0',
        'mobile' => 'kh_955bcf1a0bd12ad47aa777bbf447e184',
        'webhook' => 'kh_fee552fc0e31553dc5edca3bd28d4e18'
    ));
    add_option('kh_events_social_general', array(
        'auto_post' => true
    ));
    add_option('kh_events_crm_general', array(
        'auto_sync_contacts' => true,
        'auto_create_deals' => true
    ));
    add_option('kh_events_webhooks', array(
        array(
            'name' => 'Booking Notification Service',
            'url' => 'https://api.example.com/webhooks/kh-events',
            'events' => array('booking.completed', 'booking.cancelled'),
            'secret' => 'f8bd621380ba71f06e4fd1858a2d7a9c89bb348289bbcbc7e5660ff73d6638a1',
            'active' => false
        )
    ));
}
?>