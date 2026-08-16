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
        add_action( 'wp_ajax_bv_qt_move_question', array( $this, 'ajax_move_question' ) );
        add_action( 'wp_ajax_bv_qt_import_questionnaires', array( $this, 'ajax_import_questionnaires' ) );
        add_action( 'wp_ajax_bv_qt_import_json', array( $this, 'ajax_import_json' ) );
        add_action( 'wp_ajax_bv_qt_export_json', array( $this, 'ajax_export_json' ) );
        add_action( 'wp_ajax_bv_qt_parse_document', array( $this, 'ajax_parse_document' ) );
        add_action( 'wp_ajax_bv_qt_import_document', array( $this, 'ajax_import_document' ) );
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
            array( 'jquery', 'jquery-ui-sortable' ),
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
            error_log( 'BV Questionnaire Admin Error: ' . $wpdb->last_error );
            wp_send_json_error( array(
                'message' => 'Database error occurred',
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
            error_log( 'BV Questionnaire Admin Error: ' . $wpdb->last_error );
            wp_send_json_error( array( 'message' => 'Database error occurred' ) );
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
            $placeholders = implode( ',', array_fill( 0, count( $section_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$t['questions']} WHERE section_id IN ({$placeholders})",
                $section_ids
            ) );
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
            error_log( 'BV Questionnaire Admin Error: ' . $wpdb->last_error );
            wp_send_json_error( array( 'message' => 'Database error occurred' ) );
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
            'url', 'time', 'range', 'color', 'address', 'wysiwyg', 'rating', 'repeatable',
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
        } elseif ( $type === 'range' && ! empty( $options_text ) ) {
            // Range: parse as min\nmax\nstep (values only, stored as simple string array)
            $parts = array_map( 'trim', explode( "\n", $options_text ) );
            $parts = array_filter( $parts, function( $p ) { return $p !== ''; });
            $options = array_values( $parts );
        } elseif ( $type === 'rating' && ! empty( $options_text ) ) {
            // Rating: parse as stars count (single value, stored as simple string array)
            $options = array( trim( $options_text ) );
        } elseif ( $type === 'repeatable' && ! empty( $options_text ) ) {
            // Repeatable table: parse as type|ColumnName pairs (one per line)
            // type is the column input type, ColumnName is the header label
            $lines = explode( "\n", $options_text );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( '' === $line ) {
                    continue;
                }
                if ( strpos( $line, '|' ) !== false ) {
                    $parts = explode( '|', $line, 2 );
                    $options[] = array(
                        'value' => sanitize_text_field( trim( $parts[0] ) ), // column type
                        'label' => sanitize_text_field( trim( $parts[1] ) ), // column name
                    );
                } else {
                    $options[] = array(
                        'value' => 'text',
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
            error_log( 'BV Questionnaire Admin Error: ' . $wpdb->last_error );
            wp_send_json_error( array( 'message' => 'Database error occurred' ) );
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

    /**
     * AJAX: Move a question to a different section.
     *
     * POST fields: question_id (int), new_section_id (int)
     */
    public function ajax_move_question() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        $question_id   = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0;
        $new_section_id = isset( $_POST['new_section_id'] ) ? absint( $_POST['new_section_id'] ) : 0;

        if ( ! $question_id || ! $new_section_id ) {
            wp_send_json_error( array( 'message' => 'Missing question_id or new_section_id.' ) );
        }

        // Verify the question exists.
        $question = $wpdb->get_row( $wpdb->prepare( "SELECT id, section_id FROM {$t['questions']} WHERE id = %d", $question_id ) );
        if ( ! $question ) {
            wp_send_json_error( array( 'message' => 'Question not found.' ) );
        }

        // Verify the destination section exists.
        $section = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$t['sections']} WHERE id = %d", $new_section_id ) );
        if ( ! $section ) {
            wp_send_json_error( array( 'message' => 'Destination section not found.' ) );
        }

        // Determine the display_order for the question in the new section (append at end).
        $max_order = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COALESCE(MAX(display_order), -1) FROM {$t['questions']} WHERE section_id = %d", $new_section_id )
        );

        $updated = $wpdb->update(
            $t['questions'],
            array(
                'section_id'    => $new_section_id,
                'display_order' => $max_order + 1,
            ),
            array( 'id' => $question_id ),
            array( '%d', '%d' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            wp_send_json_error( array( 'message' => 'Database error moving question.' ) );
        }

        wp_send_json_success( array( 'message' => 'Question moved successfully.' ) );
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
                    <button type="button" id="bv-qt-import-doc-btn" class="button button-primary" style="margin-left:8px;background:#D4AF37;border-color:#c4a030;color:#fff;">
                        <span class="dashicons dashicons-media-document" style="margin-top:4px;margin-right:3px;"></span>
                        Import from PDF/Word
                    </button>
                    <input type="file" id="bv-qt-doc-file" accept=".pdf,.docx" style="display:none;" />
                    <p style="margin:8px 0 0 0;color:#646970;font-size:12px;">
                        <span class="dashicons dashicons-info" style="font-size:16px;vertical-align:middle;margin-right:2px;color:#D4AF37;"></span>
                        Upload a PDF or Word (.docx) questionnaire - the system will auto-detect sections and questions for your review.
                    </p>
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
                <div class="bv-qt-qform">
                    <h4 id="bv-qt-question-form-title">New Question</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="bv-qt-q-type">Type</label></th>
                            <td>
                                <select id="bv-qt-q-type" class="bv-qt-q-type-select">
                                    <optgroup label="Basic Inputs">
                                    <option value="text">Text</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="number">Number</option>
                                    <option value="email">Email</option>
                                    <option value="phone">Phone</option>
                                    <option value="url">URL</option>
                                    <option value="date">Date</option>
                                    <option value="time">Time</option>
                                    </optgroup>
                                    <optgroup label="Selection">
                                    <option value="select">Select (Dropdown)</option>
                                    <option value="radio">Radio Buttons</option>
                                    <option value="checkbox">Checkboxes</option>
                                    </optgroup>
                                    <optgroup label="Specialized">
                                    <option value="range">Range / Slider</option>
                                    <option value="color">Color Picker</option>
                                    <option value="rating">Star Rating</option>
                                    <option value="address">Address Block</option>
                                    <option value="repeatable">Repeatable Table</option>
                                    </optgroup>
                                    <optgroup label="Rich Content">
                                    <option value="wysiwyg">Rich Text Editor</option>
                                    <option value="file">File Upload</option>
                                    </optgroup>
                                    <optgroup label="Display Only">
                                    <option value="heading">Heading</option>
                                    <option value="paragraph">Paragraph</option>
                                    </optgroup>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bv-qt-q-label">Label <span style="color:#a00;">*</span></label></th>
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
                            <th scope="row"><label>Required</label></th>
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
                                <label>Options</label>
                                <p class="description bv-qt-opts-desc" style="margin:6px 0 0;font-weight:400;">Type labels one per line — values auto-generate.<br><code>value|Label</code> for custom values.</p>
                            </th>
                            <td>
                                <div class="bv-qt-opts-presets" id="bv-qt-q-opts-presets">
                                    <span class="bv-qt-opts-presets-label">Quick fill:</span>
                                    <button type="button" class="button button-small bv-qt-preset-btn" data-preset="yes_no">Yes / No</button>
                                    <button type="button" class="button button-small bv-qt-preset-btn" data-preset="yes_no_other">Yes / No / Other</button>
                                    <button type="button" class="button button-small bv-qt-preset-btn" data-preset="true_false">True / False</button>
                                    <button type="button" class="button button-small bv-qt-preset-btn" data-preset="agree5">Likert 5</button>
                                    <button type="button" class="button button-small bv-qt-preset-btn" data-preset="satisfaction">Satisfaction</button>
                                    <button type="button" class="button button-small bv-qt-preset-btn" data-preset="rating5">Rating 1-5</button>
                                    <button type="button" class="button button-small bv-qt-preset-btn" data-preset="rating10">Rating 1-10</button>
                                </div>
                                <textarea id="bv-qt-q-options" rows="4" class="large-text" placeholder="Option Label&#10;Another Option&#10;Third Option"></textarea>
                            </td>
                        </tr>
                        <!-- Repeatable Table Column Builder -->
                        <tr id="bv-qt-q-cols-row" style="display:none;">
                            <th scope="row">
                                <label>Table Columns</label>
                                <p class="description" style="margin:6px 0 0;font-weight:400;">Define the columns for the repeatable table. Users can add/remove rows when filling out the form.</p>
                            </th>
                            <td>
                                <div class="bv-qt-col-builder" id="bv-qt-q-col-builder">
                                    <div class="bv-qt-col-presets-row">
                                        <span class="bv-qt-opts-presets-label">Presets:</span>
                                        <button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="contact">Contact Info</button>
                                        <button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="line_items">Line Items</button>
                                        <button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="references">References</button>
                                        <button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="address_book">Address Book</button>
                                        <button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="education">Education</button>
                                    </div>
                                    <div class="bv-qt-col-list" id="bv-qt-q-col-list">
                                        <!-- Columns rendered by JS -->
                                    </div>
                                    <button type="button" class="button button-small bv-qt-col-add-btn" id="bv-qt-q-col-add">
                                        <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;margin-right:3px;font-size:14px;"></span>Add Column
                                    </button>
                                    <div class="bv-qt-table-preview" id="bv-qt-q-table-preview">
                                        <div class="bv-qt-table-preview-label">Preview:</div>
                                        <table>
                                            <thead id="bv-qt-q-preview-thead"><tr></tr></thead>
                                            <tbody id="bv-qt-q-preview-tbody"><tr></tr></tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!-- Range Settings -->
                        <tr id="bv-qt-q-range-row" style="display:none;">
                            <th scope="row">
                                <label>Range Settings</label>
                                <p class="description" style="margin:6px 0 0;font-weight:400;">Set the slider's minimum, maximum, and step values.</p>
                            </th>
                            <td>
                                <div class="bv-qt-range-inputs">
                                    <div class="bv-qt-range-field">
                                        <label for="bv-qt-q-range-min">Min</label>
                                        <input type="number" id="bv-qt-q-range-min" class="small-text" value="0" />
                                    </div>
                                    <div class="bv-qt-range-field">
                                        <label for="bv-qt-q-range-max">Max</label>
                                        <input type="number" id="bv-qt-q-range-max" class="small-text" value="100" />
                                    </div>
                                    <div class="bv-qt-range-field">
                                        <label for="bv-qt-q-range-step">Step</label>
                                        <input type="number" id="bv-qt-q-range-step" class="small-text" value="1" min="0.01" step="any" />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!-- Rating Settings -->
                        <tr id="bv-qt-q-rating-row" style="display:none;">
                            <th scope="row">
                                <label>Rating Settings</label>
                                <p class="description" style="margin:6px 0 0;font-weight:400;">Set the maximum number of stars.</p>
                            </th>
                            <td>
                                <div class="bv-qt-rating-inputs">
                                    <label for="bv-qt-q-rating-stars">Stars</label>
                                    <input type="number" id="bv-qt-q-rating-stars" class="small-text" value="5" min="1" max="10" />
                                    <span class="bv-qt-rating-preview" id="bv-qt-q-rating-preview">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div class="bv-qt-qform-actions">
                        <button type="button" class="bv-qt-save-q-btn button button-primary">Save Question</button>
                        <button type="button" class="bv-qt-cancel-q-btn button">Cancel</button>
                    </div>
                    <input type="hidden" class="bv-qt-q-edit-id" value="0" />
                    <input type="hidden" class="bv-qt-q-section-id" value="0" />
                </div>
            </div>

            <!-- ==================== DOCUMENT IMPORT MODAL ==================== -->
            <div id="bv-qt-doc-modal" class="bv-qt-modal-overlay" style="display:none;">
                <div class="bv-qt-modal" style="max-width:800px;">
                    <!-- Step 1: Upload -->
                    <div id="bv-qt-doc-step-upload" style="display:flex;">
                        <div class="bv-qt-modal-header">
                            <h2><span class="dashicons dashicons-media-document" style="margin-right:8px;"></span>Import from PDF/Word</h2>
                            <button type="button" class="bv-qt-modal-close button-link" onclick="bvQTDocCloseModal()">&times;</button>
                        </div>
                        <div class="bv-qt-modal-body" style="text-align:center;padding:60px 40px;">
                            <div id="bv-qt-doc-drop-zone" style="border:2px dashed #c3c4c7;border-radius:12px;padding:50px 30px;cursor:pointer;transition:all 0.2s;background:#fafafa;">
                                <span class="dashicons dashicons-upload" style="font-size:48px;width:48px;height:48px;color:#8c8f94;"></span>
                                <p style="margin-top:16px;font-size:16px;font-weight:600;color:#1d2327;">
                                    Drop your file here or click to browse
                                </p>
                                <p style="margin-top:8px;color:#646970;font-size:13px;">
                                    Accepts PDF and Word (.docx) files up to 10MB
                                </p>
                            </div>
                            <input type="file" id="bv-qt-doc-upload-input" accept=".pdf,.docx" style="display:none;" />
                        </div>
                    </div>

                    <!-- Step 2: Parsing -->
                    <div id="bv-qt-doc-step-parsing" style="display:none;">
                        <div class="bv-qt-modal-header">
                            <h2><span class="dashicons dashicons-media-document" style="margin-right:8px;"></span>Parsing Document</h2>
                        </div>
                        <div class="bv-qt-modal-body" style="text-align:center;padding:60px 40px;">
                            <span class="spinner is-active" style="float:none;width:30px;height:30px;"></span>
                            <p style="margin-top:20px;font-size:16px;color:#1d2327;">Analyzing document structure...</p>
                            <p style="margin-top:8px;color:#646970;font-size:13px;">Detecting sections, questions, and question types</p>
                        </div>
                    </div>

                    <!-- Step 3: Review & Confirm -->
                    <div id="bv-qt-doc-step-review" style="display:none;">
                        <div class="bv-qt-modal-header">
                            <h2><span class="dashicons dashicons-yes-alt" style="margin-right:8px;color:#00a32a;"></span>Review & Import</h2>
                            <button type="button" class="bv-qt-modal-close button-link" onclick="bvQTDocCloseModal()">&times;</button>
                        </div>
                        <div class="bv-qt-modal-body" style="padding:20px;">
                            <!-- Template details -->
                            <div style="margin-bottom:16px;">
                                <label for="bv-qt-doc-name" style="font-weight:600;">Template Name</label>
                                <input type="text" id="bv-qt-doc-name" class="large-text" style="margin-top:4px;" />
                                <p style="margin-top:4px;">
                                    <label for="bv-qt-doc-desc" style="font-weight:600;font-size:13px;">Description</label>
                                    <textarea id="bv-qt-doc-desc" rows="2" class="large-text" style="margin-top:4px;font-size:13px;"></textarea>
                                </p>
                            </div>

                            <!-- Stats -->
                            <div id="bv-qt-doc-stats" style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;"></div>

                            <!-- Sections & Questions -->
                            <div id="bv-qt-doc-sections"></div>

                            <!-- Actions -->
                            <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;padding-top:16px;border-top:1px solid #c3c4c7;">
                                <button type="button" class="button button-secondary" onclick="bvQTDocCloseModal()">Cancel</button>
                                <button type="button" id="bv-qt-doc-confirm-btn" class="button button-primary" style="background:#00a32a;border-color:#009525;">
                                    <span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span>
                                    Import Questionnaire
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    /* ==========================================================================
     * AJAX: Import questionnaire templates from a JSON file
     * ========================================================================== */

    /**
     * Allowed question types for JSON import validation.
     */
    private static $allowed_question_types = array(
        'text', 'textarea', 'number', 'email', 'phone', 'date',
        'select', 'radio', 'checkbox', 'heading', 'paragraph', 'file',
        'url', 'time', 'range', 'color', 'address', 'wysiwyg', 'rating', 'repeatable',
    );

    /**
     * Import questionnaire templates from a user-uploaded JSON file.
     *
     * Expected JSON format:
     * {
     *   "questionnaires": [
     *     {
     *       "name": "...", "slug": "...", "description": "...",
     *       "version": "1.0", "status": "published",
     *       "sections": [
     *         {
     *           "title": "...", "description": "...", "order": 1,
     *           "questions": [
     *             { "type": "text", "label": "...", "required": true, ... }
     *           ]
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @since 2.7.1
     */
    public function ajax_import_json() {
        $this->verify_nonce();

        set_time_limit( 300 );

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
        }

        $file = $_FILES['file'];

        // Validate file type
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( $ext !== 'json' ) {
            wp_send_json_error( array( 'message' => 'Only JSON files are accepted. Please upload a .json file.' ) );
        }

        // Validate file size (5MB max for JSON)
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => 'File is too large. Maximum import size is 5MB.' ) );
        }

        $json_content = file_get_contents( $file['tmp_name'] );
        $data = json_decode( $json_content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => 'Invalid JSON file: ' . json_last_error_msg() ) );
        }

        // Support both "questionnaires" and "templates" keys
        $questionnaires = array();
        if ( ! empty( $data['questionnaires'] ) && is_array( $data['questionnaires'] ) ) {
            $questionnaires = $data['questionnaires'];
        } elseif ( ! empty( $data['templates'] ) && is_array( $data['templates'] ) ) {
            $questionnaires = $data['templates'];
        } else {
            wp_send_json_error( array(
                'message' => 'Invalid format. JSON must contain a "questionnaires" array with template definitions.',
            ) );
        }

        global $wpdb;
        $t = $this->get_tables();
        $results = array(
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => 0,
            'details'  => array(),
        );

        foreach ( $questionnaires as $idx => $tpl_data ) {
            $result = $this->import_single_template( $wpdb, $t, $tpl_data, $idx + 1 );
            $results[ $result['status'] ]++;
            $results['details'][] = $result;
        }

        // Build summary message
        $message = "Import complete: {$results['imported']} imported, {$results['skipped']} skipped, {$results['errors']} errors.";
        wp_send_json_success( array(
            'message' => $message,
            'results' => $results,
        ) );
    }

    /**
     * Import a single template from parsed JSON data.
     *
     * @since 2.7.1
     * @param \wpdb  $wpdb     Database instance.
     * @param array  $t        Table names from get_tables().
     * @param array  $tpl_data Template data from JSON.
     * @param int    $index    Template index (for error messages).
     * @return array Result with status, message, counts.
     */
    private function import_single_template( $wpdb, $t, $tpl_data, $index ) {
        // Validate required fields
        if ( empty( $tpl_data['name'] ) ) {
            return array(
                'status'  => 'error',
                'message' => "Template #{$index}: Missing 'name' field.",
                'sections' => 0, 'questions' => 0,
            );
        }

        $name        = sanitize_text_field( wp_unslash( $tpl_data['name'] ) );
        $slug        = ! empty( $tpl_data['slug'] ) ? sanitize_title( wp_unslash( $tpl_data['slug'] ) ) : sanitize_title( $name );
        $description = ! empty( $tpl_data['description'] ) ? sanitize_textarea_field( wp_unslash( $tpl_data['description'] ) ) : '';
        $version     = ! empty( $tpl_data['version'] ) ? sanitize_text_field( $tpl_data['version'] ) : '1.0';
        $status      = ( ! empty( $tpl_data['status'] ) && in_array( $tpl_data['status'], array( 'draft', 'published' ), true ) )
                        ? $tpl_data['status'] : 'draft';
        $sections    = ! empty( $tpl_data['sections'] ) && is_array( $tpl_data['sections'] )
                        ? $tpl_data['sections'] : array();

        // Check if slug already exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$t['templates']} WHERE slug = %s",
            $slug
        ) );

        if ( $existing ) {
            return array(
                'status'   => 'skipped',
                'message'  => "Template #{$index} '{$name}' already exists (slug: {$slug}). Skipped.",
                'sections' => 0, 'questions' => 0,
            );
        }

        // Insert template
        $wpdb->insert( $t['templates'], array(
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'version'     => $version,
            'status'      => $status,
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );

        if ( ! $wpdb->insert_id ) {
            error_log( "BV JSON Import: Failed to insert template '{$name}': " . $wpdb->last_error );
            return array(
                'status'  => 'error',
                'message' => "Template #{$index} '{$name}': Database error on insert.",
                'sections' => 0, 'questions' => 0,
            );
        }

        $template_id = $wpdb->insert_id;
        $total_questions = 0;
        $section_count = 0;

        // Wrap in transaction
        $wpdb->query( 'START TRANSACTION' );

        foreach ( $sections as $sec_idx => $section ) {
            if ( empty( $section['title'] ) ) {
                continue;
            }

            $sec_title = sanitize_text_field( wp_unslash( $section['title'] ) );
            $sec_desc  = ! empty( $section['description'] ) ? sanitize_textarea_field( wp_unslash( $section['description'] ) ) : '';
            $sec_order = ! empty( $section['order'] ) ? absint( $section['order'] ) : ( $sec_idx + 1 );
            $questions = ! empty( $section['questions'] ) && is_array( $section['questions'] )
                            ? $section['questions'] : array();

            $wpdb->insert( $t['sections'], array(
                'template_id'   => $template_id,
                'title'         => $sec_title,
                'description'   => $sec_desc,
                'display_order' => $sec_order,
                'created_at'    => current_time( 'mysql' ),
            ), array( '%d', '%s', '%s', '%d', '%s' ) );

            if ( ! $wpdb->insert_id ) {
                error_log( "BV JSON Import: Failed to insert section '{$sec_title}': " . $wpdb->last_error );
                $wpdb->query( 'ROLLBACK' );
                $wpdb->delete( $t['templates'], array( 'id' => $template_id ) );
                return array(
                    'status'    => 'error',
                    'message'   => "Template #{$index} '{$name}': Failed to insert section '{$sec_title}'.",
                    'sections'  => $section_count, 'questions' => $total_questions,
                );
            }

            $section_id = $wpdb->insert_id;
            $section_count++;

            foreach ( $questions as $q_idx => $q ) {
                if ( empty( $q['label'] ) ) {
                    continue;
                }

                $q_type  = ! empty( $q['type'] ) ? sanitize_text_field( $q['type'] ) : 'text';
                $q_label = sanitize_text_field( wp_unslash( $q['label'] ) );

                // Validate question type
                if ( ! in_array( $q_type, self::$allowed_question_types, true ) ) {
                    $q_type = 'text';
                }

                $q_placeholder = ! empty( $q['placeholder'] ) ? sanitize_text_field( wp_unslash( $q['placeholder'] ) ) : '';
                $q_help_text   = ! empty( $q['help_text'] ) ? sanitize_text_field( wp_unslash( $q['help_text'] ) ) : '';
                $q_required    = ! empty( $q['required'] ) ? 1 : 0;

                // Process options
                $options = array();
                if ( ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
                    foreach ( $q['options'] as $opt ) {
                        if ( is_array( $opt ) ) {
                            $opt_val = ! empty( $opt['value'] ) ? sanitize_text_field( $opt['value'] ) : sanitize_title( $opt['label'] );
                            $opt_lbl = ! empty( $opt['label'] ) ? sanitize_text_field( $opt['label'] ) : $opt_val;
                            $options[] = array( 'value' => $opt_val, 'label' => $opt_lbl );
                        } elseif ( is_string( $opt ) ) {
                            $options[] = array( 'value' => sanitize_title( $opt ), 'label' => sanitize_text_field( $opt ) );
                        }
                    }
                }
                $options_json = wp_json_encode( $options );

                $wpdb->insert( $t['questions'], array(
                    'section_id'    => $section_id,
                    'type'          => $q_type,
                    'label'         => $q_label,
                    'placeholder'   => $q_placeholder,
                    'is_required'   => $q_required,
                    'options'       => $options_json,
                    'help_text'     => $q_help_text,
                    'display_order' => $q_idx + 1,
                ), array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d' ) );

                $total_questions++;
            }
        }

        $wpdb->query( 'COMMIT' );

        return array(
            'status'    => 'imported',
            'message'   => "Template #{$index} '{$name}': Imported successfully.",
            'sections'  => $section_count, 'questions' => $total_questions,
        );
    }

    /* ==========================================================================
     * AJAX: Export questionnaire templates as JSON
     * ========================================================================== */

    /**
     * Export questionnaire templates as a downloadable JSON file.
     *
     * Accepts optional 'template_ids' (comma-separated) to export specific templates.
     * If no IDs provided, exports all templates.
     *
     * @since 2.7.1
     */
    public function ajax_export_json() {
        $this->verify_nonce();

        global $wpdb;
        $t = $this->get_tables();

        // Check if tables exist
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$t['templates']}'" );
        if ( ! $table_exists ) {
            wp_send_json_error( array( 'message' => 'Questionnaire tables not found.' ) );
        }

        // Determine which templates to export
        $template_ids_param = isset( $_POST['template_ids'] ) ? sanitize_text_field( $_POST['template_ids'] ) : '';
        if ( $template_ids_param ) {
            $id_array = array_map( 'absint', explode( ',', $template_ids_param ) );
            $id_array = array_filter( $id_array );
            if ( empty( $id_array ) ) {
                wp_send_json_error( array( 'message' => 'No valid template IDs provided.' ) );
            }
            $placeholders = implode( ',', array_fill( 0, count( $id_array ), '%d' ) );
            $templates = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$t['templates']} WHERE id IN ({$placeholders}) ORDER BY id ASC",
                $id_array
            ) );
        } else {
            $templates = $wpdb->get_results( "SELECT * FROM {$t['templates']} ORDER BY id ASC" );
        }

        if ( ! $templates ) {
            wp_send_json_error( array( 'message' => 'No templates found to export.' ) );
        }

        $export = array( 'questionnaires' => array() );

        foreach ( $templates as $tpl ) {
            $sections = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$t['sections']} WHERE template_id = %d ORDER BY display_order ASC, id ASC",
                $tpl->id
            ) );

            $sec_data = array();
            if ( $sections ) {
                foreach ( $sections as $sec ) {
                    $questions = $wpdb->get_results( $wpdb->prepare(
                        "SELECT * FROM {$t['questions']} WHERE section_id = %d ORDER BY display_order ASC, id ASC",
                        $sec->id
                    ) );

                    $q_data = array();
                    if ( $questions ) {
                        foreach ( $questions as $q ) {
                            $q_opts = json_decode( $q->options, true );
                            $q_data[] = array(
                                'type'        => $q->type,
                                'label'       => $q->label,
                                'placeholder' => $q->placeholder,
                                'required'    => (bool) $q->is_required,
                                'help_text'   => $q->help_text,
                                'options'     => is_array( $q_opts ) ? $q_opts : array(),
                            );
                        }
                    }

                    $sec_data[] = array(
                        'title'       => $sec->title,
                        'description' => $sec->description,
                        'order'       => (int) $sec->display_order,
                        'questions'   => $q_data,
                    );
                }
            }

            $export['questionnaires'][] = array(
                'name'        => $tpl->name,
                'slug'        => $tpl->slug,
                'description' => $tpl->description,
                'version'     => $tpl->version,
                'status'      => $tpl->status,
                'sections'    => $sec_data,
            );
        }

        wp_send_json_success( array(
            'message'    => 'Export successful.',
            'data'       => $export,
            'filename'   => 'questionnaire-templates-' . date( 'Y-m-d' ) . '.json',
            'count'      => count( $export['questionnaires'] ),
        ) );
    }

    /* ==========================================================================
     * AJAX: Parse uploaded PDF/Word document for questionnaire preview
     * ========================================================================== */

    /**
     * Upload and parse a PDF or Word document, returning the detected structure
     * for user review before import.
     *
     * @since 2.7.1
     */
    public function ajax_parse_document() {
        $this->verify_nonce();

        set_time_limit( 120 );
        @ini_set( 'memory_limit', '256M' );

        // Log errors to uploads directory for debugging
        $debug_log = function( $msg ) {
            $log_dir = wp_upload_dir()['basedir'] . '/bv-documents';
            if ( ! is_dir( $log_dir ) ) {
                @mkdir( $log_dir, 0755, true );
            }
            @file_put_contents( $log_dir . '/parse-debug.log', date( 'Y-m-d H:i:s' ) . ' — ' . $msg . "\n", FILE_APPEND );
        };

        try {
            $debug_log( 'Starting parse. POST keys: ' . implode( ', ', array_keys( $_POST ) ) . ' FILES keys: ' . implode( ', ', array_keys( $_FILES ) ) );
            $debug_log( 'PHP version: ' . phpversion() . ' | Memory limit: ' . ini_get( 'memory_limit' ) . ' | Max execution: ' . ini_get( 'max_execution_time' ) );

            if ( empty( $_FILES['file'] ) ) {
                wp_send_json_error( array( 'message' => 'No file uploaded.' ) );
                return;
            }

            $file = $_FILES['file'];
            $debug_log( 'File: ' . $file['name'] . ' | Size: ' . $file['size'] . ' | Error: ' . $file['error'] );

            // Validate file type
            $allowed_ext = array( 'pdf', 'docx' );
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, $allowed_ext, true ) ) {
                wp_send_json_error( array( 'message' => 'Unsupported file type. Please upload a PDF or .docx file.' ) );
                return;
            }

            // Validate file size (10MB max)
            if ( $file['size'] > 10 * 1024 * 1024 ) {
                wp_send_json_error( array( 'message' => 'File is too large. Maximum upload size is 10MB.' ) );
                return;
            }

            // Check for upload errors
            if ( $file['error'] !== UPLOAD_ERR_OK ) {
                $error_messages = array(
                    UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the maximum upload size.',
                    UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form maximum size.',
                    UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                    UPLOAD_ERR_NO_FILE     => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR  => 'Missing temporary folder.',
                    UPLOAD_ERR_CANT_WRITE  => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION   => 'A PHP extension stopped the file upload.',
                );
                $error_msg = isset( $error_messages[ $file['error'] ] )
                    ? $error_messages[ $file['error'] ]
                    : 'Upload error occurred.';
                wp_send_json_error( array( 'message' => $error_msg ) );
                return;
            }

            // Load parser
            if ( ! class_exists( 'BV_Document_Parser' ) ) {
                $debug_log( 'Loading parser from: ' . BV_PLUGIN_DIR . 'includes/class-bv-document-parser.php' );
                require_once BV_PLUGIN_DIR . 'includes/class-bv-document-parser.php';
            }
            $debug_log( 'Parser class loaded. File exists: ' . ( file_exists( BV_PLUGIN_DIR . 'includes/class-bv-document-parser.php' ) ? 'yes' : 'no' ) );

            $parser  = new BV_Document_Parser();
            $debug_log( 'Parser instantiated, calling parse_file...' );
            $result  = $parser->parse_file( $file['tmp_name'], $file['name'] );
            $debug_log( 'Parse complete. Sections: ' . count( $result['sections'] ) . ' Questions: ' . $result['total_questions'] );

        } catch ( \Throwable $e ) {
            // Catch ALL errors including TypeError, Error, ParseError, etc.
            $err_class  = get_class( $e );
            $err_file   = $e->getFile();
            $err_line   = $e->getLine();
            $err_msg    = $e->getMessage();
            $php_ver    = phpversion();
            $debug_log( "ERROR: {$err_class}: {$err_msg} in {$err_file}:{$err_line} | PHP {$php_ver}" );

            // Build a user-friendly error message with diagnostics
            $user_msg = $err_msg;
            if ( $err_class === 'ParseError' ) {
                $user_msg .= sprintf(
                    ' (in %s line %d — PHP version %s; this plugin requires PHP 7.4+)',
                    basename( $err_file ),
                    $err_line,
                    $php_ver
                );
            }
            wp_send_json_error( array( 'message' => $user_msg ) );
            return;
        }

        // Sanitize the parsed data for safe output
        $result['name']        = sanitize_text_field( $result['name'] );
        $result['description'] = sanitize_textarea_field( $result['description'] );

        foreach ( $result['sections'] as &$section ) {
            $section['title']       = sanitize_text_field( $section['title'] );
            $section['description'] = sanitize_textarea_field( $section['description'] );
            foreach ( $section['questions'] as &$question ) {
                $question['label']       = sanitize_text_field( $question['label'] );
                $question['placeholder'] = sanitize_text_field( $question['placeholder'] );
                $question['help_text']   = sanitize_text_field( $question['help_text'] );
                // Ensure type is valid
                if ( ! in_array( $question['type'], self::$allowed_question_types, true ) ) {
                    $question['type'] = 'text';
                }
                // Sanitize options
                if ( ! empty( $question['options'] ) && is_array( $question['options'] ) ) {
                    $cleaned_opts = array();
                    foreach ( $question['options'] as $opt ) {
                        if ( is_array( $opt ) ) {
                            $cleaned_opts[] = array(
                                'value' => sanitize_title( $opt['value'] ),
                                'label' => sanitize_text_field( $opt['label'] ),
                            );
                        }
                    }
                    $question['options'] = $cleaned_opts;
                }
            }
            unset( $question );
        }
        unset( $section );

        wp_send_json_success( array(
            'message'    => 'Document parsed successfully.',
            'data'       => $result,
        ) );
    }

    /* ==========================================================================
     * AJAX: Import parsed document data into the database
     * ========================================================================== */

    /**
     * Receive confirmed/edited questionnaire data from the preview step
     * and import it as a template.
     *
     * @since 2.7.1
     */
    public function ajax_import_document() {
        $this->verify_nonce();

        set_time_limit( 300 );

        $raw_data = isset( $_POST['template_data'] ) ? wp_unslash( $_POST['template_data'] ) : '';
        if ( empty( $raw_data ) ) {
            wp_send_json_error( array( 'message' => 'No template data received.' ) );
        }

        $tpl_data = json_decode( $raw_data, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => 'Invalid template data format.' ) );
        }

        // Use existing import_single_template method
        global $wpdb;
        $t = $this->get_tables();

        $result = $this->import_single_template( $wpdb, $t, $tpl_data, 1 );

        if ( $result['status'] === 'imported' ) {
            wp_send_json_success( array(
                'message'      => 'Questionnaire imported successfully!',
                'template_id'  => $wpdb->insert_id,
                'sections'     => $result['sections'],
                'questions'    => $result['questions'],
            ) );
        } elseif ( $result['status'] === 'skipped' ) {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        } else {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }
    }

    /**
     * AJAX handler for importing pre-built questionnaire templates.
     *
     * @since 2.0.3
     */
    public function ajax_import_questionnaires() {
        $this->verify_nonce();

        // Allow enough time for 350+ row inserts
        set_time_limit( 300 );
        // Prevent output buffering issues on large AJAX responses
        @ini_set( 'display_errors', 0 );

        if ( ! class_exists( 'BV_Questionnaire_Import' ) ) {
            require_once BV_PLUGIN_DIR . 'includes/class-bv-questionnaire-import.php';
        }

        try {
            $results = BV_Questionnaire_Import::import_questionnaires();
        } catch ( \Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Import error: ' . $e->getMessage(),
            ) );
            return;
        }

        wp_send_json_success( array(
            'message' => 'Import complete.',
            'results' => $results,
        ) );
    }
}
