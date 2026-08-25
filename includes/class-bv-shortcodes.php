<?php
/**
 * Shortcodes for BusinessVance Services Manager
 *
 * Provides [businessvance_services], [businessvance_onceoff], [businessvance_subscriptions],
 * [bv_login_page]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Shortcodes {

    /** @var array Icon SVG paths (Lucide-style) */
    private $icon_paths = array();

    public function __construct() {
        $this->load_icon_paths();

        add_shortcode( 'businessvance_services', array( $this, 'render_full_page' ) );
        add_shortcode( 'businessvance_onceoff', array( $this, 'render_services_section' ) );
        add_shortcode( 'businessvance_subscriptions', array( $this, 'render_plans_section' ) );
        add_shortcode( 'bv_login_page', array( $this, 'render_login_page' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // Role-based login redirect (priority 100 — consultant filter at 99999 overrides for consultants)
        add_filter( 'woocommerce_login_redirect', array( $this, 'bv_login_redirect' ), 100, 2 );
        add_filter( 'login_redirect', array( $this, 'bv_login_redirect' ), 100, 2 );
    }

    /**
     * Load SVG icon paths
     */
    private function load_icon_paths() {
        if ( class_exists( 'BV_Icon_Manager' ) ) {
            // Attempt to use centralized icon data from Icon Manager
            $admin_icons = BV_Icon_Manager::get_all_icons_for_picker();
            if ( ! empty( $admin_icons ) ) {
                $this->icon_paths = array();
                foreach ( $admin_icons as $icon ) {
                    $this->icon_paths[ $icon['name'] ] = $icon['svg_inner'];
                }
                return;
            }
        }

        // Fallback: hardcoded Lucide SVG paths
        $this->icon_paths = array(
            'briefcase'    => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
            'building'     => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
            'building-2'   => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/>',
            'landmark'     => '<line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/>',
            'chart-bar'    => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
            'chart-line'   => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
            'trending-up'  => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'trending-down'=> '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
            'bar-chart-3'  => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
            'pie-chart'    => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
            'calculator'   => '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8" y1="10" y2="10.01"/><line x1="12" x2="12" y1="10" y2="10.01"/><line x1="16" x2="16" y1="10" y2="10.01"/><line x1="8" x2="8" y1="14" y2="14.01"/><line x1="12" x2="12" y1="14" y2="14.01"/><line x1="8" x2="8" y1="18" y2="18.01"/><line x1="12" x2="12" y1="18" y2="18.01"/>',
            'file-text'    => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
            'file-check'   => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
            'file-plus'    => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="M9 15h6"/>',
            'file-search'  => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><circle cx="11" cy="14" r="3"/><path d="m14 16 2 2"/>',
            'folder'       => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
            'award'        => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
            'star'         => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'shield'       => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
            'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
            'users'        => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user-plus'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
            'user-check'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
            'handshake'    => '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
            'globe'        => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
            'palette'      => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
            'pen-tool'     => '<path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/>',
            'layers'       => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
            'layout'       => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="9" x2="9" y1="21" y2="9"/>',
            'code'         => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
            'mail'         => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
            'phone'        => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'message-circle'=> '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
            'share-2'      => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/>',
            'megaphone'    => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
            'search'       => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'settings'     => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
            'sliders'      => '<line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/><line x1="2" x2="6" y1="14" y2="14"/><line x1="10" x2="14" y1="8" y2="8"/><line x1="18" x2="22" y1="16" y2="16"/>',
            'target'       => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
            'zap'          => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
            'heart'        => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
            'book-open'    => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
            'graduation-cap'=> '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
            'lightbulb'    => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
            'rocket'       => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
            'receipt'      => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
            'credit-card'  => '<rect width="22" height="16" x="1" y="4" rx="2"/><line x1="1" x2="23" y1="10" y2="10"/>',
            'banknote'     => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
            'wallet'       => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
            'piggy-bank'   => '<path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 0 3 2 4.5V20h4v-2h3v2h4v-4c1-.5 1.7-1 2-2h2v-4h-2c0-1-.5-1.5-1-2"/><path d="M2 9v1c0 1.1.9 2 2 2h1"/><circle cx="16" cy="11" r="1"/>',
            'clock'        => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'crown'        => '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>',
            'lock'         => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'calendar'     => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
            'map-pin'      => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'truck'        => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
            'package'      => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
            'clipboard-list'=> '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
            'search'       => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'trending-up'  => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'bar-chart'    => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
            'users'        => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'megaphone'    => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
            'activity'     => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
            'shield-alert' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
            'lock'         => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'file-text'    => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
            'wrench'       => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
            'heart-pulse'  => '<path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"/><path d="M12 6l-2 4h4l-2 4"/>',
            'presentation' => '<path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/>',
        );
    }

    /**
     * Enqueue frontend CSS
     */
    public function enqueue_frontend_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) &&
             ( has_shortcode( $post->post_content, 'businessvance_services' ) ||
               has_shortcode( $post->post_content, 'businessvance_onceoff' ) ||
               has_shortcode( $post->post_content, 'businessvance_subscriptions' ) )
        ) {
            wp_enqueue_style(
                'bv-frontend-css',
                BV_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                BV_VERSION
            );

            // Dynamic CSS from settings
            $settings = BV_Settings::get_settings();

            // Calculate border-radius from shape setting
            $logo_shape  = isset( $settings['services_logo_shape'] ) ? $settings['services_logo_shape'] : 'none';
            $logo_radius = intval( $settings['services_logo_border_radius'] );
            if ( $logo_shape === 'circle' ) {
                $logo_radius = 9999;
            } elseif ( $logo_shape === 'square' || $logo_shape === 'none' ) {
                $logo_radius = 0;
            }

            $dynamic_css = ':root{--bv-primary:' . esc_attr( $settings['primary_color'] ) . ';--bv-secondary:' . esc_attr( $settings['secondary_color'] ) . ';--bv-accent:' . esc_attr( $settings['accent_color'] ) . ';--bv-logo-height:' . intval( $settings['services_logo_height'] ) . 'px;--bv-logo-max-width:' . intval( $settings['services_logo_max_width'] ) . 'px;--bv-logo-radius:' . $logo_radius . 'px;}';
            if ( $settings['services_enable_animations'] !== 'yes' ) {
                $dynamic_css .= '.bv-animate{opacity:1!important;transform:none!important;}';
            }
            if ( $settings['services_layout_style'] === 'cards' ) {
                $dynamic_css .= '.bv-desktop-only{display:none!important;}.bv-mobile-only{display:block!important;}';
            }
            wp_add_inline_style( 'bv-frontend-css', $dynamic_css );
        }
    }

    /**
     * Get icon SVG markup — delegates to BV_Icon_Manager if available,
     * otherwise falls back to the local hardcoded paths.
     */
    private function get_icon_svg( $icon_name, $size = 24 ) {
        if ( class_exists( 'BV_Icon_Manager' ) ) {
            return BV_Icon_Manager::get_icon_svg( $icon_name, $size );
        }
        if ( isset( $this->icon_paths[ $icon_name ] ) ) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $this->icon_paths[ $icon_name ] . '</svg>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2"/></svg>';
    }

    /**
     * Get WooCommerce add-to-cart URL
     */
    private function get_woo_url( $product_id ) {
        if ( $product_id && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                return esc_url( $product->add_to_cart_url() );
            }
        }
        return '#';
    }

    /**
     * Render the full services page
     */
    public function render_full_page( $atts ) {
        $atts = shortcode_atts( array(
            'category' => '',
        ), $atts, 'businessvance_services' );
        $settings = BV_Settings::get_settings();

        ob_start();
        echo $this->render_header();

        // Section header for services
        if ( $settings['services_show_header'] === 'yes' ) {
            echo '<div class="bv-services-section"><div class="bv-section-header">';
            echo '<div class="bv-section-banner">' . esc_html( $settings['services_page_title'] ) . '</div>';
            echo '</div></div>';
        }

        echo $this->render_services_section( $atts );

        if ( $settings['services_show_plans'] === 'yes' ) {
            echo $this->render_plans_section( $atts );
        }

        echo $this->render_footer();
        return ob_get_clean();
    }

    /**
     * Render page header
     */
    private function render_header() {
        $settings = BV_Settings::get_settings();

        // Check if header should be shown
        if ( $settings['services_show_header'] !== 'yes' ) {
            return '<div class="bv-page-wrapper">';
        }

        $company_name    = esc_html( $settings['company_name'] );
        $company_tagline = esc_html( strtoupper( $settings['company_tagline'] ) );
        $page_subtitle   = esc_html( $settings['services_page_subtitle'] );
        $header_style    = $settings['services_header_style'];
        $logo_url        = esc_url( $settings['logo_url'] );

        ob_start();
        ?>
        <div class="bv-page-wrapper">
            <header class="bv-header bv-header-<?php echo esc_attr( $header_style ); ?>">
                <div class="bv-header-inner">
                    <?php if ( $header_style === 'navy' ) : ?>
                    <div class="bv-header-stars">
                        <?php echo $this->get_icon_svg( 'star', 18 ); ?>
                        <?php echo $this->get_icon_svg( 'star', 18 ); ?>
                        <?php echo $this->get_icon_svg( 'star', 18 ); ?>
                    </div>
                    <?php endif; ?>
                    <div class="bv-header-brand">
                        <div class="bv-shield-logo">
                            <?php if ( $logo_url ) : ?>
                                <img src="<?php echo $logo_url; ?>" alt="<?php echo $company_name; ?>">
                            <?php else : ?>
                                <?php echo $this->get_icon_svg( 'shield', 36 ); ?>
                            <?php endif; ?>
                        </div>
                        <div class="bv-brand-text">
                            <h1 class="bv-brand-name"><?php echo $company_name; ?></h1>
                            <p class="bv-brand-tagline"><?php echo $company_tagline; ?></p>
                        </div>
                    </div>
                    <?php if ( $page_subtitle ) : ?>
                    <p class="bv-brand-description"><?php echo $page_subtitle; ?></p>
                    <?php endif; ?>
                </div>
            </header>
        <?php
        return ob_get_clean();
    }

    /**
     * Render page footer
     */
    private function render_footer() {
        $settings = BV_Settings::get_settings();
        $company_name    = esc_html( $settings['company_name'] );
        $phone           = esc_html( $settings['phone_number'] );
        $email           = esc_html( $settings['email_address'] );
        $address         = esc_html( $settings['physical_address'] );
        $website         = home_url( '/' );
        $show_badges     = $settings['services_show_trust_badges'] === 'yes';
        $custom_footer   = $settings['services_footer_text'];

        ob_start();
        ?>
            <footer class="bv-footer">
                <div class="bv-footer-inner">
                    <?php if ( $show_badges ) : ?>
                    <div class="bv-trust-badges">
                        <div class="bv-trust-badge">
                            <?php echo $this->get_icon_svg( 'shield-check', 20 ); ?>
                            <span><?php esc_html_e( 'PROFESSIONAL QUALITY REPORTS', 'businessvance-services-manager' ); ?></span>
                        </div>
                        <div class="bv-trust-badge">
                            <?php echo $this->get_icon_svg( 'clock', 20 ); ?>
                            <span><?php esc_html_e( 'FAST TURNAROUND', 'businessvance-services-manager' ); ?></span>
                        </div>
                        <div class="bv-trust-badge">
                            <?php echo $this->get_icon_svg( 'lock', 20 ); ?>
                            <span><?php esc_html_e( '100% CONFIDENTIAL & SECURE', 'businessvance-services-manager' ); ?></span>
                        </div>
                        <div class="bv-trust-badge">
                            <?php echo $this->get_icon_svg( 'star', 20 ); ?>
                            <span><?php esc_html_e( 'ACTIONABLE RECOMMENDATIONS', 'businessvance-services-manager' ); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="bv-footer-contact">
                        <?php if ( $custom_footer ) : ?>
                            <p><?php echo wp_kses_post( nl2br( $custom_footer ) ); ?></p>
                        <?php else : ?>
                            <p><strong><?php echo $company_name; ?></strong></p>
                            <p>
                                <?php echo esc_html( $website ); ?>
                                <?php if ( $phone ) : ?>&nbsp;|&nbsp; <?php echo $phone; ?><?php endif; ?>
                                <?php if ( $email ) : ?>&nbsp;|&nbsp; <?php echo $email; ?><?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="bv-footer-copyright">
                        <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo $company_name; ?>. All rights reserved.</p>
                    </div>
                </div>
            </footer>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render once-off services section
     */
    public function render_services_section( $atts ) {
        global $wpdb;
        $atts = is_array( $atts ) ? $atts : array();
        $tables = array(
            'services'   => $wpdb->prefix . 'bv_services',
            'categories' => $wpdb->prefix . 'bv_categories',
        );

        $where = "s.is_visible = 1";
        $category_slug = ! empty( $atts['category'] ) ? sanitize_text_field( $atts['category'] ) : '';
        if ( $category_slug ) {
            $cat_slugs   = array_map( 'trim', explode( ',', $category_slug ) );
            $cat_slugs   = array_map( 'sanitize_title', $cat_slugs );
            $cat_placeholders = implode( ',', array_fill( 0, count( $cat_slugs ), '%s' ) );
            $where .= $wpdb->prepare( " AND c.slug IN ({$cat_placeholders})", ...$cat_slugs );
        }

        $services = $wpdb->get_results(
            "SELECT s.*, c.name as category_name, c.color as category_color
             FROM {$tables['services']} s
             LEFT JOIN {$tables['categories']} c ON s.category_id = c.id
             WHERE {$where}
             ORDER BY s.display_order ASC, s.id ASC"
        );

        if ( empty( $services ) ) {
            return '';
        }

        ob_start();
        ?>
        <?php $svc_settings = BV_Settings::get_settings(); ?>
        <section class="bv-services-section">
            <div class="bv-section-header">
                <div class="bv-section-banner"><?php echo esc_html( $svc_settings['services_services_title'] ); ?></div>
                <div class="bv-section-sub"><?php echo esc_html( $svc_settings['services_services_subtitle'] ); ?></div>
            </div>

            <!-- Desktop Table -->
            <div class="bv-table-wrapper bv-desktop-only">
                <table class="bv-services-table">
                    <thead>
                        <tr>
                            <th style="width:28%;">SERVICE</th>
                            <th style="width:47%;">DESCRIPTION</th>
                            <th class="bv-col-price" style="width:25%;"><?php echo esc_html( $svc_settings['services_table_header_label'] ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $services as $service ) : ?>
                            <tr>
                                <td>
                                    <div class="bv-service-info">
                                        <div class="bv-service-icon">
                                            <?php echo $this->get_icon_svg( $service->icon, 20 ); ?>
                                        </div>
                                        <div>
                                            <div class="bv-service-name">
                                                <?php echo esc_html( $service->name ); ?>
                                                <?php if ( $service->is_featured ) : ?>
                                                    <span class="bv-featured-badge">★ Popular</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <p class="bv-service-desc"><?php echo esc_html( $service->description ); ?></p>
                                </td>
                                <td>
                                    <div class="bv-price-block">
                                        <span class="bv-service-price">
                                            <?php echo esc_html( ! empty( $service->price_display ) ? $service->price_display : $service->price ); ?>
                                        </span>
                                        <a href="<?php echo $this->get_service_url( $service ); ?>" class="bv-btn bv-btn-<?php echo esc_attr( $svc_settings['services_button_color'] ); ?> bv-btn-sm">
                                            <?php echo esc_html( $svc_settings['services_button_text'] ); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="bv-mobile-cards bv-mobile-only">
                <?php foreach ( $services as $service ) : ?>
                    <div class="bv-service-card">
                        <div class="bv-card-header">
                            <div class="bv-card-icon">
                                <?php echo $this->get_icon_svg( $service->icon, 20 ); ?>
                            </div>
                            <div>
                                <h3 class="bv-card-name">
                                    <?php echo esc_html( $service->name ); ?>
                                    <?php if ( $service->is_featured ) : ?>
                                        <span class="bv-featured-badge">★ Popular</span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                        </div>
                        <p class="bv-card-desc"><?php echo esc_html( $service->description ); ?></p>
                        <div class="bv-card-footer">
                            <span class="bv-card-price">
                                <?php echo esc_html( ! empty( $service->price_display ) ? $service->price_display : $service->price ); ?>
                            </span>
                            <a href="<?php echo $this->get_service_url( $service ); ?>" class="bv-btn bv-btn-<?php echo esc_attr( $svc_settings['services_button_color'] ); ?> bv-btn-sm">
                                <?php echo esc_html( $svc_settings['services_button_text'] ); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Get the correct URL for a service based on its type
     */
    private function get_service_url( $service ) {
        if ( $service->woo_product_id ) {
            return $this->get_woo_url( $service->woo_product_id );
        }
        if ( $service->service_type === 'quote' ) {
            return home_url( '/contact/' );
        }
        if ( $service->service_type === 'booking' ) {
            return home_url( '/booking/' );
        }
        return '#';
    }

    /**
     * Render subscription plans section
     */
    public function render_plans_section( $atts ) {
        global $wpdb;
        $atts = is_array( $atts ) ? $atts : array();
        $tables = array(
            'plans'      => $wpdb->prefix . 'bv_plans',
            'features'   => $wpdb->prefix . 'bv_plan_features',
            'categories' => $wpdb->prefix . 'bv_categories',
        );

        $where = "p.is_visible = 1";
        $category_slug = ! empty( $atts['category'] ) ? sanitize_text_field( $atts['category'] ) : '';
        if ( $category_slug ) {
            $cat_slugs   = array_map( 'trim', explode( ',', $category_slug ) );
            $cat_slugs   = array_map( 'sanitize_title', $cat_slugs );
            $cat_placeholders = implode( ',', array_fill( 0, count( $cat_slugs ), '%s' ) );
            $where .= $wpdb->prepare( " AND c.slug IN ({$cat_placeholders})", ...$cat_slugs );
        }

        $plans = $wpdb->get_results(
            "SELECT p.* FROM {$tables['plans']} p
             LEFT JOIN {$tables['categories']} c ON p.category_id = c.id
             WHERE {$where}
             ORDER BY p.display_order ASC, p.id ASC"
        );

        if ( empty( $plans ) ) {
            return '';
        }

        // Batch-load all plan features in a single query
        $plan_ids = array_map( 'intval', wp_list_pluck( $plans, 'id' ) );
        $all_features = array();
        if ( ! empty( $plan_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $plan_ids ), '%d' ) );
            $raw_features = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['features']} WHERE plan_id IN ({$placeholders}) ORDER BY display_order ASC",
                    ...$plan_ids
                )
            );
            foreach ( $raw_features as $f ) {
                $all_features[ $f->plan_id ][] = $f;
            }
        }

        foreach ( $plans as $plan ) {
            $plan->features = isset( $all_features[ $plan->id ] ) ? $all_features[ $plan->id ] : array();
        }

        // Button style classes per plan color
        $btn_styles = array(
            '#2A9D8F' => 'bv-btn-teal',
            '#264653' => 'bv-btn-navy',
            '#F4A261' => 'bv-btn-gold',
            '#0A2647' => 'bv-btn-navy',
        );

        ob_start();
        ?>
        <?php $plan_settings = BV_Settings::get_settings(); ?>
        <section class="bv-plans-section">
            <div class="bv-section-header">
                <div class="bv-section-banner">
                    <?php echo $this->get_icon_svg( 'crown', 18 ); ?>
                    &nbsp; <?php echo esc_html( $plan_settings['services_plans_title'] ); ?>
                </div>
                <div class="bv-section-sub-alt"><?php echo esc_html( $plan_settings['services_plans_subtitle'] ); ?></div>
            </div>

            <div class="bv-plans-grid">
                <?php foreach ( $plans as $plan ) :
                    $btn_class = isset( $btn_styles[ $plan->color ] ) ? $btn_styles[ $plan->color ] : 'bv-btn-gold';
                ?>
                    <div class="bv-plan-card <?php echo $plan->is_featured ? 'bv-plan-featured' : ''; ?>" <?php if ( $plan->is_featured ) echo 'style="border-color:' . esc_attr( $plan->color ) . ';"'; ?>>
                        <?php if ( $plan->is_featured ) : ?>
                            <div class="bv-plan-popular" style="background-color: <?php echo esc_attr( $plan->color ); ?>;">
<?php esc_html_e( 'MOST POPULAR', 'businessvance-services-manager' ); ?>
                            </div>
                        <?php endif; ?>

                        <div class="bv-plan-header" style="background-color: <?php echo esc_attr( $plan->color ); ?>;">
                            <h3 class="bv-plan-name"><?php echo esc_html( $plan->name ); ?></h3>
                            <?php if ( $plan->subtitle ) : ?>
                                <p class="bv-plan-subtitle"><?php echo esc_html( $plan->subtitle ); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="bv-plan-price-wrap">
                            <span class="bv-plan-price"><?php echo esc_html( $plan->price ); ?></span>
                        </div>

                        <div class="bv-plan-features">
                            <?php if ( ! empty( $plan->features ) ) : ?>
                                <ul>
                                    <?php foreach ( $plan->features as $feature ) : ?>
                                        <li>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            <?php echo esc_html( $feature->feature_text ); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <div class="bv-plan-action">
                            <?php if ( $plan->woo_product_id ) : ?>
                                <a href="<?php echo $this->get_woo_url( $plan->woo_product_id ); ?>" class="bv-btn <?php echo esc_attr( $btn_class ); ?> bv-btn-block">
                                    <?php echo esc_html( $plan->button_label ); ?>
                                </a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="bv-btn <?php echo esc_attr( $btn_class ); ?> bv-btn-block">
                                    <?php echo esc_html( $plan->button_label ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Branded login page shortcode [bv_login_page]
     *
     * Two-step flow:
     *  1. Not logged in → shows a clean WooCommerce login form
     *  2. After login → shows a destination picker with two cards:
     *     - Client Portal (URL from admin settings: portal_url)
     *     - LMS Dashboard (URL from admin settings: tutor_dashboard_url)
     *
     * Consultant Dashboard is completely separate (wp-admin) and NOT shown here.
     *
     * @since 2.7.61
     * @return string HTML output
     */
    public function render_login_page( $atts ) {
        $atts = shortcode_atts( array(
            'title'        => '',
            'subtitle'     => '',
            'register_url' => '',
        ), $atts, 'bv_login_page' );

        $settings        = BV_Settings::get_settings();
        $primary_color   = $settings['primary_color'] ?? '#002B5C';
        $secondary_color = $settings['secondary_color'] ?? '#008080';
        $logo_url        = $settings['logo_url'] ?? '';
        $register_url    = $atts['register_url'];

        // Auto-detect registration URL (WooCommerce My Account)
        if ( empty( $register_url ) && function_exists( 'wc_get_page_permalink' ) ) {
            $my_account = wc_get_page_permalink( 'myaccount' );
            if ( $my_account ) {
                $register_url = $my_account;
            }
        }

        // Resolve portal URL
        $portal_url = '';
        if ( ! empty( $settings['portal_url'] ) ) {
            $portal_url = $settings['portal_url'];
        } else {
            $portal_page = get_posts( array(
                'post_type'      => 'page',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                's'              => '[businessvance_client_portal]',
            ) );
            if ( ! empty( $portal_page ) ) {
                $portal_url = get_permalink( $portal_page[0]->ID );
            }
        }

        // Resolve TutorLMS dashboard URL
        $tutor_url = '';
        if ( ! empty( $settings['tutor_dashboard_url'] ) ) {
            $tutor_url = $settings['tutor_dashboard_url'];
        } elseif ( function_exists( 'tutor_utils' ) ) {
            $tutor_dashboard_page_id = tutor_utils()->get_tutor_dashboard_page_id();
            if ( $tutor_dashboard_page_id ) {
                $tutor_url = get_permalink( $tutor_dashboard_page_id );
            }
        }

        // ---- Already logged in? Show destination picker ----
        if ( is_user_logged_in() ) {
            $user    = wp_get_current_user();
            $title   = $atts['title'] ?: sprintf( esc_html__( 'Welcome back, %s', 'businessvance-services-manager' ), $user->first_name ?: $user->display_name );
            $current_page = $_SERVER['REQUEST_URI'] ?? '';

            ob_start();
            ?>
            <style>
                .bv-pick-wrap {
                    max-width: 620px;
                    margin: 40px auto;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }
                .bv-pick-card {
                    background: #fff;
                    border-radius: 16px;
                    box-shadow: 0 4px 24px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
                    overflow: hidden;
                }
                .bv-pick-header {
                    background: linear-gradient(135deg, <?php echo esc_attr( $primary_color ); ?> 0%, <?php echo esc_attr( $secondary_color ); ?> 100%);
                    padding: 32px 28px 28px;
                    text-align: center;
                }
                .bv-pick-logo {
                    max-height: 60px;
                    max-width: 200px;
                    margin-bottom: 16px;
                    border-radius: 4px;
                }
                .bv-pick-header h2 {
                    margin: 0;
                    font-size: 22px;
                    font-weight: 700;
                    color: #fff;
                }
                .bv-pick-header p {
                    margin: 8px 0 0;
                    font-size: 14px;
                    color: rgba(255,255,255,0.85);
                }
                .bv-pick-destinations {
                    display: flex;
                    gap: 16px;
                    padding: 24px;
                }
                .bv-pick-dest {
                    flex: 1;
                    border: 2px solid #e5e7eb;
                    border-radius: 12px;
                    padding: 24px 20px;
                    text-align: center;
                    text-decoration: none;
                    transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
                    display: block;
                }
                .bv-pick-dest:hover {
                    border-color: <?php echo esc_attr( $primary_color ); ?>;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    transform: translateY(-2px);
                }
                .bv-pick-dest-icon {
                    width: 56px;
                    height: 56px;
                    border-radius: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 14px;
                }
                .bv-pick-dest.bv-pick-portal .bv-pick-dest-icon {
                    background: <?php echo esc_attr( $primary_color ); ?>12;
                    color: <?php echo esc_attr( $primary_color ); ?>;
                }
                .bv-pick-dest.bv-pick-lms .bv-pick-dest-icon {
                    background: #f59e0b12;
                    color: #b45309;
                }
                .bv-pick-dest h3 {
                    margin: 0 0 8px;
                    font-size: 16px;
                    font-weight: 700;
                }
                .bv-pick-dest p {
                    margin: 0 0 16px;
                    font-size: 13px;
                    color: #6b7280;
                    line-height: 1.5;
                }
                .bv-pick-dest.bv-pick-portal h3 { color: <?php echo esc_attr( $primary_color ); ?>; }
                .bv-pick-dest.bv-pick-lms h3 { color: #b45309; }
                .bv-pick-dest .bv-pick-arrow {
                    font-size: 13px;
                    font-weight: 600;
                }
                .bv-pick-dest.bv-pick-portal .bv-pick-arrow { color: <?php echo esc_attr( $primary_color ); ?>; }
                .bv-pick-dest.bv-pick-lms .bv-pick-arrow { color: #b45309; }
                .bv-pick-footer {
                    text-align: center;
                    padding: 0 24px 24px;
                }
                .bv-pick-footer a {
                    color: #6b7280;
                    text-decoration: none;
                    font-size: 13px;
                }
                .bv-pick-footer a:hover {
                    color: <?php echo esc_attr( $primary_color ); ?>;
                }
                @media (max-width: 500px) {
                    .bv-pick-destinations { flex-direction: column; gap: 12px; }
                }
            </style>

            <div class="bv-pick-wrap">
                <div class="bv-pick-card">
                    <div class="bv-pick-header">
                        <?php if ( $logo_url ) : ?>
                            <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="bv-pick-logo" />
                        <?php endif; ?>
                        <h2><?php echo esc_html( $title ); ?></h2>
                        <p><?php esc_html_e( 'Where would you like to go?', 'businessvance-services-manager' ); ?></p>
                    </div>

                    <div class="bv-pick-destinations">
                        <?php if ( $portal_url ) : ?>
                        <a href="<?php echo esc_url( $portal_url ); ?>" class="bv-pick-dest bv-pick-portal">
                            <div class="bv-pick-dest-icon">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <h3><?php esc_html_e( 'Client Portal', 'businessvance-services-manager' ); ?></h3>
                            <p><?php esc_html_e( 'View projects, upload documents, and track your service progress.', 'businessvance-services-manager' ); ?></p>
                            <span class="bv-pick-arrow"><?php esc_html_e( 'Open Portal →', 'businessvance-services-manager' ); ?></span>
                        </a>
                        <?php endif; ?>

                        <?php if ( $tutor_url ) : ?>
                        <a href="<?php echo esc_url( $tutor_url ); ?>" class="bv-pick-dest bv-pick-lms">
                            <div class="bv-pick-dest-icon">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            </div>
                            <h3><?php esc_html_e( 'LMS Dashboard', 'businessvance-services-manager' ); ?></h3>
                            <p><?php esc_html_e( 'Access your courses, lessons, quizzes, and learning materials.', 'businessvance-services-manager' ); ?></p>
                            <span class="bv-pick-arrow"><?php esc_html_e( 'Open Dashboard →', 'businessvance-services-manager' ); ?></span>
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="bv-pick-footer">
                        <?php $logout_url = wp_logout_url( $current_page ); ?>
                        <a href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Not you? Log out', 'businessvance-services-manager' ); ?></a>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        // ---- Not logged in: show login form ----
        $title    = $atts['title'] ?: esc_html__( 'Sign In', 'businessvance-services-manager' );
        $subtitle = $atts['subtitle'] ?: esc_html__( 'Access your client portal and courses.', 'businessvance-services-manager' );

        // Login error handling
        $error_message = '';
        if ( isset( $_GET['bv_login'] ) && $_GET['bv_login'] === 'failed' ) {
            $error_message = esc_html__( 'Invalid username or password. Please try again.', 'businessvance-services-manager' );
        }

        ob_start();
        ?>
        <style>
            .bv-login-wrap {
                max-width: 440px;
                margin: 40px auto;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .bv-login-card {
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 4px 24px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
                overflow: hidden;
            }
            .bv-login-header {
                background: linear-gradient(135deg, <?php echo esc_attr( $primary_color ); ?> 0%, <?php echo esc_attr( $secondary_color ); ?> 100%);
                padding: 32px 28px 28px;
                text-align: center;
            }
            .bv-login-logo {
                max-height: 60px;
                max-width: 200px;
                margin-bottom: 16px;
                border-radius: 4px;
            }
            .bv-login-header h2 {
                margin: 0;
                font-size: 22px;
                font-weight: 700;
                color: #fff;
            }
            .bv-login-header p {
                margin: 8px 0 0;
                font-size: 14px;
                color: rgba(255,255,255,0.85);
            }
            .bv-login-body {
                padding: 32px 28px 8px;
            }
            .bv-login-form .bv-login-field {
                margin-bottom: 20px;
            }
            .bv-login-form .bv-login-field label {
                display: block;
                margin-bottom: 6px;
                font-size: 13px;
                font-weight: 600;
                color: #374151;
            }
            .bv-login-form .bv-login-field input {
                width: 100%;
                padding: 12px 14px;
                border: 1.5px solid #d1d5db;
                border-radius: 8px;
                font-size: 15px;
                transition: border-color 0.2s, box-shadow 0.2s;
                box-sizing: border-box;
                outline: none;
            }
            .bv-login-form .bv-login-field input:focus {
                border-color: <?php echo esc_attr( $primary_color ); ?>;
                box-shadow: 0 0 0 3px rgba(0,43,92,0.1);
            }
            .bv-login-remember {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 24px;
                font-size: 13px;
                color: #6b7280;
            }
            .bv-login-remember input[type="checkbox"] {
                width: 16px;
                height: 16px;
                accent-color: <?php echo esc_attr( $primary_color ); ?>;
            }
            .bv-login-submit {
                width: 100%;
                padding: 13px;
                background: linear-gradient(135deg, <?php echo esc_attr( $primary_color ); ?> 0%, <?php echo esc_attr( $secondary_color ); ?> 100%);
                color: #fff;
                border: none;
                border-radius: 8px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: opacity 0.2s, transform 0.1s;
            }
            .bv-login-submit:hover {
                opacity: 0.9;
            }
            .bv-login-submit:active {
                transform: scale(0.98);
            }
            .bv-login-footer {
                text-align: center;
                padding: 0 28px 28px;
            }
            .bv-login-footer a {
                color: <?php echo esc_attr( $primary_color ); ?>;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
            }
            .bv-login-footer a:hover {
                text-decoration: underline;
            }
            .bv-login-footer .bv-login-separator {
                color: #d1d5db;
                margin: 0 12px;
            }
            .bv-login-error {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #dc2626;
                padding: 12px 16px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
        </style>

        <div class="bv-login-wrap">
            <div class="bv-login-card">
                <div class="bv-login-header">
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="bv-login-logo" />
                    <?php endif; ?>
                    <h2><?php echo esc_html( $title ); ?></h2>
                    <p><?php echo esc_html( $subtitle ); ?></p>
                </div>

                <div class="bv-login-body">
                    <?php if ( $error_message ) : ?>
                        <div class="bv-login-error">
                            <span>&#9888;</span> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="bv-login-form" novalidate>
                        <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>

                        <div class="bv-login-field">
                            <label for="bv_username"><?php esc_html_e( 'Username or email', 'businessvance-services-manager' ); ?></label>
                            <input type="text" name="username" id="bv_username" autocomplete="username" required />
                        </div>

                        <div class="bv-login-field">
                            <label for="bv_password"><?php esc_html_e( 'Password', 'businessvance-services-manager' ); ?></label>
                            <input type="password" name="password" id="bv_password" autocomplete="current-password" required />
                        </div>

                        <div class="bv-login-remember">
                            <input type="checkbox" name="rememberme" id="bv_rememberme" value="forever" />
                            <label for="bv_rememberme"><?php esc_html_e( 'Remember me', 'businessvance-services-manager' ); ?></label>
                        </div>

                        <button type="submit" name="login" value="<?php esc_attr_e( 'Login', 'businessvance-services-manager' ); ?>" class="bv-login-submit">
                            <?php esc_html_e( 'Sign In', 'businessvance-services-manager' ); ?>
                        </button>

                        <input type="hidden" name="redirect_to" value="<?php echo esc_url( $_SERVER['REQUEST_URI'] ?? '' ); ?>" />
                        <input type="hidden" name="bv_login_source" value="1" />
                    </form>
                </div>

                <div class="bv-login-footer">
                    <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'businessvance-services-manager' ); ?></a>
                    <?php if ( $register_url ) : ?>
                        <span class="bv-login-separator">|</span>
                        <a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Create an account', 'businessvance-services-manager' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Login redirect handler.
     *
     * Runs at priority 100 on `woocommerce_login_redirect` and `login_redirect`.
     *
     * When the user logs in via the [bv_login_page] form (bv_login_source=1),
     * we redirect back to that same page. The page then detects the user is
     * logged in and shows the destination picker (Client Portal / LMS Dashboard).
     *
     * For logins from other sources (e.g. WooCommerce My Account), the default
     * WooCommerce redirect is passed through unchanged.
     *
     * Note: Consultant Dashboard is completely separate — consultants log in
     * via wp-admin, not through this page.
     *
     * @since 2.7.61
     * @param string   $redirect Default redirect URL.
     * @param WP_User  $user     Authenticated user object.
     * @return string  Redirect URL.
     */
    public function bv_login_redirect( $redirect, $user ) {
        if ( ! is_a( $user, 'WP_User' ) ) return $redirect;

        // If login came from our [bv_login_page] form, redirect back to that page
        // so the user sees the destination picker (Client Portal / LMS Dashboard).
        if ( ! empty( $_REQUEST['bv_login_source'] ) ) {
            return $redirect; // $redirect is already set to the login page via redirect_to
        }

        // For all other login sources (My Account, wp-login, etc.),
        // pass through to WooCommerce/WordPress default behavior.
        return $redirect;
    }
}