<?php
/**
 * Plugin Name: BusinessVance Services Manager
 * Plugin URI: https://www.studyvance.co.za
 * Description: Dynamically manage BusinessVance Consulting services, subscription plans, and categories from WP Admin. Links services to WooCommerce products with Yoco payment support.
 * Version: 1.0.0
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

define( 'BV_VERSION', '1.0.0' );
define( 'BV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class BusinessVance_Services_Manager {

    /** @var BusinessVance_Services_Manager singleton instance */
    private static $instance = null;

    /** @var BV_Activator */
    public $activator;

    /** @var BV_Admin */
    public $admin;

    /** @var BV_Shortcodes */
    public $shortcodes;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once BV_PLUGIN_DIR . 'includes/class-bv-activator.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-admin.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-shortcodes.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation / Deactivation
        register_activation_hook( __FILE__, array( $this->activator, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this->activator, 'deactivate' ) );

        // Initialize components
        add_action( 'plugins_loaded', array( $this, 'init_components' ) );
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        $this->activator  = new BV_Activator();
        $this->admin      = new BV_Admin();
        $this->shortcodes = new BV_Shortcodes();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton' );
    }
}

/**
 * Initialize plugin
 */
function businessvance_services_manager() {
    return BusinessVance_Services_Manager::instance();
}

// Start the plugin
businessvance_services_manager();