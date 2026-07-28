<?php
/**
 * Uninstall BusinessVance Services Manager
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

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
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete options
delete_option( 'bv_plugin_version' );
delete_option( 'bv_agreement_template' );
delete_option( 'bv_services_manager_db_version' );
delete_option( 'bv_services_manager_seeded' );

// Delete uploaded documents directory
$upload_dir = wp_upload_dir()['basedir'] . '/bv-documents';
if ( is_dir( $upload_dir ) ) {
    $files = glob( $upload_dir . '/*' );
    if ( $files ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) unlink( $file );
        }
    }
    rmdir( $upload_dir );
}
