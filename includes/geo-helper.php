<?php
/**
 * Geo helper utilities for the TMW CR Slot Sidebar Banner plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Geo_Helper {
    /**
     * Attempts to determine the visitor country code.
     *
     * @return string
     */
    public static function get_country_code() {
        $country = apply_filters( 'tmw_cr_slot_banner_pre_country_code', '' );

        if ( ! empty( $country ) ) {
            return strtoupper( $country );
        }

        if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
            return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
        }

        if ( ! empty( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) {
            return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) );
        }

        $ip = self::get_user_ip();

        if ( empty( $ip ) ) {
            return apply_filters( 'tmw_cr_slot_banner_country_code', '' );
        }

        $cache_key = 'tmw_cr_slot_geo_' . md5( $ip );
        $country   = get_transient( $cache_key );

        if ( false === $country ) {
            $country = self::query_remote_country( $ip );

            if ( ! empty( $country ) ) {
                set_transient( $cache_key, $country, HOUR_IN_SECONDS * 12 );
            }
        }

        return apply_filters( 'tmw_cr_slot_banner_country_code', strtoupper( $country ) );
    }

    /**
     * Determines the visitor IP address using common server variables.
     *
     * @return string
     */
    protected static function get_user_ip() {
        $keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CF_CONNECTING_IP',
            'REMOTE_ADDR',
        );

        foreach ( $keys as $key ) {
            if ( empty( $_SERVER[ $key ] ) ) {
                continue;
            }

            $ip_list = explode( ',', wp_unslash( $_SERVER[ $key ] ) );

            foreach ( $ip_list as $ip ) {
                $ip = trim( $ip );

                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Performs a remote lookup for the visitor country.
     *
     * @param string $ip Visitor IP address.
     *
     * @return string
     */
    protected static function query_remote_country( $ip ) {
        $url     = sprintf( 'https://ipapi.co/%s/country/', rawurlencode( $ip ) );
        $request = wp_remote_get( $url, array( 'timeout' => 2 ) );
        $country = '';

        if ( is_wp_error( $request ) ) {
            return $country;
        }

        if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
            return $country;
        }

        $body = trim( wp_remote_retrieve_body( $request ) );

        if ( 2 === strlen( $body ) ) {
            $country = sanitize_text_field( $body );
        }

        return $country;
    }
}
