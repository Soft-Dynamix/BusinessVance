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

    /**
     * Check if a WooCommerce product is a Tutor LMS course.
     *
     * @param int $product_id The product ID.
     * @return bool
     */
    public static function is_tutor_lms_course( $product_id ) {
        // Check by product type
        $product = wc_get_product( $product_id );
        if ( ! $product ) return false;
        if ( $product->get_type() === 'tutor_product_type' ) return true;
        // Check by meta
        $course_id = get_post_meta( $product_id, '_tutor_course', true );
        if ( $course_id ) return true;
        // Check if product belongs to a course category
        $terms = get_the_terms( $product_id, 'course_category' );
        if ( $terms && ! is_wp_error( $terms ) ) return true;
        return false;
    }

    /**
     * Get BV service IDs from order items.
     *
     * @param WC_Order $order The order object.
     * @return array
     */
    public static function get_bv_service_ids_from_order( $order ) {
        global $wpdb;
        $service_ids = array();
        $services_table = $wpdb->prefix . 'bv_services';
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $svc = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$services_table} WHERE woo_product_id = %d",
                $product_id
            ) );
            if ( $svc ) {
                $service_ids[] = $svc;
            }
        }
        return $service_ids;
    }

    /**
     * Check if an order contains any Tutor LMS course products.
     *
     * @param WC_Order $order The order object.
     * @return bool
     */
    public static function has_tutor_lms_courses( $order ) {
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            if ( self::is_tutor_lms_course( $product_id ) ) return true;
        }
        return false;
    }

    /**
     * Get the Tutor LMS dashboard URL.
     *
     * @return string
     */
    public static function get_tutor_dashboard_url() {
        // Check settings first
        $settings = BV_Settings::get_settings();
        $url = $settings['tutor_dashboard_url'] ?? '';
        if ( ! empty( $url ) ) return $url;

        // Try to find the Tutor LMS dashboard page
        $dashboard_page = get_page_by_path( 'tutor-dashboard' );
        if ( $dashboard_page ) return get_permalink( $dashboard_page->ID );

        // Try common slugs
        $slugs = array( 'dashboard', 'my-courses', 'tutor-dashboard' );
        foreach ( $slugs as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page ) return get_permalink( $page->ID );
        }

        return '';
    }

    /**
     * Render post-purchase notice on WooCommerce thank-you page.
     *
     * @param int $order_id The order ID.
     * @return void
     */
    public static function render_post_purchase_notice( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $has_services = ! empty( self::get_bv_service_ids_from_order( $order ) );
        $has_courses = self::has_tutor_lms_courses( $order );

        if ( ! $has_services && ! $has_courses ) return;

        $settings = BV_Settings::get_settings();
        $portal_url = $settings['portal_url'] ?? '';
        $tutor_dashboard_url = self::get_tutor_dashboard_url();

        if ( empty( $portal_url ) ) $portal_url = site_url( '/client-portal/' );
        if ( empty( $tutor_dashboard_url ) ) $tutor_dashboard_url = site_url( '/tutor-dashboard/' );

        echo '<div style="max-width:600px;margin:30px auto;padding:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">';

        if ( $has_services && ! $has_courses ) {
            // Service only → direct to portal
            echo '<div style="background:linear-gradient(135deg,#002B5C 0%,#003d7a 100%);color:#fff;border-radius:12px;padding:32px;text-align:center;margin-bottom:20px;">';
            echo '<div style="font-size:48px;margin-bottom:16px;">✦</div>';
            echo '<h2 style="margin:0 0 8px;font-size:22px;">Your Service Project Has Been Created!</h2>';
            echo '<p style="margin:0 0 24px;color:rgba(255,255,255,0.8);font-size:15px;">Your consulting service order has been processed. Start managing your project now.</p>';
            echo '<a href="' . esc_url( $portal_url ) . '" style="display:inline-block;background:#D4AF37;color:#002B5C;padding:14px 32px;border-radius:8px;font-size:16px;font-weight:700;text-decoration:none;transition:all 0.2s;">Go to Client Portal →</a>';
            echo '</div>';
            echo '<div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:8px;padding:20px;text-align:center;">';
            echo '<p style="margin:0;color:#666;font-size:14px;">You can also access your portal anytime from your account area.</p>';
            echo '</div>';
        } elseif ( $has_courses && ! $has_services ) {
            // Course only → go to Tutor LMS
            echo '<div style="background:linear-gradient(135deg,#2A9D8F 0%,#1a7a6e 100%);color:#fff;border-radius:12px;padding:32px;text-align:center;margin-bottom:20px;">';
            echo '<div style="font-size:48px;margin-bottom:16px;">🎓</div>';
            echo '<h2 style="margin:0 0 8px;font-size:22px;">Your Course Enrollment is Ready!</h2>';
            echo '<p style="margin:0 0 24px;color:rgba(255,255,255,0.8);font-size:15px;">Start learning right away from your Tutor LMS dashboard.</p>';
            echo '<a href="' . esc_url( $tutor_dashboard_url ) . '" style="display:inline-block;background:#fff;color:#2A9D8F;padding:14px 32px;border-radius:8px;font-size:16px;font-weight:700;text-decoration:none;">Go to My Courses →</a>';
            echo '</div>';
        } else {
            // Both services and courses → show both links
            echo '<div style="background:linear-gradient(135deg,#002B5C 0%,#2A9D8F 100%);color:#fff;border-radius:12px;padding:32px;text-align:center;margin-bottom:20px;">';
            echo '<h2 style="margin:0 0 8px;font-size:22px;">Thank You for Your Purchase!</h2>';
            echo '<p style="margin:0;color:rgba(255,255,255,0.8);font-size:15px;">You\'ve purchased both consulting services and courses. Here\'s where to go next:</p>';
            echo '</div>';
            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">';
            echo '<div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:8px;padding:24px;text-align:center;">';
            echo '<div style="font-size:36px;margin-bottom:12px;">✦</div>';
            echo '<h3 style="margin:0 0 8px;color:#002B5C;font-size:16px;">Consulting Services</h3>';
            echo '<p style="margin:0 0 16px;color:#666;font-size:14px;">Manage your project, sign agreements, and track progress.</p>';
            echo '<a href="' . esc_url( $portal_url ) . '" style="display:inline-block;background:#002B5C;color:#fff;padding:12px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;">Client Portal →</a>';
            echo '</div>';
            echo '<div style="background:#f8f9fa;border:1px solid #e0e0e0;border-radius:8px;padding:24px;text-align:center;">';
            echo '<div style="font-size:36px;margin-bottom:12px;">🎓</div>';
            echo '<h3 style="margin:0 0 8px;color:#2A9D8F;font-size:16px;">Your Courses</h3>';
            echo '<p style="margin:0 0 16px;color:#666;font-size:14px;">Access your enrolled courses and start learning.</p>';
            echo '<a href="' . esc_url( $tutor_dashboard_url ) . '" style="display:inline-block;background:#2A9D8F;color:#fff;padding:12px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;">My Courses →</a>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }
}