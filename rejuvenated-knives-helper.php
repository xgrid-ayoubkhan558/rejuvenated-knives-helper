<?php
/**
 * Plugin Name: Rejuvenated Knives Helper
 * Plugin URI:  https://www.xgrid.co/
 * Description: Custom site logic: Checkout field modifications, dynamic body classes, and conditional checkout rules.
 * Version:     1.0.1
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
        'class-rk-pickup-days-settings.php',
    );

    foreach ($includes as $file) {
        $path = plugin_dir_path(__FILE__) . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

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

    if (class_exists('RK_Pickup_Days_Settings')) {
        RK_Pickup_Days_Settings::init();
    }
}

if (did_action('plugins_loaded')) {
    rk_helper_init();
} else {
    add_action('plugins_loaded', 'rk_helper_init');
}