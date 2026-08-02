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

        // Ensure bv_project_documents has document_requirement_id column
        add_action( 'init', array( $this, 'maybe_add_document_requirement_column' ), 99 );
    }

    /**
     * Add document_requirement_id column to bv_project_documents if missing.
     * This supports the new document requirements system.
     *
     * @since 2.5.0
     * @return void
     */
    public function maybe_add_document_requirement_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'bv_project_documents';
        $col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'document_requirement_id'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN document_requirement_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER service_id" );
        }
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

    /**
     * Determine which tabs to show based on service assignments.
     *
     * @since 2.5.0
     * @param int   $project_id The project ID.
     * @param array $services   Services linked to the project.
     * @param array $portal_settings Portal settings.
     * @return array Associative array of tab_id => label for visible tabs.
     */
    private function get_visible_tabs( $project_id, $services, $portal_settings ) {
        global $wpdb;
        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }

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

        // --- Agreement tab skip logic ---
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $has_agreements = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_agreements WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
            if ( ! $has_agreements ) {
                unset( $all_tabs['agreement'] );
            }
        } else {
            unset( $all_tabs['agreement'] );
        }

        // --- Questionnaire tab skip logic ---
        if ( ! empty( $service_ids ) ) {
            // Check junction table first
            $has_questionnaires = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_questionnaires WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
            // Fallback: check legacy column
            if ( ! $has_questionnaires ) {
                $has_questionnaires = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}bv_services WHERE id IN ($placeholders) AND questionnaire_template_id > 0",
                    ...$service_ids
                ) );
            }
            if ( ! $has_questionnaires ) {
                unset( $all_tabs['questionnaire'] );
            }
        } else {
            unset( $all_tabs['questionnaire'] );
        }

        // --- Documents tab skip logic ---
        if ( ! empty( $service_ids ) ) {
            $has_doc_requirements = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_documents WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
            // If no document requirements from junction, tab stays only if setting forces it
            if ( ! $has_doc_requirements ) {
                // Don't re-add if settings already removed it
                if ( ! isset( $all_tabs['documents'] ) ) {
                    // Already removed by settings, keep removed
                }
                // If still present (setting is 'yes'), remove it since no requirements
                else {
                    unset( $all_tabs['documents'] );
                }
            } else {
                // Document requirements exist — always show the tab
                $all_tabs['documents'] = 'Documents';
            }
        } else {
            unset( $all_tabs['documents'] );
        }

        // Ensure at least overview exists as fallback
        if ( empty( $all_tabs ) ) {
            $all_tabs['overview'] = 'Overview';
        }

        return $all_tabs;
    }

    /**
     * Check if ALL project services have nda_only = 1.
     *
     * @since 2.5.0
     * @param array $services Services linked to the project.
     * @return bool
     */
    private function all_services_nda_only( $services ) {
        if ( empty( $services ) ) return false;
        foreach ( $services as $svc ) {
            if ( empty( $svc->nda_only ) ) return false;
        }
        return true;
    }

    /**
     * Determine the next project status after agreement is signed.
     *
     * @since 2.5.0
     * @param int   $project_id The project ID.
     * @param array $services   Services linked to the project.
     * @return string Next status.
     */
    private function get_next_status_after_agreement( $project_id, $services ) {
        global $wpdb;
        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }

        $has_questionnaires = false;
        $has_documents      = false;

        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

            // Check questionnaires
            $has_questionnaires = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_questionnaires WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
            if ( ! $has_questionnaires ) {
                $has_questionnaires = (bool) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}bv_services WHERE id IN ($placeholders) AND questionnaire_template_id > 0",
                    ...$service_ids
                ) );
            }

            // Check documents
            $has_documents = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_documents WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
        }

        if ( $has_questionnaires ) {
            return 'awaiting-questionnaire';
        } elseif ( $has_documents ) {
            return 'awaiting-documents';
        } else {
            return 'in-progress';
        }
    }

    /**
     * Determine the next project status after questionnaire is completed.
     *
     * @since 2.5.0
     * @param int   $project_id The project ID.
     * @param array $services   Services linked to the project.
     * @return string Next status.
     */
    private function get_next_status_after_questionnaire( $project_id, $services ) {
        global $wpdb;
        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }

        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $has_documents = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_documents WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
            if ( $has_documents ) {
                return 'awaiting-documents';
            }
        }

        return 'in-progress';
    }

    /**
     * Check if all required document requirements have been fulfilled.
     *
     * @since 2.5.0
     * @param int   $project_id The project ID.
     * @param array $services   Services linked to the project.
     * @return bool
     */
    private function all_required_docs_uploaded( $project_id, $services ) {
        global $wpdb;
        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }
        if ( empty( $service_ids ) ) return true;

        $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

        // Get all required document requirement IDs for these services
        $required_dr_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT sd.document_requirement_id
             FROM {$wpdb->prefix}bv_service_documents sd
             JOIN {$wpdb->prefix}bv_document_requirements dr ON dr.id = sd.document_requirement_id
             WHERE sd.service_id IN ($placeholders) AND dr.is_required = 1",
            ...$service_ids
        ) );

        if ( empty( $required_dr_ids ) ) return true;

        // Check that each required requirement has at least one uploaded document
        foreach ( $required_dr_ids as $dr_id ) {
            $uploaded = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_project_documents
                 WHERE project_id = %d AND document_requirement_id = %d",
                $project_id, $dr_id
            ) );
            if ( ! $uploaded ) return false;
        }

        return true;
    }

    public function render_portal( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="bv-portal-login-message"><p>' . sprintf(
                /* translators: %s: login URL */
                esc_html__( 'Please <a href="%s">log in</a> to access your client portal.', 'businessvance-services-manager' ),
                esc_url( wp_login_url( get_permalink() ) )
            ) . '</p></div>';
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
            array( '%d' ), array( '%d', '%s' )
        );

        // Get questionnaire data
        $questionnaire = $this->get_questionnaire_data( $project_id );

        // Determine visible tabs
        $portal_settings = BV_Settings::get_settings();
        $all_tabs = $this->get_visible_tabs( $project_id, $services, $portal_settings );

        // If requested tab is not visible, redirect to first available tab
        if ( ! isset( $all_tabs[ $tab ] ) ) {
            $tab_keys = array_keys( $all_tabs );
            $tab = $tab_keys[0];
        }

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
                    <h3><?php echo esc_html__( 'My Projects', 'businessvance-services-manager' ); ?></h3>
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
                        <a href="?project_id=<?php echo $project_id; ?>&tab=<?php echo $tab_id; ?>" class="bv-portal-tab <?php echo $active; ?>"><?php echo esc_html( $tab_label ) . $count; ?></a>
                        <?php endforeach; ?>
                    </div>

                    <div class="bv-portal-content">
                        <?php
                        switch ( $tab ) {
                            case 'overview': echo $this->render_overview_tab( $active_project, $services ); break;
                            case 'agreement': echo $this->render_agreement_tab( $active_project, $agreement, $services ); break;
                            case 'questionnaire': echo $this->render_questionnaire_tab( $active_project, $questionnaire ); break;
                            case 'documents':
                                echo $this->render_documents_tab( $active_project, $documents, $services );
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
                    <h2><?php echo esc_html__( 'No Active Projects', 'businessvance-services-manager' ); ?></h2>
                    <p><?php echo esc_html__( 'Your projects will appear here after you purchase a service from our services page.', 'businessvance-services-manager' ); ?></p>
                    <p><?php echo esc_html__( 'Once you complete a purchase through our shop, a project will be automatically created and you can track its progress here.', 'businessvance-services-manager' ); ?></p>
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
                    <p class="bv-subtitle"><?php echo esc_html__( 'Created', 'businessvance-services-manager' ); ?> <?php echo esc_html( date( 'd M Y H:i', strtotime( $project->created_at ) ) ); ?></p>
                </div>
                <span class="bv-portal-project-status-badge bv-status-<?php echo esc_attr( $project->status ); ?>">
                    <?php echo esc_html( $this->status_label( $project->status ) ); ?>
                </span>
            </div>

            <div class="bv-progress-section">
                <div class="bv-progress-header">
                    <span><?php echo esc_html__( 'Project Progress', 'businessvance-services-manager' ); ?></span>
                    <span class="bv-progress-percent"><?php echo $progress; ?>%</span>
                </div>
                <div class="bv-progress-bar">
                    <div class="bv-progress-fill" style="width: <?php echo $progress; ?>%"></div>
                </div>
            </div>

            <div class="bv-info-grid">
                <div class="bv-info-card">
                    <h4><?php echo esc_html__( 'Client Details', 'businessvance-services-manager' ); ?></h4>
                    <p><strong><?php echo esc_html__( 'Name:', 'businessvance-services-manager' ); ?></strong> <?php echo esc_html( $project->client_name ); ?></p>
                    <p><strong><?php echo esc_html__( 'Email:', 'businessvance-services-manager' ); ?></strong> <?php echo esc_html( $project->client_email ); ?></p>
                    <?php if ( $project->client_phone ) : ?>
                    <p><strong><?php echo esc_html__( 'Phone:', 'businessvance-services-manager' ); ?></strong> <?php echo esc_html( $project->client_phone ); ?></p>
                    <?php endif; ?>
                    <?php if ( $project->client_company ) : ?>
                    <p><strong><?php echo esc_html__( 'Company:', 'businessvance-services-manager' ); ?></strong> <?php echo esc_html( $project->client_company ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="bv-info-card">
                    <h4><?php echo esc_html__( 'Services', 'businessvance-services-manager' ); ?></h4>
                    <?php if ( empty( $services ) ) : ?>
                    <p><?php echo esc_html__( 'No services linked', 'businessvance-services-manager' ); ?></p>
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
                <h4><?php echo esc_html__( 'Notes from Consultant', 'businessvance-services-manager' ); ?></h4>
                <div class="bv-notes-content"><?php echo nl2br( esc_html( $project->notes ) ); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the agreement tab.
     * Only shows templates assigned via bv_service_agreements junction table.
     * Removed all global/default fallback logic per v2.5.0.
     *
     * @since 2.0.0
     * @param object     $project  The project object.
     * @param object|null $agreement Signed agreement record or null.
     * @param array      $services  Services linked to the project.
     * @return string
     */
    private function render_agreement_tab( $project, $agreement, $services = array() ) {
        global $wpdb;

        // Collect service IDs
        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }

        $custom_templates = array();
        $has_nda_only      = $this->all_services_nda_only( $services );

        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

            // Get agreement template IDs from junction table for all project services
            $junction_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT sa.service_id, sa.agreement_template_id, s.name as service_name
                 FROM {$wpdb->prefix}bv_service_agreements sa
                 JOIN {$wpdb->prefix}bv_services s ON s.id = sa.service_id
                 WHERE sa.service_id IN ($placeholders)
                 ORDER BY sa.service_id, sa.display_order ASC",
                ...$service_ids
            ) );

            foreach ( $junction_rows as $jr ) {
                $tpl = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bv_agreement_templates WHERE id = %d",
                    $jr->agreement_template_id
                ) );
                if ( $tpl ) {
                    // If ALL services are NDA-only, only show NDA/confidentiality type agreements
                    if ( $has_nda_only && ! in_array( $tpl->type, array( 'nda', 'confidentiality' ), true ) ) {
                        continue;
                    }
                    $custom_templates[] = array(
                        'service'  => $jr->service_name,
                        'template' => $tpl,
                    );
                }
            }
        }

        $has_signed = $agreement && ! empty( $agreement->agreed_at );
        ob_start();
        ?>
        <div class="bv-agreement-section">
            <h2><?php echo esc_html__( 'Agreement', 'businessvance-services-manager' ); ?></h2>
            <?php if ( $has_signed ) : ?>
                <div class="bv-agreement-signed">
                    <div class="bv-check-icon">✓</div>
                    <h3><?php echo esc_html__( 'Agreement Signed', 'businessvance-services-manager' ); ?></h3>
                    <p><?php echo esc_html__( 'Signed by', 'businessvance-services-manager' ); ?> <strong><?php echo esc_html( $agreement->full_name ); ?></strong> <?php echo esc_html__( 'on', 'businessvance-services-manager' ); ?> <strong><?php echo esc_html( date( 'd M Y \a\t H:i', strtotime( $agreement->agreed_at ) ) ); ?></strong></p>
                </div>
                <div class="bv-agreement-content">
                    <?php echo wp_kses_post( $agreement->template_content ); ?>
                </div>
            <?php elseif ( empty( $custom_templates ) ) : ?>
                <div class="bv-empty-state">
                    <p><?php echo esc_html__( 'No agreement is required for this project.', 'businessvance-services-manager' ); ?></p>
                </div>
            <?php else : ?>
                <div class="bv-agreement-warning">
                    <p>⚠️ <?php echo esc_html__( 'Please read and sign the agreement(s) below to proceed with your project.', 'businessvance-services-manager' ); ?></p>
                </div>

                <?php foreach ( $custom_templates as $ct ) : ?>
                <div class="bv-agreement-content" style="margin-bottom: 20px;">
                    <h3 style="margin-top:0;"><?php echo esc_html( $ct['service'] ); ?> — <?php echo esc_html( $ct['template']->name ); ?></h3>
                    <?php echo wp_kses_post( $ct['template']->content ); ?>
                </div>
                <?php endforeach; ?>

                <div class="bv-agreement-sign-form">
                    <h3><?php echo esc_html__( 'Sign the Agreement', 'businessvance-services-manager' ); ?></h3>
                    <p><?php echo esc_html__( 'By signing below, you confirm that you have read and agree to the terms above.', 'businessvance-services-manager' ); ?></p>
                    <div class="bv-form-group">
                        <label><?php echo esc_html__( 'Full Legal Name', 'businessvance-services-manager' ); ?></label>
                        <input type="text" id="bv-sign-name" value="<?php echo esc_attr( $project->client_name ); ?>" required />
                    </div>
                    <button type="button" class="bv-btn bv-btn-primary" onclick="bv_sign_agreement(<?php echo $project->id; ?>)">
                        ✓ <?php echo esc_html__( 'I Agree — Sign Agreement', 'businessvance-services-manager' ); ?>
                    </button>
                    <div id="bv-agreement-status"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get questionnaire data for a project using junction table with legacy fallback.
     * Deduplicates by label|type|options composite key.
     *
     * @since 2.0.0  Updated 2.5.0 for multi-questionnaire + improved dedup.
     * @param int $project_id The project ID.
     * @return array Array of section objects with questions.
     */
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

        if ( empty( $service_ids ) ) {
            return array();
        }

        // Collect all unique questionnaire template IDs
        // Primary: from bv_service_questionnaires junction table
        // Fallback: from legacy bv_services.questionnaire_template_id column
        $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );

        $junction_tids = $wpdb->get_results( $wpdb->prepare(
            "SELECT sq.service_id, sq.questionnaire_template_id
             FROM {$wpdb->prefix}bv_service_questionnaires sq
             WHERE sq.service_id IN ($placeholders)
             ORDER BY sq.service_id, sq.display_order ASC",
            ...$service_ids
        ) );

        $template_ids = array();
        $service_template_map = array(); // service_id => [template_ids]

        if ( ! empty( $junction_tids ) ) {
            foreach ( $junction_tids as $jt ) {
                $template_ids[] = absint( $jt->questionnaire_template_id );
                if ( ! isset( $service_template_map[ $jt->service_id ] ) ) {
                    $service_template_map[ $jt->service_id ] = array();
                }
                $service_template_map[ $jt->service_id ][] = absint( $jt->questionnaire_template_id );
            }
        }

        // Fallback for services with no junction entries: check legacy column
        foreach ( $service_ids as $sid ) {
            if ( ! isset( $service_template_map[ $sid ] ) ) {
                $legacy_tid = $wpdb->get_var( $wpdb->prepare(
                    "SELECT questionnaire_template_id FROM {$wpdb->prefix}bv_services WHERE id = %d AND questionnaire_template_id > 0",
                    $sid
                ) );
                if ( $legacy_tid ) {
                    $template_ids[] = absint( $legacy_tid );
                    $service_template_map[ $sid ] = array( absint( $legacy_tid ) );
                }
            }
        }

        $template_ids = array_unique( $template_ids );
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

        // Deduplicate questions by label|type|options composite key
        // Track which questions came from which template for display
        $seen_question_keys = array();
        $question_service_map = array(); // question_id => service_id
        $all_sections = array();

        // Build reverse map: template_id => service_ids
        $template_service_map = array();
        foreach ( $service_template_map as $sid => $tids ) {
            foreach ( $tids as $tid ) {
                if ( ! isset( $template_service_map[ $tid ] ) ) {
                    $template_service_map[ $tid ] = array();
                }
                $template_service_map[ $tid ][] = $sid;
            }
        }

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

            // Deduplicate by label|type|options composite key
            $unique_questions = array();
            foreach ( $questions as $q ) {
                $key = $q->label . '|' . $q->type . '|' . $q->options;
                if ( isset( $seen_question_keys[ $key ] ) ) {
                    continue;
                }
                $seen_question_keys[ $key ] = true;
                $unique_questions[] = $q;

                // Track which service this question belongs to
                $tpl_id = absint( $section->template_id );
                if ( isset( $template_service_map[ $tpl_id ] ) ) {
                    $question_service_map[ $q->id ] = $template_service_map[ $tpl_id ][0];
                }
            }

            if ( ! empty( $unique_questions ) ) {
                $section->questions   = $unique_questions;
                $section->template_name = $section->template_name;
                $all_sections[]        = $section;
            }
        }

        // Store the question-service map for use in AJAX handler
        $this->_question_service_map = $question_service_map;

        return $all_sections;
    }

    /**
     * Render the questionnaire tab with multi-questionnaire support.
     *
     * @since 2.0.0  Updated 2.5.0
     * @param object $project  The project object.
     * @param array  $sections Array of section objects.
     * @return string
     */
    private function render_questionnaire_tab( $project, $sections ) {
        ob_start();
        ?>
        <div class="bv-questionnaire-section">
            <h2><?php echo esc_html__( 'Client Questionnaire', 'businessvance-services-manager' ); ?></h2>
            <p><?php echo esc_html__( 'Please complete all required fields so we can prepare your report.', 'businessvance-services-manager' ); ?></p>
            <?php if ( empty( $sections ) ) : ?>
                <div class="bv-empty-state"><?php echo esc_html__( 'No questionnaire available for this project yet.', 'businessvance-services-manager' ); ?></div>
            <?php else : ?>
            <form id="bv-questionnaire-form" data-project-id="<?php echo $project->id; ?>">
                <?php foreach ( $sections as $section ) : ?>
                <div class="bv-q-section">
                    <h3><?php echo esc_html( $section->title ); ?></h3>
                    <?php if ( ! empty( $section->template_name ) ) : ?>
                    <p class="bv-q-source"><?php echo esc_html__( 'Source:', 'businessvance-services-manager' ); ?> <em><?php echo esc_html( $section->template_name ); ?></em></p>
                    <?php endif; ?>
                    <?php if ( $section->description ) : ?>
                    <p class="bv-q-desc"><?php echo esc_html( $section->description ); ?></p>
                    <?php endif; ?>
                    <?php foreach ( $section->questions as $q ) : ?>
                    <div class="bv-q-field">
                        <label><?php echo esc_html( $q->label ); ?><?php if ( $q->is_required ) echo ' <span class="bv-required">*</span>'; ?></label>
                        <?php if ( ! empty( $q->help_text ) ) : ?>
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
                                <option value=""><?php echo esc_html__( '— Select —', 'businessvance-services-manager' ); ?></option>
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
                            <?php if ( $val ) : ?><span class="bv-q-file-saved">✓ <?php echo esc_html__( 'File uploaded', 'businessvance-services-manager' ); ?></span><?php endif; ?>
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
                    <button type="submit" class="bv-btn bv-btn-primary"><?php echo esc_html__( 'Save Questionnaire', 'businessvance-services-manager' ); ?></button>
                    <span id="bv-q-status"></span>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the documents tab using bv_service_documents + bv_document_requirements.
     * Removed old json_decode(required_documents) logic per v2.5.0.
     *
     * @since 2.0.0  Updated 2.5.0
     * @param object $project   The project object.
     * @param array  $documents Uploaded documents for the project.
     * @param array  $services  Services linked to the project.
     * @return string
     */
    private function render_documents_tab( $project, $documents, $services = array() ) {
        global $wpdb;

        // Collect service IDs
        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }

        // Get document requirements for all project services
        $requirements = array();
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $requirements = $wpdb->get_results( $wpdb->prepare(
                "SELECT dr.*, sd.service_id, s.name as service_name
                 FROM {$wpdb->prefix}bv_service_documents sd
                 JOIN {$wpdb->prefix}bv_document_requirements dr ON dr.id = sd.document_requirement_id
                 JOIN {$wpdb->prefix}bv_services s ON s.id = sd.service_id
                 WHERE sd.service_id IN ($placeholders)
                 ORDER BY s.name, sd.display_order ASC, dr.display_order ASC",
                ...$service_ids
            ) );
        }

        // Build a map: requirement_id => uploaded documents count
        $doc_map = array();
        $doc_list_by_req = array();
        foreach ( $documents as $doc ) {
            $dr_id = absint( $doc->document_requirement_id );
            if ( $dr_id > 0 ) {
                $doc_map[ $dr_id ] = ( $doc_map[ $dr_id ] ?? 0 ) + 1;
                $doc_list_by_req[ $dr_id ][] = $doc;
            }
        }

        // Check if all required docs are uploaded
        $all_required_done = true;
        $has_any_required  = false;
        foreach ( $requirements as $req ) {
            if ( $req->is_required ) {
                $has_any_required = true;
                if ( empty( $doc_map[ $req->id ] ) ) {
                    $all_required_done = false;
                }
            }
        }

        ob_start();
        ?>
        <div class="bv-documents-section">
            <h2><?php echo esc_html__( 'Documents', 'businessvance-services-manager' ); ?></h2>

            <?php if ( empty( $requirements ) ) : ?>
                <div class="bv-empty-state"><?php echo esc_html__( 'No documents are required for this project.', 'businessvance-services-manager' ); ?></div>
            <?php else : ?>

                <?php if ( $has_any_required ) : ?>
                <div class="bv-doc-completion-bar" style="margin-bottom:20px;">
                    <?php if ( $all_required_done ) : ?>
                        <div class="bv-doc-completion-done" style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:8px;padding:14px 20px;color:#065F46;font-weight:600;">✓ <?php echo esc_html__( 'All required documents have been uploaded.', 'businessvance-services-manager' ); ?></div>
                    <?php else : ?>
                        <div class="bv-doc-completion-pending" style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:14px 20px;color:#92400E;font-weight:600;">⚠️ <?php echo esc_html__( 'Some required documents are still missing. Please upload them below.', 'businessvance-services-manager' ); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="bv-doc-requirements-list">
                <?php foreach ( $requirements as $req ) :
                    $is_fulfilled = ! empty( $doc_map[ $req->id ] );
                    $allowed_exts = ! empty( $req->allowed_types ) ? explode( ',', $req->allowed_types ) : array();
                    $accept_str   = ! empty( $allowed_exts ) ? '.' . implode( ',.', array_map( 'trim', $allowed_exts ) ) : '';
                ?>
                    <div class="bv-doc-requirement-card" id="bv-doc-req-<?php echo $req->id; ?>" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px 24px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,0.04);<?php if ( $is_fulfilled && $req->is_required ) echo 'border-left:4px solid #059669;'; ?>">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                            <div>
                                <h4 style="margin:0 0 4px;"><?php echo esc_html( $req->name ); ?><?php if ( $req->is_required ) echo ' <span class="bv-required" style="color:#DC2626;">*</span>'; ?></h4>
                                <?php if ( $req->description ) : ?>
                                <p style="margin:0;color:#6b7280;font-size:13px;"><?php echo esc_html( $req->description ); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if ( $is_fulfilled ) : ?>
                                <span style="background:#D1FAE5;color:#065F46;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">✓ <?php echo esc_html__( 'Uploaded', 'businessvance-services-manager' ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:#9CA3AF;margin-bottom:14px;">
                            <span><?php echo esc_html__( 'Service:', 'businessvance-services-manager' ); ?> <?php echo esc_html( $req->service_name ); ?></span>
                            <?php if ( ! empty( $allowed_exts ) ) : ?>
                            <span><?php echo esc_html__( 'Accepted:', 'businessvance-services-manager' ); ?> <?php echo esc_html( strtoupper( implode( ', ', array_map( 'trim', $allowed_exts ) ) ) ); ?></span>
                            <?php endif; ?>
                            <?php if ( $req->max_size_mb > 0 ) : ?>
                            <span><?php echo esc_html__( 'Max size:', 'businessvance-services-manager' ); ?> <?php echo esc_html( $req->max_size_mb ); ?>MB</span>
                            <?php endif; ?>
                        </div>

                        <?php if ( ! empty( $doc_list_by_req[ $req->id ] ) ) : ?>
                        <div style="margin-bottom:14px;">
                            <strong style="font-size:13px;color:#374151;"><?php echo esc_html__( 'Uploaded files:', 'businessvance-services-manager' ); ?></strong>
                            <ul style="margin:6px 0 0;padding-left:20px;font-size:13px;color:#4b5563;">
                                <?php foreach ( $doc_list_by_req[ $req->id ] as $d ) : ?>
                                <li><?php echo esc_html( $d->name ); ?> <span style="color:#9CA3AF;"><?php echo esc_html( size_format( $d->filesize ) ); ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="bv-doc-upload-row" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <input type="file" id="bv-doc-file-<?php echo $req->id; ?>" accept="<?php echo esc_attr( $accept_str ); ?>" style="font-size:13px;" />
                            <button type="button" class="bv-btn bv-btn-primary bv-btn-sm" onclick="bv_upload_document_for_requirement(<?php echo $project->id; ?>, <?php echo $req->id; ?>)">
                                <?php echo esc_html__( 'Upload', 'businessvance-services-manager' ); ?>
                            </button>
                            <span class="bv-doc-upload-status" id="bv-doc-status-<?php echo $req->id; ?>"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $documents ) ) : ?>
            <div class="bv-documents-list" style="margin-top:28px;">
                <h4><?php echo esc_html__( 'All Uploaded Documents', 'businessvance-services-manager' ); ?></h4>
                <table class="bv-table">
                    <thead>
                        <tr><th><?php echo esc_html__( 'Document', 'businessvance-services-manager' ); ?></th><th><?php echo esc_html__( 'Uploaded', 'businessvance-services-manager' ); ?></th><th><?php echo esc_html__( 'Size', 'businessvance-services-manager' ); ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $documents as $doc ) : ?>
                        <tr>
                            <td><?php echo esc_html( $doc->name ); ?></td>
                            <td><?php echo esc_html( date( 'd M Y H:i', strtotime( $doc->created_at ) ) ); ?></td>
                            <td><?php echo esc_html( size_format( $doc->filesize ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
            <h2><?php echo esc_html__( 'Reports', 'businessvance-services-manager' ); ?></h2>

            <?php if ( ! empty( $delivered ) ) : ?>
            <div class="bv-reports-delivered">
                <h4><?php echo esc_html__( 'Available Reports', 'businessvance-services-manager' ); ?></h4>
                <div class="bv-reports-grid">
                    <?php foreach ( $delivered as $rpt ) : ?>
                    <div class="bv-report-card">
                        <div class="bv-report-icon">📄</div>
                        <h5><?php echo esc_html( $rpt->title ); ?></h5>
                        <p class="bv-report-meta"><?php echo esc_html__( 'Version', 'businessvance-services-manager' ); ?> <?php echo esc_html( $rpt->version ); ?> — <?php echo esc_html__( 'Delivered', 'businessvance-services-manager' ); ?> <?php echo esc_html( date( 'd M Y', strtotime( $rpt->delivered_at ) ) ); ?></p>
                        <button type="button" class="bv-btn bv-btn-primary" onclick="bv_download_report(<?php echo $rpt->id; ?>)">
                            ⬇ <?php echo esc_html__( 'Download Report', 'businessvance-services-manager' ); ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( empty( $delivered ) ) : ?>
            <div class="bv-empty-state">
                <div class="bv-empty-icon">📄</div>
                <h3><?php echo esc_html__( 'No Reports Available Yet', 'businessvance-services-manager' ); ?></h3>
                <p><?php echo esc_html__( 'Your reports will appear here once they are completed and delivered by your consultant.', 'businessvance-services-manager' ); ?></p>
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
            <h2><?php echo esc_html__( 'Messages', 'businessvance-services-manager' ); ?></h2>

            <div class="bv-messages-thread" id="bv-messages-thread">
                <?php if ( empty( $messages ) ) : ?>
                <div class="bv-empty-state"><?php echo esc_html__( 'No messages yet. Start a conversation below.', 'businessvance-services-manager' ); ?></div>
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
                <textarea id="bv-message-text" placeholder="<?php echo esc_attr__( 'Type your message...', 'businessvance-services-manager' ); ?>" rows="3"></textarea>
                <button type="button" class="bv-btn bv-btn-primary" onclick="bv_send_message(<?php echo $project->id; ?>)">
                    <?php echo esc_html__( 'Send Message', 'businessvance-services-manager' ); ?>
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

    /**
     * Handle document upload with requirement validation.
     *
     * @since 2.0.0  Updated 2.5.0 for document requirements
     * @return void
     */
    public function ajax_upload_document() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( esc_html__( 'Not logged in', 'businessvance-services-manager' ) );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( esc_html__( 'Project not found or access denied', 'businessvance-services-manager' ) );

        if ( empty( $_FILES['file'] ) ) wp_send_json_error( esc_html__( 'No file uploaded', 'businessvance-services-manager' ) );

        $file = $_FILES['file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        // If a document_requirement_id is provided, validate against it
        $dr_id = absint( $_POST['document_requirement_id'] ?? 0 );
        if ( $dr_id > 0 ) {
            global $wpdb;
            $requirement = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bv_document_requirements WHERE id = %d",
                $dr_id
            ) );
            if ( ! $requirement ) {
                wp_send_json_error( esc_html__( 'Document requirement not found', 'businessvance-services-manager' ) );
            }

            // Validate file type
            if ( ! empty( $requirement->allowed_types ) ) {
                $allowed = array_map( 'trim', explode( ',', strtolower( $requirement->allowed_types ) ) );
                if ( ! in_array( $ext, $allowed, true ) ) {
                    wp_send_json_error( sprintf(
                        /* translators: %s: allowed file types */
                        esc_html__( 'File type not allowed. Accepted types: %s', 'businessvance-services-manager' ),
                        strtoupper( implode( ', ', $allowed ) )
                    ) );
                }
            } else {
                // Default allowed types if requirement doesn't specify
                $allowed = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
                if ( ! in_array( $ext, $allowed, true ) ) {
                    wp_send_json_error( esc_html__( 'File type not allowed', 'businessvance-services-manager' ) );
                }
            }

            // Validate file size
            if ( $requirement->max_size_mb > 0 ) {
                $max_bytes = $requirement->max_size_mb * 1024 * 1024;
                if ( $file['size'] > $max_bytes ) {
                    wp_send_json_error( sprintf(
                        /* translators: %d: max size in MB */
                        esc_html__( 'File exceeds maximum size of %d MB', 'businessvance-services-manager' ),
                        $requirement->max_size_mb
                    ) );
                }
            }
        } else {
            // No specific requirement — use default validation
            $allowed = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
            if ( ! in_array( $ext, $allowed, true ) ) {
                wp_send_json_error( esc_html__( 'File type not allowed', 'businessvance-services-manager' ) );
            }
        }

        $filename   = $project_id . '_' . time() . '_' . sanitize_file_name( $file['name'] );
        $upload_path = BV_UPLOAD_DIR . '/' . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $upload_path ) ) {
            wp_send_json_error( esc_html__( 'Upload failed', 'businessvance-services-manager' ) );
        }

        global $wpdb;

        // Determine service_id from the requirement if possible
        $service_id = 0;
        if ( $dr_id > 0 ) {
            $service_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT service_id FROM {$wpdb->prefix}bv_service_documents WHERE document_requirement_id = %d LIMIT 1",
                $dr_id
            ) );
            $service_id = absint( $service_id );
        }

        $wpdb->insert( $wpdb->prefix . 'bv_project_documents', array(
            'project_id'            => $project_id,
            'service_id'            => $service_id,
            'document_requirement_id' => $dr_id,
            'name'                  => sanitize_text_field( $_POST['name'] ?? $file['name'] ),
            'filename'              => $filename,
            'filepath'              => $upload_path,
            'filesize'              => $file['size'],
            'mime_type'             => $file['type'],
            'category'              => $dr_id > 0 ? 'requirement' : sanitize_text_field( $_POST['category'] ?? 'other' ),
            'uploaded_by'           => 'client',
        ), array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id'  => $project_id,
            'entity_type' => 'document',
            'entity_id'   => $wpdb->insert_id,
            'action'      => 'uploaded',
            'description' => esc_html__( 'Client uploaded document: ', 'businessvance-services-manager' ) . sanitize_text_field( $_POST['name'] ?? $file['name'] ),
            'user_id'     => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        $this->notify_consultant( $project_id, esc_html__( 'Document Uploaded', 'businessvance-services-manager' ), esc_html__( 'Client uploaded document: ', 'businessvance-services-manager' ) . sanitize_text_field( $_POST['name'] ?? $file['name'] ) );

        // Check if all required documents are now uploaded and advance status
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d", $project_id ) );
        if ( $project->status === 'awaiting-documents' && $this->all_required_docs_uploaded( $project_id, $services ) ) {
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => 'in-progress', 'progress_percent' => 40 ),
                array( 'id' => $project_id ),
                array( '%s', '%d' ), array( '%d' )
            );
        }

        wp_send_json_success( esc_html__( 'Document uploaded successfully', 'businessvance-services-manager' ) );
    }

    /**
     * Handle questionnaire submission with correct service_id mapping.
     *
     * @since 2.0.0  Updated 2.5.0 for service_id fix + smart status transitions
     * @return void
     */
    public function ajax_submit_questionnaire() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( esc_html__( 'Not logged in', 'businessvance-services-manager' ) );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( esc_html__( 'Project not found or access denied', 'businessvance-services-manager' ) );

        global $wpdb;
        $responses_table = $wpdb->prefix . 'bv_questionnaire_responses';

        // Build question-service map if not already available (recompute for AJAX context)
        $question_service_map = $this->_question_service_map ?? array();

        foreach ( $_POST['responses'] as $question_id => $value ) {
            if ( is_array( $value ) ) $value = wp_json_encode( $value );
            $q_id = absint( $question_id );

            // Determine the correct service_id for this question
            $service_id = isset( $question_service_map[ $q_id ] ) ? absint( $question_service_map[ $q_id ] ) : 0;

            // Fallback: look up which service's template contains this question
            if ( ! $service_id ) {
                $service_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT sq.service_id
                     FROM {$wpdb->prefix}bv_questionnaire_questions q
                     JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON qs.id = q.section_id
                     JOIN {$wpdb->prefix}bv_service_questionnaires sq ON sq.questionnaire_template_id = qs.template_id
                     JOIN {$wpdb->prefix}bv_project_services ps ON ps.service_id = sq.service_id
                     WHERE q.id = %d AND ps.project_id = %d
                     LIMIT 1",
                    $q_id, $project_id
                ) );
                $service_id = absint( $service_id );
            }

            // Second fallback: legacy column
            if ( ! $service_id ) {
                $service_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT ps.service_id
                     FROM {$wpdb->prefix}bv_questionnaire_questions q
                     JOIN {$wpdb->prefix}bv_questionnaire_sections qs ON qs.id = q.section_id
                     JOIN {$wpdb->prefix}bv_services s ON s.questionnaire_template_id = qs.template_id
                     JOIN {$wpdb->prefix}bv_project_services ps ON ps.service_id = s.id
                     WHERE q.id = %d AND ps.project_id = %d
                     LIMIT 1",
                    $q_id, $project_id
                ) );
                $service_id = absint( $service_id );
            }

            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$responses_table} WHERE project_id = %d AND question_id = %d",
                $project_id, $q_id
            ) );
            $data = array(
                'project_id'     => $project_id,
                'service_id'     => $service_id,
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

        // Smart status transition after questionnaire submission
        if ( $project->status === 'awaiting-questionnaire' ) {
            $services = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d",
                $project_id
            ) );
            $next_status = $this->get_next_status_after_questionnaire( $project_id, $services );
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => $next_status, 'progress_percent' => 25 ),
                array( 'id' => $project_id ),
                array( '%s', '%d' ), array( '%d' )
            );
        }

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'questionnaire', 'entity_id' => $project_id,
            'action' => 'submitted', 'description' => esc_html__( 'Client submitted questionnaire responses', 'businessvance-services-manager' ), 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        $this->notify_consultant( $project_id, esc_html__( 'Questionnaire Submitted', 'businessvance-services-manager' ), sprintf(
            /* translators: %s: project number */
            esc_html__( 'Client submitted questionnaire responses for project %s.', 'businessvance-services-manager' ),
            $project->project_number
        ) );
        wp_send_json_success( esc_html__( 'Questionnaire saved successfully', 'businessvance-services-manager' ) );
    }

    /**
     * Handle agreement signing with smart status transitions.
     * Removed all global/default fallback logic per v2.5.0.
     *
     * @since 2.0.0  Updated 2.5.0
     * @return void
     */
    public function ajax_sign_agreement() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( esc_html__( 'Not logged in', 'businessvance-services-manager' ) );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( esc_html__( 'Project not found or access denied', 'businessvance-services-manager' ) );

        $full_name = sanitize_text_field( $_POST['full_name'] );
        if ( empty( $full_name ) ) wp_send_json_error( esc_html__( 'Please enter your full name', 'businessvance-services-manager' ) );

        global $wpdb;

        // Get project services
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT ps.service_id, s.name, s.nda_only
             FROM {$wpdb->prefix}bv_project_services ps
             JOIN {$wpdb->prefix}bv_services s ON s.id = ps.service_id
             WHERE ps.project_id = %d",
            $project_id
        ) );

        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->service_id );
        }

        $has_nda_only = $this->all_services_nda_only( $services );

        // Build template content from junction table ONLY
        $template_parts = array();
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $junction_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT sa.service_id, sa.agreement_template_id, s.name as service_name
                 FROM {$wpdb->prefix}bv_service_agreements sa
                 JOIN {$wpdb->prefix}bv_services s ON s.id = sa.service_id
                 WHERE sa.service_id IN ($placeholders)
                 ORDER BY sa.service_id, sa.display_order ASC",
                ...$service_ids
            ) );

            foreach ( $junction_rows as $jr ) {
                $tpl = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bv_agreement_templates WHERE id = %d",
                    $jr->agreement_template_id
                ) );
                if ( $tpl ) {
                    // If NDA-only, filter out non-NDA types
                    if ( $has_nda_only && ! in_array( $tpl->type, array( 'nda', 'confidentiality' ), true ) ) {
                        continue;
                    }
                    $template_parts[] = '<h3>' . esc_html( $jr->service_name ) . ' — ' . esc_html( $tpl->name ) . '</h3>' . $tpl->content;
                }
            }
        }

        $template = implode( '<hr style="margin:20px 0;">', $template_parts );

        $wpdb->insert( $wpdb->prefix . 'bv_project_agreements', array(
            'project_id'      => $project_id,
            'template_content' => $template,
            'full_name'       => $full_name,
            'ip_address'      => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ),
            'user_agent'      => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ),
            'agreed_at'       => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );

        // Smart status transition
        $next_status = $this->get_next_status_after_agreement( $project_id, $services );
        $wpdb->update( $wpdb->prefix . 'bv_projects',
            array( 'status' => $next_status, 'progress_percent' => 10 ),
            array( 'id' => $project_id ),
            array( '%s', '%d' ), array( '%d' )
        );

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'agreement', 'entity_id' => $wpdb->insert_id,
            'action' => 'signed', 'description' => sprintf(
                /* translators: %s: signer full name */
                esc_html__( 'Agreement signed by %s', 'businessvance-services-manager' ),
                $full_name
            ), 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        $this->notify_consultant( $project_id, esc_html__( 'Agreement Signed', 'businessvance-services-manager' ), sprintf(
            /* translators: %1$s: client name, %2$s: project number */
            esc_html__( 'Client %1$s signed the service agreement for project %2$s.', 'businessvance-services-manager' ),
            $full_name,
            $project->project_number
        ) );
        wp_send_json_success( esc_html__( 'Agreement signed successfully', 'businessvance-services-manager' ) );
    }

    public function ajax_send_message() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( esc_html__( 'Not logged in', 'businessvance-services-manager' ) );

        $project_id = absint( $_POST['project_id'] );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( esc_html__( 'Project not found or access denied', 'businessvance-services-manager' ) );

        $message = sanitize_textarea_field( $_POST['message'] );
        if ( empty( $message ) ) wp_send_json_error( esc_html__( 'Message cannot be empty', 'businessvance-services-manager' ) );

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
        if ( ! is_user_logged_in() ) die( esc_html__( 'Not logged in', 'businessvance-services-manager' ) );

        $report_id = absint( $_GET['report_id'] ?? $_POST['report_id'] ?? 0 );
        if ( ! $report_id ) die( esc_html__( 'Invalid report', 'businessvance-services-manager' ) );

        global $wpdb;
        $report = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.* FROM {$wpdb->prefix}bv_project_reports r
             JOIN {$wpdb->prefix}bv_projects p ON r.project_id = p.id
             WHERE r.id = %d AND p.client_user_id = %d AND r.status = 'delivered'",
            $report_id, get_current_user_id()
        ) );
        if ( ! $report || ! file_exists( $report->filepath ) ) {
            wp_die( esc_html__( 'Report not found', 'businessvance-services-manager' ) );
        }

        header( 'Content-Type: ' . $report->mime_type );
        header( 'Content-Disposition: attachment; filename="' . basename( $report->filename ) . '"' );
        header( 'Content-Length: ' . $report->filesize );
        readfile( $report->filepath );
        exit;
    }

    private function status_label( $status ) {
        $labels = array(
            'awaiting-agreement'     => esc_html__( 'Awaiting Agreement', 'businessvance-services-manager' ),
            'awaiting-questionnaire' => esc_html__( 'Awaiting Questionnaire', 'businessvance-services-manager' ),
            'awaiting-documents'     => esc_html__( 'Awaiting Documents', 'businessvance-services-manager' ),
            'in-progress'            => esc_html__( 'In Progress', 'businessvance-services-manager' ),
            'quality-check'          => esc_html__( 'Quality Check', 'businessvance-services-manager' ),
            'completed'              => esc_html__( 'Completed', 'businessvance-services-manager' ),
            'delivered'              => esc_html__( 'Delivered', 'businessvance-services-manager' ),
            'archived'               => esc_html__( 'Archived', 'businessvance-services-manager' ),
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '-', ' ', $status ) );
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

    /* Questionnaire source label */
    .bv-q-source { font-size: 12px; color: #9CA3AF; margin: 0 0 8px; font-style: italic; }

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

    /* Required asterisk */
    .bv-required { color: #DC2626; font-weight: 600; }

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
