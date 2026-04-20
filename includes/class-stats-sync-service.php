<?php
/**
 * [TMW-CR-STATS] Stats sync workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Stats_Sync_Service {
    const CRON_HOOK = 'tmw_cr_slot_banner_scheduled_stats_sync';
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
            $repository->save_stats_meta(
                array(
                    'last_stats_synced_at'     => $synced_at,
                    'last_stats_error'         => __( '[TMW-CR-STATS] Stats sync preserved existing stats: parser mismatch detected.', 'tmw-cr-slot-sidebar-banner' ),
                    'last_stats_raw_rows'      => $raw_rows,
                    'last_stats_imported_rows' => 0,
                    'last_stats_date_start'    => (string) $args['date_start'],
                    'last_stats_date_end'      => (string) $args['date_end'],
                )
            );

            return array(
                'preserved_previous'         => true,
                'last_stats_synced_at'       => $synced_at,
                'last_stats_raw_rows'        => $raw_rows,
                'last_stats_imported_rows'   => 0,
                'last_stats_date_start'      => (string) $args['date_start'],
                'last_stats_date_end'        => (string) $args['date_end'],
            );
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
            )
        );

        return array(
            'preserved_previous'         => false,
            'last_stats_synced_at'       => $synced_at,
            'last_stats_raw_rows'        => $raw_rows,
            'last_stats_imported_rows'   => $imported,
            'last_stats_date_start'      => (string) $args['date_start'],
            'last_stats_date_end'        => (string) $args['date_end'],
        );
    }

    /**
     * @param array<string,mixed> $response API response.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function extract_stats_rows( $response ) {
        $candidates = array(
            isset( $response['response']['data'] ) ? $response['response']['data'] : null,
            isset( $response['data'] ) ? $response['data'] : null,
            isset( $response['results'] ) ? $response['results'] : null,
            isset( $response['response'] ) ? $response['response'] : null,
            $response,
        );

        foreach ( $candidates as $candidate ) {
            if ( ! is_array( $candidate ) || empty( $candidate ) ) {
                continue;
            }

            if ( array_is_list( $candidate ) ) {
                return array_values(
                    array_filter(
                        $candidate,
                        static function ( $row ) {
                            return is_array( $row );
                        }
                    )
                );
            }

            $rows = array();
            foreach ( $candidate as $row ) {
                if ( is_array( $row ) ) {
                    $rows[] = $row;
                }
            }

            if ( ! empty( $rows ) ) {
                return array_values( $rows );
            }
        }

        return array();
    }

    /**
     * @param array<string,mixed> $row Raw row.
     *
     * @return array<string,mixed>
     */
    public static function normalize_stats_row( $row ) {
        $stat    = isset( $row['Stat'] ) && is_array( $row['Stat'] ) ? $row['Stat'] : $row;
        $offer   = isset( $row['Offer'] ) && is_array( $row['Offer'] ) ? $row['Offer'] : array();
        $country = isset( $row['Country'] ) && is_array( $row['Country'] ) ? $row['Country'] : array();

        $offer_id = (string) ( $stat['offer_id'] ?? $stat['offerid'] ?? $row['offer_id'] ?? '' );

        return array(
            'offer_id'         => sanitize_text_field( $offer_id ),
            'offer_name'       => sanitize_text_field( (string) ( $offer['name'] ?? $row['offer_name'] ?? '' ) ),
            'country_name'     => sanitize_text_field( (string) ( $country['name'] ?? $row['country_name'] ?? 'GLOBAL' ) ),
            'clicks'           => max( 0, (int) ( $stat['clicks'] ?? $row['clicks'] ?? 0 ) ),
            'conversions'      => (float) ( $stat['conversions'] ?? $row['conversions'] ?? 0 ),
            'payout'           => (float) ( $stat['payout'] ?? $row['payout'] ?? 0 ),
            'payout_type'      => sanitize_text_field( (string) ( $stat['payout_type'] ?? $row['payout_type'] ?? '' ) ),
            'epc'              => 0.0,
            'conversion_rate'  => 0.0,
            'date_start'       => '',
            'date_end'         => '',
            'synced_at'        => '',
        );
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
