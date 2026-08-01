<?php
/**
 * BusinessVance Client Portal
 *
 * Front-end portal for WooCommerce customers to manage their projects,
 * sign agreements, fill questionnaires, upload documents, view reports,
 * and communicate with consultants.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.0.0
 * @version 2.0.7 Added per-service document requirements, dynamic tab visibility,
 *          email notifications to consultant, responsive UI redesign.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Client_Portal {

    private $plugin_url;

    public function __construct() {
        $this->plugin_url = BV_PLUGIN_URL;
        add_shortcode( 'businessvance_client_portal', array( $this, 'render_portal' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bv_portal_upload_document', array( $this, 'ajax_upload_document' ) );
        add_action( 'wp_ajax_bv_portal_submit_questionnaire', array( $this, 'ajax_submit_questionnaire' ) );
        add_action( 'wp_ajax_bv_portal_sign_agreement', array( $this, 'ajax_sign_agreement' ) );
        add_action( 'wp_ajax_bv_portal_send_message', array( $this, 'ajax_send_message' ) );
        add_action( 'wp_ajax_bv_portal_download_report', array( $this, 'ajax_download_report' ) );
    }

    public function enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'businessvance_client_portal' ) ) {
            wp_enqueue_style( 'bv-client-portal', $this->plugin_url . 'assets/css/client-portal.css', array(), BV_VERSION );
            wp_enqueue_script( 'bv-client-portal', $this->plugin_url . 'assets/js/client-portal.js', array( 'jquery' ), BV_VERSION, true );
            wp_localize_script( 'bv-client-portal', 'bv_portal', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'bv_portal_action' ),
            ) );
        }
    }

    // =========================================================================
    // Service Requirements — determine which steps are needed per service
    // =========================================================================

    /**
     * Get aggregated service requirements for a project.
     *
     * Merges requirements from all services linked to the project.
     * Returns an associative array with boolean flags for each step.
     *
     * @since 2.0.7
     * @param int $project_id
     * @return array
     */
    private function get_service_requirements( $project_id ) {
        global $wpdb;

        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id
             WHERE ps.project_id = %d",
            $project_id
        ) );

        $requires = array(
            'agreement'    => false,
            'questionnaire' => false,
            'documents'    => false,
            'document_types' => array(),
        );

        if ( empty( $services ) ) {
            // Default: everything required if no services linked
            return array(
                'agreement'    => true,
                'questionnaire' => true,
                'documents'    => true,
                'document_types' => array(),
            );
        }

        foreach ( $services as $svc ) {
            if ( $svc->requires_agreement ) {
                $requires['agreement'] = true;
            }
            if ( $svc->requires_questionnaire ) {
                $requires['questionnaire'] = true;
            }
            $doc_types = json_decode( $svc->required_document_types, true );
            if ( is_array( $doc_types ) && ! empty( $doc_types ) ) {
                $requires['documents'] = true;
                $requires['document_types'] = array_merge( $requires['document_types'], $doc_types );
            }
        }

        $requires['document_types'] = array_unique( $requires['document_types'] );

        return $requires;
    }

    /**
     * Get consultant email for a project.
     *
     * Checks per-service consultant_email first, falls back to WordPress admin email.
     *
     * @since 2.0.7
     * @param int $project_id
     * @return string
     */
    private function get_consultant_email( $project_id ) {
        global $wpdb;

        $email = $wpdb->get_var( $wpdb->prepare(
            "SELECT s.consultant_email FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id
             WHERE ps.project_id = %d AND s.consultant_email != ''
             LIMIT 1",
            $project_id
        ) );

        if ( $email ) {
            return $email;
        }

        // Fallback to WordPress admin email
        return get_option( 'admin_email', '' );
    }

    /**
     * Send email notification to consultant when client takes action.
     *
     * @since 2.0.7
     * @param int    $project_id
     * @param string $action       e.g. 'signed_agreement', 'submitted_questionnaire', 'uploaded_document', 'sent_message'
     * @param string $description  Human-readable description
     * @return void
     */
    private function notify_consultant( $project_id, $action, $description ) {
        global $wpdb;

        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d",
            $project_id
        ) );
        if ( ! $project ) return;

        $to = $this->get_consultant_email( $project_id );
        if ( empty( $to ) ) return;

        $site_name = get_bloginfo( 'name', 'display' );
        $subject   = "[BusinessVance] Client Action on {$project->project_number} — {$action}";
        $body      = "Hello,\n\n";
        $body     .= "A client has taken action on project {$project->project_number}:\n\n";
        $body     .= "Client: {$project->client_name} ({$project->client_email})\n";
        $body     .= "Action: {$description}\n";
        $body     .= "Project: {$project->project_number}\n";
        $body     .= "Status: " . ucfirst( str_replace( '-', ' ', $project->status ) ) . "\n\n";
        $body     .= "Please log in to the Consultant Dashboard to review and take action.\n\n";
        $body     .= "— {$site_name}";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $project->client_email );

        wp_mail( $to, $subject, $body, $headers );
    }

    // =========================================================================
    // Access verification
    // =========================================================================

    private function verify_project_access( $project_id ) {
        if ( ! is_user_logged_in() ) return false;
        global $wpdb;
        $user_id = get_current_user_id();
        $project = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d AND client_user_id = %d",
            $project_id, $user_id
        ) );
        return $project ? $project : false;
    }

    // =========================================================================
    // Portal Renderer
    // =========================================================================

    public function render_portal( $atts ) {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_prompt();
        }

        global $wpdb;
        $user_id    = get_current_user_id();
        $user       = wp_get_current_user();
        $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
        $tab        = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';

        // Get all projects for this user
        $projects = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_projects WHERE client_user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );

        if ( empty( $projects ) ) {
            return $this->render_no_projects( $user );
        }

        // Validate project_id
        $active_project = null;
        if ( $project_id ) {
            foreach ( $projects as $p ) {
                if ( $p->id == $project_id ) { $active_project = $p; break; }
            }
        }
        if ( ! $active_project ) {
            $active_project = $projects[0];
            $project_id = $active_project->id;
        }

        // Load project data
        $services   = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d", $project_id ) );
        $agreement  = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_project_agreements WHERE project_id = %d ORDER BY id DESC LIMIT 1", $project_id ) );
        $documents  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_project_documents WHERE project_id = %d ORDER BY created_at DESC", $project_id ) );
        $reports    = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_project_reports WHERE project_id = %d AND status = 'delivered' ORDER BY created_at DESC", $project_id ) );
        $messages   = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_project_messages WHERE project_id = %d ORDER BY created_at ASC", $project_id ) );

        // Mark admin messages as read
        $wpdb->update( $wpdb->prefix . 'bv_project_messages',
            array( 'is_read' => 1 ),
            array( 'project_id' => $project_id, 'sender_type' => 'admin' ),
            array( '%d' ), array( '%d', '%s' )
        );

        // Get questionnaire data
        $questionnaire = $this->get_questionnaire_data( $project_id );

        // Determine which tabs to show based on service requirements
        $requirements = $this->get_service_requirements( $project_id );

        ob_start();
        ?>
        <div class="bv-portal" id="bv-portal-app">

            <!-- ====== HEADER ====== -->
            <header class="bv-header">
                <div class="bv-header-inner">
                    <div class="bv-brand">
                        <span class="bv-brand-icon">&#10022;</span>
                        <h1 class="bv-brand-name">BusinessVance</h1>
                    </div>
                    <div class="bv-header-user">
                        <span class="bv-greeting">Welcome, <strong><?php echo esc_html( $user->display_name ); ?></strong></span>
                        <?php if ( $active_project->client_company ) : ?>
                            <span class="bv-company-badge"><?php echo esc_html( $active_project->client_company ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="bv-layout">
                <!-- ====== SIDEBAR ====== -->
                <aside class="bv-sidebar">
                    <div class="bv-sidebar-title">My Projects</div>
                    <nav class="bv-project-list" aria-label="Project list">
                        <?php foreach ( $projects as $p ) : ?>
                        <a href="?project_id=<?php echo $p->id; ?>" class="bv-project-item <?php echo $p->id == $project_id ? 'active' : ''; ?>" aria-current="<?php echo $p->id == $project_id ? 'page' : 'false'; ?>">
                            <span class="bv-project-number"><?php echo esc_html( $p->project_number ); ?></span>
                            <span class="bv-status-pill bv-status-<?php echo esc_attr( $p->status ); ?>">
                                <?php echo esc_html( $this->status_label( $p->status ) ); ?>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </nav>
                </aside>

                <!-- ====== MAIN CONTENT ====== -->
                <main class="bv-main">
                    <!-- Tabs -->
                    <nav class="bv-tabs" aria-label="Project tabs">
                        <?php
                        $all_tabs = array(
                            'overview'     => 'Overview',
                            'agreement'    => 'Agreement',
                            'questionnaire' => 'Questionnaire',
                            'documents'    => 'Documents',
                            'reports'      => 'Reports',
                            'messages'     => 'Messages',
                        );

                        // Filter tabs based on service requirements
                        $visible_tabs = array();
                        foreach ( $all_tabs as $tab_id => $tab_label ) {
                            $show = true;
                            if ( $tab_id === 'agreement' && ! $requirements['agreement'] ) $show = false;
                            if ( $tab_id === 'questionnaire' && ! $requirements['questionnaire'] ) $show = false;
                            if ( $tab_id === 'documents' && ! $requirements['documents'] ) $show = false;
                            if ( $show ) $visible_tabs[ $tab_id ] = $tab_label;
                        }

                        // Auto-redirect if current tab is hidden
                        if ( ! isset( $visible_tabs[ $tab ] ) ) {
                            $tab = 'overview';
                        }

                        foreach ( $visible_tabs as $tab_id => $tab_label ) :
                            $is_active = ( $tab === $tab_id );
                            $count = '';
                            if ( $tab_id === 'messages' ) {
                                $unread = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}bv_project_messages WHERE project_id = %d AND sender_type = 'admin' AND is_read = 0",
                                    $project_id ) );
                                if ( $unread ) $count = " <span class=\"bv-tab-badge\">{$unread}</span>";
                            }
                            if ( $tab_id === 'reports' && ! empty( $reports ) ) {
                                $count = ' <span class="bv-tab-count">' . count( $reports ) . '</span>';
                            }
                            if ( $tab_id === 'documents' && ! empty( $documents ) ) {
                                $count = ' <span class="bv-tab-count">' . count( $documents ) . '</span>';
                            }
                        ?>
                        <a href="?project_id=<?php echo $project_id; ?>&tab=<?php echo $tab_id; ?>" class="bv-tab <?php echo $is_active ? 'active' : ''; ?>" aria-selected="<?php echo $is_active; ?>"><?php echo $tab_label . $count; ?></a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="bv-content">
                        <?php
                        switch ( $tab ) {
                            case 'overview':     echo $this->render_overview_tab( $active_project, $services, $requirements ); break;
                            case 'agreement':    echo $this->render_agreement_tab( $active_project, $agreement, $requirements ); break;
                            case 'questionnaire': echo $this->render_questionnaire_tab( $active_project, $questionnaire ); break;
                            case 'documents':    echo $this->render_documents_tab( $active_project, $documents, $requirements ); break;
                            case 'reports':      echo $this->render_reports_tab( $active_project, $reports ); break;
                            case 'messages':     echo $this->render_messages_tab( $active_project, $messages ); break;
                            default:             echo $this->render_overview_tab( $active_project, $services, $requirements );
                        }
                        ?>
                    </div>
                </main>
            </div>

            <footer class="bv-footer">
                <p>&copy; <?php echo date('Y'); ?> BusinessVance Consulting. All rights reserved.</p>
            </footer>
        </div>

        <style><?php echo $this->get_inline_css(); ?></style>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // Render helpers
    // =========================================================================

    private function render_login_prompt() {
        ob_start();
        ?>
        <div class="bv-portal bv-portal-empty" id="bv-portal-app">
            <header class="bv-header">
                <div class="bv-header-inner">
                    <div class="bv-brand">
                        <span class="bv-brand-icon">&#10022;</span>
                        <h1 class="bv-brand-name">BusinessVance</h1>
                    </div>
                </div>
            </header>
            <div class="bv-empty-body">
                <div class="bv-empty-state">
                    <div class="bv-empty-icon">&#128274;</div>
                    <h2>Login Required</h2>
                    <p>Please <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bv-link">log in</a> to access your client portal.</p>
                </div>
            </div>
            <footer class="bv-footer"><p>&copy; <?php echo date('Y'); ?> BusinessVance Consulting</p></footer>
        </div>
        <style><?php echo $this->get_inline_css(); ?></style>
        <?php
        return ob_get_clean();
    }

    private function render_no_projects( $user ) {
        ob_start();
        ?>
        <div class="bv-portal bv-portal-empty" id="bv-portal-app">
            <header class="bv-header">
                <div class="bv-header-inner">
                    <div class="bv-brand">
                        <span class="bv-brand-icon">&#10022;</span>
                        <h1 class="bv-brand-name">BusinessVance</h1>
                    </div>
                    <div class="bv-header-user">
                        <span class="bv-greeting">Welcome, <strong><?php echo esc_html( $user->display_name ); ?></strong></span>
                    </div>
                </div>
            </header>
            <div class="bv-empty-body">
                <div class="bv-empty-state">
                    <div class="bv-empty-icon">&#128203;</div>
                    <h2>No Active Projects</h2>
                    <p>Your projects will appear here after you purchase a service from our <a href="/services/" class="bv-link">services page</a>.</p>
                    <p class="bv-muted">Once you complete a purchase through our shop, a project will be automatically created and you can track its progress here.</p>
                </div>
            </div>
            <footer class="bv-footer"><p>&copy; <?php echo date('Y'); ?> BusinessVance Consulting</p></footer>
        </div>
        <style><?php echo $this->get_inline_css(); ?></style>
        <?php
        return ob_get_clean();
    }

    private function render_overview_tab( $project, $services, $requirements ) {
        $progress = max( 0, min( 100, (int) $project->progress_percent ) );
        ob_start();
        ?>
        <div class="bv-overview">
            <div class="bv-overview-top">
                <div>
                    <h2 class="bv-page-title"><?php echo esc_html( $project->project_number ); ?></h2>
                    <p class="bv-meta">Created <?php echo esc_html( date( 'd M Y \a\t H:i', strtotime( $project->created_at ) ) ); ?></p>
                </div>
                <span class="bv-status-pill bv-status-<?php echo esc_attr( $project->status ); ?> bv-status-lg">
                    <?php echo esc_html( $this->status_label( $project->status ) ); ?>
                </span>
            </div>

            <div class="bv-progress-box">
                <div class="bv-progress-top">
                    <span class="bv-progress-label">Project Progress</span>
                    <span class="bv-progress-pct"><?php echo $progress; ?>%</span>
                </div>
                <div class="bv-progress-track">
                    <div class="bv-progress-bar" style="width: <?php echo $progress; ?>%"></div>
                </div>
            </div>

            <!-- Step checklist -->
            <div class="bv-steps-grid">
                <?php if ( $requirements['agreement'] ) : ?>
                <div class="bv-step-card <?php echo $this->step_done_class( $project, 'agreement' ); ?>">
                    <div class="bv-step-icon <?php echo $this->step_done_icon( $project, 'agreement' ); ?>">
                        <?php echo $this->step_icon_html( $project, 'agreement' ); ?>
                    </div>
                    <h4>Sign Agreement</h4>
                    <p>Review and sign the confidentiality agreement</p>
                    <a href="?project_id=<?php echo $project->id; ?>&tab=agreement" class="bv-step-link">Go to Agreement &rarr;</a>
                </div>
                <?php endif; ?>
                <?php if ( $requirements['questionnaire'] ) : ?>
                <div class="bv-step-card <?php echo $this->step_done_class( $project, 'questionnaire' ); ?>">
                    <div class="bv-step-icon <?php echo $this->step_done_icon( $project, 'questionnaire' ); ?>">
                        <?php echo $this->step_icon_html( $project, 'questionnaire' ); ?>
                    </div>
                    <h4>Complete Questionnaire</h4>
                    <p>Fill out the service questionnaire</p>
                    <a href="?project_id=<?php echo $project->id; ?>&tab=questionnaire" class="bv-step-link">Go to Questionnaire &rarr;</a>
                </div>
                <?php endif; ?>
                <?php if ( $requirements['documents'] ) : ?>
                <div class="bv-step-card <?php echo $this->step_done_class( $project, 'documents' ); ?>">
                    <div class="bv-step-icon <?php echo $this->step_done_icon( $project, 'documents' ); ?>">
                        <?php echo $this->step_icon_html( $project, 'documents' ); ?>
                    </div>
                    <h4>Upload Documents</h4>
                    <p>Submit required documents for your project</p>
                    <a href="?project_id=<?php echo $project->id; ?>&tab=documents" class="bv-step-link">Go to Documents &rarr;</a>
                </div>
                <?php endif; ?>
                <div class="bv-step-card <?php echo $this->step_done_class( $project, 'reports' ); ?>">
                    <div class="bv-step-icon <?php echo $this->step_done_icon( $project, 'reports' ); ?>">
                        <?php echo $this->step_icon_html( $project, 'reports' ); ?>
                    </div>
                    <h4>Receive Report</h4>
                    <p>Your consultant will deliver the final report</p>
                    <a href="?project_id=<?php echo $project->id; ?>&tab=reports" class="bv-step-link">Go to Reports &rarr;</a>
                </div>
            </div>

            <div class="bv-info-grid">
                <div class="bv-info-card">
                    <h4 class="bv-card-title">Client Details</h4>
                    <div class="bv-detail-row"><span class="bv-detail-label">Name</span><span class="bv-detail-value"><?php echo esc_html( $project->client_name ); ?></span></div>
                    <div class="bv-detail-row"><span class="bv-detail-label">Email</span><span class="bv-detail-value"><?php echo esc_html( $project->client_email ); ?></span></div>
                    <?php if ( $project->client_phone ) : ?>
                    <div class="bv-detail-row"><span class="bv-detail-label">Phone</span><span class="bv-detail-value"><?php echo esc_html( $project->client_phone ); ?></span></div>
                    <?php endif; ?>
                    <?php if ( $project->client_company ) : ?>
                    <div class="bv-detail-row"><span class="bv-detail-label">Company</span><span class="bv-detail-value"><?php echo esc_html( $project->client_company ); ?></span></div>
                    <?php endif; ?>
                </div>
                <div class="bv-info-card">
                    <h4 class="bv-card-title">Services</h4>
                    <?php if ( empty( $services ) ) : ?>
                    <p class="bv-muted">No services linked</p>
                    <?php else : ?>
                    <ul class="bv-service-list">
                        <?php foreach ( $services as $svc ) : ?>
                        <li class="bv-service-item"><?php echo esc_html( $svc->name ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $project->notes ) : ?>
            <div class="bv-notes-box">
                <h4 class="bv-card-title">Notes from Consultant</h4>
                <div class="bv-notes-text"><?php echo nl2br( esc_html( $project->notes ) ); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_agreement_tab( $project, $agreement, $requirements ) {
        global $wpdb;
        $has_signed = $agreement && ! empty( $agreement->agreed_at );

        // Get agreement template - check per-service template, fallback to default
        $template = get_option( 'bv_agreement_template', '' );
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d", $project->id ) );
        if ( ! empty( $services ) ) {
            foreach ( $services as $svc ) {
                if ( $svc->requires_agreement && $svc->agreement_template_id > 0 ) {
                    $tpl = $wpdb->get_var( $wpdb->prepare(
                        "SELECT content FROM {$wpdb->prefix}bv_agreement_templates WHERE id = %d", $svc->agreement_template_id ) );
                    if ( $tpl ) { $template = $tpl; break; }
                }
            }
        }

        ob_start();
        ?>
        <div class="bv-section">
            <h2 class="bv-page-title">Agreement</h2>
            <?php if ( $has_signed ) : ?>
                <div class="bv-success-box">
                    <div class="bv-check-circle">&#10003;</div>
                    <div>
                        <h3>Agreement Signed</h3>
                        <p>Signed by <strong><?php echo esc_html( $agreement->full_name ); ?></strong> on <strong><?php echo esc_html( date( 'd M Y \a\t H:i', strtotime( $agreement->agreed_at ) ) ); ?></strong></p>
                    </div>
                </div>
                <div class="bv-agreement-content">
                    <?php echo wp_kses_post( $agreement->template_content ); ?>
                </div>
            <?php else : ?>
                <div class="bv-warning-box">
                    <p>Please read and sign the agreement below to proceed with your project.</p>
                </div>
                <div class="bv-agreement-content">
                    <?php echo wp_kses_post( $template ); ?>
                </div>
                <div class="bv-form-card">
                    <h3>Sign the Agreement</h3>
                    <p class="bv-muted">By signing below, you confirm that you have read and agree to the terms above.</p>
                    <div class="bv-form-group">
                        <label for="bv-sign-name">Full Legal Name</label>
                        <input type="text" id="bv-sign-name" value="<?php echo esc_attr( $project->client_name ); ?>" required />
                    </div>
                    <button type="button" class="bv-btn bv-btn-primary" onclick="bv_sign_agreement(<?php echo $project->id; ?>)">
                        &#10003; I Agree — Sign Agreement
                    </button>
                    <div id="bv-agreement-status"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_questionnaire_tab( $project, $sections ) {
        ob_start();
        ?>
        <div class="bv-section">
            <h2 class="bv-page-title">Client Questionnaire</h2>
            <p class="bv-section-desc">Please complete all required fields so we can prepare your report.</p>
            <?php if ( empty( $sections ) ) : ?>
                <div class="bv-empty-state-small">
                    <p>No questionnaire available for this project yet.</p>
                </div>
            <?php else : ?>
            <form id="bv-questionnaire-form" data-project-id="<?php echo $project->id; ?>">
                <?php foreach ( $sections as $idx => $section ) : ?>
                <div class="bv-q-section" id="section-<?php echo $idx; ?>">
                    <div class="bv-q-section-header">
                        <span class="bv-q-section-num"><?php echo $idx + 1; ?></span>
                        <div>
                            <h3><?php echo esc_html( $section->title ); ?></h3>
                            <?php if ( $section->description ) : ?>
                            <p class="bv-q-desc"><?php echo esc_html( $section->description ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bv-q-section-body">
                    <?php foreach ( $section->questions as $q ) : ?>
                    <div class="bv-q-field">
                        <label><?php echo esc_html( $q->label ); ?><?php if ( $q->is_required ) echo ' <span class="bv-required">*</span>'; ?></label>
                        <?php if ( $q->help_text ) : ?>
                        <small class="bv-q-help"><?php echo esc_html( $q->help_text ); ?></small>
                        <?php endif; ?>

                        <?php
                        $options = json_decode( $q->options, true );
                        $val = $q->response_value ? $q->response_value : '';
                        $qid = esc_attr( $q->id );
                        $req = $q->is_required ? 'required' : '';

                        if ( $q->type === 'heading' ) : ?>
                            <h4><?php echo esc_html( $q->label ); ?></h4>
                        <?php elseif ( $q->type === 'paragraph' ) : ?>
                            <p><?php echo esc_html( $q->label ); ?></p>
                        <?php elseif ( $q->type === 'textarea' ) : ?>
                            <textarea name="q_<?php echo $qid; ?>" <?php echo $req; ?> placeholder="<?php echo esc_attr( $q->placeholder ); ?>"><?php echo esc_textarea( $val ); ?></textarea>
                        <?php elseif ( $q->type === 'select' && is_array( $options ) ) : ?>
                            <select name="q_<?php echo $qid; ?>" <?php echo $req; ?>>
                                <option value="">— Select —</option>
                                <?php foreach ( $options as $opt ) : ?>
                                <option value="<?php echo esc_attr( is_array($opt) ? $opt['value'] ?? $opt[0] : $opt ); ?>" <?php selected( $val, is_array($opt) ? $opt['value'] ?? $opt[0] : $opt ); ?>>
                                    <?php echo esc_html( is_array($opt) ? $opt['label'] ?? $opt[1] : $opt ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ( $q->type === 'radio' && is_array( $options ) ) : ?>
                            <div class="bv-q-radio-group">
                                <?php foreach ( $options as $i => $opt ) : ?>
                                <label class="bv-q-radio"><input type="radio" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( is_array($opt) ? $opt['value'] ?? $opt[0] : $opt ); ?>" <?php checked( $val, is_array($opt) ? $opt['value'] ?? $opt[0] : $opt ); ?> <?php echo $req; ?> /> <?php echo esc_html( is_array($opt) ? $opt['label'] ?? $opt[1] : $opt ); ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ( $q->type === 'checkbox' && is_array( $options ) ) : ?>
                            <div class="bv-q-checkbox-group">
                                <?php $saved = json_decode( $val, true ) ?: array(); foreach ( $options as $opt ) : $ov = is_array($opt) ? $opt['value'] ?? $opt[0] : $opt; ?>
                                <label class="bv-q-checkbox"><input type="checkbox" name="q_<?php echo $qid; ?>[]" value="<?php echo esc_attr( $ov ); ?>" <?php echo in_array( $ov, $saved ) ? 'checked' : ''; ?> /> <?php echo esc_html( is_array($opt) ? $opt['label'] ?? $opt[1] : $opt ); ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ( $q->type === 'file' ) : ?>
                            <input type="file" name="q_<?php echo $qid; ?>" class="bv-q-file" data-question-id="<?php echo $qid; ?>" />
                            <?php if ( $val ) : ?><span class="bv-q-file-saved">&#10003; File uploaded</span><?php endif; ?>
                        <?php elseif ( $q->type === 'number' ) : ?>
                            <input type="number" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'email' ) : ?>
                            <input type="email" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'phone' ) : ?>
                            <input type="tel" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'date' ) : ?>
                            <input type="date" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" <?php echo $req; ?> />
                        <?php else : ?>
                            <input type="text" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="bv-q-actions">
                    <button type="submit" class="bv-btn bv-btn-primary bv-btn-lg">Save Questionnaire</button>
                    <span id="bv-q-status"></span>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_documents_tab( $project, $documents, $requirements ) {
        $doc_types = $requirements['document_types'];
        $has_required_types = ! empty( $doc_types );

        ob_start();
        ?>
        <div class="bv-section">
            <h2 class="bv-page-title">Documents</h2>
            <p class="bv-section-desc">Upload documents required for your project. Accepted formats: PDF, DOC, DOCX, JPG, PNG.</p>

            <?php if ( $has_required_types ) : ?>
            <div class="bv-info-note">
                <strong>Required Documents:</strong>
                <ul class="bv-required-types">
                    <?php foreach ( $doc_types as $dt ) : ?>
                    <li><?php echo esc_html( $this->document_type_label( $dt ) ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="bv-upload-box">
                <h4 class="bv-card-title">Upload Document</h4>
                <div class="bv-upload-fields">
                    <div class="bv-form-group">
                        <label for="bv-doc-category">Document Category</label>
                        <select id="bv-doc-category">
                            <?php if ( $has_required_types ) : ?>
                                <option value="">— Select Required Category —</option>
                                <?php foreach ( $doc_types as $dt ) : ?>
                                <option value="<?php echo esc_attr( $dt ); ?>"><?php echo esc_html( $this->document_type_label( $dt ) ); ?></option>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <option value="company-registration">Company Registration</option>
                                <option value="id">ID / Passport</option>
                                <option value="financial">Financial Statements</option>
                                <option value="logo">Logo / Branding</option>
                                <option value="branding">Branding Assets</option>
                                <option value="other">Other</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="bv-form-group">
                        <label for="bv-doc-file">File</label>
                        <div class="bv-file-input-wrap">
                            <input type="file" id="bv-doc-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                        </div>
                    </div>
                </div>
                <button type="button" class="bv-btn bv-btn-primary" onclick="bv_upload_document(<?php echo $project->id; ?>)">
                    Upload Document
                </button>
                <div id="bv-doc-status"></div>
            </div>

            <?php if ( ! empty( $documents ) ) : ?>
            <div class="bv-documents-list">
                <h4 class="bv-card-title">Uploaded Documents</h4>
                <div class="bv-table-responsive">
                    <table class="bv-table">
                        <thead>
                            <tr><th>Document</th><th>Category</th><th>Uploaded</th><th>Size</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $documents as $doc ) : ?>
                            <tr>
                                <td><?php echo esc_html( $doc->name ); ?></td>
                                <td><span class="bv-cat-badge"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $doc->category ) ) ); ?></span></td>
                                <td><?php echo esc_html( date( 'd M Y H:i', strtotime( $doc->created_at ) ) ); ?></td>
                                <td><?php echo esc_html( size_format( $doc->filesize ) ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else : ?>
            <div class="bv-empty-state-small">
                <p>No documents uploaded yet.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_reports_tab( $project, $reports ) {
        ob_start();
        ?>
        <div class="bv-section">
            <h2 class="bv-page-title">Reports</h2>

            <?php if ( ! empty( $reports ) ) : ?>
            <div class="bv-reports-grid">
                <?php foreach ( $reports as $rpt ) : ?>
                <div class="bv-report-card">
                    <div class="bv-report-card-icon">&#128196;</div>
                    <h5><?php echo esc_html( $rpt->title ); ?></h5>
                    <p class="bv-report-meta">Version <?php echo esc_html( $rpt->version ); ?> &mdash; Delivered <?php echo esc_html( date( 'd M Y', strtotime( $rpt->delivered_at ) ) ); ?></p>
                    <button type="button" class="bv-btn bv-btn-primary" onclick="bv_download_report(<?php echo $rpt->id; ?>)">
                        &#11015; Download Report
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="bv-empty-state-small">
                <div class="bv-empty-icon">&#128196;</div>
                <h3>No Reports Available Yet</h3>
                <p class="bv-muted">Your reports will appear here once they are completed and delivered by your consultant.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_messages_tab( $project, $messages ) {
        ob_start();
        ?>
        <div class="bv-section">
            <h2 class="bv-page-title">Messages</h2>

            <div class="bv-messages-thread" id="bv-messages-thread">
                <?php if ( empty( $messages ) ) : ?>
                <div class="bv-empty-state-small"><p>No messages yet. Start a conversation below.</p></div>
                <?php else : ?>
                <?php foreach ( $messages as $msg ) : ?>
                <div class="bv-message bv-message-<?php echo esc_attr( $msg->sender_type ); ?>">
                    <div class="bv-message-header">
                        <strong><?php echo esc_html( $msg->sender_name ); ?></strong>
                        <span class="bv-message-time"><?php echo esc_html( date( 'd M Y H:i', strtotime( $msg->created_at ) ) ); ?></span>
                    </div>
                    <div class="bv-message-body"><?php echo nl2br( esc_html( $msg->message ) ); ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="bv-message-form">
                <textarea id="bv-message-text" placeholder="Type your message..." rows="3"></textarea>
                <button type="button" class="bv-btn bv-btn-primary" onclick="bv_send_message(<?php echo $project->id; ?>)">
                    Send Message
                </button>
                <span id="bv-msg-status"></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // =========================================================================
    // Questionnaire data
    // =========================================================================

    private function get_questionnaire_data( $project_id ) {
        global $wpdb;

        $project_services = $wpdb->get_results( $wpdb->prepare(
            "SELECT service_id FROM {$wpdb->prefix}bv_project_services WHERE project_id = %d",
            $project_id
        ) );
        $service_ids = array();
        foreach ( $project_services as $ps ) {
            $service_ids[] = absint( $ps->service_id );
        }

        if ( empty( $service_ids ) ) return array();

        $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
        $template_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT questionnaire_template_id FROM {$wpdb->prefix}bv_services
             WHERE id IN ($placeholders) AND questionnaire_template_id > 0",
            ...$service_ids
        ) );

        if ( empty( $template_ids ) ) return array();

        $tpl_placeholders = implode( ',', array_fill( 0, count( $template_ids ), '%d' ) );
        $sections = $wpdb->get_results( $wpdb->prepare(
            "SELECT qs.*, qt.name as template_name
             FROM {$wpdb->prefix}bv_questionnaire_sections qs
             JOIN {$wpdb->prefix}bv_questionnaire_templates qt ON qs.template_id = qt.id
             WHERE qs.template_id IN ($tpl_placeholders) AND qt.status = 'published'
             ORDER BY qs.template_id, qs.display_order ASC",
            ...$template_ids
        ) );

        $seen_question_keys = array();
        $all_sections = array();

        foreach ( $sections as $section ) {
            $questions = $wpdb->get_results( $wpdb->prepare(
                "SELECT q.*, r.response_value
                 FROM {$wpdb->prefix}bv_questionnaire_questions q
                 LEFT JOIN {$wpdb->prefix}bv_questionnaire_responses r
                   ON r.question_id = q.id AND r.project_id = %d
                 WHERE q.section_id = %d
                 ORDER BY q.display_order ASC",
                $project_id, $section->id
            ) );

            $unique_questions = array();
            foreach ( $questions as $q ) {
                $key = $q->label . '|' . $q->type;
                if ( isset( $seen_question_keys[ $key ] ) ) continue;
                $seen_question_keys[ $key ] = true;
                $unique_questions[] = $q;
            }

            if ( ! empty( $unique_questions ) ) {
                $section->questions = $unique_questions;
                $all_sections[] = $section;
            }
        }

        return $all_sections;
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    public function ajax_upload_document() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( 'Project not found or access denied' );

        if ( empty( $_FILES['file'] ) ) wp_send_json_error( 'No file uploaded' );

        $file = $_FILES['file'];
        $allowed = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowed ) ) wp_send_json_error( 'File type not allowed' );

        $filename = $project_id . '_' . time() . '_' . sanitize_file_name( $file['name'] );
        $upload_path = BV_UPLOAD_DIR . '/' . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $upload_path ) ) {
            wp_send_json_error( 'Upload failed' );
        }

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'bv_project_documents', array(
            'project_id'  => $project_id,
            'service_id'  => 0,
            'name'        => sanitize_text_field( $_POST['name'] ?? $file['name'] ),
            'filename'    => $filename,
            'filepath'    => $upload_path,
            'filesize'    => $file['size'],
            'mime_type'   => $file['type'],
            'category'    => sanitize_text_field( $_POST['category'] ?? 'other' ),
            'uploaded_by' => 'client',
        ), array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id'  => $project_id,
            'entity_type' => 'document',
            'entity_id'   => $wpdb->insert_id,
            'action'      => 'uploaded',
            'description' => 'Client uploaded document: ' . sanitize_text_field( $_POST['name'] ?? $file['name'] ),
            'user_id'     => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        // Notify consultant (v2.0.7)
        $this->notify_consultant( $project_id, 'uploaded_document', 'Client uploaded a document: ' . sanitize_text_field( $_POST['name'] ?? $file['name'] ) );

        wp_send_json_success( 'Document uploaded successfully' );
    }

    public function ajax_submit_questionnaire() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( 'Project not found or access denied' );

        global $wpdb;
        $responses_table = $wpdb->prefix . 'bv_questionnaire_responses';

        foreach ( $_POST['responses'] as $question_id => $value ) {
            if ( is_array( $value ) ) $value = wp_json_encode( $value );
            $q_id = absint( $question_id );
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$responses_table} WHERE project_id = %d AND question_id = %d",
                $project_id, $q_id
            ) );
            $data = array(
                'project_id'     => $project_id,
                'service_id'     => 0,
                'question_id'    => $q_id,
                'response_value' => sanitize_text_field( $value ),
            );
            $format = array( '%d', '%d', '%d', '%s' );
            if ( $existing ) {
                $wpdb->update( $responses_table, $data, array( 'id' => $existing ), $format, array( '%d' ) );
            } else {
                $wpdb->insert( $responses_table, $data, $format );
            }
        }

        // Auto-progress
        if ( $project->status === 'awaiting-questionnaire' ) {
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => 'in-progress', 'progress_percent' => 50 ),
                array( 'id' => $project_id ),
                array( '%s', '%d' ), array( '%d' )
            );
        }

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'questionnaire', 'entity_id' => $project_id,
            'action' => 'submitted', 'description' => 'Client submitted questionnaire responses', 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        // Notify consultant (v2.0.7)
        $this->notify_consultant( $project_id, 'submitted_questionnaire', 'Client submitted the questionnaire for this project.' );

        wp_send_json_success( 'Questionnaire saved successfully' );
    }

    public function ajax_sign_agreement() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( 'Project not found or access denied' );

        $full_name = sanitize_text_field( $_POST['full_name'] );
        if ( empty( $full_name ) ) wp_send_json_error( 'Please enter your full name' );

        $template = get_option( 'bv_agreement_template', '' );

        global $wpdb;

        // Check for per-service agreement template
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id
             WHERE ps.project_id = %d", $project_id ) );
        if ( ! empty( $services ) ) {
            foreach ( $services as $svc ) {
                if ( $svc->requires_agreement && $svc->agreement_template_id > 0 ) {
                    $tpl = $wpdb->get_var( $wpdb->prepare(
                        "SELECT content FROM {$wpdb->prefix}bv_agreement_templates WHERE id = %d", $svc->agreement_template_id ) );
                    if ( $tpl ) { $template = $tpl; break; }
                }
            }
        }

        $wpdb->insert( $wpdb->prefix . 'bv_project_agreements', array(
            'project_id'      => $project_id,
            'template_content' => $template,
            'full_name'       => $full_name,
            'ip_address'      => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ),
            'user_agent'      => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ),
            'agreed_at'       => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );

        // Auto-progress
        $requirements = $this->get_service_requirements( $project_id );
        if ( $requirements['questionnaire'] ) {
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => 'awaiting-questionnaire', 'progress_percent' => 15 ),
                array( 'id' => $project_id ),
                array( '%s', '%d' ), array( '%d' )
            );
        } elseif ( $requirements['documents'] ) {
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => 'awaiting-documents', 'progress_percent' => 25 ),
                array( 'id' => $project_id ),
                array( '%s', '%d' ), array( '%d' )
            );
        } else {
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => 'in-progress', 'progress_percent' => 40 ),
                array( 'id' => $project_id ),
                array( '%s', '%d' ), array( '%d' )
            );
        }

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'agreement', 'entity_id' => $wpdb->insert_id,
            'action' => 'signed', 'description' => "Agreement signed by {$full_name}", 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        // Notify consultant (v2.0.7)
        $this->notify_consultant( $project_id, 'signed_agreement', "Client {$full_name} signed the agreement." );

        wp_send_json_success( 'Agreement signed successfully' );
    }

    public function ajax_send_message() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( 'Project not found or access denied' );

        $message = sanitize_textarea_field( $_POST['message'] );
        if ( empty( $message ) ) wp_send_json_error( 'Message cannot be empty' );

        $user = wp_get_current_user();

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'bv_project_messages', array(
            'project_id'  => $project_id,
            'sender_type' => 'client',
            'sender_name' => $user->display_name,
            'sender_email'=> $user->user_email,
            'message'     => $message,
            'is_read'     => 0,
        ), array( '%d', '%s', '%s', '%s', '%s', '%d' ) );

        // Notify consultant (v2.0.7)
        $this->notify_consultant( $project_id, 'sent_message', "Client sent a message on project {$project->project_number}." );

        wp_send_json_success( array(
            'sender_name' => $user->display_name,
            'sender_type' => 'client',
            'message'     => nl2br( esc_html( $message ) ),
            'created_at'  => date( 'd M Y H:i' ),
        ) );
    }

    public function ajax_download_report() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) die( 'Not logged in' );

        $report_id = absint( $_GET['report_id'] ?? $_POST['report_id'] ?? 0 );
        if ( ! $report_id ) die( 'Invalid report' );

        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.* FROM {$wpdb->prefix}bv_project_reports r
             JOIN {$wpdb->prefix}bv_projects p ON r.project_id = p.id
             WHERE r.id = %d AND p.client_user_id = %d AND r.status = 'delivered'",
            $report_id, get_current_user_id()
        ) );
        if ( ! $report || ! file_exists( $report->filepath ) ) {
            wp_die( 'Report not found' );
        }

        header( 'Content-Type: ' . $report->mime_type );
        header( 'Content-Disposition: attachment; filename="' . basename( $report->filename ) . '"' );
        header( 'Content-Length: ' . $report->filesize );
        readfile( $report->filepath );
        exit;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function status_label( $status ) {
        $labels = array(
            'awaiting-agreement' => 'Awaiting Agreement',
            'awaiting-questionnaire' => 'Awaiting Questionnaire',
            'awaiting-documents' => 'Awaiting Documents',
            'in-progress' => 'In Progress',
            'quality-check' => 'Quality Check',
            'completed' => 'Completed',
            'delivered' => 'Delivered',
            'archived' => 'Archived',
        );
        return $labels[ $status ] ?? ucfirst( str_replace( '-', ' ', $status ) );
    }

    private function document_type_label( $type ) {
        $labels = array(
            'company-registration' => 'Company Registration',
            'id' => 'ID / Passport',
            'financial' => 'Financial Statements',
            'logo' => 'Logo / Branding',
            'branding' => 'Branding Assets',
            'other' => 'Other',
        );
        return $labels[ $type ] ?? ucfirst( str_replace( '-', ' ', $type ) );
    }

    private function step_done_class( $project, $step ) {
        $status = $project->status;
        $done = false;
        switch ( $step ) {
            case 'agreement':
                $done = in_array( $status, array( 'awaiting-questionnaire', 'awaiting-documents', 'in-progress', 'quality-check', 'completed', 'delivered' ) );
                break;
            case 'questionnaire':
                $done = in_array( $status, array( 'awaiting-documents', 'in-progress', 'quality-check', 'completed', 'delivered' ) );
                break;
            case 'documents':
                $done = in_array( $status, array( 'in-progress', 'quality-check', 'completed', 'delivered' ) );
                break;
            case 'reports':
                $done = in_array( $status, array( 'delivered' ) );
                break;
        }
        return $done ? 'bv-step-done' : '';
    }

    private function step_done_icon( $project, $step ) {
        return $this->step_done_class( $project, $step ) ? 'bv-step-icon-done' : 'bv-step-icon-pending';
    }

    private function step_icon_html( $project, $step ) {
        return $this->step_done_class( $project, $step ) ? '&#10003;' : '&#9744;';
    }

    // =========================================================================
    // Professional CSS
    // =========================================================================

    private function get_inline_css() {
        return '

        /* ===== BASE ===== */
        .bv-portal {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1a1a2e;
            max-width: 1440px;
            margin: 0 auto;
            background: #ffffff;
            line-height: 1.6;
        }
        .bv-link { color: #0A2647; text-decoration: underline; font-weight: 500; }
        .bv-muted { color: #6b7280; font-size: 14px; }

        /* ===== HEADER ===== */
        .bv-header {
            background: linear-gradient(135deg, #0A2647 0%, #144272 100%);
            color: #fff;
        }
        .bv-header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .bv-brand { display: flex; align-items: center; gap: 10px; }
        .bv-brand-icon { font-size: 24px; color: #D4AF37; }
        .bv-brand-name { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .bv-header-user { font-size: 14px; color: rgba(255,255,255,0.85); text-align: right; }
        .bv-greeting strong { color: #fff; }
        .bv-company-badge {
            display: inline-block;
            background: rgba(212,175,55,0.2);
            color: #D4AF37;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }

        /* ===== LAYOUT ===== */
        .bv-layout { display: flex; min-height: 500px; border: 1px solid #e5e7eb; border-top: none; }

        /* ===== SIDEBAR ===== */
        .bv-sidebar {
            width: 280px;
            background: #f9fafb;
            border-right: 1px solid #e5e7eb;
            padding: 20px 16px;
            flex-shrink: 0;
        }
        .bv-sidebar-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin: 0 0 12px 4px;
        }
        .bv-project-list { display: flex; flex-direction: column; gap: 6px; }
        .bv-project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            text-decoration: none;
            color: #1a1a2e;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .bv-project-item:hover { border-color: #0A2647; background: #f0f4f8; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(10,38,71,0.08); }
        .bv-project-item.active { border-color: #0A2647; background: #e8f0fe; box-shadow: 0 0 0 2px rgba(10,38,71,0.3); }

        /* ===== MAIN ===== */
        .bv-main { flex: 1; min-width: 0; }

        /* ===== TABS ===== */
        .bv-tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            padding: 0 20px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .bv-tab {
            padding: 14px 20px;
            text-decoration: none;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            white-space: nowrap;
            transition: color 0.2s, border-color 0.2s;
        }
        .bv-tab:hover { color: #0A2647; }
        .bv-tab.active { color: #0A2647; border-bottom-color: #0A2647; font-weight: 600; }
        .bv-tab-badge { display: inline-block; background: #dc2626; color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 600; margin-left: 4px; }
        .bv-tab-count { display: inline-block; background: #e5e7eb; color: #374151; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 500; margin-left: 4px; }

        /* ===== CONTENT ===== */
        .bv-content { padding: 24px; }

        /* ===== STATUS PILLS ===== */
        .bv-status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .bv-status-lg { padding: 6px 16px; font-size: 12px; }
        .bv-status-awaiting-agreement { background: #fef3c7; color: #92400e; }
        .bv-status-awaiting-questionnaire { background: #fef3c7; color: #92400e; }
        .bv-status-awaiting-documents { background: #f3e8ff; color: #6b21a8; }
        .bv-status-in-progress { background: #dbeafe; color: #1e40af; }
        .bv-status-quality-check { background: #d1fae5; color: #065f46; }
        .bv-status-completed { background: #d1fae5; color: #065f46; }
        .bv-status-delivered { background: #059669; color: #fff; }
        .bv-status-archived { background: #f3f4f6; color: #6b7280; }

        /* ===== OVERVIEW ===== */
        .bv-overview-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .bv-page-title { margin: 0 0 4px; color: #0A2647; font-size: 22px; font-weight: 700; }
        .bv-meta { color: #6b7280; font-size: 14px; margin: 0; }

        /* ===== PROGRESS ===== */
        .bv-progress-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .bv-progress-top { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; font-weight: 600; }
        .bv-progress-pct { color: #0A2647; }
        .bv-progress-track { height: 10px; background: #e5e7eb; border-radius: 10px; overflow: hidden; }
        .bv-progress-bar { height: 100%; background: linear-gradient(90deg, #2A9D8F, #0A2647); border-radius: 10px; transition: width 0.6s ease; }

        /* ===== STEP CARDS ===== */
        .bv-steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .bv-step-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s ease;
        }
        .bv-step-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); transform: translateY(-1px); }
        .bv-step-card.bv-step-done { border-color: #059669; background: #f0fdf4; }
        .bv-step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .bv-step-icon-pending { background: #f3f4f6; color: #9ca3af; }
        .bv-step-icon-done { background: #059669; color: #fff; }
        .bv-step-card h4 { margin: 0 0 4px; font-size: 15px; color: #1a1a2e; }
        .bv-step-card p { margin: 0 0 12px; font-size: 13px; color: #6b7280; }
        .bv-step-link { font-size: 13px; color: #0A2647; font-weight: 500; text-decoration: none; }
        .bv-step-link:hover { text-decoration: underline; }

        /* ===== INFO GRID ===== */
        .bv-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .bv-info-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
        }
        .bv-card-title { margin: 0 0 16px; color: #0A2647; font-size: 16px; font-weight: 600; }
        .bv-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .bv-detail-row:last-child { border-bottom: none; }
        .bv-detail-label { color: #6b7280; font-weight: 500; }
        .bv-detail-value { color: #1a1a2e; font-weight: 500; text-align: right; }
        .bv-service-list { margin: 0; padding: 0; list-style: none; }
        .bv-service-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bv-service-item::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #2A9D8F;
            flex-shrink: 0;
        }
        .bv-service-item:last-child { border-bottom: none; }

        /* ===== NOTES ===== */
        .bv-notes-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 20px;
        }
        .bv-notes-box .bv-card-title { color: #92400e; }
        .bv-notes-text { font-size: 14px; color: #78350f; }

        /* ===== AGREEMENT ===== */
        .bv-success-box {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 24px;
            background: #f0fdf4;
            border: 1px solid #059669;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .bv-check-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #059669;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .bv-success-box h3 { margin: 0 0 4px; color: #065f46; font-size: 16px; }
        .bv-success-box p { margin: 0; font-size: 14px; color: #065f46; }
        .bv-warning-box {
            padding: 14px 18px;
            background: #fffbeb;
            border: 1px solid #f59e0b;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #92400e;
        }
        .bv-agreement-content {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 20px;
            max-height: 500px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.7;
        }
        .bv-form-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
        }
        .bv-form-card h3 { margin: 0 0 4px; color: #0A2647; font-size: 16px; }

        /* ===== FORMS ===== */
        .bv-form-group { margin-bottom: 16px; }
        .bv-form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #374151; }
        .bv-form-group input, .bv-form-group select, .bv-form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #fff;
            transition: border-color 0.2s;
        }
        .bv-form-group input:focus, .bv-form-group select:focus, .bv-form-group textarea:focus {
            outline: none;
            border-color: #0A2647;
            box-shadow: 0 0 0 3px rgba(10,38,71,0.1);
        }
        .bv-file-input-wrap input[type="file"] {
            padding: 10px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
        }
        .bv-upload-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* ===== BUTTONS ===== */
        .bv-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .bv-btn-primary { background: #0A2647; color: #fff; }
        .bv-btn-primary:hover { background: #144272; box-shadow: 0 4px 12px rgba(10,38,71,0.3); }
        .bv-btn-lg { padding: 12px 32px; font-size: 15px; }

        /* ===== QUESTIONNAIRE ===== */
        .bv-section-desc { color: #6b7280; font-size: 14px; margin: 0 0 20px; }
        .bv-q-section { margin-bottom: 24px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .bv-q-section-header {
            background: #0A2647;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .bv-q-section-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(212,175,55,0.25);
            color: #D4AF37;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .bv-q-section-header h3 { margin: 0; font-size: 15px; font-weight: 600; }
        .bv-q-desc { color: rgba(255,255,255,0.7); font-size: 12px; margin: 2px 0 0; }
        .bv-q-section-body { padding: 0; }
        .bv-q-field { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; }
        .bv-q-field:last-child { border-bottom: none; }
        .bv-q-field label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #374151; }
        .bv-q-field input[type="text"], .bv-q-field input[type="email"], .bv-q-field input[type="tel"], .bv-q-field input[type="number"], .bv-q-field input[type="date"], .bv-q-field input[type="file"], .bv-q-field select, .bv-q-field textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .bv-q-field input:focus, .bv-q-field select:focus, .bv-q-field textarea:focus {
            outline: none;
            border-color: #0A2647;
            box-shadow: 0 0 0 3px rgba(10,38,71,0.1);
        }
        .bv-q-field textarea { min-height: 80px; resize: vertical; }
        .bv-required { color: #dc2626; }
        .bv-q-help { display: block; font-size: 12px; color: #6b7280; margin-bottom: 6px; }
        .bv-q-radio-group, .bv-q-checkbox-group { display: flex; flex-direction: column; gap: 8px; }
        .bv-q-radio label, .bv-q-checkbox label { display: flex; align-items: center; gap: 8px; font-weight: 400; cursor: pointer; font-size: 14px; }
        .bv-q-actions { margin-top: 24px; }

        /* ===== DOCUMENTS ===== */
        .bv-info-note {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1e40af;
        }
        .bv-required-types { margin: 6px 0 0; padding-left: 20px; }
        .bv-required-types li { margin: 2px 0; }
        .bv-upload-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .bv-documents-list h4 { margin: 0 0 12px; }

        /* ===== TABLE ===== */
        .bv-table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -4px; padding: 0 4px; }
        .bv-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .bv-table th { background: #0A2647; color: #fff; padding: 10px 14px; text-align: left; font-size: 13px; font-weight: 600; white-space: nowrap; }
        .bv-table th:first-child { border-radius: 8px 0 0 0; }
        .bv-table th:last-child { border-radius: 0 8px 0 0; }
        .bv-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; }
        .bv-table tbody tr:hover td { background: #f9fafb; }
        .bv-cat-badge { display: inline-block; padding: 3px 10px; background: #f3e8ff; color: #6b21a8; border-radius: 6px; font-size: 12px; font-weight: 500; }

        /* ===== REPORTS ===== */
        .bv-reports-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-top: 16px; }
        .bv-report-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px;
            text-align: center;
            transition: all 0.2s ease;
        }
        .bv-report-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); transform: translateY(-2px); }
        .bv-report-card-icon { font-size: 40px; margin-bottom: 12px; }
        .bv-report-card h5 { margin: 0 0 8px; color: #0A2647; font-size: 16px; font-weight: 600; }
        .bv-report-meta { font-size: 13px; color: #6b7280; margin-bottom: 16px; }

        /* ===== MESSAGES ===== */
        .bv-messages-thread {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 16px;
            background: #f9fafb;
        }
        .bv-message { margin-bottom: 16px; padding: 12px 16px; border-radius: 10px; }
        .bv-message:last-child { margin-bottom: 0; }
        .bv-message-admin { background: #eff6ff; border: 1px solid #bfdbfe; }
        .bv-message-client { background: #fff; border: 1px solid #e5e7eb; margin-left: 40px; }
        .bv-message-header { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .bv-message-header strong { font-size: 14px; color: #0A2647; }
        .bv-message-time { font-size: 12px; color: #9ca3af; }
        .bv-message-body { font-size: 14px; line-height: 1.6; }
        .bv-message-form textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            box-sizing: border-box;
            resize: vertical;
        }
        .bv-message-form textarea:focus { outline: none; border-color: #0A2647; box-shadow: 0 0 0 3px rgba(10,38,71,0.1); }

        /* ===== EMPTY STATES ===== */
        .bv-empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        .bv-empty-state-small { text-align: center; padding: 40px 20px; color: #6b7280; }
        .bv-empty-icon { font-size: 48px; margin-bottom: 12px; }
        .bv-empty-state h3 { margin: 0 0 8px; color: #1a1a2e; }
        .bv-empty-state p { margin: 4px 0; font-size: 14px; }

        /* ===== FOOTER ===== */
        .bv-footer {
            background: #0A2647;
            color: rgba(255,255,255,0.5);
            text-align: center;
            padding: 16px;
            font-size: 13px;
            margin-top: auto;
        }

        /* ===== RESPONSIVE: TABLET ===== */
        @media (max-width: 1024px) {
            .bv-info-grid { grid-template-columns: 1fr; }
            .bv-upload-fields { grid-template-columns: 1fr; }
            .bv-steps-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* ===== RESPONSIVE: MOBILE ===== */
        @media (max-width: 768px) {
            .bv-layout { flex-direction: column; }
            .bv-sidebar { width: 100%; border-right: none; border-bottom: 1px solid #e5e7eb; padding: 16px; }
            .bv-sidebar-title { margin-bottom: 8px; }
            .bv-content { padding: 16px; }
            .bv-tabs { padding: 0 12px; }
            .bv-tab { padding: 12px 14px; font-size: 13px; }
            .bv-page-title { font-size: 18px; }
            .bv-overview-top { flex-direction: column; }
            .bv-steps-grid { grid-template-columns: 1fr; }
            .bv-reports-grid { grid-template-columns: 1fr; }
            .bv-message-client { margin-left: 0; }
            .bv-success-box { flex-direction: column; text-align: center; }
            .bv-info-grid { grid-template-columns: 1fr; }
            .bv-upload-fields { grid-template-columns: 1fr; }
            .bv-agreement-content { padding: 16px; }
            .bv-q-field { padding: 12px 16px; }
            .bv-q-section-header { padding: 12px 16px; }
        }
        ';
    }
}
