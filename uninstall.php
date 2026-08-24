<?php
/**
 * Uninstall BusinessVance Services Manager
 *
 * IMPORTANT: By default, this does NOT delete client data (projects, documents,
 * reports, questionnaires, messages) to prevent accidental data loss.
 *
 * To perform a COMPLETE removal of all plugin data, the user must first
 * go to Settings → Data Management and check "Delete all plugin data on uninstall".
 * This sets the option 'bv_delete_data_on_uninstall' to 'yes'.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.1.0
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

// Read the uninstall flag BEFORE deleting any options
$delete_all_data = get_option( 'bv_delete_data_on_uninstall', 'no' );

// Clean up lightweight plugin options on every uninstall (safe, no user config)
$options_to_delete = array(
    'bv_plugin_version',
    'bv_agreement_template',
    'bv_services_manager_db_version',
    'bv_services_manager_seeded',
    // 'bv_settings' — preserved across reinstalls so user config survives
    'bv_delete_data_on_uninstall',
    'bv_agreements_migrated',
    'bv_questionnaires_migrated',
);

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

if ( $delete_all_data === 'yes' ) {
    // ===== FULL CLEANUP — User explicitly requested complete removal =====

    // Delete settings
    delete_option( 'bv_settings' );

    // Drop all BV tables
    $tables = array(
        $prefix . 'bv_activity_log',
        $prefix . 'bv_project_notes',
        $prefix . 'bv_project_messages',
        $prefix . 'bv_project_reports',
        $prefix . 'bv_project_documents',
        $prefix . 'bv_questionnaire_responses',
        $prefix . 'bv_questionnaire_questions',
        $prefix . 'bv_questionnaire_sections',
        $prefix . 'bv_questionnaire_templates',
        $prefix . 'bv_project_agreements',
        $prefix . 'bv_project_services',
        $prefix . 'bv_projects',
        $prefix . 'bv_plan_features',
        $prefix . 'bv_plans',
        $prefix . 'bv_services',
        $prefix . 'bv_categories',
        $prefix . 'bv_agreement_templates',
        $prefix . 'bv_service_agreements',
        $prefix . 'bv_document_requirements',
        $prefix . 'bv_service_documents',
        $prefix . 'bv_service_questionnaires',
        $prefix . 'bv_custom_icons',
    );

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }

    // Delete uploaded documents directory
    $upload_dir = wp_upload_dir()['basedir'] . '/bv-documents';
    if ( is_dir( $upload_dir ) ) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $upload_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $files as $fileinfo ) {
            if ( $fileinfo->isDir() ) {
                rmdir( $fileinfo->getRealPath() );
            } else {
                unlink( $fileinfo->getRealPath() );
            }
        }
        rmdir( $upload_dir );
    }
}
// ===== DEFAULT: Tables and files are PRESERVED =====
// Client projects, documents, reports, questionnaires, messages, and agreements
// remain intact. On reactivation, the plugin will reconnect to the existing data.
