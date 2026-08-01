<?php
/**
 * BusinessVance Settings
 *
 * Comprehensive admin settings page with 5 tabs: General, Portal Settings,
 * Agreement, Email Notifications, WooCommerce Integration.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Settings {

    /** @var string Option key in wp_options */
    const OPTION_KEY = 'bv_settings';

    /** @var array Default settings values */
    private static $defaults = array(
        // General
        'company_name'            => 'BusinessVance Consulting',
        'company_tagline'         => 'Insight. Strategy. Success.',
        'primary_color'           => '#002B5C',
        'secondary_color'         => '#008080',
        'accent_color'            => '#D4AF37',
        'logo_url'                => '',
        'favicon_url'             => '',
        'phone_number'            => '',
        'email_address'           => '',
        'physical_address'        => '',
        'consultant_email'        => '',
        'portal_url'              => '',
        'tutor_dashboard_url'     => '',

        // Portal Settings
        'portal_enabled'          => 'yes',
        'portal_welcome_title'    => 'Welcome to Your Client Portal',
        'portal_welcome_message'  => 'Manage your projects, sign agreements, and communicate with your consultant from one convenient place.',
        'portal_login_gate'       => 'yes',
        'portal_login_message'    => 'Please log in with your WooCommerce account to access the client portal.',
        'portal_show_profile'     => 'yes',
        'portal_show_timeline'    => 'yes',
        'portal_show_documents'   => 'yes',

        // Agreement
        'agreement_enabled'       => 'yes',
        'agreement_title'         => 'Client Service Agreement',
        'agreement_text'          => "CLIENT SERVICE AGREEMENT\n\n1. SERVICES\nThe Consultant agrees to provide the services described in the project proposal.\n\n2. FEES & PAYMENT\nClient agrees to pay the fees as outlined in the accepted proposal or WooCommerce order.\n\n3. CONFIDENTIALITY\nBoth parties agree to maintain strict confidentiality regarding all proprietary information shared during the engagement.\n\n4. TERM & TERMINATION\nThis agreement remains in effect until all deliverables are completed or terminated by either party with 30 days written notice.\n\n5. INTELLECTUAL PROPERTY\nAll deliverables created during the engagement become the property of the Client upon full payment.\n\n6. LIMITATION OF LIABILITY\nThe Consultant's total liability shall not exceed the fees paid under this agreement.",
        'agreement_signature_required' => 'yes',

        // Email Notifications
        'email_project_created'       => 'yes',
        'email_project_created_subject' => 'Your Project Has Been Created - {project_number}',
        'email_project_created_body'   => "Dear {client_name},\n\nYour project {project_number} has been created successfully.\n\nService(s): {services}\n\nYou can access your client portal here: {portal_url}\n\nBest regards,\n{company_name}",
        'email_agreement_ready'       => 'yes',
        'email_agreement_ready_subject' => 'Agreement Ready for Review - {project_number}',
        'email_agreement_ready_body'   => "Dear {client_name},\n\nYour service agreement for project {project_number} is ready for review.\n\nPlease log in to your client portal to review and sign the agreement.\n{portal_url}\n\nBest regards,\n{company_name}",
        'email_report_ready'           => 'yes',
        'email_report_ready_subject'   => 'New Report Available - {project_number}',
        'email_report_ready_body'      => "Dear {client_name},\n\nA new report has been uploaded for your project {project_number}.\n\nPlease log in to your client portal to download it.\n{portal_url}\n\nBest regards,\n{company_name}",

        // WooCommerce
        'wc_auto_create_project'    => 'yes',
        'wc_status_triggers'        => 'completed',
        'wc_product_category'       => '',
        'wc_link_services'          => 'yes',

        // Services Page Appearance
        'services_page_title'          => 'Our Services',
        'services_page_subtitle'       => 'Professional business reports and advisory services to help you make confident, informed decisions.',
        'services_header_style'         => 'navy',           // navy, gradient, minimal
        'services_show_header'         => 'yes',
        'services_show_categories'     => 'yes',
        'services_show_plans'          => 'yes',
        'services_currency_symbol'      => 'R',
        'services_currency_position'   => 'before',
        'services_enable_animations'   => 'yes',
        'services_layout_style'        => 'table',          // table, cards
        'services_button_style'        => 'primary',       // primary, gold, teal
        'services_footer_text'         => '',
        'services_show_trust_badges'   => 'yes',

        // Consultant Dashboard
        'cd_enabled'                   => 'yes',
        'cd_welcome_title'             => 'Consultant Dashboard',
        'cd_show_activity_log'         => 'yes',
        'cd_show_messages'             => 'yes',
        'cd_show_notes'                => 'yes',
        'cd_default_status'            => 'awaiting-agreement',
        'cd_items_per_page'            => '20',
        'cd_auto_notify_consultant'   => 'yes',

        // Client Portal Appearance
        'portal_header_color'          => '#002B5C',
        'portal_accent_color'          => '#2A9D8F',
        'portal_button_color'          => '#D4AF37',
        'portal_sidebar_color'         => '#f8f9fb',
        'portal_card_border_color'     => '#e5e7eb',
        'portal_tab_style'             => 'underline',      // underline, pill
        'portal_show_overview'         => 'yes',
        'portal_show_questionnaire'    => 'yes',
        'portal_show_messages'         => 'yes',
        'portal_show_reports'          => 'yes',
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bv_export_data', array( $this, 'ajax_export_data' ) );
        add_action( 'wp_ajax_bv_import_data', array( $this, 'ajax_import_data' ) );
        add_action( 'wp_ajax_bv_purge_all_data', array( $this, 'ajax_purge_all_data' ) );
    }

    /**
     * Add Settings submenu page under BusinessVance
     */
    public function add_menu_pages() {
        add_submenu_page(
            'businessvance',
            __( 'Settings', 'businessvance-services-manager' ),
            __( 'Settings', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings and fields
     */
    public function register_settings() {
        // Main option group
        register_setting( 'bv_settings_group', self::OPTION_KEY, array(
            'sanitize_callback' => array( $this, 'sanitize_settings' ),
        ) );
    }

    /**
     * Sanitize all settings on save
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $defaults  = self::get_defaults();

        foreach ( $defaults as $key => $default ) {
            if ( isset( $input[ $key ] ) ) {
                $sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
            } else {
                $sanitized[ $key ] = $default;
            }
        }

        // Allow longer text for agreement and email bodies
        if ( isset( $input['agreement_text'] ) ) {
            $sanitized['agreement_text'] = wp_kses_post( $input['agreement_text'] );
        }
        if ( isset( $input['portal_welcome_message'] ) ) {
            $sanitized['portal_welcome_message'] = sanitize_textarea_field( $input['portal_welcome_message'] );
        }
        if ( isset( $input['email_project_created_body'] ) ) {
            $sanitized['email_project_created_body'] = sanitize_textarea_field( $input['email_project_created_body'] );
        }
        if ( isset( $input['email_agreement_ready_body'] ) ) {
            $sanitized['email_agreement_ready_body'] = sanitize_textarea_field( $input['email_agreement_ready_body'] );
        }
        if ( isset( $input['email_report_ready_body'] ) ) {
            $sanitized['email_report_ready_body'] = sanitize_textarea_field( $input['email_report_ready_body'] );
        }
        if ( isset( $input['physical_address'] ) ) {
            $sanitized['physical_address'] = sanitize_textarea_field( $input['physical_address'] );
        }

        // Sanitize URLs
        if ( isset( $input['logo_url'] ) ) {
            $sanitized['logo_url'] = esc_url_raw( $input['logo_url'] );
        }
        if ( isset( $input['favicon_url'] ) ) {
            $sanitized['favicon_url'] = esc_url_raw( $input['favicon_url'] );
        }

        return $sanitized;
    }

    /**
     * Enqueue admin assets for settings page
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'businessvance_page_businessvance-settings' ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();

        wp_enqueue_script(
            'bv-settings-js',
            BV_PLUGIN_URL . 'assets/js/settings.js',
            array( 'jquery', 'wp-color-picker' ),
            BV_VERSION,
            true
        );

        wp_enqueue_style(
            'bv-settings-css',
            BV_PLUGIN_URL . 'assets/css/settings.css',
            array(),
            BV_VERSION
        );
    }

    /**
     * Get all settings (merged with defaults)
     *
     * @return array
     */
    public static function get_settings() {
        $saved   = get_option( self::OPTION_KEY, array() );
        $defaults = self::get_defaults();

        return array_merge( $defaults, $saved );
    }

    /**
     * Get a single setting value
     *
     * @param string $key
     * @return mixed
     */
    public static function get( $key ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
    }

    /**
     * Get defaults
     *
     * @return array
     */
    public static function get_defaults() {
        return self::$defaults;
    }

    /**
     * Render the main settings page
     */
    public function render_settings_page() {
        $settings = self::get_settings();
        $tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $page_url = admin_url( 'admin.php?page=businessvance-settings' );
        ?>
        <div class="wrap bv-settings-wrap">
            <div class="bv-settings-header">
                <h1><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'BusinessVance Settings', 'businessvance-services-manager' ); ?></h1>
                <p class="bv-settings-subtitle"><?php esc_html_e( 'Configure your plugin settings, branding, portal options, and WooCommerce integration.', 'businessvance-services-manager' ); ?></p>
            </div>

            <div class="bv-settings-nav">
                <nav class="nav-tab-wrapper bv-nav-tab-wrapper">
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'general', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-admin-site"></span> <?php esc_html_e( 'General', 'businessvance-services-manager' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'portal', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'portal' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Portal Settings', 'businessvance-services-manager' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'appearance', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Appearance', 'businessvance-services-manager' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'agreement', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'agreement' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-file-alt"></span> <?php esc_html_e( 'Agreement', 'businessvance-services-manager' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'email', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'email' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Email Notifications', 'businessvance-services-manager' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'woocommerce', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'WooCommerce', 'businessvance-services-manager' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'consultant', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'consultant' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Consultant Dashboard', 'businessvance-services-manager' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'data', $page_url ) ); ?>"
                       class="nav-tab <?php echo $tab === 'data' ? 'nav-tab-active' : ''; ?>"
                       style="color: #DC2626;">
                        <span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Data', 'businessvance-services-manager' ); ?>
                    </a>
                </nav>
            </div>

            <div class="bv-settings-content">
                <form method="post" action="options.php">
                    <?php settings_fields( 'bv_settings_group' ); ?>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'general' ? 'block' : 'none'; ?>;">
                        <?php $this->render_general_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'portal' ? 'block' : 'none'; ?>;">
                        <?php $this->render_portal_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'appearance' ? 'block' : 'none'; ?>;">
                        <?php $this->render_appearance_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'consultant' ? 'block' : 'none'; ?>;">
                        <?php $this->render_consultant_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'agreement' ? 'block' : 'none'; ?>;">
                        <?php $this->render_agreement_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'email' ? 'block' : 'none'; ?>;">
                        <?php $this->render_email_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'woocommerce' ? 'block' : 'none'; ?>;">
                        <?php $this->render_woocommerce_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'data' ? 'block' : 'none'; ?>;">
                        <?php $this->render_data_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-footer">
                        <button type="submit" name="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-saved" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php esc_html_e( 'Save Settings', 'businessvance-services-manager' ); ?>
                        </button>
                        <span class="bv-settings-reset-link">
                            <a href="<?php echo esc_url( add_query_arg( array(
                                'page'   => 'businessvance-settings',
                                'tab'    => $tab,
                                'action' => 'reset',
                                '_wpnonce' => wp_create_nonce( 'bv_reset_settings' ),
                            ), admin_url( 'admin.php' ) ) ); ?>"
                               class="button button-link-delete"
                               onclick="return confirm('<?php esc_attr_e( 'Reset all settings to defaults? This cannot be undone.', 'businessvance-services-manager' ); ?>');">
                                <?php esc_html_e( 'Reset to Defaults', 'businessvance-services-manager' ); ?>
                            </a>
                        </span>
                    </div>
                </form>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Color picker
            $('.bv-color-picker').wpColorPicker();

            // Media uploader
            $('.bv-media-upload').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var field  = $('#' + button.data('field'));
                var preview = $('#' + button.data('preview'));

                var frame = wp.media({
                    title: '<?php esc_attr_e( "Select Image", "businessvance-services-manager" ); ?>',
                    button: { text: '<?php esc_attr_e( "Use this image", "businessvance-services-manager" ); ?>' },
                    multiple: false
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    field.val(attachment.url);
                    if (preview.length) {
                        preview.html('<img src="' + attachment.url + '" style="max-width:200px;max-height:100px;border:1px solid #ccc;border-radius:4px;padding:4px;">');
                    }
                });

                frame.open();
            });

            // Remove image button
            $('.bv-media-remove').on('click', function(e) {
                e.preventDefault();
                var field   = $('#' + $(this).data('field'));
                var preview = $('#' + $(this).data('preview'));
                field.val('');
                preview.html('');
            });

            // Tab switching via JS fallback
            $('.bv-nav-tab-wrapper .nav-tab').on('click', function(e) {
                // Links handle navigation already
            });
        });
        </script>
        <?php

        // Handle reset action
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'reset' && isset( $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'bv_reset_settings' ) && current_user_can( 'manage_options' ) ) {
                delete_option( self::OPTION_KEY );
                wp_safe_redirect( admin_url( 'admin.php?page=businessvance-settings&tab=' . $tab . '&reset=success' ) );
                exit;
            }
        }

        // Show reset success message
        if ( isset( $_GET['reset'] ) && $_GET['reset'] === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings have been reset to defaults.', 'businessvance-services-manager' ) . '</p></div>';
        }
    }

    /**
     * Render General Settings tab
     */
    private function render_general_tab( $settings ) {
        ?>
        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Company Branding', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Set your company name, tagline, and brand colors used across the plugin.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_company_name"><?php esc_html_e( 'Company Name', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_company_name" name="bv_settings[company_name]"
                               value="<?php echo esc_attr( $settings['company_name'] ); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_company_tagline"><?php esc_html_e( 'Company Tagline', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_company_tagline" name="bv_settings[company_tagline]"
                               value="<?php echo esc_attr( $settings['company_tagline'] ); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Company Logo', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_logo_url" name="bv_settings[logo_url]"
                               value="<?php echo esc_attr( $settings['logo_url'] ); ?>"
                               class="regular-text bv-field-url" />
                        <button type="button" class="button bv-media-upload"
                                data-field="bv_logo_url" data-preview="bv_logo_preview">
                            <?php esc_html_e( 'Select Image', 'businessvance-services-manager' ); ?>
                        </button>
                        <button type="button" class="button bv-media-remove"
                                data-field="bv_logo_url" data-preview="bv_logo_preview"
                                style="display: <?php echo empty( $settings['logo_url'] ) ? 'none' : 'inline-block'; ?>;">
                            <?php esc_html_e( 'Remove', 'businessvance-services-manager' ); ?>
                        </button>
                        <div id="bv_logo_preview" class="bv-image-preview">
                            <?php if ( ! empty( $settings['logo_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $settings['logo_url'] ); ?>" style="max-width:200px;max-height:100px;border:1px solid #ccc;border-radius:4px;padding:4px;" />
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Favicon', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_favicon_url" name="bv_settings[favicon_url]"
                               value="<?php echo esc_attr( $settings['favicon_url'] ); ?>"
                               class="regular-text bv-field-url" />
                        <button type="button" class="button bv-media-upload"
                                data-field="bv_favicon_url" data-preview="bv_favicon_preview">
                            <?php esc_html_e( 'Select Image', 'businessvance-services-manager' ); ?>
                        </button>
                        <button type="button" class="button bv-media-remove"
                                data-field="bv_favicon_url" data-preview="bv_favicon_preview"
                                style="display: <?php echo empty( $settings['favicon_url'] ) ? 'none' : 'inline-block'; ?>;">
                            <?php esc_html_e( 'Remove', 'businessvance-services-manager' ); ?>
                        </button>
                        <div id="bv_favicon_preview" class="bv-image-preview">
                            <?php if ( ! empty( $settings['favicon_url'] ) ) : ?>
                                <img src="<?php echo esc_url( $settings['favicon_url'] ); ?>" style="max-width:48px;max-height:48px;border:1px solid #ccc;border-radius:4px;padding:4px;" />
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Brand Colors', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'These colors are used in the front-end services page, client portal, and shortcodes.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_primary_color"><?php esc_html_e( 'Primary Color', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_primary_color" name="bv_settings[primary_color]"
                               value="<?php echo esc_attr( $settings['primary_color'] ); ?>"
                               class="bv-color-picker" />
                        <p class="description"><?php esc_html_e( 'Used for headers, buttons, and main branding elements.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_secondary_color"><?php esc_html_e( 'Secondary Color', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_secondary_color" name="bv_settings[secondary_color]"
                               value="<?php echo esc_attr( $settings['secondary_color'] ); ?>"
                               class="bv-color-picker" />
                        <p class="description"><?php esc_html_e( 'Used for accents, highlights, and secondary elements.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_accent_color"><?php esc_html_e( 'Accent Color', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_accent_color" name="bv_settings[accent_color]"
                               value="<?php echo esc_attr( $settings['accent_color'] ); ?>"
                               class="bv-color-picker" />
                        <p class="description"><?php esc_html_e( 'Used for badges, featured items, and special highlights.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Contact Information', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Contact details displayed in the services page footer and email templates.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_phone_number"><?php esc_html_e( 'Phone Number', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="tel" id="bv_phone_number" name="bv_settings[phone_number]"
                               value="<?php echo esc_attr( $settings['phone_number'] ); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_email_address"><?php esc_html_e( 'Email Address', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="email" id="bv_email_address" name="bv_settings[email_address]"
                               value="<?php echo esc_attr( $settings['email_address'] ); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_physical_address"><?php esc_html_e( 'Physical Address', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_physical_address" name="bv_settings[physical_address]"
                                  rows="3" class="large-text"><?php echo esc_textarea( $settings['physical_address'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Client Portal URL', 'businessvance-services-manager' ); ?></th>
                    <td>
                        <input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[portal_url]" value="<?php echo esc_attr( $settings['portal_url'] ?? '' ); ?>" class="regular-text" placeholder="https://yoursite.com/client-portal/" />
                        <p class="description"><?php esc_html_e( 'Full URL to the page containing the [businessvance_client_portal] shortcode.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Portal Settings tab
     */
    private function render_portal_tab( $settings ) {
        ?>
        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Client Portal Configuration', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Configure the client portal behaviour, login gate, and visible sections.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_portal_enabled"><?php esc_html_e( 'Enable Client Portal', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" id="bv_portal_enabled" name="bv_settings[portal_enabled]"
                                   value="yes" <?php checked( $settings['portal_enabled'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Enable the client portal shortcode', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_portal_welcome_title"><?php esc_html_e( 'Welcome Title', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_portal_welcome_title" name="bv_settings[portal_welcome_title]"
                               value="<?php echo esc_attr( $settings['portal_welcome_title'] ); ?>"
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_portal_welcome_message"><?php esc_html_e( 'Welcome Message', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_portal_welcome_message" name="bv_settings[portal_welcome_message]"
                                  rows="3" class="large-text"><?php echo esc_textarea( $settings['portal_welcome_message'] ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Login Gate', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Control whether users must be logged in with a WooCommerce account to access the portal.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_portal_login_gate"><?php esc_html_e( 'Require Login', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" id="bv_portal_login_gate" name="bv_settings[portal_login_gate]"
                                   value="yes" <?php checked( $settings['portal_login_gate'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Show login form to non-logged-in visitors', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_portal_login_message"><?php esc_html_e( 'Login Message', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_portal_login_message" name="bv_settings[portal_login_message]"
                                  rows="2" class="large-text"><?php echo esc_textarea( $settings['portal_login_message'] ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Portal Sections', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Toggle which sections are visible in the client portal.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Profile', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[portal_show_profile]"
                                   value="yes" <?php checked( $settings['portal_show_profile'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Display client profile section', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Timeline', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[portal_show_timeline]"
                                   value="yes" <?php checked( $settings['portal_show_timeline'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Display project timeline/activity', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Documents', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[portal_show_documents]"
                                   value="yes" <?php checked( $settings['portal_show_documents'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Display document upload/download section', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section bv-settings-info-box">
            <h3><?php esc_html_e( 'Portal Shortcode', 'businessvance-services-manager' ); ?></h3>
            <p><?php esc_html_e( 'Add this shortcode to any page to display the client portal:', 'businessvance-services-manager' ); ?></p>
            <code style="display:inline-block;padding:8px 16px;background:#f0f0f1;border-radius:4px;font-size:14px;">[businessvance_client_portal]</code>
        </div>
        <?php
    }

    /**
     * Render Agreement Settings tab
     */
    private function render_agreement_tab( $settings ) {
        ?>
        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Service Agreement', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Configure the default client service agreement that clients sign in the portal.', 'businessvance-services-manager' ); ?></p>

            <div class="bv-settings-info-box" style="background:#EBF5FF;border:1px solid #93C5FD;border-radius:8px;padding:16px 20px;margin:16px 0;">
                <p style="margin:0 0 8px;color:#1E40AF;font-weight:600;">
                    <span class="dashicons dashicons-migrate" style="vertical-align:middle;margin-right:4px;"></span>
                    <?php esc_html_e( 'Agreement Templates Manager', 'businessvance-services-manager' ); ?>
                </p>
                <p style="margin:0 0 12px;color:#1E3A8A;font-size:13px;">
                    <?php esc_html_e( 'Create and manage multiple agreement templates (NDA, Service Agreement, Confidentiality, Custom). Each template can be assigned per-service in the Services editor.', 'businessvance-services-manager' ); ?>
                </p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=businessvance-agreements' ) ); ?>" class="button button-primary" style="background:#002B5C;border-color:#002B5C;">
                    <span class="dashicons dashicons-file-alt" style="vertical-align:middle;margin-right:4px;"></span>
                    <?php esc_html_e( 'Open Agreement Manager', 'businessvance-services-manager' ); ?>
                </a>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_agreement_enabled"><?php esc_html_e( 'Enable Agreement', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" id="bv_agreement_enabled" name="bv_settings[agreement_enabled]"
                                   value="yes" <?php checked( $settings['agreement_enabled'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Require agreement signing before project starts', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_agreement_title"><?php esc_html_e( 'Agreement Title', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_agreement_title" name="bv_settings[agreement_title]"
                               value="<?php echo esc_attr( $settings['agreement_title'] ); ?>"
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_agreement_text"><?php esc_html_e( 'Agreement Text', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor( $settings['agreement_text'], 'bv_agreement_text', array(
                            'textarea_name' => 'bv_settings[agreement_text]',
                            'textarea_rows' => 15,
                            'media_buttons' => false,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ) );
                        ?>
                        <p class="description"><?php esc_html_e( 'You can use HTML for formatting. This is displayed to clients for review and signature.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_agreement_signature_required"><?php esc_html_e( 'Require Signature', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" id="bv_agreement_signature_required"
                                   name="bv_settings[agreement_signature_required]"
                                   value="yes" <?php checked( $settings['agreement_signature_required'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Clients must type their full name as signature', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Email Notifications tab
     */
    private function render_email_tab( $settings ) {
        ?>
        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Email Templates', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Configure email notifications sent to clients. Available placeholders:', 'businessvance-services-manager' ); ?></p>
            <div class="bv-placeholders-info">
                <strong><?php esc_html_e( 'Placeholders:', 'businessvance-services-manager' ); ?></strong>
                <code>{client_name}</code>,
                <code>{project_number}</code>,
                <code>{services}</code>,
                <code>{portal_url}</code>,
                <code>{company_name}</code>,
                <code>{site_name}</code>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Consultant Notification Email', 'businessvance-services-manager' ); ?></th>
                    <td>
                        <input type="email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[consultant_email]" value="<?php echo esc_attr( $settings['consultant_email'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'consultant@yourcompany.com', 'businessvance-services-manager' ); ?>" />
                        <p class="description"><?php esc_html_e( 'Email address to receive notifications when clients complete actions (sign agreement, submit questionnaire, upload documents). Defaults to admin email if empty.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top:20px;"><?php esc_html_e( 'Project Created Email', 'businessvance-services-manager' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Enable', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[email_project_created]"
                                   value="yes" <?php checked( $settings['email_project_created'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Send email when a new project is created', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_email_project_created_subject"><?php esc_html_e( 'Subject', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_email_project_created_subject"
                               name="bv_settings[email_project_created_subject]"
                               value="<?php echo esc_attr( $settings['email_project_created_subject'] ); ?>"
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_email_project_created_body"><?php esc_html_e( 'Body', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_email_project_created_body"
                                  name="bv_settings[email_project_created_body]"
                                  rows="6" class="large-text"><?php echo esc_textarea( $settings['email_project_created_body'] ); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Agreement Ready Email', 'businessvance-services-manager' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Enable', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[email_agreement_ready]"
                                   value="yes" <?php checked( $settings['email_agreement_ready'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Send email when agreement is ready for signing', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_email_agreement_ready_subject"><?php esc_html_e( 'Subject', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_email_agreement_ready_subject"
                               name="bv_settings[email_agreement_ready_subject]"
                               value="<?php echo esc_attr( $settings['email_agreement_ready_subject'] ); ?>"
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_email_agreement_ready_body"><?php esc_html_e( 'Body', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_email_agreement_ready_body"
                                  name="bv_settings[email_agreement_ready_body]"
                                  rows="6" class="large-text"><?php echo esc_textarea( $settings['email_agreement_ready_body'] ); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Report Ready Email', 'businessvance-services-manager' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Enable', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[email_report_ready]"
                                   value="yes" <?php checked( $settings['email_report_ready'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Send email when a report is uploaded', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_email_report_ready_subject"><?php esc_html_e( 'Subject', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_email_report_ready_subject"
                               name="bv_settings[email_report_ready_subject]"
                               value="<?php echo esc_attr( $settings['email_report_ready_subject'] ); ?>"
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_email_report_ready_body"><?php esc_html_e( 'Body', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_email_report_ready_body"
                                  name="bv_settings[email_report_ready_body]"
                                  rows="6" class="large-text"><?php echo esc_textarea( $settings['email_report_ready_body'] ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render WooCommerce Integration tab
     */
    private function render_woocommerce_tab( $settings ) {
        $wc_active = class_exists( 'WooCommerce' );
        ?>
        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'WooCommerce Integration', 'businessvance-services-manager' ); ?></h2>

            <?php if ( ! $wc_active ) : ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php esc_html_e( 'WooCommerce is not active.', 'businessvance-services-manager' ); ?></strong>
                    <?php esc_html_e( 'Please install and activate WooCommerce to use these features.', 'businessvance-services-manager' ); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-success inline">
                    <p><strong><?php esc_html_e( 'WooCommerce is active.', 'businessvance-services-manager' ); ?></strong>
                    <?php esc_html_e( 'HPOS compatibility has been declared.', 'businessvance-services-manager' ); ?></p>
                </div>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_wc_auto_create_project"><?php esc_html_e( 'Auto-Create Projects', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" id="bv_wc_auto_create_project"
                                   name="bv_settings[wc_auto_create_project]"
                                   value="yes" <?php checked( $settings['wc_auto_create_project'], 'yes' ); ?>
                                   <?php echo ! $wc_active ? 'disabled' : ''; ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Automatically create projects when WooCommerce orders match linked services', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_wc_status_triggers"><?php esc_html_e( 'Order Status Triggers', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="bv_wc_status_triggers" name="bv_settings[wc_status_triggers]"
                                class="bv-multiselect" multiple
                                <?php echo ! $wc_active ? 'disabled' : ''; ?>>
                            <option value="processing" <?php echo in_array( 'processing', explode( ',', $settings['wc_status_triggers'] ) ) ? 'selected' : ''; ?>>
                                <?php esc_html_e( 'Processing', 'businessvance-services-manager' ); ?>
                            </option>
                            <option value="completed" <?php echo in_array( 'completed', explode( ',', $settings['wc_status_triggers'] ) ) ? 'selected' : ''; ?>>
                                <?php esc_html_e( 'Completed', 'businessvance-services-manager' ); ?>
                            </option>
                            <option value="on-hold" <?php echo in_array( 'on-hold', explode( ',', $settings['wc_status_triggers'] ) ) ? 'selected' : ''; ?>>
                                <?php esc_html_e( 'On Hold', 'businessvance-services-manager' ); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple. Projects are created when orders reach these statuses.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_wc_link_services"><?php esc_html_e( 'Link WC Products to Services', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" id="bv_wc_link_services" name="bv_settings[wc_link_services]"
                                   value="yes" <?php checked( $settings['wc_link_services'], 'yes' ); ?>
                                   <?php echo ! $wc_active ? 'disabled' : ''; ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Enable the WooCommerce Product ID field in service editing', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Client Portal URL', 'businessvance-services-manager' ); ?></th>
                    <td>
                        <input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[portal_url]" value="<?php echo esc_attr( $settings['portal_url'] ?? '' ); ?>" class="regular-text" placeholder="https://yoursite.com/client-portal/" />
                        <p class="description"><?php esc_html_e( 'The URL of your client portal page. Used for post-purchase redirects and email links. Leave blank to auto-detect.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Tutor LMS Dashboard URL', 'businessvance-services-manager' ); ?></th>
                    <td>
                        <input type="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[tutor_dashboard_url]" value="<?php echo esc_attr( $settings['tutor_dashboard_url'] ?? '' ); ?>" class="regular-text" placeholder="https://yoursite.com/tutor-dashboard/" />
                        <p class="description"><?php esc_html_e( 'The URL of your Tutor LMS dashboard page. Used when a client buys a course alongside a service. Leave blank to auto-detect.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section bv-settings-info-box">
            <h3><?php esc_html_e( 'How It Works', 'businessvance-services-manager' ); ?></h3>
            <ol style="line-height:2;">
                <li><?php esc_html_e( 'Create WooCommerce products for your services (Simple or Subscription products).', 'businessvance-services-manager' ); ?></li>
                <li><?php esc_html_e( 'In the Services admin, edit each service and assign the corresponding WooCommerce Product ID.', 'businessvance-services-manager' ); ?></li>
                <li><?php esc_html_e( 'When a customer purchases a linked product, a project is auto-created in the system.', 'businessvance-services-manager' ); ?></li>
                <li><?php esc_html_e( 'The customer can access their project via the Client Portal shortcode.', 'businessvance-services-manager' ); ?></li>
            </ol>
        </div>
        <?php
    }

    /**
     * Render Appearance Settings tab
     */
    private function render_appearance_tab( $settings ) {
        ?>
        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Services Page Appearance', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Customize the frontend services page rendered by the [businessvance_services] shortcode.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_services_page_title"><?php esc_html_e( 'Page Title', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_services_page_title" name="bv_settings[services_page_title]"
                               value="<?php echo esc_attr( $settings['services_page_title'] ); ?>"
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_services_page_subtitle"><?php esc_html_e( 'Page Subtitle', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_services_page_subtitle" name="bv_settings[services_page_subtitle]"
                                  rows="2" class="large-text"><?php echo esc_textarea( $settings['services_page_subtitle'] ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Header', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[services_show_header]"
                                   value="yes" <?php checked( $settings['services_show_header'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Show the branded header section with logo and tagline', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_services_header_style"><?php esc_html_e( 'Header Style', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="bv_services_header_style" name="bv_settings[services_header_style]">
                            <option value="navy" <?php selected( $settings['services_header_style'], 'navy' ); ?>><?php esc_html_e( 'Navy Classic', 'businessvance-services-manager' ); ?></option>
                            <option value="gradient" <?php selected( $settings['services_header_style'], 'gradient' ); ?>><?php esc_html_e( 'Gradient', 'businessvance-services-manager' ); ?></option>
                            <option value="minimal" <?php selected( $settings['services_header_style'], 'minimal' ); ?>><?php esc_html_e( 'Minimal (No Background)', 'businessvance-services-manager' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Category Filter', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[services_show_categories]"
                                   value="yes" <?php checked( $settings['services_show_categories'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Show category filter buttons above services', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Plans Section', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[services_show_plans]"
                                   value="yes" <?php checked( $settings['services_show_plans'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Display subscription plans section', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_services_layout_style"><?php esc_html_e( 'Services Layout', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="bv_services_layout_style" name="bv_settings[services_layout_style]">
                            <option value="table" <?php selected( $settings['services_layout_style'], 'table' ); ?>><?php esc_html_e( 'Table (Desktop) / Cards (Mobile)', 'businessvance-services-manager' ); ?></option>
                            <option value="cards" <?php selected( $settings['services_layout_style'], 'cards' ); ?>><?php esc_html_e( 'Cards (Always)', 'businessvance-services-manager' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_services_button_style"><?php esc_html_e( 'Button Style', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="bv_services_button_style" name="bv_settings[services_button_style]">
                            <option value="primary" <?php selected( $settings['services_button_style'], 'primary' ); ?>><?php esc_html_e( 'Navy (Primary)', 'businessvance-services-manager' ); ?></option>
                            <option value="gold" <?php selected( $settings['services_button_style'], 'gold' ); ?>><?php esc_html_e( 'Gold (Accent)', 'businessvance-services-manager' ); ?></option>
                            <option value="teal" <?php selected( $settings['services_button_style'], 'teal' ); ?>><?php esc_html_e( 'Teal (Secondary)', 'businessvance-services-manager' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_services_currency_symbol"><?php esc_html_e( 'Currency Symbol', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_services_currency_symbol" name="bv_settings[services_currency_symbol]"
                               value="<?php echo esc_attr( $settings['services_currency_symbol'] ); ?>"
                               class="small-text" style="width:60px;" />
                        <select name="bv_settings[services_currency_position]" style="width:auto;">
                            <option value="before" <?php selected( $settings['services_currency_position'], 'before' ); ?>><?php esc_html_e( 'Before (R 100)', 'businessvance-services-manager' ); ?></option>
                            <option value="after" <?php selected( $settings['services_currency_position'], 'after' ); ?>><?php esc_html_e( 'After (100 R)', 'businessvance-services-manager' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Enable Animations', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[services_enable_animations]"
                                   value="yes" <?php checked( $settings['services_enable_animations'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Fade-in animations on page load', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Trust Badges', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[services_show_trust_badges]"
                                   value="yes" <?php checked( $settings['services_show_trust_badges'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Show trust badges in footer', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_services_footer_text"><?php esc_html_e( 'Footer Custom Text', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <textarea id="bv_services_footer_text" name="bv_settings[services_footer_text]"
                                  rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Leave blank to use default footer with contact details', 'businessvance-services-manager' ); ?>"><?php echo esc_textarea( $settings['services_footer_text'] ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Client Portal Appearance', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Customize colors and sections for the client portal.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_portal_header_color"><?php esc_html_e( 'Header Color', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_portal_header_color" name="bv_settings[portal_header_color]"
                               value="<?php echo esc_attr( $settings['portal_header_color'] ); ?>"
                               class="bv-color-picker" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_portal_accent_color"><?php esc_html_e( 'Accent Color', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_portal_accent_color" name="bv_settings[portal_accent_color]"
                               value="<?php echo esc_attr( $settings['portal_accent_color'] ); ?>"
                               class="bv-color-picker" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_portal_button_color"><?php esc_html_e( 'Button Color', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_portal_button_color" name="bv_settings[portal_button_color]"
                               value="<?php echo esc_attr( $settings['portal_button_color'] ); ?>"
                               class="bv-color-picker" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_portal_tab_style"><?php esc_html_e( 'Tab Style', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="bv_portal_tab_style" name="bv_settings[portal_tab_style]">
                            <option value="underline" <?php selected( $settings['portal_tab_style'], 'underline' ); ?>><?php esc_html_e( 'Underline Tabs', 'businessvance-services-manager' ); ?></option>
                            <option value="pill" <?php selected( $settings['portal_tab_style'], 'pill' ); ?>><?php esc_html_e( 'Pill Tabs', 'businessvance-services-manager' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Portal Sections Visibility', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="bv_settings[portal_show_overview]" value="yes" <?php checked( $settings['portal_show_overview'], 'yes' ); ?>>
                                <?php esc_html_e( 'Overview Tab', 'businessvance-services-manager' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="bv_settings[portal_show_questionnaire]" value="yes" <?php checked( $settings['portal_show_questionnaire'], 'yes' ); ?>>
                                <?php esc_html_e( 'Questionnaire Tab', 'businessvance-services-manager' ); ?>
                            </label>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="checkbox" name="bv_settings[portal_show_messages]" value="yes" <?php checked( $settings['portal_show_messages'], 'yes' ); ?>>
                                <?php esc_html_e( 'Messages Tab', 'businessvance-services-manager' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="bv_settings[portal_show_reports]" value="yes" <?php checked( $settings['portal_show_reports'], 'yes' ); ?>>
                                <?php esc_html_e( 'Reports Tab', 'businessvance-services-manager' ); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Consultant Dashboard Settings tab
     */
    private function render_consultant_tab( $settings ) {
        ?>
        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Consultant Dashboard Configuration', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Configure the consultant dashboard behaviour and defaults.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bv_cd_enabled"><?php esc_html_e( 'Enable Dashboard', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" id="bv_cd_enabled" name="bv_settings[cd_enabled]"
                                   value="yes" <?php checked( $settings['cd_enabled'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Enable the consultant dashboard shortcode', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_cd_welcome_title"><?php esc_html_e( 'Dashboard Title', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bv_cd_welcome_title" name="bv_settings[cd_welcome_title]"
                               value="<?php echo esc_attr( $settings['cd_welcome_title'] ); ?>"
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_cd_default_status"><?php esc_html_e( 'Default Project Status', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <select id="bv_cd_default_status" name="bv_settings[cd_default_status]">
                            <option value="awaiting-agreement" <?php selected( $settings['cd_default_status'], 'awaiting-agreement' ); ?>><?php esc_html_e( 'Awaiting Agreement', 'businessvance-services-manager' ); ?></option>
                            <option value="awaiting-questionnaire" <?php selected( $settings['cd_default_status'], 'awaiting-questionnaire' ); ?>><?php esc_html_e( 'Awaiting Questionnaire', 'businessvance-services-manager' ); ?></option>
                            <option value="awaiting-documents" <?php selected( $settings['cd_default_status'], 'awaiting-documents' ); ?>><?php esc_html_e( 'Awaiting Documents', 'businessvance-services-manager' ); ?></option>
                            <option value="in-progress" <?php selected( $settings['cd_default_status'], 'in-progress' ); ?>><?php esc_html_e( 'In Progress', 'businessvance-services-manager' ); ?></option>
                            <option value="quality-check" <?php selected( $settings['cd_default_status'], 'quality-check' ); ?>><?php esc_html_e( 'Quality Check', 'businessvance-services-manager' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Status assigned when a new project is created.', 'businessvance-services-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bv_cd_items_per_page"><?php esc_html_e( 'Items Per Page', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="bv_cd_items_per_page" name="bv_settings[cd_items_per_page]"
                               value="<?php echo esc_attr( $settings['cd_items_per_page'] ); ?>"
                               min="5" max="100" class="small-text" style="width:80px;" />
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section">
            <h2><?php esc_html_e( 'Dashboard Sections', 'businessvance-services-manager' ); ?></h2>
            <p><?php esc_html_e( 'Toggle which sections are visible in the consultant dashboard.', 'businessvance-services-manager' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Activity Log', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[cd_show_activity_log]"
                                   value="yes" <?php checked( $settings['cd_show_activity_log'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Display activity/timeline log for projects', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Messages', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[cd_show_messages]"
                                   value="yes" <?php checked( $settings['cd_show_messages'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Display messaging tab in project view', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Show Notes', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[cd_show_notes]"
                                   value="yes" <?php checked( $settings['cd_show_notes'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Display internal notes section', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Auto-Notify Consultant', 'businessvance-services-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="bv-toggle">
                            <input type="checkbox" name="bv_settings[cd_auto_notify_consultant]"
                                   value="yes" <?php checked( $settings['cd_auto_notify_consultant'], 'yes' ); ?> />
                            <span class="bv-toggle-slider"></span>
                            <span class="bv-toggle-label"><?php esc_html_e( 'Send email to consultant when client signs agreement, submits questionnaire, or uploads documents', 'businessvance-services-manager' ); ?></span>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="bv-settings-section bv-settings-info-box">
            <h3><?php esc_html_e( 'Consultant Dashboard Shortcode', 'businessvance-services-manager' ); ?></h3>
            <p><?php esc_html_e( 'Add this shortcode to a page to display the consultant dashboard:', 'businessvance-services-manager' ); ?></p>
            <code style="display:inline-block;padding:8px 16px;background:#f0f0f1;border-radius:4px;font-size:14px;">[businessvance_consultant_dashboard]</code>
        </div>
        <?php
    }

    // =========================================================================
    // Data Management Tab
    // =========================================================================

    /**
     * Render the Data Management tab with export/import/purge tools
     */
    private function render_data_tab( $settings ) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Count records in each table
        $counts = array();
        $bv_tables = array(
            'bv_categories'             => 'Categories',
            'bv_services'               => 'Services',
            'bv_plans'                  => 'Plans',
            'bv_projects'               => 'Projects',
            'bv_project_services'       => 'Project Services',
            'bv_project_agreements'     => 'Agreements',
            'bv_project_documents'       => 'Documents',
            'bv_project_reports'         => 'Reports',
            'bv_project_messages'        => 'Messages',
            'bv_project_notes'           => 'Notes',
            'bv_questionnaire_templates' => 'Questionnaire Templates',
            'bv_questionnaire_sections' => 'Questionnaire Sections',
            'bv_questionnaire_questions'=> 'Questionnaire Questions',
            'bv_questionnaire_responses'=> 'Questionnaire Responses',
            'bv_agreement_templates'     => 'Agreement Templates',
            'bv_service_agreements'     => 'Service-Agreement Links',
            'bv_activity_log'           => 'Activity Log',
        );

        foreach ( $bv_tables as $table_name => $label ) {
            $full_table = $prefix . $table_name;
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table}" );
            $counts[ $table_name ] = (int) $count;
        }

        $total = array_sum( $counts );
        $upload_dir = wp_upload_dir()['basedir'] . '/bv-documents';
        $file_count = 0;
        if ( is_dir( $upload_dir ) ) {
            $files = glob( $upload_dir . '/*' );
            $file_count = $files ? count( $files ) : 0;
        }

        $delete_on_uninstall = get_option( 'bv_delete_data_on_uninstall', 'no' );
        $nonce = wp_create_nonce( 'bv_data_management' );

        ?>
        <div class="bv-settings-section">
            <h2><span class="dashicons dashicons-database" style="color: #002B5C;"></span> <?php esc_html_e( 'Data Management', 'businessvance-services-manager' ); ?></h2>
            <p style="max-width:700px;"><?php esc_html_e( 'Manage your plugin data: export backups, import restored data, or purge everything. Your data is automatically preserved when you deactivate or delete the plugin unless you enable full cleanup below.', 'businessvance-services-manager' ); ?></p>

            <!-- Data Overview -->
            <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="margin: 0 0 14px; color: #002B5C;"><?php esc_html_e( 'Current Data Summary', 'businessvance-services-manager' ); ?></h4>
                <table class="widefat striped" style="max-width: 500px;">
                    <thead>
                        <tr><th><?php esc_html_e( 'Data Type', 'businessvance-services-manager' ); ?></th><th><?php esc_html_e( 'Records', 'businessvance-services-manager' ); ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $bv_tables as $table_name => $label ) : ?>
                        <tr>
                            <td><?php echo esc_html( $label ); ?></td>
                            <td><strong><?php echo number_format( $counts[ $table_name ] ); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr>
                            <td><strong><?php esc_html_e( 'Uploaded Files', 'businessvance-services-manager' ); ?></strong></td>
                            <td><strong><?php echo number_format( $file_count ); ?></strong></td>
                        </tr>
                        <tr style="background: #EBF5FF;">
                            <td><strong><?php esc_html_e( 'TOTAL', 'businessvance-services-manager' ); ?></strong></td>
                            <td><strong style="color: #002B5C; font-size: 16px;"><?php echo number_format( $total ); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Export -->
            <div style="background: #D1FAE5; border: 1px solid #6EE7B7; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="margin: 0 0 10px; color: #065F46;">
                    <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Export Backup', 'businessvance-services-manager' ); ?>
                </h4>
                <p style="margin: 0 0 14px; color: #047857;"><?php esc_html_e( 'Download a complete JSON backup of all plugin data (settings, services, projects, documents metadata, questionnaires, etc.). Use this to restore data after a plugin reinstall or migration.', 'businessvance-services-manager' ); ?></p>
                <button type="button" class="button button-primary button-large" onclick="bv_export_data(<?php echo esc_attr( json_encode( $nonce ) ); ?>)" style="background: #059669; border-color: #059669;">
                    <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php esc_html_e( 'Download Full Backup', 'businessvance-services-manager' ); ?>
                </button>
                <span id="bv-export-status" style="margin-left: 12px;"></span>
            </div>

            <!-- Import -->
            <div style="background: #DBEAFE; border: 1px solid #93C5FD; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="margin: 0 0 10px; color: #1E40AF;">
                    <span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Import Restore', 'businessvance-services-manager' ); ?>
                </h4>
                <p style="margin: 0 0 14px; color: #1E3A8A;"><?php esc_html_e( 'Restore data from a previously exported JSON backup file. Existing data with matching IDs will be updated. This action cannot be undone.', 'businessvance-services-manager' ); ?></p>
                <input type="file" id="bv-import-file" accept=".json" style="margin-bottom: 10px;" />
                <br>
                <button type="button" class="button button-primary button-large" onclick="bv_import_data(<?php echo esc_attr( json_encode( $nonce ) ); ?>)" style="background: #2563EB; border-color: #2563EB;">
                    <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php esc_html_e( 'Import from Backup', 'businessvance-services-manager' ); ?>
                </button>
                <span id="bv-import-status" style="margin-left: 12px;"></span>
            </div>

            <!-- Uninstall Behavior -->
            <div style="background: #FFF7ED; border: 1px solid #FDBA74; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="margin: 0 0 10px; color: #9A3412;">
                    <span class="dashicons dashicons-admin-plugins" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Uninstall Behavior', 'businessvance-services-manager' ); ?>
                </h4>
                <p style="margin: 0 0 14px; color: #9A3412;">
                    <?php esc_html_e( 'By default, when you delete the plugin, ALL your data (projects, documents, questionnaires, reports, etc.) is <strong>preserved</strong>. If you want a completely clean removal, enable the option below BEFORE deleting the plugin.', 'businessvance-services-manager' ); ?>
                </p>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #fff; border: 2px solid #FED7AA; border-radius: 6px; max-width: 500px;">
                    <input type="checkbox" id="bv-delete-on-uninstall" value="yes" <?php checked( $delete_on_uninstall, 'yes' ); ?> onchange="bv_toggle_uninstall(this)" />
                    <strong style="color: #DC2626;"><?php esc_html_e( 'Delete all plugin data when the plugin is deleted', 'businessvance-services-manager' ); ?></strong>
                </label>
                <p style="margin: 10px 0 0; font-size: 12px; color: #78716C;"><?php esc_html_e( '⚠️ This includes: all database tables, uploaded documents, and plugin settings. Export a backup first!', 'businessvance-services-manager' ); ?></p>
            </div>

            <!-- Danger Zone: Purge -->
            <div style="background: #FEF2F2; border: 2px solid #FCA5A5; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="margin: 0 0 10px; color: #DC2626;">
                    <span class="dashicons dashicons-warning" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Danger Zone: Purge All Data Now', 'businessvance-services-manager' ); ?>
                </h4>
                <p style="margin: 0 0 14px; color: #991B1B;"><?php esc_html_e( 'Immediately delete ALL plugin data including database tables, uploaded files, and settings. The plugin remains active but starts completely fresh. Export a backup first!', 'businessvance-services-manager' ); ?></p>
                <button type="button" class="button" onclick="bv_purge_all_data(<?php echo esc_attr( json_encode( $nonce ) ); ?>)" style="background: #DC2626; color: #fff; border-color: #DC2626;">
                    <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 5px;"></span>
                    <?php esc_html_e( 'Purge All Data (Irreversible)', 'businessvance-services-manager' ); ?>
                </button>
                <span id="bv-purge-status" style="margin-left: 12px;"></span>
            </div>
        </div>

        <script>
        function bv_export_data(nonce) {
            var status = document.getElementById('bv-export-status');
            status.innerHTML = '<em><?php esc_html_e( 'Exporting...', 'businessvance-services-manager' ); ?></em>';
            jQuery.post(ajaxurl, {
                action: 'bv_export_data',
                nonce: nonce
            }, function(r) {
                if (r.success) {
                    var blob = new Blob([r.data.json], {type: 'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = r.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    status.innerHTML = '<span style="color:#059669;">✓ <?php esc_html_e( 'Backup downloaded!', 'businessvance-services-manager' ); ?></span>';
                } else {
                    status.innerHTML = '<span style="color:#DC2626;"><?php esc_html_e( 'Error:', 'businessvance-services-manager' ); ?> ' + (r.data || '<?php esc_html_e( 'Export failed.', 'businessvance-services-manager' ); ?>') + '</span>';
                }
            }).fail(function() {
                status.innerHTML = '<span style="color:#DC2626;"><?php esc_html_e( 'Request failed.', 'businessvance-services-manager' ); ?></span>';
            });
        }

        function bv_import_data(nonce) {
            var fileInput = document.getElementById('bv-import-file');
            var status = document.getElementById('bv-import-status');
            if (!fileInput.files.length) { alert('<?php esc_html_e( 'Please select a backup file.', 'businessvance-services-manager' ); ?>'); return; }
            if (!confirm('<?php esc_html_e( 'This will restore data from the backup file. Continue?', 'businessvance-services-manager' ); ?>')) return;
            status.innerHTML = '<em><?php esc_html_e( 'Importing... this may take a moment.', 'businessvance-services-manager' ); ?></em>';
            var fd = new FormData();
            fd.append('file', fileInput.files[0]);
            fd.append('action', 'bv_import_data');
            fd.append('nonce', nonce);
            jQuery.ajax({
                url: ajaxurl, type: 'POST', data: fd,
                processData: false, contentType: false,
                success: function(r) {
                    if (r.success) {
                        status.innerHTML = '<span style="color:#059669;">✓ ' + (r.data || '<?php esc_html_e( 'Data restored successfully!', 'businessvance-services-manager' ); ?>') + '</span>';
                    } else {
                        status.innerHTML = '<span style="color:#DC2626;"><?php esc_html_e( 'Error:', 'businessvance-services-manager' ); ?> ' + (r.data || '<?php esc_html_e( 'Import failed.', 'businessvance-services-manager' ); ?>') + '</span>';
                    }
                },
                error: function() {
                    status.innerHTML = '<span style="color:#DC2626;"><?php esc_html_e( 'Request failed.', 'businessvance-services-manager' ); ?></span>';
                }
            });
        }

        function bv_purge_all_data(nonce) {
            if (!confirm('<?php esc_html_e( '⚠️ WARNING: This will permanently delete ALL plugin data (projects, documents, questionnaires, reports, messages, settings, uploaded files). This cannot be undone!\n\nAre you absolutely sure?', 'businessvance-services-manager' ); ?>')) return;
            if (!confirm('<?php esc_html_e( 'FINAL CONFIRMATION: Type "DELETE" mentally and click OK to proceed.', 'businessvance-services-manager' ); ?>')) return;
            var status = document.getElementById('bv-purge-status');
            status.innerHTML = '<em><?php esc_html_e( 'Purging...', 'businessvance-services-manager' ); ?></em>';
            jQuery.post(ajaxurl, {
                action: 'bv_purge_all_data',
                nonce: nonce
            }, function(r) {
                if (r.success) {
                    status.innerHTML = '<span style="color:#059669;">✓ <?php esc_html_e( 'All data purged. Refreshing...', 'businessvance-services-manager' ); ?></span>';
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    status.innerHTML = '<span style="color:#DC2626;"><?php esc_html_e( 'Error:', 'businessvance-services-manager' ); ?> ' + (r.data || '<?php esc_html_e( 'Purge failed.', 'businessvance-services-manager' ); ?>') + '</span>';
                }
            }).fail(function() {
                status.innerHTML = '<span style="color:#DC2626;"><?php esc_html_e( 'Request failed.', 'businessvance-services-manager' ); ?></span>';
            });
        }

        function bv_toggle_uninstall(checkbox) {
            jQuery.post(ajaxurl, {
                action: 'bv_purge_all_data',
                nonce: <?php echo esc_attr( json_encode( $nonce ) ); ?>,
                toggle_uninstall: 'yes',
                value: checkbox.checked ? 'yes' : 'no'
            }, function(r) {
                if (r.success) {
                    // Saved
                }
            });
        }
        </script>
        <?php
    }

    // =========================================================================
    // Data AJAX Handlers
    // =========================================================================

    /**
     * Export all plugin data as JSON
     */
    public function ajax_export_data() {
        check_ajax_referer( 'bv_data_management', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );

        global $wpdb;
        $prefix = $wpdb->prefix;

        $data = array(
            'exported_at' => current_time( 'mysql' ),
            'plugin_version' => BV_VERSION,
            'site_url' => site_url(),
            'settings' => get_option( self::OPTION_KEY, array() ),
            'tables' => array(),
        );

        // Export all BV tables
        $bv_tables = array(
            'bv_categories', 'bv_services', 'bv_plans', 'bv_plan_features',
            'bv_projects', 'bv_project_services', 'bv_project_agreements',
            'bv_project_documents', 'bv_project_reports', 'bv_project_messages',
            'bv_project_notes', 'bv_questionnaire_templates', 'bv_questionnaire_sections',
            'bv_questionnaire_questions', 'bv_questionnaire_responses',
            'bv_agreement_templates', 'bv_activity_log',
        );

        foreach ( $bv_tables as $table ) {
            $full_table = $prefix . $table;
            // Check table exists
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$full_table}'" );
            if ( $exists ) {
                $data['tables'][ $table ] = $wpdb->get_results( "SELECT * FROM {$full_table}", ARRAY_A );
            }
        }

        // Export file list (not file contents — too large)
        $upload_dir = wp_upload_dir()['basedir'] . '/bv-documents';
        $data['files'] = array();
        if ( is_dir( $upload_dir ) ) {
            $files = glob( $upload_dir . '/*' );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        $data['files'][] = array(
                            'name' => basename( $file ),
                            'size' => filesize( $file ),
                            'md5'  => md5_file( $file ),
                        );
                    }
                }
            }
        }

        $filename = 'bv-backup-' . date( 'Y-m-d-His' ) . '.json';

        wp_send_json_success( array(
            'json'     => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
            'filename' => $filename,
        ) );
    }

    /**
     * Import data from a JSON backup file
     */
    public function ajax_import_data() {
        check_ajax_referer( 'bv_data_management', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );

        if ( empty( $_FILES['file'] ) ) wp_send_json_error( 'No file uploaded' );

        $file = $_FILES['file'];
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'json' ) wp_send_json_error( 'Only JSON backup files are accepted' );

        $json_content = file_get_contents( $file['tmp_name'] );
        $data = json_decode( $json_content, true );
        if ( ! $data || empty( $data['tables'] ) ) {
            wp_send_json_error( 'Invalid backup file format' );
        }

        global $wpdb;
        $prefix = $wpdb->prefix;
        $imported = 0;

        foreach ( $data['tables'] as $table_name => $rows ) {
            $full_table = $prefix . $table_name;
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$full_table}'" );
            if ( ! $exists ) continue;

            if ( empty( $rows ) || ! is_array( $rows ) ) continue;

            foreach ( $rows as $row ) {
                // Remove auto-increment column for insert
                unset( $row['id'] );

                // Remove timestamp columns for insert (let DB handle them)
                unset( $row['created_at'] );
                unset( $row['updated_at'] );

                if ( empty( $row ) ) continue;

                $columns = array_keys( $row );
                $values = array_values( $row );
                $placeholders = implode( ',', array_fill( 0, count( $columns ), '%s' ) );
                $column_list = implode( ',', $columns );

                // Try insert, ignore on duplicate
                $wpdb->query( $wpdb->prepare(
                    "INSERT IGNORE INTO {$full_table} ({$column_list}) VALUES ({$placeholders})",
                    ...$values
                ) );

                $imported++;
            }
        }

        // Restore settings if present
        if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
            update_option( self::OPTION_KEY, $data['settings'] );
        }

        wp_send_json_success( "Imported {$imported} records across " . count( $data['tables'] ) . " tables." );
    }

    /**
     * Purge all plugin data OR toggle uninstall behavior
     */
    public function ajax_purge_all_data() {
        check_ajax_referer( 'bv_data_management', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );

        // Handle toggle_uninstall flag
        if ( isset( $_POST['toggle_uninstall'] ) && $_POST['toggle_uninstall'] === 'yes' ) {
            $value = sanitize_text_field( $_POST['value'] ?? 'no' );
            update_option( 'bv_delete_data_on_uninstall', $value );
            wp_send_json_success( 'Uninstall setting updated.' );
            return;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Drop all BV tables
        $tables = array(
            'bv_activity_log',
            'bv_project_notes',
            'bv_project_messages',
            'bv_project_reports',
            'bv_project_documents',
            'bv_questionnaire_responses',
            'bv_questionnaire_questions',
            'bv_questionnaire_sections',
            'bv_questionnaire_templates',
            'bv_project_agreements',
            'bv_project_services',
            'bv_projects',
            'bv_plan_features',
            'bv_plans',
            'bv_services',
            'bv_categories',
            'bv_agreement_templates',
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" );
        }

        // Delete options
        delete_option( 'bv_plugin_version' );
        delete_option( 'bv_agreement_template' );
        delete_option( 'bv_services_manager_db_version' );
        delete_option( 'bv_services_manager_seeded' );
        delete_option( 'bv_settings' );
        delete_option( 'bv_delete_data_on_uninstall' );

        // Delete uploaded files
        $upload_dir = wp_upload_dir()['basedir'] . '/bv-documents';
        if ( is_dir( $upload_dir ) ) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $upload_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $files as $fileinfo ) {
                if ( $fileinfo->isDir() ) {
                    @rmdir( $fileinfo->getRealPath() );
                } else {
                    @unlink( $fileinfo->getRealPath() );
                }
            }
            @rmdir( $upload_dir );
        }

        // Re-create tables (fresh start)
        BV_Activator::activate();

        wp_send_json_success( 'All data purged. Tables recreated with fresh seed data.' );
    }
}
