<?php
/**
 * BusinessVance Services Manager - Admin Dashboard
 *
 * Displays overview statistics for the BusinessVance admin panel.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Admin_Dashboard
 *
 * Renders the dashboard overview page with stat cards.
 */
class BV_Admin_Dashboard {

    /**
     * Render the dashboard page.
     *
     * @return void
     */
    public static function render_page() {
        global $wpdb;

        $services_table = $wpdb->prefix . 'bv_services';
        $plans_table    = $wpdb->prefix . 'bv_plans';
        $categories_table = $wpdb->prefix . 'bv_categories';

        // Query stats.
        $total_services  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $services_table" );
        $total_plans     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $plans_table" );
        $visible_items   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $services_table WHERE visible = 1" )
                         + (int) $wpdb->get_var( "SELECT COUNT(*) FROM $plans_table WHERE visible = 1" );
        $hidden_items    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $services_table WHERE visible = 0" )
                         + (int) $wpdb->get_var( "SELECT COUNT(*) FROM $plans_table WHERE visible = 0" );
        $featured_items  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $services_table WHERE featured = 1" )
                         + (int) $wpdb->get_var( "SELECT COUNT(*) FROM $plans_table WHERE featured = 1" );
        $total_categories = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $categories_table" );

        $stats = array(
            array(
                'label' => 'Total Services',
                'value' => $total_services,
                'icon'  => '📋',
                'color' => '#002B5C',
            ),
            array(
                'label' => 'Total Plans',
                'value' => $total_plans,
                'icon'  => '💳',
                'color' => '#002B5C',
            ),
            array(
                'label' => 'Visible Items',
                'value' => $visible_items,
                'icon'  => '👁️',
                'color' => '#1a7a3a',
            ),
            array(
                'label' => 'Hidden Items',
                'value' => $hidden_items,
                'icon'  => '🙈',
                'color' => '#8a6d3b',
            ),
            array(
                'label' => 'Featured',
                'value' => $featured_items,
                'icon'  => '⭐',
                'color' => '#c5a028',
            ),
            array(
                'label' => 'Categories',
                'value' => $total_categories,
                'icon'  => '📁',
                'color' => '#002B5C',
            ),
        );
        ?>
        <div class="wrap bv-admin-wrap">
            <h1 class="bv-page-title">BusinessVance Dashboard</h1>

            <div class="bv-dashboard-grid">
                <?php foreach ( $stats as $stat ) : ?>
                    <div class="bv-stat-card" style="border-left: 4px solid <?php echo esc_attr( $stat['color'] ); ?>">
                        <div class="bv-stat-icon"><?php echo esc_html( $stat['icon'] ); ?></div>
                        <div class="bv-stat-value"><?php echo esc_html( number_format_i18n( $stat['value'] ) ); ?></div>
                        <div class="bv-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bv-dashboard-info" style="margin-top:30px; max-width:600px;">
                <h2>Quick Start</h2>
                <ol style="line-height:2;">
                    <li>Add <strong>Categories</strong> to organise your services.</li>
                    <li>Create <strong>Services</strong> (once-off) and <strong>Plans</strong> (monthly subscriptions).</li>
                    <li>Link each item to a WooCommerce product for cart/checkout integration.</li>
                    <li>Use the shortcode <code>[businessvance_services]</code> on any page to display services.</li>
                </ol>
            </div>
        </div>
        <?php
    }
}