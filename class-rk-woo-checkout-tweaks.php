<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle WooCommerce Checkout form tweaks: field reordering, removals, and JS tweaks
 */
final class RK_Woo_Checkout_Tweaks
{
    public static function init()
    {
        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'handle_field_tweaks'), 999);

        // City autofill disable JS
        if (get_option('rk_disable_city_autofill', 0)) {
            add_action('wp_footer', array(__CLASS__, 'disable_city_field_autofill'), 999);
        }
    }

    /**
     * Consistently handle all field modifications
     */
    public static function handle_field_tweaks($fields)
    {
        // 1. Reorder Email Field
        if (get_option('rk_reorder_email_field', 0)) {
            if (isset($fields['billing']['billing_email'])) {
                $email = $fields['billing']['billing_email'];
                unset($fields['billing']['billing_email']);
                $fields['billing']['billing_email'] = $email;
                $fields['billing']['billing_first_name']['priority'] = 10;
                $fields['billing']['billing_last_name']['priority'] = 20;
                $fields['billing']['billing_email']['priority'] = 30;
            }
        }

        // 2. Remove Zipcode
        if (get_option('rk_remove_zipcode', 0)) {
            unset($fields['billing']['billing_postcode']);
            unset($fields['shipping']['shipping_postcode']);
        }

        // 3. Remove Country and State
        if (get_option('rk_remove_country_state', 0)) {
            unset($fields['billing']['billing_country']);
            unset($fields['billing']['billing_state']);
            unset($fields['shipping']['shipping_country']);
            unset($fields['shipping']['shipping_state']);
        }

        // 4. Address 2 Label
        if (get_option('rk_address_2_label_custom', 0)) {
            if (isset($fields['billing']['billing_address_2'])) {
                $fields['billing']['billing_address_2']['label'] = 'Address *';
                $fields['billing']['billing_address_2']['label_class'] = array();
            }
        }

        return $fields;
    }

    /**
     * Disable city field auto fill from cookies and cache
     */
    public static function disable_city_field_autofill()
    {
        if (!is_checkout() || is_order_received_page()) {
            return;
        }
        ?>
        <script>
            (function () {
                const field = document.getElementById('billing_rk_city_search');
                if (!field) return;

                // Disable browser autofill completely
                field.setAttribute('autocomplete', 'new-password');
                field.setAttribute('autocorrect', 'off');
                field.setAttribute('autocapitalize', 'off');
                field.setAttribute('spellcheck', 'false');

                // Clear initial value
                field.value = '';

                // Allow manual typing
                field.addEventListener('input', function () {
                    field.dataset.userTyping = 'true';
                });

                // Re-clear field on WooCommerce AJAX update only if user hasn't typed
                jQuery(document.body).on(
                    'updated_checkout updated_cart_totals country_to_state_changed',
                    function () {
                        if (!field.dataset.userTyping) {
                            field.value = '';
                        }
                    }
                );

            })();
        </script>
        <?php
    }
}
