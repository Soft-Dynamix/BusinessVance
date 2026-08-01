<?php
/**
 * BusinessVance Questionnaire Admin
 *
 * Admin page for managing questionnaire templates, sections, and questions.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Questionnaire_Admin {

    /**
     * Constructor — register hooks
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_bv_qt_get_templates', array( $this, 'ajax_get_templates' ) );
        add_action( 'wp_ajax_bv_qt_get_template', array( $this, 'ajax_get_template' ) );
        add_action( 'wp_ajax_bv_qt_save_template', array( $this, 'ajax_save_template' ) );
        add_action( 'wp_ajax_bv_qt_delete_template', array( $this, 'ajax_delete_template' ) );
        add_action( 'wp_ajax_bv_qt_save_section', array( $this, 'ajax_save_section' ) );
        add_action( 'wp_ajax_bv_qt_delete_section', array( $this, 'ajax_delete_section' ) );
        add_action( 'wp_ajax_bv_qt_save_question', array( $this, 'ajax_save_question' ) );
        add_action( 'wp_ajax_bv_qt_delete_question', array( $this, 'ajax_delete_question' ) );
        add_action( 'wp_ajax_bv_qt_reorder', array( $this, 'ajax_reorder' ) );
        add_action( 'wp_ajax_bv_qt_import_questionnaires', array( $this, 'ajax_import_questionnaires' ) );
    }

    /**
     * Register the admin submenu page
     */
    public function register_menu() {
        add_submenu_page(
            'businessvance',
            'Questionnaires',
            'Questionnaires',
            'manage_options',
            'businessvance-questionnaires',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue scripts and styles for the questionnaire admin page
     *
     * @param string $hook
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'businessvance_page_businessvance-questionnaires' ) {
            return;
        }

        wp_enqueue_style(
            'bv-questionnaire-admin-css',
            BV_PLUGIN_URL . 'assets/css/questionnaire-admin.css',
            array(),
            BV_VERSION
        );

        wp_enqueue_script(
            'bv-questionnaire-admin-js',
            BV_PLUGIN_URL . 'assets/js/questionnaire-admin.js',
            array( 'jquery' ),
            BV_VERSION,
            true
        );

        wp_localize_script( 'bv-questionnaire-admin-js', 'bvQT', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'strings'  => array(
                'confirm_delete_template' => 'Are you sure you want to delete this template? All sections and questions will be permanently removed.',
                'confirm_delete_section'  => 'Are you sure you want to delete this section? All questions in it will be permanently removed.',
                'confirm_delete_question' => 'Are you sure you want to delete this question?',
                'saving'                  => 'Saving...',
                'saved'                   => 'Saved successfully.',
                'error'                   => 'An error occurred. Please try again.',
                'loading'                 => 'Loading...',
            ),
        ) );
    }

    /**
     * Verify nonce and capability
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
     * Get database table names
     *
     * @return array
     */
    private function get_tables() {
        global $wpdb;
        return array(
            'templates' => $wpdb->prefix . 'bv_questionnaire_templates',
            'sections'  => $wpdb->prefix . 'bv_questionnaire_sections',
            'questions' => $wpdb->prefix . 'bv_questionnaire_questions',
        );
    }

    /* ==========================================================================
     * AJAX: Get all templates
     * ========================================================================== */

    public function ajax_get_templates() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        // Check if templates table exists
        $table_exists = $wpdb->get_var(
            "SHOW TABLES LIKE '{$t['templates']}'"
        );

        if ( ! $table_exists ) {
            wp_send_json_error( array(
                'message' => 'Questionnaire database tables not found. Please deactivate and reactivate the plugin to create the required tables.',
            ) );
        }

        $results = $wpdb->get_results(
            "SELECT t.*, 
                (SELECT COUNT(*) FROM {$t['sections']} WHERE template_id = t.id) AS section_count,
                (SELECT COUNT(*) FROM {$t['questions']} qq 
                    JOIN {$t['sections']} s ON qq.section_id = s.id 
                    WHERE s.template_id = t.id) AS question_count 
            FROM {$t['templates']} t 
            ORDER BY t.created_at DESC"
        );

        if ( $wpdb->last_error ) {
            wp_send_json_error( array(
                'message' => 'Database error: ' . $wpdb->last_error,
            ) );
        }

        wp_send_json_success( array( 'templates' => $results ? $results : array() ) );
    }

    /* ==========================================================================
     * AJAX: Get single template with all sections and questions
     * ========================================================================== */

    public function ajax_get_template() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $template_id = absint( $_POST['template_id'] );
        if ( ! $template_id ) {
            wp_send_json_error( array( 'message' => 'Invalid template ID.' ) );
        }

        $template = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t['templates']} WHERE id = %d",
            $template_id
        ) );

        if ( ! $template ) {
            wp_send_json_error( array( 'message' => 'Template not found.' ) );
        }

        $sections = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t['sections']} WHERE template_id = %d ORDER BY display_order ASC, id ASC",
            $template_id
        ) );

        if ( $sections ) {
            foreach ( $sections as $section ) {
                $questions = $wpdb->get_results( $wpdb->prepare(
                    "SELECT * FROM {$t['questions']} WHERE section_id = %d ORDER BY display_order ASC, id ASC",
                    $section->id
                ) );

                if ( $questions ) {
                    foreach ( $questions as $question ) {
                        $question->options = json_decode( $question->options, true );
                        if ( ! is_array( $question->options ) ) {
                            $question->options = array();
                        }
                    }
                } else {
                    $questions = array();
                }

                $section->questions = $questions;
            }
        } else {
            $sections = array();
        }

        $template->sections = $sections;

        wp_send_json_success( array( 'template' => $template ) );
    }

    /* ==========================================================================
     * AJAX: Save (create or update) template
     * ========================================================================== */

    public function ajax_save_template() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $slug        = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title( $name );
        $description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $status      = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'draft';

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Template name is required.' ) );
        }

        if ( ! in_array( $status, array( 'draft', 'published' ), true ) ) {
            $status = 'draft';
        }

        // Check for duplicate slug
        if ( $id ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['templates']} WHERE slug = %s AND id != %d",
                $slug,
                $id
            ) );
        } else {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$t['templates']} WHERE slug = %s",
                $slug
            ) );
        }

        if ( (int) $existing > 0 ) {
            wp_send_json_error( array( 'message' => 'A template with this slug already exists. Please use a different name.' ) );
        }

        $data = array(
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'status'      => $status,
            'updated_at'  => current_time( 'mysql' ),
        );
        $format = array( '%s', '%s', '%s', '%s', '%s' );

        if ( $id ) {
            $wpdb->update( $t['templates'], $data, array( 'id' => $id ), $format, array( '%d' ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $format[] = '%s';
            $wpdb->insert( $t['templates'], $data, $format );
            $id = $wpdb->insert_id;
        }

        if ( $wpdb->last_error ) {
            wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
        }

        wp_send_json_success( array(
            'template_id' => $id,
            'message'     => 'Template saved successfully.',
        ) );
    }

    /* ==========================================================================
     * AJAX: Delete template (cascade to sections and questions)
     * ========================================================================== */

    public function ajax_delete_template() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid template ID.' ) );
        }

        // Get section IDs for this template
        $section_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$t['sections']} WHERE template_id = %d",
            $id
        ) );

        // Delete all questions in those sections
        if ( ! empty( $section_ids ) ) {
            $section_id_list = implode( ',', array_map( 'absint', $section_ids ) );
            $wpdb->query( "DELETE FROM {$t['questions']} WHERE section_id IN ({$section_id_list})" );
        }

        // Delete all sections
        $wpdb->delete( $t['sections'], array( 'template_id' => $id ), array( '%d' ) );

        // Delete template
        $wpdb->delete( $t['templates'], array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => 'Template deleted successfully.' ) );
    }

    /* ==========================================================================
     * AJAX: Save (create or update) section
     * ========================================================================== */

    public function ajax_save_section() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
        $title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

        if ( ! $template_id ) {
            wp_send_json_error( array( 'message' => 'Template ID is required.' ) );
        }

        if ( empty( $title ) ) {
            wp_send_json_error( array( 'message' => 'Section title is required.' ) );
        }

        $data = array(
            'template_id' => $template_id,
            'title'       => $title,
            'description' => $description,
        );
        $format = array( '%d', '%s', '%s' );

        if ( $id ) {
            $wpdb->update( $t['sections'], $data, array( 'id' => $id ), $format, array( '%d' ) );
        } else {
            // Get the next display_order
            $max_order = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(MAX(display_order), -1) FROM {$t['sections']} WHERE template_id = %d",
                $template_id
            ) );
            $data['display_order'] = $max_order + 1;
            $format[] = '%d';
            $data['created_at'] = current_time( 'mysql' );
            $format[] = '%s';
            $wpdb->insert( $t['sections'], $data, $format );
            $id = $wpdb->insert_id;
        }

        if ( $wpdb->last_error ) {
            wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
        }

        $section = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t['sections']} WHERE id = %d",
            $id
        ) );

        wp_send_json_success( array(
            'section' => $section,
            'message' => 'Section saved successfully.',
        ) );
    }

    /* ==========================================================================
     * AJAX: Delete section (cascade to questions)
     * ========================================================================== */

    public function ajax_delete_section() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid section ID.' ) );
        }

        // Delete all questions in this section
        $wpdb->delete( $t['questions'], array( 'section_id' => $id ), array( '%d' ) );

        // Delete section
        $wpdb->delete( $t['sections'], array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => 'Section deleted successfully.' ) );
    }

    /* ==========================================================================
     * AJAX: Save (create or update) question
     * ========================================================================== */

    public function ajax_save_question() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $id           = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $section_id   = isset( $_POST['section_id'] ) ? absint( $_POST['section_id'] ) : 0;
        $type         = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'text';
        $label        = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
        $placeholder  = isset( $_POST['placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['placeholder'] ) ) : '';
        $is_required  = isset( $_POST['is_required'] ) ? (int) $_POST['is_required'] : 0;
        $help_text    = isset( $_POST['help_text'] ) ? sanitize_text_field( wp_unslash( $_POST['help_text'] ) ) : '';
        $options_text = isset( $_POST['options_text'] ) ? wp_unslash( $_POST['options_text'] ) : '';

        $allowed_types = array(
            'text', 'textarea', 'number', 'email', 'phone', 'date',
            'select', 'radio', 'checkbox', 'heading', 'paragraph', 'file',
        );

        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'text';
        }

        if ( ! $section_id ) {
            wp_send_json_error( array( 'message' => 'Section ID is required.' ) );
        }

        if ( empty( $label ) ) {
            wp_send_json_error( array( 'message' => 'Question label is required.' ) );
        }

        // Convert options_text to JSON array
        $options = array();
        if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) && ! empty( $options_text ) ) {
            $lines = explode( "\n", $options_text );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( '' === $line ) {
                    continue;
                }
                if ( strpos( $line, '|' ) !== false ) {
                    $parts = explode( '|', $line, 2 );
                    $options[] = array(
                        'value' => sanitize_text_field( trim( $parts[0] ) ),
                        'label' => sanitize_text_field( trim( $parts[1] ) ),
                    );
                } else {
                    $options[] = array(
                        'value' => sanitize_title( $line ),
                        'label' => sanitize_text_field( $line ),
                    );
                }
            }
        }

        $options_json = wp_json_encode( $options );

        $data = array(
            'section_id'  => $section_id,
            'type'        => $type,
            'label'       => $label,
            'placeholder' => $placeholder,
            'is_required' => $is_required,
            'options'     => $options_json,
            'help_text'   => $help_text,
        );
        $format = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' );

        if ( $id ) {
            $wpdb->update( $t['questions'], $data, array( 'id' => $id ), $format, array( '%d' ) );
        } else {
            // Get the next display_order
            $max_order = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(MAX(display_order), -1) FROM {$t['questions']} WHERE section_id = %d",
                $section_id
            ) );
            $data['display_order'] = $max_order + 1;
            $format[] = '%d';
            $data['created_at'] = current_time( 'mysql' );
            $format[] = '%s';
            $wpdb->insert( $t['questions'], $data, $format );
            $id = $wpdb->insert_id;
        }

        if ( $wpdb->last_error ) {
            wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
        }

        $question = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t['questions']} WHERE id = %d",
            $id
        ) );

        if ( $question ) {
            $question->options = json_decode( $question->options, true );
            if ( ! is_array( $question->options ) ) {
                $question->options = array();
            }
        }

        wp_send_json_success( array(
            'question' => $question,
            'message'  => 'Question saved successfully.',
        ) );
    }

    /* ==========================================================================
     * AJAX: Delete question
     * ========================================================================== */

    public function ajax_delete_question() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid question ID.' ) );
        }

        $wpdb->delete( $t['questions'], array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => 'Question deleted successfully.' ) );
    }

    /* ==========================================================================
     * AJAX: Reorder sections or questions
     * ========================================================================== */

    public function ajax_reorder() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $ids  = isset( $_POST['ids'] ) ? sanitize_text_field( $_POST['ids'] ) : '';

        if ( ! in_array( $type, array( 'section', 'question' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid reorder type.' ) );
        }

        $id_array = array_map( 'absint', explode( ',', $ids ) );
        $id_array = array_filter( $id_array );

        if ( empty( $id_array ) ) {
            wp_send_json_error( array( 'message' => 'No IDs provided.' ) );
        }

        if ( 'section' === $type ) {
            $table = $t['sections'];
        } else {
            $table = $t['questions'];
        }

        foreach ( $id_array as $order => $item_id ) {
            $wpdb->update(
                $table,
                array( 'display_order' => $order ),
                array( 'id' => $item_id ),
                array( '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success( array( 'message' => 'Order updated successfully.' ) );
    }

    /* ==========================================================================
     * Render the admin page
     * ========================================================================== */

    public function render_page() {
        ?>
        <div class="wrap bv-qt-wrap">
            <h1 class="bv-qt-page-title">
                <span class="dashicons dashicons-clipboard" style="font-size:28px;margin-right:8px;vertical-align:middle;"></span>
                Questionnaire Manager
            </h1>
            <p class="description" style="margin-bottom:20px;">
                Create and manage questionnaire templates. Each template can have multiple sections, and each section can contain various question types.
            </p>

            <div id="bv-qt-notices"></div>

            <!-- ==================== TEMPLATES LIST VIEW ==================== -->
            <div id="bv-qt-list-view">
                <div style="margin-bottom:15px;">
                    <button type="button" id="bv-qt-add-template-btn" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt2" style="margin-top:4px;margin-right:3px;"></span>
                        Add New Template
                    </button>
                    <button type="button" id="bv-qt-import-btn" class="button button-secondary" style="margin-left:8px;" onclick="bvQTImportQuestionnaires()">
                        <span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span>
                        Import Pre-built Questionnaires
                    </button>
                </div>

                <table class="wp-list-table widefat fixed striped" id="bv-qt-templates-table">
                    <thead>
                        <tr>
                            <th style="width:25%;">Name</th>
                            <th style="width:15%;">Slug</th>
                            <th style="width:10%;">Status</th>
                            <th style="width:8%;">Sections</th>
                            <th style="width:8%;">Questions</th>
                            <th style="width:12%;">Created</th>
                            <th style="width:22%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bv-qt-templates-body">
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px 0;">
                                <span class="spinner is-active" style="float:none;"></span>
                                <?php esc_html_e( 'Loading templates...', 'businessvance-services-manager' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ==================== EDIT/CREATE TEMPLATE VIEW ==================== -->
            <div id="bv-qt-edit-view" style="display:none;">
                <input type="hidden" id="bv-qt-edit-id" value="0" />
                <div style="margin-bottom:20px;">
                    <button type="button" id="bv-qt-back-to-list" class="button">
                        <span class="dashicons dashicons-arrow-left-alt2" style="margin-top:4px;margin-right:3px;"></span>
                        Back to Templates
                    </button>
                    <span id="bv-qt-edit-title" style="margin-left:15px;font-size:16px;font-weight:600;"></span>
                </div>

                <!-- Template Info Card -->
                <div class="bv-qt-card" style="margin-bottom:20px;">
                    <h2 class="bv-qt-card-title">Template Details</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bv-qt-tpl-name">Name <span class="description">(required)</span></label></th>
                            <td>
                                <input type="text" id="bv-qt-tpl-name" class="regular-text" placeholder="e.g. Client Onboarding Questionnaire" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bv-qt-tpl-slug">Slug</label></th>
                            <td>
                                <input type="text" id="bv-qt-tpl-slug" class="regular-text" />
                                <p class="description">Auto-generated from name. Used for shortcode/reference.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bv-qt-tpl-description">Description</label></th>
                            <td>
                                <textarea id="bv-qt-tpl-description" rows="3" class="large-text" placeholder="Optional description of this questionnaire template..."></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bv-qt-tpl-status">Status</label></th>
                            <td>
                                <select id="bv-qt-tpl-status">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:15px;">
                        <button type="button" id="bv-qt-save-tpl-btn" class="button button-primary">
                            <span class="dashicons dashicons-saved" style="margin-top:4px;margin-right:3px;"></span>
                            Save Template
                        </button>
                    </p>
                </div>

                <!-- Sections Panel -->
                <div id="bv-qt-sections-panel" style="display:none;">
                    <div style="margin-bottom:15px; display:flex; align-items:center; justify-content:space-between;">
                        <h2 style="margin:0;"><span class="dashicons dashicons-editor-ol" style="margin-right:5px;vertical-align:middle;"></span> Sections</h2>
                        <button type="button" id="bv-qt-add-section-btn" class="button">
                            <span class="dashicons dashicons-plus-alt2" style="margin-top:4px;margin-right:3px;"></span>
                            Add Section
                        </button>
                    </div>

                    <!-- Add Section Inline Form -->
                    <div id="bv-qt-section-form-wrap" class="bv-qt-card bv-qt-inline-form" style="display:none;margin-bottom:20px;">
                        <h3 class="bv-qt-card-title" id="bv-qt-section-form-title">New Section</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="bv-qt-sec-title">Title <span class="description">(required)</span></label></th>
                                <td>
                                    <input type="text" id="bv-qt-sec-title" class="regular-text" placeholder="e.g. Personal Information" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="bv-qt-sec-description">Description</label></th>
                                <td>
                                    <textarea id="bv-qt-sec-description" rows="2" class="large-text" placeholder="Optional section description..."></textarea>
                                </td>
                            </tr>
                        </table>
                        <p style="margin-top:10px;">
                            <button type="button" id="bv-qt-save-sec-btn" class="button button-primary">Save Section</button>
                            <button type="button" id="bv-qt-cancel-sec-btn" class="button button-secondary">Cancel</button>
                        </p>
                        <input type="hidden" id="bv-qt-sec-edit-id" value="0" />
                    </div>

                    <!-- Sections List -->
                    <div id="bv-qt-sections-list"></div>
                </div>
            </div>

            <!-- ==================== QUESTION INLINE FORM (reused via JS) ==================== -->
            <div id="bv-qt-question-form-template" style="display:none;">
                <div class="bv-qt-card bv-qt-inline-form bv-qt-question-form">
                    <h3 class="bv-qt-card-title" id="bv-qt-question-form-title">New Question</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bv-qt-q-type">Type</label></th>
                            <td>
                                <select id="bv-qt-q-type" class="bv-qt-q-type-select">
                                    <option value="text">Text</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="number">Number</option>
                                    <option value="email">Email</option>
                                    <option value="phone">Phone</option>
                                    <option value="date">Date</option>
                                    <option value="select">Select (Dropdown)</option>
                                    <option value="radio">Radio Buttons</option>
                                    <option value="checkbox">Checkboxes</option>
                                    <option value="heading">Heading (display only)</option>
                                    <option value="paragraph">Paragraph (display only)</option>
                                    <option value="file">File Upload</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bv-qt-q-label">Label <span class="description">(required)</span></label></th>
                            <td>
                                <input type="text" id="bv-qt-q-label" class="large-text" placeholder="Question label" />
                            </td>
                        </tr>
                        <tr id="bv-qt-q-placeholder-row">
                            <th scope="row"><label for="bv-qt-q-placeholder">Placeholder</label></th>
                            <td>
                                <input type="text" id="bv-qt-q-placeholder" class="large-text" placeholder="Placeholder text (optional)" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bv-qt-q-required">Required</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="bv-qt-q-required" value="1" />
                                    This question is required
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bv-qt-q-help-text">Help Text</label></th>
                            <td>
                                <input type="text" id="bv-qt-q-help-text" class="large-text" placeholder="Optional help text shown below the field" />
                            </td>
                        </tr>
                        <tr id="bv-qt-q-options-row" style="display:none;">
                            <th scope="row">
                                <label for="bv-qt-q-options">Options</label>
                                <p class="description">One per line. Format: <code>value|Label</code></p>
                            </th>
                            <td>
                                <textarea id="bv-qt-q-options" rows="5" class="large-text" placeholder="option_value|Option Label&#10;another_value|Another Label"></textarea>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:10px;">
                        <button type="button" class="bv-qt-save-q-btn button button-primary">Save Question</button>
                        <button type="button" class="bv-qt-cancel-q-btn button button-secondary">Cancel</button>
                    </p>
                    <input type="hidden" class="bv-qt-q-edit-id" value="0" />
                    <input type="hidden" class="bv-qt-q-section-id" value="0" />
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * AJAX handler for importing pre-built questionnaire templates.
     *
     * @since 2.0.3
     */
    public function ajax_import_questionnaires() {
        $this->verify_nonce();

        if ( ! class_exists( 'BV_Questionnaire_Import' ) ) {
            require_once BV_PLUGIN_DIR . 'includes/class-bv-questionnaire-import.php';
        }

        $results = BV_Questionnaire_Import::import_questionnaires();

        wp_send_json_success( array(
            'message' => 'Import complete.',
            'results' => $results,
        ) );
    }
}
