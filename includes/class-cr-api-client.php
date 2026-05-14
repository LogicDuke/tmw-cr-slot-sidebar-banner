<?php
/**
 * CrackRevenue API client.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_CR_API_Client {
    /** @var string */
    const ENDPOINT = 'https://gateway.crakrevenue.com/affiliate';

    /** @var string */
    protected $api_key;

    /**
     * @param string $api_key API key.
     */
    public function __construct( $api_key ) {
        $this->api_key = trim( (string) $api_key );
    }

    /**
     * @return bool
     */
    public function has_api_key() {
        return '' !== $this->api_key;
    }

    /**
     * @return string
     */
    public function get_masked_api_key() {
        if ( '' === $this->api_key ) {
            return '';
        }

        $length = strlen( $this->api_key );

        if ( $length <= 4 ) {
            return str_repeat( '*', $length );
        }

        return str_repeat( '*', $length - 4 ) . substr( $this->api_key, -4 );
    }

    /**
     * Sends a small request to validate credentials.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function test_connection() {
        return $this->find_all_offers(
            array(
                'fields' => array( 'id', 'name' ),
                'sort'   => array( 'id' => 'asc' ),
                'limit'  => 1,
                'page'   => 1,
            )
        );
    }

    /**
     * Calls Affiliate_Offer.findAll.
     *
     * @param array<string,mixed> $args Request arguments.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function find_all_offers( $args = array() ) {
        if ( ! $this->has_api_key() ) {
            return new WP_Error( 'tmw_cr_slot_missing_api_key', __( 'CrakRevenue API key is missing.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        $defaults = array(
            'fields'  => array(),
            'filters' => array(),
            'sort'    => array( 'id' => 'asc' ),
            'limit'   => 100,
            'page'    => 1,
        );

        $args = wp_parse_args( $args, $defaults );

        $query = array(
            'Target'  => 'Affiliate_Offer',
            'Method'  => 'findAll',
            'api_key' => $this->api_key,
            'limit'   => max( 1, (int) $args['limit'] ),
            'page'    => max( 1, (int) $args['page'] ),
        );

        foreach ( (array) $args['fields'] as $field ) {
            $query['fields'][] = (string) $field;
        }

        foreach ( (array) $args['sort'] as $field => $direction ) {
            $query[ sprintf( 'sort[%s]', $field ) ] = strtolower( (string) $direction );
        }

        foreach ( (array) $args['filters'] as $field => $value ) {
            $query[ sprintf( 'filters[%s]', $field ) ] = $value;
        }

        return $this->request( $query );
    }

    /**
     * Calls Affiliate_Report.getStats.
     *
     * @param array<string,mixed> $args Request arguments.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function get_offer_stats( $args = array() ) {
        if ( ! $this->has_api_key() ) {
            return new WP_Error( 'tmw_cr_slot_missing_api_key', __( 'CrakRevenue API key is missing.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        $defaults = array(
            'fields'      => array(),
            'groups'      => array(),
            'filters'     => array(),
            'sort'        => array(),
            'limit'       => 100,
            'page'        => 1,
            'data_start'  => '',
            'data_end'    => '',
            'totals'      => '',
            'hour_offset' => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $query = array(
            'Target'  => 'Affiliate_Report',
            'Method'  => 'getStats',
            'api_key' => $this->api_key,
            'limit'   => max( 1, (int) $args['limit'] ),
            'page'    => max( 1, (int) $args['page'] ),
        );

        foreach ( (array) $args['fields'] as $field ) {
            $query['fields'][] = (string) $field;
        }

        foreach ( (array) $args['groups'] as $group ) {
            $query['groups'][] = (string) $group;
        }

        foreach ( (array) $args['filters'] as $field => $value ) {
            $query[ sprintf( 'filters[%s]', $field ) ] = $value;
        }

        foreach ( (array) $args['sort'] as $field => $direction ) {
            $query[ sprintf( 'sort[%s]', $field ) ] = strtolower( (string) $direction );
        }

        if ( '' !== (string) $args['data_start'] ) {
            $query['data_start'] = sanitize_text_field( (string) $args['data_start'] );
        }

        if ( '' !== (string) $args['data_end'] ) {
            $query['data_end'] = sanitize_text_field( (string) $args['data_end'] );
        }

        if ( '' !== (string) $args['totals'] ) {
            $query['totals'] = sanitize_text_field( (string) $args['totals'] );
        }

        if ( '' !== (string) $args['hour_offset'] ) {
            $query['hour_offset'] = sanitize_text_field( (string) $args['hour_offset'] );
        }

        return $this->request( $query );
    }


    public function audit_request( $target, $method, $params = array() ) {
        if ( ! $this->has_api_key() ) {
            return new WP_Error( 'tmw_cr_slot_missing_api_key', __( 'CrakRevenue API key is missing.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        $target = trim( (string) $target );
        $method = trim( (string) $method );

        if ( '' === $target || '' === $method ) {
            return new WP_Error( 'tmw_cr_slot_audit_invalid_request', __( '[TMW-CR-AUDIT] Target and Method are required.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        $query = array(
            'Target'  => $target,
            'Method'  => $method,
            'api_key' => $this->api_key,
        );

        foreach ( (array) $params as $key => $value ) {
            if ( in_array( (string) $key, array( 'Target', 'Method', 'api_key' ), true ) ) {
                continue;
            }
            $query[ $key ] = $value;
        }

        return $this->request( $query );
    }

    public static function redact_url_for_log( $url ) {
        return (string) preg_replace( '/(api_key=)([^&]+)/i', '$1[redacted]', (string) $url );
    }

    /**
     * @param array<string,mixed> $query Query arguments.
     *
     * @return array<string,mixed>|WP_Error
     */
    protected function request( $query ) {
        $url      = add_query_arg( $query, self::ENDPOINT );
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'tmw_cr_slot_api_unavailable', __( '[TMW-CR-API] CrakRevenue API request failed.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( 200 !== $status ) {
            return new WP_Error( 'tmw_cr_slot_api_http_error', sprintf( __( '[TMW-CR-API] CrakRevenue API returned HTTP %d.', 'tmw-cr-slot-sidebar-banner' ), $status ) );
        }

        if ( ! is_array( $data ) ) {
            return new WP_Error( 'tmw_cr_slot_api_invalid_json', __( '[TMW-CR-API] CrakRevenue API returned an unexpected response.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        if ( isset( $data['error'] ) && ! empty( $data['error'] ) ) {
            return new WP_Error( 'tmw_cr_slot_api_error', sanitize_text_field( (string) $data['error'] ) );
        }

        return $data;
    }
}
