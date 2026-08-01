<?php
/**
 * Admin panel for BusinessVance Services Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Admin {

    /** @var array Available Lucide icon names */
    private $icons = array();

    public function __construct() {
        $this->load_icons();
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bv_get_service', array( $this, 'ajax_get_service' ) );
        add_action( 'wp_ajax_bv_save_service', array( $this, 'ajax_save_service' ) );
        add_action( 'wp_ajax_bv_delete_service', array( $this, 'ajax_delete_service' ) );
        add_action( 'wp_ajax_bv_toggle_visibility', array( $this, 'ajax_toggle_visibility' ) );
        add_action( 'wp_ajax_bv_reorder_services', array( $this, 'ajax_reorder_services' ) );
        add_action( 'wp_ajax_bv_get_plan', array( $this, 'ajax_get_plan' ) );
        add_action( 'wp_ajax_bv_save_plan', array( $this, 'ajax_save_plan' ) );
        add_action( 'wp_ajax_bv_delete_plan', array( $this, 'ajax_delete_plan' ) );
        add_action( 'wp_ajax_bv_reorder_plans', array( $this, 'ajax_reorder_plans' ) );
        add_action( 'wp_ajax_bv_save_category', array( $this, 'ajax_save_category' ) );
        add_action( 'wp_ajax_bv_delete_category', array( $this, 'ajax_delete_category' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_post' ) );
    }

    /**
     * Load available icon list
     */
    private function load_icons() {
        $this->icons = array(
            'briefcase', 'building', 'building-2', 'landmark', 'chart-bar', 'chart-line',
            'trending-up', 'trending-down', 'bar-chart-3', 'pie-chart', 'calculator',
            'file-text', 'file-check', 'file-plus', 'file-search', 'folder',
            'award', 'star', 'shield', 'shield-check', 'check-circle',
            'users', 'user-plus', 'user-check', 'handshake', 'globe',
            'palette', 'pen-tool', 'layers', 'layout', 'code',
            'mail', 'phone', 'message-circle', 'share-2', 'megaphone',
            'search', 'settings', 'sliders', 'target', 'zap',
            'heart', 'book-open', 'graduation-cap', 'lightbulb', 'rocket',
            'receipt', 'credit-card', 'banknote', 'wallet', 'piggy-bank',
            'clock', 'calendar', 'map-pin', 'truck', 'package',
        );
    }

    /**
     * Get SVG path data for an icon name
     */
    private function get_icon_svg_path( $icon_name ) {
        static $paths = array(
            'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
            'building' => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
            'building-2' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/>',
            'landmark' => '<line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/>',
            'chart-bar' => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
            'chart-line' => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
            'trending-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'trending-down' => '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
            'bar-chart-3' => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
            'pie-chart' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
            'calculator' => '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8" y1="10" y2="10.01"/><line x1="12" x2="12" y1="10" y2="10.01"/><line x1="16" x2="16" y1="10" y2="10.01"/><line x1="8" x2="8" y1="14" y2="14.01"/><line x1="12" x2="12" y1="14" y2="14.01"/><line x1="8" x2="8" y1="18" y2="18.01"/><line x1="12" x2="12" y1="18" y2="18.01"/>',
            'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
            'file-check' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
            'file-plus' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="M9 15h6"/>',
            'file-search' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><circle cx="11" cy="14" r="3"/><path d="m14 16 2 2"/>',
            'folder' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
            'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
            'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
            'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
            'handshake' => '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
            'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
            'palette' => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
            'pen-tool' => '<path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/>',
            'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
            'layout' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="9" x2="9" y1="21" y2="9"/>',
            'code' => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
            'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
            'share-2' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/>',
            'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
            'sliders' => '<line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/><line x1="2" x2="6" y1="14" y2="14"/><line x1="10" x2="14" y1="8" y2="8"/><line x1="18" x2="22" y1="16" y2="16"/>',
            'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
            'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
            'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
            'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
            'graduation-cap' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
            'lightbulb' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
            'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
            'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
            'credit-card' => '<rect width="22" height="16" x="1" y="4" rx="2"/><line x1="1" x2="23" y1="10" y2="10"/>',
            'banknote' => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
            'wallet' => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
            'piggy-bank' => '<path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 0 3 2 4.5V20h4v-2h3v2h4v-4c1-.5 1.7-1 2-2h2v-4h-2c0-1-.5-1.5-1-2"/><path d="M2 9v1c0 1.1.9 2 2 2h1"/><circle cx="16" cy="11" r="1"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'crown' => '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>',
            'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
            'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
            'package' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
            'clipboard-list' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        );
        return isset( $paths[ $icon_name ] ) ? $paths[ $icon_name ] : '';
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            __( 'BusinessVance', 'businessvance-services-manager' ),
            __( 'BusinessVance', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance',
            array( $this, 'render_dashboard' ),
            'dashicons-shield-alt',
            30
        );

        add_submenu_page(
            'businessvance',
            __( 'Dashboard', 'businessvance-services-manager' ),
            __( 'Dashboard', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'businessvance',
            __( 'Services', 'businessvance-services-manager' ),
            __( 'Services', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-services',
            array( $this, 'render_services' )
        );

        add_submenu_page(
            'businessvance',
            __( 'Subscription Plans', 'businessvance-services-manager' ),
            __( 'Plans', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-plans',
            array( $this, 'render_plans' )
        );

        add_submenu_page(
            'businessvance',
            __( 'Categories', 'businessvance-services-manager' ),
            __( 'Categories', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-categories',
            array( $this, 'render_categories' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'businessvance' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'bv-admin-css',
            BV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BV_VERSION
        );

        wp_enqueue_script(
            'bv-admin-js',
            BV_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            BV_VERSION,
            true
        );

        wp_localize_script( 'bv-admin-js', 'bvAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'strings'  => array(
                'confirm_delete'    => __( 'Are you sure you want to delete this item?', 'businessvance-services-manager' ),
                'saving'            => __( 'Saving...', 'businessvance-services-manager' ),
                'saved'             => __( 'Saved successfully!', 'businessvance-services-manager' ),
                'error'             => __( 'An error occurred. Please try again.', 'businessvance-services-manager' ),
                'reorder_saved'     => __( 'Order saved!', 'businessvance-services-manager' ),
            ),
        ) );
    }

    /**
     * Get table names
     */
    private function get_tables() {
        global $wpdb;
        return array(
            'categories' => $wpdb->prefix . 'bv_categories',
            'services'   => $wpdb->prefix . 'bv_services',
            'plans'      => $wpdb->prefix . 'bv_plans',
            'features'   => $wpdb->prefix . 'bv_plan_features',
        );
    }

    /**
     * Verify admin nonce
     */
    private function verify_nonce() {
        if ( ! check_ajax_referer( 'bv_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }
    }

    /**
     * Render Dashboard page
     */
    public function render_dashboard() {
        global $wpdb;
        $tables = $this->get_tables();

        $total_services = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']}" );
        $visible_services = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']} WHERE is_visible = 1" );
        $hidden_services = $total_services - $visible_services;
        $featured_services = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['services']} WHERE is_featured = 1" );

        $total_plans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['plans']}" );
        $featured_plans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['plans']} WHERE is_featured = 1" );

        $total_categories = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['categories']}" );

        $total_features = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['features']}" );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <span class="bv-shield-icon">🛡️</span>
                    <div>
                        <h1><?php esc_html_e( 'BusinessVance Services Manager', 'businessvance-services-manager' ); ?></h1>
                        <p class="bv-subtitle"><?php esc_html_e( 'INSIGHT. STRATEGY. SUCCESS.', 'businessvance-services-manager' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="bv-stats-grid">
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #002B5C;"><?php echo esc_html( $total_services ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Total Services', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #008080;"><?php echo esc_html( $total_plans ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Subscription Plans', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #16a34a;"><?php echo esc_html( $visible_services ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Visible Services', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #dc2626;"><?php echo esc_html( $hidden_services ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Hidden Services', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #D4AF37;"><?php echo esc_html( $featured_services + $featured_plans ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Featured Items', 'businessvance-services-manager' ); ?></div>
                </div>
                <div class="bv-stat-card">
                    <div class="bv-stat-number" style="color: #6366f1;"><?php echo esc_html( $total_categories ); ?></div>
                    <div class="bv-stat-label"><?php esc_html_e( 'Categories', 'businessvance-services-manager' ); ?></div>
                </div>
            </div>

            <div class="bv-dashboard-info">
                <h2><?php esc_html_e( 'Shortcodes', 'businessvance-services-manager' ); ?></h2>
                <div class="bv-shortcode-grid">
                    <div class="bv-shortcode-card">
                        <h3>[businessvance_services]</h3>
                        <p><?php esc_html_e( 'Renders the complete services page with header, once-off services table, subscription plans, and footer.', 'businessvance-services-manager' ); ?></p>
                        <code>[businessvance_services]</code>
                    </div>
                    <div class="bv-shortcode-card">
                        <h3>[businessvance_onceoff]</h3>
                        <p><?php esc_html_e( 'Renders only the once-off services table section.', 'businessvance-services-manager' ); ?></p>
                        <code>[businessvance_onceoff]</code>
                    </div>
                    <div class="bv-shortcode-card">
                        <h3>[businessvance_subscriptions]</h3>
                        <p><?php esc_html_e( 'Renders only the subscription plans section.', 'businessvance-services-manager' ); ?></p>
                        <code>[businessvance_subscriptions]</code>
                    </div>
                </div>
            </div>

            <div class="bv-dashboard-info" style="display:flex;flex-wrap:wrap;gap:12px;">
                <h2 style="width:100%;"><?php esc_html_e( 'Quick Access', 'businessvance-services-manager' ); ?></h2>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=businessvance-settings' ) ); ?>"
                   class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;padding:8px 20px;">
                    <span class="dashicons dashicons-admin-generic" style="font-size:18px;"></span>
                    <?php esc_html_e( 'Settings', 'businessvance-services-manager' ); ?>
                </a>
                <a href="#" onclick="prompt('<?php esc_attr_e( 'Client Portal Shortcode:', 'businessvance-services-manager' ); ?>', '[businessvance_client_portal]'); return false;"
                   class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;font-size:14px;padding:8px 20px;">
                    <span class="dashicons dashicons-admin-users" style="font-size:18px;"></span>
                    <?php esc_html_e( 'Client Portal', 'businessvance-services-manager' ); ?>
                </a>
            </div>

            <div class="bv-dashboard-info">
                <h2><?php esc_html_e( 'Quick Start', 'businessvance-services-manager' ); ?></h2>
                <ol class="bv-steps">
                    <li><?php esc_html_e( 'Go to <strong>Settings</strong> and configure your company branding, colors, and contact info.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Add your Categories under the Categories menu.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Create your WooCommerce products (simple products for once-off, subscription products for plans).', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Add your Services and link each to its WooCommerce Product ID.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Set up Subscription Plans with features and link to WooCommerce subscription products.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Create a new WordPress page and add the shortcode [businessvance_services].', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Create another page with [businessvance_client_portal] for the client portal.', 'businessvance-services-manager' ); ?></li>
                    <li><?php esc_html_e( 'Ensure Yoco payment gateway is configured in WooCommerce settings.', 'businessvance-services-manager' ); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }

    /**
     * Render Services page
     */
    public function render_services() {
        global $wpdb;
        $tables = $this->get_tables();

        $services = $wpdb->get_results(
            "SELECT s.*, c.name as category_name, c.color as category_color
             FROM {$tables['services']} s
             LEFT JOIN {$tables['categories']} c ON s.category_id = c.id
             ORDER BY s.display_order ASC, s.id ASC"
        );

        $categories = $wpdb->get_results( "SELECT * FROM {$tables['categories']} ORDER BY name ASC" );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Services', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Manage your once-off services displayed on the services page.', 'businessvance-services-manager' ); ?></p>
                </div>
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-service">
                    <?php esc_html_e( '+ Add Service', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <div class="bv-table-container">
                <table class="wp-list-table widefat fixed striped bv-sortable-table" id="bv-services-table">
                    <thead>
                        <tr>
                            <th style="width:40px;" class="bv-sort-handle-col"></th>
                            <th><?php esc_html_e( 'Service', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Price', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Woo Product', 'businessvance-services-manager' ); ?></th>
                            <th style="width:110px;"><?php esc_html_e( 'Questionnaire', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Visible', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Featured', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $services ) ) : ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px;">
                                    <?php esc_html_e( 'No services found. Click "Add Service" to create your first service.', 'businessvance-services-manager' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $services as $service ) : ?>
                                <tr data-id="<?php echo esc_attr( $service->id ); ?>">
                                    <td class="bv-sort-handle-col">
                                        <span class="bv-sort-handle" title="<?php esc_attr_e( 'Drag to reorder', 'businessvance-services-manager' ); ?>">☰</span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $service->name ); ?></strong>
                                        <br>
                                        <small style="color:#666;">
                                            <?php echo esc_html( wp_trim_words( $service->description, 10, '...' ) ); ?>
                                        </small>
                                        <?php if ( $service->is_featured ) : ?>
                                            <span class="bv-badge-featured">★ Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $service->price ); ?></td>
                                    <td>
                                        <?php if ( $service->category_name ) : ?>
                                            <span class="bv-category-dot" style="background-color:<?php echo esc_attr( $service->category_color ); ?>"></span>
                                            <?php echo esc_html( $service->category_name ); ?>
                                        <?php else : ?>
                                            <em style="color:#999;"><?php esc_html_e( 'None', 'businessvance-services-manager' ); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $service->woo_product_id ) : ?>
                                            <?php 
                                            $woo_product = function_exists( 'wc_get_product' ) ? wc_get_product( $service->woo_product_id ) : null;
                                            $woo_name = $woo_product ? $woo_product->get_name() : '';
                                            ?>
                                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $service->woo_product_id . '&action=edit' ) ); ?>" target="_blank" title="<?php esc_attr_e( 'Edit product in WooCommerce', 'businessvance-services-manager' ); ?>">
                                                <?php if ( $woo_name ) : ?>
                                                    <span class="bv-woo-product-link"><?php echo esc_html( $woo_name ); ?></span>
                                                    <small style="color:#999;display:block;">#<?php echo esc_html( $service->woo_product_id ); ?></small>
                                                <?php else : ?>
                                                    #<?php echo esc_html( $service->woo_product_id ); ?>
                                                    <small style="color:#d63638;display:block;"><?php esc_html_e( 'Product not found', 'businessvance-services-manager' ); ?></small>
                                                <?php endif; ?>
                                            </a>
                                        <?php else : ?>
                                            <em style="color:#999;"><?php esc_html_e( 'Not linked', 'businessvance-services-manager' ); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $service->questionnaire_template_id ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=businessvance-questionnaires' ) ); ?>" title="<?php esc_attr_e( 'Open Questionnaire Manager', 'businessvance-services-manager' ); ?>">
                                                📋 #<?php echo esc_html( $service->questionnaire_template_id ); ?>
                                            </a>
                                        <?php else : ?>
                                            <em style="color:#999;">—</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="bv-toggle-btn <?php echo $service->is_visible ? 'bv-active' : 'bv-inactive'; ?>"
                                                data-id="<?php echo esc_attr( $service->id ); ?>"
                                                data-type="service"
                                                title="<?php echo $service->is_visible ? esc_attr__( 'Click to hide', 'businessvance-services-manager' ) : esc_attr__( 'Click to show', 'businessvance-services-manager' ); ?>">
                                            <?php echo $service->is_visible ? '👁️' : '🚫'; ?>
                                        </button>
                                    </td>
                                    <td>
                                        <span style="font-size:18px;"><?php echo $service->is_featured ? '⭐' : '☆'; ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small bv-edit-btn" data-id="<?php echo esc_attr( $service->id ); ?>">
                                            <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-delete-btn" data-id="<?php echo esc_attr( $service->id ); ?>" data-type="service" data-name="<?php echo esc_attr( $service->name ); ?>">
                                            <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Service Form Modal -->
        <div id="bv-service-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content">
                <div class="bv-modal-header">
                    <h2 id="bv-service-modal-title"><?php esc_html_e( 'Add New Service', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-service-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group bv-form-full">
                            <label for="svc-name"><?php esc_html_e( 'Service Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-name" name="name" required class="regular-text">
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="svc-description"><?php esc_html_e( 'Description', 'businessvance-services-manager' ); ?></label>
                            <textarea id="svc-description" name="description" rows="3" class="large-text"></textarea>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-price"><?php esc_html_e( 'Price (e.g. R1,500)', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-price" name="price" value="R0" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-price-display"><?php esc_html_e( 'Price Display (override)', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-price-display" name="price_display" placeholder="<?php esc_attr_e( 'Leave blank to use Price', 'businessvance-services-manager' ); ?>" class="regular-text">
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="svc-icon"><?php esc_html_e( 'Icon', 'businessvance-services-manager' ); ?></label>
                            <div class="bv-icon-picker-wrap">
                                <input type="text" class="bv-icon-search-input" placeholder="<?php esc_attr_e( 'Search icons...', 'businessvance-services-manager' ); ?>">
                                <div class="bv-icon-picker-grid" id="bv-icon-picker-grid">
                                    <?php foreach ( $this->icons as $icon ) : ?>
                                        <button type="button" class="bv-icon-pick-btn" data-icon="<?php echo esc_attr( $icon ); ?>" title="<?php echo esc_attr( $icon ); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $this->get_icon_svg_path( $icon ); ?></svg>
                                            <span class="bv-icon-pick-label"><?php echo esc_html( $icon ); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="bv-icon-picker-selected">
                                    <label><?php esc_html_e( 'Selected:', 'businessvance-services-manager' ); ?></label>
                                    <div id="bv-icon-preview" class="bv-icon-preview-box"></div>
                                    <input type="hidden" id="svc-icon" name="icon" value="briefcase">
                                </div>
                            </div>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-button-label"><?php esc_html_e( 'Button Label', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-button-label" name="button_label" value="Get Started" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-type"><?php esc_html_e( 'Service Type', 'businessvance-services-manager' ); ?></label>
                            <select id="svc-type" name="service_type">
                                <option value="onceoff"><?php esc_html_e( 'Once-off', 'businessvance-services-manager' ); ?></option>
                                <option value="quote"><?php esc_html_e( 'Request Quote', 'businessvance-services-manager' ); ?></option>
                                <option value="booking"><?php esc_html_e( 'Book Consultation', 'businessvance-services-manager' ); ?></option>
                                <option value="download"><?php esc_html_e( 'Digital Download', 'businessvance-services-manager' ); ?></option>
                            </select>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-category"><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></label>
                            <select id="svc-category" name="category_id">
                                <option value="0"><?php esc_html_e( '-- None --', 'businessvance-services-manager' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="svc-woo-product"><?php esc_html_e( 'WooCommerce Product', 'businessvance-services-manager' ); ?></label>
                            <?php if ( BV_WooCommerce::is_active() ) : ?>
                                <?php 
                                $svc_wc_products = BV_WooCommerce::get_woo_products();
                                $svc_simple_products = array();
                                $svc_variable_products = array();
                                $svc_subscription_products = array();
                                $svc_course_products = array();
                                $svc_other_products = array();
                                
                                foreach ( $svc_wc_products as $pid => $plabel ) {
                                    $product = wc_get_product( $pid );
                                    if ( ! $product ) continue;
                                    $type = $product->get_type();
                                    if ( BV_WooCommerce::is_tutor_lms_course( $pid ) ) {
                                        $svc_course_products[$pid] = $plabel;
                                    } elseif ( $type === 'simple' ) {
                                        $svc_simple_products[$pid] = $plabel;
                                    } elseif ( $type === 'variable' || $type === 'variation' ) {
                                        $svc_variable_products[$pid] = $plabel;
                                    } elseif ( $type === 'subscription' || $type === 'variable-subscription' || $type === 'simple-subscription' ) {
                                        $svc_subscription_products[$pid] = $plabel;
                                    } else {
                                        $svc_other_products[$pid] = $plabel;
                                    }
                                }
                                ?>
                                <div class="bv-woo-select-wrap">
                                    <input type="text" class="bv-woo-search" placeholder="<?php esc_attr_e( 'Search products...', 'businessvance-services-manager' ); ?>">
                                    <select id="svc-woo-product" name="woo_product_id" class="bv-woo-product-select regular-text">
                                        <option value="0"><?php esc_html_e( '-- None (Not Linked) --', 'businessvance-services-manager' ); ?></option>
                                        <?php if ( ! empty( $svc_simple_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Simple Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $svc_simple_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $svc_variable_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Variable Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $svc_variable_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $svc_subscription_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Subscription Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $svc_subscription_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $svc_course_products ) ) : ?>
                                            <optgroup label="🎓 <?php esc_attr_e( 'Tutor LMS Courses', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $svc_course_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $svc_other_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Other Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $svc_other_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description"><?php echo esc_html( sprintf( __( '%d WooCommerce products found. Select one to link this service for payment processing.', 'businessvance-services-manager' ), count( $svc_wc_products ) ) ); ?></p>
                                </div>
                            <?php else : ?>
                                <input type="number" id="svc-woo-product" name="woo_product_id" value="0" min="0" class="regular-text">
                                <p class="description" style="color:#d63638;"><?php esc_html_e( 'WooCommerce is not active. Enter product ID manually.', 'businessvance-services-manager' ); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="bv-form-group">
                            <label for="svc-questionnaire"><?php esc_html_e( 'Questionnaire Template', 'businessvance-services-manager' ); ?></label>
                            <select id="svc-questionnaire" name="questionnaire_template_id">
                                <option value="0"><?php esc_html_e( '-- None --', 'businessvance-services-manager' ); ?></option>
                                <?php
                                global $wpdb;
                                $qt_templates = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}bv_questionnaire_templates ORDER BY name ASC" );
                                foreach ( $qt_templates as $qt ) : ?>
                                    <option value="<?php echo esc_attr( $qt->id ); ?>"><?php echo esc_html( $qt->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Link a questionnaire template. Clients will answer these questions in the portal.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label><?php esc_html_e( 'Agreement Templates', 'businessvance-services-manager' ); ?></label>
                            <div class="bv-multi-select-wrap">
                                <div class="bv-multi-select-list" id="bv-svc-agreements-list">
                                    <!-- Dynamically populated by JS -->
                                </div>
                                <div class="bv-multi-select-add">
                                    <select id="bv-svc-add-agreement" class="regular-text" style="max-width:320px;">
                                        <option value=""><?php esc_html_e( '+ Add agreement...', 'businessvance-services-manager' ); ?></option>
                                        <?php
                                        $all_agreement_templates = $wpdb->get_results( "SELECT id, name, type FROM {$wpdb->prefix}bv_agreement_templates ORDER BY type ASC, name ASC" );
                                        $aat_types = array();
                                        foreach ( $all_agreement_templates as $aat ) {
                                            $aat_types[$aat->type][] = $aat;
                                        }
                                        $aat_labels = array(
                                            'nda'               => 'NDA',
                                            'service-agreement' => 'Service Agreement',
                                            'confidentiality'   => 'Confidentiality Agreement',
                                            'custom'            => 'Custom',
                                        );
                                        foreach ( $aat_types as $aat_type => $aat_items ) :
                                            $aat_label = isset( $aat_labels[$aat_type] ) ? $aat_labels[$aat_type] : $aat_type;
                                        ?>
                                            <optgroup label="<?php echo esc_attr( $aat_label ); ?>">
                                                <?php foreach ( $aat_items as $aat ) : ?>
                                                    <option value="<?php echo esc_attr( $aat->id ); ?>"><?php echo esc_html( $aat->name ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                        <?php if ( empty( $all_agreement_templates ) ) : ?>
                                            <option value="" disabled><?php esc_html_e( 'No templates yet', 'businessvance-services-manager' ); ?></option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="button" class="button button-small" id="bv-svc-add-agreement-btn" title="<?php esc_attr_e( 'Add selected agreement template', 'businessvance-services-manager' ); ?>">+</button>
                                </div>
                                <p class="description"><?php esc_html_e( 'Select one or more agreement/NDA templates. If none are assigned, the global agreement from Settings will be used.', 'businessvance-services-manager' ); ?></p>
                            </div>
                            <!-- Hidden input stores comma-separated IDs for form submission -->
                            <input type="hidden" id="svc-agreement-ids" name="agreement_ids" value="">
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="nda_only" value="1">
                                <?php esc_html_e( 'NDA Only (skip service agreement)', 'businessvance-services-manager' ); ?>
                            </label>
                            <p class="description" style="margin-top:4px;"><?php esc_html_e( 'If checked, only a confidentiality/NDA is required — the full service agreement is skipped.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="svc-required-docs"><?php esc_html_e( 'Required Documents', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="svc-required-docs" name="required_documents" placeholder="<?php esc_attr_e( 'e.g. ID Copy, Proof of Address, Company Registration', 'businessvance-services-manager' ); ?>" class="large-text">
                            <p class="description"><?php esc_html_e( 'Comma-separated list of documents the client must upload. Leave blank to skip document uploads for this service.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_visible" value="1" checked>
                                <?php esc_html_e( 'Visible on frontend', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_featured" value="1">
                                <?php esc_html_e( 'Featured (shows badge)', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Service', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render Plans page
     */
    public function render_plans() {
        global $wpdb;
        $tables = $this->get_tables();

        $plans = $wpdb->get_results(
            "SELECT p.*, c.name as category_name, c.color as category_color,
                    (SELECT COUNT(*) FROM {$tables['features']} f WHERE f.plan_id = p.id) as feature_count
             FROM {$tables['plans']} p
             LEFT JOIN {$tables['categories']} c ON p.category_id = c.id
             ORDER BY p.display_order ASC, p.id ASC"
        );

        $categories = $wpdb->get_results( "SELECT * FROM {$tables['categories']} ORDER BY name ASC" );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Subscription Plans', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Manage monthly subscription plans displayed on the services page.', 'businessvance-services-manager' ); ?></p>
                </div>
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-plan">
                    <?php esc_html_e( '+ Add Plan', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <div class="bv-table-container">
                <table class="wp-list-table widefat fixed striped bv-sortable-table" id="bv-plans-table">
                    <thead>
                        <tr>
                            <th style="width:40px;" class="bv-sort-handle-col"></th>
                            <th><?php esc_html_e( 'Plan', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Price', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Color', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Woo Product', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Features', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Visible', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Featured', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $plans ) ) : ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:40px;">
                                    <?php esc_html_e( 'No plans found. Click "Add Plan" to create your first subscription plan.', 'businessvance-services-manager' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $plans as $plan ) : ?>
                                <tr data-id="<?php echo esc_attr( $plan->id ); ?>">
                                    <td class="bv-sort-handle-col">
                                        <span class="bv-sort-handle" title="<?php esc_attr_e( 'Drag to reorder', 'businessvance-services-manager' ); ?>">☰</span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html( $plan->name ); ?></strong>
                                        <?php if ( $plan->subtitle ) : ?>
                                            <br><small style="color:#666;"><?php echo esc_html( $plan->subtitle ); ?></small>
                                        <?php endif; ?>
                                        <?php if ( $plan->is_featured ) : ?>
                                            <span class="bv-badge-featured">★ Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $plan->price ); ?></td>
                                    <td><span class="bv-color-dot" style="background-color:<?php echo esc_attr( $plan->color ); ?>"></span></td>
                                    <td>
                                        <?php if ( $plan->woo_product_id ) : ?>
                                            <?php 
                                            $plan_woo_product = function_exists( 'wc_get_product' ) ? wc_get_product( $plan->woo_product_id ) : null;
                                            $plan_woo_name = $plan_woo_product ? $plan_woo_product->get_name() : '';
                                            ?>
                                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $plan->woo_product_id . '&action=edit' ) ); ?>" target="_blank" title="<?php esc_attr_e( 'Edit product in WooCommerce', 'businessvance-services-manager' ); ?>">
                                                <?php if ( $plan_woo_name ) : ?>
                                                    <span class="bv-woo-product-link"><?php echo esc_html( $plan_woo_name ); ?></span>
                                                    <small style="color:#999;display:block;">#<?php echo esc_html( $plan->woo_product_id ); ?></small>
                                                <?php else : ?>
                                                    #<?php echo esc_html( $plan->woo_product_id ); ?>
                                                    <small style="color:#d63638;display:block;"><?php esc_html_e( 'Product not found', 'businessvance-services-manager' ); ?></small>
                                                <?php endif; ?>
                                            </a>
                                        <?php else : ?>
                                            <em style="color:#999;"><?php esc_html_e( 'Not linked', 'businessvance-services-manager' ); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $plan->feature_count ); ?></td>
                                    <td>
                                        <button type="button" class="bv-toggle-btn <?php echo $plan->is_visible ? 'bv-active' : 'bv-inactive'; ?>"
                                                data-id="<?php echo esc_attr( $plan->id ); ?>"
                                                data-type="plan"
                                                title="<?php echo $plan->is_visible ? esc_attr__( 'Click to hide', 'businessvance-services-manager' ) : esc_attr__( 'Click to show', 'businessvance-services-manager' ); ?>">
                                            <?php echo $plan->is_visible ? '👁️' : '🚫'; ?>
                                        </button>
                                    </td>
                                    <td>
                                        <span style="font-size:18px;"><?php echo $plan->is_featured ? '⭐' : '☆'; ?></span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small bv-edit-btn" data-id="<?php echo esc_attr( $plan->id ); ?>" data-type="plan">
                                            <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-delete-btn" data-id="<?php echo esc_attr( $plan->id ); ?>" data-type="plan" data-name="<?php echo esc_attr( $plan->name ); ?>">
                                            <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Plan Form Modal -->
        <div id="bv-plan-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content">
                <div class="bv-modal-header">
                    <h2 id="bv-plan-modal-title"><?php esc_html_e( 'Add New Plan', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-plan-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group">
                            <label for="plan-name"><?php esc_html_e( 'Plan Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-name" name="name" required class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-subtitle"><?php esc_html_e( 'Subtitle', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-subtitle" name="subtitle" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. For growing businesses', 'businessvance-services-manager' ); ?>">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-price"><?php esc_html_e( 'Price (e.g. R599/mo)', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-price" name="price" value="R0/mo" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-color"><?php esc_html_e( 'Theme Color', 'businessvance-services-manager' ); ?></label>
                            <input type="color" id="plan-color" name="color" value="#008080">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-button-label"><?php esc_html_e( 'Button Label', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="plan-button-label" name="button_label" value="Subscribe Now" class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="plan-category"><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></label>
                            <select id="plan-category" name="category_id">
                                <option value="0"><?php esc_html_e( '-- None --', 'businessvance-services-manager' ); ?></option>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="plan-woo-product"><?php esc_html_e( 'WooCommerce Product', 'businessvance-services-manager' ); ?></label>
                            <?php if ( BV_WooCommerce::is_active() ) : ?>
                                <?php 
                                $plan_wc_products = BV_WooCommerce::get_woo_products();
                                $plan_simple_products = array();
                                $plan_variable_products = array();
                                $plan_subscription_products = array();
                                $plan_course_products = array();
                                $plan_other_products = array();
                                
                                foreach ( $plan_wc_products as $pid => $plabel ) {
                                    $product = wc_get_product( $pid );
                                    if ( ! $product ) continue;
                                    $type = $product->get_type();
                                    if ( BV_WooCommerce::is_tutor_lms_course( $pid ) ) {
                                        $plan_course_products[$pid] = $plabel;
                                    } elseif ( $type === 'simple' ) {
                                        $plan_simple_products[$pid] = $plabel;
                                    } elseif ( $type === 'variable' || $type === 'variation' ) {
                                        $plan_variable_products[$pid] = $plabel;
                                    } elseif ( $type === 'subscription' || $type === 'variable-subscription' || $type === 'simple-subscription' ) {
                                        $plan_subscription_products[$pid] = $plabel;
                                    } else {
                                        $plan_other_products[$pid] = $plabel;
                                    }
                                }
                                ?>
                                <div class="bv-woo-select-wrap">
                                    <input type="text" class="bv-woo-search" placeholder="<?php esc_attr_e( 'Search products...', 'businessvance-services-manager' ); ?>">
                                    <select id="plan-woo-product" name="woo_product_id" class="bv-woo-product-select regular-text">
                                        <option value="0"><?php esc_html_e( '-- None (Not Linked) --', 'businessvance-services-manager' ); ?></option>
                                        <?php if ( ! empty( $plan_simple_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Simple Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $plan_simple_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $plan_variable_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Variable Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $plan_variable_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $plan_subscription_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Subscription Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $plan_subscription_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $plan_course_products ) ) : ?>
                                            <optgroup label="🎓 <?php esc_attr_e( 'Tutor LMS Courses', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $plan_course_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $plan_other_products ) ) : ?>
                                            <optgroup label="<?php esc_attr_e( 'Other Products', 'businessvance-services-manager' ); ?>">
                                                <?php foreach ( $plan_other_products as $pid => $plabel ) : ?>
                                                    <option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $plabel ); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description"><?php echo esc_html( sprintf( __( '%d WooCommerce products found. Select one to link this plan for subscription payment.', 'businessvance-services-manager' ), count( $plan_wc_products ) ) ); ?></p>
                                </div>
                            <?php else : ?>
                                <input type="number" id="plan-woo-product" name="woo_product_id" value="0" min="0" class="regular-text">
                                <p class="description" style="color:#d63638;"><?php esc_html_e( 'WooCommerce is not active. Enter product ID manually.', 'businessvance-services-manager' ); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_visible" value="1" checked>
                                <?php esc_html_e( 'Visible on frontend', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_featured" value="1">
                                <?php esc_html_e( 'Featured (highlighted card)', 'businessvance-services-manager' ); ?>
                            </label>
                        </div>
                    </div>

                    <div class="bv-form-group bv-form-full">
                        <label><?php esc_html_e( 'Features', 'businessvance-services-manager' ); ?></label>
                        <div id="bv-features-list">
                            <!-- Features will be added dynamically -->
                        </div>
                        <button type="button" id="bv-add-feature" class="button button-small">+ <?php esc_html_e( 'Add Feature', 'businessvance-services-manager' ); ?></button>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Plan', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render Categories page
     */
    public function render_categories() {
        global $wpdb;
        $tables = $this->get_tables();

        $categories = $wpdb->get_results(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$tables['services']} s WHERE s.category_id = c.id) as service_count,
                    (SELECT COUNT(*) FROM {$tables['plans']} p WHERE p.category_id = c.id) as plan_count
             FROM {$tables['categories']} c
             ORDER BY c.name ASC"
        );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Categories', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Organize your services and plans into categories.', 'businessvance-services-manager' ); ?></p>
                </div>
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-category">
                    <?php esc_html_e( '+ Add Category', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <div class="bv-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Category', 'businessvance-services-manager' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( 'Slug', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Color', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Services', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Plans', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $categories ) ) : ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px;">
                                    <?php esc_html_e( 'No categories found. Click "Add Category" to create one.', 'businessvance-services-manager' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $categories as $cat ) : ?>
                                <tr data-id="<?php echo esc_attr( $cat->id ); ?>">
                                    <td>
                                        <span class="bv-category-dot" style="background-color:<?php echo esc_attr( $cat->color ); ?>"></span>
                                        <strong><?php echo esc_html( $cat->name ); ?></strong>
                                    </td>
                                    <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                                    <td><span class="bv-color-dot" style="background-color:<?php echo esc_attr( $cat->color ); ?>"></span></td>
                                    <td><?php echo esc_html( $cat->service_count ); ?></td>
                                    <td><?php echo esc_html( $cat->plan_count ); ?></td>
                                    <td>
                                        <button type="button" class="button button-small bv-edit-btn" data-id="<?php echo esc_attr( $cat->id ); ?>" data-type="category">
                                            <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-delete-btn" data-id="<?php echo esc_attr( $cat->id ); ?>" data-type="category" data-name="<?php echo esc_attr( $cat->name ); ?>">
                                            <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Category Form Modal -->
        <div id="bv-category-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content bv-modal-small">
                <div class="bv-modal-header">
                    <h2 id="bv-category-modal-title"><?php esc_html_e( 'Add New Category', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-category-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group bv-form-full">
                            <label for="cat-name"><?php esc_html_e( 'Category Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="cat-name" name="name" required class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Financial Services', 'businessvance-services-manager' ); ?>">
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="cat-slug"><?php esc_html_e( 'Slug', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="cat-slug" name="slug" class="regular-text" placeholder="<?php esc_attr_e( 'auto-generated-from-name', 'businessvance-services-manager' ); ?>">
                            <p class="description"><?php esc_html_e( 'Auto-generated from name if left blank.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="cat-color"><?php esc_html_e( 'Color', 'businessvance-services-manager' ); ?></label>
                            <input type="color" id="cat-color" name="color" value="#008080">
                        </div>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Category', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle admin POST requests (fallback for non-AJAX)
     */
    public function handle_admin_post() {
        // All CRUD is handled via AJAX
    }

    /* ========== AJAX HANDLERS ========== */

    /**
     * Get service data for editing
     */
    public function ajax_get_service() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['services']} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $service ) {
            wp_send_json_error( array( 'message' => 'Service not found.' ) );
        }

        // Get associated agreement template IDs from junction table
        $service['agreement_ids'] = $wpdb->get_col( $wpdb->prepare(
            "SELECT agreement_template_id FROM {$wpdb->prefix}bv_service_agreements WHERE service_id = %d ORDER BY display_order ASC",
            $id
        ) );

        // Fallback: if junction is empty but legacy column has a value, use that
        if ( empty( $service['agreement_ids'] ) && ! empty( $service['agreement_template_id'] ) ) {
            $service['agreement_ids'] = array( $service['agreement_template_id'] );
        }

        wp_send_json_success( $service );
    }

    /**
     * Save (create/update) service
     */
    public function ajax_save_service() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        $data = array(
            'name'                    => sanitize_text_field( $_POST['name'] ?? '' ),
            'description'             => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'price'                   => sanitize_text_field( $_POST['price'] ?? 'R0' ),
            'price_display'           => sanitize_text_field( $_POST['price_display'] ?? '' ),
            'icon'                    => sanitize_text_field( $_POST['icon'] ?? 'briefcase' ),
            'button_label'            => sanitize_text_field( $_POST['button_label'] ?? 'Get Started' ),
            'service_type'            => sanitize_text_field( $_POST['service_type'] ?? 'onceoff' ),
            'woo_product_id'          => intval( $_POST['woo_product_id'] ?? 0 ),
            'questionnaire_template_id' => intval( $_POST['questionnaire_template_id'] ?? 0 ),
            'category_id'             => intval( $_POST['category_id'] ?? 0 ),
            'nda_only'                => intval( $_POST['nda_only'] ?? 0 ),
            'required_documents'       => sanitize_text_field( $_POST['required_documents'] ?? '' ),
            'is_visible'              => intval( $_POST['is_visible'] ?? 0 ),
            'is_featured'             => intval( $_POST['is_featured'] ?? 0 ),
        );
        $format = array( '%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%d','%d' );

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Service name is required.', 'businessvance-services-manager' ) ) );
        }

        // Parse agreement IDs from comma-separated string
        $agreement_ids_raw = isset( $_POST['agreement_ids'] ) ? sanitize_text_field( $_POST['agreement_ids'] ) : '';
        $agreement_ids = array();
        if ( ! empty( $agreement_ids_raw ) ) {
            $agreement_ids = array_map( 'intval', explode( ',', $agreement_ids_raw ) );
            $agreement_ids = array_filter( $agreement_ids, function( $v ) { return $v > 0; } );
            $agreement_ids = array_values( array_unique( $agreement_ids ) );
        }

        if ( $id > 0 ) {
            // Update
            $wpdb->update( $tables['services'], $data, array( 'id' => $id ), $format, array( '%d' ) );

            // Sync agreement junction table
            $this->sync_service_agreements( $id, $agreement_ids );

            wp_send_json_success( array( 'message' => __( 'Service updated.', 'businessvance-services-manager' ), 'id' => $id ) );
        } else {
            // Get max display_order
            $max_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order), 0) FROM {$tables['services']}" );
            $data['display_order'] = $max_order + 1;
            $format[] = '%d';

            $wpdb->insert( $tables['services'], $data, $format );
            $new_id = $wpdb->insert_id;

            // Sync agreement junction table
            $this->sync_service_agreements( $new_id, $agreement_ids );

            wp_send_json_success( array( 'message' => __( 'Service created.', 'businessvance-services-manager' ), 'id' => $new_id ) );
        }
    }

    /**
     * Sync the bv_service_agreements junction table for a service.
     * Deletes all existing rows then re-inserts.
     *
     * @param int   $service_id
     * @param array $agreement_ids Array of agreement_template_id values
     */
    private function sync_service_agreements( $service_id, $agreement_ids ) {
        global $wpdb;
        $junction = $wpdb->prefix . 'bv_service_agreements';

        // Delete existing
        $wpdb->delete( $junction, array( 'service_id' => $service_id ), array( '%d' ) );

        // Insert new
        foreach ( $agreement_ids as $order => $tpl_id ) {
            $wpdb->insert( $junction, array(
                'service_id'            => $service_id,
                'agreement_template_id' => intval( $tpl_id ),
                'display_order'         => $order,
            ), array( '%d', '%d', '%d' ) );
        }

        // Also update the legacy column for backward compat (first one)
        $first_id = ! empty( $agreement_ids ) ? intval( $agreement_ids[0] ) : 0;
        $wpdb->update(
            $wpdb->prefix . 'bv_services',
            array( 'agreement_template_id' => $first_id ),
            array( 'id' => $service_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Delete service
     */
    public function ajax_delete_service() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $wpdb->delete( $tables['services'], array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success( array( 'message' => __( 'Service deleted.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Toggle visibility
     */
    public function ajax_toggle_visibility() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id   = intval( $_POST['id'] ?? 0 );
        $type = sanitize_text_field( $_POST['type'] ?? '' );

        if ( ! in_array( $type, array( 'service', 'plan' ), true ) || $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        }

        $table = ( $type === 'service' ) ? $tables['services'] : $tables['plans'];
        $current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_visible FROM {$table} WHERE id = %d", $id ) );
        $new_value = $current ? 0 : 1;

        $wpdb->update( $table, array( 'is_visible' => $new_value ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
        wp_send_json_success( array( 'is_visible' => $new_value ) );
    }

    /**
     * Reorder services
     */
    public function ajax_reorder_services() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $order = $_POST['order'] ?? array();
        if ( ! is_array( $order ) ) {
            wp_send_json_error();
        }

        foreach ( $order as $position => $id ) {
            $wpdb->update(
                $tables['services'],
                array( 'display_order' => intval( $position ) ),
                array( 'id' => intval( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success( array( 'message' => __( 'Order saved.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Get plan data for editing
     */
    public function ajax_get_plan() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? $_GET['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $plan = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tables['plans']} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $plan ) {
            wp_send_json_error( array( 'message' => 'Plan not found.' ) );
        }

        $plan['features'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tables['features']} WHERE plan_id = %d ORDER BY display_order ASC",
                $id
            ),
            ARRAY_A
        );

        wp_send_json_success( $plan );
    }

    /**
     * Save (create/update) plan
     */
    public function ajax_save_plan() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        $data = array(
            'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
            'subtitle'      => sanitize_text_field( $_POST['subtitle'] ?? '' ),
            'price'         => sanitize_text_field( $_POST['price'] ?? 'R0/mo' ),
            'color'         => sanitize_text_field( $_POST['color'] ?? '#008080' ),
            'button_label'  => sanitize_text_field( $_POST['button_label'] ?? 'Subscribe Now' ),
            'woo_product_id' => intval( $_POST['woo_product_id'] ?? 0 ),
            'category_id'   => intval( $_POST['category_id'] ?? 0 ),
            'is_visible'    => intval( $_POST['is_visible'] ?? 0 ),
            'is_featured'   => intval( $_POST['is_featured'] ?? 0 ),
        );
        $format = array( '%s','%s','%s','%s','%s','%d','%d','%d','%d' );

        $features = $_POST['features'] ?? array();
        if ( ! is_array( $features ) ) {
            $features = array();
        }
        $features = array_map( 'sanitize_text_field', $features );

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Plan name is required.', 'businessvance-services-manager' ) ) );
        }

        if ( $id > 0 ) {
            // Update plan
            $wpdb->update( $tables['plans'], $data, array( 'id' => $id ), $format, array( '%d' ) );

            // Update features - delete existing and re-insert
            $wpdb->delete( $tables['features'], array( 'plan_id' => $id ), array( '%d' ) );
            foreach ( $features as $i => $feature_text ) {
                if ( ! empty( $feature_text ) ) {
                    $wpdb->insert( $tables['features'], array(
                        'plan_id'       => $id,
                        'feature_text'  => $feature_text,
                        'display_order' => $i,
                    ), array( '%d', '%s', '%d' ) );
                }
            }

            wp_send_json_success( array( 'message' => __( 'Plan updated.', 'businessvance-services-manager' ), 'id' => $id ) );
        } else {
            // Get max display_order
            $max_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order), 0) FROM {$tables['plans']}" );
            $data['display_order'] = $max_order + 1;
            $format[] = '%d';

            $wpdb->insert( $tables['plans'], $data, $format );
            $new_id = $wpdb->insert_id;

            // Insert features
            foreach ( $features as $i => $feature_text ) {
                if ( ! empty( $feature_text ) ) {
                    $wpdb->insert( $tables['features'], array(
                        'plan_id'       => $new_id,
                        'feature_text'  => $feature_text,
                        'display_order' => $i,
                    ), array( '%d', '%s', '%d' ) );
                }
            }

            wp_send_json_success( array( 'message' => __( 'Plan created.', 'businessvance-services-manager' ), 'id' => $new_id ) );
        }
    }

    /**
     * Delete plan
     */
    public function ajax_delete_plan() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        // Delete features first (cascade)
        $wpdb->delete( $tables['features'], array( 'plan_id' => $id ), array( '%d' ) );
        $wpdb->delete( $tables['plans'], array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success( array( 'message' => __( 'Plan deleted.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Reorder plans
     */
    public function ajax_reorder_plans() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $order = $_POST['order'] ?? array();
        if ( ! is_array( $order ) ) {
            wp_send_json_error();
        }

        foreach ( $order as $position => $id ) {
            $wpdb->update(
                $tables['plans'],
                array( 'display_order' => intval( $position ) ),
                array( 'id' => intval( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success( array( 'message' => __( 'Order saved.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Save (create/update) category
     */
    public function ajax_save_category() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        $slug = sanitize_text_field( $_POST['slug'] ?? '' );
        $color = sanitize_text_field( $_POST['color'] ?? '#008080' );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Category name is required.', 'businessvance-services-manager' ) ) );
        }

        if ( empty( $slug ) ) {
            $slug = sanitize_title( $name );
        }

        $data = array(
            'name'  => $name,
            'slug'  => $slug,
            'color' => $color,
        );
        $format = array( '%s', '%s', '%s' );

        if ( $id > 0 ) {
            $wpdb->update( $tables['categories'], $data, array( 'id' => $id ), $format, array( '%d' ) );
            wp_send_json_success( array( 'message' => __( 'Category updated.', 'businessvance-services-manager' ), 'id' => $id ) );
        } else {
            $wpdb->insert( $tables['categories'], $data, $format );
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array( 'message' => __( 'Category created.', 'businessvance-services-manager' ), 'id' => $new_id ) );
        }
    }

    /**
     * Delete category
     */
    public function ajax_delete_category() {
        $this->verify_nonce();
        global $wpdb;
        $tables = $this->get_tables();

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        // Remove category reference from services and plans
        $wpdb->update( $tables['services'], array( 'category_id' => 0 ), array( 'category_id' => $id ), array( '%d' ), array( '%d' ) );
        $wpdb->update( $tables['plans'], array( 'category_id' => 0 ), array( 'category_id' => $id ), array( '%d' ), array( '%d' ) );
        $wpdb->delete( $tables['categories'], array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => __( 'Category deleted.', 'businessvance-services-manager' ) ) );
    }
}