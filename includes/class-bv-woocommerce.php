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
        $active = in_array(
            'woocommerce/woocommerce.php',
            apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
            true
        );

        // Also check network-activated plugins on multisite
        if ( ! $active && is_multisite() ) {
            $network_plugins = get_site_option( 'active_sitewide_plugins', array() );
            $active = isset( $network_plugins['woocommerce/woocommerce.php'] );
        }

        return $active;
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
    }

    /**
     * Add a meta box to WooCommerce product edit screens showing BV linkage.
     *
     * @return void
     */
    public static function add_meta_box() {
        add_meta_box(
            'bv_product_link',
            __( 'BusinessVance Link', 'businessvance-services-manager' ),
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

        echo '<div class="bv-woo-notice-wrap">';

        if ( $has_services && ! $has_courses ) {
            // Service only → direct to portal
            echo '<div class="bv-woo-notice-hero bv-woo-notice-hero--services">';
            echo '<div class="bv-woo-notice-hero-icon">✦</div>';
            echo '<h2>Your Service Project Has Been Created!</h2>';
            echo '<p>Your consulting service order has been processed. Start managing your project now.</p>';
            echo '<a href="' . esc_url( $portal_url ) . '" class="bv-woo-notice-cta--gold">Go to Client Portal →</a>';
            echo '</div>';
            echo '<div class="bv-woo-notice-sub">';
            echo '<p>You can also access your portal anytime from your account area.</p>';
            echo '</div>';
        } elseif ( $has_courses && ! $has_services ) {
            // Course only → go to Tutor LMS
            echo '<div class="bv-woo-notice-hero bv-woo-notice-hero--courses">';
            echo '<div class="bv-woo-notice-hero-icon">🎓</div>';
            echo '<h2>Your Course Enrollment is Ready!</h2>';
            echo '<p>Start learning right away from your Tutor LMS dashboard.</p>';
            echo '<a href="' . esc_url( $tutor_dashboard_url ) . '" class="bv-woo-notice-cta--white">Go to My Courses →</a>';
            echo '</div>';
        } else {
            // Both services and courses → show both links
            echo '<div class="bv-woo-notice-hero bv-woo-notice-hero--both">';
            echo '<h2>Thank You for Your Purchase!</h2>';
            echo '<p>You\'ve purchased both consulting services and courses. Here\'s where to go next:</p>';
            echo '</div>';
            echo '<div class="bv-woo-notice-grid">';
            echo '<div class="bv-woo-notice-card">';
            echo '<div class="bv-woo-notice-card-icon">✦</div>';
            echo '<h3 class="h3--navy">Consulting Services</h3>';
            echo '<p>Manage your project, sign agreements, and track progress.</p>';
            echo '<a href="' . esc_url( $portal_url ) . '" class="bv-woo-notice-card-cta--navy">Client Portal →</a>';
            echo '</div>';
            echo '<div class="bv-woo-notice-card">';
            echo '<div class="bv-woo-notice-card-icon">🎓</div>';
            echo '<h3 class="h3--teal">Your Courses</h3>';
            echo '<p>Access your enrolled courses and start learning.</p>';
            echo '<a href="' . esc_url( $tutor_dashboard_url ) . '" class="bv-woo-notice-card-cta--teal">My Courses →</a>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Get all WooCommerce products as a keyed array for dropdowns.
     *
     * @param string $product_type Optional. Filter by product type: 'simple', 'variable', 'subscription', 'course', or 'all'. Default 'all'.
     * @return array Array of [ product_id => 'Product Name (#ID) - R Price - Type' ]
     */
    public static function get_woo_products( $product_type = 'all' ) {
        if ( ! self::is_active() ) {
            return array();
        }

        $args = array(
            'status'   => 'publish',
            'limit'    => 500,
            'orderby'  => 'title',
            'order'    => 'ASC',
            'return'   => 'objects',
        );

        // Filter by product type if needed
        if ( 'all' !== $product_type ) {
            $args['type'] = $product_type;
        }

        /** @var WC_Product[] $products */
        $products = wc_get_products( $args );
        $result   = array();

        foreach ( $products as $product ) {
            $id        = $product->get_id();
            $name      = $product->get_name();
            $type      = $product->get_type();
            $price     = $product->get_price_html();
            $sku       = $product->get_sku();
            $stock_qty = $product->get_stock_quantity();
            $status    = $product->get_stock_status();

            // Build label: Product Name (#ID) [SKU]
            $label = $name . ' (#' . $id . ')';
            if ( $sku ) {
                $label .= ' [' . $sku . ']';
            }

            // Append price
            if ( $price && ! empty( strip_tags( $price ) ) ) {
                $label .= ' — ' . strip_tags( $price );
            }

            // Append type badge
            $type_label = ucfirst( str_replace( '_', ' ', $type ) );
            if ( self::is_tutor_lms_course( $id ) ) {
                $type_label = '🎓 Course';
            }
            $label .= ' — ' . $type_label;

            // Stock indicator
            if ( 'outofstock' === $status ) {
                $label .= ' ⚠ Out of stock';
            } elseif ( $stock_qty !== null && $stock_qty <= 5 ) {
                $label .= ' ⚠ Low stock (' . $stock_qty . ')';
            }

            $result[ $id ] = $label;
        }

        return $result;
    }

    // @deprecated: search_woo_products is not currently used. Removed in 2.7.1.
}