<?php
require_once __DIR__ . '/bootstrap.php';

class TMW_CR_Slot_Sidebar_Banner {
    const OPTION_KEY = 'tmw_cr_slot_banner_settings';

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
        );

        return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
    }

    public static function asset_url( $relative_path ) {
        return 'https://example.test/plugins/tmw/' . ltrim( $relative_path, '/' );
    }
}

$tests = array();

$tests['sanitize_settings_preserves_blank_api_key'] = function() {
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
    tmw_assert_same( array( '12', '34' ), $sanitized['slot_offer_ids'], 'Selected offers should be preserved.' );
    tmw_assert_same( 'sub_id', $sanitized['subid_param'], 'Sub ID param should be sanitized.' );
};

$tests['api_client_masks_key_and_uses_findall'] = function() {
    $GLOBALS['tmw_test_remote_get'] = function( $url ) {
        tmw_assert_contains( 'Target=Affiliate_Offer', $url, 'Request should target Affiliate_Offer.' );
        tmw_assert_contains( 'Method=findAll', $url, 'Request should call findAll.' );
        tmw_assert_contains( 'id', $url, 'Request should include requested offer fields.' );

        return array(
            'response' => array( 'code' => 200 ),
            'body'     => wp_json_encode( array( 'response' => array( 'data' => array( array( 'id' => '1', 'name' => 'Offer A' ) ) ) ) ),
        );
    };

    $client = new TMW_CR_Slot_CR_API_Client( 'abcd1234secret' );
    tmw_assert_same( '**********cret', $client->get_masked_api_key(), 'Masked key should hide all but last four characters.' );
    $response = $client->test_connection();
    tmw_assert_true( ! is_wp_error( $response ), 'Connection test should succeed.' );
};

$tests['offer_sync_stores_normalized_offers'] = function() {
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
                                array(
                                    'id' => '10',
                                    'name' => 'Offer Ten',
                                    'description' => 'Desc',
                                    'preview_url' => 'https://preview.test/10',
                                    'status' => 'active',
                                    'default_payout' => '50.00',
                                    'percent_payout' => '25.00',
                                    'payout_type' => 'cpa_both',
                                    'require_approval' => '1',
                                    'featured' => '2026-04-20 00:00:00',
                                    'currency' => 'USD',
                                ),
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

    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $client = new TMW_CR_Slot_CR_API_Client( 'sync-key' );
    $result = TMW_CR_Slot_Offer_Sync_Service::sync_all( $client, $repository );

    tmw_assert_true( ! is_wp_error( $result ), 'Sync should succeed.' );
    $offers = $repository->get_synced_offers();
    tmw_assert_same( 'Offer Ten', $offers['10']['name'], 'Offer name should be stored.' );
    tmw_assert_same( '1', $offers['10']['require_approval'], 'Approval flag should be normalized.' );
    tmw_assert_true( true === $offers['10']['is_featured'], 'Featured should be normalized to a boolean flag.' );
};

$tests['render_page_populates_synced_offer_table'] = function() {
    update_option(
        TMW_CR_Slot_Sidebar_Banner::OPTION_KEY,
        array(
            'cr_api_key' => 'render-key',
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

    $page = new TMW_CR_Slot_Admin_Page( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, $repository, 'sidebar' );

    ob_start();
    $page->render_page();
    $html = ob_get_clean();

    tmw_assert_contains( 'Offer Ten', $html, 'Admin page should include synced offer names.' );
    tmw_assert_contains( 'https://img.test/10.png', $html, 'Admin page should include image override field values.' );
    tmw_assert_contains( 'Test Connection', $html, 'Admin page should include connection button.' );
};

$tests['sync_failure_updates_meta_without_overwriting_existing_offers'] = function() {
    $repository = new TMW_CR_Slot_Offer_Repository( 'offers', 'meta' );
    $repository->save_synced_offers( array( 'existing' => array( 'id' => 'existing', 'name' => 'Existing Offer' ) ) );

    $GLOBALS['tmw_test_remote_get'] = function() {
        return new WP_Error( 'offline', 'Offline' );
    };

    $client = new TMW_CR_Slot_CR_API_Client( 'bad-key' );
    $result = TMW_CR_Slot_Offer_Sync_Service::sync_all( $client, $repository );

    tmw_assert_true( is_wp_error( $result ), 'Sync should fail when the API is unavailable.' );
    $offers = $repository->get_synced_offers();
    $meta   = $repository->get_sync_meta();
    tmw_assert_same( 'Existing Offer', $offers['existing']['name'], 'Existing offers should remain when sync fails.' );
    tmw_assert_same( 'CrakRevenue API request failed.', $meta['last_error'], 'Failure reason should be stored in sync meta.' );
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
