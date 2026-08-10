<?php
/**
 * BusinessVance Document Requirements Manager
 *
 * Admin page for managing document requirements that can be
 * assigned to services. Clients must upload these documents
 * in the portal before a project can proceed.
 *
 * @package BusinessVance_Services_Manager
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BV_Document_Requirements {

    /**
     * Constructor — register hooks
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_bv_get_document_requirements', array( $this, 'ajax_get_list' ) );
        add_action( 'wp_ajax_bv_get_document_requirement', array( $this, 'ajax_get_single' ) );
        add_action( 'wp_ajax_bv_save_document_requirement', array( $this, 'ajax_save' ) );
        add_action( 'wp_ajax_bv_delete_document_requirement', array( $this, 'ajax_delete' ) );
        add_action( 'wp_ajax_bv_reorder_document_requirements', array( $this, 'ajax_reorder' ) );
    }

    /**
     * Register the admin submenu page
     */
    public function register_menu() {
        add_submenu_page(
            'businessvance',
            __( 'Document Requirements', 'businessvance-services-manager' ),
            __( 'Documents', 'businessvance-services-manager' ),
            'manage_options',
            'businessvance-documents',
            array( $this, 'render_document_requirements_page' )
        );
    }

    /**
     * Enqueue admin assets on the documents page
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'businessvance_page_businessvance-documents' ) {
            return;
        }

        wp_enqueue_style(
            'bv-admin-css',
            BV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BV_VERSION
        );

        wp_enqueue_script(
            'bv-admin-js',
            BV_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            BV_VERSION,
            false
        );

        $strings = array(
            'confirm_delete' => __( 'Are you sure you want to delete this document requirement?', 'businessvance-services-manager' ),
            'saving'        => __( 'Saving...', 'businessvance-services-manager' ),
            'saved'         => __( 'Saved successfully!', 'businessvance-services-manager' ),
            'error'         => __( 'An error occurred. Please try again.', 'businessvance-services-manager' ),
        );

        wp_localize_script( 'bv-admin-js', 'bvAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'page'     => 'documents',
            'strings'  => $strings,
        ) );

        wp_enqueue_script(
            'bv-doc-reqs-js',
            BV_PLUGIN_URL . 'assets/js/document-requirements.js',
            array( 'jquery', 'jquery-ui-sortable', 'bv-admin-js' ),
            BV_VERSION,
            true
        );

        wp_localize_script( 'bv-doc-reqs-js', 'bvDocReqs', array(
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'bv_admin_nonce' ),
            'services_url' => admin_url( 'admin.php?page=businessvance-services' ),
            'strings'      => array(
                'no_items'      => __( 'No document requirements yet. Click "+ Add Requirement" to create one.', 'businessvance-services-manager' ),
                'edit'          => __( 'Edit', 'businessvance-services-manager' ),
                'delete'        => __( 'Delete', 'businessvance-services-manager' ),
                'add_title'     => __( 'Add Document Requirement', 'businessvance-services-manager' ),
                'edit_title'    => __( 'Edit Document Requirement', 'businessvance-services-manager' ),
                'view_services' => __( 'View services', 'businessvance-services-manager' ),
                'confirm_delete' => $strings['confirm_delete'],
                'saving'        => $strings['saving'],
                'saved'         => $strings['saved'],
                'error'         => $strings['error'],
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
        return $wpdb->prefix . 'bv_document_requirements';
    }

    /**
     * AJAX: Get all document requirements
     */
    public function ajax_get_list() {
        $this->verify_nonce();
        global $wpdb;

        $table    = $this->get_table();
        $junction = $wpdb->prefix . 'bv_service_documents';

        $rows = $wpdb->get_results(
            "SELECT dr.*,
                    (SELECT COUNT(*) FROM {$junction} sd WHERE sd.document_requirement_id = dr.id) AS service_count
             FROM {$table} dr
             ORDER BY dr.display_order ASC, dr.id ASC"
        );

        wp_send_json_success( $rows );
    }

    /**
     * AJAX: Get a single document requirement
     */
    public function ajax_get_single() {
        $this->verify_nonce();
        global $wpdb;

        $id   = intval( $_POST['id'] ?? $_GET['id'] ?? 0 );
        $row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table()} WHERE id = %d", $id ), ARRAY_A );

        if ( ! $row ) {
            wp_send_json_error( array( 'message' => 'Document requirement not found.' ) );
        }

        wp_send_json_success( $row );
    }

    /**
     * AJAX: Create or update a document requirement
     */
    public function ajax_save() {
        $this->verify_nonce();
        global $wpdb;

        $id         = intval( $_POST['id'] ?? 0 );
        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $slug       = sanitize_title( $_POST['slug'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $allowed    = sanitize_text_field( $_POST['allowed_types'] ?? 'pdf,doc,docx,jpg,jpeg,png' );
        $max_size   = max( 1, intval( $_POST['max_size_mb'] ?? 10 ) );
        $is_req     = intval( $_POST['is_required'] ?? 1 );
        $order      = intval( $_POST['display_order'] ?? 0 );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Name is required.', 'businessvance-services-manager' ) ) );
        }

        if ( empty( $slug ) ) {
            $slug = sanitize_title( $name );
        }

        $data = array(
            'name'           => $name,
            'slug'           => $slug,
            'description'    => $description,
            'allowed_types'  => $allowed,
            'max_size_mb'    => $max_size,
            'is_required'    => $is_req,
            'display_order'  => $order,
        );
        $format = array( '%s', '%s', '%s', '%s', '%d', '%d', '%d' );

        if ( $id > 0 ) {
            $wpdb->update( $this->get_table(), $data, array( 'id' => $id ), $format, array( '%d' ) );
            wp_send_json_success( array( 'message' => __( 'Document requirement updated.', 'businessvance-services-manager' ), 'id' => $id ) );
        } else {
            // Auto-assign next display_order
            $max_order = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(display_order), 0) FROM {$this->get_table()}" ) );
            $data['display_order'] = $max_order + 1;
            $format[] = '%d';

            $wpdb->insert( $this->get_table(), $data, $format );
            $new_id = $wpdb->insert_id;
            wp_send_json_success( array( 'message' => __( 'Document requirement created.', 'businessvance-services-manager' ), 'id' => $new_id ) );
        }
    }

    /**
     * AJAX: Delete a document requirement
     */
    public function ajax_delete() {
        $this->verify_nonce();
        global $wpdb;

        $id = intval( $_POST['id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        // Clean up junction table
        $wpdb->delete( $wpdb->prefix . 'bv_service_documents', array( 'document_requirement_id' => $id ), array( '%d' ) );

        // Delete the requirement
        $wpdb->delete( $this->get_table(), array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => __( 'Document requirement deleted.', 'businessvance-services-manager' ) ) );
    }

    /**
     * AJAX: Reorder document requirements
     */
    public function ajax_reorder() {
        $this->verify_nonce();
        global $wpdb;

        $order = $_POST['order'] ?? array();
        if ( ! is_array( $order ) ) {
            wp_send_json_error();
        }

        $sanitized_ids = array();
        $case_parts = array();

        foreach ( $order as $position => $id ) {
            $sanitized_ids[] = intval( $id );
            $case_parts[] = $wpdb->prepare( 'WHEN %d THEN %d', intval( $id ), intval( $position ) );
        }

        if ( ! empty( $sanitized_ids ) && ! empty( $case_parts ) ) {
            $table = $this->get_table();
            $id_list = implode( ',', $sanitized_ids );
            $sql = "UPDATE {$table} SET display_order = CASE id " . implode( ' ', $case_parts ) . " END WHERE id IN ({$id_list})";
            $wpdb->query( $sql );
        }

        wp_send_json_success( array( 'message' => __( 'Order saved.', 'businessvance-services-manager' ) ) );
    }

    /**
     * Render the Document Requirements admin page
     */
    public function render_document_requirements_page() {
        ?>
        <div class="wrap bv-admin-wrap">
            <div class="bv-admin-header">
                <div class="bv-admin-title">
                    <h1><?php esc_html_e( 'Document Requirements', 'businessvance-services-manager' ); ?></h1>
                    <p class="bv-subtitle"><?php esc_html_e( 'Define documents that clients must upload for services. Assign them per-service in the Services editor.', 'businessvance-services-manager' ); ?></p>
                </div>
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-doc-req">
                    <?php esc_html_e( '+ Add Requirement', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <div class="bv-table-container">
                <table class="wp-list-table widefat fixed striped bv-sortable-table" id="bv-doc-reqs-table">
                    <thead>
                        <tr>
                            <th style="width:40px;" class="bv-sort-handle-col"></th>
                            <th><?php esc_html_e( 'Name', 'businessvance-services-manager' ); ?></th>
                            <th><?php esc_html_e( 'Slug', 'businessvance-services-manager' ); ?></th>
                            <th><?php esc_html_e( 'Allowed Types', 'businessvance-services-manager' ); ?></th>
                            <th style="width:70px;"><?php esc_html_e( 'Required', 'businessvance-services-manager' ); ?></th>
                            <th style="width:50px;"><?php esc_html_e( 'Max MB', 'businessvance-services-manager' ); ?></th>
                            <th style="width:60px;"><?php esc_html_e( 'Order', 'businessvance-services-manager' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Services', 'businessvance-services-manager' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Actions', 'businessvance-services-manager' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="bv-doc-reqs-tbody">
                        <tr>
                            <td colspan="9" style="text-align:center; padding:40px;">
                                <span class="spinner is-active" style="float:none;"></span>
                                <?php esc_html_e( 'Loading...', 'businessvance-services-manager' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div id="bv-doc-req-modal" class="bv-modal" style="display:none;">
            <div class="bv-modal-overlay"></div>
            <div class="bv-modal-content" style="max-width:600px;">
                <div class="bv-modal-header">
                    <h2 id="bv-doc-req-modal-title"><?php esc_html_e( 'Add Document Requirement', 'businessvance-services-manager' ); ?></h2>
                    <button type="button" class="bv-modal-close">&times;</button>
                </div>
                <form id="bv-doc-req-form" class="bv-modal-body">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bv_admin_nonce' ) ); ?>">

                    <div class="bv-form-grid">
                        <div class="bv-form-group bv-form-full">
                            <label for="dr-name"><?php esc_html_e( 'Requirement Name *', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="dr-name" name="name" required class="large-text">
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="dr-slug"><?php esc_html_e( 'Slug', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="dr-slug" name="slug" class="large-text" placeholder="<?php esc_attr_e( 'auto-generated from name', 'businessvance-services-manager' ); ?>">
                            <p class="description"><?php esc_html_e( 'URL-friendly identifier. Auto-generated if left blank.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group bv-form-full">
                            <label for="dr-description"><?php esc_html_e( 'Description', 'businessvance-services-manager' ); ?></label>
                            <textarea id="dr-description" name="description" rows="3" class="large-text"></textarea>
                            <p class="description"><?php esc_html_e( 'Brief instructions shown to the client in the portal.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group">
                            <label for="dr-allowed-types"><?php esc_html_e( 'Allowed File Types', 'businessvance-services-manager' ); ?></label>
                            <input type="text" id="dr-allowed-types" name="allowed_types" value="pdf,doc,docx,jpg,jpeg,png" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Comma-separated file extensions (no dots).', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group">
                            <label for="dr-max-size"><?php esc_html_e( 'Max File Size (MB)', 'businessvance-services-manager' ); ?></label>
                            <input type="number" id="dr-max-size" name="max_size_mb" value="10" min="1" max="100" class="small-text">
                        </div>

                        <div class="bv-form-group">
                            <label>
                                <input type="checkbox" name="is_required" value="1" checked>
                                <?php esc_html_e( 'Required', 'businessvance-services-manager' ); ?>
                            </label>
                            <p class="description" style="margin-top:4px;"><?php esc_html_e( 'If unchecked, this document is optional for the client.', 'businessvance-services-manager' ); ?></p>
                        </div>

                        <div class="bv-form-group">
                            <label for="dr-order"><?php esc_html_e( 'Display Order', 'businessvance-services-manager' ); ?></label>
                            <input type="number" id="dr-order" name="display_order" value="0" min="0" class="small-text">
                        </div>
                    </div>

                    <div class="bv-modal-footer">
                        <button type="button" class="button bv-cancel-btn"><?php esc_html_e( 'Cancel', 'businessvance-services-manager' ); ?></button>
                        <button type="submit" class="button button-primary bv-gold-btn"><?php esc_html_e( 'Save Requirement', 'businessvance-services-manager' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}
