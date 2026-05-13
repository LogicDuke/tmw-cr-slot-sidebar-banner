<?php
/**
 * Admin settings page for the TMW CR Slot Sidebar Banner plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Admin_Page {
    /** @var string */
    protected $option_key;

    /** @var TMW_CR_Slot_Offer_Repository */
    protected $offer_repository;

    /** @var string */
    protected $slot_key;

    /**
     * @param string                       $option_key Option key.
     * @param TMW_CR_Slot_Offer_Repository $offer_repository Offer repository.
     * @param string                       $slot_key Slot key.
     */
    public function __construct( $option_key, $offer_repository, $slot_key ) {
        $this->option_key       = $option_key;
        $this->offer_repository = $offer_repository;
        $this->slot_key         = $slot_key;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_test_connection', array( $this, 'handle_test_connection' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_sync_offers', array( $this, 'handle_sync_offers' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_sync_stats', array( $this, 'handle_sync_stats' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_import_final_url_overrides', array( $this, 'handle_import_final_url_overrides' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_import_allowed_country_overrides', array( $this, 'handle_import_allowed_country_overrides' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_import_both_overrides', array( $this, 'handle_import_both_overrides' ) );
        add_action( 'admin_post_tmw_cr_slot_import_skipped_offers', array( $this, 'handle_import_skipped_offers' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
    }

    /**
     * @return void
     */
    public function register_menu() {
        add_options_page(
            __( 'TMW CR Slot Banner', 'tmw-cr-slot-sidebar-banner' ),
            __( 'TMW Slot Banner', 'tmw-cr-slot-sidebar-banner' ),
            'manage_options',
            'tmw-cr-slot-sidebar-banner',
            array( $this, 'render_page' )
        );
    }

    /**
     * @return void
     */
    public function register_settings() {
        register_setting( 'tmw_cr_slot_banner', $this->option_key, array( $this, 'sanitize_settings' ) );
    }

    /**
     * @param string $hook_suffix Current admin page hook.
     *
     * @return void
     */
    public function enqueue_dashboard_assets( $hook_suffix ) {
        if ( 'settings_page_tmw-cr-slot-sidebar-banner' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'tmw-cr-slot-admin-dashboard',
            TMW_CR_Slot_Sidebar_Banner::asset_url( 'assets/css/admin-dashboard.css' ),
            array(),
            TMW_CR_SLOT_BANNER_VERSION
        );
        wp_enqueue_script(
            'tmw-cr-slot-admin-dashboard',
            TMW_CR_Slot_Sidebar_Banner::asset_url( 'assets/js/admin-dashboard.js' ),
            array(),
            TMW_CR_SLOT_BANNER_VERSION,
            true
        );
    }

    /**
     * @param array<string,mixed> $input Raw settings.
     *
     * @return array<string,mixed>
     */
    public function sanitize_settings( $input ) {
        $input    = (array) $input;
        $existing = TMW_CR_Slot_Sidebar_Banner::get_settings();
        $output   = array();

        $output['headline']              = isset( $input['headline'] ) ? sanitize_text_field( $input['headline'] ) : '';
        $output['subheadline']           = isset( $input['subheadline'] ) ? sanitize_text_field( $input['subheadline'] ) : '';
        $output['spin_button_text']      = isset( $input['spin_button_text'] ) ? sanitize_text_field( $input['spin_button_text'] ) : '';
        $output['cta_text']              = isset( $input['cta_text'] ) ? sanitize_text_field( $input['cta_text'] ) : '';
        $output['cta_url']               = isset( $input['cta_url'] ) ? esc_url_raw( $input['cta_url'] ) : '';
        $output['default_image_url']     = isset( $input['default_image_url'] ) ? esc_url_raw( $input['default_image_url'] ) : '';
        $output['subid_param']           = isset( $input['subid_param'] ) ? sanitize_key( $input['subid_param'] ) : '';
        $output['subid_value']           = isset( $input['subid_value'] ) ? sanitize_text_field( $input['subid_value'] ) : '';
        $output['open_in_new_tab']       = ! empty( $input['open_in_new_tab'] ) ? 1 : 0;
        $output['country_overrides_raw'] = isset( $input['country_overrides_raw'] ) ? sanitize_textarea_field( $input['country_overrides_raw'] ) : '';
        $output['rotation_mode']         = isset( $input['rotation_mode'] ) ? sanitize_key( (string) $input['rotation_mode'] ) : 'manual';
        if ( ! in_array( $output['rotation_mode'], array( 'manual', 'payout_desc', 'conversions_desc', 'epc_desc', 'country_epc_desc', 'hybrid_score', 'safe_hybrid_score' ), true ) ) {
            $output['rotation_mode'] = 'manual';
        }
        $output['optimization_enabled'] = ! empty( $input['optimization_enabled'] ) ? 1 : 0;
        $output['minimum_clicks_threshold'] = isset( $input['minimum_clicks_threshold'] ) ? max( 0, (int) $input['minimum_clicks_threshold'] ) : 10;
        $output['minimum_conversions_threshold'] = isset( $input['minimum_conversions_threshold'] ) ? max( 0, (float) $input['minimum_conversions_threshold'] ) : 1;
        $output['minimum_payout_threshold'] = isset( $input['minimum_payout_threshold'] ) ? max( 0, (float) $input['minimum_payout_threshold'] ) : 0;
        $output['exclude_zero_click_offers'] = ! empty( $input['exclude_zero_click_offers'] ) ? 1 : 0;
        $output['exclude_zero_conversion_offers'] = ! empty( $input['exclude_zero_conversion_offers'] ) ? 1 : 0;
        $output['country_decay_enabled'] = ! empty( $input['country_decay_enabled'] ) ? 1 : 0;
        $output['country_weight'] = isset( $input['country_weight'] ) ? max( 0, min( 1, (float) $input['country_weight'] ) ) : 0.7;
        $output['global_weight'] = isset( $input['global_weight'] ) ? max( 0, min( 1, (float) $input['global_weight'] ) ) : 0.3;
        $output['decay_min_country_clicks'] = isset( $input['decay_min_country_clicks'] ) ? max( 1, (int) $input['decay_min_country_clicks'] ) : 10;
        $output['fallback_to_global_when_low_sample'] = ! empty( $input['fallback_to_global_when_low_sample'] ) ? 1 : 0;
        $output['auto_sync_enabled'] = ! empty( $input['auto_sync_enabled'] ) ? 1 : 0;
        $output['auto_sync_frequency'] = isset( $input['auto_sync_frequency'] ) ? sanitize_key( (string) $input['auto_sync_frequency'] ) : 'daily';
        if ( ! in_array( $output['auto_sync_frequency'], array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
            $output['auto_sync_frequency'] = 'daily';
        }
        $output['stats_sync_range']      = isset( $input['stats_sync_range'] ) ? sanitize_key( (string) $input['stats_sync_range'] ) : '30d';
        if ( ! in_array( $output['stats_sync_range'], array( '7d', '30d', '90d' ), true ) ) {
            $output['stats_sync_range'] = '30d';
        }
        $output['optimization_notes'] = isset( $input['optimization_notes'] ) ? sanitize_textarea_field( (string) $input['optimization_notes'] ) : '';
        $output['allowed_offer_types'] = TMW_CR_Slot_Offer_Repository::sanitize_allowed_offer_types(
            isset( $input['allowed_offer_types'] ) ? $input['allowed_offer_types'] : array()
        );

        $output['enforce_skipped_offers_exclusion'] = ! empty( $input['enforce_skipped_offers_exclusion'] ) ? 1 : 0;

        $api_key              = isset( $input['cr_api_key'] ) ? trim( (string) $input['cr_api_key'] ) : '';
        $output['cr_api_key'] = '' !== $api_key ? sanitize_text_field( $api_key ) : (string) $existing['cr_api_key'];

        $output['slot_offer_ids'] = array();
        if ( isset( $input['slot_offer_ids'] ) && is_array( $input['slot_offer_ids'] ) ) {
            foreach ( $input['slot_offer_ids'] as $offer_id ) {
                $offer_id = sanitize_text_field( (string) $offer_id );
                if ( '' !== $offer_id ) {
                    $output['slot_offer_ids'][] = $offer_id;
                }
            }
        }

        $output['slot_offer_priority'] = array();
        if ( isset( $input['slot_offer_priority'] ) && is_array( $input['slot_offer_priority'] ) ) {
            foreach ( $input['slot_offer_priority'] as $offer_id => $priority ) {
                $offer_id = sanitize_text_field( (string) $offer_id );
                if ( '' === $offer_id ) {
                    continue;
                }

                $output['slot_offer_priority'][ $offer_id ] = max( 0, (int) $priority );
            }
        }

        $output['offer_image_overrides'] = array();
        if ( isset( $input['offer_image_overrides'] ) && is_array( $input['offer_image_overrides'] ) ) {
            foreach ( $input['offer_image_overrides'] as $offer_id => $image_url ) {
                $offer_id = sanitize_text_field( (string) $offer_id );
                if ( '' === $offer_id ) {
                    continue;
                }

                $output['offer_image_overrides'][ $offer_id ] = esc_url_raw( (string) $image_url );
            }
        }

        $offer_overrides = $this->offer_repository->get_offer_overrides();
        if ( ! is_array( $offer_overrides ) ) {
            $offer_overrides = array();
        }

        if ( isset( $input['offer_overrides'] ) && is_array( $input['offer_overrides'] ) ) {
            foreach ( $input['offer_overrides'] as $offer_id => $override ) {
                $offer_id = sanitize_text_field( (string) $offer_id );
                if ( '' === $offer_id || ! is_array( $override ) ) {
                    continue;
                }

                $existing_override = isset( $offer_overrides[ $offer_id ] ) && is_array( $offer_overrides[ $offer_id ] ) ? $offer_overrides[ $offer_id ] : array();
                $offer_overrides[ $offer_id ] = array_merge(
                    $existing_override,
                    array(
                        'enabled'            => ! empty( $override['enabled'] ) ? 1 : 0,
                        'final_url_override' => isset( $override['final_url_override'] ) ? esc_url_raw( (string) $override['final_url_override'] ) : (string) ( $existing_override['final_url_override'] ?? '' ),
                        'image_url_override' => isset( $override['image_url_override'] ) ? esc_url_raw( (string) $override['image_url_override'] ) : '',
                        'custom_cta_text'    => isset( $override['custom_cta_text'] ) ? sanitize_text_field( (string) $override['custom_cta_text'] ) : '',
                        'label_override'     => isset( $override['label_override'] ) ? sanitize_text_field( (string) $override['label_override'] ) : '',
                        'allowed_countries'  => isset( $override['allowed_countries'] ) ? sanitize_text_field( (string) $override['allowed_countries'] ) : '',
                        'blocked_countries'  => isset( $override['blocked_countries'] ) ? sanitize_text_field( (string) $override['blocked_countries'] ) : '',
                        'notes'              => isset( $override['notes'] ) ? sanitize_textarea_field( (string) $override['notes'] ) : '',
                        'dashboard_tags'     => isset( $override['dashboard_tags'] ) ? sanitize_text_field( (string) $override['dashboard_tags'] ) : '',
                        'dashboard_vertical' => isset( $override['dashboard_vertical'] ) ? sanitize_text_field( (string) $override['dashboard_vertical'] ) : '',
                        'dashboard_performs_in' => isset( $override['dashboard_performs_in'] ) ? sanitize_text_field( (string) $override['dashboard_performs_in'] ) : '',
                        'dashboard_optimized_for' => isset( $override['dashboard_optimized_for'] ) ? sanitize_text_field( (string) $override['dashboard_optimized_for'] ) : '',
                        'dashboard_accepted_countries' => isset( $override['dashboard_accepted_countries'] ) ? sanitize_text_field( (string) $override['dashboard_accepted_countries'] ) : '',
                        'dashboard_niche'    => isset( $override['dashboard_niche'] ) ? sanitize_text_field( (string) $override['dashboard_niche'] ) : '',
                        'dashboard_promotion_method' => isset( $override['dashboard_promotion_method'] ) ? sanitize_text_field( (string) $override['dashboard_promotion_method'] ) : '',
                    )
                );
            }
        }

        
        if ( isset( $input['skipped_offers_csv'] ) ) {
            $this->offer_repository->import_skipped_offers_csv( (string) $input['skipped_offers_csv'] );
        }
        $output['skipped_offers_csv'] = '';

        $this->offer_repository->save_offer_overrides( $offer_overrides );
        $offers = $this->offer_repository->get_synced_offers();
        $type_allowed_count = 0;
        foreach ( $offers as $offer ) {
            if ( is_array( $offer ) && $this->offer_repository->is_offer_type_allowed( $offer, $output ) ) {
                ++$type_allowed_count;
            }
        }
        error_log(
            sprintf(
                '[TMW-BANNER-TYPE] settings_saved allowed_types=%s total_offers=%d type_allowed_count=%d',
                implode( ',', (array) $output['allowed_offer_types'] ),
                count( $offers ),
                $type_allowed_count
            )
        );

        return $output;
    }

    /**
     * @return void
     */
    public function handle_test_connection() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_test_connection' );

        $client = $this->build_api_client();
        $result = $client->test_connection();

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'error', $result->get_error_message() );
        }

        $rows           = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $result );
        $response_shape = TMW_CR_Slot_Offer_Sync_Service::detect_response_shape( $result );
        $raw_count      = count( $rows );
        $importable     = 0;

        foreach ( $rows as $row ) {
            $normalized = TMW_CR_Slot_Offer_Sync_Service::normalize_offer( $row );
            if ( '' !== (string) $normalized['id'] ) {
                ++$importable;
            }
        }

        if ( $raw_count > 0 && 0 === $importable ) {
            $this->redirect_with_notice(
                'error',
                sprintf(
                    /* translators: 1: raw rows, 2: response shape */
                    __( '[TMW-CR-API] Connection succeeded (%1$d row(s), shape: %2$s) but rows look non-importable.', 'tmw-cr-slot-sidebar-banner' ),
                    $raw_count,
                    $response_shape
                )
            );
        }

        if ( $raw_count > 0 ) {
            TMW_CR_Slot_Offer_Sync_Service::log_geo_audit_rows( $rows, 'test_connection' );
            $this->redirect_with_notice(
                'success',
                sprintf(
                    /* translators: 1: raw rows, 2: response shape */
                    __( '[TMW-CR-API] Connection successful. Detected %1$d row(s) (shape: %2$s).', 'tmw-cr-slot-sidebar-banner' ),
                    $raw_count,
                    $response_shape
                )
            );
        }

        $this->redirect_with_notice(
            'success',
            sprintf(
                __( '[TMW-CR-API] Connection successful but no rows were returned (shape: %s).', 'tmw-cr-slot-sidebar-banner' ),
                $response_shape
            )
        );
    }

    /**
     * @return void
     */
    public function handle_sync_offers() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_sync_offers' );

        $client = $this->build_api_client();
        $result = TMW_CR_Slot_Offer_Sync_Service::sync_all( $client, $this->offer_repository );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'error', $result->get_error_message() );
        }

        $count          = isset( $result['offer_count'] ) ? (int) $result['offer_count'] : 0;
        $raw_count      = isset( $result['last_raw_row_count'] ) ? (int) $result['last_raw_row_count'] : 0;
        $imported_count = isset( $result['last_imported_count'] ) ? (int) $result['last_imported_count'] : $count;
        $skipped_count  = isset( $result['last_skipped_count'] ) ? (int) $result['last_skipped_count'] : 0;
        $preserved      = ! empty( $result['preserved_previous'] );

        $message = sprintf(
            /* translators: 1: imported count, 2: raw row count, 3: skipped count, 4: stored count */
            __( '[TMW-CR-SYNC] Sync complete. Imported: %1$d, Raw: %2$d, Skipped: %3$d, Stored: %4$d.', 'tmw-cr-slot-sidebar-banner' ),
            $imported_count,
            $raw_count,
            $skipped_count,
            $count
        );

        if ( $preserved ) {
            $message .= ' ' . __( 'Previous synced offers were preserved due to parser soft-failure.', 'tmw-cr-slot-sidebar-banner' );
        }

        $synced_offers = array_values( $this->offer_repository->get_synced_offers() );
        if ( ! empty( $synced_offers ) ) {
            TMW_CR_Slot_Offer_Sync_Service::log_geo_audit_rows( $synced_offers, 'post_sync' );
        }

        $this->redirect_with_notice( 'success', $message );
    }

    /**
     * @return void
     */
    public function handle_sync_stats() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_sync_stats' );

        $settings = TMW_CR_Slot_Sidebar_Banner::get_settings();
        $client   = $this->build_api_client();
        $result   = TMW_CR_Slot_Stats_Sync_Service::sync(
            $client,
            $this->offer_repository,
            array(
                'preset' => isset( $_POST['stats_sync_range'] ) ? sanitize_key( wp_unslash( $_POST['stats_sync_range'] ) ) : (string) $settings['stats_sync_range'],
            )
        );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'error', $result->get_error_message() );
        }

        $raw_rows = (int) ( $result['last_stats_raw_rows'] ?? 0 );
        $imported_rows = (int) ( $result['last_stats_imported_rows'] ?? 0 );
        $date_start = (string) ( $result['last_stats_date_start'] ?? '' );
        $date_end = (string) ( $result['last_stats_date_end'] ?? '' );
        $shape = sanitize_text_field( (string) ( $result['last_stats_response_shape'] ?? 'unknown' ) );
        $preserved = ! empty( $result['preserved_previous'] );

        if ( $raw_rows > 0 && $imported_rows > 0 ) {
            $message = sprintf(
                __( '[TMW-CR-STATS] API success with importable rows. Raw rows: %1$d, Imported rows: %2$d, Range: %3$s to %4$s, Shape: %5$s.', 'tmw-cr-slot-sidebar-banner' ),
                $raw_rows,
                $imported_rows,
                $date_start,
                $date_end,
                $shape
            );
        } elseif ( 0 === $raw_rows ) {
            $message = sprintf(
                __( '[TMW-CR-STATS] API success with no rows returned. Raw rows: %1$d, Imported rows: %2$d, Range: %3$s to %4$s, Shape: %5$s.', 'tmw-cr-slot-sidebar-banner' ),
                $raw_rows,
                $imported_rows,
                $date_start,
                $date_end,
                $shape
            );
        } else {
            $message = sprintf(
                __( '[TMW-CR-STATS] API success but parser mismatch (0 imported). Raw rows: %1$d, Imported rows: %2$d, Range: %3$s to %4$s, Shape: %5$s.', 'tmw-cr-slot-sidebar-banner' ),
                $raw_rows,
                $imported_rows,
                $date_start,
                $date_end,
                $shape
            );
        }

        if ( $preserved ) {
            $message .= ' ' . __( 'Previous local stats were preserved due to parser soft-failure.', 'tmw-cr-slot-sidebar-banner' );
        }

        $this->redirect_with_notice( 'success', $message );
    }

    /**
     * @return void
     */
    public function handle_import_final_url_overrides() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_import_final_url_overrides', 'tmw_legacy_final_url_nonce' );
        $raw_csv = isset( $_POST['final_url_override_csv'] ) ? (string) wp_unslash( $_POST['final_url_override_csv'] ) : '';
        $result = $this->import_final_url_override_rows( $raw_csv );
        $this->redirect_with_notice_to_tab( 'success', sprintf( 'Final URL override import complete. Imported: %1$d, Rejected: %2$d, Total saved overrides: %3$d.', $result['imported'], $result['rejected'], $result['total_saved'] ), 'slot-setup' );
    }

    /**
     * @return void
     */
    public function handle_import_allowed_country_overrides() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_import_allowed_country_overrides', 'tmw_legacy_allowed_country_nonce' );
        $raw_csv   = isset( $_POST['allowed_country_override_csv'] ) ? (string) wp_unslash( $_POST['allowed_country_override_csv'] ) : '';
        $result = $this->import_allowed_country_override_rows( $raw_csv );
        $this->redirect_with_notice_to_tab( 'success', sprintf( 'Allowed country override import complete. Imported: %1$d, Rejected: %2$d, Total saved overrides: %3$d.', $result['imported'], $result['rejected'], $result['total_saved'] ), 'slot-setup' );
    }

    /**
     * @return void
     */
    public function handle_import_both_overrides() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_import_both_overrides' );
        $allowed_csv = isset( $_POST['allowed_country_override_csv'] ) ? (string) wp_unslash( $_POST['allowed_country_override_csv'] ) : '';
        $final_url_csv = isset( $_POST['final_url_override_csv'] ) ? (string) wp_unslash( $_POST['final_url_override_csv'] ) : '';

        if ( '' === trim( $allowed_csv ) && '' === trim( $final_url_csv ) ) {
            $this->redirect_with_notice_to_tab( 'error', 'No override rows were submitted.', 'slot-setup' );
        }

        $allowed_result = $this->import_allowed_country_override_rows( $allowed_csv );
        $final_url_result = $this->import_final_url_override_rows( $final_url_csv );

        error_log(
            sprintf(
                '[TMW-BANNER-OVERRIDE-IMPORT] combined_import country_imported=%1$d country_rejected=%2$d final_url_imported=%3$d final_url_rejected=%4$d',
                $allowed_result['imported'],
                $allowed_result['rejected'],
                $final_url_result['imported'],
                $final_url_result['rejected']
            )
        );

        $this->redirect_with_notice_to_tab(
            'success',
            sprintf(
                'Override import complete. Allowed countries: Imported %1$d, Rejected %2$d, Total saved %3$d. Final URLs: Imported %4$d, Rejected %5$d, Total saved %6$d.',
                $allowed_result['imported'],
                $allowed_result['rejected'],
                $allowed_result['total_saved'],
                $final_url_result['imported'],
                $final_url_result['rejected'],
                $final_url_result['total_saved']
            ),
            'slot-setup'
        );
    }



    /**
     * @return void
     */
    public function handle_import_skipped_offers() {
        $this->assert_admin_action( 'tmw_cr_slot_import_skipped_offers' );
        $raw_csv = isset( $_POST['skipped_offers_csv'] ) ? (string) wp_unslash( $_POST['skipped_offers_csv'] ) : '';
        $result = $this->offer_repository->import_skipped_offers_csv( $raw_csv );

        $this->redirect_with_notice_to_tab(
            'success',
            sprintf( 'Skipped offers import complete. Imported: %1$d, Skipped: %2$d.', (int) ( $result['imported'] ?? 0 ), (int) ( $result['skipped'] ?? 0 ) ),
            'slot-setup'
        );
    }

    protected function import_final_url_override_rows( $raw_csv ) {
        $lines = preg_split( '/\r?\n/', trim( (string) $raw_csv ) );
        $overrides = $this->offer_repository->get_offer_overrides();
        $imported = 0;
        $rejected = 0;

        foreach ( (array) $lines as $idx => $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }
            if ( 0 === $idx && false !== strpos( strtolower( $line ), 'offer_id,final_url_override' ) ) {
                continue;
            }
            $parts = str_getcsv( $line );
            $offer_id = sanitize_text_field( (string) ( $parts[0] ?? '' ) );
            $final_url = esc_url_raw( trim( (string) ( $parts[1] ?? '' ) ) );

            if ( '' === $offer_id || '' === $final_url || ! $this->offer_repository->is_valid_manual_final_url_override( $final_url ) ) {
                ++$rejected;
                error_log( sprintf( '[TMW-BANNER-LINK] manual_final_url_rejected offer_id=%1$s reason=%2$s', $offer_id, '' === $final_url ? 'empty_url' : 'invalid_url' ) );
                continue;
            }

            if ( ! isset( $overrides[ $offer_id ] ) || ! is_array( $overrides[ $offer_id ] ) ) {
                $overrides[ $offer_id ] = array();
            }
            $overrides[ $offer_id ]['final_url_override'] = $final_url;
            ++$imported;
            error_log( sprintf( '[TMW-BANNER-LINK] manual_final_url_imported offer_id=%s', $offer_id ) );
        }

        $this->offer_repository->save_offer_overrides( $overrides );
        $total_saved_overrides = (int) $this->offer_repository->get_manual_override_diagnostics()['manual_final_url_overrides'];
        error_log( sprintf( '[TMW-BANNER-LINK] manual_final_url_import_summary imported=%1$d rejected=%2$d', $imported, $rejected ) );

        return array(
            'imported' => $imported,
            'rejected' => $rejected,
            'total_saved' => $total_saved_overrides,
        );
    }

    protected function import_allowed_country_override_rows( $raw_csv ) {
        $lines = preg_split( '/\r?\n/', trim( (string) $raw_csv ) );
        $overrides = $this->offer_repository->get_offer_overrides();
        $imported = 0;
        $rejected = 0;

        foreach ( (array) $lines as $idx => $line ) {
            $line = trim( (string) $line );
            if ( '' === $line ) {
                continue;
            }
            if ( 0 === $idx && false !== strpos( strtolower( $line ), 'offer_id,allowed_countries' ) ) {
                continue;
            }
            $parts = str_getcsv( $line );
            $offer_id = preg_replace( '/\D+/', '', (string) ( $parts[0] ?? '' ) );
            $countries = array_filter( array_map( 'trim', explode( '|', (string) ( $parts[1] ?? '' ) ) ) );
            $countries = array_values( array_unique( $countries ) );

            if ( '' === $offer_id || empty( $countries ) ) {
                ++$rejected;
                continue;
            }

            if ( ! isset( $overrides[ $offer_id ] ) || ! is_array( $overrides[ $offer_id ] ) ) {
                $overrides[ $offer_id ] = array();
            }
            $overrides[ $offer_id ]['allowed_countries'] = $countries;
            ++$imported;
            error_log( sprintf( '[TMW-BANNER-COUNTRY] allowed_country_imported offer_id=%1$s countries=%2$d', $offer_id, count( $countries ) ) );
        }

        $this->offer_repository->save_offer_overrides( $overrides );
        $total_saved = (int) $this->offer_repository->get_manual_override_diagnostics()['manual_allowed_country_overrides'];
        error_log( sprintf( '[TMW-BANNER-COUNTRY] country_import_summary imported=%1$d rejected=%2$d total_saved=%3$d', $imported, $rejected, $total_saved ) );

        return array(
            'imported' => $imported,
            'rejected' => $rejected,
            'total_saved' => $total_saved,
        );
    }

    /**
     * @return void
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings    = TMW_CR_Slot_Sidebar_Banner::get_settings();
        $sync_meta   = $this->offer_repository->get_sync_meta();
        $summary     = $this->offer_repository->get_dashboard_summary( $settings );
        $notice_type = isset( $_GET['tmw_cr_slot_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['tmw_cr_slot_notice'] ) ) : '';
        $notice_text = isset( $_GET['tmw_cr_slot_message'] ) ? sanitize_text_field( wp_unslash( $_GET['tmw_cr_slot_message'] ) ) : '';
        $client      = $this->build_api_client();

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        if ( ! in_array( $active_tab, array( 'overview', 'offers', 'performance', 'slot-setup', 'settings' ), true ) ) {
            $active_tab = 'overview';
        }
        ?>
        <div class="wrap tmw-cr-slot-banner-dashboard">
            <h1><?php esc_html_e( 'TMW CrakRevenue Slot Operations Dashboard', 'tmw-cr-slot-sidebar-banner' ); ?></h1>
            <p><?php esc_html_e( 'Manage synced offers, slot selection, priorities, image overrides, and sync health at scale.', 'tmw-cr-slot-sidebar-banner' ); ?></p>

            <?php if ( '' !== $notice_text ) : ?>
                <div class="notice notice-<?php echo 'success' === $notice_type ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( $notice_text ); ?></p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $this->get_tabs() as $tab_slug => $tab_label ) : ?>
                    <?php $is_active = $tab_slug === $active_tab; ?>
                    <a href="<?php echo esc_url( $this->build_tab_url( $tab_slug ) ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
                <?php endforeach; ?>
            </h2>

            <?php
            if ( 'overview' === $active_tab ) {
                $this->render_overview_tab( $summary, $sync_meta, $client );
            } elseif ( 'offers' === $active_tab ) {
                $this->render_offers_tab( $settings );
            } elseif ( 'performance' === $active_tab ) {
                $this->render_performance_tab();
            } elseif ( 'slot-setup' === $active_tab ) {
                $this->render_slot_setup_tab( $settings );
            } else {
                $this->render_settings_tab( $settings, $sync_meta, $client );
            }
            ?>
        </div>
        <?php
    }

    /**
     * @return array<string,string>
     */
    protected function get_tabs() {
        return array(
            'overview'   => __( 'Overview', 'tmw-cr-slot-sidebar-banner' ),
            'offers'     => __( 'Offers', 'tmw-cr-slot-sidebar-banner' ),
            'performance'=> __( 'Performance', 'tmw-cr-slot-sidebar-banner' ),
            'slot-setup' => __( 'Slot Setup', 'tmw-cr-slot-sidebar-banner' ),
            'settings'   => __( 'Settings', 'tmw-cr-slot-sidebar-banner' ),
        );
    }

    /**
     * @param string $tab_slug Tab slug.
     * @param array<string,string> $args Optional query args.
     *
     * @return string
     */
    protected function build_tab_url( $tab_slug, $args = array() ) {
        $params = array_merge(
            array(
                'page' => 'tmw-cr-slot-sidebar-banner',
                'tab'  => $tab_slug,
            ),
            $args
        );

        return add_query_arg( $params, admin_url( 'options-general.php' ) );
    }

    /**
     * @param array<string,mixed> $summary Summary data.
     * @param array<string,mixed> $sync_meta Sync meta.
     * @param TMW_CR_Slot_CR_API_Client $client Client.
     *
     * @return void
     */
    protected function render_overview_tab( $summary, $sync_meta, $client ) {
        ?>
        <div class="tmw-cr-sync-health notice <?php echo ! empty( $summary['last_soft_failure'] ) ? 'notice-warning' : 'notice-success'; ?>">
            <p>
                <?php if ( ! empty( $summary['last_soft_failure'] ) ) : ?>
                    <?php esc_html_e( '[TMW-CR-DASH] Last sync recorded a parser soft-failure. Previously stored offers were preserved.', 'tmw-cr-slot-sidebar-banner' ); ?>
                <?php else : ?>
                    <?php esc_html_e( '[TMW-CR-DASH] Sync health is stable. No parser soft-failure on the last sync run.', 'tmw-cr-slot-sidebar-banner' ); ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="tmw-cr-card-grid">
            <?php $this->render_summary_card( __( 'Stored offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['stored_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Selected slot offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['selected_slot_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Active synced offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['active_synced_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Featured synced offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['featured_synced_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Offers requiring approval', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['approval_required_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Offers with manual image overrides', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['manual_image_overrides'] ); ?>
            <?php $this->render_summary_card( __( 'Last sync time', 'tmw-cr-slot-sidebar-banner' ), ! empty( $summary['last_sync_time'] ) ? (string) $summary['last_sync_time'] : __( 'Never', 'tmw-cr-slot-sidebar-banner' ) ); ?>
            <?php $this->render_summary_card( __( 'Last raw/imported/skipped', 'tmw-cr-slot-sidebar-banner' ), sprintf( '%d / %d / %d', (int) $summary['last_raw_row_count'], (int) $summary['last_imported_count'], (int) $summary['last_skipped_count'] ) ); ?>
            <?php $this->render_summary_card( __( 'Last soft-failure', 'tmw-cr-slot-sidebar-banner' ), ! empty( $summary['last_soft_failure'] ) ? __( 'Yes', 'tmw-cr-slot-sidebar-banner' ) : __( 'No', 'tmw-cr-slot-sidebar-banner' ) ); ?>
            <?php $this->render_summary_card( __( 'Total clicks (stats)', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['total_clicks'] ); ?>
            <?php $this->render_summary_card( __( 'Total conversions (stats)', 'tmw-cr-slot-sidebar-banner' ), (string) (float) $summary['total_conversions'] ); ?>
            <?php $this->render_summary_card( __( 'Total payout (stats)', 'tmw-cr-slot-sidebar-banner' ), (string) round( (float) $summary['total_payout'], 2 ) ); ?>
            <?php $this->render_summary_card( __( 'Top performing offer', 'tmw-cr-slot-sidebar-banner' ), ! empty( $summary['top_offer_name'] ) ? (string) $summary['top_offer_name'] : __( 'N/A', 'tmw-cr-slot-sidebar-banner' ) ); ?>
            <?php $this->render_summary_card( __( 'Top performing country', 'tmw-cr-slot-sidebar-banner' ), ! empty( $summary['top_country_name'] ) ? (string) $summary['top_country_name'] : __( 'N/A', 'tmw-cr-slot-sidebar-banner' ) ); ?>
            <?php $this->render_summary_card( __( 'Stats freshness', 'tmw-cr-slot-sidebar-banner' ), ! empty( $summary['last_stats_synced_at'] ) ? (string) $summary['last_stats_synced_at'] : __( 'Never', 'tmw-cr-slot-sidebar-banner' ) ); ?>
        </div>

        <div class="tmw-cr-actions-row">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_test_connection' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_test_connection" />
                <?php submit_button( __( 'Test Connection', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_sync_offers' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_sync_offers" />
                <?php submit_button( __( 'Sync Offers Now', 'tmw-cr-slot-sidebar-banner' ), 'primary', 'submit', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php $settings = TMW_CR_Slot_Sidebar_Banner::get_settings(); ?>
                <?php wp_nonce_field( 'tmw_cr_slot_banner_sync_stats' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_sync_stats" />
                <select name="stats_sync_range">
                    <option value="7d" <?php selected( $settings['stats_sync_range'], '7d' ); ?>>7d</option>
                    <option value="30d" <?php selected( $settings['stats_sync_range'], '30d' ); ?>>30d</option>
                    <option value="90d" <?php selected( $settings['stats_sync_range'], '90d' ); ?>>90d</option>
                </select>
                <?php submit_button( __( 'Sync Stats Now', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
            </form>
            <p><strong><?php esc_html_e( 'Stored API key', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo esc_html( $client->get_masked_api_key() ? $client->get_masked_api_key() : __( 'Not configured', 'tmw-cr-slot-sidebar-banner' ) ); ?></p>
        </div>

        <table class="widefat striped tmw-cr-overview-table">
            <tbody>
                <tr><th><?php esc_html_e( 'Last response shape', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo ! empty( $sync_meta['last_response_shape'] ) ? esc_html( (string) $sync_meta['last_response_shape'] ) : esc_html__( 'Unknown', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Sample row keys', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo ! empty( $sync_meta['sample_row_keys'] ) ? esc_html( (string) $sync_meta['sample_row_keys'] ) : esc_html__( 'N/A', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Last sync error', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo ! empty( $summary['last_error'] ) ? esc_html( (string) $summary['last_error'] ) : esc_html__( 'None', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Last stats range', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo esc_html( (string) $summary['last_stats_date_start'] . ' → ' . (string) $summary['last_stats_date_end'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Last stats error', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo ! empty( $summary['last_stats_error'] ) ? esc_html( (string) $summary['last_stats_error'] ) : esc_html__( 'None', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array<string,mixed> $settings Settings.
     *
     * @return void
     */
    protected function render_offers_tab( $settings ) {
        $country_options = $this->get_country_options();
        $args = array_merge(
            $this->read_offers_tab_filters_from_request(),
            array(
            'sort_by'           => isset( $_GET['sort_by'] ) ? sanitize_key( wp_unslash( $_GET['sort_by'] ) ) : 'name',
            'sort_order'        => isset( $_GET['sort_order'] ) ? sanitize_key( wp_unslash( $_GET['sort_order'] ) ) : 'asc',
            'page'              => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
            'per_page'          => 25,
            )
        );

        $legacy_catalog = TMW_CR_Slot_Sidebar_Banner::get_offer_catalog_defaults();
        $filter_model    = $this->offer_repository->get_dashboard_filter_model();
        $result         = $this->offer_repository->get_filtered_synced_offers_for_admin( $args, $settings );
        $items          = $result['items'];
        $country        = strtoupper( TMW_CR_Slot_Geo_Helper::get_country_code() );
        $tag_options = $this->build_filter_option_map( (array) ( $filter_model['supported']['tag'] ?? array() ) );
        $vertical_options = $this->build_filter_option_map( (array) ( $filter_model['supported']['vertical'] ?? array() ) );
        $payout_labels = array(
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
        $payout_options = $this->build_filter_option_map(
            (array) ( $filter_model['supported']['payout_type'] ?? array() ),
            $payout_labels
        );
        $reconciliation_counts = $this->offer_repository->get_admin_payout_reconciliation_counts();
        $offers_summary = $this->build_offers_count_summary( $result, $args, $payout_labels, $reconciliation_counts );
        $performs_in_options = $country_options;
        $optimized_for_options = $this->build_filter_option_map( (array) ( $filter_model['supported']['optimized_for'] ?? array() ) );
        $accepted_country_options = $country_options;
        $niche_options = $this->build_filter_option_map( (array) ( $filter_model['supported']['niche'] ?? array() ) );
        $status_options = $this->build_filter_option_map( (array) ( $filter_model['supported']['status'] ?? array() ) );
        $promotion_options = $this->build_filter_option_map( (array) ( $filter_model['supported']['promotion_method'] ?? array() ) );
        ?>
        <form method="get" class="tmw-cr-filters tmw-cr-filters--offers">
            <input type="hidden" name="page" value="tmw-cr-slot-sidebar-banner" />
            <input type="hidden" name="tab" value="offers" />
            <input type="search" name="search" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search by offer name or id', 'tmw-cr-slot-sidebar-banner' ); ?>" />
            <?php $this->render_filter_panel( 'tag', __( 'Tag', 'tmw-cr-slot-sidebar-banner' ), $args['tag'], $tag_options, true ); ?>
            <?php $this->render_filter_panel( 'vertical', __( 'Vertical', 'tmw-cr-slot-sidebar-banner' ), $args['vertical'], $vertical_options, true ); ?>
            <?php $this->render_filter_panel( 'payout_type', __( 'Payout Type', 'tmw-cr-slot-sidebar-banner' ), $args['payout_type'], $payout_options, false ); ?>
            <?php $this->render_filter_panel( 'performs_in', __( 'Performs In', 'tmw-cr-slot-sidebar-banner' ), $args['performs_in'], $performs_in_options, true ); ?>
            <?php $this->render_filter_panel( 'optimized_for', __( 'Optimized For', 'tmw-cr-slot-sidebar-banner' ), $args['optimized_for'], $optimized_for_options, true ); ?>
            <?php $this->render_filter_panel( 'accepted_country', __( 'Accepted Country', 'tmw-cr-slot-sidebar-banner' ), $args['accepted_country'], $accepted_country_options, true ); ?>
            <?php $this->render_filter_panel( 'niche', __( 'Niche', 'tmw-cr-slot-sidebar-banner' ), $args['niche'], $niche_options, true ); ?>
            <?php $status_select_options = array( '' => 'Status: any' ) + $status_options; ?>
            <?php $this->render_filter_select( 'status', $args['status'], $status_select_options ); ?>
            <?php $this->render_filter_panel( 'promotion_method', __( 'Promotion Method', 'tmw-cr-slot-sidebar-banner' ), $args['promotion_method'], $promotion_options, true ); ?>
            <?php $this->render_filter_select( 'featured', $args['featured'], array( '' => 'Featured: any', 'yes' => 'Featured: yes', 'no' => 'Featured: no' ) ); ?>
            <?php $this->render_filter_select( 'approval_required', $args['approval_required'], array( '' => 'Approval: any', 'yes' => 'Approval required', 'no' => 'Approval not required' ) ); ?>
            <?php $this->render_filter_select( 'image_status', $args['image_status'], array( '' => 'Image status: any', 'manual_override' => 'Manual override', 'auto_local' => 'Auto local', 'auto_remote' => 'Auto remote', 'placeholder_only' => 'Placeholder' ) ); ?>
            <?php $this->render_filter_select( 'logo_status', $args['logo_status'], array( '' => 'Logo source: any', 'manual_override' => 'Manual override', 'mapped_local' => 'Mapped local', 'auto_remote' => 'Remote', 'placeholder_only' => 'Placeholder only', 'missing' => 'Missing' ) ); ?>
            <?php submit_button( __( 'Apply', 'tmw-cr-slot-sidebar-banner' ), 'secondary', '', false ); ?>
            <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'options-general.php?page=tmw-cr-slot-sidebar-banner&tab=offers' ) ); ?>"><?php esc_html_e( 'Clear all', 'tmw-cr-slot-sidebar-banner' ); ?></a>
        </form>
        <p class="description">
            <strong><?php echo esc_html( (string) ( $offers_summary['headline'] ?? '' ) ); ?></strong>
            <?php if ( ! empty( $offers_summary['context'] ) ) : ?>
                <br />
                <?php echo esc_html( (string) $offers_summary['context'] ); ?>
            <?php endif; ?>
            <br />
            <?php esc_html_e( 'Payout filters use normalized detected type keys from synced offers. Raw payout strings (for example cpa_flat) can still appear in the payout display.', 'tmw-cr-slot-sidebar-banner' ); ?>
        </p>
        <?php $this->render_payout_reconciliation_panel( $reconciliation_counts, $payout_labels ); ?>
        <?php $this->render_cr_fixture_reconciliation_panel( (array) ( $result['active_filters'] ?? array() ) ); ?>

        <table class="widefat striped">
            <?php if ( ! empty( $result['active_filters'] ) ) : ?>
                <!-- TMW-BANNER-OFFERS-FILTER active="<?php echo esc_attr( implode( ',', array_map( 'sanitize_key', (array) $result['active_filters'] ) ) ); ?>" total="<?php echo esc_attr( (string) (int) ( $result['source_total'] ?? 0 ) ); ?>" matched="<?php echo esc_attr( (string) (int) $result['total'] ); ?>" -->
            <?php endif; ?>
            <thead>
                <tr>
                    <?php $this->render_sort_link_header( 'name', __( 'Name', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'id', __( 'ID', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'status', __( 'Status', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'payout', __( 'Payout', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'featured', __( 'Featured', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <th><?php esc_html_e( 'Approval', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Image', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Logo source', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Frontend eligible', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Block reason', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Slot', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Effective', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php echo esc_html( sprintf( __( 'Country (%s)', 'tmw-cr-slot-sidebar-banner' ), '' !== $country ? $country : '--' ) ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="13"><?php esc_html_e( 'No offers match the current filters.', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $items as $offer ) : ?>
                        <?php
                        $offer_id  = (string) ( $offer['id'] ?? '' );
                        $override  = $this->offer_repository->get_offer_override( $offer_id );
                        $allowed   = $this->offer_repository->is_offer_allowed_for_country( $offer_id, $country, $override, $offer, $legacy_catalog );
                        $is_active = empty( $offer['status'] ) || 'active' === strtolower( (string) $offer['status'] );
                        $is_unavailable = $this->offer_repository->is_offer_unavailable_account_pps( $offer );
                        $eligibility_summary = $this->offer_repository->get_offer_frontend_eligibility_summary( $offer, $settings, $country, $legacy_catalog );
                        $block_reason_labels = array( 'valid' => 'Valid', 'not_allowed_type' => 'Not allowed type', 'business_rule_blocked' => 'Business rule blocked', 'unavailable_account_offer' => 'Unavailable for account', 'missing_valid_cta' => 'Missing valid CTA', 'country_not_allowed' => 'Country not allowed', 'missing_logo' => 'Missing logo', 'skipped_offer' => 'Skipped offer' );
                        $logo_status_labels = array( 'manual_override' => 'Manual override', 'mapped_local' => 'Mapped local', 'auto_remote' => 'Remote', 'placeholder_only' => 'Placeholder only', 'missing' => 'Missing' );
                        ?>
                        <tr>
                            <td>
                                <span class="tmw-cr-offer-title-wrap">
                                    <?php if ( ! empty( $offer['logo_url'] ) ) : ?>
                                        <span class="tmw-cr-offer-logo-wrap">
                                            <img
                                                class="tmw-cr-offer-logo"
                                                src="<?php echo esc_url( (string) $offer['logo_url'] ); ?>"
                                                alt="<?php echo esc_attr( sprintf( __( '%s logo', 'tmw-cr-slot-sidebar-banner' ), (string) ( $offer['name'] ?? '' ) ) ); ?>"
                                                loading="lazy"
                                            />
                                        </span>
                                    <?php endif; ?>
                                    <strong><?php echo esc_html( (string) ( $offer['name'] ?? '' ) ); ?></strong>
                                    <?php if ( $is_unavailable ) : ?>
                                        <small><?php $this->render_badge( 'Unavailable for account', 'muted' ); ?></small>
                                    <?php endif; ?>
                                    <?php
                                    $type_keys = $this->offer_repository->get_offer_type_keys( $offer );
                                    if ( ! empty( $type_keys ) ) :
                                        ?>
                                        <small class="description"><?php echo esc_html( 'Offer Type Keys: ' . implode( ', ', array_map( 'ucfirst', $type_keys ) ) ); ?></small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td><code><?php echo esc_html( (string) ( $offer['id'] ?? '' ) ); ?></code></td>
                            <td><?php $this->render_badge( (string) ( $offer['status'] ?? '-' ), 'status' ); ?></td>
                            <td>
                                <small><?php echo esc_html( 'Raw payout: ' . $this->format_payout( $offer ) ); ?></small>
                                <?php
                                $type_keys = $this->offer_repository->get_offer_type_keys( $offer );
                                if ( ! empty( $type_keys ) ) :
                                    ?>
                                    <br /><small class="description"><?php echo esc_html( 'Detected types: ' . implode( ', ', array_map( 'ucfirst', $type_keys ) ) ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php $this->render_badge( ! empty( $offer['is_featured'] ) ? 'Yes' : 'No', ! empty( $offer['is_featured'] ) ? 'featured' : 'muted' ); ?></td>
                            <td><?php $this->render_badge( '1' === (string) ( $offer['require_approval'] ?? '' ) ? 'Required' : 'No', 'approval' ); ?></td>
                            <td><?php $this->render_image_status_badge( (string) ( $offer['image_status'] ?? '' ) ); ?></td>
                            <td><?php $this->render_badge( (string) ( $logo_status_labels[ (string) ( $offer['logo_status'] ?? '' ) ] ?? 'Unknown' ), 'status' ); ?></td>
                            <td><?php $this->render_badge( ! empty( $eligibility_summary['is_eligible'] ) ? 'Eligible' : 'Excluded', ! empty( $eligibility_summary['is_eligible'] ) ? 'selected' : 'muted' ); ?></td>
                            <td><?php $this->render_badge( (string) ( $block_reason_labels[ (string) ( $eligibility_summary['block_reason'] ?? '' ) ] ?? 'Unknown' ), 'muted' ); ?></td>
                            <td><?php $this->render_badge( ! empty( $offer['is_selected_for_slot'] ) ? 'Selected for slot' : 'Not selected', ! empty( $offer['is_selected_for_slot'] ) ? 'selected' : 'muted' ); ?></td>
                            <td><?php $this->render_badge( ( $is_active && $allowed ) ? 'Eligible' : 'Excluded', ( $is_active && $allowed ) ? 'selected' : 'muted' ); ?></td>
                            <td><?php $this->render_badge( $allowed ? 'Allowed' : 'Blocked', $allowed ? 'featured' : 'muted' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php $this->render_pagination( (int) $result['page'], (int) $result['pages'], $args ); ?>
        <?php
    }

    /**
     * @param array<string,mixed> $settings Settings.
     *
     * @return void
     */
    protected function render_slot_setup_tab( $settings ) {
        $include_all = ! empty( $_GET['include_all_offers'] );
        $args        = array(
            'selected_only' => ! $include_all,
            'include_all'   => $include_all,
            'sort_by'       => 'name',
            'sort_order'    => 'asc',
            'page'          => 1,
            'per_page'      => 400,
        );
        $result         = $this->offer_repository->get_filtered_synced_offers_for_admin( $args, $settings );
        $offers         = $result['items'];
        $country        = strtoupper( TMW_CR_Slot_Geo_Helper::get_country_code() );
        $allowed_offer_types = $this->offer_repository->get_allowed_offer_types( $settings );
        $selected_count = 0;
        $selected_disallowed_count = 0;
        $displayed_pool_count = 0;
        $filtered_offers = array();

        foreach ( $offers as $offer ) {
            $offer_id = (string) ( $offer['id'] ?? '' );
            if ( '' === $offer_id ) {
                continue;
            }

            $is_selected = ! empty( $offer['is_selected_for_slot'] );
            if ( $is_selected ) {
                ++$selected_count;
            }

            $is_allowed_type = $this->offer_repository->is_offer_type_allowed( $offer, $settings );
            if ( $include_all && ! $is_selected && ! $is_allowed_type ) {
                continue;
            }

            if ( $is_selected && ! $is_allowed_type ) {
                ++$selected_disallowed_count;
            }

            ++$displayed_pool_count;
            $offer['is_type_allowed_for_slot'] = $is_allowed_type;
            $filtered_offers[] = $offer;
        }

        $offers = isset( $filtered_offers ) ? $filtered_offers : array();
        error_log( sprintf( '[TMW-BANNER-TYPE] slot_setup_pool allowed_types=%s total_synced_pool=%d displayed_pool_count=%d selected_count=%d selected_disallowed_count=%d', implode( ',', $allowed_offer_types ), count( $this->offer_repository->get_synced_offers() ), $displayed_pool_count, $selected_count, $selected_disallowed_count ) );
        $legacy_catalog = TMW_CR_Slot_Sidebar_Banner::get_offer_catalog_defaults();

        usort(
            $offers,
            static function ( $left, $right ) use ( $settings ) {
                $priorities = isset( $settings['slot_offer_priority'] ) ? (array) $settings['slot_offer_priority'] : array();
                $left_id    = (string) ( $left['id'] ?? '' );
                $right_id   = (string) ( $right['id'] ?? '' );
                $left_p     = isset( $priorities[ $left_id ] ) ? (int) $priorities[ $left_id ] : 9999;
                $right_p    = isset( $priorities[ $right_id ] ) ? (int) $priorities[ $right_id ] : 9999;

                if ( $left_p !== $right_p ) {
                    return $left_p <=> $right_p;
                }

                return strcasecmp( (string) ( $left['name'] ?? '' ), (string) ( $right['name'] ?? '' ) );
            }
        );
        ?>
        <form method="get" class="tmw-cr-filters">
            <input type="hidden" name="page" value="tmw-cr-slot-sidebar-banner" />
            <input type="hidden" name="tab" value="slot-setup" />
            <label>
                <input type="checkbox" name="include_all_offers" value="1" <?php checked( $include_all ); ?> />
                <?php esc_html_e( 'Include more offers from synced pool', 'tmw-cr-slot-sidebar-banner' ); ?>
            </label>
            <?php submit_button( __( 'Refresh View', 'tmw-cr-slot-sidebar-banner' ), 'secondary', '', false ); ?>
        </form>

        <form method="post" action="options.php">
            <?php settings_fields( 'tmw_cr_slot_banner' ); ?>
            <h3><?php esc_html_e( 'Allowed offer types for live banner', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Choose which offer types may appear in the frontend slot/sidebar banner. Logo display in admin is brand-level and remains unaffected.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <?php
            $synced_offers = $this->offer_repository->get_synced_offers();
            $type_allowed_count = 0;
            foreach ( $synced_offers as $synced_offer ) {
                if ( is_array( $synced_offer ) && $this->offer_repository->is_offer_type_allowed( $synced_offer, $settings ) ) {
                    ++$type_allowed_count;
                }
            }
            $type_labels = array(
                'pps' => 'PPS',
                'revshare' => 'Revshare',
                'soi' => 'SOI',
                'doi' => 'DOI',
                'cpa' => 'CPA / Multi-CPA',
                'cpl' => 'CPL / PPL',
                'cpc' => 'CPC / PPC',
                'cpi' => 'CPI',
                'cpm' => 'CPM',
                'smartlink' => 'Smartlink',
                'fallback' => 'Fallback offers',
            );
            ?>
            <p>
                <?php foreach ( $type_labels as $type_key => $type_label ) : ?>
                    <label style="display:inline-block;min-width:180px;margin:0 12px 8px 0;">
                        <input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[allowed_offer_types][]" value="<?php echo esc_attr( $type_key ); ?>" <?php checked( in_array( $type_key, $allowed_offer_types, true ) ); ?> />
                        <?php echo esc_html( $type_label ); ?>
                    </label>
                <?php endforeach; ?>
            </p>
            <p class="description">
                <?php
                echo esc_html(
                    sprintf(
                        'Allowed type filter: %1$s — %2$d offers available.',
                        implode( ' + ', array_map( 'ucfirst', $allowed_offer_types ) ),
                        (int) $displayed_pool_count
                    )
                );
                ?>
            </p>
            <p class="description">
                <?php
                echo esc_html(
                    sprintf(
                        'Type-allowed synced offers: %1$d of %2$d.',
                        (int) $type_allowed_count,
                        count( $synced_offers )
                    )
                );
                ?>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enforce_skipped_offers_exclusion]" value="1" <?php checked( ! empty( $settings['enforce_skipped_offers_exclusion'] ) ); ?> />
                    <?php esc_html_e( 'Enforce skipped-offer exclusion from frontend banner pool', 'tmw-cr-slot-sidebar-banner' ); ?>
                </label>
            </p>
            <p class="description"><?php esc_html_e( 'When enabled, any offer in the Skipped / Rejected list with decision=skip is excluded from the live banner. When disabled, the skipped list remains audit-only.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <?php $pps_coverage = $this->offer_repository->get_pps_logo_coverage_report( $settings ); ?>
            <p class="description">
                <?php
                echo esc_html(
                    sprintf(
                        'PPS logo coverage: %1$d / %2$d mapped',
                        (int) $pps_coverage['pps_with_logo'],
                        (int) $pps_coverage['pps_candidates_total']
                    )
                );
                ?>
            </p>
            <?php if ( ! empty( $pps_coverage['pps_missing_logo'] ) ) : ?>
                <p class="description">
                    <?php esc_html_e( 'Missing PPS logos:', 'tmw-cr-slot-sidebar-banner' ); ?>
                    <?php
                    $missing_rows = array();
                    foreach ( (array) $pps_coverage['missing_logo_offer_names'] as $idx => $missing_name ) {
                        $missing_rows[] = sprintf(
                            '%1$s / %2$s',
                            (string) $missing_name,
                            (string) ( $pps_coverage['missing_logo_offer_ids'][ $idx ] ?? '' )
                        );
                    }
                    echo esc_html( implode( '; ', $missing_rows ) );
                    ?>
                </p>
            <?php endif; ?>
            <?php if ( ! empty( $pps_coverage['blocked_pps_offers_excluded'] ) ) : ?>
                <p style="margin-top:6px;">
                    <?php
                    printf(
                        esc_html__( 'Blocked PPS offers excluded: %d', 'tmw-cr-slot-sidebar-banner' ),
                        (int) $pps_coverage['blocked_pps_offers_excluded']
                    );
                    ?>
                </p>
            <?php endif; ?>
            <?php if ( ! empty( $pps_coverage['unavailable_account_pps_offers_excluded'] ) ) : ?>
                <p class="description">
                    <?php esc_html_e( 'Unavailable/account-inaccessible PPS offers excluded:', 'tmw-cr-slot-sidebar-banner' ); ?>
                    <?php
                    $unavailable_rows = array();
                    foreach ( (array) $pps_coverage['unavailable_account_offer_names'] as $idx => $unavailable_name ) {
                        $unavailable_rows[] = sprintf(
                            '%1$s / %2$s',
                            (string) ( $pps_coverage['unavailable_account_offer_ids'][ $idx ] ?? '' ),
                            (string) $unavailable_name
                        );
                    }
                    echo esc_html( implode( '; ', $unavailable_rows ) );
                    ?>
                </p>
            <?php endif; ?>

            <?php
            $eligible_winner_offers = $this->offer_repository->get_frontend_slot_offers(
                $this->slot_key,
                $settings,
                array(
                    'cta_url'  => (string) ( $settings['cta_url'] ?? '' ),
                    'cta_text' => (string) ( $settings['cta_text'] ?? '' ),
                ),
                $country,
                $legacy_catalog
            );
            ?>
            <p class="description"><?php echo esc_html( sprintf( 'Eligible winner offers: %d', count( $eligible_winner_offers ) ) ); ?></p>
            <?php $manual_diag = $this->offer_repository->get_manual_override_diagnostics(); ?>
            <p class="description"><?php echo esc_html( sprintf( 'Manual final URL overrides: %d', (int) $manual_diag['manual_final_url_overrides'] ) ); ?></p>
            <p class="description"><?php echo esc_html( sprintf( 'Manual allowed country overrides: %d', (int) $manual_diag['manual_allowed_country_overrides'] ) ); ?></p>
            <?php
            $use_target_rules_enabled_count   = 0;
            $use_target_rules_no_override     = 0;
            $all_synced_offers_for_audit      = $this->offer_repository->get_synced_offers();
            $all_offer_overrides_for_audit    = $this->offer_repository->get_offer_overrides();
            foreach ( $all_synced_offers_for_audit as $audit_offer_id => $audit_offer ) {
                if ( ! is_array( $audit_offer ) || empty( $audit_offer['use_target_rules'] ) ) {
                    continue;
                }
                ++$use_target_rules_enabled_count;
                $audit_override = isset( $all_offer_overrides_for_audit[ $audit_offer_id ] ) && is_array( $all_offer_overrides_for_audit[ $audit_offer_id ] ) ? $all_offer_overrides_for_audit[ $audit_offer_id ] : array();
                if ( empty( $audit_override['allowed_countries'] ) ) {
                    ++$use_target_rules_no_override;
                }
            }
            ?>
            <p class="description"><?php echo esc_html( sprintf( 'Offers with API use_target_rules enabled: %d', (int) $use_target_rules_enabled_count ) ); ?></p>
            <p class="description"><?php echo esc_html( sprintf( 'Offers with use_target_rules but no manual country override: %d', (int) $use_target_rules_no_override ) ); ?></p>
            <p class="description"><?php echo esc_html( sprintf( 'Invalid manual URL overrides rejected: %d', (int) $manual_diag['invalid_manual_url_overrides_rejected'] ) ); ?></p>
            <?php
            $manual_override_rows = array();
            foreach ( $this->offer_repository->get_offer_overrides() as $diag_offer_id => $diag_override ) {
                if ( ! is_array( $diag_override ) || empty( $diag_override['final_url_override'] ) ) {
                    continue;
                }
                $host = (string) parse_url( (string) $diag_override['final_url_override'], PHP_URL_HOST );
                $manual_override_rows[] = sprintf( '%1$s / %2$s', sanitize_text_field( (string) $diag_offer_id ), sanitize_text_field( $host ) );
            }
            ?>
            <?php if ( ! empty( $manual_override_rows ) ) : ?>
                <p class="description"><?php esc_html_e( 'Saved manual final URL overrides:', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                <p class="description"><code><?php echo esc_html( implode( '; ', $manual_override_rows ) ); ?></code></p>
            <?php endif; ?>
            <?php
            $manual_country_rows = array();
            foreach ( $this->offer_repository->get_offer_overrides() as $country_offer_id => $country_override ) {
                $allowed = ! empty( $country_override['allowed_countries'] ) ? $this->offer_repository->get_sanitized_country_names( $country_override['allowed_countries'] ) : array();
                if ( empty( $allowed ) ) {
                    continue;
                }
                $offer_name = (string) ( $all_synced_offers_for_audit[ $country_offer_id ]['name'] ?? '' );
                $manual_country_rows[] = sprintf( '%1$s / %2$s countries', sanitize_text_field( (string) $country_offer_id . ( '' !== $offer_name ? ' / ' . $offer_name : '' ) ), count( $allowed ) );
            }
            ?>
            <?php if ( ! empty( $manual_country_rows ) ) : ?>
                <p class="description"><?php esc_html_e( 'Saved manual allowed country overrides:', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                <p class="description"><code><?php echo esc_html( implode( '; ', $manual_country_rows ) ); ?></code></p>
            <?php endif; ?>
            <?php $eligibility_rows = $this->offer_repository->get_manual_winner_eligibility_audit_rows( $settings, array( 'cta_url' => (string) ( $settings['cta_url'] ?? '' ), 'cta_text' => (string) ( $settings['cta_text'] ?? '' ) ), $country, $legacy_catalog ); ?>
            <?php $manual_audit_page = $this->get_positive_query_int( 'manual_audit_page', 1 ); ?>
            <?php $manual_audit_pagination = $this->paginate_rows( $eligibility_rows, $manual_audit_page, 25 ); ?>
            <h3><?php esc_html_e( 'Manual winner eligibility audit', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
            <?php $this->render_audit_pagination( (int) $manual_audit_pagination['current_page'], (int) $manual_audit_pagination['total_pages'], 'manual_audit_page', array( 'pps_audit_page', 'pps_audit_filter', 'pps_audit_search' ) ); ?>
            <table class="widefat striped">
                <thead><tr><th>Offer ID</th><th>Offer name</th><th>Has final URL override</th><th>Final URL host</th><th>Has allowed country override</th><th>Allowed countries count</th><th>Detected visitor country raw</th><th>Detected visitor country normalized</th><th>Eligibility result</th><th>Exclusion reason</th></tr></thead>
                <tbody>
                <?php foreach ( $manual_audit_pagination['rows'] as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) $row['offer_id'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['offer_name'] ); ?></td>
                        <td><?php echo esc_html( ! empty( $row['has_final_url_override'] ) ? 'yes' : 'no' ); ?></td>
                        <td><?php echo esc_html( (string) $row['final_url_host'] ); ?></td>
                        <td><?php echo esc_html( ! empty( $row['has_allowed_country_override'] ) ? 'yes' : 'no' ); ?></td>
                        <td><?php echo esc_html( (string) $row['allowed_countries_count'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['visitor_country_raw'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['visitor_country_normalized'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['eligibility_result'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['exclusion_reason'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $this->render_audit_pagination( (int) $manual_audit_pagination['current_page'], (int) $manual_audit_pagination['total_pages'], 'manual_audit_page', array( 'pps_audit_page', 'pps_audit_filter', 'pps_audit_search' ) ); ?>
            <?php $pps_expansion_rows = $this->offer_repository->get_pps_expansion_readiness_audit_rows( $settings, array( 'cta_url' => (string) ( $settings['cta_url'] ?? '' ), 'cta_text' => (string) ( $settings['cta_text'] ?? '' ) ) ); ?>
            <?php $pps_expansion_summary = $this->offer_repository->get_pps_expansion_readiness_audit_summary( $pps_expansion_rows ); ?>
            <?php $pps_audit_filter = isset( $_GET['pps_audit_filter'] ) ? sanitize_key( wp_unslash( $_GET['pps_audit_filter'] ) ) : 'all'; ?>
            <?php $pps_audit_search = isset( $_GET['pps_audit_search'] ) ? sanitize_text_field( wp_unslash( $_GET['pps_audit_search'] ) ) : ''; ?>
            <?php $pps_filtered_rows = $this->apply_pps_audit_filter( $pps_expansion_rows, $pps_audit_filter, $pps_audit_search ); ?>
            <?php $pps_audit_page = $this->get_positive_query_int( 'pps_audit_page', 1 ); ?>
            <?php $pps_audit_pagination = $this->paginate_rows( $pps_filtered_rows, $pps_audit_page, 25 ); ?>
            <h3><?php esc_html_e( 'PPS expansion readiness audit', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
            <ul>
                <li><?php echo esc_html( sprintf( 'Total PPS candidates: %d', (int) ( $pps_expansion_summary['total_pps_candidates'] ?? 0 ) ) ); ?></li>
                <li><?php echo esc_html( sprintf( 'Frontend-ready PPS offers: %d', (int) ( $pps_expansion_summary['frontend_ready_pps_offers'] ?? 0 ) ) ); ?></li>
                <li><?php echo esc_html( sprintf( 'Blocked by business rule: %d', (int) ( $pps_expansion_summary['blocked_by_business_rule'] ?? 0 ) ) ); ?></li>
                <li><?php echo esc_html( sprintf( 'Missing valid CTA: %d', (int) ( $pps_expansion_summary['missing_valid_cta'] ?? 0 ) ) ); ?></li>
                <li><?php echo esc_html( sprintf( 'Missing allowed-country override: %d', (int) ( $pps_expansion_summary['missing_allowed_country_override'] ?? 0 ) ) ); ?></li>
                <li><?php echo esc_html( sprintf( 'Missing logo: %d', (int) ( $pps_expansion_summary['missing_logo'] ?? 0 ) ) ); ?></li>
                <li><?php echo esc_html( sprintf( 'Override-only candidates: %d', (int) ( $pps_expansion_summary['override_only_candidates'] ?? 0 ) ) ); ?></li>
                <li><?php echo esc_html( sprintf( 'Synced candidates: %d', (int) ( $pps_expansion_summary['synced_candidates'] ?? 0 ) ) ); ?></li>
            </ul>
            <form method="get" style="margin:12px 0;">
                <input type="hidden" name="page" value="tmw-cr-slot-sidebar-banner" />
                <input type="hidden" name="tab" value="slot-setup" />
                <input type="hidden" name="manual_audit_page" value="<?php echo esc_attr( (string) $manual_audit_pagination['current_page'] ); ?>" />
                <label for="pps_audit_filter"><strong><?php esc_html_e( 'Filter', 'tmw-cr-slot-sidebar-banner' ); ?></strong></label>
                <select id="pps_audit_filter" name="pps_audit_filter">
                    <?php $allowed_filters = array( 'all', 'frontend_ready_only', 'missing_cta', 'missing_country_override', 'missing_logo', 'blocked_by_business_rule', 'override_only', 'synced' ); ?>
                    <?php foreach ( $allowed_filters as $filter_key ) : ?>
                        <option value="<?php echo esc_attr( $filter_key ); ?>" <?php selected( $pps_audit_filter, $filter_key ); ?>><?php echo esc_html( $filter_key ); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="pps_audit_search"><strong><?php esc_html_e( 'Search', 'tmw-cr-slot-sidebar-banner' ); ?></strong></label>
                <input type="text" id="pps_audit_search" name="pps_audit_search" value="<?php echo esc_attr( $pps_audit_search ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( 'Apply', 'tmw-cr-slot-sidebar-banner' ); ?></button>
            </form>
            <?php if ( 'all' !== $pps_audit_filter || '' !== $pps_audit_search ) : ?>
                <p class="description"><?php echo esc_html( sprintf( 'Filtered rows: %1$d of %2$d', count( $pps_filtered_rows ), count( $pps_expansion_rows ) ) ); ?></p>
            <?php endif; ?>
            <?php $this->render_audit_pagination( (int) $pps_audit_pagination['current_page'], (int) $pps_audit_pagination['total_pages'], 'pps_audit_page', array( 'manual_audit_page', 'pps_audit_filter', 'pps_audit_search' ) ); ?>
            <table class="widefat striped">
                <thead><tr><th>Offer ID</th><th>Offer name</th><th>Source</th><th>PPS detected?</th><th>Blocked by business rule?</th><th>Block reason</th><th>Final CTA source</th><th>Final CTA host only</th><th>Has allowed-country override?</th><th>Allowed countries count</th><th>Example BE result</th><th>Example US result</th><th>Logo resolved?</th><th>Logo filename</th><th>Frontend-ready?</th></tr></thead>
                <tbody>
                <?php foreach ( $pps_audit_pagination['rows'] as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) $row['offer_id'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['offer_name'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['source'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['pps_detected'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['blocked_by_business_rule'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['block_reason'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['final_cta_source'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['final_cta_host'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['has_allowed_country_override'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['allowed_countries_count'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['example_be_result'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['example_us_result'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['logo_resolved'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['logo_filename'] ); ?></td>
                        <td><?php echo esc_html( (string) $row['frontend_ready'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $this->render_audit_pagination( (int) $pps_audit_pagination['current_page'], (int) $pps_audit_pagination['total_pages'], 'pps_audit_page', array( 'manual_audit_page', 'pps_audit_filter', 'pps_audit_search' ) ); ?>
            <?php if ( 0 === count( $eligible_winner_offers ) ) : ?>
                <p class="description" style="color:#b32d2e;"><strong><?php esc_html_e( 'No eligible winner offers. Add valid final URL overrides or sync real tracking URLs.', 'tmw-cr-slot-sidebar-banner' ); ?></strong></p>
            <?php endif; ?>
            <p class="description"><?php esc_html_e( 'Winner mode: forced three-logo match', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <p class="description"><?php esc_html_e( 'Final reel behavior: one selected offer repeated across 3 reels', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <?php $url_audit = $this->offer_repository->get_cr_url_field_audit_summary( $settings ); ?>
                <h3><?php esc_html_e( 'CR URL field audit', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
                <ul>
                    <li><?php echo esc_html( sprintf( 'synced PPS offers checked: %d', (int) $url_audit['synced_pps_offers_checked'] ) ); ?></li>
                    <li><?php echo esc_html( sprintf( 'offers with tracking_url: %d', (int) $url_audit['offers_with_tracking_url'] ) ); ?></li>
                    <li><?php echo esc_html( sprintf( 'offers with preview/template URL only: %d', (int) $url_audit['offers_with_preview_template_url_only'] ) ); ?></li>
                    <li><?php echo esc_html( sprintf( 'offers with raw advertiser URL only: %d', (int) $url_audit['offers_with_raw_advertiser_url_only'] ) ); ?></li>
                    <li><?php echo esc_html( sprintf( 'offers with empty URL: %d', (int) $url_audit['offers_with_empty_url'] ) ); ?></li>
                    <li><?php echo esc_html( sprintf( 'offers with unresolved placeholders: %d', (int) $url_audit['offers_with_unresolved_placeholders'] ) ); ?></li>
                    <li><?php echo esc_html( sprintf( 'offers excluded by invalid CTA validation: %d', (int) $url_audit['offers_excluded_by_invalid_cta_validation'] ) ); ?></li>
                </ul>
                <?php
                $tracking_coverage_ratio = 0;
                if ( (int) $url_audit['synced_pps_offers_checked'] > 0 ) {
                    $tracking_coverage_ratio = (int) $url_audit['offers_with_tracking_url'] / (int) $url_audit['synced_pps_offers_checked'];
                }
                ?>
                <?php if ( $tracking_coverage_ratio < 0.5 ) : ?>
                    <p class="description" style="color:#b32d2e;"><strong><?php esc_html_e( 'WARNING: Real tracking URL coverage is low. Winner pool may be limited.', 'tmw-cr-slot-sidebar-banner' ); ?></strong></p>
                <?php endif; ?>
                <p class="description"><?php esc_html_e( 'URL field hostnames are shown for audit only.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                <ul>
                    <?php foreach ( (array) $url_audit['field_counts'] as $field_name => $field_count ) : ?>
                        <?php $hosts = isset( $url_audit['field_hosts'][ $field_name ] ) ? (array) $url_audit['field_hosts'][ $field_name ] : array(); ?>
                        <li>
                            <code><?php echo esc_html( (string) $field_name ); ?></code>:
                            <?php echo esc_html( sprintf( 'count=%d hosts=%s', (int) $field_count, implode( ',', array_map( 'sanitize_text_field', $hosts ) ) ) ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
                    <p class="description"><?php esc_html_e( 'WP_DEBUG is enabled; detailed URL values can be inspected in the stored synced offer option for deeper troubleshooting.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Select', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Offer', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Image override', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Final URL', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Custom CTA', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Allowed countries', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Blocked countries', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Preview', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Image source', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Quick action', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $offers ) ) : ?>
                        <tr><td colspan="11"><?php esc_html_e( 'No offers available for slot setup yet. Sync offers first.', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $offers as $offer ) : ?>
                            <?php
                            $offer_id    = (string) ( $offer['id'] ?? '' );
                            $selected    = ! empty( $offer['is_selected_for_slot'] );
                            $priority    = isset( $settings['slot_offer_priority'][ $offer_id ] ) ? (int) $settings['slot_offer_priority'][ $offer_id ] : 100;
                            $image_value = isset( $settings['offer_image_overrides'][ $offer_id ] ) ? (string) $settings['offer_image_overrides'][ $offer_id ] : '';
                            $override    = $this->offer_repository->get_offer_override( $offer_id );
                            $enabled     = ! isset( $override['enabled'] ) || ! empty( $override['enabled'] );
                            $allowed_raw = ! empty( $override['allowed_countries'] ) ? implode( ',', (array) $override['allowed_countries'] ) : '';
                            $blocked_raw = ! empty( $override['blocked_countries'] ) ? implode( ',', (array) $override['blocked_countries'] ) : '';
                            $eligible    = $this->offer_repository->is_offer_allowed_for_country( $offer_id, $country, $override, $offer, array() );
                            $effective_image = $this->offer_repository->get_effective_image( $offer_id, $settings, array(), $offer, $override, $legacy_catalog );
                            $effective_url   = $this->offer_repository->get_effective_cta_url( $offer_id, $settings, array( 'cta_url' => (string) $settings['cta_url'] ), $offer, $override );
                            $image_status    = $this->offer_repository->get_image_status_for_offer( $offer_id, $settings, $legacy_catalog );
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[slot_offer_ids][]" value="<?php echo esc_attr( $offer_id ); ?>" <?php checked( $selected ); ?> />
                                    <br />
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> />
                                        <?php esc_html_e( 'Enabled', 'tmw-cr-slot-sidebar-banner' ); ?>
                                    </label>
                                </td>
                                <td><strong><?php echo esc_html( (string) ( $offer['name'] ?? '' ) ); ?></strong><br /><code><?php echo esc_html( $offer_id ); ?></code></td>
                                <td><input type="number" min="0" step="1" name="<?php echo esc_attr( $this->option_key ); ?>[slot_offer_priority][<?php echo esc_attr( $offer_id ); ?>]" value="<?php echo esc_attr( (string) $priority ); ?>" style="width:90px;" /></td>
                                <td>
                                    <input type="url" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_image_overrides][<?php echo esc_attr( $offer_id ); ?>]" value="<?php echo esc_attr( $image_value ); ?>" />
                                    <input type="url" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][image_url_override]" value="<?php echo esc_attr( (string) ( $override['image_url_override'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Per-offer image override', 'tmw-cr-slot-sidebar-banner' ); ?>" />
                                    <p class="description"><?php esc_html_e( 'Optional. Leave blank to use automatic resolver chain (local/remote/placeholder).', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                                </td>
                                <td><input type="url" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][final_url_override]" value="<?php echo esc_attr( (string) ( $override['final_url_override'] ?? '' ) ); ?>" placeholder="https://..." /></td>
                                <td>
                                    <input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][custom_cta_text]" value="<?php echo esc_attr( (string) ( $override['custom_cta_text'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Custom CTA text', 'tmw-cr-slot-sidebar-banner' ); ?>" />
                                    <input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][label_override]" value="<?php echo esc_attr( (string) ( $override['label_override'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Label override', 'tmw-cr-slot-sidebar-banner' ); ?>" />
                                    <textarea class="large-text" rows="2" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][notes]" placeholder="<?php esc_attr_e( 'Internal notes', 'tmw-cr-slot-sidebar-banner' ); ?>"><?php echo esc_textarea( (string) ( $override['notes'] ?? '' ) ); ?></textarea>
                                </td>
                                <td><input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][allowed_countries]" value="<?php echo esc_attr( $allowed_raw ); ?>" placeholder="US,CA,GB" /></td>
                                <td><input type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_overrides][<?php echo esc_attr( $offer_id ); ?>][blocked_countries]" value="<?php echo esc_attr( $blocked_raw ); ?>" placeholder="FR,DE" /></td>
                                <td>
                                    <?php if ( '' !== $effective_image ) : ?>
                                        <img src="<?php echo esc_url( $effective_image ); ?>" alt="" style="max-width:70px;height:auto;border-radius:4px;" />
                                    <?php else : ?>
                                        <span class="description"><?php esc_html_e( 'Placeholder in use', 'tmw-cr-slot-sidebar-banner' ); ?></span>
                                    <?php endif; ?>
                                    <p class="description"><strong><?php esc_html_e( '[TMW-CR-DASH] Destination:', 'tmw-cr-slot-sidebar-banner' ); ?></strong> <?php echo esc_html( $effective_url ? $effective_url : '-' ); ?></p>
                                </td>
                                <td><?php $this->render_image_status_badge( $image_status ); ?></td>
                                <td>
                                    <?php $this->render_badge( $selected ? 'Selected' : 'Not selected', $selected ? 'selected' : 'muted' ); ?>
                                    <?php $this->render_badge( $eligible ? 'Country eligible' : 'Country blocked', $eligible ? 'featured' : 'muted' ); ?>
                                    <?php if ( $selected && empty( $offer['is_type_allowed_for_slot'] ) ) : ?>
                                        <?php $this->render_badge( 'Type not currently allowed', 'warning' ); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php submit_button( __( 'Save Slot Setup', 'tmw-cr-slot-sidebar-banner' ) ); ?>
        </form>

        <h3><?php esc_html_e( 'Current final reel pool order', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
        <ol>
            <?php
            $selected_ids = isset( $settings['slot_offer_ids'] ) && is_array( $settings['slot_offer_ids'] ) ? $settings['slot_offer_ids'] : array();
            $priorities   = isset( $settings['slot_offer_priority'] ) && is_array( $settings['slot_offer_priority'] ) ? $settings['slot_offer_priority'] : array();
            $all_offers   = $this->offer_repository->get_synced_offers();
            $pool         = array();

            foreach ( $selected_ids as $selected_id ) {
                $selected_id = (string) $selected_id;
                if ( isset( $all_offers[ $selected_id ] ) ) {
                    $pool[] = array(
                        'id'       => $selected_id,
                        'name'     => (string) ( $all_offers[ $selected_id ]['name'] ?? $selected_id ),
                        'priority' => isset( $priorities[ $selected_id ] ) ? (int) $priorities[ $selected_id ] : 9999,
                    );
                }
            }

            usort(
                $pool,
                static function ( $left, $right ) {
                    if ( $left['priority'] !== $right['priority'] ) {
                        return $left['priority'] <=> $right['priority'];
                    }

                    return strcasecmp( $left['name'], $right['name'] );
                }
            );

            if ( empty( $pool ) ) {
                echo '<li>' . esc_html__( 'No offers selected yet.', 'tmw-cr-slot-sidebar-banner' ) . '</li>';
            } else {
                foreach ( $pool as $item ) {
                    echo '<li><strong>' . esc_html( $item['name'] ) . '</strong> <code>' . esc_html( $item['id'] ) . '</code> — ' . sprintf( esc_html__( 'Priority %d', 'tmw-cr-slot-sidebar-banner' ), (int) $item['priority'] ) . '</li>';
                }
            }
            ?>
        </ol>
        <?php
        if ( current_user_can( 'manage_options' ) ) :
        ?>
        <div class="screen-reader-text" aria-hidden="true" data-legacy-allowed-country-action="tmw_cr_slot_banner_import_allowed_country_overrides" data-legacy-final-url-action="tmw_cr_slot_banner_import_final_url_overrides">
            <?php wp_nonce_field( 'tmw_cr_slot_banner_import_allowed_country_overrides', 'tmw_legacy_allowed_country_nonce' ); ?>
            <?php wp_nonce_field( 'tmw_cr_slot_banner_import_final_url_overrides', 'tmw_legacy_final_url_nonce' ); ?>
        </div>
        <h3><?php esc_html_e( 'Import Both Override CSVs', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'tmw_cr_slot_banner_import_both_overrides' ); ?>
            <input type="hidden" name="action" value="tmw_cr_slot_banner_import_both_overrides" />
            <textarea name="allowed_country_override_csv" class="large-text code" rows="6" placeholder="offer_id,allowed_countries&#10;8780,&quot;Belgium|United States|United Kingdom|Germany|France&quot;"></textarea>
            <textarea name="final_url_override_csv" class="large-text code" rows="6" placeholder="offer_id,final_url_override&#10;8873,https://real-cr-tracking-link.example/..."></textarea>
            <?php submit_button( __( 'Import Both Override CSVs', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
        </form>

        <h3><?php esc_html_e( 'Import Skipped / Rejected Offers', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Audit-only tracker for offers we do not want in the banner. This does not change frontend winner logic in this hotfix.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'tmw_cr_slot_import_skipped_offers' ); ?>
            <input type="hidden" name="action" value="tmw_cr_slot_import_skipped_offers" />
            <textarea name="skipped_offers_csv" class="large-text code" rows="6" placeholder="offer_id,offer_name,decision,reason,notes&#10;2492,Example Offer,skip,male-targeted,Do not use in sidebar banner&#10;9781,Dating.com PPS,skip,unavailable-account,Excluded from account&#10;9647,Tapyn PPS,skip,unavailable-account,Excluded from account"></textarea>
            <?php submit_button( __( 'Import Skipped Offers', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
        </form>
        <?php
        $skipped_rows = array_values( $this->offer_repository->get_skipped_offers() );
        usort(
            $skipped_rows,
            static function ( $left, $right ) {
                return strcmp( (string) ( $right['updated_at'] ?? '' ), (string) ( $left['updated_at'] ?? '' ) );
            }
        );
        $skipped_rows = array_slice( $skipped_rows, 0, 50 );
        ?>
        <h4><?php esc_html_e( 'Current skipped / rejected offers (latest 50)', 'tmw-cr-slot-sidebar-banner' ); ?></h4>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Offer ID', 'tmw-cr-slot-sidebar-banner' ); ?></th><th><?php esc_html_e( 'Offer Name', 'tmw-cr-slot-sidebar-banner' ); ?></th><th><?php esc_html_e( 'Decision', 'tmw-cr-slot-sidebar-banner' ); ?></th><th><?php esc_html_e( 'Reason', 'tmw-cr-slot-sidebar-banner' ); ?></th><th><?php esc_html_e( 'Updated', 'tmw-cr-slot-sidebar-banner' ); ?></th></tr></thead>
            <tbody>
                <?php if ( empty( $skipped_rows ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No skipped offers saved yet.', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $skipped_rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( (string) ( $row['offer_id'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['offer_name'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['decision'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['reason'] ?? '' ) ); ?></td>
                            <td><?php echo esc_html( (string) ( $row['updated_at'] ?? '' ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        endif;
        ?>
        <?php
    }

    /**
     * @return void
     */
    protected function render_performance_tab() {
        $settings   = TMW_CR_Slot_Sidebar_Banner::get_settings();
        $country    = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
        $status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
        $payout_type_filter = isset( $_GET['payout_type'] ) ? sanitize_key( wp_unslash( $_GET['payout_type'] ) ) : '';
        $sort_by    = isset( $_GET['sort_by'] ) ? sanitize_key( wp_unslash( $_GET['sort_by'] ) ) : 'payout';
        $sort_order = isset( $_GET['sort_order'] ) ? sanitize_key( wp_unslash( $_GET['sort_order'] ) ) : 'desc';
        $rows       = $this->offer_repository->get_performance_rows(
            $country,
            array(
                'status'     => $status_filter,
                'payout_type'=> $payout_type_filter,
                'sort_by'    => $sort_by,
                'sort_order' => $sort_order,
            )
        );
        $summary = $this->offer_repository->get_performance_summary();
        $stats_meta = $this->offer_repository->get_stats_meta();
        $filter_model = $this->offer_repository->get_dashboard_filter_model();
        $next_cron = wp_next_scheduled( TMW_CR_Slot_Sidebar_Banner::STATS_SYNC_CRON_HOOK );
        $explain_rows = $this->offer_repository->get_optimization_explain_rows( $country, $settings, 10 );
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'tmw_cr_slot_banner' ); ?>
            <h2><?php esc_html_e( 'Optimization Controls', 'tmw-cr-slot-sidebar-banner' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr><th scope="row"><?php esc_html_e( 'Rotation mode', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><select name="<?php echo esc_attr( $this->option_key ); ?>[rotation_mode]"><option value="manual" <?php selected( $settings['rotation_mode'], 'manual' ); ?>>manual</option><option value="payout_desc" <?php selected( $settings['rotation_mode'], 'payout_desc' ); ?>>payout_desc</option><option value="conversions_desc" <?php selected( $settings['rotation_mode'], 'conversions_desc' ); ?>>conversions_desc</option><option value="epc_desc" <?php selected( $settings['rotation_mode'], 'epc_desc' ); ?>>epc_desc</option><option value="country_epc_desc" <?php selected( $settings['rotation_mode'], 'country_epc_desc' ); ?>>country_epc_desc</option><option value="hybrid_score" <?php selected( $settings['rotation_mode'], 'hybrid_score' ); ?>>hybrid_score</option><option value="safe_hybrid_score" <?php selected( $settings['rotation_mode'], 'safe_hybrid_score' ); ?>>safe_hybrid_score</option></select></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Optimization enabled', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[optimization_enabled]" value="1" <?php checked( ! empty( $settings['optimization_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable runtime optimization ordering', 'tmw-cr-slot-sidebar-banner' ); ?></label></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Minimum clicks', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><input type="number" min="0" name="<?php echo esc_attr( $this->option_key ); ?>[minimum_clicks_threshold]" value="<?php echo esc_attr( (string) $settings['minimum_clicks_threshold'] ); ?>" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Minimum conversions', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( $this->option_key ); ?>[minimum_conversions_threshold]" value="<?php echo esc_attr( (string) $settings['minimum_conversions_threshold'] ); ?>" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Minimum payout', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( $this->option_key ); ?>[minimum_payout_threshold]" value="<?php echo esc_attr( (string) $settings['minimum_payout_threshold'] ); ?>" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Zero-data exclusion', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[exclude_zero_click_offers]" value="1" <?php checked( ! empty( $settings['exclude_zero_click_offers'] ) ); ?> /> <?php esc_html_e( 'Exclude zero-click offers', 'tmw-cr-slot-sidebar-banner' ); ?></label><br/><label><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[exclude_zero_conversion_offers]" value="1" <?php checked( ! empty( $settings['exclude_zero_conversion_offers'] ) ); ?> /> <?php esc_html_e( 'Exclude zero-conversion offers', 'tmw-cr-slot-sidebar-banner' ); ?></label></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Country decay', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[country_decay_enabled]" value="1" <?php checked( ! empty( $settings['country_decay_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable country/global blended scoring', 'tmw-cr-slot-sidebar-banner' ); ?></label><br/><label>country_weight <input type="number" min="0" max="1" step="0.05" name="<?php echo esc_attr( $this->option_key ); ?>[country_weight]" value="<?php echo esc_attr( (string) $settings['country_weight'] ); ?>" /></label> <label>global_weight <input type="number" min="0" max="1" step="0.05" name="<?php echo esc_attr( $this->option_key ); ?>[global_weight]" value="<?php echo esc_attr( (string) $settings['global_weight'] ); ?>" /></label><br/><label>decay_min_country_clicks <input type="number" min="1" name="<?php echo esc_attr( $this->option_key ); ?>[decay_min_country_clicks]" value="<?php echo esc_attr( (string) $settings['decay_min_country_clicks'] ); ?>" /></label><br/><label><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[fallback_to_global_when_low_sample]" value="1" <?php checked( ! empty( $settings['fallback_to_global_when_low_sample'] ) ); ?> /> <?php esc_html_e( 'Fallback to global when country sample is low', 'tmw-cr-slot-sidebar-banner' ); ?></label><p class="description"><?php esc_html_e( '[TMW-CR-OPT] Formula: effective_metric = (country_metric × adjusted_country_weight) + (global_metric × adjusted_global_weight).', 'tmw-cr-slot-sidebar-banner' ); ?></p></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Stats sync automation', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[auto_sync_enabled]" value="1" <?php checked( ! empty( $settings['auto_sync_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable scheduled stats sync', 'tmw-cr-slot-sidebar-banner' ); ?></label><br/><label>frequency <select name="<?php echo esc_attr( $this->option_key ); ?>[auto_sync_frequency]"><option value="hourly" <?php selected( $settings['auto_sync_frequency'], 'hourly' ); ?>>hourly</option><option value="twicedaily" <?php selected( $settings['auto_sync_frequency'], 'twicedaily' ); ?>>twice_daily</option><option value="daily" <?php selected( $settings['auto_sync_frequency'], 'daily' ); ?>>daily</option></select></label><br/><label>stats range <select name="<?php echo esc_attr( $this->option_key ); ?>[stats_sync_range]"><option value="7d" <?php selected( $settings['stats_sync_range'], '7d' ); ?>>7d</option><option value="30d" <?php selected( $settings['stats_sync_range'], '30d' ); ?>>30d</option><option value="90d" <?php selected( $settings['stats_sync_range'], '90d' ); ?>>90d</option></select></label><p class="description"><?php echo esc_html( sprintf( '[TMW-CR-CRON] next=%s | last=%s | result=%s', $next_cron ? gmdate( 'c', (int) $next_cron ) : 'none', (string) ( $stats_meta['last_scheduled_run_at'] ?? 'never' ), (string) ( $stats_meta['last_scheduled_result'] ?? 'n/a' ) ) ); ?></p></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Optimization notes', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><textarea class="large-text" rows="3" name="<?php echo esc_attr( $this->option_key ); ?>[optimization_notes]"><?php echo esc_textarea( (string) $settings['optimization_notes'] ); ?></textarea></td></tr>
                </tbody>
            </table>
            <?php submit_button( __( 'Save Optimization Controls', 'tmw-cr-slot-sidebar-banner' ) ); ?>
        </form>

        <form method="get" class="tmw-cr-filters">
            <input type="hidden" name="page" value="tmw-cr-slot-sidebar-banner" />
            <input type="hidden" name="tab" value="performance" />
            <label><?php esc_html_e( 'Country', 'tmw-cr-slot-sidebar-banner' ); ?> <input type="text" name="country" value="<?php echo esc_attr( $country ); ?>" placeholder="US / Canada / GLOBAL" /></label>
            <?php
            $status_options = array( '' => 'Status: any' );
            foreach ( (array) ( $filter_model['supported']['status'] ?? array() ) as $status_option ) {
                $status_options[ $status_option ] = strtoupper( $status_option );
            }
            $payout_options = array( '' => 'Payout type: any' );
            $payout_labels = array(
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
            $available_payout_types = (array) ( $filter_model['supported']['payout_type'] ?? array() );
            foreach ( $available_payout_types as $type_option ) {
                $type_option = sanitize_key( (string) $type_option );
                if ( '' === $type_option ) {
                    continue;
                }
                $payout_options[ $type_option ] = $payout_labels[ $type_option ] ?? strtoupper( str_replace( '_', ' ', $type_option ) );
            }
            ?>
            <?php $this->render_filter_select( 'status', $status_filter, $status_options ); ?>
            <?php $this->render_filter_select( 'payout_type', $payout_type_filter, $payout_options ); ?>
            <label><?php esc_html_e( 'Sort by', 'tmw-cr-slot-sidebar-banner' ); ?>
                <select name="sort_by">
                    <option value="payout" <?php selected( $sort_by, 'payout' ); ?>>payout</option>
                    <option value="clicks" <?php selected( $sort_by, 'clicks' ); ?>>clicks</option>
                    <option value="conversions" <?php selected( $sort_by, 'conversions' ); ?>>conversions</option>
                    <option value="epc" <?php selected( $sort_by, 'epc' ); ?>>epc</option>
                    <option value="conversion_rate" <?php selected( $sort_by, 'conversion_rate' ); ?>>conversion rate</option>
                </select>
            </label>
            <label><?php esc_html_e( 'Order', 'tmw-cr-slot-sidebar-banner' ); ?>
                <select name="sort_order">
                    <option value="desc" <?php selected( $sort_order, 'desc' ); ?>>desc</option>
                    <option value="asc" <?php selected( $sort_order, 'asc' ); ?>>asc</option>
                </select>
            </label>
            <?php submit_button( __( 'Apply', 'tmw-cr-slot-sidebar-banner' ), 'secondary', '', false ); ?>
        </form>

        <p><strong>[TMW-CR-DASH]</strong> <?php echo esc_html( sprintf( 'Top offer: %s | Top country: %s | Total payout: %.2f', (string) $summary['top_offer_name'], (string) $summary['top_country_name'], (float) $summary['total_payout'] ) ); ?></p>
        <p><strong>[TMW-CR-STATS]</strong> <?php echo esc_html( sprintf( 'Shape: %s | Sample keys: %s | Soft failure: %s', (string) ( $stats_meta['last_stats_response_shape'] ?? 'unknown' ), (string) ( $stats_meta['last_stats_sample_row_keys'] ?? 'n/a' ), (string) ( $stats_meta['last_stats_soft_failure'] ?? 'none' ) ) ); ?>
            <?php if ( ! empty( $stats_meta['last_stats_preserved_previous'] ) ) : ?>
                <span class="tmw-cr-badge tmw-cr-badge-warn"><?php esc_html_e( 'Parser soft-failure preserved previous stats', 'tmw-cr-slot-sidebar-banner' ); ?></span>
            <?php endif; ?>
        </p>
        <details>
            <summary><?php esc_html_e( '[TMW-CR-DASH] Operator filter roadmap scaffold', 'tmw-cr-slot-sidebar-banner' ); ?></summary>
            <ul>
                <?php foreach ( (array) ( $filter_model['todo'] ?? array() ) as $todo_key => $todo_message ) : ?>
                    <li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $todo_key ) ) ); ?>:</strong> <?php echo esc_html( (string) $todo_message ); ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Offer', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Clicks', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Conversions', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Payout', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'EPC', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Conversion rate %', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $rows ) ) : ?>
                <tr><td colspan="6"><?php esc_html_e( 'No local stats found. Run Stats Sync first.', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( (string) $row['offer_name'] ); ?></strong><br/><code><?php echo esc_html( (string) $row['offer_id'] ); ?></code></td>
                        <td><?php echo esc_html( (string) (int) $row['clicks'] ); ?></td>
                        <td><?php echo esc_html( (string) (float) $row['conversions'] ); ?></td>
                        <td><?php echo esc_html( number_format( (float) $row['payout'], 2 ) ); ?></td>
                        <td><?php echo esc_html( number_format( (float) $row['epc'], 6 ) ); ?></td>
                        <td><?php echo esc_html( number_format( (float) $row['conversion_rate'], 4 ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <h3><?php esc_html_e( 'Optimization Explainability (Top Ranked)', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
        <table class="widefat striped">
            <thead><tr><th>Offer</th><th>Clicks</th><th>Conversions</th><th>Payout</th><th>EPC</th><th>Country sample</th><th>Global fallback</th><th>Low-sample penalty</th><th>Final score</th></tr></thead>
            <tbody>
            <?php foreach ( $explain_rows as $row ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( (string) $row['offer_name'] ); ?></strong><br/><code><?php echo esc_html( (string) $row['offer_id'] ); ?></code></td>
                    <td><?php echo esc_html( (string) (int) $row['clicks'] ); ?></td>
                    <td><?php echo esc_html( (string) (float) $row['conversions'] ); ?></td>
                    <td><?php echo esc_html( number_format( (float) $row['payout'], 2 ) ); ?></td>
                    <td><?php echo esc_html( number_format( (float) $row['epc'], 6 ) ); ?></td>
                    <td><?php echo esc_html( (string) (int) $row['country_sample_used'] ); ?></td>
                    <td><?php echo ! empty( $row['used_global_fallback'] ) ? 'yes' : 'no'; ?></td>
                    <td><?php echo ! empty( $row['low_sample_penalty'] ) ? 'yes' : 'no'; ?></td>
                    <td><?php echo esc_html( number_format( (float) $row['final_score'], 6 ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array<string,mixed> $settings Settings.
     * @param array<string,mixed> $sync_meta Sync meta.
     * @param TMW_CR_Slot_CR_API_Client $client Client.
     *
     * @return void
     */
    protected function render_settings_tab( $settings, $sync_meta, $client ) {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'tmw_cr_slot_banner' ); ?>

            <h2><?php esc_html_e( 'Fallback Banner Settings', 'tmw-cr-slot-sidebar-banner' ); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="tmw-cr-headline"><?php esc_html_e( 'Headline', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="tmw-cr-headline" name="<?php echo esc_attr( $this->option_key ); ?>[headline]" value="<?php echo esc_attr( $settings['headline'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-subheadline"><?php esc_html_e( 'Subheadline', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td><input type="text" class="regular-text" id="tmw-cr-subheadline" name="<?php echo esc_attr( $this->option_key ); ?>[subheadline]" value="<?php echo esc_attr( $settings['subheadline'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-cta-text"><?php esc_html_e( 'CTA Text', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tmw-cr-cta-text" name="<?php echo esc_attr( $this->option_key ); ?>[cta_text]" value="<?php echo esc_attr( $settings['cta_text'] ); ?>" />
                            <p class="description"><?php esc_html_e( 'Default used when empty: TRY YOUR FREE SPINS', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-spin-button-text"><?php esc_html_e( 'Spin Button Text', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="tmw-cr-spin-button-text" name="<?php echo esc_attr( $this->option_key ); ?>[spin_button_text]" value="<?php echo esc_attr( $settings['spin_button_text'] ?? '' ); ?>" />
                            <p class="description"><?php esc_html_e( 'Default used when empty: SPIN THE REELS', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Frontend Text Debug', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <td>
                            <p><strong><?php esc_html_e( 'Headline', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <code><?php echo esc_html( (string) $settings['headline'] ); ?></code></p>
                            <p><strong><?php esc_html_e( 'Subheadline', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <code><?php echo esc_html( (string) $settings['subheadline'] ); ?></code></p>
                            <p><strong><?php esc_html_e( 'CTA Text', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <code><?php echo esc_html( (string) $settings['cta_text'] ); ?></code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-cta-url"><?php esc_html_e( 'CTA URL', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td><input type="url" class="regular-text" id="tmw-cr-cta-url" name="<?php echo esc_attr( $this->option_key ); ?>[cta_url]" value="<?php echo esc_attr( $settings['cta_url'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-default-image"><?php esc_html_e( 'Default Image URL', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td><input type="url" class="regular-text" id="tmw-cr-default-image" name="<?php echo esc_attr( $this->option_key ); ?>[default_image_url]" value="<?php echo esc_attr( $settings['default_image_url'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-subid-param"><?php esc_html_e( 'SubID Query Parameter', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td><input type="text" id="tmw-cr-subid-param" name="<?php echo esc_attr( $this->option_key ); ?>[subid_param]" value="<?php echo esc_attr( $settings['subid_param'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-subid-value"><?php esc_html_e( 'SubID Value', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td><input type="text" id="tmw-cr-subid-value" name="<?php echo esc_attr( $this->option_key ); ?>[subid_value]" value="<?php echo esc_attr( $settings['subid_value'] ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-api-key"><?php esc_html_e( 'CrakRevenue API Key', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td>
                            <input type="password" class="regular-text" id="tmw-cr-api-key" name="<?php echo esc_attr( $this->option_key ); ?>[cr_api_key]" value="" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep existing key', 'tmw-cr-slot-sidebar-banner' ); ?>" />
                            <p class="description"><?php esc_html_e( 'Used only for secure server-side sync requests. Never exposed in HTML, the slot shortcode output, or notices.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                            <p><strong><?php esc_html_e( 'Stored key', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo esc_html( $client->get_masked_api_key() ? $client->get_masked_api_key() : __( 'Not configured', 'tmw-cr-slot-sidebar-banner' ) ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Open CTA in new tab', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <td>
                            <label for="tmw-cr-open-new-tab">
                                <input type="checkbox" id="tmw-cr-open-new-tab" name="<?php echo esc_attr( $this->option_key ); ?>[open_in_new_tab]" value="1" <?php checked( $settings['open_in_new_tab'], 1 ); ?> />
                                <?php esc_html_e( 'Open the CTA in a new browser tab.', 'tmw-cr-slot-sidebar-banner' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-rotation-mode"><?php esc_html_e( 'Rotation mode', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td>
                            <select id="tmw-cr-rotation-mode" name="<?php echo esc_attr( $this->option_key ); ?>[rotation_mode]">
                                <option value="manual" <?php selected( $settings['rotation_mode'], 'manual' ); ?>>manual</option>
                                <option value="payout_desc" <?php selected( $settings['rotation_mode'], 'payout_desc' ); ?>>payout_desc</option>
                                <option value="conversions_desc" <?php selected( $settings['rotation_mode'], 'conversions_desc' ); ?>>conversions_desc</option>
                                <option value="epc_desc" <?php selected( $settings['rotation_mode'], 'epc_desc' ); ?>>epc_desc</option>
                                <option value="country_epc_desc" <?php selected( $settings['rotation_mode'], 'country_epc_desc' ); ?>>country_epc_desc</option>
                                <option value="hybrid_score" <?php selected( $settings['rotation_mode'], 'hybrid_score' ); ?>>hybrid_score</option>
                                <option value="safe_hybrid_score" <?php selected( $settings['rotation_mode'], 'safe_hybrid_score' ); ?>>safe_hybrid_score</option>
                            </select>
                            <p class="description"><?php esc_html_e( '[TMW-CR-OPT] Runtime ordering mode. Manual priority state is never rewritten.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-stats-range"><?php esc_html_e( 'Stats sync range', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td>
                            <select id="tmw-cr-stats-range" name="<?php echo esc_attr( $this->option_key ); ?>[stats_sync_range]">
                                <option value="7d" <?php selected( $settings['stats_sync_range'], '7d' ); ?>>7d</option>
                                <option value="30d" <?php selected( $settings['stats_sync_range'], '30d' ); ?>>30d</option>
                                <option value="90d" <?php selected( $settings['stats_sync_range'], '90d' ); ?>>90d</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmw-cr-country-overrides"><?php esc_html_e( 'Country Overrides', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                        <td>
                            <textarea class="large-text code" rows="8" id="tmw-cr-country-overrides" name="<?php echo esc_attr( $this->option_key ); ?>[country_overrides_raw]" placeholder="US|https://example.com/us-banner.png|https://offer.com/us|Join Now|Exclusive US Bonus"><?php echo esc_textarea( $settings['country_overrides_raw'] ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'One override per line using the format CC|Image URL|CTA URL|CTA Text|Headline.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>


            <?php // Skipped offers UI intentionally disabled in runtime hotfix to restore stable admin surface. ?>

            <?php submit_button( __( 'Save Banner Settings', 'tmw-cr-slot-sidebar-banner' ) ); ?>
        </form>

        <div class="tmw-cr-actions-row">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_test_connection' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_test_connection" />
                <?php submit_button( __( 'Test Connection', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_sync_offers' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_sync_offers" />
                <?php submit_button( __( 'Sync Offers Now', 'tmw-cr-slot-sidebar-banner' ), 'primary', 'submit', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_sync_stats' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_sync_stats" />
                <input type="hidden" name="stats_sync_range" value="<?php echo esc_attr( (string) $settings['stats_sync_range'] ); ?>" />
                <?php submit_button( __( 'Sync Stats Now', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
            </form>
            <p>
                <strong><?php esc_html_e( 'Last sync', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong>
                <?php echo ! empty( $sync_meta['last_synced_at'] ) ? esc_html( (string) $sync_meta['last_synced_at'] ) : esc_html__( 'Never', 'tmw-cr-slot-sidebar-banner' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * @param string $label Label.
     * @param string $value Value.
     *
     * @return void
     */
    protected function render_summary_card( $label, $value ) {
        ?>
        <div class="tmw-cr-card">
            <p class="tmw-cr-card__label"><?php echo esc_html( $label ); ?></p>
            <p class="tmw-cr-card__value"><?php echo esc_html( $value ); ?></p>
        </div>
        <?php
    }

    /**
     * @param string $name Name.
     * @param string $value Value.
     * @param array<string,string> $options Options.
     *
     * @return void
     */
    protected function render_filter_select( $name, $value, $options ) {
        echo '<select name="' . esc_attr( $name ) . '">';
        foreach ( $options as $option_value => $label ) {
            echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /**
     * @param string            $name Filter name.
     * @param string            $label Label.
     * @param array<int,string> $selected Selected values.
     * @param array<string,string> $options Options.
     * @param bool              $searchable Searchable panel.
     *
     * @return void
     */
    protected function render_filter_panel( $name, $label, $selected, $options, $searchable ) {
        $count = count( $selected );
        ?>
        <div class="tmw-cr-filter-panel" data-filter-name="<?php echo esc_attr( $name ); ?>">
            <button type="button" class="button button-secondary tmw-cr-filter-panel__toggle" aria-expanded="false">
                <span><?php echo esc_html( $label ); ?></span>
                <span class="tmw-cr-filter-panel__count<?php echo $count > 0 ? '' : ' is-empty'; ?>"<?php echo 0 === $count ? ' hidden' : ''; ?>><?php echo $count > 0 ? esc_html( (string) $count ) : ''; ?></span>
            </button>
            <div class="tmw-cr-filter-panel__card" hidden>
                <div class="tmw-cr-filter-panel__title"><?php echo esc_html( $label ); ?></div>
                <div class="tmw-cr-filter-panel__actions">
                    <?php if ( $searchable ) : ?>
                        <input type="search" class="tmw-cr-filter-panel__search" placeholder="<?php esc_attr_e( 'Search…', 'tmw-cr-slot-sidebar-banner' ); ?>" />
                    <?php endif; ?>
                    <button type="button" class="button-link tmw-cr-filter-panel__clear"><?php esc_html_e( 'Clear All', 'tmw-cr-slot-sidebar-banner' ); ?></button>
                </div>
                <div class="tmw-cr-filter-panel__list">
                    <?php foreach ( $options as $option_value => $option_label ) : ?>
                        <label data-filter-label="<?php echo esc_attr( strtolower( $option_label ) ); ?>">
                            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( in_array( $option_value, $selected, true ) ); ?> />
                            <span><?php echo esc_html( $option_label ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<int,string>       $values Values.
     * @param array<string,string>|null $override_labels Label map.
     *
     * @return array<string,string>
     */
    protected function build_filter_option_map( $values, $override_labels = null ) {
        $options = array();
        foreach ( $values as $value ) {
            $value = trim( (string) $value );
            if ( '' === $value ) {
                continue;
            }
            $options[ $value ] = isset( $override_labels[ $value ] ) ? $override_labels[ $value ] : ( strtoupper( $value ) === $value ? $value : ucwords( str_replace( array( '_', '-' ), ' ', $value ) ) );
        }

        return $options;
    }

    /**
     * @param string $key Query key.
     * @param bool   $force_uppercase Uppercase items.
     *
     * @return array<int,string>
     */
    protected function read_multi_query_values( $key, $force_uppercase = false ) {
        if ( ! isset( $_GET[ $key ] ) ) {
            return array();
        }
        $raw = wp_unslash( $_GET[ $key ] );
        $raw = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
        $values = array();
        foreach ( $raw as $value ) {
            $value = sanitize_text_field( (string) $value );
            $value = trim( $value );
            if ( '' === $value ) {
                continue;
            }
            $values[] = $force_uppercase ? strtoupper( $value ) : $value;
        }

        return array_values( array_unique( $values ) );
    }

    /**
     * @param string $key Query key.
     *
     * @return string
     */
    protected function read_scalar_query_value( $key ) {
        if ( ! isset( $_GET[ $key ] ) ) {
            return '';
        }
        $raw = wp_unslash( $_GET[ $key ] );
        if ( is_array( $raw ) ) {
            $raw = reset( $raw );
        }
        if ( is_array( $raw ) || is_object( $raw ) ) {
            return '';
        }
        $value = trim( sanitize_text_field( (string) $raw ) );
        return '' === $value ? '' : $value;
    }

    /**
     * @return array<string,mixed>
     */
    protected function read_offers_tab_filters_from_request() {
        return array(
            'search'            => isset( $_GET['search'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['search'] ) ) ) : '',
            'tag'               => $this->read_multi_query_values( 'tag' ),
            'vertical'          => $this->read_multi_query_values( 'vertical' ),
            'status'            => $this->read_scalar_query_value( 'status' ),
            'featured'          => $this->read_scalar_query_value( 'featured' ),
            'approval_required' => $this->read_scalar_query_value( 'approval_required' ),
            'payout_type'       => $this->read_multi_query_values( 'payout_type' ),
            'performs_in'       => $this->read_multi_query_values( 'performs_in', true ),
            'optimized_for'     => $this->read_multi_query_values( 'optimized_for' ),
            'accepted_country'  => $this->read_multi_query_values( 'accepted_country', true ),
            'niche'             => $this->read_multi_query_values( 'niche' ),
            'promotion_method'  => $this->read_multi_query_values( 'promotion_method' ),
            'image_status'      => $this->read_scalar_query_value( 'image_status' ),
            'logo_status'       => $this->read_scalar_query_value( 'logo_status' ),
        );
    }

    /**
     * @return array<string,string>
     */
    protected function get_country_options() {
        return array(
            'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain', 'BR' => 'Brazil', 'MX' => 'Mexico', 'JP' => 'Japan', 'IN' => 'India', 'NL' => 'Netherlands', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'IE' => 'Ireland', 'CH' => 'Switzerland', 'AT' => 'Austria', 'BE' => 'Belgium', 'PL' => 'Poland', 'PT' => 'Portugal', 'NZ' => 'New Zealand', 'ZA' => 'South Africa', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia', 'PE' => 'Peru', 'VE' => 'Venezuela', 'EC' => 'Ecuador', 'UY' => 'Uruguay', 'PY' => 'Paraguay', 'BO' => 'Bolivia', 'CR' => 'Costa Rica', 'PA' => 'Panama', 'DO' => 'Dominican Republic', 'PR' => 'Puerto Rico', 'GT' => 'Guatemala', 'SV' => 'El Salvador', 'HN' => 'Honduras', 'NI' => 'Nicaragua', 'JM' => 'Jamaica', 'TT' => 'Trinidad and Tobago', 'IS' => 'Iceland', 'LU' => 'Luxembourg', 'CZ' => 'Czechia', 'SK' => 'Slovakia', 'HU' => 'Hungary', 'RO' => 'Romania', 'BG' => 'Bulgaria', 'HR' => 'Croatia', 'SI' => 'Slovenia', 'GR' => 'Greece', 'TR' => 'Turkey', 'CY' => 'Cyprus', 'MT' => 'Malta', 'EE' => 'Estonia', 'LV' => 'Latvia', 'LT' => 'Lithuania', 'UA' => 'Ukraine', 'MD' => 'Moldova', 'RS' => 'Serbia', 'ME' => 'Montenegro', 'AL' => 'Albania', 'MK' => 'North Macedonia', 'BA' => 'Bosnia and Herzegovina', 'IL' => 'Israel', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar', 'KW' => 'Kuwait', 'BH' => 'Bahrain', 'OM' => 'Oman', 'JO' => 'Jordan', 'LB' => 'Lebanon', 'EG' => 'Egypt', 'MA' => 'Morocco', 'DZ' => 'Algeria', 'TN' => 'Tunisia', 'KE' => 'Kenya', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'UG' => 'Uganda', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'ET' => 'Ethiopia', 'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'LK' => 'Sri Lanka', 'NP' => 'Nepal', 'TH' => 'Thailand', 'VN' => 'Vietnam', 'MY' => 'Malaysia', 'SG' => 'Singapore', 'PH' => 'Philippines', 'ID' => 'Indonesia', 'KR' => 'South Korea', 'TW' => 'Taiwan', 'HK' => 'Hong Kong', 'CN' => 'China'
        );
    }

    /**
     * @param string $key Column key.
     * @param string $label Label.
     * @param array<string,mixed> $args Args.
     *
     * @return void
     */
    protected function render_sort_link_header( $key, $label, $args ) {
        $order     = ( isset( $args['sort_by'] ) && $key === $args['sort_by'] && 'asc' === $args['sort_order'] ) ? 'desc' : 'asc';
        $url_args  = array_merge( $args, array( 'sort_by' => $key, 'sort_order' => $order, 'paged' => 1 ) );
        $url_args['tab']  = 'offers';
        $url_args['page'] = 'tmw-cr-slot-sidebar-banner';

        echo '<th><a href="' . esc_url( add_query_arg( $url_args, admin_url( 'options-general.php' ) ) ) . '">' . esc_html( $label ) . '</a></th>';
    }

    /**
     * @param int $current Current page.
     * @param int $total Total pages.
     * @param array<string,mixed> $args Query args.
     *
     * @return void
     */
    protected function render_pagination( $current, $total, $args ) {
        if ( $total <= 1 ) {
            return;
        }

        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ( $page = 1; $page <= $total; $page++ ) {
            $url_args           = $args;
            $url_args['page']   = 'tmw-cr-slot-sidebar-banner';
            $url_args['tab']    = 'offers';
            $url_args['paged']  = $page;
            $url                = add_query_arg( $url_args, admin_url( 'options-general.php' ) );
            $class              = $page === $current ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $page ) . '</a> ';
        }
        echo '</div></div>';
    }

    /**
     * Read a positive integer page value from the query string.
     *
     * @param string $key Query parameter key.
     * @param int    $default Fallback page number.
     * @return int
     */
    protected function get_positive_query_int( $key, $default = 1 ) {
        if ( ! isset( $_GET[ $key ] ) ) {
            return max( 1, (int) $default );
        }

        $raw_value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
        if ( '' === $raw_value || ! ctype_digit( $raw_value ) ) {
            return max( 1, (int) $default );
        }

        $value = (int) $raw_value;
        return $value > 0 ? $value : max( 1, (int) $default );
    }

    /**
     * Paginate an in-memory audit row array for admin rendering.
     *
     * @param array<int,array<string,mixed>> $rows Rows to slice.
     * @param int $page Requested page.
     * @param int $per_page Rows per page.
     * @return array<string,mixed>
     */
    protected function paginate_rows( array $rows, $page, $per_page = 25 ) {
        $total_rows  = count( $rows );
        $per_page    = max( 1, (int) $per_page );
        $total_pages = max( 1, (int) ceil( $total_rows / $per_page ) );
        $page        = max( 1, (int) $page );
        if ( $page > $total_pages ) {
            $page = $total_pages;
        }

        return array(
            'rows'         => array_slice( $rows, ( $page - 1 ) * $per_page, $per_page ),
            'current_page' => $page,
            'total_pages'  => $total_pages,
        );
    }

    /**
     * Render Slot Setup audit pagination controls with preserved query args.
     *
     * @param int $current_page Current page number.
     * @param int $total_pages Total available pages.
     * @param string $page_arg Query key for the paged audit.
     * @param array<int,string> $preserve_args Extra query args to preserve.
     * @return void
     */
    protected function render_audit_pagination( $current_page, $total_pages, $page_arg, $preserve_args = array() ) {
        if ( $total_pages <= 1 ) {
            return;
        }
        $base_args = array(
            'page' => 'tmw-cr-slot-sidebar-banner',
            'tab'  => 'slot-setup',
        );
        foreach ( $preserve_args as $arg ) {
            if ( isset( $_GET[ $arg ] ) ) {
                $base_args[ $arg ] = sanitize_text_field( wp_unslash( $_GET[ $arg ] ) );
            }
        }
        echo '<div class="tablenav"><div class="tablenav-pages">';
        if ( $current_page > 1 ) {
            $prev_url = add_query_arg( array_merge( $base_args, array( $page_arg => $current_page - 1 ) ), admin_url( 'options-general.php' ) );
            echo '<a class="button" href="' . esc_url( $prev_url ) . '">Previous</a> ';
        } else {
            echo '<span class="button disabled">Previous</span> ';
        }
        for ( $p = max( 1, $current_page - 2 ); $p <= min( $total_pages, $current_page + 2 ); $p++ ) {
            $page_url = add_query_arg( array_merge( $base_args, array( $page_arg => $p ) ), admin_url( 'options-general.php' ) );
            $class = $p === $current_page ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $page_url ) . '">' . esc_html( (string) $p ) . '</a> ';
        }
        if ( $current_page < $total_pages ) {
            $next_url = add_query_arg( array_merge( $base_args, array( $page_arg => $current_page + 1 ) ), admin_url( 'options-general.php' ) );
            echo '<a class="button" href="' . esc_url( $next_url ) . '">Next</a>';
        } else {
            echo '<span class="button disabled">Next</span>';
        }
        echo '</div></div>';
    }

    /**
     * Apply PPS audit filter/search for admin-only reporting rows.
     *
     * @param array<int,array<string,mixed>> $rows Full PPS audit rows.
     * @param string $filter Filter slug.
     * @param string $search Case-insensitive search token.
     * @return array<int,array<string,mixed>>
     */
    protected function apply_pps_audit_filter( array $rows, $filter, $search ) {
        $allowed_filters = array( 'all', 'frontend_ready_only', 'missing_cta', 'missing_country_override', 'missing_logo', 'blocked_by_business_rule', 'override_only', 'synced' );
        if ( ! in_array( $filter, $allowed_filters, true ) ) {
            $filter = 'all';
        }
        $search = strtolower( (string) $search );
        $filtered = array_filter(
            $rows,
            function( $row ) use ( $filter, $search ) {
                $source = (string) ( $row['source'] ?? '' );
                $block_reason = (string) ( $row['block_reason'] ?? '' );
                $frontend_ready = strtolower( (string) ( $row['frontend_ready'] ?? '' ) );
                $matches_filter = true;
                if ( 'frontend_ready_only' === $filter ) {
                    $matches_filter = in_array( $frontend_ready, array( 'yes', 'true', '1' ), true );
                } elseif ( 'missing_cta' === $filter ) {
                    $matches_filter = 'missing_valid_cta' === $block_reason;
                } elseif ( 'missing_country_override' === $filter ) {
                    $matches_filter = 'missing_allowed_country_override' === $block_reason;
                } elseif ( 'missing_logo' === $filter ) {
                    $matches_filter = 'missing_logo' === $block_reason;
                } elseif ( 'blocked_by_business_rule' === $filter ) {
                    $matches_filter = in_array( $block_reason, array( 'business_rule_blocked', 'unavailable_account_offer' ), true );
                } elseif ( 'override_only' === $filter ) {
                    $matches_filter = in_array( $source, array( 'manual_override_only', 'override_only' ), true );
                } elseif ( 'synced' === $filter ) {
                    $matches_filter = 'synced' === $source;
                }
                if ( ! $matches_filter ) {
                    return false;
                }
                if ( '' === $search ) {
                    return true;
                }
                $haystack = strtolower( (string) ( $row['offer_id'] ?? '' ) . ' ' . (string) ( $row['offer_name'] ?? '' ) );
                return false !== strpos( $haystack, $search );
            }
        );
        return array_values( $filtered );
    }

    /**
     * @param array<string,mixed> $offer Offer.
     *
     * @return string
     */
    protected function format_payout( $offer ) {
        $parts = array_filter(
            array(
                ! empty( $offer['default_payout'] ) ? (string) $offer['default_payout'] : '',
                ! empty( $offer['percent_payout'] ) ? (string) $offer['percent_payout'] . '%' : '',
                ! empty( $offer['currency'] ) ? (string) $offer['currency'] : '',
                ! empty( $offer['payout_type'] ) ? (string) $offer['payout_type'] : '',
            )
        );

        return ! empty( $parts ) ? implode( ' / ', $parts ) : '-';
    }

    /**
     * @param array<string,mixed> $result Filtered result payload.
     * @param array<string,mixed> $args Request args.
     * @param array<string,string> $payout_labels Label map.
     *
     * @return array<string,string>
     */
    protected function build_offers_count_summary( $result, $args, $payout_labels, $reconciliation_counts = array() ) {
        $visible_count = count( (array) ( $result['items'] ?? array() ) );
        $source_total  = isset( $result['source_total'] ) ? (int) $result['source_total'] : (int) $visible_count;
        $matched_total = isset( $result['total'] ) ? (int) $result['total'] : (int) $visible_count;
        $has_filters   = ! empty( $result['active_filters'] );
        $page          = max( 1, (int) ( $result['page'] ?? 1 ) );
        $per_page      = max( 1, (int) ( $result['per_page'] ?? 25 ) );
        $headline      = '';

        if ( $has_filters ) {
            if ( $matched_total <= 0 ) {
                $headline = sprintf( 'Showing 0 of 0 matched offers from %d synced offers', $source_total );
            } elseif ( $visible_count <= 0 ) {
                $headline = sprintf( 'Showing 0 on this page of %1$d matched offers from %2$d synced offers', $matched_total, $source_total );
            } else {
                $first = (int) ( ( $page - 1 ) * $per_page ) + 1;
                if ( $first > $matched_total ) {
                    $headline = sprintf( 'Showing 0 on this page of %1$d matched offers from %2$d synced offers', $matched_total, $source_total );
                } else {
                    $last     = min( (int) ( $first + $visible_count - 1 ), $matched_total );
                    $headline = sprintf( 'Showing %1$d–%2$d of %3$d matched offers from %4$d synced offers', $first, $last, $matched_total, $source_total );
                }
            }
        } else {
            $headline = sprintf( 'Showing %1$d of %2$d synced offers', $visible_count, $source_total );
        }

        $context = '';
        $payout_values = isset( $args['payout_type'] ) ? (array) $args['payout_type'] : array();
        if ( ! empty( $payout_values ) ) {
            $labels = array();
            foreach ( $payout_values as $value ) {
                $value = $this->normalize_payout_summary_value( (string) $value );
                if ( '' === $value ) {
                    continue;
                }
                $labels[] = isset( $payout_labels[ $value ] ) ? $payout_labels[ $value ] : strtoupper( $value );
            }
            if ( ! empty( $labels ) ) {
                $context = sprintf( 'Payout Type: %1$s — %2$d admin-filter matched from %3$d synced offers', implode( ', ', $labels ), $matched_total, $source_total );
                foreach ( $payout_values as $value ) {
                    $normalized = $this->normalize_payout_summary_value( (string) $value );
                    if ( '' === $normalized ) {
                        continue;
                    }
                    $label = isset( $payout_labels[ $normalized ] ) ? $payout_labels[ $normalized ] : strtoupper( $normalized );
                    $raw_count = (int) ( $this->get_reconciliation_family_count( $reconciliation_counts, 'raw', $normalized ) );
                    $detected_count = (int) ( $this->get_reconciliation_family_count( $reconciliation_counts, 'detected', $normalized ) );
                    $fallback_group_count = (int) $this->get_reconciliation_group_family_count( $reconciliation_counts, $normalized );
                    $comparison_count = (int) ( $this->get_reconciliation_family_count( $reconciliation_counts, 'cr_ui_label_comparison', $normalized ) );
                    $extras_count = max( 0, (int) ( $this->get_reconciliation_family_count( $reconciliation_counts, 'admin_filter', $normalized ) - $comparison_count ) );
                    $context .= sprintf( ' | CR UI-label comparison %1$s rows: %2$d | Local fallback/smartlink %1$s extras: %3$d', $label, $comparison_count, $extras_count );
                    if ( 'revshare_lifetime' === $normalized ) {
                        $context .= sprintf( ' | Raw cpa_flat rows mapped locally: %d', (int) ( $reconciliation_counts['raw']['cpa_flat'] ?? 0 ) );
                    }
                }
            }
        }

        return array(
            'headline' => $headline,
            'context'  => $context,
        );
    }

    protected function get_reconciliation_family_count( $counts, $bucket, $family ) {
        $bucket = sanitize_key( (string) $bucket );
        $family = sanitize_key( (string) $family );
        if ( 'raw' === $bucket ) {
            $raw_map = array(
                'multi_cpa' => 'cpa',
                'revshare' => 'cpa_percentage',
                'revshare_lifetime' => 'cpa_flat',
                'smartlink' => 'smartlink',
                'fallback' => 'fallback',
            );
            $raw_key = isset( $raw_map[ $family ] ) ? $raw_map[ $family ] : $family;
            return (int) ( $counts['raw'][ $raw_key ] ?? 0 );
        }
        return (int) ( $counts[ $bucket ][ $family ] ?? 0 );
    }

    protected function render_payout_reconciliation_panel( $counts, $payout_labels ) {
        $families = array( 'pps', 'soi', 'doi', 'cpc', 'cpi', 'cpm', 'multi_cpa', 'revshare', 'revshare_lifetime', 'fallback', 'smartlink' );
        $source_class = (array) ( $counts['source_class'] ?? array() );
        $group_fallback_rows = (int) ( $source_class['group_fallback'] ?? 0 ) + (int) ( $source_class['fallback'] ?? 0 );
        ?>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e( 'Payout count reconciliation', 'tmw-cr-slot-sidebar-banner' ); ?></strong></p>
            <p class="description"><?php esc_html_e( 'These counts use local synced data. CrakRevenue website counts may differ because local counts include synced fallback/group rows and normalized detected payout families.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <p><?php echo esc_html( sprintf( 'Total synced offers: %d', (int) ( $counts['source_total'] ?? 0 ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Normal offers: %d', (int) ( $source_class['normal_offer'] ?? 0 ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Group fallback/fallback rows: %d', $group_fallback_rows ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Smartlink rows: %d', (int) ( $source_class['smartlink'] ?? 0 ) ) ); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Payout family', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'API payout_type raw count', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Detected local type', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Admin filter count', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'CR UI-label comparison', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Local fallback/smartlink extras', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $families as $family ) : ?>
                        <?php $label = isset( $payout_labels[ $family ] ) ? $payout_labels[ $family ] : strtoupper( $family ); ?>
                        <tr>
                            <td><?php echo esc_html( $label ); ?></td>
                            <td><?php echo esc_html( (string) $this->get_reconciliation_family_count( $counts, 'raw', $family ) ); ?></td>
                            <td><?php echo esc_html( (string) $this->get_reconciliation_family_count( $counts, 'detected', $family ) ); ?></td>
                            <td><?php echo esc_html( (string) $this->get_reconciliation_family_count( $counts, 'admin_filter', $family ) ); ?></td>
                            <?php $comparison_count = (int) $this->get_reconciliation_family_count( $counts, 'cr_ui_label_comparison', $family ); ?>
                            <td><?php echo esc_html( (string) $comparison_count ); ?></td>
                            <td><?php echo esc_html( (string) max( 0, (int) $this->get_reconciliation_family_count( $counts, 'admin_filter', $family ) - $comparison_count ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">
                <?php esc_html_e( 'API payout_type is the CR API calculation method. It is not always the same as the CrakRevenue dashboard Payout Type label. CR UI-label comparison excludes local group fallback, fallback, smartlink, and unknown rows.', 'tmw-cr-slot-sidebar-banner' ); ?><br />
                <?php esc_html_e( 'API payout_type: The raw API calculation method, such as cpa_flat, cpa_percentage, cpc, cpm.', 'tmw-cr-slot-sidebar-banner' ); ?><br />
                <?php esc_html_e( 'Detected local type: Local inferred CR UI-style payout label from offer names/type keys.', 'tmw-cr-slot-sidebar-banner' ); ?><br />
                <?php esc_html_e( 'Admin filter count: Current local filter matching family.', 'tmw-cr-slot-sidebar-banner' ); ?><br />
                <?php esc_html_e( 'CR UI-label comparison: Local approximation for comparing with the CrakRevenue website dashboard.', 'tmw-cr-slot-sidebar-banner' ); ?>
            </p>
        </div>
        <?php
    }

    protected function get_reconciliation_group_family_count( $counts, $family ) {
        $family = sanitize_key( (string) $family );
        $values = (array) ( $counts['group_admin_filter'] ?? array() );
        return (int) ( $values[ $family ] ?? 0 );
    }

    protected function render_cr_fixture_reconciliation_panel( $active_filters = array() ) {
        $audit = $this->offer_repository->get_cr_fixture_reconciliation_audit();
        ?>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e( 'CR CSV vs local offer ID reconciliation', 'tmw-cr-slot-sidebar-banner' ); ?></strong></p>
            <p class="description"><?php esc_html_e( 'Compares the parsed CrakRevenue dashboard CSV fixture against locally synced offers by offer ID. This is read-only audit data. It does not affect syncing, filters, or frontend banner eligibility.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <?php if ( empty( $audit['fixture_available'] ) ) : ?>
                <p><em><?php esc_html_e( 'CR fixture not found; ID reconciliation unavailable.', 'tmw-cr-slot-sidebar-banner' ); ?></em></p>
                </div><?php return; ?>
            <?php endif; ?>
            <p><?php echo esc_html( sprintf( 'CR fixture rows: %d', (int) ( $audit['fixture_rows'] ?? 0 ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'CR unique IDs: %d', (int) ( $audit['fixture_unique_ids'] ?? 0 ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Local synced rows: %d', (int) ( $audit['local_total_synced'] ?? 0 ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Matched IDs: %d', (int) ( $audit['matched_ids'] ?? 0 ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'CR IDs missing locally: %d', count( (array) ( $audit['cr_missing_locally'] ?? array() ) ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Local normal offers missing from CR fixture: %d', count( (array) ( $audit['local_normal_missing_from_cr'] ?? array() ) ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Local fallback/group fallback rows missing from CR fixture: %d', count( (array) ( $audit['local_fallback_missing_from_cr'] ?? array() ) ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Local smartlink rows missing from CR fixture: %d', count( (array) ( $audit['local_smartlink_missing_from_cr'] ?? array() ) ) ) ); ?></p>
            <p><?php echo esc_html( sprintf( 'Payout label mismatches: %d', count( (array) ( $audit['payout_label_mismatches'] ?? array() ) ) ) ); ?></p>
            <?php if ( ! empty( $active_filters ) ) : ?>
                <p class="description"><?php esc_html_e( 'Detailed reconciliation tables are hidden while Offers filters are active.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <?php else : ?>
                <?php $this->render_cr_fixture_reconciliation_table( 'CR IDs missing locally', (array) ( $audit['cr_missing_locally'] ?? array() ) ); ?>
                <?php $this->render_cr_fixture_reconciliation_table( 'Local normal offers missing from CR fixture', (array) ( $audit['local_normal_missing_from_cr'] ?? array() ) ); ?>
                <?php $this->render_cr_fixture_reconciliation_table( 'Payout label mismatches', (array) ( $audit['payout_label_mismatches'] ?? array() ) ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    protected function render_cr_fixture_reconciliation_table( $title, $rows ) {
        $limit = 25;
        ?>
        <p><strong><?php echo esc_html( $title ); ?></strong><?php echo count( $rows ) > $limit ? esc_html( ' (showing first 25)' ) : ''; ?></p>
        <table class="widefat striped">
            <thead><tr><th>ID</th><th>CR name</th><th>Local name</th><th>CR payout_type</th><th>Local raw payout_type</th><th>Detected/admin families</th><th>source_class</th><th>note</th></tr></thead>
            <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="8">None</td></tr>
                <?php else : foreach ( array_slice( $rows, 0, $limit ) as $row ) : ?>
                    <?php $families = array_merge( (array) ( $row['local_detected_type_keys'] ?? array() ), (array) ( $row['local_admin_filter_families'] ?? array() ) ); ?>
                    <tr>
                        <td><code><?php echo esc_html( (string) ( $row['cr_id'] ?? $row['id'] ?? '' ) ); ?></code></td>
                        <td><?php echo esc_html( (string) ( $row['cr_name'] ?? $row['name'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $row['local_name'] ?? $row['name'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $row['cr_payout_type'] ?? $row['payout_type'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $row['local_raw_payout_type'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( implode( ', ', array_unique( array_filter( array_map( 'sanitize_key', $families ) ) ) ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $row['source_class'] ?? '' ) ); ?></td>
                        <td><?php echo esc_html( (string) ( $row['note'] ?? '' ) ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param string $value Raw payout filter value.
     *
     * @return string
     */
    protected function normalize_payout_summary_value( $value ) {
        $value = sanitize_key( strtolower( trim( str_replace( array( ' ', '-' ), '_', (string) $value ) ) ) );
        $aliases = array(
            'cpa' => 'multi_cpa',
            'multi_cpa' => 'multi_cpa',
            'cpa_flat' => 'revshare_lifetime',
            'pps' => 'pps',
            'soi' => 'soi',
            'doi' => 'doi',
            'cpc' => 'cpc',
            'cpi' => 'cpi',
            'cpm' => 'cpm',
            'revshare' => 'revshare',
            'revshare_lifetime' => 'revshare_lifetime',
            'fallback' => 'fallback',
        );
        return isset( $aliases[ $value ] ) ? $aliases[ $value ] : $value;
    }

    /**
     * @param string $label Label.
     * @param string $variant Variant.
     *
     * @return void
     */
    protected function render_badge( $label, $variant ) {
        echo '<span class="tmw-cr-badge tmw-cr-badge--' . esc_attr( sanitize_key( $variant ) ) . '">' . esc_html( $label ) . '</span>';
    }

    /**
     * @param string $status Image status.
     *
     * @return void
     */
    protected function render_image_status_badge( $status ) {
        $status = sanitize_key( (string) $status );

        $map = array(
            'manual_override' => array( 'label' => 'manual', 'variant' => 'featured' ),
            'auto_local'      => array( 'label' => 'auto-local', 'variant' => 'selected' ),
            'auto_remote'     => array( 'label' => 'auto-remote', 'variant' => 'approval' ),
            'placeholder_only' => array( 'label' => 'placeholder', 'variant' => 'muted' ),
        );

        $badge = isset( $map[ $status ] ) ? $map[ $status ] : $map['placeholder_only'];
        $this->render_badge( $badge['label'], $badge['variant'] );
    }

    /**
     * @return TMW_CR_Slot_CR_API_Client
     */
    protected function build_api_client() {
        $settings = TMW_CR_Slot_Sidebar_Banner::get_settings();

        return new TMW_CR_Slot_CR_API_Client( (string) $settings['cr_api_key'] );
    }

    /**
     * @param string $notice_type Notice type.
     * @param string $message     Message.
     *
     * @return void
     */
    protected function redirect_with_notice( $notice_type, $message ) {
        $tab = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['tab'] ) ) : 'overview';
        if ( ! in_array( $tab, array( 'overview', 'offers', 'performance', 'slot-setup', 'settings' ), true ) ) {
            $tab = 'overview';
        }
        $this->redirect_with_notice_to_tab( $notice_type, $message, $tab );
    }

    protected function redirect_with_notice_to_tab( $notice_type, $message, $tab_slug = 'overview' ) {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                => 'tmw-cr-slot-sidebar-banner',
                    'tab'                 => sanitize_key( $tab_slug ),
                    'tmw_cr_slot_notice'  => sanitize_key( $notice_type ),
                    'tmw_cr_slot_message' => $message,
                ),
                admin_url( 'options-general.php' )
            )
        );
        exit;
    }

    /**
     * @param string $nonce_action Nonce action.
     *
     * @return void
     */
    protected function assert_admin_action( $nonce_action, $custom_nonce_field = '' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        if ( isset( $_REQUEST['_wpnonce'] ) ) {
            check_admin_referer( $nonce_action );

            return;
        }

        if ( '' !== $custom_nonce_field && isset( $_REQUEST[ $custom_nonce_field ] ) ) {
            check_admin_referer( $nonce_action, $custom_nonce_field );

            return;
        }

        check_admin_referer( $nonce_action );
    }
}
