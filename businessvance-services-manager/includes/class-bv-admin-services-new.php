<?php
/**
 * BusinessVance Services Manager - Admin Services (Add New Form)
 *
 * Renders the "Add New Service" form. Split out for clarity.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Admin_Services_New
 *
 * Static method to render the add-new service form.
 */
class BV_Admin_Services_New {

    /**
     * Render the add-new service form.
     *
     * @return void
     */
    public static function render_form() {
        global $wpdb;

        $categories_table = $wpdb->prefix . 'bv_categories';
        $categories = $wpdb->get_results( "SELECT * FROM $categories_table ORDER BY name ASC" );

        $icons = BV_Admin_Services::$icons;

        // Build icon options HTML.
        $icon_options = '';
        foreach ( $icons as $icon ) {
            $icon_options .= '<option value="' . esc_attr( $icon ) . '">' . esc_html( $icon ) . '</option>';
        }

        // Build category options HTML.
        $cat_options = '<option value="">— None —</option>';
        foreach ( $categories as $cat ) {
            $cat_options .= '<option value="' . esc_attr( $cat->id ) . '">' . esc_html( $cat->name ) . '</option>';
        }
        ?>
        <div class="wrap bv-admin-wrap" id="bv-form">
            <h1 class="bv-page-title">
                Add New Service
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-services' ) ); ?>" class="page-title-action">← Back to list</a>
            </h1>

            <form method="post" action="" class="bv-form-card">
                <?php wp_nonce_field( 'bv_save_service', 'bv_service_nonce' ); ?>
                <input type="hidden" name="bv_action" value="bv_save_service">

                <div class="bv-form-grid">
                    <div class="bv-form-main">
                        <div class="bv-field">
                            <label for="svc-name">Service Name <span class="required">*</span></label>
                            <input type="text" id="svc-name" name="name" value="" required class="large-text" placeholder="e.g. Business Plan Development">
                        </div>

                        <div class="bv-field">
                            <label for="svc-slug">Slug</label>
                            <input type="text" id="svc-slug" name="slug" value="" class="large-text" placeholder="Auto-generated from name if blank">
                        </div>

                        <div class="bv-field">
                            <label for="svc-desc">Description</label>
                            <textarea id="svc-desc" name="description" rows="4" class="large-text" placeholder="Brief description of this service..."></textarea>
                        </div>

                        <div class="bv-field-row">
                            <div class="bv-field" style="flex:1;">
                                <label for="svc-price">Price (ZAR) <span class="required">*</span></label>
                                <input type="number" id="svc-price" name="price" value="0" required step="0.01" min="0" class="regular-text" placeholder="0.00">
                            </div>
                            <div class="bv-field" style="flex:1;">
                                <label for="svc-order">Display Order</label>
                                <input type="number" id="svc-order" name="display_order" value="0" min="0" class="small-text">
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
                            <input type="text" id="svc-button-label" name="button_label" value="ADD TO CART" class="regular-text">
                        </div>

                        <div class="bv-field">
                            <label for="svc-button-type">Button Type</label>
                            <select id="svc-button-type" name="button_type">
                                <option value="cart" selected>Add to Cart</option>
                                <option value="quote">Request Quote</option>
                                <option value="booking">Book Consultation</option>
                                <option value="link">External Link</option>
                            </select>
                        </div>

                        <div class="bv-field" id="svc-url-field" style="display:none;">
                            <label for="svc-button-url">Button URL</label>
                            <input type="url" id="svc-button-url" name="button_url" value="" class="regular-text" placeholder="https://...">
                        </div>

                        <div class="bv-field">
                            <label for="svc-wc-product">WooCommerce Product ID</label>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input type="text" id="svc-wc-product" name="woocommerce_product_id" value="" class="regular-text" placeholder="e.g. 42">
                                <button type="button" class="button bv-browse-products" data-target="svc-wc-product">Browse</button>
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
                    <?php submit_button( 'Add Service', 'primary', 'bv_submit', false ); ?>
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
}