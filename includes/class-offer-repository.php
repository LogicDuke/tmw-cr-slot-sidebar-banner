<?php
/**
 * Local storage and frontend normalization for synced offers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Offer_Repository {
    const ALLOWED_OFFER_TYPES = array( 'pps', 'revshare_lifetime', 'revshare', 'soi', 'doi', 'cpa', 'cpl', 'cpc', 'cpi', 'cpm', 'smartlink', 'fallback' );
    const ALLOWED_MANUAL_OFFER_TYPES = array( 'pps', 'revshare_lifetime', 'revshare', 'soi', 'doi', 'cpa', 'cpl', 'cpc', 'cpi', 'cpm', 'smartlink', 'fallback' );
    const UNAVAILABLE_ACCOUNT_PPS_OFFER_IDS = array( '9647', '9781' );
    const ELIGIBILITY_REASON_MISSING_FINAL_URL = 'missing_final_url';
    const ELIGIBILITY_REASON_INVALID_FINAL_URL = 'invalid_final_url';
    const ELIGIBILITY_REASON_BLOCKED_OFFER = 'blocked_offer';
    const ELIGIBILITY_REASON_UNAVAILABLE_OFFER = 'unavailable_offer';
    const ELIGIBILITY_REASON_MISSING_LOGO = 'missing_logo';
    const ELIGIBILITY_REASON_COUNTRY_NOT_ALLOWED = 'country_not_allowed';
    const ELIGIBILITY_REASON_OFFER_TYPE_NOT_ALLOWED = 'offer_type_not_allowed';
    const ELIGIBILITY_REASON_NO_MANUAL_COUNTRY_OVERRIDE = 'no_manual_country_override';
    const ELIGIBILITY_REASON_VALID = 'valid';

    protected $offers_option_key;
    protected $meta_option_key;
    protected $overrides_option_key;
    protected $stats_option_key;
    protected $stats_meta_option_key;
    protected $dashboard_meta_option_key;
    protected $skipped_offers_option_key;
    protected $offer_logo_manifest_rows = null;
    protected $offer_overrides_cache = null;
    protected $offer_overrides_cache_hash = null;

    public function __construct( $offers_option_key, $meta_option_key, $overrides_option_key = 'tmw_cr_slot_banner_offer_overrides', $stats_option_key = 'tmw_cr_slot_banner_offer_stats', $stats_meta_option_key = 'tmw_cr_slot_banner_offer_stats_meta', $dashboard_meta_option_key = 'tmw_cr_slot_banner_offer_dashboard_meta', $skipped_offers_option_key = 'tmw_cr_slot_banner_skipped_offers' ) {
        $this->offers_option_key = $offers_option_key;
        $this->meta_option_key = $meta_option_key;
        $this->overrides_option_key = $overrides_option_key;
        $this->stats_option_key = $stats_option_key;
        $this->stats_meta_option_key = $stats_meta_option_key;
        $this->dashboard_meta_option_key = $dashboard_meta_option_key;
        $this->skipped_offers_option_key = $skipped_offers_option_key;
    }

    public function get_synced_offers() { return array(); }
}
