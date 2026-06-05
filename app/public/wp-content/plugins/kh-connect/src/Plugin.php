<?php

namespace KH\Connect;

defined('ABSPATH') || exit;

class Plugin {
    public static function init() {
        ( new ConnectShortlistEndpoint() )->register();
        ( new ConnectComparisonEndpoint() )->register();
        ( new ConnectIntroThreadEndpoint() )->register();
        ( new ConnectAdminPage() )->register();
        ( new ConnectEngagedSettingsPage() )->register();
        ( new ConnectSponsorProviderEndpoint() )->register();
        ( new ConnectSellerPaymentEndpoint() )->register();
        ( new ConnectBuyerValidationEndpoint() )->register();
        ( new ConnectSellerResponseEndpoint() )->register();
        ( new ConnectHandoverEndpoint() )->register();
        ( new ConnectDiscountCodeClaimEndpoint() )->register();
        ( new ConnectOpportunityEndpoint() )->register();
        ( new ConnectOutreachChargingListener() )->register();
        ( new ConnectMatchPaymentEndpoint() )->register();
        ( new ConnectColdOutreachChargeHandler() )->register();
        ( new ConnectRFQReportingPage() )->register();
        ( new ConnectSubscriptionEndpoint() )->register();
        ( new ConnectProviderActivationListener() )->register();
        ( new ConnectDirectoryEndpoint() )->register();
        ( new ConnectRfqEndpoint() )->register();
        ( new ConnectSavedSearchEndpoint() )->register();
        
        // Cron hooks, if extracted later / previously.
        // Taxonomies? 
        ( new ConnectRFQUpsellWorker() )->register();
        ( new ConnectRFQCommissionWorker() )->register();
        ( new ConnectSubscriptionExpiryWorker() )->register();
        ( new ConnectLegacyShortcodes() )->register();
        ConnectTaxonomy::register();
        ( new ConnectDirectoryShortcode() )->register();
    }
}
