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
            'tags',
            'tag',
            'vertical',
            'niche',
            'performs_in',
            'optimized_for',
            'accepted_countries',
            'accepted_country',
            'promotion_method',
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
        return self::extract_offer_rows_from_payload( $response );
    }

    /**
     * @param array<string,mixed> $response API response.
     *
     * @return string
     */
    public static function detect_response_shape( $response ) {
        $shape = self::detect_response_shape_from_payload( $response, 'top-level' );

        if ( 'unknown' !== $shape ) {
            return $shape;
        }

        if ( is_array( $response ) ) {
            return array_is_list( $response ) ? 'list' : 'top-level:keyed';
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
            'tags'            => self::normalize_list_field( self::pick_first_value( $offer, array( 'tags', 'tag', 'labels', 'offer_tags' ) ) ),
            'vertical'        => sanitize_text_field( (string) self::pick_first_value( $offer, array( 'vertical', 'category', 'offer_vertical' ) ) ),
            'performs_in'     => self::normalize_country_list_field( self::pick_first_value( $offer, array( 'performs_in', 'top_countries', 'performing_countries' ) ) ),
            'optimized_for'   => self::normalize_list_field( self::pick_first_value( $offer, array( 'optimized_for', 'optimization_goal', 'device_targets' ) ) ),
            'accepted_countries' => self::normalize_country_list_field( self::pick_first_value( $offer, array( 'accepted_countries', 'accepted_country', 'countries', 'country_codes' ) ) ),
            'niche'           => self::normalize_list_field( self::pick_first_value( $offer, array( 'niche', 'niches' ) ) ),
            'promotion_method' => self::normalize_list_field( self::pick_first_value( $offer, array( 'promotion_method', 'promotion_methods', 'traffic_types' ) ) ),
        );
    }

    /**
     * @param array<string,mixed> $offer Offer row.
     * @param array<int,string>   $keys Candidate keys.
     *
     * @return mixed
     */
    protected static function pick_first_value( $offer, $keys ) {
        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $offer ) ) {
                return $offer[ $key ];
            }
        }

        return '';
    }

    /**
     * @param mixed $value Raw value.
     *
     * @return array<int,string>
     */
    protected static function normalize_list_field( $value ) {
        $items = array();

        if ( is_array( $value ) ) {
            $items = $value;
        } elseif ( is_string( $value ) ) {
            $items = preg_split( '/[,|]/', $value );
        } elseif ( is_scalar( $value ) ) {
            $items = array( (string) $value );
        }

        $normalized = array();
        foreach ( (array) $items as $item ) {
            $item = sanitize_text_field( trim( (string) $item ) );
            if ( '' === $item ) {
                continue;
            }
            $normalized[] = $item;
        }

        return array_values( array_unique( $normalized ) );
    }

    /**
     * @param mixed $value Raw value.
     *
     * @return array<int,string>
     */
    protected static function normalize_country_list_field( $value ) {
        $countries = self::normalize_list_field( $value );

        return array_values(
            array_unique(
                array_map(
                    static function ( $country ) {
                        return strtoupper( sanitize_text_field( (string) $country ) );
                    },
                    $countries
                )
            )
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

        $strong_identifiers = array(
            'id',
            'ID',
            'offer_id',
        );

        foreach ( $strong_identifiers as $identifier_key ) {
            if ( array_key_exists( $identifier_key, $entry ) ) {
                return true;
            }
        }

        if ( self::is_envelope_container( $entry ) ) {
            return false;
        }

        $has_name = array_key_exists( 'name', $entry );
        $offer_context_keys = array(
            'preview_url',
            'payout_type',
            'description',
            'default_payout',
            'percent_payout',
            'currency',
            'require_approval',
            'featured',
            'status',
        );

        if ( $has_name ) {
            foreach ( $offer_context_keys as $context_key ) {
                if ( array_key_exists( $context_key, $entry ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param mixed $payload API payload.
     * @param int   $depth Traversal depth.
     *
     * @return array<int,array<string,mixed>>
     */
    protected static function extract_offer_rows_from_payload( $payload, $depth = 0 ) {
        if ( ! is_array( $payload ) || $depth > 4 ) {
            return array();
        }

        $rows = self::extract_rows_from_candidate( $payload );
        if ( ! empty( $rows ) ) {
            return $rows;
        }

        foreach ( self::get_response_wrapper_keys() as $wrapper_key ) {
            if ( isset( $payload[ $wrapper_key ] ) && is_array( $payload[ $wrapper_key ] ) ) {
                $rows = self::extract_offer_rows_from_payload( $payload[ $wrapper_key ], $depth + 1 );
                if ( ! empty( $rows ) ) {
                    return $rows;
                }
            }
        }

        return array();
    }

    /**
     * @param mixed  $payload API payload.
     * @param string $shape_path Current path.
     * @param int    $depth Traversal depth.
     *
     * @return string
     */
    protected static function detect_response_shape_from_payload( $payload, $shape_path, $depth = 0 ) {
        if ( ! is_array( $payload ) || $depth > 4 ) {
            return 'unknown';
        }

        $rows = self::extract_rows_from_candidate( $payload );
        if ( ! empty( $rows ) ) {
            if ( 'top-level' === $shape_path ) {
                return array_is_list( $payload ) ? 'list' : 'top-level:keyed';
            }

            return array_is_list( $payload ) ? $shape_path : $shape_path . ':keyed';
        }

        foreach ( self::get_response_wrapper_keys() as $wrapper_key ) {
            if ( ! isset( $payload[ $wrapper_key ] ) || ! is_array( $payload[ $wrapper_key ] ) ) {
                continue;
            }

            $next_shape = self::append_shape_segment( $shape_path, $payload, $wrapper_key );
            $detected   = self::detect_response_shape_from_payload( $payload[ $wrapper_key ], $next_shape, $depth + 1 );

            if ( 'unknown' !== $detected ) {
                return $detected;
            }
        }

        return 'unknown';
    }

    /**
     * @return array<int,string>
     */
    protected static function get_response_wrapper_keys() {
        return array( 'response', 'data', 'results' );
    }

    /**
     * @param string              $current_shape Current shape path.
     * @param array<string,mixed> $parent_payload Parent payload.
     * @param string              $wrapper_key Wrapper key.
     *
     * @return string
     */
    protected static function append_shape_segment( $current_shape, $parent_payload, $wrapper_key ) {
        if ( 'data' === $wrapper_key && self::is_envelope_container( $parent_payload ) ) {
            if ( 'top-level' === $current_shape ) {
                return 'envelope.data';
            }

            return $current_shape . '.envelope.data';
        }

        if ( 'top-level' === $current_shape ) {
            return $wrapper_key;
        }

        return $current_shape . '.' . $wrapper_key;
    }

    /**
     * @param array<string,mixed> $entry Candidate entry.
     *
     * @return bool
     */
    protected static function is_envelope_container( $entry ) {
        $keys = array_map( 'strtolower', array_keys( $entry ) );
        $keys = array_unique( $keys );

        $envelope_markers = array( 'status', 'httpstatus', 'data', 'errors', 'errormessage' );
        $marker_count     = count( array_intersect( $keys, $envelope_markers ) );

        if ( $marker_count >= 3 ) {
            return true;
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
