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
            'upload_dir' => BV_UPLOAD_DIR,
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
        <div class="wrap bv-cd-wrap" id="bv-cd-app">
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

        <style><?php echo $this->get_inline_css(); ?></style>
        <script>
        jQuery(document).ready(function($) {
            // Tab switching in project detail
            $('.bv-cd-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                $('.bv-cd-tab').removeClass('active');
                $(this).addClass('active');
                $('.bv-cd-panel').hide();
                $('#bv-cd-panel-' + tab).show();
            });

            // Status update
            $('.bv-cd-status-update').on('change', function() {
                var pid = $(this).data('project-id');
                var status = $(this).val();
                $.post(bv_cd.ajax_url, { action: 'bv_cd_update_project_status', nonce: bv_cd.nonce, project_id: pid, status: status }, function(r) {
                    if (r.success) { location.reload(); } else { alert(r.data || 'Error'); }
                });
            });

            // Progress update
            $('.bv-cd-progress-input').on('change', function() {
                var pid = $(this).data('project-id');
                var val = $(this).val();
                $.post(bv_cd.ajax_url, { action: 'bv_cd_update_progress', nonce: bv_cd.nonce, project_id: pid, progress: val }, function(r) {
                    if (r.success) { $('.bv-cd-progress-display').text(val + '%'); $('.bv-cd-progress-fill').css('width', val + '%'); }
                });
            });

            // Internal notes save
            $('#bv-cd-save-notes').on('click', function() {
                var pid = $(this).data('project-id');
                var notes = $('#bv-cd-internal-notes').val();
                $.post(bv_cd.ajax_url, { action: 'bv_cd_update_internal_notes', nonce: bv_cd.nonce, project_id: pid, notes: notes }, function(r) {
                    if (r.success) { alert('Notes saved'); } else { alert(r.data || 'Error'); }
                });
            });

            // Add note
            $('#bv-cd-add-note').on('click', function() {
                var pid = $(this).data('project-id');
                var content = $('#bv-cd-note-content').val();
                if (!content) return alert('Note cannot be empty');
                $.post(bv_cd.ajax_url, { action: 'bv_cd_add_note', nonce: bv_cd.nonce, project_id: pid, content: content }, function(r) {
                    if (r.success) {
                        var user = '<?php echo esc_js( wp_get_current_user()->display_name ); ?>';
                        var time = '<?php echo date("d M Y H:i"); ?>';
                        $('#bv-cd-notes-list').prepend('<div class="bv-cd-note"><strong>' + user + '</strong><span class="bv-cd-note-time">' + time + '</span><p>' + $('<div>').text(content).html() + '</p></div>');
                        $('#bv-cd-note-content').val('');
                    } else { alert(r.data || 'Error'); }
                });
            });

            // Send message
            $('#bv-cd-send-msg').on('click', function() {
                var pid = $(this).data('project-id');
                var msg = $('#bv-cd-msg-text').val();
                if (!msg) return alert('Message cannot be empty');
                $.post(bv_cd.ajax_url, { action: 'bv_cd_send_message', nonce: bv_cd.nonce, project_id: pid, message: msg }, function(r) {
                    if (r.success) {
                        var user = '<?php echo esc_js( wp_get_current_user()->display_name ); ?>';
                        var time = '<?php echo date("d M Y H:i"); ?>';
                        $('#bv-cd-msg-thread').append('<div class="bv-cd-msg bv-cd-msg-admin"><strong>' + user + '</strong><span>' + time + '</span><p>' + $('<div>').text(msg).html() + '</p></div>');
                        $('#bv-cd-msg-text').val('');
                        $('#bv-cd-msg-thread').scrollTop($('#bv-cd-msg-thread')[0].scrollHeight);
                    }
                });
            });

            // Report upload
            $('#bv-cd-upload-report').on('click', function() {
                var pid = $(this).data('project-id');
                var fileInput = $('#bv-cd-report-file')[0];
                var title = $('#bv-cd-report-title').val();
                if (!fileInput.files.length || !title) return alert('Please enter title and select file');
                var fd = new FormData();
                fd.append('file', fileInput.files[0]);
                fd.append('action', 'bv_cd_upload_report');
                fd.append('nonce', bv_cd.nonce);
                fd.append('project_id', pid);
                fd.append('title', title);
                $.ajax({ url: bv_cd.ajax_url, type: 'POST', data: fd, processData: false, contentType: false, success: function(r) {
                    if (r.success) { alert('Report uploaded'); location.reload(); } else { alert(r.data || 'Error uploading'); }
                }});
            });

            // Deliver report
            $('.bv-cd-deliver-report').on('click', function() {
                var rid = $(this).data('report-id');
                if (!confirm('Mark this report as delivered? The client will be able to download it.')) return;
                $.post(bv_cd.ajax_url, { action: 'bv_cd_deliver_report', nonce: bv_cd.nonce, report_id: rid }, function(r) {
                    if (r.success) { location.reload(); } else { alert(r.data || 'Error'); }
                });
            });

            // Create project
            $('#bv-cd-create-project').on('click', function() {
                var data = { action: 'bv_cd_create_project', nonce: bv_cd.nonce };
                data.client_name = $('#bv-cd-new-name').val();
                data.client_email = $('#bv-cd-new-email').val();
                data.client_phone = $('#bv-cd-new-phone').val();
                data.client_company = $('#bv-cd-new-company').val();
                data.notes = $('#bv-cd-new-notes').val();
                if (!data.client_name || !data.client_email) return alert('Name and email required');
                $.post(bv_cd.ajax_url, data, function(r) {
                    if (r.success) { location.href = '?page=bv-consultant-dashboard&project_id=' + r.data.project_id; }
                    else { alert(r.data || 'Error'); }
                });
            });
        });
        </script>
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

        // Questionnaire responses
        $responses = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, q.label, q.type, qs.title as section_title
             FROM {$wpdb->prefix}bv_questionnaire_responses r
             JOIN {$wpdb->prefix}bv_questionnaire_questions q ON r.question_id = q.id
             JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON q.section_id = qs.id
             WHERE r.project_id = %d
             ORDER BY qs.display_order, q.display_order", $project_id ) );

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
            <?php if ( ! empty( $responses ) ) : ?>
            <div style="margin-bottom:12px;">
                <button type="button" class="button button-secondary" onclick="bv_cd_download_questionnaire(<?php echo $project_id; ?>)">⬇ <?php echo esc_html__( 'Download Responses (CSV)', 'businessvance-services-manager' ); ?></button>
            </div>
            <?php endif; ?>
            <?php if (empty($responses)) : ?>
            <div class="bv-cd-card"><p><?php echo esc_html__( 'No questionnaire responses submitted yet.', 'businessvance-services-manager' ); ?></p></div>
            <?php else : ?>
            <table class="widefat striped bv-cd-table">
                <thead><tr><th><?php echo esc_html__( 'Section', 'businessvance-services-manager' ); ?></th><th><?php echo esc_html__( 'Question', 'businessvance-services-manager' ); ?></th><th><?php echo esc_html__( 'Response', 'businessvance-services-manager' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ($responses as $r) : ?>
                <tr>
                    <td><small><?php echo esc_html($r->section_title); ?></small></td>
                    <td><?php echo esc_html($r->label); ?></td>
                    <td><?php echo nl2br(esc_html($r->response_value)); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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

    public function ajax_update_project_status() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        $pid = absint( $_POST['project_id'] );
        $status = sanitize_text_field( $_POST['status'] );
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'status' => $status ), array( 'id' => $pid ), array( '%s' ), array( '%d' ) );
        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array( 'project_id' => $pid, 'entity_type' => 'project', 'entity_id' => $pid, 'action' => 'status_changed', 'description' => "Status changed to {$status}", 'user_id' => get_current_user_id() ), array( '%d','%s','%d','%s','%s','%d' ) );
        wp_send_json_success();
    }

    public function ajax_update_progress() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
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
        $pid = absint( $_POST['project_id'] );
        $notes = sanitize_textarea_field( $_POST['notes'] );
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'bv_projects', array( 'internal_notes' => $notes ), array( 'id' => $pid ), array( '%s' ), array( '%d' ) );
        wp_send_json_success();
    }

    public function ajax_get_messages() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
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
        header( 'Content-Type: ' . $doc->mime_type );
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
        
        header( 'Content-Type: ' . $report->mime_type );
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

        // Generate project number
        $projects_table = $wpdb->prefix . 'bv_projects';
        $year = date( 'Y' );
        $last = $wpdb->get_var( "SELECT project_number FROM {$projects_table} WHERE project_number LIKE 'BV-{$year}-%' ORDER BY project_number DESC LIMIT 1" );
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

        wp_send_json_success( array( 'project_id' => $wpdb->insert_id, 'project_number' => $project_number ) );
    }

    /**
     * Download questionnaire responses as CSV.
     *
     * @since 2.5.0
     * @return void
     */
    public function ajax_download_questionnaire() {
        check_ajax_referer( 'bv_consultant_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Access denied', 'businessvance-services-manager' ) );

        $project_id = absint( $_GET['project_id'] ?? $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) wp_die( esc_html__( 'Invalid project', 'businessvance-services-manager' ) );

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d",
            $project_id
        ) );
        if ( ! $project ) wp_die( esc_html__( 'Project not found', 'businessvance-services-manager' ) );

        // Get all responses with section/question info
        $responses = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.response_value, q.label, q.type, qs.title as section_title, qs.display_order as section_order, q.display_order as question_order
             FROM {$wpdb->prefix}bv_questionnaire_responses r
             JOIN {$wpdb->prefix}bv_questionnaire_questions q ON r.question_id = q.id
             JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON q.section_id = qs.id
             WHERE r.project_id = %d
             ORDER BY qs.display_order, q.display_order",
            $project_id
        ) );

        // Build CSV
        $filename = sanitize_file_name( $project->project_number . '_' . sanitize_file_name( $project->client_name ) . '_questionnaire.csv' );
        $csv_rows = array();
        $csv_rows[] = array( 'Section', 'Question', 'Type', 'Client Response' );

        foreach ( $responses as $r ) {
            $csv_rows[] = array(
                $r->section_title,
                $r->label,
                $r->type,
                $r->response_value,
            );
        }

        // Output CSV
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        // BOM for Excel UTF-8 compatibility
        fprintf( $output, "\xEF\xBB\xBF" );
        foreach ( $csv_rows as $row ) {
            fputcsv( $output, $row );
        }
        fclose( $output );
        exit;
    }

    private function get_inline_css() {
        $settings = BV_Settings::get_settings();
        $primary   = esc_attr( $settings['primary_color'] );
        $secondary = esc_attr( $settings['secondary_color'] );

        return '
    .bv-cd-wrap { max-width: 1400px; }
    .bv-cd-title { display: flex; align-items: center; gap: 10px; color: ' . $primary . '; }
    .bv-cd-icon { font-size: 28px; }
    .bv-cd-stats { display: flex; gap: 16px; margin: 20px 0; }
    .bv-cd-stat { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px 24px; text-align: center; min-width: 100px; }
    .bv-cd-stat-num { display: block; font-size: 28px; font-weight: 700; color: ' . $primary . '; }
    .bv-cd-stat-label { font-size: 12px; color: #666; text-transform: uppercase; }
    .bv-cd-stat-active .bv-cd-stat-num { color: ' . $secondary . '; }
    .bv-cd-stat-waiting .bv-cd-stat-num { color: #F4A261; }
    .bv-cd-stat-done .bv-cd-stat-num { color: #27AE60; }
    .bv-cd-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
    .bv-cd-filter-form { display: flex; gap: 8px; flex-wrap: wrap; }
    .bv-cd-back { margin-bottom: 12px; }
    .bv-cd-project-header { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
    .bv-cd-project-header h2 { margin: 0; color: ' . $primary . '; }
    .bv-cd-project-header p { margin: 4px 0 0; font-size: 14px; color: #666; }
    .bv-cd-project-controls { display: flex; flex-direction: column; gap: 12px; align-items: flex-end; }
    .bv-cd-status-select label, .bv-cd-progress-control label { font-size: 13px; font-weight: 600; color: #333; }
    .bv-cd-progress-input { width: 200px; }
    .bv-cd-tabs { display: flex; border-bottom: 2px solid #e0e0e0; margin-bottom: 16px; overflow-x: auto; }
    .bv-cd-tab { padding: 10px 20px; border: none; background: none; cursor: pointer; font-size: 14px; font-weight: 500; color: #666; border-bottom: 2px solid transparent; margin-bottom: -2px; }
    .bv-cd-tab:hover { color: ' . $primary . '; }
    .bv-cd-tab.active { color: ' . $primary . '; border-bottom-color: ' . $primary . '; }
    .bv-cd-panel { padding: 4px 0; }
    .bv-cd-overview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .bv-cd-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
    .bv-cd-card h4 { margin: 0 0 12px; color: ' . $primary . '; font-size: 15px; }
    .bv-cd-card ul { margin: 0; padding-left: 20px; }
    .bv-cd-card ul li { margin: 4px 0; }
    .bv-cd-card p { margin: 4px 0; font-size: 14px; }
    .bv-cd-mini-progress { width: 60px; height: 6px; background: #e0e0e0; border-radius: 3px; display: inline-block; vertical-align: middle; margin-right: 6px; }
    .bv-cd-mini-fill { height: 100%; background: ' . $secondary . '; border-radius: 3px; }
    .bv-status { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .bv-status-awaiting-agreement { background: #FFF3CD; color: #856404; }
    .bv-status-awaiting-questionnaire { background: #FFF3CD; color: #856404; }
    .bv-status-awaiting-documents { background: #E8DAEF; color: #6C3483; }
    .bv-status-in-progress { background: #D6EAF8; color: #1A5276; }
    .bv-status-quality-check { background: #D1F2EB; color: #0E6655; }
    .bv-status-completed { background: #D5F5E3; color: #1E8449; }
    .bv-status-delivered { background: #27AE60; color: #fff; }
    .bv-status-archived { background: #E5E7EB; color: #6B7280; }
    .bv-cd-msg-thread { border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; max-height: 400px; overflow-y: auto; margin-bottom: 16px; background: #fafafa; }
    .bv-cd-msg { margin-bottom: 12px; padding: 10px 14px; border-radius: 6px; }
    .bv-cd-msg strong { font-size: 13px; }
    .bv-cd-msg span { font-size: 11px; color: #999; margin-left: 8px; }
    .bv-cd-msg p { margin: 6px 0 0; font-size: 14px; line-height: 1.5; }
    .bv-cd-msg-admin { background: #E3F2FD; border-left: 3px solid ' . $primary . '; }
    .bv-cd-msg-client { background: #fff; border-left: 3px solid ' . $secondary . '; }
    .bv-cd-note { padding: 12px; border-bottom: 1px solid #e0e0e0; }
    .bv-cd-note strong { font-size: 13px; color: ' . $primary . '; }
    .bv-cd-note-time { font-size: 11px; color: #999; margin-left: 8px; }
    .bv-cd-note p { margin: 6px 0 0; font-size: 14px; }
    @media (max-width: 782px) {
        .bv-cd-stats { flex-wrap: wrap; }
        .bv-cd-overview-grid { grid-template-columns: 1fr; }
        .bv-cd-project-header { flex-direction: column; }
    }
    ';
    }
}
