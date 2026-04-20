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
        $page        = 1;
        $limit       = 100;
        $all_offers  = array();
        $synced_at   = gmdate( 'c' );
        $has_results = false;

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
                        'last_synced_at' => '',
                        'last_error'     => $response->get_error_message(),
                        'offer_count'    => count( $repository->get_synced_offers() ),
                    )
                );

                return $response;
            }

            $rows = self::extract_offer_rows( $response );

            if ( empty( $rows ) ) {
                break;
            }

            $has_results = true;

            foreach ( $rows as $row ) {
                $normalized = self::normalize_offer( $row );

                if ( empty( $normalized['id'] ) ) {
                    continue;
                }

                $all_offers[ $normalized['id'] ] = $normalized;
            }

            ++$page;
        } while ( count( $rows ) >= $limit );

        if ( ! $has_results && empty( $all_offers ) ) {
            $repository->save_sync_meta(
                array(
                    'last_synced_at' => $synced_at,
                    'last_error'     => '',
                    'offer_count'    => 0,
                )
            );
            $repository->save_synced_offers( array() );

            return array(
                'offer_count'    => 0,
                'last_synced_at' => $synced_at,
            );
        }

        $repository->save_synced_offers( $all_offers );
        $repository->save_sync_meta(
            array(
                'last_synced_at' => $synced_at,
                'last_error'     => '',
                'offer_count'    => count( $all_offers ),
            )
        );

        return array(
            'offer_count'    => count( $all_offers ),
            'last_synced_at' => $synced_at,
        );
    }

    /**
     * @param array<string,mixed> $response API response.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function extract_offer_rows( $response ) {
        if ( isset( $response['response']['data'] ) && is_array( $response['response']['data'] ) ) {
            return array_values( $response['response']['data'] );
        }

        if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
            return array_values( $response['data'] );
        }

        if ( array_is_list( $response ) ) {
            return array_values( $response );
        }

        return array();
    }

    /**
     * @param array<string,mixed> $offer Raw offer.
     *
     * @return array<string,mixed>
     */
    public static function normalize_offer( $offer ) {
        $featured_raw = isset( $offer['featured'] ) ? (string) $offer['featured'] : '';
        $is_featured  = '' !== $featured_raw && '0000-00-00 00:00:00' !== $featured_raw;

        return array(
            'id'              => (string) ( $offer['id'] ?? '' ),
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
