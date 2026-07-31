<?php
/**
 * BusinessVance Services Manager - Admin Plans
 *
 * Full CRUD management for monthly subscription plans including features.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Admin_Plans
 *
 * Handles the admin plans management page.
 */
class BV_Admin_Plans {

    /**
     * Render the plans management page.
     *
     * @return void
     */
    public static function render_page() {
        self::handle_save();
        self::handle_delete();

        $edit_plan  = null;
        $is_editing = false;
        $edit_id    = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;

        if ( $edit_id > 0 ) {
            global $wpdb;
            $table     = $wpdb->prefix . 'bv_plans';
            $edit_plan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $edit_id ) );
            if ( $edit_plan ) {
                $is_editing = true;
            }
        }

        if ( $is_editing && $edit_plan ) {
            self::render_form( $edit_plan );
        } else {
            self::render_list();
        }
    }

    /**
     * Render the plans list table.
     *
     * @return void
     */
    private static function render_list() {
        global $wpdb;

        $table          = $wpdb->prefix . 'bv_plans';
        $categories_table = $wpdb->prefix . 'bv_categories';
        $features_table = $wpdb->prefix . 'bv_plan_features';

        $plans = $wpdb->get_results(
            "SELECT p.*, c.name AS category_name,
                (SELECT COUNT(*) FROM $features_table f WHERE f.plan_id = p.id) AS feature_count
             FROM $table p
             LEFT JOIN $categories_table c ON p.category_id = c.id
             ORDER BY p.display_order ASC, p.id DESC"
        );
        ?>
        <div class="wrap bv-admin-wrap">
            <h1 class="bv-page-title">
                Plans
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-plans&edit=0#bv-form' ) ); ?>" class="page-title-action">Add New Plan</a>
            </h1>

            <table class="wp-list-table widefat fixed striped bv-sortable-table" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bv_reorder_plans' ) ); ?>">
                <thead>
                    <tr>
                        <th style="width:40px;" class="column-order">Order</th>
                        <th>Color</th>
                        <th>Name</th>
                        <th style="width:90px;">Price/mo</th>
                        <th style="width:60px;">Features</th>
                        <th style="width:80px;">Button</th>
                        <th style="width:60px;">Visible</th>
                        <th style="width:60px;">Feat.</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody class="bv-sortable-rows" data-type="plans">
                    <?php if ( empty( $plans ) ) : ?>
                        <tr><td colspan="9" style="text-align:center; padding:40px;">No plans yet. Click "Add New Plan" to create one.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $plans as $p ) : ?>
                            <tr data-id="<?php echo esc_attr( $p->id ); ?>">
                                <td class="bv-drag-handle" title="Drag to reorder">⠿</td>
                                <td><span style="display:inline-block;width:22px;height:22px;border-radius:50%;background:<?php echo esc_attr( $p->color ); ?>;"></span></td>
                                <td>
                                    <strong><?php echo esc_html( $p->name ); ?></strong>
                                    <?php if ( $p->subtitle ) : ?>
                                        <br><small style="color:#666;"><?php echo esc_html( $p->subtitle ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>R <?php echo esc_html( number_format( (float) $p->price, 2 ) ); ?></td>
                                <td><?php echo esc_html( $p->feature_count ); ?></td>
                                <td>
                                    <span class="bv-badge bv-badge-<?php echo esc_attr( $p->button_type ); ?>">
                                        <?php echo esc_html( strtoupper( $p->button_type ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bv-plans&action=bv_toggle_plan_visibility&id=' . absint( $p->id ) ), 'bv_toggle_visibility_' . $p->id ) ); ?>"
                                       class="bv-toggle-btn <?php echo $p->visible ? 'bv-toggle-on' : 'bv-toggle-off'; ?>"
                                       title="Toggle visibility">
                                        <?php echo $p->visible ? '👁' : '🚫'; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ( $p->featured ) : ?>
                                        <span title="Featured">⭐</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-plans&edit=' . absint( $p->id ) ) ); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bv-plans&action=bv_delete_plan&id=' . absint( $p->id ) ), 'bv_delete_plan_' . $p->id ) ); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Delete this plan and its features?');">Delete</a>
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
     * Render the add/edit plan form.
     *
     * @param object $plan The plan object.
     * @return void
     */
    private static function render_form( $plan ) {
        global $wpdb;

        $categories_table = $wpdb->prefix . 'bv_categories';
        $categories = $wpdb->get_results( "SELECT * FROM $categories_table ORDER BY name ASC" );

        $is_edit   = true;
        $form_data = $plan;

        // Fetch existing features.
        $features_table = $wpdb->prefix . 'bv_plan_features';
        $features       = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $features_table WHERE plan_id = %d ORDER BY id ASC", $plan->id )
        );

        // Build category options.
        $cat_options = '<option value="">— None —</option>';
        foreach ( $categories as $cat ) {
            $selected = ( $form_data->category_id == $cat->id ) ? ' selected' : '';
            $cat_options .= '<option value="' . esc_attr( $cat->id ) . '"' . $selected . '>' . esc_html( $cat->name ) . '</option>';
        }
        ?>
        <div class="wrap bv-admin-wrap" id="bv-form">
            <h1 class="bv-page-title">
                Edit Plan
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-plans' ) ); ?>" class="page-title-action">← Back to list</a>
            </h1>

            <form method="post" action="" class="bv-form-card">
                <?php wp_nonce_field( 'bv_save_plan', 'bv_plan_nonce' ); ?>
                <input type="hidden" name="bv_action" value="bv_save_plan">
                <input type="hidden" name="plan_id" value="<?php echo esc_attr( $form_data->id ); ?>">

                <div class="bv-form-grid">
                    <div class="bv-form-main">
                        <div class="bv-field-row">
                            <div class="bv-field" style="flex:2;">
                                <label for="plan-name">Plan Name <span class="required">*</span></label>
                                <input type="text" id="plan-name" name="name" value="<?php echo esc_attr( $form_data->name ); ?>" required class="large-text" placeholder="e.g. Starter Plan">
                            </div>
                            <div class="bv-field" style="flex:1;">
                                <label for="plan-color">Color</label>
                                <input type="color" id="plan-color" name="color" value="<?php echo esc_attr( $form_data->color ); ?>" style="width:100%; height:38px; border:none; cursor:pointer; border-radius:4px;">
                            </div>
                        </div>

                        <div class="bv-field">
                            <label for="plan-subtitle">Subtitle</label>
                            <input type="text" id="plan-subtitle" name="subtitle" value="<?php echo esc_attr( $form_data->subtitle ); ?>" class="large-text" placeholder="e.g. Perfect for small businesses">
                        </div>

                        <div class="bv-field-row">
                            <div class="bv-field" style="flex:1;">
                                <label for="plan-price">Monthly Price (ZAR) <span class="required">*</span></label>
                                <input type="number" id="plan-price" name="price" value="<?php echo esc_attr( $form_data->price ); ?>" required step="0.01" min="0" class="regular-text" placeholder="0.00">
                            </div>
                            <div class="bv-field" style="flex:1;">
                                <label for="plan-order">Display Order</label>
                                <input type="number" id="plan-order" name="display_order" value="<?php echo esc_attr( $form_data->display_order ); ?>" min="0" class="small-text">
                            </div>
                        </div>

                        <!-- Features Section -->
                        <div class="bv-field">
                            <label>Plan Features</label>
                            <div id="bv-features-list">
                                <?php foreach ( $features as $i => $f ) : ?>
                                    <div class="bv-feature-row">
                                        <input type="text" name="features[]" value="<?php echo esc_attr( $f->text ); ?>" class="regular-text" placeholder="Feature description">
                                        <button type="button" class="button bv-remove-feature" title="Remove feature">−</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button" id="bv-add-feature" style="margin-top:8px;">+ Add Feature</button>
                        </div>
                    </div>

                    <div class="bv-form-sidebar">
                        <div class="bv-field">
                            <label for="plan-category">Category</label>
                            <select id="plan-category" name="category_id"><?php echo $cat_options; ?></select>
                        </div>

                        <div class="bv-field">
                            <label for="plan-button-label">Button Label</label>
                            <input type="text" id="plan-button-label" name="button_label" value="<?php echo esc_attr( $form_data->button_label ); ?>" class="regular-text">
                        </div>

                        <div class="bv-field">
                            <label for="plan-button-type">Button Type</label>
                            <select id="plan-button-type" name="button_type">
                                <option value="cart" <?php selected( $form_data->button_type, 'cart' ); ?>>Add to Cart</option>
                                <option value="quote" <?php selected( $form_data->button_type, 'quote' ); ?>>Request Quote</option>
                                <option value="booking" <?php selected( $form_data->button_type, 'booking' ); ?>>Book Consultation</option>
                                <option value="link" <?php selected( $form_data->button_type, 'link' ); ?>>External Link</option>
                            </select>
                        </div>

                        <div class="bv-field" id="plan-url-field" style="<?php echo $form_data->button_type === 'link' ? '' : 'display:none;'; ?>">
                            <label for="plan-button-url">Button URL</label>
                            <input type="url" id="plan-button-url" name="button_url" value="<?php echo esc_url( $form_data->button_url ); ?>" class="regular-text" placeholder="https://...">
                        </div>

                        <div class="bv-field">
                            <label for="plan-wc-product">WooCommerce Product ID</label>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input type="text" id="plan-wc-product" name="woocommerce_product_id" value="<?php echo esc_attr( $form_data->woocommerce_product_id ); ?>" class="regular-text" placeholder="e.g. 42">
                                <button type="button" class="button bv-browse-products" data-target="plan-wc-product">Browse</button>
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
                    <?php submit_button( 'Update Plan', 'primary', 'bv_submit', false ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-plans' ) ); ?>" class="button">Cancel</a>
                </div>
            </form>
        </div>

        <!-- WooCommerce Product Browse Modal (shared) -->
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
     * Render the "Add New Plan" form (no pre-existing plan).
     *
     * @return void
     */
    public static function render_new_form() {
        global $wpdb;

        $categories_table = $wpdb->prefix . 'bv_categories';
        $categories = $wpdb->get_results( "SELECT * FROM $categories_table ORDER BY name ASC" );

        $form_data = (object) array(
            'id'                    => 0,
            'name'                  => '',
            'subtitle'              => '',
            'price'                 => 0,
            'color'                 => '#002B5C',
            'button_label'          => 'GET STARTED',
            'button_type'           => 'cart',
            'button_url'            => '',
            'woocommerce_product_id' => '',
            'category_id'           => null,
            'visible'               => 1,
            'featured'              => 0,
            'display_order'         => 0,
        );

        // Build category options.
        $cat_options = '<option value="">— None —</option>';
        foreach ( $categories as $cat ) {
            $cat_options .= '<option value="' . esc_attr( $cat->id ) . '">' . esc_html( $cat->name ) . '</option>';
        }
        ?>
        <div class="wrap bv-admin-wrap" id="bv-form">
            <h1 class="bv-page-title">
                Add New Plan
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-plans' ) ); ?>" class="page-title-action">← Back to list</a>
            </h1>

            <form method="post" action="" class="bv-form-card">
                <?php wp_nonce_field( 'bv_save_plan', 'bv_plan_nonce' ); ?>
                <input type="hidden" name="bv_action" value="bv_save_plan">

                <div class="bv-form-grid">
                    <div class="bv-form-main">
                        <div class="bv-field-row">
                            <div class="bv-field" style="flex:2;">
                                <label for="plan-name">Plan Name <span class="required">*</span></label>
                                <input type="text" id="plan-name" name="name" value="" required class="large-text" placeholder="e.g. Starter Plan">
                            </div>
                            <div class="bv-field" style="flex:1;">
                                <label for="plan-color">Color</label>
                                <input type="color" id="plan-color" name="color" value="#002B5C" style="width:100%; height:38px; border:none; cursor:pointer; border-radius:4px;">
                            </div>
                        </div>

                        <div class="bv-field">
                            <label for="plan-subtitle">Subtitle</label>
                            <input type="text" id="plan-subtitle" name="subtitle" value="" class="large-text" placeholder="e.g. Perfect for small businesses">
                        </div>

                        <div class="bv-field-row">
                            <div class="bv-field" style="flex:1;">
                                <label for="plan-price">Monthly Price (ZAR) <span class="required">*</span></label>
                                <input type="number" id="plan-price" name="price" value="0" required step="0.01" min="0" class="regular-text" placeholder="0.00">
                            </div>
                            <div class="bv-field" style="flex:1;">
                                <label for="plan-order">Display Order</label>
                                <input type="number" id="plan-order" name="display_order" value="0" min="0" class="small-text">
                            </div>
                        </div>

                        <!-- Features Section -->
                        <div class="bv-field">
                            <label>Plan Features</label>
                            <div id="bv-features-list">
                                <div class="bv-feature-row">
                                    <input type="text" name="features[]" value="" class="regular-text" placeholder="Feature description">
                                    <button type="button" class="button bv-remove-feature" title="Remove feature">−</button>
                                </div>
                            </div>
                            <button type="button" class="button" id="bv-add-feature" style="margin-top:8px;">+ Add Feature</button>
                        </div>
                    </div>

                    <div class="bv-form-sidebar">
                        <div class="bv-field">
                            <label for="plan-category">Category</label>
                            <select id="plan-category" name="category_id"><?php echo $cat_options; ?></select>
                        </div>

                        <div class="bv-field">
                            <label for="plan-button-label">Button Label</label>
                            <input type="text" id="plan-button-label" name="button_label" value="GET STARTED" class="regular-text">
                        </div>

                        <div class="bv-field">
                            <label for="plan-button-type">Button Type</label>
                            <select id="plan-button-type" name="button_type">
                                <option value="cart" selected>Add to Cart</option>
                                <option value="quote">Request Quote</option>
                                <option value="booking">Book Consultation</option>
                                <option value="link">External Link</option>
                            </select>
                        </div>

                        <div class="bv-field" id="plan-url-field" style="display:none;">
                            <label for="plan-button-url">Button URL</label>
                            <input type="url" id="plan-button-url" name="button_url" value="" class="regular-text" placeholder="https://...">
                        </div>

                        <div class="bv-field">
                            <label for="plan-wc-product">WooCommerce Product ID</label>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input type="text" id="plan-wc-product" name="woocommerce_product_id" value="" class="regular-text" placeholder="e.g. 42">
                                <button type="button" class="button bv-browse-products" data-target="plan-wc-product">Browse</button>
                            </div>
                            <p class="description">Used for "Add to Cart" button type.</p>
                        </div>

                        <div class="bv-field bv-field-checkbox">
                            <label><input type="checkbox" name="visible" value="1" checked> Visible</label>
                        </div>

                        <div class="bv-field bv-field-checkbox">
                            <label><input type="checkbox" name="featured" value="1"> Featured</label>
                        </div>
                    </div>
                </div>

                <div class="bv-form-actions">
                    <?php submit_button( 'Add Plan', 'primary', 'bv_submit', false ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-plans' ) ); ?>" class="button">Cancel</a>
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
     * Handle save (create/update) plan and its features.
     *
     * @return void
     */
    private static function handle_save() {
        if ( ! isset( $_POST['bv_action'] ) || $_POST['bv_action'] !== 'bv_save_plan' ) {
            return;
        }

        if ( ! isset( $_POST['bv_plan_nonce'] ) || ! wp_verify_nonce( $_POST['bv_plan_nonce'], 'bv_save_plan' ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $table          = $wpdb->prefix . 'bv_plans';
        $features_table = $wpdb->prefix . 'bv_plan_features';

        $name      = sanitize_text_field( wp_unslash( $_POST['name'] ) );
        $subtitle  = sanitize_text_field( wp_unslash( $_POST['subtitle'] ?? '' ) );
        $price     = ! empty( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;
        $color     = ! empty( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#002B5C';
        $btn_label = sanitize_text_field( wp_unslash( $_POST['button_label'] ?? 'GET STARTED' ) );
        $btn_type  = in_array( $_POST['button_type'] ?? 'cart', array( 'cart', 'quote', 'booking', 'link' ), true )
                     ? $_POST['button_type'] : 'cart';
        $btn_url   = esc_url_raw( wp_unslash( $_POST['button_url'] ?? '' ) );
        $wc_id     = sanitize_text_field( wp_unslash( $_POST['woocommerce_product_id'] ?? '' ) );
        $cat_id    = ! empty( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : null;
        $visible   = isset( $_POST['visible'] ) ? 1 : 0;
        $featured  = isset( $_POST['featured'] ) ? 1 : 0;
        $order     = absint( $_POST['display_order'] ?? 0 );

        // Features from the dynamic form.
        $features_input = isset( $_POST['features'] ) && is_array( $_POST['features'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['features'] ) )
            : array();

        if ( empty( $name ) ) {
            wp_admin_notice( 'Plan name is required.', array( 'type' => 'error' ) );
            return;
        }

        $edit_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;

        $data = array(
            'name'                  => $name,
            'subtitle'              => $subtitle,
            'price'                 => $price,
            'color'                 => $color,
            'button_label'          => $btn_label,
            'button_type'           => $btn_type,
            'button_url'            => $btn_url,
            'woocommerce_product_id' => $wc_id,
            'category_id'           => $cat_id,
            'visible'               => $visible,
            'featured'              => $featured,
            'display_order'         => $order,
        );
        $format = array( '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' );

        if ( $edit_id > 0 ) {
            $wpdb->update( $table, $data, array( 'id' => $edit_id ), $format, array( '%d' ) );

            // Delete old features and insert new ones.
            $wpdb->delete( $features_table, array( 'plan_id' => $edit_id ), array( '%d' ) );
            foreach ( $features_input as $f_text ) {
                if ( ! empty( trim( $f_text ) ) ) {
                    $wpdb->insert(
                        $features_table,
                        array( 'plan_id' => $edit_id, 'text' => $f_text ),
                        array( '%d', '%s' )
                    );
                }
            }

            wp_admin_notice( 'Plan updated successfully.', array( 'type' => 'success' ) );
        } else {
            $wpdb->insert( $table, $data, $format );
            $edit_id = $wpdb->insert_id;

            // Insert features.
            foreach ( $features_input as $f_text ) {
                if ( ! empty( trim( $f_text ) ) ) {
                    $wpdb->insert(
                        $features_table,
                        array( 'plan_id' => $edit_id, 'text' => $f_text ),
                        array( '%d', '%s' )
                    );
                }
            }

            wp_admin_notice( 'Plan created successfully.', array( 'type' => 'success' ) );
        }

        // Sync WooCommerce product meta.
        if ( ! empty( $wc_id ) && function_exists( 'wc_get_product' ) ) {
            update_post_meta( $wc_id, '_bv_plan_id', $edit_id );
        }
    }

    /**
     * Handle delete plan and its features.
     *
     * @return void
     */
    private static function handle_delete() {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'bv_delete_plan' ) {
            return;
        }

        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( $id <= 0 ) {
            return;
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'bv_delete_plan_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $plans_table    = $wpdb->prefix . 'bv_plans';
        $features_table = $wpdb->prefix . 'bv_plan_features';

        // Clean up WC meta.
        $wc_id = $wpdb->get_var( $wpdb->prepare( "SELECT woocommerce_product_id FROM $plans_table WHERE id = %d", $id ) );
        if ( ! empty( $wc_id ) ) {
            delete_post_meta( $wc_id, '_bv_plan_id' );
        }

        $wpdb->delete( $features_table, array( 'plan_id' => $id ), array( '%d' ) );
        $wpdb->delete( $plans_table, array( 'id' => $id ), array( '%d' ) );

        wp_safe_redirect( admin_url( 'admin.php?page=bv-plans&bv_deleted=1' ) );
        exit;
    }

    /**
     * Handle visibility toggle for plans.
     *
     * @return void
     */
    public static function handle_visibility_toggle() {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'bv_toggle_plan_visibility' ) {
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
        $table = $wpdb->prefix . 'bv_plans';

        $wpdb->query(
            $wpdb->prepare( "UPDATE $table SET visible = IF(visible = 1, 0, 1) WHERE id = %d", $id )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=bv-plans' ) );
        exit;
    }
}