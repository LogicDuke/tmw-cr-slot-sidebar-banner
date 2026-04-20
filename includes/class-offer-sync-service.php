<?php
/**
 * Offer sync workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Offer_Sync_Service {
    /**
     * @return array<int,string>
     */
    public static function get_default_offer_fields() {
        return array(
            'id',
            'name',
            'description',
            'preview_url',
            'status',
            'default_payout',
            'percent_payout',
            'payout_type',
            'require_approval',
            'featured',
            'currency',
        );
    }

    /**
     * Syncs all offers into local storage.
     *
     * @param TMW_CR_Slot_CR_API_Client    $client API client.
     * @param TMW_CR_Slot_Offer_Repository $repository Repository.
     *
     * @return array<string,mixed>|WP_Error
     */
    public static function sync_all( $client, $repository ) {
        $page                = 1;
        $limit               = 100;
        $all_offers          = array();
        $synced_at           = gmdate( 'c' );
        $last_response_shape = 'unknown';
        $sample_row_keys     = '';
        $raw_row_count       = 0;
        $imported_count      = 0;
        $skipped_count       = 0;

        do {
            $response = $client->find_all_offers(
                array(
                    'fields' => self::get_default_offer_fields(),
                    'sort'   => array( 'id' => 'asc' ),
                    'limit'  => $limit,
                    'page'   => $page,
                )
            );

            if ( is_wp_error( $response ) ) {
                $repository->save_sync_meta(
                    array(
                        'last_synced_at'      => '',
                        'last_error'          => $response->get_error_message(),
                        'offer_count'         => count( $repository->get_synced_offers() ),
                        'last_raw_row_count'  => $raw_row_count,
                        'last_imported_count' => $imported_count,
                        'last_skipped_count'  => $skipped_count,
                        'last_response_shape' => $last_response_shape,
                        'last_soft_failure'   => 0,
                        'sample_row_keys'     => $sample_row_keys,
                    )
                );

                return $response;
            }

            $last_response_shape = self::detect_response_shape( $response );
            $rows                = self::extract_offer_rows( $response );

            if ( empty( $rows ) ) {
                break;
            }

            $raw_row_count += count( $rows );

            if ( '' === $sample_row_keys ) {
                $sample_row_keys = self::summarize_row_keys( $rows[0] );
            }

            foreach ( $rows as $row ) {
                $normalized = self::normalize_offer( $row );

                if ( empty( $normalized['id'] ) ) {
                    ++$skipped_count;
                    continue;
                }

                $all_offers[ $normalized['id'] ] = $normalized;
            }

            $imported_count = count( $all_offers );
            ++$page;
        } while ( count( $rows ) >= $limit );

        if ( $raw_row_count > 0 && 0 === $imported_count ) {
            $repository->save_sync_meta(
                array(
                    'last_synced_at'      => $synced_at,
                    'last_error'          => __( '[TMW-CR-SYNC] Sync preserved existing offers: parser mismatch detected (rows fetched but 0 imported).', 'tmw-cr-slot-sidebar-banner' ),
                    'offer_count'         => count( $repository->get_synced_offers() ),
                    'last_raw_row_count'  => $raw_row_count,
                    'last_imported_count' => 0,
                    'last_skipped_count'  => $skipped_count,
                    'last_response_shape' => $last_response_shape,
                    'last_soft_failure'   => 1,
                    'sample_row_keys'     => $sample_row_keys,
                )
            );

            return array(
                'offer_count'          => count( $repository->get_synced_offers() ),
                'last_synced_at'       => $synced_at,
                'last_raw_row_count'   => $raw_row_count,
                'last_imported_count'  => 0,
                'last_skipped_count'   => $skipped_count,
                'last_response_shape'  => $last_response_shape,
                'last_soft_failure'    => 1,
                'preserved_previous'   => true,
                'sample_row_keys'      => $sample_row_keys,
            );
        }

        if ( 0 === $raw_row_count ) {
            $repository->save_sync_meta(
                array(
                    'last_synced_at'      => $synced_at,
                    'last_error'          => '',
                    'offer_count'         => 0,
                    'last_raw_row_count'  => 0,
                    'last_imported_count' => 0,
                    'last_skipped_count'  => 0,
                    'last_response_shape' => $last_response_shape,
                    'last_soft_failure'   => 0,
                    'sample_row_keys'     => '',
                )
            );
            $repository->save_synced_offers( array() );

            return array(
                'offer_count'          => 0,
                'last_synced_at'       => $synced_at,
                'last_raw_row_count'   => 0,
                'last_imported_count'  => 0,
                'last_skipped_count'   => 0,
                'last_response_shape'  => $last_response_shape,
                'last_soft_failure'    => 0,
                'preserved_previous'   => false,
                'sample_row_keys'      => '',
            );
        }

        $repository->save_synced_offers( $all_offers );
        $repository->save_sync_meta(
            array(
                'last_synced_at'      => $synced_at,
                'last_error'          => '',
                'offer_count'         => count( $all_offers ),
                'last_raw_row_count'  => $raw_row_count,
                'last_imported_count' => count( $all_offers ),
                'last_skipped_count'  => $skipped_count,
                'last_response_shape' => $last_response_shape,
                'last_soft_failure'   => 0,
                'sample_row_keys'     => $sample_row_keys,
            )
        );

        return array(
            'offer_count'          => count( $all_offers ),
            'last_synced_at'       => $synced_at,
            'last_raw_row_count'   => $raw_row_count,
            'last_imported_count'  => count( $all_offers ),
            'last_skipped_count'   => $skipped_count,
            'last_response_shape'  => $last_response_shape,
            'last_soft_failure'    => 0,
            'preserved_previous'   => false,
            'sample_row_keys'      => $sample_row_keys,
        );
    }

    /**
     * @param array<string,mixed> $response API response.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function extract_offer_rows( $response ) {
        $candidates = array();

        if ( isset( $response['response']['data'] ) && is_array( $response['response']['data'] ) ) {
            $candidates[] = $response['response']['data'];
        }

        if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
            $candidates[] = $response['data'];
        }

        if ( isset( $response['response']['results'] ) && is_array( $response['response']['results'] ) ) {
            $candidates[] = $response['response']['results'];
        }

        if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
            $candidates[] = $response['results'];
        }

        if ( is_array( $response ) ) {
            $candidates[] = $response;
        }

        foreach ( $candidates as $candidate ) {
            $rows = self::extract_rows_from_candidate( $candidate );

            if ( ! empty( $rows ) ) {
                return $rows;
            }
        }

        return array();
    }

    /**
     * @param array<string,mixed> $response API response.
     *
     * @return string
     */
    public static function detect_response_shape( $response ) {
        $candidates = array();

        if ( isset( $response['response']['data'] ) && is_array( $response['response']['data'] ) ) {
            $candidates[] = array(
                'shape' => array_is_list( $response['response']['data'] ) ? 'response.data' : 'response.data:keyed',
                'rows'  => self::extract_rows_from_candidate( $response['response']['data'] ),
            );
        }

        if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
            $candidates[] = array(
                'shape' => array_is_list( $response['data'] ) ? 'data' : 'data:keyed',
                'rows'  => self::extract_rows_from_candidate( $response['data'] ),
            );
        }

        if ( isset( $response['response']['results'] ) && is_array( $response['response']['results'] ) ) {
            $candidates[] = array(
                'shape' => array_is_list( $response['response']['results'] ) ? 'response.results' : 'response.results:keyed',
                'rows'  => self::extract_rows_from_candidate( $response['response']['results'] ),
            );
        }

        if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
            $candidates[] = array(
                'shape' => array_is_list( $response['results'] ) ? 'results' : 'results:keyed',
                'rows'  => self::extract_rows_from_candidate( $response['results'] ),
            );
        }

        if ( is_array( $response ) ) {
            $candidates[] = array(
                'shape' => array_is_list( $response ) ? 'list' : 'top-level:keyed',
                'rows'  => self::extract_rows_from_candidate( $response ),
            );
        }

        foreach ( $candidates as $candidate ) {
            if ( ! empty( $candidate['rows'] ) ) {
                return (string) $candidate['shape'];
            }
        }

        foreach ( $candidates as $candidate ) {
            if ( '' !== (string) $candidate['shape'] ) {
                return (string) $candidate['shape'];
            }
        }

        return 'unknown';
    }

    /**
     * @param array<string,mixed> $row Row candidate.
     *
     * @return string
     */
    public static function summarize_row_keys( $row ) {
        $unwrapped = self::unwrap_offer_row( $row );

        if ( ! is_array( $unwrapped ) || empty( $unwrapped ) ) {
            return '';
        }

        $keys = array_slice( array_keys( $unwrapped ), 0, 10 );
        $keys = array_map( 'sanitize_key', $keys );
        $keys = array_filter( $keys );

        return implode( ',', $keys );
    }

    /**
     * @param array<string,mixed> $offer Raw offer.
     *
     * @return array<string,mixed>
     */
    public static function normalize_offer( $offer ) {
        $offer        = self::unwrap_offer_row( $offer );
        $featured_raw = isset( $offer['featured'] ) ? (string) $offer['featured'] : '';
        $is_featured  = '' !== $featured_raw && '0000-00-00 00:00:00' !== $featured_raw;

        return array(
            'id'              => (string) ( $offer['id'] ?? $offer['ID'] ?? $offer['offer_id'] ?? '' ),
            'name'            => sanitize_text_field( (string) ( $offer['name'] ?? '' ) ),
            'description'     => sanitize_textarea_field( (string) ( $offer['description'] ?? '' ) ),
            'preview_url'     => esc_url_raw( (string) ( $offer['preview_url'] ?? '' ) ),
            'status'          => sanitize_text_field( (string) ( $offer['status'] ?? '' ) ),
            'default_payout'  => sanitize_text_field( (string) ( $offer['default_payout'] ?? '' ) ),
            'percent_payout'  => sanitize_text_field( (string) ( $offer['percent_payout'] ?? '' ) ),
            'payout_type'     => sanitize_text_field( (string) ( $offer['payout_type'] ?? '' ) ),
            'require_approval'=> self::normalize_boolean_string( $offer['require_approval'] ?? '' ),
            'featured'        => $featured_raw,
            'is_featured'     => $is_featured,
            'currency'        => sanitize_text_field( (string) ( $offer['currency'] ?? '' ) ),
        );
    }

    /**
     * @param array<string,mixed> $offer Raw row.
     *
     * @return array<string,mixed>
     */
    protected static function unwrap_offer_row( $offer ) {
        if ( isset( $offer['Offer'] ) && is_array( $offer['Offer'] ) ) {
            return $offer['Offer'];
        }

        if ( isset( $offer['offer'] ) && is_array( $offer['offer'] ) ) {
            return $offer['offer'];
        }

        return $offer;
    }

    /**
     * @param mixed $candidate Potential row collection.
     *
     * @return array<int,array<string,mixed>>
     */
    protected static function extract_rows_from_candidate( $candidate ) {
        if ( ! is_array( $candidate ) || empty( $candidate ) ) {
            return array();
        }

        $rows = array();

        foreach ( $candidate as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            if ( self::is_offer_row_candidate( $entry ) ) {
                $rows[] = $entry;
            }
        }

        return array_values( $rows );
    }

    /**
     * @param array<string,mixed> $entry Candidate row.
     *
     * @return bool
     */
    protected static function is_offer_row_candidate( $entry ) {
        if ( isset( $entry['Offer'] ) && is_array( $entry['Offer'] ) ) {
            return true;
        }

        if ( isset( $entry['offer'] ) && is_array( $entry['offer'] ) ) {
            return true;
        }

        $known_keys = array(
            'id',
            'ID',
            'offer_id',
            'name',
            'description',
            'preview_url',
            'status',
            'default_payout',
            'percent_payout',
            'payout_type',
            'require_approval',
            'featured',
            'currency',
        );

        foreach ( $known_keys as $known_key ) {
            if ( array_key_exists( $known_key, $entry ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value Raw value.
     *
     * @return string
     */
    protected static function normalize_boolean_string( $value ) {
        $value = strtolower( trim( (string) $value ) );

        if ( in_array( $value, array( '1', 'true', 'enabled', 'yes' ), true ) ) {
            return '1';
        }

        return '0';
    }
}
