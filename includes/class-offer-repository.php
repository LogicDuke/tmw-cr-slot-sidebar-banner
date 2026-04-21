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

    /** @var string */
    protected $stats_option_key;

    /** @var string */
    protected $stats_meta_option_key;

    /**
     * @param string $offers_option_key     Option key for synced offers.
     * @param string $meta_option_key       Option key for sync meta.
     * @param string $overrides_option_key  Option key for offer overrides.
     */
    public function __construct( $offers_option_key, $meta_option_key, $overrides_option_key = 'tmw_cr_slot_banner_offer_overrides', $stats_option_key = 'tmw_cr_slot_banner_offer_stats', $stats_meta_option_key = 'tmw_cr_slot_banner_offer_stats_meta' ) {
        $this->offers_option_key    = $offers_option_key;
        $this->meta_option_key      = $meta_option_key;
        $this->overrides_option_key = $overrides_option_key;
        $this->stats_option_key     = $stats_option_key;
        $this->stats_meta_option_key = $stats_meta_option_key;
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

            foreach ( $supported as $field_key => $values ) {
                $field_values = isset( $meta[ $field_key ] ) ? (array) $meta[ $field_key ] : array();
                foreach ( $field_values as $value ) {
                    $value = (string) $value;
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
        $tag         = strtolower( trim( (string) $query['tag'] ) );
        $vertical    = strtolower( trim( (string) $query['vertical'] ) );
        $featured    = strtolower( trim( (string) $query['featured'] ) );
        $approval    = strtolower( trim( (string) $query['approval_required'] ) );
        $payout_type = strtolower( trim( (string) $query['payout_type'] ) );
        $performs_in = strtoupper( trim( (string) $query['performs_in'] ) );
        $optimized   = strtolower( trim( (string) $query['optimized_for'] ) );
        $accepted_country = strtoupper( trim( (string) $query['accepted_country'] ) );
        $niche       = strtolower( trim( (string) $query['niche'] ) );
        $promotion_method = strtolower( trim( (string) $query['promotion_method'] ) );
        $image       = strtolower( trim( (string) $query['image_status'] ) );
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

            $offer_status = strtolower( (string) ( $offer['status'] ?? '' ) );
            if ( '' !== $status && $status !== $offer_status ) {
                continue;
            }
            $offer_meta = $this->get_offer_dashboard_metadata( $offer_id, $offer );

            if ( '' !== $tag && ! $this->value_in_filter_set( $tag, (array) ( $offer_meta['tag'] ?? array() ) ) ) {
                continue;
            }

            if ( '' !== $vertical && ! $this->value_in_filter_set( $vertical, (array) ( $offer_meta['vertical'] ?? array() ) ) ) {
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
            if ( '' !== $performs_in && ! $this->value_in_filter_set( $performs_in, (array) ( $offer_meta['performs_in'] ?? array() ), true ) ) {
                continue;
            }
            if ( '' !== $optimized && ! $this->value_in_filter_set( $optimized, (array) ( $offer_meta['optimized_for'] ?? array() ) ) ) {
                continue;
            }
            if ( '' !== $accepted_country && ! $this->value_in_filter_set( $accepted_country, (array) ( $offer_meta['accepted_country'] ?? array() ), true ) ) {
                continue;
            }
            if ( '' !== $niche && ! $this->value_in_filter_set( $niche, (array) ( $offer_meta['niche'] ?? array() ) ) ) {
                continue;
            }
            if ( '' !== $promotion_method && ! $this->value_in_filter_set( $promotion_method, (array) ( $offer_meta['promotion_method'] ?? array() ) ) ) {
                continue;
            }

            $image_status = $this->get_image_status_for_offer( $offer_id, $settings, $legacy_catalog );
            if ( '' !== $image && $image !== $image_status ) {
                continue;
            }

            $offer['dashboard_metadata']   = $offer_meta;
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
            'image'    => $this->get_effective_image( $offer_id, $settings, $banner_data, $synced_offer, $override, $legacy_catalog ),
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

        return (string) ( $banner_data['cta_text'] ?? '' );
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
                    'payout_type'  => (string) ( $row['payout_type'] ?? ( $offer['payout_type'] ?? '' ) ),
                    'status'       => (string) ( $offer['status'] ?? '' ),
                );
            }

            $grouped[ $offer_id ]['clicks'] += (int) ( $row['clicks'] ?? 0 );
            $grouped[ $offer_id ]['conversions'] += (float) ( $row['conversions'] ?? 0 );
            $grouped[ $offer_id ]['payout'] += (float) ( $row['payout'] ?? 0 );
        }

        foreach ( $grouped as $offer_id => $row ) {
            if ( '' !== $payout_type_filter && $payout_type_filter !== sanitize_key( (string) ( $row['payout_type'] ?? '' ) ) ) {
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
            'allowed_countries' => $this->sanitize_country_codes( isset( $override['allowed_countries'] ) ? $override['allowed_countries'] : array() ),
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

        $status = sanitize_key( (string) ( $offer['status'] ?? '' ) );
        $vertical = sanitize_text_field( (string) ( $offer['vertical'] ?? '' ) );
        if ( '' === $vertical && ! empty( $override['dashboard_vertical'] ) ) {
            $vertical = sanitize_text_field( (string) $override['dashboard_vertical'] );
        }

        return array(
            'tag' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['tags'] ) ? $offer['tags'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_tags'] ) ? $override['dashboard_tags'] : array() )
            ),
            'vertical' => '' !== $vertical ? array( $vertical ) : array(),
            'payout_type' => $this->sanitize_list_values( isset( $offer['payout_type'] ) ? $offer['payout_type'] : '' ),
            'performs_in' => $this->merge_preferred_values(
                $this->sanitize_country_codes( isset( $offer['performs_in'] ) ? $offer['performs_in'] : array() ),
                $this->sanitize_country_codes( isset( $override['dashboard_performs_in'] ) ? $override['dashboard_performs_in'] : array() )
            ),
            'optimized_for' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['optimized_for'] ) ? $offer['optimized_for'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_optimized_for'] ) ? $override['dashboard_optimized_for'] : array() )
            ),
            'accepted_country' => $this->merge_preferred_values(
                $this->sanitize_country_codes( isset( $offer['accepted_countries'] ) ? $offer['accepted_countries'] : array() ),
                $this->sanitize_country_codes( isset( $override['dashboard_accepted_countries'] ) ? $override['dashboard_accepted_countries'] : array() )
            ),
            'niche' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['niche'] ) ? $offer['niche'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_niche'] ) ? $override['dashboard_niche'] : array() )
            ),
            'status' => '' !== $status ? array( $status ) : array(),
            'promotion_method' => $this->merge_preferred_values(
                $this->sanitize_list_values( isset( $offer['promotion_method'] ) ? $offer['promotion_method'] : array() ),
                $this->sanitize_list_values( isset( $override['dashboard_promotion_method'] ) ? $override['dashboard_promotion_method'] : array() )
            ),
        );
    }

    /**
     * @param string                 $needle Filter value.
     * @param array<int,string>      $values Values.
     * @param bool                   $uppercase Normalize to uppercase.
     *
     * @return bool
     */
    protected function value_in_filter_set( $needle, $values, $uppercase = false ) {
        foreach ( $values as $value ) {
            $hay = $uppercase ? strtoupper( (string) $value ) : strtolower( (string) $value );
            if ( $needle === $hay ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,string> $primary First source.
     * @param array<int,string> $fallback Fallback source.
     *
     * @return array<int,string>
     */
    protected function merge_preferred_values( $primary, $fallback ) {
        $values = ! empty( $primary ) ? $primary : $fallback;

        return array_values( array_unique( array_filter( array_map( 'strval', (array) $values ) ) ) );
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
