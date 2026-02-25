<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Cart Sync frontend logic
 */
final class RK_Woo_Cart_Sync
{

    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    public static function enqueue_scripts()
    {
        if (!get_option('rk_enable_cart_sync_logic', 1)) {
            return;
        }

        wp_enqueue_script(
            'rk-cart-sync',
            plugin_dir_url(dirname(__FILE__) . '/../../') . 'assets/js/rk-cart-sync.js',
            array(),
            '1.0.0',
            true
        );
    }
}
