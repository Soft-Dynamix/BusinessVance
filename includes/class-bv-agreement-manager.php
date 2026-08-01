<?php
/**
 * BusinessVance Agreement Manager
 *
 * Admin page for managing agreement and NDA templates.
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
            true
        );

        wp_localize_script( 'bv-admin-js', 'bvAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bv_admin_nonce' ),
            'strings'  => array(
                'confirm_delete' => __( 'Are you sure you want to delete this agreement template?', 'businessvance-services-manager' ),
                'saving'         => __( 'Saving...', 'businessvance-services-manager' ),
                'saved'          => __( 'Saved successfully!', 'businessvance-services-manager' ),
                'error'          => __( 'An error occurred. Please try again.', 'businessvance-services-manager' ),
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
                <button type="button" class="button button-primary bv-gold-btn" id="bv-add-agreement">
                    <?php esc_html_e( '+ Add Template', 'businessvance-services-manager' ); ?>
                </button>
            </div>

            <?php if ( empty( $templates ) ) : ?>
                <div class="bv-table-container" style="text-align:center; padding:60px 20px;">
                    <p style="font-size:16px; color:#666;">
                        <?php esc_html_e( 'No agreement templates found. Click "Add Template" to create your first agreement or NDA template.', 'businessvance-services-manager' ); ?>
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
                $modal.hide();
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

            // Close on Escape.
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $modal.is(':visible')) {
                    $modal.hide();
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
