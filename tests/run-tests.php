<?php
require_once __DIR__ . '/bootstrap.php';

class TMW_CR_Slot_Sidebar_Banner {
    const DEFAULT_HEADLINE = 'Discover Adult Offers';
    const DEFAULT_SUBHEADLINE = 'Cam, Dating, AI & More';
    const DEFAULT_SPIN_BUTTON_TEXT = 'SPIN THE REELS';
    const DEFAULT_CTA_TEXT = 'TRY YOUR FREE SPINS';
    const OPTION_KEY = 'tmw_cr_slot_banner_settings';
    const STATS_SYNC_CRON_HOOK = 'tmw_cr_slot_banner_scheduled_stats_sync';

    public static function get_settings() {
        $defaults = array(
            'headline'               => self::DEFAULT_HEADLINE,
            'subheadline'            => self::DEFAULT_SUBHEADLINE,
            'spin_button_text'       => self::DEFAULT_SPIN_BUTTON_TEXT,
            'cta_text'               => self::DEFAULT_CTA_TEXT,
            'cta_url'                => 'https://example.test/click',
            'default_image_url'      => '',
            'open_in_new_tab'        => 1,
            'subid_param'            => 'subid',
            'subid_value'            => 'slot',
            'country_overrides_raw'  => '',
            'cr_api_key'             => '',
            'slot_offer_ids'         => array(),
            'slot_offer_priority'    => array(),
            'offer_image_overrides'  => array(),
            'rotation_mode'          => 'manual',
            'optimization_enabled'   => 1,
            'minimum_clicks_threshold' => 10,
            'minimum_conversions_threshold' => 1,
            'minimum_payout_threshold' => 0,
            'exclude_zero_click_offers' => 0,
            'exclude_zero_conversion_offers' => 0,
            'country_decay_enabled' => 1,
            'country_weight' => 0.7,
            'global_weight' => 0.3,
            'decay_min_country_clicks' => 10,
            'fallback_to_global_when_low_sample' => 1,
            'auto_sync_enabled' => 0,
            'auto_sync_frequency' => 'daily',
            'stats_sync_range'       => '30d',
            'optimization_notes' => '',
            'allowed_offer_types' => array( 'pps' ),
        );

        return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
    }

    public static function asset_url( $relative_path ) {
        return 'https://example.test/plugins/tmw/' . ltrim( $relative_path, '/' );
    }

    public static function get_offer_catalog_defaults() {
        return array(
            'cam4' => array(
                'id' => 'cam4',
                'name' => 'CAM4',
                'filename' => 'CAM4.png',
                'aliases' => array( 'cam 4' ),
            ),
            'live-jasmin' => array(
                'id' => 'live-jasmin',
                'name' => 'Live Jasmin',
                'filename' => 'Live Jasmin.png',
                'aliases' => array( 'livejasmin' ),
            ),
            'sex-messenger' => array(
                'id' => 'sex-messenger',
                'name' => 'Sex Messenger',
                'filename' => 'Sex Messenger.png',
                'aliases' => array( 'sexmessenger' ),
            ),
        );
    }
}

class TMW_Test_Admin_Page extends TMW_CR_Slot_Admin_Page {
    public $notice = array();

    protected function redirect_with_notice( $notice_type, $message ) {
        if ( ! empty( $this->notice ) ) {
            return;
        }

        $this->notice = array(
            'type'    => $notice_type,
            'message' => $message,
        );
    }

    protected function assert_admin_action( $nonce_action ) {
        unset( $nonce_action );
    }
}

function tmw_reset_test_state() {
    $GLOBALS['tmw_test_options']      = array();
    $GLOBALS['tmw_test_transients']   = array();
    $GLOBALS['tmw_test_remote_get']   = null;
    $GLOBALS['tmw_test_last_redirect'] = '';
    $GLOBALS['tmw_test_cron_events'] = array();
    $_GET  = array();
    $_POST = array();
}

$tests = array();

$tests['sanitize_settings_preserves_blank_api_key'] = function() {
    tmw_reset_test_state();

    update_option(
        TMW_CR_Slot_Sidebar_Banner::OPTION_KEY,
        array(
            'cr_api_key' => 'secret-key-1234',
        )
    );

    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $sanitized = $page->sanitize_settings(
        array(
            'headline'      => 'H',
            'subheadline'   => 'S',
            'cta_text'      => 'C',
            'cta_url'       => 'https://example.test',
            'subid_param'   => 'Sub_ID',
            'subid_value'   => 'slot',
            'cr_api_key'    => '',
            'slot_offer_ids' => array( '12', '34' ),
            'slot_offer_priority' => array( '12' => '5', '34' => '1' ),
        )
    );

    tmw_assert_same( 'secret-key-1234', $sanitized['cr_api_key'], 'Blank API key should preserve existing value.' );
};

$tests['optimization_thresholds_and_decay_guard_low_sample_outliers'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_offer_stats(
        array(
            'A|US' => array( 'offer_id' => 'A', 'offer_name' => 'A', 'country_name' => 'US', 'clicks' => 1, 'conversions' => 1, 'payout' => 20, 'epc' => 20 ),
            'A|GLOBAL' => array( 'offer_id' => 'A', 'offer_name' => 'A', 'country_name' => 'GLOBAL', 'clicks' => 100, 'conversions' => 10, 'payout' => 100, 'epc' => 1 ),
            'B|US' => array( 'offer_id' => 'B', 'offer_name' => 'B', 'country_name' => 'US', 'clicks' => 50, 'conversions' => 10, 'payout' => 75, 'epc' => 1.5 ),
        )
    );

    $offers = array(
        array( 'id' => 'A', 'name' => 'Offer A' ),
        array( 'id' => 'B', 'name' => 'Offer B' ),
    );

    $ranked = $repository->rank_offers_for_slot(
        $offers,
        array(
            'rotation_mode' => 'safe_hybrid_score',
            'optimization_enabled' => 1,
            'minimum_clicks_threshold' => 25,
            'minimum_conversions_threshold' => 2,
            'country_decay_enabled' => 1,
            'country_weight' => 0.8,
            'global_weight' => 0.2,
            'decay_min_country_clicks' => 10,
            'fallback_to_global_when_low_sample' => 1,
        ),
        'US',
        array()
    );

    tmw_assert_same( 'B', $ranked[0]['id'], 'Low-sample US outlier should not outrank established offer when thresholds apply.' );
};

$tests['sanitize_settings_defaults_allowed_offer_types_to_pps'] = function() {
    tmw_reset_test_state();
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $sanitized = $page->sanitize_settings( array() );
    tmw_assert_same( array( 'pps' ), $sanitized['allowed_offer_types'], 'Missing allowed_offer_types should default to PPS.' );
};

$tests['sanitize_settings_filters_invalid_allowed_offer_types'] = function() {
    tmw_reset_test_state();
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $sanitized = $page->sanitize_settings( array( 'allowed_offer_types' => array( 'pps', 'bad', 'soi', 'evil' ) ) );
    tmw_assert_same( array( 'pps', 'soi' ), $sanitized['allowed_offer_types'], 'Invalid allowed_offer_types values should be removed.' );
};

$tests['sanitize_settings_empty_allowed_offer_types_defaults_to_pps'] = function() {
    tmw_reset_test_state();
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $sanitized = $page->sanitize_settings( array( 'allowed_offer_types' => array() ) );
    tmw_assert_same( array( 'pps' ), $sanitized['allowed_offer_types'], 'Empty allowed_offer_types should default to PPS.' );
};

$tests['sanitize_settings_preserves_valid_allowed_offer_types_combination'] = function() {
    tmw_reset_test_state();
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $sanitized = $page->sanitize_settings( array( 'allowed_offer_types' => array( 'pps', 'revshare', 'soi' ) ) );
    tmw_assert_same( array( 'pps', 'revshare', 'soi' ), $sanitized['allowed_offer_types'], 'Valid allowed_offer_types combination should be preserved.' );
};

$tests['offer_type_detection_cases'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_assert_same( array( 'pps' ), $repository->get_offer_type_keys( array( 'name' => 'Jerkmate - PPS' ) ), 'PPS should be detected from name.' );
    tmw_assert_same( array( 'pps' ), $repository->get_offer_type_keys( array( 'name' => 'Instabang - PPS - Premium' ) ), 'PPS should be detected when surrounded by other words.' );
    tmw_assert_same( array( 'revshare' ), $repository->get_offer_type_keys( array( 'name' => 'Jerkmate - Revshare Lifetime' ) ), 'Revshare should be detected from name.' );
    tmw_assert_same( array( 'soi' ), $repository->get_offer_type_keys( array( 'name' => 'Bongacams - SOI' ) ), 'SOI should be detected from name.' );
    tmw_assert_same( array( 'doi' ), $repository->get_offer_type_keys( array( 'name' => 'Stripchat - DOI' ) ), 'DOI should be detected from name.' );
    tmw_assert_same( array( 'smartlink', 'cpa' ), $repository->get_offer_type_keys( array( 'name' => 'CR Smartlink - Multi-CPA - Global Adult Traffic' ) ), 'Smartlink + CPA should both be detected.' );
    tmw_assert_same( array( 'cpl' ), $repository->get_offer_type_keys( array( 'name' => 'Jerkmate - TX - PPL' ) ), 'PPL should normalize to CPL key.' );
    tmw_assert_same( array( 'cpc' ), $repository->get_offer_type_keys( array( 'name' => 'Conexo Madura - CPC - BR' ) ), 'CPC should normalize to CPC key.' );
    tmw_assert_same( array( 'fallback', 'pps' ), $repository->get_offer_type_keys( array( 'name' => 'Group Fallback - Jerkmate - PPS - DE-AT-CH' ) ), 'Fallback and PPS should both be detected.' );
    tmw_assert_same( array( 'pps', 'revshare' ), $repository->get_offer_type_keys( array( 'name' => 'Bongacams - PPS + Revshare lifetime' ) ), 'Mixed offers should return all detected type keys.' );
};

$tests['offer_type_allowlist_behavior_cases'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_assert_true( $repository->is_offer_type_allowed( array( 'name' => 'Jerkmate - PPS' ), array( 'allowed_offer_types' => array( 'pps' ) ) ), 'PPS-only should allow PPS offers.' );
    tmw_assert_true( ! $repository->is_offer_type_allowed( array( 'name' => 'Jerkmate - Revshare Lifetime' ), array( 'allowed_offer_types' => array( 'pps' ) ) ), 'PPS-only should reject Revshare-only offers.' );
    tmw_assert_true( $repository->is_offer_type_allowed( array( 'name' => 'Bongacams - PPS + Revshare lifetime' ), array( 'allowed_offer_types' => array( 'pps' ) ) ), 'Mixed PPS+Revshare should be allowed when PPS is enabled.' );
    tmw_assert_true( $repository->is_offer_type_allowed( array( 'name' => 'Jerkmate - Revshare Lifetime' ), array( 'allowed_offer_types' => array( 'pps', 'revshare' ) ) ), 'PPS+Revshare should allow Revshare offers.' );
    tmw_assert_true( $repository->is_offer_type_allowed( array( 'name' => 'CR Smartlink - Multi-CPA' ), array( 'allowed_offer_types' => array( 'smartlink', 'cpa' ) ) ), 'Smartlink + CPA allowlist should allow Smartlink Multi-CPA.' );
    tmw_assert_true( ! $repository->is_offer_type_allowed( array( 'name' => 'Unknown Campaign Name' ), array( 'allowed_offer_types' => array( 'pps' ) ) ), 'Unknown type should return false when no supported type is detected.' );
    tmw_assert_true( $repository->is_offer_type_allowed( array( 'name' => 'Custom Fallback - Unknown Campaign Name' ), array( 'allowed_offer_types' => array( 'fallback' ) ) ), 'Fallback offers should be allowed when fallback is selected.' );
};

$tests['optimization_excludes_zero_clicks_and_zero_conversions'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_offer_stats(
        array(
            'A|GLOBAL' => array( 'offer_id' => 'A', 'offer_name' => 'A', 'country_name' => 'GLOBAL', 'clicks' => 0, 'conversions' => 0, 'payout' => 0, 'epc' => 0 ),
            'B|GLOBAL' => array( 'offer_id' => 'B', 'offer_name' => 'B', 'country_name' => 'GLOBAL', 'clicks' => 20, 'conversions' => 0, 'payout' => 12, 'epc' => 0.6 ),
            'C|GLOBAL' => array( 'offer_id' => 'C', 'offer_name' => 'C', 'country_name' => 'GLOBAL', 'clicks' => 20, 'conversions' => 2, 'payout' => 20, 'epc' => 1 ),
        )
    );
    $offers = array( array( 'id' => 'A', 'name' => 'A' ), array( 'id' => 'B', 'name' => 'B' ), array( 'id' => 'C', 'name' => 'C' ) );
    $ranked = $repository->rank_offers_for_slot( $offers, array( 'rotation_mode' => 'epc_desc', 'optimization_enabled' => 1, 'exclude_zero_click_offers' => 1, 'exclude_zero_conversion_offers' => 1 ), 'US', array() );
    tmw_assert_same( 1, count( $ranked ), 'Zero click and zero conversion offers should be excluded from optimization ranking when configured.' );
    tmw_assert_same( 'C', $ranked[0]['id'], 'Remaining eligible offer should rank.' );
};

$tests['manual_mode_unaffected_by_optimization_filters'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $offers = array( array( 'id' => 'A', 'name' => 'A' ), array( 'id' => 'B', 'name' => 'B' ) );
    $ranked = $repository->rank_offers_for_slot( $offers, array( 'rotation_mode' => 'manual', 'optimization_enabled' => 1, 'exclude_zero_click_offers' => 1 ), 'US', array( 'B' => 1, 'A' => 2 ) );
    tmw_assert_same( 'B', $ranked[0]['id'], 'Manual mode should still honor manual priorities only.' );
};

$tests['performance_tab_renders_optimization_controls_and_explainability'] = function() {
    tmw_reset_test_state();
    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure', 'rotation_mode' => 'safe_hybrid_score', 'optimization_enabled' => 1 ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_synced_offers( array( '901' => array( 'id' => '901', 'name' => 'Offer 901', 'status' => 'active' ) ) );
    $repository->save_offer_stats( array( '901|GLOBAL' => array( 'offer_id' => '901', 'offer_name' => 'Offer 901', 'country_name' => 'GLOBAL', 'clicks' => 10, 'conversions' => 1, 'payout' => 9, 'epc' => 0.9 ) ) );
    $_GET = array( 'tab' => 'performance' );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );
    ob_start();
    $page->render_page();
    $html = ob_get_clean();
    tmw_assert_contains( 'minimum_clicks_threshold', $html, 'Performance tab should render optimization controls.' );
    tmw_assert_contains( 'Optimization Explainability', $html, 'Performance tab should include explainability section.' );
};

$tests['scheduled_auto_sync_registration_unschedule_and_no_duplicates'] = function() {
    tmw_reset_test_state();
    tmw_assert_same( false, wp_next_scheduled( TMW_CR_Slot_Stats_Sync_Service::CRON_HOOK ), 'No cron event should exist initially.' );
    TMW_CR_Slot_Stats_Sync_Service::ensure_cron_schedule( 'hourly' );
    $first = wp_next_scheduled( TMW_CR_Slot_Stats_Sync_Service::CRON_HOOK );
    tmw_assert_true( false !== $first, 'Cron should be registered when enabled.' );
    TMW_CR_Slot_Stats_Sync_Service::ensure_cron_schedule( 'hourly' );
    tmw_assert_same( 1, count( $GLOBALS['tmw_test_cron_events'] ), 'Duplicate cron events should not be created.' );
    TMW_CR_Slot_Stats_Sync_Service::clear_cron_schedule();
    tmw_assert_same( false, wp_next_scheduled( TMW_CR_Slot_Stats_Sync_Service::CRON_HOOK ), 'Cron should be removed when disabled.' );
};

$tests['country_blended_score_and_global_fallback_flags'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_synced_offers( array( 'X' => array( 'id' => 'X', 'name' => 'Offer X', 'status' => 'active' ) ) );
    $repository->save_offer_stats(
        array(
            'X|US' => array( 'offer_id' => 'X', 'offer_name' => 'Offer X', 'country_name' => 'US', 'clicks' => 3, 'conversions' => 1, 'payout' => 12, 'epc' => 4 ),
            'X|GLOBAL' => array( 'offer_id' => 'X', 'offer_name' => 'Offer X', 'country_name' => 'GLOBAL', 'clicks' => 100, 'conversions' => 10, 'payout' => 100, 'epc' => 1 ),
        )
    );
    $rows = $repository->get_optimization_explain_rows(
        'US',
        array(
            'slot_offer_ids' => array( 'X' ),
            'rotation_mode' => 'safe_hybrid_score',
            'country_decay_enabled' => 1,
            'country_weight' => 0.8,
            'global_weight' => 0.2,
            'decay_min_country_clicks' => 10,
            'fallback_to_global_when_low_sample' => 1,
        ),
        5
    );
    tmw_assert_true( ! empty( $rows ), 'Explain rows should return ranked offers.' );
    tmw_assert_same( 1, (int) $rows[0]['used_global_fallback'], 'Low sample country data should trigger global fallback flag.' );
};

$tests['dashboard_does_not_leak_raw_api_key'] = function() {
    tmw_reset_test_state();
    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'super-secret-api-key' ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );
    $_GET = array( 'tab' => 'settings' );
    ob_start();
    $page->render_page();
    $html = ob_get_clean();
    tmw_assert_true( false === strpos( $html, 'super-secret-api-key' ), 'Raw API key must never be rendered in dashboard HTML.' );
};

$tests['offer_override_resolution_and_country_filters'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_synced_offers(
        array(
            '100' => array( 'id' => '100', 'name' => 'Offer 100', 'status' => 'active', 'preview_url' => 'https://preview.test/100' ),
            '101' => array( 'id' => '101', 'name' => 'Offer 101', 'status' => 'active', 'preview_url' => 'https://preview.test/101' ),
            '102' => array( 'id' => '102', 'name' => 'Offer 102', 'status' => 'active', 'preview_url' => 'https://preview.test/102' ),
        )
    );

    $repository->save_offer_overrides(
        array(
            '100' => array(
                'enabled' => 1,
                'final_url_override' => 'https://override.test/100',
                'custom_cta_text' => 'Claim 100',
                'allowed_countries' => array( 'US' ),
                'image_url_override' => 'https://img.test/new-100.png',
            ),
            '101' => array(
                'enabled' => 0,
            ),
            '102' => array(
                'enabled' => 1,
                'blocked_countries' => array( 'US' ),
            ),
        )
    );

    $settings = array(
        'slot_offer_ids' => array( '100', '101', '102' ),
        'slot_offer_priority' => array( '100' => 1, '101' => 2, '102' => 3 ),
        'offer_image_overrides' => array( '100' => 'https://img.test/legacy-100.png' ),
    );
    $banner_data = array( 'cta_url' => 'https://base.test/click', 'cta_text' => 'Base CTA' );

    $effective = $repository->get_effective_offer_record( '100', $settings, $banner_data, 'US', array() );
    tmw_assert_same( 'https://override.test/100', $effective['cta_url'], 'Per-offer final_url_override should win.' );
    tmw_assert_same( 'https://img.test/new-100.png', $effective['image'], 'New image override should win over legacy image map.' );
    tmw_assert_same( 'Claim 100', $effective['cta_text'], 'custom_cta_text override should win.' );

    $fallback_url = $repository->get_effective_cta_url( '101', $settings, $banner_data, array( 'id' => '101', 'name' => 'Offer 101' ), array() );
    tmw_assert_contains( 'https://base.test/click', $fallback_url, 'Base CTA fallback should remain usable.' );

    $preview_only_url = $repository->get_effective_cta_url( '101', $settings, array( 'cta_url' => '' ), array( 'id' => '101', 'name' => 'Offer 101', 'preview_url' => 'https://preview.test/101' ), array() );
    tmw_assert_same( '', $preview_only_url, 'preview_url should not be used as CTA fallback.' );

    tmw_assert_true( ! $repository->is_offer_allowed_for_country( '101', 'US', $repository->get_offer_override( '101' ), array(), array() ), 'Disabled offer should be excluded.' );
    tmw_assert_true( $repository->is_offer_allowed_for_country( '100', 'US', $repository->get_offer_override( '100' ), array(), array() ), 'Allowed countries should permit matching country.' );
    tmw_assert_true( ! $repository->is_offer_allowed_for_country( '102', 'US', $repository->get_offer_override( '102' ), array(), array() ), 'Blocked countries should exclude matching country.' );
};

$tests['empty_offer_cta_override_falls_back_to_global_then_default'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );

    $from_global = $repository->get_effective_cta_text( '100', array(), array( 'cta_text' => 'GLOBAL CTA' ), array(), array( 'custom_cta_text' => '' ), array() );
    tmw_assert_same( 'GLOBAL CTA', $from_global, 'Empty custom CTA should fallback to global CTA text.' );

    $from_default = $repository->get_effective_cta_text( '100', array(), array( 'cta_text' => '' ), array(), array( 'custom_cta_text' => '' ), array() );
    tmw_assert_same( TMW_CR_Slot_Sidebar_Banner::DEFAULT_CTA_TEXT, $from_default, 'Empty global CTA should fallback to plugin default CTA text.' );
};

$tests['frontend_pool_filters_and_legacy_fallback_to_three'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_synced_offers(
        array(
            '200' => array( 'id' => '200', 'name' => 'Offer 200 - PPS', 'status' => 'active', 'preview_url' => 'https://preview.test/200' ),
            '201' => array( 'id' => '201', 'name' => 'Offer 201 - PPS', 'status' => 'active', 'preview_url' => 'https://preview.test/201' ),
        )
    );
    $repository->save_offer_overrides(
        array(
            '201' => array(
                'enabled' => 1,
                'allowed_countries' => array( 'CA' ),
            ),
        )
    );

    $settings = array(
        'allowed_offer_types' => array( 'pps', 'fallback' ),
        'slot_offer_ids' => array( '201', '200' ),
        'slot_offer_priority' => array( '201' => 1, '200' => 2 ),
        'offer_image_overrides' => array( '200' => 'https://img.test/legacy-200.png' ),
    );
    $legacy = array(
        'legacy-a' => array( 'id' => 'legacy-a', 'name' => 'Legacy A', 'cta_text' => 'Legacy CTA A', 'countries' => array( 'US' ) ),
        'legacy-b' => array( 'id' => 'legacy-b', 'name' => 'Legacy B', 'cta_text' => 'Legacy CTA B', 'countries' => array( 'US' ) ),
        'legacy-c' => array( 'id' => 'legacy-c', 'name' => 'Legacy C', 'cta_text' => 'Legacy CTA C', 'countries' => array( 'US' ) ),
    );

    $offers = $repository->get_frontend_slot_offers( 'sidebar', $settings, array( 'cta_url' => 'https://base.test', 'cta_text' => 'Base CTA' ), 'US', $legacy );

    tmw_assert_same( 3, count( $offers ), 'Legacy fallback should fill the reel pool to 3 offers.' );
    tmw_assert_same( '200', $offers[0]['id'], 'Priority ordering should persist for eligible synced offers.' );
    tmw_assert_same( 'https://img.test/legacy-200.png', $offers[0]['image'], 'Legacy offer_image_overrides should remain compatible.' );
    tmw_assert_true( '201' !== $offers[0]['id'], 'Country-ineligible synced offers should be removed from pool.' );
};

$tests['frontend_pool_excludes_invalid_winner_affiliate_urls'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_synced_offers(
        array(
            '701' => array( 'id' => '701', 'name' => 'Offer 701 - PPS', 'status' => 'active', 'tracking_url' => 'https://valid.test/path?transaction_id=abc123' ),
            '702' => array( 'id' => '702', 'name' => 'Offer 702 - PPS', 'status' => 'active', 'preview_url' => 'https://valid.test/path?transaction_id=preview' ),
            '703' => array( 'id' => '703', 'name' => 'Offer 703 - PPS', 'status' => 'active', 'preview_url' => 'https://track.test/click?aid=affiliate_id' ),
            '704' => array( 'id' => '704', 'name' => 'Offer 704 - PPS', 'status' => 'active', 'preview_url' => 'https://ads.advertisingpolicies.com/path' ),
            '705' => array( 'id' => '705', 'name' => 'Offer 705 - PPS', 'status' => 'active', 'preview_url' => 'https://track.test/click?src=source' ),
            '706' => array( 'id' => '706', 'name' => 'Offer 706 - PPS', 'status' => 'active', 'preview_url' => 'https://track.test/template/click' ),
        )
    );

    $settings = array(
        'allowed_offer_types' => array( 'pps' ),
        'slot_offer_ids' => array( '701', '702', '703', '704', '705', '706' ),
    );

    $offers = $repository->get_frontend_slot_offers( 'sidebar', $settings, array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_same( 1, count( $offers ), 'Only valid winner CTA URLs should remain in frontend pool.' );
    tmw_assert_same( '701', (string) $offers[0]['id'], 'Valid CTA URL offer should remain eligible.' );
};

$tests['image_resolver_chain_prefers_manual_then_local_then_remote_then_placeholder'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $legacy     = TMW_CR_Slot_Sidebar_Banner::get_offer_catalog_defaults();

    $manual = $repository->resolve_synced_offer_image(
        array( 'id' => '500', 'name' => 'CAM4' ),
        array( 'offer_image_overrides' => array( '500' => 'https://img.test/manual.png' ) ),
        $legacy,
        array( 'image_url_override' => 'https://img.test/full-control.png' )
    );
    tmw_assert_same( 'https://img.test/full-control.png', $manual, 'Full-control override must win.' );

    $local = $repository->resolve_synced_offer_image(
        array( 'id' => '501', 'name' => 'LiveJasmin' ),
        array(),
        $legacy
    );
    tmw_assert_contains( 'assets/img/offers/Live Jasmin.png', $local, 'Local alias match should return bundled image URL.' );

    $remote = $repository->resolve_synced_offer_image(
        array( 'id' => '502', 'name' => 'OnlyFans' ),
        array(),
        $legacy
    );
    tmw_assert_contains( 'upload.wikimedia.org', $remote, 'Known remote map entries should resolve to explicit URLs.' );

    $placeholder = $repository->resolve_synced_offer_image(
        array( 'id' => '503', 'name' => 'Unknown Offer XYZ' ),
        array(),
        $legacy
    );
    tmw_assert_true( 0 === strpos( $placeholder, 'data:image/svg+xml;base64,' ), 'Unknown offers should fallback to placeholder image.' );
};

$tests['alias_normalization_handles_spaces_dashes_and_case'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );

    tmw_assert_same( 'live jasmin', $repository->normalize_offer_name_for_image_match( ' LIVE-Jasmin ' ), 'Normalization should collapse dashes and case.' );
    tmw_assert_same( 'sex messenger', $repository->normalize_offer_name_for_image_match( 'Sex_Messenger' ), 'Normalization should normalize separators.' );
    tmw_assert_same( 'my free cams', $repository->normalize_offer_name_for_image_match( 'my   free   cams' ), 'Normalization should collapse spaces.' );
};

$tests['synced_offer_normalization_keeps_frontend_pool_behavior'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_synced_offers(
        array(
            '601' => array( 'id' => '601', 'name' => 'SexMessenger - PPS', 'status' => 'active', 'preview_url' => 'https://preview.test/601' ),
            '602' => array( 'id' => '602', 'name' => 'Paused Offer', 'status' => 'paused', 'preview_url' => 'https://preview.test/602' ),
        )
    );

    $settings = array(
        'slot_offer_ids' => array( '601', '602' ),
        'allowed_offer_types' => array( 'pps' ),
        'slot_offer_priority' => array( '601' => 1, '602' => 2 ),
    );

    $offers = $repository->get_frontend_slot_offers( 'sidebar', $settings, array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', TMW_CR_Slot_Sidebar_Banner::get_offer_catalog_defaults() );
    tmw_assert_same( '601', $offers[0]['id'], 'Active synced offers should still normalize for frontend slot pool.' );
    tmw_assert_contains( 'assets/logos/80x80/sex-messenger-80x80-transparent.png', (string) ( $offers[0]['logo_url'] ?? '' ), 'Known mapped logos should resolve safely when assets are present.' );
};

$tests['admin_sanitize_and_render_supports_offer_overrides'] = function() {
    tmw_reset_test_state();

    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure-key' ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_synced_offers(
        array(
            '301' => array( 'id' => '301', 'name' => 'Offer 301 - PPS', 'status' => 'active', 'preview_url' => 'https://preview.test/301' ),
        )
    );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    $page->sanitize_settings(
        array(
            'headline' => 'Headline',
            'subheadline' => 'Subheadline',
            'cta_text' => 'CTA',
            'cta_url' => 'https://base.test',
            'subid_param' => 'subid',
            'subid_value' => 'slot',
            'cr_api_key' => '',
            'offer_overrides' => array(
                '301' => array(
                    'enabled' => '1',
                    'final_url_override' => 'https://final.test/301',
                    'image_url_override' => 'https://img.test/301.png',
                    'custom_cta_text' => 'Go 301',
                    'allowed_countries' => 'us,ca',
                    'blocked_countries' => 'fr',
                    'label_override' => 'Offer 301 Label',
                    'notes' => 'Internal note',
                ),
            ),
        )
    );

    $override = $repository->get_offer_override( '301' );
    tmw_assert_same( 'https://final.test/301', $override['final_url_override'], 'Admin sanitize should persist final URL override.' );
    tmw_assert_same( 'US', $override['allowed_countries'][0], 'Country lists should be sanitized to uppercase ISO-2.' );

    $_GET = array( 'tab' => 'slot-setup', 'include_all_offers' => 1 );
    ob_start();
    $page->render_page();
    $html = ob_get_clean();

    tmw_assert_contains( 'offer_overrides', $html, 'Slot setup should render new override fields.' );
    tmw_assert_contains( '[TMW-CR-DASH] Destination:', $html, 'Slot setup should render effective destination indicator.' );
    tmw_assert_true( false === strpos( $html, 'secure-key' ), 'Admin render path must not leak raw API key.' );
};

$tests['slot_setup_shows_winner_mode_diagnostics'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            'safe1' => array( 'id' => 'safe1', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
        )
    );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );
    $_GET = array( 'tab' => 'slot-setup', 'include_all_offers' => 1 );

    ob_start();
    $page->render_page();
    $html = ob_get_clean();

    tmw_assert_contains( 'Eligible winner offers:', $html, 'Slot setup should show eligible winner pool count.' );
    tmw_assert_contains( 'Winner mode: forced three-logo match', $html, 'Slot setup should show forced winner mode.' );
    tmw_assert_contains( 'Final reel behavior: one selected offer repeated across 3 reels', $html, 'Slot setup should describe final reel behavior.' );
};

$tests['extract_offer_rows_supports_response_data_and_keyed_collections'] = function() {
    tmw_reset_test_state();

    $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows(
        array(
            'response' => array(
                'data' => array(
                    'first' => array( 'id' => '10', 'name' => 'Offer Ten' ),
                    'second' => array( 'id' => '11', 'name' => 'Offer Eleven' ),
                ),
            ),
        )
    );

    tmw_assert_same( 2, count( $rows ), 'Keyed response.data collections should be accepted.' );
    tmw_assert_same( '10', $rows[0]['id'], 'Rows should be reindexed by array_values.' );
};

$tests['extract_offer_rows_supports_results_shapes'] = function() {
    tmw_reset_test_state();

    $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows(
        array(
            'results' => array(
                array( 'id' => '9', 'name' => 'Offer Nine' ),
            ),
        )
    );

    tmw_assert_same( 1, count( $rows ), 'results shape should be accepted.' );
    tmw_assert_same( 'results', TMW_CR_Slot_Offer_Sync_Service::detect_response_shape( array( 'results' => array( array( 'id' => '9' ) ) ) ), 'results shape should be detected.' );
};

$tests['extract_offer_rows_unwraps_response_envelope_data_list'] = function() {
    tmw_reset_test_state();

    $response = array(
        'response' => array(
            'status' => true,
            'httpStatus' => 200,
            'data' => array(
                array(
                    'id' => '123',
                    'name' => 'Offer A',
                    'status' => 'active',
                ),
                array(
                    'id' => '124',
                    'name' => 'Offer B',
                    'status' => 'active',
                ),
            ),
            'errors' => array(),
            'errorMessage' => '',
        ),
    );

    $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $response );

    tmw_assert_same( 2, count( $rows ), 'Envelope response rows should be unwrapped from response.data.' );
    tmw_assert_same( '123', $rows[0]['id'], 'First nested response offer should be preserved.' );
    tmw_assert_same( 'response.envelope.data', TMW_CR_Slot_Offer_Sync_Service::detect_response_shape( $response ), 'Shape diagnostics should indicate envelope unwrapping.' );
};

$tests['extract_offer_rows_unwraps_double_nested_response_data_data'] = function() {
    tmw_reset_test_state();

    $response = array(
        'response' => array(
            'status' => true,
            'httpStatus' => 200,
            'data' => array(
                'data' => array(
                    array( 'id' => '123', 'name' => 'Offer A', 'status' => 'active' ),
                ),
            ),
            'errors' => array(),
            'errorMessage' => '',
        ),
    );

    $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $response );

    tmw_assert_same( 1, count( $rows ), 'Double nested response.data.data rows should be extracted.' );
    tmw_assert_same( '123', $rows[0]['id'], 'Double nested row should keep offer id.' );
    tmw_assert_same( 'response.envelope.data.data', TMW_CR_Slot_Offer_Sync_Service::detect_response_shape( $response ), 'Shape diagnostics should include nested data path.' );
};

$tests['extract_offer_rows_supports_top_level_keyed_collection'] = function() {
    tmw_reset_test_state();

    $response = array(
        'offer_a' => array( 'id' => '41', 'name' => 'Forty One' ),
        'offer_b' => array( 'id' => '42', 'name' => 'Forty Two' ),
    );

    $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $response );

    tmw_assert_same( 2, count( $rows ), 'Top-level keyed collections should be accepted.' );
    tmw_assert_same( 'top-level:keyed', TMW_CR_Slot_Offer_Sync_Service::detect_response_shape( $response ), 'Top-level keyed shape should be detected.' );
};

$tests['extract_offer_rows_supports_keyed_collection_with_scalar_metadata'] = function() {
    tmw_reset_test_state();

    $response = array(
        'offer_a' => array( 'id' => '51', 'name' => 'Fifty One' ),
        'meta'    => 'page-1',
        'offer_b' => array( 'id' => '52', 'name' => 'Fifty Two' ),
        'count'   => 2,
    );

    $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $response );

    tmw_assert_same( 2, count( $rows ), 'Keyed payloads with scalar metadata should keep valid rows.' );
    tmw_assert_same( '51', $rows[0]['id'], 'First valid offer row should be preserved.' );
};

$tests['extract_offer_rows_uses_non_empty_fallback_shape'] = function() {
    tmw_reset_test_state();

    $response = array(
        'response' => array(
            'data' => array(),
        ),
        'results' => array(
            array( 'id' => '61', 'name' => 'Fallback Offer' ),
        ),
    );

    $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $response );

    tmw_assert_same( 1, count( $rows ), 'Extractor should continue to later non-empty fallback shapes.' );
    tmw_assert_same( '61', $rows[0]['id'], 'Fallback rows should be returned when earlier candidates are empty.' );
    tmw_assert_same( 'results', TMW_CR_Slot_Offer_Sync_Service::detect_response_shape( $response ), 'Detected shape should prefer first non-empty candidate.' );
};

$tests['normalize_offer_supports_nested_offer_wrappers_and_id_aliases'] = function() {
    tmw_reset_test_state();

    $offer_upper = TMW_CR_Slot_Offer_Sync_Service::normalize_offer(
        array(
            'Offer' => array(
                'ID' => '88',
                'name' => 'Upper Offer',
                'featured' => '2026-04-20 00:00:00',
            ),
        )
    );

    $offer_lower = TMW_CR_Slot_Offer_Sync_Service::normalize_offer(
        array(
            'offer' => array(
                'offer_id' => '77',
                'name' => 'Lower Offer',
                'featured' => '0000-00-00 00:00:00',
            ),
        )
    );

    tmw_assert_same( '88', $offer_upper['id'], 'ID alias should normalize to id.' );
    tmw_assert_same( '77', $offer_lower['id'], 'offer_id alias should normalize to id.' );
    tmw_assert_true( true === $offer_upper['is_featured'], 'Non-empty featured timestamp should be featured.' );
    tmw_assert_true( false === $offer_lower['is_featured'], 'Zero-date featured timestamp should not be featured.' );
};

$tests['sync_default_fields_include_url_and_normalize_uses_it_for_tracking'] = function() {
    tmw_reset_test_state();
    $fields = TMW_CR_Slot_Offer_Sync_Service::get_default_offer_fields();
    tmw_assert_true( in_array( 'url', $fields, true ), 'Default sync fields should request url.' );

    $normalized = TMW_CR_Slot_Offer_Sync_Service::normalize_offer(
        array(
            'id' => '9001',
            'name' => 'URL Only Offer',
            'url' => 'https://gateway.crakrevenue.com/click/url-only',
        )
    );
    tmw_assert_same( 'https://gateway.crakrevenue.com/click/url-only', (string) $normalized['tracking_url'], 'url field should populate tracking_url when present.' );
};

$tests['sync_imports_nested_offer_rows'] = function() {
    tmw_reset_test_state();

    $GLOBALS['tmw_test_remote_get'] = function() {
        static $page = 0;
        ++$page;

        if ( 1 === $page ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'response' => array(
                            'results' => array(
                                array(
                                    'Offer' => array(
                                        'ID' => '301',
                                        'name' => 'Wrapped Offer',
                                        'status' => 'active',
                                        'preview_url' => 'https://preview.test/301',
                                    ),
                                ),
                                array(
                                    'offer' => array(
                                        'offer_id' => '302',
                                        'name' => 'Lower Wrapped',
                                        'status' => 'active',
                                    ),
                                ),
                            ),
                        ),
                    )
                ),
            );
        }

        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'results' => array() ) ) ),
        );
    };

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $result = TMW_CR_Slot_Offer_Sync_Service::sync_all( new TMW_CR_Slot_CR_API_Client( 'sync-key' ), $repository );

    tmw_assert_true( ! is_wp_error( $result ), 'Sync should succeed for wrapped rows.' );
    $offers = $repository->get_synced_offers();
    tmw_assert_same( 'Wrapped Offer', $offers['301']['name'], 'Offer wrapper rows should import.' );
    tmw_assert_same( 'Lower Wrapped', $offers['302']['name'], 'offer wrapper rows should import.' );
};

$tests['sync_soft_failure_preserves_previous_offers'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array( 'existing' => array( 'id' => 'existing', 'name' => 'Existing Offer' ) ) );

    $GLOBALS['tmw_test_remote_get'] = function() {
        static $page = 0;
        ++$page;

        if ( 1 === $page ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'response' => array(
                            'data' => array(
                                array( 'name' => 'No ID Offer', 'status' => 'active' ),
                            ),
                        ),
                    )
                ),
            );
        }

        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'data' => array() ) ) ),
        );
    };

    $result = TMW_CR_Slot_Offer_Sync_Service::sync_all( new TMW_CR_Slot_CR_API_Client( 'sync-key' ), $repository );

    tmw_assert_true( ! is_wp_error( $result ), 'Soft failure should return a non-error result.' );
    tmw_assert_true( ! empty( $result['preserved_previous'] ), 'Soft failure should preserve previous offers.' );
    tmw_assert_same( 'Existing Offer', $repository->get_synced_offers()['existing']['name'], 'Previous offers should not be overwritten on soft failure.' );

    $meta = $repository->get_sync_meta();
    tmw_assert_same( 1, (int) $meta['last_soft_failure'], 'Soft failure flag should be set in sync meta.' );
    tmw_assert_same( 1, (int) $meta['last_raw_row_count'], 'Raw row count should be recorded.' );
    tmw_assert_same( 1, (int) $meta['last_skipped_count'], 'Skipped count should be recorded.' );
};

$tests['sync_imports_nested_envelope_rows_and_avoids_soft_failure'] = function() {
    tmw_reset_test_state();

    $GLOBALS['tmw_test_remote_get'] = function() {
        static $page = 0;
        ++$page;

        if ( 1 === $page ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'response' => array(
                            'status' => true,
                            'httpStatus' => 200,
                            'data' => array(
                                array( 'id' => '123', 'name' => 'Offer A', 'status' => 'active' ),
                                array( 'id' => '124', 'name' => 'Offer B', 'status' => 'active' ),
                            ),
                            'errors' => array(),
                            'errorMessage' => '',
                        ),
                    )
                ),
            );
        }

        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'status' => true, 'httpStatus' => 200, 'data' => array(), 'errors' => array(), 'errorMessage' => '' ) ) ),
        );
    };

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $result     = TMW_CR_Slot_Offer_Sync_Service::sync_all( new TMW_CR_Slot_CR_API_Client( 'sync-key' ), $repository );

    tmw_assert_true( ! is_wp_error( $result ), 'Envelope sync should succeed.' );
    tmw_assert_same( 2, (int) $result['offer_count'], 'Envelope sync should import both offers.' );
    tmw_assert_same( 0, (int) $result['last_soft_failure'], 'Envelope sync should not flag parser soft failure.' );
    tmw_assert_same( 'response.envelope.data', $result['last_response_shape'], 'Envelope sync should report nested shape.' );
    tmw_assert_same( 'Offer A', $repository->get_synced_offers()['123']['name'], 'Envelope rows should be stored locally.' );
};

$tests['sync_transport_failure_preserves_existing_offers'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array( 'existing' => array( 'id' => 'existing', 'name' => 'Existing Offer' ) ) );

    $GLOBALS['tmw_test_remote_get'] = function() {
        return new WP_Error( 'offline', 'Offline' );
    };

    $result = TMW_CR_Slot_Offer_Sync_Service::sync_all( new TMW_CR_Slot_CR_API_Client( 'sync-key' ), $repository );

    tmw_assert_true( is_wp_error( $result ), 'Sync should fail on transport errors.' );
    tmw_assert_same( 'Existing Offer', $repository->get_synced_offers()['existing']['name'], 'Transport failure should not wipe offers.' );
};

$tests['sync_meta_stores_diagnostics'] = function() {
    tmw_reset_test_state();

    $GLOBALS['tmw_test_remote_get'] = function() {
        static $page = 0;
        ++$page;

        if ( 1 === $page ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'data' => array(
                            array( 'id' => '12', 'name' => 'Offer Twelve', 'status' => 'active' ),
                            array( 'name' => 'Skipped Row', 'status' => 'active' ),
                        ),
                    )
                ),
            );
        }

        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'data' => array() ) ),
        );
    };

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    TMW_CR_Slot_Offer_Sync_Service::sync_all( new TMW_CR_Slot_CR_API_Client( 'sync-key' ), $repository );

    $meta = $repository->get_sync_meta();
    tmw_assert_same( 2, (int) $meta['last_raw_row_count'], 'Meta should store raw row count.' );
    tmw_assert_same( 1, (int) $meta['last_imported_count'], 'Meta should store imported count.' );
    tmw_assert_same( 1, (int) $meta['last_skipped_count'], 'Meta should store skipped count.' );
    tmw_assert_same( 'data', $meta['last_response_shape'], 'Meta should store response shape.' );
    tmw_assert_contains( 'id', $meta['sample_row_keys'], 'Meta should include sample row keys.' );
};

$tests['handle_test_connection_notices_cover_rows_and_no_rows'] = function() {
    tmw_reset_test_state();

    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'key' ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    $GLOBALS['tmw_test_remote_get'] = function() {
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'data' => array( array( 'id' => '1', 'name' => 'A' ) ) ) ) ),
        );
    };
    $page->notice = array();
    $page->handle_test_connection();
    tmw_assert_contains( 'detected 1 row', strtolower( $page->notice['message'] ), 'Connection notice should include row count when rows exist.' );

    $GLOBALS['tmw_test_remote_get'] = function() {
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'data' => array() ) ) ),
        );
    };
    $page->notice = array();
    $page->handle_test_connection();
    tmw_assert_contains( 'no rows were returned', strtolower( $page->notice['message'] ), 'Connection notice should mention no-row success.' );
};

$tests['handle_sync_offers_notice_includes_counts_and_preserve_flag'] = function() {
    tmw_reset_test_state();

    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'key' ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array( '99' => array( 'id' => '99', 'name' => 'Existing Offer' ) ) );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    $GLOBALS['tmw_test_remote_get'] = function() {
        static $page = 0;
        ++$page;
        if ( 1 === $page ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'response' => array( 'data' => array( array( 'name' => 'No ID', 'status' => 'active' ) ) ) ) ),
            );
        }

        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'data' => array() ) ) ),
        );
    };

    $page->notice = array();
    $page->handle_sync_offers();

    tmw_assert_contains( 'Imported: 0', $page->notice['message'], 'Sync notice should include imported count.' );
    tmw_assert_contains( 'Raw: 1', $page->notice['message'], 'Sync notice should include raw count.' );
    tmw_assert_contains( 'Skipped: 1', $page->notice['message'], 'Sync notice should include skipped count.' );
    tmw_assert_contains( 'preserved', strtolower( $page->notice['message'] ), 'Sync notice should mention preserved offers on soft failure.' );
};

$tests['render_page_shows_sync_diagnostics_and_hides_api_key'] = function() {
    tmw_reset_test_state();

    update_option(
        TMW_CR_Slot_Sidebar_Banner::OPTION_KEY,
        array(
            'cr_api_key' => 'super-secret-key',
            'slot_offer_ids' => array( '10' ),
            'slot_offer_priority' => array( '10' => 2 ),
            'offer_image_overrides' => array( '10' => 'https://img.test/10.png' ),
        )
    );

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '10' => array(
                'id' => '10',
                'name' => 'Offer Ten',
                'status' => 'active',
                'default_payout' => '50.00',
                'percent_payout' => '25.00',
                'currency' => 'USD',
                'payout_type' => 'cpa_both',
                'require_approval' => '1',
                'is_featured' => true,
                'preview_url' => 'https://preview.test/10',
            ),
        )
    );
    $repository->save_sync_meta(
        array(
            'last_synced_at' => '2026-04-20T00:00:00+00:00',
            'last_error' => '[TMW-CR-AUDIT] sample error',
            'offer_count' => 1,
            'last_raw_row_count' => 5,
            'last_imported_count' => 1,
            'last_skipped_count' => 4,
            'last_response_shape' => 'response.data:keyed',
            'last_soft_failure' => 1,
            'sample_row_keys' => 'id,name,status',
        )
    );

    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    ob_start();
    $page->render_page();
    $html = ob_get_clean();

    tmw_assert_contains( 'Last raw/imported/skipped', $html, 'Admin page should show raw row diagnostics.' );
    tmw_assert_contains( 'response.data:keyed', $html, 'Admin page should show response shape diagnostics.' );
    tmw_assert_true( false === strpos( $html, 'super-secret-key' ), 'Rendered admin HTML must not leak raw API key.' );
    tmw_assert_contains( '************-key', $html, 'Rendered admin HTML should show masked API key only.' );
};


$tests['dashboard_summary_counts_and_image_status'] = function() {
    tmw_reset_test_state();

    $settings = array(
        'slot_offer_ids' => array( '11', '13' ),
        'offer_image_overrides' => array( '11' => 'https://img.test/11.png' ),
        'slot_offer_priority' => array( '11' => 2, '13' => 1 ),
    );

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '11' => array( 'id' => '11', 'name' => 'One', 'status' => 'active', 'is_featured' => true, 'require_approval' => '1' ),
            '12' => array( 'id' => '12', 'name' => 'Two', 'status' => 'paused', 'is_featured' => false, 'require_approval' => '0' ),
            '13' => array( 'id' => '13', 'name' => 'Three', 'status' => 'active', 'is_featured' => true, 'require_approval' => '1' ),
        )
    );
    $repository->save_sync_meta( array( 'last_synced_at' => '2026-04-20T00:00:00+00:00', 'last_raw_row_count' => 9, 'last_imported_count' => 3, 'last_skipped_count' => 6, 'last_soft_failure' => 1 ) );

    $summary = $repository->get_dashboard_summary( $settings );

    tmw_assert_same( 3, (int) $summary['stored_offers'], 'Summary should include stored offers count.' );
    tmw_assert_same( 2, (int) $summary['selected_slot_offers'], 'Summary should include selected count.' );
    tmw_assert_same( 2, (int) $summary['active_synced_offers'], 'Summary should include active count.' );
    tmw_assert_same( 2, (int) $summary['featured_synced_offers'], 'Summary should include featured count.' );
    tmw_assert_same( 2, (int) $summary['approval_required_offers'], 'Summary should include approval count.' );
    tmw_assert_same( 1, (int) $summary['manual_image_overrides'], 'Summary should include selected offers with manual image override.' );

    tmw_assert_same( 'manual_override', $repository->get_image_status_for_offer( '11', $settings ), 'Image status should detect manual overrides.' );
    tmw_assert_same( 'placeholder_only', $repository->get_image_status_for_offer( '13', $settings ), 'Image status should detect placeholder-only selected offers.' );
    tmw_assert_same( 'not_selected', $repository->get_image_status_for_offer( '12', $settings ), 'Image status should detect unselected offers.' );
};

$tests['filtered_synced_offers_search_filter_sort_and_selected_indicator'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '21' => array( 'id' => '21', 'name' => 'Alpha Slots', 'status' => 'active', 'is_featured' => true, 'require_approval' => '1', 'payout_type' => 'cpa', 'default_payout' => '22.00' ),
            '22' => array( 'id' => '22', 'name' => 'Bravo Slots', 'status' => 'active', 'is_featured' => false, 'require_approval' => '0', 'payout_type' => 'revshare', 'default_payout' => '80.00' ),
            '23' => array( 'id' => '23', 'name' => 'Charlie', 'status' => 'paused', 'is_featured' => false, 'require_approval' => '1', 'payout_type' => 'cpa', 'default_payout' => '10.00' ),
        )
    );

    $settings = array(
        'slot_offer_ids' => array( '21' ),
        'offer_image_overrides' => array( '21' => 'https://img.test/21.png' ),
    );

    $result = $repository->get_filtered_synced_offers_for_admin(
        array(
            'search' => 'slots',
            'status' => 'active',
            'featured' => 'yes',
            'approval_required' => 'yes',
            'payout_type' => 'cpa',
            'image_status' => 'manual_override',
            'sort_by' => 'name',
            'sort_order' => 'asc',
            'page' => 1,
            'per_page' => 10,
        ),
        $settings
    );

    tmw_assert_same( 1, (int) $result['total'], 'Filter should narrow to one offer.' );
    tmw_assert_same( '21', (string) $result['items'][0]['id'], 'Matching offer should be returned.' );
    tmw_assert_true( ! empty( $result['items'][0]['is_selected_for_slot'] ), 'Result rows should include selected indicator.' );
};

$tests['dashboard_filter_model_and_extended_offer_filters'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '31' => array(
                'id' => '31',
                'name' => 'Geo CPA',
                'status' => 'active',
                'payout_type' => 'cpa',
                'tags' => array( 'vip', 'cash' ),
                'vertical' => 'casino',
                'performs_in' => array( 'US', 'CA' ),
                'optimized_for' => array( 'mobile' ),
                'accepted_countries' => array( 'US', 'GB' ),
                'niche' => array( 'slots' ),
                'promotion_method' => array( 'seo' ),
            ),
            '32' => array(
                'id' => '32',
                'name' => 'Global Revshare',
                'status' => 'paused',
                'payout_type' => 'revshare',
                'tags' => array( 'stream' ),
                'vertical' => 'sportsbook',
                'performs_in' => array( 'DE' ),
                'optimized_for' => array( 'desktop' ),
                'accepted_countries' => array( 'DE' ),
                'niche' => array( 'live' ),
                'promotion_method' => array( 'ppc' ),
            ),
        )
    );

    $model = $repository->get_dashboard_filter_model();
    tmw_assert_true( in_array( 'vip', (array) $model['supported']['tag'], true ), 'Filter model should include tag values.' );
    tmw_assert_true( in_array( 'casino', (array) $model['supported']['vertical'], true ), 'Filter model should include vertical values.' );
    tmw_assert_true( in_array( 'US', (array) $model['supported']['performs_in'], true ), 'Filter model should include performs_in values.' );
    tmw_assert_true( in_array( 'US', (array) $model['supported']['accepted_country'], true ), 'Filter model should include accepted country values.' );

    $settings = array( 'slot_offer_ids' => array( '31', '32' ) );
    $result = $repository->get_filtered_synced_offers_for_admin(
        array(
            'tag' => array( 'vip' ),
            'vertical' => array( 'casino' ),
            'payout_type' => array( 'cpa' ),
            'performs_in' => array( 'US' ),
            'optimized_for' => array( 'mobile' ),
            'accepted_country' => array( 'US' ),
            'niche' => array( 'slots' ),
            'status' => array( 'active' ),
            'promotion_method' => array( 'seo' ),
            'page' => 1,
            'per_page' => 25,
        ),
        $settings
    );
    tmw_assert_same( 1, (int) $result['total'], 'Extended dashboard filters should deterministically match one offer.' );
    tmw_assert_same( '31', (string) $result['items'][0]['id'], 'Extended dashboard filters should return expected offer.' );
};

$tests['dashboard_payout_filter_maps_revshare_to_cr_payout_values'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '71' => array( 'id' => '71', 'name' => 'Rev Offer', 'status' => 'active', 'payout_type' => 'cpa_percentage' ),
            '72' => array( 'id' => '72', 'name' => 'Lifetime Offer', 'status' => 'active', 'payout_type' => 'cpa_flat' ),
        )
    );

    $model = $repository->get_dashboard_filter_model();
    tmw_assert_true( in_array( 'revshare', (array) $model['supported']['payout_type'], true ), 'Payout filter model should expose canonical revshare option.' );
    tmw_assert_true( ! in_array( 'cpa_percentage', (array) $model['supported']['payout_type'], true ), 'Raw payout type values should not leak into canonical filter options.' );

    $result = $repository->get_filtered_synced_offers_for_admin(
        array(
            'payout_type' => array( 'revshare' ),
        ),
        array()
    );

    tmw_assert_same( 1, (int) $result['total'], 'Revshare filter should map to intended stored payout values.' );
    tmw_assert_same( '71', (string) $result['items'][0]['id'], 'Revshare filter should return cpa_percentage offer.' );
};

$tests['dashboard_payout_filter_maps_revshare_lifetime_to_cr_payout_values'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '81' => array( 'id' => '81', 'name' => 'Rev Offer', 'status' => 'active', 'payout_type' => 'cpa_percentage' ),
            '82' => array( 'id' => '82', 'name' => 'Lifetime Offer', 'status' => 'active', 'payout_type' => 'cpa_flat' ),
        )
    );

    $result = $repository->get_filtered_synced_offers_for_admin(
        array(
            'payout_type' => array( 'revshare_lifetime' ),
        ),
        array()
    );

    tmw_assert_same( 1, (int) $result['total'], 'Revshare Lifetime filter should map to intended stored payout values.' );
    tmw_assert_same( '82', (string) $result['items'][0]['id'], 'Revshare Lifetime filter should return cpa_flat offer.' );
};

$tests['dashboard_combined_vertical_and_payout_filters_prevent_false_negatives'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '91' => array( 'id' => '91', 'name' => 'Cam Rev Offer', 'status' => 'active', 'vertical' => 'cam', 'payout_type' => 'cpa_percentage' ),
            '92' => array( 'id' => '92', 'name' => 'Casino Rev Offer', 'status' => 'active', 'vertical' => 'casino', 'payout_type' => 'cpa_percentage' ),
        )
    );

    $result = $repository->get_filtered_synced_offers_for_admin(
        array(
            'vertical' => array( 'cam' ),
            'payout_type' => array( 'revshare' ),
        ),
        array()
    );

    tmw_assert_same( 1, (int) $result['total'], 'Combined cam + payout filter should no longer false-negative valid offers.' );
    tmw_assert_same( '91', (string) $result['items'][0]['id'], 'Combined filters should return the intended cam offer.' );
};

$tests['dashboard_filter_model_only_shows_metadata_backed_values'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '101' => array( 'id' => '101', 'name' => 'No Metadata', 'status' => 'active', 'payout_type' => 'cpa_flat' ),
        )
    );

    $model = $repository->get_dashboard_filter_model();
    tmw_assert_same( array(), (array) $model['supported']['tag'], 'Metadata-backed filters should not show seeded options when offers do not provide metadata.' );
    tmw_assert_true( ! empty( $model['todo']['tag'] ), 'Metadata-backed filters should expose todo guidance when no values are available.' );
};

$tests['dashboard_filters_backward_compatibility_and_override_fallback'] = function() {
    tmw_reset_test_state();
    update_option(
        TMW_CR_Slot_Sidebar_Banner::OPTION_KEY,
        array(
            'offer_overrides' => array(),
        )
    );

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '41' => array( 'id' => '41', 'name' => 'Legacy Offer', 'status' => 'active', 'payout_type' => 'cpa' ),
            '42' => array( 'id' => '42', 'name' => 'Metadata Offer', 'status' => 'active', 'payout_type' => 'cpa', 'tags' => array( 'organic' ) ),
        )
    );
    $repository->save_offer_overrides(
        array(
            '41' => array(
                'dashboard_tags' => 'legacy-tag',
                'dashboard_performs_in' => 'US',
                'dashboard_accepted_countries' => 'US',
                'dashboard_optimized_for' => 'mobile',
                'dashboard_niche' => 'slots',
                'dashboard_promotion_method' => 'seo',
                'dashboard_vertical' => 'casino',
            ),
        )
    );

    $result = $repository->get_filtered_synced_offers_for_admin(
        array(
            'tag' => array( 'legacy-tag' ),
            'vertical' => array( 'casino' ),
            'performs_in' => array( 'US' ),
            'optimized_for' => array( 'mobile' ),
            'accepted_country' => array( 'US' ),
            'niche' => array( 'slots' ),
            'promotion_method' => array( 'seo' ),
        ),
        array()
    );

    tmw_assert_same( 1, (int) $result['total'], 'Legacy offers should remain filterable through override metadata fallback.' );
    tmw_assert_same( '41', (string) $result['items'][0]['id'], 'Override metadata fallback should resolve missing synced fields.' );
};

$tests['offers_tab_renders_expanded_filter_controls'] = function() {
    tmw_reset_test_state();

    update_option(
        TMW_CR_Slot_Sidebar_Banner::OPTION_KEY,
        array(
            'cr_api_key' => 'key',
            'slot_offer_ids' => array(),
            'slot_offer_priority' => array(),
            'offer_image_overrides' => array(),
        )
    );

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '51' => array(
                'id' => '51',
                'name' => 'Filter UI Offer',
                'status' => 'active',
                'payout_type' => 'cpa',
                'tags' => array( 'vip' ),
                'vertical' => 'casino',
                'performs_in' => array( 'US' ),
                'optimized_for' => array( 'mobile' ),
                'accepted_countries' => array( 'US' ),
                'niche' => array( 'slots' ),
                'promotion_method' => array( 'seo' ),
            ),
        )
    );

    $_GET = array( 'tab' => 'offers' );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );
    ob_start();
    $page->render_page();
    $html = ob_get_clean();

    tmw_assert_contains( 'name="tag[]"', $html, 'Offers tab should render Tag filter panel checkboxes.' );
    tmw_assert_contains( 'name="vertical[]"', $html, 'Offers tab should render Vertical filter panel checkboxes.' );
    tmw_assert_contains( 'name="performs_in[]"', $html, 'Offers tab should render Performs In filter panel checkboxes.' );
    tmw_assert_contains( 'name="optimized_for[]"', $html, 'Offers tab should render Optimized For filter panel checkboxes.' );
    tmw_assert_contains( 'name="accepted_country[]"', $html, 'Offers tab should render Accepted Country filter panel checkboxes.' );
    tmw_assert_contains( 'name="niche[]"', $html, 'Offers tab should render Niche filter panel checkboxes.' );
    tmw_assert_contains( 'name="promotion_method[]"', $html, 'Offers tab should render Promotion Method filter panel checkboxes.' );
    tmw_assert_contains( 'tmw-cr-filter-panel__clear', $html, 'Offers tab should render panel Clear All actions.' );
    tmw_assert_contains( 'Clear all', $html, 'Offers tab should render clear-all action.' );
};

$tests['dashboard_local_metadata_layer_preserves_missing_api_families'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '601' => array(
                'id' => '601',
                'name' => 'Rich Meta Offer',
                'status' => 'active',
                'tags' => array( 'vip' ),
                'vertical' => 'casino',
                'performs_in' => array( 'US' ),
                'accepted_countries' => array( 'US' ),
            ),
        )
    );
    $repository->save_synced_offers(
        array(
            '601' => array(
                'id' => '601',
                'name' => 'Missing Meta Offer',
                'status' => 'active',
            ),
        )
    );

    $model = $repository->get_dashboard_filter_model();
    tmw_assert_true( in_array( 'vip', (array) $model['supported']['tag'], true ), 'Local metadata layer should keep prior synced tag metadata when API payload is sparse.' );
    tmw_assert_true( in_array( 'US', (array) $model['supported']['performs_in'], true ), 'Local metadata layer should keep prior country metadata when API payload is sparse.' );
};

$tests['render_page_shows_dashboard_tabs_and_sections'] = function() {
    tmw_reset_test_state();

    update_option(
        TMW_CR_Slot_Sidebar_Banner::OPTION_KEY,
        array(
            'cr_api_key' => 'hidden-api-key',
            'slot_offer_ids' => array( '10' ),
            'slot_offer_priority' => array( '10' => 2 ),
            'offer_image_overrides' => array( '10' => 'https://img.test/10.png' ),
        )
    );

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '10' => array( 'id' => '10', 'name' => 'Offer Ten', 'status' => 'active', 'default_payout' => '50.00', 'currency' => 'USD', 'payout_type' => 'cpa', 'require_approval' => '1', 'is_featured' => true ),
        )
    );

    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    $_GET = array( 'tab' => 'overview' );
    ob_start();
    $page->render_page();
    $html = ob_get_clean();

    tmw_assert_contains( 'Overview', $html, 'Dashboard should render Overview tab.' );
    tmw_assert_contains( 'Offers', $html, 'Dashboard should render Offers tab.' );
    tmw_assert_contains( 'Performance', $html, 'Dashboard should render Performance tab.' );
    tmw_assert_contains( 'Slot Setup', $html, 'Dashboard should render Slot Setup tab.' );
    tmw_assert_contains( 'Settings', $html, 'Dashboard should render Settings tab.' );
    tmw_assert_contains( 'Last raw/imported/skipped', $html, 'Overview cards should render sync count summary.' );
    tmw_assert_true( false === strpos( $html, 'hidden-api-key' ), 'Overview should never leak API key.' );
};

$tests['stats_client_request_shape_get_stats'] = function() {
    tmw_reset_test_state();

    $captured = '';
    $GLOBALS['tmw_test_remote_get'] = static function ( $url ) use ( &$captured ) {
        $captured = $url;
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'data' => array() ) ) ),
        );
    };

    $client = new TMW_CR_Slot_CR_API_Client( 'apikey' );
    $client->get_offer_stats(
        array(
            'fields' => array( 'Stat.clicks', 'Stat.offer_id' ),
            'groups' => array( 'Stat.offer_id', 'Country.name' ),
            'filters' => array( 'Stat.offer_id' => '100' ),
            'sort' => array( 'Stat.payout' => 'desc' ),
            'data_start' => '2026-04-01',
            'data_end' => '2026-04-20',
            'limit' => 50,
            'page' => 2,
        )
    );

    tmw_assert_contains( 'Target=Affiliate_Report', $captured, 'Stats request should target Affiliate_Report.' );
    tmw_assert_contains( 'Method=getStats', $captured, 'Stats request should use getStats.' );
    tmw_assert_contains( 'fields[]=Stat.clicks', $captured, 'Stats request should include fields[] payload.' );
    tmw_assert_contains( 'groups[]=Stat.offer_id', $captured, 'Stats request should include groups[] payload.' );
};

$tests['stats_sync_storage_and_transport_failure_preserves_existing'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_offer_stats(
        array(
            'old' => array( 'offer_id' => '1', 'country_name' => 'US', 'clicks' => 2, 'conversions' => 1, 'payout' => 4, 'epc' => 2 ),
        )
    );

    $calls = 0;
    $GLOBALS['tmw_test_remote_get'] = static function () use ( &$calls ) {
        ++$calls;
        if ( 1 === $calls ) {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'response' => array(
                            'data' => array(
                                array(
                                    'Stat' => array( 'offer_id' => '10', 'clicks' => 100, 'conversions' => 4, 'payout' => 130, 'payout_type' => 'cpa' ),
                                    'Offer' => array( 'name' => 'Ten' ),
                                    'Country' => array( 'name' => 'US' ),
                                ),
                            ),
                        ),
                    )
                ),
            );
        }
        return new WP_Error( 'down', 'down' );
    };

    $client = new TMW_CR_Slot_CR_API_Client( 'apikey' );
    $result = TMW_CR_Slot_Stats_Sync_Service::sync( $client, $repository, array( 'preset' => '7d' ) );
    tmw_assert_true( ! is_wp_error( $result ), 'First stats sync should pass.' );
    $stored = $repository->get_offer_stats();
    tmw_assert_true( ! empty( $stored['10|US'] ), 'Aggregated stats should be keyed by offer+country.' );

    $error = TMW_CR_Slot_Stats_Sync_Service::sync( $client, $repository, array( 'preset' => '7d' ) );
    tmw_assert_true( is_wp_error( $error ), 'Second sync should fail transport.' );
    $stored_after = $repository->get_offer_stats();
    tmw_assert_same( $stored, $stored_after, 'Transport failure should preserve existing stats.' );
};

$tests['stats_extracts_grouped_rows_from_supported_envelopes'] = function() {
    tmw_reset_test_state();

    $rows_a = TMW_CR_Slot_Stats_Sync_Service::extract_stats_rows(
        array(
            'response' => array(
                'status' => 'ok',
                'data' => array(
                    array(
                        'Stat' => array( 'offer_id' => '100', 'clicks' => 12, 'conversions' => 2, 'payout' => 30, 'payout_type' => 'cpa' ),
                        'Offer' => array( 'name' => 'Offer 100' ),
                        'Country' => array( 'name' => 'US' ),
                    ),
                ),
            ),
        )
    );
    tmw_assert_same( 1, count( $rows_a ), 'response.data list should be extracted as stats rows.' );

    $rows_b = TMW_CR_Slot_Stats_Sync_Service::extract_stats_rows(
        array(
            'response' => array(
                'data' => array(
                    'data' => array(
                        array(
                            'Stat' => array( 'offer_id' => '101', 'clicks' => 8, 'conversions' => 1, 'payout' => 9, 'payout_type' => 'revshare' ),
                            'Offer' => array( 'name' => 'Offer 101' ),
                            'Country' => array( 'name' => 'CA' ),
                        ),
                    ),
                ),
            ),
        )
    );
    tmw_assert_same( 1, count( $rows_b ), 'response.data.data list should be extracted as stats rows.' );

    $rows_c = TMW_CR_Slot_Stats_Sync_Service::extract_stats_rows(
        array(
            'response' => array(
                'results' => array(
                    array(
                        'Stat' => array( 'offer_id' => '102', 'clicks' => 30, 'conversions' => 3, 'payout' => 40, 'payout_type' => 'hybrid' ),
                        'Offer' => array( 'name' => 'Offer 102' ),
                        'Country' => array( 'name' => 'GB' ),
                    ),
                ),
            ),
        )
    );
    tmw_assert_same( 1, count( $rows_c ), 'response.results list should be extracted as stats rows.' );
};

$tests['stats_does_not_treat_metadata_wrapper_as_row'] = function() {
    tmw_reset_test_state();
    $rows = TMW_CR_Slot_Stats_Sync_Service::extract_stats_rows(
        array(
            'response' => array(
                'status' => 'success',
                'httpStatus' => 200,
                'errors' => array(),
                'errorMessage' => '',
                'data' => array(
                    'status' => 'ok',
                    'httpStatus' => 200,
                ),
            ),
        )
    );
    tmw_assert_same( 0, count( $rows ), 'Metadata-only wrappers should never be treated as stats rows.' );
};

$tests['stats_sync_parser_soft_failure_preserves_previous_data'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_offer_stats(
        array(
            'old|GLOBAL' => array( 'offer_id' => 'old', 'offer_name' => 'Old', 'country_name' => 'GLOBAL', 'clicks' => 5, 'conversions' => 1, 'payout' => 7, 'epc' => 1.4 ),
        )
    );

    $GLOBALS['tmw_test_remote_get'] = static function () {
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode(
                array(
                    'response' => array(
                        'data' => array(
                            array(
                                'Stat' => array( 'clicks' => 30, 'conversions' => 2, 'payout' => 15 ),
                                'Country' => array( 'name' => 'US' ),
                            ),
                        ),
                    ),
                )
            ),
        );
    };

    $client = new TMW_CR_Slot_CR_API_Client( 'apikey' );
    $result = TMW_CR_Slot_Stats_Sync_Service::sync( $client, $repository, array( 'preset' => '7d' ) );
    tmw_assert_true( ! is_wp_error( $result ), 'Parser soft failure should return a non-error sync result.' );
    tmw_assert_same( 1, (int) ( $result['last_stats_raw_rows'] ?? 0 ), 'Raw rows should still report detected candidate rows.' );
    tmw_assert_same( 0, (int) ( $result['last_stats_imported_rows'] ?? 0 ), 'Rows without offer_id should remain skipped.' );
    tmw_assert_true( ! empty( $result['preserved_previous'] ), 'raw>0 and imported=0 should preserve previous stats.' );

    $stored = $repository->get_offer_stats();
    tmw_assert_true( isset( $stored['old|GLOBAL'] ), 'Existing stats should remain stored on parser soft failure.' );
    $meta = $repository->get_stats_meta();
    tmw_assert_same( 'parser_mismatch', (string) ( $meta['last_stats_soft_failure'] ?? '' ), 'Meta should store parser soft-failure reason.' );
    tmw_assert_true( ! empty( $meta['last_stats_preserved_previous'] ), 'Meta should mark preserved_previous for parser mismatch.' );
};

$tests['stats_sync_imports_real_grouped_rows_and_normalizes_country'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $GLOBALS['tmw_test_remote_get'] = static function () {
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode(
                array(
                    'response' => array(
                        'data' => array(
                            'data' => array(
                                array(
                                    'stat' => array( 'offer_id' => '500', 'clicks' => '20', 'conversions' => '2', 'payout' => '30.00', 'payout_type' => 'CPA' ),
                                    'offer' => array( 'name' => 'Offer 500' ),
                                    'country' => array( 'name' => '' ),
                                ),
                            ),
                        ),
                    ),
                )
            ),
        );
    };
    $client = new TMW_CR_Slot_CR_API_Client( 'apikey' );
    $result = TMW_CR_Slot_Stats_Sync_Service::sync( $client, $repository, array( 'preset' => '7d' ) );
    tmw_assert_true( ! is_wp_error( $result ), 'Grouped rows in nested envelope should sync without error.' );
    tmw_assert_same( 1, (int) ( $result['last_stats_imported_rows'] ?? 0 ), 'Grouped row should import once offer_id is present.' );
    tmw_assert_same( 'root.response.data.data.list', (string) ( $result['last_stats_response_shape'] ?? '' ), 'Shape diagnostics should track nested envelope path.' );

    $stored = $repository->get_offer_stats();
    tmw_assert_true( isset( $stored['500|GLOBAL'] ), 'Empty country should normalize to GLOBAL.' );
    tmw_assert_same( 20, (int) $stored['500|GLOBAL']['clicks'], 'Numeric clicks should be sanitized and stored.' );
};

$tests['performance_tab_shows_stats_parser_diagnostics'] = function() {
    tmw_reset_test_state();
    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure', 'rotation_mode' => 'safe_hybrid_score', 'optimization_enabled' => 1 ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_synced_offers( array( '901' => array( 'id' => '901', 'name' => 'Offer 901', 'status' => 'active', 'payout_type' => 'cpa' ) ) );
    $repository->save_offer_stats( array( '901|GLOBAL' => array( 'offer_id' => '901', 'offer_name' => 'Offer 901', 'country_name' => 'GLOBAL', 'clicks' => 10, 'conversions' => 1, 'payout' => 9, 'epc' => 0.9, 'payout_type' => 'cpa' ) ) );
    $repository->save_stats_meta( array( 'last_stats_response_shape' => 'root.response.data.list', 'last_stats_sample_row_keys' => 'Stat,Offer,Country', 'last_stats_soft_failure' => 'parser_mismatch', 'last_stats_preserved_previous' => 1 ) );
    $_GET = array( 'tab' => 'performance' );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );
    ob_start();
    $page->render_page();
    $html = ob_get_clean();
    tmw_assert_contains( 'Shape: root.response.data.list', $html, 'Performance tab should show stats response shape diagnostics.' );
    tmw_assert_contains( 'Parser soft-failure preserved previous stats', $html, 'Performance tab should show preserved-previous soft-failure badge.' );
};

$tests['runtime_ranking_modes_and_country_fallback'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_synced_offers(
        array(
            '1' => array( 'id' => '1', 'name' => 'One', 'status' => 'active' ),
            '2' => array( 'id' => '2', 'name' => 'Two', 'status' => 'active' ),
        )
    );
    $repository->save_offer_stats(
        array(
            '1|US' => array( 'offer_id' => '1', 'country_name' => 'US', 'clicks' => 10, 'conversions' => 1, 'payout' => 10, 'epc' => 1 ),
            '2|GLOBAL' => array( 'offer_id' => '2', 'country_name' => 'GLOBAL', 'clicks' => 100, 'conversions' => 20, 'payout' => 300, 'epc' => 3 ),
        )
    );
    $offers = array(
        array( 'id' => '1', 'name' => 'One' ),
        array( 'id' => '2', 'name' => 'Two' ),
    );
    $manual = $repository->rank_offers_for_slot( $offers, array( 'rotation_mode' => 'manual' ), 'US', array( '1' => 1, '2' => 2 ) );
    tmw_assert_same( '1', $manual[0]['id'], 'Manual mode should keep priority order.' );

    $optimized = $repository->rank_offers_for_slot( $offers, array( 'rotation_mode' => 'payout_desc' ), 'US', array( '1' => 1, '2' => 2 ) );
    tmw_assert_same( '2', $optimized[0]['id'], 'Payout mode should use country row when available and fallback to global if missing.' );
};

$tests['dashboard_performance_summary_fields'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_offer_stats(
        array(
            '1|US' => array( 'offer_id' => '1', 'offer_name' => 'One', 'country_name' => 'US', 'clicks' => 10, 'conversions' => 1, 'payout' => 20 ),
            '2|CA' => array( 'offer_id' => '2', 'offer_name' => 'Two', 'country_name' => 'CA', 'clicks' => 20, 'conversions' => 2, 'payout' => 25 ),
        )
    );
    $repository->save_stats_meta( array( 'last_stats_synced_at' => '2026-04-20T00:00:00+00:00', 'last_stats_date_start' => '2026-04-01', 'last_stats_date_end' => '2026-04-20' ) );
    $summary = $repository->get_dashboard_summary( array() );
    tmw_assert_same( 30, (int) $summary['total_clicks'], 'Summary should include stats clicks.' );
    tmw_assert_same( 'Two', (string) $summary['top_offer_name'], 'Summary should include top offer by payout.' );
};


$tests['offer_logo_mapping_known_pps_offers'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );

    $cases = array(
        'Jerkmate - PPS' => 'jerkmate-80x80-transparent.png',
        'Joi - PPS - Tier 1' => 'joi-80x80-transparent.png',
        'Joi - PPS - T1 (Premium)' => 'joi-80x80-transparent.png',
        'Instabang - PPS - Premium' => 'instabang-80x80-transparent.png',
        'Candy.ai - PPS - T1 (Premium)' => 'candyai-80x80-transparent.png',
        'Live Jasmin - PPS' => 'livejasmin-80x80-transparent.png',
        'NaughtyCharm - PPS' => 'naughtycharm-80x80-transparent.png',
        'NaughtyTalk - PPS' => 'naughtytalk-80x80-transparent.png',
        'CheekyCrush - PPS' => 'cheekycrush-80x80-transparent.png',
        'FlirtTendre - PPS' => 'flirttendre-80x80-transparent.png',
        'RencontreDouce - PPS' => 'rencontredouce-80x80-transparent.png',
        'WannaHookup - PPS' => 'wannahookup-80x80-transparent.png',
        'Dorcel Club - PPS' => 'dorcel-club-80x80-transparent.png',
        'Cams.com - PPS' => 'cams-com-80x80-transparent.png',
        'BeiAnrufSex - PPS' => 'beianrufsex-80x80-transparent.png',
        'BlackedRaw - PPS' => 'blacked-raw-80x80-transparent.png',
        'TushyRaw - PPS' => 'tushy-raw-80x80-transparent.png',
        'VixenPlus - PPS' => 'vixen-plus-80x80-transparent.png',
        'Gabrielle Moore Masterclasses - PPS' => 'gabrielle-moore-masterclasses-80x80-transparent.png',
        'Total Webcam - PPS' => 'total-webcams-80x80-transparent.png',
        'Total Webcams - PPS' => 'total-webcams-80x80-transparent.png',
        'Hentai Heroes - PPS' => 'hentaiheroes-80x80-transparent.png',
        'Cougar Life - PPS' => 'cougar-life-80x80-transparent.png',
        'Flirtbate - PPS' => 'flirtbate-80x80-transparent.png',
        'SeasonedFlirt - PPS' => 'seasonedflirt-80x80-transparent.png',
        'Blacked - PPS' => 'blacked-80x80-transparent.png',
        'Tushy - PPS' => 'tushy-80x80-transparent.png',
        'Vixen - PPS' => 'vixen-80x80-transparent.png',
        'Phalogenics - PPS' => 'phalogenics-80x80-transparent.png',
        'Oranum - PPS' => 'oranum-80x80-transparent.png',
        'Delhi Sex Chat - PPS' => 'delhi-sex-chat-80x80-transparent.png',
        'Squirting School - PPS' => 'squirting-school-80x80-transparent.png',
        'Growth Matrix - PPS' => 'growth-matrix-80x80-transparent.png',
        'Endura Naturals - PPS' => 'endura-naturals-80x80-transparent.png',
        'FILF - PPS' => 'filf-80x80-transparent.png',
        'Faphouse.com - PPS' => 'faphouse-80x80-transparent.png',
        'OléCams - PPS' => 'ole-cams-80x80-transparent.png',
        'Camirada - PPS' => 'camirada-80x80-transparent.png',
        'Nananue Cam - PPS' => 'nananue-cam-80x80-transparent.png',
        'Nananue Live - PPS' => 'nananue-cam-80x80-transparent.png',
        'ourdream.ai - PPS' => 'ourdream-ai-80x80-transparent.png',
        'ourdream.ai - PPS (Premium)' => 'ourdream-ai-80x80-transparent.png',
        'Sex Messenger - PPS - US' => 'sex-messenger-80x80-transparent.png',
        'Secrets.ai - PPS' => 'secrets-ai-80x80-transparent.png',
        'SinParty - PPS' => 'sinparty-80x80-transparent.png',
        'Xtease - PPS' => 'xtease-80x80-transparent.png',
        'Xotic AI - PPS' => 'xotic-ai-80x80-transparent.png',
        'Fanfinity - PPS' => 'fanfinity-80x80-transparent.png',
        'Get-Harder - PPS' => 'get-harder-80x80-transparent.png',
        'Testosterone Support Innerbody Labs - PPS - US' => 'testosterone-support-innerbody-80x80-transparent.png',
        'Primal Blast - PPS - US' => 'primal-blast-80x80-transparent.png',
        'Deeper - PPS' => 'deeper-80x80-transparent.png',
        'Milfy - PPS' => 'milfy-80x80-transparent.png',
        'Wifey - PPS' => 'wifey-80x80-transparent.png',
        'Slayed - PPS' => 'slayed-80x80-transparent.png',
    );

    foreach ( $cases as $offer_name => $expected ) {
        $offer = array( 'id' => 'case-' . md5( $offer_name ), 'name' => $offer_name );
        tmw_assert_same( $expected, $repository->get_offer_logo_filename( $offer ), 'Expected known logo filename to resolve: ' . $offer_name );
        tmw_assert_contains( 'assets/logos/80x80/' . $expected, $repository->get_offer_logo_url( $offer ), 'Expected known logo url to resolve: ' . $offer_name );
    }
};

$tests['offer_logo_mapping_unknown_and_missing_files_safe'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );

    $unknown = array( 'id' => 'u1', 'name' => 'Unknown Brand - PPS' );
    tmw_assert_same( '', $repository->get_offer_logo_filename( $unknown ), 'Unknown brands should return empty filename.' );
    tmw_assert_same( '', $repository->get_offer_logo_url( $unknown ), 'Unknown brands should return empty logo URL.' );

    $known = array( 'id' => 'm1', 'name' => 'Sex Messenger - PPS - US' );
    tmw_assert_same( 'sex-messenger-80x80-transparent.png', $repository->get_offer_logo_filename( $known ), 'Known mapped files should resolve safely.' );
    tmw_assert_contains( 'assets/logos/80x80/sex-messenger-80x80-transparent.png', $repository->get_offer_logo_url( $known ), 'Known mapped logo url should resolve safely.' );
};

$tests['frontend_slot_offer_includes_logo_fields_for_mapped_brand'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_synced_offers(
        array(
            '1001' => array( 'id' => '1001', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
        )
    );

    $offers = $repository->get_frontend_slot_offers(
        'sidebar',
        array( 'slot_offer_ids' => array( '1001' ), 'slot_offer_priority' => array( '1001' => 1 ) ),
        array( 'cta_url' => 'https://example.test/click', 'cta_text' => 'TRY YOUR FREE SPINS' ),
        'US',
        array()
    );

    tmw_assert_true( ! empty( $offers ), 'Expected at least one frontend slot offer.' );
    tmw_assert_same( 'jerkmate', (string) ( $offers[0]['brand_key'] ?? '' ), 'Mapped offer should include brand_key.' );
    tmw_assert_same( 'jerkmate-80x80-transparent.png', (string) ( $offers[0]['logo_filename'] ?? '' ), 'Mapped offer should include logo filename.' );
    tmw_assert_contains( 'assets/logos/80x80/jerkmate-80x80-transparent.png', (string) ( $offers[0]['logo_url'] ?? '' ), 'Mapped offer should include logo URL.' );
};


$tests['frontend_slot_offer_includes_logo_fields_for_newly_mapped_pps_brand'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_synced_offers(
        array(
            '1003' => array( 'id' => '1003', 'name' => 'Cougar Life - PPS', 'status' => 'active' ),
        )
    );

    $offers = $repository->get_frontend_slot_offers(
        'sidebar',
        array( 'slot_offer_ids' => array( '1003' ), 'slot_offer_priority' => array( '1003' => 1 ) ),
        array( 'cta_url' => 'https://example.test/click', 'cta_text' => 'TRY YOUR FREE SPINS' ),
        'US',
        array()
    );

    tmw_assert_true( ! empty( $offers ), 'Expected at least one frontend slot offer for newly mapped brand.' );
    tmw_assert_same( 'cougar-life', (string) ( $offers[0]['brand_key'] ?? '' ), 'Newly mapped offer should include brand_key.' );
    tmw_assert_same( 'cougar-life-80x80-transparent.png', (string) ( $offers[0]['logo_filename'] ?? '' ), 'Newly mapped offer should include logo filename.' );
    tmw_assert_contains( 'assets/logos/80x80/cougar-life-80x80-transparent.png', (string) ( $offers[0]['logo_url'] ?? '' ), 'Newly mapped offer should include logo URL.' );
};

$tests['frontend_slot_offer_includes_empty_logo_url_when_unmapped'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repository->save_synced_offers(
        array(
            '1002' => array( 'id' => '1002', 'name' => 'Unknown Brand - PPS', 'status' => 'active' ),
        )
    );

    $offers = $repository->get_frontend_slot_offers(
        'sidebar',
        array( 'slot_offer_ids' => array( '1002' ), 'slot_offer_priority' => array( '1002' => 1 ) ),
        array( 'cta_url' => 'https://example.test/click', 'cta_text' => 'TRY YOUR FREE SPINS' ),
        'US',
        array()
    );

    tmw_assert_true( ! empty( $offers ), 'Expected at least one frontend slot offer.' );
    tmw_assert_same( '', (string) ( $offers[0]['logo_url'] ?? '' ), 'Unmapped offer should include empty logo_url for text fallback.' );
    tmw_assert_same( '', (string) ( $offers[0]['logo_filename'] ?? '' ), 'Unmapped offer should include empty logo_filename for text fallback.' );
};


$tests['offer_logo_mapping_manifest_consistency'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $reflection = new ReflectionClass( $repository );
    $method = $reflection->getMethod( 'get_offer_logo_filename_map' );
    $method->setAccessible( true );
    $map = (array) $method->invoke( $repository );

    $manifest_rows = array_map( 'str_getcsv', file( __DIR__ . '/../assets/logos/80x80/manifest.csv' ) );
    $manifest_files = array();
    foreach ( $manifest_rows as $index => $row ) {
        if ( 0 === $index || ! isset( $row[1] ) ) {
            continue;
        }
        $manifest_files[ trim( (string) $row[1] ) ] = true;
    }

    foreach ( $map as $filename ) {
        tmw_assert_true( isset( $manifest_files[ $filename ] ), 'Mapped filename must exist in manifest.csv: ' . $filename );
        tmw_assert_true( file_exists( __DIR__ . '/../assets/logos/80x80/' . $filename ), 'Mapped filename must exist on disk: ' . $filename );
    }

    $required = array(
        'nananue-cam-80x80-transparent.png',
        'ourdream-ai-80x80-transparent.png',
        'sex-messenger-80x80-transparent.png',
        'secrets-ai-80x80-transparent.png',
        'sinparty-80x80-transparent.png',
        'xtease-80x80-transparent.png',
        'xotic-ai-80x80-transparent.png',
    );

    foreach ( $required as $filename ) {
        tmw_assert_true( isset( $manifest_files[ $filename ] ), 'Required filename must exist in manifest.csv: ' . $filename );
        tmw_assert_true( file_exists( __DIR__ . '/../assets/logos/80x80/' . $filename ), 'Required filename must exist on disk: ' . $filename );
    }
};

$tests['offer_type_allowlist_pps_only_rejects_fallback_only'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_assert_true( ! $repository->is_offer_type_allowed( array( 'name' => 'Group Fallback - Cam - Not Restricted 01' ), array( 'allowed_offer_types' => array( 'pps' ) ) ), 'PPS-only should reject fallback-only offers.' );
};

$tests['offer_type_allowlist_fallback_only_accepts_fallback_only'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_assert_true( $repository->is_offer_type_allowed( array( 'name' => 'Group Fallback - Cam - Not Restricted 01' ), array( 'allowed_offer_types' => array( 'fallback' ) ) ), 'Fallback-only should accept fallback-only offers.' );
};

$tests['frontend_pps_only_filters_disallowed_types_and_keeps_pps'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            'fallback' => array( 'id' => 'fallback', 'name' => 'Group Fallback - Cam - Not Restricted 01', 'status' => 'active' ),
            'rev-only' => array( 'id' => 'rev-only', 'name' => 'Revenue Driver - Revshare', 'status' => 'active' ),
            'soi-only' => array( 'id' => 'soi-only', 'name' => 'Lead Maker - SOI', 'status' => 'active' ),
            'doi-only' => array( 'id' => 'doi-only', 'name' => 'Lead Maker - DOI', 'status' => 'active' ),
            'pps-1' => array( 'id' => 'pps-1', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
            'pps-mix' => array( 'id' => 'pps-mix', 'name' => 'Bongacams - PPS + Revshare lifetime', 'status' => 'active' ),
            'joi' => array( 'id' => 'joi', 'name' => 'Joi - PPS - Tier 1', 'status' => 'active' ),
            'jasmin' => array( 'id' => 'jasmin', 'name' => 'Live Jasmin - PPS', 'status' => 'active' ),
        )
    );

    $offers = $repository->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', array() );
    $names = array_map( static function( $row ) { return (string) ( $row['name'] ?? '' ); }, $offers );
    tmw_assert_true( in_array( 'Jerkmate - PPS', $names, true ), 'PPS-only should include Jerkmate - PPS.' );
    tmw_assert_true( in_array( 'Joi - PPS - Tier 1', $names, true ), 'PPS-only should include Joi - PPS - Tier 1.' );
    tmw_assert_true( in_array( 'Live Jasmin - PPS', $names, true ), 'PPS-only should include Live Jasmin - PPS.' );
    tmw_assert_true( in_array( 'Bongacams - PPS + Revshare lifetime', $names, true ), 'PPS-only should include mixed PPS+Revshare if PPS detected.' );
    tmw_assert_true( ! in_array( 'Group Fallback - Cam - Not Restricted 01', $names, true ), 'PPS-only should reject fallback-only offers.' );
    tmw_assert_true( ! in_array( 'Revenue Driver - Revshare', $names, true ), 'PPS-only should reject Revshare-only offers.' );
    tmw_assert_true( ! in_array( 'Lead Maker - SOI', $names, true ), 'PPS-only should reject SOI-only offers.' );
    tmw_assert_true( ! in_array( 'Lead Maker - DOI', $names, true ), 'PPS-only should reject DOI-only offers.' );
};

$tests['frontend_zero_allowed_offers_returns_safe_empty_pool'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            'fallback' => array( 'id' => 'fallback', 'name' => 'Group Fallback - Cam - Not Restricted 01', 'status' => 'active' ),
            'rev-only' => array( 'id' => 'rev-only', 'name' => 'Revenue Driver - Revshare', 'status' => 'active' ),
        )
    );
    $offers = $repository->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_same( 0, count( $offers ), 'When no allowed offers exist, frontend pool should be empty without fatal behavior.' );
};

$tests['pps_logo_coverage_report_lists_missing_logo_offers'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            'has-logo' => array( 'id' => 'has-logo', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
            'missing-logo' => array( 'id' => 'missing-logo', 'name' => 'Unknown Brand - PPS', 'status' => 'active' ),
        )
    );
    $report = $repository->get_pps_logo_coverage_report( array( 'allowed_offer_types' => array( 'pps' ) ) );
    tmw_assert_same( 2, (int) $report['pps_candidates_total'], 'Coverage report should include all PPS candidates in allowed pool.' );
    tmw_assert_same( 1, (int) $report['pps_with_logo'], 'Coverage report should count PPS candidates with mapped logos.' );
    tmw_assert_same( 1, (int) $report['pps_missing_logo'], 'Coverage report should count PPS candidates with missing logos.' );
    tmw_assert_true( in_array( 'missing-logo', (array) $report['missing_logo_offer_ids'], true ), 'Coverage report should list missing-logo offer id.' );
};

$tests['offer_blocklist_expected_cases'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_assert_true( $repository->is_offer_blocked_for_banner( array( 'name' => 'XLoveGay - PPS' ) ), 'XLoveGay should be blocked.' );
    tmw_assert_true( $repository->is_offer_blocked_for_banner( array( 'name' => 'XLove Gay - PPS' ) ), 'XLove Gay should be blocked.' );
    tmw_assert_true( $repository->is_offer_blocked_for_banner( array( 'name' => 'Mennation - PPS' ) ), 'Mennation should be blocked.' );
    tmw_assert_true( $repository->is_offer_blocked_for_banner( array( 'name' => 'GayBloom - PPS - US' ) ), 'GayBloom should be blocked.' );
    tmw_assert_true( $repository->is_offer_blocked_for_banner( array( 'name' => 'PridePair - PPS - US' ) ), 'PridePair should be blocked.' );
    tmw_assert_true( $repository->is_offer_blocked_for_banner( array( 'name' => 'TransDate - SOI' ) ), 'TransDate should be blocked.' );
    tmw_assert_true( $repository->is_offer_blocked_for_banner( array( 'name' => 'Group Fallback - Gay Cam' ) ), 'Gay Cam phrase should be blocked.' );
    tmw_assert_true( ! $repository->is_offer_blocked_for_banner( array( 'name' => 'Jerkmate - PPS' ) ), 'Jerkmate should not be blocked.' );
    tmw_assert_true( ! $repository->is_offer_blocked_for_banner( array( 'name' => 'Adult FriendFinder - PPS' ) ), 'Adult FriendFinder should not be blocked.' );
    tmw_assert_true( ! $repository->is_offer_blocked_for_banner( array( 'name' => 'Live Jasmin - PPS' ) ), 'Live Jasmin should not be blocked.' );
    tmw_assert_true( ! $repository->is_offer_blocked_for_banner( array( 'name' => 'transparent logo test offer' ) ), 'transparent should not be blocked by trans token-safe matching.' );
    tmw_assert_true( ! $repository->is_offer_blocked_for_banner( array( 'name' => 'transaction test offer' ) ), 'transaction should not be blocked by trans token-safe matching.' );
    tmw_assert_true( ! $repository->is_offer_blocked_for_banner( array( 'name' => 'transfer test offer' ) ), 'transfer should not be blocked by trans token-safe matching.' );
};

$tests['frontend_pps_pool_excludes_blocked_and_hot_pick_is_not_blocked'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '2492' => array( 'id' => '2492', 'name' => 'XLoveGay - PPS', 'status' => 'active' ),
            '5875' => array( 'id' => '5875', 'name' => 'Mennation - PPS', 'status' => 'active' ),
            '10372' => array( 'id' => '10372', 'name' => 'GayBloom - PPS - US', 'status' => 'active' ),
            '10373' => array( 'id' => '10373', 'name' => 'PridePair - PPS - US', 'status' => 'active' ),
            'safe1' => array( 'id' => 'safe1', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
        )
    );
    $offers = $repository->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', array() );
    $names = array_map( static function( $row ) { return (string) ( $row['name'] ?? '' ); }, $offers );
    tmw_assert_true( in_array( 'Jerkmate - PPS', $names, true ), 'Safe PPS offers should remain.' );
    tmw_assert_true( ! in_array( 'XLoveGay - PPS', $names, true ), 'Blocked XLoveGay should be excluded.' );
    tmw_assert_true( ! in_array( 'Mennation - PPS', $names, true ), 'Blocked Mennation should be excluded.' );
    tmw_assert_true( ! in_array( 'GayBloom - PPS - US', $names, true ), 'Blocked GayBloom should be excluded.' );
    tmw_assert_true( ! in_array( 'PridePair - PPS - US', $names, true ), 'Blocked PridePair should be excluded.' );
    tmw_assert_true( ! empty( $offers ) && ! $repository->is_offer_blocked_for_banner( $offers[0] ), 'Hot pick (top offer) should never be blocked.' );
};

$tests['pps_logo_coverage_excludes_blocked_offers_from_missing'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            'safe' => array( 'id' => 'safe', 'name' => 'Unknown Brand - PPS', 'status' => 'active' ),
            '2492' => array( 'id' => '2492', 'name' => 'XLoveGay - PPS', 'status' => 'active' ),
        )
    );
    $report = $repository->get_pps_logo_coverage_report( array( 'allowed_offer_types' => array( 'pps' ) ) );
    tmw_assert_same( 1, (int) $report['pps_candidates_total'], 'Blocked PPS offers should be excluded from total coverage candidates.' );
    tmw_assert_same( 1, (int) $report['pps_missing_logo'], 'Only non-blocked missing logos should count.' );
    tmw_assert_same( 1, (int) $report['blocked_pps_offers_excluded'], 'Blocked PPS excluded count should be reported.' );
};

$tests['pps_logo_coverage_excludes_unavailable_account_offers'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $offers = array();
    for ( $i = 1; $i <= 87; $i++ ) {
        $offers[ 'mapped-' . $i ] = array(
            'id' => 'mapped-' . $i,
            'name' => 'Jerkmate - PPS',
            'status' => 'active',
        );
    }
    $offers['9647'] = array( 'id' => '9647', 'name' => 'Group Fallback - Tapyn - PPS - Mobile - Android', 'status' => 'active' );
    $offers['9781'] = array( 'id' => '9781', 'name' => 'Group Fallback - Dating.com PPS', 'status' => 'active' );
    $offers['2492'] = array( 'id' => '2492', 'name' => 'XLoveGay - PPS', 'status' => 'active' );
    $offers['5875'] = array( 'id' => '5875', 'name' => 'Mennation - PPS', 'status' => 'active' );
    $offers['10372'] = array( 'id' => '10372', 'name' => 'GayBloom - PPS - US', 'status' => 'active' );
    $offers['10373'] = array( 'id' => '10373', 'name' => 'PridePair - PPS - US', 'status' => 'active' );
    $offers['transdate'] = array( 'id' => 'transdate', 'name' => 'TransDate - PPS', 'status' => 'active' );
    $repository->save_synced_offers( $offers );

    $report = $repository->get_pps_logo_coverage_report( array( 'allowed_offer_types' => array( 'pps' ) ) );
    tmw_assert_same( 87, (int) $report['pps_candidates_total'], 'Unavailable account offers must be excluded from PPS denominator.' );
    tmw_assert_same( 87, (int) $report['pps_with_logo'], 'Mapped PPS offers should remain 87.' );
    tmw_assert_same( 0, (int) $report['pps_missing_logo'], 'Unavailable account offers must not appear as missing logos.' );
    tmw_assert_same( 5, (int) $report['blocked_pps_offers_excluded'], 'Blocked PPS offers excluded count should remain 5.' );
    tmw_assert_same( 2, (int) $report['unavailable_account_pps_offers_excluded'], 'Unavailable account PPS excluded count should be reported.' );
    tmw_assert_true( in_array( '9647', (array) $report['unavailable_account_offer_ids'], true ), 'Tapyn offer id should be listed as unavailable.' );
    tmw_assert_true( in_array( '9781', (array) $report['unavailable_account_offer_ids'], true ), 'Dating.com offer id should be listed as unavailable.' );
    tmw_assert_true( ! in_array( '9647', (array) $report['missing_logo_offer_ids'], true ), 'Tapyn must not be treated as missing logo.' );
    tmw_assert_true( ! in_array( '9781', (array) $report['missing_logo_offer_ids'], true ), 'Dating.com must not be treated as missing logo.' );
};

$tests['frontend_pps_pool_excludes_unavailable_account_offers'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            '9647' => array( 'id' => '9647', 'name' => 'Group Fallback - Tapyn - PPS - Mobile - Android', 'status' => 'active' ),
            '9781' => array( 'id' => '9781', 'name' => 'Group Fallback - Dating.com PPS', 'status' => 'active' ),
            'safe1' => array( 'id' => 'safe1', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
        )
    );

    $offers = $repository->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', array() );
    $ids = array_map( static function( $row ) { return (string) ( $row['id'] ?? '' ); }, $offers );
    tmw_assert_true( in_array( 'safe1', $ids, true ), 'Safe PPS offers should remain eligible.' );
    tmw_assert_true( ! in_array( '9647', $ids, true ), 'Tapyn should be excluded from frontend pool.' );
    tmw_assert_true( ! in_array( '9781', $ids, true ), 'Dating.com should be excluded from frontend pool.' );
};

$tests['sanitize_settings_preserves_selected_disallowed_offer_ids'] = function() {
    tmw_reset_test_state();
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $sanitized = $page->sanitize_settings(
        array(
            'allowed_offer_types' => array( 'pps' ),
            'slot_offer_ids' => array( 'fallback-1', 'pps-1' ),
        )
    );
    tmw_assert_same( array( 'fallback-1', 'pps-1' ), $sanitized['slot_offer_ids'], 'Selected offers should not be removed from saved settings even when currently disallowed by type filters.' );
};

$tests['cr_url_field_audit_summary_classifies_pps_urls'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers(
        array(
            't1' => array( 'id' => 't1', 'name' => 'Offer A - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/?affiliate_id=123&transaction_id=abc' ),
            't2' => array( 'id' => 't2', 'name' => 'Offer B - PPS', 'status' => 'active', 'preview_url' => 'https://preview.example.com/template' ),
            't3' => array( 'id' => 't3', 'name' => 'Offer C - PPS', 'status' => 'active', 'preview_url' => 'https://brand.example.com/landing' ),
            't4' => array( 'id' => 't4', 'name' => 'Offer D - PPS', 'status' => 'active', 'preview_url' => 'https://trk.example.com/?affiliate_id=affiliate_id&transaction_id=preview' ),
            't5' => array( 'id' => 't5', 'name' => 'Offer E - PPS', 'status' => 'active', 'preview_url' => '' ),
            't6' => array( 'id' => 't6', 'name' => 'Offer F - PPS', 'status' => 'active', 'tracking_url' => 'https://gateway.crakrevenue.com/click/abcdef' ),
        )
    );
    $summary = $repository->get_cr_url_field_audit_summary( array( 'cta_url' => '' ) );
    tmw_assert_same( 6, (int) $summary['synced_pps_offers_checked'], 'All PPS rows should be included in URL audit summary.' );
    tmw_assert_same( 2, (int) $summary['offers_with_tracking_url'], 'Tracking URLs should be counted, including known CR tracking hosts.' );
    tmw_assert_same( 0, (int) $summary['offers_with_preview_template_url_only'], 'Preview/template URLs should not be used as effective CTA URLs.' );
    tmw_assert_same( 0, (int) $summary['offers_with_raw_advertiser_url_only'], 'Raw advertiser URLs should not be used as effective CTA URLs.' );
    tmw_assert_same( 0, (int) $summary['offers_with_unresolved_placeholders'], 'Placeholder URLs should not be used as effective CTA URLs.' );
    tmw_assert_same( 4, (int) $summary['offers_with_empty_url'], 'Rows without usable tracking/global URL should be counted as empty effective URLs.' );
};

$tests['invalid_template_tracking_url_falls_back_to_global_cta_or_empty'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );

    $global = array( 'cta_url' => 'https://base.test/click', 'cta_text' => 'CTA' );
    $invalid_urls = array(
        'https://trk.example.com/path?affiliate_id=123&transaction_id=preview',
        'https://trk.example.com/path?affiliate_id=affiliate_id&transaction_id=abc',
        'https://trk.example.com/path?aid=affiliate_id&transaction_id=abc',
        'https://trk.example.com/path?src=source&transaction_id=abc',
        'https://gateway.crakrevenue.com/click?transaction_id=preview',
        'https://gateway.crakrevenue.com/click?affiliate_id=affiliate_id',
        'https://gateway.crakrevenue.com/click?aid=affiliate_id',
        'https://gateway.crakrevenue.com/click?src=source',
    );

    foreach ( $invalid_urls as $idx => $invalid_url ) {
        $offer_id = (string) ( 9500 + $idx );
        $with_global = $repository->get_effective_cta_url( $offer_id, array(), $global, array( 'id' => $offer_id, 'tracking_url' => $invalid_url ), array() );
        tmw_assert_contains( 'https://base.test/click', $with_global, 'Invalid template tracking URL should fallback to global CTA when available.' );

        $without_global = $repository->get_effective_cta_url( $offer_id, array(), array( 'cta_url' => '', 'cta_text' => 'CTA' ), array( 'id' => $offer_id, 'tracking_url' => $invalid_url, 'preview_url' => 'https://preview.test/should-not-use' ), array() );
        tmw_assert_same( '', $without_global, 'Invalid template tracking URL should return empty without valid global CTA and never fallback to preview_url.' );
    }
};

$tests['manual_final_url_override_importer_accepts_and_preserves'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '999' => array( 'custom_cta_text' => 'keep me' ) ) );
    $_POST['final_url_override_csv'] = "offer_id,final_url_override\n1234,https://trk.example.com/?tid=abc\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_final_url_overrides();
    $overrides = $repo->get_offer_overrides();
    tmw_assert_same( 'https://trk.example.com/?tid=abc', (string) $overrides['1234']['final_url_override'], 'Importer should save valid final_url_override.' );
    tmw_assert_same( 'keep me', (string) $overrides['999']['custom_cta_text'], 'Importer should preserve existing override rows.' );
};

$tests['manual_final_url_override_importer_rejects_invalid_patterns'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['final_url_override_csv'] = "offer_id,final_url_override\n1,https://preview.example.com/path\n2,https://ads.advertisingpolicies.com/path\n3,https://trk.example.com/?transaction_id=preview\n4,https://trk.example.com/?affiliate_id=affiliate_id\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_final_url_overrides();
    $overrides = $repo->get_offer_overrides();
    tmw_assert_true( empty( $overrides['1']['final_url_override'] ), 'Preview URL should be rejected.' );
    tmw_assert_true( empty( $overrides['2']['final_url_override'] ), 'advertisingpolicies URL should be rejected.' );
    tmw_assert_true( empty( $overrides['3']['final_url_override'] ), 'transaction_id=preview should be rejected.' );
    tmw_assert_true( empty( $overrides['4']['final_url_override'] ), 'affiliate_id=affiliate_id should be rejected.' );
};

$tests['manual_final_url_override_enables_frontend_pool_without_tracking'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '7001' => array( 'id' => '7001', 'name' => 'Offer 7001 - PPS', 'status' => 'active' ) ) );
    $repo->save_offer_overrides( array( '7001' => array( 'final_url_override' => 'https://trk.example.com/?tid=winner' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_same( 1, count( $offers ), 'Valid final_url_override should allow offer into frontend winner pool.' );
};

$tests['offer_without_tracking_or_manual_override_remains_excluded'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '7002' => array( 'id' => '7002', 'name' => 'Offer 7002 - PPS', 'status' => 'active' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_same( 0, count( $offers ), 'Offer without tracking_url and without final_url_override should remain excluded.' );
};


$failures = array();
$passes   = 0;

foreach ( $tests as $name => $test ) {
    try {
        $test();
        ++$passes;
        echo "[PASS] {$name}\n";
    } catch ( Throwable $throwable ) {
        $failures[] = array( 'name' => $name, 'message' => $throwable->getMessage() );
        echo "[FAIL] {$name}: {$throwable->getMessage()}\n";
    }
}

echo "\nTotal: {$passes} passed, " . count( $failures ) . " failed\n";

if ( ! empty( $failures ) ) {
    exit( 1 );
}
