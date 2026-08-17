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

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
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
    }

    public function add_menu_page() {
        add_menu_page(
            'Consultant Dashboard',
            'BV Consultant',
            'manage_options',
            'bv-consultant-dashboard',
            array( $this, 'render_page' ),
            'dashicons-clipboard',
            3
        );
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

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
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
                $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects" );
                $active = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status NOT IN ('delivered', 'archived')" );
                $completed = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status IN ('completed', 'delivered')" );
                $awaiting = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bv_projects WHERE status = 'awaiting-agreement'" );
                ?>
                <div class="bv-cd-stat"><span class="bv-cd-stat-num"><?php echo $total; ?></span><span class="bv-cd-stat-label">Total</span></div>
                <div class="bv-cd-stat bv-cd-stat-active"><span class="bv-cd-stat-num"><?php echo $active; ?></span><span class="bv-cd-stat-label">Active</span></div>
                <div class="bv-cd-stat bv-cd-stat-waiting"><span class="bv-cd-stat-num"><?php echo $awaiting; ?></span><span class="bv-cd-stat-label">Awaiting</span></div>
                <div class="bv-cd-stat bv-cd-stat-done"><span class="bv-cd-stat-num"><?php echo $completed; ?></span><span class="bv-cd-stat-label">Done</span></div>
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
        $filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $base_url = admin_url( 'admin.php?page=bv-consultant-dashboard' );

        $where = "1=1";
        if ( $filter_status ) $where .= $wpdb->prepare( " AND p.status = %s", $filter_status );
        if ( $search ) $where .= $wpdb->prepare( " AND (p.project_number LIKE %s OR p.client_name LIKE %s OR p.client_email LIKE %s OR p.client_company LIKE %s)", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%" );

        $projects = $wpdb->get_results( "SELECT p.* FROM {$wpdb->prefix}bv_projects p WHERE {$where} ORDER BY p.created_at DESC" );
        ?>
        <div class="bv-cd-toolbar">
            <form method="get" class="bv-cd-filter-form">
                <input type="hidden" name="page" value="bv-consultant-dashboard" />
                <select name="filter_status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="awaiting-agreement" <?php selected($filter_status, 'awaiting-agreement'); ?>>Awaiting Agreement</option>
                    <option value="awaiting-questionnaire" <?php selected($filter_status, 'awaiting-questionnaire'); ?>>Awaiting Questionnaire</option>
                    <option value="awaiting-documents" <?php selected($filter_status, 'awaiting-documents'); ?>>Awaiting Documents</option>
                    <option value="in-progress" <?php selected($filter_status, 'in-progress'); ?>>In Progress</option>
                    <option value="quality-check" <?php selected($filter_status, 'quality-check'); ?>>Quality Check</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completed</option>
                    <option value="delivered" <?php selected($filter_status, 'delivered'); ?>>Delivered</option>
                    <option value="archived" <?php selected($filter_status, 'archived'); ?>>Archived</option>
                </select>
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search projects..." />
                <button type="submit" class="button">Filter</button>
            </form>
            <a href="#bv-cd-new-project" class="button button-primary" onclick="jQuery('#bv-cd-new-project-form').toggle(); return false;">+ New Project</a>
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
                    <th>Project #</th>
                    <th>Client</th>
                    <th>Services</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $projects ) ) : ?>
                <tr><td colspan="7" style="text-align:center; padding:40px; color:#666;">No projects found.</td></tr>
            <?php else : foreach ( $projects as $p ) :
                $services = $wpdb->get_results( $wpdb->prepare(
                    "SELECT s.name FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d", $p->id ) );
                $svc_names = array_map( function($s) { return $s->name; }, $services );
            ?>
                <tr>
                    <td><strong><a href="<?php echo $base_url; ?>&project_id=<?php echo $p->id; ?>"><?php echo esc_html( $p->project_number ); ?></a></strong></td>
                    <td>
                        <?php echo esc_html( $p->client_name ); ?><br>
                        <small style="color:#666;"><?php echo esc_html( $p->client_email ); ?><?php if ($p->client_company) echo ' — ' . esc_html($p->client_company); ?></small>
                    </td>
                    <td><?php echo esc_html( implode( ', ', $svc_names ) ); ?></td>
                    <td><span class="bv-status bv-status-<?php echo esc_attr($p->status); ?>"><?php echo esc_html( ucfirst(str_replace('-', ' ', $p->status))); ?></span></td>
                    <td>
                        <div class="bv-cd-mini-progress"><div class="bv-cd-mini-fill" style="width:<?php echo max(0,min(100,$p->progress_percent)); ?>%"></div></div>
                        <small><?php echo $p->progress_percent; ?>%</small>
                    </td>
                    <td><?php echo esc_html( date( 'd M Y', strtotime($p->created_at) ) ); ?></td>
                    <td><a href="<?php echo $base_url; ?>&project_id=<?php echo $p->id; ?>" class="button button-small">Open</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
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
        if ( ! current_user_can( 'manage_options' ) ) {
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
        if ( ! current_user_can( 'manage_options' ) ) {
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
        if ( ! current_user_can( 'manage_options' ) ) {
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
        $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'status' => $status ), array( 'id' => $pid ), array( '%s' ), array( '%d' ) );
        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array( 'project_id' => $pid, 'entity_type' => 'project', 'entity_id' => $pid, 'action' => 'status_changed', 'description' => "Status changed to {$status}", 'user_id' => get_current_user_id() ), array( '%d','%s','%d','%s','%s','%d' ) );
        wp_send_json_success();
    }

    public function ajax_update_progress() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $pid = absint( $_POST['project_id'] );
        $progress = max(0, min(100, absint( $_POST['progress'] )));
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'progress_percent' => $progress ), array( 'id' => $pid ), array( '%d' ), array( '%d' ) );
        wp_send_json_success();
    }

    public function ajax_upload_report() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );

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
            'action' => 'uploaded', 'description' => "Report uploaded: {$title}", 'user_id' => get_current_user_id(),
        ), array( '%d','%s','%d','%s','%s','%d' ) );

        wp_send_json_success( 'Report uploaded' );
    }

    public function ajax_deliver_report() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
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
            'action' => 'delivered', 'description' => "Report delivered: {$report->title}", 'user_id' => get_current_user_id(),
        ), array( '%d','%s','%d','%s','%s','%d' ) );

        wp_send_json_success( 'Report delivered' );
    }

    public function ajax_send_message() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
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
            'user_id'     => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );
    }

    public function ajax_add_note() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
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
        if ( ! current_user_can( 'manage_options' ) ) {
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
        if ( ! current_user_can( 'manage_options' ) ) {
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
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied' );
        $doc_id = absint( $_GET['document_id'] ?? 0 );
        global $wpdb;
        $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_documents WHERE id = %d", $doc_id ) );
        if ( ! $doc || ! file_exists( $doc->filepath ) ) wp_die( 'Document not found' );
        // Validate MIME type against whitelist
        $allowed_mime_types = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'application/zip', 'application/octet-stream' );
        $mime = in_array( $doc->mime_type, $allowed_mime_types, true ) ? $doc->mime_type : 'application/octet-stream';
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . basename( $doc->filename ) . '"' );
        header( 'Content-Length: ' . $doc->filesize );
        readfile( $doc->filepath );
        exit;
    }

    public function ajax_download_report() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied' );
        $report_id = absint( $_GET['report_id'] ?? $_POST['report_id'] ?? 0 );
        if ( ! $report_id ) wp_die( 'Invalid report' );
        
        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_project_reports WHERE id = %d", $report_id ) );
        if ( ! $report || ! file_exists( $report->filepath ) ) {
            wp_die( 'Report not found' );
        }
        
        // Validate MIME type against whitelist
        $allowed_mime_types = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'application/zip', 'application/octet-stream' );
        $mime = in_array( $report->mime_type, $allowed_mime_types, true ) ? $report->mime_type : 'application/octet-stream';
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: inline; filename="' . basename( $report->filename ) . '"' );
        header( 'Content-Length: ' . $report->filesize );
        readfile( $report->filepath );
        exit;
    }

    public function ajax_create_project() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Access denied' );

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
     * Download questionnaire responses as professional HTML document.
     * Opens as a standalone file that can be printed to PDF or opened in Word.
     *
     * @since 2.7.21
     * @return void
     */
    public function ajax_download_questionnaire_html() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Access denied', 'businessvance-services-manager' ) );

        $project_id = absint( $_GET['project_id'] ?? $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) wp_die( esc_html__( 'Invalid project', 'businessvance-services-manager' ) );

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id
        ) );
        if ( ! $project ) wp_die( esc_html__( 'Project not found', 'businessvance-services-manager' ) );

        // Get services for this project
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.name, s.price FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id
             WHERE ps.project_id = %d ORDER BY s.name",
            $project_id
        ) );

        // Get all responses with service, section, question info
        $responses = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.response_value, r.service_id, r.created_at as answered_at,
                    q.label, q.type, q.help_text,
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

        // Group by service, then by section
        $grouped = array();
        foreach ( $responses as $r ) {
            $sname = $r->service_name;
            if ( ! isset( $grouped[ $sname ] ) ) {
                $grouped[ $sname ] = array();
            }
            $grouped[ $sname ][] = $r;
        }

        $has_multiple = count( $grouped ) > 1;
        $generated_at = current_time( 'mysql' );
        $nonce = wp_create_nonce( 'bv_consultant_dashboard' );
        $service_names = ! empty( $services ) ? wp_list_pluck( $services, 'name' ) : array();

        // Build professional HTML document
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
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1a1a2e; line-height: 1.6; padding: 40px; max-width: 900px; margin: 0 auto; background: #fff; padding-top: 70px; }

    /* Header */
    .doc-header { border-bottom: 3px solid #002B5C; padding-bottom: 20px; margin-bottom: 30px; }
    .doc-header h1 { font-size: 22px; color: #002B5C; margin-bottom: 4px; }
    .doc-header .subtitle { font-size: 13px; color: #666; }
    .doc-meta { display: flex; flex-wrap: wrap; gap: 24px; margin-top: 16px; font-size: 13px; color: #444; }
    .doc-meta span { display: inline-flex; align-items: center; gap: 6px; }
    .doc-meta strong { color: #1a1a2e; }

    /* TOC */
    .toc { background: #f8f9fb; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 28px; }
    .toc h3 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; margin-bottom: 10px; }
    .toc ol { margin-left: 20px; font-size: 13px; color: #002B5C; }
    .toc ol li { margin-bottom: 4px; }

    /* Service groups */
    .service-section { margin-bottom: 36px; page-break-inside: avoid; }
    .service-header { background: #002B5C; color: #fff; padding: 10px 16px; border-radius: 6px; font-size: 16px; font-weight: 700; margin-bottom: 16px; }

    /* Section headers */
    .section-header { font-size: 14px; font-weight: 700; color: #002B5C; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #d1d5db; padding-bottom: 6px; margin: 20px 0 12px; page-break-after: avoid; }

    /* Question rows */
    .q-row { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; page-break-inside: avoid; }
    .q-label { flex: 0 0 38%; font-weight: 600; font-size: 13px; color: #333; vertical-align: top; }
    .q-label .q-help { display: block; font-weight: 400; font-size: 11px; color: #999; margin-top: 2px; }
    .q-label .q-type { display: inline-block; font-weight: 400; font-size: 10px; color: #9CA3AF; background: #f3f4f6; padding: 1px 6px; border-radius: 3px; margin-left: 6px; text-transform: uppercase; }
    .q-value { flex: 1; font-size: 13px; color: #1a1a2e; white-space: pre-wrap; word-wrap: break-word; }
    .q-value.empty { color: #bbb; font-style: italic; }

    /* Table for repeatable data */
    .rep-table { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 12px; }
    .rep-table th { background: #f1f5f9; padding: 6px 10px; text-align: left; font-weight: 600; border: 1px solid #e2e8f0; }
    .rep-table td { padding: 5px 10px; border: 1px solid #e2e8f0; }

    /* File attachments */
    .mf-file { margin-bottom: 6px; font-size: 13px; }
    .mf-file-name { font-weight: 600; }
    .mf-file-size { color: #888; font-size: 12px; }
    .mf-file-dl { color: #002B5C; font-size: 12px; margin-left: 6px; }

    /* Signature */
    .sig-img { max-width: 300px; height: 80px; object-fit: contain; border-bottom: 1px solid #ccc; }

    /* Footer */
    .doc-footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #d1d5db; font-size: 11px; color: #999; text-align: center; }

    /* Print bar (screen only) */
    .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #002B5C; color: #fff; padding: 10px 20px; display: flex; justify-content: center; align-items: center; gap: 16px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15); font-size: 14px; }
    .print-bar span { opacity: 0.8; }
    .print-btn, .close-btn { padding: 8px 20px; border-radius: 6px; font-size: 14px; cursor: pointer; border: none; font-weight: 600; }
    .print-btn { background: #fff; color: #002B5C; }
    .print-btn:hover { background: #f0f0f0; }
    .close-btn { background: rgba(255,255,255,0.15); color: #fff; }
    .close-btn:hover { background: rgba(255,255,255,0.25); }

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
<div class="print-bar">
    <span>Use your browser's Print function to save this as a PDF</span>
    <button class="print-btn" onclick="window.print()">🖨 Save as PDF</button>
    <button class="close-btn" onclick="window.close()">✕ Close</button>
</div>

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
    <div style="margin-top:12px; font-size:12px; color:#666;">Services Purchased: <strong><?php echo esc_html( implode( ', ', $service_names ) ); ?></strong></div>
    <?php endif; ?>
</div>

<?php if ( $has_multiple ) : ?>
<div class="toc">
    <h3>Table of Contents — Services</h3>
    <ol>
    <?php foreach ( $grouped as $sname => $svc_responses ) : ?>
        <li><strong><?php echo esc_html( $sname ); ?></strong>
        <?php
        $sections_in_svc = array();
        foreach ( $svc_responses as $sr ) {
            if ( ! in_array( $sr->section_title, $sections_in_svc ) ) {
                $sections_in_svc[] = $sr->section_title;
            }
        }
        if ( count( $sections_in_svc ) > 1 ) {
            echo ' — Sections: ' . esc_html( implode( ', ', $sections_in_svc ) );
        }
        ?>
        </li>
    <?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<?php foreach ( $grouped as $service_name => $service_responses ) : ?>
<?php if ( $has_multiple ) : ?>
<div class="service-section" style="page-break-before: always;">
    <div class="service-header">Service: <?php echo esc_html( $service_name ); ?></div>
<?php endif; ?>

<?php
$current_section = '';
foreach ( $service_responses as $r ) :
    if ( $r->section_title !== $current_section ) :
        $current_section = $r->section_title;
?>
    <div class="section-header"><?php echo esc_html( $current_section ); ?></div>
<?php endif; ?>

    <div class="q-row">
        <div class="q-label"><?php echo esc_html( $r->label ); ?><span class="q-type"><?php echo esc_html( $r->type ); ?></span><?php if ( $r->help_text ) : ?><span class="q-help"><?php echo esc_html( $r->help_text ); ?></span><?php endif; ?></div>
        <div class="q-value<?php echo empty( $r->response_value ) || $r->response_value === '[]' ? ' empty' : ''; ?>">
        <?php
        $val = $r->response_value;
        $json_val = json_decode( $val, true );

        // Signature
        if ( preg_match( '/^data:image/', $val ) ) :
        ?>
            <img src="<?php echo esc_attr( $val ); ?>" alt="Client Signature" class="sig-img" />
        <?php
        // Multifile JSON with URLs
        elseif ( $json_val && isset( $json_val[0] ) && isset( $json_val[0]['url'] ) ) :
        ?>
            <div class="mf-list"><?php foreach ( $json_val as $f ) : ?>
                <div class="mf-file">
                    <span class="mf-file-name"><?php echo esc_html( $f['name'] ?? 'File' ); ?></span><?php if ( ! empty( $f['size'] ) ) : ?> <span class="mf-file-size"><?php echo esc_html( $f['size'] ); ?></span><?php endif; ?><?php if ( ! empty( $f['file'] ) ) : ?> <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bv_cd_download_qfile&nonce=' . $nonce . '&project_id=' . $project_id . '&file=' . rawurlencode( $f['file'] ) ) ); ?>" class="mf-file-dl">⬇ Download</a><?php elseif ( ! empty( $f['url'] ) ) : ?> <a href="<?php echo esc_url( $f['url'] ); ?>" class="mf-file-dl" target="_blank">⬇ Open</a><?php endif; ?>
                </div>
            <?php endforeach; ?></div>
        <?php
        // Repeatable table (2D array)
        elseif ( is_array( $json_val ) && isset( $json_val[0] ) && is_array( $json_val[0] ) ) :
            $col_count = 0;
            foreach ( $json_val as $row ) { if ( count( $row ) > $col_count ) $col_count = count( $row ); }
        ?>
            <table class="rep-table"><thead><tr><th>#</th><?php for ( $c = 0; $c < $col_count; $c++ ) : ?><th>Column <?php echo $c + 1; ?></th><?php endfor; ?></tr></thead>
            <tbody><?php foreach ( $json_val as $ri => $row ) : ?><tr><td><?php echo $ri + 1; ?></td><?php for ( $c = 0; $c < $col_count; $c++ ) : ?><td><?php echo esc_html( $row[ $c ] ?? '' ); ?></td><?php endfor; ?></tr><?php endforeach; ?></tbody></table>
        <?php
        // Address (keyed object)
        elseif ( is_array( $json_val ) && ! isset( $json_val[0] ) ) :
            $addr_labels = array( 'street' => 'Street', 'city' => 'City', 'state' => 'State/Province', 'zip' => 'ZIP/Postal Code', 'country' => 'Country' );
            foreach ( $addr_labels as $key => $label ) :
                if ( ! empty( $json_val[ $key ] ) ) :
                    echo esc_html( $label ) . ': ' . esc_html( $json_val[ $key ] ) . '<br>';
                endif;
            endforeach;
        // Checkbox/array values
        elseif ( is_array( $json_val ) ) :
            echo esc_html( implode( ', ', $json_val ) );
        // Plain text / empty
        else :
            echo empty( $val ) || $val === '[]' ? '—' : nl2br( esc_html( $val ) );
        endif;
        ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if ( $has_multiple ) : ?>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php if ( empty( $grouped ) ) : ?>
<div style="text-align:center;padding:60px 20px;color:#999;font-size:15px;">No questionnaire responses have been submitted for this project.</div>
<?php endif; ?>

<div class="doc-footer">
    Generated by BusinessVance Services Manager on <?php echo esc_html( date( 'd F Y \a\t H:i', strtotime( $generated_at ) ) ); ?>
    &nbsp;·&nbsp; Project: <?php echo esc_html( $project->project_number ); ?>
</div>

</body>
</html>
        <?php
        $html = ob_get_clean();

        // Output as HTML page (not attachment) — opens in new window, user can print/save as PDF
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
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Access denied', 'businessvance-services-manager' ) );

        $project_id = absint( $_GET['project_id'] ?? $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) wp_die( esc_html__( 'Invalid project', 'businessvance-services-manager' ) );

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id
        ) );
        if ( ! $project ) wp_die( esc_html__( 'Project not found', 'businessvance-services-manager' ) );

        // Get all responses with service, section, question info
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

        // Build CSV with service column
        $filename = sanitize_file_name( $project->project_number . '_' . sanitize_file_name( $project->client_name ) . '_questionnaire.csv' );
        $csv_rows = array();
        $csv_rows[] = array( 'Service', 'Section', 'Question', 'Type', 'Client Response' );

        foreach ( $responses as $r ) {
            // Flatten JSON values for CSV
            $val = $r->response_value;
            $json_val = json_decode( $val, true );
            if ( preg_match( '/^data:image/', $val ) ) {
                $val = '[Signature]';
            } elseif ( $json_val && isset( $json_val[0] ) && isset( $json_val[0]['url'] ) ) {
                // Multifile: list names with sizes and URLs
                $parts = array();
                foreach ( $json_val as $f ) {
                    $parts[] = ( $f['name'] ?? 'File' ) . ( ! empty( $f['size'] ) ? ' (' . $f['size'] . ')' : '' ) . ( ! empty( $f['url'] ) ? ' — ' . $f['url'] : '' );
                }
                $val = implode( '; ', $parts );
            } elseif ( is_array( $json_val ) ) {
                if ( isset( $json_val[0] ) && is_array( $json_val[0] ) ) {
                    // Repeatable table: flatten to "row1: col1 | col2; row2: col1 | col2"
                    $flat = array();
                    foreach ( $json_val as $row ) { $flat[] = implode( ' | ', $row ); }
                    $val = implode( '; ', $flat );
                } else {
                    $val = implode( ', ', $json_val );
                }
            }

            $csv_rows[] = array(
                $r->service_name,
                $r->section_title,
                $r->label,
                $r->type,
                $val,
            );
        }

        // Output CSV
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fprintf( $output, "\xEF\xBB\xBF" );
        foreach ( $csv_rows as $row ) {
            fputcsv( $output, $row );
        }
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
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Access denied', 'businessvance-services-manager' ) );

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
