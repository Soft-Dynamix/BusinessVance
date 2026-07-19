<?php
/**
 * Database activator for BusinessVance Services Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Activator {

    /** @var string Database table prefix */
    private $table_prefix;

    /** @var string Charset collate */
    private $charset_collate;

    public function __construct() {
        global $wpdb;
        $this->table_prefix  = $wpdb->prefix;
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_tables();
        $this->insert_default_data();
        update_option( 'bv_plugin_version', BV_VERSION );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up cron jobs or transients if needed
        delete_option( 'bv_plugin_version' );
    }

    /**
     * Create custom database tables
     */
    private function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Categories table
        $categories_table = $this->table_prefix . 'bv_categories';
        $sql1 = "CREATE TABLE {$categories_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            color varchar(50) DEFAULT '#008080',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) {$this->charset_collate};";
        dbDelta( $sql1 );

        // Services table
        $services_table = $this->table_prefix . 'bv_services';
        $sql2 = "CREATE TABLE {$services_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            price varchar(100) DEFAULT '0',
            price_display varchar(100) DEFAULT '',
            icon varchar(100) DEFAULT 'briefcase',
            button_label varchar(200) DEFAULT 'Get Started',
            service_type varchar(50) DEFAULT 'onceoff',
            woo_product_id bigint(20) UNSIGNED DEFAULT 0,
            category_id bigint(20) UNSIGNED DEFAULT 0,
            is_visible tinyint(1) NOT NULL DEFAULT 1,
            is_featured tinyint(1) NOT NULL DEFAULT 0,
            display_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category_id (category_id),
            KEY display_order (display_order),
            KEY is_visible (is_visible)
        ) {$this->charset_collate};";
        dbDelta( $sql2 );

        // Plans table
        $plans_table = $this->table_prefix . 'bv_plans';
        $sql3 = "CREATE TABLE {$plans_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subtitle varchar(255) DEFAULT '',
            price varchar(100) DEFAULT '0',
            color varchar(50) DEFAULT '#008080',
            button_label varchar(200) DEFAULT 'Subscribe Now',
            woo_product_id bigint(20) UNSIGNED DEFAULT 0,
            category_id bigint(20) UNSIGNED DEFAULT 0,
            is_visible tinyint(1) NOT NULL DEFAULT 1,
            is_featured tinyint(1) NOT NULL DEFAULT 0,
            display_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category_id (category_id),
            KEY display_order (display_order),
            KEY is_visible (is_visible)
        ) {$this->charset_collate};";
        dbDelta( $sql3 );

        // Plan features table
        $features_table = $this->table_prefix . 'bv_plan_features';
        $sql4 = "CREATE TABLE {$features_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_id bigint(20) UNSIGNED NOT NULL,
            feature_text varchar(500) NOT NULL,
            display_order int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY plan_id (plan_id),
            KEY display_order (display_order)
        ) {$this->charset_collate};";
        dbDelta( $sql4 );
    }

    /**
     * Insert default categories and sample data
     */
    private function insert_default_data() {
        global $wpdb;

        $categories_table = $wpdb->prefix . 'bv_categories';

        // Check if data already exists
        $existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$categories_table}" );
        if ( $existing > 0 ) {
            return;
        }

        // Default categories
        $default_categories = array(
            array( 'Business Strategy', 'business-strategy', '#002B5C' ),
            array( 'Financial Services', 'financial-services', '#008080' ),
            array( 'Marketing & Growth', 'marketing-growth', '#D4AF37' ),
            array( 'Digital & Technology', 'digital-technology', '#2E86AB' ),
        );

        foreach ( $default_categories as $cat ) {
            $wpdb->insert(
                $categories_table,
                array(
                    'name'  => $cat[0],
                    'slug'  => $cat[1],
                    'color' => $cat[2],
                ),
                array( '%s', '%s', '%s' )
            );
        }

        // Insert default once-off services
        $services_table = $wpdb->prefix . 'bv_services';
        $default_services = array(
            array( 'Business Registration', 'Complete company registration with CIPC including all necessary documentation and compliance certificates.', 'R1,500', 'R1,500', 'building', 'Register Now', 'onceoff', 0, 1, 1, 1, 0 ),
            array( 'Tax Clearance Certificate', 'Obtain your tax clearance certificate from SARS. Includes all supporting documentation preparation.', 'R800', 'R800', 'file-check', 'Apply Now', 'onceoff', 0, 1, 1, 1, 1 ),
            array( 'BEE Affidavit', 'Professional B-BBEE affidavit preparation and certification for your business.', 'R600', 'R600', 'award', 'Get Affidavit', 'onceoff', 0, 1, 1, 1, 2 ),
            array( 'Business Plan Writing', 'Comprehensive business plan tailored for funding applications or strategic planning.', 'R3,500', 'R3,500', 'file-text', 'Get Started', 'onceoff', 0, 1, 1, 1, 3 ),
            array( 'Financial Statements', 'Annual financial statements prepared according to IFRS for SME standards.', 'R4,000', 'R4,000', 'bar-chart-3', 'Get Started', 'onceoff', 0, 2, 1, 1, 4 ),
            array( 'Tax Returns (Individual)', 'Professional personal income tax return filing and optimization.', 'R1,200', 'R1,200', 'calculator', 'File Now', 'onceoff', 0, 2, 1, 1, 5 ),
            array( 'Tax Returns (Business)', 'Complete business tax return preparation and submission to SARS.', 'R2,500', 'R2,500', 'receipt', 'File Now', 'onceoff', 0, 2, 1, 1, 6 ),
            array( 'Payroll Registration', 'Register your business for PAYE, UI-19 and SDL with SARS.', 'R1,000', 'R1,000', 'users', 'Register Now', 'onceoff', 0, 2, 1, 1, 7 ),
            array( 'Logo & Brand Identity', 'Professional logo design and brand identity package including guidelines.', 'R5,000', 'R5,000', 'palette', 'Get Started', 'onceoff', 0, 3, 1, 1, 8 ),
            array( 'Social Media Setup', 'Complete social media profile setup and optimization across all major platforms.', 'R2,000', 'R2,000', 'share-2', 'Get Started', 'onceoff', 0, 3, 1, 1, 9 ),
            array( 'Website Development', 'Professional responsive website design and development for your business.', 'R8,000', 'R8,000', 'globe', 'Get Started', 'onceoff', 0, 4, 1, 1, 10 ),
        );

        foreach ( $default_services as $svc ) {
            $wpdb->insert(
                $services_table,
                array(
                    'name'          => $svc[0],
                    'description'   => $svc[1],
                    'price'         => $svc[2],
                    'price_display' => $svc[3],
                    'icon'          => $svc[4],
                    'button_label'  => $svc[5],
                    'service_type'  => $svc[6],
                    'woo_product_id' => $svc[7],
                    'category_id'   => $svc[8],
                    'is_visible'    => $svc[9],
                    'is_featured'   => $svc[10],
                    'display_order' => $svc[11],
                ),
                array( '%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%d' )
            );
        }

        // Insert default subscription plans
        $plans_table = $wpdb->prefix . 'bv_plans';
        $default_plans = array(
            array( 'Starter', 'Perfect for new entrepreneurs', 'R299/mo', '#008080', 'Get Started', 0, 1, 1, 0, 0 ),
            array( 'Professional', 'For growing businesses', 'R599/mo', '#002B5C', 'Get Started', 0, 1, 1, 1, 1 ),
            array( 'Business Partner', 'Full-service partnership', 'R999/mo', '#D4AF37', 'Get Started', 0, 1, 1, 0, 2 ),
        );

        foreach ( $default_plans as $plan ) {
            $wpdb->insert(
                $plans_table,
                array(
                    'name'          => $plan[0],
                    'subtitle'      => $plan[1],
                    'price'         => $plan[2],
                    'color'         => $plan[3],
                    'button_label'  => $plan[4],
                    'woo_product_id' => $plan[5],
                    'category_id'   => $plan[6],
                    'is_visible'    => $plan[7],
                    'is_featured'   => $plan[8],
                    'display_order' => $plan[9],
                ),
                array( '%s','%s','%s','%s','%s','%d','%d','%d','%d','%d' )
            );
        }

        // Insert default plan features
        $features_table = $wpdb->prefix . 'bv_plan_features';
        $plan_features = array(
            // Starter plan (id=1) features
            array( 1, 'Basic business consultation', 0 ),
            array( 1, 'Monthly financial health check', 1 ),
            array( 1, 'Email support (48hr response)', 2 ),
            array( 1, 'Access to resource library', 3 ),
            // Professional plan (id=2) features
            array( 2, 'Everything in Starter', 0 ),
            array( 2, 'Dedicated account manager', 1 ),
            array( 2, 'Quarterly business reviews', 2 ),
            array( 2, 'Priority support (24hr response)', 3 ),
            array( 2, 'Tax planning & optimization', 4 ),
            array( 2, 'Marketing strategy sessions', 5 ),
            // Business Partner plan (id=3) features
            array( 3, 'Everything in Professional', 0 ),
            array( 3, 'Unlimited consultations', 1 ),
            array( 3, 'Monthly strategic planning', 2 ),
            array( 3, 'CFO advisory services', 3 ),
            array( 3, 'BEE compliance management', 4 ),
            array( 3, '24/7 priority support', 5 ),
            array( 3, 'Annual business retreat planning', 6 ),
        );

        foreach ( $plan_features as $feature ) {
            $wpdb->insert(
                $features_table,
                array(
                    'plan_id'       => $feature[0],
                    'feature_text'  => $feature[1],
                    'display_order' => $feature[2],
                ),
                array( '%d', '%s', '%d' )
            );
        }
    }
}