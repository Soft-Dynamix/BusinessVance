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
                self::cleanup_demo_data();
                self::seed_default_services();
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
                        agreement_template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        nda_only tinyint(1) NOT NULL DEFAULT 0,
                        required_documents varchar(1000) NOT NULL DEFAULT '',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        KEY category_id (category_id),
                        KEY woo_product_id (woo_product_id),
                        KEY is_visible (is_visible)
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
                        PRIMARY KEY  (id),
                        KEY category_id (category_id),
                        KEY woo_product_id (woo_product_id),
                        KEY is_visible (is_visible)
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
                        PRIMARY KEY  (id),
                        KEY plan_id (plan_id)
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
                        UNIQUE KEY project_number (project_number),
                        KEY client_user_id (client_user_id),
                        KEY wc_order_id (wc_order_id),
                        KEY status (status)
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
                        PRIMARY KEY  (id),
                        KEY project_id (project_id),
                        KEY service_id (service_id),
                        UNIQUE KEY project_service (project_id, service_id)
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
                        PRIMARY KEY  (id),
                        KEY project_id (project_id)
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
                        PRIMARY KEY  (id),
                        KEY project_id (project_id)
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
                        PRIMARY KEY  (id),
                        KEY project_id (project_id)
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
                        PRIMARY KEY  (id),
                        KEY project_id (project_id)
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
                        PRIMARY KEY  (id),
                        KEY template_id (template_id)
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
                        PRIMARY KEY  (id),
                        KEY section_id (section_id)
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
                        PRIMARY KEY  (id),
                        KEY project_id (project_id),
                        KEY question_id (question_id)
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
                        PRIMARY KEY  (id),
                        KEY project_id (project_id)
                ) {$charset_collate};";

                dbDelta( $sql_activity_log );

                // -------------------------------------------------------
                // 18. bv_agreement_templates
                // -------------------------------------------------------
                $table_agreement_templates = $wpdb->prefix . 'bv_agreement_templates';

                $sql_agreement_templates = "CREATE TABLE {$table_agreement_templates} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name varchar(255) NOT NULL DEFAULT '',
                        slug varchar(255) NOT NULL DEFAULT '',
                        type varchar(50) NOT NULL DEFAULT 'nda',
                        content longtext NOT NULL,
                        is_default tinyint(1) NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id)
                ) {$charset_collate};";

                dbDelta( $sql_agreement_templates );

                // -------------------------------------------------------
                // 19. bv_service_agreements (junction: services ↔ agreement_templates)
                // -------------------------------------------------------
                $table_service_agreements = $wpdb->prefix . 'bv_service_agreements';

                $sql_service_agreements = "CREATE TABLE {$table_service_agreements} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        agreement_template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        display_order int(11) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id),
                        UNIQUE KEY service_template (service_id, agreement_template_id),
                        KEY service_id (service_id)
                ) {$charset_collate};";

                dbDelta( $sql_service_agreements );

                // -------------------------------------------------------
                // 20. bv_document_requirements
                // -------------------------------------------------------
                $table_document_requirements = $wpdb->prefix . 'bv_document_requirements';

                $sql_document_requirements = "CREATE TABLE {$table_document_requirements} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name varchar(255) NOT NULL DEFAULT '',
                        slug varchar(255) NOT NULL DEFAULT '',
                        description text NOT NULL,
                        allowed_types varchar(500) NOT NULL DEFAULT 'pdf,doc,docx,jpg,jpeg,png',
                        max_size_mb int(11) NOT NULL DEFAULT 10,
                        is_required tinyint(1) NOT NULL DEFAULT 1,
                        display_order int(11) NOT NULL DEFAULT 0,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        UNIQUE KEY slug (slug),
                        KEY display_order (display_order)
                ) {$charset_collate};";

                dbDelta( $sql_document_requirements );

                // -------------------------------------------------------
                // 21. bv_service_questionnaires (junction: services ↔ questionnaire_templates)
                // -------------------------------------------------------
                $table_service_questionnaires = $wpdb->prefix . 'bv_service_questionnaires';

                $sql_service_questionnaires = "CREATE TABLE {$table_service_questionnaires} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        questionnaire_template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        display_order int(11) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id),
                        UNIQUE KEY service_questionnaire (service_id, questionnaire_template_id),
                        KEY service_id (service_id)
                ) {$charset_collate};";

                dbDelta( $sql_service_questionnaires );

                // -------------------------------------------------------
                // 22. bv_service_documents (junction: services ↔ document_requirements)
                // -------------------------------------------------------
                $table_service_documents = $wpdb->prefix . 'bv_service_documents';

                $sql_service_documents = "CREATE TABLE {$table_service_documents} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        service_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        document_requirement_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                        display_order int(11) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id),
                        UNIQUE KEY service_document (service_id, document_requirement_id),
                        KEY service_id (service_id)
                ) {$charset_collate};";

                dbDelta( $sql_service_documents );

                // -------------------------------------------------------
                // 23. bv_custom_icons
                // -------------------------------------------------------
                $table_custom_icons = $wpdb->prefix . 'bv_custom_icons';

                $sql_custom_icons = "CREATE TABLE {$table_custom_icons} (
                        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        name varchar(100) NOT NULL DEFAULT '',
                        label varchar(200) NOT NULL DEFAULT '',
                        svg_inner TEXT NOT NULL,
                        source varchar(50) NOT NULL DEFAULT 'upload',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        UNIQUE KEY name (name),
                        KEY source (source)
                ) {$charset_collate};";

                dbDelta( $sql_custom_icons );

                // Add new columns to existing bv_services table if they don't exist
                $services_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_services}" );
                $existing_cols = array();
                foreach ( $services_columns as $col ) {
                    $existing_cols[] = $col->Field;
                }
                
                if ( ! in_array( 'agreement_template_id', $existing_cols ) ) {
                    $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN agreement_template_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER display_order" );
                }
                if ( ! in_array( 'nda_only', $existing_cols ) ) {
                    $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN nda_only tinyint(1) NOT NULL DEFAULT 0 AFTER agreement_template_id" );
                }
                if ( ! in_array( 'required_documents', $existing_cols ) ) {
                    $wpdb->query( "ALTER TABLE {$table_services} ADD COLUMN required_documents varchar(1000) NOT NULL DEFAULT '' AFTER nda_only" );
                }

                // Migrate legacy single agreement_template_id to junction table
                self::migrate_service_agreements();

                // Migrate legacy single questionnaire_template_id to junction table
                self::migrate_service_questionnaires();

                // Store the current version for future update comparisons.
                update_option( 'bv_services_manager_db_version', BV_VERSION, false );
        }

        /**
         * Migrate legacy single agreement_template_id from bv_services to the
         * bv_service_agreements junction table. Runs only once (option-gated).
         *
         * @since 2.3.0
         * @return void
         */
        private static function migrate_service_agreements() {
                if ( get_option( 'bv_agreements_migrated', '0' ) === '1' ) {
                        return;
                }

                global $wpdb;
                $services_table    = $wpdb->prefix . 'bv_services';
                $junction_table    = $wpdb->prefix . 'bv_service_agreements';

                // Check that both tables exist
                $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$junction_table}'" );
                if ( ! $table_exists ) {
                        return;
                }

                // Find all services that have a legacy agreement_template_id > 0
                $legacy_rows = $wpdb->get_results(
                        "SELECT id, agreement_template_id FROM {$services_table} WHERE agreement_template_id > 0"
                );

                foreach ( $legacy_rows as $row ) {
                        // Skip if already in junction
                        $exists = $wpdb->get_var( $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$junction_table} WHERE service_id = %d AND agreement_template_id = %d",
                                $row->id,
                                $row->agreement_template_id
                        ) );

                        if ( ! $exists ) {
                                $wpdb->insert( $junction_table, array(
                                        'service_id'            => $row->id,
                                        'agreement_template_id' => $row->agreement_template_id,
                                        'display_order'         => 0,
                                ), array( '%d', '%d', '%d' ) );
                        }
                }

                update_option( 'bv_agreements_migrated', '1' );
        }

        /**
         * Migrate legacy single questionnaire_template_id from bv_services to the
         * bv_service_questionnaires junction table. Runs only once (option-gated).
         *
         * @since 2.5.0
         * @return void
         */
        private static function migrate_service_questionnaires() {
                if ( get_option( 'bv_questionnaires_migrated', '0' ) === '1' ) {
                        return;
                }

                global $wpdb;
                $services_table    = $wpdb->prefix . 'bv_services';
                $junction_table    = $wpdb->prefix . 'bv_service_questionnaires';

                // Check that both tables exist
                $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$junction_table}'" );
                if ( ! $table_exists ) {
                        return;
                }

                // Find all services that have a legacy questionnaire_template_id > 0
                $legacy_rows = $wpdb->get_results(
                        "SELECT id, questionnaire_template_id FROM {$services_table} WHERE questionnaire_template_id > 0"
                );

                foreach ( $legacy_rows as $row ) {
                        // Skip if already in junction
                        $exists = $wpdb->get_var( $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$junction_table} WHERE service_id = %d AND questionnaire_template_id = %d",
                                $row->id,
                                $row->questionnaire_template_id
                        ) );

                        if ( ! $exists ) {
                                $wpdb->insert( $junction_table, array(
                                        'service_id'                => $row->id,
                                        'questionnaire_template_id' => $row->questionnaire_template_id,
                                        'display_order'             => 0,
                                ), array( '%d', '%d', '%d' ) );
                        }
                }

                update_option( 'bv_questionnaires_migrated', '1' );
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
                // Seed Agreement Templates
                // ----------------------------------------------------------------
                $wpdb->insert( $wpdb->prefix . 'bv_agreement_templates', array(
                    'name'      => 'Client Confidentiality and Information Protection Undertaking',
                    'slug'      => 'client-confidentiality-undertaking',
                    'type'      => 'confidentiality',
                    'content'   => self::seed_agreement_html(),
                    'is_default' => 1,
                ), array( '%s', '%s', '%s', '%s', '%d' ) );

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

                $wpdb->query( 'START TRANSACTION' );

                try {
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

                // Services and Plans are no longer auto-seeded.
                // Admin should add only the services and plans they need.

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

                $wpdb->query( 'COMMIT' );

                // Mark seeding as complete.
                update_option( 'bv_services_manager_seeded', '1', false );

                } catch ( Exception $e ) {
                $wpdb->query( 'ROLLBACK' );
                error_log( 'BV_Activator::seed_data failed: ' . $e->getMessage() );
                }
        }

        /**
         * Remove demo/seed services and plans from previous versions.
         *
         * On fresh installs seed_data() no longer creates services or plans,
         * but existing databases may still have the old demo data. This method
         * removes them so that only admin-configured services and plans appear.
         *
         * @since 2.7.5
         * @return void
         */
        private static function cleanup_demo_data() {
                global $wpdb;

                $demo_service_names = array(
                        'Business Feasibility Report',
                        'Market Research Report',
                        'Financial Analysis',
                        'Business Plan Development',
                        'Strategic Growth Plan',
                        'Marketing Strategy',
                        'Business Advisory Session',
                        'Compliance Assessment',
                        'Tax Planning Consultation',
                        'Company Registration',
                        'BEE Certificate Assistance',
                        'Logo & Brand Identity',
                );

                $demo_plan_names = array(
                        'STARTER',
                        'PROFESSIONAL',
                        'BUSINESS PARTNER',
                );

                $table_services = $wpdb->prefix . 'bv_services';
                $table_plans    = $wpdb->prefix . 'bv_plans';
                $table_features = $wpdb->prefix . 'bv_plan_features';

                // Delete demo plan features first (cascade)
                if ( ! empty( $demo_plan_names ) ) {
                        $plan_placeholders = implode( ',', array_fill( 0, count( $demo_plan_names ), '%s' ) );
                        $wpdb->query(
                                $wpdb->prepare(
                                        "DELETE FROM {$table_features} WHERE plan_id IN (SELECT id FROM {$table_plans} WHERE name IN ({$plan_placeholders}))",
                                        ...$demo_plan_names
                                )
                        );
                        // Delete demo plans
                        $wpdb->query(
                                $wpdb->prepare(
                                        "DELETE FROM {$table_plans} WHERE name IN ({$plan_placeholders})",
                                        ...$demo_plan_names
                                )
                        );
                }

                // Delete demo services
                if ( ! empty( $demo_service_names ) ) {
                        $svc_placeholders = implode( ',', array_fill( 0, count( $demo_service_names ), '%s' ) );
                        $wpdb->query(
                                $wpdb->prepare(
                                        "DELETE FROM {$table_services} WHERE name IN ({$svc_placeholders})",
                                        ...$demo_service_names
                                )
                        );
                }
        }

        /**
         * Seed the default services configured for BusinessVance.
         *
         * Idempotent: skips any service whose name already exists in the database,
         * so it is safe to call on both fresh installs and upgrades.
         *
         * @since 2.7.5
         * @return void
         */
        private static function seed_default_services() {
                global $wpdb;

                $table_services = $wpdb->prefix . 'bv_services';

                $defaults = array(
                        array(
                                'name'          => 'BUSINESS FEASIBILITY REPORT',
                                'description'   => 'Determine if your idea is practical and has potential.',
                                'price'         => '299.00',
                                'price_display' => 'R299',
                                'icon'          => 'file-search',
                                'service_type'  => 'onceoff',
                                'display_order' => 1,
                        ),
                        array(
                                'name'          => 'START-UP COST ESTIMATE REPORT',
                                'description'   => 'Get a clear estimate of the costs to start your business.',
                                'price'         => '299.00',
                                'price_display' => 'R299',
                                'icon'          => 'calculator',
                                'service_type'  => 'onceoff',
                                'display_order' => 2,
                        ),
                        array(
                                'name'          => 'COMPETITOR ANALYSIS REPORT',
                                'description'   => 'Understand your competitors and find your advantage.',
                                'price'         => '399.00',
                                'price_display' => 'R399',
                                'icon'          => 'bar-chart-3',
                                'service_type'  => 'onceoff',
                                'display_order' => 3,
                        ),
                        array(
                                'name'          => 'RISK ASSESSMENT REPORT',
                                'description'   => 'Identify risks and practical mitigation strategies.',
                                'price'         => '399.00',
                                'price_display' => 'R399',
                                'icon'          => 'shield-alert',
                                'service_type'  => 'onceoff',
                                'display_order' => 4,
                        ),
                        array(
                                'name'          => 'MARKET RESEARCH REPORT',
                                'description'   => 'Understand your market and customer demand.',
                                'price'         => '499.00',
                                'price_display' => 'R499',
                                'icon'          => 'trending-up',
                                'service_type'  => 'onceoff',
                                'display_order' => 5,
                        ),
                        array(
                                'name'          => 'MARKETING STRATEGY REPORT',
                                'description'   => 'A practical marketing plan for your business.',
                                'price'         => '599.00',
                                'price_display' => 'R599',
                                'icon'          => 'megaphone',
                                'service_type'  => 'onceoff',
                                'display_order' => 6,
                        ),
                        array(
                                'name'          => 'FINANCIAL FORECAST REPORT',
                                'description'   => 'Estimated financial forecast based on your information.',
                                'price'         => '699.00',
                                'price_display' => 'R699',
                                'icon'          => 'chart-line',
                                'service_type'  => 'onceoff',
                                'display_order' => 7,
                        ),
                        array(
                                'name'          => 'BUSINESS HEALTH CHECK REPORT',
                                'description'   => 'Evaluate your business and identify areas to improve.',
                                'price'         => '799.00',
                                'price_display' => 'R799',
                                'icon'          => 'heart-pulse',
                                'service_type'  => 'onceoff',
                                'display_order' => 8,
                        ),
                        array(
                                'name'          => 'INVESTOR READINESS REPORT',
                                'description'   => 'Find out if your business is ready for investors.',
                                'price'         => '899.00',
                                'price_display' => 'R899',
                                'icon'          => 'target',
                                'service_type'  => 'onceoff',
                                'display_order' => 9,
                        ),
                        array(
                                'name'          => 'COMPLETE BUSINESS PLAN',
                                'description'   => 'A full business plan based on your detailed information.',
                                'price'         => '1499.00',
                                'price_display' => 'R1 499',
                                'icon'          => 'clipboard-list',
                                'service_type'  => 'onceoff',
                                'display_order' => 10,
                        ),
                );

                foreach ( $defaults as $svc ) {
                        $exists = $wpdb->get_var(
                                $wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$table_services} WHERE name = %s",
                                        $svc['name']
                                )
                        );
                        if ( $exists ) {
                                continue;
                        }

                        $wpdb->insert(
                                $table_services,
                                array(
                                        'name'           => $svc['name'],
                                        'description'    => $svc['description'],
                                        'price'          => $svc['price'],
                                        'price_display'  => $svc['price_display'],
                                        'icon'           => $svc['icon'],
                                        'button_label'   => 'Get Started',
                                        'service_type'   => $svc['service_type'],
                                        'woo_product_id' => 0,
                                        'category_id'    => 0,
                                        'is_visible'     => 1,
                                        'is_featured'    => 0,
                                        'display_order'  => $svc['display_order'],
                                        'created_at'     => current_time( 'mysql' ),
                                        'updated_at'     => current_time( 'mysql' ),
                                ),
                                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
                        );
                }
        }

        /**
         * Return HTML for the seed agreement template.
         * Used only during first-activation seeding into bv_agreement_templates.
         *
         * @since  1.0.0
         * @return string
         */
        private static function seed_agreement_html() {
                return '<div style="font-family: Georgia, serif; line-height: 1.8; color: #333; max-width: 800px; margin: 0 auto;">

<div style="text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px double #D4AF37;">
<h1 style="color: #0A2647; font-size: 28px; margin: 0 0 5px 0; letter-spacing: 2px;">BUSINESSVANCE</h1>
<p style="color: #666; font-size: 13px; margin: 0; letter-spacing: 3px;">RESEARCH. ANALYZE. PLAN. SUCCEED.</p>
<p style="color: #999; font-size: 11px; margin: 5px 0 0 0;">{{REPRESENTATIVE_PHONE}}</p>
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
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; width: 40%; font-weight: 600; background: #f8f9fa;">Representative name</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{REPRESENTATIVE_NAME}}</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Signature</td><td style="padding: 10px 12px; border: 1px solid #ddd;">&nbsp;</td></tr>
<tr><td style="padding: 10px 12px; border: 1px solid #ddd; font-weight: 600; background: #f8f9fa;">Date</td><td style="padding: 10px 12px; border: 1px solid #ddd;">{{DATE}}</td></tr>
</table>

<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 3px double #D4AF37;">
<p style="margin: 0; font-weight: 700; color: #0A2647; letter-spacing: 2px;">BUSINESSVANCE</p>
<p style="margin: 4px 0 0 0; font-size: 12px; color: #666; letter-spacing: 2px;">Research. Analyze. Plan. Succeed.</p>
<p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">Contact: {{REPRESENTATIVE_PHONE}}</p>
</div>

<p style="text-align: center; margin-top: 20px; font-size: 11px; color: #999;">This document was generated by BusinessVance &mdash; Professional Business Consulting<br />Confidential &middot; For authorised use only</p>

<p><strong>IP Address:</strong> {{IP_ADDRESS}}</p>
</div>';
        }
}
