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
    private $_question_service_map = array();

    public function __construct() {
        $this->plugin_url = BV_PLUGIN_URL;
        add_shortcode( 'businessvance_client_portal', array( $this, 'render_portal' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_bv_portal_upload_document', array( $this, 'ajax_upload_document' ) );
        add_action( 'wp_ajax_bv_portal_submit_questionnaire', array( $this, 'ajax_submit_questionnaire' ) );
        add_action( 'wp_ajax_bv_portal_sign_agreement', array( $this, 'ajax_sign_agreement' ) );
        add_action( 'wp_ajax_bv_portal_send_message', array( $this, 'ajax_send_message' ) );
        add_action( 'wp_ajax_bv_portal_download_report', array( $this, 'ajax_download_report' ) );
        add_action( 'wp_ajax_bv_portal_upload_multifile', array( $this, 'ajax_upload_multifile' ) );

        // Ensure bv_project_documents has document_requirement_id column
        // Only runs on admin_init to avoid running on every frontend page load.
        add_action( 'admin_init', array( $this, 'maybe_add_document_requirement_column' ), 99 );
    }

    /**
     * Add document_requirement_id column to bv_project_documents if missing.
     * This supports the new document requirements system.
     *
     * @since 2.5.0
     * @return void
     */
    public function maybe_add_document_requirement_column() {
        // Only run this migration once.
        if ( get_option( 'bv_document_requirement_column_added' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_project_documents';
        $col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'document_requirement_id'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN document_requirement_id bigint(20) UNSIGNED NOT NULL DEFAULT 0 AFTER service_id" );
        }

        // Mark as done so we never run this again on every page load.
        update_option( 'bv_document_requirement_column_added', '1' );
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
            // Star rating, repeatable table, other option toggle, multifile, signature logic are all in client-portal.js
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

    /**
     * Auto-calculate project progress based on completed client steps.
     *
     * Checks which steps are required for the project's services, then
     * determines which have been completed, and returns a percentage.
     *
     * Steps: Agreement, Questionnaire, Documents
     *
     * @since 2.7.6
     * @param int $project_id The project ID.
     * @param array $services Services linked to the project.
     * @return int Progress percentage 0-100.
     */
    private function calculate_project_progress( $project_id, $services = null ) {
        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id ) );
        if ( ! $project ) return 0;

        if ( ! $services ) {
            $services = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d",
                $project_id
            ) );
        }

        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }

        // Build list of required steps for this project
        $required_steps = array();

        // Step 1: Agreement
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $has_agreement_templates = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_agreements WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
        } else {
            $has_agreement_templates = false;
        }

        if ( $has_agreement_templates ) {
            // Check if agreement is signed
            $agreement_signed = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_project_agreements WHERE project_id = %d",
                $project_id
            ) );
            $required_steps['agreement'] = $agreement_signed;
        }

        // Step 2: Questionnaire
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
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
        } else {
            $has_questionnaires = false;
        }

        if ( $has_questionnaires ) {
            // Check if any questionnaire responses exist for this project
            $questionnaire_done = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_questionnaire_responses WHERE project_id = %d",
                $project_id
            ) );
            $required_steps['questionnaire'] = $questionnaire_done;
        }

        // Step 3: Documents
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $has_document_reqs = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_documents WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
        } else {
            $has_document_reqs = false;
        }

        if ( $has_document_reqs ) {
            $docs_done = $this->all_required_docs_uploaded( $project_id, $services );
            $required_steps['documents'] = $docs_done;
        }

        // Calculate percentage
        if ( empty( $required_steps ) ) {
            // No steps required — if project is in-progress or beyond, show meaningful progress
            $final_statuses = array( 'in-progress', 'quality-check', 'completed', 'delivered', 'archived' );
            if ( in_array( $project->status, $final_statuses, true ) ) {
                return 50; // Consultant's turn, client has nothing else to do
            }
            return 0;
        }

        $completed = count( array_filter( $required_steps ) );
        $total     = count( $required_steps );
        $percent   = round( ( $completed / $total ) * 100 );

        return max( 0, min( 100, $percent ) );
    }

    /**
     * Update the project's progress_percent in the database and return the new value.
     *
     * @since 2.7.6
     * @param int $project_id The project ID.
     * @param array $services Services linked to the project (optional, fetched if null).
     * @return int The new progress percentage.
     */
    private function update_project_progress( $project_id, $services = null ) {
        global $wpdb;
        $progress = $this->calculate_project_progress( $project_id, $services );
        $wpdb->update(
            $wpdb->prefix . 'bv_projects',
            array( 'progress_percent' => $progress ),
            array( 'id' => $project_id ),
            array( '%d' ),
            array( '%d' )
        );
        return $progress;
    }

    /**
     * Get visual step completion info for the overview tab progress section.
     *
     * Returns an ordered array of steps with 'num', 'label', 'done', 'active' keys.
     *
     * @since 2.7.6
     * @param int   $project_id The project ID.
     * @param array $services   Services linked to the project.
     * @return array Step info arrays.
     */
    private function get_step_completion_info( $project_id, $services ) {
        global $wpdb;
        $service_ids = array();
        foreach ( $services as $svc ) {
            $service_ids[] = absint( $svc->id );
        }

        $steps = array();
        $num  = 1;

        // Step: Agreement
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $has_agreement = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_agreements WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
        } else {
            $has_agreement = false;
        }

        if ( $has_agreement ) {
            $signed = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_project_agreements WHERE project_id = %d",
                $project_id
            ) );
            $steps[] = array( 'num' => $num, 'label' => esc_html__( 'Agreement', 'businessvance-services-manager' ), 'done' => $signed, 'active' => ! $signed );
            $num++;
        }

        // Step: Questionnaire
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $has_q = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_questionnaires WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
            if ( ! $has_q ) {
                $has_q = (bool) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}bv_services WHERE id IN ($placeholders) AND questionnaire_template_id > 0",
                    ...$service_ids
                ) );
            }
        } else {
            $has_q = false;
        }

        if ( $has_q ) {
            $done = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_questionnaire_responses WHERE project_id = %d",
                $project_id
            ) );
            $is_active = false;
            // Mark as active if no prior step is incomplete
            if ( empty( $steps ) || ! in_array( false, array_column( $steps, 'done' ), true ) ) {
                $is_active = ! $done;
            }
            $steps[] = array( 'num' => $num, 'label' => esc_html__( 'Questionnaire', 'businessvance-services-manager' ), 'done' => $done, 'active' => $is_active );
            $num++;
        }

        // Step: Documents
        if ( ! empty( $service_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
            $has_docs = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bv_service_documents WHERE service_id IN ($placeholders)",
                ...$service_ids
            ) );
        } else {
            $has_docs = false;
        }

        if ( $has_docs ) {
            $done = $this->all_required_docs_uploaded( $project_id, $services );
            $is_active = false;
            if ( empty( $steps ) || ! in_array( false, array_column( $steps, 'done' ), true ) ) {
                $is_active = ! $done;
            }
            $steps[] = array( 'num' => $num, 'label' => esc_html__( 'Documents', 'businessvance-services-manager' ), 'done' => $done, 'active' => $is_active );
            $num++;
        }

        return $steps;
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
                if ( $p->id === $project_id ) { $active_project = $p; break; }
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
        <div class="bv-portal" id="bv-portal-app" data-tab-style="<?php echo esc_attr( $portal_settings['portal_tab_style'] ?? 'underline' ); ?>">
            <style><?php echo $this->get_inline_css(); ?></style>
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

    /**
     * Build client guidance info — determines what the client should do next
     * and whether all their required steps are complete.
     *
     * @since 2.7.7
     * @param object $project   The project row.
     * @param array  $services  Services linked to the project.
     * @param array  $step_info Step completion info from get_step_completion_info().
     * @param int    $progress  Current progress percentage.
     * @return array { all_done: bool, next_action: array|null }
     */
    private function build_client_guidance( $project, $services, $step_info, $progress ) {
        $all_done = ( $progress >= 100 );
        $next_action = null;

        if ( $all_done ) {
            return array( 'all_done' => true, 'next_action' => null );
        }

        // Find the first incomplete step
        foreach ( $step_info as $step ) {
            if ( ! $step['done'] ) {
                $label      = strtolower( $step['label'] );
                $step_title = sprintf(
                    /* translators: %1$d: step number, %2$s: step label */
                    esc_html__( 'Step %1$d: %2$s', 'businessvance-services-manager' ),
                    $step['num'],
                    $step['label']
                );
                switch ( $label ) {
                    case 'agreement':
                        $next_action = array(
                            'title'       => $step_title,
                            'description' => esc_html__( 'Please read through the agreement carefully and sign it by entering your full legal name. This must be completed before you can proceed to the next step.', 'businessvance-services-manager' ),
                            'button'      => esc_html__( 'Go to Agreement', 'businessvance-services-manager' ),
                            'tab'         => 'agreement',
                        );
                        break 2;
                    case 'questionnaire':
                        $next_action = array(
                            'title'       => $step_title,
                            'description' => esc_html__( 'Please fill out the questionnaire with accurate details about your business. This information is essential for preparing your report. You can save and return later if needed.', 'businessvance-services-manager' ),
                            'button'      => esc_html__( 'Go to Questionnaire', 'businessvance-services-manager' ),
                            'tab'         => 'questionnaire',
                        );
                        break 2;
                    case 'documents':
                        $next_action = array(
                            'title'       => $step_title,
                            'description' => esc_html__( 'Please upload all the required documents listed. These may include ID copies, registration certificates, financial statements, etc. Make sure each file meets the format and size requirements shown.', 'businessvance-services-manager' ),
                            'button'      => esc_html__( 'Go to Documents', 'businessvance-services-manager' ),
                            'tab'         => 'documents',
                        );
                        break 2;
                    default:
                        $next_action = array(
                            'title'       => $step_title,
                            'description' => esc_html__( 'Please complete this step to continue with your project.', 'businessvance-services-manager' ),
                            'button'      => sprintf( /* translators: %s: step label */ esc_html__( 'Go to %s', 'businessvance-services-manager' ), $step['label'] ),
                            'tab'         => $label,
                        );
                        break 2;
                }
            }
        }

        return array( 'all_done' => false, 'next_action' => $next_action );
    }

    /**
     * Send a completion email to the client when they finish all required steps.
     *
     * @since 2.7.7
     * @param int $project_id The project ID.
     * @return void
     */
    private function notify_client_completion( $project_id ) {
        $settings = BV_Settings::get_settings();
        if ( ( $settings['email_report_ready'] ?? 'yes' ) !== 'yes' ) {
            return;
        }

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id ) );
        if ( ! $project || empty( $project->client_email ) ) {
            return;
        }

        $company_name    = $settings['company_name'] ?? 'BusinessVance';
        $consultant_email = $settings['consultant_email'] ?? '';
        $consultant_phone = $settings['phone_number'] ?? '';
        $portal_url      = $settings['portal_url'] ?? '';

        $subject = sprintf(
            /* translators: %1$s: project number, %2$s: company name */
            esc_html__( 'All Information Submitted for Project %1$s — %2$s', 'businessvance-services-manager' ),
            $project->project_number,
            $company_name
        );

        $body = sprintf(
            /* translators: %1$s: client name, %2$s: project number, %3$s: company name */
            esc_html__( 'Dear %1$s,', 'businessvance-services-manager' ) . "\n\n" .
            esc_html__( 'Thank you for completing all the required steps for project %2$s.', 'businessvance-services-manager' ) . "\n\n" .
            esc_html__( 'Your consultant at %3$s will now review your information and begin working on your report. No further action is needed from your side at this time.', 'businessvance-services-manager' ) . "\n\n",
            $project->client_name,
            $project->project_number,
            $company_name
        );

        if ( $consultant_email || $consultant_phone ) {
            $body .= esc_html__( 'If you have any questions, you can reach us:', 'businessvance-services-manager' ) . "\n";
            if ( $consultant_email ) {
                $body .= '  • ' . esc_html__( 'Email: ', 'businessvance-services-manager' ) . $consultant_email . "\n";
            }
            if ( $consultant_phone ) {
                $body .= '  • ' . esc_html__( 'Phone: ', 'businessvance-services-manager' ) . $consultant_phone . "\n";
            }
            $body .= "\n";
        }

        if ( $portal_url ) {
            $body .= sprintf(
                /* translators: %s: portal URL */
                esc_html__( 'You can check your project status anytime here: %s', 'businessvance-services-manager' ),
                $portal_url
            ) . "\n\n";
        }

        $body .= esc_html__( 'Best regards,', 'businessvance-services-manager' ) . "\n" . $company_name;

        wp_mail( $project->client_email, $subject, $body );
    }

    private function render_overview_tab( $project, $services ) {
        $progress = max( 0, min( 100, (int) $project->progress_percent ) );

        // Build step completion info for visual display
        $step_info = $this->get_step_completion_info( $project->id, $services );

        // Build next-step guidance
        $guidance = $this->build_client_guidance( $project, $services, $step_info, $progress );
        $client_steps_complete = $guidance['all_done'];
        $next_action = $guidance['next_action'];

        // Consultant contact details from settings
        $settings         = BV_Settings::get_settings();
        $company_name     = $settings['company_name'] ?? '';
        $consultant_email = $settings['consultant_email'] ?? '';
        $consultant_phone = $settings['phone_number'] ?? '';
        $consultant_address = $settings['physical_address'] ?? '';
        $has_contact_info = ( $consultant_email || $consultant_phone || $consultant_address );

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
                <?php if ( ! empty( $step_info ) ) : ?>
                <div class="bv-steps">
                    <?php foreach ( $step_info as $step ) : ?>
                    <div class="bv-step">
                        <div class="bv-step-dot <?php echo $step['done'] ? 'done' : ( $step['active'] ? 'active' : '' ); ?>">
                            <?php if ( $step['done'] ) : ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else : ?>
                                <?php echo esc_html( $step['num'] ); ?>
                            <?php endif; ?>
                        </div>
                        <span class="bv-step-label <?php echo $step['done'] ? 'done' : ( $step['active'] ? 'active' : '' ); ?>"><?php echo esc_html( $step['label'] ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( $client_steps_complete ) : ?>
            <!-- All client steps complete -->
            <div class="bv-guidance-banner bv-guidance-complete">
                <div class="bv-guidance-icon">🎉</div>
                <div class="bv-guidance-content">
                    <h3><?php echo esc_html__( 'All Information Submitted!', 'businessvance-services-manager' ); ?></h3>
                    <p><?php echo esc_html__( 'Thank you for completing all the required steps. Your consultant will now review your information and prepare your report. No further action is needed from your side at this time.', 'businessvance-services-manager' ); ?></p>
                    <?php if ( $has_contact_info ) : ?>
                    <div class="bv-guidance-contact">
                        <h4><?php echo esc_html__( 'Need to contact us?', 'businessvance-services-manager' ); ?></h4>
                        <?php if ( $consultant_email ) : ?>
                        <p><strong><?php echo esc_html__( 'Email:', 'businessvance-services-manager' ); ?></strong> <a href="mailto:<?php echo esc_attr( $consultant_email ); ?>"><?php echo esc_html( $consultant_email ); ?></a></p>
                        <?php endif; ?>
                        <?php if ( $consultant_phone ) : ?>
                        <p><strong><?php echo esc_html__( 'Phone:', 'businessvance-services-manager' ); ?></strong> <a href="tel:<?php echo esc_attr( $consultant_phone ); ?>"><?php echo esc_html( $consultant_phone ); ?></a></p>
                        <?php endif; ?>
                        <?php if ( $consultant_address ) : ?>
                        <p><strong><?php echo esc_html__( 'Address:', 'businessvance-services-manager' ); ?></strong> <?php echo esc_html( $consultant_address ); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ( $next_action ) : ?>
            <!-- Next step guidance -->
            <div class="bv-guidance-banner bv-guidance-action">
                <div class="bv-guidance-icon">👉</div>
                <div class="bv-guidance-content">
                    <h3><?php echo esc_html( $next_action['title'] ); ?></h3>
                    <p><?php echo esc_html( $next_action['description'] ); ?></p>
                    <?php if ( ! empty( $next_action['tab'] ) ) : ?>
                    <a href="?project_id=<?php echo $project->id; ?>&tab=<?php echo esc_attr( $next_action['tab'] ); ?>" class="bv-guidance-btn"><?php echo esc_html( $next_action['button'] ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

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

            <?php if ( $has_contact_info ) : ?>
            <!-- Always-visible contact card -->
            <div class="bv-contact-card">
                <h4><?php echo esc_html( $company_name ?: esc_html__( 'Consultant Contact', 'businessvance-services-manager' ) ); ?></h4>
                <div class="bv-contact-card-body">
                    <?php if ( $consultant_email ) : ?>
                    <div class="bv-contact-item">
                        <span class="bv-contact-icon">✉</span>
                        <a href="mailto:<?php echo esc_attr( $consultant_email ); ?>"><?php echo esc_html( $consultant_email ); ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if ( $consultant_phone ) : ?>
                    <div class="bv-contact-item">
                        <span class="bv-contact-icon">📞</span>
                        <a href="tel:<?php echo esc_attr( $consultant_phone ); ?>"><?php echo esc_html( $consultant_phone ); ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if ( $consultant_address ) : ?>
                    <div class="bv-contact-item">
                        <span class="bv-contact-icon">📍</span>
                        <span><?php echo esc_html( $consultant_address ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

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
            <div class="bv-q-intro">
                <h2><?php echo esc_html__( 'Client Questionnaire', 'businessvance-services-manager' ); ?></h2>
                <p><?php echo esc_html__( 'Please complete all required fields so we can prepare your report.', 'businessvance-services-manager' ); ?></p>
            </div>
            <?php if ( empty( $sections ) ) : ?>
                <div class="bv-empty-state"><?php echo esc_html__( 'No questionnaire available for this project yet.', 'businessvance-services-manager' ); ?></div>
            <?php else : ?>
            <form id="bv-questionnaire-form" data-project-id="<?php echo $project->id; ?>">
                <?php $section_num = 0; foreach ( $sections as $section ) : $section_num++; ?>
                <div class="bv-q-section" id="bv-q-section-<?php echo $section_num; ?>">
                    <div class="bv-q-section-header">
                        <div class="bv-q-section-num"><?php echo $section_num; ?></div>
                        <div class="bv-q-section-info">
                            <h3><?php echo esc_html( $section->title ); ?></h3>
                            <?php if ( ! empty( $section->template_name ) ) : ?>
                            <span class="bv-q-source"><?php echo esc_html__( 'Source:', 'businessvance-services-manager' ); ?> <em><?php echo esc_html( $section->template_name ); ?></em></span>
                            <?php endif; ?>
                            <?php if ( $section->description ) : ?>
                            <p class="bv-q-desc"><?php echo esc_html( $section->description ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bv-q-section-body">
                    <?php foreach ( $section->questions as $q ) : ?>
                    <?php
                        $options = json_decode( $q->options, true );
                        $val = $q->response_value ? $q->response_value : '';
                        $qid = esc_attr( $q->id );
                        $req = $q->is_required ? 'required' : '';

                        if ( $q->type === 'heading' ) : ?>
                        <div class="bv-q-field bv-q-heading">
                            <h4><?php echo esc_html( $q->label ); ?></h4>
                        </div>
                    <?php elseif ( $q->type === 'paragraph' ) : ?>
                        <div class="bv-q-field bv-q-paragraph">
                            <p><?php echo esc_html( $q->label ); ?></p>
                        </div>
                    <?php else : ?>
                    <div class="bv-q-field">
                        <?php if ( ! in_array( $q->type, array( 'heading', 'paragraph', 'static_text', 'static_image' ), true ) ) : ?>
                        <label for="q_<?php echo $qid; ?>"><?php echo esc_html( $q->label ); ?><?php if ( $q->is_required ) echo ' <span class="bv-required">*</span>'; ?></label>
                        <?php endif; ?>
                        <?php if ( ! empty( $q->help_text ) && ! in_array( $q->type, array( 'static_text', 'static_image' ), true ) ) : ?>
                        <small class="bv-q-help"><?php echo esc_html( $q->help_text ); ?></small>
                        <?php endif; ?>

                        <?php if ( $q->type === 'textarea' ) : ?>
                            <textarea id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" <?php echo $req; ?> placeholder="<?php echo esc_attr( $q->placeholder ); ?>" rows="4"><?php echo esc_textarea( $val ); ?></textarea>
                        <?php elseif ( $q->type === 'select' && is_array( $options ) ) : ?>
                            <select id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" <?php echo $req; ?>>
                                <option value=""><?php echo esc_html__( '— Select —', 'businessvance-services-manager' ); ?></option>
                                <?php foreach ( $options as $opt ) : ?>
                                <option value="<?php echo esc_attr( is_array($opt) ? $opt['value'] ?? $opt[0] : $opt ); ?>" <?php selected( $val, is_array($opt) ? $opt['value'] ?? $opt[0] : $opt ); ?>>
                                    <?php echo esc_html( is_array($opt) ? $opt['label'] ?? $opt[1] : $opt ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ( $q->type === 'radio' && is_array( $options ) ) : ?>
                            <div class="bv-q-radio-group">
                                <?php $has_other = false; foreach ( $options as $i => $opt ) : $ov = is_array($opt) ? $opt['value'] ?? $opt[0] : $opt; if ( $ov === '__other__' ) { $has_other = true; continue; } ?>
                                <label class="bv-q-radio"><input type="radio" id="q_<?php echo $qid; ?>_<?php echo $i; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $ov ); ?>" <?php checked( $val, $ov ); ?> <?php echo $req; ?> /><span><?php echo esc_html( is_array($opt) ? $opt['label'] ?? $opt[1] : $opt ); ?></span></label>
                                <?php endforeach; ?>
                                <?php if ( $has_other ) : ?>
                                <label class="bv-q-radio bv-q-other-option"><input type="radio" id="q_<?php echo $qid; ?>_other" name="q_<?php echo $qid; ?>" value="__other__" <?php checked( $val, '__other__' ); ?> <?php echo $req; ?> /><span><?php echo esc_html__( 'Other', 'businessvance-services-manager' ); ?></span> <input type="text" id="q_<?php echo $qid; ?>_other_text" name="q_<?php echo $qid; ?>_other_text" class="bv-q-other-input" placeholder="<?php echo esc_attr__( 'Please specify…', 'businessvance-services-manager' ); ?>" value="<?php echo ( $val === '__other__' ) ? esc_attr( get_post_meta( $project->id, 'bv_q_' . $qid . '_other', true ) ) : ''; ?>" style="display:<?php echo ( $val === '__other__' ) ? 'inline-block' : 'none'; ?>;" /></label>
                                <?php endif; ?>
                            </div>
                        <?php elseif ( $q->type === 'checkbox' && is_array( $options ) ) : ?>
                            <div class="bv-q-checkbox-group">
                                <?php $saved = json_decode( $val, true ) ?: array(); $has_other = false; foreach ( $options as $opt ) : $ov = is_array($opt) ? $opt['value'] ?? $opt[0] : $opt; if ( $ov === '__other__' ) { $has_other = true; continue; } ?>
                                <label class="bv-q-checkbox"><input type="checkbox" id="q_<?php echo $qid; ?>_<?php echo esc_attr( $ov ); ?>" name="q_<?php echo $qid; ?>[]" value="<?php echo esc_attr( $ov ); ?>" <?php echo in_array( $ov, $saved ) ? 'checked' : ''; ?> /><span><?php echo esc_html( is_array($opt) ? $opt['label'] ?? $opt[1] : $opt ); ?></span></label>
                                <?php endforeach; ?>
                                <?php if ( $has_other ) : ?>
                                <label class="bv-q-checkbox bv-q-other-option"><input type="checkbox" id="q_<?php echo $qid; ?>_other" name="q_<?php echo $qid; ?>[]" value="__other__" <?php echo in_array( '__other__', $saved ) ? 'checked' : ''; ?> /><span><?php echo esc_html__( 'Other', 'businessvance-services-manager' ); ?></span> <input type="text" id="q_<?php echo $qid; ?>_other_text" name="q_<?php echo $qid; ?>_other_text" class="bv-q-other-input" placeholder="<?php echo esc_attr__( 'Please specify…', 'businessvance-services-manager' ); ?>" value="<?php echo in_array( '__other__', $saved ) ? esc_attr( get_post_meta( $project->id, 'bv_q_' . $qid . '_other', true ) ) : ''; ?>" style="display:<?php echo in_array( '__other__', $saved ) ? 'inline-block' : 'none'; ?>;" /></label>
                                <?php endif; ?>
                            </div>
                        <?php elseif ( $q->type === 'file' ) : ?>
                            <div class="bv-q-file-area">
                                <input type="file" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" class="bv-q-file" data-question-id="<?php echo $qid; ?>" />
                                <?php if ( $val ) : ?><span class="bv-q-file-saved">&#10003; <?php echo esc_html__( 'File uploaded', 'businessvance-services-manager' ); ?></span><?php endif; ?>
                            </div>
                        <?php elseif ( $q->type === 'number' ) : ?>
                            <input type="number" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'email' ) : ?>
                            <input type="email" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'phone' ) : ?>
                            <input type="tel" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'date' ) : ?>
                            <input type="date" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'url' ) : ?>
                            <input type="url" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'time' ) : ?>
                            <input type="time" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" <?php echo $req; ?> />
                        <?php elseif ( $q->type === 'color' ) : ?>
                            <input type="color" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ) ?: '#000000'; ?>" <?php echo $req; ?> style="width:60px;height:40px;cursor:pointer;" />
                        <?php elseif ( $q->type === 'range' ) : ?>
                            <?php $range_min = 0; $range_max = 100; $range_step = 1; if ( is_array( $options ) && count( $options ) >= 2 ) { $range_min = intval( $options[0] ); $range_max = intval( $options[1] ); if ( count( $options ) >= 3 ) $range_step = intval( $options[2] ); } ?>
                            <div class="bv-q-range-wrap"><input type="range" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ) ?: $range_min; ?>" min="<?php echo $range_min; ?>" max="<?php echo $range_max; ?>" step="<?php echo $range_step; ?>" <?php echo $req; ?> oninput="this.nextElementSibling.textContent=this.value" /><output><?php echo esc_html( $val ) ?: $range_min; ?></output></div>
                        <?php elseif ( $q->type === 'rating' ) : ?>
                            <?php $stars = 5; $rating_val = intval( $val ) ?: 0; if ( is_array( $options ) && count( $options ) >= 1 ) $stars = max( 1, intval( $options[0] ) ); ?>
                            <div class="bv-q-rating-wrap" data-qid="<?php echo $qid; ?>">
                                <?php for ( $si = 1; $si <= $stars; $si++ ) : ?>
                                <span class="bv-q-star" data-val="<?php echo $si; ?>" style="cursor:pointer;font-size:28px;color:<?php echo $si <= $rating_val ? '#f59e0b' : '#d1d5db'; ?>;">&#9733;</span>
                                <?php endfor; ?>
                                <input type="hidden" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo $rating_val; ?>" />
                            </div>
                        <?php elseif ( $q->type === 'address' ) : ?>
                            <?php $addr = json_decode( $val, true ) ?: array(); ?>
                            <div class="bv-q-address-wrap">
                                <input type="text" name="q_<?php echo $qid; ?>[street]" value="<?php echo esc_attr( $addr['street'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Street Address', 'businessvance-services-manager' ); ?>" class="bv-q-addr-field" <?php echo $req; ?> />
                                <div class="bv-q-addr-row"><input type="text" name="q_<?php echo $qid; ?>[city]" value="<?php echo esc_attr( $addr['city'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'City', 'businessvance-services-manager' ); ?>" class="bv-q-addr-field" <?php echo $req; ?> /><input type="text" name="q_<?php echo $qid; ?>[state]" value="<?php echo esc_attr( $addr['state'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'State/Province', 'businessvance-services-manager' ); ?>" class="bv-q-addr-field" <?php echo $req; ?> /></div>
                                <div class="bv-q-addr-row"><input type="text" name="q_<?php echo $qid; ?>[zip]" value="<?php echo esc_attr( $addr['zip'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'ZIP/Postal Code', 'businessvance-services-manager' ); ?>" class="bv-q-addr-field" <?php echo $req; ?> /><input type="text" name="q_<?php echo $qid; ?>[country]" value="<?php echo esc_attr( $addr['country'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Country', 'businessvance-services-manager' ); ?>" class="bv-q-addr-field" <?php echo $req; ?> /></div>
                            </div>
                        <?php elseif ( $q->type === 'repeatable' ) : ?>
                            <?php $rows = json_decode( $val, true ) ?: array(); $col_defs = array(); if ( is_array( $options ) ) { foreach ( $options as $col ) { $col_defs[] = array( 'name' => is_array($col) ? ($col['label'] ?? $col[1] ?? $col['value'] ?? $col[0]) : $col, 'type' => is_array($col) ? ($col['value'] ?? 'text') : 'text' ); } } ?>
                            <div class="bv-q-repeatable-wrap" data-qid="<?php echo $qid; ?>">
                                <div class="bv-q-rep-table"><table><thead><tr><?php foreach ( $col_defs as $col ) : ?><th><?php echo esc_html( $col['name'] ); ?></th><?php endforeach; ?><th style="width:32px;"></th></tr></thead><tbody>
                                <?php foreach ( $rows as $ri => $row ) : ?>
                                <tr><?php foreach ( $col_defs as $ci => $col ) : ?><td><input type="text" name="q_<?php echo $qid; ?>[<?php echo $ri; ?>][<?php echo $ci; ?>]" value="<?php echo esc_attr( $row[$ci] ?? '' ); ?>" class="bv-q-rep-cell" /></td><?php endforeach; ?><td><button type="button" class="bv-q-rep-remove" title="Remove row">&times;</button></td></tr>
                                <?php endforeach; ?>
                                <?php if ( empty( $rows ) ) : ?>
                                <tr><?php foreach ( $col_defs as $ci => $col ) : ?><td><input type="text" name="q_<?php echo $qid; ?>[0][<?php echo $ci; ?>]" value="" class="bv-q-rep-cell" /></td><?php endforeach; ?><td><button type="button" class="bv-q-rep-remove" title="Remove row">&times;</button></td></tr>
                                <?php endif; ?>
                                </tbody></table></div>
                                <button type="button" class="bv-q-rep-add-row button button-small"><?php echo esc_html__( '+ Add Row', 'businessvance-services-manager' ); ?></button>
                            </div>
                        <?php elseif ( $q->type === 'multifile' ) : ?>
                            <?php
                            $saved_files = json_decode( $val, true ) ?: array();
                            $accept = '';
                            if ( is_array( $options ) && ! empty( $options[0] ) ) {
                                $exts = is_array( $options[0] ) ? ( $options[0]['value'] ?? '' ) : $options[0];
                                if ( $exts ) $accept = ' accept=".' . implode( ',.', array_map( 'trim', explode( ',', $exts ) ) ) . '"';
                            }
                            ?>
                            <div class="bv-q-multifile-wrap" data-qid="<?php echo $qid; ?>">
                                <div class="bv-q-multifile-dropzone" data-qid="<?php echo $qid; ?>">
                                    <span class="bv-q-multifile-dropzone-icon">&#128194;</span>
                                    <div class="bv-q-multifile-dropzone-text"><?php echo esc_html__( 'Drag files here or', 'businessvance-services-manager' ); ?> <strong><?php echo esc_html__( 'browse', 'businessvance-services-manager' ); ?></strong></div>
                                </div>
                                <input type="file" class="bv-q-multifile-input" data-qid="<?php echo $qid; ?>" multiple<?php echo $accept; ?> />
                                <div class="bv-q-multifile-list">
                                <?php foreach ( $saved_files as $sf ) : ?>
                                    <div class="bv-q-mf-file" data-filename="<?php echo esc_attr( is_array($sf) ? ($sf['name'] ?? '') : $sf ); ?>">
                                        <span class="bv-q-mf-file-icon">&#128196;</span>
                                        <span class="bv-q-mf-file-name"><?php echo esc_html( is_array($sf) ? ($sf['name'] ?? '') : $sf ); ?></span>
                                        <?php if ( is_array($sf) && ! empty( $sf['url'] ) ) : ?>
                                        <a href="<?php echo esc_url( $sf['url'] ); ?>" target="_blank" class="bv-q-mf-file-icon" title="Download">&#128279;</a>
                                        <?php endif; ?>
                                        <?php if ( is_array($sf) && ! empty( $sf['size'] ) ) : ?>
                                        <span class="bv-q-mf-file-size"><?php echo esc_html( $sf['size'] ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <input type="hidden" class="bv-q-multifile-data" data-qid="<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" />
                            </div>
                        <?php elseif ( $q->type === 'static_text' ) : ?>
                            <?php
                            // Get content from saved options (stored as single-element array with content in label)
                            $static_content = '';
                            if ( is_array( $options ) && ! empty( $options[0] ) ) {
                                $static_content = is_array( $options[0] ) ? ( $options[0]['label'] ?? $options[0]['value'] ?? '' ) : (string) $options[0];
                            }
                            // Fallback to label if no content was saved
                            if ( ! $static_content ) {
                                $static_content = $q->label;
                            }
                            ?>
                            <div class="bv-q-static-text"><?php echo wp_kses_post( $static_content ); ?></div>
                        <?php elseif ( $q->type === 'static_image' ) : ?>
                            <?php
                            $img_url = '';
                            $img_caption = '';
                            if ( is_array( $options ) && ! empty( $options[0] ) ) {
                                // New format: [0] has URL in value/label, [1] has caption
                                $img_url = is_array( $options[0] ) ? ( $options[0]['value'] ?? $options[0]['label'] ?? '' ) : (string) $options[0];
                                if ( ! empty( $options[1] ) ) {
                                    $img_caption = is_array( $options[1] ) ? ( $options[1]['label'] ?? '' ) : (string) $options[1];
                                }
                            }
                            if ( ! $img_url && ! empty( $q->placeholder ) ) {
                                $img_url = $q->placeholder;
                            }
                            ?>
                            <figure class="bv-q-static-image">
                            <?php if ( $img_url ) : ?>
                                <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $q->label ); ?>" loading="lazy" />
                            <?php else : ?>
                                <div style="padding:40px;background:#f3f4f6;border-radius:10px;color:#9CA3AF;"><?php echo esc_html__( 'No image URL configured. Set the image URL in the placeholder field.', 'businessvance-services-manager' ); ?></div>
                            <?php endif; ?>
                            <?php if ( $img_caption ) : ?>
                                <figcaption><?php echo esc_html( $img_caption ); ?></figcaption>
                            <?php endif; ?>
                            </figure>
                        <?php elseif ( $q->type === 'signature' ) : ?>
                            <?php $sig_val = $val; ?>
                            <div class="bv-q-signature-wrap<?php echo $sig_val ? ' bv-q-sig-confirmed' : ''; ?>">
                                <?php if ( $sig_val && preg_match( '/^data:image/', $sig_val ) ) : ?>
                                    <img src="<?php echo esc_attr( $sig_val ); ?>" alt="Signature" style="width:100%;height:160px;object-fit:contain;background:repeating-linear-gradient(0deg,transparent,transparent 39px,#f3f4f6 39px,#f3f4f6 40px),repeating-linear-gradient(90deg,transparent,transparent 39px,#f3f4f6 39px,#f3f4f6 40px);background-size:40px 40px;border-radius:8px;" />
                                    <div class="bv-q-sig-actions">
                                        <button type="button" class="bv-q-sig-clear" style="display:inline-flex;">&#9998; <?php echo esc_html__( 'Clear & Re-sign', 'businessvance-services-manager' ); ?></button>
                                        <span class="bv-q-sig-hint" style="display:block;margin:0;flex:1;text-align:right;">&#10003; <?php echo esc_html__( 'Signature on file', 'businessvance-services-manager' ); ?></span>
                                    </div>
                                <?php else : ?>
                                    <canvas class="bv-q-signature-canvas" data-qid="<?php echo $qid; ?>" width="600" height="160"></canvas>
                                    <div class="bv-q-sig-actions">
                                        <button type="button" class="bv-q-sig-confirm">&#10003; <?php echo esc_html__( 'Confirm Signature', 'businessvance-services-manager' ); ?></button>
                                        <button type="button" class="bv-q-sig-clear">&#10005; <?php echo esc_html__( 'Clear', 'businessvance-services-manager' ); ?></button>
                                    </div>
                                    <p class="bv-q-sig-hint"><?php echo esc_html__( 'Draw your signature above using your mouse or finger', 'businessvance-services-manager' ); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php elseif ( $q->type === 'wysiwyg' ) : ?>
                            <?php wp_editor( $val, 'q_' . $qid, array( 'textarea_name' => 'q_' . $qid, 'textarea_rows' => 6, 'media_buttons' => false, 'teeny' => true, 'quicktags' => true ) ); ?>
                        <?php else : ?>
                            <input type="text" id="q_<?php echo $qid; ?>" name="q_<?php echo $qid; ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $q->placeholder ); ?>" <?php echo $req; ?> />
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    </div>
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

        // Update progress and notify consultant only if 100%
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d", $project_id ) );

        if ( $project->status === 'awaiting-documents' && $this->all_required_docs_uploaded( $project_id, $services ) ) {
            $wpdb->update( $wpdb->prefix . 'bv_projects',
                array( 'status' => 'in-progress' ),
                array( 'id' => $project_id ),
                array( '%s' ), array( '%d' )
            );
        }

        $new_progress = $this->update_project_progress( $project_id, $services );
        if ( $new_progress >= 100 ) {
            $this->notify_consultant( $project_id, esc_html__( 'All Client Information Received', 'businessvance-services-manager' ), sprintf(
                /* translators: %s: project number */
                esc_html__( 'Client has completed all required steps for project %s. All information has been submitted.', 'businessvance-services-manager' ),
                $project->project_number
            ) );
            $this->notify_client_completion( $project_id );
        }

        wp_send_json_success( esc_html__( 'Document uploaded successfully', 'businessvance-services-manager' ) );
    }

    /**
     * Handle multiple file upload for the multifile questionnaire field type.
     * Files are uploaded to the BV upload directory and metadata is returned as JSON.
     *
     * @since 2.7.19
     * @return void
     */
    public function ajax_upload_multifile() {
        check_ajax_referer( 'bv_portal_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( esc_html__( 'Not logged in', 'businessvance-services-manager' ) );

        $project_id = absint( $_POST['project_id'] ?? 0 );
        if ( ! $project_id ) wp_send_json_error( esc_html__( 'Missing project ID.', 'businessvance-services-manager' ) );
        $project = $this->verify_project_access( $project_id );
        if ( ! $project ) wp_send_json_error( esc_html__( 'Project not found or access denied.', 'businessvance-services-manager' ) );

        if ( empty( $_FILES['files'] ) ) wp_send_json_error( esc_html__( 'No files uploaded.', 'businessvance-services-manager' ) );

        $upload_dir  = wp_upload_dir();
        $upload_base = $upload_dir['basedir'] . '/bv-documents';
        $upload_url  = $upload_dir['baseurl'] . '/bv-documents';
        if ( ! file_exists( $upload_base ) ) wp_mkdir_p( $upload_base );

        $files    = $_FILES['files'];
        $uploaded = array();
        $allowed  = array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'csv', 'txt', 'zip', 'ppt', 'pptx' );

        // Handle single file or multiple
        $file_names = is_array( $files['name'] ) ? $files['name'] : array( $files['name'] );
        $file_tmps  = is_array( $files['tmp_name'] ) ? $files['tmp_name'] : array( $files['tmp_name'] );
        $file_sizes = is_array( $files['size'] ) ? $files['size'] : array( $files['size'] );

        for ( $i = 0; $i < count( $file_names ); $i++ ) {
            $name = sanitize_file_name( $file_names[ $i ] );
            $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

            if ( ! in_array( $ext, $allowed, true ) ) {
                $uploaded[] = array( 'name' => $name, 'error' => 'File type .' . esc_html( $ext ) . ' not allowed.' );
                continue;
            }

            if ( $file_sizes[ $i ] > 10 * 1024 * 1024 ) {
                $uploaded[] = array( 'name' => $name, 'error' => 'File exceeds 10MB limit.' );
                continue;
            }

            $filename   = $project_id . '_mf_' . time() . '_' . $i . '_' . $name;
            $upload_path = $upload_base . '/' . $filename;

            if ( ! move_uploaded_file( $file_tmps[ $i ], $upload_path ) ) {
                $uploaded[] = array( 'name' => $name, 'error' => 'Upload failed.' );
                continue;
            }

            $uploaded[] = array(
                'name' => $name,
                'file' => $filename,
                'size' => size_format( $file_sizes[ $i ] ),
                'url'  => $upload_url . '/' . $filename,
            );
        }

        wp_send_json_success( $uploaded );
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
                array( 'status' => $next_status ),
                array( 'id' => $project_id ),
                array( '%s' ), array( '%d' )
            );
        }

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'questionnaire', 'entity_id' => $project_id,
            'action' => 'submitted', 'description' => esc_html__( 'Client submitted questionnaire responses', 'businessvance-services-manager' ), 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        // Update progress and notify consultant only if 100%
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}bv_project_services ps JOIN {$wpdb->prefix}bv_services s ON ps.service_id = s.id WHERE ps.project_id = %d",
            $project_id
        ) );
        $new_progress = $this->update_project_progress( $project_id, $services );
        if ( $new_progress >= 100 ) {
            $this->notify_consultant( $project_id, esc_html__( 'All Client Information Received', 'businessvance-services-manager' ), sprintf(
                /* translators: %s: project number */
                esc_html__( 'Client has completed all required steps for project %s. All information has been submitted.', 'businessvance-services-manager' ),
                $project->project_number
            ) );
            $this->notify_client_completion( $project_id );
        }
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
            array( 'status' => $next_status ),
            array( 'id' => $project_id ),
            array( '%s' ), array( '%d' )
        );

        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id' => $project_id, 'entity_type' => 'agreement', 'entity_id' => $wpdb->insert_id,
            'action' => 'signed', 'description' => sprintf(
                /* translators: %s: signer full name */
                esc_html__( 'Agreement signed by %s', 'businessvance-services-manager' ),
                $full_name
            ), 'user_id' => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );

        // Update progress and notify consultant only if 100%
        $new_progress = $this->update_project_progress( $project_id, $services );
        if ( $new_progress >= 100 ) {
            $this->notify_consultant( $project_id, esc_html__( 'All Client Information Received', 'businessvance-services-manager' ), sprintf(
                /* translators: %1$s: client name, %2$s: project number */
                esc_html__( 'Client %1$s signed the service agreement for project %2$s. All required information has been submitted.', 'businessvance-services-manager' ),
                $full_name,
                $project->project_number
            ) );
            $this->notify_client_completion( $project_id );
        }
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

        // Notify consultant via email
        $this->notify_consultant_new_message( $project_id, $user->display_name, $message );

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

        // Validate MIME type against whitelist
        $allowed_mime_types = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'application/zip', 'application/octet-stream' );
        $mime = in_array( $report->mime_type, $allowed_mime_types, true ) ? $report->mime_type : 'application/octet-stream';
        header( 'Content-Type: ' . $mime );
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

    /**
     * Notify consultant of a client action (document uploaded, questionnaire submitted, agreement signed).
     *
     * Respects the cd_auto_notify_consultant setting and uses customizable subject/body templates
     * from settings. Falls back to sensible defaults if no custom template is configured.
     *
     * @since 2.6.0
     * @param int    $project_id
     * @param string $action      Human-readable action label (e.g. "Document Uploaded").
     * @param string $description Action details.
     * @return void
     */
    private function notify_consultant( $project_id, $action, $description ) {
        $settings = BV_Settings::get_settings();

        // Respect the master "notify consultant" toggle.
        if ( ( $settings['cd_auto_notify_consultant'] ?? 'yes' ) !== 'yes' ) {
            return;
        }

        $consultant_email = $settings['consultant_email'] ?? get_option( 'admin_email' );
        if ( empty( $consultant_email ) ) {
            return;
        }

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id ) );
        if ( ! $project ) {
            return;
        }

        $company_name  = $settings['company_name'] ?? 'BusinessVance';
        $dashboard_url = admin_url( 'admin.php?page=bv-consultant-dashboard&project_id=' . $project_id );

        // Token map for template replacement.
        $tokens = array(
            '{project_number}' => $project->project_number,
            '{action}'         => $action,
            '{description}'    => $description,
            '{client_name}'    => $project->client_name,
            '{client_email}'   => $project->client_email,
            '{dashboard_url}'  => $dashboard_url,
            '{company_name}'   => $company_name,
        );

        // Build subject — use custom template or sensible default.
        $subject_template = $settings['email_consultant_action_subject'] ?? 'Client Action on {project_number} — {action}';
        $subject = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject_template );

        // Build body — use custom template or sensible default.
        $body_template = $settings['email_consultant_action_body'] ?? '';
        if ( empty( $body_template ) ) {
            $body_template = "Dear Consultant,\n\n"
                . "A client has completed an action on project {project_number}:\n\n"
                . "Action: {action}\n"
                . "Details: {description}\n"
                . "Client: {client_name} ({client_email})\n\n"
                . "Please review and take necessary action in the Consultant Dashboard:\n"
                . "{dashboard_url}\n\n"
                . "Best regards,\n{company_name} System";
        }
        $body = str_replace( array_keys( $tokens ), array_values( $tokens ), $body_template );

        // Use the company's email address as the From header (not the consultant's own address).
        $from_email = ! empty( $settings['email_address'] ) ? $settings['email_address'] : $consultant_email;
        $headers    = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $company_name . ' <' . $from_email . '>',
        );

        wp_mail( $consultant_email, $subject, $body, $headers );
    }

    /**
     * Notify consultant when a client sends a new message.
     *
     * @since 2.6.0
     * @param int    $project_id
     * @param string $sender_name
     * @param string $message
     * @return void
     */
    private function notify_consultant_new_message( $project_id, $sender_name, $message ) {
        $settings = BV_Settings::get_settings();
        if ( ( $settings['email_message_to_consultant'] ?? 'yes' ) !== 'yes' ) {
            return;
        }

        $consultant_email = $settings['consultant_email'] ?? get_option( 'admin_email' );
        if ( empty( $consultant_email ) ) return;

        global $wpdb;
        $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bv_projects WHERE id = %d", $project_id ) );
        if ( ! $project ) return;

        $company_name  = $settings['company_name'] ?? 'BusinessVance';
        $dashboard_url = admin_url( 'admin.php?page=bv-consultant-dashboard&project_id=' . $project_id );

        // Build subject
        $subject = $settings['email_message_to_consultant_subject'] ?? 'New Client Message - {project_number}';
        $subject = str_replace(
            array( '{project_number}', '{sender_name}' ),
            array( $project->project_number, $sender_name ),
            $subject
        );

        // Build body
        $body = $settings['email_message_to_consultant_body'] ?? '';
        $body = str_replace(
            array( '{sender_name}', '{project_number}', '{message}', '{dashboard_url}', '{company_name}' ),
            array( $sender_name, $project->project_number, $message, $dashboard_url, $company_name ),
            $body
        );

        $from_email = ! empty( $settings['email_address'] ) ? $settings['email_address'] : $consultant_email;
        $headers    = array( 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $company_name . ' <' . $from_email . '>' );
        wp_mail( $consultant_email, $subject, $body, $headers );

        // Also log activity
        $wpdb->insert( $wpdb->prefix . 'bv_activity_log', array(
            'project_id'  => $project_id,
            'entity_type' => 'project',
            'entity_id'   => $project_id,
            'action'      => 'message_sent',
            'description' => 'Client message notification sent to consultant',
            'user_id'     => get_current_user_id(),
        ), array( '%d', '%s', '%d', '%s', '%s', '%d' ) );
    }

    /**
     * Output a small inline <style> block that defines CSS custom properties
     * from the user's color settings. The bulk of the CSS lives in the
     * external client-portal.css file (browsable, cacheable, minifiable).
     *
     * @since 2.7.0
     * @return string
     */
    private function get_inline_css() {
        $settings   = BV_Settings::get_settings();
        $primary    = esc_attr( $settings['primary_color'] );
        $secondary  = esc_attr( $settings['secondary_color'] );
        $accent     = esc_attr( $settings['accent_color'] );
        $portal_hdr = esc_attr( $settings['portal_header_color'] ?? $primary );
        $portal_acn = esc_attr( $settings['portal_accent_color'] ?? $secondary );
        $portal_btn = esc_attr( $settings['portal_button_color'] ?? $accent );

        return ':root{'
            . '--bv-primary:' . $portal_hdr . ';'
            . '--bv-secondary:' . $portal_acn . ';'
            . '--bv-accent:' . $portal_btn . ';'
            . '--bv-primary-light:' . $this->adjust_color( $portal_hdr, 25 ) . ';'
            . '--bv-primary-light-20:' . $this->adjust_color( $portal_hdr, 20 ) . ';'
            . '}';
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
