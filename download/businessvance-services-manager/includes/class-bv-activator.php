<?php
/**
 * BusinessVance Services Manager - Database Activator
 *
 * Creates all required database tables and seeds demo data on plugin activation.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

class BV_Activator {

        /**
         * Plugin activation callback.
         *
         * Creates all database tables and seeds initial demo data.
         *
         * @since 1.0.0
         * @return void
         */
        public static function activate() {
                self::create_tables();
                self::seed_data();
        }

        /**
         * Create all plugin database tables using dbDelta.
         *
         * @since 1.0.0
         * @return void
         */
        private static function create_tables() {
                global $wpdb;

                $charset_collate = $wpdb->get_charset_collate();

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                // -------------------------------------------------------
                // 1. bv_categories
                // -------------------------------------------------------
                $table_categories = $wpdb->prefix . 'bv_categories';

                $sql_categories = "CREATE TABLE {$table_categories} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name varchar(200) NOT NULL DEFAULT '',
                        slug varchar(200) NOT NULL DEFAULT '',
                        color varchar(50) NOT NULL DEFAULT '#008080',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        UNIQUE KEY slug (slug)
                ) {$charset_collate};";

                dbDelta( $sql_categories );

                // -------------------------------------------------------
                // 2. bv_services
                // -------------------------------------------------------
                $table_services = $wpdb->prefix . 'bv_services';

                $sql_services = "CREATE TABLE {$table_services} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name varchar(255) NOT NULL DEFAULT '',
                        description text NOT NULL,
                        price varchar(100) NOT NULL DEFAULT '',
                        price_display varchar(100) NOT NULL DEFAULT '',
                        icon varchar(100) NOT NULL DEFAULT 'briefcase',
                        button_label varchar(200) NOT NULL DEFAULT 'Get Started',
                        service_type varchar(50) NOT NULL DEFAULT 'onceoff',
                        woo_product_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        questionnaire_template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        category_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        is_visible tinyint(1) NOT NULL DEFAULT 1,
                        is_featured tinyint(1) NOT NULL DEFAULT 0,
                        display_order int(11) NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_services );

                // -------------------------------------------------------
                // 3. bv_plans
                // -------------------------------------------------------
                $table_plans = $wpdb->prefix . 'bv_plans';

                $sql_plans = "CREATE TABLE {$table_plans} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name varchar(255) NOT NULL DEFAULT '',
                        subtitle varchar(255) NOT NULL DEFAULT '',
                        price varchar(100) NOT NULL DEFAULT '',
                        color varchar(50) NOT NULL DEFAULT '',
                        button_label varchar(255) NOT NULL DEFAULT 'Get Started',
                        woo_product_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        category_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        is_visible tinyint(1) NOT NULL DEFAULT 1,
                        is_featured tinyint(1) NOT NULL DEFAULT 0,
                        display_order int(11) NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_plans );

                // -------------------------------------------------------
                // 4. bv_plan_features
                // -------------------------------------------------------
                $table_plan_features = $wpdb->prefix . 'bv_plan_features';

                $sql_plan_features = "CREATE TABLE {$table_plan_features} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        plan_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        feature_text varchar(500) NOT NULL DEFAULT '',
                        display_order int(11) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_plan_features );

                // -------------------------------------------------------
                // 5. bv_projects
                // -------------------------------------------------------
                $table_projects = $wpdb->prefix . 'bv_projects';

                $sql_projects = "CREATE TABLE {$table_projects} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_number varchar(50) NOT NULL DEFAULT '',
                        client_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        client_name varchar(255) NOT NULL DEFAULT '',
                        client_email varchar(255) NOT NULL DEFAULT '',
                        client_phone varchar(50) NOT NULL DEFAULT '',
                        client_company varchar(255) NOT NULL DEFAULT '',
                        wc_order_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        status varchar(50) NOT NULL DEFAULT 'awaiting-agreement',
                        progress_percent int(11) NOT NULL DEFAULT 0,
                        notes text NOT NULL,
                        assigned_to varchar(255) NOT NULL DEFAULT '',
                        internal_notes text NOT NULL,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        UNIQUE KEY project_number (project_number)
                ) {$charset_collate};";

                dbDelta( $sql_projects );

                // -------------------------------------------------------
                // 6. bv_project_services
                // -------------------------------------------------------
                $table_project_services = $wpdb->prefix . 'bv_project_services';

                $sql_project_services = "CREATE TABLE {$table_project_services} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        status varchar(50) NOT NULL DEFAULT 'pending',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_project_services );

                // -------------------------------------------------------
                // 7. bv_project_agreements
                // -------------------------------------------------------
                $table_project_agreements = $wpdb->prefix . 'bv_project_agreements';

                $sql_project_agreements = "CREATE TABLE {$table_project_agreements} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        template_content text NOT NULL,
                        full_name varchar(255) NOT NULL DEFAULT '',
                        ip_address varchar(45) NOT NULL DEFAULT '',
                        user_agent text NOT NULL,
                        agreed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_project_agreements );

                // -------------------------------------------------------
                // 8. bv_project_documents
                // -------------------------------------------------------
                $table_project_documents = $wpdb->prefix . 'bv_project_documents';

                $sql_project_documents = "CREATE TABLE {$table_project_documents} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        name varchar(255) NOT NULL DEFAULT '',
                        filename varchar(255) NOT NULL DEFAULT '',
                        filepath varchar(500) NOT NULL DEFAULT '',
                        filesize bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        mime_type varchar(100) NOT NULL DEFAULT '',
                        category varchar(50) NOT NULL DEFAULT 'other',
                        uploaded_by varchar(255) NOT NULL DEFAULT 'client',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_project_documents );

                // -------------------------------------------------------
                // 9. bv_project_reports
                // -------------------------------------------------------
                $table_project_reports = $wpdb->prefix . 'bv_project_reports';

                $sql_project_reports = "CREATE TABLE {$table_project_reports} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        title varchar(255) NOT NULL DEFAULT '',
                        filename varchar(255) NOT NULL DEFAULT '',
                        filepath varchar(500) NOT NULL DEFAULT '',
                        filesize bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        mime_type varchar(100) NOT NULL DEFAULT '',
                        status varchar(50) NOT NULL DEFAULT 'draft',
                        version varchar(20) NOT NULL DEFAULT '1.0',
                        delivered_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_project_reports );

                // -------------------------------------------------------
                // 10. bv_project_messages
                // -------------------------------------------------------
                $table_project_messages = $wpdb->prefix . 'bv_project_messages';

                $sql_project_messages = "CREATE TABLE {$table_project_messages} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        sender_type varchar(20) NOT NULL DEFAULT 'client',
                        sender_name varchar(255) NOT NULL DEFAULT '',
                        sender_email varchar(255) NOT NULL DEFAULT '',
                        message text NOT NULL,
                        is_read tinyint(1) NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_project_messages );

                // -------------------------------------------------------
                // 11. bv_project_notes
                // -------------------------------------------------------
                $table_project_notes = $wpdb->prefix . 'bv_project_notes';

                $sql_project_notes = "CREATE TABLE {$table_project_notes} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        author_name varchar(255) NOT NULL DEFAULT '',
                        content text NOT NULL,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_project_notes );

                // -------------------------------------------------------
                // 12. bv_questionnaire_templates
                // -------------------------------------------------------
                $table_questionnaire_templates = $wpdb->prefix . 'bv_questionnaire_templates';

                $sql_questionnaire_templates = "CREATE TABLE {$table_questionnaire_templates} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name varchar(255) NOT NULL DEFAULT '',
                        slug varchar(200) NOT NULL DEFAULT '',
                        description text NOT NULL,
                        version varchar(20) NOT NULL DEFAULT '1.0',
                        status varchar(50) NOT NULL DEFAULT 'draft',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        UNIQUE KEY slug (slug)
                ) {$charset_collate};";

                dbDelta( $sql_questionnaire_templates );

                // -------------------------------------------------------
                // 13. bv_questionnaire_sections
                // -------------------------------------------------------
                $table_questionnaire_sections = $wpdb->prefix . 'bv_questionnaire_sections';

                $sql_questionnaire_sections = "CREATE TABLE {$table_questionnaire_sections} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        title varchar(255) NOT NULL DEFAULT '',
                        description text NOT NULL,
                        display_order int(11) NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_questionnaire_sections );

                // -------------------------------------------------------
                // 14. bv_questionnaire_questions
                // -------------------------------------------------------
                $table_questionnaire_questions = $wpdb->prefix . 'bv_questionnaire_questions';

                $sql_questionnaire_questions = "CREATE TABLE {$table_questionnaire_questions} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        section_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        type varchar(50) NOT NULL DEFAULT 'text',
                        label varchar(500) NOT NULL DEFAULT '',
                        placeholder varchar(500) NOT NULL DEFAULT '',
                        is_required tinyint(1) NOT NULL DEFAULT 0,
                        options text NOT NULL,
                        help_text varchar(500) NOT NULL DEFAULT '',
                        display_order int(11) NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_questionnaire_questions );

                // -------------------------------------------------------
                // 15. bv_questionnaire_responses
                // -------------------------------------------------------
                $table_questionnaire_responses = $wpdb->prefix . 'bv_questionnaire_responses';

                $sql_questionnaire_responses = "CREATE TABLE {$table_questionnaire_responses} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        question_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        response_value text NOT NULL,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_questionnaire_responses );

                // -------------------------------------------------------
                // 16. bv_activity_log
                // -------------------------------------------------------
                $table_activity_log = $wpdb->prefix . 'bv_activity_log';

                $sql_activity_log = "CREATE TABLE {$table_activity_log} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        project_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        entity_type varchar(50) NOT NULL DEFAULT '',
                        entity_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        action varchar(100) NOT NULL DEFAULT '',
                        description text NOT NULL,
                        metadata text NOT NULL,
                        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_activity_log );

                // Store the current version for future update comparisons.
                update_option( 'bv_services_manager_db_version', BV_VERSION, false );
        }

        /**
         * Seed demo data into the database.
         *
         * Checks if data already exists before inserting. Returns early
         * if the categories table already has rows, indicating the
         * plugin has been activated previously.
         *
         * @since 1.0.0
         * @return void
         */
        private static function seed_data() {
                global $wpdb;

                $table_categories = $wpdb->prefix . 'bv_categories';

                // Prevent re-seeding on subsequent activations.
                $existing_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_categories}" );

                if ( $existing_count > 0 ) {
                        return;
                }

                // ----------------------------------------------------------------
                // Seed Categories
                // ----------------------------------------------------------------
                $categories = array(
                        array(
                                'name'  => 'Research & Analysis',
                                'slug'  => 'research-analysis',
                                'color' => '#0A2647',
                        ),
                        array(
                                'name'  => 'Planning & Strategy',
                                'slug'  => 'planning-strategy',
                                'color' => '#2A9D8F',
                        ),
                        array(
                                'name'  => 'Advisory & Consulting',
                                'slug'  => 'advisory-consulting',
                                'color' => '#F4A261',
                        ),
                        array(
                                'name'  => 'Implementation',
                                'slug'  => 'implementation',
                                'color' => '#264653',
                        ),
                );

                $category_ids = array();

                foreach ( $categories as $cat ) {
                        $wpdb->insert(
                                $table_categories,
                                array(
                                        'name'       => $cat['name'],
                                        'slug'       => $cat['slug'],
                                        'color'      => $cat['color'],
                                        'created_at' => current_time( 'mysql' ),
                                ),
                                array( '%s', '%s', '%s', '%s' )
                        );
                        $category_ids[ $cat['slug'] ] = $wpdb->insert_id;
                }

                // ----------------------------------------------------------------
                // Seed Services
                // ----------------------------------------------------------------
                $table_services = $wpdb->prefix . 'bv_services';

                $services = array(
                        // Research & Analysis
                        array(
                                'name'          => 'Business Feasibility Report',
                                'description'   => 'A comprehensive feasibility analysis evaluating the viability of your business concept, including market potential, financial projections, and risk assessment.',
                                'price'         => '4500.00',
                                'price_display' => 'R4,500',
                                'icon'          => 'file-search',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['research-analysis'],
                                'is_visible'    => 1,
                                'is_featured'   => 1,
                                'display_order' => 1,
                        ),
                        array(
                                'name'          => 'Market Research Report',
                                'description'   => 'In-depth market analysis covering industry trends, competitor landscape, target audience profiling, and market sizing to inform strategic decisions.',
                                'price'         => '5500.00',
                                'price_display' => 'R5,500',
                                'icon'          => 'bar-chart-3',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['research-analysis'],
                                'is_visible'    => 1,
                                'is_featured'   => 1,
                                'display_order' => 2,
                        ),
                        array(
                                'name'          => 'Financial Analysis',
                                'description'   => 'Detailed financial assessment including cash flow projections, break-even analysis, profit margin evaluation, and funding requirement analysis.',
                                'price'         => '3800.00',
                                'price_display' => 'R3,800',
                                'icon'          => 'trending-up',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['research-analysis'],
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 3,
                        ),
                        // Planning & Strategy
                        array(
                                'name'          => 'Business Plan Development',
                                'description'   => 'Professional business plan preparation for funding applications, investor pitches, or internal strategic planning with financial models.',
                                'price'         => '7500.00',
                                'price_display' => 'R7,500',
                                'icon'          => 'clipboard-list',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['planning-strategy'],
                                'is_visible'    => 1,
                                'is_featured'   => 1,
                                'display_order' => 4,
                        ),
                        array(
                                'name'          => 'Strategic Growth Plan',
                                'description'   => 'A tailored growth strategy outlining expansion opportunities, market penetration tactics, and sustainable scaling pathways.',
                                'price'         => '6500.00',
                                'price_display' => 'R6,500',
                                'icon'          => 'target',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['planning-strategy'],
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 5,
                        ),
                        array(
                                'name'          => 'Marketing Strategy',
                                'description'   => 'Comprehensive marketing plan covering digital and traditional channels, brand positioning, customer acquisition, and campaign frameworks.',
                                'price'         => '5000.00',
                                'price_display' => 'R5,000',
                                'icon'          => 'megaphone',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['planning-strategy'],
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 6,
                        ),
                        // Advisory & Consulting
                        array(
                                'name'          => 'Business Advisory Session',
                                'description'   => 'One-on-one consulting session with experienced business advisors to address specific challenges, explore opportunities, and gain expert insights.',
                                'price'         => '1500.00',
                                'price_display' => 'R1,500',
                                'icon'          => 'message-circle',
                                'button_label'  => 'Book Session',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['advisory-consulting'],
                                'is_visible'    => 1,
                                'is_featured'   => 1,
                                'display_order' => 7,
                        ),
                        array(
                                'name'          => 'Compliance Assessment',
                                'description'   => 'Thorough review of your business compliance status covering regulatory requirements, industry standards, and risk mitigation strategies.',
                                'price'         => '3200.00',
                                'price_display' => 'R3,200',
                                'icon'          => 'shield-check',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['advisory-consulting'],
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 8,
                        ),
                        array(
                                'name'          => 'Tax Planning Consultation',
                                'description'   => 'Expert tax planning advice to optimize your tax position, ensure SARS compliance, and identify legitimate tax-saving opportunities.',
                                'price'         => '2800.00',
                                'price_display' => 'R2,800',
                                'icon'          => 'calculator',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['advisory-consulting'],
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 9,
                        ),
                        // Implementation
                        array(
                                'name'          => 'Company Registration',
                                'description'   => 'End-to-end company registration service with CIPC, including name reservation, incorporation documents, and registration certificates.',
                                'price'         => '1750.00',
                                'price_display' => 'R1,750',
                                'icon'          => 'building-2',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['implementation'],
                                'is_visible'    => 1,
                                'is_featured'   => 1,
                                'display_order' => 10,
                        ),
                        array(
                                'name'          => 'BEE Certificate Assistance',
                                'description'   => 'Professional assistance with B-BBEE certification, including scorecard assessment, documentation preparation, and affidavit guidance.',
                                'price'         => '2500.00',
                                'price_display' => 'R2,500',
                                'icon'          => 'award',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['implementation'],
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 11,
                        ),
                        array(
                                'name'          => 'Logo & Brand Identity',
                                'description'   => 'Professional logo design and brand identity package including logo concepts, colour palette, typography, and brand guidelines document.',
                                'price'         => '4200.00',
                                'price_display' => 'R4,200',
                                'icon'          => 'palette',
                                'button_label'  => 'Get Started',
                                'service_type'  => 'onceoff',
                                'category_id'   => $category_ids['implementation'],
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 12,
                        ),
                );

                $service_ids = array();

                foreach ( $services as $svc ) {
                        $wpdb->insert(
                                $table_services,
                                array(
                                        'name'           => $svc['name'],
                                        'description'    => $svc['description'],
                                        'price'          => $svc['price'],
                                        'price_display'  => $svc['price_display'],
                                        'icon'           => $svc['icon'],
                                        'button_label'   => $svc['button_label'],
                                        'service_type'   => $svc['service_type'],
                                        'woo_product_id' => 0,
                                        'category_id'    => $svc['category_id'],
                                        'is_visible'     => $svc['is_visible'],
                                        'is_featured'    => $svc['is_featured'],
                                        'display_order'  => $svc['display_order'],
                                        'created_at'     => current_time( 'mysql' ),
                                        'updated_at'     => current_time( 'mysql' ),
                                ),
                                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
                        );
                        $service_ids[ $svc['name'] ] = $wpdb->insert_id;
                }

                // ----------------------------------------------------------------
                // Seed Plans
                // ----------------------------------------------------------------
                $table_plans = $wpdb->prefix . 'bv_plans';

                $plans = array(
                        array(
                                'name'          => 'STARTER',
                                'subtitle'      => 'Perfect for new businesses',
                                'price'         => '299',
                                'color'         => '#2A9D8F',
                                'button_label'  => 'Choose Starter',
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 1,
                        ),
                        array(
                                'name'          => 'PROFESSIONAL',
                                'subtitle'      => 'For growing businesses',
                                'price'         => '599',
                                'color'         => '#0A2647',
                                'button_label'  => 'Choose Professional',
                                'is_visible'    => 1,
                                'is_featured'   => 1,
                                'display_order' => 2,
                        ),
                        array(
                                'name'          => 'BUSINESS PARTNER',
                                'subtitle'      => 'Enterprise-grade support',
                                'price'         => '999',
                                'color'         => '#F4A261',
                                'button_label'  => 'Choose Business Partner',
                                'is_visible'    => 1,
                                'is_featured'   => 0,
                                'display_order' => 3,
                        ),
                );

                $plan_ids = array();

                foreach ( $plans as $plan ) {
                        $wpdb->insert(
                                $table_plans,
                                array(
                                        'name'           => $plan['name'],
                                        'subtitle'       => $plan['subtitle'],
                                        'price'          => $plan['price'],
                                        'color'          => $plan['color'],
                                        'button_label'   => $plan['button_label'],
                                        'woo_product_id' => 0,
                                        'category_id'    => 0,
                                        'is_visible'     => $plan['is_visible'],
                                        'is_featured'    => $plan['is_featured'],
                                        'display_order'  => $plan['display_order'],
                                        'created_at'     => current_time( 'mysql' ),
                                        'updated_at'     => current_time( 'mysql' ),
                                ),
                                array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
                        );
                        $plan_ids[ $plan['name'] ] = $wpdb->insert_id;
                }

                // ----------------------------------------------------------------
                // Seed Plan Features
                // ----------------------------------------------------------------
                $table_plan_features = $wpdb->prefix . 'bv_plan_features';

                $plan_features = array(
                        // STARTER features
                        array( 'plan' => 'STARTER', 'feature' => 'Up to 2 advisory sessions per month', 'order' => 1 ),
                        array( 'plan' => 'STARTER', 'feature' => 'Basic compliance health check', 'order' => 2 ),
                        array( 'plan' => 'STARTER', 'feature' => 'Email support (48h response)', 'order' => 3 ),
                        array( 'plan' => 'STARTER', 'feature' => 'Access to resource library', 'order' => 4 ),
                        array( 'plan' => 'STARTER', 'feature' => 'Monthly business health report', 'order' => 5 ),
                        // PROFESSIONAL features
                        array( 'plan' => 'PROFESSIONAL', 'feature' => 'Up to 5 advisory sessions per month', 'order' => 1 ),
                        array( 'plan' => 'PROFESSIONAL', 'feature' => 'Full compliance assessment', 'order' => 2 ),
                        array( 'plan' => 'PROFESSIONAL', 'feature' => 'Priority email & phone support (24h)', 'order' => 3 ),
                        array( 'plan' => 'PROFESSIONAL', 'feature' => 'Quarterly strategy review session', 'order' => 4 ),
                        array( 'plan' => 'PROFESSIONAL', 'feature' => 'Discounted à la carte services (15%)', 'order' => 5 ),
                        array( 'plan' => 'PROFESSIONAL', 'feature' => 'Access to resource library & templates', 'order' => 6 ),
                        array( 'plan' => 'PROFESSIONAL', 'feature' => 'Dedicated account manager', 'order' => 7 ),
                        // BUSINESS PARTNER features
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Unlimited advisory sessions', 'order' => 1 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Full compliance & BEE assessment', 'order' => 2 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Dedicated phone line & priority support', 'order' => 3 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Monthly strategy review sessions', 'order' => 4 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Discounted à la carte services (25%)', 'order' => 5 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Full resource library, templates & tools', 'order' => 6 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Dedicated senior account manager', 'order' => 7 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'Annual business audit & review', 'order' => 8 ),
                        array( 'plan' => 'BUSINESS PARTNER', 'feature' => 'VIP access to events & workshops', 'order' => 9 ),
                );

                foreach ( $plan_features as $pf ) {
                        $wpdb->insert(
                                $table_plan_features,
                                array(
                                        'plan_id'       => $plan_ids[ $pf['plan'] ],
                                        'feature_text'  => $pf['feature'],
                                        'display_order' => $pf['order'],
                                ),
                                array( '%d', '%s', '%d' )
                        );
                }

                // ----------------------------------------------------------------
                // Seed Questionnaire Template
                // ----------------------------------------------------------------
                $table_templates = $wpdb->prefix . 'bv_questionnaire_templates';

                $wpdb->insert(
                        $table_templates,
                        array(
                                'name'        => 'Standard Client Questionnaire',
                                'slug'        => 'standard-client-questionnaire',
                                'description' => 'A comprehensive onboarding questionnaire to gather essential company, contact, and financial information from new clients before starting a project.',
                                'version'     => '1.0',
                                'status'      => 'published',
                                'created_at'  => current_time( 'mysql' ),
                                'updated_at'  => current_time( 'mysql' ),
                        ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                );

                $template_id = $wpdb->insert_id;

                // ----------------------------------------------------------------
                // Seed Questionnaire Sections
                // ----------------------------------------------------------------
                $table_sections = $wpdb->prefix . 'bv_questionnaire_sections';

                $sections = array(
                        array(
                                'title'         => 'Company Profile',
                                'description'   => 'Please provide details about your company or business entity.',
                                'display_order' => 1,
                        ),
                        array(
                                'title'         => 'Contact Details',
                                'description'   => 'Provide the primary contact person for this project.',
                                'display_order' => 2,
                        ),
                        array(
                                'title'         => 'Financial Information',
                                'description'   => 'Help us understand your financial position so we can tailor our services accordingly.',
                                'display_order' => 3,
                        ),
                );

                $section_ids = array();

                foreach ( $sections as $sec ) {
                        $wpdb->insert(
                                $table_sections,
                                array(
                                        'template_id'   => $template_id,
                                        'title'         => $sec['title'],
                                        'description'   => $sec['description'],
                                        'display_order' => $sec['display_order'],
                                        'created_at'    => current_time( 'mysql' ),
                                ),
                                array( '%d', '%s', '%s', '%d', '%s' )
                        );
                        $section_ids[ $sec['title'] ] = $wpdb->insert_id;
                }

                // ----------------------------------------------------------------
                // Seed Questionnaire Questions
                // ----------------------------------------------------------------
                $table_questions = $wpdb->prefix . 'bv_questionnaire_questions';

                $questions = array(
                        // Section 1: Company Profile
                        array(
                                'section_id'    => $section_ids['Company Profile'],
                                'type'          => 'text',
                                'label'         => 'Company Name',
                                'placeholder'   => 'e.g. Acme (Pty) Ltd',
                                'is_required'   => 1,
                                'options'       => '[]',
                                'help_text'     => 'The registered legal name of your company.',
                                'display_order' => 1,
                        ),
                        array(
                                'section_id'    => $section_ids['Company Profile'],
                                'type'          => 'text',
                                'label'         => 'Registration Number',
                                'placeholder'   => 'e.g. 2024/123456/07',
                                'is_required'   => 0,
                                'options'       => '[]',
                                'help_text'     => 'Your CIPC registration number, if applicable.',
                                'display_order' => 2,
                        ),
                        array(
                                'section_id'    => $section_ids['Company Profile'],
                                'type'          => 'text',
                                'label'         => 'Trading Name',
                                'placeholder'   => 'e.g. Acme Trading',
                                'is_required'   => 0,
                                'options'       => '[]',
                                'help_text'     => 'The name under which your business operates, if different from the registered name.',
                                'display_order' => 3,
                        ),
                        array(
                                'section_id'    => $section_ids['Company Profile'],
                                'type'          => 'select',
                                'label'         => 'Industry',
                                'placeholder'   => 'Select your industry',
                                'is_required'   => 1,
                                'options'       => wp_json_encode( array(
                                        'Agriculture & Farming',
                                        'Construction & Engineering',
                                        'Education & Training',
                                        'Financial Services',
                                        'Healthcare & Medical',
                                        'Information Technology',
                                        'Manufacturing',
                                        'Mining & Resources',
                                        'Professional Services',
                                        'Real Estate & Property',
                                        'Retail & Wholesale',
                                        'Tourism & Hospitality',
                                        'Transport & Logistics',
                                        'Other',
                                ) ),
                                'help_text'     => 'Choose the industry that best describes your business.',
                                'display_order' => 4,
                        ),
                        array(
                                'section_id'    => $section_ids['Company Profile'],
                                'type'          => 'number',
                                'label'         => 'Years in Business',
                                'placeholder'   => 'e.g. 3',
                                'is_required'   => 1,
                                'options'       => '[]',
                                'help_text'     => 'How many years has your company been operating?',
                                'display_order' => 5,
                        ),

                        // Section 2: Contact Details
                        array(
                                'section_id'    => $section_ids['Contact Details'],
                                'type'          => 'text',
                                'label'         => 'Primary Contact Name',
                                'placeholder'   => 'e.g. John Doe',
                                'is_required'   => 1,
                                'options'       => '[]',
                                'help_text'     => 'The main person we should contact regarding this project.',
                                'display_order' => 1,
                        ),
                        array(
                                'section_id'    => $section_ids['Contact Details'],
                                'type'          => 'email',
                                'label'         => 'Email',
                                'placeholder'   => 'e.g. john@company.co.za',
                                'is_required'   => 1,
                                'options'       => '[]',
                                'help_text'     => 'The email address for all project communications.',
                                'display_order' => 2,
                        ),
                        array(
                                'section_id'    => $section_ids['Contact Details'],
                                'type'          => 'phone',
                                'label'         => 'Phone',
                                'placeholder'   => 'e.g. +27 82 123 4567',
                                'is_required'   => 1,
                                'options'       => '[]',
                                'help_text'     => 'A contact number where we can reach you during business hours.',
                                'display_order' => 3,
                        ),
                        array(
                                'section_id'    => $section_ids['Contact Details'],
                                'type'          => 'text',
                                'label'         => 'Position',
                                'placeholder'   => 'e.g. Managing Director',
                                'is_required'   => 0,
                                'options'       => '[]',
                                'help_text'     => 'Your job title or role within the company.',
                                'display_order' => 4,
                        ),

                        // Section 3: Financial Information
                        array(
                                'section_id'    => $section_ids['Financial Information'],
                                'type'          => 'select',
                                'label'         => 'Annual Revenue Range',
                                'placeholder'   => 'Select your annual revenue range',
                                'is_required'   => 1,
                                'options'       => wp_json_encode( array(
                                        'Under R1M',
                                        'R1M - R5M',
                                        'R5M - R20M',
                                        'R20M - R50M',
                                        'Over R50M',
                                ) ),
                                'help_text'     => 'Your approximate annual revenue helps us recommend the right service level.',
                                'display_order' => 1,
                        ),
                        array(
                                'section_id'    => $section_ids['Financial Information'],
                                'type'          => 'select',
                                'label'         => 'Number of Employees',
                                'placeholder'   => 'Select employee count',
                                'is_required'   => 1,
                                'options'       => wp_json_encode( array(
                                        '1 - 5 (Micro)',
                                        '6 - 20 (Small)',
                                        '21 - 50 (Medium)',
                                        '51 - 200 (Large)',
                                        '201+ (Enterprise)',
                                ) ),
                                'help_text'     => 'The current number of employees in your organisation.',
                                'display_order' => 2,
                        ),
                );

                foreach ( $questions as $q ) {
                        $wpdb->insert(
                                $table_questions,
                                array(
                                        'section_id'    => $q['section_id'],
                                        'type'          => $q['type'],
                                        'label'         => $q['label'],
                                        'placeholder'   => $q['placeholder'],
                                        'is_required'   => $q['is_required'],
                                        'options'       => $q['options'],
                                        'help_text'     => $q['help_text'],
                                        'display_order' => $q['display_order'],
                                        'created_at'    => current_time( 'mysql' ),
                                ),
                                array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
                        );
                }

                // ----------------------------------------------------------------
                // Store Default Agreement Template
                // ----------------------------------------------------------------
                $agreement_template_content = self::get_default_agreement_template();
                update_option( 'bv_default_agreement_template', $agreement_template_content, false );

                // Mark seeding as complete.
                update_option( 'bv_services_manager_seeded', '1', false );
        }

        /**
         * Return the default engagement / confidentiality agreement template.
         *
         * This HTML content is stored as a WordPress option and cloned into
         * bv_project_agreements rows when a client signs.
         *
         * @since  1.0.0
         * @return string
         */
        private static function get_default_agreement_template() {
                return '<h2 style="text-align:center;">CONFIDENTIALITY AND ENGAGEMENT AGREEMENT</h2>

<p style="text-align:center;"><strong>BusinessVance Services Manager</strong></p>

<hr />

<p>This Confidentiality and Engagement Agreement (the <strong>"Agreement"</strong>) is entered into between <strong>BusinessVance (Pty) Ltd</strong> (hereinafter referred to as the <strong>"Service Provider"</strong>) and the client identified below (hereinafter referred to as the <strong>"Client"</strong>), collectively referred to as the <strong>"Parties"</strong>).</p>

<h3>1. DEFINITIONS</h3>
<p><strong>1.1</strong> "Confidential Information" means any and all non-public information, whether written, oral, electronic, or in any other form, disclosed by either Party to the other in connection with the services to be rendered under this Agreement, including but not limited to business plans, financial data, customer lists, trade secrets, proprietary processes, and any other information that is designated as confidential or that reasonably should be understood to be confidential given the nature of the information and circumstances of disclosure.</p>

<h3>2. SCOPE OF SERVICES</h3>
<p><strong>2.1</strong> The Service Provider agrees to render the professional services as described in the service order or proposal accepted by the Client (the <strong>"Services"</strong>).</p>
<p><strong>2.2</strong> The specific deliverables, timelines, and fees for the Services shall be outlined in the relevant service proposal or order form, which forms an integral part of this Agreement.</p>

<h3>3. CLIENT OBLIGATIONS</h3>
<p><strong>3.1</strong> The Client agrees to provide all necessary information, documentation, and access required by the Service Provider to perform the Services in a timely manner.</p>
<p><strong>3.2</strong> The Client undertakes to respond to queries and requests from the Service Provider within a reasonable timeframe to avoid delays in the delivery of the Services.</p>
<p><strong>3.3</strong> The Client acknowledges that delays in providing required information may impact the agreed-upon timelines and deliverables.</p>

<h3>4. CONFIDENTIALITY OBLIGATIONS</h3>
<p><strong>4.1</strong> Each Party agrees to hold all Confidential Information of the other Party in strict confidence and not to disclose such information to any third party without the prior written consent of the disclosing Party.</p>
<p><strong>4.2</strong> Each Party agrees to use the Confidential Information solely for the purpose of performing its obligations under this Agreement and for no other purpose.</p>
<p><strong>4.3</strong> Each Party shall take all reasonable precautions to protect the Confidential Information of the other Party, using at least the same degree of care it uses to protect its own confidential information, but in no event less than reasonable care.</p>

<h3>5. INTELLECTUAL PROPERTY</h3>
<p><strong>5.1</strong> All intellectual property, including but not limited to reports, analyses, strategies, and other deliverables created by the Service Provider in the course of rendering the Services, shall remain the property of the Service Provider until full payment has been received.</p>
<p><strong>5.2</strong> Upon full payment, the Client shall be granted a non-exclusive, perpetual licence to use the deliverables for their intended business purpose.</p>

<h3>6. PAYMENT TERMS</h3>
<p><strong>6.1</strong> Fees for the Services shall be as set out in the accepted service proposal or order form.</p>
<p><strong>6.2</strong> Payment is due within 14 (fourteen) days of invoice date unless otherwise agreed in writing.</p>
<p><strong>6.3</strong> Late payments may incur interest at the rate of 2% per month on the outstanding balance.</p>

<h3>7. LIMITATION OF LIABILITY</h3>
<p><strong>7.1</strong> The Service Provider\'s total liability arising out of or in connection with this Agreement shall not exceed the total fees paid by the Client for the specific Services giving rise to the claim.</p>
<p><strong>7.2</strong> The Service Provider shall not be liable for any indirect, incidental, consequential, or punitive damages, including but not limited to loss of profits, revenue, or data.</p>

<h3>8. TERM AND TERMINATION</h3>
<p><strong>8.1</strong> This Agreement shall commence on the date of acceptance and shall remain in effect until the Services have been completed and all obligations have been fulfilled.</p>
<p><strong>8.2</strong> Either Party may terminate this Agreement by providing 14 (fourteen) days\' written notice to the other Party.</p>

<h3>9. DISPUTE RESOLUTION</h3>
<p><strong>9.1</strong> Any dispute arising out of or in connection with this Agreement shall be resolved through good-faith negotiation between the Parties.</p>
<p><strong>9.2</strong> If the dispute cannot be resolved through negotiation, the Parties agree to submit the matter to mediation before resorting to legal proceedings.</p>

<h3>10. GENERAL PROVISIONS</h3>
<p><strong>10.1</strong> This Agreement shall be governed by and construed in accordance with the laws of the Republic of South Africa.</p>
<p><strong>10.2</strong> This Agreement constitutes the entire agreement between the Parties with respect to the subject matter hereof and supersedes all prior agreements and understandings.</p>

<hr />

<p style="text-align:center;"><strong>ACCEPTANCE</strong></p>

<p>By clicking "I Agree" below, the Client acknowledges that they have read, understood, and agree to be bound by the terms and conditions set out in this Agreement.</p>

<p><strong>Client Name:</strong> {{CLIENT_NAME}}<br />
<strong>Date:</strong> {{DATE}}<br />
<strong>IP Address:</strong> {{IP_ADDRESS}}</p>';
        }
}
