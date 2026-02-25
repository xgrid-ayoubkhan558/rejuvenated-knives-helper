<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Admin specific adjustments like user role restrictions
 */
final class RK_Admin_Security_Tweaks
{
    public static function init()
    {
        // Shop Manager Limitation
        if (get_option('rk_limit_shop_manager', 0)) {
            add_action('admin_menu', array(__CLASS__, 'restrict_shop_manager_menu'), 999);
        }
    }

    /**
     * Remove menu pages for Shop Managers
     */
    public static function restrict_shop_manager_menu()
    {
        if (!current_user_can('shop_manager')) {
            return;
        }

        global $menu;

        // Allowed menu slugs
        $allowed = array(
            'woocommerce',              // WooCommerce
            'edit.php?post_type=product', // Products
            'users.php',                 // Customers (Users)
            'profile.php',               // Profile
            'rk-woo-ajax-cart-count'     // The RK Helper settings itself (so they can fix it if needed)
        );

        foreach ($menu as $key => $item) {
            if (!in_array($item[2], $allowed)) {
                remove_menu_page($item[2]);
            }
        }
    }
}
