<?php
/**
 * [TMW-CR-STATS] Stats sync workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Stats_Sync_Service {
    const CRON_HOOK = 'tmw_cr_slot_banner_scheduled_stats_sync';
    const MAX_UNWRAP_DEPTH = 5;
    /**
     * @return array<string,mixed>
     */
    public static function get_default_query_args() {
        return array(
            'fields' => array(
                'Stat.clicks',
                'Stat.conversions',
                'Stat.payout',
                'Stat.offer_id',
                'Offer.name',
                'Country.name',
                'Stat.payout_type',
            ),
            'groups' => array(
                'Stat.offer_id',
                'Country.name',
            ),
            'sort'   => array(
                'Stat.payout' => 'desc',
            ),
            'filters' => array(),
            'limit'  => 250,
            'page'   => 1,
        );
    }

    /**
     * [TMW-CR-CRON] Ensures scheduled stats sync event exists once.
     *
     * @param string $frequency Cron frequency.
     *
     * @return bool
     */
    public static function ensure_cron_schedule( $frequency ) {
        $frequency = sanitize_key( (string) $frequency );
        if ( ! in_array( $frequency, array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
            $frequency = 'daily';
        }

        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            return true;
        }

        return (bool) wp_schedule_event( time() + 60, $frequency, self::CRON_HOOK );
    }

    /**
     * [TMW-CR-CRON] Removes scheduled stats sync event.
     *
     * @return bool
     */
    public static function clear_cron_schedule() {
        return (bool) wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * @param string $preset Preset key.
     *
     * @return array<string,string>
     */
    public static function get_date_range_from_preset( $preset ) {
        $preset = sanitize_key( (string) $preset );
        $days   = 30;

        if ( '7d' === $preset ) {
            $days = 7;
        } elseif ( '90d' === $preset ) {
            $days = 90;
        }

        return array(
            'start' => gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) ),
            'end'   => gmdate( 'Y-m-d' ),
        );
    }

    /**
     * Syncs stats and stores aggregated rows keyed by offer_id + country_name.
     *
     * @param TMW_CR_Slot_CR_API_Client    $client API client.
     * @param TMW_CR_Slot_Offer_Repository $repository Repository.
     * @param array<string,mixed>          $args Sync args.
     *
     * @return array<string,mixed>|WP_Error
     */
    public static function sync( $client, $repository, $args = array() ) {
        $defaults = array(
            'date_start' => '',
            'date_end'   => '',
            'preset'     => '30d',
        );
        $args     = wp_parse_args( (array) $args, $defaults );

        if ( '' === (string) $args['date_start'] || '' === (string) $args['date_end'] ) {
            $range              = self::get_date_range_from_preset( (string) $args['preset'] );
            $args['date_start'] = $range['start'];
            $args['date_end']   = $range['end'];
        }

        $page         = 1;
        $query_args   = self::get_default_query_args();
        $limit        = (int) $query_args['limit'];
        $synced_at    = gmdate( 'c' );
        $raw_rows     = 0;
        $stats_rows   = array();
        $imported     = 0;
        $response_shape = 'unknown';
        $sample_row_keys = '';
        $soft_failure = '';
        $preserved_previous = false;

        do {
            $request = $query_args;
            $request['page']       = $page;
            $request['data_start'] = (string) $args['date_start'];
            $request['data_end']   = (string) $args['date_end'];

            $response = $client->get_offer_stats( $request );
            if ( is_wp_error( $response ) ) {
                $repository->save_stats_meta(
                    array(
                        'last_stats_synced_at'    => '',
                        'last_stats_error'        => $response->get_error_message(),
                        'last_stats_raw_rows'     => $raw_rows,
                        'last_stats_imported_rows'=> $imported,
                        'last_stats_date_start'   => (string) $args['date_start'],
                        'last_stats_date_end'     => (string) $args['date_end'],
                    )
                );

                return $response;
            }

            $rows      = self::extract_stats_rows( $response );
            $response_shape = self::detect_stats_response_shape( $response );
            if ( '' === $sample_row_keys && ! empty( $rows[0] ) && is_array( $rows[0] ) ) {
                $sample_row_keys = self::summarize_row_keys( $rows[0] );
            }
            $raw_rows += count( $rows );

            foreach ( $rows as $row ) {
                $normalized = self::normalize_stats_row( $row );
                if ( '' === $normalized['offer_id'] ) {
                    continue;
                }

                $key = $normalized['offer_id'] . '|' . strtoupper( $normalized['country_name'] );

                if ( ! isset( $stats_rows[ $key ] ) ) {
                    $stats_rows[ $key ] = $normalized;
                } else {
                    $stats_rows[ $key ]['clicks']      += $normalized['clicks'];
                    $stats_rows[ $key ]['conversions'] += $normalized['conversions'];
                    $stats_rows[ $key ]['payout']      += $normalized['payout'];
                    if ( '' === $stats_rows[ $key ]['offer_name'] && '' !== $normalized['offer_name'] ) {
                        $stats_rows[ $key ]['offer_name'] = $normalized['offer_name'];
                    }
                }
            }

            ++$page;
        } while ( count( $rows ) >= $limit );

        foreach ( $stats_rows as $key => $row ) {
            $stats_rows[ $key ]['epc']             = self::calculate_epc( $row['payout'], $row['clicks'] );
            $stats_rows[ $key ]['conversion_rate'] = self::calculate_conversion_rate( $row['conversions'], $row['clicks'] );
            $stats_rows[ $key ]['date_start']      = (string) $args['date_start'];
            $stats_rows[ $key ]['date_end']        = (string) $args['date_end'];
            $stats_rows[ $key ]['synced_at']       = $synced_at;
            ++$imported;
        }

        if ( $raw_rows > 0 && 0 === $imported ) {
            $soft_failure = 'parser_mismatch';
            $preserved_previous = true;
            $repository->save_stats_meta(
                array(
                    'last_stats_synced_at'     => $synced_at,
                    'last_stats_error'         => __( '[TMW-CR-STATS] Stats sync preserved existing stats: parser mismatch detected.', 'tmw-cr-slot-sidebar-banner' ),
                    'last_stats_raw_rows'      => $raw_rows,
                    'last_stats_imported_rows' => 0,
                    'last_stats_date_start'    => (string) $args['date_start'],
                    'last_stats_date_end'      => (string) $args['date_end'],
                    'last_stats_response_shape' => $response_shape,
                    'last_stats_sample_row_keys' => $sample_row_keys,
                    'last_stats_soft_failure' => $soft_failure,
                    'last_stats_preserved_previous' => 1,
                )
            );

            return array(
                'preserved_previous'         => $preserved_previous,
                'last_stats_synced_at'       => $synced_at,
                'last_stats_raw_rows'        => $raw_rows,
                'last_stats_imported_rows'   => 0,
                'last_stats_date_start'      => (string) $args['date_start'],
                'last_stats_date_end'        => (string) $args['date_end'],
                'last_stats_response_shape'  => $response_shape,
                'last_stats_sample_row_keys' => $sample_row_keys,
                'last_stats_soft_failure'    => $soft_failure,
            );
        }

        if ( 0 === $raw_rows && 0 === $imported ) {
            $soft_failure = 'api_success_no_rows';
        }

        $repository->save_offer_stats( $stats_rows );
        $repository->save_stats_meta(
            array(
                'last_stats_synced_at'     => $synced_at,
                'last_stats_error'         => '',
                'last_stats_raw_rows'      => $raw_rows,
                'last_stats_imported_rows' => $imported,
                'last_stats_date_start'    => (string) $args['date_start'],
                'last_stats_date_end'      => (string) $args['date_end'],
                'last_stats_response_shape' => $response_shape,
                'last_stats_sample_row_keys' => $sample_row_keys,
                'last_stats_soft_failure' => $soft_failure,
                'last_stats_preserved_previous' => 0,
            )
        );

        return array(
            'preserved_previous'         => $preserved_previous,
            'last_stats_synced_at'       => $synced_at,
            'last_stats_raw_rows'        => $raw_rows,
            'last_stats_imported_rows'   => $imported,
            'last_stats_date_start'      => (string) $args['date_start'],
            'last_stats_date_end'        => (string) $args['date_end'],
            'last_stats_response_shape'  => $response_shape,
            'last_stats_sample_row_keys' => $sample_row_keys,
            'last_stats_soft_failure'    => $soft_failure,
        );
    }

    /**
     * @param array<string,mixed> $response API response.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function extract_stats_rows( $response ) {
        $rows = self::extract_stats_rows_recursive( $response, 0 );

        return array_values(
            array_filter(
                $rows,
                static function ( $row ) {
                    return self::is_stats_row_candidate( $row );
                }
            )
        );
    }

    /**
     * @param mixed $candidate Candidate payload.
     * @param int   $depth Current recursion depth.
     *
     * @return array<int,array<string,mixed>>
     */
    protected static function extract_stats_rows_recursive( $candidate, $depth ) {
        if ( $depth > self::MAX_UNWRAP_DEPTH || ! is_array( $candidate ) || empty( $candidate ) ) {
            return array();
        }

        if ( array_is_list( $candidate ) ) {
            $rows = array();

            foreach ( $candidate as $row ) {
                if ( self::is_stats_row_candidate( $row ) ) {
                    $rows[] = $row;
                }
            }

            if ( ! empty( $rows ) ) {
                return $rows;
            }
        }

        foreach ( self::get_wrapper_keys() as $key ) {
            if ( ! isset( $candidate[ $key ] ) ) {
                continue;
            }

            $rows = self::extract_stats_rows_recursive( $candidate[ $key ], $depth + 1 );
            if ( ! empty( $rows ) ) {
                return $rows;
            }
        }

        return array();
    }

    /**
     * @param mixed $row Candidate row.
     *
     * @return bool
     */
    public static function is_stats_row_candidate( $row ) {
        if ( ! is_array( $row ) || empty( $row ) ) {
            return false;
        }

        $flat_meta_only = array( 'status', 'httpstatus', 'errors', 'errormessage', 'data', 'response', 'results', 'report', 'rows' );
        $keys = array_map( 'strtolower', array_keys( $row ) );
        if ( ! empty( $keys ) && 0 === count( array_diff( $keys, $flat_meta_only ) ) ) {
            return false;
        }

        $stat    = self::extract_case_insensitive_array( $row, 'stat' );
        $offer   = self::extract_case_insensitive_array( $row, 'offer' );
        $country = self::extract_case_insensitive_array( $row, 'country' );

        if ( is_array( $stat ) && '' !== self::get_scalar_value( $stat, array( 'offer_id', 'offerid', 'offerId', 'Stat.offer_id' ) ) ) {
            return true;
        }

        if ( is_array( $stat ) && ( is_array( $offer ) || is_array( $country ) ) ) {
            $has_metric = '' !== self::get_scalar_value( $stat, array( 'clicks', 'conversions', 'payout', 'Stat.clicks', 'Stat.conversions', 'Stat.payout' ) );
            return $has_metric;
        }

        $flat_offer_id = self::get_scalar_value( $row, array( 'offer_id', 'offerid', 'offerId', 'Stat.offer_id' ) );
        $has_metric    = '' !== self::get_scalar_value( $row, array( 'clicks', 'conversions', 'payout', 'Stat.clicks', 'Stat.conversions', 'Stat.payout' ) );

        return '' !== $flat_offer_id && $has_metric;
    }

    /**
     * @param array<string,mixed> $row Raw row.
     *
     * @return array<string,mixed>
     */
    public static function normalize_stats_row( $row ) {
        $stat    = self::extract_case_insensitive_array( $row, 'stat' );
        $offer   = self::extract_case_insensitive_array( $row, 'offer' );
        $country = self::extract_case_insensitive_array( $row, 'country' );
        $source  = is_array( $stat ) ? $stat : $row;

        $offer_id = self::get_scalar_value( $source, array( 'offer_id', 'offerid', 'offerId', 'Stat.offer_id' ) );
        if ( '' === $offer_id ) {
            $offer_id = self::get_scalar_value( $row, array( 'offer_id', 'offerid', 'offerId', 'Stat.offer_id' ) );
        }
        $country_name = self::get_scalar_value( is_array( $country ) ? $country : $row, array( 'name', 'country_name', 'Country.name' ) );
        $country_name = strtoupper( sanitize_text_field( $country_name ) );
        if ( '' === $country_name ) {
            $country_name = 'GLOBAL';
        }

        return array(
            'offer_id'         => sanitize_text_field( $offer_id ),
            'offer_name'       => sanitize_text_field( self::get_scalar_value( is_array( $offer ) ? $offer : $row, array( 'name', 'offer_name', 'Offer.name' ) ) ),
            'country_name'     => $country_name,
            'clicks'           => max( 0, (int) self::sanitize_numeric( self::get_scalar_value( $source, array( 'clicks', 'Stat.clicks' ), self::get_scalar_value( $row, array( 'clicks', 'Stat.clicks' ), '0' ) ) ) ),
            'conversions'      => max( 0.0, (float) self::sanitize_numeric( self::get_scalar_value( $source, array( 'conversions', 'Stat.conversions' ), self::get_scalar_value( $row, array( 'conversions', 'Stat.conversions' ), '0' ) ) ) ),
            'payout'           => max( 0.0, (float) self::sanitize_numeric( self::get_scalar_value( $source, array( 'payout', 'Stat.payout' ), self::get_scalar_value( $row, array( 'payout', 'Stat.payout' ), '0' ) ) ) ),
            'payout_type'      => sanitize_text_field( self::get_scalar_value( $source, array( 'payout_type', 'payoutType', 'Stat.payout_type' ), self::get_scalar_value( $row, array( 'payout_type', 'payoutType', 'Stat.payout_type' ) ) ) ),
            'epc'              => 0.0,
            'conversion_rate'  => 0.0,
            'date_start'       => '',
            'date_end'         => '',
            'synced_at'        => '',
        );
    }

    /**
     * @param array<string,mixed> $response API response.
     *
     * @return string
     */
    public static function detect_stats_response_shape( $response ) {
        if ( ! is_array( $response ) || empty( $response ) ) {
            return 'unknown';
        }

        $path = self::detect_shape_recursive( $response, 0, 'root' );

        return sanitize_text_field( substr( $path, 0, 120 ) );
    }

    /**
     * @param array<string,mixed> $row Row sample.
     *
     * @return string
     */
    protected static function summarize_row_keys( $row ) {
        $keys = array_slice( array_keys( $row ), 0, 8 );
        $keys = array_map( 'sanitize_key', $keys );

        return sanitize_text_field( implode( ',', array_filter( $keys ) ) );
    }

    /**
     * @return array<int,string>
     */
    protected static function get_wrapper_keys() {
        return array( 'response', 'data', 'results', 'report', 'rows' );
    }

    protected static function detect_shape_recursive( $candidate, $depth, $path ) {
        if ( $depth > self::MAX_UNWRAP_DEPTH || ! is_array( $candidate ) || empty( $candidate ) ) {
            return $path;
        }
        if ( array_is_list( $candidate ) ) {
            return $path . '.list';
        }
        foreach ( self::get_wrapper_keys() as $key ) {
            if ( isset( $candidate[ $key ] ) && is_array( $candidate[ $key ] ) ) {
                return self::detect_shape_recursive( $candidate[ $key ], $depth + 1, $path . '.' . $key );
            }
        }

        return $path . '.keyed';
    }

    protected static function extract_case_insensitive_array( $payload, $target_key ) {
        if ( ! is_array( $payload ) ) {
            return null;
        }
        foreach ( $payload as $key => $value ) {
            if ( is_string( $key ) && strtolower( $key ) === strtolower( $target_key ) && is_array( $value ) ) {
                return $value;
            }
        }

        return null;
    }

    protected static function get_scalar_value( $source, $candidates, $default = '' ) {
        if ( ! is_array( $source ) ) {
            return (string) $default;
        }
        foreach ( (array) $candidates as $candidate ) {
            if ( isset( $source[ $candidate ] ) && ! is_array( $source[ $candidate ] ) ) {
                return trim( (string) $source[ $candidate ] );
            }
            foreach ( $source as $key => $value ) {
                if ( ! is_string( $key ) || is_array( $value ) ) {
                    continue;
                }
                if ( strtolower( $key ) === strtolower( (string) $candidate ) ) {
                    return trim( (string) $value );
                }
            }
        }

        return (string) $default;
    }

    protected static function sanitize_numeric( $value ) {
        $value = is_scalar( $value ) ? (string) $value : '0';
        $value = preg_replace( '/[^0-9.\-]/', '', $value );

        return '' === $value ? '0' : $value;
    }

    /**
     * @param float $payout Payout.
     * @param int   $clicks Clicks.
     *
     * @return float
     */
    public static function calculate_epc( $payout, $clicks ) {
        if ( $clicks <= 0 ) {
            return 0.0;
        }

        return round( (float) $payout / $clicks, 6 );
    }

    /**
     * @param float $conversions Conversions.
     * @param int   $clicks Clicks.
     *
     * @return float
     */
    public static function calculate_conversion_rate( $conversions, $clicks ) {
        if ( $clicks <= 0 ) {
            return 0.0;
        }

        return round( ( (float) $conversions / $clicks ) * 100, 4 );
    }
}
