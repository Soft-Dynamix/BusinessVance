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
                        <button class="bv-portal-mobile-toggle" onclick="document.querySelector(\'.bv-portal-sidebar\').classList.toggle(\'bv-sidebar-open\')">☰</button>
                        <span class="bv-portal-logo">✦</span>
                        <h1><?php echo esc_html( BV_Settings::get( 'company_name' ) ?: 'BusinessVance' ); ?></h1>
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
                        // Build tabs based on project status and admin settings
                        $portal_settings = BV_Settings::get_settings();
                        $all_tabs = array( 'overview' => 'Overview', 'agreement' => 'Agreement', 'questionnaire' => 'Questionnaire', 'documents' => 'Documents', 'reports' => 'Reports', 'messages' => 'Messages' );
                        
                        // Apply section visibility from settings
                        if ( $portal_settings['portal_show_overview'] !== 'yes' ) {
                            unset( $all_tabs['overview'] );
                        }
                        if ( $portal_settings['portal_show_questionnaire'] !== 'yes' ) {
                            unset( $all_tabs['questionnaire'] );
                        }
                        if ( $portal_settings['portal_show_messages'] !== 'yes' ) {
                            unset( $all_tabs['messages'] );
                        }
                        if ( $portal_settings['portal_show_reports'] !== 'yes' ) {
                            unset( $all_tabs['reports'] );
                        }
                        if ( $portal_settings['portal_show_documents'] !== 'yes' ) {
                            unset( $all_tabs['documents'] );
                        }
                        
                        // Check if any service requires documents (override: show documents tab if required)
                        $documents_required = false;
                        foreach ( $services as $svc ) {
                            $req_docs = json_decode( $svc->required_documents, true );
                            if ( is_array( $req_docs ) && ! empty( $req_docs ) ) {
                                $documents_required = true;
                                break;
                            }
                        }
                        // If documents are required by service, always show the tab
                        if ( $documents_required ) {
                            $all_tabs['documents'] = 'Documents';
                        }
                        
                        // Ensure at least overview exists as fallback
                        if ( empty( $all_tabs ) ) {
                            $all_tabs['overview'] = 'Overview';
                        }
                        
                        foreach ( $all_tabs as $tab_id => $tab_label ) :
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
                            case 'documents':
                                if ( isset( $all_tabs['documents'] ) ) {
                                    echo $this->render_documents_tab( $active_project, $documents, $services );
                                } else {
                                    echo $this->render_overview_tab( $active_project, $services );
                                }
                                break;
                            case 'reports': echo $this->render_reports_tab( $active_project, $reports ); break;
                            case 'messages': echo $this->render_messages_tab( $active_project, $messages ); break;
                            default: echo $this->render_overview_tab( $active_project, $services );
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="bv-portal-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo esc_html( BV_Settings::get( 'company_name' ) ?: 'BusinessVance Consulting' ); ?>. All rights reserved.</p>
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
                        <h1><?php echo esc_html( BV_Settings::get( 'company_name' ) ?: 'BusinessVance' ); ?></h1>
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
        global $wpdb;
        
        // Determine agreement template to use
        $template = '';
        
        // Check if any service has a custom agreement template
        $project_services = $wpdb->get_results( $wpdb->prepare(
            "SELECT ps.service_id, s.name, s.agreement_template_id, s.nda_only
             FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id
             WHERE ps.project_id = %d",
            $project->id
        ) );
        
        $custom_templates = array();
        $has_nda_only = false;
        foreach ( $project_services as $ps ) {
            if ( $ps->agreement_template_id > 0 ) {
                $tpl = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bv_agreement_templates WHERE id = %d",
                    $ps->agreement_template_id
                ) );
                if ( $tpl ) {
                    $custom_templates[] = array( 'service' => $ps->name, 'template' => $tpl );
                }
            }
            if ( $ps->nda_only ) {
                $has_nda_only = true;
            }
        }
        
        // If no custom templates, use the global agreement template
        if ( empty( $custom_templates ) ) {
            $template = get_option( 'bv_agreement_template', '' );
            // Fallback to default NDA if no global template
            if ( empty( $template ) ) {
                $default_nda = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}bv_agreement_templates WHERE is_default = 1 ORDER BY id ASC LIMIT 1" );
                if ( $default_nda ) {
                    $template = $default_nda->content;
                }
            }
        }
        
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
                    <p>⚠️ Please read and sign the agreement(s) below to proceed with your project.</p>
                </div>
                
                <?php if ( ! empty( $custom_templates ) ) : ?>
                    <?php foreach ( $custom_templates as $ct ) : ?>
                    <div class="bv-agreement-content" style="margin-bottom: 20px;">
                        <h3 style="margin-top:0;"><?php echo esc_html( $ct['service'] ); ?> — <?php echo esc_html( $ct['template']->name ); ?></h3>
                        <?php echo wp_kses_post( $ct['template']->content ); ?>
                    </div>
                    <?php endforeach; ?>
                <?php elseif ( ! empty( $template ) ) : ?>
                    <div class="bv-agreement-content">
                        <?php echo wp_kses_post( $template ); ?>
                    </div>
                <?php else : ?>
                    <div class="bv-empty-state">
                        <p>No agreement template has been configured for this project yet.</p>
                    </div>
                <?php endif; ?>
                
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

        // Get all services linked to this project
        $project_services = $wpdb->get_results( $wpdb->prepare(
            "SELECT service_id FROM {$wpdb->prefix}bv_project_services WHERE project_id = %d",
            $project_id
        ) );
        $service_ids = array();
        foreach ( $project_services as $ps ) {
            $service_ids[] = absint( $ps->service_id );
        }

        // If no services linked, return empty
        if ( empty( $service_ids ) ) {
            return array();
        }

        // Get unique questionnaire template IDs from the linked services
        $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
        $template_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT questionnaire_template_id FROM {$wpdb->prefix}bv_services
             WHERE id IN ($placeholders) AND questionnaire_template_id > 0",
            ...$service_ids
        ) );

        if ( empty( $template_ids ) ) {
            return array();
        }

        // Get all published sections from these templates
        $tpl_placeholders = implode( ',', array_fill( 0, count( $template_ids ), '%d' ) );
        $sections = $wpdb->get_results( $wpdb->prepare(
            "SELECT qs.*, qt.name as template_name
             FROM {$wpdb->prefix}bv_questionnaire_sections qs
             JOIN {$wpdb->prefix}bv_questionnaire_templates qt ON qs.template_id = qt.id
             WHERE qs.template_id IN ($tpl_placeholders) AND qt.status = 'published'
             ORDER BY qs.template_id, qs.display_order ASC",
            ...$template_ids
        ) );

        // Collect unique question IDs across all sections (for deduplication)
        $seen_question_keys = array(); // keyed by "label|type" to deduplicate
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

            // Deduplicate questions by label+type — skip if already seen
            $unique_questions = array();
            foreach ( $questions as $q ) {
                $key = $q->label . '|' . $q->type;
                if ( isset( $seen_question_keys[ $key ] ) ) {
                    continue; // Skip duplicate question
                }
                $seen_question_keys[ $key ] = true;
                $unique_questions[] = $q;
            }

            // Only include section if it has unique questions
            if ( ! empty( $unique_questions ) ) {
                $section->questions = $unique_questions;
                $all_sections[] = $section;
            }
        }

        return $all_sections;
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

    private function render_documents_tab( $project, $documents, $services = array() ) {
        ob_start();
        ?>
        <div class="bv-documents-section">
            <h2>Documents</h2>
            <p>Upload documents required for your project. Accepted formats: PDF, DOC, DOCX, JPG, PNG.</p>
            <?php if ( ! empty( $services ) ) : ?>
            <div style="background:#FFF3CD;border:1px solid #FFC107;border-radius:8px;padding:16px;margin-bottom:20px;">
                <h4 style="margin:0 0 8px;color:#856404;">📋 Required Documents</h4>
                <ul style="margin:0;padding-left:20px;color:#856404;">
                <?php foreach ( $services as $svc ) : ?>
                    <?php $req_docs = json_decode( $svc->required_documents, true ); ?>
                    <?php if ( is_array( $req_docs ) && ! empty( $req_docs ) ) : ?>
                    <li><strong><?php echo esc_html( $svc->name ); ?>:</strong> <?php echo esc_html( implode( ', ', $req_docs ) ); ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
                </ul>
                <?php if ( empty( array_filter( array_map( function($s) { return json_decode( $s->required_documents, true ); }, $services ) ) ) ) : ?>
                <p style="margin:4px 0 0;">No specific documents are required. Upload any supporting documents you'd like to share.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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

        $this->notify_consultant( $project_id, 'Document Uploaded', "Client uploaded document: " . sanitize_text_field( $_POST['name'] ?? $file['name'] ) );
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

        $this->notify_consultant( $project_id, 'Questionnaire Submitted', "Client submitted questionnaire responses for project {$project->project_number}." );
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

        // Build template content for the signed record
        global $wpdb;
        $project_services = $wpdb->get_results( $wpdb->prepare(
            "SELECT ps.service_id, s.agreement_template_id
             FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id
             WHERE ps.project_id = %d",
            $project_id
        ) );
        $template_parts = array();
        foreach ( $project_services as $ps ) {
            if ( $ps->agreement_template_id > 0 ) {
                $tpl = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bv_agreement_templates WHERE id = %d",
                    $ps->agreement_template_id
                ) );
                if ( $tpl ) {
                    $svc = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}bv_services WHERE id = %d", $ps->service_id ) );
                    $template_parts[] = '<h3>' . esc_html( $svc ) . ' — ' . esc_html( $tpl->name ) . '</h3>' . $tpl->content;
                }
            }
        }
        if ( empty( $template_parts ) ) {
            $template = get_option( 'bv_agreement_template', '' );
            if ( empty( $template ) ) {
                $default_nda = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}bv_agreement_templates WHERE is_default = 1 ORDER BY id ASC LIMIT 1" );
                if ( $default_nda ) {
                    $template = $default_nda->content;
                }
            }
        } else {
            $template = implode( '<hr style="margin:20px 0;">', $template_parts );
        }

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

        $this->notify_consultant( $project_id, 'Agreement Signed', "Client {$full_name} signed the service agreement for project {$project->project_number}." );
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

    private function notify_consultant( $project_id, $action, $description ) {
        $settings = BV_Settings::get_settings();
        $consultant_email = $settings['consultant_email'] ?? get_option( 'admin_email' );
        if ( empty( $consultant_email ) ) return;
        
        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id ) );
        if ( ! $project ) return;
        
        $company_name = $settings['company_name'] ?? 'BusinessVance';
        $portal_url = $settings['portal_url'] ?? site_url();
        $dashboard_url = admin_url( 'admin.php?page=bv-consultant-dashboard&project_id=' . $project_id );
        
        $subject = "Client Action on {$project->project_number} - {$action}";
        $body = "Dear Consultant,\n\n";
        $body .= "A client has completed an action on project {$project->project_number}:\n\n";
        $body .= "Action: {$action}\n";
        $body .= "Details: {$description}\n";
        $body .= "Client: {$project->client_name} ({$project->client_email})\n\n";
        $body .= "Please review and take necessary action in the Consultant Dashboard:\n";
        $body .= "{$dashboard_url}\n\n";
        $body .= "Best regards,\n{$company_name} System";
        
        $headers = array( 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $company_name . ' <' . $consultant_email . '>' );
        wp_mail( $consultant_email, $subject, $body, $headers );
    }

    private function get_inline_css() {
        $settings    = BV_Settings::get_settings();
        $primary     = esc_attr( $settings['primary_color'] );
        $secondary   = esc_attr( $settings['secondary_color'] );
        $accent      = esc_attr( $settings['accent_color'] );
        $portal_hdr  = esc_attr( $settings['portal_header_color'] ?? $primary );
        $portal_acnt = esc_attr( $settings['portal_accent_color'] ?? $secondary );
        $portal_btn  = esc_attr( $settings['portal_button_color'] ?? $accent );
        $tab_style   = $settings['portal_tab_style'] ?? 'underline';

        $tab_active_css = 'border-bottom-color:' . $portal_hdr . ';color:' . $portal_hdr . ';font-weight:600;';
        if ( $tab_style === 'pill' ) {
            $tab_active_css = 'background:' . $portal_hdr . ';color:#fff;border-radius:8px 8px 0 0;border:none;';
        }

        return '
    /* ============================================
       BusinessVance Client Portal — Modern Theme
       ============================================ */
    .bv-portal { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #1a1a2e; max-width: 1440px; margin: 0 auto; background: #ffffff; border-radius: 0; overflow-x: hidden; }
    .bv-portal-login-message { padding: 60px 40px; text-align: center; background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%); border-radius: 12px; margin: 40px auto; max-width: 500px; border: 1px solid #e0e0e0; }
    .bv-portal-login-message a { color: ' . $portal_hdr . '; font-weight: 600; }

    /* Header */
    .bv-portal-header { background: linear-gradient(135deg, ' . $portal_hdr . ' 0%, ' . $this->adjust_color($portal_hdr, 25) . ' 50%, ' . $portal_hdr . ' 100%); color: #fff; padding: 0; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 12px rgba(0,0,0,0.15); }
    .bv-portal-header-inner { display: flex; justify-content: space-between; align-items: center; padding: 14px 28px; flex-wrap: wrap; gap: 12px; }
    .bv-portal-brand { display: flex; align-items: center; gap: 12px; }
    .bv-portal-brand h1 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
    .bv-portal-logo { font-size: 28px; color: ' . $portal_btn . '; filter: drop-shadow(0 0 4px rgba(212,175,55,0.4)); }
    .bv-portal-user-info { font-size: 14px; color: rgba(255,255,255,0.85); text-align: right; }
    .bv-portal-user-info strong { color: #fff; }
    .bv-portal-company { display: block; font-size: 12px; color: rgba(255,255,255,0.55); margin-top: 2px; }

    /* Mobile hamburger */
    .bv-portal-mobile-toggle { display: none; background: none; border: 2px solid rgba(255,255,255,0.3); border-radius: 6px; padding: 6px 10px; color: #fff; font-size: 18px; cursor: pointer; line-height: 1; }
    .bv-portal-mobile-toggle:hover { border-color: ' . $portal_btn . '; }

    /* Body */
    .bv-portal-body { display: flex; min-height: calc(100vh - 120px); }

    /* Sidebar */
    .bv-portal-sidebar { width: 300px; background: linear-gradient(180deg, #f8f9fb 0%, #f0f2f5 100%); border-right: 1px solid #e5e7eb; padding: 24px 20px; flex-shrink: 0; overflow-y: auto; }
    .bv-portal-sidebar h3 { margin: 0 0 16px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #6b7280; padding: 0 4px; }
    .bv-portal-project-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; margin-bottom: 8px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; text-decoration: none; color: #1a1a2e; font-size: 14px; transition: all 0.25s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .bv-portal-project-item:hover { border-color: ' . $portal_hdr . '; background: #f0f4f8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,43,92,0.1); }
    .bv-portal-project-item.active { border-color: ' . $portal_hdr . '; background: linear-gradient(135deg, ' . $portal_hdr . ' 0%, ' . $this->adjust_color($portal_hdr, 25) . ' 100%); color: #fff; box-shadow: 0 4px 16px rgba(0,43,92,0.25); }
    .bv-portal-project-item.active .bv-portal-project-num { color: #fff; }
    .bv-portal-project-item.active .bv-portal-project-status-badge { background: rgba(255,255,255,0.2); color: #fff; }
    .bv-portal-project-num { font-weight: 600; font-size: 13px; color: ' . $portal_hdr . '; }

    /* Main Content */
    .bv-portal-main { flex: 1; padding: 0; display: flex; flex-direction: column; }

    /* Tabs */
    .bv-portal-tabs { display: flex; border-bottom: 2px solid #e5e7eb; padding: 0 28px; overflow-x: auto; background: #fff; flex-shrink: 0; -webkit-overflow-scrolling: touch; }
    .bv-portal-tab { padding: 14px 22px; text-decoration: none; color: #6b7280; font-size: 14px; font-weight: 500; border-bottom: 3px solid transparent; margin-bottom: -2px; white-space: nowrap; transition: all 0.2s ease; position: relative; }
    .bv-portal-tab:hover { color: ' . $portal_hdr . '; background: rgba(0,43,92,0.03); }
    .bv-portal-tab.active { ' . $tab_active_css . ' }

    /* Content */
    .bv-portal-content { padding: 28px; flex: 1; }

    /* Status Badges */
    .bv-portal-project-status-badge, .bv-status-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .bv-status-awaiting-agreement { background: #FEF3C7; color: #92400E; }
    .bv-status-awaiting-questionnaire { background: #FEF3C7; color: #92400E; }
    .bv-status-awaiting-documents { background: #F3E8FF; color: #6B21A8; }
    .bv-status-in-progress { background: #DBEAFE; color: #1E40AF; }
    .bv-status-quality-check { background: #D1FAE5; color: #065F46; }
    .bv-status-completed { background: #D1FAE5; color: #065F46; }
    .bv-status-delivered { background: #059669; color: #fff; box-shadow: 0 2px 8px rgba(5,150,105,0.3); }
    .bv-status-archived { background: #F3F4F6; color: #6B7280; }

    /* Overview */
    .bv-overview-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; }
    .bv-overview-header h2 { margin: 0; color: ' . $portal_hdr . '; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
    .bv-subtitle { color: #6b7280; font-size: 14px; margin: 4px 0 0; }

    /* Progress */
    .bv-progress-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px 24px; margin-bottom: 28px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    .bv-progress-header { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; font-weight: 600; color: #374151; }
    .bv-progress-percent { color: ' . $portal_hdr . '; font-size: 16px; font-weight: 700; }
    .bv-progress-bar { height: 10px; background: #e5e7eb; border-radius: 10px; overflow: hidden; }
    .bv-progress-fill { height: 100%; background: linear-gradient(90deg, ' . $portal_acnt . ', ' . $portal_hdr . '); border-radius: 10px; transition: width 0.6s ease; position: relative; }
    .bv-progress-fill::after { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); animation: shimmer 2s infinite; }
    @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }

    /* Steps Indicator */
    .bv-steps { display: flex; justify-content: space-between; margin-top: 20px; padding: 0 8px; }
    .bv-step { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; }
    .bv-step::before { content: ""; position: absolute; top: 16px; left: -50%; right: 50%; height: 2px; background: #e5e7eb; z-index: 0; }
    .bv-step:first-child::before { display: none; }
    .bv-step-dot { width: 32px; height: 32px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #9CA3AF; position: relative; z-index: 1; transition: all 0.3s ease; }
    .bv-step-dot.done { background: #059669; color: #fff; box-shadow: 0 2px 8px rgba(5,150,105,0.3); }
    .bv-step-dot.active { background: ' . $portal_hdr . '; color: #fff; box-shadow: 0 2px 8px rgba(0,43,92,0.3); }
    .bv-step-label { font-size: 11px; color: #9CA3AF; margin-top: 8px; font-weight: 500; text-align: center; }
    .bv-step-label.done { color: #059669; font-weight: 600; }
    .bv-step-label.active { color: ' . $portal_hdr . '; font-weight: 600; }

    /* Info Grid */
    .bv-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
    .bv-info-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); transition: box-shadow 0.2s ease; }
    .bv-info-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .bv-info-card h4 { margin: 0 0 14px; color: ' . $portal_hdr . '; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .bv-info-card p { margin: 8px 0; font-size: 14px; color: #4b5563; line-height: 1.5; }
    .bv-info-card p strong { color: #1a1a2e; }
    .bv-service-list { margin: 0; padding-left: 20px; }
    .bv-service-list li { margin: 6px 0; font-size: 14px; color: #4b5563; position: relative; }
    .bv-service-list li::before { content: "\2022"; color: ' . $portal_acnt . '; font-weight: 700; margin-right: 8px; }

    /* Notes */
    .bv-notes-section { background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border: 1px solid #FDE68A; border-radius: 12px; padding: 20px 24px; }
    .bv-notes-section h4 { margin: 0 0 10px; color: #92400E; font-size: 15px; font-weight: 600; }
    .bv-notes-content { font-size: 14px; color: #78350F; line-height: 1.7; }

    /* Agreement */
    .bv-agreement-content { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; margin: 20px 0; max-height: 500px; overflow-y: auto; font-size: 14px; line-height: 1.7; color: #374151; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    .bv-agreement-signed { text-align: center; padding: 32px; background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%); border: 1px solid #6EE7B7; border-radius: 12px; margin-bottom: 20px; }
    .bv-check-icon { font-size: 40px; color: #059669; display: inline-block; animation: checkPop 0.5s ease; }
    @keyframes checkPop { 0% { transform: scale(0); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
    .bv-agreement-signed h3 { margin: 12px 0 8px; color: #065F46; font-size: 20px; }
    .bv-agreement-signed p { margin: 0; color: #047857; font-size: 15px; }
    .bv-agreement-warning { padding: 14px 20px; background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border: 1px solid #FCD34D; border-radius: 10px; margin-bottom: 20px; color: #92400E; font-size: 14px; }
    .bv-agreement-sign-form { background: #f8f9fb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; margin-top: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    .bv-agreement-sign-form h3 { margin: 0 0 8px; color: ' . $portal_hdr . '; }
    .bv-agreement-sign-form > p { color: #6b7280; font-size: 14px; margin: 0 0 20px; }

    /* Forms */
    .bv-form-group { margin-bottom: 20px; }
    .bv-form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #374151; }
    .bv-form-group input, .bv-form-group select, .bv-form-group textarea { width: 100%; padding: 12px 16px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box; transition: all 0.2s ease; background: #fff; color: #1a1a2e; }
    .bv-form-group input:focus, .bv-form-group select:focus, .bv-form-group textarea:focus { outline: none; border-color: ' . $portal_hdr . '; box-shadow: 0 0 0 3px rgba(0,43,92,0.1); }
    .bv-form-group .description { font-size: 12px; color: #9CA3AF; margin-top: 6px; }

    /* Buttons */
    .bv-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; }
    .bv-btn-primary { background: ' . $portal_hdr . '; color: #fff; }
    .bv-btn-primary:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .bv-btn-accent { background: ' . $portal_btn . '; color: ' . $portal_hdr . '; }
    .bv-btn-accent:hover { opacity: 0.9; transform: translateY(-1px); }
    .bv-btn-success { background: #059669; color: #fff; }
    .bv-btn-success:hover { background: #047857; }
    .bv-btn-outline { background: transparent; color: ' . $portal_hdr . '; border: 2px solid ' . $portal_hdr . '; }
    .bv-btn-outline:hover { background: ' . $portal_hdr . '; color: #fff; }
    .bv-btn-danger { background: #DC2626; color: #fff; }
    .bv-btn-danger:hover { background: #B91C1C; }
    .bv-btn-sm { padding: 6px 14px; font-size: 12px; }

    /* Upload area */
    .bv-upload-area { border: 2px dashed #d1d5db; border-radius: 12px; padding: 32px; text-align: center; background: #f9fafb; transition: all 0.2s ease; cursor: pointer; }
    .bv-upload-area:hover, .bv-upload-area.dragover { border-color: ' . $portal_hdr . '; background: rgba(0,43,92,0.03); }
    .bv-upload-icon { font-size: 40px; margin-bottom: 12px; }

    /* Messages */
    .bv-messages-list { display: flex; flex-direction: column; gap: 12px; }
    .bv-message-bubble { padding: 14px 18px; border-radius: 12px; max-width: 80%; font-size: 14px; line-height: 1.6; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    .bv-message-sent { background: linear-gradient(135deg, ' . $portal_hdr . ', ' . $this->adjust_color($portal_hdr, 20) . '); color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
    .bv-message-received { background: #f3f4f6; color: #1a1a2e; align-self: flex-start; border-bottom-left-radius: 4px; }
    .bv-message-meta { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 6px; }
    .bv-message-received .bv-message-meta { color: #9CA3AF; }

    /* Reports */
    .bv-report-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; transition: box-shadow 0.2s; }
    .bv-report-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

    /* Timeline */
    .bv-timeline { position: relative; padding-left: 28px; }
    .bv-timeline::before { content: ""; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e5e7eb; }
    .bv-timeline-item { position: relative; margin-bottom: 20px; }
    .bv-timeline-dot { position: absolute; left: -24px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: ' . $portal_acnt . '; border: 2px solid #fff; box-shadow: 0 0 0 2px #e5e7eb; }

    /* Empty State */
    .bv-portal-empty-state { text-align: center; padding: 60px 40px; }
    .bv-portal-empty-icon { font-size: 56px; margin-bottom: 16px; }
    .bv-portal-empty-state h2 { color: ' . $portal_hdr . '; font-size: 24px; margin-bottom: 8px; }
    .bv-portal-empty-state p { color: #6b7280; font-size: 15px; line-height: 1.6; max-width: 460px; margin: 0 auto 16px; }
    .bv-portal-empty-state a { color: ' . $portal_hdr . '; font-weight: 600; }

    /* Responsive */
    @media (max-width: 768px) {
        .bv-portal-body { flex-direction: column; }
        .bv-portal-sidebar { width: 100%; border-right: none; border-bottom: 1px solid #e5e7eb; padding: 16px; }
        .bv-portal-sidebar h3 { display: none; }
        .bv-portal-mobile-toggle { display: inline-block; }
        .bv-portal-main { width: 100%; }
        .bv-portal-tabs { padding: 0 16px; }
        .bv-portal-tab { padding: 12px 16px; font-size: 13px; }
        .bv-portal-content { padding: 20px 16px; }
        .bv-info-grid { grid-template-columns: 1fr; }
        .bv-overview-header { flex-direction: column; }
        .bv-portal-header-inner { padding: 12px 16px; }
    }
    ';
    }

    /**
     * Adjust a hex color brightness
     */
    private function adjust_color( $hex, $amount ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = min( 255, max( 0, hexdec( substr( $hex, 0, 2 ) ) + $amount ) );
        $g = min( 255, max( 0, hexdec( substr( $hex, 2, 2 ) ) + $amount ) );
        $b = min( 255, max( 0, hexdec( substr( $hex, 4, 2 ) ) + $amount ) );
        return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
    }
}
