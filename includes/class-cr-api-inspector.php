<?php
/**
 * CrakRevenue API audit/discovery inspector.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_CR_API_Inspector {
    const LOG_TAG = '[TMW-CR-AUDIT]';

    protected $client;

    public function __construct( $client ) {
        $this->client = $client;
    }

    public static function is_enabled() {
        $debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'TMW_CR_API_AUDIT' ) && TMW_CR_API_AUDIT );
        return is_admin() && current_user_can( 'manage_options' ) && $debug_enabled;
    }

    public function run_full_audit( $limit = 3 ) {
        $limit = max( 1, (int) $limit );
        $offers = $this->inspect_offers( $limit );
        $targeting = $this->inspect_targeting_field_groups( $limit );
        $sample_offer_id = isset( $offers['sample_offer_id'] ) ? (string) $offers['sample_offer_id'] : '';
        $tracking = '' !== $sample_offer_id ? $this->inspect_tracking_url( $sample_offer_id ) : array( 'skipped' => true, 'reason' => 'no_offer_id' );

        $report = array(
            'offers' => $offers,
            'targeting' => $targeting,
            'tracking_url' => $tracking,
        );

        $this->log_human_readable_summary( $report );
        error_log( self::LOG_TAG . ' Full audit summary: ' . wp_json_encode( $this->summarize_keys( $report, 3 ) ) );
        return $report;
    }

    public function inspect_offers( $limit = 3 ) {
        $response = $this->client->find_all_offers(
            array(
                'fields' => array( 'id', 'name', 'description', 'preview_url', 'status', 'payout_type', 'default_payout', 'percent_payout', 'require_approval', 'use_target_rules', 'terms_and_conditions' ),
                'limit' => max( 1, (int) $limit ),
                'page' => 1,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $this->scrub_string( $response->get_error_message() ) );
        }

        $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $response );
        $sample_offer_id = '';
        if ( ! empty( $rows[0]['id'] ) ) {
            $sample_offer_id = (string) $rows[0]['id'];
        }

        $row_count = is_array( $rows ) ? count( $rows ) : 0;
        $report = array(
            'success' => true,
            'top_level_keys' => array_keys( $response ),
            'row_count' => $row_count,
            'row_keys' => ! empty( $rows[0] ) && is_array( $rows[0] ) ? array_keys( $rows[0] ) : array(),
            'sample_offer_id' => $this->is_safe_offer_id( $sample_offer_id ) ? $sample_offer_id : '',
            'iso_candidates' => $this->extract_iso_country_candidates( $response ),
        );
        if ( $row_count < 1 ) {
            $report['reason'] = 'empty_rows';
        }
        return $report;
    }

    public function inspect_targeting_field_groups( $limit = 3 ) {
        $fields = array( 'targeting', 'target_rules', 'targeting_rules', 'rules', 'offer_targeting', 'geo_targeting', 'countries', 'allowed_countries' );
        $result = array();
        foreach ( $fields as $field ) {
            $resp = $this->client->audit_request( 'Affiliate_Offer', 'findAll', array( 'fields' => array( 'id', 'name', $field ), 'limit' => max( 1, (int) $limit ), 'page' => 1 ) );
            if ( is_wp_error( $resp ) ) {
                $result[ $field ] = array( 'success' => false, 'error' => $this->scrub_string( $resp->get_error_message() ) );
                error_log( self::LOG_TAG . ' targeting probe failed field=' . $field . ' error=' . $this->scrub_string( $resp->get_error_message() ) );
                continue;
            }
            $rows = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $resp );
            $nested = array();
            if ( ! empty( $rows[0][ $field ] ) ) {
                $nested = $this->summarize_keys( $rows[0][ $field ], 3 );
            }
            $result[ $field ] = array(
                'success' => true,
                'top_level_keys' => array_keys( $resp ),
                'row_count' => is_array( $rows ) ? count( $rows ) : 0,
                'row_keys' => ! empty( $rows[0] ) && is_array( $rows[0] ) ? array_keys( $rows[0] ) : array(),
                'nested_keys' => $nested,
                'iso_candidates' => $this->extract_iso_country_candidates( $resp ),
            );
        }
        return $result;
    }

    public function inspect_tracking_url( $offer_id ) {
        $methods = array( 'getTrackingUrl', 'generateTrackingLink', 'findOneTrackingLink', 'getTrackingLink' );
        $out = array();
        foreach ( $methods as $method ) {
            $resp = $this->client->audit_request( 'Affiliate_Offer', $method, array( 'offer_id' => (string) $offer_id, 'id' => (string) $offer_id ) );
            $out[ $method ] = is_wp_error( $resp )
                ? array( 'success' => false, 'error' => $this->scrub_string( $resp->get_error_message() ) )
                : array( 'success' => true, 'keys' => $this->summarize_keys( $resp, 3 ) );
        }
        return $out;
    }

    public function extract_iso_country_candidates( $payload ) {
        $codes = array();
        $walker = function( $value ) use ( &$walker, &$codes ) {
            if ( is_array( $value ) ) {
                foreach ( $value as $child ) { $walker( $child ); }
                return;
            }
            if ( is_string( $value ) && preg_match( '/^[A-Z]{2}$/', strtoupper( trim( $value ) ) ) ) {
                $codes[] = strtoupper( trim( $value ) );
            }
        };
        $walker( $payload );
        $codes = array_values( array_unique( $codes ) );
        sort( $codes );
        return $codes;
    }

    public function summarize_keys( $payload, $max_depth = 4, $depth = 0 ) {
        if ( ! is_array( $payload ) ) {
            return $this->sanitize_audit_scalar( $payload );
        }
        if ( $depth >= $max_depth ) {
            return array();
        }
        $summary = array();
        foreach ( $payload as $key => $value ) {
            $label = is_int( $key ) ? '[index]' : (string) $key;
            if ( is_int( $key ) ) {
                $summary[] = $this->summarize_keys( $value, $max_depth, $depth + 1 );
                continue;
            }
            $summary[ $label ] = $this->summarize_keys( $value, $max_depth, $depth + 1 );
        }
        return $summary;
    }

    public function scrub_string( $value ) {
        return preg_replace( '/(api_key|apikey|token|access_token|key)=([^&\s]+)/i', '$1=[redacted]', (string) $value );
    }

    protected function sanitize_audit_scalar( $value ) {
        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
            return $value;
        }
        if ( null === $value ) {
            return null;
        }
        $string = trim( (string) $value );
        if ( '' === $string ) {
            return '';
        }
        if ( preg_match( '#^https?://#i', $string ) ) {
            return '[redacted_url]';
        }
        if ( preg_match( '/(bearer\s+[a-z0-9\-_\.]+|authorization:|cookie:|set-cookie:|api[_-]?key|access[_-]?token|token|password|secret)/i', $string ) ) {
            return '[redacted_secret]';
        }
        if ( strlen( $string ) >= 32 && preg_match( '/^[a-f0-9]{32,}$/i', $string ) ) {
            return '[redacted_secret]';
        }
        return $this->scrub_string( $string );
    }

    protected function is_safe_offer_id( $offer_id ) {
        $offer_id = trim( (string) $offer_id );
        return '' !== $offer_id && strlen( $offer_id ) <= 40 && (bool) preg_match( '/^[A-Za-z0-9_-]+$/', $offer_id );
    }

    protected function log_human_readable_summary( $report ) {
        if ( ! is_array( $report ) ) {
            return;
        }
        $offers = isset( $report['offers'] ) && is_array( $report['offers'] ) ? $report['offers'] : array();
        $parts = array(
            'offers success=' . ( ! empty( $offers['success'] ) ? 'YES' : 'NO' ),
            'top_level_keys=' . implode( ',', array_slice( (array) ( $offers['top_level_keys'] ?? array() ), 0, 8 ) ),
            'row_keys=' . implode( ',', array_slice( (array) ( $offers['row_keys'] ?? array() ), 0, 8 ) ),
            'row_count=' . (int) ( $offers['row_count'] ?? 0 ),
            'iso_candidates=' . implode( ',', array_slice( (array) ( $offers['iso_candidates'] ?? array() ), 0, 8 ) ),
        );
        if ( ! empty( $offers['reason'] ) ) {
            $parts[] = 'reason=' . sanitize_key( (string) $offers['reason'] );
        }
        $tracking = isset( $report['tracking_url'] ) && is_array( $report['tracking_url'] ) ? $report['tracking_url'] : array();
        if ( array_key_exists( 'skipped', $tracking ) ) {
            $parts[] = 'tracking_url=' . ( ! empty( $tracking['skipped'] ) ? 'skipped' : 'ready' );
            $parts[] = 'reason=' . sanitize_key( (string) ( $tracking['reason'] ?? 'unknown' ) );
        }
        error_log( self::LOG_TAG . ' ' . implode( ' ', array_filter( $parts ) ) );
    }
}
