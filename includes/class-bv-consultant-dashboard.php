<?php
/**
 * BusinessVance Consultant Dashboard
 *
 * Admin page for consultants to manage client projects, view questionnaires,
 * upload reports, manage documents, and communicate with clients.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Consultant_Dashboard {

    const CAP = 'bv_access_consultant_dashboard';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_menu', array( $this, 'restrict_admin_menu' ), 9999 );
        add_action( 'admin_init', array( $this, 'lock_admin_access' ) );
        // Dynamic capability mapping — fires DURING current_user_can() checks.
        // 1. Admins always get bv_access_consultant_dashboard (menu visibility).
        // 2. Consultant users get edit_posts in admin context — this prevents
        //    WooCommerce's "Prevent admin access" feature from redirecting
        //    them to My Account (which would create a redirect loop).
        add_filter( 'user_has_cap', array( $this, 'grant_caps' ), 10, 4 );
        // WooCommerce login redirect: the login form POSTs to /my-account/ (NOT wp-login.php),
        // so the standard login_redirect filter never fires. WooCommerce's own
        // WC_Form_Handler::process_login() applies this filter before its redirect.
        // We return the admin dashboard URL — WooCommerce handles the actual redirect.
        add_filter( 'woocommerce_login_redirect', array( $this, 'wc_login_redirect' ), 99999, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bv_cd_get_projects', array( $this, 'ajax_get_projects' ) );
        add_action( 'wp_ajax_bv_cd_get_project_detail', array( $this, 'ajax_get_project_detail' ) );
        add_action( 'wp_ajax_bv_cd_update_project_status', array( $this, 'ajax_update_project_status' ) );
        add_action( 'wp_ajax_bv_cd_update_progress', array( $this, 'ajax_update_progress' ) );
        add_action( 'wp_ajax_bv_cd_upload_report', array( $this, 'ajax_upload_report' ) );
        add_action( 'wp_ajax_bv_cd_deliver_report', array( $this, 'ajax_deliver_report' ) );
        add_action( 'wp_ajax_bv_cd_send_message', array( $this, 'ajax_send_message' ) );
        add_action( 'wp_ajax_bv_cd_add_note', array( $this, 'ajax_add_note' ) );
        add_action( 'wp_ajax_bv_cd_update_internal_notes', array( $this, 'ajax_update_internal_notes' ) );
        add_action( 'wp_ajax_bv_cd_get_messages', array( $this, 'ajax_get_messages' ) );
        add_action( 'wp_ajax_bv_cd_download_document', array( $this, 'ajax_download_document' ) );
        add_action( 'wp_ajax_bv_cd_create_project', array( $this, 'ajax_create_project' ) );
        add_action( 'wp_ajax_bv_cd_download_report', array( $this, 'ajax_download_report' ) );
        add_action( 'wp_ajax_bv_cd_download_questionnaire', array( $this, 'ajax_download_questionnaire' ) );
        add_action( 'wp_ajax_bv_cd_download_questionnaire_html', array( $this, 'ajax_download_questionnaire_html' ) );
        add_action( 'wp_ajax_bv_cd_download_qfile', array( $this, 'ajax_download_questionnaire_file' ) );
        add_action( 'bv_project_completion_email', array( $this, 'email_project_package_to_consultant' ) );
        add_action( 'wp_ajax_bv_cd_reset_project', array( $this, 'ajax_reset_project' ) );
        add_action( 'wp_ajax_bv_cd_remove_project', array( $this, 'ajax_delete_project' ) );
        add_action( 'wp_ajax_bv_cd_bulk_update_status', array( $this, 'ajax_bulk_update_status' ) );
        add_action( 'wp_ajax_bv_cd_quick_note', array( $this, 'ajax_quick_note' ) );
    }
    public function add_menu_page() {
        add_menu_page(
            'Consultant Dashboard',
            'BV Consultant',
            self::CAP,
            'bv-consultant-dashboard',
            array( $this, 'render_page' ),
            'dashicons-clipboard',
            3
        );
    }

    public function restrict_admin_menu() {
        if ( current_user_can( 'manage_options' ) || ! current_user_can( self::CAP ) ) return;
        global $menu, $submenu;
        $allowed = array( 'bv-consultant-dashboard' );
        if ( is_array( $menu ) ) foreach ( $menu as $i => $item ) { if ( ! in_array( $item[2], $allowed, true ) ) unset( $menu[$i] ); }
        if ( is_array( $submenu ) ) foreach ( $submenu as $slug => $items ) { if ( ! in_array( $slug, $allowed, true ) ) unset( $submenu[$slug] ); }
    }



    public function lock_admin_access() {
        // AJAX requests must never be redirected — they must reach their handler.
        // wp_doing_ajax() returns true for /wp-admin/admin-ajax.php requests.
        if ( wp_doing_ajax() ) return;
        if ( ! is_admin() || ! is_user_logged_in() ) return;
        if ( current_user_can( 'manage_options' ) || ! current_user_can( self::CAP ) ) return;
        $page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
        if ( $page === 'bv-consultant-dashboard' ) return;
        $dest = admin_url( 'admin.php?page=bv-consultant-dashboard', 'admin' );
        wp_redirect( $dest );
        exit;
    }

    /**
     * WooCommerce login redirect filter.
     * The My Account login form POSTs directly to /my-account/ (NOT wp-login.php),
     * so the standard login_redirect filter never fires. WooCommerce's own
     * WC_Form_Handler::process_login() applies this filter before calling
     * wp_safe_redirect() + exit. We simply return the admin dashboard URL
     * and let WooCommerce handle the actual redirect — no wp_redirect/exit from us.
     *
     * @param string   $redirect Default redirect URL (My Account page).
     * @param WP_User $user     The authenticated user.
     * @return string
     */
    public function wc_login_redirect( $redirect, $user ) {
        if ( $user->has_cap( 'manage_options' ) ) return $redirect;
        if ( ! $user->has_cap( self::CAP ) ) return $redirect;
        return admin_url( 'admin.php?page=bv-consultant-dashboard', 'admin' );
    }

    /**
     * Dynamic capability mapping — fires DURING every current_user_can() check.
     *
     * Two jobs:
     * 1. Grant bv_access_consultant_dashboard to admins (menu visibility).
     * 2. Grant edit_posts to consultant users IN ADMIN CONTEXT — this prevents
     *    WooCommerce's "Prevent admin access for non-admin users" feature
     *    (which checks current_user_can('edit_posts') on admin_init) from
     *    redirecting the consultant to My Account and causing a redirect loop.
     *
     * @param array   $allcaps Capabilities the user has.
     * @param array   $caps    Capabilities being checked.
     * @param array   $args    [0] = cap name, [1] = user ID.
     * @param WP_User $user    The user object.
     * @return array
     */
    public function grant_caps( $allcaps, $caps, $args, $user ) {
        // 1. Admins always see the BV Consultant menu
        if ( in_array( self::CAP, $caps, true ) && ! empty( $user->allcaps['manage_options'] ) ) {
            $allcaps[ self::CAP ] = true;
        }
        // 2. Let consultant users through WooCommerce's admin-access gate
        //    (WooCommerce checks current_user_can('edit_posts') on admin_init
        //     and redirects non-admin users to My Account — causing redirect loops).
        //    We grant the caps only during the check (non-persistent) and only
        //    for users who have bv_access_consultant_dashboard but NOT manage_options.
        if ( is_admin() && ! empty( $user->allcaps[ self::CAP ] ) && empty( $user->allcaps['manage_options'] ) ) {
            // Grant edit_posts (and common WooCommerce checks) only during capability
            // checks in the admin context. This doesn't persist — it only affects
            // the return value of current_user_can() calls.
            $admin_caps = array( 'edit_posts', 'edit_theme_options', 'export', 'import', 'list_users' );
            foreach ( $admin_caps as $cap ) {
                if ( in_array( $cap, $caps, true ) ) {
                    $allcaps[ $cap ] = true;
                }
            }
        }
        return $allcaps;
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_bv-consultant-dashboard' !== $hook ) return;
        wp_enqueue_style( 'bv-consultant-dashboard', BV_PLUGIN_URL . 'assets/css/consultant-dashboard.css', array(), BV_VERSION );
        wp_enqueue_script( 'bv-consultant-dashboard', BV_PLUGIN_URL . 'assets/js/consultant-dashboard.js', array( 'jquery' ), BV_VERSION, true );
        wp_localize_script( 'bv-consultant-dashboard', 'bv_cd', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_consultant_dashboard' ),
            'upload_url' => BV_PLUGIN_URL . 'uploads/',
            'current_user' => wp_get_current_user()->display_name,
            'current_time' => date( 'd M Y H:i' ),
        ) );
    }

    public function ajax_bulk_update_status() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );
        $pids = isset( $_POST['project_ids'] ) ? array_map( 'absint', (array) $_POST['project_ids'] ) : array();
        $status = sanitize_text_field( $_POST['status'] );
        $allowed = array( 'awaiting-agreement', 'awaiting-questionnaire', 'awaiting-documents', 'in-progress', 'quality-check', 'completed', 'delivered', 'archived' );
        if ( empty( $pids ) || ! in_array( $status, $allowed, true ) ) wp_send_json_error( 'Invalid data' );
        global $wpdb;
        $count = 0;
        foreach ( $pids as $pid ) {
            $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'status' => $status ), array( 'id' => $pid ), array( '%s' ), array( '%d' ) );
            $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array( 'project_id' => $pid, 'entity_type' => 'project', 'entity_id' => $pid, 'action' => 'status_changed', 'description' => "Bulk status changed to {$status}", 'metadata' => '', 'user_id' => get_current_user_id() ), array( '%d','%s','%d','%s','%s','%s','%d' ) );
            $count++;
        }
        wp_send_json_success( $count . ' projects updated to ' . $status );
    }

    public function ajax_quick_note() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Unauthorized' );
        $pid = absint( $_POST['project_id'] );
        $content = sanitize_textarea_field( $_POST['content'] );
        if ( ! $pid || empty( $content ) ) wp_send_json_error( 'Project ID and note required' );
        global $wpdb;
        $user = wp_get_current_user();
        $wpdb->insert( $wpdb->prefix . 'bv_project_notes', array( 'project_id' => $pid, 'author_name' => $user->display_name, 'content' => $content ), array( '%d', '%s', '%s' ) );
        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array( 'project_id' => $pid, 'entity_type' => 'note', 'entity_id' => $wpdb->insert_id, 'action' => 'added', 'description' => 'Quick note added from project list', 'metadata' => '', 'user_id' => get_current_user_id() ), array( '%d','%s','%d','%s','%s','%s','%d' ) );
        wp_send_json_success( 'Note added' );
    }

    public function render_page() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( 'Access denied' );
        }
        global $wpdb;
        $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
        $tab = isset( $_GET['cd_tab'] ) ? sanitize_text_field( $_GET['cd_tab'] ) : 'projects';
        $statuses = array( 'awaiting-agreement', 'awaiting-questionnaire', 'awaiting-documents', 'in-progress', 'quality-check', 'completed', 'delivered', 'archived' );

        ?>
        <?php
        $cd_settings = BV_Settings::get_settings();
        $cd_primary = esc_attr( $cd_settings['primary_color'] ?? '#002B5C' );
        $cd_secondary = esc_attr( $cd_settings['secondary_color'] ?? '#0A2647' );
        ?>
        <div class="wrap bv-cd-wrap" id="bv-cd-app" style="--bv-cd-primary: <?php echo $cd_primary; ?>; --bv-cd-secondary: <?php echo $cd_secondary; ?>;">
            <h1 class="bv-cd-title"><span class="bv-cd-icon">📋</span> <?php echo esc_html( BV_Settings::get( 'cd_welcome_title' ) ?: 'Consultant Dashboard' ); ?></h1>

            <!-- Stats Bar -->
            <div class="bv-cd-stats">
                <?php
                $dash_url = admin_url( 'admin.php?page=bv-consultant-dashboard' );
                $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects" );
                $active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status NOT IN ('delivered', 'archived')" );
                $completed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status IN ('completed', 'delivered')" );
                $awaiting = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status IN ('awaiting-agreement', 'awaiting-questionnaire', 'awaiting-documents')" );
                $this_week = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status IN ('completed','delivered') AND updated_at >= %s", date( 'Y-m-d H:i:s', strtotime( 'monday this week' ) ) ) );
                $this_month = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status IN ('completed','delivered') AND updated_at >= %s", date( 'Y-m-01 00:00:00' ) ) );
                $pipeline_raw = $wpdb->get_var( "SELECT SUM(CAST(REPLACE(REPLACE(s.price, '$', ''), ',', '') AS DECIMAL(10,2))) FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id JOIN {$wpdb->prefix}bv_projects p ON ps.project_id = p.id WHERE p.status NOT IN ('delivered', 'archived')" );
                $pipeline = $pipeline_raw ? (float) $pipeline_raw : 0;
                $cur = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'R';
                ?>
                <a href="<?php echo esc_url( $dash_url ); ?>" class="bv-cd-stat bv-cd-stat-clickable" title="Clear filters">
                    <span class="bv-cd-stat-num"><?php echo $total; ?></span>
                    <span class="bv-cd-stat-label">Total</span>
                </a>
                <a href="<?php echo esc_url( $dash_url . '&filter_status[]=awaiting-agreement&filter_status[]=awaiting-questionnaire&filter_status[]=awaiting-documents&filter_status[]=in-progress&filter_status[]=quality-check&filter_status[]=delivered' ); ?>" class="bv-cd-stat bv-cd-stat-active bv-cd-stat-clickable" title="Active projects">
                    <span class="bv-cd-stat-num"><?php echo $active; ?></span>
                    <span class="bv-cd-stat-label">Active</span>
                </a>
                <a href="<?php echo esc_url( $dash_url . '&filter_status[]=awaiting-agreement&filter_status[]=awaiting-questionnaire&filter_status[]=awaiting-documents' ); ?>" class="bv-cd-stat bv-cd-stat-waiting bv-cd-stat-clickable" title="Awaiting client action">
                    <span class="bv-cd-stat-num"><?php echo $awaiting; ?></span>
                    <span class="bv-cd-stat-label">Awaiting</span>
                </a>
                <a href="<?php echo esc_url( $dash_url . '&filter_status[]=completed&filter_status[]=delivered' ); ?>" class="bv-cd-stat bv-cd-stat-done bv-cd-stat-clickable" title="Completed & delivered">
                    <span class="bv-cd-stat-num"><?php echo $completed; ?></span>
                    <span class="bv-cd-stat-label">Done</span>
                    <span class="bv-cd-stat-sub">+<?php echo $this_week; ?> week / +<?php echo $this_month; ?> month</span>
                </a>
                <div class="bv-cd-stat bv-cd-stat-pipeline">
                    <span class="bv-cd-stat-num"><?php echo esc_html( $cur . number_format( $pipeline, 0 ) ); ?></span>
                    <span class="bv-cd-stat-label">Pipeline</span>
                </div>
            </div>

            <?php if ( $project_id ) : ?>
                <?php $this->render_project_detail( $project_id, $tab ); ?>
            <?php else : ?>
                <?php $this->render_projects_list(); ?>
            <?php endif; ?>
        </div>

        <?php
    }

    private function render_projects_list() {
        global $wpdb;

        // --- Status filter (unchanged logic) ---
        $all_statuses = array(
            'awaiting-agreement'     => 'Awaiting Agreement',
            'awaiting-questionnaire' => 'Awaiting Questionnaire',
            'awaiting-documents'     => 'Awaiting Documents',
            'in-progress'            => 'In Progress',
            'quality-check'          => 'Quality Check',
            'completed'              => 'Completed',
            'delivered'              => 'Delivered',
            'archived'               => 'Archived',
        );
        $default_off = array( 'completed', 'archived' );
        $default_on  = array_keys( array_diff_key( $all_statuses, array_flip( $default_off ) ) );
        $raw = isset( $_GET['filter_status'] ) ? (array) $_GET['filter_status'] : array();
        $raw = array_filter( array_map( 'sanitize_text_field', $raw ) );
        $active_statuses = array_filter( $raw, function($v) use ($all_statuses) { return isset( $all_statuses[ $v ] ); } );
        if ( ! isset( $_GET['filter_status'] ) ) $active_statuses = $default_on;
        if ( isset( $_GET['filter_status'] ) && empty( $active_statuses ) ) $active_statuses = array();

        $search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $base_url = admin_url( 'admin.php?page=bv-consultant-dashboard' );

        // --- Sorting (Feature 2) ---
        $allowed_orderby = array( 'project_number', 'client_name', 'status', 'progress_percent', 'created_at', 'updated_at' );
        $orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
        $order   = ( isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ) ? 'ASC' : 'DESC';

        // --- Build query ---
        $where = '1=1';
        if ( ! empty( $active_statuses ) ) {
            $ph = implode( ',', array_fill( 0, count( $active_statuses ), '%s' ) );
            $where .= $wpdb->prepare( " AND p.status IN ($ph)", $active_statuses );
        }
        if ( $search ) $where .= $wpdb->prepare( " AND (p.project_number LIKE %s OR p.client_name LIKE %s OR p.client_email LIKE %s OR p.client_company LIKE %s)", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%" );

        // Feature 3: last activity + Feature 8: unread messages — join once
        $projects = $wpdb->get_results( "
            SELECT p.*,
                ( SELECT MAX(al.created_at) FROM {$wpdb->prefix}bv_activity_log al WHERE al.project_id = p.id ) AS last_activity,
                ( SELECT COUNT(*) FROM {$wpdb->prefix}bv_project_messages m WHERE m.project_id = p.id AND m.sender_type = 'client' AND m.is_read = 0 ) AS unread_count
            FROM {$wpdb->prefix}bv_projects p
            WHERE {$where}
            ORDER BY p.{$orderby} {$order}
        " );

        // Pre-fetch service data + prices for all projects (avoid N+1)
        $pids = array_map( function($pr) { return $pr->id; }, $projects );
        $project_services = array();
        $project_values  = array();
        $cur = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'R';
        if ( ! empty( $pids ) ) {
            $pid_ph = implode( ',', array_fill( 0, count( $pids ), '%d' ) );
            $svc_rows = $wpdb->get_results( $wpdb->prepare( "SELECT ps.project_id, s.name, s.price FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id IN ($pid_ph)", $pids ) );
            foreach ( $svc_rows as $sr ) {
                $project_services[ $sr->project_id ][] = $sr->name;
                $num = preg_replace( '/[^0-9.]/', '', $sr->price );
                $project_values[ $sr->project_id ] = ( $project_values[ $sr->project_id ] ?? 0 ) + (float) $num;
            }
        }

        // --- Overdue config (Feature 1) ---
        $overdue_days    = 7;
        $overdue_statuses = array( 'awaiting-agreement', 'awaiting-questionnaire', 'awaiting-documents' );
        $now_ts = time();

        // --- Avatar color palette (Feature 12) ---
        $avatar_colors = array( '#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c','#3498db','#9b59b6','#e84393','#00b894','#6c5ce7' );

        $checked_statuses = isset( $_GET['filter_status'] ) ? $active_statuses : $default_on;

        // --- Sort link helper ---
        $sort_link = function( $col ) use ( $base_url, $orderby, $order, $active_statuses, $search ) {
            $new_order = ( $orderby === $col && $order === 'DESC' ) ? 'asc' : 'desc';
            $url = $base_url . '&orderby=' . $col . '&order=' . $new_order;
            foreach ( $active_statuses as $st ) $url .= '&filter_status[]=' . $st;
            if ( $search ) $url .= '&s=' . urlencode( $search );
            $arrow = ( $orderby === $col ) ? ( $order === 'ASC' ? ' ↑' : ' ↓' ) : '';
            return '<a href="' . esc_url( $url ) . '">' . $arrow . '</a>';
        };
        ?>
        <div class="bv-cd-toolbar">
            <form method="get" class="bv-cd-filter-form">
                <input type="hidden" name="page" value="bv-consultant-dashboard" />
                <div class="bv-cd-status-filters" id="bv-cd-status-filters">
                    <?php foreach ( $all_statuses as $slug => $label ) : ?>
                    <label class="bv-cd-status-chip bv-cd-chip-<?php echo esc_attr( $slug ); ?>">
                        <input type="checkbox" name="filter_status[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $checked_statuses, true ) ); ?> />
                        <span><?php echo esc_html( $label ); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search projects..." />
                <button type="submit" class="button">Filter</button>
            </form>
            <div class="bv-cd-toolbar-right">
                <a href="#bv-cd-new-project" class="button button-primary" onclick="jQuery('#bv-cd-new-project-form').toggle(); return false;">+ New Project</a>
            </div>
        </div>

        <!-- Bulk Actions Bar (Feature 9) -->
        <div id="bv-cd-bulk-bar" class="bv-cd-bulk-bar" style="display:none;">
            <span class="bv-cd-bulk-count">0 selected</span>
            <select id="bv-cd-bulk-status">
                <?php foreach ( $all_statuses as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <button id="bv-cd-bulk-apply" class="button">Apply</button>
        </div>

        <!-- New Project Form -->
        <div id="bv-cd-new-project-form" style="display:none; margin-bottom:20px; padding:20px; background:#fff; border:1px solid #ccd0d4; border-radius:4px;">
            <h3>Create New Project</h3>
            <table class="form-table">
                <tr><th>Client Name *</th><td><input id="bv-cd-new-name" type="text" class="regular-text" required /></td></tr>
                <tr><th>Client Email *</th><td><input id="bv-cd-new-email" type="email" class="regular-text" required /></td></tr>
                <tr><th>Phone</th><td><input id="bv-cd-new-phone" type="tel" class="regular-text" /></td></tr>
                <tr><th>Company</th><td><input id="bv-cd-new-company" type="text" class="regular-text" /></td></tr>
                <tr><th>Notes</th><td><textarea id="bv-cd-new-notes" rows="2" class="large-text"></textarea></td></tr>
            </table>
            <button id="bv-cd-create-project" class="button button-primary">Create Project</button>
        </div>

        <!-- Projects Table -->
        <table class="wp-list-table widefat fixed striped bv-cd-table">
            <thead>
                <tr>
                    <th class="column-cb" style="width:30px;padding:8px 4px"><input type="checkbox" id="bv-cd-select-all" /></th>
                    <th>Project # <?php echo $sort_link( 'project_number' ); ?></th>
                    <th>Client <?php echo $sort_link( 'client_name' ); ?></th>
                    <th>Services</th>
                    <th>Status <?php echo $sort_link( 'status' ); ?></th>
                    <th>Progress <?php echo $sort_link( 'progress_percent' ); ?></th>
                    <th>Value</th>
                    <th>Last Activity <?php echo $sort_link( 'updated_at' ); ?></th>
                    <th>Created</th>
                    <th style="width:160px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $projects ) ) : ?>
                <tr><td colspan="10" class="bv-cd-empty-state">
                    <div class="bv-cd-empty-icon">📁</div>
                    <div class="bv-cd-empty-title">No projects found</div>
                    <div class="bv-cd-empty-text">Try adjusting your filters or create a new project to get started.</div>
                </td></tr>
            <?php else : foreach ( $projects as $p ) :
                $svc_names = isset( $project_services[ $p->id ] ) ? $project_services[ $p->id ] : array();
                $value = isset( $project_values[ $p->id ] ) ? $project_values[ $p->id ] : 0;
                // Feature 1: overdue check
                $is_overdue = in_array( $p->status, $overdue_statuses, true ) && ( $now_ts - strtotime( $p->updated_at ) ) > $overdue_days * 86400;
                // Feature 12: avatar
                $name_parts = preg_split( '/[\s]+/', trim( $p->client_name ), 2 );
                $initials = strtoupper( substr( $name_parts[0] ?? 'U', 0, 1 ) . substr( $name_parts[1] ?? '', 0, 1 ) );
                $color_idx = abs( crc32( $p->client_email ) ) % count( $avatar_colors );
                // Feature 14: progress color class
                $pct = max( 0, min( 100, (int) $p->progress_percent ) );
                $pbar_class = $pct >= 100 ? 'bv-pbar-done' : ( $pct >= 71 ? 'bv-pbar-high' : ( $pct >= 31 ? 'bv-pbar-mid' : 'bv-pbar-low' ) );
                // Feature 3: relative time
                $last_rel = $p->last_activity ? $this->time_ago( $p->last_activity ) : '—';
                // Feature 8: unread
                $unread = (int) $p->unread_count;
            ?>
                <tr data-pid="<?php echo $p->id; ?>">
                    <td class="column-cb" style="padding:8px 4px"><input type="checkbox" class="bv-cd-bulk-cb" value="<?php echo $p->id; ?>" /></td>
                    <td>
                        <strong><a href="<?php echo $base_url; ?>&project_id=<?php echo $p->id; ?>"><?php echo esc_html( $p->project_number ); ?></a></strong>
                        <?php if ( $is_overdue ) : ?><span class="bv-cd-overdue-badge" title="No action for <?php echo $overdue_days; ?>+ days">Overdue</span><?php endif; ?>
                    </td>
                    <td>
                        <div class="bv-cd-client-cell">
                            <span class="bv-cd-avatar" style="background:<?php echo $avatar_colors[ $color_idx ]; ?>"><?php echo $initials; ?></span>
                            <div>
                                <?php echo esc_html( $p->client_name ); ?><br>
                                <small style="color:#666;"><?php echo esc_html( $p->client_email ); ?><?php if ($p->client_company) echo ' — ' . esc_html($p->client_company); ?></small>
                            </div>
                        </div>
                    </td>
                    <td class="bv-cd-services-cell"><?php echo esc_html( implode( ', ', $svc_names ) ); ?></td>
                    <td>
                        <select class="bv-cd-quick-status" data-project-id="<?php echo $p->id; ?>" style="font-size:12px;padding:2px 4px;">
                            <?php foreach ( $all_statuses as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $p->status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <div class="bv-cd-mini-progress"><div class="bv-cd-mini-fill <?php echo $pbar_class; ?>" style="width:<?php echo $pct; ?>%"></div></div>
                        <small><?php echo $pct; ?>%</small>
                    </td>
                    <td><?php echo $value > 0 ? esc_html( $cur . number_format( $value, 0 ) ) : '—'; ?></td>
                    <td><small style="color:#666;"><?php echo esc_html( $last_rel ); ?></small></td>
                    <td><small style="color:#666;"><?php echo esc_html( date( 'd M Y', strtotime( $p->created_at ) ) ); ?></small></td>
                    <td>
                        <a href="<?php echo $base_url; ?>&project_id=<?php echo $p->id; ?>" class="button button-small bv-cd-open-btn" style="position:relative;">Open<?php if ( $unread > 0 ) : ?><span class="bv-cd-unread-dot"><?php echo $unread; ?></span><?php endif; ?></a>
                        <button type="button" class="button button-small bv-cd-quick-note-btn" data-project-id="<?php echo $p->id; ?>" data-project-number="<?php echo esc_attr( $p->project_number ); ?>" title="Quick note">📝</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>

        <!-- Quick Note Modal (Feature 10) -->
        <div id="bv-cd-note-modal" class="bv-cd-modal" style="display:none;">
            <div class="bv-cd-modal-backdrop"></div>
            <div class="bv-cd-modal-box">
                <h3>Quick Note — <span id="bv-cd-note-modal-pnum"></span></h3>
                <textarea id="bv-cd-quick-note-text" rows="4" style="width:100%" placeholder="Add a quick internal note..."></textarea>
                <div style="margin-top:10px;text-align:right;">
                    <button type="button" class="button" onclick="jQuery('#bv-cd-note-modal').hide()">Cancel</button>
                    <button type="button" id="bv-cd-save-quick-note" class="button button-primary">Save Note</button>
                </div>
            </div>
        </div>

        <?php
        // --- Sidebar Widgets (Features 15 & 16) ---
        $this->render_sidebar_widgets( $all_statuses, $base_url );
    }

    /**
     * Relative time helper (e.g. "2 hours ago", "3 days ago").
     */
    private function time_ago( $datetime ) {
        $diff = time() - strtotime( $datetime );
        if ( $diff < 60 ) return 'Just now';
        if ( $diff < 3600 ) return floor( $diff / 60 ) . 'm ago';
        if ( $diff < 86400 ) return floor( $diff / 3600 ) . 'h ago';
        if ( $diff < 604800 ) return floor( $diff / 86400 ) . 'd ago';
        return date( 'd M', strtotime( $datetime ) );
    }

    /**
     * Sidebar widgets: Stale Projects + Activity Timeline (Features 15 & 16).
     */
    private function render_sidebar_widgets( $all_statuses, $base_url ) {
        global $wpdb;
        ?>
        <div class="bv-cd-widgets-grid">
            <!-- Feature 15: Stale / Overdue Projects -->
            <div class="bv-cd-card bv-cd-widget">
                <h4>🔥 Needs Attention</h4>
                <div class="bv-cd-widget-list">
                <?php
                $stale = $wpdb->get_results( "
                    SELECT p.id, p.project_number, p.client_name, p.status, p.updated_at,
                        DATEDIFF(NOW(), p.updated_at) AS days_waiting
                    FROM {$wpdb->prefix}bv_projects p
                    WHERE p.status IN ('awaiting-agreement','awaiting-questionnaire','awaiting-documents')
                    ORDER BY p.updated_at ASC LIMIT 5
                " );
                if ( empty( $stale ) ) {
                    echo '<div class="bv-cd-widget-empty">All projects are moving ✅</div>';
                } else {
                    foreach ( $stale as $s ) {
                        $label = $all_statuses[ $s->status ] ?? $s->status;
                        $days = (int) $s->days_waiting;
                        $urgency = $days >= 14 ? 'bv-cd-urgent' : ( $days >= 7 ? 'bv-cd-warning' : '' );
                        echo '<a href="' . esc_url( $base_url . '&project_id=' . $s->id ) . '" class="bv-cd-widget-item ' . $urgency . '">';
                        echo '<strong>' . esc_html( $s->project_number ) . '</strong> ';
                        echo '<span class="bv-cd-widget-meta">' . esc_html( $s->client_name ) . ' · ' . esc_html( $label ) . '</span>';
                        echo '<span class="bv-cd-widget-days">' . $days . 'd waiting</span>';
                        echo '</a>';
                    }
                }
                ?>
                </div>
            </div>

            <!-- Feature 16: Activity Timeline -->
            <div class="bv-cd-card bv-cd-widget">
                <h4>📜 Recent Activity</h4>
                <div class="bv-cd-widget-list">
                <?php
                $recent = $wpdb->get_results( "
                    SELECT al.*, p.project_number, p.client_name
                    FROM {$wpdb->prefix}bv_activity_log al
                    JOIN {$wpdb->prefix}bv_projects p ON al.project_id = p.id
                    ORDER BY al.created_at DESC LIMIT 8
                " );
                if ( empty( $recent ) ) {
                    echo '<div class="bv-cd-widget-empty">No activity yet</div>';
                } else {
                    foreach ( $recent as $r ) {
                        echo '<a href="' . esc_url( $base_url . '&project_id=' . $r->project_id ) . '" class="bv-cd-widget-item">';
                        echo '<div class="bv-cd-timeline-dot"></div>';
                        echo '<div>';
                        echo '<div>' . esc_html( $r->description ) . '</div>';
                        echo '<span class="bv-cd-widget-meta">' . esc_html( $r->project_number ) . ' · ' . esc_html( $r->client_name ) . '</span>';
                        echo '</div></a>';
                    }
                }
                ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_project_detail( $project_id, $active_tab ) {
        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id ) );
        if ( ! $project ) { echo '<div class="notice notice-error"><p>Project not found.</p></div>'; return; }

        $services   = $wpdb->get_results( $wpdb->prepare( "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d", $project_id ) );
        $agreement  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_agreements WHERE project_id = %d ORDER BY id DESC LIMIT 1", $project_id ) );
        $documents  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_documents WHERE project_id = %d ORDER BY created_at DESC", $project_id ) );
        $reports    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_reports WHERE project_id = %d ORDER BY created_at DESC", $project_id ) );
        $messages   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_messages WHERE project_id = %d ORDER BY created_at ASC", $project_id ) );
        $notes      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_notes WHERE project_id = %d ORDER BY created_at DESC", $project_id ) );

        // Questionnaire responses — grouped by service
        $responses_raw = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.response_value, r.service_id, q.label, q.type, q.help_text,
                    qs.title as section_title, qs.display_order as section_order,
                    q.display_order as question_order,
                    COALESCE(s.name, 'General') as service_name
             FROM {$wpdb->prefix}bv_questionnaire_responses r
             JOIN {$wpdb->prefix}bv_questionnaire_questions q ON r.question_id = q.id
             JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON q.section_id = qs.id
             LEFT JOIN {$wpdb->prefix}bv_services s ON r.service_id = s.id
             WHERE r.project_id = %d
             ORDER BY COALESCE(s.name, 'zzz'), qs.display_order, q.display_order",
            $project_id ) );

        // Group responses by service, then by section
        $responses_by_service = array();
        foreach ( $responses_raw as $r ) {
            $sname = $r->service_name;
            if ( ! isset( $responses_by_service[ $sname ] ) ) {
                $responses_by_service[ $sname ] = array();
            }
            $responses_by_service[ $sname ][] = $r;
        }
        $has_multiple_services = count( $responses_by_service ) > 1;

        $back_url = admin_url( 'admin.php?page=bv-consultant-dashboard' );
        $statuses = array( 'awaiting-agreement', 'awaiting-questionnaire', 'awaiting-documents', 'in-progress', 'quality-check', 'completed', 'delivered', 'archived' );
        $cd_settings = BV_Settings::get_settings();
        $show_messages = $cd_settings['cd_show_messages'] === 'yes';
        $show_notes = $cd_settings['cd_show_notes'] === 'yes';
        $show_activity = $cd_settings['cd_show_activity_log'] === 'yes';
        ?>
        <div class="bv-cd-back"><a href="<?php echo $back_url; ?>">&larr; Back to All Projects</a></div>

        <!-- Project Header -->
        <div class="bv-cd-project-header">
            <div>
                <h2><?php echo esc_html( $project->project_number ); ?></h2>
                <p>Client: <strong><?php echo esc_html( $project->client_name ); ?></strong> — <?php echo esc_html( $project->client_email ); ?><?php if ($project->client_company) echo ' — ' . esc_html($project->client_company); ?></p>
                <?php if ($project->client_phone) echo '<p>Phone: ' . esc_html($project->client_phone) . '</p>'; ?>
            </div>
            <div class="bv-cd-project-controls">
                <div class="bv-cd-status-select">
                    <label>Status:</label>
                    <select class="bv-cd-status-update" data-project-id="<?php echo $project_id; ?>">
                        <?php foreach ($statuses as $s) : ?>
                        <option value="<?php echo $s; ?>" <?php selected($project->status, $s); ?>><?php echo ucfirst(str_replace('-',' ',$s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bv-cd-progress-control">
                    <label>Progress: <span class="bv-cd-progress-display"><?php echo $project->progress_percent; ?>%</span></label>
                    <input type="range" min="0" max="100" value="<?php echo $project->progress_percent; ?>" class="bv-cd-progress-input" data-project-id="<?php echo $project_id; ?>" />
                </div>
                <?php if ($project->wc_order_id) : ?>
                <a href="<?php echo admin_url('post.php?post=' . $project->wc_order_id . '&action=edit'); ?>" class="button">View Order #<?php echo $project->wc_order_id; ?></a>
                <?php endif; ?>
                <button type="button" id="bv-cd-reset-project" class="button" style="color:#dc2626;border-color:#dc2626;margin-left:8px;" data-project-id="<?php echo $project_id; ?>" data-project-number="<?php echo esc_attr( $project->project_number ); ?>">&#x21bb; Reset Project</button>
                <button type="button" id="bv-cd-remove-project" class="button" style="color:#fff;background:#dc2626;border-color:#dc2626;margin-left:8px;" data-project-id="<?php echo $project_id; ?>" data-project-number="<?php echo esc_attr( $project->project_number ); ?>">&#128465; Remove Project</button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bv-cd-tabs">
            <button class="bv-cd-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" data-tab="overview">Overview</button>
            <button class="bv-cd-tab <?php echo $active_tab === 'agreement' ? 'active' : ''; ?>" data-tab="agreement">Agreement</button>
            <button class="bv-cd-tab <?php echo $active_tab === 'questionnaire' ? 'active' : ''; ?>" data-tab="questionnaire">Questionnaire</button>
            <button class="bv-cd-tab <?php echo $active_tab === 'documents' ? 'active' : ''; ?>" data-tab="documents">Documents</button>
            <button class="bv-cd-tab <?php echo $active_tab === 'reports' ? 'active' : ''; ?>" data-tab="reports">Reports</button>
            <?php if ( $show_messages ) : ?>
            <button class="bv-cd-tab <?php echo $active_tab === 'messages' ? 'active' : ''; ?>" data-tab="messages">Messages</button>
            <?php endif; ?>
            <?php if ( $show_notes ) : ?>
            <button class="bv-cd-tab <?php echo $active_tab === 'notes' ? 'active' : ''; ?>" data-tab="notes">Notes</button>
            <?php endif; ?>
        </div>

        <!-- Tab Panels -->
        <div id="bv-cd-panel-overview" class="bv-cd-panel" style="<?php echo $active_tab === 'overview' ? '' : 'display:none'; ?>">
            <div class="bv-cd-overview-grid">
                <div class="bv-cd-card">
                    <h4>Services</h4>
                    <ul><?php foreach ($services as $s) echo '<li>' . esc_html($s->name) . ' — R' . esc_html($s->price) . '</li>'; ?></ul>
                </div>
                <div class="bv-cd-card">
                    <h4>Internal Notes</h4>
                    <textarea id="bv-cd-internal-notes" rows="8" class="large-text"><?php echo esc_textarea($project->internal_notes); ?></textarea>
                    <br><button id="bv-cd-save-notes" class="button button-primary" data-project-id="<?php echo $project_id; ?>">Save Notes</button>
                </div>
                <div class="bv-cd-card">
                    <h4>Client Notes (visible to client)</h4>
                    <p><?php echo nl2br(esc_html($project->notes)) ?: '<em>No notes yet</em>'; ?></p>
                </div>
            </div>
        </div>

        <div id="bv-cd-panel-agreement" class="bv-cd-panel" style="<?php echo $active_tab === 'agreement' ? '' : 'display:none'; ?>">
            <?php if ($agreement) : ?>
            <div class="bv-cd-card" style="background:#D5F5E3; border-color:#27AE60;">
                <h4>✓ Agreement Signed</h4>
                <p>By <strong><?php echo esc_html($agreement->full_name); ?></strong> on <?php echo esc_html(date('d M Y H:i', strtotime($agreement->agreed_at))); ?></p>
                <p>IP: <?php echo esc_html($agreement->ip_address); ?></p>
            </div>
            <div class="bv-cd-card"><h4>Agreement Content</h4><div style="max-height:400px;overflow:auto;font-size:13px;line-height:1.6;"><?php echo wp_kses_post($agreement->template_content); ?></div></div>
            <?php else : ?>
            <div class="bv-cd-card" style="background:#FFF3CD; border-color:#FFC107;"><p>⚠️ Client has not signed the agreement yet.</p></div>
            <?php endif; ?>
        </div>

        <div id="bv-cd-panel-questionnaire" class="bv-cd-panel" style="<?php echo $active_tab === 'questionnaire' ? '' : 'display:none'; ?>">
            <?php if ( ! empty( $responses_by_service ) ) : ?>
            <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="button button-primary" onclick="bv_cd_download_questionnaire_html(<?php echo $project_id; ?>)">📄 <?php echo esc_html__( 'Open Report (PDF)', 'businessvance-services-manager' ); ?></button>
                <button type="button" class="button button-secondary" onclick="bv_cd_download_questionnaire(<?php echo $project_id; ?>)">⬇ <?php echo esc_html__( 'Download Data (CSV)', 'businessvance-services-manager' ); ?></button>
            </div>
            <?php endif; ?>
            <?php if ( empty( $responses_by_service ) ) : ?>
            <div class="bv-cd-card"><p><?php echo esc_html__( 'No questionnaire responses submitted yet.', 'businessvance-services-manager' ); ?></p></div>
            <?php else : ?>
                <?php foreach ( $responses_by_service as $service_name => $service_responses ) : ?>
                <?php if ( $has_multiple_services ) : ?>
                <div class="bv-cd-service-group-header"><?php echo esc_html( $service_name ); ?></div>
                <?php endif; ?>
                <table class="widefat striped bv-cd-table" style="margin-bottom:20px;">
                    <thead><tr>
                        <?php if ( ! $has_multiple_services ) : ?><th><?php echo esc_html__( 'Service', 'businessvance-services-manager' ); ?></th><?php endif; ?>
                        <th><?php echo esc_html__( 'Section', 'businessvance-services-manager' ); ?></th>
                        <th><?php echo esc_html__( 'Question', 'businessvance-services-manager' ); ?></th>
                        <th><?php echo esc_html__( 'Response', 'businessvance-services-manager' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php
                    $current_section = '';
                    $section_count = 0;
                    foreach ( $service_responses as $r ) :
                        $section_count++;
                        $val = $r->response_value;
                        // Decode JSON values for display
                        $display_val = $val;
                        $is_html = false;  // flag: output contains safe HTML (multifile links)
                        $json_val = json_decode( $val, true );
                        // Check if it's a data URL (signature)
                        if ( preg_match( '/^data:image/', $val ) ) {
                            $display_val = '✍️ [Signature provided]';
                        }
                        // Check multifile JSON with URLs — BEFORE repeatable table check
                        // (multifile is also an array of arrays, but has 'url' key)
                        elseif ( $json_val && isset( $json_val[0] ) && isset( $json_val[0]['url'] ) ) {
                            $display_val = '';
                            $is_html = true;
                            $nonce = wp_create_nonce( 'bv_consultant_dashboard' );
                            foreach ( $json_val as $f ) {
                                $display_val .= '<div style="margin-bottom:4px;">' . esc_html( $f['name'] ?? 'File' );
                                if ( ! empty( $f['size'] ) ) $display_val .= ' <small>(' . esc_html( $f['size'] ) . ')</small>';
                                if ( ! empty( $f['file'] ) ) {
                                    $dl_url = admin_url( 'admin-ajax.php?action=bv_cd_download_qfile&nonce=' . $nonce . '&project_id=' . $project_id . '&file=' . rawurlencode( $f['file'] ) );
                                    $display_val .= ' — <a href="' . esc_url( $dl_url ) . '" class="button button-small" style="margin-left:4px;">⬇ Download</a>';
                                } elseif ( ! empty( $f['url'] ) ) {
                                    $display_val .= ' — <a href="' . esc_url( $f['url'] ) . '" target="_blank" class="button button-small" style="margin-left:4px;">⬇ Open</a>';
                                }
                                $display_val .= '</div>';
                            }
                        }
                        elseif ( is_array( $json_val ) ) {
                            if ( isset( $json_val[0] ) && is_array( $json_val[0] ) ) {
                                // Repeatable table: 2D array
                                $display_val = '';
                                foreach ( $json_val as $row ) {
                                    $display_val .= implode( ' | ', $row ) . "\n";
                                }
                            } else {
                                // Checkbox or simple array
                                $display_val = implode( ', ', $json_val );
                            }
                        }
                    ?>
                    <tr>
                        <?php if ( ! $has_multiple_services ) : ?><td><small><?php echo esc_html( $r->service_name ); ?></small></td><?php endif; ?>
                        <td><small><?php echo esc_html( $r->section_title ); ?></small></td>
                        <td><?php echo esc_html( $r->label ); ?><?php if ( $r->help_text ) : ?><br><small style="color:#888;"><?php echo esc_html( $r->help_text ); ?></small><?php endif; ?></td>
                        <td><?php echo $is_html ? $display_val : nl2br( esc_html( $display_val ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="bv-cd-panel-documents" class="bv-cd-panel" style="<?php echo $active_tab === 'documents' ? '' : 'display:none'; ?>">
            <?php if (empty($documents)) : ?>
            <div class="bv-cd-card"><p>No documents uploaded.</p></div>
            <?php else : ?>
            <table class="widefat striped bv-cd-table">
                <thead><tr><th>Document</th><th>Category</th><th>Uploaded By</th><th>Date</th><th>Size</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($documents as $d) : ?>
                <tr>
                    <td><?php echo esc_html($d->name); ?></td>
                    <td><?php echo esc_html(ucfirst(str_replace('-',' ',$d->category))); ?></td>
                    <td><?php echo esc_html($d->uploaded_by); ?></td>
                    <td><?php echo esc_html(date('d M Y H:i', strtotime($d->created_at))); ?></td>
                    <td><?php echo esc_html(size_format($d->filesize)); ?></td>
                    <td><a href="<?php echo admin_url('admin-ajax.php?action=bv_cd_download_document&nonce=' . wp_create_nonce('bv_consultant_dashboard') . '&document_id=' . $d->id); ?>" class="button button-small">Download</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div id="bv-cd-panel-reports" class="bv-cd-panel" style="<?php echo $active_tab === 'reports' ? '' : 'display:none'; ?>">
            <div class="bv-cd-card">
                <h4>Upload Report</h4>
                <p><input id="bv-cd-report-title" type="text" placeholder="Report title (e.g., Business Feasibility Report)" class="regular-text" /></p>
                <p><input id="bv-cd-report-file" type="file" accept=".pdf,.doc,.docx" /></p>
                <p><button id="bv-cd-upload-report" class="button button-primary" data-project-id="<?php echo $project_id; ?>">Upload Report</button></p>
            </div>
            <?php if (!empty($reports)) : ?>
            <table class="widefat striped bv-cd-table" style="margin-top:16px;">
                <thead><tr><th>Title</th><th>Version</th><th>Status</th><th>Uploaded</th><th>Delivered</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($reports as $r) : ?>
                <tr>
                    <td><?php echo esc_html($r->title); ?></td>
                    <td>v<?php echo esc_html($r->version); ?></td>
                    <td><span class="bv-status bv-status-<?php echo $r->status === 'delivered' ? 'delivered' : ($r->status === 'ready' ? 'in-progress' : 'awaiting-agreement'); ?>"><?php echo ucfirst($r->status); ?></span></td>
                    <td><?php echo esc_html(date('d M Y H:i', strtotime($r->created_at))); ?></td>
                    <td><?php echo $r->delivered_at ? esc_html(date('d M Y H:i', strtotime($r->delivered_at))) : '—'; ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin-ajax.php?action=bv_cd_download_report&nonce=' . wp_create_nonce('bv_consultant_dashboard') . '&report_id=' . $r->id); ?>" class="button button-small" title="Preview/Download report" target="_blank">👁 Preview</a>
                        <?php if ($r->status !== 'delivered') : ?>
                        <button class="button button-small bv-cd-deliver-report" data-report-id="<?php echo $r->id; ?>">✓ Deliver</button>
                        <?php else : ?>
                        <span style="color:#27AE60;">✓ Delivered</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div id="bv-cd-panel-messages" class="bv-cd-panel" style="<?php echo $active_tab === 'messages' ? '' : 'display:none'; ?>">
            <div class="bv-cd-msg-thread" id="bv-cd-msg-thread">
                <?php foreach ($messages as $m) : ?>
                <div class="bv-cd-msg bv-cd-msg-<?php echo esc_attr($m->sender_type); ?>">
                    <strong><?php echo esc_html($m->sender_name); ?></strong>
                    <span><?php echo esc_html(date('d M Y H:i', strtotime($m->created_at))); ?></span>
                    <?php if ($m->sender_type === 'client' && !$m->is_read) : ?><span style="color:#DC3545; font-weight:600; font-size:11px;">NEW</span><?php endif; ?>
                    <p><?php echo nl2br(esc_html($m->message)); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="bv-cd-msg-form">
                <textarea id="bv-cd-msg-text" rows="3" placeholder="Type a message to the client..." class="large-text"></textarea>
                <button id="bv-cd-send-msg" class="button button-primary" data-project-id="<?php echo $project_id; ?>">Send Message</button>
            </div>
        </div>

        <div id="bv-cd-panel-notes" class="bv-cd-panel" style="<?php echo $active_tab === 'notes' ? '' : 'display:none'; ?>">
            <div class="bv-cd-card">
                <h4>Add Note</h4>
                <textarea id="bv-cd-note-content" rows="3" class="large-text" placeholder="Add an internal note..."></textarea>
                <p><button id="bv-cd-add-note" class="button button-primary" data-project-id="<?php echo $project_id; ?>">Add Note</button></p>
            </div>
            <div id="bv-cd-notes-list" style="margin-top:16px;">
                <?php foreach ($notes as $n) : ?>
                <div class="bv-cd-note">
                    <strong><?php echo esc_html($n->author_name); ?></strong>
                    <span class="bv-cd-note-time"><?php echo esc_html(date('d M Y H:i', strtotime($n->created_at))); ?></span>
                    <p><?php echo nl2br(esc_html($n->content)); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    // ============================
    // AJAX Handlers
    // ============================

    public function ajax_get_projects() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $filter_status = isset( $_POST['filter_status'] ) ? sanitize_text_field( $_POST['filter_status'] ) : '';
        $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
        $page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $offset = ( $page - 1 ) * $per_page;

        $where = '1=1';
        if ( $filter_status ) {
            $where .= $wpdb->prepare( ' AND p.status = %s', $filter_status );
        }
        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( ' AND (p.project_number LIKE %s OR p.client_name LIKE %s OR p.client_email LIKE %s OR p.client_company LIKE %s)', $like, $like, $like, $like );
        }

        $projects = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.* FROM {$wpdb->prefix}bv_projects p WHERE {$where} ORDER BY p.created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects p WHERE {$where}" );

        wp_send_json_success( array(
            'projects' => $projects,
            'total'    => $total,
            'pages'    => ceil( $total / $per_page ),
        ) );
    }

    public function ajax_get_project_detail() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $pid = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
        if ( ! $pid ) {
            wp_send_json_error( 'Invalid project ID' );
        }

        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $pid ) );
        if ( ! $project ) {
            wp_send_json_error( 'Project not found' );
        }

        $services  = $wpdb->get_results( $wpdb->prepare( "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d", $pid ) );
        $agreement = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_agreements WHERE project_id = %d ORDER BY id DESC LIMIT 1", $pid ) );
        $documents = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_documents WHERE project_id = %d ORDER BY created_at DESC", $pid ) );
        $reports   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_reports WHERE project_id = %d ORDER BY created_at DESC", $pid ) );
        $messages  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_messages WHERE project_id = %d ORDER BY created_at ASC", $pid ) );
        $notes     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_notes WHERE project_id = %d ORDER BY created_at DESC", $pid ) );
        $responses = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.response_value, r.service_id, q.label, q.type, q.help_text,
                    qs.title as section_title, qs.display_order as section_order,
                    q.display_order as question_order,
                    COALESCE(s.name, 'General') as service_name
             FROM {$wpdb->prefix}bv_questionnaire_responses r
             JOIN {$wpdb->prefix}bv_questionnaire_questions q ON r.question_id = q.id
             JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON q.section_id = qs.id
             LEFT JOIN {$wpdb->prefix}bv_services s ON r.service_id = s.id
             WHERE r.project_id = %d
             ORDER BY COALESCE(s.name, 'zzz'), qs.display_order, q.display_order", $pid ) );

        wp_send_json_success( array(
            'project'   => $project,
            'services'  => $services,
            'agreement' => $agreement,
            'documents' => $documents,
            'reports'   => $reports,
            'messages'  => $messages,
            'notes'     => $notes,
            'responses' => $responses,
        ) );
    }

    public function ajax_update_project_status() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $pid = absint( $_POST['project_id'] );
        $status = sanitize_text_field( $_POST['status'] );

        // Validate status against whitelist
        $allowed_statuses = array( 'pending', 'agreement', 'questionnaire', 'documents', 'in-progress', 'review', 'delivered', 'completed', 'cancelled',
            'awaiting-agreement', 'awaiting-questionnaire', 'awaiting-documents', 'quality-check', 'archived' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            wp_send_json_error( 'Invalid status value' );
        }

        global $wpdb;
        $wpdb->suppress_errors( true );
        $updated = $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'status' => $status ), array( 'id' => $pid ), array( '%s' ), array( '%d' ) );
        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array( 'project_id' => $pid, 'entity_type' => 'project', 'entity_id' => $pid, 'action' => 'status_changed', 'description' => "Status changed to {$status}", 'metadata' => '', 'user_id' => get_current_user_id() ), array( '%d','%s','%d','%s','%s','%s','%d' ) );
        $wpdb->suppress_errors( false );
        if ( $updated === false ) {
            wp_send_json_error( 'Database error: ' . $wpdb->last_error );
        }
        if ( $updated === 0 ) {
            wp_send_json_error( 'No rows updated. PID=' . $pid . ' Status=' . $status );
        }
        wp_send_json_success( 'Status updated to ' . $status );
    }

    public function ajax_update_progress() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $pid = absint( $_POST['project_id'] );
        $progress = max(0, min(100, absint( $_POST['progress'] )));
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'progress_percent' => $progress ), array( 'id' => $pid ), array( '%d' ), array( '%d' ) );
        wp_send_json_success();
    }

    /**
     * Reset a project so the client can redo all requirements.
     * Clears: questionnaire responses, signed agreement, uploaded documents,
     * delivered reports. Resets status to awaiting-agreement and progress to 0.
     *
     * @since 2.7.23
     * @return void
     */
    public function ajax_reset_project() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $pid = absint( $_POST['project_id'] );
        if ( ! $pid ) {
            wp_send_json_error( 'Invalid project ID' );
        }

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $pid ) );
        if ( ! $project ) {
            wp_send_json_error( 'Project not found' );
        }

        $p = $wpdb->prefix;
        $deleted_counts = array();
        $upload_dir = wp_upload_dir();
        $bv_docs_dir = $upload_dir['basedir'] . '/bv-documents/';

        // 1. Delete questionnaire multifile attachments (stored in response JSON)
        $deleted_counts['multifiles'] = 0;
        $q_responses = $wpdb->get_results( $wpdb->prepare( "SELECT response_value FROM {$p}bv_questionnaire_responses WHERE project_id = %d", $pid ) );
        foreach ( $q_responses as $qr ) {
            $json = json_decode( $qr->response_value, true );
            if ( is_array( $json ) && isset( $json[0] ) && isset( $json[0]['file'] ) ) {
                foreach ( $json as $f ) {
                    if ( ! empty( $f['file'] ) ) {
                        $fpath = $bv_docs_dir . $f['file'];
                        if ( file_exists( $fpath ) ) {
                            @unlink( $fpath );
                            $deleted_counts['multifiles']++;
                        }
                    }
                }
            }
        }
        // Now delete the response rows
        $deleted_counts['responses'] = (int) $wpdb->delete( $p . 'bv_questionnaire_responses', array( 'project_id' => $pid ), array( '%d' ) );

        // 2. Delete signed agreements
        $deleted_counts['agreements'] = (int) $wpdb->delete( $p . 'bv_project_agreements', array( 'project_id' => $pid ), array( '%d' ) );

        // 3. Delete uploaded documents (and their files)
        $docs = $wpdb->get_results( $wpdb->prepare( "SELECT id, filepath FROM {$p}bv_project_documents WHERE project_id = %d", $pid ) );
        $deleted_counts['documents'] = 0;
        foreach ( $docs as $doc ) {
            if ( ! empty( $doc->filepath ) && file_exists( $doc->filepath ) ) {
                @unlink( $doc->filepath );
            }
            $deleted_counts['documents']++;
        }
        $wpdb->delete( $p . 'bv_project_documents', array( 'project_id' => $pid ), array( '%d' ) );

        // 4. Delete delivered reports (and their files)
        $reports = $wpdb->get_results( $wpdb->prepare( "SELECT id, filepath FROM {$p}bv_project_reports WHERE project_id = %d", $pid ) );
        $deleted_counts['reports'] = 0;
        foreach ( $reports as $rpt ) {
            if ( ! empty( $rpt->filepath ) && file_exists( $rpt->filepath ) ) {
                @unlink( $rpt->filepath );
            }
            $deleted_counts['reports']++;
        }
        $wpdb->delete( $p . 'bv_project_reports', array( 'project_id' => $pid ), array( '%d' ) );

        // 5. Reset project status and progress
        $wpdb->update( $p . 'bv_projects',
            array( 'status' => 'awaiting-agreement', 'progress_percent' => 0 ),
            array( 'id' => $pid ),
            array( '%s', '%d' ), array( '%d' )
        );

        // 6. Log the reset
        $desc = sprintf(
            'Project reset by consultant. Cleared: %d response(s), %d agreement(s), %d document(s), %d report(s), %d multifile(s).',
            $deleted_counts['responses'],
            $deleted_counts['agreements'],
            $deleted_counts['documents'],
            $deleted_counts['reports'],
            $deleted_counts['multifiles']
        );
        $wpdb->insert( $p . 'bv_activity_log', array(
            'project_id'  => $pid,
            'entity_type' => 'project',
            'entity_id'   => $pid,
            'action'      => 'reset',
            'description' => $desc,
            'metadata'    => '',
            'user_id'     => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%s', '%d' ) );

        wp_send_json_success( $desc );
    }

    /**
     * Permanently remove a project and all associated data.
     * Action name uses "remove" instead of "delete" to avoid security plugin
     * firewall rules that block requests containing "delete".
     */
    public function ajax_delete_project() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $pid = absint( $_POST['project_id'] );
        if ( ! $pid ) {
            wp_send_json_error( 'Invalid project ID' );
        }

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $pid ) );
        if ( ! $project ) {
            wp_send_json_error( 'Project not found' );
        }

        $p = $wpdb->prefix;
        $upload_dir = wp_upload_dir();
        $bv_docs_dir = $upload_dir['basedir'] . '/bv-documents/';

        $wpdb->suppress_errors( true );

        // 1. Delete questionnaire multifile attachments
        $q_responses = $wpdb->get_results( $wpdb->prepare( "SELECT response_value FROM {$p}bv_questionnaire_responses WHERE project_id = %d", $pid ) );
        $mf_count = 0;
        foreach ( $q_responses as $qr ) {
            $json = json_decode( $qr->response_value, true );
            if ( is_array( $json ) && isset( $json[0] ) && isset( $json[0]['file'] ) ) {
                foreach ( $json as $f ) {
                    if ( ! empty( $f['file'] ) && file_exists( $bv_docs_dir . $f['file'] ) ) {
                        @unlink( $bv_docs_dir . $f['file'] );
                        $mf_count++;
                    }
                }
            }
        }

        // 2. Delete uploaded document files
        $docs = $wpdb->get_results( $wpdb->prepare( "SELECT filepath FROM {$p}bv_project_documents WHERE project_id = %d", $pid ) );
        $doc_count = 0;
        foreach ( $docs as $doc ) {
            if ( ! empty( $doc->filepath ) && file_exists( $doc->filepath ) ) {
                @unlink( $doc->filepath );
            }
            $doc_count++;
        }

        // 3. Delete report files
        $reports = $wpdb->get_results( $wpdb->prepare( "SELECT filepath FROM {$p}bv_project_reports WHERE project_id = %d", $pid ) );
        $rpt_count = 0;
        foreach ( $reports as $rpt ) {
            if ( ! empty( $rpt->filepath ) && file_exists( $rpt->filepath ) ) {
                @unlink( $rpt->filepath );
            }
            $rpt_count++;
        }

        // 4. Delete all related database rows
        $wpdb->delete( $p . 'bv_questionnaire_responses',  array( 'project_id' => $pid ), array( '%d' ) );
        $wpdb->delete( $p . 'bv_project_agreements',       array( 'project_id' => $pid ), array( '%d' ) );
        $wpdb->delete( $p . 'bv_project_documents',        array( 'project_id' => $pid ), array( '%d' ) );
        $wpdb->delete( $p . 'bv_project_reports',          array( 'project_id' => $pid ), array( '%d' ) );
        $wpdb->delete( $p . 'bv_project_messages',         array( 'project_id' => $pid ), array( '%d' ) );
        $wpdb->delete( $p . 'bv_project_notes',            array( 'project_id' => $pid ), array( '%d' ) );
        $wpdb->delete( $p . 'bv_project_services',         array( 'project_id' => $pid ), array( '%d' ) );
        $wpdb->delete( $p . 'bv_activity_log',             array( 'project_id' => $pid ), array( '%d' ) );

        // 5. Delete the project itself
        $deleted = $wpdb->delete( $p . 'bv_projects', array( 'id' => $pid ), array( '%d' ) );

        $wpdb->suppress_errors( false );

        if ( $deleted === false ) {
            wp_send_json_error( 'Database error deleting project: ' . $wpdb->last_error );
        }
        if ( $deleted === 0 ) {
            wp_send_json_error( 'Project was not found in database (PID=' . $pid . ')' );
        }

        wp_send_json_success( 'Project deleted.' );
    }

    public function ajax_upload_report() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Access denied' );

        $pid = absint( $_POST['project_id'] );
        $title = sanitize_text_field( $_POST['title'] );
        if ( empty( $title ) || empty( $_FILES['file'] ) ) wp_send_json_error( 'Title and file required' );

        $file = $_FILES['file'];
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $allowed = array( 'pdf', 'doc', 'docx' );
        if ( ! in_array( $ext, $allowed ) ) wp_send_json_error( 'File type not allowed (PDF, DOC, DOCX only)' );

        $filename = 'report_' . $pid . '_' . time() . '_' . sanitize_file_name( $file['name'] );
        $upload_path = BV_UPLOAD_DIR . '/' . $filename;
        if ( ! move_uploaded_file( $file['tmp_name'], $upload_path ) ) wp_send_json_error( 'Upload failed' );

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'bv_project_reports', array(
            'project_id' => $pid, 'service_id' => 0, 'title' => $title, 'filename' => $filename,
            'filepath' => $upload_path, 'filesize' => $file['size'], 'mime_type' => $file['type'],
            'status' => 'draft', 'version' => '1.0',
        ), array( '%d','%d','%s','%s','%s','%d','%s','%s','%s' ) );

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $pid, 'entity_type' => 'report', 'entity_id' => $wpdb->insert_id,
            'action' => 'uploaded', 'description' => "Report uploaded: {$title}", 'metadata' => '', 'user_id' => get_current_user_id(),
        ), array( '%d','%s','%d','%s','%s','%s','%d' ) );

        wp_send_json_success( 'Report uploaded' );
    }

    public function ajax_deliver_report() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        global $wpdb;
        $rid = absint( $_POST['report_id'] );
        $wpdb->update( $wpdb->prefix . 'bv_project_reports',
            array( 'status' => 'delivered', 'delivered_at' => current_time('mysql') ),
            array( 'id' => $rid ), array( '%s', '%s' ), array( '%d' ) );

        $report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_reports WHERE id = %d", $rid ) );
        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $report->project_id, 'entity_type' => 'report', 'entity_id' => $rid,
            'action' => 'delivered', 'description' => "Report delivered: {$report->title}", 'metadata' => '', 'user_id' => get_current_user_id(),
        ), array( '%d','%s','%d','%s','%s','%s','%d' ) );

        wp_send_json_success( 'Report delivered' );
    }

    public function ajax_send_message() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $pid = absint( $_POST['project_id'] );
        $message = sanitize_textarea_field( $_POST['message'] );
        if ( empty( $message ) ) wp_send_json_error( 'Message required' );

        $user = wp_get_current_user();
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'bv_project_messages', array(
            'project_id' => $pid, 'sender_type' => 'admin', 'sender_name' => $user->display_name,
            'sender_email' => $user->user_email, 'message' => $message, 'is_read' => 0,
        ), array( '%d','%s','%s','%s','%s','%d' ) );

        // Notify client via email
        $this->notify_client_new_message( $pid, $user->display_name, $message );

        wp_send_json_success();
    }

    /**
     * Notify client when consultant sends a new message.
     *
     * @since 2.6.0
     * @param int    $project_id
     * @param string $sender_name
     * @param string $message
     * @return void
     */
    private function notify_client_new_message( $project_id, $sender_name, $message ) {
        $settings = BV_Settings::get_settings();
        if ( ( $settings['email_message_to_client'] ?? 'yes' ) !== 'yes' ) {
            return;
        }

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id ) );
        if ( ! $project ) return;

        $client_email = $project->client_email;
        if ( empty( $client_email ) ) return;

        $company_name = $settings['company_name'] ?? 'BusinessVance';
        $portal_url   = $settings['portal_url'] ?? site_url();

        // Build subject
        $subject = $settings['email_message_to_client_subject'] ?? 'New Message from {sender_name} - {project_number}';
        $subject = str_replace(
            array( '{sender_name}', '{project_number}', '{client_name}' ),
            array( $sender_name, $project->project_number, $project->client_name ),
            $subject
        );

        // Build body
        $body = $settings['email_message_to_client_body'] ?? '';
        $body = str_replace(
            array( '{client_name}', '{sender_name}', '{project_number}', '{message}', '{portal_url}', '{company_name}' ),
            array( $project->client_name, $sender_name, $project->project_number, $message, $portal_url, $company_name ),
            $body
        );

        $from_email = $settings['consultant_email'] ?? get_option( 'admin_email' );
        $headers = array( 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $company_name . ' <' . $from_email . '>' );
        wp_mail( $client_email, $subject, $body, $headers );

        // Log activity
        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id'  => $project_id,
            'entity_type' => 'project',
            'entity_id'   => $project_id,
            'action'      => 'message_sent',
            'description' => 'Consultant message notification sent to client (' . $project->client_email . ')',
            'metadata'    => '',
            'user_id'     => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%s', '%d' ) );
    }

    public function ajax_add_note() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $pid = absint( $_POST['project_id'] );
        $content = sanitize_textarea_field( $_POST['content'] );
        if ( empty( $content ) ) wp_send_json_error( 'Note required' );

        $user = wp_get_current_user();
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'bv_project_notes', array(
            'project_id' => $pid, 'author_name' => $user->display_name, 'content' => $content,
        ), array( '%d','%s','%s' ) );

        wp_send_json_success();
    }

    public function ajax_update_internal_notes() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $pid = absint( $_POST['project_id'] );
        $notes = sanitize_textarea_field( $_POST['notes'] );
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'internal_notes' => $notes ), array( 'id' => $pid ), array( '%s' ), array( '%d' ) );
        wp_send_json_success();
    }

    public function ajax_get_messages() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $pid = absint( $_GET['project_id'] ?? $_POST['project_id'] ?? 0 );
        global $wpdb;
        $messages = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_project_messages WHERE project_id = %d ORDER BY created_at ASC LIMIT 50", $pid ) );
        wp_send_json_success( $messages );
    }

    public function ajax_download_document() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_die( 'Access denied' );
        $doc_id = absint( $_GET['document_id'] ?? 0 );
        global $wpdb;
        $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_documents WHERE id = %d", $doc_id ) );
        if ( ! $doc || ! file_exists( $doc->filepath ) ) wp_die( 'Document not found' );
        // Validate MIME type against whitelist
        $allowed_mime_types = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/csv', 'text/plain', 'application/zip', 'application/octet-stream' );
        $mime = in_array( $doc->mime_type, $allowed_mime_types, true ) ? $doc->mime_type : 'application/octet-stream';
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . basename( $doc->filename ) . '"' );
        header( 'Content-Length: ' . $doc->filesize );
        readfile( $doc->filepath );
        exit;
    }

    public function ajax_download_report() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_die( 'Access denied' );
        $report_id = absint( $_GET['report_id'] ?? $_POST['report_id'] ?? 0 );
        if ( ! $report_id ) wp_die( 'Invalid report' );
        
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_reports WHERE id = %d", $report_id ) );
        if ( ! $report || ! file_exists( $report->filepath ) ) {
            wp_die( 'Report not found' );
        }
        
        // Validate MIME type against whitelist
        $allowed_mime_types = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/csv', 'text/plain', 'application/zip', 'application/octet-stream' );
        $mime = in_array( $report->mime_type, $allowed_mime_types, true ) ? $report->mime_type : 'application/octet-stream';
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: inline; filename="' . basename( $report->filename ) . '"' );
        header( 'Content-Length: ' . $report->filesize );
        readfile( $report->filepath );
        exit;
    }

    public function ajax_create_project() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_send_json_error( 'Access denied' );

        global $wpdb;
        $name = sanitize_text_field( $_POST['client_name'] );
        $email = sanitize_email( $_POST['client_email'] );
        if ( empty( $name ) || empty( $email ) ) wp_send_json_error( 'Name and email required' );

        // Generate project number with retry for race condition
        $projects_table = $wpdb->prefix . 'bv_projects';
        $year = date( 'Y' );
        $project_number = '';
        $max_retries = 3;

        for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
            $last = $wpdb->get_var( $wpdb->prepare(
                "SELECT project_number FROM {$projects_table} WHERE project_number LIKE %s ORDER BY project_number DESC LIMIT 1",
                'BV-' . $year . '-%'
            ) );
            $next = $last ? (int) end( explode( '-', $last ) ) + 1 : 1;
            $project_number = 'BV-' . $year . '-' . str_pad( $next, 6, '0', STR_PAD_LEFT );

            $wpdb->insert( $projects_table, array(
            'project_number' => $project_number, 'client_user_id' => 0,
            'client_name' => $name, 'client_email' => $email,
            'client_phone' => sanitize_text_field( $_POST['client_phone'] ?? '' ),
            'client_company' => sanitize_text_field( $_POST['client_company'] ?? '' ),
            'wc_order_id' => 0, 'status' => 'awaiting-agreement', 'progress_percent' => 0,
            'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ), array( '%s','%d','%s','%s','%s','%s','%d','%s','%d','%s' ) );

            if ( $wpdb->insert_id ) {
                break; // Success, exit retry loop
            }
            // Check if it was a duplicate key error, otherwise break
            if ( empty( $wpdb->last_error ) || strpos( $wpdb->last_error, 'Duplicate' ) === false ) {
                break;
            }
        }

        if ( ! $wpdb->insert_id ) {
            wp_send_json_error( 'Failed to create project. Please try again.' );
        }

        wp_send_json_success( array( 'project_id' => $wpdb->insert_id, 'project_number' => $project_number ) );
    }

    /**
     * Build the complete questionnaire report HTML body for a project.
     * Queries ALL questions (including display-only types) and renders every field.
     * Reusable by both the download handler and the email-on-completion system.
     *
     * @since 2.7.23
     * @param int  $project_id  The project ID.
     * @param bool $for_email   If true, omits print bar and interactive elements.
     * @return string Complete HTML document.
     */
    public function build_questionnaire_report_html( $project_id, $for_email = false ) {
        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id
        ) );
        if ( ! $project ) return '<p>Project not found.</p>';

        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.name, s.price FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id
             WHERE ps.project_id = %d ORDER BY s.name",
            $project_id
        ) );
        $service_names = ! empty( $services ) ? wp_list_pluck( $services, 'name' ) : array();
        $service_ids  = ! empty( $services ) ? wp_list_pluck( $services, 'id' ) : array();

        // --- Get all template IDs linked to this project's services ---
        $template_ids = array();
        if ( ! empty( $service_ids ) ) {
            $ph = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            // From junction table
            $junction_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT questionnaire_template_id FROM {$wpdb->prefix}bv_service_questionnaires WHERE service_id IN ($ph)",
                ...$service_ids
            ) );
            $template_ids = array_merge( $template_ids, array_map( 'absint', $junction_ids ) );
            // Legacy: service.questionnaire_template_id
            $legacy_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT questionnaire_template_id FROM {$wpdb->prefix}bv_services WHERE id IN ($ph) AND questionnaire_template_id > 0",
                ...$service_ids
            ) );
            $template_ids = array_merge( $template_ids, array_map( 'absint', $legacy_ids ) );
            $template_ids = array_unique( $template_ids );
        }

        // --- Get ALL questions for these templates, with responses ---
        $all_questions = array();
        if ( ! empty( $template_ids ) ) {
            $tpl_ph = implode( ',', array_fill( 0, count( $template_ids ), '%d' ) );
            $all_questions = $wpdb->get_results( $wpdb->prepare(
                "SELECT q.id as question_id, q.label, q.type, q.help_text, q.options as question_options,
                        q.placeholder, q.is_required,
                        qs.title as section_title, qs.display_order as section_order,
                        q.display_order as question_order,
                        r.response_value,
                        COALESCE(s.name, 'General') as service_name
                 FROM {$wpdb->prefix}bv_questionnaire_questions q
                 JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON q.section_id = qs.id
                 JOIN {$wpdb->prefix}bv_service_questionnaires sq ON sq.questionnaire_template_id = qs.template_id
                 JOIN {$wpdb->prefix}bv_project_services ps ON ps.service_id = sq.service_id AND ps.project_id = %d
                 LEFT JOIN {$wpdb->prefix}bv_services s ON sq.service_id = s.id
                 LEFT JOIN {$wpdb->prefix}bv_questionnaire_responses r ON r.question_id = q.id AND r.project_id = %d
                 WHERE qs.template_id IN ($tpl_ph)
                 ORDER BY COALESCE(s.name, 'zzz'), qs.display_order, q.display_order",
                $project_id, $project_id, ...$template_ids
            ) );
        }

        // Group by service, then by section
        $grouped = array();
        foreach ( $all_questions as $q ) {
            $sname = $q->service_name;
            if ( ! isset( $grouped[ $sname ] ) ) {
                $grouped[ $sname ] = array();
            }
            $grouped[ $sname ][] = $q;
        }

        // Also try legacy query for templates not in junction table
        if ( empty( $all_questions ) && ! empty( $template_ids ) ) {
            $tpl_ph = implode( ',', array_fill( 0, count( $template_ids ), '%d' ) );
            $legacy_qs = $wpdb->get_results( $wpdb->prepare(
                "SELECT q.id as question_id, q.label, q.type, q.help_text, q.options as question_options,
                        q.placeholder, q.is_required,
                        qs.title as section_title, qs.display_order as section_order,
                        q.display_order as question_order,
                        r.response_value,
                        COALESCE(s.name, 'General') as service_name
                 FROM {$wpdb->prefix}bv_questionnaire_questions q
                 JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON q.section_id = qs.id
                 JOIN {$wpdb->prefix}bv_services s ON s.questionnaire_template_id = qs.template_id
                 JOIN {$wpdb->prefix}bv_project_services ps ON ps.service_id = s.id AND ps.project_id = %d
                 LEFT JOIN {$wpdb->prefix}bv_questionnaire_responses r ON r.question_id = q.id AND r.project_id = %d
                 WHERE qs.template_id IN ($tpl_ph)
                 ORDER BY COALESCE(s.name, 'zzz'), qs.display_order, q.display_order",
                $project_id, $project_id, ...$template_ids
            ) );
            foreach ( $legacy_qs as $q ) {
                $sname = $q->service_name;
                if ( ! isset( $grouped[ $sname ] ) ) $grouped[ $sname ] = array();
                $grouped[ $sname ][] = $q;
            }
        }

        $has_multiple  = count( $grouped ) > 1;
        $generated_at  = current_time( 'mysql' );
        $nonce         = wp_create_nonce( 'bv_consultant_dashboard' );

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $project->project_number . ' — Questionnaire Responses' ); ?></title>
<style>
    @page { margin: 18mm 15mm; size: A4; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1a1a2e; line-height: 1.6; padding: 40px; max-width: 900px; margin: 0 auto; background: #fff; <?php if ( ! $for_email ) echo 'padding-top: 70px;'; ?> }
    .doc-header { border-bottom: 3px solid #002B5C; padding-bottom: 20px; margin-bottom: 30px; }
    .doc-header h1 { font-size: 22px; color: #002B5C; margin-bottom: 4px; text-align: left; }
    .doc-header .subtitle { font-size: 13px; color: #666; text-align: left; }
    .doc-meta { display: flex; flex-wrap: wrap; gap: 24px; margin-top: 16px; font-size: 13px; color: #444; text-align: left; }
    .doc-meta span { text-align: left; }
    .doc-meta strong { color: #1a1a2e; }
    .svc-list { margin-top: 12px; font-size: 12px; color: #666; text-align: left; }
    .svc-list strong { color: #1a1a2e; }
    .toc { background: #f8f9fb; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 28px; text-align: left; }
    .toc h3 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; margin-bottom: 10px; }
    .toc ol { margin-left: 20px; font-size: 13px; color: #002B5C; }
    .toc ol li { margin-bottom: 4px; }
    .service-section { margin-bottom: 36px; page-break-inside: avoid; }
    .service-header { background: #002B5C; color: #fff; padding: 10px 16px; border-radius: 6px; font-size: 16px; font-weight: 700; margin-bottom: 16px; text-align: left; }
    .section-header { font-size: 14px; font-weight: 700; color: #002B5C; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #d1d5db; padding-bottom: 6px; margin: 20px 0 12px; page-break-after: avoid; text-align: left; }
    .q-heading { font-size: 15px; font-weight: 700; color: #1a1a2e; margin: 16px 0 8px; text-align: left; }
    .q-paragraph { font-size: 13px; color: #555; margin-bottom: 12px; text-align: left; }
    .q-static-text { font-size: 13px; color: #444; margin-bottom: 12px; padding: 10px 14px; background: #f8f9fb; border-radius: 6px; border-left: 3px solid #002B5C; text-align: left; }
    .q-static-image { margin-bottom: 12px; text-align: left; }
    .q-static-image img { max-width: 400px; border-radius: 6px; border: 1px solid #e2e8f0; }
    .q-static-image figcaption { font-size: 11px; color: #888; margin-top: 4px; }
    .q-row { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; page-break-inside: avoid; text-align: left; }
    .q-label { flex: 0 0 38%; font-weight: 600; font-size: 13px; color: #333; text-align: left; }
    .q-label .q-help { display: block; font-weight: 400; font-size: 11px; color: #999; margin-top: 2px; }
    .q-value { flex: 1; font-size: 13px; color: #1a1a2e; white-space: pre-wrap; word-wrap: break-word; text-align: left; }
    .q-value.empty { color: #bbb; font-style: italic; }
    .q-stars { font-size: 22px; color: #f59e0b; letter-spacing: 2px; }
    .rep-table { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 12px; text-align: left; }
    .rep-table th { background: #f1f5f9; padding: 6px 10px; text-align: left; font-weight: 600; border: 1px solid #e2e8f0; }
    .rep-table td { padding: 5px 10px; border: 1px solid #e2e8f0; text-align: left; }
    .mf-file { margin-bottom: 6px; font-size: 13px; text-align: left; }
    .mf-file-name { font-weight: 600; }
    .mf-file-size { color: #888; font-size: 12px; }
    .mf-file-dl { color: #002B5C; font-size: 12px; margin-left: 6px; }
    .sig-img { max-width: 300px; height: 80px; object-fit: contain; border-bottom: 1px solid #ccc; }
    .doc-footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #d1d5db; font-size: 11px; color: #999; text-align: center; }
    <?php if ( ! $for_email ) : ?>
    .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #002B5C; color: #fff; padding: 10px 20px; display: flex; justify-content: center; align-items: center; gap: 16px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15); font-size: 14px; }
    .print-bar span { opacity: 0.8; }
    .print-btn, .close-btn { padding: 8px 20px; border-radius: 6px; font-size: 14px; cursor: pointer; border: none; font-weight: 600; }
    .print-btn { background: #fff; color: #002B5C; }
    .print-btn:hover { background: #f0f0f0; }
    .close-btn { background: rgba(255,255,255,0.15); color: #fff; }
    .close-btn:hover { background: rgba(255,255,255,0.25); }
    <?php endif; ?>
    @media print {
        body { padding: 0; max-width: none; padding-top: 0; }
        .print-bar { display: none !important; }
        .service-section { page-break-inside: avoid; }
        .q-row { page-break-inside: avoid; }
        .toc { page-break-after: always; }
    }
</style>
</head>
<body>
<?php if ( ! $for_email ) : ?>
<div class="print-bar">
    <span>Use your browser's Print function to save this as a PDF</span>
    <button class="print-btn" onclick="window.print()">🖨 Save as PDF</button>
    <button class="close-btn" onclick="window.close()">✕ Close</button>
</div>
<?php endif; ?>

<div class="doc-header">
    <h1><?php echo esc_html( $project->project_number ); ?> — Questionnaire Responses</h1>
    <div class="subtitle">BusinessVance Consulting — Client Questionnaire Report</div>
    <div class="doc-meta">
        <span>Client: <strong><?php echo esc_html( $project->client_name ); ?></strong></span>
        <?php if ( $project->client_company ) : ?><span>Company: <strong><?php echo esc_html( $project->client_company ); ?></strong></span><?php endif; ?>
        <span>Email: <strong><?php echo esc_html( $project->client_email ); ?></strong></span>
        <?php if ( $project->client_phone ) : ?><span>Phone: <strong><?php echo esc_html( $project->client_phone ); ?></strong></span><?php endif; ?>
        <span>Generated: <strong><?php echo esc_html( date( 'd M Y H:i', strtotime( $generated_at ) ) ); ?></strong></span>
    </div>
    <?php if ( ! empty( $service_names ) ) : ?>
    <div class="svc-list">Services Purchased: <strong><?php echo esc_html( implode( ', ', $service_names ) ); ?></strong></div>
    <?php endif; ?>
</div>

<?php if ( $has_multiple ) : ?>
<div class="toc">
    <h3>Table of Contents — Services</h3>
    <ol>
    <?php foreach ( $grouped as $sname => $svc_qs ) : ?>
        <li><strong><?php echo esc_html( $sname ); ?></strong>
        <?php
        $secs = array();
        foreach ( $svc_qs as $sq ) { if ( ! in_array( $sq->section_title, $secs ) ) $secs[] = $sq->section_title; }
        if ( count( $secs ) > 1 ) echo ' — Sections: ' . esc_html( implode( ', ', $secs ) );
        ?>
        </li>
    <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<?php foreach ( $grouped as $service_name => $service_questions ) : ?>
<?php if ( $has_multiple ) : ?>
<div class="service-section" style="page-break-before: always;">
    <div class="service-header">Service: <?php echo esc_html( $service_name ); ?></div>
<?php endif; ?>

<?php
$current_section = '';
foreach ( $service_questions as $q ) :
    // Section header
    if ( $q->section_title !== $current_section ) :
        $current_section = $q->section_title;
?>
    <div class="section-header"><?php echo esc_html( $current_section ); ?></div>
<?php endif; ?>

<?php
    $qtype   = $q->type;
    $qlabel  = $q->label;
    $qhelp   = $q->help_text;
    $qopts   = json_decode( $q->question_options, true );
    $qval    = $q->response_value;
    $qjson   = is_string( $qval ) ? json_decode( $qval, true ) : null;

    // ---- HEADING ----
    if ( $qtype === 'heading' ) :
?>
    <div class="q-heading"><?php echo esc_html( $qlabel ); ?></div>
<?php
    // ---- PARAGRAPH ----
    elseif ( $qtype === 'paragraph' ) :
?>
    <div class="q-paragraph"><?php echo nl2br( esc_html( $qlabel ) ); ?></div>
<?php
    // ---- STATIC TEXT ----
    elseif ( $qtype === 'static_text' ) :
        $static_content = '';
        if ( is_array( $qopts ) && ! empty( $qopts[0] ) ) {
            $static_content = is_array( $qopts[0] ) ? ( $qopts[0]['label'] ?? $qopts[0]['value'] ?? '' ) : (string) $qopts[0];
        }
        if ( ! $static_content ) $static_content = $qlabel;
?>
    <div class="q-static-text"><?php echo wp_kses_post( $static_content ); ?></div>
<?php
    // ---- STATIC IMAGE ----
    elseif ( $qtype === 'static_image' ) :
        $img_url = '';
        $img_caption = '';
        if ( is_array( $qopts ) && ! empty( $qopts[0] ) ) {
            $img_url = is_array( $qopts[0] ) ? ( $qopts[0]['value'] ?? $qopts[0]['label'] ?? '' ) : (string) $qopts[0];
            if ( ! empty( $qopts[1] ) ) $img_caption = is_array( $qopts[1] ) ? ( $qopts[1]['label'] ?? '' ) : (string) $qopts[1];
        }
        if ( ! $img_url && ! empty( $q->placeholder ) ) $img_url = $q->placeholder;
?>
    <figure class="q-static-image">
    <?php if ( $img_url ) : ?>
        <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $qlabel ); ?>" />
        <?php if ( $img_caption ) : ?><figcaption><?php echo esc_html( $img_caption ); ?></figcaption><?php endif; ?>
    <?php else : ?>
        <p style="color:#999;font-style:italic;">[No image configured]</p>
    <?php endif; ?>
    </figure>
<?php
    // ---- ALL OTHER FIELDS WITH RESPONSES ----
    else :
        $is_empty = empty( $qval ) || $qval === '[]';
?>
    <div class="q-row">
        <div class="q-label"><?php echo esc_html( $qlabel ); ?><?php if ( $qhelp ) : ?><span class="q-help"><?php echo esc_html( $qhelp ); ?></span><?php endif; ?></div>
        <div class="q-value<?php echo $is_empty ? ' empty' : ''; ?>">
        <?php
        // Signature
        if ( preg_match( '/^data:image/', $qval ) ) :
        ?>
            <img src="<?php echo esc_attr( $qval ); ?>" alt="Client Signature" class="sig-img" />
        <?php
        // Multifile JSON with URLs (check BEFORE repeatable table)
        elseif ( $qjson && isset( $qjson[0] ) && isset( $qjson[0]['url'] ) ) :
        ?>
            <div class="mf-list"><?php foreach ( $qjson as $f ) : ?>
                <div class="mf-file">
                    <span class="mf-file-name"><?php echo esc_html( $f['name'] ?? 'File' ); ?></span><?php if ( ! empty( $f['size'] ) ) : ?> <span class="mf-file-size"><?php echo esc_html( $f['size'] ); ?></span><?php endif; ?><?php if ( ! empty( $f['file'] ) && ! $for_email ) : ?> <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bv_cd_download_qfile&nonce=' . $nonce . '&project_id=' . $project_id . '&file=' . rawurlencode( $f['file'] ) ) ); ?>" class="mf-file-dl">⬇ Download</a><?php elseif ( ! empty( $f['file'] ) && $for_email ) : ?> <span class="mf-file-size"><?php echo esc_html( $f['name'] ?? $f['file'] ); ?></span><?php elseif ( ! empty( $f['url'] ) ) : ?> <a href="<?php echo esc_url( $f['url'] ); ?>" class="mf-file-dl" target="_blank">⬇ Open</a><?php endif; ?>
                </div>
            <?php endforeach; ?></div>
        <?php
        // Repeatable table (2D array) — use column names from question options
        elseif ( is_array( $qjson ) && isset( $qjson[0] ) && is_array( $qjson[0] ) ) :
            $col_headers = array();
            if ( is_array( $qopts ) ) {
                foreach ( $qopts as $col ) {
                    if ( is_array( $col ) && ! empty( $col['label'] ) ) {
                        $col_headers[] = $col['label'];
                    }
                }
            }
            $col_count = 0;
            foreach ( $qjson as $row ) { if ( count( $row ) > $col_count ) $col_count = count( $row ); }
            // Fill missing headers
            while ( count( $col_headers ) < $col_count ) { $col_headers[] = 'Column ' . ( count( $col_headers ) + 1 ); }
        ?>
            <table class="rep-table"><thead><tr><th>#</th><?php foreach ( $col_headers as $ch ) : ?><th><?php echo esc_html( $ch ); ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ( $qjson as $ri => $row ) : ?><tr><td><?php echo $ri + 1; ?></td><?php foreach ( $col_headers as $ci => $ch ) : ?><td><?php echo esc_html( $row[ $ci ] ?? '' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
        <?php
        // Rating — render as stars
        elseif ( $qtype === 'rating' ) :
            $rating_val = intval( $qval );
            $max_stars  = 5;
            if ( is_array( $qopts ) && ! empty( $qopts[0] ) ) {
                $max_stars = max( 1, intval( is_array( $qopts[0] ) ? ( $qopts[0]['value'] ?? $qopts[0] ) : $qopts[0] ) );
            }
            $stars_html = '';
            for ( $si = 1; $si <= $max_stars; $si++ ) {
                $stars_html .= $si <= $rating_val ? '★' : '☆';
            }
            echo '<span class="q-stars">' . esc_html( $stars_html ) . '</span>';
            echo ' <span style="font-size:12px;color:#666;">(' . intval( $qval ) . '/' . $max_stars . ')</span>';
        // Address (keyed object)
        elseif ( is_array( $qjson ) && ! isset( $qjson[0] ) ) :
            $addr_labels = array( 'street' => 'Street', 'city' => 'City', 'state' => 'State/Province', 'zip' => 'ZIP/Postal Code', 'country' => 'Country' );
            foreach ( $addr_labels as $key => $label ) :
                if ( ! empty( $qjson[ $key ] ) ) :
                    echo esc_html( $label ) . ': ' . esc_html( $qjson[ $key ] ) . '<br>';
                endif;
            endforeach;
        // Checkbox/array values (not multifile, not repeatable)
        elseif ( is_array( $qjson ) ) :
            echo esc_html( implode( ', ', $qjson ) );
        // WYSIWYG — render as HTML
        elseif ( $qtype === 'wysiwyg' ) :
            echo $is_empty ? '—' : wp_kses_post( $qval );
        // Plain text / empty
        else :
            echo $is_empty ? '—' : nl2br( esc_html( $qval ) );
        endif;
        ?>
        </div>
    </div>
<?php endif; // end field type switch ?>
<?php endforeach; // end questions loop ?>

<?php if ( $has_multiple ) : ?>
</div>
<?php endif; ?>
<?php endforeach; // end services loop ?>

<?php if ( empty( $grouped ) ) : ?>
<div style="text-align:left;padding:60px 20px;color:#999;font-size:15px;">No questionnaire responses have been submitted for this project.</div>
<?php endif; ?>

<?php
// ========== REQUIRED DOCUMENTS SECTION ==========
$docs = $wpdb->get_results( $wpdb->prepare(
    "SELECT id, name, filename, filesize, category, uploaded_by, created_at
     FROM {$wpdb->prefix}bv_project_documents WHERE project_id = %d ORDER BY created_at DESC",
    $project_id
) );
if ( ! empty( $docs ) ) :
?>
<div class="service-section" style="margin-top:48px;">
    <div class="service-header">Required Documents (<?php echo count( $docs ); ?>)</div>
    <table class="rep-table" style="margin-top:0;">
        <thead>
            <tr>
                <th style="text-align:left;">Document</th>
                <th style="text-align:left;">Category</th>
                <th style="text-align:left;">Uploaded By</th>
                <th style="text-align:left;">Date</th>
                <th style="text-align:left;">Size</th>
                <?php if ( ! $for_email ) : ?><th style="text-align:left;">Action</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $docs as $doc ) : ?>
        <tr>
            <td style="text-align:left;"><?php echo esc_html( $doc->name ); ?></td>
            <td style="text-align:left;"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $doc->category ) ) ); ?></td>
            <td style="text-align:left;"><?php echo esc_html( $doc->uploaded_by ); ?></td>
            <td style="text-align:left;"><?php echo esc_html( date( 'd M Y H:i', strtotime( $doc->created_at ) ) ); ?></td>
            <td style="text-align:left;"><?php echo esc_html( size_format( $doc->filesize ) ); ?></td>
            <?php if ( ! $for_email ) : ?>
            <td style="text-align:left;"><a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bv_cd_download_document&nonce=' . $nonce . '&document_id=' . $doc->id ) ); ?>" style="color:#002B5C;font-weight:600;font-size:12px;">⬇ Download</a></td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
// ========== SIGNED AGREEMENT SECTION ==========
$agreement_rec = $wpdb->get_row( $wpdb->prepare(
    "SELECT full_name, signed_at, template_content FROM {$wpdb->prefix}bv_project_agreements WHERE project_id = %d ORDER BY id DESC LIMIT 1",
    $project_id
) );
if ( $agreement_rec && ! empty( $agreement_rec->template_content ) ) :
?>
<div class="service-section" style="margin-top:48px;">
    <div class="service-header">Signed Agreement</div>
    <div style="padding:16px 20px;">
        <p style="margin-bottom:12px;font-size:13px;color:#666;">Signed by: <strong style="color:#1a1a2e;"><?php echo esc_html( $agreement_rec->full_name ?? '' ); ?></strong> on <?php echo esc_html( date( 'd M Y \a\t H:i', strtotime( $agreement_rec->signed_at ) ) ); ?></p>
        <div style="border:1px solid #e2e8f0;border-radius:6px;padding:20px;font-size:13px;line-height:1.7;color:#1a1a2e;">
            <?php echo wp_kses_post( $agreement_rec->template_content ); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="doc-footer">
    Generated by BusinessVance Services Manager on <?php echo esc_html( date( 'd F Y \a\t H:i', strtotime( $generated_at ) ) ); ?>
    &nbsp;·&nbsp; Project: <?php echo esc_html( $project->project_number ); ?>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler: Open questionnaire report as HTML in a new window.
     *
     * @since 2.7.21  Rewritten 2.7.23 to show ALL question types with proper formatting.
     * @return void
     */
    public function ajax_download_questionnaire_html() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_die( esc_html__( 'Access denied', 'businessvance-services-manager' ) );

        $project_id = absint( $_GET['project_id'] ?? $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) wp_die( esc_html__( 'Invalid project', 'businessvance-services-manager' ) );

        $html = $this->build_questionnaire_report_html( $project_id, false );

        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html;
        exit;
    }

    /**
     * Download questionnaire responses as CSV (data processing format).
     * Includes service name column for multi-service projects.
     *
     * @since 2.5.0  Updated 2.7.21 to include service names
     * @return void
     */
    public function ajax_download_questionnaire() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_die( esc_html__( 'Access denied', 'businessvance-services-manager' ) );

        $project_id = absint( $_GET['project_id'] ?? $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) wp_die( esc_html__( 'Invalid project', 'businessvance-services-manager' ) );

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id
        ) );
        if ( ! $project ) wp_die( esc_html__( 'Project not found', 'businessvance-services-manager' ) );

        $responses = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.response_value, r.service_id, q.label, q.type,
                    qs.title as section_title, qs.display_order as section_order,
                    q.display_order as question_order,
                    COALESCE(s.name, 'General') as service_name
             FROM {$wpdb->prefix}bv_questionnaire_responses r
             JOIN {$wpdb->prefix}bv_questionnaire_questions q ON r.question_id = q.id
             JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON q.section_id = qs.id
             LEFT JOIN {$wpdb->prefix}bv_services s ON r.service_id = s.id
             WHERE r.project_id = %d
             ORDER BY COALESCE(s.name, 'zzz'), qs.display_order, q.display_order",
            $project_id
        ) );

        $filename = sanitize_file_name( $project->project_number . '_' . sanitize_file_name( $project->client_name ) . '_questionnaire.csv' );
        $csv_rows = array();
        $csv_rows[] = array( 'Service', 'Section', 'Question', 'Type', 'Client Response' );

        foreach ( $responses as $r ) {
            $val = $r->response_value;
            $json_val = json_decode( $val, true );
            if ( preg_match( '/^data:image/', $val ) ) {
                $val = '[Signature]';
            } elseif ( $json_val && isset( $json_val[0] ) && isset( $json_val[0]['url'] ) ) {
                $parts = array();
                foreach ( $json_val as $f ) {
                    $parts[] = ( $f['name'] ?? 'File' ) . ( ! empty( $f['size'] ) ? ' (' . $f['size'] . ')' : '' );
                }
                $val = implode( '; ', $parts );
            } elseif ( is_array( $json_val ) ) {
                if ( isset( $json_val[0] ) && is_array( $json_val[0] ) ) {
                    $flat = array();
                    foreach ( $json_val as $row ) { $flat[] = implode( ' | ', $row ); }
                    $val = implode( '; ', $flat );
                } else {
                    $val = implode( ', ', $json_val );
                }
            }

            $csv_rows[] = array( $r->service_name, $r->section_title, $r->label, $r->type, $val );
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fprintf( $output, "\xEF\xBB\xBF" );
        foreach ( $csv_rows as $row ) { fputcsv( $output, $row ); }
        fclose( $output );
        exit;
    }

    /**
     * Download a file attached to a questionnaire multifile response.
     * Validates that the file belongs to a questionnaire response for the project.
     *
     * @since 2.7.22
     * @return void
     */
    public function ajax_download_questionnaire_file() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( self::CAP ) ) wp_die( esc_html__( 'Access denied', 'businessvance-services-manager' ) );

        $project_id = absint( $_GET['project_id'] ?? $_POST['project_id'] ?? 0 );
        $filename   = sanitize_file_name( $_GET['file'] ?? $_POST['file'] ?? '' );
        if ( ! $project_id || ! $filename ) wp_die( esc_html__( 'Invalid request', 'businessvance-services-manager' ) );

        // Security: verify the file belongs to a questionnaire response for this project
        global $wpdb;
        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bv_questionnaire_responses 
             WHERE project_id = %d AND response_value LIKE %s",
            $project_id, '%' . $wpdb->esc_like( $filename ) . '%'
        ) );
        if ( ! $found ) wp_die( esc_html__( 'File not found', 'businessvance-services-manager' ) );

        // Build the file path from uploads
        $upload_dir = wp_upload_dir();
        $filepath   = $upload_dir['basedir'] . '/bv-documents/' . $filename;
        if ( ! file_exists( $filepath ) ) wp_die( esc_html__( 'File not found on disk', 'businessvance-services-manager' ) );

        // Determine MIME type
        $mime_types = array(
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png', 'gif' => 'image/gif',
            'csv'  => 'text/csv', 'txt' => 'text/plain',
            'zip'  => 'application/zip',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        );
        $ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $mime = isset( $mime_types[ $ext ] ) ? $mime_types[ $ext ] : 'application/octet-stream';

        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
        header( 'Content-Length: ' . filesize( $filepath ) );
        header( 'Cache-Control: no-cache' );
        readfile( $filepath );
        exit;
    }

    /**
     * Email a complete project data package to the consultant when a
     * client's project reaches 100 % progress. Includes:
     *  1. A ZIP containing questionnaire HTML, agreement HTML, and all uploaded files
     *  2. Individual file attachments directly on the email (fallback for ZIP issues)
     *
     * @since 2.7.23  Rewritten 2.7.35 with robust file resolution, individual attachments, HTML email, and comprehensive logging.
     * @param int $project_id The project ID.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public function email_project_package_to_consultant( $project_id ) {
        global $wpdb;

        $settings = BV_Settings::get_settings();
        $consultant_email = $settings['consultant_email'] ?? get_option( 'admin_email' );
        if ( empty( $consultant_email ) ) {
            error_log( sprintf( '[BV 2.7.35] email_package skipped project %d: no consultant email', $project_id ) );
            return false;
        }

        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id
        ) );
        if ( ! $project ) {
            error_log( sprintf( '[BV 2.7.35] email_package skipped project %d: not found', $project_id ) );
            return false;
        }

        error_log( sprintf( '[BV 2.7.35] === email_package START project %d ===', $project_id ) );

        $company_name = $settings['company_name'] ?? 'BusinessVance';
        $dashboard_url = admin_url( 'admin.php?page=bv-consultant-dashboard&project_id=' . $project_id );
        $upload_dir   = wp_upload_dir();
        $bv_docs_dir  = $upload_dir['basedir'] . '/bv-documents';
        $zip_path     = null;
        $attachments  = array(); // files to attach individually to the email

        try {
            if ( ! class_exists( 'ZipArchive' ) ) {
                error_log( '[BV 2.7.35] ZipArchive not available — will attach files individually only.' );
                $zip_available = false;
            } else {
                $zip_available = true;
            }

            // =============================================
            // 1. Generate questionnaire HTML report
            // =============================================
            $questionnaire_html = $this->build_questionnaire_report_html( $project_id, true );
            error_log( sprintf( '[BV 2.7.35] Questionnaire HTML: %d bytes', strlen( $questionnaire_html ) ) );

            // =============================================
            // 2. Collect multifile uploads from questionnaire
            // =============================================
            $multifile_paths = array();
            $responses = $wpdb->get_results( $wpdb->prepare(
                "SELECT response_value FROM {$wpdb->prefix}bv_questionnaire_responses WHERE project_id = %d",
                $project_id
            ) );
            error_log( sprintf( '[BV 2.7.35] Found %d response rows for project %d', count( $responses ), $project_id ) );

            foreach ( $responses as $r ) {
                $raw = $r->response_value;
                error_log( sprintf( '[BV 2.7.35] Response raw (first 120 chars): %s', substr( $raw, 0, 120 ) ) );

                $json = json_decode( $raw, true );
                if ( ! is_array( $json ) ) {
                    continue;
                }

                // Check if any entry in the array looks like a multifile upload (has 'url' key)
                $is_multifile = false;
                foreach ( $json as $entry ) {
                    if ( is_array( $entry ) && isset( $entry['url'] ) && ! empty( $entry['file'] ) ) {
                        $is_multifile = true;
                        break;
                    }
                }
                if ( ! $is_multifile ) {
                    continue;
                }

                foreach ( $json as $f ) {
                    // Skip entries without file key (failed uploads have 'error' instead)
                    if ( ! is_array( $f ) || empty( $f['file'] ) ) {
                        continue;
                    }

                    // Try multiple path patterns
                    $found_path = null;
                    $candidates = array(
                        $bv_docs_dir . '/' . $f['file'],                             // Standard: basedir/bv-documents/filename
                        $bv_docs_dir . '/' . basename( $f['file'] ),                // Basename only
                        $upload_dir['basedir'] . '/' . $f['file'],                   // basedir/filename
                        $f['url'] ? ABSPATH . wp_make_link_relative( $f['url'] ) : '', // From URL
                    );

                    foreach ( $candidates as $candidate ) {
                        if ( $candidate && file_exists( $candidate ) ) {
                            $found_path = $candidate;
                            break;
                        }
                    }

                    if ( $found_path ) {
                        $safe_name = basename( $found_path );
                        $multifile_paths[ $safe_name ] = $found_path;
                        error_log( sprintf( '[BV 2.7.35]   Found file: %s (%d bytes)', $safe_name, filesize( $found_path ) ) );
                    } else {
                        error_log( sprintf( '[BV 2.7.35]   FILE NOT FOUND for: %s', $f['file'] ) );
                        error_log( sprintf( '[BV 2.7.35]   Tried: %s', implode( ' | ', array_filter( $candidates ) ) ) );
                    }
                }
            }
            error_log( sprintf( '[BV 2.7.35] Total multifile paths found: %d', count( $multifile_paths ) ) );

            // =============================================
            // 3. Collect required documents from bv_project_documents
            // =============================================
            $doc_paths = array();
            $documents = $wpdb->get_results( $wpdb->prepare(
                "SELECT filepath, filename, filesize FROM {$wpdb->prefix}bv_project_documents WHERE project_id = %d",
                $project_id
            ) );
            error_log( sprintf( '[BV 2.7.35] Found %d required document rows', count( $documents ) ) );

            foreach ( $documents as $doc ) {
                if ( empty( $doc->filepath ) ) {
                    error_log( '[BV 2.7.35]   Skipping doc with empty filepath' );
                    continue;
                }

                error_log( sprintf( '[BV 2.7.35]   Doc filepath from DB: %s', $doc->filepath ) );

                // Try multiple path patterns
                $found_path = null;
                $candidates = array(
                    $doc->filepath,                                           // As stored
                    $bv_docs_dir . '/' . basename( $doc->filepath ),         // Relative to bv-documents
                    $upload_dir['basedir'] . '/' . basename( $doc->filepath ),// Relative to uploads
                );

                foreach ( $candidates as $candidate ) {
                    if ( $candidate && file_exists( $candidate ) ) {
                        $found_path = $candidate;
                        break;
                    }
                }

                if ( $found_path ) {
                    $safe_name = ! empty( $doc->filename ) ? $doc->filename : basename( $found_path );
                    $doc_paths[ $safe_name ] = $found_path;
                    error_log( sprintf( '[BV 2.7.35]   Found doc: %s (%d bytes)', $safe_name, filesize( $found_path ) ) );
                } else {
                    error_log( sprintf( '[BV 2.7.35]   DOC NOT FOUND: %s', $doc->filepath ) );
                }
            }
            error_log( sprintf( '[BV 2.7.35] Total required doc paths found: %d', count( $doc_paths ) ) );

            // =============================================
            // 4. Collect agreement
            // =============================================
            $agreement_html = '';
            $agreement = $wpdb->get_row( $wpdb->prepare(
                "SELECT template_content, full_name, agreed_at FROM {$wpdb->prefix}bv_project_agreements WHERE project_id = %d ORDER BY id DESC LIMIT 1",
                $project_id
            ) );
            if ( $agreement ) {
                $agreement_content = ! empty( $agreement->template_content )
                    ? $agreement->template_content
                    : '<p><em>No agreement template content was configured for this service.</em></p>';
                $signed_time = ! empty( $agreement->agreed_at ) ? $agreement->agreed_at : '';
                $agreement_html = '<!DOCTYPE html>'
                    . '<html><head><meta charset="UTF-8"><title>Service Agreement</title>'
                    . '<style>body{font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;max-width:800px;margin:40px auto;padding:20px;color:#1a1a2e;line-height:1.7;}h1{color:#002B5C;border-bottom:3px solid #002B5C;padding-bottom:10px;}.meta{font-size:13px;color:#666;margin-bottom:20px;}</style>'
                    . '</head><body>'
                    . '<h1>Service Agreement</h1>'
                    . '<div class="meta">'
                    . 'Project: ' . esc_html( $project->project_number ) . '<br>'
                    . 'Client: ' . esc_html( $project->client_name ) . '<br>'
                    . 'Signed by: ' . esc_html( $agreement->full_name ?? '' ) . '<br>'
                    . 'Signed at: ' . esc_html( $signed_time )
                    . '</div>'
                    . wp_kses_post( $agreement_content )
                    . '</body></html>';
                error_log( sprintf( '[BV 2.7.35] Agreement HTML: %d bytes', strlen( $agreement_html ) ) );
            } else {
                error_log( sprintf( '[BV 2.7.35] No agreement found for project %d', $project_id ) );
            }

            // =============================================
            // 5. Collect all file attachments for individual email attachment
            // =============================================
            $all_file_paths = array_merge( $multifile_paths, $doc_paths );
            foreach ( $all_file_paths as $name => $path ) {
                if ( file_exists( $path ) && is_readable( $path ) ) {
                    $attachments[] = $path;
                }
            }
            error_log( sprintf( '[BV 2.7.35] Individual attachments prepared: %d', count( $attachments ) ) );

            // =============================================
            // 6. Build ZIP
            // =============================================
            if ( $zip_available ) {
                $zip_filename = 'project-' . sanitize_file_name( $project->project_number ) . '-package.zip';
                // Use WordPress uploads dir instead of sys_get_temp_dir for reliable access
                $zip_path = $bv_docs_dir . '/' . $zip_filename;

                if ( ! file_exists( $bv_docs_dir ) ) {
                    wp_mkdir_p( $bv_docs_dir );
                }

                if ( file_exists( $zip_path ) ) {
                    @unlink( $zip_path );
                }

                $zip = new ZipArchive();
                $zip_opened = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
                if ( $zip_opened !== true ) {
                    error_log( sprintf( '[BV 2.7.35] ZIP create failed: error code %d, path: %s', $zip_opened, $zip_path ) );
                    $zip_path = null;
                } else {
                    $zip->addFromString( 'questionnaire-report.html', $questionnaire_html );

                    if ( ! empty( $agreement_html ) ) {
                        $zip->addFromString( 'agreement.html', $agreement_html );
                    }

                    $mf_count = 0;
                    foreach ( $multifile_paths as $name => $path ) {
                        $zip->addFile( $path, 'questionnaire-files/' . $name );
                        $mf_count++;
                    }

                    $doc_count = 0;
                    foreach ( $doc_paths as $name => $path ) {
                        $zip->addFile( $path, 'required-documents/' . $name );
                        $doc_count++;
                    }

                    $zip->close();

                    if ( file_exists( $zip_path ) && filesize( $zip_path ) > 0 ) {
                        $attachments[] = $zip_path; // Add ZIP as attachment too
                        error_log( sprintf( '[BV 2.7.35] ZIP created: %s (%d bytes) — contains: 1 HTML report, %s agreement, %d questionnaire files, %d required docs',
                            $zip_path, filesize( $zip_path ),
                            empty( $agreement_html ) ? 'no' : '1',
                            $mf_count, $doc_count
                        ) );
                    } else {
                        error_log( sprintf( '[BV 2.7.35] ZIP file empty or not created at: %s', $zip_path ) );
                        $zip_path = null;
                    }
                }
            }

            // =============================================
            // 7. Build and send email (HTML format)
            // =============================================
            $tokens = array(
                '{project_number}'  => $project->project_number,
                '{client_name}'     => $project->client_name,
                '{client_email}'    => $project->client_email,
                '{company_name}'    => $company_name,
                '{dashboard_url}'   => $dashboard_url,
            );

            $subject = $settings['email_project_package_subject'] ?? 'Project {project_number} Complete — All Client Information';
            $subject = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject );

            // Build file listing for email body
            $file_listing_html = '';
            if ( ! empty( $multifile_paths ) ) {
                $file_listing_html .= '<h3 style="color:#002B5C;margin:16px 0 8px;">Questionnaire Files (' . count( $multifile_paths ) . ')</h3><ul style="margin:0 0 12px;padding-left:20px;">';
                foreach ( $multifile_paths as $name => $path ) {
                    $file_listing_html .= '<li>' . esc_html( $name ) . ' (' . size_format( filesize( $path ) ) . ')</li>';
                }
                $file_listing_html .= '</ul>';
            }
            if ( ! empty( $doc_paths ) ) {
                $file_listing_html .= '<h3 style="color:#002B5C;margin:16px 0 8px;">Required Documents (' . count( $doc_paths ) . ')</h3><ul style="margin:0 0 12px;padding-left:20px;">';
                foreach ( $doc_paths as $name => $path ) {
                    $file_listing_html .= '<li>' . esc_html( $name ) . ' (' . size_format( filesize( $path ) ) . ')</li>';
                }
                $file_listing_html .= '</ul>';
            }
            if ( empty( $multifile_paths ) && empty( $doc_paths ) && empty( $agreement_html ) ) {
                $file_listing_html = '<p style="color:#e67e22;font-weight:600;">⚠ No uploaded files, required documents, or signed agreement were found for this project. Only the questionnaire HTML report is included.</p>';
            }

            $body_html = '<div style="font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;max-width:680px;margin:0 auto;color:#1a1a2e;line-height:1.6;">'
                . '<div style="background:#002B5C;color:#fff;padding:24px 28px;border-radius:8px 8px 0 0;">'
                . '<h1 style="margin:0;font-size:20px;">Project Complete: ' . esc_html( $project->project_number ) . '</h1>'
                . '<p style="margin:6px 0 0;opacity:0.85;font-size:14px;">All client information has been submitted</p>'
                . '</div>'
                . '<div style="background:#fff;padding:28px;border:1px solid #e2e8f0;border-top:none;">'
                . '<p style="margin:0 0 16px;">Project <strong>' . esc_html( $project->project_number ) . '</strong> for client <strong>' . esc_html( $project->client_name ) . '</strong> (' . esc_html( $project->client_email ) . ') is now 100% complete.</p>'
                . '<h3 style="color:#002B5C;margin:20px 0 8px;">Package Contents</h3>'
                . '<ul style="margin:0 0 12px;padding-left:20px;">'
                . '<li>Complete Project Report (HTML) — questionnaire responses, required documents, and signed agreement</li>'
                . ( ! empty( $agreement_html ) ? '<li>Signed Service Agreement (HTML)</li>' : '' )
                . ( ! empty( $multifile_paths ) ? '<li>' . count( $multifile_paths ) . ' file(s) uploaded via questionnaire</li>' : '' )
                . ( ! empty( $doc_paths ) ? '<li>' . count( $doc_paths ) . ' required document(s)</li>' : '' )
                . '</ul>'
                . $file_listing_html
                . '<div style="background:#f8f9fb;border:1px solid #e2e8f0;border-radius:6px;padding:14px 18px;margin-top:20px;">'
                . '<p style="margin:0 0 6px;font-size:13px;color:#666;">All files are attached to this email individually AND as a ZIP package for your convenience.</p>'
                . '<p style="margin:0;font-size:13px;"><a href="' . esc_url( $dashboard_url ) . '" style="color:#002B5C;font-weight:600;">Open in Consultant Dashboard →</a></p>'
                . '</div>'
                . '</div>'
                . '<div style="background:#f8f9fb;padding:16px 28px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;font-size:12px;color:#999;text-align:center;">'
                . esc_html( $company_name ) . ' System · ' . date( 'd F Y H:i' )
                . '</div>'
                . '</div>';

            // Also build a plain text fallback body
            $body_text  = "Project {$project->project_number} for {$project->client_name} ({$project->client_email}) is 100% complete.\n\n";
            $body_text .= "Package Contents:\n";
            $body_text .= "- questionnaire-report.html (Complete report: responses, documents, agreement)\n";
            if ( ! empty( $agreement_html ) ) $body_text .= "- agreement.html (Signed service agreement)\n";
            if ( ! empty( $multifile_paths ) ) $body_text .= "- " . count( $multifile_paths ) . " uploaded file(s) from questionnaire\n";
            if ( ! empty( $doc_paths ) ) $body_text .= "- " . count( $doc_paths ) . " required document(s)\n";
            $body_text .= "\nAll files are attached to this email.\n";
            $body_text .= "Dashboard: {$dashboard_url}\n";

            $from_email = ! empty( $settings['email_address'] ) ? $settings['email_address'] : $consultant_email;
            $headers    = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $company_name . ' <' . $from_email . '>',
            );

            error_log( sprintf( '[BV 2.7.35] Sending email to %s with %d attachment(s)', $consultant_email, count( $attachments ) ) );
            error_log( sprintf( '[BV 2.7.35] Attachment paths: %s', implode( ', ', $attachments ) ) );

            $sent = wp_mail( $consultant_email, $subject, $body_html, $headers, $attachments );

            error_log( sprintf( '[BV 2.7.35] wp_mail result for project %d: %s', $project_id, $sent ? 'SUCCESS' : 'FAILED' ) );
            if ( ! $sent ) {
                global $phpmailer;
                if ( $phpmailer && ! empty( $phpmailer->ErrorInfo ) ) {
                    error_log( sprintf( '[BV 2.7.35] PHPMailer error: %s', $phpmailer->ErrorInfo ) );
                }
            }

            // Clean up ZIP (but keep uploaded files — they belong to the project)
            if ( $zip_path && file_exists( $zip_path ) ) {
                @unlink( $zip_path );
            }
            $zip_path = null;

            return $sent;

        } catch ( Exception $e ) {
            error_log( sprintf( '[BV 2.7.35] Exception in email_package for project %d: %s', $project_id, $e->getMessage() ) );
            if ( $zip_path && file_exists( $zip_path ) ) {
                @unlink( $zip_path );
            }
            return false;
        }
    }

    /**
     * Returns empty string — all CSS was extracted to assets/css/consultant-dashboard.css.
     * CSS custom properties are set inline on the container div in render_page().
     *
     * @since 2.7.0
     * @return string
     */
    private function get_inline_css() {
        return '';
    }
}
