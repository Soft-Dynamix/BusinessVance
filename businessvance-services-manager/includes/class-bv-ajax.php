<?php
/**
 * BusinessVance Services Manager - AJAX Handlers
 *
 * Handles AJAX requests for reordering and visibility toggling.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Ajax
 *
 * Registers and processes AJAX actions.
 */
class BV_Ajax {

    /**
     * Register all AJAX hooks.
     *
     * @return void
     */
    public static function register() {
        // Reorder services.
        add_action( 'wp_ajax_bv_reorder_services', array( __CLASS__, 'reorder_services' ) );

        // Reorder plans.
        add_action( 'wp_ajax_bv_reorder_plans', array( __CLASS__, 'reorder_plans' ) );

        // Toggle visibility (services and plans).
        add_action( 'wp_ajax_bv_toggle_visibility', array( __CLASS__, 'toggle_visibility' ) );

        // Search WooCommerce products (admin only).
        add_action( 'wp_ajax_bv_search_wc_products', array( __CLASS__, 'search_wc_products' ) );
    }

    /**
     * Reorder services via AJAX.
     *
     * Expects POST: action=bv_reorder_services, nonce, order (JSON array of IDs).
     *
     * @return void
     */
    public static function reorder_services() {
        check_ajax_referer( 'bv_reorder_services', 'nonce' );

        if ( ! isset( $_POST['order'] ) || ! is_array( $_POST['order'] ) ) {
            wp_send_json_error( 'Invalid order data.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_services';
        $order = array_map( 'absint', $_POST['order'] );

        foreach ( $order as $index => $id ) {
            $wpdb->update(
                $table,
                array( 'display_order' => $index ),
                array( 'id' => $id ),
                array( '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success( 'Services reordered.' );
    }

    /**
     * Reorder plans via AJAX.
     *
     * Expects POST: action=bv_reorder_plans, nonce, order (JSON array of IDs).
     *
     * @return void
     */
    public static function reorder_plans() {
        check_ajax_referer( 'bv_reorder_plans', 'nonce' );

        if ( ! isset( $_POST['order'] ) || ! is_array( $_POST['order'] ) ) {
            wp_send_json_error( 'Invalid order data.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_plans';
        $order = array_map( 'absint', $_POST['order'] );

        foreach ( $order as $index => $id ) {
            $wpdb->update(
                $table,
                array( 'display_order' => $index ),
                array( 'id' => $id ),
                array( '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success( 'Plans reordered.' );
    }

    /**
     * Toggle visibility of a service or plan.
     *
     * Expects POST: action=bv_toggle_visibility, nonce, type (service/plan), id.
     *
     * @return void
     */
    public static function toggle_visibility() {
        check_ajax_referer( 'bv_toggle_visibility', 'nonce' );

        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( ! in_array( $type, array( 'service', 'plan' ), true ) || $id <= 0 ) {
            wp_send_json_error( 'Invalid parameters.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . ( $type === 'plan' ? 'bv_plans' : 'bv_services' );

        $wpdb->query(
            $wpdb->prepare( "UPDATE $table SET visible = IF(visible = 1, 0, 1) WHERE id = %d", $id )
        );

        $new_val = (int) $wpdb->get_var( $wpdb->prepare( "SELECT visible FROM $table WHERE id = %d", $id ) );

        wp_send_json_success( array( 'visible' => $new_val ) );
    }

    /**
     * Search WooCommerce products for the product picker modal.
     *
     * Expects POST: action=bv_search_wc_products, nonce, search.
     *
     * @return void
     */
    public static function search_wc_products() {
        check_ajax_referer( 'bv_wc_search', 'nonce' );

        if ( ! function_exists( 'wc_get_products' ) ) {
            wp_send_json_error( 'WooCommerce is not active.' );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        $args = array(
            'limit'    => 20,
            'status'   => 'publish',
            'paginate' => false,
        );

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        $products = wc_get_products( $args );
        $results  = array();

        foreach ( $products as $product ) {
            $results[] = array(
                'id'    => $product->get_id(),
                'name'  => $product->get_name(),
                'price' => $product->get_price_html(),
                'sku'   => $product->get_sku() ? $product->get_sku() : '—',
            );
        }

        wp_send_json_success( $results );
    }
}