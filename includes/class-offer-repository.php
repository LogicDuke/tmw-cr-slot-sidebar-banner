<?php
/**
 * Local storage and frontend normalization for synced offers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Offer_Repository {
    const ALLOWED_OFFER_TYPES = array( 'pps', 'revshare', 'soi', 'doi', 'cpa', 'cpl', 'cpc', 'cpi', 'cpm', 'smartlink', 'fallback' );
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
    /** @var string */
    protected $offers_option_key;

    /** @var string */
    protected $meta_option_key;

    /** @var string */
    protected $overrides_option_key;

    /** @var string */
    protected $stats_option_key;

    /** @var string */
    protected $stats_meta_option_key;
    /** @var string */
    protected $dashboard_meta_option_key;

    /** @var string */
    protected $skipped_offers_option_key;

    /**
     * @param string $offers_option_key     Option key for synced offers.
     * @param string $meta_option_key       Option key for sync meta.
     * @param string $overrides_option_key  Option key for offer overrides.
     */
    public function __construct( $offers_option_key, $meta_option_key, $overrides_option_key = 'tmw_cr_slot_banner_offer_overrides', $stats_option_key = 'tmw_cr_slot_banner_offer_stats', $stats_meta_option_key = 'tmw_cr_slot_banner_offer_stats_meta', $dashboard_meta_option_key = 'tmw_cr_slot_banner_offer_dashboard_meta', $skipped_offers_option_key = 'tmw_cr_slot_banner_skipped_offers' ) {
        $this->offers_option_key    = $offers_option_key;
        $this->meta_option_key      = $meta_option_key;
        $this->overrides_option_key = $overrides_option_key;
        $this->stats_option_key     = $stats_option_key;
        $this->stats_meta_option_key = $stats_meta_option_key;
        $this->dashboard_meta_option_key = $dashboard_meta_option_key;
        $this->skipped_offers_option_key = $skipped_offers_option_key;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function get_synced_offers() {
        $offers = get_option( $this->offers_option_key, array() );

        return is_array( $offers ) ? $offers : array();
    }

    /**
     * @param array<string,array<string,mixed>> $offers Offers to store.
     *
     * @return void
     */
    public function save_synced_offers( $offers ) {
        update_option( $this->offers_option_key, $offers, false );
        $this->sync_dashboard_metadata_layer( $offers );
    }

    /**
     * @return array<string,array<string,array<int,string>>>
     */
    public function get_dashboard_metadata_layer() {
        $payload = get_option( $this->dashboard_meta_option_key, array() );
        if ( ! is_array( $payload ) ) {
            return array();
        }
        $clean = array();
        foreach ( $payload as $offer_id => $families ) {
            $offer_id = sanitize_text_field( (string) $offer_id );
            if ( '' === $offer_id || ! is_array( $families ) ) {
                continue;
            }
            $clean[ $offer_id ] = array(
                'tag' => $this->sanitize_list_values( isset( $families['tag'] ) ? $families['tag'] : array() ),
                'vertical' => $this->sanitize_list_values( isset( $families['vertical'] ) ? $families['vertical'] : array() ),
                'performs_in' => $this->sanitize_country_codes( isset( $families['performs_in'] ) ? $families['performs_in'] : array() ),
                'optimized_for' => $this->sanitize_list_values( isset( $families['optimized_for'] ) ? $families['optimized_for'] : array() ),
                'accepted_country' => $this->sanitize_country_codes( isset( $families['accepted_country'] ) ? $families['accepted_country'] : array() ),
                'niche' => $this->sanitize_list_values( isset( $families['niche'] ) ? $families['niche'] : array() ),
                'promotion_method' => $this->sanitize_list_values( isset( $families['promotion_method'] ) ? $families['promotion_method'] : array() ),
            );
        }
        return $clean;
    }

    /**
     * @return array<string,mixed>
     */
    /**
     * @return array<string,array<string,string>>
     */
    public function get_skipped_offers() {
        $rows = get_option( $this->skipped_offers_option_key, array() );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $clean = array();
        foreach ( $rows as $key => $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $offer_id = sanitize_text_field( (string) ( $row['offer_id'] ?? $key ) );
            $offer_name = sanitize_text_field( (string) ( $row['offer_name'] ?? '' ) );
            $decision = sanitize_key( (string) ( $row['decision'] ?? 'skip' ) );
            $reason = sanitize_text_field( (string) ( $row['reason'] ?? '' ) );
            $notes = $this->sanitize_skipped_offer_notes( (string) ( $row['notes'] ?? '' ) );
            $updated_at = sanitize_text_field( (string) ( $row['updated_at'] ?? '' ) );

            if ( '' === $offer_id ) {
                continue;
            }
            if ( ! in_array( $decision, array( 'skip', 'review_later' ), true ) ) {
                $decision = 'skip';
            }

            $clean[ $offer_id ] = array(
                'offer_id' => $offer_id,
                'offer_name' => $offer_name,
                'decision' => $decision,
                'reason' => $reason,
                'notes' => $notes,
                'updated_at' => $updated_at,
            );
        }

        return $clean;
    }
    
    /**
     * Returns normalized skipped offer IDs used for frontend exclusion.
     *
     * @return array<string,array<string,string>>
     */
    public function get_skipped_offer_ids_for_frontend() {
        $rows = get_option( $this->skipped_offers_option_key, array() );
        $set  = array();

        foreach ( (array) $rows as $key => $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $raw_offer_id = array_key_exists( 'offer_id', $row ) ? (string) $row['offer_id'] : (string) $key;
            $offer_id     = trim( sanitize_text_field( $raw_offer_id ) );
            if ( '' === $offer_id ) {
                continue;
            }

            $decision = sanitize_key( (string) ( $row['decision'] ?? '' ) );
            if ( 'skip' !== $decision ) {
                continue;
            }

            $set[ $offer_id ] = array(
                'reason'   => sanitize_text_field( (string) ( $row['reason'] ?? '' ) ),
                'decision' => 'skip',
            );
        }

        return $set;
    }

    /**
     * @param string                               $offer_id Offer id.
     * @param array<string,array<string,string>>   $skipped_offer_ids Skipped set.
     *
     * @return bool
     */
    protected function is_offer_skipped_for_frontend( $offer_id, $skipped_offer_ids ) {
        $offer_id = trim( (string) $offer_id );
        return '' !== $offer_id && isset( $skipped_offer_ids[ $offer_id ] );
    }

    /**
     * @param string                               $offer_id Offer id.
     * @param array<string,array<string,string>>   $skipped_offer_ids Skipped set.
     * @param int                                  $excluded_during_pool_build Excluded counter.
     *
     * @return bool
     */
    protected function should_exclude_skipped_frontend_offer( $offer_id, $skipped_offer_ids, &$excluded_during_pool_build ) {
        $offer_id = trim( (string) $offer_id );
        if ( '' === $offer_id || ! isset( $skipped_offer_ids[ $offer_id ] ) ) {
            return false;
        }
        ++$excluded_during_pool_build;
        if ( function_exists( 'error_log' ) ) {
            $skip = $skipped_offer_ids[ $offer_id ];
            error_log( sprintf( '[TMW-BANNER-SKIP] skipped_offer_excluded offer_id=%s decision=%s reason="%s"', sanitize_text_field( $offer_id ), sanitize_key( (string) ( $skip['decision'] ?? 'skip' ) ), sanitize_text_field( (string) ( $skip['reason'] ?? '' ) ) ) );
        }
        return true;
    }
    public function get_skipped_offer( $offer_id ) {
        $offer_id = sanitize_text_field( (string) $offer_id );
        if ( '' === $offer_id ) { return null; }
        $all = $this->get_skipped_offers();
        return isset( $all[ $offer_id ] ) ? $all[ $offer_id ] : null;
    }

    /**
     * @param array<int,array<string,mixed>> $rows Rows to save.
     *
     * @return void
     */
    public function save_skipped_offers( $rows ) {
        $clean = array();

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $offer_id = sanitize_text_field( (string) ( $row['offer_id'] ?? '' ) );
                if ( '' === $offer_id ) {
                    continue;
                }

                $decision = sanitize_key( (string) ( $row['decision'] ?? 'skip' ) );
                if ( ! in_array( $decision, array( 'skip', 'review_later' ), true ) ) {
                    $decision = 'skip';
                }
                $clean[ $offer_id ] = array(
                    'offer_id' => $offer_id,
                    'offer_name' => sanitize_text_field( (string) ( $row['offer_name'] ?? '' ) ),
                    'decision' => $decision,
                    'reason' => sanitize_text_field( (string) ( $row['reason'] ?? '' ) ),
                    'notes' => $this->sanitize_skipped_offer_notes( (string) ( $row['notes'] ?? '' ) ),
                    'updated_at' => sanitize_text_field( (string) ( $row['updated_at'] ?? gmdate( 'Y-m-d H:i:s' ) ) ),
                );
            }
        }

        update_option( $this->skipped_offers_option_key, $clean, false );
    }

    public function import_skipped_offers_csv( $csv_text ) {
        $lines = preg_split( '/\r\n|\r|\n/', (string) $csv_text );
        $header_map = array();
        $rows = $this->get_skipped_offers();
        $counts = array( 'imported' => 0, 'skipped' => 0 );
        foreach ( (array) $lines as $index => $line ) {
            if ( '' === trim( (string) $line ) ) { continue; }
            $cols = str_getcsv( (string) $line );
            if ( 0 === $index ) {
                foreach ( $cols as $i => $header ) {
                    $header_map[ sanitize_key( (string) $header ) ] = $i;
                }
                continue;
            }
            $offer_id = isset( $header_map['offer_id'] ) ? sanitize_text_field( (string) ( $cols[ $header_map['offer_id'] ] ?? '' ) ) : '';
            $decision = isset( $header_map['decision'] ) ? sanitize_key( (string) ( $cols[ $header_map['decision'] ] ?? '' ) ) : 'skip';
            $reason = isset( $header_map['reason'] ) ? sanitize_text_field( (string) ( $cols[ $header_map['reason'] ] ?? '' ) ) : '';
            if ( '' === $offer_id || '' === $reason ) { ++$counts['skipped']; continue; }
            if ( '' === $decision ) { $decision = 'skip'; }
            if ( ! in_array( $decision, array( 'skip', 'review_later' ), true ) ) { $decision = 'skip'; }
            $rows[ $offer_id ] = array(
                'offer_id' => $offer_id,
                'offer_name' => isset( $header_map['offer_name'] ) ? sanitize_text_field( (string) ( $cols[ $header_map['offer_name'] ] ?? '' ) ) : '',
                'decision' => $decision,
                'reason' => $reason,
                'notes' => isset( $header_map['notes'] ) ? $this->sanitize_skipped_offer_notes( (string) ( $cols[ $header_map['notes'] ] ?? '' ) ) : '',
                'updated_at' => gmdate( 'Y-m-d H:i:s' ),
            );
            ++$counts['imported'];
        }
        $this->save_skipped_offers( $rows );
        return $counts;
    }

    protected function sanitize_skipped_offer_notes( $notes ) {
        $clean = sanitize_textarea_field( (string) $notes );
        $clean = preg_replace( '/https?:\/\/\S+/i', '', $clean );
        $clean = preg_replace( '/\bwww\.\S+/i', '', (string) $clean );
        $clean = trim( preg_replace( '/\s+/', ' ', (string) $clean ) );
        return $clean;
    }

    public function get_sync_meta() {
        $meta = get_option( $this->meta_option_key, array() );

        return is_array( $meta ) ? $meta : array();
    }

    /**
     * @param array<string,mixed> $meta Sync metadata.
     *
     * @return void
     */
    public function save_sync_meta( $meta ) {
        $existing = $this->get_sync_meta();
        $defaults = array(
            'last_synced_at'      => '',
            'last_error'          => '',
            'offer_count'         => 0,
            'last_raw_row_count'  => 0,
            'last_imported_count' => 0,
            'last_skipped_count'  => 0,
            'last_response_shape' => '',
            'last_soft_failure'   => 0,
            'sample_row_keys'     => '',
        );

        $payload = wp_parse_args( (array) $meta, wp_parse_args( $existing, $defaults ) );

        $payload['last_synced_at']      = sanitize_text_field( (string) $payload['last_synced_at'] );
        $payload['last_error']          = sanitize_text_field( (string) $payload['last_error'] );
        $payload['offer_count']         = max( 0, (int) $payload['offer_count'] );
        $payload['last_raw_row_count']  = max( 0, (int) $payload['last_raw_row_count'] );
        $payload['last_imported_count'] = max( 0, (int) $payload['last_imported_count'] );
        $payload['last_skipped_count']  = max( 0, (int) $payload['last_skipped_count'] );
        $payload['last_response_shape'] = sanitize_text_field( (string) $payload['last_response_shape'] );
        $payload['last_soft_failure']   = ! empty( $payload['last_soft_failure'] ) ? 1 : 0;
        $payload['sample_row_keys']     = sanitize_text_field( (string) $payload['sample_row_keys'] );

        update_option( $this->meta_option_key, $payload, false );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function get_offer_stats() {
        $stats = get_option( $this->stats_option_key, array() );

        return is_array( $stats ) ? $stats : array();
    }

    /**
     * @param array<string,array<string,mixed>> $stats Stats rows.
     *
     * @return void
     */
    public function save_offer_stats( $stats ) {
        update_option( $this->stats_option_key, $stats, false );
    }

    /**
     * @return array<string,mixed>
     */
    public function get_stats_meta() {
        $meta = get_option( $this->stats_meta_option_key, array() );

        return is_array( $meta ) ? $meta : array();
    }

    /**
     * @param array<string,mixed> $meta Stats metadata.
     *
     * @return void
     */
    public function save_stats_meta( $meta ) {
        $existing = $this->get_stats_meta();
        $defaults = array(
            'last_stats_synced_at'      => '',
            'last_stats_error'          => '',
            'last_stats_raw_rows'       => 0,
            'last_stats_imported_rows'  => 0,
            'last_stats_date_start'     => '',
            'last_stats_date_end'       => '',
            'last_stats_response_shape' => '',
            'last_stats_sample_row_keys' => '',
            'last_stats_soft_failure'   => '',
            'last_stats_preserved_previous' => 0,
            'last_scheduled_run_at'     => '',
            'last_scheduled_result'     => '',
            'last_scheduled_message'    => '',
        );
        $payload  = wp_parse_args( (array) $meta, wp_parse_args( $existing, $defaults ) );

        $payload['last_stats_synced_at']      = sanitize_text_field( (string) $payload['last_stats_synced_at'] );
        $payload['last_stats_error']          = sanitize_text_field( (string) $payload['last_stats_error'] );
        $payload['last_stats_raw_rows']       = max( 0, (int) $payload['last_stats_raw_rows'] );
        $payload['last_stats_imported_rows']  = max( 0, (int) $payload['last_stats_imported_rows'] );
        $payload['last_stats_date_start']     = sanitize_text_field( (string) $payload['last_stats_date_start'] );
        $payload['last_stats_date_end']       = sanitize_text_field( (string) $payload['last_stats_date_end'] );
        $payload['last_stats_response_shape'] = sanitize_text_field( substr( (string) $payload['last_stats_response_shape'], 0, 120 ) );
        $payload['last_stats_sample_row_keys'] = sanitize_text_field( substr( (string) $payload['last_stats_sample_row_keys'], 0, 120 ) );
        $payload['last_stats_soft_failure']   = sanitize_key( substr( (string) $payload['last_stats_soft_failure'], 0, 64 ) );
        $payload['last_stats_preserved_previous'] = ! empty( $payload['last_stats_preserved_previous'] ) ? 1 : 0;
        $payload['last_scheduled_run_at']     = sanitize_text_field( (string) $payload['last_scheduled_run_at'] );
        $payload['last_scheduled_result']     = sanitize_text_field( (string) $payload['last_scheduled_result'] );
        $payload['last_scheduled_message']    = sanitize_text_field( (string) $payload['last_scheduled_message'] );

        update_option( $this->stats_meta_option_key, $payload, false );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function get_offer_overrides() {
        $overrides = get_option( $this->overrides_option_key, array() );
        if ( ! is_array( $overrides ) ) {
            return array();
        }

        $clean = array();

        foreach ( $overrides as $offer_id => $override ) {
            $offer_id = sanitize_text_field( (string) $offer_id );
            if ( '' === $offer_id || ! is_array( $override ) ) {
                continue;
            }

            $clean[ $offer_id ] = $this->sanitize_offer_override( $override );
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return array<int,string>
     */
    public function get_allowed_offer_types( $settings ) {
        return self::sanitize_allowed_offer_types( isset( $settings['allowed_offer_types'] ) ? $settings['allowed_offer_types'] : array() );
    }

    /**
     * @param array<string,mixed> $offer Offer payload.
     *
     * @return array<int,string>
     */
    public function get_offer_type_keys( $offer ) {
        $name_haystack = strtolower( (string) ( $offer['name'] ?? '' ) );
        $payout_haystack = strtolower( (string) ( $offer['payout_type'] ?? '' ) );
        $patterns = array(
            'fallback'  => '/\b(group\s+fallback|custom\s+fallback)\b/i',
            'smartlink' => '/\bsmartlink\b/i',
            'revshare'  => '/\brevshare(\s+lifetime)?\b/i',
            'soi'       => '/\bsoi\b/i',
            'doi'       => '/\bdoi\b/i',
            'cpa'       => '/\b(multi[\s-]*cpa|cpa)\b/i',
            'cpl'       => '/\b(ppl|cpl)\b/i',
            'cpc'       => '/\b(ppc|cpc)\b/i',
            'cpi'       => '/\bcpi\b/i',
            'cpm'       => '/\bcpm\b/i',
            'pps'       => '/\bpps\b/i',
        );

        $types = array();
        $positions = array();
        foreach ( $patterns as $key => $pattern ) {
            if ( preg_match( $pattern, $name_haystack, $matches, PREG_OFFSET_CAPTURE ) ) {
                $types[] = $key;
                $positions[ $key ] = (int) $matches[0][1];
                continue;
            }

            if ( preg_match( $pattern, $payout_haystack, $matches, PREG_OFFSET_CAPTURE ) ) {
                $types[] = $key;
                $positions[ $key ] = 10000 + (int) $matches[0][1];
            }
        }

        $types = array_values( array_unique( $types ) );
        usort(
            $types,
            static function ( $left, $right ) use ( $positions ) {
                return ( $positions[ $left ] ?? PHP_INT_MAX ) <=> ( $positions[ $right ] ?? PHP_INT_MAX );
            }
        );

        return $types;
    }

    public function is_offer_type_allowed( $offer, $settings ) {
        $allowed = $this->get_allowed_offer_types( $settings );
        $types   = $this->get_offer_type_keys( $offer );
        if ( empty( $types ) || empty( $allowed ) ) {
            return false;
        }
        return ! empty( array_intersect( $types, $allowed ) );
    }

    public static function sanitize_allowed_offer_types( $raw_types ) {
        $values = is_array( $raw_types ) ? $raw_types : array( $raw_types );
        $clean  = array();
        foreach ( $values as $value ) {
            $key = sanitize_key( (string) $value );
            if ( in_array( $key, self::ALLOWED_OFFER_TYPES, true ) ) {
                $clean[] = $key;
            }
        }
        $clean = array_values( array_unique( $clean ) );
        return empty( $clean ) ? array( 'pps' ) : $clean;
    }

    /**
     * @param array<string,array<string,mixed>> $overrides Offer overrides.
     *
     * @return void
     */
    public function save_offer_overrides( $overrides ) {
        $payload = array();

        foreach ( (array) $overrides as $offer_id => $override ) {
            $offer_id = sanitize_text_field( (string) $offer_id );
            if ( '' === $offer_id || ! is_array( $override ) ) {
                continue;
            }

            $payload[ $offer_id ] = $this->sanitize_offer_override( $override );
        }

        update_option( $this->overrides_option_key, $payload, false );
    }

    /**
     * @param string $offer_id Offer ID.
     *
     * @return array<string,mixed>
     */
    public function get_offer_override( $offer_id ) {
        $offer_id  = sanitize_text_field( (string) $offer_id );
        $overrides = $this->get_offer_overrides();

        return isset( $overrides[ $offer_id ] ) ? $overrides[ $offer_id ] : array();
    }

    /**
     * Returns sorted offers for admin display.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_synced_offers_for_admin() {
        $offers = array_values( $this->get_synced_offers() );

        usort(
            $offers,
            static function ( $left, $right ) {
                $left_featured  = ! empty( $left['is_featured'] ) ? 1 : 0;
                $right_featured = ! empty( $right['is_featured'] ) ? 1 : 0;

                if ( $left_featured !== $right_featured ) {
                    return $right_featured <=> $left_featured;
                }

                return strcasecmp( (string) ( $left['name'] ?? '' ), (string) ( $right['name'] ?? '' ) );
            }
        );

        return $offers;
    }


    /**
     * @param array<string,mixed> $offer Offer row.
     *
     * @return string
     */
    public function get_offer_logo_filename( $offer ) {
        $offer_name = isset( $offer['name'] ) ? (string) $offer['name'] : '';
        $brand_key  = $this->get_offer_brand_key( $offer_name );
        if ( '' === $brand_key ) {
            return '';
        }

        $map      = $this->get_offer_logo_filename_map();
        $expected = isset( $map[ $brand_key ] ) ? (string) $map[ $brand_key ] : '';
        if ( '' === $expected ) {
            return '';
        }

        $path = dirname( __DIR__ ) . '/assets/logos/80x80/' . $expected;
        if ( ! file_exists( $path ) ) {
            if ( function_exists( 'error_log' ) ) {
                $offer_id = sanitize_text_field( (string) ( $offer['id'] ?? '' ) );
                error_log( sprintf( '[TMW-BANNER-LOGO] missing_logo offer_id=%s brand_key=%s expected=%s', $offer_id, $brand_key, $expected ) );
            }
            return '';
        }

        return $expected;
    }

    /**
     * @param array<string,mixed> $offer Offer row.
     *
     * @return string
     */
    public function get_offer_logo_url( $offer ) {
        $filename = $this->get_offer_logo_filename( $offer );
        if ( '' === $filename ) {
            return '';
        }

        return TMW_CR_Slot_Sidebar_Banner::asset_url( 'assets/logos/80x80/' . $filename );
    }

    /**
     * @param string $offer_name Offer name.
     *
     * @return string
     */
    public function get_offer_brand_key( $offer_name ) {
        $needle = $this->normalize_offer_name_for_image_match( $offer_name );
        if ( '' === $needle ) {
            return '';
        }

        foreach ( $this->get_offer_brand_token_map() as $brand_key => $tokens ) {
            foreach ( $tokens as $token ) {
                if ( false !== strpos( $needle, (string) $token ) ) {
                    return (string) $brand_key;
                }
            }
        }

        return '';
    }

    /**
     * @return array<string,array<int,string>>
     */
    protected function get_offer_brand_token_map() {
        return array(
            'jerkmate' => array( 'jerkmate' ),
            'candy-ai' => array( 'candy ai', 'candyai', 'candy.ai' ),
            'girlfriend-gpt' => array( 'girlfriend gpt' ),
            'joi' => array( ' joi ', 'joi' ),
            'instabang' => array( 'instabang' ),
            'livejasmin' => array( 'live jasmin', 'livejasmin' ),
            'imlive' => array( 'imlive' ),
            'cam4' => array( 'cam4', 'cam 4' ),
            'xcams' => array( 'xcams' ),
            'xmatch' => array( 'xmatch' ),
            'sex-messenger' => array( 'sex messenger', 'sexmessenger' ),
            'adult-friendfinder' => array( 'adult friendfinder', 'friendfinder' ),
            'ashley-madison' => array( 'ashley madison' ),
            'stripchat' => array( 'stripchat' ),
            'bongacams' => array( 'bongacams' ),
            'sexpanther' => array( 'sextpanther', 'sexpanther' ),
            'visit-x' => array( 'visit x', 'visitx' ),
            'xlovecam' => array( 'xlovecam' ),
            'bellesaplus' => array( 'bellesa plus', 'bellesaplus' ),
            'victoria-milan' => array( 'victoria milan' ),
            'alt' => array( ' alt ', 'alt' ),
            'fling' => array( ' fling ', 'fling' ),
            'lovescape' => array( 'lovescape' ),
            'promptchan' => array( 'promptchan' ),
            'swipey' => array( 'swipey' ),
            'naughtycharm' => array( 'naughtycharm', 'naughty charm' ),
            'naughtytalk' => array( 'naughtytalk', 'naughty talk' ),
            'cheekycrush' => array( 'cheekycrush', 'cheeky crush' ),
            'flirttendre' => array( 'flirttendre', 'flirt tendre' ),
            'rencontredouce' => array( 'rencontredouce', 'rencontre douce' ),
            'wannahookup' => array( 'wannahookup', 'wanna hookup' ),
            'dorcel-club' => array( 'dorcel club', 'dorcelclub' ),
            'cams-com' => array( 'cams com', 'cams.com' ),
            'beianrufsex' => array( 'beianrufsex', 'bei anruf sex' ),
            'blacked-raw' => array( 'blackedraw', 'blacked raw' ),
            'tushy-raw' => array( 'tushyraw', 'tushy raw' ),
            'vixen-plus' => array( 'vixenplus', 'vixen plus' ),
            'gabrielle-moore-masterclasses' => array( 'gabrielle moore masterclasses' ),
            'total-webcams' => array( 'total webcams', 'total webcam' ),
            'hentaiheroes' => array( 'hentaiheroes', 'hentai heroes' ),
            'cougar-life' => array( 'cougar life', 'cougarlife' ),
            'flirtbate' => array( 'flirtbate', 'flirt bate' ),
            'seasonedflirt' => array( 'seasonedflirt', 'seasoned flirt' ),
            'blacked' => array( ' blacked ', 'blacked' ),
            'tushy' => array( ' tushy ', 'tushy' ),
            'vixen' => array( ' vixen ', 'vixen' ),
            'secrets-ai' => array( 'secrets ai', 'secrets.ai', 'secretsai' ),
            'darlink-ai' => array( 'darlink ai', 'darlink.ai', 'darlinkai' ),
            'xotic-ai' => array( 'xotic ai', 'xotic.ai', 'xoticai' ),
            'ourdream-ai' => array( 'ourdream ai', 'ourdream.ai', 'ourdreamai' ),
            'phalogenics' => array( 'phalogenics' ),
            'oranum' => array( 'oranum' ),
            'delhi-sex-chat' => array( 'delhi sex chat' ),
            'squirting-school' => array( 'squirting school' ),
            'growth-matrix' => array( 'growth matrix' ),
            'endura-naturals' => array( 'endura naturals' ),
            'filf' => array( ' filf ', 'filf' ),
            'faphouse' => array( 'faphouse.com', 'faphouse' ),
            'ole-cams' => array( 'olécams', 'olecams', 'ole cams', 'ol cams' ),
            'camirada' => array( 'camirada' ),
            'nananue-cam' => array( 'nananue cam', 'nananue live' ),
            'sinparty' => array( 'sinparty', 'sin party' ),
            'xtease' => array( 'xtease', 'x tease' ),
            'fanfinity' => array( 'fanfinity' ),
            'get-harder' => array( 'get harder', 'get-harder' ),
            'testosterone-support-innerbody' => array( 'testosterone support innerbody labs', 'testosterone support innerbody' ),
            'primal-blast' => array( 'primal blast' ),
            'deeper' => array( ' deeper ', 'deeper' ),
            'milfy' => array( ' milfy ', 'milfy' ),
            'wifey' => array( ' wifey ', 'wifey' ),
            'slayed' => array( ' slayed ', 'slayed' ),
        );
    }

    /**
     * @return array<string,string>
     */
    protected function get_offer_logo_filename_map() {
        return array(
            'jerkmate' => 'jerkmate-80x80-transparent.png',
            'candy-ai' => 'candyai-80x80-transparent.png',
            'girlfriend-gpt' => 'girlfriend-gpt-80x80-transparent.png',
            'joi' => 'joi-80x80-transparent.png',
            'instabang' => 'instabang-80x80-transparent.png',
            'livejasmin' => 'livejasmin-80x80-transparent.png',
            'imlive' => 'imlive-80x80-transparent.png',
            'cam4' => 'cam4-80x80-transparent.png',
            'xcams' => 'xcams-80x80-transparent.png',
            'xmatch' => 'xmatch-80x80-transparent.png',
            'sex-messenger' => 'sex-messenger-80x80-transparent.png',
            'adult-friendfinder' => 'adult-friendfinder-80x80-transparent.png',
            'ashley-madison' => 'ashley-madison-80x80-transparent.png',
            'stripchat' => 'stripchat-80x80-transparent.png',
            'bongacams' => 'bongacams-80x80-transparent.png',
            'sexpanther' => 'sexpanther-80x80-transparent.png',
            'visit-x' => 'visit-x-80x80-transparent.png',
            'xlovecam' => 'xlovecam-80x80-transparent.png',
            'bellesaplus' => 'bellesaplus-80x80-transparent.png',
            'victoria-milan' => 'victoria-milan-80x80-transparent.png',
            'alt' => 'alt-80x80-transparent.png',
            'fling' => 'fling-80x80-transparent.png',
            'lovescape' => 'lovescape-80x80-transparent.png',
            'promptchan' => 'promptchan-80x80-transparent.png',
            'swipey' => 'swipey-80x80-transparent.png',
            'naughtycharm' => 'naughtycharm-80x80-transparent.png',
            'naughtytalk' => 'naughtytalk-80x80-transparent.png',
            'cheekycrush' => 'cheekycrush-80x80-transparent.png',
            'flirttendre' => 'flirttendre-80x80-transparent.png',
            'rencontredouce' => 'rencontredouce-80x80-transparent.png',
            'wannahookup' => 'wannahookup-80x80-transparent.png',
            'dorcel-club' => 'dorcel-club-80x80-transparent.png',
            'cams-com' => 'cams-com-80x80-transparent.png',
            'beianrufsex' => 'beianrufsex-80x80-transparent.png',
            'blacked-raw' => 'blacked-raw-80x80-transparent.png',
            'tushy-raw' => 'tushy-raw-80x80-transparent.png',
            'vixen-plus' => 'vixen-plus-80x80-transparent.png',
            'gabrielle-moore-masterclasses' => 'gabrielle-moore-masterclasses-80x80-transparent.png',
            'total-webcams' => 'total-webcams-80x80-transparent.png',
            'hentaiheroes' => 'hentaiheroes-80x80-transparent.png',
            'cougar-life' => 'cougar-life-80x80-transparent.png',
            'flirtbate' => 'flirtbate-80x80-transparent.png',
            'seasonedflirt' => 'seasonedflirt-80x80-transparent.png',
            'blacked' => 'blacked-80x80-transparent.png',
            'tushy' => 'tushy-80x80-transparent.png',
            'vixen' => 'vixen-80x80-transparent.png',
            'secrets-ai' => 'secrets-ai-80x80-transparent.png',
            'darlink-ai' => 'darlink-ai-80x80-transparent.png',
            'xotic-ai' => 'xotic-ai-80x80-transparent.png',
            'ourdream-ai' => 'ourdream-ai-80x80-transparent.png',
            'phalogenics' => 'phalogenics-80x80-transparent.png',
            'oranum' => 'oranum-80x80-transparent.png',
            'delhi-sex-chat' => 'delhi-sex-chat-80x80-transparent.png',
            'squirting-school' => 'squirting-school-80x80-transparent.png',
            'growth-matrix' => 'growth-matrix-80x80-transparent.png',
            'endura-naturals' => 'endura-naturals-80x80-transparent.png',
            'filf' => 'filf-80x80-transparent.png',
            'faphouse' => 'faphouse-80x80-transparent.png',
            'ole-cams' => 'ole-cams-80x80-transparent.png',
            'camirada' => 'camirada-80x80-transparent.png',
            'nananue-cam' => 'nananue-cam-80x80-transparent.png',
            'sinparty' => 'sinparty-80x80-transparent.png',
            'xtease' => 'xtease-80x80-transparent.png',
            'fanfinity' => 'fanfinity-80x80-transparent.png',
            'get-harder' => 'get-harder-80x80-transparent.png',
            'testosterone-support-innerbody' => 'testosterone-support-innerbody-80x80-transparent.png',
            'primal-blast' => 'primal-blast-80x80-transparent.png',
            'deeper' => 'deeper-80x80-transparent.png',
            'milfy' => 'milfy-80x80-transparent.png',
            'wifey' => 'wifey-80x80-transparent.png',
            'slayed' => 'slayed-80x80-transparent.png',
        );
    }

    /**
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return int
     */
    public function get_selected_offer_count( $settings ) {
        return count( $this->get_selected_offer_ids( $settings ) );
    }

    /**
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return array<string,mixed>
     */
    public function get_dashboard_summary( $settings ) {
        $offers         = $this->get_synced_offers();
        $sync_meta      = $this->get_sync_meta();
        $stats_meta     = $this->get_stats_meta();
        $performance    = $this->get_performance_summary();
        $selected_ids   = $this->get_selected_offer_ids( $settings );
        $selected_map   = array_flip( $selected_ids );
        $active_count   = 0;
        $featured_count = 0;
        $approval_count = 0;
        $manual_images  = 0;

        foreach ( $offers as $offer ) {
            $offer_id = (string) ( $offer['id'] ?? '' );

            if ( '' === $offer_id ) {
                continue;
            }

            if ( 'active' === strtolower( (string) ( $offer['status'] ?? '' ) ) ) {
                ++$active_count;
            }

            if ( ! empty( $offer['is_featured'] ) ) {
                ++$featured_count;
            }

            if ( '1' === (string) ( $offer['require_approval'] ?? '' ) ) {
                ++$approval_count;
            }

            if ( isset( $selected_map[ $offer_id ] ) && 'manual_override' === $this->get_image_status_for_offer( $offer_id, $settings ) ) {
                ++$manual_images;
            }
        }

        return array(
            'stored_offers'            => count( $offers ),
            'selected_slot_offers'     => count( $selected_ids ),
            'active_synced_offers'     => $active_count,
            'featured_synced_offers'   => $featured_count,
            'approval_required_offers' => $approval_count,
            'manual_image_overrides'   => $manual_images,
            'last_sync_time'           => (string) ( $sync_meta['last_synced_at'] ?? '' ),
            'last_raw_row_count'       => (int) ( $sync_meta['last_raw_row_count'] ?? 0 ),
            'last_imported_count'      => (int) ( $sync_meta['last_imported_count'] ?? 0 ),
            'last_skipped_count'       => (int) ( $sync_meta['last_skipped_count'] ?? 0 ),
            'last_soft_failure'        => ! empty( $sync_meta['last_soft_failure'] ) ? 1 : 0,
            'last_error'               => (string) ( $sync_meta['last_error'] ?? '' ),
            'total_clicks'             => (int) $performance['total_clicks'],
            'total_conversions'        => (float) $performance['total_conversions'],
            'total_payout'             => (float) $performance['total_payout'],
            'top_offer_name'           => (string) $performance['top_offer_name'],
            'top_country_name'         => (string) $performance['top_country_name'],
            'last_stats_synced_at'     => (string) ( $stats_meta['last_stats_synced_at'] ?? '' ),
            'last_stats_date_start'    => (string) ( $stats_meta['last_stats_date_start'] ?? '' ),
            'last_stats_date_end'      => (string) ( $stats_meta['last_stats_date_end'] ?? '' ),
            'last_stats_error'         => (string) ( $stats_meta['last_stats_error'] ?? '' ),
            'last_stats_response_shape' => (string) ( $stats_meta['last_stats_response_shape'] ?? '' ),
            'last_stats_sample_row_keys' => (string) ( $stats_meta['last_stats_sample_row_keys'] ?? '' ),
            'last_stats_soft_failure'  => (string) ( $stats_meta['last_stats_soft_failure'] ?? '' ),
            'last_stats_preserved_previous' => ! empty( $stats_meta['last_stats_preserved_previous'] ) ? 1 : 0,
            'last_scheduled_run_at'    => (string) ( $stats_meta['last_scheduled_run_at'] ?? '' ),
            'last_scheduled_result'    => (string) ( $stats_meta['last_scheduled_result'] ?? '' ),
        );
    }

    /**
     * [TMW-CR-DASH] Filter configuration scaffold aligned with operator PDF where available.
     *
     * @return array<string,mixed>
     */
    public function get_dashboard_filter_model() {
        $offers = $this->get_synced_offers();
        $supported = array(
            'tag' => array(),
            'vertical' => array(),
            'payout_type' => array(),
            'performs_in' => array(),
            'optimized_for' => array(),
            'accepted_country' => array(),
            'niche' => array(),
            'status' => array(),
            'promotion_method' => array(),
        );
        $todo = array();

        foreach ( $offers as $offer_id => $offer ) {
            $meta = $this->get_offer_dashboard_metadata( (string) $offer_id, $offer );
            $combined = $this->get_admin_offer_filter_values( $offer, $meta );

            foreach ( $supported as $field_key => $values ) {
                $field_values = isset( $combined[ $field_key ] ) ? (array) $combined[ $field_key ] : array();
                foreach ( $field_values as $value ) {
                    $value = $this->normalize_filter_family_value( $field_key, $value );
                    if ( '' === $value ) {
                        continue;
                    }
                    $supported[ $field_key ][ $value ] = $value;
                }
            }
        }

        foreach ( $supported as $field_key => $values ) {
            if ( ! empty( $values ) ) {
                ksort( $values, SORT_NATURAL | SORT_FLAG_CASE );
                $supported[ $field_key ] = array_values( $values );
            } else {
                $supported[ $field_key ] = array();
                $todo[ $field_key ]      = __( 'No synced values yet. Use richer API fields or local offer override metadata to populate this filter.', 'tmw-cr-slot-sidebar-banner' );
            }
        }

        return array(
            'supported' => $supported,
            'todo' => $todo,
        );
    }

    /**
     * @param array<string,mixed> $args Query args.
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return array<string,mixed>
     */
    public function get_filtered_synced_offers_for_admin( $args, $settings ) {
        $defaults = array(
            'search'            => '',
            'status'            => '',
            'tag'               => '',
            'vertical'          => '',
            'featured'          => '',
            'approval_required' => '',
            'payout_type'       => '',
            'performs_in'       => '',
            'optimized_for'     => '',
            'accepted_country'  => '',
            'niche'             => '',
            'promotion_method'  => '',
            'image_status'      => '',
            'logo_status'       => '',
            'sort_by'           => 'name',
            'sort_order'        => 'asc',
            'page'              => 1,
            'per_page'          => 25,
            'selected_only'     => false,
            'include_all'       => false,
        );

        $query       = wp_parse_args( (array) $args, $defaults );
        $selected    = array_flip( $this->get_selected_offer_ids( $settings ) );
        $search      = strtolower( trim( (string) $query['search'] ) );
        $status_values   = $this->normalize_query_values( $query['status'], 'status' );
        $tag_values      = $this->normalize_query_values( $query['tag'], 'tag' );
        $vertical_values = $this->normalize_query_values( $query['vertical'], 'vertical' );
        $featured    = strtolower( trim( (string) $query['featured'] ) );
        $approval    = strtolower( trim( (string) $query['approval_required'] ) );
        $payout_values    = $this->normalize_query_values( $query['payout_type'], 'payout_type' );
        $performs_values  = $this->normalize_query_values( $query['performs_in'], 'performs_in' );
        $optimized_values = $this->normalize_query_values( $query['optimized_for'], 'optimized_for' );
        $accepted_values  = $this->normalize_query_values( $query['accepted_country'], 'accepted_country' );
        $niche_values     = $this->normalize_query_values( $query['niche'], 'niche' );
        $promotion_values = $this->normalize_query_values( $query['promotion_method'], 'promotion_method' );
        $image       = strtolower( trim( (string) $query['image_status'] ) );
        $logo_status = strtolower( trim( (string) $query['logo_status'] ) );
        $active_filters = array();
        $offers         = array_values( $this->get_synced_offers() );
        $legacy_catalog = $this->get_default_legacy_catalog();
        $filtered       = array();

        foreach ( $offers as $offer ) {
            $offer_id = (string) ( $offer['id'] ?? '' );
            if ( '' === $offer_id ) {
                continue;
            }

            $is_selected = isset( $selected[ $offer_id ] );
            if ( ! empty( $query['selected_only'] ) && ! $is_selected ) {
                continue;
            }

            if ( '' !== $search ) {
                $haystack = strtolower( $offer_id . ' ' . (string) ( $offer['name'] ?? '' ) );
                if ( false === strpos( $haystack, $search ) ) {
                    continue;
                }
            }

            $offer_status = strtolower( trim( (string) ( $offer['status'] ?? '' ) ) );
            if ( ! empty( $status_values ) && ! in_array( $offer_status, $status_values, true ) ) {
                continue;
            }
            $offer_meta = $this->get_offer_dashboard_metadata( $offer_id, $offer );
            $filter_values = $this->get_admin_offer_filter_values( $offer, $offer_meta );

            if ( ! empty( $tag_values ) && ! $this->values_intersect_filter_set( $tag_values, (array) ( $filter_values['tag'] ?? array() ) ) ) {
                continue;
            }

            if ( ! empty( $vertical_values ) && ! $this->values_intersect_filter_set( $vertical_values, (array) ( $filter_values['vertical'] ?? array() ) ) ) {
                continue;
            }

            if ( '' !== $featured ) {
                $is_featured = ! empty( $offer['is_featured'] );
                if ( ( 'yes' === $featured && ! $is_featured ) || ( 'no' === $featured && $is_featured ) ) {
                    continue;
                }
            }

            if ( '' !== $approval ) {
                $needs_approval = '1' === (string) ( $offer['require_approval'] ?? '' );
                if ( ( 'yes' === $approval && ! $needs_approval ) || ( 'no' === $approval && $needs_approval ) ) {
                    continue;
                }
            }

            if ( ! empty( $payout_values ) && ! $this->values_intersect_filter_set( $payout_values, (array) ( $filter_values['payout_type'] ?? array() ) ) ) {
                continue;
            }
            if ( ! empty( $performs_values ) && ! $this->values_intersect_filter_set( $performs_values, (array) ( $filter_values['performs_in'] ?? array() ), true ) ) {
                continue;
            }
            if ( ! empty( $optimized_values ) && ! $this->values_intersect_filter_set( $optimized_values, (array) ( $filter_values['optimized_for'] ?? array() ) ) ) {
                continue;
            }
            if ( ! empty( $accepted_values ) && ! $this->values_intersect_filter_set( $accepted_values, (array) ( $filter_values['accepted_country'] ?? array() ), true ) ) {
                continue;
            }
            if ( ! empty( $niche_values ) && ! $this->values_intersect_filter_set( $niche_values, (array) ( $filter_values['niche'] ?? array() ) ) ) {
                continue;
            }
            if ( ! empty( $promotion_values ) && ! $this->values_intersect_filter_set( $promotion_values, (array) ( $filter_values['promotion_method'] ?? array() ) ) ) {
                continue;
            }

            $image_status = $this->get_image_status_for_offer( $offer_id, $settings, $legacy_catalog );
            if ( '' !== $image && $image !== $image_status ) {
                continue;
            }
            $offer_logo_status = $this->get_logo_status_for_offer_any( $offer_id, $offer, $settings, $legacy_catalog );
            if ( '' !== $logo_status && $logo_status !== $offer_logo_status ) {
                continue;
            }

            $offer['dashboard_metadata']   = $offer_meta;
            $offer['is_selected_for_slot'] = $is_selected;
            $offer['image_status']         = $image_status;
            $offer['logo_status']          = $offer_logo_status;
            $offer['brand_key']            = $this->get_offer_brand_key( (string) ( $offer['name'] ?? '' ) );
            $offer['logo_filename']        = $this->get_offer_logo_filename( $offer );
            $offer['logo_url']             = $this->get_offer_logo_url( $offer );
            $filtered[]                    = $offer;
        }

        $sort_by    = in_array( $query['sort_by'], array( 'name', 'id', 'status', 'payout', 'featured' ), true ) ? $query['sort_by'] : 'name';
        $sort_order = 'desc' === strtolower( (string) $query['sort_order'] ) ? 'desc' : 'asc';

        usort(
            $filtered,
            static function ( $left, $right ) use ( $sort_by, $sort_order ) {
                switch ( $sort_by ) {
                    case 'id':
                        $left_value  = (string) ( $left['id'] ?? '' );
                        $right_value = (string) ( $right['id'] ?? '' );
                        break;
                    case 'status':
                        $left_value  = (string) ( $left['status'] ?? '' );
                        $right_value = (string) ( $right['status'] ?? '' );
                        break;
                    case 'payout':
                        $left_value  = (float) ( $left['default_payout'] ?? 0 );
                        $right_value = (float) ( $right['default_payout'] ?? 0 );
                        break;
                    case 'featured':
                        $left_value  = ! empty( $left['is_featured'] ) ? 1 : 0;
                        $right_value = ! empty( $right['is_featured'] ) ? 1 : 0;
                        break;
                    case 'name':
                    default:
                        $left_value  = (string) ( $left['name'] ?? '' );
                        $right_value = (string) ( $right['name'] ?? '' );
                        break;
                }

                if ( $left_value === $right_value ) {
                    return strcasecmp( (string) ( $left['name'] ?? '' ), (string) ( $right['name'] ?? '' ) );
                }

                $comparison = is_string( $left_value ) ? strcasecmp( $left_value, (string) $right_value ) : ( $left_value <=> $right_value );

                return 'desc' === $sort_order ? -1 * $comparison : $comparison;
            }
        );

        $total    = count( $filtered );
        $per_page = max( 1, (int) $query['per_page'] );
        $page     = max( 1, (int) $query['page'] );
        $pages    = max( 1, (int) ceil( $total / $per_page ) );
        if ( $page > $pages ) {
            $page = $pages;
        }

        $offset = ( $page - 1 ) * $per_page;

        $family_filters = array(
            'payout_type' => $payout_values, 'tag' => $tag_values, 'vertical' => $vertical_values, 'performs_in' => $performs_values,
            'optimized_for' => $optimized_values, 'accepted_country' => $accepted_values, 'niche' => $niche_values, 'promotion_method' => $promotion_values,
        );
        foreach ( array( 'search' => $search, 'status' => $status_values, 'featured' => $featured, 'approval_required' => $approval, 'image_status' => $image, 'logo_status' => $logo_status ) as $k => $v ) {
            if ( ! empty( $v ) ) { $active_filters[] = sanitize_key( $k ); }
        }
        foreach ( $family_filters as $family => $requested_values ) {
            if ( empty( $requested_values ) ) { continue; }
            $active_filters[] = sanitize_key( $family );
        }
        $active_filters = array_values( array_unique( $active_filters ) );
        if ( ! empty( $active_filters ) ) {
            $zero_reason = 0 === $total ? 'no_matches' : 'n/a';
            error_log( sprintf( '[TMW-BANNER-OFFERS-FILTER] active=%s total=%d matched=%d zero_reason=%s', implode( ',', $active_filters ), count( $offers ), $total, sanitize_key( $zero_reason ) ) );
        }

        return array(
            'items'    => array_slice( $filtered, $offset, $per_page ),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => $pages,
            'sort_by'  => $sort_by,
            'order'    => $sort_order,
            'active_filters' => $active_filters,
            'source_total' => count( $offers ),
        );
    }

    protected function get_admin_offer_filter_values( $offer, $offer_meta ) {
        $families = array( 'tag', 'vertical', 'performs_in', 'optimized_for', 'accepted_country', 'niche', 'promotion_method', 'payout_type' );
        $values = array();
        foreach ( $families as $family ) {
            $meta_values = $this->normalize_query_values( (array) ( $offer_meta[ $family ] ?? array() ), $family );
            $raw_values  = $this->extract_admin_raw_filter_values( $offer, $family );
            $values[ $family ] = array_values( array_unique( array_merge( $meta_values, $raw_values ) ) );
        }
        return $values;
    }

    protected function extract_admin_raw_filter_values( $offer, $family ) {
        $raw_map = array(
            'tag' => array( 'tag', 'tags' ), 'vertical' => array( 'vertical', 'verticals' ), 'performs_in' => array( 'performs_in', 'countries_performs_in' ),
            'optimized_for' => array( 'optimized_for' ), 'accepted_country' => array( 'accepted_country', 'accepted_countries', 'countries' ),
            'niche' => array( 'niche' ), 'promotion_method' => array( 'promotion_method' ), 'payout_type' => array( 'payout_type' ),
        );
        $raw_values = array();
        foreach ( (array) ( $raw_map[ $family ] ?? array() ) as $key ) {
            $raw_source = $offer[ $key ] ?? array();
            if ( is_string( $raw_source ) && ( false !== strpos( $raw_source, '|' ) || false !== strpos( $raw_source, ',' ) ) ) {
                $raw_source = preg_split( '/[|,]/', $raw_source );
            }
            $raw_values = array_merge( $raw_values, $this->normalize_query_values( $raw_source, $family ) );
        }
        if ( 'payout_type' === $family ) {
            $has_direct_payout = '' !== trim( (string) ( $offer['payout_type'] ?? '' ) );
            if ( ! $has_direct_payout ) {
                $raw_values = array_merge( $raw_values, $this->normalize_query_values( $this->get_offer_type_keys( $offer ), 'payout_type' ) );
            }
            $name = strtolower( (string) ( $offer['name'] ?? '' ) );
            if ( false !== strpos( $name, 'cpa' ) ) { $raw_values[] = 'multi_cpa'; }
            if ( 'cpa_flat' === strtolower( trim( (string) ( $offer['payout_type'] ?? '' ) ) ) ) { $raw_values[] = 'multi_cpa'; }
        }
        return array_values( array_unique( $raw_values ) );
    }

    /**
     * @param string $offer_id Offer ID.
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return string
     */
    public function get_image_status_for_offer( $offer_id, $settings, $legacy_catalog = array() ) {
        $offer_id     = (string) $offer_id;
        $selected_ids = $this->get_selected_offer_ids( $settings );
        if ( ! in_array( $offer_id, $selected_ids, true ) ) {
            return 'not_selected';
        }

        $offer_override = $this->get_offer_override( $offer_id );
        if ( ! empty( $offer_override['image_url_override'] ) ) {
            return 'manual_override';
        }

        $overrides = isset( $settings['offer_image_overrides'] ) && is_array( $settings['offer_image_overrides'] ) ? $settings['offer_image_overrides'] : array();

        if ( ! empty( $overrides[ $offer_id ] ) ) {
            return 'manual_override';
        }

        $synced_offers = $this->get_synced_offers();
        if ( isset( $synced_offers[ $offer_id ] ) ) {
            $offer_name = (string) ( $synced_offers[ $offer_id ]['name'] ?? $offer_id );

            if ( '' !== $this->resolve_local_catalog_image( $offer_name, $legacy_catalog ) ) {
                return 'auto_local';
            }

            if ( '' !== $this->resolve_remote_thumbnail_image( $offer_name ) ) {
                return 'auto_remote';
            }
        }

        return 'placeholder_only';
    }

    /**
     * Returns logo source status for any synced offer.
     *
     * @param string              $offer_id Offer ID.
     * @param array<string,mixed> $offer Offer payload.
     * @param array<string,mixed> $settings Settings payload.
     * @param array<string,mixed> $legacy_catalog Legacy catalog.
     *
     * @return string
     */
    public function get_logo_status_for_offer_any( $offer_id, $offer, $settings = array(), $legacy_catalog = array() ) {
        $offer_id = (string) $offer_id;
        $override = $this->get_offer_override( $offer_id );
        if ( ! empty( $override['image_url_override'] ) ) {
            return 'manual_override';
        }
        $overrides = isset( $settings['offer_image_overrides'] ) && is_array( $settings['offer_image_overrides'] ) ? $settings['offer_image_overrides'] : array();
        if ( ! empty( $overrides[ $offer_id ] ) ) {
            return 'manual_override';
        }
        $brand_key = $this->get_offer_brand_key( (string) ( $offer['name'] ?? '' ) );
        $filename_map = $this->get_offer_logo_filename_map();
        if ( '' !== $brand_key && isset( $filename_map[ $brand_key ] ) ) {
            $expected_filename = (string) $filename_map[ $brand_key ];
            $local_path        = rtrim( (string) TMW_CR_SLOT_BANNER_PATH, '/\\' ) . '/assets/logos/' . $expected_filename;
            return file_exists( $local_path ) ? 'mapped_local' : 'missing';
        }
        $offer_name = (string) ( $offer['name'] ?? '' );
        if ( '' !== $this->resolve_remote_thumbnail_image( $offer_name ) || '' !== $this->resolve_local_catalog_image( $offer_name, $legacy_catalog ) ) {
            return 'auto_remote';
        }
        return 'placeholder_only';
    }

    /**
     * Returns frontend eligibility summary for dashboard display.
     *
     * @param array<string,mixed> $offer Offer payload.
     * @param array<string,mixed> $settings Settings payload.
     * @param string              $country Country.
     * @param array<string,mixed> $legacy_catalog Legacy catalog.
     *
     * @return array<string,mixed>
     */
    public function get_offer_frontend_eligibility_summary( $offer, $settings, $country, $legacy_catalog = array() ) {
        $offer_id = (string) ( $offer['id'] ?? '' );
        if ( ! $this->is_offer_type_allowed( $offer, $settings ) ) {
            return array( 'is_eligible' => false, 'block_reason' => 'not_allowed_type' );
        }
        if ( $this->is_offer_blocked_for_banner( $offer, $settings ) ) {
            return array( 'is_eligible' => false, 'block_reason' => 'business_rule_blocked' );
        }
        if ( $this->is_unavailable_account_pps_offer( $offer ) ) {
            return array( 'is_eligible' => false, 'block_reason' => 'unavailable_account_offer' );
        }
        if ( ! empty( $settings['enforce_skipped_offers_exclusion'] ) && $this->is_offer_skipped_for_frontend( $offer_id, $this->get_skipped_offer_ids_for_frontend() ) ) {
            return array( 'is_eligible' => false, 'block_reason' => 'skipped_offer' );
        }
        $override = $this->get_offer_override( $offer_id );
        $effective = $this->get_effective_cta_url( $offer_id, $settings, array( 'cta_url' => (string) ( $settings['cta_url'] ?? '' ) ), $offer, $override );
        if ( ! $this->is_valid_frontend_winner_cta_url( (string) $effective ) ) {
            return array( 'is_eligible' => false, 'block_reason' => 'missing_valid_cta' );
        }
        if ( ! $this->is_offer_allowed_for_country( $offer_id, $country, $override, $offer, $legacy_catalog ) ) {
            return array( 'is_eligible' => false, 'block_reason' => 'country_not_allowed' );
        }
        $logo_status = $this->get_logo_status_for_offer_any( $offer_id, $offer, $settings, $legacy_catalog );
        if ( 'missing' === $logo_status || 'placeholder_only' === $logo_status ) {
            return array( 'is_eligible' => false, 'block_reason' => 'missing_logo' );
        }
        return array( 'is_eligible' => true, 'block_reason' => 'valid' );
    }

    /**
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return array<int,string>
     */
    protected function get_selected_offer_ids( $settings ) {
        $selected = isset( $settings['slot_offer_ids'] ) && is_array( $settings['slot_offer_ids'] ) ? $settings['slot_offer_ids'] : array();

        return array_values( array_unique( array_filter( array_map( 'strval', $selected ) ) ) );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    protected function get_default_legacy_catalog() {
        if ( class_exists( 'TMW_CR_Slot_Sidebar_Banner' ) && method_exists( 'TMW_CR_Slot_Sidebar_Banner', 'get_offer_catalog_defaults' ) ) {
            return (array) TMW_CR_Slot_Sidebar_Banner::get_offer_catalog_defaults();
        }

        return array();
    }

    /**
     * Builds frontend offers for the sidebar slot.
     *
     * @param string                      $slot_key     Slot identifier.
     * @param array<string,mixed>         $settings     Settings.
     * @param array<string,string>        $banner_data  Banner data.
     * @param string                      $country      Country code.
     * @param array<string,array<string,mixed>> $legacy_catalog Legacy fallback catalog.
     *
     * @return array<int,array<string,string>>
     */
    public function get_frontend_slot_offers( $slot_key, $settings, $banner_data, $country, $legacy_catalog ) {
        unset( $slot_key );

        $synced_offers = $this->get_synced_offers();
        $overrides_map = $this->get_offer_overrides();
        $selected_ids  = isset( $settings['slot_offer_ids'] ) && is_array( $settings['slot_offer_ids'] ) ? array_values( $settings['slot_offer_ids'] ) : array();
        $priorities    = isset( $settings['slot_offer_priority'] ) && is_array( $settings['slot_offer_priority'] ) ? $settings['slot_offer_priority'] : array();

        $offers = array();

        $skipped_offer_ids = ! empty( $settings['enforce_skipped_offers_exclusion'] ) ? $this->get_skipped_offer_ids_for_frontend() : array();
        $excluded_during_pool_build = 0;

        $type_allowed_count = 0;
        $skipped_type_disallowed_count = 0;

        foreach ( $selected_ids as $selected_id ) {
            $selected_id = (string) $selected_id;

            if ( isset( $synced_offers[ $selected_id ] ) ) {
                if ( ! $this->is_offer_type_allowed( $synced_offers[ $selected_id ], $settings ) ) {
                    ++$skipped_type_disallowed_count;
                    continue;
                }
                if ( $this->should_exclude_skipped_frontend_offer( $selected_id, $skipped_offer_ids, $excluded_during_pool_build ) ) {
                    continue;
                }
                if ( $this->is_offer_blocked_for_banner( $synced_offers[ $selected_id ], $settings ) ) {
                    continue;
                }
                if ( $this->is_unavailable_account_pps_offer( $synced_offers[ $selected_id ] ) ) {
                    $this->log_unavailable_account_offer_excluded( $synced_offers[ $selected_id ] );
                    continue;
                }
                ++$type_allowed_count;
                $evaluation = $this->evaluate_offer_eligibility( $selected_id, $settings, $banner_data, $country, $legacy_catalog );
                $effective = $evaluation['effective_offer'];
                $this->log_eligibility_event( $selected_id, $evaluation['reason'], $country );

                if ( ! empty( $effective ) ) {
                    if ( ! $this->is_valid_frontend_winner_cta_url( (string) ( $effective['cta_url'] ?? '' ) ) ) {
                        continue;
                    }
                    $offers[] = $effective;
                }
            }
        }

        if ( empty( $offers ) ) {
            $sorted_synced = array_values( $synced_offers );

            usort(
                $sorted_synced,
                static function ( $left, $right ) {
                    $left_featured  = ! empty( $left['is_featured'] ) ? 1 : 0;
                    $right_featured = ! empty( $right['is_featured'] ) ? 1 : 0;

                    if ( $left_featured !== $right_featured ) {
                        return $right_featured <=> $left_featured;
                    }

                    return strcasecmp( (string) ( $left['name'] ?? '' ), (string) ( $right['name'] ?? '' ) );
                }
            );

            foreach ( $sorted_synced as $synced_offer ) {
                $offer_id = (string) ( $synced_offer['id'] ?? '' );
                if ( '' === $offer_id ) {
                    continue;
                }

                if ( $this->should_exclude_skipped_frontend_offer( $offer_id, $skipped_offer_ids, $excluded_during_pool_build ) ) { continue; }
                $override = isset( $overrides_map[ $offer_id ] ) ? $overrides_map[ $offer_id ] : array();
                if ( ! $this->is_offer_allowed_for_country( $offer_id, $country, $override, $synced_offer, $legacy_catalog ) ) {
                    continue;
                }
                if ( ! $this->is_offer_type_allowed( $synced_offer, $settings ) ) {
                    ++$skipped_type_disallowed_count;
                    continue;
                }
                if ( $this->is_offer_blocked_for_banner( $synced_offer, $settings ) ) {
                    continue;
                }
                if ( $this->is_unavailable_account_pps_offer( $synced_offer ) ) {
                    $this->log_unavailable_account_offer_excluded( $synced_offer );
                    continue;
                }
                ++$type_allowed_count;
                $evaluation = $this->evaluate_offer_eligibility( $offer_id, $settings, $banner_data, $country, $legacy_catalog );
                $effective = $evaluation['effective_offer'];
                $this->log_eligibility_event( $offer_id, $evaluation['reason'], $country );

                if ( ! empty( $effective ) ) {
                    if ( ! $this->is_valid_frontend_winner_cta_url( (string) ( $effective['cta_url'] ?? '' ) ) ) {
                        continue;
                    }
                    $offers[] = $effective;
                }
            }
        }

        if ( count( $offers ) < 3 ) {
            foreach ( $overrides_map as $offer_id => $override ) {
                $offer_id = (string) $offer_id;
                if ( '' === $offer_id || isset( $synced_offers[ $offer_id ] ) ) {
                    continue;
                }

                if ( $this->should_exclude_skipped_frontend_offer( $offer_id, $skipped_offer_ids, $excluded_during_pool_build ) ) { continue; }
                $effective = $this->get_override_only_effective_offer_record( $offer_id, $override, $settings, $banner_data, $country, $legacy_catalog );
                if ( empty( $effective ) ) {
                    continue;
                }
                $offers[] = $effective;
            }
        }

        $offers = array_values( array_filter( $offers ) );

        $offers = $this->rank_offers_for_slot( $offers, $settings, $country, $priorities );

        if ( count( $offers ) < 3 ) {
            $used_ids = array();
            foreach ( $offers as $offer ) {
                if ( isset( $offer['id'] ) ) {
                    $used_ids[] = (string) $offer['id'];
                }
            }

            foreach ( $legacy_catalog as $legacy_offer ) {
                $legacy_id = (string) ( $legacy_offer['id'] ?? $legacy_offer['name'] ?? '' );
                if ( '' === $legacy_id || in_array( $legacy_id, $used_ids, true ) ) {
                    continue;
                }

                if ( $this->should_exclude_skipped_frontend_offer( $legacy_id, $skipped_offer_ids, $excluded_during_pool_build ) ) { continue; }
                if ( ! $this->is_offer_allowed_for_country( $legacy_id, $country, array(), array(), $legacy_catalog ) ) {
                    continue;
                }
                $legacy_offer_for_type = $legacy_offer;
                if ( empty( $legacy_offer_for_type['name'] ) ) {
                    $legacy_offer_for_type['name'] = 'Group Fallback - Legacy Offer';
                } elseif ( empty( $this->get_offer_type_keys( $legacy_offer_for_type ) ) ) {
                    $legacy_offer_for_type['name'] = 'Group Fallback - ' . (string) $legacy_offer_for_type['name'];
                }
                if ( ! $this->is_offer_type_allowed( $legacy_offer_for_type, $settings ) ) {
                    ++$skipped_type_disallowed_count;
                    continue;
                }
                if ( $this->is_offer_blocked_for_banner( $legacy_offer_for_type, $settings ) ) {
                    continue;
                }
                if ( $this->is_unavailable_account_pps_offer( $legacy_offer_for_type ) ) {
                    $this->log_unavailable_account_offer_excluded( $legacy_offer_for_type );
                    continue;
                }
                ++$type_allowed_count;

                $normalized_legacy_offer = $this->normalize_legacy_offer( $legacy_offer, $banner_data );
                if ( ! $this->is_valid_frontend_winner_cta_url( (string) ( $normalized_legacy_offer['cta_url'] ?? '' ) ) ) {
                    continue;
                }

                $offers[]  = $normalized_legacy_offer;
                $used_ids[] = $legacy_id;

                if ( count( $offers ) >= 3 ) {
                    break;
                }
            }
        }

        $offers = array_values( array_filter( $offers ) );


        if ( function_exists( 'error_log' ) ) {
            error_log(
                sprintf(
                    '[TMW-BANNER-TYPE] frontend_pool allowed_types=%s total_candidates=%d type_allowed_count=%d skipped_type_disallowed_count=%d',
                    implode( ',', $this->get_allowed_offer_types( $settings ) ),
                    count( $selected_ids ) + count( $synced_offers ) + count( $legacy_catalog ),
                    (int) $type_allowed_count,
                    (int) $skipped_type_disallowed_count
                )
            );
        }
        if ( ! empty( $settings['enforce_skipped_offers_exclusion'] ) && function_exists( 'error_log' ) ) {
            error_log( sprintf( '[TMW-BANNER-SKIP] frontend_pool_summary enforce_enabled=1 total_skipped_in_store=%d excluded_during_pool_build=%d', count( $skipped_offer_ids ), (int) $excluded_during_pool_build ) );
        }
        if ( empty( $offers ) && function_exists( 'error_log' ) ) {
            error_log( '[TMW-BANNER-TYPE] frontend_pool_empty safe_empty_state=1' );
        }

        return apply_filters( 'tmw_cr_slot_banner_offers', $offers, '', $banner_data );
    }

    /**
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return array<string,mixed>
     */
    public function get_pps_logo_coverage_report( $settings ) {
        return $this->get_logo_coverage_report_for_type( 'pps', $settings );
    }

    /**
     * @param string              $type     Offer type key.
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return array<string,mixed>
     */
    public function get_logo_coverage_report_for_type( $type, $settings ) {
        $report_type = sanitize_key( (string) $type );
        if ( ! in_array( $report_type, self::ALLOWED_OFFER_TYPES, true ) ) {
            $report_type = 'pps';
        }
        $synced      = $this->get_synced_offers();
        $report      = array(
            'pps_candidates_total' => 0,
            'pps_with_logo' => 0,
            'pps_missing_logo' => 0,
            'pps_missing_logo_offers' => array(),
            'pps_with_logo_offers' => array(),
            'blocked_pps_offers_excluded' => 0,
            'unavailable_account_pps_offers_excluded' => 0,
            'unavailable_account_offer_ids' => array(),
            'unavailable_account_offer_names' => array(),
            'missing_logo_offer_ids' => array(),
            'missing_logo_offer_names' => array(),
            'missing_logo_expected_brand_keys' => array(),
        );

        foreach ( $synced as $offer ) {
            if ( ! is_array( $offer ) ) {
                continue;
            }
            if ( ! $this->is_offer_type_allowed( $offer, array( 'allowed_offer_types' => array( $report_type ) ) ) ) {
                continue;
            }
            if ( $this->is_offer_blocked_for_banner( $offer, $settings ) ) {
                ++$report['blocked_pps_offers_excluded'];
                continue;
            }
            if ( $this->is_unavailable_account_pps_offer( $offer ) ) {
                ++$report['unavailable_account_pps_offers_excluded'];
                $offer_id = sanitize_text_field( (string) ( $offer['id'] ?? '' ) );
                $offer_name = sanitize_text_field( (string) ( $offer['name'] ?? '' ) );
                $report['unavailable_account_offer_ids'][] = $offer_id;
                $report['unavailable_account_offer_names'][] = $offer_name;
                $this->log_unavailable_account_offer_excluded( $offer );
                continue;
            }

            ++$report['pps_candidates_total'];
            $logo_url = $this->get_offer_logo_url( $offer );
            if ( '' !== $logo_url ) {
                ++$report['pps_with_logo'];
                $report['pps_with_logo_offers'][] = sanitize_text_field( (string) ( $offer['id'] ?? '' ) );
                continue;
            }
            ++$report['pps_missing_logo'];
            $offer_id = sanitize_text_field( (string) ( $offer['id'] ?? '' ) );
            $offer_name = sanitize_text_field( (string) ( $offer['name'] ?? '' ) );
            $brand_key = $this->get_offer_brand_key( $offer_name );
            $report['missing_logo_offer_ids'][] = $offer_id;
            $report['pps_missing_logo_offers'][] = $offer_id;
            $report['missing_logo_offer_names'][] = $offer_name;
            $report['missing_logo_expected_brand_keys'][] = $brand_key;
        }


        $report[ $report_type . '_candidates_total' ] = (int) $report['pps_candidates_total'];
        $report[ $report_type . '_with_logo' ] = (int) $report['pps_with_logo'];
        $report[ $report_type . '_missing_logo' ] = (int) $report['pps_missing_logo'];
        $report[ $report_type . '_missing_logo_offers' ] = (array) $report['pps_missing_logo_offers'];
        $report[ $report_type . '_with_logo_offers' ] = (array) $report['pps_with_logo_offers'];


        if ( function_exists( 'error_log' ) ) {
            error_log(
                sprintf(
                    '[TMW-BANNER-LOGO-COVERAGE] type=%s candidates_total=%d with_logo=%d missing_logo=%d',
                    $report_type,
                    (int) $report['pps_candidates_total'],
                    (int) $report['pps_with_logo'],
                    (int) $report['pps_missing_logo']
                )
            );
        }

        return $report;
    }

    /**
     * @param array<string,mixed> $offer Offer payload.
     *
     * @return bool
     */
    protected function is_unavailable_account_pps_offer( $offer ) {
        $offer_id = sanitize_text_field( (string) ( $offer['id'] ?? '' ) );
        if ( '' === $offer_id || ! in_array( $offer_id, self::UNAVAILABLE_ACCOUNT_PPS_OFFER_IDS, true ) ) {
            return false;
        }
        return in_array( 'pps', $this->get_offer_type_keys( $offer ), true );
    }

    /**
     * @param array<string,mixed> $offer Offer payload.
     *
     * @return void
     */
    protected function log_unavailable_account_offer_excluded( $offer ) {
        if ( ! function_exists( 'error_log' ) ) {
            return;
        }
        $offer_id = sanitize_text_field( (string) ( $offer['id'] ?? '' ) );
        $offer_name = sanitize_text_field( (string) ( $offer['name'] ?? '' ) );
        error_log(
            sprintf(
                '[TMW-SLOT-LOGO] unavailable_account_offer_excluded offer_id=%s offer_name="%s"',
                $offer_id,
                $offer_name
            )
        );
    }

    /**
     * [TMW-CR-OPT] Runtime-only ranking. Never rewrites saved manual priorities.
     *
     * @param array<int,array<string,string>> $offers Offers.
     * @param array<string,mixed>             $settings Settings.
     * @param string                          $country Country name/code.
     * @param array<string,mixed>             $priorities Manual priorities.
     *
     * @return array<int,array<string,string>>
     */
    public function rank_offers_for_slot( $offers, $settings, $country, $priorities = array() ) {
        $mode = isset( $settings['rotation_mode'] ) ? sanitize_key( (string) $settings['rotation_mode'] ) : 'manual';
        $optimization = $this->get_optimization_settings( $settings );
        if ( '' === $mode ) {
            $mode = 'manual';
        }

        if ( 'manual' === $mode || empty( $optimization['optimization_enabled'] ) ) {
            usort(
                $offers,
                static function ( $left, $right ) use ( $priorities ) {
                    $left_priority  = isset( $priorities[ $left['id'] ] ) ? (int) $priorities[ $left['id'] ] : 9999;
                    $right_priority = isset( $priorities[ $right['id'] ] ) ? (int) $priorities[ $right['id'] ] : 9999;

                    if ( $left_priority !== $right_priority ) {
                        return $left_priority <=> $right_priority;
                    }

                    return strcasecmp( $left['name'], $right['name'] );
                }
            );

            return $offers;
        }

        $country = strtoupper( sanitize_text_field( (string) $country ) );
        $stats   = $this->get_offer_stats();
        $scored  = array();

        foreach ( $offers as $offer ) {
            $offer_id = (string) ( $offer['id'] ?? '' );
            $metrics  = $this->get_offer_metrics_for_country( $offer_id, $country, $stats, $optimization );

            if ( ! empty( $optimization['exclude_zero_click_offers'] ) && $metrics['clicks'] <= 0 ) {
                continue;
            }
            if ( ! empty( $optimization['exclude_zero_conversion_offers'] ) && $metrics['conversions'] <= 0 ) {
                continue;
            }

            $score    = $this->build_rotation_score( $mode, $metrics, $optimization );

            $offer['_tmw_score'] = $score;
            $offer['_tmw_has_country_stats'] = ! empty( $metrics['country_used'] ) ? 1 : 0;
            $scored[] = $offer;
        }

        usort(
            $scored,
            static function ( $left, $right ) use ( $priorities ) {
                if ( $left['_tmw_score'] !== $right['_tmw_score'] ) {
                    return $right['_tmw_score'] <=> $left['_tmw_score'];
                }

                if ( $left['_tmw_has_country_stats'] !== $right['_tmw_has_country_stats'] ) {
                    return $right['_tmw_has_country_stats'] <=> $left['_tmw_has_country_stats'];
                }

                $left_priority  = isset( $priorities[ $left['id'] ] ) ? (int) $priorities[ $left['id'] ] : 9999;
                $right_priority = isset( $priorities[ $right['id'] ] ) ? (int) $priorities[ $right['id'] ] : 9999;
                if ( $left_priority !== $right_priority ) {
                    return $left_priority <=> $right_priority;
                }

                return strcasecmp( $left['name'], $right['name'] );
            }
        );

        foreach ( $scored as $index => $offer ) {
            unset( $scored[ $index ]['_tmw_score'], $scored[ $index ]['_tmw_has_country_stats'] );
        }

        return $scored;
    }

    /**
     * @param array<string,mixed>      $offer Raw synced offer.
     * @param array<string,string>     $banner_data Banner data.
     * @param array<string,string>     $image_map Optional image overrides.
     *
     * @return array<string,string>
     */
    protected function normalize_synced_offer( $offer, $banner_data, $image_map ) {
        $offer_id = (string) ( $offer['id'] ?? '' );

        if ( '' === $offer_id ) {
            return array();
        }

        if ( ! empty( $offer['status'] ) && 'active' !== strtolower( (string) $offer['status'] ) ) {
            return array();
        }

        $image = $this->resolve_synced_offer_image( $offer, array( 'offer_image_overrides' => $image_map ), array() );

        return array(
            'id'       => $offer_id,
            'name'     => (string) ( $offer['name'] ?? $offer_id ),
            'image'    => $image,
            'cta_url'  => $this->build_cta_url( $banner_data, $offer ),
            'cta_text' => (string) ( $banner_data['cta_text'] ?? '' ),
        );
    }

    /**
     * @param array<string,mixed>  $offer Legacy catalog offer.
     * @param array<string,string> $banner_data Banner data.
     *
     * @return array<string,string>
     */
    protected function normalize_legacy_offer( $offer, $banner_data ) {
        $offer_id = (string) ( $offer['id'] ?? $offer['name'] ?? '' );
        $file     = isset( $offer['filename'] ) ? (string) $offer['filename'] : '';
        $path     = '' !== $file ? TMW_CR_SLOT_BANNER_PATH . 'assets/img/offers/' . $file : '';
        $image    = '';

        if ( '' !== $path && file_exists( $path ) ) {
            $image = TMW_CR_Slot_Sidebar_Banner::asset_url( 'assets/img/offers/' . $file );
        }

        if ( '' === $image ) {
            $image = $this->build_placeholder_image( (string) ( $offer['name'] ?? $offer_id ) );
        }

        return array(
            'id'       => $offer_id,
            'name'     => (string) ( $offer['name'] ?? $offer_id ),
            'image'    => $image,
            'cta_url'  => $this->build_cta_url( $banner_data, $offer ),
            'cta_text' => ! empty( $offer['cta_text'] ) ? (string) $offer['cta_text'] : (string) ( $banner_data['cta_text'] ?? '' ),
            'brand_key' => '',
            'logo_filename' => '',
            'logo_url' => '',
        );
    }

    /**
     * @param array<string,string> $banner_data Banner data.
     * @param array<string,mixed>  $offer Offer data.
     *
     * @return string
     */
    protected function build_cta_url( $banner_data, $offer ) {
        $base_url = isset( $banner_data['cta_url'] ) ? (string) $banner_data['cta_url'] : '';

        if ( '' !== $base_url ) {
            $query_args = array(
                'offer_id'   => (string) ( $offer['id'] ?? '' ),
                'offer_name' => (string) ( $offer['name'] ?? '' ),
            );

            return esc_url_raw( add_query_arg( $query_args, $base_url ) );
        }

        return '';
    }

    /**
     * @param string $cta_url CTA destination URL.
     *
     * @return bool
     */
    protected function is_valid_frontend_winner_cta_url( $cta_url ) {
        $cta_url = trim( (string) $cta_url );
        if ( '' === $cta_url ) {
            return false;
        }

        $lower = strtolower( rawurldecode( $cta_url ) );
        if ( false !== strpos( $lower, 'preview' ) ) {
            return false;
        }
        if ( false !== strpos( $lower, 'template' ) ) {
            return false;
        }
        if ( false !== strpos( $lower, 'advertisingpolicies.com' ) ) {
            return false;
        }
        if ( false !== strpos( $lower, 'transaction_id=preview' ) ) {
            return false;
        }
        if ( false !== strpos( $lower, 'aid=affiliate_id' ) ) {
            return false;
        }
        if ( false !== strpos( $lower, 'affiliate_id' ) ) {
            return false;
        }
        if ( false !== strpos( $lower, 'src=source' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Public wrapper for unavailable-account offer checks.
     *
     * @param array<string,mixed> $offer Offer payload.
     *
     * @return bool
     */
    public function is_offer_unavailable_account_pps( $offer ) {
        return $this->is_unavailable_account_pps_offer( $offer );
    }

    /**
     * @param string $cta_url URL candidate.
     *
     * @return bool
     */
    public function is_valid_manual_final_url_override( $cta_url ) {
        return $this->is_valid_frontend_winner_cta_url( $cta_url );
    }

    /**
     * @param string                      $offer_id Offer ID.
     * @param array<string,mixed>         $settings Settings.
     * @param array<string,string>        $banner_data Banner data.
     * @param string                      $country Country code.
     * @param array<string,array<string,mixed>> $legacy_catalog Legacy catalog.
     *
     * @return array<string,string>
     */
    public function get_effective_offer_record( $offer_id, $settings, $banner_data, $country, $legacy_catalog ) {
        $offer_id      = (string) $offer_id;
        $synced_offers = $this->get_synced_offers();
        if ( ! isset( $synced_offers[ $offer_id ] ) ) {
            return array();
        }

        $synced_offer = $synced_offers[ $offer_id ];
        $override     = $this->get_offer_override( $offer_id );

        if ( ! empty( $synced_offer['status'] ) && 'active' !== strtolower( (string) $synced_offer['status'] ) ) {
            return array();
        }

        if ( ! $this->is_offer_allowed_for_country( $offer_id, $country, $override, $synced_offer, $legacy_catalog ) ) {
            return array();
        }

        $name = (string) ( $synced_offer['name'] ?? $offer_id );
        if ( ! empty( $override['label_override'] ) ) {
            $name = (string) $override['label_override'];
        }

        return array(
            'id'       => $offer_id,
            'name'     => $name,
            'image'    => $this->get_effective_image( $offer_id, $settings, $banner_data, $synced_offer, $override, $legacy_catalog ),
            'cta_url'  => $this->get_effective_cta_url( $offer_id, $settings, $banner_data, $synced_offer, $override ),
            'cta_text' => $this->get_effective_cta_text( $offer_id, $settings, $banner_data, $synced_offer, $override, $legacy_catalog ),
            'brand_key' => $this->get_offer_brand_key( (string) ( $synced_offer['name'] ?? '' ) ),
            'logo_filename' => $this->get_offer_logo_filename( $synced_offer ),
            'logo_url' => $this->get_offer_logo_url( $synced_offer ),
        );
    }

    /**
     * @param string              $offer_id Offer ID.
     * @param array<string,mixed> $override Override row.
     * @param array<string,mixed> $settings Settings payload.
     * @param array<string,string> $banner_data Banner data.
     * @param string              $country Visitor country.
     * @param array<string,array<string,mixed>> $legacy_catalog Legacy fallback catalog.
     *
     * @return array<string,string>
     */
    protected function get_override_only_effective_offer_record( $offer_id, $override, $settings, $banner_data, $country, $legacy_catalog ) {
        if ( '' === (string) $offer_id ) {
            return array();
        }
        if ( isset( $override['enabled'] ) && ( false === $override['enabled'] || '0' === (string) $override['enabled'] || 0 === (int) $override['enabled'] ) ) {
            return array();
        }
        if ( empty( $override['final_url_override'] ) ) {
            return array();
        }

        $final_url_override = (string) $override['final_url_override'];
        if ( ! $this->is_valid_manual_final_url_override( $final_url_override ) || ! $this->is_valid_frontend_winner_cta_url( $final_url_override ) ) {
            return array();
        }

        $allowed_countries = $this->sanitize_country_names( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() );
        if ( empty( $allowed_countries ) || ! $this->is_country_allowed_by_name_or_alias( $country, $allowed_countries ) ) {
            return array();
        }
        $blocked_countries = $this->sanitize_country_names( isset( $override['blocked_countries'] ) ? $override['blocked_countries'] : array() );
        if ( ! empty( $blocked_countries ) && $this->is_country_allowed_by_name_or_alias( $country, $blocked_countries ) ) {
            return array();
        }

        $name = sanitize_text_field( (string) ( $override['label_override'] ?? '' ) );
        if ( '' === $name ) {
            $name = sanitize_text_field( (string) ( $override['offer_name'] ?? '' ) );
        }
        if ( '' === $name && isset( $legacy_catalog[ (string) $offer_id ] ) && is_array( $legacy_catalog[ (string) $offer_id ] ) ) {
            $name = sanitize_text_field( (string) ( $legacy_catalog[ (string) $offer_id ]['name'] ?? '' ) );
        }
        if ( '' === $name ) {
            $identity_map = $this->get_manual_override_offer_identity_map();
            if ( isset( $identity_map[ (string) $offer_id ]['name'] ) ) {
                $name = sanitize_text_field( (string) $identity_map[ (string) $offer_id ]['name'] );
            }
        }
        if ( '' === $name ) {
            return array();
        }

        $offer_stub = array(
            'id' => (string) $offer_id,
            'name' => $name,
            'status' => 'active',
        );
        if ( ! $this->is_offer_type_allowed( $offer_stub, $settings ) || $this->is_offer_blocked_for_banner( $offer_stub, $settings ) ) {
            return array();
        }
        if ( $this->is_unavailable_account_pps_offer( $offer_stub ) ) {
            $this->log_unavailable_account_offer_excluded( $offer_stub );
            return array();
        }

        $logo_url = $this->get_offer_logo_url( $offer_stub );
        if ( '' === $logo_url ) {
            return array();
        }

        return array(
            'id' => (string) $offer_id,
            'name' => $name,
            'image' => $this->build_placeholder_image( $name ),
            'cta_url' => esc_url_raw( $final_url_override ),
            'cta_text' => (string) ( $banner_data['cta_text'] ?? '' ),
            'source' => 'manual_override_only',
            'brand_key' => $this->get_offer_brand_key( $name ),
            'logo_filename' => $this->get_offer_logo_filename( $offer_stub ),
            'logo_url' => $logo_url,
        );
    }

    /**
     * @param string $offer_id Offer ID.
     * @param string $country Country code.
     * @param array<string,mixed> $override Override row.
     * @param array<string,mixed> $synced_offer Synced offer.
     * @param array<string,array<string,mixed>> $legacy_catalog Legacy catalog.
     *
     * @return bool
     */
    public function is_offer_allowed_for_country( $offer_id, $country, $override, $synced_offer, $legacy_catalog ) {
        $offer_id = (string) $offer_id;
        $country  = strtoupper( sanitize_text_field( (string) $country ) );

        if ( isset( $override['enabled'] ) && 0 === (int) $override['enabled'] ) {
            return false;
        }

        $allowed = isset( $override['allowed_countries'] ) ? $this->sanitize_country_names( $override['allowed_countries'] ) : array();
        if ( ! empty( $allowed ) && ! $this->is_country_allowed_by_name_or_alias( $country, $allowed ) ) {
            error_log( sprintf( '[TMW-BANNER-COUNTRY] country_excluded offer_id=%1$s visitor_country="%2$s" reason="not_allowed"', $offer_id, $country ) );
            return false;
        }

        $blocked = isset( $override['blocked_countries'] ) ? $this->sanitize_country_codes( $override['blocked_countries'] ) : array();
        if ( ! empty( $country ) && in_array( $country, $blocked, true ) ) {
            return false;
        }

        if ( empty( $allowed ) && empty( $blocked ) && empty( $synced_offer ) && isset( $legacy_catalog[ $offer_id ] ) ) {
            $legacy_countries = isset( $legacy_catalog[ $offer_id ]['countries'] ) ? (array) $legacy_catalog[ $offer_id ]['countries'] : array();
            $legacy_countries = $this->sanitize_country_codes( $legacy_countries );

            if ( ! empty( $legacy_countries ) && '' !== $country && ! in_array( $country, $legacy_countries, true ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $offer_id Offer ID.
     * @param array<string,mixed> $settings Settings.
     * @param array<string,string> $banner_data Banner data.
     * @param array<string,mixed> $synced_offer Synced offer.
     * @param array<string,mixed> $override Override.
     *
     * @return string
     */
    public function get_effective_cta_url( $offer_id, $settings, $banner_data, $synced_offer, $override ) {
        unset( $settings );

        if ( ! empty( $override['final_url_override'] ) ) {
            $this->log_frontend_cta_source( $offer_id, 'final_url_override' );
            return esc_url_raw( (string) $override['final_url_override'] );
        }

        $tracking_url = isset( $synced_offer['tracking_url'] ) ? esc_url_raw( (string) $synced_offer['tracking_url'] ) : '';
        if ( '' !== $tracking_url && 'tracking_url' === $this->classify_url_audit_reason( $tracking_url ) ) {
            $this->log_tracking_url_synced( $offer_id, $tracking_url );
            $this->log_frontend_cta_source( $offer_id, 'tracking_url' );
            return $tracking_url;
        }

        $this->log_tracking_url_missing( $offer_id, '' === $tracking_url ? 'api_field_missing' : 'invalid_tracking_url' );

        $fallback = $this->build_cta_url( $banner_data, $synced_offer );
        if ( '' !== $fallback && $this->is_valid_frontend_winner_cta_url( $fallback ) ) {
            $this->log_frontend_cta_source( $offer_id, 'global_cta_url' );
            return $fallback;
        }

        $this->log_frontend_cta_source( $offer_id, 'none' );
        return '';
    }

    /**
     * Builds URL field diagnostics for synced PPS offers.
     *
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return array<string,mixed>
     */
    public function get_cr_url_field_audit_summary( $settings ) {
        $offers = $this->get_synced_offers();
        $summary = array(
            'synced_pps_offers_checked' => 0,
            'offers_with_tracking_url' => 0,
            'offers_with_preview_template_url_only' => 0,
            'offers_with_raw_advertiser_url_only' => 0,
            'offers_with_empty_url' => 0,
            'offers_with_unresolved_placeholders' => 0,
            'offers_excluded_by_invalid_cta_validation' => 0,
            'field_counts' => array(),
            'field_hosts' => array(),
        );
        $url_fields = array( 'preview_url', 'tracking_url', 'final_url', 'offer_url', 'campaign_url', 'landing_page', 'landing_page_url', 'destination_url', 'cta_url', 'url' );

        foreach ( $offers as $offer_id => $offer ) {
            if ( ! $this->is_offer_type_allowed( $offer, array( 'allowed_offer_types' => array( 'pps' ) ) ) ) {
                continue;
            }
            ++$summary['synced_pps_offers_checked'];

            foreach ( $url_fields as $field ) {
                if ( ! isset( $offer[ $field ] ) || '' === trim( (string) $offer[ $field ] ) ) {
                    continue;
                }
                if ( ! isset( $summary['field_counts'][ $field ] ) ) {
                    $summary['field_counts'][ $field ] = 0;
                    $summary['field_hosts'][ $field ] = array();
                }
                ++$summary['field_counts'][ $field ];
                $host = parse_url( (string) $offer[ $field ], PHP_URL_HOST );
                if ( is_string( $host ) && '' !== $host ) {
                    $summary['field_hosts'][ $field ][ strtolower( $host ) ] = true;
                }
            }

            $effective_url = $this->get_effective_cta_url( (string) $offer_id, $settings, array( 'cta_url' => (string) ( $settings['cta_url'] ?? '' ) ), $offer, $this->get_offer_override( (string) $offer_id ) );
            $reason = $this->classify_url_audit_reason( $effective_url );
            if ( 'tracking_url' === $reason ) {
                ++$summary['offers_with_tracking_url'];
            } elseif ( 'preview_template_only' === $reason ) {
                ++$summary['offers_with_preview_template_url_only'];
            } elseif ( 'raw_advertiser_only' === $reason ) {
                ++$summary['offers_with_raw_advertiser_url_only'];
            } elseif ( 'empty_url' === $reason ) {
                ++$summary['offers_with_empty_url'];
            } elseif ( 'unresolved_placeholders' === $reason ) {
                ++$summary['offers_with_unresolved_placeholders'];
            }
            if ( ! $this->is_valid_frontend_winner_cta_url( (string) $effective_url ) ) {
                ++$summary['offers_excluded_by_invalid_cta_validation'];
            }
        }

        foreach ( $summary['field_hosts'] as $field => $hosts_map ) {
            $hosts = array_keys( $hosts_map );
            sort( $hosts );
            $summary['field_hosts'][ $field ] = array_slice( $hosts, 0, 5 );
        }

        return $summary;
    }

    /**
     * @return array<string,int>
     */
    public function get_manual_override_diagnostics() {
        $overrides = $this->get_offer_overrides();
        $counts = array(
            'manual_final_url_overrides' => 0,
            'invalid_manual_url_overrides_rejected' => 0,
            'manual_allowed_country_overrides' => 0,
        );

        foreach ( $overrides as $override ) {
            if ( ! empty( $this->sanitize_country_names( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() ) ) ) {
                ++$counts['manual_allowed_country_overrides'];
            }
            if ( empty( $override['final_url_override'] ) ) {
                continue;
            }
            ++$counts['manual_final_url_overrides'];
            if ( ! $this->is_valid_manual_final_url_override( (string) $override['final_url_override'] ) ) {
                ++$counts['invalid_manual_url_overrides_rejected'];
            }
        }

        return $counts;
    }

    protected function normalize_country_name( $country ) {
        return strtolower( trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $country ) ) ) );
    }

    protected function get_country_alias_map() {
        return array(
            'be' => 'belgium',
            'us' => 'united states',
            'gb' => 'united kingdom',
            'uk' => 'united kingdom',
            'ca' => 'canada',
            'de' => 'germany',
            'fr' => 'france',
            'nl' => 'netherlands',
            'au' => 'australia',
        );
    }

    /**
     * @return array<string,array<string,string>>
     */
    protected function get_manual_override_offer_identity_map() {
        return array(
            '8780' => array( 'brand_key' => 'jerkmate', 'name' => 'Jerkmate - PPS' ),
            '10366' => array( 'brand_key' => 'naughtycharm', 'name' => 'NaughtyCharm - PPS' ),
        );
    }

    protected function normalize_country_for_match( $country ) {
        $normalized = $this->normalize_country_name( $country );
        $alias_map = $this->get_country_alias_map();
        return isset( $alias_map[ $normalized ] ) ? $alias_map[ $normalized ] : $normalized;
    }

    protected function is_country_allowed_by_name_or_alias( $country, $allowed ) {
        $needle = $this->normalize_country_for_match( $country );
        if ( '' === $needle ) {
            return false;
        }
        foreach ( $allowed as $allowed_country ) {
            if ( $needle === $this->normalize_country_for_match( $allowed_country ) ) {
                return true;
            }
        }
        return false;
    }

    protected function evaluate_offer_eligibility( $offer_id, $settings, $banner_data, $country, $legacy_catalog ) {
        $effective = $this->get_effective_offer_record( $offer_id, $settings, $banner_data, $country, $legacy_catalog );
        if ( empty( $effective ) ) {
            $synced_offer = $this->get_synced_offer( $offer_id );
            $override = $this->get_offer_override( $offer_id );
            if ( empty( $synced_offer ) ) {
                $override_effective = $this->get_override_only_effective_offer_record( (string) $offer_id, $override, $settings, $banner_data, $country, $legacy_catalog );
                if ( ! empty( $override_effective ) && $this->is_valid_frontend_winner_cta_url( (string) ( $override_effective['cta_url'] ?? '' ) ) ) {
                    return array( 'reason' => self::ELIGIBILITY_REASON_VALID, 'effective_offer' => $override_effective );
                }
                if ( empty( $override['final_url_override'] ) ) {
                    return array( 'reason' => self::ELIGIBILITY_REASON_MISSING_FINAL_URL, 'effective_offer' => array() );
                }
                if ( ! $this->is_valid_manual_final_url_override( (string) $override['final_url_override'] ) || ! $this->is_valid_frontend_winner_cta_url( (string) $override['final_url_override'] ) ) {
                    return array( 'reason' => self::ELIGIBILITY_REASON_INVALID_FINAL_URL, 'effective_offer' => array() );
                }
                $allowed = $this->sanitize_country_names( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() );
                if ( empty( $allowed ) ) {
                    return array( 'reason' => self::ELIGIBILITY_REASON_NO_MANUAL_COUNTRY_OVERRIDE, 'effective_offer' => array() );
                }
                if ( ! $this->is_country_allowed_by_name_or_alias( $country, $allowed ) ) {
                    return array( 'reason' => self::ELIGIBILITY_REASON_COUNTRY_NOT_ALLOWED, 'effective_offer' => array() );
                }
                return array( 'reason' => self::ELIGIBILITY_REASON_INVALID_FINAL_URL, 'effective_offer' => array() );
            }
            if ( ! $this->is_offer_type_allowed( $synced_offer, $settings ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_OFFER_TYPE_NOT_ALLOWED, 'effective_offer' => array() );
            }
            if ( $this->is_offer_blocked_for_banner( $synced_offer, $settings ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_BLOCKED_OFFER, 'effective_offer' => array() );
            }
            if ( $this->is_unavailable_account_pps_offer( $synced_offer ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_UNAVAILABLE_OFFER, 'effective_offer' => array() );
            }
            if ( ! $this->is_offer_allowed_for_country( $offer_id, $country, $override, $synced_offer, $legacy_catalog ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_COUNTRY_NOT_ALLOWED, 'effective_offer' => array() );
            }
            if ( empty( $override['final_url_override'] ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_MISSING_FINAL_URL, 'effective_offer' => array() );
            }
            if ( ! $this->is_valid_manual_final_url_override( (string) $override['final_url_override'] ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_INVALID_FINAL_URL, 'effective_offer' => array() );
            }
            if ( '' === (string) $this->get_offer_logo_url( $synced_offer ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_MISSING_LOGO, 'effective_offer' => array() );
            }
            if ( empty( $this->sanitize_country_names( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() ) ) ) {
                return array( 'reason' => self::ELIGIBILITY_REASON_NO_MANUAL_COUNTRY_OVERRIDE, 'effective_offer' => array() );
            }
            return array( 'reason' => self::ELIGIBILITY_REASON_INVALID_FINAL_URL, 'effective_offer' => array() );
        }
        return array( 'reason' => self::ELIGIBILITY_REASON_VALID, 'effective_offer' => $effective );
    }

    protected function get_synced_offer( $offer_id ) {
        $offers = $this->get_synced_offers();
        return isset( $offers[ (string) $offer_id ] ) && is_array( $offers[ (string) $offer_id ] ) ? $offers[ (string) $offer_id ] : array();
    }

    protected function log_eligibility_event( $offer_id, $reason, $country ) {
        if ( self::ELIGIBILITY_REASON_VALID === $reason ) {
            error_log( sprintf( '[TMW-BANNER-ELIGIBILITY] offer_id=%1$s result=eligible country="%2$s"', (string) $offer_id, (string) $country ) );
            return;
        }
        error_log( sprintf( '[TMW-BANNER-ELIGIBILITY] offer_id=%1$s result=excluded reason="%2$s" country="%3$s"', (string) $offer_id, (string) $reason, (string) $country ) );
    }

    public function get_manual_winner_eligibility_audit_rows( $settings, $banner_data, $country, $legacy_catalog ) {
        $rows = array();
        foreach ( $this->get_offer_overrides() as $offer_id => $override ) {
            if ( empty( $override['final_url_override'] ) && empty( $override['allowed_countries'] ) ) {
                continue;
            }
            $synced_offer = $this->get_synced_offer( (string) $offer_id );
            $allowed = $this->sanitize_country_names( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() );
            $evaluation = $this->evaluate_offer_eligibility( (string) $offer_id, $settings, $banner_data, $country, $legacy_catalog );
            $rows[] = array(
                'offer_id' => (string) $offer_id,
                'offer_name' => (string) ( $synced_offer['name'] ?? '' ),
                'has_final_url_override' => ! empty( $override['final_url_override'] ),
                'final_url_host' => (string) parse_url( (string) ( $override['final_url_override'] ?? '' ), PHP_URL_HOST ),
                'has_allowed_country_override' => ! empty( $allowed ),
                'allowed_countries_count' => count( $allowed ),
                'visitor_country_raw' => (string) $country,
                'visitor_country_normalized' => $this->normalize_country_for_match( $country ),
                'eligibility_result' => self::ELIGIBILITY_REASON_VALID === $evaluation['reason'] ? 'eligible' : 'excluded',
                'exclusion_reason' => (string) $evaluation['reason'],
            );
        }
        return $rows;
    }

    /**
     * @param array<string,mixed> $settings Settings payload.
     * @param array<string,mixed> $banner_data Banner payload.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_pps_expansion_readiness_audit_rows( $settings, $banner_data = array() ) {
        $rows           = array();
        $synced_offers  = $this->get_synced_offers();
        $overrides      = $this->get_offer_overrides();
        $legacy_catalog = $this->get_default_legacy_catalog();
        $seen_ids       = array();

        $add_row = function( $offer_id, $offer, $source ) use ( &$rows, &$seen_ids, $settings, $banner_data, $overrides, $legacy_catalog ) {
            $id = (string) $offer_id;
            if ( '' === $id || isset( $seen_ids[ $id ] ) ) {
                return;
            }
            $seen_ids[ $id ] = true;
            $override        = isset( $overrides[ $id ] ) && is_array( $overrides[ $id ] ) ? $overrides[ $id ] : array();
            $offer_name      = (string) ( $offer['name'] ?? '' );
            $pps_detected    = $this->is_offer_type_allowed( $offer, array( 'allowed_offer_types' => array( 'pps' ) ) );
            $is_unavailable  = $this->is_unavailable_account_pps_offer( $offer );
            $is_rule_blocked = $this->is_offer_blocked_for_banner( $offer, $settings );
            $blocked         = $is_rule_blocked || $is_unavailable;
            $has_identity    = true;
            if ( 'manual_override_only' === $source ) {
                $has_identity = '' !== $offer_name && '' !== $this->get_offer_logo_filename( $offer );
            }

            $cta_source = 'none';
            $cta_url    = '';
            if ( ! empty( $override['final_url_override'] ) ) {
                $cta_source = $this->is_valid_manual_final_url_override( (string) $override['final_url_override'] ) ? 'final_url_override' : 'invalid';
                $cta_url    = (string) $override['final_url_override'];
            } elseif ( ! empty( $offer['tracking_url'] ) ) {
                $tracking_reason = $this->classify_url_audit_reason( (string) $offer['tracking_url'] );
                $cta_source      = 'tracking_url' === $tracking_reason ? 'tracking_url' : 'invalid';
                $cta_url         = (string) $offer['tracking_url'];
            }

            $allowed_countries   = $this->sanitize_country_names( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() );
            $has_allowed         = ! empty( $allowed_countries );
            $example_be_eligible = $this->evaluate_offer_eligibility( $id, $settings, $banner_data, 'BE', $legacy_catalog );
            $example_us_eligible = $this->evaluate_offer_eligibility( $id, $settings, $banner_data, 'US', $legacy_catalog );
            $be_valid            = 'valid' === (string) ( $example_be_eligible['reason'] ?? '' );
            $us_valid            = 'valid' === (string) ( $example_us_eligible['reason'] ?? '' );
            $country_ready       = $be_valid || $us_valid;
            $logo_filename       = $this->get_offer_logo_filename( $offer );
            $logo_resolved       = '' !== $logo_filename;
            $has_valid_cta       = in_array( $cta_source, array( 'final_url_override', 'tracking_url' ), true );
            $frontend_ready      = $pps_detected && ! $blocked && $has_allowed && $logo_resolved && $has_valid_cta && $country_ready && $has_identity;
            $block_reason        = 'valid';
            if ( ! $has_identity && 'manual_override_only' === $source ) {
                $block_reason = 'unknown_override_only_identity';
            } elseif ( ! $pps_detected ) {
                $block_reason = 'not_pps';
            } elseif ( $is_rule_blocked ) {
                $block_reason = 'business_rule_blocked';
            } elseif ( $is_unavailable ) {
                $block_reason = 'unavailable_account_offer';
            } elseif ( ! $has_valid_cta ) {
                $block_reason = 'missing_valid_cta';
            } elseif ( ! $has_allowed ) {
                $block_reason = 'missing_allowed_country_override';
            } elseif ( ! $country_ready ) {
                $block_reason = 'country_not_allowed';
            } elseif ( ! $logo_resolved ) {
                $block_reason = 'missing_logo';
            }

            $rows[] = array(
                'offer_id'                    => $id,
                'offer_name'                  => $offer_name,
                'source'                      => $source,
                'pps_detected'                => $pps_detected ? 'yes' : 'no',
                'blocked_by_business_rule'    => $blocked ? 'yes' : 'no',
                'block_reason'                => $block_reason,
                'final_cta_source'            => $cta_source,
                'final_cta_host'              => (string) parse_url( $cta_url, PHP_URL_HOST ),
                'has_allowed_country_override'=> $has_allowed ? 'yes' : 'no',
                'allowed_countries_count'     => count( $allowed_countries ),
                'example_be_result'           => ( 'valid' === (string) ( $example_be_eligible['reason'] ?? '' ) ) ? 'eligible' : 'excluded',
                'example_us_result'           => ( 'valid' === (string) ( $example_us_eligible['reason'] ?? '' ) ) ? 'eligible' : 'excluded',
                'logo_resolved'               => $logo_resolved ? 'yes' : 'no',
                'logo_filename'               => $logo_filename,
                'frontend_ready'              => $frontend_ready ? 'yes' : 'no',
            );
        };

        foreach ( $synced_offers as $offer_id => $offer ) {
            if ( ! is_array( $offer ) ) {
                continue;
            }
            $add_row( $offer_id, $offer, 'synced' );
        }
        foreach ( $overrides as $offer_id => $override ) {
            if ( isset( $synced_offers[ (string) $offer_id ] ) ) {
                continue;
            }
            $identity_map = $this->get_manual_override_offer_identity_map();
            $identity     = isset( $identity_map[ (string) $offer_id ] ) ? $identity_map[ (string) $offer_id ] : array();
            $add_row(
                $offer_id,
                array( 'id' => (string) $offer_id, 'name' => (string) ( $identity['name'] ?? ( $override['label_override'] ?? '' ) ) ),
                'manual_override_only'
            );
        }
        foreach ( $legacy_catalog as $legacy_offer ) {
            if ( ! is_array( $legacy_offer ) ) {
                continue;
            }
            $legacy_id = (string) ( $legacy_offer['id'] ?? '' );
            if ( '' === $legacy_id ) {
                continue;
            }
            $add_row( $legacy_id, $legacy_offer, 'legacy' );
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows Audit rows.
     *
     * @return array<string,int>
     */
    public function get_pps_expansion_readiness_audit_summary( $rows ) {
        $summary = array(
            'total_pps_candidates' => 0,
            'frontend_ready_pps_offers' => 0,
            'blocked_by_business_rule' => 0,
            'missing_valid_cta' => 0,
            'missing_allowed_country_override' => 0,
            'missing_logo' => 0,
            'override_only_candidates' => 0,
            'synced_candidates' => 0,
        );
        foreach ( $rows as $row ) {
            if ( 'yes' === (string) ( $row['pps_detected'] ?? 'no' ) ) {
                ++$summary['total_pps_candidates'];
            }
            if ( 'yes' === (string) ( $row['frontend_ready'] ?? 'no' ) ) {
                ++$summary['frontend_ready_pps_offers'];
            }
            if ( 'business_rule_blocked' === (string) ( $row['block_reason'] ?? '' ) || 'unavailable_account_offer' === (string) ( $row['block_reason'] ?? '' ) ) {
                ++$summary['blocked_by_business_rule'];
            }
            if ( 'missing_valid_cta' === (string) ( $row['block_reason'] ?? '' ) ) {
                ++$summary['missing_valid_cta'];
            }
            if ( 'missing_allowed_country_override' === (string) ( $row['block_reason'] ?? '' ) ) {
                ++$summary['missing_allowed_country_override'];
            }
            if ( 'missing_logo' === (string) ( $row['block_reason'] ?? '' ) ) {
                ++$summary['missing_logo'];
            }
            if ( 'manual_override_only' === (string) ( $row['source'] ?? '' ) ) {
                ++$summary['override_only_candidates'];
            }
            if ( 'synced' === (string) ( $row['source'] ?? '' ) ) {
                ++$summary['synced_candidates'];
            }
        }
        return $summary;
    }

    protected function sanitize_country_names( $countries ) {
        if ( is_string( $countries ) ) {
            $countries = preg_split( '/[|,]/', $countries );
        }
        $unique = array();
        foreach ( (array) $countries as $country ) {
            $display = trim( sanitize_text_field( (string) $country ) );
            if ( '' === $display ) {
                continue;
            }
            if ( preg_match( '/^[a-z]{2}$/i', $display ) ) {
                $display = strtoupper( $display );
            }
            $key = $this->normalize_country_name( $display );
            if ( '' !== $key ) {
                $unique[ $key ] = $display;
            }
        }
        return array_values( $unique );
    }

    public function get_sanitized_country_names( $countries ) {
        return $this->sanitize_country_names( $countries );
    }

    /**
     * @param string $url URL candidate.
     *
     * @return string
     */
    protected function classify_url_audit_reason( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return 'empty_url';
        }
        $lower = strtolower( rawurldecode( $url ) );
        $host  = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

        if ( false !== strpos( $lower, 'affiliate_id=affiliate_id' ) || false !== strpos( $lower, 'transaction_id=preview' ) || false !== strpos( $lower, 'aid=affiliate_id' ) || false !== strpos( $lower, 'src=source' ) || false !== strpos( $lower, '{' ) ) {
            return 'unresolved_placeholders';
        }

        if ( $this->is_known_cr_tracking_host( $host ) ) {
            return 'tracking_url';
        }

        if ( false !== strpos( $lower, 'affiliate_id' ) || false !== strpos( $lower, 'transaction_id' ) || false !== strpos( $lower, 'subid=' ) ) {
            if ( false !== strpos( $lower, 'affiliate_id=' ) ) {
                return 'tracking_url';
            }
            if ( false !== strpos( $lower, 'transaction_id=' ) ) {
                return 'tracking_url';
            }
            if ( false !== strpos( $lower, 'subid=' ) && false === strpos( $lower, 'subid={') ) {
                return 'tracking_url';
            }
        }
        if ( false !== strpos( $lower, 'preview' ) || false !== strpos( $lower, 'template' ) ) {
            return 'preview_template_only';
        }
        return 'raw_advertiser_only';
    }

    /**
     * @param string $host URL hostname.
     *
     * @return bool
     */
    protected function is_known_cr_tracking_host( $host ) {
        $host = strtolower( trim( (string) $host ) );
        if ( '' === $host ) {
            return false;
        }

        $known_fragments = array(
            'crakrevenue.com',
            'crakmedia.com',
        );

        foreach ( $known_fragments as $fragment ) {
            if ( false !== strpos( $host, $fragment ) ) {
                return true;
            }
        }

        return false;
    }

    protected function log_tracking_url_synced( $offer_id, $url ) {
        $host = (string) parse_url( (string) $url, PHP_URL_HOST );
        error_log( sprintf( '[TMW-BANNER-LINK] tracking_url_synced offer_id=%1$s host="%2$s"', sanitize_text_field( (string) $offer_id ), sanitize_text_field( strtolower( $host ) ) ) );
    }

    protected function log_tracking_url_missing( $offer_id, $reason ) {
        error_log( sprintf( '[TMW-BANNER-LINK] tracking_url_missing offer_id=%1$s reason="%2$s"', sanitize_text_field( (string) $offer_id ), sanitize_key( (string) $reason ) ) );
    }

    protected function log_frontend_cta_source( $offer_id, $source ) {
        error_log( sprintf( '[TMW-BANNER-LINK] frontend_cta_source offer_id=%1$s source="%2$s"', sanitize_text_field( (string) $offer_id ), sanitize_key( (string) $source ) ) );
    }

    /**
     * @param string $offer_id Offer ID.
     * @param array<string,mixed> $settings Settings.
     * @param array<string,string> $banner_data Banner data.
     * @param array<string,mixed> $synced_offer Synced offer.
     * @param array<string,mixed> $override Override.
     * @param array<string,array<string,mixed>> $legacy_catalog Legacy catalog.
     *
     * @return string
     */
    public function get_effective_image( $offer_id, $settings, $banner_data, $synced_offer, $override, $legacy_catalog = array() ) {
        unset( $banner_data );

        return $this->resolve_synced_offer_image( $synced_offer, $settings, $legacy_catalog, $override );
    }

    /**
     * [TMW-CR-IMG] Resolves synced offer image with layered fallback strategy.
     *
     * Resolution order:
     * 1) Per-offer control-layer override (`offer_overrides[].image_url_override`)
     * 2) Legacy `offer_image_overrides`
     * 3) Local bundled catalog match (name + aliases)
     * 4) Explicit remote thumbnail map
     * 5) Placeholder SVG
     *
     * @param array<string,mixed>              $offer Synced offer payload.
     * @param array<string,mixed>              $settings Plugin settings payload.
     * @param array<string,array<string,mixed>> $legacy_catalog Bundled local catalog.
     * @param array<string,mixed>              $overrides Offer-level override payload.
     *
     * @return string
     */
    public function resolve_synced_offer_image( $offer, $settings, $legacy_catalog, $overrides = array() ) {
        $offer_id   = (string) ( $offer['id'] ?? '' );
        $offer_name = (string) ( $offer['name'] ?? $offer_id );

        if ( ! empty( $overrides['image_url_override'] ) ) {
            return esc_url_raw( (string) $overrides['image_url_override'] );
        }

        $image_map = isset( $settings['offer_image_overrides'] ) && is_array( $settings['offer_image_overrides'] ) ? $settings['offer_image_overrides'] : array();
        if ( '' !== $offer_id && ! empty( $image_map[ $offer_id ] ) ) {
            return esc_url_raw( (string) $image_map[ $offer_id ] );
        }

        $local_image = $this->resolve_local_catalog_image( $offer_name, $legacy_catalog );
        if ( '' !== $local_image ) {
            return $local_image;
        }

        $remote_image = $this->resolve_remote_thumbnail_image( $offer_name );
        if ( '' !== $remote_image ) {
            return $remote_image;
        }

        return $this->build_placeholder_image( $offer_name );
    }

    /**
     * @param string $name Raw offer name.
     *
     * @return string
     */
    public function normalize_offer_name_for_image_match( $name ) {
        $name = strtolower( sanitize_text_field( (string) $name ) );
        $name = str_replace( '&', ' and ', $name );
        $name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
        $name = preg_replace( '/\s+/', ' ', (string) $name );

        return trim( (string) $name );
    }

    /**
     * @param string                           $offer_name Offer name.
     * @param array<string,array<string,mixed>> $legacy_catalog Bundled offer catalog.
     *
     * @return string
     */
    public function resolve_local_catalog_image( $offer_name, $legacy_catalog ) {
        $needle = $this->normalize_offer_name_for_image_match( $offer_name );
        if ( '' === $needle || empty( $legacy_catalog ) ) {
            return '';
        }

        foreach ( $legacy_catalog as $legacy_offer ) {
            $candidates = array();
            $candidates[] = (string) ( $legacy_offer['name'] ?? '' );
            $candidates[] = (string) ( $legacy_offer['id'] ?? '' );

            if ( ! empty( $legacy_offer['aliases'] ) && is_array( $legacy_offer['aliases'] ) ) {
                $candidates = array_merge( $candidates, $legacy_offer['aliases'] );
            }

            foreach ( $candidates as $candidate ) {
                $normalized = $this->normalize_offer_name_for_image_match( (string) $candidate );
                if ( '' !== $normalized && $needle === $normalized ) {
                    $file = isset( $legacy_offer['filename'] ) ? (string) $legacy_offer['filename'] : '';
                    if ( '' !== $file ) {
                        return TMW_CR_Slot_Sidebar_Banner::asset_url( 'assets/img/offers/' . $file );
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param string $offer_name Offer name.
     *
     * @return string
     */
    public function resolve_remote_thumbnail_image( $offer_name ) {
        $needle = $this->normalize_offer_name_for_image_match( $offer_name );
        $map    = $this->get_remote_thumbnail_map();

        return isset( $map[ $needle ] ) ? esc_url_raw( (string) $map[ $needle ] ) : '';
    }

    /**
     * [TMW-CR-IMG] Curated explicit map for known brands not yet bundled locally.
     *
     * @return array<string,string>
     */
    public function get_remote_thumbnail_map() {
        return array(
            'onlyfans' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/48/OnlyFans_logo.svg/640px-OnlyFans_logo.svg.png',
            'fansly'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/Fansly_logo.svg/640px-Fansly_logo.svg.png',
            'stripchat' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Stripchat_logo.svg/640px-Stripchat_logo.svg.png',
        );
    }

    /**
     * @param string $offer_id Offer ID.
     * @param array<string,mixed> $settings Settings.
     * @param array<string,string> $banner_data Banner data.
     * @param array<string,mixed> $synced_offer Synced offer.
     * @param array<string,mixed> $override Override.
     * @param array<string,array<string,mixed>> $legacy_catalog Legacy catalog.
     *
     * @return string
     */
    public function get_effective_cta_text( $offer_id, $settings, $banner_data, $synced_offer, $override, $legacy_catalog ) {
        unset( $settings );

        if ( ! empty( $override['custom_cta_text'] ) ) {
            return (string) $override['custom_cta_text'];
        }

        if ( ! empty( $synced_offer['cta_text'] ) ) {
            return (string) $synced_offer['cta_text'];
        }

        if ( isset( $legacy_catalog[ $offer_id ] ) && ! empty( $legacy_catalog[ $offer_id ]['cta_text'] ) ) {
            return (string) $legacy_catalog[ $offer_id ]['cta_text'];
        }

        $global_cta = trim( (string) ( $banner_data['cta_text'] ?? '' ) );
        if ( '' !== $global_cta ) {
            return $global_cta;
        }

        if ( class_exists( 'TMW_CR_Slot_Sidebar_Banner' ) && defined( 'TMW_CR_Slot_Sidebar_Banner::DEFAULT_CTA_TEXT' ) ) {
            return (string) TMW_CR_Slot_Sidebar_Banner::DEFAULT_CTA_TEXT;
        }

        return 'TRY YOUR FREE SPINS';
    }

    /**
     * @return array<string,mixed>
     */
    public function get_performance_summary() {
        $stats                = $this->get_offer_stats();
        $total_clicks         = 0;
        $total_conversions    = 0.0;
        $total_payout         = 0.0;
        $offer_totals         = array();
        $country_totals       = array();

        foreach ( $stats as $row ) {
            $offer_id   = (string) ( $row['offer_id'] ?? '' );
            $offer_name = (string) ( $row['offer_name'] ?? $offer_id );
            $country    = strtoupper( (string) ( $row['country_name'] ?? 'GLOBAL' ) );
            $clicks     = max( 0, (int) ( $row['clicks'] ?? 0 ) );
            $conv       = (float) ( $row['conversions'] ?? 0 );
            $payout     = (float) ( $row['payout'] ?? 0 );

            $total_clicks      += $clicks;
            $total_conversions += $conv;
            $total_payout      += $payout;

            if ( '' !== $offer_id ) {
                if ( ! isset( $offer_totals[ $offer_id ] ) ) {
                    $offer_totals[ $offer_id ] = array( 'name' => $offer_name, 'payout' => 0.0 );
                }
                $offer_totals[ $offer_id ]['payout'] += $payout;
            }

            if ( ! isset( $country_totals[ $country ] ) ) {
                $country_totals[ $country ] = 0.0;
            }
            $country_totals[ $country ] += $payout;
        }

        arsort( $country_totals );

        $top_offer_name   = '';
        $top_country_name = '';
        $top_offer_payout = -1;
        foreach ( $offer_totals as $offer_total ) {
            if ( ! is_array( $offer_total ) ) {
                continue;
            }
            $payout = isset( $offer_total['payout'] ) ? (float) $offer_total['payout'] : 0.0;
            if ( $payout > $top_offer_payout ) {
                $top_offer_payout = $payout;
                $top_offer_name   = isset( $offer_total['name'] ) ? (string) $offer_total['name'] : '';
            }
        }
        if ( ! empty( $country_totals ) ) {
            $top_country_name = (string) key( $country_totals );
        }

        return array(
            'total_clicks'      => $total_clicks,
            'total_conversions' => round( $total_conversions, 4 ),
            'total_payout'      => round( $total_payout, 4 ),
            'top_offer_name'    => $top_offer_name,
            'top_country_name'  => $top_country_name,
        );
    }

    /**
     * @param string                      $country Country name/code.
     * @param array<string,mixed>         $args Filters.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_performance_rows( $country = '', $args = array() ) {
        $stats    = $this->get_offer_stats();
        $country  = strtoupper( sanitize_text_field( (string) $country ) );
        $status_filter = sanitize_key( (string) ( $args['status'] ?? '' ) );
        $payout_type_filter = sanitize_key( (string) ( $args['payout_type'] ?? '' ) );
        $offers_map = $this->get_synced_offers();
        $grouped  = array();

        foreach ( $stats as $row ) {
            $offer_id = (string) ( $row['offer_id'] ?? '' );
            if ( '' === $offer_id ) {
                continue;
            }
            $offer = isset( $offers_map[ $offer_id ] ) && is_array( $offers_map[ $offer_id ] ) ? $offers_map[ $offer_id ] : array();
            if ( '' !== $status_filter && $status_filter !== sanitize_key( (string) ( $offer['status'] ?? '' ) ) ) {
                continue;
            }

            $row_country = strtoupper( (string) ( $row['country_name'] ?? 'GLOBAL' ) );
            if ( '' !== $country && $country !== $row_country ) {
                continue;
            }

            if ( ! isset( $grouped[ $offer_id ] ) ) {
                $grouped[ $offer_id ] = array(
                    'offer_id'     => $offer_id,
                    'offer_name'   => (string) ( $row['offer_name'] ?? $offer_id ),
                    'clicks'       => 0,
                    'conversions'  => 0.0,
                    'payout'       => 0.0,
                    'payout_type'  => $this->normalize_filter_family_value( 'payout_type', (string) ( $row['payout_type'] ?? ( $offer['payout_type'] ?? '' ) ) ),
                    'status'       => (string) ( $offer['status'] ?? '' ),
                );
            }

            $grouped[ $offer_id ]['clicks'] += (int) ( $row['clicks'] ?? 0 );
            $grouped[ $offer_id ]['conversions'] += (float) ( $row['conversions'] ?? 0 );
            $grouped[ $offer_id ]['payout'] += (float) ( $row['payout'] ?? 0 );
        }

        foreach ( $grouped as $offer_id => $row ) {
            if ( '' !== $payout_type_filter && $payout_type_filter !== $this->normalize_filter_family_value( 'payout_type', (string) ( $row['payout_type'] ?? '' ) ) ) {
                unset( $grouped[ $offer_id ] );
                continue;
            }
            $grouped[ $offer_id ]['epc'] = $row['clicks'] > 0 ? round( $row['payout'] / $row['clicks'], 6 ) : 0.0;
            $grouped[ $offer_id ]['conversion_rate'] = $row['clicks'] > 0 ? round( ( $row['conversions'] / $row['clicks'] ) * 100, 4 ) : 0.0;
        }

        $rows = array_values( $grouped );
        $sort_by = isset( $args['sort_by'] ) ? sanitize_key( (string) $args['sort_by'] ) : 'payout';
        $sort_order = isset( $args['sort_order'] ) ? strtolower( (string) $args['sort_order'] ) : 'desc';

        usort(
            $rows,
            static function ( $left, $right ) use ( $sort_by, $sort_order ) {
                $l = isset( $left[ $sort_by ] ) ? $left[ $sort_by ] : 0;
                $r = isset( $right[ $sort_by ] ) ? $right[ $sort_by ] : 0;
                if ( $l === $r ) {
                    return strcasecmp( (string) $left['offer_name'], (string) $right['offer_name'] );
                }

                if ( 'asc' === $sort_order ) {
                    return $l <=> $r;
                }

                return $r <=> $l;
            }
        );

        return $rows;
    }

    /**
     * @param string                            $offer_id Offer id.
     * @param string                            $country Country.
     * @param array<string,array<string,mixed>> $stats Stats map.
     *
     * @return array<string,mixed>
     */
    protected function get_offer_metrics_for_country( $offer_id, $country, $stats, $optimization = array() ) {
        $offer_id = (string) $offer_id;
        $country  = strtoupper( (string) $country );
        $country_row = null;
        $global      = array( 'clicks' => 0, 'conversions' => 0.0, 'payout' => 0.0, 'epc' => 0.0, 'country_used' => '', 'country_clicks' => 0, 'fallback_to_global' => 1 );
        $optimization = $this->get_optimization_settings( $optimization );

        foreach ( $stats as $row ) {
            if ( $offer_id !== (string) ( $row['offer_id'] ?? '' ) ) {
                continue;
            }
            $row_country = strtoupper( (string) ( $row['country_name'] ?? 'GLOBAL' ) );
            $global['clicks'] += (int) ( $row['clicks'] ?? 0 );
            $global['conversions'] += (float) ( $row['conversions'] ?? 0 );
            $global['payout'] += (float) ( $row['payout'] ?? 0 );

            if ( '' !== $country && $country === $row_country ) {
                $country_row = $row;
            }
        }

        $global['epc'] = $global['clicks'] > 0 ? round( $global['payout'] / $global['clicks'], 6 ) : 0.0;

        if ( ! is_array( $country_row ) ) {
            return $global;
        }

        $country_clicks      = max( 0, (int) ( $country_row['clicks'] ?? 0 ) );
        $country_conversions = max( 0.0, (float) ( $country_row['conversions'] ?? 0 ) );
        $country_payout      = max( 0.0, (float) ( $country_row['payout'] ?? 0 ) );
        $country_epc         = $country_clicks > 0 ? round( $country_payout / $country_clicks, 6 ) : 0.0;

        if ( empty( $optimization['country_decay_enabled'] ) ) {
            return array(
                'clicks'      => $country_clicks,
                'conversions' => $country_conversions,
                'payout'      => $country_payout,
                'epc'         => $country_epc,
                'country_used' => $country,
                'country_clicks' => $country_clicks,
                'fallback_to_global' => 0,
            );
        }

        $minimum_country_clicks = max( 1, (int) $optimization['decay_min_country_clicks'] );
        if ( $country_clicks < $minimum_country_clicks && ! empty( $optimization['fallback_to_global_when_low_sample'] ) ) {
            return $global;
        }

        // [TMW-CR-OPT] Weighted country decay formula:
        // effective_metric = (country_metric * adjusted_country_weight) + (global_metric * adjusted_global_weight)
        // For low country samples, adjusted_country_weight scales down proportionally to sample depth.
        $sample_ratio             = min( 1, $country_clicks / $minimum_country_clicks );
        $country_weight_adjusted  = $optimization['country_weight'] * $sample_ratio;
        $global_weight_adjusted   = $optimization['global_weight'] + ( $optimization['country_weight'] - $country_weight_adjusted );
        $weight_sum               = $country_weight_adjusted + $global_weight_adjusted;
        if ( $weight_sum <= 0 ) {
            $country_weight_adjusted = 0.7;
            $global_weight_adjusted  = 0.3;
            $weight_sum              = 1;
        }
        $country_weight_adjusted = $country_weight_adjusted / $weight_sum;
        $global_weight_adjusted  = $global_weight_adjusted / $weight_sum;

        return array(
            'clicks'            => (int) round( ( $country_clicks * $country_weight_adjusted ) + ( $global['clicks'] * $global_weight_adjusted ) ),
            'conversions'       => round( ( $country_conversions * $country_weight_adjusted ) + ( $global['conversions'] * $global_weight_adjusted ), 4 ),
            'payout'            => round( ( $country_payout * $country_weight_adjusted ) + ( $global['payout'] * $global_weight_adjusted ), 4 ),
            'epc'               => round( ( $country_epc * $country_weight_adjusted ) + ( $global['epc'] * $global_weight_adjusted ), 6 ),
            'country_used'      => $country,
            'country_clicks'    => $country_clicks,
            'fallback_to_global'=> $country_clicks < $minimum_country_clicks ? 1 : 0,
        );
    }

    /**
     * @param string             $mode Rotation mode.
     * @param array<string,mixed> $metrics Metrics.
     *
     * @return float
     */
    protected function build_rotation_score( $mode, $metrics, $optimization = array() ) {
        $clicks      = (int) ( $metrics['clicks'] ?? 0 );
        $conversions = (float) ( $metrics['conversions'] ?? 0 );
        $payout      = (float) ( $metrics['payout'] ?? 0 );
        $epc         = (float) ( $metrics['epc'] ?? 0 );
        $optimization = $this->get_optimization_settings( $optimization );
        $confidence   = $this->calculate_confidence_multiplier( $metrics, $optimization );

        if ( 'payout_desc' === $mode ) {
            return $payout * $confidence;
        }
        if ( 'conversions_desc' === $mode ) {
            return $conversions * $confidence;
        }
        if ( 'epc_desc' === $mode || 'country_epc_desc' === $mode ) {
            return $epc * $confidence;
        }
        if ( 'safe_hybrid_score' === $mode ) {
            return ( ( $epc * 1000 ) + ( $conversions * 100 ) + $payout ) * $confidence;
        }

        // [TMW-CR-OPT] hybrid_score: conversions first, payout second, epc third.
        return ( $conversions * 100000 ) + ( $payout * 100 ) + $epc + ( $clicks / 1000000 );
    }

    /**
     * @param array<string,mixed> $settings Settings.
     *
     * @return array<string,mixed>
     */
    protected function get_optimization_settings( $settings ) {
        $defaults = array(
            'optimization_enabled' => 1,
            'minimum_clicks_threshold' => 10,
            'minimum_conversions_threshold' => 1,
            'minimum_payout_threshold' => 0.0,
            'exclude_zero_click_offers' => 0,
            'exclude_zero_conversion_offers' => 0,
            'country_decay_enabled' => 1,
            'country_weight' => 0.7,
            'global_weight' => 0.3,
            'decay_min_country_clicks' => 10,
            'fallback_to_global_when_low_sample' => 1,
        );
        $settings = wp_parse_args( (array) $settings, $defaults );
        $settings['optimization_enabled'] = ! empty( $settings['optimization_enabled'] ) ? 1 : 0;
        $settings['minimum_clicks_threshold'] = max( 0, (int) $settings['minimum_clicks_threshold'] );
        $settings['minimum_conversions_threshold'] = max( 0, (float) $settings['minimum_conversions_threshold'] );
        $settings['minimum_payout_threshold'] = max( 0, (float) $settings['minimum_payout_threshold'] );
        $settings['exclude_zero_click_offers'] = ! empty( $settings['exclude_zero_click_offers'] ) ? 1 : 0;
        $settings['exclude_zero_conversion_offers'] = ! empty( $settings['exclude_zero_conversion_offers'] ) ? 1 : 0;
        $settings['country_decay_enabled'] = ! empty( $settings['country_decay_enabled'] ) ? 1 : 0;
        $settings['country_weight'] = max( 0, min( 1, (float) $settings['country_weight'] ) );
        $settings['global_weight'] = max( 0, min( 1, (float) $settings['global_weight'] ) );
        $settings['decay_min_country_clicks'] = max( 1, (int) $settings['decay_min_country_clicks'] );
        $settings['fallback_to_global_when_low_sample'] = ! empty( $settings['fallback_to_global_when_low_sample'] ) ? 1 : 0;

        return $settings;
    }

    /**
     * @param array<string,mixed> $metrics Metrics.
     * @param array<string,mixed> $optimization Optimization settings.
     *
     * @return float
     */
    protected function calculate_confidence_multiplier( $metrics, $optimization ) {
        $clicks      = (int) ( $metrics['clicks'] ?? 0 );
        $conversions = (float) ( $metrics['conversions'] ?? 0 );
        $payout      = (float) ( $metrics['payout'] ?? 0 );

        $click_ratio = $optimization['minimum_clicks_threshold'] > 0 ? min( 1, $clicks / $optimization['minimum_clicks_threshold'] ) : 1;
        $conv_ratio  = $optimization['minimum_conversions_threshold'] > 0 ? min( 1, $conversions / $optimization['minimum_conversions_threshold'] ) : 1;
        $payout_ratio= $optimization['minimum_payout_threshold'] > 0 ? min( 1, $payout / $optimization['minimum_payout_threshold'] ) : 1;

        return max( 0.05, min( $click_ratio, $conv_ratio, $payout_ratio ) );
    }

    /**
     * [TMW-CR-DASH] Explainability rows for optimization ranking.
     *
     * @param string               $country Country.
     * @param array<string,mixed>  $settings Settings.
     * @param int                  $limit Limit.
     *
     * @return array<int,array<string,mixed>>
     */
    public function get_optimization_explain_rows( $country, $settings, $limit = 10 ) {
        $selected_ids  = $this->get_selected_offer_ids( $settings );
        $synced_offers = $this->get_synced_offers();
        $stats         = $this->get_offer_stats();
        $optimization  = $this->get_optimization_settings( $settings );
        $mode          = isset( $settings['rotation_mode'] ) ? sanitize_key( (string) $settings['rotation_mode'] ) : 'manual';
        $rows          = array();

        foreach ( $selected_ids as $offer_id ) {
            if ( ! isset( $synced_offers[ $offer_id ] ) ) {
                continue;
            }

            $metrics     = $this->get_offer_metrics_for_country( $offer_id, $country, $stats, $optimization );
            $confidence  = $this->calculate_confidence_multiplier( $metrics, $optimization );
            $rows[] = array(
                'offer_id' => $offer_id,
                'offer_name' => (string) ( $synced_offers[ $offer_id ]['name'] ?? $offer_id ),
                'clicks' => (int) $metrics['clicks'],
                'conversions' => (float) $metrics['conversions'],
                'payout' => (float) $metrics['payout'],
                'epc' => (float) $metrics['epc'],
                'country_sample_used' => (int) ( $metrics['country_clicks'] ?? 0 ),
                'used_global_fallback' => ! empty( $metrics['fallback_to_global'] ) ? 1 : 0,
                'low_sample_penalty' => $confidence < 1 ? 1 : 0,
                'final_score' => $this->build_rotation_score( $mode, $metrics, $optimization ),
            );
        }

        usort(
            $rows,
            static function ( $left, $right ) {
                if ( $left['final_score'] !== $right['final_score'] ) {
                    return $right['final_score'] <=> $left['final_score'];
                }
                return strcasecmp( (string) $left['offer_name'], (string) $right['offer_name'] );
            }
        );

        return array_slice( $rows, 0, max( 1, (int) $limit ) );
    }

    /**
     * @param array<string,mixed> $override Raw override.
     *
     * @return array<string,mixed>
     */
    protected function sanitize_offer_override( $override ) {
        return array(
            'enabled'           => ! isset( $override['enabled'] ) || ! empty( $override['enabled'] ) ? 1 : 0,
            'final_url_override' => ! empty( $override['final_url_override'] ) ? esc_url_raw( (string) $override['final_url_override'] ) : '',
            'image_url_override' => ! empty( $override['image_url_override'] ) ? esc_url_raw( (string) $override['image_url_override'] ) : '',
            'allowed_countries' => $this->sanitize_country_names( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() ),
            'blocked_countries' => $this->sanitize_country_codes( isset( $override['blocked_countries'] ) ? $override['blocked_countries'] : array() ),
            'custom_cta_text'   => ! empty( $override['custom_cta_text'] ) ? sanitize_text_field( (string) $override['custom_cta_text'] ) : '',
            'label_override'    => ! empty( $override['label_override'] ) ? sanitize_text_field( (string) $override['label_override'] ) : '',
            'notes'             => ! empty( $override['notes'] ) ? sanitize_textarea_field( (string) $override['notes'] ) : '',
            'dashboard_tags'    => $this->sanitize_list_values( isset( $override['dashboard_tags'] ) ? $override['dashboard_tags'] : array() ),
            'dashboard_vertical' => ! empty( $override['dashboard_vertical'] ) ? sanitize_text_field( (string) $override['dashboard_vertical'] ) : '',
            'dashboard_performs_in' => $this->sanitize_country_codes( isset( $override['dashboard_performs_in'] ) ? $override['dashboard_performs_in'] : array() ),
            'dashboard_optimized_for' => $this->sanitize_list_values( isset( $override['dashboard_optimized_for'] ) ? $override['dashboard_optimized_for'] : array() ),
            'dashboard_accepted_countries' => $this->sanitize_country_codes( isset( $override['dashboard_accepted_countries'] ) ? $override['dashboard_accepted_countries'] : array() ),
            'dashboard_niche'   => $this->sanitize_list_values( isset( $override['dashboard_niche'] ) ? $override['dashboard_niche'] : array() ),
            'dashboard_promotion_method' => $this->sanitize_list_values( isset( $override['dashboard_promotion_method'] ) ? $override['dashboard_promotion_method'] : array() ),
        );
    }

    /**
     * @param string              $offer_id Offer ID.
     * @param array<string,mixed> $offer Offer row.
     *
     * @return array<string,array<int,string>>
     */
    protected function get_offer_dashboard_metadata( $offer_id, $offer ) {
        $override = $this->get_offer_override( $offer_id );
        $local_layer = $this->get_dashboard_metadata_layer();
        $local_meta  = isset( $local_layer[ $offer_id ] ) ? (array) $local_layer[ $offer_id ] : array();

        $status = sanitize_key( (string) ( $offer['status'] ?? '' ) );
        $vertical = sanitize_text_field( (string) ( $offer['vertical'] ?? '' ) );
        if ( '' === $vertical && ! empty( $override['dashboard_vertical'] ) ) {
            $vertical = sanitize_text_field( (string) $override['dashboard_vertical'] );
        }

        $meta = array(
            'tag' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['tags'] ) ? $offer['tags'] : array() ),
                $this->sanitize_list_values( isset( $local_meta['tag'] ) ? $local_meta['tag'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_tags'] ) ? $override['dashboard_tags'] : array() )
            ),
            'vertical' => $this->merge_preferred_values(
                '' !== $vertical ? array( $vertical ) : array(),
                $this->sanitize_list_values( isset( $local_meta['vertical'] ) ? $local_meta['vertical'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_vertical'] ) ? $override['dashboard_vertical'] : array() )
            ),
            'payout_type' => $this->sanitize_list_values( isset( $offer['payout_type'] ) ? $offer['payout_type'] : '' ),
            'performs_in' => $this->merge_preferred_values(
                $this->sanitize_country_codes( isset( $offer['performs_in'] ) ? $offer['performs_in'] : array() ),
                $this->sanitize_country_codes( isset( $local_meta['performs_in'] ) ? $local_meta['performs_in'] : array() ),
                $this->sanitize_country_codes( isset( $override['dashboard_performs_in'] ) ? $override['dashboard_performs_in'] : array() )
            ),
            'optimized_for' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['optimized_for'] ) ? $offer['optimized_for'] : array() ),
                $this->sanitize_list_values( isset( $local_meta['optimized_for'] ) ? $local_meta['optimized_for'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_optimized_for'] ) ? $override['dashboard_optimized_for'] : array() )
            ),
            'accepted_country' => $this->merge_preferred_values(
                $this->sanitize_country_codes( isset( $offer['accepted_countries'] ) ? $offer['accepted_countries'] : array() ),
                $this->sanitize_country_codes( isset( $local_meta['accepted_country'] ) ? $local_meta['accepted_country'] : array() ),
                $this->sanitize_country_codes( isset( $override['dashboard_accepted_countries'] ) ? $override['dashboard_accepted_countries'] : array() )
            ),
            'niche' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['niche'] ) ? $offer['niche'] : array() ),
                $this->sanitize_list_values( isset( $local_meta['niche'] ) ? $local_meta['niche'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_niche'] ) ? $override['dashboard_niche'] : array() )
            ),
            'status' => '' !== $status ? array( $status ) : array(),
            'promotion_method' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['promotion_method'] ) ? $offer['promotion_method'] : array() ),
                $this->sanitize_list_values( isset( $local_meta['promotion_method'] ) ? $local_meta['promotion_method'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_promotion_method'] ) ? $override['dashboard_promotion_method'] : array() )
            ),
        );

        foreach ( $meta as $family => $values ) {
            $normalized = array();
            foreach ( (array) $values as $value ) {
                $value = $this->normalize_filter_family_value( $family, $value );
                if ( '' === $value ) {
                    continue;
                }
                $normalized[] = $value;
            }
            $meta[ $family ] = array_values( array_unique( $normalized ) );
        }

        return $meta;
    }

    /**
     * @param array<int,string>      $needles Filter values.
     * @param array<int,string>      $values Values.
     * @param bool                   $uppercase Normalize to uppercase.
     *
     * @return bool
     */
    protected function values_intersect_filter_set( $needles, $values, $uppercase = false ) {
        foreach ( $needles as $needle ) {
            $needle = $uppercase ? strtoupper( (string) $needle ) : strtolower( (string) $needle );
            if ( '' === $needle ) {
                continue;
            }
            foreach ( $values as $value ) {
                $hay = $uppercase ? strtoupper( (string) $value ) : strtolower( (string) $value );
                if ( $needle === $hay ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function is_offer_blocked_for_banner( $offer, $settings = array() ) {
        unset( $settings );
        $single_word_keywords = array( 'gay', 'trans', 'shemale', 'xlovegay', 'mennation', 'gaybloom', 'pridepair', 'tsdates', 'transdate' );
        $phrase_keywords      = array( 'male gay', 'gay cam', 'gay dating', 'xlove gay' );
        $parts                = array( strtolower( sanitize_text_field( (string) ( $offer['name'] ?? '' ) ) ) );
        foreach ( array( 'category', 'categories', 'tag', 'tags' ) as $field ) {
            if ( ! isset( $offer[ $field ] ) ) {
                continue;
            }
            if ( is_array( $offer[ $field ] ) ) {
                $parts[] = strtolower( implode( ' ', array_map( 'strval', $offer[ $field ] ) ) );
            } else {
                $parts[] = strtolower( (string) $offer[ $field ] );
            }
        }

        $normalized_haystack = strtolower( implode( ' ', $parts ) );
        $normalized_haystack = preg_replace( '/[^a-z0-9]+/i', ' ', $normalized_haystack );
        $normalized_haystack = is_string( $normalized_haystack ) ? trim( preg_replace( '/\s+/', ' ', $normalized_haystack ) ) : '';
        if ( '' === $normalized_haystack ) {
            return false;
        }

        $tokens = explode( ' ', $normalized_haystack );

        foreach ( $single_word_keywords as $keyword ) {
            if ( in_array( $keyword, $tokens, true ) ) {
                return true;
            }
        }

        foreach ( $phrase_keywords as $phrase ) {
            $pattern = '/\b' . str_replace( ' ', '\s+', preg_quote( $phrase, '/' ) ) . '\b/i';
            if ( 1 === preg_match( $pattern, $normalized_haystack ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $raw Raw query value.
     * @param bool  $uppercase Uppercase normalization.
     *
     * @return array<int,string>
     */
    protected function normalize_query_values( $raw, $family = '' ) {
        $values = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
        $clean  = array();

        foreach ( (array) $values as $value ) {
            $value = $this->normalize_filter_family_value( $family, $value );
            if ( '' === $value ) {
                continue;
            }
            $clean[] = $value;
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * @param array<string,array<string,mixed>> $offers Synced offers.
     *
     * @return void
     */
    protected function sync_dashboard_metadata_layer( $offers ) {
        $existing = $this->get_dashboard_metadata_layer();
        $payload  = array();

        foreach ( $offers as $offer_id => $offer ) {
            $offer_id = sanitize_text_field( (string) $offer_id );
            if ( '' === $offer_id || ! is_array( $offer ) ) {
                continue;
            }
            $previous = isset( $existing[ $offer_id ] ) ? $existing[ $offer_id ] : array();
            $payload[ $offer_id ] = array(
                'tag' => $this->merge_preferred_values(
                    $this->sanitize_list_values( isset( $offer['tags'] ) ? $offer['tags'] : array() ),
                    $this->sanitize_list_values( isset( $previous['tag'] ) ? $previous['tag'] : array() )
                ),
                'vertical' => $this->merge_preferred_values(
                    $this->sanitize_list_values( isset( $offer['vertical'] ) ? $offer['vertical'] : array() ),
                    $this->sanitize_list_values( isset( $previous['vertical'] ) ? $previous['vertical'] : array() )
                ),
                'performs_in' => $this->merge_preferred_values(
                    $this->sanitize_country_codes( isset( $offer['performs_in'] ) ? $offer['performs_in'] : array() ),
                    $this->sanitize_country_codes( isset( $previous['performs_in'] ) ? $previous['performs_in'] : array() )
                ),
                'optimized_for' => $this->merge_preferred_values(
                    $this->sanitize_list_values( isset( $offer['optimized_for'] ) ? $offer['optimized_for'] : array() ),
                    $this->sanitize_list_values( isset( $previous['optimized_for'] ) ? $previous['optimized_for'] : array() )
                ),
                'accepted_country' => $this->merge_preferred_values(
                    $this->sanitize_country_codes( isset( $offer['accepted_countries'] ) ? $offer['accepted_countries'] : array() ),
                    $this->sanitize_country_codes( isset( $previous['accepted_country'] ) ? $previous['accepted_country'] : array() )
                ),
                'niche' => $this->merge_preferred_values(
                    $this->sanitize_list_values( isset( $offer['niche'] ) ? $offer['niche'] : array() ),
                    $this->sanitize_list_values( isset( $previous['niche'] ) ? $previous['niche'] : array() )
                ),
                'promotion_method' => $this->merge_preferred_values(
                    $this->sanitize_list_values( isset( $offer['promotion_method'] ) ? $offer['promotion_method'] : array() ),
                    $this->sanitize_list_values( isset( $previous['promotion_method'] ) ? $previous['promotion_method'] : array() )
                ),
            );
        }

        update_option( $this->dashboard_meta_option_key, $payload, false );
    }

    /**
     * @param string $family Filter family.
     * @param mixed  $value Raw value.
     *
     * @return string
     */
    protected function normalize_filter_family_value( $family, $value ) {
        $family = sanitize_key( (string) $family );
        $value  = sanitize_text_field( trim( (string) $value ) );
        if ( '' === $value ) {
            return '';
        }

        if ( in_array( $family, array( 'performs_in', 'accepted_country' ), true ) ) {
            return $this->normalize_country_filter_value( $value );
        }

        $normalized_key = str_replace( '-', '_', sanitize_key( $value ) );

        if ( 'payout_type' === $family ) {
            $aliases = array(
                'cpa_percentage' => 'revshare',
                'revshare' => 'revshare',
                'revenue_share' => 'revshare',
                'cpa' => 'multi_cpa',
                'cpa_flat' => 'revshare_lifetime',
                'revshare_lifetime' => 'revshare_lifetime',
                'pps' => 'pps',
                'cpa_both' => 'multi_cpa',
                'multi_cpa' => 'multi_cpa',
                'multi-cpa' => 'multi_cpa',
                'multicpa' => 'multi_cpa',
                'hybrid' => 'multi_cpa',
                'soi' => 'soi',
                'doi' => 'doi',
                'cpi' => 'cpi',
                'cpm' => 'cpm',
                'cpc' => 'cpc',
                'ppc' => 'cpc',
            );

            if ( isset( $aliases[ $normalized_key ] ) ) {
                return $aliases[ $normalized_key ];
            }

            error_log(
                sprintf(
                    '[TMW-BANNER-PAYOUT-NORM] unknown_payout_type raw="%s" normalized="%s"',
                    sanitize_text_field( (string) $value ),
                    $normalized_key
                )
            );
        }

        return strtolower( $value );
    }

    protected function normalize_country_filter_value( $value ) {
        $value = strtolower( trim( (string) $value ) );
        $map = array(
            'be' => 'BE', 'belgium' => 'BE',
            'us' => 'US', 'usa' => 'US', 'united states' => 'US', 'united_states' => 'US',
            'united kingdom' => 'GB', 'uk' => 'GB', 'great britain' => 'GB',
            'germany' => 'DE', 'france' => 'FR', 'canada' => 'CA', 'australia' => 'AU',
            'netherlands' => 'NL', 'spain' => 'ES', 'italy' => 'IT', 'austria' => 'AT',
            'switzerland' => 'CH', 'sweden' => 'SE', 'norway' => 'NO', 'denmark' => 'DK',
            'finland' => 'FI', 'ireland' => 'IE', 'portugal' => 'PT', 'poland' => 'PL',
            'brazil' => 'BR', 'mexico' => 'MX',
        );
        if ( isset( $map[ $value ] ) ) {
            return $map[ $value ];
        }
        return strtoupper( sanitize_text_field( (string) $value ) );
    }

    /**
     * @param array<int,string> ...$sources Sources in preference order.
     *
     * @return array<int,string>
     */
    protected function merge_preferred_values( ...$sources ) {
        foreach ( $sources as $source ) {
            $values = array_values( array_unique( array_filter( array_map( 'strval', (array) $source ) ) ) );
            if ( ! empty( $values ) ) {
                return $values;
            }
        }

        return array();
    }

    /**
     * @param array<int|string,mixed>|string $raw Raw list payload.
     *
     * @return array<int,string>
     */
    protected function sanitize_list_values( $raw ) {
        $values = is_array( $raw ) ? $raw : preg_split( '/[,|]/', (string) $raw );
        $clean  = array();

        foreach ( (array) $values as $value ) {
            $value = sanitize_text_field( trim( (string) $value ) );
            if ( '' === $value ) {
                continue;
            }
            $clean[] = $value;
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * @param array<int|string,mixed>|string $raw Raw country payload.
     *
     * @return array<int,string>
     */
    protected function sanitize_country_codes( $raw ) {
        $codes = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
        $clean = array();

        foreach ( $codes as $code ) {
            $code = strtoupper( sanitize_text_field( trim( (string) $code ) ) );
            if ( 2 !== strlen( $code ) || ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
                continue;
            }

            $clean[] = $code;
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * @param string $label Placeholder label.
     *
     * @return string
     */
    protected function build_placeholder_image( $label ) {
        $label = trim( preg_replace( '/\s+/', ' ', (string) $label ) );
        $label = '' !== $label ? $label : 'Offer';
        $abbr  = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $label ), 0, 3 ) );
        $abbr  = '' !== $abbr ? $abbr : 'AD';

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" rx="24" fill="#29164a"/><text x="100" y="112" text-anchor="middle" font-size="56" font-family="Arial, sans-serif" fill="#ffffff">%s</text></svg>',
            esc_html( $abbr )
        );

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
}
