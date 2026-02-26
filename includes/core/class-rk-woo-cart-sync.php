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
            plugins_url('assets/js/rk-cart-sync.js', RK_HELPER_FILE),
            array(),
            '1.0.0',
            true
        );
    }
}
