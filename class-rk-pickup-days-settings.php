<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RK_Pickup_Days_Settings
{

    public static function init()
    {
        if (did_action('admin_init')) {
            self::register_settings();
        } else {
            add_action('admin_init', array(__CLASS__, 'register_settings'));
        }

        if (did_action('admin_menu')) {
            self::menu();
        } else {
            add_action('admin_menu', array(__CLASS__, 'menu'));
        }
    }

    public static function register_settings()
    {
        register_setting(
            'rk_pickup_days_settings',
            'rk_enable_advanced_pickup_columns',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => 1,
            )
        );

        register_setting(
            'rk_pickup_days_settings',
            'rk_enable_admin_debug',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => 0,
            )
        );
    }

    public static function menu()
    {
        add_menu_page(
            'RK Pickup Days',
            'RK Pickup Days',
            'manage_woocommerce',
            'rk-pickup-days-settings',
            array(__CLASS__, 'render_page'),
            'dashicons-calendar-alt',
            58
        );
    }

    public static function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $enabled = get_option('rk_enable_advanced_pickup_columns', 1);
        $debug = get_option('rk_enable_admin_debug', 0);

        echo '<div class="wrap">';
        echo '<h1>RK Pickup Days Settings</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('rk_pickup_days_settings');

        echo '<table class="form-table" role="presentation">';

        echo '<tr>';
        echo '<th scope="row">Enable Advanced Column</th>';
        echo '<td>';
        echo '<label><input name="rk_enable_advanced_pickup_columns" type="checkbox" value="1" ' . checked(1, $enabled, false) . ' /> Enable styled Pickup Days column in Locations admin</label>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">Admin Debug Mode</th>';
        echo '<td>';
        echo '<label><input name="rk_enable_admin_debug" type="checkbox" value="1" ' . checked(1, $debug, false) . ' /> Show Screen ID at the top of admin pages (Debug)</label>';
        echo '</td>';
        echo '</tr>';

        echo '</table>';

        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
