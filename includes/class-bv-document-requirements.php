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

        wp_localize_script( 'bv-admin-js', 'bvAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'page'     => 'documents',
            'strings'  => array(
                'confirm_delete' => __( 'Are you sure you want to delete this document requirement?', 'businessvance-services-manager' ),
                'saving'        => __( 'Saving...', 'businessvance-services-manager' ),
                'saved'         => __( 'Saved successfully!', 'businessvance-services-manager' ),
                'error'         => __( 'An error occurred. Please try again.', 'businessvance-services-manager' ),
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
            $max_order = (int) $wpdb->get_var( "SELECT COALESCE(MAX(display_order), 0) FROM {$this->get_table()}" );
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

        foreach ( $order as $position => $id ) {
            $wpdb->update(
                $this->get_table(),
                array( 'display_order' => intval( $position ) ),
                array( 'id' => intval( $id ) ),
                array( '%d' ),
                array( '%d' )
            );
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

        <script>
        (function($) {
            'use strict';
            if (typeof bvAdmin === 'undefined') return;

            var ajaxUrl = bvAdmin.ajax_url;
            var nonce   = bvAdmin.nonce;
            var strings = bvAdmin.strings;

            // Load table data
            function loadDocReqs() {
                $.post(ajaxUrl, { action: 'bv_get_document_requirements', nonce: nonce }, function(res) {
                    if (!res.success) return;
                    var rows = res.data;
                    var $tbody = $('#bv-doc-reqs-tbody').empty();

                    if (!rows || rows.length === 0) {
                        $tbody.html('<tr><td colspan="9" style="text-align:center; padding:40px; color:#999;"><?php echo esc_js( 'No document requirements yet. Click "+"Add Requirement" to create one.', 'businessvance-services-manager' ); ?></td></tr>');
                        return;
                    }

                    rows.forEach(function(r) {
                        var typesHtml = r.allowed_types.split(',').map(function(t) {
                            return '<code style="font-size:11px;background:#f0f0f1;padding:1px 5px;border-radius:3px;">' + $('<span>').text(t.trim()).html() + '</code>';
                        }).join(' ');

                        var reqLabel = r.is_required == 1
                            ? '<span style="color:#00a32a;font-weight:600;">✓ Yes</span>'
                            : '<span style="color:#999;">No</span>';

                        var svcCount = parseInt(r.service_count) || 0;
                        var svcHtml = svcCount > 0
                            ? '<a href="<?php echo esc_url( admin_url( 'admin.php?page=businessvance-services' ) ); ?>" title="<?php echo esc_attr( 'View services', 'businessvance-services-manager' ); ?>">' + svcCount + '</a>'
                            : '<span style="color:#999;">0</span>';

                        $tbody.append(
                            '<tr data-id="' + r.id + '">' +
                                '<td class="bv-sort-handle-col"><span class="bv-sort-handle" title="Drag to reorder">☰</span></td>' +
                                '<td><strong>' + $('<span>').text(r.name).html() + '</strong></td>' +
                                '<td><code style="font-size:12px;">' + $('<span>').text(r.slug).html() + '</code></td>' +
                                '<td>' + typesHtml + '</td>' +
                                '<td>' + reqLabel + '</td>' +
                                '<td>' + r.max_size_mb + '</td>' +
                                '<td>' + r.display_order + '</td>' +
                                '<td>' + svcHtml + '</td>' +
                                '<td>' +
                                    '<button type="button" class="button button-small bv-edit-doc-req" data-id="' + r.id + '"><?php echo esc_js( 'Edit', 'businessvance-services-manager' ); ?></button> ' +
                                    '<button type="button" class="button button-small bv-delete-doc-req" data-id="' + r.id + '"><?php echo esc_js( 'Delete', 'businessvance-services-manager' ); ?></button>' +
                                '</td>' +
                            '</tr>'
                        );
                    });

                    // Init sortable
                    initSortable();
                });
            }

            // Sortable
            function initSortable() {
                if ($.fn.sortable) {
                    $('#bv-doc-reqs-table tbody').sortable({
                        handle: '.bv-sort-handle',
                        axis: 'y',
                        update: function() {
                            var order = [];
                            $(this).find('tr').each(function() { order.push($(this).data('id')); });
                            $.post(ajaxUrl, { action: 'bv_reorder_document_requirements', nonce: nonce, order: order });
                        }
                    });
                }
            }

            // Open modal
            function openDocReqModal(title) {
                $('#bv-doc-req-modal-title').text(title);
                $('#bv-doc-req-modal').show();
                $('body').css('overflow', 'hidden');
            }

            // Close modal
            function closeDocReqModal() {
                $('#bv-doc-req-modal').hide();
                $('body').css('overflow', '');
            }

            // Add button
            $(document).on('click', '#bv-add-doc-req', function() {
                $('#bv-doc-req-form')[0].reset();
                $('#bv-doc-req-form input[name="id"]').val('');
                $('#bv-doc-req-form input[name="is_required"]').prop('checked', true);
                $('#bv-doc-req-form input[name="max_size_mb"]').val('10');
                $('#bv-doc-req-form input[name="allowed_types"]').val('pdf,doc,docx,jpg,jpeg,png');
                $('#bv-doc-req-form input[name="display_order"]').val('0');
                openDocReqModal('<?php echo esc_js( 'Add Document Requirement', 'businessvance-services-manager' ); ?>');
            });

            // Edit button
            $(document).on('click', '.bv-edit-doc-req', function() {
                var id = $(this).data('id');
                $.post(ajaxUrl, { action: 'bv_get_document_requirement', nonce: nonce, id: id }, function(res) {
                    if (!res.success) { alert(res.data.message || strings.error); return; }
                    var r = res.data;
                    $('#bv-doc-req-form input[name="id"]').val(r.id);
                    $('#bv-doc-req-form input[name="name"]').val(r.name);
                    $('#bv-doc-req-form input[name="slug"]').val(r.slug);
                    $('#bv-doc-req-form textarea[name="description"]').val(r.description);
                    $('#bv-doc-req-form input[name="allowed_types"]').val(r.allowed_types);
                    $('#bv-doc-req-form input[name="max_size_mb"]').val(r.max_size_mb);
                    $('#bv-doc-req-form input[name="is_required"]').prop('checked', r.is_required == 1);
                    $('#bv-doc-req-form input[name="display_order"]').val(r.display_order);
                    openDocReqModal('<?php echo esc_js( 'Edit Document Requirement', 'businessvance-services-manager' ); ?>');
                });
            });

            // Delete button
            $(document).on('click', '.bv-delete-doc-req', function() {
                if (!confirm(strings.confirm_delete)) return;
                var id = $(this).data('id');
                $.post(ajaxUrl, { action: 'bv_delete_document_requirement', nonce: nonce, id: id }, function(res) {
                    if (res.success) { location.reload(); } else { alert(res.data.message || strings.error); }
                });
            });

            // Save form
            $(document).on('submit', '#bv-doc-req-form', function(e) {
                e.preventDefault();
                var $btn = $(this).find('.bv-gold-btn');
                var origText = $btn.text();
                $btn.text(strings.saving).prop('disabled', true);

                var formData = $(this).serialize() + '&action=bv_save_document_requirement';
                $.post(ajaxUrl, formData, function(res) {
                    if (res.success) { alert(strings.saved); location.reload(); }
                    else { alert(res.data.message || strings.error); $btn.text(origText).prop('disabled', false); }
                }).fail(function() { alert(strings.error); $btn.text(origText).prop('disabled', false); });
            });

            // Close modal handlers
            $(document).on('click', '#bv-doc-req-modal .bv-modal-overlay, #bv-doc-req-modal .bv-modal-close, #bv-doc-req-modal .bv-cancel-btn', function() {
                closeDocReqModal();
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') closeDocReqModal();
            });

            // Auto-generate slug from name
            $(document).on('input', '#dr-name', function() {
                if ($('#dr-slug').data('edited')) return;
                $('#dr-slug').val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
            });
            $(document).on('input', '#dr-slug', function() {
                $(this).data('edited', true);
            });

            // Initial load
            loadDocReqs();
        })(jQuery);
        </script>
        <?php
    }
}
