<?php
/**
 * Admin panel for BusinessVance Services Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Admin {

    /** @var array Available Lucide icon names */
    private $icons = array();

    public function __construct() {
        $this->load_icons();
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bv_get_service', array( $this, 'ajax_get_service' ) );
        add_action( 'wp_ajax_bv_save_service', array( $this, 'ajax_save_service' ) );
        add_action( 'wp_ajax_bv_delete_service', array( $this, 'ajax_delete_service' ) );
        add_action( 'wp_ajax_bv_toggle_visibility', array( $this, 'ajax_toggle_visibility' ) );
        add_action( 'wp_ajax_bv_reorder_services', array( $this, 'ajax_reorder_services' ) );
        add_action( 'wp_ajax_bv_get_plan', array( $this, 'ajax_get_plan' ) );
        add_action( 'wp_ajax_bv_save_plan', array( $this, 'ajax_save_plan' ) );
        add_action( 'wp_ajax_bv_delete_plan', array( $this, 'ajax_delete_plan' ) );
        add_action( 'wp_ajax_bv_reorder_plans', array( $this, 'ajax_reorder_plans' ) );
        add_action( 'wp_ajax_bv_save_category', array( $this, 'ajax_save_category' ) );
        add_action( 'wp_ajax_bv_delete_category', array( $this, 'ajax_delete_category' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_post' ) );
    }

    /**
     * Load available icon list
     */
    private function load_icons() {
        $this->icons = array(
            'briefcase', 'building', 'building-2', 'landmark', 'chart-bar', 'chart-line',
            'trending-up', 'trending-down', 'bar-chart-3', 'pie-chart', 'calculator',
            'file-text', 'file-check', 'file-plus', 'file-search', 'folder',
            'award', 'star', 'shield', 'shield-check', 'check-circle',
            'users', 'user-plus', 'user-check', 'handshake', 'globe',
            'palette', 'pen-tool', 'layers', 'layout', 'code',
            'mail', 'phone', 'message-circle', 'share-2', 'megaphone',
            'search', 'settings', 'sliders', 'target', 'zap',
            'heart', 'book-open', 'graduation-cap', 'lightbulb', 'rocket',
            'receipt', 'credit-card', 'banknote', 'wallet', 'piggy-bank',
            'clock', 'calendar', 'map-pin', 'truck', 'package',
        );
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            __( 'BusinessVance', 'businessvance-services-manager' ),
            __( 'BusinessVance', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance',
            array( $this, 'render_dashboard' ),
            'dashicons-shield-alt',
            30
        );

        add_submenu_page(
            'businessvance',
            __( 'Dashboard', 'businessvance-services-manager' ),
            __( 'Dashboard', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'businessvance',
            __( 'Services', 'businessvance-services-manager' ),
            __( 'Services', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-services',
            array( $this, 'render_services' )
        );

        add_submenu_page(
            'businessvance',
            __( 'Subscription Plans', 'businessvance-services-manager' ),
            __( 'Plans', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-plans',
            array( $this, 'render_plans' )
        );

        add_submenu_page(
            'businessvance',
            __( 'Categories', 'businessvance-services-manager' ),
            __( 'Categories', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-categories',
            array( $this, 'render_categories' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'businessvance' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'bv-admin-css',
            BV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BV_VERSION
        );

        wp_enqueue_script(
            'bv-admin-js',
            BV_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            BV_VERSION,
            true
        );

        wp_localize_script( 'bv-admin-js', 'bvAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'strings'  => array(
                'confirm_delete'    => __( 'Are you sure you want to delete this item?', 'businessvance-services-manager' ),
                'saving'            => __( 'Saving...', 'businessvance-services-manager' ),
                'saved'             => __( 'Saved successfully!', 'businessvance-services-manager' ),
                'error'             => __( 'An error occurred. Please try again.', 'businessvance-services-manager' ),
                'reorder_saved'     => __( 'Order saved!', 'businessvance-services-manager' ),
            ),
        ) );
    }

    /**
     * Get table names
     */
    private function get_tables() {
        global $wpdb;
        return array(
            'categories' => $wpdb->prefix . 'bv_categories',
            'services'   => $wpdb->prefix . 'bv_services',
            'plans'      => $wpdb->prefix . 'bv_plans',
            'features'   => $wpdb->prefix . 'bv_plan_features',
        );
    }

    /**
     * Verify admin nonce
     */
    private function verify_nonce() {
        if ( ! check_ajax_referer( 'bv_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
    }

    /**
     * Render Dashboard page
     */
    public function render_dashboard() {
        global $wpdb;
        $tables = $this->get_tables();

        $total_services = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']}" );
        $visible_services = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']} WHERE is_visible = 1" );
        $hidden_services = $total_services - $visible_services;
        $featured_services = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']} WHERE is_featured = 1" );

        $total_plans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['plans']}" );
        $featured_plans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['plans']} WHERE is_featured = 1" );

        $total_categories = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['categories']}" );

        $total_features = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['features']}" );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <span class="bv-shield-icon">🛡️</span>
                    <div>
                        <h1><?php esc_html_e( 'BusinessVance Services Manager', 'businessvance-services-manager' ); ?></h1>
                        <p class="bv-subtitle"><?php esc_html_e( 'INSIGHT. STRATEGY. SUCCESS.', 'businessvance-services-manager' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="bv-stats-grid">
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #002B5C;"><?php echo esc_html( $total_services ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Total Services', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #008080;"><?php echo esc_html( $total_plans ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Subscription Plans', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #16a34a;"><?php echo esc_html( $visible_services ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Visible Services', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #dc2626;"><?php echo esc_html( $hidden_services ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Hidden Services', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #D4AF37;"><?php echo esc_html( $featured_services + $featured_plans ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Featured Items', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #6366f1;"><?php echo esc_html( $total_categories ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Categories', 'businessvance-services-manager' ); ?></div>
                </div>
            </div>

            <div class="bv-dashboard-info">
                <h2><?php esc_html_e( 'Shortcodes', 'businessvance-services-manager' ); ?></h2>
                <div class="bv-shortcode-grid">
                    <div class="bv-shortcode-card">
                        <h3>[businessvance_services]</h3>
                        <p><?php esc_html_e( 'Renders the complete services page with header, once-off services table, subscription plans, and footer.', 'businessvance-services-manager' ); ?></p>
                        <code>[businessvance_services]</code>
                    </div>
                    <div class="bv-shortcode-card">
                        <h3>[businessvance_onceoff]</h3>
                        <p><?php esc_html_e( 'Renders only the once-off services table section.', 'businessvance-services-manager' ); ?></p>
                        <code>[businessvance_onceoff]</code>
                    </div>
                    <div class="bv-shortcode-card">
                        <h3>[businessvance_subscriptions]</h3>
                        <p><?php esc_html_e( 'Renders only the subscription plans section.', 'businessvance-services-manager' ); ?></p>
                        <code>[businessvance_subscriptions]</code>
                    </div>
                </div>
            </div>

            <div class="bv-dashboard-info" style="display:flex;flex-wrap:wrap;gap:12px;">
                <h2 style="width:100%;"><?php esc_html_e( 'Quick Access', 'businessvance-services-manager' ); ?></h2>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=businessvance-settings' ) ); ?>"
                   class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;padding:8px 20px;">
                    <span class="dashicons dashicons-admin-generic" style="font-size:18px;"></span>
                    <?php esc_html_e( 'Settings', 'businessvance-services-manager' ); ?>
                </a>
                <a href="#" onclick="prompt('<?php esc_attr_e( 'Client Portal Shortcode:', 'businessvance-services-manager' ); ?>', '[businessvance_client_portal]'); return false;"
                   class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;padding:8px 20px;">
                    <span class="dashicons dashicons-admin-users" style="font-size:18px;"></span>
                    <?php esc_html_e( 'Client Portal', 'businessvance-services-manager' ); ?>
                </a>
            </div>

            <div class="bv-dashboard-info">
                <h2><?php esc_html_e( 'Quick Start', 'businessvance-services-manager' ); ?></h2>
                <ol class="bv-steps">
                    <li><?php esc_html_e( 'Go to <strong>Settings</strong> and configure your company branding, colors, and contact info.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Add your Categories under the Categories menu.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Create your WooCommerce products (simple products for once-off, subscription products for plans).', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Add your Services and link each to its WooCommerce Product ID.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Set up Subscription Plans with features and link to WooCommerce subscription products.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Create a new WordPress page and add the shortcode [businessvance_services].', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Create another page with [businessvance_client_portal] for the client portal.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Ensure Yoco payment gateway is configured in WooCommerce settings.', 'businessvance-services-manager' ); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }

    /**
     * Render Services page
     */
    public function render_services() {
        global $wpdb;
        $tables = $this->get_tables();

        $services = $wpdb->get_results(
            "SELECT s.*, c.name as category_name, c.color as category_color
             FROM {$tables['services']} s
             LEFT JOIN {$tables['categories']} c ON s.category_id = c.id
             ORDER BY s.display_order ASC, s.id ASC"
        );

        $categories = $wpdb->get_results( "SELECT * FROM {$tables['categories']} ORDER BY name ASC" );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Services', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Manage your once-off services displayed on the services page.', 'businessvance-services-manager' ); ?></p>
                </div>
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-service">
                    <?php esc_html_e( '+ Add Service', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <div class="bv-table-container">
                <table class="wp-list-table widefat fixed striped bv-sortable-table" id="bv-services-table">
                    <thead>
                        <tr>
                            <th style="width:40px;" class="bv-sort-handle-col"></th>
                            <th><?php esc_html_e( 'Service', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Price', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Woo Product', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Visible', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Featured', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $services ) ) : ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px;">
                                    <?php esc_html_e( 'No services found. Click "Add Service" to create your first service.', 'businessvance-services-manager' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $services as $service ) : ?>
                                <tr data-id="<?php echo esc_attr( $service->id ); ?>">
                                    <td class="bv-sort-handle-col">
                                        <span class="bv-sort-handle" title="<?php esc_attr_e( 'Drag to reorder', 'businessvance-services-manager' ); ?>">☰</span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $service->name ); ?></strong>
                                        <br>
                                        <small style="color:#666;">
                                            <?php echo esc_html( wp_trim_words( $service->description, 10, '...' ) ); ?>
                                        </small>
                                        <?php if ( $service->is_featured ) : ?>
                                            <span class="bv-badge-featured">★ Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $service->price ); ?></td>
                                    <td>
                                        <?php if ( $service->category_name ) : ?>
                                            <span class="bv-category-dot" style="background-color:<?php echo esc_attr( $service->category_color ); ?>"></span>
                                            <?php echo esc_html( $service->category_name ); ?>
                                        <?php else : ?>
                                            <em style="color:#999;"><?php esc_html_e( 'None', 'businessvance-services-manager' ); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $service->woo_product_id ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $service->woo_product_id . '&action=edit' ) ); ?>" target="_blank">
                                                #<?php echo esc_html( $service->woo_product_id ); ?>
                                            </a>
                                        <?php else : ?>
                                            <em style="color:#999;"><?php esc_html_e( 'Not linked', 'businessvance-services-manager' ); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="bv-toggle-btn <?php echo $service->is_visible ? 'bv-active' : 'bv-inactive'; ?>"
                                                data-id="<?php echo esc_attr( $service->id ); ?>"
                                                data-type="service"
                                                title="<?php echo $service->is_visible ? esc_attr__( 'Click to hide', 'businessvance-services-manager' ) : esc_attr__( 'Click to show', 'businessvance-services-manager' ); ?>">
                                            <?php echo $service->is_visible ? '👁️' : '🚫'; ?>
                                        </button>
                                    </td>
                                    <td>
                                        <span style="font-size:18px;"><?php echo $service->is_featured ? '⭐' : '☆'; ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small bv-edit-btn" data-id="<?php echo esc_attr( $service->id ); ?>">
                                            <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-delete-btn" data-id="<?php echo esc_attr( $service->id ); ?>" data-type="service" data-name="<?php echo esc_attr( $service->name ); ?>">
                                            <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Service Form Modal -->
        <div id="bv-service-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content">
                <div class="bv-modal-header">
                    <h2 id="bv-service-modal-title"><?php esc_html_e( 'Add New Service', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-service-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group bv-form-full">
                            <label for="svc-name"><?php esc_html_e( 'Service Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-name" name="name" required class="regular-text">
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="svc-description"><?php esc_html_e( 'Description', 'businessvance-services-manager' ); ?></label>
                            <textarea id="svc-description" name="description" rows="3" class="large-text"></textarea>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-price"><?php esc_html_e( 'Price (e.g. R1,500)', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-price" name="price" value="R0" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-price-display"><?php esc_html_e( 'Price Display (override)', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-price-display" name="price_display" placeholder="<?php esc_attr_e( 'Leave blank to use Price', 'businessvance-services-manager' ); ?>" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-icon"><?php esc_html_e( 'Icon', 'businessvance-services-manager' ); ?></label>
                            <select id="svc-icon" name="icon">
                                <?php foreach ( $this->icons as $icon ) : ?>
                                    <option value="<?php echo esc_attr( $icon ); ?>"><?php echo esc_html( $icon ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-button-label"><?php esc_html_e( 'Button Label', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-button-label" name="button_label" value="Get Started" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-type"><?php esc_html_e( 'Service Type', 'businessvance-services-manager' ); ?></label>
                            <select id="svc-type" name="service_type">
                                <option value="onceoff"><?php esc_html_e( 'Once-off', 'businessvance-services-manager' ); ?></option>
                                <option value="quote"><?php esc_html_e( 'Request Quote', 'businessvance-services-manager' ); ?></option>
                                <option value="booking"><?php esc_html_e( 'Book Consultation', 'businessvance-services-manager' ); ?></option>
                                <option value="download"><?php esc_html_e( 'Digital Download', 'businessvance-services-manager' ); ?></option>
                            </select>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-category"><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></label>
                            <select id="svc-category" name="category_id">
                                <option value="0"><?php esc_html_e( '-- None --', 'businessvance-services-manager' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-woo-product"><?php esc_html_e( 'WooCommerce Product ID', 'businessvance-services-manager' ); ?></label>
                            <input type="number" id="svc-woo-product" name="woo_product_id" value="0" min="0" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Enter the WooCommerce product ID to link for Yoco payment. Leave 0 if not linked.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_visible" value="1" checked>
                                <?php esc_html_e( 'Visible on frontend', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_featured" value="1">
                                <?php esc_html_e( 'Featured (shows badge)', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Service', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render Plans page
     */
    public function render_plans() {
        global $wpdb;
        $tables = $this->get_tables();

        $plans = $wpdb->get_results(
            "SELECT p.*, c.name as category_name, c.color as category_color,
                    (SELECT COUNT(*) FROM {$tables['features']} f WHERE f.plan_id = p.id) as feature_count
             FROM {$tables['plans']} p
             LEFT JOIN {$tables['categories']} c ON p.category_id = c.id
             ORDER BY p.display_order ASC, p.id ASC"
        );

        $categories = $wpdb->get_results( "SELECT * FROM {$tables['categories']} ORDER BY name ASC" );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Subscription Plans', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Manage monthly subscription plans displayed on the services page.', 'businessvance-services-manager' ); ?></p>
                </div>
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-plan">
                    <?php esc_html_e( '+ Add Plan', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <div class="bv-table-container">
                <table class="wp-list-table widefat fixed striped bv-sortable-table" id="bv-plans-table">
                    <thead>
                        <tr>
                            <th style="width:40px;" class="bv-sort-handle-col"></th>
                            <th><?php esc_html_e( 'Plan', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Price', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Color', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Woo Product', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Features', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Visible', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Featured', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $plans ) ) : ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:40px;">
                                    <?php esc_html_e( 'No plans found. Click "Add Plan" to create your first subscription plan.', 'businessvance-services-manager' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $plans as $plan ) : ?>
                                <tr data-id="<?php echo esc_attr( $plan->id ); ?>">
                                    <td class="bv-sort-handle-col">
                                        <span class="bv-sort-handle" title="<?php esc_attr_e( 'Drag to reorder', 'businessvance-services-manager' ); ?>">☰</span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $plan->name ); ?></strong>
                                        <?php if ( $plan->subtitle ) : ?>
                                            <br><small style="color:#666;"><?php echo esc_html( $plan->subtitle ); ?></small>
                                        <?php endif; ?>
                                        <?php if ( $plan->is_featured ) : ?>
                                            <span class="bv-badge-featured">★ Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $plan->price ); ?></td>
                                    <td><span class="bv-color-dot" style="background-color:<?php echo esc_attr( $plan->color ); ?>"></span></td>
                                    <td>
                                        <?php if ( $plan->woo_product_id ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $plan->woo_product_id . '&action=edit' ) ); ?>" target="_blank">
                                                #<?php echo esc_html( $plan->woo_product_id ); ?>
                                            </a>
                                        <?php else : ?>
                                            <em style="color:#999;"><?php esc_html_e( 'Not linked', 'businessvance-services-manager' ); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $plan->feature_count ); ?></td>
                                    <td>
                                        <button type="button" class="bv-toggle-btn <?php echo $plan->is_visible ? 'bv-active' : 'bv-inactive'; ?>"
                                                data-id="<?php echo esc_attr( $plan->id ); ?>"
                                                data-type="plan"
                                                title="<?php echo $plan->is_visible ? esc_attr__( 'Click to hide', 'businessvance-services-manager' ) : esc_attr__( 'Click to show', 'businessvance-services-manager' ); ?>">
                                            <?php echo $plan->is_visible ? '👁️' : '🚫'; ?>
                                        </button>
                                    </td>
                                    <td>
                                        <span style="font-size:18px;"><?php echo $plan->is_featured ? '⭐' : '☆'; ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small bv-edit-btn" data-id="<?php echo esc_attr( $plan->id ); ?>" data-type="plan">
                                            <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-delete-btn" data-id="<?php echo esc_attr( $plan->id ); ?>" data-type="plan" data-name="<?php echo esc_attr( $plan->name ); ?>">
                                            <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Plan Form Modal -->
        <div id="bv-plan-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content">
                <div class="bv-modal-header">
                    <h2 id="bv-plan-modal-title"><?php esc_html_e( 'Add New Plan', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-plan-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group">
                            <label for="plan-name"><?php esc_html_e( 'Plan Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-name" name="name" required class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-subtitle"><?php esc_html_e( 'Subtitle', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-subtitle" name="subtitle" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. For growing businesses', 'businessvance-services-manager' ); ?>">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-price"><?php esc_html_e( 'Price (e.g. R599/mo)', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-price" name="price" value="R0/mo" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-color"><?php esc_html_e( 'Theme Color', 'businessvance-services-manager' ); ?></label>
                            <input type="color" id="plan-color" name="color" value="#008080">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-button-label"><?php esc_html_e( 'Button Label', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-button-label" name="button_label" value="Subscribe Now" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-category"><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></label>
                            <select id="plan-category" name="category_id">
                                <option value="0"><?php esc_html_e( '-- None --', 'businessvance-services-manager' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-woo-product"><?php esc_html_e( 'WooCommerce Product ID', 'businessvance-services-manager' ); ?></label>
                            <input type="number" id="plan-woo-product" name="woo_product_id" value="0" min="0" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Enter the WooCommerce subscription product ID for Yoco payment.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_visible" value="1" checked>
                                <?php esc_html_e( 'Visible on frontend', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_featured" value="1">
                                <?php esc_html_e( 'Featured (highlighted card)', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>
                    </div>

                    <div class="bv-form-group bv-form-full">
                        <label><?php esc_html_e( 'Features', 'businessvance-services-manager' ); ?></label>
                        <div id="bv-features-list">
                            <!-- Features will be added dynamically -->
                        </div>
                        <button type="button" id="bv-add-feature" class="button button-small">+ <?php esc_html_e( 'Add Feature', 'businessvance-services-manager' ); ?></button>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Plan', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render Categories page
     */
    public function render_categories() {
        global $wpdb;
        $tables = $this->get_tables();

        $categories = $wpdb->get_results(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$tables['services']} s WHERE s.category_id = c.id) as service_count,
                    (SELECT COUNT(*) FROM {$tables['plans']} p WHERE p.category_id = c.id) as plan_count
             FROM {$tables['categories']} c
             ORDER BY c.name ASC"
        );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Categories', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Organize your services and plans into categories.', 'businessvance-services-manager' ); ?></p>
                </div>
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-category">
                    <?php esc_html_e( '+ Add Category', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <div class="bv-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Slug', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Color', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Services', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Plans', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $categories ) ) : ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px;">
                                    <?php esc_html_e( 'No categories found. Click "Add Category" to create one.', 'businessvance-services-manager' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $categories as $cat ) : ?>
                                <tr data-id="<?php echo esc_attr( $cat->id ); ?>">
                                    <td>
                                        <span class="bv-category-dot" style="background-color:<?php echo esc_attr( $cat->color ); ?>"></span>
                                        <strong><?php echo esc_html( $cat->name ); ?></strong>
                                    </td>
                                    <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                                    <td><span class="bv-color-dot" style="background-color:<?php echo esc_attr( $cat->color ); ?>"></span></td>
                                    <td><?php echo esc_html( $cat->service_count ); ?></td>
                                    <td><?php echo esc_html( $cat->plan_count ); ?></td>
                                    <td>
                                        <button type="button" class="button button-small bv-edit-btn" data-id="<?php echo esc_attr( $cat->id ); ?>" data-type="category">
                                            <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-delete-btn" data-id="<?php echo esc_attr( $cat->id ); ?>" data-type="category" data-name="<?php echo esc_attr( $cat->name ); ?>">
                                            <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Category Form Modal -->
        <div id="bv-category-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content bv-modal-small">
                <div class="bv-modal-header">
                    <h2 id="bv-category-modal-title"><?php esc_html_e( 'Add New Category', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-category-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group bv-form-full">
                            <label for="cat-name"><?php esc_html_e( 'Category Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="cat-name" name="name" required class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Financial Services', 'businessvance-services-manager' ); ?>">
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="cat-slug"><?php esc_html_e( 'Slug', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="cat-slug" name="slug" class="regular-text" placeholder="<?php esc_attr_e( 'auto-generated-from-name', 'businessvance-services-manager' ); ?>">
                            <p class="description"><?php esc_html_e( 'Auto-generated from name if left blank.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="cat-color"><?php esc_html_e( 'Color', 'businessvance-services-manager' ); ?></label>
                            <input type="color" id="cat-color" name="color" value="#008080">
                        </div>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Category', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle admin POST requests (fallback for non-AJAX)
     */
    public function handle_admin_post() {
        // All CRUD is handled via AJAX
    }

    /* ========== AJAX HANDLERS ========== */

    /**
     * Get service data for editing
     */
    public function ajax_get_service() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['services']} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $service ) {
            wp_send_json_error( array( 'message' => 'Service not found.' ) );
        }

        wp_send_json_success( $service );
    }

    /**
     * Save (create/update) service
     */
    public function ajax_save_service() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        $data = array(
            'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
            'description'   => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'price'         => sanitize_text_field( $_POST['price'] ?? 'R0' ),
            'price_display' => sanitize_text_field( $_POST['price_display'] ?? '' ),
            'icon'          => sanitize_text_field( $_POST['icon'] ?? 'briefcase' ),
            'button_label'  => sanitize_text_field( $_POST['button_label'] ?? 'Get Started' ),
            'service_type'  => sanitize_text_field( $_POST['service_type'] ?? 'onceoff' ),
            'woo_product_id' => intval( $_POST['woo_product_id'] ?? 0 ),
            'category_id'   => intval( $_POST['category_id'] ?? 0 ),
            'is_visible'    => intval( $_POST['is_visible'] ?? 0 ),
            'is_featured'   => intval( $_POST['is_featured'] ?? 0 ),
        );
        $format = array( '%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d' );

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Service name is required.', 'businessvance-services-manager' ) ) );
        }

        if ( $id > 0 ) {
            // Update
            $wpdb->update( $tables['services'], $data, array( 'id' => $id ), $format, array( '%d' ) );
            wp_send_json_success( array( 'message' => __( 'Service updated.', 'businessvance-services-manager' ), 'id' => $id ) );
        } else {
            // Get max display_order
            $max_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order), 0) FROM {$tables['services']}" );
            $data['display_order'] = $max_order + 1;
            $format[] = '%d';

            $wpdb->insert( $tables['services'], $data, $format );
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array( 'message' => __( 'Service created.', 'businessvance-services-manager' ), 'id' => $new_id ) );
        }
    }

    /**
     * Delete service
     */
    public function ajax_delete_service() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $wpdb->delete( $tables['services'], array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success( array( 'message' => __( 'Service deleted.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Toggle visibility
     */
    public function ajax_toggle_visibility() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id   = intval( $_POST['id'] ?? 0 );
        $type = sanitize_text_field( $_POST['type'] ?? '' );

        if ( ! in_array( $type, array( 'service', 'plan' ), true ) || $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        }

        $table = ( $type === 'service' ) ? $tables['services'] : $tables['plans'];
        $current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_visible FROM {$table} WHERE id = %d", $id ) );
        $new_value = $current ? 0 : 1;

        $wpdb->update( $table, array( 'is_visible' => $new_value ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
        wp_send_json_success( array( 'is_visible' => $new_value ) );
    }

    /**
     * Reorder services
     */
    public function ajax_reorder_services() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $order = $_POST['order'] ?? array();
        if ( ! is_array( $order ) ) {
            wp_send_json_error();
        }

        foreach ( $order as $position => $id ) {
            $wpdb->update(
                $tables['services'],
                array( 'display_order' => intval( $position ) ),
                array( 'id' => intval( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success( array( 'message' => __( 'Order saved.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Get plan data for editing
     */
    public function ajax_get_plan() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? $_GET['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $plan = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['plans']} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $plan ) {
            wp_send_json_error( array( 'message' => 'Plan not found.' ) );
        }

        $plan['features'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['features']} WHERE plan_id = %d ORDER BY display_order ASC",
                $id
            ),
            ARRAY_A
        );

        wp_send_json_success( $plan );
    }

    /**
     * Save (create/update) plan
     */
    public function ajax_save_plan() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        $data = array(
            'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
            'subtitle'      => sanitize_text_field( $_POST['subtitle'] ?? '' ),
            'price'         => sanitize_text_field( $_POST['price'] ?? 'R0/mo' ),
            'color'         => sanitize_text_field( $_POST['color'] ?? '#008080' ),
            'button_label'  => sanitize_text_field( $_POST['button_label'] ?? 'Subscribe Now' ),
            'woo_product_id' => intval( $_POST['woo_product_id'] ?? 0 ),
            'category_id'   => intval( $_POST['category_id'] ?? 0 ),
            'is_visible'    => intval( $_POST['is_visible'] ?? 0 ),
            'is_featured'   => intval( $_POST['is_featured'] ?? 0 ),
        );
        $format = array( '%s','%s','%s','%s','%s','%d','%d','%d','%d' );

        $features = $_POST['features'] ?? array();
        if ( ! is_array( $features ) ) {
            $features = array();
        }
        $features = array_map( 'sanitize_text_field', $features );

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Plan name is required.', 'businessvance-services-manager' ) ) );
        }

        if ( $id > 0 ) {
            // Update plan
            $wpdb->update( $tables['plans'], $data, array( 'id' => $id ), $format, array( '%d' ) );

            // Update features - delete existing and re-insert
            $wpdb->delete( $tables['features'], array( 'plan_id' => $id ), array( '%d' ) );
            foreach ( $features as $i => $feature_text ) {
                if ( ! empty( $feature_text ) ) {
                    $wpdb->insert( $tables['features'], array(
                        'plan_id'       => $id,
                        'feature_text'  => $feature_text,
                        'display_order' => $i,
                    ), array( '%d', '%s', '%d' ) );
                }
            }

            wp_send_json_success( array( 'message' => __( 'Plan updated.', 'businessvance-services-manager' ), 'id' => $id ) );
        } else {
            // Get max display_order
            $max_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order), 0) FROM {$tables['plans']}" );
            $data['display_order'] = $max_order + 1;
            $format[] = '%d';

            $wpdb->insert( $tables['plans'], $data, $format );
            $new_id = $wpdb->insert_id;

            // Insert features
            foreach ( $features as $i => $feature_text ) {
                if ( ! empty( $feature_text ) ) {
                    $wpdb->insert( $tables['features'], array(
                        'plan_id'       => $new_id,
                        'feature_text'  => $feature_text,
                        'display_order' => $i,
                    ), array( '%d', '%s', '%d' ) );
                }
            }

            wp_send_json_success( array( 'message' => __( 'Plan created.', 'businessvance-services-manager' ), 'id' => $new_id ) );
        }
    }

    /**
     * Delete plan
     */
    public function ajax_delete_plan() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        // Delete features first (cascade)
        $wpdb->delete( $tables['features'], array( 'plan_id' => $id ), array( '%d' ) );
        $wpdb->delete( $tables['plans'], array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success( array( 'message' => __( 'Plan deleted.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Reorder plans
     */
    public function ajax_reorder_plans() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $order = $_POST['order'] ?? array();
        if ( ! is_array( $order ) ) {
            wp_send_json_error();
        }

        foreach ( $order as $position => $id ) {
            $wpdb->update(
                $tables['plans'],
                array( 'display_order' => intval( $position ) ),
                array( 'id' => intval( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success( array( 'message' => __( 'Order saved.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Save (create/update) category
     */
    public function ajax_save_category() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        $slug = sanitize_text_field( $_POST['slug'] ?? '' );
        $color = sanitize_text_field( $_POST['color'] ?? '#008080' );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Category name is required.', 'businessvance-services-manager' ) ) );
        }

        if ( empty( $slug ) ) {
            $slug = sanitize_title( $name );
        }

        $data = array(
            'name'  => $name,
            'slug'  => $slug,
            'color' => $color,
        );
        $format = array( '%s', '%s', '%s' );

        if ( $id > 0 ) {
            $wpdb->update( $tables['categories'], $data, array( 'id' => $id ), $format, array( '%d' ) );
            wp_send_json_success( array( 'message' => __( 'Category updated.', 'businessvance-services-manager' ), 'id' => $id ) );
        } else {
            $wpdb->insert( $tables['categories'], $data, $format );
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array( 'message' => __( 'Category created.', 'businessvance-services-manager' ), 'id' => $new_id ) );
        }
    }

    /**
     * Delete category
     */
    public function ajax_delete_category() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        // Remove category reference from services and plans
        $wpdb->update( $tables['services'], array( 'category_id' => 0 ), array( 'category_id' => $id ), array( '%d' ), array( '%d' ) );
        $wpdb->update( $tables['plans'], array( 'category_id' => 0 ), array( 'category_id' => $id ), array( '%d' ), array( '%d' ) );
        $wpdb->delete( $tables['categories'], array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => __( 'Category deleted.', 'businessvance-services-manager' ) ) );
    }
}