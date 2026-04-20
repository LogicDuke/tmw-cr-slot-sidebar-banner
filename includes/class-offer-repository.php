<?php
/**
 * Local storage and frontend normalization for synced offers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Offer_Repository {
    /** @var string */
    protected $offers_option_key;

    /** @var string */
    protected $meta_option_key;

    /**
     * @param string $offers_option_key Option key for synced offers.
     * @param string $meta_option_key   Option key for sync meta.
     */
    public function __construct( $offers_option_key, $meta_option_key ) {
        $this->offers_option_key = $offers_option_key;
        $this->meta_option_key   = $meta_option_key;
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
    }

    /**
     * @return array<string,mixed>
     */
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
        unset( $slot_key, $country );

        $synced_offers = $this->get_synced_offers();
        $selected_ids  = isset( $settings['slot_offer_ids'] ) && is_array( $settings['slot_offer_ids'] ) ? array_values( $settings['slot_offer_ids'] ) : array();
        $priorities    = isset( $settings['slot_offer_priority'] ) && is_array( $settings['slot_offer_priority'] ) ? $settings['slot_offer_priority'] : array();
        $image_map     = isset( $settings['offer_image_overrides'] ) && is_array( $settings['offer_image_overrides'] ) ? $settings['offer_image_overrides'] : array();

        $offers = array();

        foreach ( $selected_ids as $selected_id ) {
            $selected_id = (string) $selected_id;

            if ( isset( $synced_offers[ $selected_id ] ) ) {
                $offers[] = $this->normalize_synced_offer( $synced_offers[ $selected_id ], $banner_data, $image_map );
            }
        }

        $offers = array_values( array_filter( $offers ) );

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
                $offers[] = $this->normalize_synced_offer( $synced_offer, $banner_data, $image_map );
            }
        }

        $offers = array_values( array_filter( $offers ) );

        if ( ! empty( $priorities ) ) {
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
        }

        if ( count( $offers ) < 3 ) {
            foreach ( $legacy_catalog as $legacy_offer ) {
                $offers[] = $this->normalize_legacy_offer( $legacy_offer, $banner_data );

                if ( count( $offers ) >= 3 ) {
                    break;
                }
            }
        }

        $offers = array_values( array_filter( $offers ) );

        return apply_filters( 'tmw_cr_slot_banner_offers', $offers, '', $banner_data );
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

        $image = '';

        if ( ! empty( $image_map[ $offer_id ] ) ) {
            $image = esc_url_raw( $image_map[ $offer_id ] );
        }

        if ( '' === $image ) {
            $image = $this->build_placeholder_image( (string) ( $offer['name'] ?? $offer_id ) );
        }

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

        if ( ! empty( $offer['preview_url'] ) ) {
            return esc_url_raw( (string) $offer['preview_url'] );
        }

        return '';
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
