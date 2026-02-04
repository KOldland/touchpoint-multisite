<?php
namespace KH_SMMA\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GoogleAdDraftService {
    /**
     * Generate a Google Ads draft from input.
     *
     * @param array $input
     * @return array
     */
    public function generate( array $input ): array {
        /**
         * Allow integrations to supply a draft without changing core code.
         *
         * @param array $draft
         * @param array $input
         */
        $draft = apply_filters( 'kh_smma_google_ad_draft', array(), $input );

        if ( ! is_array( $draft ) ) {
            return array();
        }

        return $draft;
    }
}
