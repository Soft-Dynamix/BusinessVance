<?php
/**
 * BusinessVance Services Manager - Admin Categories
 *
 * CRUD management for service categories.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Admin_Categories
 *
 * Handles the admin categories management page.
 */
class BV_Admin_Categories {

    /**
     * Render the categories management page.
     *
     * @return void
     */
    public static function render_page() {
        global $wpdb;

        $table          = $wpdb->prefix . 'bv_categories';
        $services_table = $wpdb->prefix . 'bv_services';
        $plans_table    = $wpdb->prefix . 'bv_plans';

        // Handle form submissions.
        self::handle_save();
        self::handle_delete();

        // Fetch categories with item counts.
        $categories = $wpdb->get_results(
            "SELECT c.*,
                (SELECT COUNT(*) FROM $services_table s WHERE s.category_id = c.id) AS service_count,
                (SELECT COUNT(*) FROM $plans_table p WHERE p.category_id = c.id) AS plan_count
             FROM $table c
             ORDER BY c.name ASC"
        );

        // If editing, fetch the category.
        $edit_category  = null;
        $is_editing     = false;
        $edit_id        = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;

        if ( $edit_id > 0 ) {
            $edit_category = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $edit_id )
            );
            if ( $edit_category ) {
                $is_editing = true;
            }
        }
        ?>
        <div class="wrap bv-admin-wrap">
            <h1 class="bv-page-title">
                Categories
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-categories' ) ); ?>" class="page-title-action">
                    <?php echo $is_editing ? esc_html__( 'Add New', 'businessvance' ) : esc_html__( 'View All', 'businessvance' ); ?>
                </a>
            </h1>

            <?php if ( $is_editing && $edit_category ) : ?>
                <div class="bv-form-card" style="max-width:600px; margin-top:20px;">
                    <h2><?php echo esc_html__( 'Edit Category', 'businessvance' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'bv_save_category', 'bv_category_nonce' ); ?>
                        <input type="hidden" name="bv_action" value="bv_save_category">
                        <input type="hidden" name="category_id" value="<?php echo esc_attr( $edit_category->id ); ?>">

                        <div class="bv-field">
                            <label for="cat-name">Name <span class="required">*</span></label>
                            <input type="text" id="cat-name" name="name" value="<?php echo esc_attr( $edit_category->name ); ?>" required class="regular-text">
                        </div>

                        <div class="bv-field">
                            <label for="cat-slug">Slug</label>
                            <input type="text" id="cat-slug" name="slug" value="<?php echo esc_attr( $edit_category->slug ); ?>" class="regular-text">
                            <p class="description">Auto-generated from name if left blank.</p>
                        </div>

                        <div class="bv-field">
                            <label for="cat-color">Color</label>
                            <input type="color" id="cat-color" name="color" value="<?php echo esc_attr( $edit_category->color ); ?>" style="width:60px; height:40px; border:none; cursor:pointer;">
                        </div>

                        <?php submit_button( 'Update Category', 'primary', 'bv_submit', false ); ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-categories' ) ); ?>" class="button" style="margin-left:8px;">Cancel</a>
                    </form>
                </div>
            <?php else : ?>
                <div class="bv-form-card" style="max-width:600px; margin-top:20px;">
                    <h2><?php echo esc_html__( 'Add New Category', 'businessvance' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'bv_save_category', 'bv_category_nonce' ); ?>
                        <input type="hidden" name="bv_action" value="bv_save_category">

                        <div class="bv-field">
                            <label for="cat-name">Name <span class="required">*</span></label>
                            <input type="text" id="cat-name" name="name" value="" required class="regular-text" placeholder="e.g. Business Planning">
                        </div>

                        <div class="bv-field">
                            <label for="cat-slug">Slug</label>
                            <input type="text" id="cat-slug" name="slug" value="" class="regular-text" placeholder="Auto-generated from name">
                        </div>

                        <div class="bv-field">
                            <label for="cat-color">Color</label>
                            <input type="color" id="cat-color" name="color" value="#002B5C" style="width:60px; height:40px; border:none; cursor:pointer;">
                        </div>

                        <?php submit_button( 'Add Category', 'primary', 'bv_submit', false ); ?>
                    </form>
                </div>

                <?php if ( ! empty( $categories ) ) : ?>
                <h2 style="margin-top:30px;">All Categories</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:40px;">Color</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th style="width:70px;">Services</th>
                            <th style="width:70px;">Plans</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $categories as $cat ) : ?>
                            <tr>
                                <td><span style="display:inline-block;width:24px;height:24px;border-radius:4px;background:<?php echo esc_attr( $cat->color ); ?>;vertical-align:middle;"></span></td>
                                <td><strong><?php echo esc_html( $cat->name ); ?></strong></td>
                                <td><code><?php echo esc_html( $cat->slug ); ?></code></td>
                                <td><?php echo esc_html( $cat->service_count ); ?></td>
                                <td><?php echo esc_html( $cat->plan_count ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bv-categories&edit=' . absint( $cat->id ) ) ); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bv-categories&action=bv_delete_category&id=' . absint( $cat->id ) ), 'bv_delete_category_' . $cat->id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this category? This will NOT delete linked services or plans.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                    <p style="margin-top:20px;">No categories found. Add one above.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle save (create or update) category.
     *
     * @return void
     */
    private static function handle_save() {
        if ( ! isset( $_POST['bv_action'] ) || $_POST['bv_action'] !== 'bv_save_category' ) {
            return;
        }

        if ( ! isset( $_POST['bv_category_nonce'] ) || ! wp_verify_nonce( $_POST['bv_category_nonce'], 'bv_save_category' ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_categories';

        $name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
        $slug = ! empty( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title( $name );
        $color = ! empty( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#002B5C';

        if ( empty( $name ) ) {
            wp_admin_notice( 'Category name is required.', array( 'type' => 'error' ) );
            return;
        }

        $edit_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

        if ( $edit_id > 0 ) {
            // Update.
            $wpdb->update(
                $table,
                array(
                    'name'  => $name,
                    'slug'  => $slug,
                    'color' => $color,
                ),
                array( 'id' => $edit_id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
            wp_admin_notice( 'Category updated successfully.', array( 'type' => 'success' ) );
        } else {
            // Check slug uniqueness.
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", $slug ) );
            if ( $exists ) {
                $slug .= '-' . wp_rand( 10, 99 );
            }

            $wpdb->insert(
                $table,
                array(
                    'name'  => $name,
                    'slug'  => $slug,
                    'color' => $color,
                ),
                array( '%s', '%s', '%s' )
            );
            wp_admin_notice( 'Category created successfully.', array( 'type' => 'success' ) );
        }
    }

    /**
     * Handle delete category.
     *
     * @return void
     */
    private static function handle_delete() {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'bv_delete_category' ) {
            return;
        }

        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( $id <= 0 ) {
            return;
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'bv_delete_category_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bv_categories';

        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        wp_safe_redirect( admin_url( 'admin.php?page=bv-categories&bv_deleted=1' ) );
        exit;
    }
}