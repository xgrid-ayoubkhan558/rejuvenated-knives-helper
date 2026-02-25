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
     * Add custom checkout fields
     */
    public function checkout_fields($fields)
    {
        $opts = self::get_plugin_options();

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
