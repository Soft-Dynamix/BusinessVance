<?php
/**
 * BusinessVance Services Manager - Plugin Activator
 *
 * Handles plugin activation: creates custom database tables and inserts default data.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Activator
 *
 * Static methods for plugin activation tasks.
 */
class BV_Activator {

    /**
     * Default categories inserted on activation.
     *
     * @var array
     */
    private static $default_categories = array(
        'Business Planning',
        'Finance',
        'Marketing',
        'Strategy',
        'Advisory Services',
        'Business Reports',
    );

    /**
     * Run activation tasks.
     *
     * Creates all custom database tables and seeds default categories.
     *
     * @return void
     */
    public static function activate() {
        self::create_tables();
        self::seed_default_categories();
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables using dbDelta.
     *
     * @return void
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── Categories Table ──────────────────────────────────────────────
        $categories_table = $wpdb->prefix . 'bv_categories';
        $sql_categories   = "CREATE TABLE $categories_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            color varchar(7) DEFAULT '#002B5C',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta( $sql_categories );

        // ── Services Table ────────────────────────────────────────────────
        $services_table = $wpdb->prefix . 'bv_services';
        $sql_services   = "CREATE TABLE $services_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL DEFAULT 0,
            icon varchar(100) DEFAULT 'FileText',
            button_label varchar(100) DEFAULT 'ADD TO CART',
            button_type varchar(20) DEFAULT 'cart',
            button_url varchar(500) DEFAULT '',
            woocommerce_product_id varchar(50) DEFAULT '',
            category_id bigint(20) DEFAULT NULL,
            visible tinyint(1) DEFAULT 1,
            featured tinyint(1) DEFAULT 0,
            display_order int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY category_id (category_id)
        ) $charset_collate;";
        dbDelta( $sql_services );

        // ── Plans Table ───────────────────────────────────────────────────
        $plans_table = $wpdb->prefix . 'bv_plans';
        $sql_plans   = "CREATE TABLE $plans_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subtitle varchar(255) DEFAULT '',
            price decimal(10,2) NOT NULL DEFAULT 0,
            color varchar(7) DEFAULT '#002B5C',
            button_label varchar(100) DEFAULT 'GET STARTED',
            button_type varchar(20) DEFAULT 'cart',
            button_url varchar(500) DEFAULT '',
            woocommerce_product_id varchar(50) DEFAULT '',
            category_id bigint(20) DEFAULT NULL,
            visible tinyint(1) DEFAULT 1,
            featured tinyint(1) DEFAULT 0,
            display_order int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta( $sql_plans );

        // ── Plan Features Table ───────────────────────────────────────────
        $features_table = $wpdb->prefix . 'bv_plan_features';
        $sql_features   = "CREATE TABLE $features_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            plan_id bigint(20) NOT NULL,
            text varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY plan_id (plan_id)
        ) $charset_collate;";
        dbDelta( $sql_features );

        // Store plugin version in options table for future upgrade checks.
        update_option( 'bv_services_manager_version', '1.0.0' );
    }

    /**
     * Seed default categories if they don't already exist.
     *
     * @return void
     */
    private static function seed_default_categories() {
        global $wpdb;

        $table = $wpdb->prefix . 'bv_categories';

        foreach ( self::$default_categories as $name ) {
            $slug = sanitize_title( $name );

            // Only insert if the slug doesn't already exist.
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug )
            );

            if ( is_null( $exists ) ) {
                $wpdb->insert(
                    $table,
                    array(
                        'name'  => $name,
                        'slug'  => $slug,
                        'color' => '#002B5C',
                    ),
                    array( '%s', '%s', '%s' )
                );
            }
        }
    }
}