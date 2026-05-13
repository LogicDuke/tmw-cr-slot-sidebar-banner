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
        $this->redirect_with_notice_to_tab( $notice_type, $message, 'overview' );
    }

    protected function redirect_with_notice_to_tab( $notice_type, $message, $tab_slug = 'overview' ) {
        if ( ! empty( $this->notice ) ) {
            return;
        }

        $this->notice = array(
            'type'    => $notice_type,
            'message' => $message,
            'tab'     => $tab_slug,
        );
    }

    protected function assert_admin_action( $nonce_action, $custom_nonce_field = '' ) {
        unset( $nonce_action );

        if ( '' === $custom_nonce_field ) {
            return;
        }

        if ( isset( $_POST['_wpnonce'] ) ) {
            return;
        }

        if ( isset( $_POST[ $custom_nonce_field ] ) && '' !== (string) $_POST[ $custom_nonce_field ] ) {
            return;
        }

        throw new RuntimeException( 'Missing nonce payload for admin action test.' );
    }
}

function tmw_reset_test_state() {
    $GLOBALS['tmw_test_options']      = array();
    $GLOBALS['tmw_test_transients']   = array();
    $GLOBALS['tmw_test_remote_get']   = null;
    $GLOBALS['tmw_test_last_redirect'] = '';
    $GLOBALS['tmw_test_cron_events'] = array();
    $GLOBALS['tmw_test_current_user_can'] = true;
    $GLOBALS['tmw_test_added_options_pages'] = array();
    $_GET  = array();
    $_POST = array();
}

/**
 * Temporarily writes a logo fixture file, runs a callback, then restores original file state.
 *
 * @param string   $relative_filename Relative filename under assets/logos.
 * @param string   $temporary_contents Temporary file contents.
 * @param callable $callback Callback executed while temp file state is active.
 *
 * @return mixed
 */
function tmw_with_temp_logo_file( $relative_filename, $temporary_contents, $callback ) {
    $logo_path = TMW_CR_SLOT_BANNER_PATH . 'assets/logos/' . ltrim( (string) $relative_filename, '/\\' );
    $logo_dir  = dirname( $logo_path );
    if ( ! is_dir( $logo_dir ) ) {
        mkdir( $logo_dir, 0777, true );
    }
    $existed_before  = file_exists( $logo_path );
    $original_binary = $existed_before ? file_get_contents( $logo_path ) : null;
    file_put_contents( $logo_path, (string) $temporary_contents );

    try {
        return $callback( $logo_path );
    } finally {
        if ( $existed_before ) {
            file_put_contents( $logo_path, (string) $original_binary );
        } elseif ( file_exists( $logo_path ) ) {
            unlink( $logo_path );
        }
    }
}

/**
 * Temporarily removes a logo fixture file, runs a callback, then restores original file state.
 *
 * @param string   $relative_filename Relative filename under assets/logos.
 * @param callable $callback Callback executed while file is removed.
 *
 * @return mixed
 */
function tmw_without_logo_file( $relative_filename, $callback ) {
    $logo_path = TMW_CR_SLOT_BANNER_PATH . 'assets/logos/' . ltrim( (string) $relative_filename, '/\\' );
    $existed_before  = file_exists( $logo_path );
    $original_binary = $existed_before ? file_get_contents( $logo_path ) : null;
    if ( $existed_before ) {
        unlink( $logo_path );
    }
    try {
        return $callback( $logo_path );
    } finally {
        if ( $existed_before ) {
            file_put_contents( $logo_path, (string) $original_binary );
        } elseif ( file_exists( $logo_path ) ) {
            unlink( $logo_path );
        }
    }
}

$tests = array();


$tests['admin_menu_registers_tmw_slot_banner_page'] = function() {
    tmw_reset_test_state();
    $GLOBALS['tmw_test_added_options_pages'] = array();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $page->register_menu();

    tmw_assert_same( 1, count( $GLOBALS['tmw_test_added_options_pages'] ), 'Admin menu should register exactly one options page entry.' );
    $entry = $GLOBALS['tmw_test_added_options_pages'][0];
    tmw_assert_same( 'TMW Slot Banner', (string) $entry['menu_title'], 'Admin menu title should be visible as TMW Slot Banner.' );
    tmw_assert_same( 'manage_options', (string) $entry['capability'], 'Admin page capability should require manage_options.' );
    tmw_assert_same( 'tmw-cr-slot-sidebar-banner', (string) $entry['menu_slug'], 'Admin menu slug should remain stable.' );
};

$tests['slot_setup_page_accessible_for_manage_options_user'] = function() {
    tmw_reset_test_state();
    $GLOBALS['tmw_test_current_user_can'] = true;
    $_GET = array( 'tab' => 'slot-setup' );

    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );

    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    tmw_assert_contains( 'TMW CrakRevenue Slot Operations Dashboard', $html, 'Manage options users should be able to load the admin dashboard page.' );
    tmw_assert_contains( 'Slot Setup', $html, 'Slot Setup tab should render for manage_options users.' );
};

$tests['slot_setup_url_uses_correct_menu_slug'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );

    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    tmw_assert_contains( 'options-general.php?page=tmw-cr-slot-sidebar-banner&tab=slot-setup', $html, 'Slot Setup tab links should use the stable options-general.php menu slug URL.' );
};

$tests['slot_setup_renders_only_combined_import_section'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );

    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    tmw_assert_contains( '<h3>Import Both Override CSVs</h3>', $html, 'Slot Setup should render combined override import heading.' );
    tmw_assert_true( false === strpos( $html, '<h3>Import Allowed Country Overrides</h3>' ), 'Slot Setup should not render standalone allowed country heading.' );
    tmw_assert_true( false === strpos( $html, '<h3>Import Final URL Overrides</h3>' ), 'Slot Setup should not render standalone final URL heading.' );
    tmw_assert_contains( 'name="allowed_country_override_csv"', $html, 'Slot Setup should keep allowed country textarea in combined form.' );
    tmw_assert_contains( 'name="final_url_override_csv"', $html, 'Slot Setup should keep final URL textarea in combined form.' );
    tmw_assert_contains( 'action" value="tmw_cr_slot_banner_import_both_overrides"', $html, 'Slot Setup should keep combined import form action.' );
    tmw_assert_contains( 'data-legacy-allowed-country-action="tmw_cr_slot_banner_import_allowed_country_overrides"', $html, 'Slot Setup should include hidden compatibility nonce source for legacy allowed-country importer.' );
    tmw_assert_contains( 'data-legacy-final-url-action="tmw_cr_slot_banner_import_final_url_overrides"', $html, 'Slot Setup should include hidden compatibility nonce source for legacy final-url importer.' );
    tmw_assert_true( false === strpos( $html, 'name="action" value="tmw_cr_slot_banner_import_allowed_country_overrides"' ), 'Slot Setup should not restore legacy standalone allowed-country form action.' );
    tmw_assert_true( false === strpos( $html, 'name="action" value="tmw_cr_slot_banner_import_final_url_overrides"' ), 'Slot Setup should not restore legacy standalone final-url form action.' );
};


$tests['legacy_import_nonce_compatibility_markup_is_hidden'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );

    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    tmw_assert_contains( 'class="screen-reader-text"', $html, 'Slot Setup should render hidden legacy nonce compatibility container.' );
    tmw_assert_true( false === strpos( $html, '<h3>Import Allowed Country Overrides</h3>' ), 'Hidden compatibility should not restore legacy allowed-country heading.' );
    tmw_assert_true( false === strpos( $html, '<h3>Import Final URL Overrides</h3>' ), 'Hidden compatibility should not restore legacy final-url heading.' );
    tmw_assert_contains( '<h3>Import Both Override CSVs</h3>', $html, 'Hidden compatibility should preserve combined UI visibility.' );
};

$tests['slot_setup_renders_safe_skipped_offers_import_section'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );

    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    tmw_assert_contains( 'Import Skipped / Rejected Offers', $html, 'Slot Setup should render skipped offers import section.' );
    tmw_assert_contains( 'Import Skipped Offers', $html, 'Slot Setup should render skipped offers import submit button.' );
    tmw_assert_contains( 'Current skipped / rejected offers (latest 50)', $html, 'Slot Setup should render skipped offers summary table.' );
};

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


$tests['get_offer_type_keys_detects_cpi_from_offer_name'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $types = $repository->get_offer_type_keys( array( 'name' => 'Brand X - CPI - T1' ) );
    tmw_assert_true( in_array( 'cpi', $types, true ), 'CPI should be detected from offer name.' );
};

$tests['get_offer_type_keys_detects_cpm_from_payout_type_field'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $types = $repository->get_offer_type_keys( array( 'name' => 'Brand Y', 'payout_type' => 'CPM' ) );
    tmw_assert_true( in_array( 'cpm', $types, true ), 'CPM should be detected from payout_type.' );
};

$tests['get_offer_type_keys_pps_still_detected_after_regex_extension'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $types = $repository->get_offer_type_keys( array( 'name' => 'Jerkmate - PPS' ) );
    tmw_assert_true( in_array( 'pps', $types, true ), 'PPS should still be detected.' );
};

$tests['logo_coverage_report_for_type_pps_matches_legacy_pps_report'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array(
        'a' => array( 'id' => 'a', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
        'b' => array( 'id' => 'b', 'name' => 'Unknown Brand - PPS', 'status' => 'active' ),
    ) );
    $settings = array( 'allowed_offer_types' => array( 'pps' ) );
    $generic = $repository->get_logo_coverage_report_for_type( 'pps', $settings );
    $legacy = $repository->get_pps_logo_coverage_report( $settings );
    tmw_assert_same( array_keys( $legacy ), array_keys( $generic ), 'Generic PPS report should match legacy shape.' );
    tmw_assert_same( $legacy['pps_candidates_total'], $generic['pps_candidates_total'], 'PPS candidate counts should match.' );
    tmw_assert_same( $legacy['pps_with_logo'], $generic['pps_with_logo'], 'PPS with-logo counts should match.' );
    tmw_assert_same( $legacy['pps_missing_logo'], $generic['pps_missing_logo'], 'PPS missing-logo counts should match.' );
};

$tests['logo_coverage_report_for_type_returns_zero_counts_for_empty_type'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array(
        'a' => array( 'id' => 'a', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
    ) );
    $report = $repository->get_logo_coverage_report_for_type( 'cpi', array( 'allowed_offer_types' => array( 'pps' ) ) );
    tmw_assert_same( 0, (int) $report['cpi_candidates_total'], 'No CPI candidates should result in zero CPI candidates.' );
    tmw_assert_same( 0, (int) $report['cpi_with_logo'], 'No CPI candidates should result in zero CPI with-logo count.' );
    tmw_assert_same( 0, (int) $report['cpi_missing_logo'], 'No CPI candidates should result in zero CPI missing-logo count.' );
    tmw_assert_true( isset( $report['pps_candidates_total'] ), 'Legacy pps_candidates_total key should remain available for compatibility.' );
};


$tests['logo_coverage_report_for_type_cpi_uses_type_specific_keys'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array(
        'cpi-has-logo' => array( 'id' => 'cpi-has-logo', 'name' => 'Jerkmate - CPI', 'status' => 'active' ),
        'cpi-missing-logo' => array( 'id' => 'cpi-missing-logo', 'name' => 'Unknown Brand - CPI', 'status' => 'active' ),
    ) );
    $report = $repository->get_logo_coverage_report_for_type( 'cpi', array( 'allowed_offer_types' => array( 'cpi' ) ) );

    tmw_assert_same( 2, (int) $report['cpi_candidates_total'], 'CPI report should use type-specific candidates key.' );
    tmw_assert_same( 1, (int) $report['cpi_with_logo'], 'CPI report should use type-specific with-logo key.' );
    tmw_assert_same( 1, (int) $report['cpi_missing_logo'], 'CPI report should use type-specific missing-logo key.' );
    tmw_assert_true( isset( $report['pps_candidates_total'] ), 'Legacy PPS keys should remain present for compatibility.' );
};


$tests['logo_coverage_report_for_type_invalid_type_falls_back_to_pps'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array(
        'a' => array( 'id' => 'a', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
    ) );
    $settings = array( 'allowed_offer_types' => array( 'pps' ) );

    $empty_report = $repository->get_logo_coverage_report_for_type( '', $settings );
    tmw_assert_true( ! isset( $empty_report['_candidates_total'] ), 'Empty type should not produce malformed _candidates_total key.' );
    tmw_assert_true( isset( $empty_report['pps_candidates_total'] ), 'Empty type should fall back to pps report keys.' );

    $bad_report = $repository->get_logo_coverage_report_for_type( 'bad-type', $settings );
    tmw_assert_true( ! isset( $bad_report['bad-type_candidates_total'] ), 'Invalid type should not produce bad-type_candidates_total key.' );
    tmw_assert_true( ! isset( $bad_report['_candidates_total'] ), 'Invalid type should not produce malformed _candidates_total key.' );
    tmw_assert_true( isset( $bad_report['pps_candidates_total'] ), 'Invalid type should fall back to pps report keys.' );
};

$tests['sanitize_allowed_offer_types_accepts_cpi_and_cpm'] = function() {
    tmw_reset_test_state();
    tmw_assert_same( array( 'cpi', 'cpm' ), TMW_CR_Slot_Offer_Repository::sanitize_allowed_offer_types( array( 'cpi', 'cpm' ) ), 'CPI and CPM should be accepted by sanitizer.' );
};

$tests['sanitize_allowed_offer_types_default_remains_pps_only_when_input_empty'] = function() {
    tmw_reset_test_state();
    tmw_assert_same( array( 'pps' ), TMW_CR_Slot_Offer_Repository::sanitize_allowed_offer_types( array() ), 'Empty type input should default to PPS only.' );
};

$tests['frontend_pool_allowlist_remains_pps_only_after_regex_extension'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( 'cpi1' => array( 'id' => 'cpi1', 'name' => 'Brand X - CPI - T1', 'status' => 'active', 'payout_type' => 'CPI', 'tracking_url' => 'https://trk.example.test/cpi1' ) ) );
    $repo->save_offer_overrides( array( 'cpi1' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/cpi1-final', 'allowed_countries' => 'United States' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array(), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_same( 0, count( $offers ), 'Default PPS-only allowlist must exclude CPI offers from frontend pool.' );
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


$tests['skipped_offers_import_saves_rows'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->import_skipped_offers_csv( "offer_id,offer_name,decision,reason,notes\n8757,Endura Naturals - PPS,skip,male_enhancement_penis_enlarger,\n9770,Gabrielle Moore Masterclasses,review_later,course_intent,Different strategy" );
    $skipped = $repository->get_skipped_offers();
    tmw_assert_same( 2, count( $skipped ), 'CSV import should persist keyed skipped offers rows.' );
    tmw_assert_same( 'review_later', $skipped['9770']['decision'], 'Decision should be persisted.' );
};

$tests['skipped_offers_import_sanitizes_values'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->import_skipped_offers_csv( "offer_id,offer_name,decision,reason,notes\n8757,<b>Endura</b>,skip,<script>x</script>,https://bad.example" );
    $skipped = $repository->get_skipped_offers();
    tmw_assert_true( false === strpos( $skipped['8757']['offer_name'], '<' ), 'Offer name should be sanitized.' );
    tmw_assert_true( false === strpos( $skipped['8757']['reason'], '<' ), 'Reason should be sanitized.' );
    tmw_assert_true( false === strpos( $skipped['8757']['notes'], 'https://' ), 'Notes should strip https URLs.' );
};

$tests['skipped_offers_import_strips_urls_from_notes'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->import_skipped_offers_csv( "offer_id,offer_name,decision,reason,notes\n8757,Endura,skip,male_enhancement,Do not add http://bad.example and https://evil.example or www.spam.test" );
    $skipped = $repository->get_skipped_offer( '8757' );
    tmw_assert_true( false === strpos( $skipped['notes'], 'http://' ), 'Stored notes should not contain http URLs.' );
    tmw_assert_true( false === strpos( $skipped['notes'], 'https://' ), 'Stored notes should not contain https URLs.' );
    tmw_assert_true( false === strpos( $skipped['notes'], 'www.' ), 'Stored notes should not contain www URLs.' );
};

$tests['skipped_offers_import_defaults_empty_decision_to_skip'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->import_skipped_offers_csv( "offer_id,offer_name,decision,reason,notes\n8757,Endura,,male_enhancement,Note" );
    $skipped = $repository->get_skipped_offer( '8757' );
    tmw_assert_same( 'skip', $skipped['decision'], 'Empty decision should default to skip.' );
};

$tests['skipped_offers_import_rejects_missing_offer_id'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $counts = $repository->import_skipped_offers_csv( "offer_id,offer_name,decision,reason,notes\n,Endura,skip,male_enhancement,Note" );
    tmw_assert_same( 0, $counts['imported'], 'Rows missing offer_id should be skipped.' );
};

$tests['skipped_offers_not_erased_when_settings_save_omits_tracker_field'] = function() {
    tmw_reset_test_state();
    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure-key' ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_skipped_offers( array( '8757' => array( 'offer_id' => '8757', 'decision' => 'skip', 'reason' => 'r', 'notes' => '', 'updated_at' => '2026-01-01 00:00:00' ) ) );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );
    $page->sanitize_settings( array( 'headline' => 'H', 'subheadline' => 'S', 'cta_text' => 'C', 'cta_url' => 'https://base.test', 'subid_param' => 'subid', 'subid_value' => 'slot', 'cr_api_key' => '' ) );
    tmw_assert_true( null !== $repository->get_skipped_offer( '8757' ), 'Saving unrelated settings should not erase skipped offers.' );
};

$tests['skipped_offers_table_not_rendered_in_settings_runtime_hotfix'] = function() {
    tmw_reset_test_state();
    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure-key' ) );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repository->save_skipped_offers( array( '8757' => array( 'offer_id' => '8757', 'offer_name' => 'Endura', 'decision' => 'skip', 'reason' => 'male_enhancement', 'notes' => '', 'updated_at' => '2026-01-01 00:00:00' ) ) );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );
    $_GET = array( 'tab' => 'settings' );
    ob_start();
    $page->render_page();
    $html = ob_get_clean();
    tmw_assert_same( false, strpos( $html, 'Skipped PPS offers' ), 'Settings tab should not render skipped offers UI during runtime hotfix.' );
    tmw_assert_same( false, strpos( $html, '8757' ), 'Settings tab should not render skipped offers rows during runtime hotfix.' );
};

$tests['skipped_offers_import_handler_preserves_existing_rows'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_skipped_offers( array( '1111' => array( 'offer_id' => '1111', 'offer_name' => 'Old Offer', 'decision' => 'skip', 'reason' => 'legacy', 'notes' => '', 'updated_at' => '2026-01-01 00:00:00' ) ) );
    $_POST['skipped_offers_csv'] = "offer_id,offer_name,decision,reason,notes
2222,New Offer,skip,policy,No fit";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_skipped_offers();
    $rows = $repo->get_skipped_offers();
    tmw_assert_true( isset( $rows['1111'] ), 'Skipped offers import should preserve existing rows not present in new CSV.' );
    tmw_assert_true( isset( $rows['2222'] ), 'Skipped offers import should add new rows.' );
};

$tests['skipped_offers_import_handler_redirects_to_slot_setup'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['skipped_offers_csv'] = "offer_id,offer_name,decision,reason,notes
2222,New Offer,skip,policy,No fit";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_skipped_offers();
    tmw_assert_same( 'slot-setup', (string) $page->notice['tab'], 'Skipped offers import should redirect back to slot-setup tab.' );
};

$tests['skipped_offers_import_handler_requires_reason_and_offer_id'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['skipped_offers_csv'] = "offer_id,offer_name,decision,reason,notes
,Missing ID,skip,policy,No fit
3333,Missing Reason,skip,,No fit
4444,Valid,skip,policy,Keep";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_skipped_offers();
    $rows = $repo->get_skipped_offers();
    tmw_assert_true( ! isset( $rows['3333'] ), 'Rows without reason should be skipped.' );
    tmw_assert_true( isset( $rows['4444'] ), 'Rows with required fields should be imported.' );
};

$tests['skipped_offers_import_notes_strip_urls'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['skipped_offers_csv'] = "offer_id,offer_name,decision,reason,notes
5555,URL Notes,skip,policy,Reject https://evil.test and www.bad.test";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_skipped_offers();
    $row = $repo->get_skipped_offer( '5555' );
    tmw_assert_true( false === strpos( (string) $row['notes'], 'https://' ), 'Skipped-offers handler should strip URL-like notes.' );
    tmw_assert_true( false === strpos( (string) $row['notes'], 'www.' ), 'Skipped-offers handler should strip www notes.' );
};

$tests['skipped_offers_ui_does_not_change_frontend_pool'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        '8757' => array( 'id' => '8757', 'name' => 'Offer A - PPS', 'status' => 'active' ),
        '9770' => array( 'id' => '9770', 'name' => 'Offer B - PPS', 'status' => 'active' ),
    ) );
    $before = $repo->get_frontend_slot_offers( 'sidebar', TMW_CR_Slot_Sidebar_Banner::get_settings(), array(), 'US', array() );
    $_POST['skipped_offers_csv'] = "offer_id,offer_name,decision,reason,notes
8757,Offer A - PPS,skip,internal,No use";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_skipped_offers();
    $after = $repo->get_frontend_slot_offers( 'sidebar', TMW_CR_Slot_Sidebar_Banner::get_settings(), array(), 'US', array() );
    tmw_assert_same( count( $before ), count( $after ), 'Skipped offers UI/storage should not change frontend pool eligibility.' );
};

$tests['skipped_offers_tracker_does_not_change_frontend_pool_legacy'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $offers = array(
        '8757' => array( 'id' => '8757', 'name' => 'Offer A - PPS', 'status' => 'active' ),
        '9770' => array( 'id' => '9770', 'name' => 'Offer B - PPS', 'status' => 'active' ),
    );
    $repository->save_synced_offers( $offers );
    $before = $repository->get_frontend_slot_offers( 'sidebar', TMW_CR_Slot_Sidebar_Banner::get_settings(), array(), 'US', array() );
    $repository->save_skipped_offers( array( '8757' => array( 'offer_id' => '8757', 'decision' => 'skip', 'reason' => 'internal', 'notes' => '', 'updated_at' => '2026-01-01 00:00:00' ) ) );
    $after = $repository->get_frontend_slot_offers( 'sidebar', TMW_CR_Slot_Sidebar_Banner::get_settings(), array(), 'US', array() );
    tmw_assert_same( count( $before ), count( $after ), 'Skipped offers tracker must not change frontend pool eligibility.' );
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
    tmw_assert_contains( 'Offers with API use_target_rules enabled:', $html, 'Slot setup should show use_target_rules audit count.' );
    tmw_assert_contains( 'Offers with use_target_rules but no manual country override:', $html, 'Slot setup should show manual override recommendation count.' );
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


$tests['sanitize_settings_without_offer_overrides_preserves_imported_final_url_override'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '1234' => array( 'final_url_override' => 'https://trk.example.com/?tid=abc' ) ) );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->sanitize_settings( array( 'headline' => 'Keep overrides' ) );
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( 'https://trk.example.com/?tid=abc', (string) $saved['1234']['final_url_override'], 'sanitize_settings should not erase imported override when offer_overrides is absent.' );
};

$tests['sanitize_settings_unrelated_settings_preserve_imported_final_url_override'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '2234' => array( 'final_url_override' => 'https://trk.example.com/?tid=def' ) ) );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->sanitize_settings( array( 'cta_text' => 'Updated CTA', 'allowed_offer_types' => array( 'pps' ) ) );
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( 'https://trk.example.com/?tid=def', (string) $saved['2234']['final_url_override'], 'Unrelated settings save should preserve imported final_url_override.' );
};

$tests['sanitize_settings_merges_submitted_overrides_into_existing_imported_overrides'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '3234' => array( 'final_url_override' => 'https://trk.example.com/?tid=ghi', 'custom_cta_text' => 'Original CTA' ) ) );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->sanitize_settings(
        array(
            'offer_overrides' => array(
                '3234' => array(
                    'custom_cta_text' => 'Updated CTA',
                    'final_url_override' => 'https://trk.example.com/?tid=ghi',
                ),
            ),
        )
    );
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( 'https://trk.example.com/?tid=ghi', (string) $saved['3234']['final_url_override'], 'Submitted override should merge without clearing imported final_url_override.' );
    tmw_assert_same( 'Updated CTA', (string) $saved['3234']['custom_cta_text'], 'Submitted fields should update existing override row.' );
};

$tests['sanitize_settings_explicit_final_url_override_clear_only_clears_target_offer'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides(
        array(
            '4234' => array( 'final_url_override' => 'https://trk.example.com/?tid=clearme' ),
            '4235' => array( 'final_url_override' => 'https://trk.example.com/?tid=keepme' ),
        )
    );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->sanitize_settings(
        array(
            'offer_overrides' => array(
                '4234' => array(
                    'final_url_override' => '',
                ),
            ),
        )
    );
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( '', (string) $saved['4234']['final_url_override'], 'Explicit clear should remove final_url_override for submitted offer.' );
    tmw_assert_same( 'https://trk.example.com/?tid=keepme', (string) $saved['4235']['final_url_override'], 'Explicit clear should not affect other offers.' );
};

$tests['manual_final_url_override_importer_accepts_and_preserves'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '999' => array( 'custom_cta_text' => 'keep me' ) ) );
    $_POST['_wpnonce'] = 'legacy-standard';
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
    $_POST['_wpnonce'] = 'legacy-standard';
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

$tests['manual_allowed_country_override_importer_accepts_pipe_separated'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['allowed_country_override_csv'] = "offer_id,allowed_countries\n8780,\"Belgium|United States|Belgium\"\n";
    $page->handle_import_allowed_country_overrides();
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( array( 'Belgium', 'United States' ), array_values( $saved['8780']['allowed_countries'] ), 'Importer should parse pipe-separated countries and remove duplicates.' );
};

$tests['allowed_country_override_preserves_existing_final_url_override'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '8780' => array( 'final_url_override' => 'https://trk.example.com/x' ) ) );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['allowed_country_override_csv'] = "offer_id,allowed_countries\n8780,\"Belgium|United States\"\n";
    $page->handle_import_allowed_country_overrides();
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( 'https://trk.example.com/x', (string) $saved['8780']['final_url_override'], 'Country import should preserve existing final URL override.' );
};

$tests['individual_final_url_import_redirects_to_slot_setup'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['final_url_override_csv'] = "offer_id,final_url_override\n1234,https://trk.example.com/?tid=abc\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_final_url_overrides();
    tmw_assert_same( 'slot-setup', (string) $page->notice['tab'], 'Final URL import should redirect back to slot-setup tab.' );
};

$tests['individual_allowed_country_import_redirects_to_slot_setup'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['allowed_country_override_csv'] = "offer_id,allowed_countries\n8780,\"Belgium|United States\"\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_allowed_country_overrides();
    tmw_assert_same( 'slot-setup', (string) $page->notice['tab'], 'Allowed country import should redirect back to slot-setup tab.' );
};

$tests['combined_override_import_processes_both_payloads'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['allowed_country_override_csv'] = "offer_id,allowed_countries\n8780,\"Belgium|United States\"\n";
    $_POST['final_url_override_csv'] = "offer_id,final_url_override\n8873,https://trk.example.com/?tid=combo\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_both_overrides();
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( array( 'Belgium', 'United States' ), array_values( $saved['8780']['allowed_countries'] ), 'Combined import should process allowed countries payload.' );
    tmw_assert_same( 'https://trk.example.com/?tid=combo', (string) $saved['8873']['final_url_override'], 'Combined import should process final URL payload.' );
    tmw_assert_same( 'slot-setup', (string) $page->notice['tab'], 'Combined import should redirect back to slot-setup tab.' );
};


$tests['slot_setup_combined_import_accepts_one_empty_textarea_country_only'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['allowed_country_override_csv'] = "offer_id,allowed_countries\n8780,\"Belgium|United States\"\n";
    $_POST['final_url_override_csv'] = '';
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_both_overrides();
    $saved = $repo->get_offer_overrides();
    tmw_assert_same( 'slot-setup', (string) $page->notice['tab'], 'Combined import should redirect back to slot-setup tab for country-only payloads.' );
    tmw_assert_true( isset( $saved['8780'] ), 'Combined import should create country override row when country textarea is provided.' );
    tmw_assert_same( array( 'Belgium', 'United States' ), array_values( (array) ( $saved['8780']['allowed_countries'] ?? array() ) ), 'Combined import should save country overrides when final URL textarea is empty.' );
};

$tests['slot_setup_combined_import_accepts_one_empty_textarea_final_url_only'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['allowed_country_override_csv'] = '';
    $_POST['final_url_override_csv'] = "offer_id,final_url_override\n8873,https://trk.example.com/?tid=solo\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_both_overrides();
    $saved = $repo->get_offer_overrides();
    tmw_assert_true( isset( $saved['8873'] ), 'Combined import should create final URL override row when final URL textarea is provided.' );
    tmw_assert_same( 'https://trk.example.com/?tid=solo', (string) ( $saved['8873']['final_url_override'] ?? '' ), 'Combined import should process the non-empty textarea only.' );
};

$tests['slot_setup_combined_import_rejects_both_empty'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['allowed_country_override_csv'] = '';
    $_POST['final_url_override_csv'] = '';
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_both_overrides();
    tmw_assert_same( 'No override rows were submitted.', (string) $page->notice['message'], 'Combined import should show a safe notice when both textareas are empty.' );
};


$tests['legacy_final_url_import_accepts_custom_hidden_nonce_field'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['tmw_legacy_final_url_nonce'] = 'legacy-custom';
    $_POST['final_url_override_csv'] = "offer_id,final_url_override\n1234,https://trk.example.com/?tid=abc\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_final_url_overrides();
    $saved = $repo->get_offer_overrides();
    tmw_assert_true( isset( $saved['1234'] ), 'Legacy final URL importer should create override row when custom hidden nonce field is used.' );
    tmw_assert_same( 'https://trk.example.com/?tid=abc', (string) ( $saved['1234']['final_url_override'] ?? '' ), 'Legacy final URL importer should save data when custom hidden nonce field is used.' );
    tmw_assert_same( 'slot-setup', (string) $page->notice['tab'], 'Legacy final URL importer should redirect to slot-setup when custom hidden nonce field is used.' );
};

$tests['legacy_allowed_country_import_accepts_custom_hidden_nonce_field'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $_POST['tmw_legacy_allowed_country_nonce'] = 'legacy-custom';
    $_POST['allowed_country_override_csv'] = "offer_id,allowed_countries\n8780,\"Belgium|United States\"\n";
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $page->handle_import_allowed_country_overrides();
    $saved = $repo->get_offer_overrides();
    tmw_assert_true( isset( $saved['8780'] ), 'Legacy allowed-country importer should create override row when custom hidden nonce field is used.' );
    tmw_assert_same( array( 'Belgium', 'United States' ), array_values( (array) ( $saved['8780']['allowed_countries'] ?? array() ) ), 'Legacy allowed-country importer should save data when custom hidden nonce field is used.' );
    tmw_assert_same( 'slot-setup', (string) $page->notice['tab'], 'Legacy allowed-country importer should redirect to slot-setup when custom hidden nonce field is used.' );
};

$tests['legacy_import_handlers_still_accept_standard_wpnonce_for_compat'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );

    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['final_url_override_csv'] = "offer_id,final_url_override\n1234,https://trk.example.com/?tid=abc\n";
    $final_url_page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $final_url_page->handle_import_final_url_overrides();
    tmw_assert_same( 'slot-setup', (string) $final_url_page->notice['tab'], 'Legacy final URL importer should redirect to slot-setup.' );

    $_POST['_wpnonce'] = 'legacy-standard';
    $_POST['allowed_country_override_csv'] = "offer_id,allowed_countries\n8780,\"Belgium|United States\"\n";
    $country_page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    $country_page->handle_import_allowed_country_overrides();
    tmw_assert_same( 'slot-setup', (string) $country_page->notice['tab'], 'Legacy allowed country importer should redirect to slot-setup.' );

    $saved = $repo->get_offer_overrides();
    tmw_assert_true( isset( $saved['1234'] ), 'Legacy final URL importer should create the target offer override row.' );
    tmw_assert_true( isset( $saved['8780'] ), 'Legacy allowed country importer should create the target offer override row.' );
    tmw_assert_same( 'https://trk.example.com/?tid=abc', (string) ( $saved['1234']['final_url_override'] ?? '' ), 'Legacy final URL importer should still save data.' );
    tmw_assert_same( array( 'Belgium', 'United States' ), array_values( (array) ( $saved['8780']['allowed_countries'] ?? array() ) ), 'Legacy allowed country importer should still save data.' );
};


$tests['offer_without_tracking_or_manual_override_remains_excluded'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '7002' => array( 'id' => '7002', 'name' => 'Offer 7002 - PPS', 'status' => 'active' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_same( 0, count( $offers ), 'Offer without tracking_url and without final_url_override should remain excluded.' );
};

$tests['country_alias_matching_name_code_cases'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers(
        array(
            '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS', 'status' => 'active' ),
            '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm - PPS', 'status' => 'active' ),
        )
    );
    $repo->save_offer_overrides(
        array(
            '8780' => array( 'final_url_override' => 'https://trk.example.com/jerkmate', 'allowed_countries' => array( 'Belgium' ) ),
            '10366' => array( 'final_url_override' => 'https://trk.example.com/naughtycharm', 'allowed_countries' => array( 'United States' ) ),
        )
    );
    tmw_assert_true( $repo->is_offer_allowed_for_country( '8780', 'Belgium', $repo->get_offer_override( '8780' ), array(), array() ), 'Belgium should match Belgium.' );
    tmw_assert_true( $repo->is_offer_allowed_for_country( '8780', 'BE', $repo->get_offer_override( '8780' ), array(), array() ), 'BE should match Belgium.' );
    tmw_assert_true( $repo->is_offer_allowed_for_country( '10366', 'United States', $repo->get_offer_override( '10366' ), array(), array() ), 'United States should match United States.' );
    tmw_assert_true( $repo->is_offer_allowed_for_country( '10366', 'US', $repo->get_offer_override( '10366' ), array(), array() ), 'US should match United States.' );
    tmw_assert_true( $repo->is_offer_allowed_for_country( '10366', 'GB', array( 'allowed_countries' => array( 'United Kingdom' ) ), array(), array() ), 'GB should match United Kingdom.' );

    $offers_be = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '8780', '10366' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'BE', array() );
    $ids_be = array_map( static function( $row ) { return (string) $row['id']; }, $offers_be );
    tmw_assert_true( in_array( '8780', $ids_be, true ), 'Offer 8780 should remain eligible in Belgium/BE.' );
    tmw_assert_true( ! in_array( '10366', $ids_be, true ), 'Offer 10366 should be excluded in Belgium.' );

    $offers_us = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '8780', '10366' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    $ids_us = array_map( static function( $row ) { return (string) $row['id']; }, $offers_us );
    tmw_assert_true( in_array( '10366', $ids_us, true ), 'Offer 10366 should be eligible in US.' );
};

$tests['override_only_offers_respect_manual_country_and_alias_rules'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array() );
    $repo->save_offer_overrides(
        array(
            '8780' => array(
                'final_url_override' => 'https://trk.example.com/jerkmate-winner',
                'allowed_countries' => array( 'Belgium' ),
            ),
            '10366' => array(
                'final_url_override' => 'https://trk.example.com/naughtycharm-winner',
                'allowed_countries' => array( 'United States' ),
            ),
        )
    );

    $legacy_catalog = array(
        '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS' ),
        '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm - PPS' ),
    );
    $offers_be = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'BE', $legacy_catalog );
    $ids_be = array_map( static function( $row ) { return (string) $row['id']; }, $offers_be );
    tmw_assert_true( in_array( '8780', $ids_be, true ), 'Override-only 8780 should be eligible in Belgium/BE.' );
    tmw_assert_true( ! in_array( '10366', $ids_be, true ), 'Override-only 10366 should remain excluded in Belgium/BE.' );
    $offer_8780 = array();
    foreach ( $offers_be as $offer_row ) {
        if ( '8780' === (string) ( $offer_row['id'] ?? '' ) ) {
            $offer_8780 = $offer_row;
            break;
        }
    }
    tmw_assert_same( 'https://trk.example.com/jerkmate-winner', (string) ( $offer_8780['cta_url'] ?? '' ), 'Override-only 8780 should use manual final_url_override as CTA.' );
    tmw_assert_same( 'manual_override_only', (string) ( $offer_8780['source'] ?? '' ), 'Override-only 8780 should be marked with manual override-only source.' );
};

$tests['override_only_naughtycharm_us_only_is_eligible_in_us'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '10366' => array( 'final_url_override' => 'https://trk.example.com/naughtycharm', 'allowed_countries' => array( 'United States' ) ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    $ids = array_map( static function( $row ) { return (string) $row['id']; }, $offers );
    tmw_assert_true( in_array( '10366', $ids, true ), 'Override-only 10366 should be eligible in US with identity map fallback.' );
};

$tests['override_only_unknown_offer_id_is_not_surfaced'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '999999' => array( 'final_url_override' => 'https://trk.example.com/u', 'allowed_countries' => array( 'United States' ) ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_same( 0, count( $offers ), 'Unknown override-only IDs should remain excluded without safe identity/logo resolution.' );
};

$tests['override_only_offer_requires_manual_allowed_country'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '8780' => array( 'final_url_override' => 'https://trk.example.com/jm' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'BE', array() );
    tmw_assert_same( 0, count( $offers ), 'Override-only offer must have manual allowed_countries.' );
};

$tests['override_only_offer_requires_valid_manual_final_url'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '8780' => array( 'final_url_override' => 'https://preview.example.com/path', 'allowed_countries' => array( 'Belgium' ) ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'BE', array() );
    tmw_assert_same( 0, count( $offers ), 'Override-only offer must use a valid non-preview CTA URL.' );
};

$tests['override_only_disabled_offer_is_excluded'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '8780' => array( 'enabled' => 0, 'final_url_override' => 'https://trk.example.com/jm', 'allowed_countries' => array( 'Belgium' ) ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'BE', array() );
    tmw_assert_same( 0, count( $offers ), 'Disabled override-only offer should be excluded.' );
};

$tests['override_only_blocked_country_overrides_allowed_country'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '8780' => array( 'final_url_override' => 'https://trk.example.com/jm', 'allowed_countries' => array( 'Belgium' ), 'blocked_countries' => array( 'BE' ) ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    tmw_assert_same( 0, count( $offers ), 'Blocked countries must override allowed countries for override-only offers.' );
};

$tests['override_only_unavailable_account_offer_is_excluded'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '9781' => array( 'label_override' => 'Group Fallback - Dating.com PPS', 'final_url_override' => 'https://trk.example.com/dating', 'allowed_countries' => array( 'United States' ) ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_same( 0, count( $offers ), 'Override-only unavailable-account PPS offers must be excluded.' );
};

$tests['manual_winner_audit_matches_override_only_frontend_eligibility'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides(
        array(
            '8780' => array( 'final_url_override' => 'https://trk.example.com/jm', 'allowed_countries' => array( 'Belgium' ) ),
            '10366' => array( 'final_url_override' => 'https://trk.example.com/nc', 'allowed_countries' => array( 'United States' ) ),
        )
    );

    $rows = $repo->get_manual_winner_eligibility_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'BE', array() );
    $by_id = array();
    foreach ( $rows as $row ) {
        $by_id[ (string) $row['offer_id'] ] = $row;
    }
    tmw_assert_same( 'eligible', (string) $by_id['8780']['eligibility_result'], 'Audit should mark override-only 8780 eligible in BE.' );
    tmw_assert_same( 'country_not_allowed', (string) $by_id['10366']['exclusion_reason'], 'Audit should mark 10366 country_not_allowed in BE.' );

    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'BE', array() );
    $ids = array_map( static function( $row ) { return (string) $row['id']; }, $offers );
    tmw_assert_true( in_array( '8780', $ids, true ), 'Frontend should include 8780 in BE.' );
    tmw_assert_true( ! in_array( '10366', $ids, true ), 'Frontend should exclude 10366 in BE.' );
};

$tests['pps_expansion_readiness_audit_reports_sources_and_hosts'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers(
        array(
            '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/jm?transaction_id=1' ),
            '2492' => array( 'id' => '2492', 'name' => 'XLoveGay - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/gay?transaction_id=1' ),
        )
    );
    $repo->save_offer_overrides(
        array(
            '8780' => array( 'final_url_override' => 'https://trk.override.example.com/jm', 'allowed_countries' => array( 'Belgium', 'United States' ) ),
            '10366' => array( 'final_url_override' => 'https://trk.override.example.com/nc', 'allowed_countries' => array( 'United States' ) ),
        )
    );
    $rows  = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $by_id = array();
    foreach ( $rows as $row ) {
        $by_id[ (string) $row['offer_id'] ] = $row;
    }
    tmw_assert_same( 'synced', (string) $by_id['8780']['source'], 'Synced offer should be marked synced.' );
    tmw_assert_same( 'final_url_override', (string) $by_id['8780']['final_cta_source'], 'Override should have highest CTA source priority.' );
    tmw_assert_same( 'trk.override.example.com', (string) $by_id['8780']['final_cta_host'], 'Audit must expose host only.' );
    tmw_assert_same( 'manual_override_only', (string) $by_id['10366']['source'], 'Override-only known offer should be included.' );
    tmw_assert_same( 'yes', (string) $by_id['2492']['blocked_by_business_rule'], 'Blocked offer should be flagged in audit.' );
};

$tests['pps_expansion_audit_us_only_offer_is_frontend_ready_for_us'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '10366' => array( 'final_url_override' => 'https://trk.example.com/nc', 'allowed_countries' => array( 'United States' ) ) ) );
    $rows = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $row  = array_values( array_filter( $rows, static function( $item ) { return '10366' === (string) $item['offer_id']; } ) )[0];
    tmw_assert_same( 'excluded', (string) $row['example_be_result'], 'US-only offer should be excluded for BE example.' );
    tmw_assert_same( 'eligible', (string) $row['example_us_result'], 'US-only offer should be eligible for US example.' );
    tmw_assert_same( 'yes', (string) $row['frontend_ready'], 'US-only eligible offer should still be frontend-ready.' );
};

$tests['pps_expansion_audit_blocks_missing_country_override'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/jm?transaction_id=1' ) ) );
    $rows = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $row  = array_values( array_filter( $rows, static function( $item ) { return '8780' === (string) $item['offer_id']; } ) )[0];
    tmw_assert_same( 'no', (string) $row['frontend_ready'], 'Offer missing country override should not be frontend-ready.' );
    tmw_assert_same( 'missing_allowed_country_override', (string) $row['block_reason'], 'Missing country override reason should be explicit.' );
};

$tests['pps_expansion_audit_blocks_invalid_cta'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS', 'status' => 'active', 'tracking_url' => 'https://preview.example.com/path' ) ) );
    $repo->save_offer_overrides( array( '8780' => array( 'allowed_countries' => array( 'Belgium' ) ) ) );
    $rows = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $row  = array_values( array_filter( $rows, static function( $item ) { return '8780' === (string) $item['offer_id']; } ) )[0];
    tmw_assert_same( 'missing_valid_cta', (string) $row['block_reason'], 'Invalid CTA should map to missing_valid_cta.' );
};

$tests['pps_expansion_audit_blocks_business_rule_offer'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '2492' => array( 'id' => '2492', 'name' => 'XLoveGay - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/x?transaction_id=1' ) ) );
    $repo->save_offer_overrides( array( '2492' => array( 'allowed_countries' => array( 'United States' ) ) ) );
    $rows = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $row  = array_values( array_filter( $rows, static function( $item ) { return '2492' === (string) $item['offer_id']; } ) )[0];
    tmw_assert_same( 'business_rule_blocked', (string) $row['block_reason'], 'Blocked term should map to business_rule_blocked.' );
};

$tests['pps_expansion_audit_unknown_override_only_identity'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '999999' => array( 'final_url_override' => 'https://trk.example.com/u', 'allowed_countries' => array( 'United States' ) ) ) );
    $rows = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $row  = array_values( array_filter( $rows, static function( $item ) { return '999999' === (string) $item['offer_id']; } ) )[0];
    tmw_assert_same( 'unknown_override_only_identity', (string) $row['block_reason'], 'Unknown override-only identity should be explicitly blocked.' );
};

$tests['pps_expansion_audit_blocks_missing_logo'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( 'u1' => array( 'id' => 'u1', 'name' => 'Unknown Brand - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/u1?transaction_id=1' ) ) );
    $repo->save_offer_overrides( array( 'u1' => array( 'allowed_countries' => array( 'United States' ) ) ) );
    $rows = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $row  = array_values( array_filter( $rows, static function( $item ) { return 'u1' === (string) $item['offer_id']; } ) )[0];
    tmw_assert_same( 'missing_logo', (string) $row['block_reason'], 'Unknown logo should map to missing_logo.' );
};

$tests['pps_expansion_audit_summary_counts_are_correct'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers(
        array(
            '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/a?transaction_id=1' ),
            '2492' => array( 'id' => '2492', 'name' => 'XLoveGay - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/b?transaction_id=1' ),
        )
    );
    $repo->save_offer_overrides(
        array(
            '8780' => array( 'allowed_countries' => array( 'Belgium' ) ),
            '10366' => array( 'final_url_override' => 'https://trk.example.com/c', 'allowed_countries' => array( 'United States' ) ),
        )
    );
    $rows    = $repo->get_pps_expansion_readiness_audit_rows( array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ) );
    $summary = $repo->get_pps_expansion_readiness_audit_summary( $rows );
    tmw_assert_true( (int) $summary['total_pps_candidates'] >= 3, 'Summary should count PPS candidates.' );
    tmw_assert_true( (int) $summary['frontend_ready_pps_offers'] >= 1, 'Summary should count frontend-ready candidates.' );
    tmw_assert_true( (int) $summary['override_only_candidates'] >= 1, 'Summary should include override-only candidates.' );
    tmw_assert_true( (int) $summary['synced_candidates'] >= 2, 'Summary should include synced candidates.' );
};



$tests['dashboard_filter_pps_is_distinct_from_revshare_lifetime'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta', 'dashboard_meta' );
    $repository->save_synced_offers(
        array(
            '9001' => array( 'id' => '9001', 'name' => 'Offer PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
            '9002' => array( 'id' => '9002', 'name' => 'Offer CPA Flat', 'status' => 'active', 'payout_type' => 'cpa_flat' ),
        )
    );

    $model = $repository->get_dashboard_filter_model();
    $types = (array) ( $model['supported']['payout_type'] ?? array() );

    tmw_assert_true( in_array( 'pps', $types, true ), 'PPS should be present as a distinct payout type.' );
    tmw_assert_true( in_array( 'revshare_lifetime', $types, true ), 'Revshare Lifetime should remain present for cpa_flat.' );
};

$tests['dashboard_filter_payout_type_dropdown_includes_full_cr_set'] = function() {
    tmw_reset_test_state();
    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure' ) );
    $_GET = array( 'tab' => 'performance' );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    foreach ( array( 'pps', 'soi', 'doi', 'cpi', 'cpm', 'cpc', 'multi_cpa', 'revshare', 'revshare_lifetime' ) as $type_key ) {
        tmw_assert_contains( 'value="' . $type_key . '"', $html, 'Payout filter dropdown should include ' . $type_key . '.' );
    }
};

$tests['dashboard_filter_normalizes_ppc_alias_to_cpc'] = function() {
    tmw_reset_test_state();
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta', 'dashboard_meta' );
    $repository->save_synced_offers(
        array(
            '9010' => array( 'id' => '9010', 'name' => 'Offer PPC', 'status' => 'active', 'payout_type' => 'PPC' ),
        )
    );

    $model = $repository->get_dashboard_filter_model();
    $types = (array) ( $model['supported']['payout_type'] ?? array() );

    tmw_assert_true( in_array( 'cpc', $types, true ), 'PPC alias should normalize to cpc.' );
};

$tests['frontend_pool_unchanged_for_8780_be_after_payout_alias_fix'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/jm?transaction_id=1' ) ) );
    $repo->save_offer_overrides( array( '8780' => array( 'allowed_countries' => array( 'Belgium' ) ) ) );

    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '8780' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    tmw_assert_same( '8780', (string) $offers[0]['id'], '8780 should remain eligible for Belgium.' );
};

$tests['frontend_pool_unchanged_for_10366_us_after_payout_alias_fix'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/nc?transaction_id=1' ) ) );
    $repo->save_offer_overrides( array( '10366' => array( 'allowed_countries' => array( 'United States' ) ) ) );

    $us_offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '10366' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_same( '10366', (string) $us_offers[0]['id'], '10366 should remain eligible for United States.' );

    $be_offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '10366' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    tmw_assert_same( 0, count( $be_offers ), '10366 should remain excluded for Belgium.' );
};

$tests['unavailable_account_offers_still_excluded_after_payout_alias_fix'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers(
        array(
            '9647' => array( 'id' => '9647', 'name' => 'Blocked A - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/a?transaction_id=1' ),
            '9781' => array( 'id' => '9781', 'name' => 'Blocked B - PPS', 'status' => 'active', 'tracking_url' => 'https://trk.example.com/b?transaction_id=1' ),
        )
    );

    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '9647', '9781' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_same( 0, count( $offers ), 'Unavailable account offers 9647 and 9781 must remain excluded.' );
};



$tests['offers_tab_payout_dropdown_includes_full_cr_set_with_proper_labels'] = function() {
    tmw_reset_test_state();
    update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, array( 'cr_api_key' => 'secure' ) );
    $_GET = array( 'tab' => 'offers' );
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    $expected = array(
        'pps' => 'PPS',
        'soi' => 'SOI',
        'doi' => 'DOI',
        'cpi' => 'CPI',
        'cpm' => 'CPM',
        'cpc' => 'CPC',
        'multi_cpa' => 'Multi-CPA',
        'revshare' => 'Revshare',
        'revshare_lifetime' => 'Revshare Lifetime',
    );

    foreach ( $expected as $value => $label ) {
        tmw_assert_contains( 'value="' . $value . '"', $html, 'Offers payout dropdown should include value=' . $value . '.' );
        tmw_assert_contains( '>' . $label . '<', $html, 'Offers payout dropdown should include label=' . $label . '.' );
    }
};

$tests['performance_filter_cpc_matches_raw_ppc_row'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repo->save_synced_offers( array( 'p1' => array( 'id' => 'p1', 'name' => 'P1', 'status' => 'active' ) ) );
    $repo->save_offer_stats( array( 'p1|US' => array( 'offer_id' => 'p1', 'offer_name' => 'P1', 'country_name' => 'US', 'payout_type' => 'PPC', 'clicks' => 10, 'conversions' => 1, 'payout' => 2 ) ) );
    $rows = $repo->get_performance_rows( 'US', array( 'payout_type' => 'cpc' ) );
    tmw_assert_same( 1, count( $rows ), 'Filter cpc should match raw PPC row.' );
};

$tests['performance_filter_revshare_lifetime_matches_raw_cpa_flat_row'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repo->save_synced_offers( array( 'p2' => array( 'id' => 'p2', 'name' => 'P2', 'status' => 'active' ) ) );
    $repo->save_offer_stats( array( 'p2|US' => array( 'offer_id' => 'p2', 'offer_name' => 'P2', 'country_name' => 'US', 'payout_type' => 'cpa_flat', 'clicks' => 10, 'conversions' => 1, 'payout' => 2 ) ) );
    $rows = $repo->get_performance_rows( 'US', array( 'payout_type' => 'revshare_lifetime' ) );
    tmw_assert_same( 1, count( $rows ), 'Filter revshare_lifetime should match raw cpa_flat row.' );
};

$tests['performance_filter_revshare_matches_raw_revenue_share_row'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repo->save_synced_offers( array( 'p3' => array( 'id' => 'p3', 'name' => 'P3', 'status' => 'active' ) ) );
    $repo->save_offer_stats( array( 'p3|US' => array( 'offer_id' => 'p3', 'offer_name' => 'P3', 'country_name' => 'US', 'payout_type' => 'revenue_share', 'clicks' => 10, 'conversions' => 1, 'payout' => 2 ) ) );
    $rows = $repo->get_performance_rows( 'US', array( 'payout_type' => 'revshare' ) );
    tmw_assert_same( 1, count( $rows ), 'Filter revshare should match raw revenue_share row.' );
};

$tests['performance_filter_multi_cpa_matches_raw_hybrid_row'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repo->save_synced_offers( array( 'p4' => array( 'id' => 'p4', 'name' => 'P4', 'status' => 'active' ) ) );
    $repo->save_offer_stats( array( 'p4|US' => array( 'offer_id' => 'p4', 'offer_name' => 'P4', 'country_name' => 'US', 'payout_type' => 'hybrid', 'clicks' => 10, 'conversions' => 1, 'payout' => 2 ) ) );
    $rows = $repo->get_performance_rows( 'US', array( 'payout_type' => 'multi_cpa' ) );
    tmw_assert_same( 1, count( $rows ), 'Filter multi_cpa should match raw hybrid row.' );
};

$tests['performance_filter_pps_distinct_from_revshare_lifetime_in_results'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides', 'stats', 'stats_meta' );
    $repo->save_synced_offers( array( 'p5' => array( 'id' => 'p5', 'name' => 'P5', 'status' => 'active' ), 'p6' => array( 'id' => 'p6', 'name' => 'P6', 'status' => 'active' ) ) );
    $repo->save_offer_stats( array(
        'p5|US' => array( 'offer_id' => 'p5', 'offer_name' => 'P5', 'country_name' => 'US', 'payout_type' => 'PPS', 'clicks' => 10, 'conversions' => 1, 'payout' => 2 ),
        'p6|US' => array( 'offer_id' => 'p6', 'offer_name' => 'P6', 'country_name' => 'US', 'payout_type' => 'cpa_flat', 'clicks' => 10, 'conversions' => 1, 'payout' => 2 ),
    ) );

    $pps_rows = $repo->get_performance_rows( 'US', array( 'payout_type' => 'pps' ) );
    tmw_assert_same( 1, count( $pps_rows ), 'PPS filter should return only PPS row.' );
    tmw_assert_same( 'p5', (string) $pps_rows[0]['offer_id'], 'PPS filter should return p5.' );

    $lifetime_rows = $repo->get_performance_rows( 'US', array( 'payout_type' => 'revshare_lifetime' ) );
    tmw_assert_same( 1, count( $lifetime_rows ), 'Revshare Lifetime filter should return only cpa_flat row.' );
    tmw_assert_same( 'p6', (string) $lifetime_rows[0]['offer_id'], 'Revshare Lifetime filter should return p6.' );
};

$tests['offers_tab_payout_filter_for_pps_returns_only_pps_offers'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        'o1' => array( 'id' => 'o1', 'name' => 'Offer PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
        'o2' => array( 'id' => 'o2', 'name' => 'Offer FLAT', 'status' => 'active', 'payout_type' => 'cpa_flat' ),
    ) );

    $result = $repo->get_filtered_synced_offers_for_admin( array( 'payout_type' => array( 'pps' ) ), array() );
    tmw_assert_same( 1, (int) $result['total'], 'Offers payout filter pps should return one offer.' );
    tmw_assert_same( 'o1', (string) $result['items'][0]['id'], 'Offers payout filter pps should return PPS offer only.' );
};



/**
 * Test double repository used to inject deterministic audit rows.
 */
class TMW_Test_Audit_Repo extends TMW_CR_Slot_Offer_Repository {
    public $manual_rows = array();
    public $pps_rows = array();

    /**
     * Return injected manual audit rows for Slot Setup rendering tests.
     *
     * @param array<string,mixed> $settings Unused in test double.
     * @param array<string,mixed> $banner_data Unused in test double.
     * @param string $country Unused in test double.
     * @param array<string,mixed> $legacy_catalog Unused in test double.
     * @return array<int,array<string,mixed>>
     */
    public function get_manual_winner_eligibility_audit_rows( $settings, $banner_data = array(), $country = '', $legacy_catalog = array() ) {
        return $this->manual_rows;
    }

    /**
     * Return injected PPS expansion rows for Slot Setup rendering tests.
     *
     * @param array<string,mixed> $settings Unused in test double.
     * @param array<string,mixed> $banner_data Unused in test double.
     * @return array<int,array<string,mixed>>
     */
    public function get_pps_expansion_readiness_audit_rows( $settings, $banner_data = array() ) {
        return $this->pps_rows;
    }

    /**
     * Return deterministic summary values derived from provided PPS rows.
     *
     * @param array<int,array<string,mixed>> $rows PPS rows.
     * @return array<string,int>
     */
    public function get_pps_expansion_readiness_audit_summary( $rows ) {
        return array(
            'total_pps_candidates' => count( $rows ),
            'frontend_ready_pps_offers' => 22,
            'blocked_by_business_rule' => 7,
            'missing_valid_cta' => 63,
            'missing_allowed_country_override' => 1,
            'missing_logo' => 0,
            'override_only_candidates' => 1,
            'synced_candidates' => 431,
        );
    }
}

/**
 * Build synthetic audit rows with predictable IDs/names for pagination and filter assertions.
 *
 * @param int    $count Number of rows.
 * @param string $prefix Prefix for row names.
 * @param string $type Row family marker.
 * @return array<int,array<string,mixed>>
 */
function tmw_make_audit_rows( $count, $prefix, $type = 'pps' ) {
    $rows = array();
    for ( $i = 1; $i <= $count; $i++ ) {
        $id = sprintf( '%03d', $i );
        $rows[] = array(
            'offer_id' => (string) $i,
            'offer_name' => strtoupper( $prefix ) . '_ROW_' . $id,
            'source' => ( 0 === $i % 2 ) ? 'synced' : 'override_only',
            'block_reason' => ( 0 === $i % 5 ) ? 'missing_logo' : ( ( 0 === $i % 3 ) ? 'business_rule_blocked' : 'missing_valid_cta' ),
            'frontend_ready' => ( 0 === $i % 7 ) ? 'yes' : 'no',
            'pps_detected' => 'yes',
            'blocked_by_business_rule' => ( 0 === $i % 3 ) ? 'yes' : 'no',
            'final_cta_source' => 'tracking_url',
            'final_cta_host' => 'trk.example.test',
            'has_allowed_country_override' => ( 0 === $i % 4 ) ? 'yes' : 'no',
            'allowed_countries_count' => ( 0 === $i % 4 ) ? '2' : '0',
            'example_be_result' => 'ok',
            'example_us_result' => 'ok',
            'logo_resolved' => 'yes',
            'logo_filename' => 'logo.png',
            'has_final_url_override' => 1,
            'final_url_host' => 'example.test',
            'visitor_country_raw' => 'BE',
            'visitor_country_normalized' => 'BE',
            'eligibility_result' => 'eligible',
            'exclusion_reason' => '',
        );
    }
    return $rows;
}

/**
 * Build a compact PPS row set that explicitly covers all filter/search branches.
 *
 * @return array<int,array<string,mixed>>
 */
function tmw_make_pps_filter_rows() {
    return array(
        array( 'offer_id' => '8780', 'offer_name' => 'JerkMate MixedCase', 'source' => 'synced', 'block_reason' => '', 'frontend_ready' => 'yes', 'pps_detected' => 'yes', 'blocked_by_business_rule' => 'no', 'final_cta_source' => 'tracking_url', 'final_cta_host' => 'trk.example.test', 'has_allowed_country_override' => 'yes', 'allowed_countries_count' => '2', 'example_be_result' => 'ok', 'example_us_result' => 'ok', 'logo_resolved' => 'yes', 'logo_filename' => 'jm.png' ),
        array( 'offer_id' => '1001', 'offer_name' => 'Missing CTA', 'source' => 'synced', 'block_reason' => 'missing_valid_cta', 'frontend_ready' => 'no', 'pps_detected' => 'yes', 'blocked_by_business_rule' => 'no', 'final_cta_source' => 'none', 'final_cta_host' => '', 'has_allowed_country_override' => 'yes', 'allowed_countries_count' => '2', 'example_be_result' => 'blocked', 'example_us_result' => 'blocked', 'logo_resolved' => 'yes', 'logo_filename' => 'a.png' ),
        array( 'offer_id' => '1002', 'offer_name' => 'Missing Country', 'source' => 'synced', 'block_reason' => 'missing_allowed_country_override', 'frontend_ready' => 'no', 'pps_detected' => 'yes', 'blocked_by_business_rule' => 'no', 'final_cta_source' => 'tracking_url', 'final_cta_host' => 'trk.example.test', 'has_allowed_country_override' => 'no', 'allowed_countries_count' => '0', 'example_be_result' => 'blocked', 'example_us_result' => 'blocked', 'logo_resolved' => 'yes', 'logo_filename' => 'b.png' ),
        array( 'offer_id' => '1003', 'offer_name' => 'Missing Logo', 'source' => 'synced', 'block_reason' => 'missing_logo', 'frontend_ready' => 'no', 'pps_detected' => 'yes', 'blocked_by_business_rule' => 'no', 'final_cta_source' => 'tracking_url', 'final_cta_host' => 'trk.example.test', 'has_allowed_country_override' => 'yes', 'allowed_countries_count' => '2', 'example_be_result' => 'blocked', 'example_us_result' => 'blocked', 'logo_resolved' => 'no', 'logo_filename' => '' ),
        array( 'offer_id' => '1004', 'offer_name' => 'Business Rule', 'source' => 'synced', 'block_reason' => 'business_rule_blocked', 'frontend_ready' => 'no', 'pps_detected' => 'yes', 'blocked_by_business_rule' => 'yes', 'final_cta_source' => 'tracking_url', 'final_cta_host' => 'trk.example.test', 'has_allowed_country_override' => 'yes', 'allowed_countries_count' => '2', 'example_be_result' => 'blocked', 'example_us_result' => 'blocked', 'logo_resolved' => 'yes', 'logo_filename' => 'd.png' ),
        array( 'offer_id' => '1005', 'offer_name' => 'Unavailable Account', 'source' => 'synced', 'block_reason' => 'unavailable_account_offer', 'frontend_ready' => 'no', 'pps_detected' => 'yes', 'blocked_by_business_rule' => 'yes', 'final_cta_source' => 'tracking_url', 'final_cta_host' => 'trk.example.test', 'has_allowed_country_override' => 'yes', 'allowed_countries_count' => '2', 'example_be_result' => 'blocked', 'example_us_result' => 'blocked', 'logo_resolved' => 'yes', 'logo_filename' => 'e.png' ),
        array( 'offer_id' => '1006', 'offer_name' => 'Override Only', 'source' => 'override_only', 'block_reason' => '', 'frontend_ready' => 'no', 'pps_detected' => 'yes', 'blocked_by_business_rule' => 'no', 'final_cta_source' => 'tracking_url', 'final_cta_host' => 'trk.example.test', 'has_allowed_country_override' => 'yes', 'allowed_countries_count' => '2', 'example_be_result' => 'ok', 'example_us_result' => 'ok', 'logo_resolved' => 'yes', 'logo_filename' => 'f.png' ),
    );
}

$tests['pps_audit_default_pagination_25_rows'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_audit_rows( 60, 'PPS' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'PPS_ROW_001', $html, 'Page 1 should include first PPS row.' );
    tmw_assert_contains( 'PPS_ROW_025', $html, 'Page 1 should include row 25.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_026' ), 'Page 1 should exclude row 26.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_060' ), 'Page 1 should exclude row 60.' );
};

$tests['pps_audit_pagination_page_2_returns_rows_26_through_50'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_page' => '2' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_audit_rows( 60, 'PPS' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'PPS_ROW_026', $html, 'Page 2 should include row 26.' );
    tmw_assert_contains( 'PPS_ROW_050', $html, 'Page 2 should include row 50.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_025' ), 'Page 2 should exclude row 25.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_051' ), 'Page 2 should exclude row 51.' );
};

$tests['manual_audit_default_pagination_25_rows'] = function() {
    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' ); $repo->manual_rows = tmw_make_audit_rows( 60, 'MAN' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'MAN_ROW_001', $html, 'Manual page 1 should include row 1.' );
    tmw_assert_contains( 'MAN_ROW_025', $html, 'Manual page 1 should include row 25.' );
    tmw_assert_true( false === strpos( $html, 'MAN_ROW_026' ), 'Manual page 1 should exclude row 26.' );
    tmw_assert_true( false === strpos( $html, 'MAN_ROW_060' ), 'Manual page 1 should exclude row 60.' );
};

$tests['manual_audit_pagination_page_2_returns_rows_26_through_50'] = function() {
    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup', 'manual_audit_page' => '2' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' ); $repo->manual_rows = tmw_make_audit_rows( 60, 'MAN' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'MAN_ROW_026', $html, 'Manual page 2 should include row 26.' );
    tmw_assert_contains( 'MAN_ROW_050', $html, 'Manual page 2 should include row 50.' );
    tmw_assert_true( false === strpos( $html, 'MAN_ROW_025' ), 'Manual page 2 should exclude row 25.' );
    tmw_assert_true( false === strpos( $html, 'MAN_ROW_051' ), 'Manual page 2 should exclude row 51.' );
};

$tests['pps_audit_pagination_clamps_invalid_page_values'] = function() {
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_audit_rows( 60, 'PPS' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );

    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup', 'pps_audit_page' => '-5' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'PPS_ROW_001', $html, 'Negative page should normalize to page 1.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_026' ), 'Negative page should exclude row 26.' );

    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup', 'pps_audit_page' => 'abc' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'PPS_ROW_025', $html, 'Non numeric page should normalize to page 1.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_051' ), 'Non numeric page should exclude row 51.' );

    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup', 'pps_audit_page' => '999' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'PPS_ROW_051', $html, 'Too-high page should clamp to last page.' );
    tmw_assert_contains( 'PPS_ROW_060', $html, 'Last page should include row 60.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_025' ), 'Clamped last page should exclude early rows.' );
};

$tests['manual_audit_pagination_independent_from_pps_pagination'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'manual_audit_page' => '3', 'pps_audit_page' => '2' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->manual_rows = tmw_make_audit_rows( 60, 'MAN' );
    $repo->pps_rows = tmw_make_audit_rows( 60, 'PPS' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'MAN_ROW_051', $html, 'Manual page 3 should include row 51.' );
    tmw_assert_contains( 'MAN_ROW_060', $html, 'Manual page 3 should include row 60.' );
    tmw_assert_true( false === strpos( $html, 'MAN_ROW_050' ), 'Manual page 3 should exclude row 50.' );
    tmw_assert_contains( 'PPS_ROW_026', $html, 'PPS page 2 should include row 26.' );
    tmw_assert_contains( 'PPS_ROW_050', $html, 'PPS page 2 should include row 50.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_025' ), 'PPS page 2 should exclude row 25.' );
    tmw_assert_true( false === strpos( $html, 'PPS_ROW_051' ), 'PPS page 2 should exclude row 51.' );
};

$tests['pps_audit_filter_frontend_ready_only_excludes_blocked_rows'] = function() {
    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'frontend_ready_only' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' ); $repo->pps_rows = tmw_make_pps_filter_rows();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'JerkMate MixedCase', $html, 'Ready row should remain.' );
    tmw_assert_true( false === strpos( $html, 'Missing CTA' ), 'Blocked row should be filtered out.' );
    tmw_assert_contains( 'Filtered rows:', $html, 'Filtered row summary should appear.' );
};
$tests['pps_audit_filter_missing_cta_keeps_only_missing_cta_block_reason'] = function() {
    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'missing_cta' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' ); $repo->pps_rows = tmw_make_pps_filter_rows(); $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Missing CTA', $html, 'Missing CTA row should be present.' ); tmw_assert_true( false === strpos( $html, 'Missing Country' ), 'Other reasons excluded.' );
};
$tests['pps_audit_filter_missing_country_override_keeps_only_matching_rows'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'missing_country_override' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_pps_filter_rows();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Missing Country', $html, 'Country override row present.' );
    tmw_assert_true( false === strpos( $html, 'Missing Logo' ), 'Other rows excluded.' );
};
$tests['pps_audit_filter_missing_logo_keeps_only_matching_rows'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'missing_logo' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_pps_filter_rows();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Missing Logo', $html, 'Missing logo row present.' );
    tmw_assert_true( false === strpos( $html, 'Missing CTA' ), 'Other rows excluded.' );
};
$tests['pps_audit_filter_blocked_by_business_rule_keeps_business_and_unavailable_rows'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'blocked_by_business_rule' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_pps_filter_rows();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Business Rule', $html, 'Business blocked row present.' );
    tmw_assert_contains( 'Unavailable Account', $html, 'Unavailable row present.' );
    tmw_assert_true( false === strpos( $html, 'Missing CTA' ), 'Non-business block excluded.' );
};
$tests['pps_audit_filter_override_only_and_synced_sources'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'override_only' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_pps_filter_rows();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Override Only', $html, 'Override source present.' );
    tmw_assert_true( false === strpos( $html, 'Missing CTA' ), 'Synced source excluded for override filter.' );

    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'synced' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Missing CTA', $html, 'Synced row present.' );
    tmw_assert_true( false === strpos( $html, 'Override Only' ), 'Override row excluded in synced filter.' );
};
$tests['pps_audit_search_matches_offer_id_substring'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_search' => '8780' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_pps_filter_rows();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'JerkMate MixedCase', $html, 'ID match row present.' );
    tmw_assert_true( false === strpos( $html, 'Missing CTA' ), 'Unrelated row excluded.' );
};
$tests['pps_audit_search_matches_offer_name_case_insensitive'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_search' => 'jerkmate' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_pps_filter_rows();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'JerkMate MixedCase', $html, 'Case-insensitive match should pass.' );
    tmw_assert_true( false === strpos( $html, 'Unavailable Account' ), 'Unrelated row excluded.' );
};
$tests['audit_pagination_summary_counts_unchanged_by_filter'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'pps_audit_filter' => 'missing_logo' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->pps_rows = tmw_make_audit_rows( 60, 'PPS' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Total PPS candidates: 60', $html, 'Summary must use unfiltered count.' );
    tmw_assert_contains( 'Filtered rows:', $html, 'Filtered row count should render separately.' );
};
$tests['audit_pagination_links_preserve_slot_setup_tab'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'manual_audit_page' => '2', 'pps_audit_page' => '2' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->manual_rows = tmw_make_audit_rows( 60, 'MAN' );
    $repo->pps_rows = tmw_make_audit_rows( 60, 'PPS' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'page=tmw-cr-slot-sidebar-banner', $html, 'Pagination links should preserve page slug.' );
    tmw_assert_contains( 'tab=slot-setup', $html, 'Pagination links should preserve slot-setup tab.' );
};
$tests['audit_pagination_links_preserve_independent_report_query_args'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'manual_audit_page' => '3', 'pps_audit_page' => '2', 'pps_audit_filter' => 'missing_cta', 'pps_audit_search' => '8780' );
    $repo = new TMW_Test_Audit_Repo( 'o', 'm' );
    $repo->manual_rows = tmw_make_audit_rows( 60, 'MAN' );
    $repo->pps_rows = tmw_make_audit_rows( 60, 'PPS' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'manual_audit_page=3', $html, 'PPS links should preserve manual page.' );
    tmw_assert_contains( 'pps_audit_page=2', $html, 'Manual links should preserve PPS page.' );
    tmw_assert_contains( 'pps_audit_filter=missing_cta', $html, 'Manual links should preserve PPS filter.' );
    tmw_assert_contains( 'pps_audit_search=8780', $html, 'Manual links should preserve PPS search.' );
};
$tests['audit_pagination_does_not_break_combined_override_import_form_post'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'action" value="tmw_cr_slot_banner_import_both_overrides"', $html, 'Combined import action remains.' );
    tmw_assert_contains( 'name="allowed_country_override_csv"', $html, 'Combined import allowed-country field remains.' );
    tmw_assert_contains( 'name="final_url_override_csv"', $html, 'Combined import final URL field remains.' );
};
$tests['audit_pagination_does_not_readd_removed_standalone_import_sections'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_true( false === strpos( $html, '<h3>Import Allowed Country Overrides</h3>' ), 'Standalone allowed-country section should remain removed.' );
    tmw_assert_true( false === strpos( $html, '<h3>Import Final URL Overrides</h3>' ), 'Standalone final-url section should remain removed.' );
    tmw_assert_contains( '<h3>Import Both Override CSVs</h3>', $html, 'Combined import section should remain visible.' );
};
$tests['audit_pagination_does_not_change_frontend_pool'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        '8780' => array( 'id' => '8780', 'name' => 'Jerkmate', 'status' => 'active', 'payout_type' => 'PPS' ),
        '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm', 'status' => 'active', 'payout_type' => 'PPS' ),
        '9647' => array( 'id' => '9647', 'name' => 'Group Fallback - Tapyn - PPS - Mobile - Android', 'status' => 'active', 'payout_type' => 'PPS' ),
        '9781' => array( 'id' => '9781', 'name' => 'Group Fallback - Dating.com PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $repo->save_offer_overrides( array(
        '8780' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/a', 'allowed_countries' => 'Belgium' ),
        '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/b', 'allowed_countries' => 'United States' ),
    ) );
    $be = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    $us = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_true( false !== strpos( wp_json_encode( $be ), '8780' ), '8780 should be eligible for Belgium.' );
    tmw_assert_true( false !== strpos( wp_json_encode( $us ), '10366' ), '10366 should be eligible for US.' );
    tmw_assert_true( false === strpos( wp_json_encode( $be ), '10366' ), '10366 should be excluded for Belgium.' );
    tmw_assert_true( false === strpos( wp_json_encode( $us ), '9647' ) && false === strpos( wp_json_encode( $us ), '9781' ), 'Unavailable offers should remain excluded.' );
};
$tests['logo_status_mapped_local_when_file_exists_for_known_brand'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_with_temp_logo_file(
        'sex-messenger-80x80-transparent.png',
        'fixture',
        static function () use ( $repo ) {
            $offer = array( 'id' => 'x1', 'name' => 'Sex Messenger' );
            tmw_assert_same( 'mapped_local', $repo->get_logo_status_for_offer_any( 'x1', $offer ), 'Known local brand should resolve mapped local logo.' );
        }
    );
};
$tests['logo_status_manual_override_when_image_url_override_set'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( 'x2' => array( 'image_url_override' => 'https://cdn.example.test/logo.png' ) ) );
    tmw_assert_same( 'manual_override', $repo->get_logo_status_for_offer_any( 'x2', array( 'id' => 'x2', 'name' => 'Unknown Name' ) ), 'Manual override should win.' );
};
$tests['logo_status_placeholder_only_when_no_brand_match_and_no_override'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_assert_same( 'placeholder_only', $repo->get_logo_status_for_offer_any( 'x3', array( 'id' => 'x3', 'name' => 'No Brand Fixture' ) ), 'Unknown offer should be placeholder only.' );
};
$tests['logo_status_missing_when_brand_match_but_no_file_on_disk'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_without_logo_file(
        'jerkmate-80x80-transparent.png',
        static function () use ( $repo ) {
            $status = $repo->get_logo_status_for_offer_any( 'x4', array( 'id' => 'x4', 'name' => 'Jerkmate' ) );
            tmw_assert_same( 'missing', $status, 'Known mapped brand without disk file should return missing.' );
        }
    );
};

$tests['logo_filename_missing_file_does_not_reference_report_alias_state'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    tmw_without_logo_file( '80x80/jerkmate-80x80-transparent.png', static function () use ( $repo ) {
        $filename = $repo->get_offer_logo_filename( array( 'id' => 'logo-fixture', 'name' => 'Jerkmate' ) );
        tmw_assert_same( '', $filename, 'Missing mapped logo file should return empty filename without report alias references.' );
    } );
};

$tests['logo_status_filter_filters_offers_correctly'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    tmw_with_temp_logo_file( 'jerkmate-80x80-transparent.png', 'fixture', static function () use ( $repo ) {
        $repo->save_synced_offers(
            array(
                '20001' => array( 'id' => '20001', 'name' => 'Unique Manual Offer', 'status' => 'active', 'payout_type' => 'PPS' ),
                '20002' => array( 'id' => '20002', 'name' => 'Jerkmate', 'status' => 'active', 'payout_type' => 'PPS' ),
                '20003' => array( 'id' => '20003', 'name' => 'No Brand Placeholder', 'status' => 'active', 'payout_type' => 'PPS' ),
            )
        );
        $repo->save_offer_overrides( array( '20001' => array( 'image_url_override' => 'https://cdn.example.test/20001.png' ) ) );
        $filtered = $repo->get_filtered_synced_offers_for_admin( array( 'logo_status' => 'manual_override', 'per_page' => 50 ), array() );
        $json = wp_json_encode( $filtered['items'] );
        tmw_assert_true( false !== strpos( $json, '20001' ), 'Manual override offer should remain after logo_status filter.' );
        tmw_assert_true( false === strpos( $json, '20002' ) && false === strpos( $json, '20003' ), 'Non-matching offers should be excluded by logo_status filter.' );
    } );
};
$tests['frontend_eligibility_summary_returns_valid_for_8780_be'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    tmw_with_temp_logo_file( 'jerkmate-80x80-transparent.png', 'fixture', static function () use ( $repo ) {
        $offer = array( 'id' => '8780', 'name' => 'Jerkmate', 'status' => 'active', 'payout_type' => 'PPS', 'thumbnail' => 'https://cdn.example.test/jerkmate.png' );
        $repo->save_offer_overrides( array( '8780' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/a', 'allowed_countries' => 'Belgium' ) ) );
        $summary = $repo->get_offer_frontend_eligibility_summary( $offer, array( 'allowed_offer_types' => array( 'pps' ) ), 'BE', array() );
        tmw_assert_true( true === $summary['is_eligible'] && 'valid' === $summary['block_reason'], '8780 should be valid for BE.' );
    } );
};
$tests['frontend_eligibility_summary_returns_country_not_allowed_for_10366_be'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $offer = array( 'id' => '10366', 'name' => 'NaughtyCharm', 'status' => 'active', 'payout_type' => 'PPS' );
    $repo->save_offer_overrides( array( '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/b', 'allowed_countries' => 'United States' ) ) );
    $summary = $repo->get_offer_frontend_eligibility_summary( $offer, array( 'allowed_offer_types' => array( 'pps' ) ), 'BE', array() );
    tmw_assert_same( 'country_not_allowed', $summary['block_reason'], '10366 should be blocked for BE.' );
};
$tests['frontend_eligibility_summary_returns_valid_for_10366_us'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    tmw_with_temp_logo_file( 'naughtycharm-80x80-transparent.png', 'fixture', static function () use ( $repo ) {
        $offer = array( 'id' => '10366', 'name' => 'NaughtyCharm', 'status' => 'active', 'payout_type' => 'PPS' );
        $repo->save_offer_overrides( array( '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/b', 'allowed_countries' => 'United States' ) ) );
        $summary = $repo->get_offer_frontend_eligibility_summary( $offer, array( 'allowed_offer_types' => array( 'pps' ) ), 'US', array() );
        tmw_assert_true( true === $summary['is_eligible'] && 'valid' === $summary['block_reason'], '10366 should be valid for US.' );
    } );
};
$tests['frontend_eligibility_summary_returns_business_rule_blocked_for_male_targeted_offer'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $offer = array( 'id' => '2492', 'name' => 'Gay Dating Offer', 'status' => 'active', 'payout_type' => 'PPS', 'tag' => 'gay' );
    $summary = $repo->get_offer_frontend_eligibility_summary( $offer, array( 'allowed_offer_types' => array( 'pps' ) ), 'US', array() );
    tmw_assert_same( 'business_rule_blocked', $summary['block_reason'], 'Male targeted offer should be blocked by business rule.' );
};
$tests['frontend_eligibility_summary_returns_unavailable_account_offer_for_9647_and_9781'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    foreach ( array( '9647' => 'Group Fallback - Tapyn - PPS - Mobile - Android', '9781' => 'Group Fallback - Dating.com PPS' ) as $id => $name ) {
        $summary = $repo->get_offer_frontend_eligibility_summary( array( 'id' => $id, 'name' => $name, 'status' => 'active', 'payout_type' => 'PPS' ), array( 'allowed_offer_types' => array( 'pps' ) ), 'US', array() );
        tmw_assert_same( 'unavailable_account_offer', $summary['block_reason'], 'Unavailable offer should be flagged for ' . $id );
    }
};
$tests['frontend_eligibility_summary_returns_missing_valid_cta'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $offer = array( 'id' => '24001', 'name' => 'CTA Missing Fixture', 'status' => 'active', 'payout_type' => 'PPS' );
    $summary = $repo->get_offer_frontend_eligibility_summary( $offer, array( 'allowed_offer_types' => array( 'pps' ), 'cta_url' => '' ), 'US', array() );
    tmw_assert_same( 'missing_valid_cta', $summary['block_reason'], 'Missing CTA should block eligibility.' );
};
$tests['frontend_eligibility_summary_matches_frontend_pool_inclusion_for_8780_be'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    tmw_with_temp_logo_file( 'jerkmate-80x80-transparent.png', 'fixture', static function () use ( $repo ) {
        $offer = array( 'id' => '8780', 'name' => 'Jerkmate', 'status' => 'active', 'payout_type' => 'PPS' );
        $repo->save_synced_offers( array( '8780' => $offer ) );
        $repo->save_offer_overrides( array( '8780' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/a', 'allowed_countries' => 'Belgium' ) ) );
        $summary = $repo->get_offer_frontend_eligibility_summary( $offer, array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '8780' ) ), 'Belgium', array() );
        $pool = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '8780' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
        tmw_assert_true( true === $summary['is_eligible'], 'Summary should mark 8780 eligible for BE.' );
        tmw_assert_true( false !== strpos( wp_json_encode( $pool ), '8780' ), 'Frontend pool should include 8780 for BE.' );
    } );
};
$tests['logo_status_tests_restore_logo_fixture_files'] = function() {
    tmw_reset_test_state();
    $files = array(
        'sex-messenger-80x80-transparent.png',
        'jerkmate-80x80-transparent.png',
        'naughtycharm-80x80-transparent.png',
    );
    $before = array();
    foreach ( $files as $file ) {
        $path = TMW_CR_SLOT_BANNER_PATH . 'assets/logos/' . $file;
        $before[ $file ] = array(
            'exists' => file_exists( $path ),
            'hash' => file_exists( $path ) ? md5_file( $path ) : '',
        );
    }
    tmw_with_temp_logo_file( 'sex-messenger-80x80-transparent.png', 'tmp1', static function () {
    } );
    tmw_without_logo_file( 'jerkmate-80x80-transparent.png', static function () {
    } );
    tmw_with_temp_logo_file( 'naughtycharm-80x80-transparent.png', 'tmp2', static function () {
    } );
    foreach ( $files as $file ) {
        $path = TMW_CR_SLOT_BANNER_PATH . 'assets/logos/' . $file;
        $exists_now = file_exists( $path );
        $hash_now = $exists_now ? md5_file( $path ) : '';
        tmw_assert_same( $before[ $file ]['exists'], $exists_now, 'File exists state should be restored for ' . $file );
        tmw_assert_same( $before[ $file ]['hash'], $hash_now, 'File hash should be restored for ' . $file );
    }
};
$tests['offers_tab_renders_logo_source_frontend_eligible_and_block_reason_columns'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'offers' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Logo source', $html, 'Offers tab should render logo source column.' );
    tmw_assert_contains( 'Frontend eligible', $html, 'Offers tab should render frontend eligible column.' );
    tmw_assert_contains( 'Block reason', $html, 'Offers tab should render block reason column.' );
};
$tests['offers_tab_logo_status_filter_dropdown_renders_expected_options'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'offers' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    foreach ( array( 'manual_override', 'mapped_local', 'auto_remote', 'placeholder_only', 'missing' ) as $opt ) {
        tmw_assert_contains( 'value="' . $opt . '"', $html, 'Missing logo status filter option: ' . $opt );
    }
};
$tests['offers_tab_unavailable_account_badge_shown_for_9647_and_9781'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'offers' );
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repo->save_synced_offers( array(
        '9647' => array( 'id' => '9647', 'name' => 'UQ-9647-Offer', 'status' => 'active', 'payout_type' => 'PPS' ),
        '9781' => array( 'id' => '9781', 'name' => 'UQ-9781-Offer', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repo, 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'UQ-9647-Offer', $html, 'Expected 9647 row in offers table.' );
    tmw_assert_contains( 'UQ-9781-Offer', $html, 'Expected 9781 row in offers table.' );
    tmw_assert_true( substr_count( $html, 'Unavailable for account' ) >= 2, 'Unavailable badge should appear for both unavailable offers.' );
};
$tests['offers_tab_logo_status_filter_preserves_existing_payout_filter'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'offers' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    foreach ( array( 'pps', 'soi', 'doi', 'cpi', 'cpm', 'cpc', 'multi_cpa', 'revshare', 'revshare_lifetime' ) as $payout_value ) {
        tmw_assert_contains( 'value="' . $payout_value . '"', $html, 'Payout filter option missing: ' . $payout_value );
    }
};
$tests['offers_tab_does_not_readd_removed_standalone_import_sections'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_true( false === strpos( $html, '<h3>Import Allowed Country Overrides</h3>' ), 'Standalone allowed-country heading must stay hidden.' );
    tmw_assert_true( false === strpos( $html, '<h3>Import Final URL Overrides</h3>' ), 'Standalone final-url heading must stay hidden.' );
    tmw_assert_contains( '<h3>Import Both Override CSVs</h3>', $html, 'Combined import section must remain visible.' );
};
$tests['offers_dashboard_changes_do_not_change_frontend_pool'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        '8780' => array( 'id' => '8780', 'name' => 'Jerkmate', 'status' => 'active', 'payout_type' => 'PPS' ),
        '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm', 'status' => 'active', 'payout_type' => 'PPS' ),
        '9647' => array( 'id' => '9647', 'name' => 'Group Fallback - Tapyn - PPS - Mobile - Android', 'status' => 'active', 'payout_type' => 'PPS' ),
        '9781' => array( 'id' => '9781', 'name' => 'Group Fallback - Dating.com PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $repo->save_offer_overrides( array(
        '8780' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/a', 'allowed_countries' => 'Belgium' ),
        '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/b', 'allowed_countries' => 'United States' ),
    ) );
    $be = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '8780', '10366', '9647', '9781' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    $us = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '8780', '10366', '9647', '9781' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_true( false !== strpos( wp_json_encode( $be ), '8780' ), '8780 remains eligible for BE.' );
    tmw_assert_true( false !== strpos( wp_json_encode( $us ), '10366' ), '10366 remains eligible for US.' );
    tmw_assert_true( false === strpos( wp_json_encode( $be ), '10366' ), '10366 remains excluded for BE.' );
    tmw_assert_true( false === strpos( wp_json_encode( $us ), '9647' ) && false === strpos( wp_json_encode( $us ), '9781' ), 'Unavailable offers remain excluded.' );
};


$tests['enforce_skipped_offers_exclusion_default_is_zero'] = function() {
    tmw_reset_test_state();
    $settings = TMW_CR_Slot_Sidebar_Banner::get_settings();
    tmw_assert_same( 0, (int) $settings['enforce_skipped_offers_exclusion'], 'Default should be 0.' );
};
$tests['enforce_skipped_offers_exclusion_setting_saves_and_loads'] = function() {
    tmw_reset_test_state();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    $a = $page->sanitize_settings( array( 'enforce_skipped_offers_exclusion' => 1 ) );
    tmw_assert_same( 1, (int) $a['enforce_skipped_offers_exclusion'], 'Enabled should save as 1.' );
    $b = $page->sanitize_settings( array() );
    tmw_assert_same( 0, (int) $b['enforce_skipped_offers_exclusion'], 'Missing should save as 0.' );
};

if ( ! function_exists( 'tmw_capture_error_log' ) ) {
    function tmw_capture_error_log( $callback ) {
        $tmp = tempnam( sys_get_temp_dir(), 'tmw-log-' );
        $prev = ini_get( 'error_log' );
        ini_set( 'error_log', $tmp );

        try {
            $callback();
            return file_exists( $tmp ) ? (string) file_get_contents( $tmp ) : '';
        } finally {
            ini_set( 'error_log', (string) $prev );
            if ( is_string( $tmp ) && file_exists( $tmp ) ) {
                unlink( $tmp );
            }
        }
    }
}

$tests['get_skipped_offer_ids_for_frontend_returns_only_skip_decisions'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array(
        '2492'=>array('offer_id'=>'2492','decision'=>'skip','reason'=>' male-targeted ','notes'=>'x'),
        '1111'=>array('offer_id'=>'1111','decision'=>'review_later','reason'=>'r','notes'=>'n'),
        '2222'=>array('offer_id'=>'2222','decision'=>'keep','reason'=>'k','notes'=>'n'),
        ''=>array('offer_id'=>'','decision'=>'skip','reason'=>'e','notes'=>'n'),
    ) );
    $set = $repo->get_skipped_offer_ids_for_frontend();
    tmw_assert_true( isset( $set['2492'] ) && ! isset( $set['1111'] ) && ! isset( $set['2222'] ) && ! isset( $set[''] ), 'Only explicit skip rows with valid IDs should be included.' );
    if ( ! isset( $set['2492'] ) ) {
        throw new Exception( 'Missing expected 2492 row.' );
    }
    tmw_assert_same( 'male-targeted', $set['2492']['reason'], 'Reason should be sanitized.' );
    tmw_assert_true( ! isset( $set['2492']['notes'] ), 'Notes must not be returned.' );
};

$tests['get_skipped_offer_ids_for_frontend_uses_row_key_for_legacy_rows'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array(
        'legacy-2492' => array( 'decision' => 'skip', 'reason' => '  legacy reason  ', 'notes' => 'secret' ),
    ) );
    $set = $repo->get_skipped_offer_ids_for_frontend();
    tmw_assert_true( isset( $set['legacy-2492'] ), 'Legacy key-based offer_id should be included.' );
    tmw_assert_same( 'legacy reason', (string) $set['legacy-2492']['reason'], 'Reason should be sanitized.' );
    tmw_assert_true( ! isset( $set['legacy-2492']['notes'] ), 'Notes should not be returned.' );
};
$tests['get_skipped_offer_ids_for_frontend_does_not_include_non_skip_defaults'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array(
        'legacy-default' => array( 'reason' => 'default decision row', 'notes' => 'secret-a' ),
        'legacy-invalid' => array( 'decision' => 'unknown', 'reason' => 'invalid decision row', 'notes' => 'secret-b' ),
    ) );
    $normalized = $repo->get_skipped_offers();
    $set = $repo->get_skipped_offer_ids_for_frontend();
    tmw_assert_true( isset( $normalized['legacy-default'] ) && 'skip' === (string) $normalized['legacy-default']['decision'], 'Missing decision should normalize to skip.' );
    tmw_assert_true( isset( $normalized['legacy-invalid'] ) && 'skip' === (string) $normalized['legacy-invalid']['decision'], 'Invalid decision should normalize to skip.' );
    tmw_assert_true( ! isset( $set['legacy-default'] ) && ! isset( $set['legacy-invalid'] ), 'Frontend helper should exclude missing/invalid non-skip decisions.' );
};
$tests['frontend_pool_excludes_skip_decision_when_setting_on_for_synced_offer'] = function() {
    tmw_reset_test_state(); $repo=new TMW_CR_Slot_Offer_Repository('offers','meta');
    $repo->save_synced_offers(array('7001'=>array('id'=>'7001','name'=>'Safe PPS','status'=>'active','payout_type'=>'PPS')));
    $repo->save_offer_overrides(array('7001'=>array('enabled'=>1,'final_url_override'=>'https://trk.example.test/7001','allowed_countries'=>'US')));
    $repo->save_skipped_offers(array(array('offer_id'=>'7001','offer_name'=>'Safe PPS','decision'=>'skip','reason'=>'test')));
    $offers=$repo->get_frontend_slot_offers('sidebar',array('allowed_offer_types'=>array('pps'),'slot_offer_ids'=>array('7001'),'enforce_skipped_offers_exclusion'=>1),array('cta_url'=>'','cta_text'=>'CTA'),'US',array());
    tmw_assert_true(false===strpos(wp_json_encode($offers),'7001'),'7001 should be excluded.');
};
$tests['frontend_pool_does_not_exclude_review_later_decision_when_setting_on'] = function() {
    tmw_reset_test_state(); $repo=new TMW_CR_Slot_Offer_Repository('offers','meta');
    $repo->save_synced_offers(array('7002'=>array('id'=>'7002','name'=>'Safe PPS2','status'=>'active','payout_type'=>'PPS')));
    $repo->save_offer_overrides(array('7002'=>array('enabled'=>1,'final_url_override'=>'https://trk.example.test/7002','allowed_countries'=>'US')));
    $repo->save_skipped_offers(array(array('offer_id'=>'7002','offer_name'=>'Safe PPS2','decision'=>'review_later','reason'=>'test')));
    $offers=$repo->get_frontend_slot_offers('sidebar',array('allowed_offer_types'=>array('pps'),'slot_offer_ids'=>array('7002'),'enforce_skipped_offers_exclusion'=>1),array('cta_url'=>'','cta_text'=>'CTA'),'US',array());
    tmw_assert_true(false!==strpos(wp_json_encode($offers),'7002'),'7002 should remain eligible.');
};

$tests['frontend_pool_ignores_skipped_offers_when_setting_off_default'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '7101' => array( 'id' => '7101', 'name' => 'Fixture PPS', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '7101' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/7101', 'allowed_countries' => 'US' ) ) );
    $repo->save_skipped_offers( array( array( 'offer_id' => '7101', 'decision' => 'skip', 'reason' => 'test' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '7101' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_true( false !== strpos( wp_json_encode( $offers ), '7101' ), 'Offer should remain when setting is OFF.' );
};
$tests['frontend_pool_excludes_skip_decision_when_setting_on_for_override_only_offer'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_offer_overrides( array( '8780' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/override-8780', 'allowed_countries' => 'Belgium' ) ) );
    $repo->save_skipped_offers( array( array( 'offer_id' => '8780', 'decision' => 'skip', 'reason' => 'test' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    tmw_assert_true( false === strpos( wp_json_encode( $offers ), '8780' ), 'Override-only offer should be excluded when skipped.' );
};
$tests['frontend_pool_does_not_exclude_keep_or_empty_decision_when_setting_on'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        '7201' => array( 'id' => '7201', 'name' => 'Keep Decision', 'status' => 'active', 'payout_type' => 'PPS' ),
        '7202' => array( 'id' => '7202', 'name' => 'Empty Decision', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $repo->save_offer_overrides( array(
        '7201' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/7201', 'allowed_countries' => 'US' ),
        '7202' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/7202', 'allowed_countries' => 'US' ),
    ) );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array(
        '7201' => array( 'offer_id' => '7201', 'decision' => 'keep', 'reason' => 'keep' ),
        '7202' => array( 'offer_id' => '7202', 'decision' => '', 'reason' => 'empty' ),
    ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '7201','7202' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    $json = wp_json_encode( $offers );
    tmw_assert_true( false !== strpos( $json, '7201' ) && false !== strpos( $json, '7202' ), 'Keep/empty decisions should remain eligible.' );
};
$tests['frontend_pool_skipped_offer_id_matching_uses_string_normalization'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '7001' => array( 'id' => 7001, 'name' => 'Numeric ID', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array( '7001' => array( 'offer_id' => '7001', 'decision' => 'skip', 'reason' => 'id match' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( 7001 ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', array() );
    tmw_assert_true( false === strpos( wp_json_encode( $offers ), '7001' ), 'String-normalized matching should exclude.' );
};
$tests['frontend_pool_8780_be_eligible_with_setting_off'] = function() { tmw_reset_test_state(); $r=new TMW_CR_Slot_Offer_Repository('offers','meta','overrides'); $r->save_synced_offers(array('8780'=>array('id'=>'8780','name'=>'Jerkmate','status'=>'active','payout_type'=>'PPS'))); $r->save_offer_overrides(array('8780'=>array('enabled'=>1,'final_url_override'=>'https://trk.example.test/a','allowed_countries'=>'Belgium'))); $o=$r->get_frontend_slot_offers('sidebar',array('allowed_offer_types'=>array('pps'),'slot_offer_ids'=>array('8780')),array('cta_url'=>'','cta_text'=>'CTA'),'Belgium',array()); tmw_assert_true(false!==strpos(wp_json_encode($o),'8780'),'8780 eligible with OFF'); };
$tests['frontend_pool_8780_be_eligible_with_setting_on_when_not_skipped'] = function() { tmw_reset_test_state(); $r=new TMW_CR_Slot_Offer_Repository('offers','meta','overrides'); $r->save_synced_offers(array('8780'=>array('id'=>'8780','name'=>'Jerkmate','status'=>'active','payout_type'=>'PPS'))); $r->save_offer_overrides(array('8780'=>array('enabled'=>1,'final_url_override'=>'https://trk.example.test/a','allowed_countries'=>'Belgium'))); $o=$r->get_frontend_slot_offers('sidebar',array('allowed_offer_types'=>array('pps'),'slot_offer_ids'=>array('8780'),'enforce_skipped_offers_exclusion'=>1),array('cta_url'=>'','cta_text'=>'CTA'),'Belgium',array()); tmw_assert_true(false!==strpos(wp_json_encode($o),'8780'),'8780 eligible with ON not skipped'); };
$tests['frontend_pool_10366_us_eligible_with_setting_off'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/10366', 'allowed_countries' => 'United States' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '10366' ) ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_true( false !== strpos( wp_json_encode( $offers ), '10366' ), '10366 should be eligible in US when setting is OFF.' );
};
$tests['frontend_pool_10366_us_eligible_with_setting_on_when_not_skipped'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/10366', 'allowed_countries' => 'United States' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '10366' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_true( false !== strpos( wp_json_encode( $offers ), '10366' ), '10366 should remain eligible in US when ON and not skipped.' );
};
$tests['frontend_pool_10366_be_still_excluded_by_country_with_setting_on'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/10366', 'allowed_countries' => 'United States' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '10366' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    tmw_assert_true( false === strpos( wp_json_encode( $offers ), '10366' ), '10366 should remain excluded for Belgium by country targeting.' );
};
$tests['frontend_pool_final_url_override_priority_unchanged_with_setting_on'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '7401' => array( 'id' => '7401', 'name' => 'Priority Offer', 'status' => 'active', 'payout_type' => 'PPS', 'tracking_url' => 'https://trk.example.test/tracking' ) ) );
    $repo->save_offer_overrides( array( '7401' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/final-override', 'allowed_countries' => 'US' ) ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '7401' ), 'enforce_skipped_offers_exclusion' => 1, 'cta_url' => 'https://global.example.test/cta' ), array( 'cta_url' => 'https://global.example.test/cta', 'cta_text' => 'CTA' ), 'US', array() );
    $offer_json = wp_json_encode( $offers );
    tmw_assert_true( false !== strpos( $offer_json, 'final-override' ), 'final_url_override should remain the selected frontend CTA target.' );
};
$tests['frontend_pool_unavailable_account_9647_9781_still_excluded_with_setting_on'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        '9647' => array( 'id' => '9647', 'name' => 'Group Fallback - Tapyn - PPS - Mobile - Android', 'status' => 'active', 'payout_type' => 'PPS' ),
        '9781' => array( 'id' => '9781', 'name' => 'Group Fallback - Dating.com PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '9647', '9781' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    $json = wp_json_encode( $offers );
    tmw_assert_true( false === strpos( $json, '9647' ) && false === strpos( $json, '9781' ), 'Unavailable account offers must remain excluded when ON.' );
};
$tests['skipped_exclusion_checkbox_renders_on_slot_setup_unchecked_by_default'] = function() {
    tmw_reset_test_state(); $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers','meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'enforce_skipped_offers_exclusion', $html, 'Checkbox should render.' );
    tmw_assert_true( false === strpos( $html, 'enforce_skipped_offers_exclusion" value="1" checked' ), 'Default should be unchecked.' );
};
$tests['skipped_exclusion_checkbox_renders_checked_when_enabled'] = function() {
    tmw_reset_test_state();
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers','meta' ), 'sidebar' );
    $saved = $page->sanitize_settings( array( 'enforce_skipped_offers_exclusion' => 1 ) );
    tmw_assert_same( 1, (int) $saved['enforce_skipped_offers_exclusion'], 'Enabled should sanitize to 1.' );
};
$tests['skipped_exclusion_does_not_readd_removed_standalone_import_sections'] = function() { tmw_reset_test_state(); $_GET=array('tab'=>'slot-setup'); $p=new TMW_Test_Admin_Page(TMW_CR_Slot_Sidebar_Banner::OPTION_KEY,new TMW_CR_Slot_Offer_Repository('offers','meta'),'sidebar'); ob_start(); $p->render_page(); $h=(string)ob_get_clean(); tmw_assert_true(false===strpos($h,'<h3>Import Allowed Country Overrides</h3>') && false===strpos($h,'<h3>Import Final URL Overrides</h3>'),'Standalone imports must remain hidden.'); };
$tests['skipped_exclusion_does_not_break_slot_setup_pagination'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup', 'manual_audit_page' => '2', 'pps_audit_page' => '3' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'manual_audit_page', $html, 'Manual audit pagination controls should still render.' );
    tmw_assert_contains( 'tab=slot-setup', $html, 'Slot setup pagination links should retain slot-setup tab context.' );
};
$tests['skipped_exclusion_does_not_break_offers_dashboard_columns'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'offers' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start(); $page->render_page(); $html = (string) ob_get_clean();
    tmw_assert_contains( 'Logo source', $html, 'Offers tab should keep Logo source column.' );
    tmw_assert_contains( 'Frontend eligible', $html, 'Offers tab should keep Frontend eligible column.' );
    tmw_assert_contains( 'Block reason', $html, 'Offers tab should keep Block reason column.' );
};
$tests['skipped_exclusion_dashboard_helper_marks_skipped_when_setting_on'] = function() {
    tmw_reset_test_state(); $repo=new TMW_CR_Slot_Offer_Repository('offers','meta','overrides');
    $offer=array('id'=>'7301','name'=>'Dash Skip','status'=>'active','payout_type'=>'PPS');
    $repo->save_offer_overrides(array('7301'=>array('enabled'=>1,'final_url_override'=>'https://trk.example.test/7301','allowed_countries'=>'US')));
    $repo->save_skipped_offers(array(array('offer_id'=>'7301','decision'=>'skip','reason'=>'dash')));
    $summary=$repo->get_offer_frontend_eligibility_summary($offer,array('allowed_offer_types'=>array('pps'),'enforce_skipped_offers_exclusion'=>1),'US',array());
    tmw_assert_same('skipped_offer',(string)$summary['block_reason'],'Skipped should block when ON.');
};
$tests['skipped_exclusion_dashboard_helper_ignores_skipped_when_setting_off'] = function() {
    tmw_reset_test_state(); $repo=new TMW_CR_Slot_Offer_Repository('offers','meta','overrides');
    $offer=array('id'=>'7302','name'=>'Dash Keep','status'=>'active','payout_type'=>'PPS');
    $repo->save_offer_overrides(array('7302'=>array('enabled'=>1,'final_url_override'=>'https://trk.example.test/7302','allowed_countries'=>'US')));
    $repo->save_skipped_offers(array(array('offer_id'=>'7302','decision'=>'skip','reason'=>'dash')));
    $summary=$repo->get_offer_frontend_eligibility_summary($offer,array('allowed_offer_types'=>array('pps'),'enforce_skipped_offers_exclusion'=>0),'US',array());
    tmw_assert_true('skipped_offer' !== (string)$summary['block_reason'],'Skipped should not block when OFF.');
};


$tests['frontend_pool_does_not_exclude_legacy_invalid_decision_when_setting_on'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '7601' => array( 'id' => '7601', 'name' => 'Legacy Invalid Decision', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '7601' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/7601', 'allowed_countries' => 'US' ) ) );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array(
        '7601' => array( 'decision' => 'unknown', 'reason' => 'legacy-invalid', 'notes' => 'secret-note' ),
    ) );
    $logs = tmw_capture_error_log( static function () use ( $repo ) {
        $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( '7601' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    } );
    tmw_assert_true( false === strpos( $logs, '[TMW-BANNER-SKIP] skipped_offer_excluded offer_id=7601' ), 'Invalid legacy decision should not emit skip exclusion log.' );
    tmw_assert_true( false === strpos( $logs, 'secret-note' ), 'Notes must never appear in logs.' );
};
$tests['skipped_exclusion_does_not_log_type_disallowed_selected_offer'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( 'x-soi' => array( 'id' => 'x-soi', 'name' => 'SOI Offer', 'status' => 'active', 'payout_type' => 'SOI' ), 'safe-pps' => array( 'id' => 'safe-pps', 'name' => 'Safe PPS', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array( 'x-soi' => array( 'offer_id' => 'x-soi', 'decision' => 'skip', 'reason' => 'type disallowed', 'notes' => 'do not log' ) ) );
    $logs = tmw_capture_error_log( static function () use ( $repo ) {
        $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps' ), 'slot_offer_ids' => array( 'safe-pps', 'x-soi' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => 'https://base.test', 'cta_text' => 'CTA' ), 'US', array() );
    } );
    tmw_assert_true( false === strpos( $logs, '[TMW-BANNER-SKIP] skipped_offer_excluded offer_id=x-soi' ), 'Type-disallowed selected offer should not emit skipped exclusion log.' );
};
$tests['skipped_exclusion_logs_every_candidate_path'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        's1' => array( 'id' => 's1', 'name' => 'S1', 'status' => 'active', 'payout_type' => 'PPS' ),
        's2' => array( 'id' => 's2', 'name' => 'S2', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $repo->save_offer_overrides( array( '8780' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/8780', 'allowed_countries' => 'US' ) ) );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array(
        's1' => array( 'offer_id' => 's1', 'decision' => 'skip', 'reason' => 'selected', 'notes' => 'secret-a' ),
        's2' => array( 'offer_id' => 's2', 'decision' => 'skip', 'reason' => 'fallback', 'notes' => 'secret-b' ),
        '8780' => array( 'offer_id' => '8780', 'decision' => 'skip', 'reason' => 'override', 'notes' => 'secret-c' ),
        'legacy-1' => array( 'offer_id' => 'legacy-1', 'decision' => 'skip', 'reason' => 'legacy', 'notes' => 'secret-d' ),
    ) );
    $legacy = array( array( 'id' => 'legacy-1', 'name' => 'Legacy One', 'cta_url' => 'https://trk.example.test/legacy', 'image_url' => 'https://img.test/legacy.jpg' ) );
    $logs = tmw_capture_error_log( static function () use ( $repo, $legacy ) {
        $repo->get_frontend_slot_offers( 'sidebar', array( 'allowed_offer_types' => array( 'pps', 'fallback' ), 'slot_offer_ids' => array( 's1' ), 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', $legacy );
    } );
    foreach ( array( 'offer_id=s1', 'offer_id=s2', 'offer_id=8780', 'offer_id=legacy-1' ) as $needle ) {
        tmw_assert_true( false !== strpos( $logs, $needle ), 'Missing skipped log for ' . $needle );
    }
    tmw_assert_true( false !== strpos( $logs, '[TMW-BANNER-SKIP] frontend_pool_summary' ), 'Expected frontend_pool_summary log when enforcement is ON.' );
    tmw_assert_true( false === strpos( $logs, 'secret-' ), 'Notes must never be logged.' );
};
$failures = array();
$passes   = 0;

$tests['frontend_pool_8780_be_eligible_after_regex_extension'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '8780' => array( 'id' => '8780', 'name' => 'Jerkmate - PPS', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '8780' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/8780', 'allowed_countries' => 'Belgium' ) ) );

    $offers = $repo->get_frontend_slot_offers( 'sidebar', array(), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    tmw_assert_true( false !== strpos( wp_json_encode( $offers ), '8780' ), '8780 must remain eligible for Belgium under default PPS allowlist.' );
};

$tests['frontend_pool_10366_us_eligible_after_regex_extension'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm - PPS', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/10366', 'allowed_countries' => 'United States' ) ) );

    $offers = $repo->get_frontend_slot_offers( 'sidebar', array(), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    tmw_assert_true( false !== strpos( wp_json_encode( $offers ), '10366' ), '10366 must remain eligible for United States under default PPS allowlist.' );
};

$tests['frontend_pool_10366_be_still_excluded_after_regex_extension'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array( '10366' => array( 'id' => '10366', 'name' => 'NaughtyCharm - PPS', 'status' => 'active', 'payout_type' => 'PPS' ) ) );
    $repo->save_offer_overrides( array( '10366' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/10366', 'allowed_countries' => 'United States' ) ) );

    $offers = $repo->get_frontend_slot_offers( 'sidebar', array(), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'Belgium', array() );
    tmw_assert_true( false === strpos( wp_json_encode( $offers ), '10366' ), '10366 must remain excluded for Belgium.' );
};

$tests['unavailable_account_9647_9781_still_excluded_after_regex_extension'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        '9647' => array( 'id' => '9647', 'name' => 'Group Fallback - Tapyn - PPS - Mobile - Android', 'status' => 'active', 'payout_type' => 'PPS' ),
        '9781' => array( 'id' => '9781', 'name' => 'Group Fallback - Dating.com PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $repo->save_offer_overrides( array(
        '9647' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/9647', 'allowed_countries' => 'United States' ),
        '9781' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/9781', 'allowed_countries' => 'United States' ),
    ) );

    $offers = $repo->get_frontend_slot_offers( 'sidebar', array(), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'United States', array() );
    $json = wp_json_encode( $offers );
    tmw_assert_true( false === strpos( $json, '9647' ) && false === strpos( $json, '9781' ), 'Unavailable account offers 9647/9781 must remain excluded.' );
};

$tests['skipped_exclusion_contract_unchanged_after_type_metadata_extension'] = function() {
    tmw_reset_test_state();
    $repo = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta', 'overrides' );
    $repo->save_synced_offers( array(
        's-skip' => array( 'id' => 's-skip', 'name' => 'Skip Decision - PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
        's-review' => array( 'id' => 's-review', 'name' => 'Review Decision - PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
        's-keep' => array( 'id' => 's-keep', 'name' => 'Keep Decision - PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
        's-empty' => array( 'id' => 's-empty', 'name' => 'Empty Decision - PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
        's-unknown' => array( 'id' => 's-unknown', 'name' => 'Unknown Decision - PPS', 'status' => 'active', 'payout_type' => 'PPS' ),
    ) );
    $repo->save_offer_overrides( array(
        's-skip' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/s-skip', 'allowed_countries' => 'US' ),
        's-review' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/s-review', 'allowed_countries' => 'US' ),
        's-keep' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/s-keep', 'allowed_countries' => 'US' ),
        's-empty' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/s-empty', 'allowed_countries' => 'US' ),
        's-unknown' => array( 'enabled' => 1, 'final_url_override' => 'https://trk.example.test/s-unknown', 'allowed_countries' => 'US' ),
    ) );
    update_option( 'tmw_cr_slot_banner_skipped_offers', array(
        's-skip' => array( 'offer_id' => 's-skip', 'decision' => 'skip', 'reason' => 'skip', 'notes' => 'secret-note' ),
        's-review' => array( 'offer_id' => 's-review', 'decision' => 'review_later', 'reason' => 'review', 'notes' => 'secret-note' ),
        's-keep' => array( 'offer_id' => 's-keep', 'decision' => 'keep', 'reason' => 'keep', 'notes' => 'secret-note' ),
        's-empty' => array( 'offer_id' => 's-empty', 'decision' => '', 'reason' => 'empty', 'notes' => 'secret-note' ),
        's-unknown' => array( 'offer_id' => 's-unknown', 'decision' => 'unknown', 'reason' => 'unknown', 'notes' => 'secret-note' ),
    ) );

    $off_offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'enforce_skipped_offers_exclusion' => 0 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
    $off_json = wp_json_encode( $off_offers );
    tmw_assert_true( false !== strpos( $off_json, 's-skip' ), 'With OFF, skipped store should not affect frontend eligibility.' );

    $logs = tmw_capture_error_log( static function () use ( $repo ) {
        $on_offers = $repo->get_frontend_slot_offers( 'sidebar', array( 'enforce_skipped_offers_exclusion' => 1 ), array( 'cta_url' => '', 'cta_text' => 'CTA' ), 'US', array() );
        $json = wp_json_encode( $on_offers );
        tmw_assert_true( false === strpos( $json, 's-skip' ), 'With ON, decision=skip must hard-exclude.' );
        tmw_assert_true( false !== strpos( $json, 's-review' ), 'With ON, review_later must not hard-exclude.' );
        tmw_assert_true( false !== strpos( $json, 's-keep' ), 'With ON, keep must not hard-exclude.' );
        tmw_assert_true( false !== strpos( $json, 's-empty' ), 'With ON, empty decision must not hard-exclude.' );
        tmw_assert_true( false !== strpos( $json, 's-unknown' ), 'With ON, unknown decision must not hard-exclude.' );
    } );
    tmw_assert_true( false === strpos( $logs, 'secret-note' ), 'Notes must never be logged.' );
};

$tests['slot_setup_cpi_cpm_options_render_unchecked_by_default'] = function() {
    tmw_reset_test_state();
    $_GET = array( 'tab' => 'slot-setup' );
    $page = new TMW_Test_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' ), 'sidebar' );
    ob_start();
    $page->render_page();
    $html = (string) ob_get_clean();

    tmw_assert_contains( 'value="cpi"', $html, 'CPI option should render in Slot Setup.' );
    tmw_assert_contains( 'value="cpm"', $html, 'CPM option should render in Slot Setup.' );
    tmw_assert_true( false === strpos( $html, 'value="cpi" checked' ), 'CPI should be unchecked by default.' );
    tmw_assert_true( false === strpos( $html, 'value="cpm" checked' ), 'CPM should be unchecked by default.' );
    tmw_assert_true( false !== strpos( $html, 'value="pps" checked' ), 'PPS should remain checked by default.' );
};


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

