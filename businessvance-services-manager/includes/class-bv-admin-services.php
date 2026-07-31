<?php
/**
 * BusinessVance Services Manager - Admin Services
 *
 * Full CRUD management for once-off services.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Admin_Services
 *
 * Handles the admin services management page including list, add, edit, delete,
 * visibility toggle, and reorder.
 */
class BV_Admin_Services {

    /**
     * Available icon options.
     *
     * @var array
     */
    public static $icons = array(
        'FileText', 'FileCheck', 'FileSearch', 'FileSpreadsheet',
        'Briefcase', 'TrendingUp', 'TrendingDown', 'BarChart3',
        'PieChart', 'Target', 'Award', 'Star', 'Heart',
        'Calculator', 'DollarSign', 'Receipt', 'Wallet',
        'Users', 'UserCheck', 'UserPlus', 'Handshake',
        'Lightbulb', 'Puzzle', 'Gear', 'Wrench', 'Settings',
        'Globe', 'MapPin', 'Building2', 'Store',
        'Phone', 'Mail', 'MessageSquare', 'Headphones',
        'Shield', 'Lock', 'Key', 'BadgeCheck',
        'BookOpen', 'GraduationCap', 'Presentation',
        'ClipboardList', 'ListChecks', 'CheckSquare',
        'Rocket', 'Zap', 'Activity', 'Sparkles',
    );

    /**
     * Render the services management page.
     *
     * @return void
     */
    public static function render_page() {
        self::handle_save();
        self::handle_delete();

        $edit_service = null;
        $is_editing   = false;
        $edit_id      = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;

        if ( $edit_id > 0 ) {
            global $wpdb;
            $table       = $wpdb->prefix . 'bv_services';
            $edit_service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $edit_id ) );
            if ( $edit_service ) {
                $is_editing = true;
            }
        }

        if ( $is_editing && $edit_service ) {
            self::render_form( $edit_service );
        } else {
            self::render_list();
        }
    }

    /**
     * Render the services list table.
     *
     * @return void
     */
    private static function render_list() {
        global $wpdb;

        $table          = $wpdb->prefix . 'bv_services';
        $categories_table = $wpdb->prefix . 'bv_categories';

        $services = $wpdb->get_results(
            "SELECT s.*, c.name AS category_name
             FROM $table s
             LEFT JOIN $categories_table c ON s.category_id = c.id
             ORDER BY s.display_order ASC, s.id DESC"
        );
        ?>
        <div class="wrap bv-admin-wrap">
            <h1 class="bv-page-title">
                Services
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-services&edit=0#bv-form' ) ); ?>" class="page-title-action">Add New Service</a>
            </h1>

            <table class="wp-list-table widefat fixed striped bv-sortable-table" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bv_reorder_services' ) ); ?>">
                <thead>
                    <tr>
                        <th style="width:40px;" class="column-order">Order</th>
                        <th>Name</th>
                        <th style="width:140px;">Category</th>
                        <th style="width:90px;">Price</th>
                        <th style="width:90px;">Button</th>
                        <th style="width:60px;">Visible</th>
                        <th style="width:60px;">Feat.</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody class="bv-sortable-rows" data-type="services">
                    <?php if ( empty( $services ) ) : ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px;">No services yet. Click "Add New Service" to create one.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $services as $s ) : ?>
                            <tr data-id="<?php echo esc_attr( $s->id ); ?>">
                                <td class="bv-drag-handle" title="Drag to reorder">⠿</td>
                                <td>
                                    <strong><?php echo esc_html( $s->name ); ?></strong>
                                    <br><small style="color:#666;"><?php echo esc_html( self::truncate( $s->description, 60 ) ); ?></small>
                                </td>
                                <td><?php echo esc_html( $s->category_name ?: '—' ); ?></td>
                                <td>R <?php echo esc_html( number_format( (float) $s->price, 2 ) ); ?></td>
                                <td>
                                    <span class="bv-badge bv-badge-<?php echo esc_attr( $s->button_type ); ?>">
                                        <?php echo esc_html( strtoupper( $s->button_type ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bv-services&action=bv_toggle_service_visibility&id=' . absint( $s->id ) ), 'bv_toggle_visibility_' . $s->id ) ); ?>"
                                       class="bv-toggle-btn <?php echo $s->visible ? 'bv-toggle-on' : 'bv-toggle-off'; ?>"
                                       title="Toggle visibility">
                                        <?php echo $s->visible ? '👁' : '🚫'; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ( $s->featured ) : ?>
                                        <span title="Featured">⭐</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-services&edit=' . absint( $s->id ) ) ); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bv-services&action=bv_delete_service&id=' . absint( $s->id ) ), 'bv_delete_service_' . $s->id ) ); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Delete this service?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the add/edit service form.
     *
     * @param object|null $service The service object when editing, null for new.
     * @return void
     */
    private static function render_form( $service ) {
        global $wpdb;

        $categories_table = $wpdb->prefix . 'bv_categories';
        $categories = $wpdb->get_results( "SELECT * FROM $categories_table ORDER BY name ASC" );

        $is_edit   = ! is_null( $service );
        $form_data = $is_edit ? $service : (object) array(
            'id'                   => 0,
            'name'                 => '',
            'slug'                 => '',
            'description'          => '',
            'price'                => 0,
            'icon'                 => 'FileText',
            'button_label'         => 'ADD TO CART',
            'button_type'          => 'cart',
            'button_url'           => '',
            'woocommerce_product_id' => '',
            'category_id'          => null,
            'visible'              => 1,
            'featured'             => 0,
            'display_order'        => 0,
        );

        // Build icon options HTML.
        $icon_options = '';
        foreach ( self::$icons as $icon ) {
            $selected = ( $form_data->icon === $icon ) ? ' selected' : '';
            $icon_options .= '<option value="' . esc_attr( $icon ) . '"' . $selected . '>' . esc_html( $icon ) . '</option>';
        }

        // Build category options HTML.
        $cat_options = '<option value="">— None —</option>';
        foreach ( $categories as $cat ) {
            $selected = ( $form_data->category_id == $cat->id ) ? ' selected' : '';
            $cat_options .= '<option value="' . esc_attr( $cat->id ) . '"' . $selected . '>' . esc_html( $cat->name ) . '</option>';
        }
        ?>
        <div class="wrap bv-admin-wrap" id="bv-form">
            <h1 class="bv-page-title">
                <?php echo $is_edit ? esc_html__( 'Edit Service', 'businessvance' ) : esc_html__( 'Add New Service', 'businessvance' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-services' ) ); ?>" class="page-title-action">← Back to list</a>
            </h1>

            <form method="post" action="" class="bv-form-card">
                <?php wp_nonce_field( 'bv_save_service', 'bv_service_nonce' ); ?>
                <input type="hidden" name="bv_action" value="bv_save_service">
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="service_id" value="<?php echo esc_attr( $form_data->id ); ?>">
                <?php endif; ?>

                <div class="bv-form-grid">
                    <div class="bv-form-main">
                        <div class="bv-field">
                            <label for="svc-name">Service Name <span class="required">*</span></label>
                            <input type="text" id="svc-name" name="name" value="<?php echo esc_attr( $form_data->name ); ?>" required class="large-text" placeholder="e.g. Business Plan Development">
                        </div>

                        <div class="bv-field">
                            <label for="svc-slug">Slug</label>
                            <input type="text" id="svc-slug" name="slug" value="<?php echo esc_attr( $form_data->slug ); ?>" class="large-text" placeholder="Auto-generated from name if blank">
                        </div>

                        <div class="bv-field">
                            <label for="svc-desc">Description</label>
                            <textarea id="svc-desc" name="description" rows="4" class="large-text" placeholder="Brief description of this service..."><?php echo esc_textarea( $form_data->description ); ?></textarea>
                        </div>

                        <div class="bv-field-row">
                            <div class="bv-field" style="flex:1;">
                                <label for="svc-price">Price (ZAR) <span class="required">*</span></label>
                                <input type="number" id="svc-price" name="price" value="<?php echo esc_attr( $form_data->price ); ?>" required step="0.01" min="0" class="regular-text" placeholder="0.00">
                            </div>
                            <div class="bv-field" style="flex:1;">
                                <label for="svc-order">Display Order</label>
                                <input type="number" id="svc-order" name="display_order" value="<?php echo esc_attr( $form_data->display_order ); ?>" min="0" class="small-text">
                            </div>
                        </div>
                    </div>

                    <div class="bv-form-sidebar">
                        <div class="bv-field">
                            <label for="svc-icon">Icon</label>
                            <select id="svc-icon" name="icon"><?php echo $icon_options; ?></select>
                        </div>

                        <div class="bv-field">
                            <label for="svc-category">Category</label>
                            <select id="svc-category" name="category_id"><?php echo $cat_options; ?></select>
                        </div>

                        <div class="bv-field">
                            <label for="svc-button-label">Button Label</label>
                            <input type="text" id="svc-button-label" name="button_label" value="<?php echo esc_attr( $form_data->button_label ); ?>" class="regular-text">
                        </div>

                        <div class="bv-field">
                            <label for="svc-button-type">Button Type</label>
                            <select id="svc-button-type" name="button_type">
                                <option value="cart" <?php selected( $form_data->button_type, 'cart' ); ?>>Add to Cart</option>
                                <option value="quote" <?php selected( $form_data->button_type, 'quote' ); ?>>Request Quote</option>
                                <option value="booking" <?php selected( $form_data->button_type, 'booking' ); ?>>Book Consultation</option>
                                <option value="link" <?php selected( $form_data->button_type, 'link' ); ?>>External Link</option>
                            </select>
                        </div>

                        <div class="bv-field" id="svc-url-field" style="<?php echo $form_data->button_type === 'link' ? '' : 'display:none;'; ?>">
                            <label for="svc-button-url">Button URL</label>
                            <input type="url" id="svc-button-url" name="button_url" value="<?php echo esc_url( $form_data->button_url ); ?>" class="regular-text" placeholder="https://...">
                        </div>

                        <div class="bv-field">
                            <label for="svc-wc-product">WooCommerce Product ID</label>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input type="text" id="svc-wc-product" name="woocommerce_product_id" value="<?php echo esc_attr( $form_data->woocommerce_product_id ); ?>" class="regular-text" placeholder="e.g. 42">
                                <button type="button" class="button bv-browse-products" data-target="svc-wc-product">Browse</button>
                            </div>
                            <p class="description">Used for "Add to Cart" button type.</p>
                        </div>

                        <div class="bv-field bv-field-checkbox">
                            <label><input type="checkbox" name="visible" value="1" <?php checked( $form_data->visible, 1 ); ?>> Visible</label>
                        </div>

                        <div class="bv-field bv-field-checkbox">
                            <label><input type="checkbox" name="featured" value="1" <?php checked( $form_data->featured, 1 ); ?>> Featured</label>
                        </div>
                    </div>
                </div>

                <div class="bv-form-actions">
                    <?php submit_button( $is_edit ? 'Update Service' : 'Add Service', 'primary', 'bv_submit', false ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-services' ) ); ?>" class="button">Cancel</a>
                </div>
            </form>
        </div>

        <!-- WooCommerce Product Browse Modal -->
        <div id="bv-wc-modal" style="display:none;">
            <div class="bv-wc-modal-backdrop"></div>
            <div class="bv-wc-modal-content">
                <div class="bv-wc-modal-header">
                    <h2>Select WooCommerce Product</h2>
                    <button type="button" class="bv-wc-modal-close">&times;</button>
                </div>
                <div class="bv-wc-modal-body">
                    <input type="text" id="bv-wc-search" class="regular-text" placeholder="Search products..." style="width:100%; margin-bottom:15px;">
                    <div id="bv-wc-results" style="max-height:400px; overflow-y:auto;">
                        <p class="bv-wc-loading" style="text-align:center; padding:40px;">Searching...</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle save (create/update) service.
     *
     * @return void
     */
    private static function handle_save() {
        if ( ! isset( $_POST['bv_action'] ) || $_POST['bv_action'] !== 'bv_save_service' ) {
            return;
        }

        if ( ! isset( $_POST['bv_service_nonce'] ) || ! wp_verify_nonce( $_POST['bv_service_nonce'], 'bv_save_service' ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_services';

        $name     = sanitize_text_field( wp_unslash( $_POST['name'] ) );
        $slug     = ! empty( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title( $name );
        $desc     = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
        $price    = ! empty( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;
        $icon     = sanitize_text_field( wp_unslash( $_POST['icon'] ?? 'FileText' ) );
        $btn_label = sanitize_text_field( wp_unslash( $_POST['button_label'] ?? 'ADD TO CART' ) );
        $btn_type  = in_array( $_POST['button_type'] ?? 'cart', array( 'cart', 'quote', 'booking', 'link' ), true )
                     ? $_POST['button_type'] : 'cart';
        $btn_url   = esc_url_raw( wp_unslash( $_POST['button_url'] ?? '' ) );
        $wc_id     = sanitize_text_field( wp_unslash( $_POST['woocommerce_product_id'] ?? '' ) );
        $cat_id    = ! empty( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : null;
        $visible   = isset( $_POST['visible'] ) ? 1 : 0;
        $featured  = isset( $_POST['featured'] ) ? 1 : 0;
        $order     = absint( $_POST['display_order'] ?? 0 );

        if ( empty( $name ) ) {
            wp_admin_notice( 'Service name is required.', array( 'type' => 'error' ) );
            return;
        }

        $edit_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

        $data = array(
            'name'                  => $name,
            'slug'                  => $slug,
            'description'           => $desc,
            'price'                 => $price,
            'icon'                  => $icon,
            'button_label'          => $btn_label,
            'button_type'           => $btn_type,
            'button_url'            => $btn_url,
            'woocommerce_product_id' => $wc_id,
            'category_id'           => $cat_id,
            'visible'               => $visible,
            'featured'              => $featured,
            'display_order'         => $order,
        );
        $format = array( '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' );

        if ( $edit_id > 0 ) {
            $wpdb->update( $table, $data, array( 'id' => $edit_id ), $format, array( '%d' ) );
            wp_admin_notice( 'Service updated successfully.', array( 'type' => 'success' ) );
        } else {
            // Check slug uniqueness.
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) );
            if ( $exists ) {
                $slug .= '-' . wp_rand( 10, 99 );
                $data['slug'] = $slug;
            }
            $wpdb->insert( $table, $data, $format );
            $edit_id = $wpdb->insert_id;
            wp_admin_notice( 'Service created successfully.', array( 'type' => 'success' ) );
        }

        // Sync WooCommerce product meta.
        if ( ! empty( $wc_id ) && function_exists( 'wc_get_product' ) ) {
            update_post_meta( $wc_id, '_bv_service_id', $edit_id );
        }
    }

    /**
     * Handle delete service.
     *
     * @return void
     */
    private static function handle_delete() {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'bv_delete_service' ) {
            return;
        }

        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( $id <= 0 ) {
            return;
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'bv_delete_service_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_services';

        // Clean up WC meta if linked.
        $wc_id = $wpdb->get_var( $wpdb->prepare( "SELECT woocommerce_product_id FROM $table WHERE id = %d", $id ) );
        if ( ! empty( $wc_id ) ) {
            delete_post_meta( $wc_id, '_bv_service_id' );
        }

        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        wp_safe_redirect( admin_url( 'admin.php?page=bv-services&bv_deleted=1' ) );
        exit;
    }

    /**
     * Handle visibility toggle.
     *
     * @return void
     */
    public static function handle_visibility_toggle() {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'bv_toggle_service_visibility' ) {
            return;
        }

        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( $id <= 0 ) {
            return;
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'bv_toggle_visibility_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_services';

        $wpdb->query(
            $wpdb->prepare( "UPDATE $table SET visible = IF(visible = 1, 0, 1) WHERE id = %d", $id )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=bv-services' ) );
        exit;
    }

    /**
     * Truncate a string for preview display.
     *
     * @param string $str  The string.
     * @param int    $len  Max length.
     * @return string
     */
    private static function truncate( $str, $len = 60 ) {
        if ( empty( $str ) ) {
            return '';
        }
        return strlen( $str ) > $len ? substr( $str, 0, $len ) . '...' : $str;
    }
}