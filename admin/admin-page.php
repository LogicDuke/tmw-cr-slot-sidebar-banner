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
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
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
     * @param string $hook_suffix Current admin page hook.
     *
     * @return void
     */
    public function enqueue_dashboard_assets( $hook_suffix ) {
        if ( 'settings_page_tmw-cr-slot-sidebar-banner' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'tmw-cr-slot-admin-dashboard',
            TMW_CR_Slot_Sidebar_Banner::asset_url( 'assets/css/admin-dashboard.css' ),
            array(),
            TMW_CR_SLOT_BANNER_VERSION
        );
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

        $output['headline']              = isset( $input['headline'] ) ? sanitize_text_field( $input['headline'] ) : '';
        $output['subheadline']           = isset( $input['subheadline'] ) ? sanitize_text_field( $input['subheadline'] ) : '';
        $output['cta_text']              = isset( $input['cta_text'] ) ? sanitize_text_field( $input['cta_text'] ) : '';
        $output['cta_url']               = isset( $input['cta_url'] ) ? esc_url_raw( $input['cta_url'] ) : '';
        $output['default_image_url']     = isset( $input['default_image_url'] ) ? esc_url_raw( $input['default_image_url'] ) : '';
        $output['subid_param']           = isset( $input['subid_param'] ) ? sanitize_key( $input['subid_param'] ) : '';
        $output['subid_value']           = isset( $input['subid_value'] ) ? sanitize_text_field( $input['subid_value'] ) : '';
        $output['open_in_new_tab']       = ! empty( $input['open_in_new_tab'] ) ? 1 : 0;
        $output['country_overrides_raw'] = isset( $input['country_overrides_raw'] ) ? sanitize_textarea_field( $input['country_overrides_raw'] ) : '';

        $api_key              = isset( $input['cr_api_key'] ) ? trim( (string) $input['cr_api_key'] ) : '';
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

        $rows           = TMW_CR_Slot_Offer_Sync_Service::extract_offer_rows( $result );
        $response_shape = TMW_CR_Slot_Offer_Sync_Service::detect_response_shape( $result );
        $raw_count      = count( $rows );
        $importable     = 0;

        foreach ( $rows as $row ) {
            $normalized = TMW_CR_Slot_Offer_Sync_Service::normalize_offer( $row );
            if ( '' !== (string) $normalized['id'] ) {
                ++$importable;
            }
        }

        if ( $raw_count > 0 && 0 === $importable ) {
            $this->redirect_with_notice(
                'error',
                sprintf(
                    /* translators: 1: raw rows, 2: response shape */
                    __( '[TMW-CR-API] Connection succeeded (%1$d row(s), shape: %2$s) but rows look non-importable.', 'tmw-cr-slot-sidebar-banner' ),
                    $raw_count,
                    $response_shape
                )
            );
        }

        if ( $raw_count > 0 ) {
            $this->redirect_with_notice(
                'success',
                sprintf(
                    /* translators: 1: raw rows, 2: response shape */
                    __( '[TMW-CR-API] Connection successful. Detected %1$d row(s) (shape: %2$s).', 'tmw-cr-slot-sidebar-banner' ),
                    $raw_count,
                    $response_shape
                )
            );
        }

        $this->redirect_with_notice(
            'success',
            sprintf(
                __( '[TMW-CR-API] Connection successful but no rows were returned (shape: %s).', 'tmw-cr-slot-sidebar-banner' ),
                $response_shape
            )
        );
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

        $count          = isset( $result['offer_count'] ) ? (int) $result['offer_count'] : 0;
        $raw_count      = isset( $result['last_raw_row_count'] ) ? (int) $result['last_raw_row_count'] : 0;
        $imported_count = isset( $result['last_imported_count'] ) ? (int) $result['last_imported_count'] : $count;
        $skipped_count  = isset( $result['last_skipped_count'] ) ? (int) $result['last_skipped_count'] : 0;
        $preserved      = ! empty( $result['preserved_previous'] );

        $message = sprintf(
            /* translators: 1: imported count, 2: raw row count, 3: skipped count, 4: stored count */
            __( '[TMW-CR-SYNC] Sync complete. Imported: %1$d, Raw: %2$d, Skipped: %3$d, Stored: %4$d.', 'tmw-cr-slot-sidebar-banner' ),
            $imported_count,
            $raw_count,
            $skipped_count,
            $count
        );

        if ( $preserved ) {
            $message .= ' ' . __( 'Previous synced offers were preserved due to parser soft-failure.', 'tmw-cr-slot-sidebar-banner' );
        }

        $this->redirect_with_notice( 'success', $message );
    }

    /**
     * @return void
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings    = TMW_CR_Slot_Sidebar_Banner::get_settings();
        $sync_meta   = $this->offer_repository->get_sync_meta();
        $summary     = $this->offer_repository->get_dashboard_summary( $settings );
        $notice_type = isset( $_GET['tmw_cr_slot_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['tmw_cr_slot_notice'] ) ) : '';
        $notice_text = isset( $_GET['tmw_cr_slot_message'] ) ? sanitize_text_field( wp_unslash( $_GET['tmw_cr_slot_message'] ) ) : '';
        $client      = $this->build_api_client();

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        if ( ! in_array( $active_tab, array( 'overview', 'offers', 'slot-setup', 'settings' ), true ) ) {
            $active_tab = 'overview';
        }
        ?>
        <div class="wrap tmw-cr-slot-banner-dashboard">
            <h1><?php esc_html_e( 'TMW CrakRevenue Slot Operations Dashboard', 'tmw-cr-slot-sidebar-banner' ); ?></h1>
            <p><?php esc_html_e( 'Manage synced offers, slot selection, priorities, image overrides, and sync health at scale.', 'tmw-cr-slot-sidebar-banner' ); ?></p>

            <?php if ( '' !== $notice_text ) : ?>
                <div class="notice notice-<?php echo 'success' === $notice_type ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html( $notice_text ); ?></p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $this->get_tabs() as $tab_slug => $tab_label ) : ?>
                    <?php $is_active = $tab_slug === $active_tab; ?>
                    <a href="<?php echo esc_url( $this->build_tab_url( $tab_slug ) ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
                <?php endforeach; ?>
            </h2>

            <?php
            if ( 'overview' === $active_tab ) {
                $this->render_overview_tab( $summary, $sync_meta, $client );
            } elseif ( 'offers' === $active_tab ) {
                $this->render_offers_tab( $settings );
            } elseif ( 'slot-setup' === $active_tab ) {
                $this->render_slot_setup_tab( $settings );
            } else {
                $this->render_settings_tab( $settings, $sync_meta, $client );
            }
            ?>
        </div>
        <?php
    }

    /**
     * @return array<string,string>
     */
    protected function get_tabs() {
        return array(
            'overview'   => __( 'Overview', 'tmw-cr-slot-sidebar-banner' ),
            'offers'     => __( 'Offers', 'tmw-cr-slot-sidebar-banner' ),
            'slot-setup' => __( 'Slot Setup', 'tmw-cr-slot-sidebar-banner' ),
            'settings'   => __( 'Settings', 'tmw-cr-slot-sidebar-banner' ),
        );
    }

    /**
     * @param string $tab_slug Tab slug.
     * @param array<string,string> $args Optional query args.
     *
     * @return string
     */
    protected function build_tab_url( $tab_slug, $args = array() ) {
        $params = array_merge(
            array(
                'page' => 'tmw-cr-slot-sidebar-banner',
                'tab'  => $tab_slug,
            ),
            $args
        );

        return add_query_arg( $params, admin_url( 'options-general.php' ) );
    }

    /**
     * @param array<string,mixed> $summary Summary data.
     * @param array<string,mixed> $sync_meta Sync meta.
     * @param TMW_CR_Slot_CR_API_Client $client Client.
     *
     * @return void
     */
    protected function render_overview_tab( $summary, $sync_meta, $client ) {
        ?>
        <div class="tmw-cr-sync-health notice <?php echo ! empty( $summary['last_soft_failure'] ) ? 'notice-warning' : 'notice-success'; ?>">
            <p>
                <?php if ( ! empty( $summary['last_soft_failure'] ) ) : ?>
                    <?php esc_html_e( '[TMW-CR-DASH] Last sync recorded a parser soft-failure. Previously stored offers were preserved.', 'tmw-cr-slot-sidebar-banner' ); ?>
                <?php else : ?>
                    <?php esc_html_e( '[TMW-CR-DASH] Sync health is stable. No parser soft-failure on the last sync run.', 'tmw-cr-slot-sidebar-banner' ); ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="tmw-cr-card-grid">
            <?php $this->render_summary_card( __( 'Stored offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['stored_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Selected slot offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['selected_slot_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Active synced offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['active_synced_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Featured synced offers', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['featured_synced_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Offers requiring approval', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['approval_required_offers'] ); ?>
            <?php $this->render_summary_card( __( 'Offers with manual image overrides', 'tmw-cr-slot-sidebar-banner' ), (string) (int) $summary['manual_image_overrides'] ); ?>
            <?php $this->render_summary_card( __( 'Last sync time', 'tmw-cr-slot-sidebar-banner' ), ! empty( $summary['last_sync_time'] ) ? (string) $summary['last_sync_time'] : __( 'Never', 'tmw-cr-slot-sidebar-banner' ) ); ?>
            <?php $this->render_summary_card( __( 'Last raw/imported/skipped', 'tmw-cr-slot-sidebar-banner' ), sprintf( '%d / %d / %d', (int) $summary['last_raw_row_count'], (int) $summary['last_imported_count'], (int) $summary['last_skipped_count'] ) ); ?>
            <?php $this->render_summary_card( __( 'Last soft-failure', 'tmw-cr-slot-sidebar-banner' ), ! empty( $summary['last_soft_failure'] ) ? __( 'Yes', 'tmw-cr-slot-sidebar-banner' ) : __( 'No', 'tmw-cr-slot-sidebar-banner' ) ); ?>
        </div>

        <div class="tmw-cr-actions-row">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_test_connection' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_test_connection" />
                <?php submit_button( __( 'Test Connection', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_sync_offers' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_sync_offers" />
                <?php submit_button( __( 'Sync Offers Now', 'tmw-cr-slot-sidebar-banner' ), 'primary', 'submit', false ); ?>
            </form>
            <p><strong><?php esc_html_e( 'Stored API key', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo esc_html( $client->get_masked_api_key() ? $client->get_masked_api_key() : __( 'Not configured', 'tmw-cr-slot-sidebar-banner' ) ); ?></p>
        </div>

        <table class="widefat striped tmw-cr-overview-table">
            <tbody>
                <tr><th><?php esc_html_e( 'Last response shape', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo ! empty( $sync_meta['last_response_shape'] ) ? esc_html( (string) $sync_meta['last_response_shape'] ) : esc_html__( 'Unknown', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Sample row keys', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo ! empty( $sync_meta['sample_row_keys'] ) ? esc_html( (string) $sync_meta['sample_row_keys'] ) : esc_html__( 'N/A', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Last sync error', 'tmw-cr-slot-sidebar-banner' ); ?></th><td><?php echo ! empty( $summary['last_error'] ) ? esc_html( (string) $summary['last_error'] ) : esc_html__( 'None', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array<string,mixed> $settings Settings.
     *
     * @return void
     */
    protected function render_offers_tab( $settings ) {
        $args = array(
            'search'            => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
            'status'            => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
            'featured'          => isset( $_GET['featured'] ) ? sanitize_key( wp_unslash( $_GET['featured'] ) ) : '',
            'approval_required' => isset( $_GET['approval_required'] ) ? sanitize_key( wp_unslash( $_GET['approval_required'] ) ) : '',
            'payout_type'       => isset( $_GET['payout_type'] ) ? sanitize_key( wp_unslash( $_GET['payout_type'] ) ) : '',
            'image_status'      => isset( $_GET['image_status'] ) ? sanitize_key( wp_unslash( $_GET['image_status'] ) ) : '',
            'sort_by'           => isset( $_GET['sort_by'] ) ? sanitize_key( wp_unslash( $_GET['sort_by'] ) ) : 'name',
            'sort_order'        => isset( $_GET['sort_order'] ) ? sanitize_key( wp_unslash( $_GET['sort_order'] ) ) : 'asc',
            'page'              => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
            'per_page'          => 25,
        );

        $result = $this->offer_repository->get_filtered_synced_offers_for_admin( $args, $settings );
        $items  = $result['items'];
        ?>
        <form method="get" class="tmw-cr-filters">
            <input type="hidden" name="page" value="tmw-cr-slot-sidebar-banner" />
            <input type="hidden" name="tab" value="offers" />
            <input type="search" name="search" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search by offer name or id', 'tmw-cr-slot-sidebar-banner' ); ?>" />
            <?php $this->render_filter_select( 'status', $args['status'], array( '' => 'All status', 'active' => 'Active', 'paused' => 'Paused' ) ); ?>
            <?php $this->render_filter_select( 'featured', $args['featured'], array( '' => 'Featured: any', 'yes' => 'Featured: yes', 'no' => 'Featured: no' ) ); ?>
            <?php $this->render_filter_select( 'approval_required', $args['approval_required'], array( '' => 'Approval: any', 'yes' => 'Approval required', 'no' => 'Approval not required' ) ); ?>
            <?php $this->render_filter_select( 'payout_type', $args['payout_type'], array( '' => 'Payout type: any', 'cpa' => 'CPA', 'revshare' => 'Revshare', 'hybrid' => 'Hybrid', 'cpa_both' => 'CPA both' ) ); ?>
            <?php $this->render_filter_select( 'image_status', $args['image_status'], array( '' => 'Image status: any', 'manual_override' => 'Manual override', 'placeholder_only' => 'Placeholder only' ) ); ?>
            <?php submit_button( __( 'Apply', 'tmw-cr-slot-sidebar-banner' ), 'secondary', '', false ); ?>
        </form>

        <table class="widefat striped">
            <thead>
                <tr>
                    <?php $this->render_sort_link_header( 'name', __( 'Name', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'id', __( 'ID', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'status', __( 'Status', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'payout', __( 'Payout', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <?php $this->render_sort_link_header( 'featured', __( 'Featured', 'tmw-cr-slot-sidebar-banner' ), $args ); ?>
                    <th><?php esc_html_e( 'Approval', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Image', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    <th><?php esc_html_e( 'Slot', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $items ) ) : ?>
                    <tr><td colspan="8"><?php esc_html_e( 'No offers match the current filters.', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $items as $offer ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( (string) ( $offer['name'] ?? '' ) ); ?></strong></td>
                            <td><code><?php echo esc_html( (string) ( $offer['id'] ?? '' ) ); ?></code></td>
                            <td><?php $this->render_badge( (string) ( $offer['status'] ?? '-' ), 'status' ); ?></td>
                            <td><small><?php echo esc_html( $this->format_payout( $offer ) ); ?></small></td>
                            <td><?php $this->render_badge( ! empty( $offer['is_featured'] ) ? 'Yes' : 'No', ! empty( $offer['is_featured'] ) ? 'featured' : 'muted' ); ?></td>
                            <td><?php $this->render_badge( '1' === (string) ( $offer['require_approval'] ?? '' ) ? 'Required' : 'No', 'approval' ); ?></td>
                            <td><?php $this->render_badge( 'manual_override' === (string) ( $offer['image_status'] ?? '' ) ? 'Manual override' : 'Placeholder only', 'manual_override' === (string) ( $offer['image_status'] ?? '' ) ? 'featured' : 'muted' ); ?></td>
                            <td><?php $this->render_badge( ! empty( $offer['is_selected_for_slot'] ) ? 'Selected for slot' : 'Not selected', ! empty( $offer['is_selected_for_slot'] ) ? 'selected' : 'muted' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php $this->render_pagination( (int) $result['page'], (int) $result['pages'], $args ); ?>
        <?php
    }

    /**
     * @param array<string,mixed> $settings Settings.
     *
     * @return void
     */
    protected function render_slot_setup_tab( $settings ) {
        $include_all = ! empty( $_GET['include_all_offers'] );
        $args        = array(
            'selected_only' => ! $include_all,
            'include_all'   => $include_all,
            'sort_by'       => 'name',
            'sort_order'    => 'asc',
            'page'          => 1,
            'per_page'      => 400,
        );
        $result      = $this->offer_repository->get_filtered_synced_offers_for_admin( $args, $settings );
        $offers      = $result['items'];

        usort(
            $offers,
            static function ( $left, $right ) use ( $settings ) {
                $priorities = isset( $settings['slot_offer_priority'] ) ? (array) $settings['slot_offer_priority'] : array();
                $left_id    = (string) ( $left['id'] ?? '' );
                $right_id   = (string) ( $right['id'] ?? '' );
                $left_p     = isset( $priorities[ $left_id ] ) ? (int) $priorities[ $left_id ] : 9999;
                $right_p    = isset( $priorities[ $right_id ] ) ? (int) $priorities[ $right_id ] : 9999;

                if ( $left_p !== $right_p ) {
                    return $left_p <=> $right_p;
                }

                return strcasecmp( (string) ( $left['name'] ?? '' ), (string) ( $right['name'] ?? '' ) );
            }
        );
        ?>
        <form method="get" class="tmw-cr-filters">
            <input type="hidden" name="page" value="tmw-cr-slot-sidebar-banner" />
            <input type="hidden" name="tab" value="slot-setup" />
            <label>
                <input type="checkbox" name="include_all_offers" value="1" <?php checked( $include_all ); ?> />
                <?php esc_html_e( 'Include more offers from synced pool', 'tmw-cr-slot-sidebar-banner' ); ?>
            </label>
            <?php submit_button( __( 'Refresh View', 'tmw-cr-slot-sidebar-banner' ), 'secondary', '', false ); ?>
        </form>

        <form method="post" action="options.php">
            <?php settings_fields( 'tmw_cr_slot_banner' ); ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Select', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Offer', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Image override', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Preview', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                        <th><?php esc_html_e( 'Quick action', 'tmw-cr-slot-sidebar-banner' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $offers ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'No offers available for slot setup yet. Sync offers first.', 'tmw-cr-slot-sidebar-banner' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $offers as $offer ) : ?>
                            <?php
                            $offer_id    = (string) ( $offer['id'] ?? '' );
                            $selected    = ! empty( $offer['is_selected_for_slot'] );
                            $priority    = isset( $settings['slot_offer_priority'][ $offer_id ] ) ? (int) $settings['slot_offer_priority'][ $offer_id ] : 100;
                            $image_value = isset( $settings['offer_image_overrides'][ $offer_id ] ) ? (string) $settings['offer_image_overrides'][ $offer_id ] : '';
                            ?>
                            <tr>
                                <td><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[slot_offer_ids][]" value="<?php echo esc_attr( $offer_id ); ?>" <?php checked( $selected ); ?> /></td>
                                <td><strong><?php echo esc_html( (string) ( $offer['name'] ?? '' ) ); ?></strong><br /><code><?php echo esc_html( $offer_id ); ?></code></td>
                                <td><input type="number" min="0" step="1" name="<?php echo esc_attr( $this->option_key ); ?>[slot_offer_priority][<?php echo esc_attr( $offer_id ); ?>]" value="<?php echo esc_attr( (string) $priority ); ?>" style="width:90px;" /></td>
                                <td>
                                    <input type="url" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[offer_image_overrides][<?php echo esc_attr( $offer_id ); ?>]" value="<?php echo esc_attr( $image_value ); ?>" />
                                    <p class="description"><?php esc_html_e( 'Optional. Leave blank to use generated placeholder image.', 'tmw-cr-slot-sidebar-banner' ); ?></p>
                                </td>
                                <td>
                                    <?php if ( '' !== $image_value ) : ?>
                                        <img src="<?php echo esc_url( $image_value ); ?>" alt="" style="max-width:70px;height:auto;border-radius:4px;" />
                                    <?php else : ?>
                                        <span class="description"><?php esc_html_e( 'Placeholder in use', 'tmw-cr-slot-sidebar-banner' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $this->render_badge( $selected ? 'Selected' : 'Not selected', $selected ? 'selected' : 'muted' ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php submit_button( __( 'Save Slot Setup', 'tmw-cr-slot-sidebar-banner' ) ); ?>
        </form>

        <h3><?php esc_html_e( 'Current final reel pool order', 'tmw-cr-slot-sidebar-banner' ); ?></h3>
        <ol>
            <?php
            $selected_ids = isset( $settings['slot_offer_ids'] ) && is_array( $settings['slot_offer_ids'] ) ? $settings['slot_offer_ids'] : array();
            $priorities   = isset( $settings['slot_offer_priority'] ) && is_array( $settings['slot_offer_priority'] ) ? $settings['slot_offer_priority'] : array();
            $all_offers   = $this->offer_repository->get_synced_offers();
            $pool         = array();

            foreach ( $selected_ids as $selected_id ) {
                $selected_id = (string) $selected_id;
                if ( isset( $all_offers[ $selected_id ] ) ) {
                    $pool[] = array(
                        'id'       => $selected_id,
                        'name'     => (string) ( $all_offers[ $selected_id ]['name'] ?? $selected_id ),
                        'priority' => isset( $priorities[ $selected_id ] ) ? (int) $priorities[ $selected_id ] : 9999,
                    );
                }
            }

            usort(
                $pool,
                static function ( $left, $right ) {
                    if ( $left['priority'] !== $right['priority'] ) {
                        return $left['priority'] <=> $right['priority'];
                    }

                    return strcasecmp( $left['name'], $right['name'] );
                }
            );

            if ( empty( $pool ) ) {
                echo '<li>' . esc_html__( 'No offers selected yet.', 'tmw-cr-slot-sidebar-banner' ) . '</li>';
            } else {
                foreach ( $pool as $item ) {
                    echo '<li><strong>' . esc_html( $item['name'] ) . '</strong> <code>' . esc_html( $item['id'] ) . '</code> — ' . sprintf( esc_html__( 'Priority %d', 'tmw-cr-slot-sidebar-banner' ), (int) $item['priority'] ) . '</li>';
                }
            }
            ?>
        </ol>
        <?php
    }

    /**
     * @param array<string,mixed> $settings Settings.
     * @param array<string,mixed> $sync_meta Sync meta.
     * @param TMW_CR_Slot_CR_API_Client $client Client.
     *
     * @return void
     */
    protected function render_settings_tab( $settings, $sync_meta, $client ) {
        ?>
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
                            <p><strong><?php esc_html_e( 'Stored key', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong> <?php echo esc_html( $client->get_masked_api_key() ? $client->get_masked_api_key() : __( 'Not configured', 'tmw-cr-slot-sidebar-banner' ) ); ?></p>
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

            <?php submit_button( __( 'Save Banner Settings', 'tmw-cr-slot-sidebar-banner' ) ); ?>
        </form>

        <div class="tmw-cr-actions-row">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_test_connection' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_test_connection" />
                <?php submit_button( __( 'Test Connection', 'tmw-cr-slot-sidebar-banner' ), 'secondary', 'submit', false ); ?>
            </form>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                <?php wp_nonce_field( 'tmw_cr_slot_banner_sync_offers' ); ?>
                <input type="hidden" name="action" value="tmw_cr_slot_banner_sync_offers" />
                <?php submit_button( __( 'Sync Offers Now', 'tmw-cr-slot-sidebar-banner' ), 'primary', 'submit', false ); ?>
            </form>
            <p>
                <strong><?php esc_html_e( 'Last sync', 'tmw-cr-slot-sidebar-banner' ); ?>:</strong>
                <?php echo ! empty( $sync_meta['last_synced_at'] ) ? esc_html( (string) $sync_meta['last_synced_at'] ) : esc_html__( 'Never', 'tmw-cr-slot-sidebar-banner' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * @param string $label Label.
     * @param string $value Value.
     *
     * @return void
     */
    protected function render_summary_card( $label, $value ) {
        ?>
        <div class="tmw-cr-card">
            <p class="tmw-cr-card__label"><?php echo esc_html( $label ); ?></p>
            <p class="tmw-cr-card__value"><?php echo esc_html( $value ); ?></p>
        </div>
        <?php
    }

    /**
     * @param string $name Name.
     * @param string $value Value.
     * @param array<string,string> $options Options.
     *
     * @return void
     */
    protected function render_filter_select( $name, $value, $options ) {
        echo '<select name="' . esc_attr( $name ) . '">';
        foreach ( $options as $option_value => $label ) {
            echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
    }

    /**
     * @param string $key Column key.
     * @param string $label Label.
     * @param array<string,mixed> $args Args.
     *
     * @return void
     */
    protected function render_sort_link_header( $key, $label, $args ) {
        $order     = ( isset( $args['sort_by'] ) && $key === $args['sort_by'] && 'asc' === $args['sort_order'] ) ? 'desc' : 'asc';
        $url_args  = array_merge( $args, array( 'sort_by' => $key, 'sort_order' => $order, 'paged' => 1 ) );
        $url_args['tab']  = 'offers';
        $url_args['page'] = 'tmw-cr-slot-sidebar-banner';

        echo '<th><a href="' . esc_url( add_query_arg( $url_args, admin_url( 'options-general.php' ) ) ) . '">' . esc_html( $label ) . '</a></th>';
    }

    /**
     * @param int $current Current page.
     * @param int $total Total pages.
     * @param array<string,mixed> $args Query args.
     *
     * @return void
     */
    protected function render_pagination( $current, $total, $args ) {
        if ( $total <= 1 ) {
            return;
        }

        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ( $page = 1; $page <= $total; $page++ ) {
            $url_args           = $args;
            $url_args['page']   = 'tmw-cr-slot-sidebar-banner';
            $url_args['tab']    = 'offers';
            $url_args['paged']  = $page;
            $url                = add_query_arg( $url_args, admin_url( 'options-general.php' ) );
            $class              = $page === $current ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $page ) . '</a> ';
        }
        echo '</div></div>';
    }

    /**
     * @param array<string,mixed> $offer Offer.
     *
     * @return string
     */
    protected function format_payout( $offer ) {
        $parts = array_filter(
            array(
                ! empty( $offer['default_payout'] ) ? (string) $offer['default_payout'] : '',
                ! empty( $offer['percent_payout'] ) ? (string) $offer['percent_payout'] . '%' : '',
                ! empty( $offer['currency'] ) ? (string) $offer['currency'] : '',
                ! empty( $offer['payout_type'] ) ? (string) $offer['payout_type'] : '',
            )
        );

        return ! empty( $parts ) ? implode( ' / ', $parts ) : '-';
    }

    /**
     * @param string $label Label.
     * @param string $variant Variant.
     *
     * @return void
     */
    protected function render_badge( $label, $variant ) {
        echo '<span class="tmw-cr-badge tmw-cr-badge--' . esc_attr( sanitize_key( $variant ) ) . '">' . esc_html( $label ) . '</span>';
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
                    'page'                => 'tmw-cr-slot-sidebar-banner',
                    'tmw_cr_slot_notice'  => sanitize_key( $notice_type ),
                    'tmw_cr_slot_message' => $message,
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
