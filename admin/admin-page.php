<?php
/**
 * Admin settings page for the TMW CR Slot Sidebar Banner plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TMW_CR_Slot_Admin_Page {
    /** @var string */
    protected $option_key;

    /** @var TMW_CR_Slot_Offer_Repository */
    protected $offer_repository;

    /** @var string */
    protected $slot_key;

    /**
     * @param string                       $option_key Option key.
     * @param TMW_CR_Slot_Offer_Repository $offer_repository Offer repository.
     * @param string                       $slot_key Slot key.
     */
    public function __construct( $option_key, $offer_repository, $slot_key ) {
        $this->option_key       = $option_key;
        $this->offer_repository = $offer_repository;
        $this->slot_key         = $slot_key;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_test_connection', array( $this, 'handle_test_connection' ) );
        add_action( 'admin_post_tmw_cr_slot_banner_sync_offers', array( $this, 'handle_sync_offers' ) );
    }

    /**
     * @return void
     */
    public function register_menu() {
        add_options_page(
            __( 'TMW CR Slot Banner', 'tmw-cr-slot-sidebar-banner' ),
            __( 'TMW Slot Banner', 'tmw-cr-slot-sidebar-banner' ),
            'manage_options',
            'tmw-cr-slot-sidebar-banner',
            array( $this, 'render_page' )
        );
    }

    /**
     * @return void
     */
    public function register_settings() {
        register_setting( 'tmw_cr_slot_banner', $this->option_key, array( $this, 'sanitize_settings' ) );
    }

    /**
     * @param array<string,mixed> $input Raw settings.
     *
     * @return array<string,mixed>
     */
    public function sanitize_settings( $input ) {
        $input    = (array) $input;
        $existing = TMW_CR_Slot_Sidebar_Banner::get_settings();
        $output   = array();

        $output['headline']          = isset( $input['headline'] ) ? sanitize_text_field( $input['headline'] ) : '';
        $output['subheadline']       = isset( $input['subheadline'] ) ? sanitize_text_field( $input['subheadline'] ) : '';
        $output['cta_text']          = isset( $input['cta_text'] ) ? sanitize_text_field( $input['cta_text'] ) : '';
        $output['cta_url']           = isset( $input['cta_url'] ) ? esc_url_raw( $input['cta_url'] ) : '';
        $output['default_image_url'] = isset( $input['default_image_url'] ) ? esc_url_raw( $input['default_image_url'] ) : '';
        $output['subid_param']       = isset( $input['subid_param'] ) ? sanitize_key( $input['subid_param'] ) : '';
        $output['subid_value']       = isset( $input['subid_value'] ) ? sanitize_text_field( $input['subid_value'] ) : '';
        $output['open_in_new_tab']   = ! empty( $input['open_in_new_tab'] ) ? 1 : 0;
        $output['country_overrides_raw'] = isset( $input['country_overrides_raw'] ) ? sanitize_textarea_field( $input['country_overrides_raw'] ) : '';

        $api_key = isset( $input['cr_api_key'] ) ? trim( (string) $input['cr_api_key'] ) : '';
        $output['cr_api_key'] = '' !== $api_key ? sanitize_text_field( $api_key ) : (string) $existing['cr_api_key'];

        $output['slot_offer_ids'] = array();
        if ( isset( $input['slot_offer_ids'] ) && is_array( $input['slot_offer_ids'] ) ) {
            foreach ( $input['slot_offer_ids'] as $offer_id ) {
                $offer_id = sanitize_text_field( (string) $offer_id );
                if ( '' !== $offer_id ) {
                    $output['slot_offer_ids'][] = $offer_id;
                }
            }
        }

        $output['slot_offer_priority'] = array();
        if ( isset( $input['slot_offer_priority'] ) && is_array( $input['slot_offer_priority'] ) ) {
            foreach ( $input['slot_offer_priority'] as $offer_id => $priority ) {
                $offer_id = sanitize_text_field( (string) $offer_id );
                if ( '' === $offer_id ) {
                    continue;
                }

                $output['slot_offer_priority'][ $offer_id ] = max( 0, (int) $priority );
            }
        }

        $output['offer_image_overrides'] = array();
        if ( isset( $input['offer_image_overrides'] ) && is_array( $input['offer_image_overrides'] ) ) {
            foreach ( $input['offer_image_overrides'] as $offer_id => $image_url ) {
                $offer_id = sanitize_text_field( (string) $offer_id );
                if ( '' === $offer_id ) {
                    continue;
                }

                $output['offer_image_overrides'][ $offer_id ] = esc_url_raw( (string) $image_url );
            }
        }

        return $output;
    }

    /**
     * @return void
     */
    public function handle_test_connection() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_test_connection' );

        $client = $this->build_api_client();
        $result = $client->test_connection();

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'error', $result->get_error_message() );
        }

        $rows    = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $result );
        $message = ! empty( $rows )
            ? __( 'CrakRevenue connection successful.', 'tmw-cr-slot-sidebar-banner' )
            : __( 'CrakRevenue connection successful, but no offers were returned for the test request.', 'tmw-cr-slot-sidebar-banner' );

        $this->redirect_with_notice( 'success', $message );
    }

    /**
     * @return void
     */
    public function handle_sync_offers() {
        $this->assert_admin_action( 'tmw_cr_slot_banner_sync_offers' );

        $client = $this->build_api_client();
        $result = TMW_CR_Slot_Offer_Sync_Service::sync_all( $client, $this->offer_repository );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_notice( 'error', $result->get_error_message() );
        }

        $count = isset( $result['offer_count'] ) ? (int) $result['offer_count'] : 0;
        $this->redirect_with_notice( 'success', sprintf( __( 'Offer sync completed. %d offers stored locally.', 'tmw-cr-slot-sidebar-banner' ), $count ) );
    }

    /**
     * @return void
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings     = TMW_CR_Slot_Sidebar_Banner::get_settings();
        $sync_meta    = $this->offer_repository->get_sync_meta();
        $synced_offers = $this->offer_repository->get_synced_offers_for_admin();
        $notice_type  = isset( $_GET['tmw_cr_slot_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['tmw_cr_slot_notice'] ) ) : '';
        $notice_text  = isset( $_GET['tmw_cr_slot_message'] ) ? sanitize_text_field( wp_unslash( $_GET['tmw_cr_slot_message'] ) ) : '';
        $client       = $this->build_api_client();
        ?>
        <div class="wrap tmw-cr-slot-banner-settings">
            <h1><?php esc_html_e( 'TMW CrackRevenue Slot Sidebar Banner', 'tmw-cr-slot-sidebar-banner' ); ?></h1>
            <p><?php esc_html_e( 'Configure fallback banner content, store the CrakRevenue API key safely, sync offers locally, and choose which offers appear in the sidebar slot.', 'tmw-cr-slot-sidebar-banner' ); ?></p>

            <?php if ( '' !== $notice_text ) : ?>
                <div class="notice notice-<?php echo 'success' === $notice_type ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( $notice_text ); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width:none;padding:16px 20px;margin-bottom:20px;">
                <h2><?php esc_html_e( 'CrakRevenue API', 'tmw-cr-slot-sidebar-banner' ); ?></h2>
                <p><?php esc_html_e( 'The API key is stored in WordPress options and never rendered back into the page source. Leave the field blank when saving to keep the current key.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                <p><strong><?php esc_html_e( 'Stored key', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo esc_html( $client->get_masked_api_key() ? $client->get_masked_api_key() : __( 'Not configured', 'tmw-cr-slot-sidebar-banner' ) ); ?></p>
                <p><strong><?php esc_html_e( 'Last sync', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo ! empty( $sync_meta['last_synced_at'] ) ? esc_html( (string) $sync_meta['last_synced_at'] ) : esc_html__( 'Never', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                <p><strong><?php esc_html_e( 'Stored offers', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo esc_html( (string) (int) ( $sync_meta['offer_count'] ?? count( $synced_offers ) ) ); ?></p>
                <?php if ( ! empty( $sync_meta['last_error'] ) ) : ?>
                    <p><strong><?php esc_html_e( 'Last sync error', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo esc_html( (string) $sync_meta['last_error'] ); ?></p>
                <?php endif; ?>
                <p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
                        <?php wp_nonce_field( 'tmw_cr_slot_banner_test_connection' ); ?>
                        <input type="hidden" name="action" value="tmw_cr_slot_banner_test_connection" />
                        <?php submit_button( __( 'Test Connection', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                        <?php wp_nonce_field( 'tmw_cr_slot_banner_sync_offers' ); ?>
                        <input type="hidden" name="action" value="tmw_cr_slot_banner_sync_offers" />
                        <?php submit_button( __( 'Sync Offers Now', 'tmw-cr-slot-sidebar-banner' ), 'primary', 'submit', false ); ?>
                    </form>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'tmw_cr_slot_banner' ); ?>

                <h2><?php esc_html_e( 'Fallback Banner Settings', 'tmw-cr-slot-sidebar-banner' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="tmw-cr-headline"><?php esc_html_e( 'Headline', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="tmw-cr-headline" name="<?php echo esc_attr( $this->option_key ); ?>[headline]" value="<?php echo esc_attr( $settings['headline'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-subheadline"><?php esc_html_e( 'Subheadline', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="tmw-cr-subheadline" name="<?php echo esc_attr( $this->option_key ); ?>[subheadline]" value="<?php echo esc_attr( $settings['subheadline'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-cta-text"><?php esc_html_e( 'CTA Text', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td><input type="text" class="regular-text" id="tmw-cr-cta-text" name="<?php echo esc_attr( $this->option_key ); ?>[cta_text]" value="<?php echo esc_attr( $settings['cta_text'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-cta-url"><?php esc_html_e( 'CTA URL', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td><input type="url" class="regular-text" id="tmw-cr-cta-url" name="<?php echo esc_attr( $this->option_key ); ?>[cta_url]" value="<?php echo esc_attr( $settings['cta_url'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-default-image"><?php esc_html_e( 'Default Image URL', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td><input type="url" class="regular-text" id="tmw-cr-default-image" name="<?php echo esc_attr( $this->option_key ); ?>[default_image_url]" value="<?php echo esc_attr( $settings['default_image_url'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-subid-param"><?php esc_html_e( 'SubID Query Parameter', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td><input type="text" id="tmw-cr-subid-param" name="<?php echo esc_attr( $this->option_key ); ?>[subid_param]" value="<?php echo esc_attr( $settings['subid_param'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-subid-value"><?php esc_html_e( 'SubID Value', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td><input type="text" id="tmw-cr-subid-value" name="<?php echo esc_attr( $this->option_key ); ?>[subid_value]" value="<?php echo esc_attr( $settings['subid_value'] ); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-api-key"><?php esc_html_e( 'CrakRevenue API Key', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td>
                                <input type="password" class="regular-text" id="tmw-cr-api-key" name="<?php echo esc_attr( $this->option_key ); ?>[cr_api_key]" value="" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave blank to keep existing key', 'tmw-cr-slot-sidebar-banner' ); ?>" />
                                <p class="description"><?php esc_html_e( 'Used only for secure server-side sync requests. Never exposed in HTML, the slot shortcode output, or notices.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Open CTA in new tab', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                            <td>
                                <label for="tmw-cr-open-new-tab">
                                    <input type="checkbox" id="tmw-cr-open-new-tab" name="<?php echo esc_attr( $this->option_key ); ?>[open_in_new_tab]" value="1" <?php checked( $settings['open_in_new_tab'], 1 ); ?> />
                                    <?php esc_html_e( 'Open the CTA in a new browser tab.', 'tmw-cr-slot-sidebar-banner' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmw-cr-country-overrides"><?php esc_html_e( 'Country Overrides', 'tmw-cr-slot-sidebar-banner' ); ?></label></th>
                            <td>
                                <textarea class="large-text code" rows="8" id="tmw-cr-country-overrides" name="<?php echo esc_attr( $this->option_key ); ?>[country_overrides_raw]" placeholder="US|https://example.com/us-banner.png|https://offer.com/us|Join Now|Exclusive US Bonus"><?php echo esc_textarea( $settings['country_overrides_raw'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'One override per line using the format CC|Image URL|CTA URL|CTA Text|Headline.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Sidebar Slot Offer Selection', 'tmw-cr-slot-sidebar-banner' ); ?></h2>
                <p><?php esc_html_e( 'Select one or more synced offers for the slot banner. Lower priority numbers appear first in the reel pool. Because the CrakRevenue Offer API does not expose a slot-ready image field, each offer can optionally use a manual image URL override.', 'tmw-cr-slot-sidebar-banner' ); ?></p>

                <?php if ( empty( $synced_offers ) ) : ?>
                    <p><?php esc_html_e( 'No synced offers available yet. Save the API key, test the connection, then sync offers.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Use', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Priority', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Image URL Override', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Offer', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Payout', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Approval', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Featured', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                                <th><?php esc_html_e( 'Preview URL', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $synced_offers as $offer ) : ?>
                                <?php
                                $offer_id     = (string) $offer['id'];
                                $is_selected  = in_array( $offer_id, $settings['slot_offer_ids'], true );
                                $priority     = isset( $settings['slot_offer_priority'][ $offer_id ] ) ? (int) $settings['slot_offer_priority'][ $offer_id ] : 100;
                                $image_url    = isset( $settings['offer_image_overrides'][ $offer_id ] ) ? (string) $settings['offer_image_overrides'][ $offer_id ] : '';
                                $payout_parts = array_filter(
                                    array(
                                        ! empty( $offer['default_payout'] ) ? (string) $offer['default_payout'] : '',
                                        ! empty( $offer['percent_payout'] ) ? (string) $offer['percent_payout'] . '%' : '',
                                        ! empty( $offer['currency'] ) ? (string) $offer['currency'] : '',
                                        ! empty( $offer['payout_type'] ) ? (string) $offer['payout_type'] : '',
                                    )
                                );
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[slot_offer_ids][]" value="<?php echo esc_attr( $offer_id ); ?>" <?php checked( $is_selected ); ?> /></td>
                                    <td><input type="number" min="0" step="1" name="<?php echo esc_attr( $this->option_key ); ?>[slot_offer_priority][<?php echo esc_attr( $offer_id ); ?>]" value="<?php echo esc_attr( (string) $priority ); ?>" style="width:90px;" /></td>
                                    <td><input type="url" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_image_overrides][<?php echo esc_attr( $offer_id ); ?>]" value="<?php echo esc_attr( $image_url ); ?>" /></td>
                                    <td><strong><?php echo esc_html( (string) $offer['name'] ); ?></strong><br /><code><?php echo esc_html( $offer_id ); ?></code></td>
                                    <td><?php echo esc_html( (string) ( $offer['status'] ?: '-' ) ); ?></td>
                                    <td><?php echo esc_html( ! empty( $payout_parts ) ? implode( ' / ', $payout_parts ) : '-' ); ?></td>
                                    <td><?php echo esc_html( '1' === (string) $offer['require_approval'] ? __( 'Required', 'tmw-cr-slot-sidebar-banner' ) : __( 'No', 'tmw-cr-slot-sidebar-banner' ) ); ?></td>
                                    <td><?php echo esc_html( ! empty( $offer['is_featured'] ) ? __( 'Yes', 'tmw-cr-slot-sidebar-banner' ) : __( 'No', 'tmw-cr-slot-sidebar-banner' ) ); ?></td>
                                    <td>
                                        <?php if ( ! empty( $offer['preview_url'] ) ) : ?>
                                            <a href="<?php echo esc_url( (string) $offer['preview_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open preview', 'tmw-cr-slot-sidebar-banner' ); ?></a>
                                        <?php else : ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php submit_button( __( 'Save Banner Settings', 'tmw-cr-slot-sidebar-banner' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @return TMW_CR_Slot_CR_API_Client
     */
    protected function build_api_client() {
        $settings = TMW_CR_Slot_Sidebar_Banner::get_settings();

        return new TMW_CR_Slot_CR_API_Client( (string) $settings['cr_api_key'] );
    }

    /**
     * @param string $notice_type Notice type.
     * @param string $message     Message.
     *
     * @return void
     */
    protected function redirect_with_notice( $notice_type, $message ) {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                 => 'tmw-cr-slot-sidebar-banner',
                    'tmw_cr_slot_notice'   => sanitize_key( $notice_type ),
                    'tmw_cr_slot_message'  => $message,
                ),
                admin_url( 'options-general.php' )
            )
        );
        exit;
    }

    /**
     * @param string $nonce_action Nonce action.
     *
     * @return void
     */
    protected function assert_admin_action( $nonce_action ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'tmw-cr-slot-sidebar-banner' ) );
        }

        check_admin_referer( $nonce_action );
    }
}
