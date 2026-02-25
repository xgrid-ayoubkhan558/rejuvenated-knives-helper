<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle core WooCommerce adjustment: Redirects, Translations, and Email meta
 */
final class RK_Woo_Core_Tweaks
{
    public static function init()
    {
        // Login redirect
        if (get_option('rk_enable_login_redirect', 0)) {
            add_filter('login_redirect', array(__CLASS__, 'redirect_to_my_account_after_login'), 10, 3);
        }

        // Rename Billing address to Delivery address
        if (get_option('rk_rename_billing_to_delivery', 0)) {
            add_filter('gettext', array(__CLASS__, 'rename_billing_address_text'), 20, 3);
        }

        // Hide region from emails
        if (get_option('rk_hide_region_emails', 0)) {
            add_filter('woocommerce_email_order_meta_fields', array(__CLASS__, 'hide_region_from_emails'), 10, 3);
        }
    }

    /**
     * Redirect users to "My Account" after login
     */
    public static function redirect_to_my_account_after_login($redirect_to, $requested_redirect_to, $user)
    {
        if (isset($user->ID) && function_exists('wc_get_page_permalink')) {
            return wc_get_page_permalink('myaccount');
        }
        return $redirect_to;
    }

    /**
     * Rename "Billing Address" to "Delivery Address"
     */
    public static function rename_billing_address_text($translated_text, $text, $domain)
    {
        if ($domain === 'woocommerce') {
            switch ($translated_text) {
                case 'Billing address':
                    $translated_text = 'Delivery address';
                    break;
                case 'Billing Address':
                    $translated_text = 'Delivery Address';
                    break;
                case 'Billing details':
                    $translated_text = 'Delivery details';
                    break;
            }
        }
        return $translated_text;
    }

    /**
     * Hide region from email message
     */
    public static function hide_region_from_emails($fields, $sent_to_admin, $order)
    {
        if (isset($fields['rk_region'])) {
            unset($fields['rk_region']);
        }
        return $fields;
    }
}
