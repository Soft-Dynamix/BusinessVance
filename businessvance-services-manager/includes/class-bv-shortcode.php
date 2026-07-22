<?php
/**
 * BusinessVance Services Manager - Shortcode
 *
 * Registers the [businessvance_services] shortcode and renders the frontend.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Shortcode
 *
 * Handles shortcode registration and data retrieval for the frontend template.
 */
class BV_Shortcode {

    /**
     * Register the shortcode.
     *
     * @return void
     */
    public static function register() {
        add_shortcode( 'businessvance_services', array( __CLASS__, 'render' ) );
    }

    /**
     * Shortcode callback.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     *
     * Attributes:
     *   type         - 'all' (default), 'onceoff', 'plans'
     *   category     - Category slug to filter by.
     *   featured_only - 'true' to show only featured items.
     */
    public static function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'type'          => 'all',
                'category'      => '',
                'featured_only' => 'false',
            ),
            $atts,
            'businessvance_services'
        );

        $settings = BV_Settings::get_all();

        // Normalise type.
        $show_services = in_array( $atts['type'], array( 'all', 'onceoff' ), true );
        $show_plans    = in_array( $atts['type'], array( 'all', 'plans' ), true );
        $featured_only = filter_var( $atts['featured_only'], FILTER_VALIDATE_BOOLEAN );

        // Fetch data.
        $services  = array();
        $plans     = array();
        $categories = array();

        global $wpdb;

        if ( $show_services ) {
            $table = $wpdb->prefix . 'bv_services';
            $sql   = "SELECT * FROM $table WHERE visible = 1";

            if ( $featured_only ) {
                $sql .= ' AND featured = 1';
            }

            if ( ! empty( $atts['category'] ) ) {
                $cat_slug  = sanitize_title( $atts['category'] );
                $cat_table = $wpdb->prefix . 'bv_categories';
                $cat_id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $cat_table WHERE slug = %s", $cat_slug ) );
                if ( $cat_id ) {
                    $sql .= $wpdb->prepare( ' AND category_id = %d', $cat_id );
                }
            }

            $sql .= ' ORDER BY display_order ASC, id DESC';
            $services = $wpdb->get_results( $sql );
        }

        if ( $show_plans ) {
            $plans_table    = $wpdb->prefix . 'bv_plans';
            $features_table = $wpdb->prefix . 'bv_plan_features';

            $sql = "SELECT * FROM $plans_table WHERE visible = 1";

            if ( $featured_only ) {
                $sql .= ' AND featured = 1';
            }

            if ( ! empty( $atts['category'] ) ) {
                $cat_slug  = sanitize_title( $atts['category'] );
                $cat_table = $wpdb->prefix . 'bv_categories';
                $cat_id    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $cat_table WHERE slug = %s", $cat_slug ) );
                if ( $cat_id ) {
                    $sql .= $wpdb->prepare( ' AND category_id = %d', $cat_id );
                }
            }

            $sql .= ' ORDER BY display_order ASC, id DESC';
            $plans = $wpdb->get_results( $sql );

            // Attach features to each plan.
            foreach ( $plans as &$plan ) {
                $plan->features = $wpdb->get_results(
                    $wpdb->prepare( "SELECT text FROM $features_table WHERE plan_id = %d ORDER BY id ASC", $plan->id )
                );
            }
            unset( $plan );
        }

        // Fetch categories for the filter (if enabled).
        if ( (int) $settings['show_categories'] === 1 && empty( $atts['category'] ) ) {
            $cat_table = $wpdb->prefix . 'bv_categories';
            $categories = $wpdb->get_results( "SELECT * FROM $cat_table ORDER BY name ASC" );
        }

        // Enqueue frontend assets.
        wp_enqueue_style( 'bv-frontend' );
        wp_enqueue_script( 'bv-frontend' );

        // Build the button URLs for each service/plan.
        $wc_active = BV_WooCommerce::is_active();

        foreach ( $services as &$svc ) {
            $svc->button_url_rendered = self::build_button_url( $svc, 'service', $wc_active );
        }
        unset( $svc );

        foreach ( $plans as &$plan ) {
            $plan->button_url_rendered = self::build_button_url( $plan, 'plan', $wc_active );
        }
        unset( $plan );

        // Include template.
        ob_start();
        include plugin_dir_path( __FILE__ ) . '../templates/services-page.php';
        return ob_get_clean();
    }

    /**
     * Build the rendered button URL for a service or plan.
     *
     * @param object $item      Service or plan row.
     * @param string $type      'service' or 'plan'.
     * @param bool   $wc_active Whether WooCommerce is active.
     * @return string
     */
    private static function build_button_url( $item, $type, $wc_active ) {
        $settings = BV_Settings::get_all();

        // External link type always uses button_url.
        if ( $item->button_type === 'link' && ! empty( $item->button_url ) ) {
            return esc_url( $item->button_url );
        }

        // Cart type with a WC product.
        if ( $item->button_type === 'cart' && $wc_active && ! empty( $item->woocommerce_product_id ) ) {
            $url = BV_WooCommerce::get_add_to_cart_url( $item->woocommerce_product_id );
            if ( $url ) {
                return $url;
            }
        }

        // Quote / Booking / fallback.
        if ( $item->button_type === 'quote' ) {
            return '#bv-quote';
        }
        if ( $item->button_type === 'booking' ) {
            return '#bv-booking';
        }

        return '#';
    }
}