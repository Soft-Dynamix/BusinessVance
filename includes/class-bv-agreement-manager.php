<?php
/**
 * BusinessVance Agreement Manager
 *
 * Admin page for managing agreement and NDA templates.
 * Supports creating, editing, deleting, and importing from PDF files.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Agreement_Manager {

    /**
     * Constructor — register hooks
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_bv_get_agreement_template', array( $this, 'ajax_get_template' ) );
        add_action( 'wp_ajax_bv_save_agreement_template', array( $this, 'ajax_save_template' ) );
        add_action( 'wp_ajax_bv_delete_agreement_template', array( $this, 'ajax_delete_template' ) );
        add_action( 'wp_ajax_bv_set_default_agreement', array( $this, 'ajax_set_default' ) );
        add_action( 'wp_ajax_bv_import_agreement_pdf', array( $this, 'ajax_import_pdf' ) );
    }

    /**
     * Register the admin submenu page
     */
    public function register_menu() {
        add_submenu_page(
            'businessvance',
            __( 'Agreements', 'businessvance-services-manager' ),
            __( 'Agreements', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-agreements',
            array( $this, 'render_agreements_page' )
        );
    }

    /**
     * Enqueue admin assets on the agreements page
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'businessvance_page_businessvance-agreements' ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'bv-admin-css',
            BV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BV_VERSION
        );

        wp_enqueue_script(
            'bv-admin-js',
            BV_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            BV_VERSION,
            false
        );

        wp_localize_script( 'bv-admin-js', 'bvAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'strings'  => array(
                'confirm_delete'     => __( 'Are you sure you want to delete this agreement template?', 'businessvance-services-manager' ),
                'saving'             => __( 'Saving...', 'businessvance-services-manager' ),
                'saved'              => __( 'Saved successfully!', 'businessvance-services-manager' ),
                'error'              => __( 'An error occurred. Please try again.', 'businessvance-services-manager' ),
                'importing'          => __( 'Importing...', 'businessvance-services-manager' ),
                'import_success'     => __( 'Agreement imported successfully!', 'businessvance-services-manager' ),
                'import_error'       => __( 'Failed to import agreement. Please check the file and try again.', 'businessvance-services-manager' ),
                'select_pdf'         => __( 'Please select a PDF file to import.', 'businessvance-services-manager' ),
                'invalid_file'       => __( 'Invalid file type. Only PDF files are accepted.', 'businessvance-services-manager' ),
                'upload_error'       => __( 'Failed to upload file. Please try again.', 'businessvance-services-manager' ),
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
     * Get database table name
     *
     * @return string
     */
    private function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'bv_agreement_templates';
    }

    /**
     * Get human-readable label for an agreement type.
     *
     * @param  string $type The agreement type slug.
     * @return string
     */
    private function get_type_label( $type ) {
        $labels = array(
            'nda'               => __( 'NDA', 'businessvance-services-manager' ),
            'service-agreement' => __( 'Service Agreement', 'businessvance-services-manager' ),
            'confidentiality'   => __( 'Confidentiality Agreement', 'businessvance-services-manager' ),
            'custom'            => __( 'Custom', 'businessvance-services-manager' ),
        );
        return isset( $labels[ $type ] ) ? $labels[ $type ] : esc_html( $type );
    }

    /**
     * Get CSS class for a type badge.
     *
     * @param  string $type The agreement type slug.
     * @return string
     */
    private function get_type_badge_class( $type ) {
        switch ( $type ) {
            case 'nda':
                return 'bv-badge-nda';
            case 'service-agreement':
                return 'bv-badge-service-agreement';
            case 'confidentiality':
                return 'bv-badge-confidentiality';
            case 'custom':
            default:
                return 'bv-badge-custom';
        }
    }

    /**
     * Extract text content from a PDF file.
     *
     * Tries multiple methods: pdftotext CLI, then pure-PHP fallback.
     *
     * @param  string $file_path Absolute path to the PDF file.
     * @return string|WP_Error  Extracted text on success, WP_Error on failure.
     */
    private function extract_pdf_text( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', __( 'PDF file not found.', 'businessvance-services-manager' ) );
        }

        // Method 1: pdftotext CLI (most reliable)
        $output = shell_exec( 'pdftotext -layout "' . escapeshellcmd( $file_path ) . '" - 2>/dev/null' );
        if ( ! empty( $output ) && strlen( trim( $output ) ) > 50 ) {
            return $output;
        }

        // Method 2: pdftotext without layout flag
        $output = shell_exec( 'pdftotext "' . escapeshellcmd( $file_path ) . '" - 2>/dev/null' );
        if ( ! empty( $output ) && strlen( trim( $output ) ) > 50 ) {
            return $output;
        }

        // Method 3: Python-based extraction
        $python_script = 'import sys
try:
    import pdfplumber
    text = ""
    with pdfplumber.open(sys.argv[1]) as pdf:
        for page in pdf.pages:
            page_text = page.extract_text()
            if page_text:
                text += page_text + "\n\n"
    print(text)
except ImportError:
    try:
        import fitz
        doc = fitz.open(sys.argv[1])
        text = ""
        for page in doc:
            text += page.get_text() + "\n\n"
        print(text)
    except ImportError:
        import PyPDF2
        reader = PyPDF2.PdfReader(sys.argv[1])
        text = ""
        for page in reader.pages:
            text += page.extract_text() + "\n\n" if page.extract_text() else ""
        print(text)
';

        $tmp_script = tempnam( sys_get_temp_dir(), 'bv_pdf_' );
        file_put_contents( $tmp_script, $python_script );
        $output = shell_exec( 'python3 "' . escapeshellcmd( $tmp_script ) . '" "' . escapeshellcmd( $file_path ) . '" 2>/dev/null' );
        unlink( $tmp_script );

        if ( ! empty( $output ) && strlen( trim( $output ) ) > 50 ) {
            return $output;
        }

        return new WP_Error( 'extraction_failed', __( 'Could not extract text from the PDF. The file may be image-based or corrupted.', 'businessvance-services-manager' ) );
    }

    /**
     * Convert extracted plain text to formatted HTML for agreement content.
     *
     * @param  string $text Raw text extracted from PDF.
     * @return string       HTML-formatted content.
     */
    private function text_to_agreement_html( $text ) {
        $lines = preg_split( '/\r?\n/', $text );
        $html  = '';
        $in_list     = false;
        $in_heading  = false;

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // Skip empty lines — close list if open
            if ( $trimmed === '' ) {
                if ( $in_list ) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                $html .= "\n";
                continue;
            }

            // Skip page headers/footers (short lines with common patterns)
            if ( preg_match( '/^(Page\s+\d+|BUSINESSVANCE\s*\|\s*Research\.\s*Analyze\.\s*Plan\.\s*Succeed\.\s*\|)/i', $trimmed ) ) {
                continue;
            }

            // Detect numbered headings like "1. Title", "10. CLIENT ACKNOWLEDGEMENT"
            if ( preg_match( '/^(\d{1,2})\.\s+[A-Z]/', $trimmed ) ) {
                if ( $in_list ) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                $html .= '<h3 style="color: #0A2647; font-size: 16px; margin-top: 25px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0;">' . esc_html( $trimmed ) . "</h3>\n";
                continue;
            }

            // Detect all-caps headings like "CLIENT DETAILS", "BUSINESSVANCE DECLARATION"
            if ( preg_match( '/^[A-Z][A-Z\s&\/]{5,}$/', $trimmed ) && strlen( $trimmed ) < 60 ) {
                if ( $in_list ) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                $html .= '<h3 style="color: #0A2647; font-size: 16px; margin-top: 30px; padding-bottom: 6px; border-bottom: 2px solid #D4AF37;">' . esc_html( $trimmed ) . "</h3>\n";
                continue;
            }

            // Detect bullet points: "•", "-", "*", or lines starting with tab+text
            if ( preg_match( '/^[\-\*\•]\s/', $trimmed ) || ( strlen( $line ) > 0 && $line[0] === "\t" && ! preg_match( '/^\t[a-zA-Z]/', $trimmed ) ) ) {
                $trimmed = preg_replace( '/^[\-\*\•]\s*/', '', $trimmed );
                if ( ! $in_list ) {
                    $html .= '<ul style="padding-left: 20px;">' . "\n";
                    $in_list = true;
                }
                $html .= '<li style="margin-bottom: 8px;">' . esc_html( $trimmed ) . "</li>\n";
                continue;
            }

            // Detect field label lines like "Client full name", "Business name" (followed by blank or value)
            if ( preg_match( '/^[A-Za-z][A-Za-z\s\/]{2,}$/', $trimmed ) && strlen( $trimmed ) < 50 && ! preg_match( '/[.!?]$/', $trimmed ) ) {
                if ( $in_list ) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                // Could be a table field — render as a simple line
                $html .= '<p style="margin: 8px 0; font-weight: 600; color: #0A2647;">' . esc_html( $trimmed ) . "</p>\n";
                continue;
            }

            // Regular paragraph text
            if ( $in_list ) {
                $html .= "</ul>\n";
                $in_list = false;
            }

            // Detect "Important:" or similar callouts
            if ( preg_match( '/^(Important|Note|Warning|Caution)\s*:/i', $trimmed ) ) {
                $html .= '<div style="background: #fff8e1; padding: 12px 18px; border-radius: 6px; margin: 15px 0; font-size: 13px; color: #795548;">' . esc_html( $trimmed ) . "</div>\n";
                continue;
            }

            // Detect indented descriptions (our commitment, etc.)
            if ( preg_match( '/^(Our commitment|BusinessVance confirms)/i', $trimmed ) ) {
                $html .= '<div style="background: #f8f9fa; padding: 15px 20px; border-left: 4px solid #D4AF37; margin-bottom: 25px; border-radius: 0 6px 6px 0;">' . "\n";
                $html .= '<p style="margin: 0; font-style: italic; color: #555;"><strong>' . esc_html( $trimmed ) . "</strong></p>\n";
                $html .= "</div>\n";
                continue;
            }

            // Default: paragraph
            $html .= '<p style="margin: 8px 0;">' . esc_html( $trimmed ) . "</p>\n";
        }

        // Close any remaining list
        if ( $in_list ) {
            $html .= "</ul>\n";
        }

        return $html;
    }

    /**
     * Detect the agreement type from text content.
     *
     * @param  string $text The extracted text.
     * @return string       Type slug.
     */
    private function detect_agreement_type( $text ) {
        $text_lower = strtolower( $text );

        if ( preg_match( '/non[\s-]?disclosure|nda/i', $text ) && ! preg_match( '/confidentiality\s+undertaking/i', $text ) ) {
            return 'nda';
        }
        if ( preg_match( '/service\s+agreement|terms\s+of\s+service|engagement/i', $text_lower ) ) {
            return 'service-agreement';
        }
        if ( preg_match( '/confidentiality|information\s+protection|undertaking/i', $text_lower ) ) {
            return 'confidentiality';
        }

        return 'custom';
    }

    /**
     * Detect a sensible name from text content.
     *
     * @param  string $text The extracted text.
     * @return string
     */
    private function detect_agreement_name( $text ) {
        // Look for a title-like line in the first 30 lines
        $lines = preg_split( '/\r?\n/', $text );
        $check_count = min( 30, count( $lines ) );

        for ( $i = 0; $i < $check_count; $i++ ) {
            $trimmed = trim( $lines[ $i ] );
            // Skip short lines, all-caps brand names, and generic text
            if ( strlen( $trimmed ) < 10 ) continue;
            if ( preg_match( '/^BUSINESSVANCE$/i', $trimmed ) ) continue;
            if ( preg_match( '/^RESEARCH\.\s*ANALYZE/i', $trimmed ) ) continue;
            if ( preg_match( '/^BV\s*RESEARCH/i', $trimmed ) ) continue;
            // Look for lines that look like a title (mixed case, no trailing period)
            if ( preg_match( '/^[A-Z][A-Za-z\s\-\&\']{10,100}$/', $trimmed ) && ! preg_match( '/\.$/', $trimmed ) ) {
                // Skip lines that look like section headings (start with number)
                if ( ! preg_match( '/^\d+\./', $trimmed ) ) {
                    return $trimmed;
                }
            }
        }

        return __( 'Imported Agreement', 'businessvance-services-manager' );
    }

    /**
     * AJAX: Get a single agreement template by ID.
     */
    public function ajax_get_template() {
        $this->verify_nonce();
        global $wpdb;

        $table = $this->get_table();
        $id    = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $template = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A );

        if ( ! $template ) {
            wp_send_json_error( array( 'message' => __( 'Agreement template not found.', 'businessvance-services-manager' ) ) );
        }

        wp_send_json_success( $template );
    }

    /**
     * AJAX: Create or update an agreement template.
     */
    public function ajax_save_template() {
        $this->verify_nonce();
        global $wpdb;

        $table = $this->get_table();
        $id    = intval( $_POST['id'] ?? 0 );

        $name    = sanitize_text_field( $_POST['name'] ?? '' );
        $slug    = sanitize_text_field( $_POST['slug'] ?? '' );
        $type    = sanitize_text_field( $_POST['type'] ?? 'nda' );
        $content = wp_kses_post( $_POST['content'] ?? '' );
        $is_default = intval( $_POST['is_default'] ?? 0 );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Template name is required.', 'businessvance-services-manager' ) ) );
        }

        // Auto-generate slug from name if empty.
        if ( empty( $slug ) ) {
            $slug = sanitize_title( $name );
        }

        // Validate type.
        $allowed_types = array( 'nda', 'service-agreement', 'confidentiality', 'custom' );
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'nda';
        }

        $now = current_time( 'mysql' );
        $data   = array(
            'name'       => $name,
            'slug'       => $slug,
            'type'       => $type,
            'content'    => $content,
            'is_default' => $is_default,
            'updated_at' => $now,
        );
        $format = array( '%s', '%s', '%s', '%s', '%d', '%s' );

        // If setting as default, unset any existing default of the same type.
        if ( $is_default ) {
            $wpdb->update(
                $table,
                array( 'is_default' => 0, 'updated_at' => $now ),
                array( 'type' => $type ),
                array( '%d', '%s' ),
                array( '%s' )
            );
        }

        if ( $id > 0 ) {
            // Update.
            $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
            wp_send_json_success( array(
                'message' => __( 'Agreement template updated.', 'businessvance-services-manager' ),
                'id'      => $id,
            ) );
        } else {
            // Create.
            $data['created_at'] = $now;
            $format[] = '%s';

            $wpdb->insert( $table, $data, $format );
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array(
                'message' => __( 'Agreement template created.', 'businessvance-services-manager' ),
                'id'      => $new_id,
            ) );
        }
    }

    /**
     * AJAX: Delete an agreement template.
     */
    public function ajax_delete_template() {
        $this->verify_nonce();
        global $wpdb;

        $table = $this->get_table();
        $id    = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        // Clear the agreement_template_id reference from any services using this template.
        $wpdb->update(
            $wpdb->prefix . 'bv_services',
            array( 'agreement_template_id' => 0 ),
            array( 'agreement_template_id' => $id ),
            array( '%d' ),
            array( '%d' )
        );

        // Also remove junction table entries
        $wpdb->delete(
            $wpdb->prefix . 'bv_service_agreements',
            array( 'agreement_template_id' => $id ),
            array( '%d' )
        );

        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success( array( 'message' => __( 'Agreement template deleted.', 'businessvance-services-manager' ) ) );
    }

    /**
     * AJAX: Set an agreement template as the default for its type.
     */
    public function ajax_set_default() {
        $this->verify_nonce();
        global $wpdb;

        $table = $this->get_table();
        $id    = intval( $_POST['id'] ?? 0 );

        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        // Get the template to find its type.
        $template = $wpdb->get_row( $wpdb->prepare(
            "SELECT type FROM {$table} WHERE id = %d",
            $id
        ) );

        if ( ! $template ) {
            wp_send_json_error( array( 'message' => __( 'Agreement template not found.', 'businessvance-services-manager' ) ) );
        }

        $now = current_time( 'mysql' );

        // Unset all defaults of the same type.
        $wpdb->update(
            $table,
            array( 'is_default' => 0, 'updated_at' => $now ),
            array( 'type' => $template->type ),
            array( '%d', '%s' ),
            array( '%s' )
        );

        // Set the selected template as default.
        $wpdb->update(
            $table,
            array( 'is_default' => 1, 'updated_at' => $now ),
            array( 'id' => $id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        wp_send_json_success( array( 'message' => __( 'Default agreement template updated.', 'businessvance-services-manager' ) ) );
    }

    /**
     * AJAX: Import an agreement template from a PDF file upload.
     *
     * Expects multipart/form-data with:
     *   - nonce: bv_admin_nonce
     *   - name: (optional) template name — auto-detected if empty
     *   - type: (optional) agreement type — auto-detected if empty
     *   - is_default: 0 or 1
     *   - pdf_file: the uploaded PDF file
     */
    public function ajax_import_pdf() {
        $this->verify_nonce();

        // Check for uploaded file
        if ( empty( $_FILES['pdf_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'businessvance-services-manager' ) ) );
        }

        $file = $_FILES['pdf_file'];

        // Validate file type
        $file_type = wp_check_filetype( $file['name'] );
        $allowed   = array( 'pdf' );
        if ( ! in_array( strtolower( $file_type['ext'] ), $allowed, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file type. Only PDF files are accepted.', 'businessvance-services-manager' ) ) );
        }

        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            $upload_errors = array(
                UPLOAD_ERR_INI_SIZE   => __( 'The file exceeds the upload_max_filesize directive.', 'businessvance-services-manager' ),
                UPLOAD_ERR_FORM_SIZE  => __( 'The file exceeds the MAX_FILE_SIZE directive.', 'businessvance-services-manager' ),
                UPLOAD_ERR_PARTIAL   => __( 'The file was only partially uploaded.', 'businessvance-services-manager' ),
                UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'businessvance-services-manager' ),
                UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder.', 'businessvance-services-manager' ),
                UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'businessvance-services-manager' ),
            );
            $error_msg = isset( $upload_errors[ $file['error'] ] ) ? $upload_errors[ $file['error'] ] : __( 'Unknown upload error.', 'businessvance-services-manager' );
            wp_send_json_error( array( 'message' => $error_msg ) );
        }

        // Move the uploaded file to a temp location
        $tmp_path = $file['tmp_name'];
        if ( ! file_exists( $tmp_path ) ) {
            wp_send_json_error( array( 'message' => __( 'Uploaded file could not be found.', 'businessvance-services-manager' ) ) );
        }

        // Extract text from PDF
        $raw_text = $this->extract_pdf_text( $tmp_path );
        if ( is_wp_error( $raw_text ) ) {
            wp_send_json_error( array( 'message' => $raw_text->get_error_message() ) );
        }

        // Get parameters (with auto-detection)
        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $slug       = sanitize_text_field( $_POST['slug'] ?? '' );
        $type       = sanitize_text_field( $_POST['type'] ?? '' );
        $is_default = intval( $_POST['is_default'] ?? 0 );

        // Auto-detect name if not provided
        if ( empty( $name ) ) {
            $name = $this->detect_agreement_name( $raw_text );
        }

        // Auto-generate slug if not provided
        if ( empty( $slug ) ) {
            $slug = sanitize_title( $name );
        }

        // Auto-detect type if not provided
        if ( empty( $type ) || ! in_array( $type, array( 'nda', 'service-agreement', 'confidentiality', 'custom' ), true ) ) {
            $type = $this->detect_agreement_type( $raw_text );
        }

        // Convert plain text to formatted HTML
        $html_content = $this->text_to_agreement_html( $raw_text );

        // Clean up temp file
        @unlink( $tmp_path );

        // Insert into database
        global $wpdb;
        $table = $this->get_table();
        $now   = current_time( 'mysql' );

        // If setting as default, unset any existing default of the same type.
        if ( $is_default ) {
            $wpdb->update(
                $table,
                array( 'is_default' => 0, 'updated_at' => $now ),
                array( 'type' => $type ),
                array( '%d', '%s' ),
                array( '%s' )
            );
        }

        $wpdb->insert( $table, array(
            'name'       => $name,
            'slug'       => $slug,
            'type'       => $type,
            'content'    => $html_content,
            'is_default' => $is_default,
            'created_at' => $now,
            'updated_at' => $now,
        ), array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ) );

        $new_id = $wpdb->insert_id;

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: template name */
                __( 'Agreement "%s" imported successfully from PDF.', 'businessvance-services-manager' ),
                $name
            ),
            'id'    => $new_id,
            'name'  => $name,
            'type'  => $type,
        ) );
    }

    /**
     * Render the Agreements admin page.
     */
    public function render_agreements_page() {
        global $wpdb;

        $table    = $this->get_table();
        $junction_table = $wpdb->prefix . 'bv_service_agreements';

        $templates = $wpdb->get_results(
            "SELECT t.*,
                ( SELECT COUNT(DISTINCT sa.service_id) FROM {$junction_table} sa WHERE sa.agreement_template_id = t.id ) AS service_count
             FROM {$table} t
             ORDER BY t.type ASC, t.name ASC"
        );
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Agreement Templates', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Manage agreement and NDA templates that can be assigned to services.', 'businessvance-services-manager' ); ?></p>
                </div>
                <div class="bv-admin-header-actions" style="display:flex;gap:10px;align-items:center;">
                    <button type="button" class="button bv-import-pdf-btn" id="bv-import-agreement-pdf" style="border:1px dashed #D4AF37;color:#b7950b;font-weight:600;">
                        <span class="dashicons dashicons-upload" style="vertical-align:middle;margin-top:4px;margin-right:4px;"></span>
                        <?php esc_html_e( 'Import from PDF', 'businessvance-services-manager' ); ?>
                    </button>
                    <button type="button" class="button button-primary bv-gold-btn" id="bv-add-agreement">
                        <?php esc_html_e( '+ Add Template', 'businessvance-services-manager' ); ?>
                    </button>
                </div>
            </div>

            <?php if ( empty( $templates ) ) : ?>
                <div class="bv-table-container" style="text-align:center; padding:60px 20px;">
                    <p style="font-size:16px; color:#666;">
                        <?php esc_html_e( 'No agreement templates found. Click "Add Template" to create your first agreement or "Import from PDF" to import an existing document.', 'businessvance-services-manager' ); ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="bv-table-container">
                    <table class="wp-list-table widefat fixed striped" id="bv-agreements-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'businessvance-services-manager' ); ?></th>
                                <th style="width:160px;"><?php esc_html_e( 'Type', 'businessvance-services-manager' ); ?></th>
                                <th style="width:80px;"><?php esc_html_e( 'Default', 'businessvance-services-manager' ); ?></th>
                                <th style="width:90px;"><?php esc_html_e( 'Services', 'businessvance-services-manager' ); ?></th>
                                <th style="width:150px;"><?php esc_html_e( 'Created', 'businessvance-services-manager' ); ?></th>
                                <th style="width:200px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $templates as $tpl ) : ?>
                                <tr data-id="<?php echo esc_attr( $tpl->id ); ?>">
                                    <td>
                                        <strong><?php echo esc_html( $tpl->name ); ?></strong>
                                        <br>
                                        <small style="color:#999;"><?php echo esc_html( $tpl->slug ); ?></small>
                                    </td>
                                    <td>
                                        <span class="<?php echo esc_attr( $this->get_type_badge_class( $tpl->type ) ); ?> bv-type-badge"
                                              style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; line-height:1.4;<?php echo $this->get_type_badge_inline_style( $tpl->type ); ?>">
                                            <?php echo esc_html( $this->get_type_label( $tpl->type ) ); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ( $tpl->is_default ) : ?>
                                            <span title="<?php esc_attr_e( 'Default template', 'businessvance-services-manager' ); ?>" style="font-size:20px; color:#D4AF37;">&#9733;</span>
                                        <?php else : ?>
                                            <span title="<?php esc_attr_e( 'Not default', 'businessvance-services-manager' ); ?>" style="font-size:20px; color:#ccc;">&#9734;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php echo (int) $tpl->service_count > 0
                                            ? '<strong>' . esc_html( (int) $tpl->service_count ) . '</strong>'
                                            : '<span style="color:#999;">0</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $created = strtotime( $tpl->created_at );
                                        if ( $created ) {
                                            echo esc_html( date_i18n( get_option( 'date_format' ), $created ) );
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small bv-edit-btn" data-id="<?php echo esc_attr( $tpl->id ); ?>">
                                            <?php esc_html_e( 'Edit', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-set-default-btn" data-id="<?php echo esc_attr( $tpl->id ); ?>" data-type="<?php echo esc_attr( $tpl->type ); ?>"<?php echo $tpl->is_default ? ' disabled style="opacity:0.5;"' : ''; ?>>
                                            <?php esc_html_e( 'Set Default', 'businessvance-services-manager' ); ?>
                                        </button>
                                        <button type="button" class="button button-small bv-delete-btn" data-id="<?php echo esc_attr( $tpl->id ); ?>" data-type="agreement" data-name="<?php echo esc_attr( $tpl->name ); ?>">
                                            <?php esc_html_e( 'Delete', 'businessvance-services-manager' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Agreement Template Modal -->
        <div id="bv-agreement-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content">
                <div class="bv-modal-header">
                    <h2 id="bv-agreement-modal-title"><?php esc_html_e( 'Add New Agreement Template', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-agreement-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group">
                            <label for="bv-agreement-name"><?php esc_html_e( 'Template Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="bv-agreement-name" name="name" required class="regular-text">
                        </div>

                        <div class="bv-form-group">
                            <label for="bv-agreement-slug"><?php esc_html_e( 'Slug', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="bv-agreement-slug" name="slug" class="regular-text" placeholder="<?php esc_attr_e( 'Auto-generated from name', 'businessvance-services-manager' ); ?>">
                        </div>

                        <div class="bv-form-group">
                            <label for="bv-agreement-type"><?php esc_html_e( 'Type', 'businessvance-services-manager' ); ?></label>
                            <select id="bv-agreement-type" name="type">
                                <option value="nda"><?php esc_html_e( 'NDA', 'businessvance-services-manager' ); ?></option>
                                <option value="service-agreement"><?php esc_html_e( 'Service Agreement', 'businessvance-services-manager' ); ?></option>
                                <option value="confidentiality"><?php esc_html_e( 'Confidentiality Agreement', 'businessvance-services-manager' ); ?></option>
                                <option value="custom"><?php esc_html_e( 'Custom', 'businessvance-services-manager' ); ?></option>
                            </select>
                        </div>

                        <div class="bv-form-group" style="display:flex;align-items:center;gap:8px;padding-top:24px;">
                            <input type="checkbox" id="bv-agreement-default" name="is_default" value="1">
                            <label for="bv-agreement-default" style="margin:0;"><?php esc_html_e( 'Set as default template for this type', 'businessvance-services-manager' ); ?></label>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="bv-agreement-content"><?php esc_html_e( 'Agreement Content', 'businessvance-services-manager' ); ?></label>
                            <textarea id="bv-agreement-content" name="content" rows="18" class="large-text" style="font-family:monospace;font-size:13px;line-height:1.6;"></textarea>
                        </div>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Template', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import PDF Modal -->
        <div id="bv-import-pdf-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content" style="max-width:560px;">
                <div class="bv-modal-header">
                    <h2><?php esc_html_e( 'Import Agreement from PDF', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-import-pdf-form" class="bv-modal-body" enctype="multipart/form-data">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid" style="display:flex;flex-direction:column;gap:16px;">

                        <!-- File upload area -->
                        <div class="bv-form-group">
                            <label for="bv-import-pdf-file"><?php esc_html_e( 'PDF File *', 'businessvance-services-manager' ); ?></label>
                            <div class="bv-file-upload-area" id="bv-pdf-drop-zone"
                                 style="border:2px dashed #ccc;border-radius:8px;padding:30px 20px;text-align:center;cursor:pointer;transition:border-color 0.2s, background 0.2s;">
                                <div id="bv-pdf-drop-zone-content">
                                    <span class="dashicons dashicons-upload" style="font-size:40px;width:40px;height:40px;color:#999;"></span>
                                    <p style="margin:12px 0 4px 0;color:#555;font-size:14px;">
                                        <?php esc_html_e( 'Drag & drop a PDF file here, or click to select', 'businessvance-services-manager' ); ?>
                                    </p>
                                    <p style="margin:0;color:#999;font-size:12px;">
                                        <?php esc_html_e( 'Accepted: .pdf files only', 'businessvance-services-manager' ); ?>
                                    </p>
                                </div>
                                <div id="bv-pdf-file-info" style="display:none;">
                                    <span class="dashicons dashicons-pdf" style="font-size:40px;width:40px;height:40px;color:#e74c3c;"></span>
                                    <p id="bv-pdf-file-name" style="margin:8px 0 4px 0;color:#0A2647;font-weight:600;font-size:14px;"></p>
                                    <p id="bv-pdf-file-size" style="margin:0;color:#999;font-size:12px;"></p>
                                    <button type="button" id="bv-pdf-remove-file" class="button button-small" style="margin-top:8px;">
                                        <?php esc_html_e( 'Remove', 'businessvance-services-manager' ); ?>
                                    </button>
                                </div>
                            </div>
                            <input type="file" id="bv-import-pdf-file" name="pdf_file" accept=".pdf" style="display:none;">
                        </div>

                        <hr style="border:none;border-top:1px solid #e0e0e0;margin:4px 0;">

                        <!-- Template details -->
                        <div class="bv-form-group">
                            <label for="bv-import-name"><?php esc_html_e( 'Template Name', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="bv-import-name" name="name" class="regular-text" placeholder="<?php esc_attr_e( 'Auto-detected from PDF (leave blank to auto-detect)', 'businessvance-services-manager' ); ?>">
                            <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Leave blank to auto-detect from the PDF content.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group">
                            <label for="bv-import-type"><?php esc_html_e( 'Agreement Type', 'businessvance-services-manager' ); ?></label>
                            <select id="bv-import-type" name="type">
                                <option value=""><?php esc_html_e( 'Auto-detect from PDF', 'businessvance-services-manager' ); ?></option>
                                <option value="nda"><?php esc_html_e( 'NDA', 'businessvance-services-manager' ); ?></option>
                                <option value="service-agreement"><?php esc_html_e( 'Service Agreement', 'businessvance-services-manager' ); ?></option>
                                <option value="confidentiality"><?php esc_html_e( 'Confidentiality Agreement', 'businessvance-services-manager' ); ?></option>
                                <option value="custom"><?php esc_html_e( 'Custom', 'businessvance-services-manager' ); ?></option>
                            </select>
                        </div>

                        <div class="bv-form-group" style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" id="bv-import-default" name="is_default" value="1">
                            <label for="bv-import-default" style="margin:0;"><?php esc_html_e( 'Set as default template for this type', 'businessvance-services-manager' ); ?></label>
                        </div>

                        <!-- Info box -->
                        <div class="bv-form-group" style="background:#f0f6fc;border-left:4px solid #2A9D8F;padding:14px 16px;border-radius:0 6px 6px 0;">
                            <p style="margin:0;font-size:13px;color:#264653;">
                                <strong><?php esc_html_e( 'How it works:', 'businessvance-services-manager' ); ?></strong><br>
                                <?php esc_html_e( 'The plugin will extract text from the PDF, auto-detect the title and type, convert it to formatted HTML, and create a new agreement template. You can edit the template after import if needed.', 'businessvance-services-manager' ); ?>
                            </p>
                        </div>
                    </div>

                    <div class="bv-modal-footer" style="margin-top:20px;">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary" id="bv-import-pdf-submit" style="background:#2A9D8F;border-color:#2A9D8F;">
                            <?php esc_html_e( 'Import Agreement', 'businessvance-services-manager' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(function($) {
            var $modal      = $('#bv-agreement-modal');
            var $form       = $('#bv-agreement-form');
            var $title      = $('#bv-agreement-modal-title');
            var $nameField  = $('#bv-agreement-name');
            var $slugField  = $('#bv-agreement-slug');
            var $typeField  = $('#bv-agreement-type');
            var $contentField = $('#bv-agreement-content');
            var $defaultField = $('#bv-agreement-default');
            var $idField    = $form.find('input[name="id"]');
            var editingId   = 0;

            // Auto-generate slug from name.
            $nameField.on('input', function() {
                if (editingId === 0) {
                    $slugField.val( $(this).val().toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-+|-+$/g, '') );
                }
            });

            // Open modal for adding.
            $('#bv-add-agreement').on('click', function() {
                editingId = 0;
                $title.text('<?php echo esc_js( __( 'Add New Agreement Template', 'businessvance-services-manager' ) ); ?>');
                $form[0].reset();
                $idField.val('');
                $modal.show();
                $nameField.focus();
            });

            // Open modal for editing.
            $(document).on('click', '.bv-edit-btn', function() {
                var id = $(this).data('id');
                editingId = id;

                $.post(bvAdmin.ajax_url, {
                    action: 'bv_get_agreement_template',
                    nonce: bvAdmin.nonce,
                    id: id
                }, function(res) {
                    if (res.success) {
                        var t = res.data;
                        $title.text('<?php echo esc_js( __( 'Edit Agreement Template', 'businessvance-services-manager' ) ); ?>');
                        $idField.val(t.id);
                        $nameField.val(t.name);
                        $slugField.val(t.slug);
                        $typeField.val(t.type);
                        $contentField.val(t.content);
                        $defaultField.prop('checked', parseInt(t.is_default) === 1);
                        $modal.show();
                        $nameField.focus();
                    } else {
                        alert(res.data.message || bvAdmin.strings.error);
                    }
                });
            });

            // Close modal.
            $('.bv-modal-close, .bv-cancel-btn, .bv-modal-overlay').on('click', function() {
                $(this).closest('.bv-modal').hide();
            });

            // Save template.
            $form.on('submit', function(e) {
                e.preventDefault();

                var $submitBtn = $form.find('button[type="submit"]');
                var originalText = $submitBtn.text();
                $submitBtn.text(bvAdmin.strings.saving).prop('disabled', true);

                $.post(bvAdmin.ajax_url, {
                    action: 'bv_save_agreement_template',
                    nonce: bvAdmin.nonce,
                    id: $idField.val(),
                    name: $nameField.val(),
                    slug: $slugField.val(),
                    type: $typeField.val(),
                    content: $contentField.val(),
                    is_default: $defaultField.is(':checked') ? 1 : 0
                }, function(res) {
                    $submitBtn.text(originalText).prop('disabled', false);
                    if (res.success) {
                        $modal.hide();
                        location.reload();
                    } else {
                        alert(res.data.message || bvAdmin.strings.error);
                    }
                }).fail(function() {
                    $submitBtn.text(originalText).prop('disabled', false);
                    alert(bvAdmin.strings.error);
                });
            });

            // Delete template.
            $(document).on('click', '.bv-delete-btn', function() {
                if (!confirm(bvAdmin.strings.confirm_delete)) {
                    return;
                }
                var id = $(this).data('id');

                $.post(bvAdmin.ajax_url, {
                    action: 'bv_delete_agreement_template',
                    nonce: bvAdmin.nonce,
                    id: id
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data.message || bvAdmin.strings.error);
                    }
                });
            });

            // Set default.
            $(document).on('click', '.bv-set-default-btn', function() {
                var id = $(this).data('id');

                $.post(bvAdmin.ajax_url, {
                    action: 'bv_set_default_agreement',
                    nonce: bvAdmin.nonce,
                    id: id
                }, function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.data.message || bvAdmin.strings.error);
                    }
                });
            });

            // ============================================================
            // PDF Import
            // ============================================================
            var $importModal    = $('#bv-import-pdf-modal');
            var $importForm     = $('#bv-import-pdf-form');
            var $fileInput      = $('#bv-import-pdf-file');
            var $dropZone       = $('#bv-pdf-drop-zone');
            var $dropContent    = $('#bv-pdf-drop-zone-content');
            var $fileInfo       = $('#bv-pdf-file-info');
            var $fileName       = $('#bv-pdf-file-name');
            var $fileSize       = $('#bv-pdf-file-size');
            var $removeFile     = $('#bv-pdf-remove-file');
            var $importSubmit   = $('#bv-import-pdf-submit');
            var selectedFile    = null;

            // Open import modal
            $('#bv-import-agreement-pdf').on('click', function() {
                $importForm[0].reset();
                resetFileSelection();
                $importModal.show();
            });

            // Click to select file
            $dropZone.on('click', function(e) {
                if ($(e.target).is('#bv-pdf-remove-file') || $(e.target).closest('#bv-pdf-remove-file').length) return;
                $fileInput.trigger('click');
            });

            // File selected via input
            $fileInput.on('change', function() {
                if (this.files && this.files[0]) {
                    handleFileSelect(this.files[0]);
                }
            });

            // Drag & drop
            $dropZone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).css('border-color', '#D4AF37').css('background', '#fffdf5');
            }).on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).css('border-color', '#ccc').css('background', '');
            }).on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).css('border-color', '#ccc').css('background', '');
                var files = e.originalEvent.dataTransfer.files;
                if (files && files[0]) {
                    handleFileSelect(files[0]);
                }
            });

            // Handle file selection
            function handleFileSelect(file) {
                // Validate type
                if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                    alert(bvAdmin.strings.invalid_file);
                    return;
                }
                selectedFile = file;
                $fileName.text(file.name);
                $fileSize.text(formatFileSize(file.size));
                $dropContent.hide();
                $fileInfo.show();
                $dropZone.css('border-color', '#2A9D8F').css('border-style', 'solid');
            }

            // Reset file selection
            function resetFileSelection() {
                selectedFile = null;
                $fileInput.val('');
                $dropContent.show();
                $fileInfo.hide();
                $dropZone.css('border-color', '#ccc').css('border-style', 'dashed').css('background', '');
            }

            // Remove selected file
            $removeFile.on('click', function(e) {
                e.stopPropagation();
                resetFileSelection();
            });

            // Format file size
            function formatFileSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            }

            // Submit import form
            $(document).on('submit', '#bv-import-pdf-form', function(e) {
                e.preventDefault();

                // Inline fallbacks in case bvAdmin is not fully loaded
                var bvStr = (typeof bvAdmin !== 'undefined' && bvAdmin.strings) ? bvAdmin.strings : {};
                var bvUrl = (typeof bvAdmin !== 'undefined') ? bvAdmin.ajax_url : ajaxUrl;
                var bvNonce = (typeof bvAdmin !== 'undefined') ? bvAdmin.nonce : nonce;

                if (!selectedFile) {
                    alert(bvStr.select_pdf || 'Please select a PDF file to import.');
                    return;
                }

                var $btn = $importSubmit;
                var originalText = $btn.text();
                $btn.text(bvStr.importing || 'Importing...').prop('disabled', true);

                var formData = new FormData();
                formData.append('action', 'bv_import_agreement_pdf');
                formData.append('nonce', bvNonce);
                formData.append('pdf_file', selectedFile);
                formData.append('name', $('#bv-import-name').val());
                formData.append('type', $('#bv-import-type').val());
                formData.append('is_default', $('#bv-import-default').is(':checked') ? 1 : 0);

                $.ajax({
                    url: bvUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        $btn.text(originalText).prop('disabled', false);
                        if (res.success) {
                            $importModal.hide();
                            location.reload();
                        } else {
                            alert(res.data.message || bvStr.import_error || 'Failed to import agreement. Please check the file and try again.');
                        }
                    },
                    error: function() {
                        $btn.text(originalText).prop('disabled', false);
                        alert(bvStr.import_error || 'Failed to import agreement. Please check the file and try again.');
                    }
                });
            });

            // Close on Escape.
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) {
                    if ($modal.is(':visible')) $modal.hide();
                    if ($importModal.is(':visible')) $importModal.hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Get inline style string for type badges.
     *
     * @param  string $type The agreement type slug.
     * @return string
     */
    private function get_type_badge_inline_style( $type ) {
        switch ( $type ) {
            case 'nda':
                return ' background:#e8f0fe; color:#1a56db;';
            case 'service-agreement':
                return ' background:#e6f7f7; color:#0d7377;';
            case 'confidentiality':
                return ' background:#fef9e7; color:#b7950b;';
            case 'custom':
            default:
                return ' background:#f3f4f6; color:#6b7280;';
        }
    }
}
