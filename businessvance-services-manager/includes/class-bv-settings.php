<?php
/**
 * BusinessVance Services Manager - Settings
 *
 * Plugin settings page using the WordPress Settings API.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BV_Settings
 *
 * Registers and renders the plugin settings page.
 */
class BV_Settings {

    /**
     * Option name for all plugin settings.
     *
     * @var string
     */
    const OPTION_KEY = 'bv_services_manager_settings';

    /**
     * Default settings values.
     *
     * @var array
     */
    private static $defaults = array(
        'page_title'     => 'BusinessVance Services',
        'show_categories' => 1,
        'currency_symbol' => 'R',
        'currency_position' => 'before',
        'enable_animations' => 1,
    );

    /**
     * Register the settings and fields.
     *
     * @return void
     */
    public static function register() {
        register_setting(
            'bv_settings_group',
            self::OPTION_KEY,
            array( __CLASS__, 'sanitize_settings' )
        );

        // General Section.
        add_settings_section(
            'bv_general_section',
            'General Settings',
            '__return_null',
            'bv-settings'
        );

        add_settings_field(
            'page_title',
            'Page Title',
            array( __CLASS__, 'render_text_field' ),
            'bv-settings',
            'bv_general_section',
            array( 'field' => 'page_title', 'desc' => 'Title displayed above the services list on the frontend.' )
        );

        add_settings_field(
            'show_categories',
            'Show Categories Filter',
            array( __CLASS__, 'render_checkbox_field' ),
            'bv-settings',
            'bv_general_section',
            array( 'field' => 'show_categories', 'label' => 'Show category filter tabs on the frontend.' )
        );

        add_settings_field(
            'enable_animations',
            'Enable Animations',
            array( __CLASS__, 'render_checkbox_field' ),
            'bv-settings',
            'bv_general_section',
            array( 'field' => 'enable_animations', 'label' => 'Enable fade-in animations on the frontend.' )
        );

        // Currency Section.
        add_settings_section(
            'bv_currency_section',
            'Currency Settings',
            '__return_null',
            'bv-settings'
        );

        add_settings_field(
            'currency_symbol',
            'Currency Symbol',
            array( __CLASS__, 'render_text_field' ),
            'bv-settings',
            'bv_currency_section',
            array( 'field' => 'currency_symbol', 'desc' => 'e.g. R, $, £, €' )
        );

        add_settings_field(
            'currency_position',
            'Currency Position',
            array( __CLASS__, 'render_select_field' ),
            'bv-settings',
            'bv_currency_section',
            array(
                'field'   => 'currency_position',
                'options' => array(
                    'before' => 'Before price (R 1,500.00)',
                    'after'  => 'After price (1,500.00 R)',
                ),
            )
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input.
     * @return array
     */
    public static function sanitize_settings( $input ) {
        $sanitized = array();
        $sanitized['page_title']       = sanitize_text_field( $input['page_title'] ?? '' );
        $sanitized['show_categories']  = isset( $input['show_categories'] ) ? 1 : 0;
        $sanitized['currency_symbol']  = sanitize_text_field( $input['currency_symbol'] ?? 'R' );
        $sanitized['currency_position'] = ( $input['currency_position'] ?? 'before' ) === 'after' ? 'after' : 'before';
        $sanitized['enable_animations'] = isset( $input['enable_animations'] ) ? 1 : 0;
        return $sanitized;
    }

    /**
     * Get a single setting value.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Fallback if not set.
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = get_option( self::OPTION_KEY, array() );
        if ( array_key_exists( $key, $settings ) ) {
            return $settings[ $key ];
        }
        return isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : $default;
    }

    /**
     * Get all settings with defaults merged.
     *
     * @return array
     */
    public static function get_all() {
        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, self::$defaults );
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public static function render_page() {
        ?>
        <div class="wrap bv-admin-wrap">
            <h1 class="bv-page-title">Settings</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'bv_settings_group' );
                do_settings_sections( 'bv-settings' );
                submit_button( 'Save Settings', 'primary', 'bv_submit', false );
                ?>
            </form>

            <div class="bv-settings-note" style="margin-top:30px; max-width:700px; padding:15px 20px; background:#f0f6fc; border-left:4px solid #002B5C; border-radius:4px;">
                <h3 style="margin-top:0;">Payment Integration</h3>
                <p><strong>Yoco</strong> payments are handled through WooCommerce checkout. Make sure Yoco is configured in <em>WooCommerce → Settings → Payments</em>.</p>
                <p>Link each service/plan to a WooCommerce product via the "WooCommerce Product ID" field to enable add-to-cart functionality.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Render a text field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public static function render_text_field( $args ) {
        $field = $args['field'];
        $value = esc_attr( self::get( $field ) );
        $desc  = isset( $args['desc'] ) ? '<p class="description">' . esc_html( $args['desc'] ) . '</p>' : '';

        echo '<input type="text" name="' . esc_attr( self::OPTION_KEY ) . '[' . $field . ']" value="' . $value . '" class="regular-text">';
        echo $desc;
    }

    /**
     * Render a checkbox field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public static function render_checkbox_field( $args ) {
        $field = $args['field'];
        $value = self::get( $field );
        $label = isset( $args['label'] ) ? esc_html( $args['label'] ) : '';

        echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[' . $field . ']" value="1" ' . checked( $value, 1, false ) . '> ' . $label . '</label>';
    }

    /**
     * Render a select dropdown.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public static function render_select_field( $args ) {
        $field   = $args['field'];
        $value   = self::get( $field );
        $options = $args['options'];

        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[' . $field . ']">';
        foreach ( $options as $opt_val => $opt_label ) {
            echo '<option value="' . esc_attr( $opt_val ) . '"' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
        }
        echo '</select>';
    }
}