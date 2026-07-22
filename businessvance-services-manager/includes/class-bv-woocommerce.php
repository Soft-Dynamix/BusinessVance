<?php
/**
 * BusinessVance Services Manager - WooCommerce Integration
 *
 * Handles WooCommerce product linking, add-to-cart URL generation,
 * and optional WC product meta display.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_WooCommerce
 *
 * Static helper methods for WooCommerce integration.
 */
class BV_WooCommerce {

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    public static function is_active() {
        return in_array(
            'woocommerce/woocommerce.php',
            apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
            true
        );
    }

    /**
     * Boot the WooCommerce integration hooks.
     *
     * @return void
     */
    public static function init() {
        if ( ! self::is_active() ) {
            return;
        }

        // Add BusinessVance meta box to WooCommerce product edit screen.
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
        add_action( 'save_post_product', array( __CLASS__, 'save_product_meta' ), 10, 2 );
    }

    /**
     * Add a meta box to WooCommerce product edit screens showing BV linkage.
     *
     * @return void
     */
    public static function add_meta_box() {
        add_meta_box(
            'bv_product_link',
            'BusinessVance Link',
            array( __CLASS__, 'render_meta_box' ),
            'product',
            'side',
            'high'
        );
    }

    /**
     * Render the meta box content.
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public static function render_meta_box( $post ) {
        $service_id = get_post_meta( $post->ID, '_bv_service_id', true );
        $plan_id    = get_post_meta( $post->ID, '_bv_plan_id', true );

        echo '<p><strong>This product is linked to:</strong></p>';

        if ( $service_id ) {
            $url = admin_url( 'admin.php?page=bv-services&edit=' . absint( $service_id ) );
            echo '<p>🔴 Service <a href="' . esc_url( $url ) . '">#' . absint( $service_id ) . '</a></p>';
        }
        if ( $plan_id ) {
            $url = admin_url( 'admin.php?page=bv-plans&edit=' . absint( $plan_id ) );
            echo '<p>🔵 Plan <a href="' . esc_url( $url ) . '">#' . absint( $plan_id ) . '</a></p>';
        }

        if ( ! $service_id && ! $plan_id ) {
            echo '<p><em>Not linked to any BusinessVance service or plan.</em></p>';
        }

        wp_nonce_field( 'bv_save_product_meta', 'bv_product_meta_nonce' );
    }

    /**
     * Save product meta (placeholder for future two-way sync).
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public static function save_product_meta( $post_id, $post ) {
        if ( ! isset( $_POST['bv_product_meta_nonce'] ) || ! wp_verify_nonce( $_POST['bv_product_meta_nonce'], 'bv_save_product_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Meta is managed from the BV admin side, so nothing to save here.
    }

    /**
     * Get the WooCommerce add-to-cart URL for a product.
     *
     * @param int|string $product_id The product ID.
     * @return string Empty string if WooCommerce is inactive or product doesn't exist.
     */
    public static function get_add_to_cart_url( $product_id ) {
        if ( ! self::is_active() || empty( $product_id ) ) {
            return '';
        }

        $product = wc_get_product( absint( $product_id ) );
        if ( ! $product || ! $product->is_purchasable() ) {
            return '';
        }

        return $product->add_to_cart_url();
    }

    /**
     * Check if a WooCommerce product exists and is published.
     *
     * @param int|string $product_id Product ID.
     * @return bool
     */
    public static function product_exists( $product_id ) {
        if ( ! self::is_active() || empty( $product_id ) ) {
            return false;
        }

        $product = wc_get_product( absint( $product_id ) );
        return $product && $product->is_purchasable();
    }
}