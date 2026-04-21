<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'TMW_CR_SLOT_BANNER_PATH', dirname( __DIR__ ) . '/' );
define( 'TMW_CR_SLOT_BANNER_VERSION', '1.8.1-test' );

$GLOBALS['tmw_test_options']      = array();
$GLOBALS['tmw_test_transients']   = array();
$GLOBALS['tmw_test_remote_get']   = null;
$GLOBALS['tmw_test_last_redirect'] = '';
$GLOBALS['tmw_test_nonce_ok']     = true;
$GLOBALS['tmw_test_cron_events']  = array();

class WP_Error {
    protected $code;
    protected $message;

    public function __construct( $code = '', $message = '' ) {
        $this->code    = $code;
        $this->message = $message;
    }

    public function get_error_message() {
        return $this->message;
    }
}

function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function esc_attr__( $text ) { return $text; }
function esc_html_e( $text ) { echo $text; }
function esc_attr_e( $text ) { echo $text; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_textarea_field( $text ) { return trim( (string) $text ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function esc_url_raw( $url ) { return trim( (string) $url ); }
function esc_url( $url ) { return (string) $url; }
function esc_attr( $text ) { return (string) $text; }
function esc_html( $text ) { return (string) $text; }
function esc_textarea( $text ) { return (string) $text; }
function checked( $checked, $current = true ) { if ( (bool) $checked === (bool) $current ) { echo 'checked'; } }
function selected( $selected, $current = true, $display = true ) {
    $result = (string) $selected === (string) $current ? 'selected' : '';
    if ( $display ) { echo $result; }
    return $result;
}
function current_user_can() { return true; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function wp_unslash( $value ) { return $value; }
function wp_nonce_field() { echo '<input type="hidden" value="1" />'; }
function check_admin_referer() { if ( empty( $GLOBALS['tmw_test_nonce_ok'] ) ) { throw new Exception( 'bad nonce' ); } }
function settings_fields() { echo '<input type="hidden" value="settings" />'; }
function submit_button( $text = 'Submit', $type = 'primary', $name = 'submit', $wrap = true ) { echo '<button type="submit" name="' . $name . '" class="' . $type . '">' . $text . '</button>'; }
function register_setting() {}
function add_action() {}
function add_filter() {}
function add_options_page() {}
function is_admin() { return true; }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, (array) $atts ); }
function add_shortcode() {}
function wp_next_scheduled( $hook ) {
    foreach ( $GLOBALS['tmw_test_cron_events'] as $event ) {
        if ( $event['hook'] === $hook ) {
            return $event['timestamp'];
        }
    }
    return false;
}
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
    foreach ( $GLOBALS['tmw_test_cron_events'] as $event ) {
        if ( $event['hook'] === $hook ) {
            return true;
        }
    }
    $GLOBALS['tmw_test_cron_events'][] = array(
        'timestamp' => (int) $timestamp,
        'recurrence' => (string) $recurrence,
        'hook' => (string) $hook,
    );
    return true;
}
function wp_clear_scheduled_hook( $hook ) {
    $GLOBALS['tmw_test_cron_events'] = array_values(
        array_filter(
            $GLOBALS['tmw_test_cron_events'],
            static function ( $event ) use ( $hook ) {
                return $event['hook'] !== $hook;
            }
        )
    );
    return true;
}
function do_action() {}
function did_action() { return 1; }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function plugins_url( $path, $file ) { unset( $file ); return 'https://example.test/plugins/tmw/' . ltrim( $path, '/' ); }
function apply_filters( $tag, $value ) { unset( $tag ); return $value; }
function wp_safe_redirect( $url ) { $GLOBALS['tmw_test_last_redirect'] = $url; }
function wp_die( $message ) { throw new Exception( $message ); }
function add_query_arg( $args, $url ) {
    $query = array();
    foreach ( (array) $args as $key => $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                $query[] = urlencode( (string) $key ) . '[]=' . urlencode( (string) $item );
            }
        } else {
            $query[] = urlencode( (string) $key ) . '=' . urlencode( (string) $value );
        }
    }
    return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $query );
}
function wp_parse_args( $args, $defaults ) { return array_merge( (array) $defaults, (array) $args ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_remote_get( $url, $args = array() ) {
    $handler = $GLOBALS['tmw_test_remote_get'];
    if ( is_callable( $handler ) ) {
        return $handler( $url, $args );
    }
    return new WP_Error( 'no_handler', 'No test handler configured.' );
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ) { return isset( $response['response']['code'] ) ? $response['response']['code'] : 0; }
function wp_remote_retrieve_body( $response ) { return isset( $response['body'] ) ? $response['body'] : ''; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['tmw_test_options'] ) ? $GLOBALS['tmw_test_options'][ $key ] : $default; }
function update_option( $key, $value ) { $GLOBALS['tmw_test_options'][ $key ] = $value; return true; }
function get_transient( $key ) { return array_key_exists( $key, $GLOBALS['tmw_test_transients'] ) ? $GLOBALS['tmw_test_transients'][ $key ] : false; }
function set_transient( $key, $value ) { $GLOBALS['tmw_test_transients'][ $key ] = $value; return true; }
if ( ! function_exists( 'array_is_list' ) ) {
    function array_is_list( $array ) {
        if ( ! is_array( $array ) ) {
            return false;
        }
        return array_values( $array ) === $array;
    }
}
function trim_file( $path ) { return preg_replace( '#^' . preg_quote( dirname( __DIR__ ) . '/', '#' ) . '#', '', $path ); }
function tmw_assert_true( $condition, $message ) { if ( ! $condition ) { throw new Exception( $message ); } }
function tmw_assert_same( $expected, $actual, $message ) { if ( $expected !== $actual ) { throw new Exception( $message . ' Expected: ' . var_export( $expected, true ) . ' Actual: ' . var_export( $actual, true ) ); } }
function tmw_assert_contains( $needle, $haystack, $message ) { if ( false === strpos( (string) $haystack, (string) $needle ) ) { throw new Exception( $message . ' Missing: ' . $needle ); } }

require_once dirname( __DIR__ ) . '/includes/class-offer-repository.php';
require_once dirname( __DIR__ ) . '/includes/geo-helper.php';
require_once dirname( __DIR__ ) . '/includes/class-cr-api-client.php';
require_once dirname( __DIR__ ) . '/includes/class-offer-sync-service.php';
require_once dirname( __DIR__ ) . '/includes/class-stats-sync-service.php';
require_once dirname( __DIR__ ) . '/admin/admin-page.php';
