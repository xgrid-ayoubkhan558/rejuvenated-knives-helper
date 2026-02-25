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
            'rk_enable_admin_debug' => 'boolean',
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

    public static function page()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            return;
        }

        $options = array();
        $option_keys = array(
            'min_order_value' => 'rk_woo_ajax_cart_count_min_order_value',
            'attributes_only' => 'rk_woo_ajax_cart_count_attributes_only',
            'minimum_formula' => 'rk_woo_ajax_cart_count_minimum_formula',
            'overwrite_quantity' => 'rk_overwrite_cart_quantity',
            'pickup_columns' => 'rk_enable_advanced_pickup_columns',
            'admin_debug' => 'rk_enable_admin_debug',
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

        foreach ($option_keys as $key => $slug) {
            $options[$key] = get_option($slug, ($key === 'pickup_columns' || $key === 'cart_sync' ? 1 : 0));
        }

        echo '<div class="wrap">';
        echo '<h1>Rejuvenated Knives Helper Settings</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('rk_woo_ajax_cart_count_settings');

        echo '<h2>Cart & Sync Settings</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="rk_woo_ajax_cart_count_min_order_value">Minimum order value</label></th>';
        echo '<td><input name="rk_woo_ajax_cart_count_min_order_value" id="rk_woo_ajax_cart_count_min_order_value" type="number" min="0" step="0.01" value="' . esc_attr($options['min_order_value']) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">Fragments mode</th>';
        echo '<td><label><input name="rk_woo_ajax_cart_count_attributes_only" type="checkbox" value="1" ' . checked(1, $options['attributes_only'], false) . ' /> Attributes only</label></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">Overwrite Quantity</th>';
        echo '<td><label><input name="rk_overwrite_cart_quantity" type="checkbox" value="1" ' . checked(1, $options['overwrite_quantity'], false) . ' /> Overwrite cart quantity instead of adding</label></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">Cart Sync Logic</th>';
        echo '<td><label><input name="rk_enable_cart_sync_logic" type="checkbox" value="1" ' . checked(1, $options['cart_sync'], false) . ' /> Sync inputs with cart state (Bricks Builder)</label></td>';
        echo '</tr>';
        echo '</table>';

        echo '<h2>Checkout & Field Tweaks</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">Email Position</th><td><label><input name="rk_reorder_email_field" type="checkbox" value="1" ' . checked(1, $options['reorder_email'], false) . ' /> Move email field after name fields</label></td></tr>';
        echo '<tr><th scope="row">Rename Billing</th><td><label><input name="rk_rename_billing_to_delivery" type="checkbox" value="1" ' . checked(1, $options['rename_billing'], false) . ' /> Rename "Billing" to "Delivery" across checkout</label></td></tr>';
        echo '<tr><th scope="row">Phone Prefix</th><td><label><input name="rk_enable_phone_prefix" type="checkbox" value="1" ' . checked(1, $options['phone_prefix'], false) . ' /> Auto-add country calling code to phone field</label></td></tr>';
        echo '<tr><th scope="row">USA phone Validation</th><td><label><input name="rk_us_phone_validation" type="checkbox" value="1" ' . checked(1, $options['us_phone_validation'], false) . ' /> Enforce (555-123-4567) format and min 6 digits</label></td></tr>';
        echo '<tr><th scope="row">Address 2 Label</th><td><label><input name="rk_address_2_label_custom" type="checkbox" value="1" ' . checked(1, $options['address_2_label'], false) . ' /> Change Address 2 label to "Address *"</label></td></tr>';
        echo '<tr><th scope="row">Remove Zipcode</th><td><label><input name="rk_remove_zipcode" type="checkbox" value="1" ' . checked(1, $options['remove_zipcode'], false) . ' /> Hide Postcode/Zip field</label></td></tr>';
        echo '<tr><th scope="row">Remove Country/State</th><td><label><input name="rk_remove_country_state" type="checkbox" value="1" ' . checked(1, $options['remove_country_state'], false) . ' /> Hide Country and State fields</label></td></tr>';
        echo '<tr><th scope="row">Disable City Autofill</th><td><label><input name="rk_disable_city_autofill" type="checkbox" value="1" ' . checked(1, $options['city_autofill'], false) . ' /> Force clear city search on checkout load</label></td></tr>';
        echo '</table>';

        echo '<h2>Account & Admin Tweaks</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">Login Redirect</th><td><label><input name="rk_enable_login_redirect" type="checkbox" value="1" ' . checked(1, $options['login_redirect'], false) . ' /> Redirect to My Account after login</label></td></tr>';
        echo '<tr><th scope="row">Shop Manager Restriction</th><td><label><input name="rk_limit_shop_manager" type="checkbox" value="1" ' . checked(1, $options['limit_shop_manager'], false) . ' /> Restrict admin menu for Shop Managers</label></td></tr>';
        echo '<tr><th scope="row">Hide Region Emails</th><td><label><input name="rk_hide_region_emails" type="checkbox" value="1" ' . checked(1, $options['hide_region_emails'], false) . ' /> Remove region info from order emails</label></td></tr>';
        echo '<tr><th scope="row">Admin Debug Mode</th><td><label><input name="rk_enable_admin_debug" type="checkbox" value="1" ' . checked(1, $options['admin_debug'], false) . ' /> Show Screen ID at the top (Debug)</label></td></tr>';
        echo '<tr><th scope="row">Pickup Column</th><td><label><input name="rk_enable_advanced_pickup_columns" type="checkbox" value="1" ' . checked(1, $options['pickup_columns'], false) . ' /> Styled Pickup Days in Locations admin</label></td></tr>';
        echo '</table>';

        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
