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
                        agreement_template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        category_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        is_visible tinyint(1) NOT NULL DEFAULT 1,
                        is_featured tinyint(1) NOT NULL DEFAULT 0,
                        display_order int(11) NOT NULL DEFAULT 0,
                        requires_agreement tinyint(1) NOT NULL DEFAULT 1,
                        requires_questionnaire tinyint(1) NOT NULL DEFAULT 1,
                        required_document_types text NOT NULL,
                        consultant_email varchar(255) NOT NULL DEFAULT '',
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
         * Upgrade database schema for existing installations.
         *
         * Called when the plugin version changes. Uses dbDelta to add
         * any new columns or tables introduced since the last version.
         *
         * @since 2.0.7
         * @return void
         */
        public static function upgrade() {
                global $wpdb;
                $charset_collate = $wpdb->get_charset_collate();
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                // Add new columns to bv_services table (v2.0.7)
                $table_services = $wpdb->prefix . 'bv_services';
                $col = $wpdb->get_results( "SHOW COLUMNS FROM {$table_services} LIKE 'requires_agreement'" );
                if ( empty( $col ) ) {
                        $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN agreement_template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER questionnaire_template_id" );
                        $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN requires_agreement tinyint(1) NOT NULL DEFAULT 1 AFTER display_order" );
                        $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN requires_questionnaire tinyint(1) NOT NULL DEFAULT 1 AFTER requires_agreement" );
                        $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN required_document_types text NOT NULL AFTER requires_questionnaire" );
                        $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN consultant_email varchar(255) NOT NULL DEFAULT '' AFTER required_document_types" );
                }

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
                return '<div style="font-family: Georgia, serif; line-height: 1.8; color: #333; max-width: 800px; margin: 0 auto;">

<div style="text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px double #D4AF37;">
<h1 style="color: #0A2647; font-size: 28px; margin: 0 0 5px 0; letter-spacing: 2px;">BUSINESSVANCE</h1>
<p style="color: #666; font-size: 13px; margin: 0; letter-spacing: 3px;">RESEARCH. ANALYZE. PLAN. SUCCEED.</p>
<p style="color: #999; font-size: 11px; margin: 5px 0 0 0;">082 377 7490</p>
</div>

<h2 style="text-align: center; color: #0A2647; font-size: 20px; margin-bottom: 25px;">CLIENT CONFIDENTIALITY AND INFORMATION PROTECTION UNDERTAKING</h2>

<div style="background: #f8f9fa; padding: 15px 20px; border-left: 4px solid #D4AF37; margin-bottom: 25px; border-radius: 0 6px 6px 0;">
<p style="margin: 0; font-style: italic; color: #555;"><strong>Our commitment</strong> &mdash; BusinessVance recognises that clients may share valuable business ideas, financial information, operating methods and personal details. We commit to treating this information responsibly, confidentially and only for the purpose of delivering the service purchased.</p>
</div>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">1. Information Covered by This Undertaking</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">This undertaking applies to information supplied through questionnaires, emails, WhatsApp messages, telephone discussions, documents, meetings or any other communication with BusinessVance.</li>
<li style="margin-bottom: 8px;">Protected information may include business ideas, products, services, processes, pricing, supplier details, customer information, financial information, marketing plans, research, intellectual property and any other information that is not publicly available.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">2. BusinessVance Confidentiality Commitment</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">BusinessVance will treat information supplied by the client as confidential.</li>
<li style="margin-bottom: 8px;">Information will be used only to prepare and deliver the product, report or consulting service purchased by the client.</li>
<li style="margin-bottom: 8px;">BusinessVance will not sell, publish, disclose or share the client&rsquo;s information or business idea with unauthorised third parties.</li>
<li style="margin-bottom: 8px;">Access will be limited to authorised persons who reasonably require the information to complete or administer the client&rsquo;s service.</li>
<li style="margin-bottom: 8px;">Reasonable safeguards will be used to protect information against loss, misuse, unauthorised access, alteration or disclosure.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">3. Protection and Ownership of Business Ideas</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">Any business idea, concept, strategy, business model, product name, process or original material submitted by the client remains the property of the client.</li>
<li style="margin-bottom: 8px;">Submitting information to BusinessVance does not transfer ownership of the client&rsquo;s idea or intellectual property to BusinessVance.</li>
<li style="margin-bottom: 8px;">BusinessVance will not knowingly use, reproduce, market or provide the client&rsquo;s confidential business idea to another person for BusinessVance&rsquo;s own benefit.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">4. Personal Information and POPIA</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">BusinessVance will process personal information only for legitimate purposes connected to the purchased service, administration, communication, recordkeeping and legal compliance.</li>
<li style="margin-bottom: 8px;">Personal information will be handled in line with the Protection of Personal Information Act 4 of 2013 (POPIA), where applicable.</li>
<li style="margin-bottom: 8px;">The client may request access to or correction of personal information held by BusinessVance, subject to reasonable verification and legal recordkeeping requirements.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">5. When Information May Be Disclosed</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">BusinessVance will not share confidential information unless: the client has provided written permission; disclosure is required by law, court order or lawful regulatory process; or limited disclosure is reasonably necessary to an authorised service provider assisting with the client&rsquo;s project and that provider is required to maintain confidentiality.</li>
<li style="margin-bottom: 8px;">This undertaking does not apply to information that is already publicly available through no breach by BusinessVance, was lawfully known before disclosure, or was independently developed without using the client&rsquo;s confidential information.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">6. Client Responsibilities</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">The client is responsible for providing accurate and complete information and for ensuring that the client is authorised to provide any personal, financial or third-party information included in the questionnaire.</li>
<li style="margin-bottom: 8px;">The quality and accuracy of the final report or service depend on the completeness and accuracy of the information supplied.</li>
<li style="margin-bottom: 8px;">Clients should not provide passwords, banking PINs, full payment-card details or other information that is not required for the purchased service.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">7. Reports and Completed Work</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">Reports and documents prepared specifically for the client are intended for the client&rsquo;s use and will not be published or distributed by BusinessVance without prior permission.</li>
<li style="margin-bottom: 8px;">BusinessVance may use anonymous and general information for internal service improvement, provided that the client, business and confidential idea cannot reasonably be identified.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">8. Information Retention and Security Limitation</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">Information may be kept for a reasonable period for service delivery, administration, legal compliance and recordkeeping, after which it will be deleted, destroyed or anonymised where reasonably practical.</li>
<li style="margin-bottom: 8px;">BusinessVance will take reasonable precautions to protect information. However, no electronic communication, online form or data-storage system can be guaranteed to be completely secure.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">9. Duration</h3>
<ul style="padding-left: 20px;">
<li style="margin-bottom: 8px;">This confidentiality commitment starts when the client provides information to BusinessVance and continues after the report or service has been completed, except where the information lawfully enters the public domain or disclosure is legally required.</li>
</ul>

<div style="background: #fff8e1; padding: 12px 18px; border-radius: 6px; margin-top: 25px; font-size: 13px; color: #795548;">
<strong>Important:</strong> This document records BusinessVance&rsquo;s confidentiality commitment to the client. It does not replace specialised legal advice or a customised non-disclosure agreement where a transaction involves investors, partners, patented technology, trade secrets or unusually high-value intellectual property.
</div>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 30px; padding-bottom: 6px; border-bottom: 2px solid #D4AF37;">10. Client Acknowledgement and Consent</h3>
<p style="margin-bottom: 15px;">By signing below, or by completing and submitting a BusinessVance questionnaire after receiving this undertaking, the client confirms that:</p>
<ul style="padding-left: 20px; margin-bottom: 25px;">
<li style="margin-bottom: 8px;">The client has read and understood this undertaking.</li>
<li style="margin-bottom: 8px;">The client consents to BusinessVance collecting and using submitted information to provide the purchased service.</li>
<li style="margin-bottom: 8px;">The client understands how the information may be stored, protected and disclosed in the limited circumstances described above.</li>
<li style="margin-bottom: 8px;">The client confirms that the information supplied is accurate to the best of the client&rsquo;s knowledge.</li>
</ul>

<h3 style="color: #0A2647; font-size: 16px; margin-top: 30px; margin-bottom: 15px;">CLIENT DETAILS</h3>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; width: 40%; font-weight: 600; background: #f8f9fa;">Client full name</td><td style="padding: 10px 12px; border: 1px solid #ddd; width: 60%;">{{CLIENT_NAME}}</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Business name</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{BUSINESS_NAME}}</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Product / service purchased</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{SERVICE_PURCHASED}}</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Email address</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{CLIENT_EMAIL}}</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Contact number</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{CLIENT_PHONE}}</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Client signature</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{CLIENT_SIGNATURE}}</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Date</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{DATE}}</td></tr>
</table>

<div style="background: #e8f4f8; padding: 15px 20px; border-radius: 6px; margin-bottom: 25px;">
<p style="margin: 0; color: #0A2647;"><strong>BUSINESSVANCE DECLARATION</strong></p>
<p style="margin: 8px 0 0 0; color: #555;">BusinessVance confirms that the client&rsquo;s information and business idea will be treated as confidential and used only for legitimate purposes connected to the service purchased by the client, subject to the terms of this undertaking.</p>
</div>

<h3 style="color: #0A2647; font-size: 16px; margin-bottom: 15px;">BUSINESSVANCE DETAILS</h3>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; width: 40%; font-weight: 600; background: #f8f9fa;">Representative name</td><td style="padding: 10px 12px; border: 1px solid #ddd;">Nico du Plessis</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Signature</td><td style="padding: 10px 12px; border: 1px solid #ddd;">&nbsp;</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Date</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{DATE}}</td></tr>
</table>

<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 3px double #D4AF37;">
<p style="margin: 0; font-weight: 700; color: #0A2647; letter-spacing: 2px;">BUSINESSVANCE</p>
<p style="margin: 4px 0 0 0; font-size: 12px; color: #666; letter-spacing: 2px;">Research. Analyze. Plan. Succeed.</p>
<p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">Contact: 082 377 7490</p>
</div>

<p style="text-align: center; margin-top: 20px; font-size: 11px; color: #999;">This document was generated by BusinessVance &mdash; Professional Business Consulting<br />Confidential &middot; For authorised use only</p>

<p><strong>IP Address:</strong> {{IP_ADDRESS}}</p>
</div>';
        }
}
