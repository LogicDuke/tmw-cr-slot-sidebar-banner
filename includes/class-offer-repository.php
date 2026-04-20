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

    /** @var string */
    protected $overrides_option_key;

    /**
     * @param string $offers_option_key     Option key for synced offers.
     * @param string $meta_option_key       Option key for sync meta.
     * @param string $overrides_option_key  Option key for offer overrides.
     */
    public function __construct( $offers_option_key, $meta_option_key, $overrides_option_key = 'tmw_cr_slot_banner_offer_overrides' ) {
        $this->offers_option_key    = $offers_option_key;
        $this->meta_option_key      = $meta_option_key;
        $this->overrides_option_key = $overrides_option_key;
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
            'featured'          => '',
            'approval_required' => '',
            'payout_type'       => '',
            'image_status'      => '',
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
        $status      = strtolower( trim( (string) $query['status'] ) );
        $featured    = strtolower( trim( (string) $query['featured'] ) );
        $approval    = strtolower( trim( (string) $query['approval_required'] ) );
        $payout_type = strtolower( trim( (string) $query['payout_type'] ) );
        $image       = strtolower( trim( (string) $query['image_status'] ) );
        $offers      = array_values( $this->get_synced_offers() );

        $filtered = array();

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

            $offer_status = strtolower( (string) ( $offer['status'] ?? '' ) );
            if ( '' !== $status && $status !== $offer_status ) {
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

            $offer_payout_type = strtolower( (string) ( $offer['payout_type'] ?? '' ) );
            if ( '' !== $payout_type && $payout_type !== $offer_payout_type ) {
                continue;
            }

            $image_status = $this->get_image_status_for_offer( $offer_id, $settings );
            if ( '' !== $image && $image !== $image_status ) {
                continue;
            }

            $offer['is_selected_for_slot'] = $is_selected;
            $offer['image_status']         = $image_status;
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

        return array(
            'items'    => array_slice( $filtered, $offset, $per_page ),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => $pages,
            'sort_by'  => $sort_by,
            'order'    => $sort_order,
        );
    }

    /**
     * @param string $offer_id Offer ID.
     * @param array<string,mixed> $settings Settings payload.
     *
     * @return string
     */
    public function get_image_status_for_offer( $offer_id, $settings ) {
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

        return 'placeholder_only';
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

        foreach ( $selected_ids as $selected_id ) {
            $selected_id = (string) $selected_id;

            if ( isset( $synced_offers[ $selected_id ] ) ) {
                $effective = $this->get_effective_offer_record(
                    $selected_id,
                    $settings,
                    $banner_data,
                    $country,
                    $legacy_catalog
                );

                if ( ! empty( $effective ) ) {
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

                $override = isset( $overrides_map[ $offer_id ] ) ? $overrides_map[ $offer_id ] : array();
                if ( ! $this->is_offer_allowed_for_country( $offer_id, $country, $override, $synced_offer, $legacy_catalog ) ) {
                    continue;
                }

                $effective = $this->get_effective_offer_record(
                    $offer_id,
                    $settings,
                    $banner_data,
                    $country,
                    $legacy_catalog
                );

                if ( ! empty( $effective ) ) {
                    $offers[] = $effective;
                }
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

                if ( ! $this->is_offer_allowed_for_country( $legacy_id, $country, array(), array(), $legacy_catalog ) ) {
                    continue;
                }

                $offers[]  = $this->normalize_legacy_offer( $legacy_offer, $banner_data );
                $used_ids[] = $legacy_id;

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
            'image'    => $this->get_effective_image( $offer_id, $settings, $banner_data, $synced_offer, $override ),
            'cta_url'  => $this->get_effective_cta_url( $offer_id, $settings, $banner_data, $synced_offer, $override ),
            'cta_text' => $this->get_effective_cta_text( $offer_id, $settings, $banner_data, $synced_offer, $override, $legacy_catalog ),
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

        $allowed = isset( $override['allowed_countries'] ) ? $this->sanitize_country_codes( $override['allowed_countries'] ) : array();
        if ( ! empty( $allowed ) && ( '' === $country || ! in_array( $country, $allowed, true ) ) ) {
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
            return esc_url_raw( (string) $override['final_url_override'] );
        }

        $fallback = $this->build_cta_url( $banner_data, $synced_offer );
        if ( '' !== $fallback ) {
            return $fallback;
        }

        if ( ! empty( $synced_offer['preview_url'] ) ) {
            return esc_url_raw( (string) $synced_offer['preview_url'] );
        }

        return '';
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
    public function get_effective_image( $offer_id, $settings, $banner_data, $synced_offer, $override ) {
        unset( $banner_data );

        if ( ! empty( $override['image_url_override'] ) ) {
            return esc_url_raw( (string) $override['image_url_override'] );
        }

        $image_map = isset( $settings['offer_image_overrides'] ) && is_array( $settings['offer_image_overrides'] ) ? $settings['offer_image_overrides'] : array();
        if ( ! empty( $image_map[ $offer_id ] ) ) {
            return esc_url_raw( (string) $image_map[ $offer_id ] );
        }

        foreach ( array( 'image_url', 'image', 'thumbnail', 'thumbnail_url' ) as $field ) {
            if ( ! empty( $synced_offer[ $field ] ) ) {
                return esc_url_raw( (string) $synced_offer[ $field ] );
            }
        }

        return $this->build_placeholder_image( (string) ( $synced_offer['name'] ?? $offer_id ) );
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

        return (string) ( $banner_data['cta_text'] ?? '' );
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
            'allowed_countries' => $this->sanitize_country_codes( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() ),
            'blocked_countries' => $this->sanitize_country_codes( isset( $override['blocked_countries'] ) ? $override['blocked_countries'] : array() ),
            'custom_cta_text'   => ! empty( $override['custom_cta_text'] ) ? sanitize_text_field( (string) $override['custom_cta_text'] ) : '',
            'label_override'    => ! empty( $override['label_override'] ) ? sanitize_text_field( (string) $override['label_override'] ) : '',
            'notes'             => ! empty( $override['notes'] ) ? sanitize_textarea_field( (string) $override['notes'] ) : '',
        );
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
