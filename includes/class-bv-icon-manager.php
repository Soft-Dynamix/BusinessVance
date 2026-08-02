<?php
/**
 * BusinessVance Services Manager - Icon Manager
 *
 * Manages custom SVG icons for the plugin. Provides an admin UI for uploading
 * or pasting SVG icons, and static helper methods for retrieving icons
 * (both built-in Lucide and custom) for use by other classes.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Icon_Manager {

    /**
     * Built-in Lucide icon names.
     *
     * @var array
     */
    private static $builtin_icon_names = array(
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
        'clipboard-list', 'crown', 'lock',
    );

    /**
     * Built-in Lucide SVG path data (inner SVG content for 24×24 viewBox).
     *
     * @var array
     */
    private static $builtin_svg_paths = array(
        'briefcase'      => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
        'building'       => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
        'building-2'     => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/>',
        'landmark'       => '<line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/>',
        'chart-bar'      => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
        'chart-line'     => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
        'trending-up'    => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'trending-down'  => '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
        'bar-chart-3'    => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'pie-chart'      => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'calculator'     => '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8" y1="10" y2="10.01"/><line x1="12" x2="12" y1="10" y2="10.01"/><line x1="16" x2="16" y1="10" y2="10.01"/><line x1="8" x2="8" y1="14" y2="14.01"/><line x1="12" x2="12" y1="14" y2="14.01"/><line x1="8" x2="8" y1="18" y2="18.01"/><line x1="12" x2="12" y1="18" y2="18.01"/>',
        'file-text'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'file-check'     => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
        'file-plus'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="M9 15h6"/>',
        'file-search'    => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><circle cx="11" cy="14" r="3"/><path d="m14 16 2 2"/>',
        'folder'         => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
        'award'          => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
        'star'           => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'shield'         => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'shield-check'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'check-circle'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
        'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-plus'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'user-check'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
        'handshake'      => '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
        'globe'          => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'palette'        => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'pen-tool'       => '<path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/>',
        'layers'         => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
        'layout'         => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="9" x2="9" y1="21" y2="9"/>',
        'code'           => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
        'mail'           => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'phone'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'share-2'        => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/>',
        'megaphone'      => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'settings'       => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'sliders'        => '<line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/><line x1="2" x2="6" y1="14" y2="14"/><line x1="10" x2="14" y1="8" y2="8"/><line x1="18" x2="22" y1="16" y2="16"/>',
        'target'         => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'zap'            => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'heart'          => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
        'book-open'      => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'graduation-cap' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
        'lightbulb'      => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
        'rocket'         => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        'receipt'        => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
        'credit-card'    => '<rect width="22" height="16" x="1" y="4" rx="2"/><line x1="1" x2="23" y1="10" y2="10"/>',
        'banknote'       => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'wallet'         => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        'piggy-bank'     => '<path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 0 3 2 4.5V20h4v-2h3v2h4v-4c1-.5 1.7-1 2-2h2v-4h-2c0-1-.5-1.5-1-2"/><path d="M2 9v1c0 1.1.9 2 2 2h1"/><circle cx="16" cy="11" r="1"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'crown'          => '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>',
        'lock'           => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'calendar'       => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'map-pin'        => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'truck'          => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
        'package'        => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'clipboard-list' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
    );

    /**
     * Allowed SVG elements and attributes for wp_kses sanitization.
     *
     * @var array
     */
    private static $svg_kses_allowed = array(
        'svg'       => array(
            'xmlns'       => true,
            'width'       => true,
            'height'      => true,
            'viewBox'     => true,
            'fill'        => true,
            'stroke'      => true,
            'stroke-width'=> true,
            'class'       => true,
            'style'       => true,
            'aria-hidden' => true,
            'role'        => true,
            'focusable'   => true,
        ),
        'path'      => array(
            'd'            => true,
            'fill'         => true,
            'stroke'       => true,
            'stroke-width' => true,
            'stroke-linecap'=> true,
            'stroke-linejoin'=> true,
            'class'        => true,
            'style'        => true,
            'transform'    => true,
            'opacity'      => true,
            'clip-path'    => true,
        ),
        'circle'    => array(
            'cx'           => true,
            'cy'           => true,
            'r'            => true,
            'fill'         => true,
            'stroke'       => true,
            'stroke-width' => true,
            'class'        => true,
            'style'        => true,
            'transform'    => true,
            'opacity'      => true,
        ),
        'rect'      => array(
            'x'            => true,
            'y'            => true,
            'width'        => true,
            'height'       => true,
            'rx'           => true,
            'ry'           => true,
            'fill'         => true,
            'stroke'       => true,
            'stroke-width' => true,
            'class'        => true,
            'style'        => true,
            'transform'    => true,
            'opacity'      => true,
        ),
        'polygon'   => array(
            'points'       => true,
            'fill'         => true,
            'stroke'       => true,
            'stroke-width' => true,
            'class'        => true,
            'style'        => true,
            'transform'    => true,
            'opacity'      => true,
        ),
        'polyline'  => array(
            'points'        => true,
            'fill'          => true,
            'stroke'        => true,
            'stroke-width'  => true,
            'stroke-linecap' => true,
            'stroke-linejoin'=> true,
            'class'         => true,
            'style'         => true,
            'transform'     => true,
            'opacity'       => true,
        ),
        'line'      => array(
            'x1'            => true,
            'y1'            => true,
            'x2'            => true,
            'y2'            => true,
            'stroke'        => true,
            'stroke-width'  => true,
            'stroke-linecap' => true,
            'stroke-linejoin'=> true,
            'class'         => true,
            'style'         => true,
            'transform'     => true,
            'opacity'       => true,
        ),
        'ellipse'    => array(
            'cx'           => true,
            'cy'           => true,
            'rx'           => true,
            'ry'           => true,
            'fill'         => true,
            'stroke'       => true,
            'stroke-width' => true,
            'class'        => true,
            'style'        => true,
            'transform'    => true,
            'opacity'      => true,
        ),
        'g'         => array(
            'fill'         => true,
            'stroke'       => true,
            'stroke-width' => true,
            'class'        => true,
            'style'        => true,
            'transform'    => true,
            'opacity'      => true,
            'id'           => true,
            'clip-path'    => true,
        ),
        'defs'      => array(
            'class' => true,
            'id'    => true,
        ),
        'use'       => array(
            'href'        => true,
            'xlink:href'  => true,
            'fill'        => true,
            'stroke'      => true,
            'stroke-width'=> true,
            'class'       => true,
            'style'       => true,
            'transform'   => true,
            'opacity'     => true,
        ),
        'clipPath'  => array(
            'id'    => true,
            'class' => true,
        ),
        'linearGradient' => array(
            'id'           => true,
            'x1'           => true,
            'y1'           => true,
            'x2'           => true,
            'y2'           => true,
            'gradientUnits'=> true,
        ),
        'radialGradient' => array(
            'id'           => true,
            'cx'           => true,
            'cy'           => true,
            'r'            => true,
            'fx'           => true,
            'fy'           => true,
            'gradientUnits'=> true,
        ),
        'stop'      => array(
            'offset'       => true,
            'stop-color'   => true,
            'stop-opacity' => true,
        ),
        'title'     => array(),
        'desc'      => array(),
        'mask'      => array(
            'id'    => true,
            'class' => true,
        ),
        'pattern'   => array(
            'id'                   => true,
            'x'                    => true,
            'y'                    => true,
            'width'                => true,
            'height'               => true,
            'patternUnits'         => true,
            'patternTransform'     => true,
        ),
        'animate'   => array(
            'attributeName' => true,
            'from'          => true,
            'to'            => true,
            'dur'           => true,
            'repeatCount'   => true,
            'values'        => true,
            'begin'         => true,
        ),
        'animateTransform' => array(
            'attributeName' => true,
            'type'          => true,
            'from'          => true,
            'to'            => true,
            'dur'           => true,
            'repeatCount'   => true,
            'values'        => true,
            'begin'         => true,
        ),
        'symbol'    => array(
            'id'      => true,
            'viewBox' => true,
            'class'   => true,
        ),
        'text'      => array(
            'x'              => true,
            'y'              => true,
            'fill'           => true,
            'stroke'         => true,
            'stroke-width'   => true,
            'font-size'      => true,
            'font-family'    => true,
            'text-anchor'    => true,
            'class'          => true,
            'style'          => true,
            'transform'      => true,
            'opacity'        => true,
            'dx'             => true,
            'dy'             => true,
        ),
        'tspan'     => array(
            'x'              => true,
            'y'              => true,
            'fill'           => true,
            'class'          => true,
            'style'          => true,
        ),
        'foreignObject' => array(
            'x'      => true,
            'y'      => true,
            'width'  => true,
            'height' => true,
            'class'  => true,
        ),
        'image'     => array(
            'href'       => true,
            'xlink:href' => true,
            'x'          => true,
            'y'          => true,
            'width'      => true,
            'height'     => true,
            'class'      => true,
        ),
    );

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_bv_save_custom_icon', array( $this, 'ajax_save_custom_icon' ) );
        add_action( 'wp_ajax_bv_delete_custom_icon', array( $this, 'ajax_delete_custom_icon' ) );
        add_action( 'wp_ajax_bv_get_custom_icon', array( $this, 'ajax_get_custom_icon' ) );
    }
    // ---------------------------------------------------------------
    // 1. DATABASE TABLE
    // ---------------------------------------------------------------

    /**
     * Create the bv_custom_icons table using dbDelta.
     *
     * @since 2.6.0
     * @return void
     */
    public static function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'bv_custom_icons';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL DEFAULT '',
                label varchar(200) NOT NULL DEFAULT '',
                svg_inner TEXT NOT NULL,
                source varchar(50) NOT NULL DEFAULT 'upload',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY name (name)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Activation callback — creates the custom icons table.
     *
     * @since 2.6.0
     * @return void
     */
    public static function activate() {
        self::create_table();
    }

    // ---------------------------------------------------------------
    // 2. ADMIN PAGE
    // ---------------------------------------------------------------

    /**
     * Add the Icon Manager submenu page under the BusinessVance parent menu.
     *
     * @since 2.6.0
     * @return void
     */
    public function add_menu_page() {
        add_submenu_page(
            'businessvance',
            __( 'Icon Manager', 'businessvance-services-manager' ),
            __( 'Icons', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-icons',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin assets on BusinessVance pages.
     *
     * @since 2.6.0
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'businessvance_page_businessvance-icons' ) {
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
            false
        );

        wp_localize_script( 'bv-admin-js', 'bvAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'page'     => 'icons',
        ) );
    }

    /**
     * Render the Icon Manager admin page.
     *
     * @since 2.6.0
     * @return void
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'businessvance-services-manager' ) );
        }

        $all_icons = self::get_all_icons_for_picker();
        $builtin_icons = array_filter( $all_icons, function( $icon ) {
            return $icon['type'] === 'builtin';
        } );
        $custom_icons = array_filter( $all_icons, function( $icon ) {
            return $icon['type'] === 'custom';
        } );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <span class="bv-shield-icon">🛡️</span>
                    <div>
                        <h1><?php esc_html_e( 'Icon Manager', 'businessvance-services-manager' ); ?></h1>
                        <p class="bv-subtitle"><?php esc_html_e( 'Manage built-in and custom SVG icons', 'businessvance-services-manager' ); ?></p>
                    </div>
                </div>
            </div>

            <!-- Add New Icon Button -->
            <div class="bv-icon-manager-toolbar" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div class="bv-icon-manager-count">
                    <span class="bv-icon-manager-count-builtin" style="margin-right: 20px;">
                        <strong><?php echo esc_html( count( $builtin_icons ) ); ?></strong> <?php esc_html_e( 'Built-in Icons', 'businessvance-services-manager' ); ?>
                    </span>
                    <span class="bv-icon-manager-count-custom">
                        <strong><?php echo esc_html( count( $custom_icons ) ); ?></strong> <?php esc_html_e( 'Custom Icons', 'businessvance-services-manager' ); ?>
                    </span>
                </div>
                <button type="button" class="button button-primary" id="bv-add-icon-btn">
                    <?php esc_html_e( '+ Add Custom Icon', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <!-- Built-in Icons Grid -->
            <div class="bv-icon-manager-section">
                <h2 class="bv-section-title"><?php esc_html_e( 'Built-in Icons', 'businessvance-services-manager' ); ?></h2>
                <div class="bv-icon-grid" id="bv-builtin-icons-grid">
                    <?php foreach ( $builtin_icons as $icon ) : ?>
                        <div class="bv-icon-card bv-icon-card--builtin" data-icon-name="<?php echo esc_attr( $icon['name'] ); ?>">
                            <div class="bv-icon-card__preview">
                                <?php echo self::get_icon_svg( $icon['name'], 28 ); ?>
                            </div>
                            <div class="bv-icon-card__label"><?php echo esc_html( $icon['label'] ); ?></div>
                            <div class="bv-icon-card__name"><?php echo esc_html( $icon['name'] ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Custom Icons Grid -->
            <div class="bv-icon-manager-section" style="margin-top: 30px;">
                <h2 class="bv-section-title"><?php esc_html_e( 'Custom Icons', 'businessvance-services-manager' ); ?></h2>
                <?php if ( empty( $custom_icons ) ) : ?>
                    <div class="bv-icon-manager-empty" style="padding: 40px; text-align: center; color: #666; border: 2px dashed #ddd; border-radius: 8px; margin-bottom: 20px;">
                        <p><?php esc_html_e( 'No custom icons yet. Click "Add Custom Icon" to upload or paste an SVG.', 'businessvance-services-manager' ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="bv-icon-grid" id="bv-custom-icons-grid">
                        <?php foreach ( $custom_icons as $icon ) : ?>
                            <div class="bv-icon-card bv-icon-card--custom" data-icon-id="<?php echo esc_attr( $icon['id'] ); ?>" data-icon-name="<?php echo esc_attr( $icon['name'] ); ?>">
                                <div class="bv-icon-card__preview">
                                    <?php echo self::get_icon_svg( $icon['name'], 28 ); ?>
                                </div>
                                <div class="bv-icon-card__label"><?php echo esc_html( $icon['label'] ); ?></div>
                                <div class="bv-icon-card__name"><?php echo esc_html( $icon['name'] ); ?></div>
                                <div class="bv-icon-card__actions">
                                    <button type="button" class="button button-small bv-icon-edit-btn" data-id="<?php echo esc_attr( $icon['id'] ); ?>" title="<?php esc_attr_e( 'Edit', 'businessvance-services-manager' ); ?>">
                                        <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                    </button>
                                    <button type="button" class="button button-small button-link-delete bv-icon-delete-btn" data-id="<?php echo esc_attr( $icon['id'] ); ?>" data-name="<?php echo esc_attr( $icon['name'] ); ?>" title="<?php esc_attr_e( 'Delete', 'businessvance-services-manager' ); ?>">
                                        <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add/Edit Icon Modal -->
        <div id="bv-icon-modal" class="bv-icon-modal" style="display: none;">
            <div class="bv-icon-modal__overlay"></div>
            <div class="bv-icon-modal__content">
                <div class="bv-icon-modal__header">
                    <h2 id="bv-icon-modal-title"><?php esc_html_e( 'Add Custom Icon', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-icon-modal__close" id="bv-icon-modal-close">&times;</button>
                </div>
                <div class="bv-icon-modal__body">
                    <input type="hidden" id="bv-icon-edit-id" value="0" />

                    <div class="bv-form-group">
                        <label for="bv-icon-label"><?php esc_html_e( 'Icon Label', 'businessvance-services-manager' ); ?> <span class="required">*</span></label>
                        <input type="text" id="bv-icon-label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Company Logo', 'businessvance-services-manager' ); ?>" />
                    </div>

                    <div class="bv-form-group">
                        <label for="bv-icon-name"><?php esc_html_e( 'Icon Name (slug)', 'businessvance-services-manager' ); ?> <span class="required">*</span></label>
                        <input type="text" id="bv-icon-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. custom-logo', 'businessvance-services-manager' ); ?>" />
                        <p class="description"><?php esc_html_e( 'Unique identifier in slug format. Auto-generated from label.', 'businessvance-services-manager' ); ?></p>
                    </div>

                    <div class="bv-form-group">
                        <label><?php esc_html_e( 'SVG Source', 'businessvance-services-manager' ); ?></label>
                        <div class="bv-icon-source-tabs">
                            <button type="button" class="button bv-icon-source-tab active" data-source="upload"><?php esc_html_e( 'Upload SVG File', 'businessvance-services-manager' ); ?></button>
                            <button type="button" class="button bv-icon-source-tab" data-source="paste"><?php esc_html_e( 'Paste SVG Code', 'businessvance-services-manager' ); ?></button>
                        </div>
                    </div>

                    <div class="bv-form-group" id="bv-icon-upload-group">
                        <label for="bv-icon-file"><?php esc_html_e( 'Upload SVG File', 'businessvance-services-manager' ); ?></label>
                        <input type="file" id="bv-icon-file" accept=".svg" />
                        <p class="description"><?php esc_html_e( 'Accepts .svg files only.', 'businessvance-services-manager' ); ?></p>
                    </div>

                    <div class="bv-form-group" id="bv-icon-paste-group" style="display: none;">
                        <label for="bv-icon-svg-code"><?php esc_html_e( 'SVG Code', 'businessvance-services-manager' ); ?></label>
                        <textarea id="bv-icon-svg-code" rows="8" class="large-text code" placeholder="<?php esc_attr_e( 'Paste your SVG code here...', 'businessvance-services-manager' ); ?>"></textarea>
                    </div>

                    <div class="bv-form-group" id="bv-icon-preview-group" style="display: none;">
                        <label><?php esc_html_e( 'Preview', 'businessvance-services-manager' ); ?></label>
                        <div class="bv-icon-preview" id="bv-icon-preview" style="padding: 30px; text-align: center; border: 2px dashed #ddd; border-radius: 8px; min-height: 80px; display: flex; align-items: center; justify-content: center;">
                            <span style="color: #999;"><?php esc_html_e( 'Icon preview will appear here', 'businessvance-services-manager' ); ?></span>
                        </div>
                    </div>

                    <input type="hidden" id="bv-icon-viewbox" value="0 0 24 24" />
                    <input type="hidden" id="bv-icon-svg-inner" value="" />
                </div>
                <div class="bv-icon-modal__footer">
                    <button type="button" class="button" id="bv-icon-modal-cancel"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                    <button type="button" class="button button-primary" id="bv-icon-save-btn"><?php esc_html_e( 'Save Icon', 'businessvance-services-manager' ); ?></button>
                </div>
            </div>
        </div>

        <style>
            /* Icon Manager Styles */
            .bv-icon-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 16px;
            }
            .bv-icon-card {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 16px 12px 12px;
                text-align: center;
                transition: box-shadow 0.2s, border-color 0.2s;
                position: relative;
            }
            .bv-icon-card:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                border-color: #008080;
            }
            .bv-icon-card__preview {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 48px;
                margin-bottom: 8px;
                color: #002B5C;
            }
            .bv-icon-card__label {
                font-weight: 600;
                font-size: 12px;
                color: #1d2327;
                margin-bottom: 2px;
                word-break: break-word;
            }
            .bv-icon-card__name {
                font-size: 11px;
                color: #666;
                word-break: break-all;
            }
            .bv-icon-card--custom .bv-icon-card__actions {
                margin-top: 10px;
                display: flex;
                gap: 6px;
                justify-content: center;
            }
            .bv-icon-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 160000;
            }
            .bv-icon-modal__overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
            }
            .bv-icon-modal__content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                border-radius: 8px;
                width: 560px;
                max-width: 95vw;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            }
            .bv-icon-modal__header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #dcdcde;
            }
            .bv-icon-modal__header h2 {
                margin: 0;
                font-size: 18px;
            }
            .bv-icon-modal__close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                line-height: 1;
                padding: 0 4px;
            }
            .bv-icon-modal__close:hover {
                color: #d63638;
            }
            .bv-icon-modal__body {
                padding: 20px;
            }
            .bv-icon-modal__body .bv-form-group {
                margin-bottom: 16px;
            }
            .bv-icon-modal__body .bv-form-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
            }
            .bv-icon-modal__body .bv-form-group .required {
                color: #d63638;
            }
            .bv-icon-modal__footer {
                padding: 16px 20px;
                border-top: 1px solid #dcdcde;
                display: flex;
                justify-content: flex-end;
                gap: 8px;
            }
            .bv-icon-source-tabs {
                display: flex;
                gap: 4px;
            }
            .bv-icon-source-tab {
                cursor: pointer;
            }
            .bv-icon-source-tab.active {
                background: #008080;
                color: #fff;
                border-color: #008080;
            }
            .bv-icon-preview svg {
                max-width: 80px;
                max-height: 80px;
                color: #002B5C;
            }
            .bv-section-title {
                margin-bottom: 16px;
                padding-bottom: 8px;
                border-bottom: 1px solid #dcdcde;
            }
        </style>

        <script>
        (function($) {
            'use strict';

            var currentSource = 'upload';

            // Open modal for adding new icon
            $('#bv-add-icon-btn').on('click', function() {
                $('#bv-icon-edit-id').val(0);
                $('#bv-icon-modal-title').text('<?php esc_html_e( 'Add Custom Icon', 'businessvance-services-manager' ); ?>');
                $('#bv-icon-save-btn').text('<?php esc_html_e( 'Save Icon', 'businessvance-services-manager' ); ?>');
                $('#bv-icon-label').val('');
                $('#bv-icon-name').val('');
                $('#bv-icon-file').val('');
                $('#bv-icon-svg-code').val('');
                $('#bv-icon-viewbox').val('0 0 24 24');
                $('#bv-icon-svg-inner').val('');
                $('#bv-icon-preview').html('<span style="color:#999;"><?php esc_html_e( 'Icon preview will appear here', 'businessvance-services-manager' ); ?></span>');
                $('#bv-icon-preview-group').hide();
                switchSourceTab('upload');
                $('#bv-icon-modal').show();
            });

            // Edit icon button
            $(document).on('click', '.bv-icon-edit-btn', function() {
                var id = $(this).data('id');
                $.ajax({
                    url: bvAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bv_get_custom_icon',
                        nonce: bvAdmin.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            var icon = response.data;
                            $('#bv-icon-edit-id').val(icon.id);
                            $('#bv-icon-modal-title').text('<?php esc_html_e( 'Edit Custom Icon', 'businessvance-services-manager' ); ?>');
                            $('#bv-icon-save-btn').text('<?php esc_html_e( 'Update Icon', 'businessvance-services-manager' ); ?>');
                            $('#bv-icon-label').val(icon.label);
                            $('#bv-icon-name').val(icon.name);
                            $('#bv-icon-svg-inner').val(icon.svg_inner);
                            $('#bv-icon-viewbox').val(icon.view_box || '0 0 24 24');
                            $('#bv-icon-file').val('');
                            $('#bv-icon-svg-code').val('');
                            switchSourceTab('paste');
                            $('#bv-icon-svg-code').val('<svg viewBox="' + (icon.view_box || '0 0 24 24') + '" xmlns="http://www.w3.org/2000/svg">' + icon.svg_inner + '</svg>');
                            showPreview(icon.svg_inner, icon.view_box || '0 0 24 24');
                            $('#bv-icon-modal').show();
                        } else {
                            alert(response.data.message || '<?php esc_html_e( 'Failed to load icon.', 'businessvance-services-manager' ); ?>');
                        }
                    }
                });
            });

            // Delete icon button
            $(document).on('click', '.bv-icon-delete-btn', function() {
                var btn = $(this);
                var id = btn.data('id');
                var name = btn.data('name');
                if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this icon?', 'businessvance-services-manager' ); ?>')) {
                    return;
                }
                $.ajax({
                    url: bvAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bv_delete_custom_icon',
                        nonce: bvAdmin.nonce,
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.warning) {
                                alert(response.data.warning);
                            }
                            btn.closest('.bv-icon-card').fadeOut(300, function() { $(this).remove(); });
                        } else {
                            alert(response.data.message || '<?php esc_html_e( 'Failed to delete icon.', 'businessvance-services-manager' ); ?>');
                        }
                    }
                });
            });

            // Close modal
            $('#bv-icon-modal-close, #bv-icon-modal-cancel, .bv-icon-modal__overlay').on('click', function() {
                $('#bv-icon-modal').hide();
            });

            // Source tabs
            $('.bv-icon-source-tab').on('click', function() {
                switchSourceTab($(this).data('source'));
            });

            function switchSourceTab(source) {
                currentSource = source;
                $('.bv-icon-source-tab').removeClass('active');
                $('.bv-icon-source-tab[data-source="' + source + '"]').addClass('active');
                if (source === 'upload') {
                    $('#bv-icon-upload-group').show();
                    $('#bv-icon-paste-group').hide();
                } else {
                    $('#bv-icon-upload-group').hide();
                    $('#bv-icon-paste-group').show();
                }
            }

            // Auto-generate slug from label
            $('#bv-icon-label').on('input', function() {
                var editId = parseInt($('#bv-icon-edit-id').val());
                if (editId === 0) {
                    var slug = $(this).val().toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/[\s-]+/g, '-')
                        .replace(/^-+|-+$/g, '')
                        .replace(/^-/, 'custom-');
                    if (!slug) slug = 'custom-icon';
                    $('#bv-icon-name').val(slug);
                }
            });

            // Handle file upload
            $('#bv-icon-file').on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                if (!file.name.toLowerCase().endsWith('.svg')) {
                    alert('<?php esc_html_e( 'Please select an SVG file.', 'businessvance-services-manager' ); ?>');
                    $(this).val('');
                    return;
                }
                var reader = new FileReader();
                reader.onload = function(ev) {
                    processSvgContent(ev.target.result);
                };
                reader.readAsText(file);
            });

            // Handle paste of SVG code
            $('#bv-icon-svg-code').on('input', function() {
                var code = $(this).val().trim();
                if (code.indexOf('<svg') !== -1) {
                    processSvgContent(code);
                }
            });

            function processSvgContent(svgString) {
                // Extract inner content between <svg ...> and </svg>
                var innerMatch = svgString.match(/<svg[^>]*>([\s\S]*)<\/svg>/i);
                if (!innerMatch) {
                    // Maybe the content is already inner content
                    if (svgString.indexOf('<svg') === -1 && (svgString.indexOf('<path') !== -1 || svgString.indexOf('<circle') !== -1 || svgString.indexOf('<rect') !== -1)) {
                        $('#bv-icon-svg-inner').val(svgString.trim());
                        showPreview(svgString.trim(), '0 0 24 24');
                    }
                    return;
                }

                var svgTag = svgString.match(/<svg[^>]*>/i)[0];
                var inner = innerMatch[1].trim();

                // Extract viewBox
                var vbMatch = svgTag.match(/viewBox\s*=\s*["']([^"']+)["']/i);
                var viewBox = vbMatch ? vbMatch[1] : '0 0 24 24';

                $('#bv-icon-svg-inner').val(inner);
                $('#bv-icon-viewbox').val(viewBox);
                showPreview(inner, viewBox);
            }

            function showPreview(inner, viewBox) {
                var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="' + viewBox + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';
                $('#bv-icon-preview').html(svg);
                $('#bv-icon-preview-group').show();
            }

            // Save icon
            $('#bv-icon-save-btn').on('click', function() {
                var label = $('#bv-icon-label').val().trim();
                var name = $('#bv-icon-name').val().trim();
                var svgInner = $('#bv-icon-svg-inner').val().trim();
                var editId = parseInt($('#bv-icon-edit-id').val());

                if (!label) {
                    alert('<?php esc_html_e( 'Icon label is required.', 'businessvance-services-manager' ); ?>');
                    $('#bv-icon-label').focus();
                    return;
                }
                if (!name) {
                    alert('<?php esc_html_e( 'Icon name is required.', 'businessvance-services-manager' ); ?>');
                    $('#bv-icon-name').focus();
                    return;
                }
                if (!/^[a-z0-9-]+$/.test(name)) {
                    alert('<?php esc_html_e( 'Icon name must contain only lowercase letters, numbers, and hyphens.', 'businessvance-services-manager' ); ?>');
                    $('#bv-icon-name').focus();
                    return;
                }
                if (!svgInner) {
                    alert('<?php esc_html_e( 'Please upload an SVG file or paste SVG code.', 'businessvance-services-manager' ); ?>');
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).text('<?php esc_html_e( 'Saving...', 'businessvance-services-manager' ); ?>');

                $.ajax({
                    url: bvAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bv_save_custom_icon',
                        nonce: bvAdmin.nonce,
                        id: editId,
                        name: name,
                        label: label,
                        svg_inner: svgInner,
                        view_box: $('#bv-icon-viewbox').val().trim(),
                        source: currentSource
                    },
                    success: function(response) {
                        btn.prop('disabled', false).text(editId ? '<?php esc_html_e( 'Update Icon', 'businessvance-services-manager' ); ?>' : '<?php esc_html_e( 'Save Icon', 'businessvance-services-manager' ); ?>');
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php esc_html_e( 'An error occurred. Please try again.', 'businessvance-services-manager' ); ?>');
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text(editId ? '<?php esc_html_e( 'Update Icon', 'businessvance-services-manager' ); ?>' : '<?php esc_html_e( 'Save Icon', 'businessvance-services-manager' ); ?>');
                        alert('<?php esc_html_e( 'An error occurred. Please try again.', 'businessvance-services-manager' ); ?>');
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    // ---------------------------------------------------------------
    // 3. AJAX HANDLERS
    // ---------------------------------------------------------------

    /**
     * Verify admin nonce and capability.
     *
     * @since 2.6.0
     * @return void
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
     * AJAX: Save (create or update) a custom icon.
     *
     * @since 2.6.0
     * @return void
     */
    public function ajax_save_custom_icon() {
        $this->verify_nonce();
        global $wpdb;

        $table = $wpdb->prefix . 'bv_custom_icons';
        $id     = intval( $_POST['id'] ?? 0 );
        $name   = sanitize_text_field( $_POST['name'] ?? '' );
        $label  = sanitize_text_field( $_POST['label'] ?? '' );
        $raw_svg = $_POST['svg_inner'] ?? '';
        $source = in_array( $_POST['source'] ?? 'upload', array( 'upload', 'paste' ), true ) ? sanitize_text_field( $_POST['source'] ) : 'upload';

        // Validate required fields
        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Icon name is required.', 'businessvance-services-manager' ) ) );
        }
        if ( empty( $label ) ) {
            wp_send_json_error( array( 'message' => __( 'Icon label is required.', 'businessvance-services-manager' ) ) );
        }
        if ( empty( $raw_svg ) ) {
            wp_send_json_error( array( 'message' => __( 'SVG content is required.', 'businessvance-services-manager' ) ) );
        }

        // Validate slug format
        if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Icon name must contain only lowercase letters, numbers, and hyphens, and must start and end with a letter or number.', 'businessvance-services-manager' ) ) );
        }

        // Validate length
        if ( strlen( $name ) > 100 ) {
            wp_send_json_error( array( 'message' => __( 'Icon name cannot exceed 100 characters.', 'businessvance-services-manager' ) ) );
        }
        if ( strlen( $label ) > 200 ) {
            wp_send_json_error( array( 'message' => __( 'Icon label cannot exceed 200 characters.', 'businessvance-services-manager' ) ) );
        }

        // Check for name conflict with built-in icons
        if ( in_array( $name, self::$builtin_icon_names, true ) ) {
            wp_send_json_error( array( 'message' => sprintf(
                /* translators: %s: icon name */
                __( 'The name "%s" conflicts with a built-in icon. Please choose a different name.', 'businessvance-services-manager' ),
                $name
            ) ) );
        }

        // Check for name uniqueness (excluding current ID when updating)
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE name = %s AND id != %d",
            $name,
            $id
        ) );
        if ( $existing ) {
            wp_send_json_error( array( 'message' => sprintf(
                /* translators: %s: icon name */
                __( 'An icon with the name "%s" already exists.', 'businessvance-services-manager' ),
                $name
            ) ) );
        }

        // Sanitize SVG inner content
        $svg_inner = self::sanitize_svg( $raw_svg );

        $data = array(
            'name'      => $name,
            'label'     => $label,
            'svg_inner' => $svg_inner,
            'source'    => $source,
        );
        $format = array( '%s', '%s', '%s', '%s' );

        if ( $id > 0 ) {
            // Update existing icon
            $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
            wp_send_json_success( array(
                'message' => __( 'Icon updated.', 'businessvance-services-manager' ),
                'id'      => $id,
            ) );
        } else {
            // Insert new icon
            $wpdb->insert( $table, $data, $format );
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array(
                'message' => __( 'Icon saved.', 'businessvance-services-manager' ),
                'id'      => $new_id,
            ) );
        }
    }

    /**
     * AJAX: Delete a custom icon by ID.
     *
     * Checks if any service uses this icon name and includes a warning.
     *
     * @since 2.6.0
     * @return void
     */
    public function ajax_delete_custom_icon() {
        $this->verify_nonce();
        global $wpdb;

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $table   = $wpdb->prefix . 'bv_custom_icons';
        $icon    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        if ( ! $icon ) {
            wp_send_json_error( array( 'message' => __( 'Icon not found.', 'businessvance-services-manager' ) ) );
        }

        // Check if any service uses this icon name
        $services_table = $wpdb->prefix . 'bv_services';
        $services_using = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$services_table} WHERE icon = %s",
            $icon->name
        ) );

        $warning = '';
        if ( intval( $services_using ) > 0 ) {
            $warning = sprintf(
                /* translators: 1: icon name, 2: number of services */
                __( 'Warning: The icon "%1$s" is used by %2$d service(s). Those services will fall back to the default icon.', 'businessvance-services-manager' ),
                $icon->name,
                intval( $services_using )
            );
        }

        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        $response = array( 'message' => __( 'Icon deleted.', 'businessvance-services-manager' ) );
        if ( $warning ) {
            $response['warning'] = $warning;
        }

        wp_send_json_success( $response );
    }

    /**
     * AJAX: Get a custom icon's data for editing.
     *
     * @since 2.6.0
     * @return void
     */
    public function ajax_get_custom_icon() {
        $this->verify_nonce();
        global $wpdb;

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $table = $wpdb->prefix . 'bv_custom_icons';
        $icon  = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, label, svg_inner, source, created_at FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $icon ) {
            wp_send_json_error( array( 'message' => __( 'Icon not found.', 'businessvance-services-manager' ) ) );
        }

        // Attempt to extract viewBox from the svg_inner content if it was stored
        // Custom icons store the viewBox alongside the svg_inner; we return a default.
        $icon['view_box'] = '0 0 24 24';

        wp_send_json_success( $icon );
    }

    // ---------------------------------------------------------------
    // 4. STATIC HELPER METHODS
    // ---------------------------------------------------------------

    /**
     * Get the list of built-in Lucide icon names.
     *
     * @since 2.6.0
     * @return array Array of icon name strings.
     */
    public static function get_builtin_icons() {
        return self::$builtin_icon_names;
    }

    /**
     * Get the built-in icon SVG path data.
     *
     * @since 2.6.0
     * @return array Associative array: icon name => SVG inner content string.
     */
    public static function get_builtin_svg_paths() {
        return self::$builtin_svg_paths;
    }

    /**
     * Get all custom icons from the database.
     *
     * @since 2.6.0
     * @return array Array of objects/associative arrays, each with id, name, label, svg_inner.
     */
    public static function get_custom_icons() {
        global $wpdb;
        $table = $wpdb->prefix . 'bv_custom_icons';

        return $wpdb->get_results( "SELECT id, name, label, svg_inner FROM {$table} ORDER BY created_at DESC", ARRAY_A );
    }

    /**
     * Get all icons (built-in + custom) combined into a single associative array.
     *
     * @since 2.6.0
     * @return array Associative array: icon name => array with 'label', 'svg_inner', 'type', 'id'.
     */
    public static function get_all_icons() {
        $all = array();

        // Built-in icons
        foreach ( self::$builtin_icon_names as $name ) {
            $label_parts = explode( '-', $name );
            $label_parts = array_map( 'ucfirst', $label_parts );
            $all[ $name ] = array(
                'label'     => implode( ' ', $label_parts ),
                'svg_inner' => isset( self::$builtin_svg_paths[ $name ] ) ? self::$builtin_svg_paths[ $name ] : '',
                'type'      => 'builtin',
                'id'        => null,
            );
        }

        // Custom icons
        $custom_icons = self::get_custom_icons();
        foreach ( $custom_icons as $icon ) {
            $all[ $icon['name'] ] = array(
                'label'     => $icon['label'],
                'svg_inner' => $icon['svg_inner'],
                'type'      => 'custom',
                'id'        => intval( $icon['id'] ),
            );
        }

        return $all;
    }

    /**
     * Get all icons in a format suitable for the icon picker UI.
     *
     * @since 2.6.0
     * @return array Array of arrays, each with 'name', 'label', 'svg_inner', 'type', 'id'.
     */
    public static function get_all_icons_for_picker() {
        $all   = self::get_all_icons();
        $items = array();

        foreach ( $all as $name => $data ) {
            $items[] = array(
                'name'      => $name,
                'label'     => $data['label'],
                'svg_inner' => $data['svg_inner'],
                'type'      => $data['type'],
                'id'        => $data['id'],
            );
        }

        return $items;
    }

    /**
     * Get the full <svg> markup for any icon (built-in or custom).
     *
     * Returns a fallback icon SVG if the requested icon is not found.
     *
     * @since 2.6.0
     * @param string $icon_name The icon identifier.
     * @param int    $size      The icon size in pixels (width and height). Default 24.
     * @return string Full <svg> HTML markup.
     */
    public static function get_icon_svg( $icon_name, $size = 24 ) {
        $svg_inner = '';
        $view_box  = '0 0 24 24';

        // Check built-in icons first
        if ( isset( self::$builtin_svg_paths[ $icon_name ] ) ) {
            $svg_inner = self::$builtin_svg_paths[ $icon_name ];
        } else {
            // Check custom icons
            $custom_icons = self::get_custom_icons();
            foreach ( $custom_icons as $icon ) {
                if ( $icon['name'] === $icon_name ) {
                    $svg_inner = $icon['svg_inner'];
                    break;
                }
            }
        }

        // Fallback icon if nothing found
        if ( empty( $svg_inner ) ) {
            $svg_inner = '<rect width="16" height="20" x="4" y="2" rx="2"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . intval( $size ) . '" height="' . intval( $size ) . '" viewBox="' . esc_attr( $view_box ) . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $svg_inner . '</svg>';
    }

    // ---------------------------------------------------------------
    // 5. SVG EXTRACTION & SANITIZATION
    // ---------------------------------------------------------------

    /**
     * Extract the inner SVG content from a full SVG string.
     *
     * Strips the <svg> opening/closing tags, extracting only the viewBox
     * attribute and everything inside the tags.
     *
     * @since 2.6.0
     * @param string $svg_string Full SVG markup or already-extracted inner content.
     * @return array Associative array with 'svg_inner' (string) and 'view_box' (string).
     */
    public static function extract_svg_inner( $svg_string ) {
        $result = array(
            'svg_inner' => '',
            'view_box'  => '0 0 24 24',
        );

        if ( empty( $svg_string ) ) {
            return $result;
        }

        // Try to match full SVG tag
        if ( preg_match( '/<svg[^>]*>([\s\S]*)<\/svg>/i', $svg_string, $matches ) ) {
            $svg_tag = $svg_string;
            if ( preg_match( '/<svg[^>]*>/i', $svg_string, $tag_matches ) ) {
                $svg_tag = $tag_matches[0];
            }

            // Extract viewBox
            if ( preg_match( '/viewBox\s*=\s*["\']([^"\']+)["\']/i', $svg_tag, $vb_matches ) ) {
                $result['view_box'] = $vb_matches[1];
            }

            $result['svg_inner'] = trim( $matches[1] );
        } else {
            // Content is already inner SVG content (no <svg> wrapper)
            $result['svg_inner'] = trim( $svg_string );
        }

        return $result;
    }

    /**
     * Sanitize SVG inner content using wp_kses.
     *
     * @since 2.6.0
     * @param string $svg_inner The SVG inner content to sanitize.
     * @return string Sanitized SVG inner content.
     */
    public static function sanitize_svg( $svg_inner ) {
        return wp_kses( $svg_inner, self::$svg_kses_allowed );
    }
}
