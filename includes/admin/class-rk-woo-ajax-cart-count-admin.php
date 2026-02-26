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
        // Individual rk_ options
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

        // The checkout fields options array (Consolidated from RK_Checkout_Fields)
        register_setting(
            'rk_woo_ajax_cart_count_settings',
            'rk_cf_options',
            array(
                'sanitize_callback' => array(__CLASS__, 'sanitize_cf_options'),
            )
        );
    }

    /**
     * Sanitize the checkout fields options array
     */
    public static function sanitize_cf_options($input)
    {
        $defaults = self::get_cf_defaults();
        $out = array();
        $out['enable_auto_payment'] = !empty($input['enable_auto_payment']) ? 1 : 0;
        $out['payment_found'] = sanitize_text_field($input['payment_found'] ?: $defaults['payment_found']);
        $out['payment_not_found'] = sanitize_text_field($input['payment_not_found'] ?: $defaults['payment_not_found']);
        $out['add_body_classes'] = !empty($input['add_body_classes']) ? 1 : 0;
        $out['date_format'] = sanitize_text_field($input['date_format'] ?: $defaults['date_format']);
        $out['mailin_message'] = sanitize_textarea_field($input['mailin_message'] ?: $defaults['mailin_message']);
        $out['city_found_message'] = sanitize_textarea_field($input['city_found_message'] ?: $defaults['city_found_message']);
        $out['city_selected_message'] = sanitize_textarea_field($input['city_selected_message'] ?: $defaults['city_selected_message']);
        $out['region_label'] = sanitize_text_field($input['region_label'] ?: $defaults['region_label']);
        $out['city_search_label'] = sanitize_text_field($input['city_search_label'] ?: $defaults['city_search_label']);
        $out['pickup_date_label'] = sanitize_text_field($input['pickup_date_label'] ?: $defaults['pickup_date_label']);
        $out['city_search_placeholder'] = sanitize_text_field($input['city_search_placeholder'] ?: $defaults['city_search_placeholder']);
        $out['disable_shipping_address'] = !empty($input['disable_shipping_address']) ? 1 : 0;
        $out['require_city_selection'] = !empty($input['require_city_selection']) ? 1 : 0;
        $out['min_days_advance'] = absint($input['min_days_advance'] ?? $defaults['min_days_advance']);
        $out['max_days_advance'] = absint($input['max_days_advance'] ?? $defaults['max_days_advance']);
        return $out;
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

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        $options = self::get_options_data();
        $cf_options = self::get_cf_options();
        $payment_methods = self::get_payment_methods();
        ?>
        <div class="wrap">
            <h1>Rejuvenated Knives Helper Settings</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=rk-woo-ajax-cart-count&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General & Cart</a>
                <a href="?page=rk-woo-ajax-cart-count&tab=checkout_tweaks" class="nav-tab <?php echo $active_tab === 'checkout_tweaks' ? 'nav-tab-active' : ''; ?>">Checkout Tweaks</a>
                <a href="?page=rk-woo-ajax-cart-count&tab=dynamic_fields" class="nav-tab <?php echo $active_tab === 'dynamic_fields' ? 'nav-tab-active' : ''; ?>">Dynamic Fields</a>
                <a href="?page=rk-woo-ajax-cart-count&tab=messages_labels" class="nav-tab <?php echo $active_tab === 'messages_labels' ? 'nav-tab-active' : ''; ?>">Messages & Labels</a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('rk_woo_ajax_cart_count_settings'); ?>

                <?php if ($active_tab === 'general'): ?>
                    <h2 class="title">Cart & Sync Settings</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="rk_woo_ajax_cart_count_min_order_value">Minimum order value</label></th>
                            <td>
                                <input name="rk_woo_ajax_cart_count_min_order_value" id="rk_woo_ajax_cart_count_min_order_value" type="number" min="0" step="0.01" value="<?php echo esc_attr($options['min_order_value']); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rk_woo_ajax_cart_count_minimum_formula">Minimum Formula</label></th>
                            <td>
                                <input name="rk_woo_ajax_cart_count_minimum_formula" id="rk_woo_ajax_cart_count_minimum_formula" type="text" value="<?php echo esc_attr($options['minimum_formula']); ?>" class="regular-text" />
                                <p class="description">Optional formula for complex minimum order logic.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Fragments mode</th>
                            <td>
                                <label><input name="rk_woo_ajax_cart_count_attributes_only" type="checkbox" value="1" <?php checked(1, $options['attributes_only']); ?> /> Attributes only</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Overwrite Quantity</th>
                            <td>
                                <label><input name="rk_overwrite_cart_quantity" type="checkbox" value="1" <?php checked(1, $options['overwrite_quantity']); ?> /> Overwrite cart quantity instead of adding</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Cart Sync Logic</th>
                            <td>
                                <label><input name="rk_enable_cart_sync_logic" type="checkbox" value="1" <?php checked(1, $options['cart_sync']); ?> /> Sync inputs with cart state (Bricks Builder)</label>
                            </td>
                        </tr>
                    </table>

                    <h2 class="title">Account & Admin Tweaks</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Login Redirect</th>
                            <td>
                                <label><input name="rk_enable_login_redirect" type="checkbox" value="1" <?php checked(1, $options['login_redirect']); ?> /> Redirect to My Account after login</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Shop Manager Restriction</th>
                            <td>
                                <label><input name="rk_limit_shop_manager" type="checkbox" value="1" <?php checked(1, $options['limit_shop_manager']); ?> /> Restrict admin menu for Shop Managers</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Pickup Column</th>
                            <td>
                                <label><input name="rk_enable_advanced_pickup_columns" type="checkbox" value="1" <?php checked(1, $options['pickup_columns']); ?> /> Styled Pickup Days in Locations admin</label>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($active_tab === 'checkout_tweaks'): ?>
                    <h2 class="title">Checkout Field Visibility & Tweaks</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Email Position</th>
                            <td>
                                <label><input name="rk_reorder_email_field" type="checkbox" value="1" <?php checked(1, $options['reorder_email']); ?> /> Move email field after name fields</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Rename Billing</th>
                            <td>
                                <label><input name="rk_rename_billing_to_delivery" type="checkbox" value="1" <?php checked(1, $options['rename_billing']); ?> /> Rename "Billing" to "Delivery" across checkout</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Phone Prefix</th>
                            <td>
                                <label><input name="rk_enable_phone_prefix" type="checkbox" value="1" <?php checked(1, $options['phone_prefix']); ?> /> Auto-add country calling code to phone field</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">USA phone Validation</th>
                            <td>
                                <label><input name="rk_us_phone_validation" type="checkbox" value="1" <?php checked(1, $options['us_phone_validation']); ?> /> Enforce (555-123-4567) format and min 6 digits</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Address 2 Label</th>
                            <td>
                                <label><input name="rk_address_2_label_custom" type="checkbox" value="1" <?php checked(1, $options['address_2_label']); ?> /> Change Address 2 label to "Address *"</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Remove Zipcode</th>
                            <td>
                                <label><input name="rk_remove_zipcode" type="checkbox" value="1" <?php checked(1, $options['remove_zipcode']); ?> /> Hide Postcode/Zip field</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Remove Country/State</th>
                            <td>
                                <label><input name="rk_remove_country_state" type="checkbox" value="1" <?php checked(1, $options['remove_country_state']); ?> /> Hide Country and State fields</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Disable City Autofill</th>
                            <td>
                                <label><input name="rk_disable_city_autofill" type="checkbox" value="1" <?php checked(1, $options['city_autofill']); ?> /> Force clear city search on checkout load</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Hide Region Emails</th>
                            <td>
                                <label><input name="rk_hide_region_emails" type="checkbox" value="1" <?php checked(1, $options['hide_region_emails']); ?> /> Remove region info from order emails</label>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($active_tab === 'dynamic_fields'): ?>
                    <h2 class="title">Dynamic Field Logic</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Auto Payment Selection</th>
                            <td>
                                <label><input type="checkbox" name="rk_cf_options[enable_auto_payment]" value="1" <?php checked(1, $cf_options['enable_auto_payment']); ?> /> Automatically select payment method based on city availability</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Payment (City Found)</th>
                            <td>
                                <?php if (!empty($payment_methods)): ?>
                                    <select name="rk_cf_options[payment_found]" class="regular-text">
                                        <option value="">-- Select Payment Method --</option>
                                        <?php foreach ($payment_methods as $id => $title): ?>
                                            <option value="<?php echo esc_attr($id); ?>" <?php selected($cf_options['payment_found'], $id); ?>><?php echo esc_html($title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="rk_cf_options[payment_found]" value="<?php echo esc_attr($cf_options['payment_found']); ?>" class="regular-text" />
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Payment (City Not Found)</th>
                            <td>
                                <?php if (!empty($payment_methods)): ?>
                                    <select name="rk_cf_options[payment_not_found]" class="regular-text">
                                        <option value="">-- Select Payment Method --</option>
                                        <?php foreach ($payment_methods as $id => $title): ?>
                                            <option value="<?php echo esc_attr($id); ?>" <?php selected($cf_options['payment_not_found'], $id); ?>><?php echo esc_html($title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" name="rk_cf_options[payment_not_found]" value="<?php echo esc_attr($cf_options['payment_not_found']); ?>" class="regular-text" />
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Require City Selection</th>
                            <td>
                                <label><input type="checkbox" name="rk_cf_options[require_city_selection]" value="1" <?php checked(1, $cf_options['require_city_selection']); ?> /> Require city selection from dropdown only</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Disable Shipping Address</th>
                            <td>
                                <label><input type="checkbox" name="rk_cf_options[disable_shipping_address]" value="1" <?php checked(1, $cf_options['disable_shipping_address']); ?> /> Hide "Ship to a different address" checkbox</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Body Classes</th>
                            <td>
                                <label><input type="checkbox" name="rk_cf_options[add_body_classes]" value="1" <?php checked(1, $cf_options['add_body_classes']); ?> /> Add CSS classes to body element (rk-city-found, etc.)</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Date picker format</th>
                            <td>
                                <input type="text" name="rk_cf_options[date_format]" value="<?php echo esc_attr($cf_options['date_format']); ?>" class="regular-text" />
                                <p class="description">e.g., d-m-Y, F j, Y. Uses flatpickr format.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Advance Days (Min/Max)</th>
                            <td>
                                Min: <input type="number" name="rk_cf_options[min_days_advance]" value="<?php echo esc_attr($cf_options['min_days_advance']); ?>" class="small-text" min="0" />
                                Max: <input type="number" name="rk_cf_options[max_days_advance]" value="<?php echo esc_attr($cf_options['max_days_advance']); ?>" class="small-text" min="1" />
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($active_tab === 'messages_labels'): ?>
                    <h2 class="title">Field Labels</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Region Label</th>
                            <td><input type="text" name="rk_cf_options[region_label]" value="<?php echo esc_attr($cf_options['region_label']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">City Search Label</th>
                            <td><input type="text" name="rk_cf_options[city_search_label]" value="<?php echo esc_attr($cf_options['city_search_label']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Pickup Date Label</th>
                            <td><input type="text" name="rk_cf_options[pickup_date_label]" value="<?php echo esc_attr($cf_options['pickup_date_label']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Search Placeholder</th>
                            <td><input type="text" name="rk_cf_options[city_search_placeholder]" value="<?php echo esc_attr($cf_options['city_search_placeholder']); ?>" class="regular-text" /></td>
                        </tr>
                    </table>

                    <h2 class="title">Dynamic Messages</h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Mail-in message (no match)</th>
                            <td><textarea name="rk_cf_options[mailin_message]" rows="5" class="large-text"><?php echo esc_textarea($cf_options['mailin_message']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">City found message</th>
                            <td><textarea name="rk_cf_options[city_found_message]" rows="4" class="large-text"><?php echo esc_textarea($cf_options['city_found_message']); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row">City selected message</th>
                            <td><textarea name="rk_cf_options[city_selected_message]" rows="4" class="large-text"><?php echo esc_textarea($cf_options['city_selected_message']); ?></textarea></td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php 
                // Render hidden fields for the rk_cf_options keys not on current tab to avoid data loss
                if ($active_tab !== 'dynamic_fields' && $active_tab !== 'messages_labels') {
                    foreach ($cf_options as $key => $val) {
                        echo '<input type="hidden" name="rk_cf_options[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" />';
                    }
                } elseif ($active_tab === 'dynamic_fields') {
                    // Hidden fields for message tab keys
                    $msg_keys = array('region_label', 'city_search_label', 'pickup_date_label', 'city_search_placeholder', 'mailin_message', 'city_found_message', 'city_selected_message');
                    foreach ($msg_keys as $key) {
                        echo '<input type="hidden" name="rk_cf_options[' . esc_attr($key) . ']" value="' . esc_attr($cf_options[$key]) . '" />';
                    }
                } elseif ($active_tab === 'messages_labels') {
                    // Hidden fields for dynamic tab keys
                    $dyn_keys = array('enable_auto_payment', 'payment_found', 'payment_not_found', 'require_city_selection', 'disable_shipping_address', 'add_body_classes', 'date_format', 'min_days_advance', 'max_days_advance');
                    foreach ($dyn_keys as $key) {
                        echo '<input type="hidden" name="rk_cf_options[' . esc_attr($key) . ']" value="' . esc_attr($cf_options[$key]) . '" />';
                    }
                }
                ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get all individual plugin options.
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

    /**
     * Get checkout field options.
     */
    public static function get_cf_options()
    {
        $defaults = self::get_cf_defaults();
        $opts = get_option('rk_cf_options', array());
        return wp_parse_args($opts, $defaults);
    }

    /**
     * Defaults for checkout fields
     */
    public static function get_cf_defaults()
    {
        return array(
            'enable_auto_payment' => 1,
            'payment_found' => 'cod',
            'payment_not_found' => 'other_payment',
            'add_body_classes' => 1,
            'date_format' => 'd-m-Y',
            'mailin_message' => "Good news!\n\nWhile your location is outside our door-to-door coverage area, you can mail in your knives using our premium mail-in service.\n\nWe'll send you a complete mailing kit and sharpen them to perfection.",
            'city_found_message' => "Hooray! You're within our door-to-door service area.\nSimply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.",
            'city_selected_message' => "Hooray! You're within our door-to-door service area.\nSimply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.",
            'region_label' => 'Region',
            'city_search_label' => 'City (search)',
            'pickup_date_label' => 'Pickup date',
            'city_search_placeholder' => 'Search your city',
            'disable_shipping_address' => 1,
            'require_city_selection' => 0,
            'min_days_advance' => 0,
            'max_days_advance' => 90,
        );
    }

    /**
     * Get available WooCommerce payment methods
     */
    public static function get_payment_methods()
    {
        $methods = array();
        if (class_exists('WooCommerce') && function_exists('WC')) {
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (is_array($available_gateways)) {
                foreach ($available_gateways as $gateway_id => $gateway) {
                    $methods[$gateway_id] = $gateway->get_title();
                }
            }
        }
        return $methods;
    }
}
