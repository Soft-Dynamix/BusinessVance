<?php
/**
 * Plugin Name: BusinessVance Services Manager
 * Plugin URI: https://www.studyvance.co.za
 * Description: Dynamically manage BusinessVance Consulting services, subscription plans, and categories. Includes Client Portal for WooCommerce customers, Consultant Dashboard for project management, questionnaire system, agreement signing, document exchange, and report delivery.
 * Version: 2.2.1
 * Author: BusinessVance Consulting
 * Author URI: https://www.studyvance.co.za
 * License: GPL v2 or later
 * Text Domain: businessvance-services-manager
 * Domain Path: /languages
 *
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BV_VERSION', '2.2.1' );
define( 'BV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BV_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/bv-documents' );

// Ensure upload directory exists
if ( ! file_exists( BV_UPLOAD_DIR ) ) {
    wp_mkdir_p( BV_UPLOAD_DIR );
}

// Declare WooCommerce HPOS compatibility before WooCommerce loads.
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'mini_cart_block', __FILE__, true );
    }
} );

// Include the activator early so activation hooks can use it
require_once BV_PLUGIN_DIR . 'includes/class-bv-activator.php';

/**
 * Plugin activation callback
 */
function bv_plugin_activate() {
    BV_Activator::activate();
    update_option( 'bv_plugin_version', BV_VERSION );
}
register_activation_hook( __FILE__, 'bv_plugin_activate' );

/**
 * Plugin deactivation callback
 *
 * Intentionally lightweight — does NOT delete data or options.
 * Data is preserved for reactivation. Use uninstall.php for full cleanup
 * (with the "Delete all data on uninstall" setting enabled).
 */
function bv_plugin_deactivate() {
    // Intentionally empty — preserve all data on deactivation.
    // Use Settings → Data Management → "Delete all data on uninstall" for full removal.
}
register_deactivation_hook( __FILE__, 'bv_plugin_deactivate' );

/**
 * WooCommerce order completion hook — creates project automatically
 */
function bv_handle_wc_order_completion( $order_id, $order ) {
    if ( ! class_exists( 'BV_WooCommerce' ) ) {
        return;
    }

    global $wpdb;
    $services_table = $wpdb->prefix . 'bv_services';
    $projects_table = $wpdb->prefix . 'bv_projects';
    $project_services_table = $wpdb->prefix . 'bv_project_services';

    // Check if project already exists for this order
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$projects_table} WHERE wc_order_id = %d",
        $order_id
    ) );
    if ( $existing ) {
        return;
    }

    $user = $order->get_user();
    $user_id = $user ? $user->ID : 0;
    $billing = $order->get_address( 'billing' );

    // Generate project number
    $year = date( 'Y' );
    $last_num = $wpdb->get_var(
        "SELECT project_number FROM {$projects_table} WHERE project_number LIKE 'BV-{$year}-%' ORDER BY project_number DESC LIMIT 1"
    );
    if ( $last_num ) {
        $parts = explode( '-', $last_num );
        $next = (int) end( $parts ) + 1;
    } else {
        $next = 1;
    }
    $project_number = 'BV-' . $year . '-' . str_pad( $next, 6, '0', STR_PAD_LEFT );

    // Get BV services from order items
    $bv_service_ids = array();
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $svc = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$services_table} WHERE woo_product_id = %d",
            $product_id
        ) );
        if ( $svc ) {
            $bv_service_ids[] = $svc;
        }
    }

    if ( empty( $bv_service_ids ) ) {
        return; // No BV services in this order
    }

    // Create project
    $wpdb->insert( $projects_table, array(
        'project_number'   => $project_number,
        'client_user_id'  => $user_id,
        'client_name'     => $billing['first_name'] . ' ' . $billing['last_name'],
        'client_email'    => $billing['email'],
        'client_phone'    => $billing['phone'],
        'client_company'  => $billing['company'],
        'wc_order_id'     => $order_id,
        'status'          => 'awaiting-agreement',
        'progress_percent'=> 0,
        'notes'           => 'Auto-created from WooCommerce order #' . $order_id,
        'assigned_to'     => '',
        'internal_notes'   => '',
    ), array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' ) );

    $project_id = $wpdb->insert_id;

    // Link services to project
    foreach ( $bv_service_ids as $svc_id ) {
        $wpdb->insert( $project_services_table, array(
            'project_id' => $project_id,
            'service_id' => $svc_id,
            'status'     => 'pending',
        ), array( '%d', '%d', '%s' ) );
    }

    // Log activity
    $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
        'project_id'   => $project_id,
        'entity_type'  => 'project',
        'entity_id'    => $project_id,
        'action'       => 'created',
        'description'  => 'Project auto-created from WooCommerce order #' . $order_id,
        'metadata'     => json_encode( array( 'order_id' => $order_id, 'services' => $bv_service_ids ) ),
        'user_id'      => $user_id,
    ), array( '%d', '%s', '%d', '%s', '%s', '%s', '%d' ) );
}

/**
 * WooCommerce thank-you page — shows post-purchase navigation for services + courses
 */
function bv_handle_wc_thankyou( $order_id ) {
    if ( ! class_exists( 'BV_WooCommerce' ) ) return;
    BV_WooCommerce::render_post_purchase_notice( $order_id );
}

/**
 * Main plugin class
 */
final class BusinessVance_Services_Manager {

    private static $instance = null;
    public $settings;
    public $admin;
    public $shortcodes;
    public $client_portal;
    public $consultant_dashboard;
    public $woocommerce;
    public $questionnaire_admin;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_components();
        $this->init_wc_hooks();
    }

    private function includes() {
        require_once BV_PLUGIN_DIR . 'includes/class-bv-settings.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-admin.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-shortcodes.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-client-portal.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-consultant-dashboard.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-questionnaire-admin.php';
        // Import class loaded on-demand only when import is triggered (see ajax_import_questionnaires)
        require_once BV_PLUGIN_DIR . 'includes/class-bv-woocommerce.php';
    }

    private function init_components() {
        $this->admin                = new BV_Admin();
        $this->shortcodes           = new BV_Shortcodes();
        $this->client_portal        = new BV_Client_Portal();
        $this->consultant_dashboard = new BV_Consultant_Dashboard();
        $this->settings             = new BV_Settings();
        $this->questionnaire_admin   = new BV_Questionnaire_Admin();
        BV_WooCommerce::init();
    }

    private function init_wc_hooks() {
        if ( BV_WooCommerce::is_active() ) {
            add_action( 'woocommerce_order_status_completed', 'bv_handle_wc_order_completion', 10, 2 );
            add_action( 'woocommerce_order_status_processing', 'bv_handle_wc_order_completion', 10, 2 );
            add_action( 'woocommerce_thankyou', 'bv_handle_wc_thankyou', 10, 1 );
        }
    }

    private function __clone() {}
    public function __wakeup() { throw new Exception( 'Cannot unserialize singleton' ); }
}

function businessvance_services_manager() {
    return BusinessVance_Services_Manager::instance();
}

businessvance_services_manager();
