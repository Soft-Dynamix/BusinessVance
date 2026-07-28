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

    public function render_portal( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="bv-portal-login-message"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to access your client portal.</p></div>';
        }

        global $wpdb;
        $user_id    = get_current_user_id();
        $user       = wp_get_current_user();
        $project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
        $tab        = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';

        // Get all projects for this user
        $projects_table = $wpdb->prefix . 'bv_projects';
        $projects = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$projects_table} WHERE client_user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );

        // No projects yet
        if ( empty( $projects ) ) {
            return $this->render_no_projects( $user );
        }

        // Validate project_id belongs to this user
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
            "SELECT * FROM {$wpdb->prefix}bv_project_reports WHERE project_id = %d ORDER BY created_at DESC", $project_id ) );
        $messages   = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bv_project_messages WHERE project_id = %d ORDER BY created_at ASC", $project_id ) );

        // Mark messages as read
        $wpdb->update( $wpdb->prefix . 'bv_project_messages',
            array( 'is_read' => 1 ),
            array( 'project_id' => $project_id, 'sender_type' => 'admin' ),
            array( '%d', '%d' )
        );

        // Get questionnaire data
        $questionnaire = $this->get_questionnaire_data( $project_id );

        ob_start();
        ?>
        <div class="bv-portal" id="bv-portal-app">
            <style><?php echo $this->get_inline_css(); ?></style>

            <!-- Header -->
            <div class="bv-portal-header">
                <div class="bv-portal-header-inner">
                    <div class="bv-portal-brand">
                        <span class="bv-portal-logo">✦</span>
                        <h1>BusinessVance</h1>
                    </div>
                    <div class="bv-portal-user-info">
                        <span>Welcome, <strong><?php echo esc_html( $user->display_name ); ?></strong></span>
                        <?php if ( $active_project->client_company ) : ?>
                            <span class="bv-portal-company"><?php echo esc_html( $active_project->client_company ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bv-portal-body">
                <!-- Sidebar: Project List -->
                <div class="bv-portal-sidebar">
                    <h3>My Projects</h3>
                    <div class="bv-portal-project-list">
                        <?php foreach ( $projects as $p ) : ?>
                        <a href="?project_id=<?php echo $p->id; ?>" class="bv-portal-project-item <?php echo $p->id == $project_id ? 'active' : ''; ?>">
                            <span class="bv-portal-project-num"><?php echo esc_html( $p->project_number ); ?></span>
                            <span class="bv-portal-project-status-badge bv-status-<?php echo esc_attr( $p->status ); ?>">
                                <?php echo esc_html( $this->status_label( $p->status ) ); ?>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="bv-portal-main">
                    <!-- Tabs -->
                    <div class="bv-portal-tabs">
                        <?php
                        $tabs = array( 'overview' => 'Overview', 'agreement' => 'Agreement', 'questionnaire' => 'Questionnaire', 'documents' => 'Documents', 'reports' => 'Reports', 'messages' => 'Messages' );
                        foreach ( $tabs as $tab_id => $tab_label ) :
                            $active = ( $tab === $tab_id ) ? 'active' : '';
                            $count = '';
                            if ( $tab_id === 'messages' ) {
                                $unread = $wpdb->get_var( $wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}bv_project_messages WHERE project_id = %d AND sender_type = 'admin' AND is_read = 0",
                                    $project_id ) );
                                if ( $unread ) $count = " <span class=\"bv-badge\">{$unread}</span>";
                            }
                            if ( $tab_id === 'reports' && ! empty( $reports ) ) {
                                $count = ' <span class="bv-count">' . count( $reports ) . '</span>';
                            }
                            if ( $tab_id === 'documents' && ! empty( $documents ) ) {
                                $count = ' <span class="bv-count">' . count( $documents ) . '</span>';
                            }
                        ?>
                        <a href="?project_id=<?php echo $project_id; ?>&tab=<?php echo $tab_id; ?>" class="bv-portal-tab <?php echo $active; ?>"><?php echo $tab_label . $count; ?></a>
                        <?php endforeach; ?>
                    </div>

                    <div class="bv-portal-content">
                        <?php
                        switch ( $tab ) {
                            case 'overview': echo $this->render_overview_tab( $active_project, $services ); break;
                            case 'agreement': echo $this->render_agreement_tab( $active_project, $agreement ); break;
                            case 'questionnaire': echo $this->render_questionnaire_tab( $active_project, $questionnaire ); break;
                            case 'documents': echo $this->render_documents_tab( $active_project, $documents ); break;
                            case 'reports': echo $this->render_reports_tab( $active_project, $reports ); break;
                            case 'messages': echo $this->render_messages_tab( $active_project, $messages ); break;
                            default: echo $this->render_overview_tab( $active_project, $services );
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="bv-portal-footer">
                <p>&copy; <?php echo date('Y'); ?> BusinessVance Consulting. All rights reserved.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_no_projects( $user ) {
        ob_start();
        ?>
        <div class="bv-portal bv-portal-empty" id="bv-portal-app">
            <style><?php echo $this->get_inline_css(); ?></style>
            <div class="bv-portal-header">
                <div class="bv-portal-header-inner">
                    <div class="bv-portal-brand">
                        <span class="bv-portal-logo">✦</span>
                        <h1>BusinessVance</h1>
                    </div>
                    <div class="bv-portal-user-info">
                        <span>Welcome, <strong><?php echo esc_html( $user->display_name ); ?></strong></span>
                    </div>
                </div>
            </div>
            <div class="bv-portal-body">
                <div class="bv-portal-empty-state">
                    <div class="bv-portal-empty-icon">📋</div>
                    <h2>No Active Projects</h2>
                    <p>Your projects will appear here after you purchase a service from our <a href="/services/">services page</a>.</p>
                    <p>Once you complete a purchase through our shop, a project will be automatically created and you can track its progress here.</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_overview_tab( $project, $services ) {
        $progress = max( 0, min( 100, (int) $project->progress_percent ) );
        ob_start();
        ?>
        <div class="bv-overview">
            <div class="bv-overview-header">
                <div>
                    <h2><?php echo esc_html( $project->project_number ); ?></h2>
                    <p class="bv-subtitle">Created <?php echo esc_html( date( 'd M Y H:i', strtotime( $project->created_at ) ) ); ?></p>
                </div>
                <span class="bv-portal-project-status-badge bv-status-<?php echo esc_attr( $project->status ); ?>">
                    <?php echo esc_html( $this->status_label( $project->status ) ); ?>
                </span>
            </div>

            <div class="bv-progress-section">
                <div class="bv-progress-header">
                    <span>Project Progress</span>
                    <span class="bv-progress-percent"><?php echo $progress; ?>%</span>
                </div>
                <div class="bv-progress-bar">
                    <div class="bv-progress-fill" style="width: <?php echo $progress; ?>%"></div>
                </div>
            </div>

            <div class="bv-info-grid">
                <div class="bv-info-card">
                    <h4>Client Details</h4>
                    <p><strong>Name:</strong> <?php echo esc_html( $project->client_name ); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html( $project->client_email ); ?></p>
                    <?php if ( $project->client_phone ) : ?>
                    <p><strong>Phone:</strong> <?php echo esc_html( $project->client_phone ); ?></p>
                    <?php endif; ?>
                    <?php if ( $project->client_company ) : ?>
                    <p><strong>Company:</strong> <?php echo esc_html( $project->client_company ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="bv-info-card">
                    <h4>Services</h4>
                    <?php if ( empty( $services ) ) : ?>
                    <p>No services linked</p>
                    <?php else : ?>
                    <ul class="bv-service-list">
                        <?php foreach ( $services as $svc ) : ?>
                        <li><?php echo esc_html( $svc->name ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( $project->notes ) : ?>
            <div class="bv-notes-section">
                <h4>Notes from Consultant</h4>
                <div class="bv-notes-content"><?php echo nl2br( esc_html( $project->notes ) ); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_agreement_tab( $project, $agreement ) {
        $template = get_option( 'bv_agreement_template', '' );
        $has_signed = $agreement && ! empty( $agreement->agreed_at );
        ob_start();
        ?>
        <div class="bv-agreement-section">
            <h2>Agreement</h2>
            <?php if ( $has_signed ) : ?>
                <div class="bv-agreement-signed">
                    <div class="bv-check-icon">✓</div>
                    <h3>Agreement Signed</h3>
                    <p>Signed by <strong><?php echo esc_html( $agreement->full_name ); ?></strong> on <strong><?php echo esc_html( date( 'd M Y \a\t H:i', strtotime( $agreement->agreed_at ) ) ); ?></strong></p>
                </div>
                <div class="bv-agreement-content">
                    <?php echo wp_kses_post( $agreement->template_content ); ?>
                </div>
            <?php else : ?>
                <div class="bv-agreement-warning">
                    <p>⚠️ Please read and sign the agreement below to proceed with your project.</p>
                </div>
                <div class="bv-agreement-content">
                    <?php echo wp_kses_post( $template ); ?>
                </div>
                <div class="bv-agreement-sign-form">
                    <h3>Sign the Agreement</h3>
                    <p>By signing below, you confirm that you have read and agree to the terms above.</p>
                    <div class="bv-form-group">
                        <label>Full Legal Name</label>
                        <input type="text" id="bv-sign-name" value="<?php echo esc_attr( $project->client_name ); ?>" required />
                    </div>
                    <button type="button" class="bv-btn bv-btn-primary" onclick="bv_sign_agreement(<?php echo $project->id; ?>)">
                        ✓ I Agree — Sign Agreement
                    </button>
                    <div id="bv-agreement-status"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_questionnaire_data( $project_id ) {
        global $wpdb;
        $sections = $wpdb->get_results(
            "SELECT qs.* FROM {$wpdb->prefix}bv_questionnaire_sections qs
             JOIN {$wpdb->prefix}bv_questionnaire_templates qt ON qs.template_id = qt.id
             WHERE qt.status = 'published'
             ORDER BY qs.display_order ASC"
        );
        foreach ( $sections as &$section ) {
            $section->questions = $wpdb->get_results( $wpdb->prepare(
                "SELECT q.*, r.response_value
                 FROM {$wpdb->prefix}bv_questionnaire_questions q
                 LEFT JOIN {$wpdb->prefix}bv_questionnaire_responses r
                   ON r.question_id = q.id AND r.project_id = %d
                 WHERE q.section_id = %d
                 ORDER BY q.display_order ASC",
                $project_id, $section->id
            ) );
        }
        return $sections;
    }

    private function render_questionnaire_tab( $project, $sections ) {
        ob_start();
        ?>
        <div class="bv-questionnaire-section">
            <h2>Client Questionnaire</h2>
            <p>Please complete all required fields so we can prepare your report.</p>
            <?php if ( empty( $sections ) ) : ?>
                <div class="bv-empty-state">No questionnaire available for this project yet.</div>
            <?php else : ?>
            <form id="bv-questionnaire-form" data-project-id="<?php echo $project->id; ?>">
                <?php foreach ( $sections as $section ) : ?>
                <div class="bv-q-section">
                    <h3><?php echo esc_html( $section->title ); ?></h3>
                    <?php if ( $section->description ) : ?>
                    <p class="bv-q-desc"><?php echo esc_html( $section->description ); ?></p>
                    <?php endif; ?>
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
                            <?php if ( $val ) : ?><span class="bv-q-file-saved">✓ File uploaded</span><?php endif; ?>
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
                <?php endforeach; ?>
                <div class="bv-q-actions">
                    <button type="submit" class="bv-btn bv-btn-primary">Save Questionnaire</button>
                    <span id="bv-q-status"></span>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_documents_tab( $project, $documents ) {
        ob_start();
        ?>
        <div class="bv-documents-section">
            <h2>Documents</h2>
            <p>Upload documents required for your project. Accepted formats: PDF, DOC, DOCX, JPG, PNG.</p>

            <div class="bv-upload-form">
                <h4>Upload Document</h4>
                <div class="bv-form-group">
                    <label>Document Category</label>
                    <select id="bv-doc-category">
                        <option value="company-registration">Company Registration</option>
                        <option value="id">ID / Passport</option>
                        <option value="financial">Financial Statements</option>
                        <option value="logo">Logo / Branding</option>
                        <option value="branding">Branding Assets</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="bv-form-group">
                    <label>File</label>
                    <input type="file" id="bv-doc-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                </div>
                <button type="button" class="bv-btn bv-btn-primary" onclick="bv_upload_document(<?php echo $project->id; ?>)">
                    Upload Document
                </button>
                <div id="bv-doc-status"></div>
            </div>

            <?php if ( ! empty( $documents ) ) : ?>
            <div class="bv-documents-list">
                <h4>Uploaded Documents</h4>
                <table class="bv-table">
                    <thead>
                        <tr><th>Document</th><th>Category</th><th>Uploaded</th><th>Size</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $documents as $doc ) : ?>
                        <tr>
                            <td><?php echo esc_html( $doc->name ); ?></td>
                            <td><span class="bv-doc-cat"><?php echo esc_html( ucfirst( str_replace( '-', ' ', $doc->category ) ) ); ?></span></td>
                            <td><?php echo esc_html( date( 'd M Y H:i', strtotime( $doc->created_at ) ) ); ?></td>
                            <td><?php echo esc_html( size_format( $doc->filesize ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else : ?>
            <div class="bv-empty-state">No documents uploaded yet.</div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_reports_tab( $project, $reports ) {
        $delivered = array_filter( $reports, function( $r ) { return $r->status === 'delivered'; } );
        ob_start();
        ?>
        <div class="bv-reports-section">
            <h2>Reports</h2>

            <?php if ( ! empty( $delivered ) ) : ?>
            <div class="bv-reports-delivered">
                <h4>Available Reports</h4>
                <div class="bv-reports-grid">
                    <?php foreach ( $delivered as $rpt ) : ?>
                    <div class="bv-report-card">
                        <div class="bv-report-icon">📄</div>
                        <h5><?php echo esc_html( $rpt->title ); ?></h5>
                        <p class="bv-report-meta">Version <?php echo esc_html( $rpt->version ); ?> — Delivered <?php echo esc_html( date( 'd M Y', strtotime( $rpt->delivered_at ) ) ); ?></p>
                        <button type="button" class="bv-btn bv-btn-primary" onclick="bv_download_report(<?php echo $rpt->id; ?>)">
                            ⬇ Download Report
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( empty( $delivered ) ) : ?>
            <div class="bv-empty-state">
                <div class="bv-empty-icon">📄</div>
                <h3>No Reports Available Yet</h3>
                <p>Your reports will appear here once they are completed and delivered by your consultant.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_messages_tab( $project, $messages ) {
        ob_start();
        ?>
        <div class="bv-messages-section">
            <h2>Messages</h2>

            <div class="bv-messages-thread" id="bv-messages-thread">
                <?php if ( empty( $messages ) ) : ?>
                <div class="bv-empty-state">No messages yet. Start a conversation below.</div>
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

    // ============================
    // AJAX Handlers
    // ============================

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

        // Update project status if still awaiting questionnaire
        if ( $project->status === 'awaiting-questionnaire' ) {
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => 'awaiting-documents', 'progress_percent' => 25 ),
                array( 'id' => $project_id ),
                array( '%s', '%d' ), array( '%d' )
            );
        }

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'questionnaire', 'entity_id' => $project_id,
            'action' => 'submitted', 'description' => 'Client submitted questionnaire responses', 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

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
        $wpdb->insert( $wpdb->prefix . 'bv_project_agreements', array(
            'project_id'      => $project_id,
            'template_content' => $template,
            'full_name'       => $full_name,
            'ip_address'      => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ),
            'user_agent'      => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ),
            'agreed_at'       => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );

        // Update project status
        $wpdb->update( $wpdb->prefix . 'bv_projects',
            array( 'status' => 'awaiting-questionnaire', 'progress_percent' => 10 ),
            array( 'id' => $project_id ),
            array( '%s', '%d' ), array( '%d' )
        );

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'agreement', 'entity_id' => $wpdb->insert_id,
            'action' => 'signed', 'description' => "Agreement signed by {$full_name}", 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

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

    private function get_inline_css() {
        return '
        .bv-portal { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #333; max-width: 1400px; margin: 0 auto; background: #fff; }
        .bv-portal-login-message { padding: 40px; text-align: center; background: #fff; border-radius: 8px; margin: 20px 0; }
        .bv-portal-header { background: #002B5C; color: #fff; padding: 0; }
        .bv-portal-header-inner { display: flex; justify-content: space-between; align-items: center; padding: 15px 24px; flex-wrap: wrap; gap: 10px; }
        .bv-portal-brand { display: flex; align-items: center; gap: 12px; }
        .bv-portal-brand h1 { margin: 0; font-size: 22px; font-weight: 700; }
        .bv-portal-logo { font-size: 28px; color: #D4AF37; }
        .bv-portal-user-info { font-size: 14px; color: rgba(255,255,255,0.8); }
        .bv-portal-company { display: block; font-size: 12px; color: rgba(255,255,255,0.6); }
        .bv-portal-body { display: flex; min-height: 500px; border: 1px solid #e0e0e0; border-top: none; }
        .bv-portal-sidebar { width: 280px; background: #f8f9fa; border-right: 1px solid #e0e0e0; padding: 20px; flex-shrink: 0; }
        .bv-portal-sidebar h3 { margin: 0 0 15px; font-size: 16px; color: #002B5C; }
        .bv-portal-project-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; margin-bottom: 6px; background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; text-decoration: none; color: #333; font-size: 14px; transition: all 0.2s; }
        .bv-portal-project-item:hover, .bv-portal-project-item.active { border-color: #002B5C; background: #f0f4f8; }
        .bv-portal-project-item.active { box-shadow: 0 0 0 2px #002B5C; }
        .bv-portal-project-num { font-weight: 600; font-size: 13px; }
        .bv-portal-main { flex: 1; padding: 0; }
        .bv-portal-tabs { display: flex; border-bottom: 2px solid #e0e0e0; padding: 0 20px; overflow-x: auto; flex-wrap: wrap; }
        .bv-portal-tab { padding: 12px 20px; text-decoration: none; color: #666; font-size: 14px; font-weight: 500; border-bottom: 2px solid transparent; margin-bottom: -2px; white-space: nowrap; }
        .bv-portal-tab:hover { color: #002B5C; }
        .bv-portal-tab.active { color: #002B5C; border-bottom-color: #002B5C; }
        .bv-portal-content { padding: 24px; }
        .bv-portal-footer { background: #002B5C; color: rgba(255,255,255,0.6); text-align: center; padding: 15px; font-size: 13px; }

        .bv-status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .bv-status-awaiting-agreement { background: #FFF3CD; color: #856404; }
        .bv-status-awaiting-questionnaire { background: #FFF3CD; color: #856404; }
        .bv-status-awaiting-documents { background: #E8DAEF; color: #6C3483; }
        .bv-status-in-progress { background: #D6EAF8; color: #1A5276; }
        .bv-status-quality-check { background: #D1F2EB; color: #0E6655; }
        .bv-status-completed { background: #D5F5E3; color: #1E8449; }
        .bv-status-delivered { background: #27AE60; color: #fff; }
        .bv-status-archived { background: #E5E7EB; color: #6B7280; }

        .bv-overview-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 10px; }
        .bv-overview-header h2 { margin: 0; color: #002B5C; font-size: 24px; }
        .bv-subtitle { color: #666; font-size: 14px; margin: 4px 0 0; }
        .bv-progress-section { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; margin-bottom: 24px; }
        .bv-progress-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; font-weight: 600; }
        .bv-progress-percent { color: #002B5C; }
        .bv-progress-bar { height: 10px; background: #e0e0e0; border-radius: 5px; overflow: hidden; }
        .bv-progress-fill { height: 100%; background: linear-gradient(90deg, #2A9D8F, #002B5C); border-radius: 5px; transition: width 0.5s; }
        .bv-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .bv-info-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; }
        .bv-info-card h4 { margin: 0 0 12px; color: #002B5C; font-size: 16px; }
        .bv-info-card p { margin: 6px 0; font-size: 14px; }
        .bv-service-list { margin: 0; padding-left: 20px; }
        .bv-service-list li { margin: 4px 0; font-size: 14px; }
        .bv-notes-section { background: #FFF8E1; border: 1px solid #FFE082; border-radius: 8px; padding: 16px; }
        .bv-notes-section h4 { margin: 0 0 8px; color: #002B5C; }

        .bv-agreement-content { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin: 16px 0; max-height: 500px; overflow-y: auto; font-size: 14px; line-height: 1.6; }
        .bv-agreement-signed { text-align: center; padding: 24px; background: #D5F5E3; border: 1px solid #27AE60; border-radius: 8px; margin-bottom: 16px; }
        .bv-check-icon { font-size: 32px; color: #27AE60; }
        .bv-agreement-warning { padding: 12px 16px; background: #FFF3CD; border: 1px solid #FFC107; border-radius: 6px; margin-bottom: 16px; }
        .bv-agreement-sign-form { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-top: 16px; }
        .bv-form-group { margin-bottom: 16px; }
        .bv-form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #333; }
        .bv-form-group input, .bv-form-group select, .bv-form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #d0d5dd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }

        .bv-btn { display: inline-block; padding: 10px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .bv-btn-primary { background: #002B5C; color: #fff; }
        .bv-btn-primary:hover { background: #003d7a; }
        .bv-badge { display: inline-block; background: #DC3545; color: #fff; border-radius: 10px; padding: 1px 8px; font-size: 11px; font-weight: 600; }
        .bv-count { font-size: 12px; color: #666; }

        .bv-q-section { margin-bottom: 32px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
        .bv-q-section h3 { background: #002B5C; color: #fff; padding: 12px 20px; margin: 0; font-size: 16px; }
        .bv-q-desc { color: #666; font-size: 13px; margin: 0; padding: 8px 20px; }
        .bv-q-field { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; }
        .bv-q-field:last-child { border-bottom: none; }
        .bv-q-field label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; }
        .bv-q-field input[type="text"], .bv-q-field input[type="email"], .bv-q-field input[type="tel"], .bv-q-field input[type="number"], .bv-q-field input[type="date"], .bv-q-field input[type="file"], .bv-q-field select, .bv-q-field textarea { width: 100%; padding: 10px 14px; border: 1px solid #d0d5dd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .bv-q-field textarea { min-height: 80px; resize: vertical; }
        .bv-required { color: #DC3545; }
        .bv-q-help { display: block; font-size: 12px; color: #666; margin-bottom: 6px; }
        .bv-q-radio-group, .bv-q-checkbox-group { display: flex; flex-direction: column; gap: 8px; }
        .bv-q-radio label, .bv-q-checkbox label { display: flex; align-items: center; gap: 8px; font-weight: 400; cursor: pointer; }
        .bv-q-actions { margin-top: 24px; }

        .bv-upload-form { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
        .bv-upload-form h4 { margin: 0 0 16px; color: #002B5C; }
        .bv-documents-list { margin-top: 24px; }
        .bv-documents-list h4 { margin: 0 0 12px; color: #002B5C; }
        .bv-doc-cat { display: inline-block; padding: 2px 8px; background: #E8DAEF; color: #6C3483; border-radius: 4px; font-size: 12px; }
        .bv-table { width: 100%; border-collapse: collapse; }
        .bv-table th { background: #002B5C; color: #fff; padding: 10px 14px; text-align: left; font-size: 13px; }
        .bv-table td { padding: 10px 14px; border-bottom: 1px solid #e0e0e0; font-size: 14px; }
        .bv-table tr:hover td { background: #f8f9fa; }

        .bv-reports-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 16px; }
        .bv-report-card { background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; text-align: center; }
        .bv-report-icon { font-size: 40px; margin-bottom: 12px; }
        .bv-report-card h5 { margin: 0 0 8px; color: #002B5C; font-size: 16px; }
        .bv-report-meta { font-size: 13px; color: #666; margin-bottom: 16px; }

        .bv-messages-thread { border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; max-height: 400px; overflow-y: auto; margin-bottom: 16px; background: #fafafa; }
        .bv-message { margin-bottom: 16px; padding: 12px 16px; border-radius: 8px; }
        .bv-message-admin { background: #E3F2FD; border: 1px solid #BBDEFB; }
        .bv-message-client { background: #fff; border: 1px solid #e0e0e0; margin-left: 40px; }
        .bv-message-header { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .bv-message-header strong { font-size: 14px; color: #002B5C; }
        .bv-message-time { font-size: 12px; color: #999; }
        .bv-message-body { font-size: 14px; line-height: 1.5; }
        .bv-message-form textarea { width: 100%; padding: 12px; border: 1px solid #d0d5dd; border-radius: 6px; font-size: 14px; box-sizing: border-box; resize: vertical; }

        .bv-empty-state { text-align: center; padding: 60px 20px; color: #666; }
        .bv-empty-icon { font-size: 48px; margin-bottom: 16px; }
        .bv-empty-state h3 { margin: 0 0 8px; color: #333; }
        .bv-empty-state p { margin: 4px 0; font-size: 14px; }
        .bv-portal-empty .bv-portal-body { display: block; }
        .bv-portal-empty-state { max-width: 500px; margin: 60px auto; text-align: center; }

        @media (max-width: 768px) {
            .bv-portal-body { flex-direction: column; }
            .bv-portal-sidebar { width: 100%; }
            .bv-info-grid { grid-template-columns: 1fr; }
            .bv-message-client { margin-left: 0; }
            .bv-reports-grid { grid-template-columns: 1fr; }
        }
        ';
    }
}
