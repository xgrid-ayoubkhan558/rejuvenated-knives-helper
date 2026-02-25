<?php


if (!defined('ABSPATH')) {
    exit;
}


class RK_Checkout_Fields
{
    public function __construct()
    {
        // This class is instantiated on `plugins_loaded` (from the main plugin file).
        // If we also hook `init()` to `plugins_loaded`, it will never run because that
        // action has already fired. Hook into WooCommerce's init point instead.
        if (did_action('woocommerce_init')) {
            $this->init();
            return;
        }

        add_action('woocommerce_init', array($this, 'init'));
    }

    public function init()
    {
        // Only run when WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Add fields into checkout billing fields
        add_filter('woocommerce_checkout_fields', array($this, 'checkout_fields'));

        // Add hidden city and service type fields manually (after WooCommerce fields)
        add_action('woocommerce_after_checkout_billing_form', array($this, 'add_hidden_fields'));

        // Disable "Ship to a different address" checkbox (if enabled in settings)
        add_action('wp', array($this, 'maybe_disable_shipping_address'));

        // Validate
        add_action('woocommerce_checkout_process', array($this, 'checkout_validate'));

        // Dynamically modify field requirements based on POST data (runs during checkout)
        add_filter('woocommerce_checkout_fields', array($this, 'modify_city_search_requirements'), 9999);

        // Remove validation notices for city search field if it has a value (runs early)
        add_action('woocommerce_checkout_process', array($this, 'remove_city_search_notices'), 5);

        // Save
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_order_meta'));

        // Display in admin (after billing address)
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_order_meta'), 10, 1);

        // Add to emails
        add_filter('woocommerce_email_order_meta_fields', array($this, 'email_order_meta_fields'), 10, 3);

        // Enqueue frontend assets on checkout
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Print a hidden container with locations JSON so frontend can read it from the DOM
        add_action('woocommerce_before_checkout_form', array($this, 'print_locations_div'));

        // AJAX endpoint to provide locations JSON if needed
        add_action('wp_ajax_nopriv_rk_get_locations', array($this, 'ajax_get_locations'));
        add_action('wp_ajax_rk_get_locations', array($this, 'ajax_get_locations'));

        // Admin settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

    }

    /**
     * Add hidden fields manually after billing form
     * This ensures proper field names without billing_ prefix if desired,
     * but here we use billing_ prefix to be consistent with WC.
     */
    public function add_hidden_fields($checkout)
    {
        echo '<input type="hidden" name="billing_rk_city" id="billing_rk_city" value="" />';
        echo '<input type="hidden" name="billing_rk_service_type" id="billing_rk_service_type" value="door-to-door" />';
    }

    /**
     * Echo a hidden div with the locations JSON for the frontend to read
     */
    public function print_locations_div()
    {
        if (!is_checkout()) {
            return;
        }

        $locations = $this->get_locations_data();
        if (empty($locations)) {
            return;
        }

        $locations_json = htmlspecialchars(wp_json_encode($locations), ENT_QUOTES, 'UTF-8');

        echo '<div id="rk-check-fields-data" style="display:none" data-regions="' . $locations_json . '"></div>';
    }

    /**
     * AJAX handler to return locations JSON
     */
    public function ajax_get_locations()
    {
        $locations = $this->get_locations_data();
        wp_send_json_success($locations);
    }

    /**
     * Enqueue scripts/styles on checkout page
     */
    public function enqueue_assets()
    {
        if (!is_checkout()) {
            return;
        }

        // flatpickr (CDN)
        wp_enqueue_style('rk-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), null);
        wp_enqueue_script('rk-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), null, true);

        // Our checkout CSS & script
        wp_enqueue_style('rk-checkout-css', plugin_dir_url(__FILE__) . '../assets/css/rk-checkout.css', array(), filemtime(plugin_dir_path(__FILE__) . '../assets/css/rk-checkout.css'));
        wp_enqueue_script('rk-checkout', plugin_dir_url(__FILE__) . '../assets/js/rk-checkout.js', array('rk-flatpickr', 'jquery'), filemtime(plugin_dir_path(__FILE__) . '../assets/js/rk-checkout.js'), true);

        // Localize data for frontend
        $data = $this->get_locations_data();

        // Debug: Log the data
        error_log('[RK Debug] Locations data: ' . print_r($data, true));

        wp_localize_script('rk-checkout', 'rk_check_fields_data', $data);
        // Provide AJAX URL for JS fallback
        wp_localize_script('rk-checkout', 'rk_check_fields_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

        // Localize plugin options (include all settings for frontend)
        $opts = $this->get_plugin_options();
        error_log('[RK Debug] Date format being sent to frontend: ' . $opts['date_format']);
        wp_localize_script('rk-checkout', 'rk_check_fields_options', $opts);

        // Debug: Add console log for debugging
        wp_add_inline_script('rk-checkout', 'console.log("[RK Debug] Script loaded with data:", window.rk_check_fields_data); console.log("[RK Debug] Options:", window.rk_check_fields_options); console.log("[RK Debug] Date format value:", window.rk_check_fields_options.date_format);');
    }

    /**
     * Build regions + cities data from 'locations' taxonomy
     * Returns a structured array suitable for JSON.
     *
     * @return array
     */
    public function get_locations_data()
    {
        $regions = get_terms(array(
            'taxonomy' => 'locations',
            'hide_empty' => false,
            'parent' => 0,
        ));

        $data = array();

        if (is_wp_error($regions) || empty($regions)) {
            return $data;
        }

        foreach ($regions as $region) {
            $regionData = array(
                'region_name' => $region->name,
                'region_id' => $region->term_id,
                'region_delivery_days' => array(),
                'pickup' => array(),
                'cities' => array(),
            );

            // Try to read ACF fields if available, fallback to term meta
            if (function_exists('get_field')) {
                $regionData['region_id'] = get_field('region_id', $region) ?: $region->term_id;
                $regionData['region_delivery_days'] = get_field('region_delivery_days', $region) ?: array();

                $days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
                foreach ($days as $day) {
                    $regionData['pickup'][$day] = array(
                        'enabled' => (bool) get_field("region_pickup_{$day}_enabled", $region),
                        'start' => get_field("region_pickup_{$day}_start_time", $region),
                        'end' => get_field("region_pickup_{$day}_end_time", $region),
                    );
                }
            } else {
                // fallback: read term meta fields
                $regionData['region_id'] = get_term_meta($region->term_id, 'region_id', true) ?: $region->term_id;
                $regionData['region_delivery_days'] = get_term_meta($region->term_id, 'region_delivery_days', true) ?: array();

                $days = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
                foreach ($days as $day) {
                    $regionData['pickup'][$day] = array(
                        'enabled' => (bool) get_term_meta($region->term_id, "region_pickup_{$day}_enabled", true),
                        'start' => get_term_meta($region->term_id, "region_pickup_{$day}_start_time", true),
                        'end' => get_term_meta($region->term_id, "region_pickup_{$day}_end_time", true),
                    );
                }


            }
            // Cities (children of region)
            $cities = get_terms(array(
                'taxonomy' => 'locations',
                'hide_empty' => false,
                'parent' => $region->term_id,
            ));

            if (!is_wp_error($cities) && !empty($cities)) {
                foreach ($cities as $city) {
                    $regionData['cities'][] = array(
                        'city_name' => $city->name,
                        'city_id' => $city->term_id,
                    );
                }
            }

            $data[] = $regionData;
        }

        return $data;
    }

    /**
     * Admin menu & settings
     */
    public function add_admin_menu()
    {
        add_submenu_page('woocommerce', 'RK Checkout Fields', 'RK Checkout Fields', 'manage_woocommerce', 'rk-checkout-fields', array($this, 'settings_page'));
    }

    public function register_settings()
    {
        register_setting('rk_cf_settings', 'rk_cf_options', array($this, 'sanitize_options'));

        // Payment Settings Section
        add_settings_section('rk_cf_payment', __('Payment Settings', 'rk-helper'), array($this, 'section_payment_callback'), 'rk-checkout-fields');

        add_settings_field('enable_auto_payment', __('Enable auto payment selection', 'rk-helper'), array($this, 'field_enable_auto_payment'), 'rk-checkout-fields', 'rk_cf_payment');
        add_settings_field('payment_found', __('Payment method (city found)', 'rk-helper'), array($this, 'field_payment_found'), 'rk-checkout-fields', 'rk_cf_payment');
        add_settings_field('payment_not_found', __('Payment method (city not found)', 'rk-helper'), array($this, 'field_payment_not_found'), 'rk-checkout-fields', 'rk_cf_payment');

        // Display Settings Section
        add_settings_section('rk_cf_display', __('Display Settings', 'rk-helper'), array($this, 'section_display_callback'), 'rk-checkout-fields');

        add_settings_field('add_body_classes', __('Add body classes', 'rk-helper'), array($this, 'field_add_body_classes'), 'rk-checkout-fields', 'rk_cf_display');
        add_settings_field('date_format', __('Date picker format', 'rk-helper'), array($this, 'field_date_format'), 'rk-checkout-fields', 'rk_cf_display');
        add_settings_field('disable_shipping_address', __('Disable shipping address', 'rk-helper'), array($this, 'field_disable_shipping_address'), 'rk-checkout-fields', 'rk_cf_display');
        add_settings_field('require_city_selection', __('Require city selection from dropdown', 'rk-helper'), array($this, 'field_require_city_selection'), 'rk-checkout-fields', 'rk_cf_display');
        add_settings_field('min_days_advance', __('Minimum days in advance for pickup', 'rk-helper'), array($this, 'field_min_days_advance'), 'rk-checkout-fields', 'rk_cf_display');
        add_settings_field('max_days_advance', __('Maximum days in advance for pickup', 'rk-helper'), array($this, 'field_max_days_advance'), 'rk-checkout-fields', 'rk_cf_display');

        // Messages Section
        add_settings_section('rk_cf_messages', __('Messages', 'rk-helper'), array($this, 'section_messages_callback'), 'rk-checkout-fields');

        add_settings_field('mailin_message', __('Mail-in message (no match)', 'rk-helper'), array($this, 'field_mailin_message'), 'rk-checkout-fields', 'rk_cf_messages');
        add_settings_field('city_found_message', __('City found message', 'rk-helper'), array($this, 'field_city_found_message'), 'rk-checkout-fields', 'rk_cf_messages');
        add_settings_field('city_selected_message', __('City selected message', 'rk-helper'), array($this, 'field_city_selected_message'), 'rk-checkout-fields', 'rk_cf_messages');

        // Field Labels Section
        add_settings_section('rk_cf_labels', __('Field Labels', 'rk-helper'), array($this, 'section_labels_callback'), 'rk-checkout-fields');

        add_settings_field('region_label', __('Region field label', 'rk-helper'), array($this, 'field_region_label'), 'rk-checkout-fields', 'rk_cf_labels');
        add_settings_field('city_search_label', __('City search field label', 'rk-helper'), array($this, 'field_city_search_label'), 'rk-checkout-fields', 'rk_cf_labels');
        add_settings_field('pickup_date_label', __('Pickup date field label', 'rk-helper'), array($this, 'field_pickup_date_label'), 'rk-checkout-fields', 'rk_cf_labels');
        add_settings_field('city_search_placeholder', __('City search placeholder', 'rk-helper'), array($this, 'field_city_search_placeholder'), 'rk-checkout-fields', 'rk_cf_labels');
    }

    public function section_payment_callback()
    {
        echo '<p>' . esc_html__('Configure automatic payment method selection based on city availability.', 'rk-helper') . '</p>';
    }

    public function section_display_callback()
    {
        echo '<p>' . esc_html__('Configure display options and styling features.', 'rk-helper') . '</p>';
    }

    public function section_messages_callback()
    {
        echo '<p>' . esc_html__('Customize messages shown to customers during checkout.', 'rk-helper') . '</p>';
    }

    public function section_labels_callback()
    {
        echo '<p>' . esc_html__('Customize field labels and placeholders shown on the checkout page.', 'rk-helper') . '</p>';
    }

    public function sanitize_options($input)
    {
        $defaults = $this->get_plugin_options();
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

    public function get_plugin_options()
    {
        $defaults = array(
            'enable_auto_payment' => 1,
            'payment_found' => 'cod',
            'payment_not_found' => 'other_payment',
            'add_body_classes' => 1,
            'date_format' => 'd-m-Y',
            'mailin_message' => "Good news!\n\nWhile your location is outside our door-to-door coverage area, you can mail in your knives using our premium mail-in service.\n\nWe'll send you a complete mailing kit and sharpen them to perfection.",
            'city_found_message' => "Hooray! You're within our door-to-door service area.\nSimply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.",
            'city_selected_message' => "Hooray! You're within our door-to-door service area.\nSimply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.",
            'region_label' => __('Region', 'rk-helper'),
            'city_search_label' => __('City (search)', 'rk-helper'),
            'pickup_date_label' => __('Pickup date', 'rk-helper'),
            'city_search_placeholder' => __('Search your city', 'rk-helper'),
            'disable_shipping_address' => 1,
            'require_city_selection' => 0,
            'min_days_advance' => 0,
            'max_days_advance' => 90,
        );
        $opts = get_option('rk_cf_options', array());
        return wp_parse_args($opts, $defaults);
    }

    /**
     * Get available WooCommerce payment methods
     */
    public function get_payment_methods()
    {
        $methods = array();
        if (class_exists('WooCommerce') && function_exists('WC')) {
            $wc = WC();
            if ($wc && isset($wc->payment_gateways)) {
                $available_gateways = $wc->payment_gateways->get_available_payment_gateways();
                if (is_array($available_gateways)) {
                    foreach ($available_gateways as $gateway_id => $gateway) {
                        if (is_object($gateway) && method_exists($gateway, 'get_title')) {
                            $methods[$gateway_id] = $gateway->get_title();
                        }
                    }
                }
            }
        }
        return $methods;
    }

    public function field_enable_auto_payment()
    {
        $opts = $this->get_plugin_options();
        echo '<label><input type="checkbox" name="rk_cf_options[enable_auto_payment]" value="1" ' . checked(1, $opts['enable_auto_payment'], false) . ' /> ' . esc_html__('Automatically select payment method based on city availability', 'rk-helper') . '</label>';
        echo '<p class="description">' . esc_html__('When enabled, the plugin will automatically select the configured payment method when a city is found or not found.', 'rk-helper') . '</p>';
    }

    public function field_payment_found()
    {
        $opts = $this->get_plugin_options();
        $methods = $this->get_payment_methods();

        if (!empty($methods)) {
            echo '<select name="rk_cf_options[payment_found]" class="regular-text">';
            echo '<option value="">' . esc_html__('-- Select Payment Method --', 'rk-helper') . '</option>';
            foreach ($methods as $method_id => $method_title) {
                echo '<option value="' . esc_attr($method_id) . '" ' . selected($opts['payment_found'], $method_id, false) . '>' . esc_html($method_title) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="rk_cf_options[payment_found]" value="' . esc_attr($opts['payment_found']) . '" class="regular-text" placeholder="e.g., cod" />';
            echo '<p class="description">' . esc_html__('Enter payment method ID (e.g., cod, bacs). Available methods will appear as dropdown if WooCommerce is active.', 'rk-helper') . '</p>';
        }
        echo '<p class="description">' . esc_html__('Payment method to automatically select when a city is found in the service area.', 'rk-helper') . '</p>';
    }

    public function field_payment_not_found()
    {
        $opts = $this->get_plugin_options();
        $methods = $this->get_payment_methods();

        if (!empty($methods)) {
            echo '<select name="rk_cf_options[payment_not_found]" class="regular-text">';
            echo '<option value="">' . esc_html__('-- Select Payment Method --', 'rk-helper') . '</option>';
            foreach ($methods as $method_id => $method_title) {
                echo '<option value="' . esc_attr($method_id) . '" ' . selected($opts['payment_not_found'], $method_id, false) . '>' . esc_html($method_title) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="rk_cf_options[payment_not_found]" value="' . esc_attr($opts['payment_not_found']) . '" class="regular-text" placeholder="e.g., payment_method_other_payment" />';
            echo '<p class="description">' . esc_html__('Enter payment method ID (e.g., cod, bacs). Available methods will appear as dropdown if WooCommerce is active.', 'rk-helper') . '</p>';
        }
        echo '<p class="description">' . esc_html__('Payment method to automatically select when a city is NOT found in the service area.', 'rk-helper') . '</p>';
    }

    public function field_add_body_classes()
    {
        $opts = $this->get_plugin_options();
        echo '<label><input type="checkbox" name="rk_cf_options[add_body_classes]" value="1" ' . checked(1, $opts['add_body_classes'], false) . ' /> ' . esc_html__('Add CSS classes to body element', 'rk-helper') . '</label>';
        echo '<p class="description">' . esc_html__('Adds classes like "rk-city-found", "rk-city-not-found", and "rk-city-selected" to the body element for custom styling.', 'rk-helper') . '</p>';
    }

    public function field_date_format()
    {
        $opts = $this->get_plugin_options();
        $formats = array(
            'm-d-Y' => 'Month-Day-Year (e.g., 12-25-2024)',
            'd-m-Y' => 'Day-Month-Year (e.g., 25-12-2024)',
            'Y-m-d' => 'Year-Month-Day (e.g., 2024-12-25)',
            'd/m/Y' => 'Day/Month/Year (e.g., 25/12/2024)',
            'm/d/Y' => 'Month/Day/Year (e.g., 12/25/2024)',
            'F j, Y' => 'Month Full Name (e.g., December 25, 2024)',
        );

        echo '<select name="rk_cf_options[date_format]" class="regular-text">';
        foreach ($formats as $format => $label) {
            echo '<option value="' . esc_attr($format) . '" ' . selected($opts['date_format'], $format, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Date format for the pickup date picker. Uses flatpickr date format syntax.', 'rk-helper') . '</p>';
    }

    public function field_mailin_message()
    {
        $opts = $this->get_plugin_options();
        echo '<textarea name="rk_cf_options[mailin_message]" rows="6" cols="60" class="large-text">' . esc_textarea($opts['mailin_message']) . '</textarea>';
        echo '<p class="description">' . esc_html__('Message shown when customer searches for a city that is not in the service area. Supports line breaks.', 'rk-helper') . '</p>';
    }

    public function field_city_found_message()
    {
        $opts = $this->get_plugin_options();
        echo '<textarea name="rk_cf_options[city_found_message]" rows="4" cols="60" class="large-text">' . esc_textarea($opts['city_found_message']) . '</textarea>';
        echo '<p class="description">' . esc_html__('Message shown when matching cities are found. Supports line breaks.', 'rk-helper') . '</p>';
    }

    public function field_city_selected_message()
    {
        $opts = $this->get_plugin_options();
        echo '<textarea name="rk_cf_options[city_selected_message]" rows="4" cols="60" class="large-text">' . esc_textarea($opts['city_selected_message']) . '</textarea>';
        echo '<p class="description">' . esc_html__('Message shown after a city is selected. Supports line breaks.', 'rk-helper') . '</p>';
    }

    public function field_region_label()
    {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[region_label]" value="' . esc_attr($opts['region_label']) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Label for the region field on checkout.', 'rk-helper') . '</p>';
    }

    public function field_city_search_label()
    {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[city_search_label]" value="' . esc_attr($opts['city_search_label']) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Label for the city search field on checkout.', 'rk-helper') . '</p>';
    }

    public function field_pickup_date_label()
    {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[pickup_date_label]" value="' . esc_attr($opts['pickup_date_label']) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Label for the pickup date field on checkout.', 'rk-helper') . '</p>';
    }

    public function field_city_search_placeholder()
    {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[city_search_placeholder]" value="' . esc_attr($opts['city_search_placeholder']) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Placeholder text for the city search input field.', 'rk-helper') . '</p>';
    }

    public function field_disable_shipping_address()
    {
        $opts = $this->get_plugin_options();
        echo '<label><input type="checkbox" name="rk_cf_options[disable_shipping_address]" value="1" ' . checked(1, $opts['disable_shipping_address'], false) . ' /> ' . esc_html__('Hide "Ship to a different address" checkbox', 'rk-helper') . '</label>';
        echo '<p class="description">' . esc_html__('When enabled, the shipping address section will be hidden and customers can only use billing address.', 'rk-helper') . '</p>';
    }

    public function field_require_city_selection()
    {
        $opts = $this->get_plugin_options();
        echo '<label><input type="checkbox" name="rk_cf_options[require_city_selection]" value="1" ' . checked(1, $opts['require_city_selection'], false) . ' /> ' . esc_html__('Require city selection from dropdown only', 'rk-helper') . '</label>';
        echo '<p class="description">' . esc_html__('When enabled, customers must select a city from the dropdown list. Manual text input will not be accepted.', 'rk-helper') . '</p>';
    }

    public function field_min_days_advance()
    {
        $opts = $this->get_plugin_options();
        echo '<input type="number" name="rk_cf_options[min_days_advance]" value="' . esc_attr($opts['min_days_advance']) . '" class="small-text" min="0" />';
        echo '<p class="description">' . esc_html__('Minimum number of days in advance for pickup date selection.', 'rk-helper') . '</p>';
    }

    public function field_max_days_advance()
    {
        $opts = $this->get_plugin_options();
        echo '<input type="number" name="rk_cf_options[max_days_advance]" value="' . esc_attr($opts['max_days_advance']) . '" class="small-text" min="1" />';
        echo '<p class="description">' . esc_html__('Maximum number of days in advance for pickup date selection.', 'rk-helper') . '</p>';
    }

    /**
     * Settings page output
     */
    public function settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('rk_cf_settings');
                do_settings_sections('rk-checkout-fields');
                submit_button(__('Save Settings', 'rk-helper'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Add custom checkout fields
     */
    public function checkout_fields($fields)
    {
        $opts = $this->get_plugin_options();

        // Region field
        $fields['billing']['billing_rk_region'] = array(
            'label' => $opts['region_label'],
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 40,
            'type' => 'select',
            'options' => $this->get_region_options(),
            'default' => '',
        );

        // City search field
        $fields['billing']['billing_rk_city_search'] = array(
            'label' => $opts['city_search_label'],
            'required' => true,
            'class' => array('form-row-wide', 'rk-city-search-field'),
            'priority' => 41,
            'type' => 'text',
            'placeholder' => $opts['city_search_placeholder'],
        );

        // Pickup date field
        $fields['billing']['billing_rk_pickup_date'] = array(
            'label' => $opts['pickup_date_label'],
            'required' => false,
            'class' => array('form-row-wide', 'rk-pickup-date-field'),
            'priority' => 42,
            'type' => 'text',
            'input_class' => array('rk-flatpickr'),
        );

        // If require city selection is enabled
        if (!empty($opts['require_city_selection'])) {
            // Mark billing_rk_city as required instead of billing_rk_city_search
            if (isset($fields['billing']['billing_rk_city'])) {
                $fields['billing']['billing_rk_city']['required'] = true;
            }
            if (isset($fields['billing']['billing_rk_city_search'])) {
                $fields['billing']['billing_rk_city_search']['required'] = false;
            }
        }

        return $fields;
    }

    /**
     * Get region options for dropdown
     */
    private function get_region_options()
    {
        $regions = get_terms(array(
            'taxonomy' => 'locations',
            'hide_empty' => false,
            'parent' => 0,
        ));

        $options = array('' => __('Select a region', 'rk-helper'));

        if (!is_wp_error($regions) && !empty($regions)) {
            foreach ($regions as $region) {
                $options[$region->name] = $region->name;
            }
        }

        return $options;
    }

    /**
     * Maybe disable shipping address based on settings
     */
    public function maybe_disable_shipping_address()
    {
        $opts = $this->get_plugin_options();

        if (!empty($opts['disable_shipping_address'])) {
            add_filter('woocommerce_ship_to_different_address_checked', '__return_false');
            add_filter('woocommerce_checkout_show_shipping_address', '__return_false');
        }
    }

    /**
     * Modify city search field requirements based on POST data
     */
    public function modify_city_search_requirements($fields)
    {
        if (isset($_POST['billing_rk_region']) && !empty($_POST['billing_rk_region'])) {
            // Region is selected - check if cities are found
            if (isset($_POST['billing_rk_city']) && !empty($_POST['billing_rk_city'])) {
                // City found - city search is optional
                if (isset($fields['billing']['billing_rk_city_search'])) {
                    $fields['billing']['billing_rk_city_search']['required'] = false;
                }
            }
        }

        return $fields;
    }

    /**
     * Remove validation notices for city search if it has a value
     */
    public function remove_city_search_notices()
    {
        if (!empty($_POST['billing_rk_city_search'])) {
            // City search has a value - remove any validation notices
            wc()->session->set('flash_messages', array());
        }
    }

    /**
     * Checkout validation
     */
    public function checkout_validate()
    {
        $opts = $this->get_plugin_options();

        // Validate region
        if (empty($_POST['billing_rk_region'])) {
            wc_add_notice(__('Please select a region.', 'rk-helper'), 'error');
        }

        // Validate city (either search or dropdown based on settings)
        if (empty($opts['require_city_selection'])) {
            if (empty($_POST['billing_rk_city_search']) && empty($_POST['billing_rk_city'])) {
                wc_add_notice(__('Please search for your city.', 'rk-helper'), 'error');
            }
        } else {
            // Require city selection from dropdown
            if (empty($_POST['billing_rk_city'])) {
                wc_add_notice(__('Please select a city from the list.', 'rk-helper'), 'error');
            }
        }
    }

    /**
     * Save order meta
     */
    public function save_order_meta($order_id)
    {
        if (!empty($_POST['billing_rk_region'])) {
            update_post_meta($order_id, 'rk_region', sanitize_text_field($_POST['billing_rk_region']));
        }

        if (!empty($_POST['billing_rk_city'])) {
            update_post_meta($order_id, 'rk_city', sanitize_text_field($_POST['billing_rk_city']));
        }

        if (!empty($_POST['billing_rk_city_search'])) {
            update_post_meta($order_id, 'rk_city_search', sanitize_text_field($_POST['billing_rk_city_search']));
        }

        if (!empty($_POST['billing_rk_pickup_date'])) {
            update_post_meta($order_id, 'rk_pickup_date', sanitize_text_field($_POST['billing_rk_pickup_date']));
        }

        if (!empty($_POST['billing_rk_service_type'])) {
            update_post_meta($order_id, 'rk_service_type', sanitize_text_field($_POST['billing_rk_service_type']));
        }
    }

    /**
     * Display in admin order
     */
    public function display_admin_order_meta($order)
    {
        $region = get_post_meta($order->get_id(), 'rk_region', true);
        $city = get_post_meta($order->get_id(), 'rk_city', true);
        $city_search = get_post_meta($order->get_id(), 'rk_city_search', true);
        $pickup_date = get_post_meta($order->get_id(), 'rk_pickup_date', true);
        $service_type = get_post_meta($order->get_id(), 'rk_service_type', true);

        if ($region) {
            echo '<p><strong>' . esc_html__('Region', 'rk-helper') . ':</strong> ' . esc_html($region) . '</p>';
        }

        if ($city) {
            echo '<p><strong>' . esc_html__('City', 'rk-helper') . ':</strong> ' . esc_html($city) . '</p>';
        }

        if ($city_search) {
            echo '<p><strong>' . esc_html__('City Search', 'rk-helper') . ':</strong> ' . esc_html($city_search) . '</p>';
        }

        if ($pickup_date) {
            echo '<p><strong>' . esc_html__('Pickup Date', 'rk-helper') . ':</strong> ' . esc_html($pickup_date) . '</p>';
        }

        if ($service_type) {
            echo '<p><strong>' . esc_html__('Service Type', 'rk-helper') . ':</strong> ' . esc_html($service_type) . '</p>';
        }
    }

    /**
     * Add to emails
     */
    public function email_order_meta_fields($fields, $sent_to_admin, $order)
    {
        if (!$order) {
            return $fields;
        }

        $region = get_post_meta($order->get_id(), 'rk_region', true);
        $city = get_post_meta($order->get_id(), 'rk_city', true);
        $pickup_date = get_post_meta($order->get_id(), 'rk_pickup_date', true);
        $service_type = get_post_meta($order->get_id(), 'rk_service_type', true);

        if ($region) {
            $fields['rk_region'] = array(
                'label' => __('Region', 'rk-helper'),
                'value' => $region,
            );
        }

        if ($city) {
            $fields['rk_city'] = array(
                'label' => __('City', 'rk-helper'),
                'value' => $city,
            );
        }

        if ($pickup_date) {
            $fields['rk_pickup_date'] = array(
                'label' => __('Pickup Date', 'rk-helper'),
                'value' => $pickup_date,
            );
        }

        if ($service_type) {
            $fields['rk_service_type'] = array(
                'label' => __('Service Type', 'rk-helper'),
                'value' => $service_type,
            );
        }

        return $fields;
    }
}
