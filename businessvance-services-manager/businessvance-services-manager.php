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

// Include the activator early so activation hooks can use it
require_once BV_PLUGIN_DIR . 'includes/class-bv-activator.php';

/**
 * Plugin activation callback (standalone, not dependent on singleton)
 */
function bv_plugin_activate() {
    $activator = new BV_Activator();
    $activator->activate();
}
register_activation_hook( __FILE__, 'bv_plugin_activate' );

/**
 * Plugin deactivation callback
 */
function bv_plugin_deactivate() {
    $activator = new BV_Activator();
    $activator->deactivate();
}
register_deactivation_hook( __FILE__, 'bv_plugin_deactivate' );

/**
 * Main plugin class
 */
final class BusinessVance_Services_Manager {

    /** @var BusinessVance_Services_Manager singleton instance */
    private static $instance = null;

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
     * Constructor — runs at plugin load time
     */
    private function __construct() {
        $this->includes();
        $this->init_components();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once BV_PLUGIN_DIR . 'includes/class-bv-admin.php';
        require_once BV_PLUGIN_DIR . 'includes/class-bv-shortcodes.php';
    }

    /**
     * Initialize plugin components immediately (not deferred)
     */
    private function init_components() {
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