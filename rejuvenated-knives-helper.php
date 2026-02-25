<?php
/**
 * Plugin Name: Rejuvenated Knives Helper
 * Plugin URI:  https://www.xgrid.co/
 * Description: Custom site logic: Checkout field modifications, dynamic body classes, and conditional checkout rules.
 * Version:     1.0.2
 * Author:      Ayoub Khan 
 * Text Domain: rk-helper
 */

if (!defined('ABSPATH')) {
    exit;
}

function rk_helper_init()
{
    $includes = array(
        'class-rk-checkout-fields.php',
        'class-rk-woo-ajax-cart-count.php',
        'class-rk-woo-ajax-cart-count-admin.php',
        'class-rk-locations-pickup-days.php',
        'class-rk-woo-cart-sync.php',
        'class-rk-woo-core-tweaks.php',
        'class-rk-woo-checkout-tweaks.php',
        'class-rk-woo-phone-tweaks.php',
        'class-rk-admin-security-tweaks.php',
    );

    foreach ($includes as $file) {
        $path = plugin_dir_path(__FILE__) . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // Initialize logic classes
    if (class_exists('RK_Checkout_Fields')) {
        new RK_Checkout_Fields();
    }

    if (class_exists('RK_Woo_Ajax_Cart_Count_Logic')) {
        RK_Woo_Ajax_Cart_Count_Logic::init();
    }

    if (class_exists('RK_Woo_Ajax_Cart_Count_Admin')) {
        RK_Woo_Ajax_Cart_Count_Admin::init();
    }

    if (class_exists('RK_Locations_Pickup_Days')) {
        RK_Locations_Pickup_Days::init();
    }

    if (class_exists('RK_Woo_Cart_Sync')) {
        RK_Woo_Cart_Sync::init();
    }

    // New logic classes
    if (class_exists('RK_Woo_Core_Tweaks')) {
        RK_Woo_Core_Tweaks::init();
    }

    if (class_exists('RK_Woo_Checkout_Tweaks')) {
        RK_Woo_Checkout_Tweaks::init();
    }

    if (class_exists('RK_Woo_Phone_Tweaks')) {
        RK_Woo_Phone_Tweaks::init();
    }

    if (class_exists('RK_Admin_Security_Tweaks')) {
        RK_Admin_Security_Tweaks::init();
    }
}

if (did_action('plugins_loaded')) {
    rk_helper_init();
} else {
    add_action('plugins_loaded', 'rk_helper_init');
}