<?php
/**
 * Plugin Name: BusinessVance Services Manager
 * Plugin URI:  https://businessvance.co.za
 * Description: Manages once-off services and monthly subscription plans for BusinessVance, with WooCommerce product linking and Yoco payment integration. Use the [businessvance_services] shortcode to display services on any page.
 * Version:     1.0.0
 * Author:      BusinessVance
 * Author URI:  https://businessvance.co.za
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: businessvance
 * Domain Path: /languages
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Plugin Constants ─────────────────────────────────────────────────────
define( 'BV_VERSION', '1.0.0' );
define( 'BV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ── Include Class Files ──────────────────────────────────────────────────
require_once BV_PLUGIN_DIR . 'includes/class-bv-activator.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-admin-dashboard.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-admin-services.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-admin-services-new.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-admin-plans.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-admin-categories.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-settings.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-ajax.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-shortcode.php';
require_once BV_PLUGIN_DIR . 'includes/class-bv-woocommerce.php';

// ── Activation / Deactivation ────────────────────────────────────────────
register_activation_hook( __FILE__, array( 'BV_Activator', 'activate' ) );

/**
 * Plugin deactivation callback.
 * Data is intentionally preserved — only flushes rewrite rules.
 */
function bv_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bv_deactivate' );

// ── Bootstrap ────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'bv_init' );

/**
 * Initialise plugin components after all plugins are loaded.
 *
 * @return void
 */
function bv_init() {
    // Load text domain for translations.
    load_plugin_textdomain( 'businessvance', false, dirname( BV_PLUGIN_BASENAME ) . '/languages' );

    // Register AJAX handlers.
    BV_Ajax::register();

    // Register shortcode.
    BV_Shortcode::register();

    // Register settings.
    BV_Settings::register();

    // Bootstrap WooCommerce integration.
    BV_WooCommerce::init();
}

// ── Admin Menu ───────────────────────────────────────────────────────────
add_action( 'admin_menu', 'bv_register_admin_menu' );

/**
 * Register the BusinessVance admin menu and sub-pages.
 *
 * @return void
 */
function bv_register_admin_menu() {

    // Top-level menu (position under "Posts" ≈ 25, but we use a decimal for fine-tuning).
    add_menu_page(
        __( 'BusinessVance', 'businessvance' ),     // Page title.
        __( 'BusinessVance', 'businessvance' ),     // Menu title.
        'manage_options',                            // Capability.
        'bv-dashboard',                              // Menu slug.
        'bv_render_dashboard',                       // Callback.
        'dashicons-list-view',                       // Icon.
        26                                           // Position.
    );

    // Sub-pages.
    add_submenu_page(
        'bv-dashboard',
        __( 'Dashboard', 'businessvance' ),
        __( 'Dashboard', 'businessvance' ),
        'manage_options',
        'bv-dashboard',
        'bv_render_dashboard'
    );

    add_submenu_page(
        'bv-dashboard',
        __( 'Services', 'businessvance' ),
        __( 'Services', 'businessvance' ),
        'manage_options',
        'bv-services',
        'bv_render_services'
    );

    add_submenu_page(
        'bv-dashboard',
        __( 'Plans', 'businessvance' ),
        __( 'Plans', 'businessvance' ),
        'manage_options',
        'bv-plans',
        'bv_render_plans'
    );

    add_submenu_page(
        'bv-dashboard',
        __( 'Categories', 'businessvance' ),
        __( 'Categories', 'businessvance' ),
        'manage_options',
        'bv-categories',
        'bv_render_categories'
    );

    add_submenu_page(
        'bv-dashboard',
        __( 'Settings', 'businessvance' ),
        __( 'Settings', 'businessvance' ),
        'manage_options',
        'bv-settings',
        'bv_render_settings'
    );
}

// ── Admin Page Renderers ─────────────────────────────────────────────────

/**
 * Render the Dashboard sub-page.
 *
 * @return void
 */
function bv_render_dashboard() {
    BV_Admin_Dashboard::render_page();
}

/**
 * Render the Services sub-page.
 * Handles the "Add New" case (edit=0) and visibility toggles.
 *
 * @return void
 */
function bv_render_services() {
    // Handle visibility toggle.
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'bv_toggle_service_visibility' ) {
        BV_Admin_Services::handle_visibility_toggle();
        return;
    }

    // "Add New" form.
    if ( isset( $_GET['edit'] ) && $_GET['edit'] === '0' ) {
        // We need the add-new form from the services class.
        // Re-use the existing class by passing a null object.
        // The render_page handles this — but it checks edit_id > 0.
        // Let's handle the add-new case explicitly.
        require_once BV_PLUGIN_DIR . 'includes/class-bv-admin-services-new.php';
        BV_Admin_Services_New::render_form();
        return;
    }

    BV_Admin_Services::render_page();
}

/**
 * Render the Plans sub-page.
 *
 * @return void
 */
function bv_render_plans() {
    // Handle visibility toggle.
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'bv_toggle_plan_visibility' ) {
        BV_Admin_Plans::handle_visibility_toggle();
        return;
    }

    // "Add New" form.
    if ( isset( $_GET['edit'] ) && $_GET['edit'] === '0' ) {
        BV_Admin_Plans::render_new_form();
        return;
    }

    BV_Admin_Plans::render_page();
}

/**
 * Render the Categories sub-page.
 *
 * @return void
 */
function bv_render_categories() {
    BV_Admin_Categories::render_page();
}

/**
 * Render the Settings sub-page.
 *
 * @return void
 */
function bv_render_settings() {
    BV_Settings::render_page();
}

// ── Admin Asset Enqueue ──────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'bv_admin_enqueue' );

/**
 * Enqueue admin CSS and JS on BusinessVance admin pages.
 *
 * @param string $hook The current admin page hook suffix.
 * @return void
 */
function bv_admin_enqueue( $hook ) {

    // Only load on our plugin pages.
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'bv-' ) === false ) {
        return;
    }

    // Admin CSS.
    wp_enqueue_style(
        'bv-admin',
        BV_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        BV_VERSION
    );

    // jQuery UI Sortable (for drag-and-drop reorder).
    wp_enqueue_script( 'jquery-ui-sortable' );

    // Admin JS.
    wp_enqueue_script(
        'bv-admin',
        BV_PLUGIN_URL . 'assets/js/admin.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        BV_VERSION,
        true
    );

    // Localise script with AJAX URL and nonces.
    wp_localize_script( 'bv-admin', 'BVAdmin', array(
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce_reorder'   => wp_create_nonce( 'bv_reorder_services' ),
        'nonce_plans'     => wp_create_nonce( 'bv_reorder_plans' ),
        'nonce_toggle'    => wp_create_nonce( 'bv_toggle_visibility' ),
        'nonce_wc_search' => wp_create_nonce( 'bv_wc_search' ),
    ) );
}

// ── Frontend Asset Enqueue ───────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'bv_frontend_enqueue' );

/**
 * Register frontend CSS and JS. Actual enqueue happens in the shortcode
 * to only load on pages that use the shortcode.
 *
 * @return void
 */
function bv_frontend_enqueue() {
    wp_register_style(
        'bv-frontend',
        BV_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        BV_VERSION
    );

    wp_register_script(
        'bv-frontend',
        BV_PLUGIN_URL . 'assets/js/frontend.js',
        array( 'jquery' ),
        BV_VERSION,
        true
    );
}

// ── Plugin Action Links ──────────────────────────────────────────────────
add_filter( 'plugin_action_links_' . BV_PLUGIN_BASENAME, 'bv_plugin_action_links' );

/**
 * Add "Settings" link to the plugin row on the Plugins page.
 *
 * @param array $links Existing action links.
 * @return array
 */
function bv_plugin_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=bv-settings' ) ) . '">' . esc_html__( 'Settings', 'businessvance' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}