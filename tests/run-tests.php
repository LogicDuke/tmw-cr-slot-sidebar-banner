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
    tmw_assert_contains( 'Slot Setup', $html, 'Dashboard should render Slot Setup tab.' );
    tmw_assert_contains( 'Settings', $html, 'Dashboard should render Settings tab.' );
    tmw_assert_contains( 'Last raw/imported/skipped', $html, 'Overview cards should render sync count summary.' );
    tmw_assert_true( false === strpos( $html, 'hidden-api-key' ), 'Overview should never leak API key.' );
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
