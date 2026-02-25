<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RK_Woo_Ajax_Cart_Count_Admin
{
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'menu'));
    }

    public static function register_settings()
    {
        $settings = array(
            'rk_woo_ajax_cart_count_min_order_value' => 'number',
            'rk_woo_ajax_cart_count_attributes_only' => 'boolean',
            'rk_woo_ajax_cart_count_minimum_formula' => 'string',
            'rk_overwrite_cart_quantity' => 'boolean',
            'rk_enable_advanced_pickup_columns' => 'boolean',
            'rk_enable_cart_sync_logic' => 'boolean',
            'rk_enable_login_redirect' => 'boolean',
            'rk_rename_billing_to_delivery' => 'boolean',
            'rk_enable_phone_prefix' => 'boolean',
            'rk_reorder_email_field' => 'boolean',
            'rk_limit_shop_manager' => 'boolean',
            'rk_remove_zipcode' => 'boolean',
            'rk_remove_country_state' => 'boolean',
            'rk_address_2_label_custom' => 'boolean',
            'rk_us_phone_validation' => 'boolean',
            'rk_hide_region_emails' => 'boolean',
            'rk_disable_city_autofill' => 'boolean',
        );

        foreach ($settings as $slug => $type) {
            register_setting(
                'rk_woo_ajax_cart_count_settings',
                $slug,
                array(
                    'type' => $type,
                    'sanitize_callback' => function ($value) use ($type) {
                        if ($type === 'boolean')
                            return $value ? 1 : 0;
                        if ($type === 'number')
                            return is_numeric($value) ? (float) $value : 0;
                        return sanitize_text_field($value);
                    },
                    'default' => ($slug === 'rk_enable_advanced_pickup_columns' || $slug === 'rk_enable_cart_sync_logic' ? 1 : 0),
                )
            );
        }
    }

    public static function menu()
    {
        add_submenu_page(
            'woocommerce',
            'RK Helper Settings',
            'RK Helper',
            'manage_options',
            'rk-woo-ajax-cart-count',
            array(__CLASS__, 'page')
        );
    }

    /**
     * Render the settings page.
     */
    public static function page()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            return;
        }

        $options = self::get_options_data();
        ?>
        <div class="wrap">
            <h1>Rejuvenated Knives Helper Settings</h1>

            <form method="post" action="options.php">
                <?php settings_fields('rk_woo_ajax_cart_count_settings'); ?>

                <h2 class="title">Cart & Sync Settings</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rk_woo_ajax_cart_count_min_order_value">Minimum order value</label></th>
                        <td>
                            <input name="rk_woo_ajax_cart_count_min_order_value" id="rk_woo_ajax_cart_count_min_order_value"
                                type="number" min="0" step="0.01" value="<?php echo esc_attr($options['min_order_value']); ?>"
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rk_woo_ajax_cart_count_minimum_formula">Minimum Formula</label></th>
                        <td>
                            <input name="rk_woo_ajax_cart_count_minimum_formula" id="rk_woo_ajax_cart_count_minimum_formula"
                                type="text" value="<?php echo esc_attr($options['minimum_formula']); ?>" class="regular-text" />
                            <p class="description">Optional formula for complex minimum order logic.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Fragments mode</th>
                        <td>
                            <label>
                                <input name="rk_woo_ajax_cart_count_attributes_only" type="checkbox" value="1" <?php checked(1, $options['attributes_only']); ?> />
                                Attributes only
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Overwrite Quantity</th>
                        <td>
                            <label>
                                <input name="rk_overwrite_cart_quantity" type="checkbox" value="1" <?php checked(1, $options['overwrite_quantity']); ?> />
                                Overwrite cart quantity instead of adding
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Cart Sync Logic</th>
                        <td>
                            <label>
                                <input name="rk_enable_cart_sync_logic" type="checkbox" value="1" <?php checked(1, $options['cart_sync']); ?> />
                                Sync inputs with cart state (Bricks Builder)
                            </label>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Checkout & Field Tweaks</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Email Position</th>
                        <td>
                            <label>
                                <input name="rk_reorder_email_field" type="checkbox" value="1" <?php checked(1, $options['reorder_email']); ?> />
                                Move email field after name fields
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Rename Billing</th>
                        <td>
                            <label>
                                <input name="rk_rename_billing_to_delivery" type="checkbox" value="1" <?php checked(1, $options['rename_billing']); ?> />
                                Rename "Billing" to "Delivery" across checkout
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Phone Prefix</th>
                        <td>
                            <label>
                                <input name="rk_enable_phone_prefix" type="checkbox" value="1" <?php checked(1, $options['phone_prefix']); ?> />
                                Auto-add country calling code to phone field
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">USA phone Validation</th>
                        <td>
                            <label>
                                <input name="rk_us_phone_validation" type="checkbox" value="1" <?php checked(1, $options['us_phone_validation']); ?> />
                                Enforce (555-123-4567) format and min 6 digits
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Address 2 Label</th>
                        <td>
                            <label>
                                <input name="rk_address_2_label_custom" type="checkbox" value="1" <?php checked(1, $options['address_2_label']); ?> />
                                Change Address 2 label to "Address *"
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Remove Zipcode</th>
                        <td>
                            <label>
                                <input name="rk_remove_zipcode" type="checkbox" value="1" <?php checked(1, $options['remove_zipcode']); ?> />
                                Hide Postcode/Zip field
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Remove Country/State</th>
                        <td>
                            <label>
                                <input name="rk_remove_country_state" type="checkbox" value="1" <?php checked(1, $options['remove_country_state']); ?> />
                                Hide Country and State fields
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Disable City Autofill</th>
                        <td>
                            <label>
                                <input name="rk_disable_city_autofill" type="checkbox" value="1" <?php checked(1, $options['city_autofill']); ?> />
                                Force clear city search on checkout load
                            </label>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Account & Admin Tweaks</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Login Redirect</th>
                        <td>
                            <label>
                                <input name="rk_enable_login_redirect" type="checkbox" value="1" <?php checked(1, $options['login_redirect']); ?> />
                                Redirect to My Account after login
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Shop Manager Restriction</th>
                        <td>
                            <label>
                                <input name="rk_limit_shop_manager" type="checkbox" value="1" <?php checked(1, $options['limit_shop_manager']); ?> />
                                Restrict admin menu for Shop Managers
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Hide Region Emails</th>
                        <td>
                            <label>
                                <input name="rk_hide_region_emails" type="checkbox" value="1" <?php checked(1, $options['hide_region_emails']); ?> />
                                Remove region info from order emails
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Pickup Column</th>
                        <td>
                            <label>
                                <input name="rk_enable_advanced_pickup_columns" type="checkbox" value="1" <?php checked(1, $options['pickup_columns']); ?> />
                                Styled Pickup Days in Locations admin
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get all plugin options.
     */
    private static function get_options_data()
    {
        $option_keys = array(
            'min_order_value' => 'rk_woo_ajax_cart_count_min_order_value',
            'attributes_only' => 'rk_woo_ajax_cart_count_attributes_only',
            'minimum_formula' => 'rk_woo_ajax_cart_count_minimum_formula',
            'overwrite_quantity' => 'rk_overwrite_cart_quantity',
            'pickup_columns' => 'rk_enable_advanced_pickup_columns',
            'cart_sync' => 'rk_enable_cart_sync_logic',
            'login_redirect' => 'rk_enable_login_redirect',
            'rename_billing' => 'rk_rename_billing_to_delivery',
            'phone_prefix' => 'rk_enable_phone_prefix',
            'reorder_email' => 'rk_reorder_email_field',
            'limit_shop_manager' => 'rk_limit_shop_manager',
            'remove_zipcode' => 'rk_remove_zipcode',
            'remove_country_state' => 'rk_remove_country_state',
            'address_2_label' => 'rk_address_2_label_custom',
            'us_phone_validation' => 'rk_us_phone_validation',
            'hide_region_emails' => 'rk_hide_region_emails',
            'city_autofill' => 'rk_disable_city_autofill',
        );

        $options = array();
        foreach ($option_keys as $key => $slug) {
            $options[$key] = get_option($slug, ($key === 'pickup_columns' || $key === 'cart_sync' ? 1 : 0));
        }

        return $options;
    }

}
