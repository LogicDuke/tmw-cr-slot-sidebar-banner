<?php
require_once __DIR__ . '/bootstrap.php';

class TMW_CR_Slot_Sidebar_Banner {
    const OPTION_KEY = 'tmw_cr_slot_banner_settings';
    const STATS_SYNC_CRON_HOOK = 'tmw_cr_slot_banner_scheduled_stats_sync';

    public static function get_settings() {
        $defaults = array(
            'headline'               => 'Headline',
            'subheadline'            => 'Subheadline',
            'cta_text'               => 'CTA',
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
    tmw_assert_contains( 'offer_id=101', $fallback_url, 'Base CTA fallback should append offer query args.' );

    $preview_only_url = $repository->get_effective_cta_url( '101', $settings, array( 'cta_url' => '' ), array( 'id' => '101', 'name' => 'Offer 101', 'preview_url' => 'https://preview.test/101' ), array() );
    tmw_assert_same( 'https://preview.test/101', $preview_only_url, 'preview_url should remain the last fallback.' );

    tmw_assert_true( ! $repository->is_offer_allowed_for_country( '101', 'US', $repository->get_offer_override( '101' ), array(), array() ), 'Disabled offer should be excluded.' );
    tmw_assert_true( $repository->is_offer_allowed_for_country( '100', 'US', $repository->get_offer_override( '100' ), array(), array() ), 'Allowed countries should permit matching country.' );
    tmw_assert_true( ! $repository->is_offer_allowed_for_country( '102', 'US', $repository->get_offer_override( '102' ), array(), array() ), 'Blocked countries should exclude matching country.' );
};

$tests['frontend_pool_filters_and_legacy_fallback_to_three'] = function() {
    tmw_reset_test_state();

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_synced_offers(
        array(
            '200' => array( 'id' => '200', 'name' => 'Offer 200', 'status' => 'active', 'preview_url' => 'https://preview.test/200' ),
            '201' => array( 'id' => '201', 'name' => 'Offer 201', 'status' => 'active', 'preview_url' => 'https://preview.test/201' ),
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
            '601' => array( 'id' => '601', 'name' => 'SexMessenger', 'status' => 'active', 'preview_url' => 'https://preview.test/601' ),
            '602' => array( 'id' => '602', 'name' => 'Paused Offer', 'status' => 'paused', 'preview_url' => 'https://preview.test/602' ),
        )
    );

    $settings = array(
        'slot_offer_ids' => array( '601', '602' ),
        'slot_offer_priority' => array( '601' => 1, '602' => 2 ),
    );

    $offers = $repository->get_frontend_slot_offers( 'sidebar', $settings, array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', TMW_CR_Slot_Sidebar_Banner::get_offer_catalog_defaults() );
    tmw_assert_same( '601', $offers[0]['id'], 'Active synced offers should still normalize for frontend slot pool.' );
    tmw_assert_contains( 'assets/img/offers/Sex Messenger.png', $offers[0]['image'], 'Resolver should pick local catalog image via alias match.' );
};

$tests['admin_sanitize_and_render_supports_offer_overrides'] = function() {
    tmw_reset_test_state();

    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure-key' ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_synced_offers(
        array(
            '301' => array( 'id' => '301', 'name' => 'Offer 301', 'status' => 'active', 'preview_url' => 'https://preview.test/301' ),
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
