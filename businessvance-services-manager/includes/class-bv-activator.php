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
            array( 'Research & Analysis', 'research-analysis', '#0A2647' ),
            array( 'Planning & Strategy', 'planning-strategy', '#2A9D8F' ),
            array( 'Advisory & Consulting', 'advisory-consulting', '#F4A261' ),
            array( 'Implementation', 'implementation', '#264653' ),
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

        // Insert default once-off services (matching reference design)
        $services_table = $wpdb->prefix . 'bv_services';
        $default_services = array(
            array( 'Business Feasibility Report', 'Comprehensive assessment of the viability and potential success of your business idea, including market demand, financial projections, and risk analysis.', 'R2,500', 'R2,500', 'clipboard-list', 'ADD TO CART', 'onceoff', 0, 1, 1, 1, 0 ),
            array( 'Market Research Report', 'In-depth analysis of your target market, customer demographics, industry trends, and competitive landscape to inform strategic decisions.', 'R3,000', 'R3,000', 'search', 'ADD TO CART', 'onceoff', 0, 1, 1, 0, 1 ),
            array( 'Competitor Analysis', 'Detailed evaluation of your competitors\' strategies, strengths, weaknesses, and market positioning to identify opportunities.', 'R2,000', 'R2,000', 'users', 'ADD TO CART', 'onceoff', 0, 1, 1, 0, 2 ),
            array( 'Startup Cost Estimate', 'Thorough breakdown of all expected costs to launch your business, including one-time and recurring expenses.', 'R1,500', 'R1,500', 'calculator', 'ADD TO CART', 'onceoff', 0, 1, 1, 0, 3 ),
            array( 'Marketing Strategy Report', 'Custom marketing plan covering digital and traditional channels, target audience segmentation, and budget allocation.', 'R3,500', 'R3,500', 'megaphone', 'ADD TO CART', 'onceoff', 0, 2, 1, 1, 4 ),
            array( 'Financial Forecast Report', '3-5 year financial projections including revenue, expenses, cash flow, and break-even analysis for investors or planning.', 'R3,000', 'R3,000', 'trending-up', 'ADD TO CART', 'onceoff', 0, 2, 1, 0, 5 ),
            array( 'Risk Assessment Report', 'Identification and evaluation of potential business risks with mitigation strategies and contingency planning.', 'R2,500', 'R2,500', 'shield-alert', 'ADD TO CART', 'onceoff', 0, 2, 1, 0, 6 ),
            array( 'Business Plan', 'Comprehensive, investor-ready business plan including executive summary, market analysis, financials, and operations plan.', 'R4,000', 'R4,000', 'file-text', 'ADD TO CART', 'onceoff', 0, 1, 1, 1, 7 ),
            array( 'Investor Readiness Report', 'Assessment of your business\'s readiness for investment, covering financials, governance, growth potential, and pitch preparation.', 'R3,500', 'R3,500', 'presentation', 'ADD TO CART', 'onceoff', 0, 1, 1, 0, 8 ),
            array( 'Business Health Check', 'Diagnostic review of your existing business operations, financials, and strategy with actionable improvement recommendations.', 'R2,000', 'R2,000', 'heart-pulse', 'ADD TO CART', 'onceoff', 0, 3, 1, 0, 9 ),
            array( 'Consulting & Strategy Session', 'One-on-one consulting session with our expert advisors to address specific business challenges and opportunities.', 'R1,200', 'R1,200', 'handshake', 'ADD TO CART', 'onceoff', 0, 3, 1, 0, 10 ),
            array( 'Implementation Support', 'Hands-on support to implement recommended strategies, processes, and systems for your business.', 'R2,500', 'R2,500', 'wrench', 'ADD TO CART', 'onceoff', 0, 4, 1, 0, 11 ),
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

        // Insert default subscription plans (matching reference design)
        $plans_table = $wpdb->prefix . 'bv_plans';
        $default_plans = array(
            array( 'STARTER', 'For new entrepreneurs', 'R299/MONTH', '#2A9D8F', 'GET STARTED', 0, 1, 1, 0, 0 ),
            array( 'PROFESSIONAL', 'For growing businesses', 'R599/MONTH', '#264653', 'UPGRADE NOW', 0, 1, 1, 1, 1 ),
            array( 'BUSINESS PARTNER', 'For serious entrepreneurs & teams', 'R999/MONTH', '#F4A261', 'JOIN NOW', 0, 1, 1, 0, 2 ),
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

        // Insert default plan features (matching reference design)
        $features_table = $wpdb->prefix . 'bv_plan_features';
        $plan_features = array(
            // Starter plan (id=1) features
            array( 1, '1 report per month', 0 ),
            array( 1, '10% discount on all services', 1 ),
            array( 1, 'Priority delivery', 2 ),
            array( 1, 'Email support', 3 ),
            array( 1, 'Monthly business tips', 4 ),
            // Professional plan (id=2) features
            array( 2, '2 reports per month', 0 ),
            array( 2, '15% discount on all services', 1 ),
            array( 2, 'Priority delivery', 2 ),
            array( 2, 'Email support', 3 ),
            array( 2, 'Monthly business tips', 4 ),
            array( 2, 'Early access to new reports', 5 ),
            // Business Partner plan (id=3) features
            array( 3, '4 reports per month', 0 ),
            array( 3, '20% discount on all services', 1 ),
            array( 3, 'Priority delivery', 2 ),
            array( 3, 'Email support', 3 ),
            array( 3, 'Monthly business tips', 4 ),
            array( 3, 'Early access to new reports', 5 ),
            array( 3, 'Dedicated account manager', 6 ),
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