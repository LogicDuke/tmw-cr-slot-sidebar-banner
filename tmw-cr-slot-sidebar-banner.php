<?php
/**
 * Plugin Name: TMW CR Slot Sidebar Banner
 * Plugin URI: https://themilisofialtd.com/
 * Description: Displays a geo-targeted CrackRevenue slot banner with a 3-reel interface in sidebar areas via shortcode or template tag.
 * Version: 1.4.1
 * Author: The Milisofia LTD
 * Author URI: https://themilisofialtd.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tmw-cr-slot-sidebar-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TMW_CR_SLOT_BANNER_VERSION', '1.4.1' );
define( 'TMW_CR_SLOT_BANNER_PATH', plugin_dir_path( __FILE__ ) );
define( 'TMW_CR_SLOT_BANNER_URL', plugin_dir_url( __FILE__ ) );

require_once TMW_CR_SLOT_BANNER_PATH . 'includes/geo-helper.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-offer-repository.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-cr-api-client.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'includes/class-offer-sync-service.php';
require_once TMW_CR_SLOT_BANNER_PATH . 'admin/admin-page.php';

/**
 * Primary plugin bootstrap class.
 */
class TMW_CR_Slot_Sidebar_Banner {
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
        $this->offer_repository = new TMW_CR_Slot_Offer_Repository( self::OFFERS_OPTION_KEY, self::SYNC_META_OPTION_KEY );

        add_action( 'init', array( $this, 'register_shortcode' ) );
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
        wp_register_style(
            'tmw-cr-slot-banner',
            self::asset_url( 'assets/css/slot-banner.css' ),
            array(),
            TMW_CR_SLOT_BANNER_VERSION
        );

        wp_register_script(
            'tmw-cr-slot-banner',
            self::asset_url( 'assets/js/slot-banner.js' ),
            array(),
            TMW_CR_SLOT_BANNER_VERSION,
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
            'headline'               => __( 'Play Exclusive Slots', 'tmw-cr-slot-sidebar-banner' ),
            'subheadline'            => __( 'Trusted casinos tailored to your location.', 'tmw-cr-slot-sidebar-banner' ),
            'cta_text'               => __( 'Claim Your Free Spins', 'tmw-cr-slot-sidebar-banner' ),
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
        );

        $settings = get_option( self::OPTION_KEY, array() );
        $settings = is_array( $settings ) ? $settings : array();

        $settings = wp_parse_args( $settings, $defaults );
        $settings['slot_offer_ids']        = is_array( $settings['slot_offer_ids'] ) ? array_values( $settings['slot_offer_ids'] ) : array();
        $settings['slot_offer_priority']   = is_array( $settings['slot_offer_priority'] ) ? $settings['slot_offer_priority'] : array();
        $settings['offer_image_overrides'] = is_array( $settings['offer_image_overrides'] ) ? $settings['offer_image_overrides'] : array();

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

        do_action( 'tmw_cr_slot_banner_rendered' );

        $classes = array( 'tmw-cr-slot-banner' );

        if ( ! empty( $atts['class'] ) ) {
            $classes[] = sanitize_html_class( (string) $atts['class'] );
        }

        $classes[] = ! empty( $slot_data['offers'] ) ? 'tmw-cr-slot-banner--has-offers' : 'tmw-cr-slot-banner--no-offers';

        $cta_target = ! empty( $settings['open_in_new_tab'] ) ? ' target="_blank" rel="noopener nofollow"' : '';

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
        >
            <header class="tmw-cr-slot-banner__header">
                <?php if ( ! empty( $banner_data['headline'] ) ) : ?>
                    <h3 class="tmw-cr-slot-banner__headline"><?php echo esc_html( $banner_data['headline'] ); ?></h3>
                <?php endif; ?>
                <?php if ( ! empty( $settings['subheadline'] ) ) : ?>
                    <p class="tmw-cr-slot-banner__subheadline"><?php echo esc_html( $settings['subheadline'] ); ?></p>
                <?php endif; ?>
            </header>

            <div class="tmw-cr-slot-banner__machine" role="group" aria-label="<?php esc_attr_e( 'CrackRevenue slot banner', 'tmw-cr-slot-sidebar-banner' ); ?>">
                <div id="container" class="tmw-slot-container" aria-hidden="true">
                    <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                        <div class="outer-col" data-reel-index="<?php echo esc_attr( $i ); ?>">
                            <div class="col"></div>
                        </div>
                    <?php endfor; ?>
                </div>
                <button type="button" id="spin" class="tmw-cr-slot-banner__spin"<?php echo empty( $slot_data['offers'] ) ? ' disabled' : ''; ?>>
                    <span class="tmw-cr-slot-banner__spin-label"><?php esc_html_e( 'Spin the reels', 'tmw-cr-slot-sidebar-banner' ); ?></span>
                </button>
            </div>

            <div class="tmw-cr-slot-banner__footer">
                <p class="tmw-cr-slot-banner__result">
                    <span class="tmw-cr-slot-banner__result-label"><?php esc_html_e( 'Hot pick:', 'tmw-cr-slot-sidebar-banner' ); ?></span>
                    <span class="tmw-cr-slot-banner__offer-name"><?php echo esc_html( $slot_data['initial_offer_name'] ); ?></span>
                </p>
                <a class="tmw-cr-slot-banner__cta" href="<?php echo esc_url( $slot_data['initial_cta_url'] ); ?>"<?php echo $cta_target; ?>>
                    <?php echo esc_html( $slot_data['initial_cta_text'] ); ?>
                </a>
            </div>

            <?php if ( empty( $slot_data['offers'] ) ) : ?>
                <p class="tmw-cr-slot-banner__empty-message"><?php esc_html_e( 'No active CrackRevenue offers were detected for this slot. Sync offers or update the fallback CTA.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
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

        return array(
            'offers'             => $slot_offers,
            'initial_offer_name' => $initial_offername,
            'initial_cta_url'    => $initial_cta_url,
            'initial_cta_text'   => $initial_cta_text,
        );
    }

    /**
     * Returns the bundled fallback catalog.
     *
     * @return array<string,array<string,mixed>>
     */
    protected function get_offer_catalog() {
        return array(
            'jerkmate' => array(
                'id'        => 'jerkmate',
                'name'      => 'Jerkmate',
                'filename'  => 'Jerkmate.png',
                'countries' => array( 'US', 'CA', 'GB', 'IE', 'BE', 'DE', 'AT', 'CH', 'FR', 'AU', 'NZ' ),
                'cta_text'  => __( 'Play on Jerkmate', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'cam4' => array(
                'id'        => 'cam4',
                'name'      => 'CAM4',
                'filename'  => 'CAM4.png',
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'SE', 'NO', 'DK', 'NL' ),
                'cta_text'  => __( 'Go Live on CAM4', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'imlive' => array(
                'id'        => 'imlive',
                'name'      => 'ImLive',
                'filename'  => 'ImLive.png',
                'countries' => array( 'US', 'CA', 'GB', 'IE', 'BE', 'DE', 'FR', 'ES', 'IT' ),
                'cta_text'  => __( 'Chat on ImLive', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'live-jasmin' => array(
                'id'        => 'live-jasmin',
                'name'      => 'Live Jasmin',
                'filename'  => 'Live Jasmin.png',
                'countries' => array( 'US', 'CA', 'GB', 'IE', 'BE', 'DE', 'FR', 'ES', 'IT', 'PT' ),
                'cta_text'  => __( 'Watch on Live Jasmin', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'xcams' => array(
                'id'        => 'xcams',
                'name'      => 'Xcams',
                'filename'  => 'Xcams.png',
                'countries' => array( 'BE', 'NL', 'DE', 'AT', 'CH', 'SE' ),
                'cta_text'  => __( 'Join Xcams', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'xmatch' => array(
                'id'        => 'xmatch',
                'name'      => 'XMatch',
                'filename'  => 'XMatch.png',
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'NL', 'SE', 'NO' ),
                'cta_text'  => __( 'Meet Singles on XMatch', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'sex-messenger' => array(
                'id'        => 'sex-messenger',
                'name'      => 'Sex Messenger',
                'filename'  => 'Sex Messenger.png',
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'AU', 'NZ' ),
                'cta_text'  => __( 'Chat on Sex Messenger', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'joi' => array(
                'id'        => 'joi',
                'name'      => 'JOI Gaming',
                'filename'  => 'Joi.png',
                'countries' => array( 'US', 'CA', 'GB', 'BE', 'DE', 'SE' ),
                'cta_text'  => __( 'Unlock JOI Gaming', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'girlfriend-gpt' => array(
                'id'        => 'girlfriend-gpt',
                'name'      => 'Girlfriend GPT',
                'filename'  => 'Girlfriend GPT.png',
                'countries' => array(),
                'cta_text'  => __( 'Chat with Girlfriend GPT', 'tmw-cr-slot-sidebar-banner' ),
            ),
            'candi-ai' => array(
                'id'        => 'candi-ai',
                'name'      => 'Candi AI',
                'filename'  => 'candi-ai.png',
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
}

register_activation_hook( __FILE__, 'tmw_cr_slot_sidebar_banner_activate' );
