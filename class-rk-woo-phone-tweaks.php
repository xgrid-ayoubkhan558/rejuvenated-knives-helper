<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle WooCommerce Phone field adjustments: prefixing, formatting, and validation
 */
final class RK_Woo_Phone_Tweaks
{
    public static function init()
    {
        // Add country calling code prefix logic
        if (get_option('rk_enable_phone_prefix', 0)) {
            add_action('wp_footer', array(__CLASS__, 'add_callback_script'));
            add_action('wp_ajax_nopriv_append_country_prefix_in_billing_phone', array(__CLASS__, 'add_phone_prefix'));
            add_action('wp_ajax_append_country_prefix_in_billing_phone', array(__CLASS__, 'add_phone_prefix'));
        }

        // Apply US phone formatting and general validation
        if (get_option('rk_us_phone_validation', 0)) {
            add_filter('woocommerce_checkout_fields', array(__CLASS__, 'apply_us_phone_formatting'), 999);
            add_action('woocommerce_after_checkout_validation', array(__CLASS__, 'validate_us_phone_number'), 10, 2);
            // Also include the minimum 6 digits validation
            add_action('woocommerce_checkout_process', array(__CLASS__, 'validate_phone_length'));
        }
    }

    /**
     * Outputs the JavaScript required for updating the billing phone with the country prefix.
     */
    public static function add_callback_script()
    {
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let lastPrefix = '';
                document.body.addEventListener('updated_checkout', function () {
                    const countryField = document.getElementById('billing_country');
                    const phoneField = document.getElementById('billing_phone');
                    if (!countryField || !phoneField) return;
                    const data = new URLSearchParams();
                    data.append('action', 'append_country_prefix_in_billing_phone');
                    data.append('country_code', countryField.value);
                    fetch('<?php echo esc_js($ajax_url); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: data.toString()
                    })
                        .then(response => response.text())
                        .then(prefix => {
                            if (!prefix) return;
                            if (phoneField.value === '') {
                                phoneField.value = prefix;
                            } else if (lastPrefix && phoneField.value.startsWith(lastPrefix)) {
                                phoneField.value = phoneField.value.replace(lastPrefix, prefix);
                            }
                            lastPrefix = prefix;
                        })
                        .catch(error => { console.error('Phone prefix error:', error); });
                });
            });
        </script>
        <?php
    }

    /**
     * Handles AJAX request to get country calling code.
     */
    public static function add_phone_prefix()
    {
        $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
        $calling_code = '';
        if ($country_code && function_exists('WC')) {
            $calling_codes = WC()->countries->get_country_calling_code($country_code);
            $calling_code = is_array($calling_codes) ? $calling_codes[0] : $calling_codes;
        }
        echo esc_html($calling_code);
        wp_die();
    }

    /**
     * Apply USA specific phone field formatting
     */
    public static function apply_us_phone_formatting($fields)
    {
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['placeholder'] = 'US Phone (e.g. 555-123-4567)';
            $fields['billing']['billing_phone']['label'] = 'Phone Number *';
            $fields['billing']['billing_phone']['description'] = 'Please enter a valid US phone number. Example: 555-123-4567';
        }
        return $fields;
    }

    /**
     * Validate the phone number format (USA)
     */
    public static function validate_us_phone_number($data, $errors)
    {
        if (!empty($data['billing_phone'])) {
            if (!preg_match('/^(\+1)?\s*\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/', $data['billing_phone'])) {
                $errors->add('billing_phone_error', __('Please enter a valid US phone number (e.g. 555-123-4567).'));
            }
        }
    }

    /**
     * Validates the phone number length
     */
    public static function validate_phone_length()
    {
        if (isset($_POST['billing_phone']) && strlen(preg_replace('/[^0-9]/', '', $_POST['billing_phone'])) < 6) {
            wc_add_notice(__('Billing Phone must be at least 6 digits long.', 'woocommerce'), 'error');
        }
    }
}
