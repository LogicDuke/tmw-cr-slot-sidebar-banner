<?php
/**
 * Plugin Name: TMW CR Offer Sidebar Banner
 * Plugin URI: https://themilisofialtd.com/
 * Description: Displays a geo-targeted CrackRevenue offer recommendation banner with an animated offer selector in sidebar areas via shortcode or template tag.
 * Version: 1.9.6
 * Author: The Milisofia LTD
 * Author URI: https://themilisofialtd.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tmw-cr-slot-sidebar-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TMW_CR_SLOT_BANNER_VERSION', '1.9.6' );
define( 'TMW_CR_SLOT_BANNER_PATH', plugin_dir_path( __FILE__ ) );
define( 'TMW_CR_SLOT_BANNER_URL', plugin_dir_url( __FILE__ ) );

require_once TMW_CR_SLOT_BANNER_PATH . 'includes/geo-helper.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-offer-repository.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-cr-api-client.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-cr-api-inspector.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-offer-sync-service.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-stats-sync-service.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'admin/admin-page.php';

/**
 * Primary plugin bootstrap class.
 */
class TMW_CR_Slot_Sidebar_Banner {
    const DEFAULT_HEADLINE = 'Discover Adult Offers';
    const DEFAULT_SUBHEADLINE = 'Cam, Dating, AI & More';
    const DEFAULT_SPIN_BUTTON_TEXT = 'Reveal My Offer';
    const DEFAULT_CTA_TEXT = 'View Offer';
    /**
     * Option key used to persist settings.
     *
     * @var string
     */
    const OPTION_KEY = 'tmw_cr_slot_banner_settings';

    /**
     * Stored synced offers option.
     *
     * @var string
     */
    const OFFERS_OPTION_KEY = 'tmw_cr_slot_banner_synced_offers';

    /**
     * Stored sync meta option.
     *
     * @var string
     */
    const SYNC_META_OPTION_KEY = 'tmw_cr_slot_banner_sync_meta';

    /**
     * Stored offer overrides option.
     *
     * @var string
     */
    const OFFER_OVERRIDES_OPTION_KEY = 'tmw_cr_slot_banner_offer_overrides';

    /**
     * Stored offer stats option.
     *
     * @var string
     */
    const OFFER_STATS_OPTION_KEY = 'tmw_cr_slot_banner_offer_stats';

    /**
     * Stored offer stats meta option.
     *
     * @var string
     */
    const OFFER_STATS_META_OPTION_KEY = 'tmw_cr_slot_banner_offer_stats_meta';
    const STATS_SYNC_CRON_HOOK = TMW_CR_Slot_Stats_Sync_Service::CRON_HOOK;

    /**
     * Single sidebar slot identifier.
     *
     * @var string
     */
    const DEFAULT_SLOT_KEY = 'sidebar';

    /**
     * Offer repository.
     *
     * @var TMW_CR_Slot_Offer_Repository
     */
    protected $offer_repository;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->offer_repository = new TMW_CR_Slot_Offer_Repository( self::OFFERS_OPTION_KEY, self::SYNC_META_OPTION_KEY, self::OFFER_OVERRIDES_OPTION_KEY, self::OFFER_STATS_OPTION_KEY, self::OFFER_STATS_META_OPTION_KEY );

        add_action( 'init', array( $this, 'register_shortcode' ) );
        add_action( 'init', array( $this, 'maybe_configure_stats_sync_schedule' ), 20 );
        add_action( self::STATS_SYNC_CRON_HOOK, array( $this, 'run_scheduled_stats_sync' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'wp_footer', array( $this, 'maybe_enqueue_assets' ), 1 );
        add_filter( 'widget_text', 'do_shortcode' );

        if ( is_admin() ) {
            new TMW_CR_Slot_Admin_Page( self::OPTION_KEY, $this->offer_repository, self::DEFAULT_SLOT_KEY );
        }
    }

    /**
     * Registers the shortcode hook.
     *
     * @return void
     */
    public function register_shortcode() {
        add_shortcode( 'tmw_cr_slot_banner', array( $this, 'render_shortcode' ) );
    }

    /**
     * Registers plugin assets without enqueuing them yet.
     *
     * @return void
     */
    public function register_assets() {
        $css_version = TMW_CR_SLOT_BANNER_VERSION;
        $js_version  = TMW_CR_SLOT_BANNER_VERSION;
        $css_path    = TMW_CR_SLOT_BANNER_PATH . 'assets/css/slot-banner.css';
        $js_path     = TMW_CR_SLOT_BANNER_PATH . 'assets/js/slot-banner.js';
        $css_mtime   = file_exists( $css_path ) ? filemtime( $css_path ) : false;
        $js_mtime    = file_exists( $js_path ) ? filemtime( $js_path ) : false;
        if ( false !== $css_mtime ) {
            $css_version .= '-' . (string) $css_mtime;
        }
        if ( false !== $js_mtime ) {
            $js_version .= '-' . (string) $js_mtime;
        }

        wp_register_style(
            'tmw-cr-slot-banner',
            self::asset_url( 'assets/css/slot-banner.css' ),
            array(),
            $css_version
        );

        wp_register_script(
            'tmw-cr-slot-banner',
            self::asset_url( 'assets/js/slot-banner.js' ),
            array(),
            $js_version,
            true
        );
    }

    /**
     * Enqueues assets if a banner was rendered during the request.
     *
     * @return void
     */
    public function maybe_enqueue_assets() {
        if ( ! did_action( 'tmw_cr_slot_banner_rendered' ) ) {
            return;
        }

        wp_enqueue_style( 'tmw-cr-slot-banner' );
        wp_enqueue_script( 'tmw-cr-slot-banner' );
    }

    /**
     * Returns plugin settings merged with defaults.
     *
     * @return array<string,mixed>
     */
    public static function get_settings() {
        $defaults = array(
            'headline'               => self::DEFAULT_HEADLINE,
            'subheadline'            => self::DEFAULT_SUBHEADLINE,
            'spin_button_text'       => self::DEFAULT_SPIN_BUTTON_TEXT,
            'cta_text'               => self::DEFAULT_CTA_TEXT,
            'cta_url'                => 'https://www.crackrevenue.com/',
            'default_image_url'      => 'https://via.placeholder.com/320x480.png?text=Upload+Slot+Banner',
            'open_in_new_tab'        => true,
            'subid_param'            => 'subid',
            'subid_value'            => 'tmw-slot-sidebar',
            'country_overrides_raw'  => '',
            'cr_api_key'             => '',
            'slot_offer_ids'         => array(),
            'slot_offer_priority'    => array(),
            'offer_image_overrides'  => array(),
            'rotation_mode'          => 'manual',
            'optimization_enabled'   => 1,
            'minimum_clicks_threshold' => 10,
            'minimum_conversions_threshold' => 1,
            'minimum_payout_threshold' => 0,
            'exclude_zero_click_offers' => 0,
            'exclude_zero_conversion_offers' => 0,
            'country_decay_enabled' => 1,
            'country_weight'        => 0.7,
            'global_weight'         => 0.3,
            'decay_min_country_clicks' => 10,
            'fallback_to_global_when_low_sample' => 1,
            'auto_sync_enabled'     => 0,
            'auto_sync_frequency'   => 'daily',
            'stats_sync_range'       => '30d',
            'optimization_notes'    => '',
            'allowed_offer_types'   => array( 'pps' ),
            'enforce_skipped_offers_exclusion' => 0,
        );

        $settings = get_option( self::OPTION_KEY, array() );
        $settings = is_array( $settings ) ? $settings : array();

        $settings = wp_parse_args( $settings, $defaults );
        if ( isset( $settings['spin_button_text'] ) && 'Show Best Offer' === trim( (string) $settings['spin_button_text'] ) ) {
            $settings['spin_button_text'] = self::DEFAULT_SPIN_BUTTON_TEXT;
        }
        $settings['slot_offer_ids']        = is_array( $settings['slot_offer_ids'] ) ? array_values( $settings['slot_offer_ids'] ) : array();
        $settings['slot_offer_priority']   = is_array( $settings['slot_offer_priority'] ) ? $settings['slot_offer_priority'] : array();
        $settings['offer_image_overrides'] = is_array( $settings['offer_image_overrides'] ) ? $settings['offer_image_overrides'] : array();
        $settings['rotation_mode']         = sanitize_key( (string) $settings['rotation_mode'] );
        if ( ! in_array( $settings['rotation_mode'], array( 'manual', 'payout_desc', 'conversions_desc', 'epc_desc', 'country_epc_desc', 'hybrid_score', 'safe_hybrid_score' ), true ) ) {
            $settings['rotation_mode'] = 'manual';
        }
        $settings['optimization_enabled'] = ! empty( $settings['optimization_enabled'] ) ? 1 : 0;
        $settings['minimum_clicks_threshold'] = max( 0, (int) $settings['minimum_clicks_threshold'] );
        $settings['minimum_conversions_threshold'] = max( 0, (float) $settings['minimum_conversions_threshold'] );
        $settings['minimum_payout_threshold'] = max( 0, (float) $settings['minimum_payout_threshold'] );
        $settings['exclude_zero_click_offers'] = ! empty( $settings['exclude_zero_click_offers'] ) ? 1 : 0;
        $settings['exclude_zero_conversion_offers'] = ! empty( $settings['exclude_zero_conversion_offers'] ) ? 1 : 0;
        $settings['country_decay_enabled'] = ! empty( $settings['country_decay_enabled'] ) ? 1 : 0;
        $settings['country_weight'] = max( 0, min( 1, (float) $settings['country_weight'] ) );
        $settings['global_weight'] = max( 0, min( 1, (float) $settings['global_weight'] ) );
        if ( ( $settings['country_weight'] + $settings['global_weight'] ) <= 0 ) {
            $settings['country_weight'] = 0.7;
            $settings['global_weight']  = 0.3;
        }
        $settings['decay_min_country_clicks'] = max( 1, (int) $settings['decay_min_country_clicks'] );
        $settings['fallback_to_global_when_low_sample'] = ! empty( $settings['fallback_to_global_when_low_sample'] ) ? 1 : 0;
        $settings['auto_sync_enabled'] = ! empty( $settings['auto_sync_enabled'] ) ? 1 : 0;
        $settings['auto_sync_frequency'] = sanitize_key( (string) $settings['auto_sync_frequency'] );
        if ( ! in_array( $settings['auto_sync_frequency'], array( 'hourly', 'twicedaily', 'daily' ), true ) ) {
            $settings['auto_sync_frequency'] = 'daily';
        }
        $settings['stats_sync_range']      = sanitize_key( (string) $settings['stats_sync_range'] );
        if ( ! in_array( $settings['stats_sync_range'], array( '7d', '30d', '90d' ), true ) ) {
            $settings['stats_sync_range'] = '30d';
        }
        $settings['optimization_notes'] = sanitize_textarea_field( (string) $settings['optimization_notes'] );
        $settings['enforce_skipped_offers_exclusion'] = ! empty( $settings['enforce_skipped_offers_exclusion'] ) ? 1 : 0;
        $settings['allowed_offer_types'] = TMW_CR_Slot_Offer_Repository::sanitize_allowed_offer_types(
            isset( $settings['allowed_offer_types'] ) ? $settings['allowed_offer_types'] : array()
        );

        return $settings;
    }

    /**
     * Parses the country override text string into a structured array.
     *
     * Each line uses the format CC|Image URL|CTA URL|CTA Text|Headline.
     *
     * @param string $raw Raw textarea content.
     *
     * @return array<string,array<string,string>>
     */
    public static function parse_country_overrides( $raw ) {
        $overrides = array();

        if ( empty( $raw ) ) {
            return $overrides;
        }

        $lines = preg_split( '/\r?\n/', $raw );

        foreach ( $lines as $line ) {
            $line = trim( (string) $line );

            if ( '' === $line ) {
                continue;
            }

            $parts = array_map( 'trim', explode( '|', $line ) );
            $parts = array_pad( $parts, 5, '' );

            $country_code = strtoupper( $parts[0] );

            if ( 2 !== strlen( $country_code ) ) {
                continue;
            }

            $overrides[ $country_code ] = array(
                'image_url' => esc_url_raw( $parts[1] ),
                'cta_url'   => esc_url_raw( $parts[2] ),
                'cta_text'  => sanitize_text_field( $parts[3] ),
                'headline'  => sanitize_text_field( $parts[4] ),
            );
        }

        return $overrides;
    }

    /**
     * Renders the banner for shortcode output.
     *
     * @param array<string,mixed> $atts Shortcode attributes.
     * @param string|null         $content Shortcode content.
     *
     * @return string
     */
    public function render_shortcode( $atts, $content = null ) {
        unset( $content );

        $atts = shortcode_atts(
            array(
                'class' => '',
            ),
            $atts,
            'tmw_cr_slot_banner'
        );

        $settings  = self::get_settings();
        $overrides = self::parse_country_overrides( $settings['country_overrides_raw'] );
        $country   = TMW_CR_Slot_Geo_Helper::get_country_code();

        $banner_data = $this->build_banner_data( $settings, $overrides, $country );
        $slot_data   = $this->build_slot_data( $settings, $banner_data, $country );
        error_log(
            sprintf(
                '[TMW-BANNER-TEXT] headline_empty=%s subheadline_empty=%s cta_empty=%s offer_cta_empty=%s',
                empty( trim( (string) ( $settings['headline'] ?? '' ) ) ) ? 'yes' : 'no',
                empty( trim( (string) ( $settings['subheadline'] ?? '' ) ) ) ? 'yes' : 'no',
                empty( trim( (string) ( $settings['cta_text'] ?? '' ) ) ) ? 'yes' : 'no',
                ! empty( $slot_data['has_empty_offer_cta'] ) ? 'yes' : 'no'
            )
        );

        do_action( 'tmw_cr_slot_banner_rendered' );

        $classes = array( 'tmw-cr-slot-banner' );

        if ( ! empty( $atts['class'] ) ) {
            $classes[] = sanitize_html_class( (string) $atts['class'] );
        }

        $classes[] = ! empty( $slot_data['offers'] ) ? 'tmw-cr-slot-banner--has-offers' : 'tmw-cr-slot-banner--no-offers';

        $cta_target = ' target="_blank" rel="noopener noreferrer nofollow sponsored"';

        ob_start();
        ?>
        <aside
            class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
            data-country="<?php echo esc_attr( $banner_data['country'] ); ?>"
            data-subid-param="<?php echo esc_attr( $settings['subid_param'] ); ?>"
            data-subid-value="<?php echo esc_attr( $settings['subid_value'] ); ?>"
            data-default-cta-text="<?php echo esc_attr( $banner_data['cta_text'] ); ?>"
            data-default-cta-url="<?php echo esc_url( $banner_data['cta_url'] ); ?>"
            data-slot-offers="<?php echo esc_attr( wp_json_encode( $slot_data['offers'] ) ); ?>"
            data-debug-enabled="<?php echo esc_attr( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '1' : '0' ); ?>"
        >
            <header class="tmw-cr-slot-banner__header">
                <h3 class="tmw-cr-slot-banner__headline"><?php echo esc_html( $banner_data['headline'] ); ?></h3>
                <p class="tmw-cr-slot-banner__subheadline"><?php echo esc_html( $banner_data['subheadline'] ); ?></p>
            </header>

            <div class="tmw-cr-slot-banner__machine" role="group" aria-label="<?php esc_attr_e( 'CrackRevenue offer banner', 'tmw-cr-slot-sidebar-banner' ); ?>">
                <div id="container" class="tmw-slot-container" aria-hidden="true">
                    <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                        <div class="outer-col" data-reel-index="<?php echo esc_attr( $i ); ?>">
                            <div class="col"></div>
                        </div>
                    <?php endfor; ?>
                </div>
                <button type="button" id="spin" class="tmw-cr-slot-banner__spin"<?php echo empty( $slot_data['offers'] ) ? ' disabled' : ''; ?>>
                    <span class="tmw-cr-slot-banner__spin-label"><?php echo esc_html( $banner_data['spin_button_text'] ); ?></span>
                </button>
            </div>

            <div class="tmw-cr-slot-banner__footer">
                <p class="tmw-cr-slot-banner__result">
                    <span class="tmw-cr-slot-banner__result-label"><?php esc_html_e( 'Top pick:', 'tmw-cr-slot-sidebar-banner' ); ?></span>
                    <span class="tmw-cr-slot-banner__offer-name"><?php echo esc_html( $slot_data['initial_offer_name'] ); ?></span>
                </p>
                <p class="tmw-cr-slot-banner__offer-slogan"><?php echo esc_html( $slot_data['initial_offer_slogan'] ); ?></p>
                <a class="tmw-cr-slot-banner__cta" href="<?php echo esc_url( $slot_data['initial_cta_url'] ); ?>"<?php echo $cta_target; ?>>
                    <?php echo esc_html( $slot_data['initial_cta_text'] ); ?>
                </a>
            </div>

            <?php if ( empty( $slot_data['offers'] ) ) : ?>
                <p class="tmw-cr-slot-banner__empty-message"><?php esc_html_e( 'No active CrackRevenue offers were detected for this banner. Sync offers or update the fallback CTA.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
            <?php endif; ?>
        </aside>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Builds banner data using settings, overrides, and the detected country.
     *
     * @param array<string,mixed>               $settings Plugin settings.
     * @param array<string,array<string,string>> $overrides Parsed country overrides.
     * @param string                            $country Country code.
     *
     * @return array<string,string>
     */
    protected function build_banner_data( $settings, $overrides, $country ) {
        $country = strtoupper( (string) $country );
        $data    = array(
            'country'  => $country,
            'cta_url'  => (string) $settings['cta_url'],
            'cta_text' => (string) $settings['cta_text'],
            'headline' => (string) $settings['headline'],
            'subheadline' => (string) $settings['subheadline'],
            'spin_button_text' => (string) $settings['spin_button_text'],
        );

        if ( isset( $overrides[ $country ] ) ) {
            $override = $overrides[ $country ];

            if ( ! empty( $override['cta_url'] ) ) {
                $data['cta_url'] = $override['cta_url'];
            }

            if ( ! empty( $override['cta_text'] ) ) {
                $data['cta_text'] = $override['cta_text'];
            }

            if ( ! empty( $override['headline'] ) ) {
                $data['headline'] = $override['headline'];
            }
        }

        $data['headline'] = self::fallback_text( $data['headline'], self::DEFAULT_HEADLINE );
        $data['subheadline'] = self::fallback_text( $data['subheadline'], self::DEFAULT_SUBHEADLINE );
        $data['spin_button_text'] = self::fallback_text( $data['spin_button_text'], self::DEFAULT_SPIN_BUTTON_TEXT );
        $data['cta_text'] = self::fallback_text( $data['cta_text'], self::DEFAULT_CTA_TEXT );

        return $data;
    }

    /**
     * Builds slot configuration data for the current request.
     *
     * @param array<string,mixed>  $settings Plugin settings.
     * @param array<string,string> $banner_data Banner data.
     * @param string               $country Country code.
     *
     * @return array<string,mixed>
     */
    protected function build_slot_data( $settings, $banner_data, $country ) {
        $slot_offers = $this->offer_repository->get_frontend_slot_offers(
            self::DEFAULT_SLOT_KEY,
            $settings,
            $banner_data,
            $country,
            $this->get_offer_catalog()
        );

        $initial_offer     = isset( $slot_offers[0] ) ? $slot_offers[0] : null;
        $initial_cta_url   = $initial_offer && ! empty( $initial_offer['cta_url'] ) ? $initial_offer['cta_url'] : $banner_data['cta_url'];
        $initial_cta_text  = $initial_offer && ! empty( $initial_offer['cta_text'] ) ? $initial_offer['cta_text'] : $banner_data['cta_text'];
        $initial_offername = $initial_offer ? $initial_offer['name'] : __( 'No active offers', 'tmw-cr-slot-sidebar-banner' );
        $initial_slogan    = $initial_offer && ! empty( $initial_offer['slogan'] ) ? $initial_offer['slogan'] : __( 'Recommended adult offer', 'tmw-cr-slot-sidebar-banner' );

        return array(
            'offers'             => $slot_offers,
            'initial_offer_name' => $initial_offername,
            'initial_offer_slogan' => $initial_slogan,
            'initial_cta_url'    => $initial_cta_url,
            'initial_cta_text'   => $initial_cta_text,
            'has_empty_offer_cta' => $this->slot_has_empty_offer_cta( $slot_offers ),
        );
    }

    protected static function fallback_text( $value, $fallback ) {
        $value = trim( (string) $value );
        return '' === $value ? $fallback : $value;
    }

    protected function slot_has_empty_offer_cta( $offers ) {
        foreach ( (array) $offers as $offer ) {
            if ( empty( trim( (string) ( $offer['cta_text'] ?? '' ) ) ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the bundled fallback catalog.
     *
     * @return array<string,array<string,mixed>>
     */
    protected function get_offer_catalog() {
        return self::get_offer_catalog_defaults();
    }

    /**
     * Returns the bundled fallback catalog defaults.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function get_offer_catalog_defaults() {
        return array(
            'jerkmate' => array(
                'id'        => 'jerkmate',
                'name'      => 'Jerkmate',
                'filename'  => 'Jerkmate.png',
                'aliases'   => array( 'jerk mate' ),
                'countries' => array( 'US', 'CA', 'GB', 'IE', 'BE', 'DE', 'AT', 'CH', 'FR', 'AU', 'NZ' ),
                'cta_text'  => __( 'Play on Jerkmate', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'cam4' => array(
                'id'        => 'cam4',
                'name'      => 'CAM4',
                'filename'  => 'CAM4.png',
                'aliases'   => array( 'cam 4' ),
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'SE', 'NO', 'DK', 'NL' ),
                'cta_text'  => __( 'Go Live on CAM4', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'myfreecams' => array(
                'id'        => 'myfreecams',
                'name'      => 'MyFreeCams',
                'filename'  => 'MyFreeCams.png',
                'aliases'   => array( 'my free cams' ),
                'countries' => array( 'US', 'CA', 'GB', 'AU', 'NZ' ),
                'cta_text'  => __( 'Watch on MyFreeCams', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'imlive' => array(
                'id'        => 'imlive',
                'name'      => 'ImLive',
                'filename'  => 'ImLive.png',
                'aliases'   => array( 'im live' ),
                'countries' => array( 'US', 'CA', 'GB', 'IE', 'BE', 'DE', 'FR', 'ES', 'IT' ),
                'cta_text'  => __( 'Chat on ImLive', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'live-jasmin' => array(
                'id'        => 'live-jasmin',
                'name'      => 'Live Jasmin',
                'filename'  => 'Live Jasmin.png',
                'aliases'   => array( 'livejasmin' ),
                'countries' => array( 'US', 'CA', 'GB', 'IE', 'BE', 'DE', 'FR', 'ES', 'IT', 'PT' ),
                'cta_text'  => __( 'Watch on Live Jasmin', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'xcams' => array(
                'id'        => 'xcams',
                'name'      => 'Xcams',
                'filename'  => 'Xcams.png',
                'aliases'   => array( 'x cams' ),
                'countries' => array( 'BE', 'NL', 'DE', 'AT', 'CH', 'SE' ),
                'cta_text'  => __( 'Join Xcams', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'xmatch' => array(
                'id'        => 'xmatch',
                'name'      => 'XMatch',
                'filename'  => 'XMatch.png',
                'aliases'   => array( 'x match' ),
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'NL', 'SE', 'NO' ),
                'cta_text'  => __( 'Meet Singles on XMatch', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'sex-messenger' => array(
                'id'        => 'sex-messenger',
                'name'      => 'Sex Messenger',
                'filename'  => 'Sex Messenger.png',
                'aliases'   => array( 'sexmessenger' ),
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'AU', 'NZ' ),
                'cta_text'  => __( 'Chat on Sex Messenger', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'joi' => array(
                'id'        => 'joi',
                'name'      => 'JOI Gaming',
                'filename'  => 'Joi.png',
                'aliases'   => array( 'joi gaming' ),
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'SE' ),
                'cta_text'  => __( 'Unlock JOI Gaming', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'girlfriend-gpt' => array(
                'id'        => 'girlfriend-gpt',
                'name'      => 'Girlfriend GPT',
                'filename'  => 'Girlfriend GPT.png',
                'aliases'   => array( 'girlfriendgpt' ),
                'countries' => array(),
                'cta_text'  => __( 'Chat with Girlfriend GPT', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'candi-ai' => array(
                'id'        => 'candi-ai',
                'name'      => 'Candi AI',
                'filename'  => 'candi-ai.png',
                'aliases'   => array( 'candiai' ),
                'countries' => array(),
                'cta_text'  => __( 'Explore Candi AI', 'tmw-cr-slot-sidebar-banner' ),
            ),
        );
    }

    /**
     * Builds a plugin asset URL that is safe for filenames containing spaces.
     *
     * @param string $relative_path Relative path.
     *
     * @return string
     */
    public static function asset_url( $relative_path ) {
        $relative_path = ltrim( (string) $relative_path, '/' );
        $segments      = array_map( 'rawurlencode', explode( '/', $relative_path ) );

        return plugins_url( implode( '/', $segments ), __FILE__ );
    }

    /**
     * [TMW-CR-CRON] Maintains scheduled stats sync according to operator settings.
     *
     * @return void
     */
    public function maybe_configure_stats_sync_schedule() {
        $settings = self::get_settings();
        if ( empty( $settings['auto_sync_enabled'] ) ) {
            TMW_CR_Slot_Stats_Sync_Service::clear_cron_schedule();
            return;
        }

        TMW_CR_Slot_Stats_Sync_Service::ensure_cron_schedule( (string) $settings['auto_sync_frequency'] );
    }

    /**
     * [TMW-CR-CRON] Runs scheduled stats sync with the same local service used for manual sync.
     *
     * @return void
     */
    public function run_scheduled_stats_sync() {
        $settings = self::get_settings();
        $client   = new TMW_CR_Slot_CR_API_Client( (string) $settings['cr_api_key'] );
        $result   = TMW_CR_Slot_Stats_Sync_Service::sync(
            $client,
            $this->offer_repository,
            array( 'preset' => (string) $settings['stats_sync_range'] )
        );

        $this->offer_repository->save_stats_meta(
            array(
                'last_scheduled_run_at'  => gmdate( 'c' ),
                'last_scheduled_result'  => is_wp_error( $result ) ? 'error' : 'success',
                'last_scheduled_message' => is_wp_error( $result ) ? $result->get_error_message() : __( '[TMW-CR-CRON] Scheduled stats sync completed.', 'tmw-cr-slot-sidebar-banner' ),
            )
        );
    }
}

new TMW_CR_Slot_Sidebar_Banner();

/**
 * Activation routine.
 *
 * @return void
 */
function tmw_cr_slot_sidebar_banner_activate() {
    if ( ! get_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY ) ) {
        update_option( TMW_CR_Slot_Sidebar_Banner::OPTION_KEY, TMW_CR_Slot_Sidebar_Banner::get_settings() );
    }

    if ( ! get_option( TMW_CR_Slot_Sidebar_Banner::OFFERS_OPTION_KEY ) ) {
        update_option( TMW_CR_Slot_Sidebar_Banner::OFFERS_OPTION_KEY, array() );
    }

    if ( ! get_option( TMW_CR_Slot_Sidebar_Banner::SYNC_META_OPTION_KEY ) ) {
        update_option(
            TMW_CR_Slot_Sidebar_Banner::SYNC_META_OPTION_KEY,
            array(
                'last_synced_at' => '',
                'last_error'     => '',
                'offer_count'    => 0,
            )
        );
    }

    if ( ! get_option( TMW_CR_Slot_Sidebar_Banner::OFFER_OVERRIDES_OPTION_KEY ) ) {
        update_option( TMW_CR_Slot_Sidebar_Banner::OFFER_OVERRIDES_OPTION_KEY, array() );
    }
}

register_activation_hook( __FILE__, 'tmw_cr_slot_sidebar_banner_activate' );
