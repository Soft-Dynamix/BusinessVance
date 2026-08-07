<?php
/**
 * BusinessVance Nonce Verification Trait
 *
 * Provides a shared `verify_nonce()` method for AJAX handlers.
 * Intended for DRY adoption across classes that verify admin nonces.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait BV_Nonce_Verification {

    /**
     * Verify the admin AJAX nonce and check manage_options capability.
     *
     * Calls wp_send_json_error() and terminates execution on failure.
     *
     * @since 2.7.0
     * @param string $action The nonce action name. Default 'bv_admin_nonce'.
     * @param string $param  The POST/GET parameter holding the nonce. Default 'nonce'.
     * @return void
     */
    protected function verify_nonce( $action = 'bv_admin_nonce', $param = 'nonce' ) {
        if ( ! check_ajax_referer( $action, $param, false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
    }
}
