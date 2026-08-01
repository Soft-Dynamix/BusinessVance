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
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'agreement' ? 'block' : 'none'; ?>;">
                        <?php $this->render_agreement_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'email' ? 'block' : 'none'; ?>;">
                        <?php $this->render_email_tab( $settings ); ?>
                    </div>

                    <div class="bv-settings-panel" style="display: <?php echo $tab === 'woocommerce' ? 'block' : 'none'; ?>;">
                        <?php $this->render_woocommerce_tab( $settings ); ?>
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
            <p><?php esc_html_e( 'Configure the client service agreement that clients sign in the portal.', 'businessvance-services-manager' ); ?></p>

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
}
