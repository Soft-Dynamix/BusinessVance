<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$tables = array(
    $wpdb->prefix . 'bv_submission_answers',
    $wpdb->prefix . 'bv_submissions',
    $wpdb->prefix . 'bv_questions',
    $wpdb->prefix . 'bv_questionnaires',
    $wpdb->prefix . 'bv_plan_features',
    $wpdb->prefix . 'bv_plans',
    $wpdb->prefix . 'bv_services',
    $wpdb->prefix . 'bv_categories',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS $table" );
}

delete_option( 'bv_plugin_version' );
delete_option( 'bv_settings' );