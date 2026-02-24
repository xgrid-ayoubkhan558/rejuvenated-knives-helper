<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pickup Days column for Locations taxonomy — ACP compatible
 */
final class RK_Locations_Pickup_Days
{

    public static function init()
    {
        // Debug Screen ID (only if debug is enabled in settings)
        if (get_option('rk_enable_admin_debug', 0)) {
            add_action('admin_head', array(__CLASS__, 'debug_screen_id'));
        }

        // Only run if feature is enabled
        if (!get_option('rk_enable_advanced_pickup_columns', 1)) {
            return;
        }

        // Register column (works alongside Admin Columns Pro)
        add_filter('manage_edit-locations_columns', array(__CLASS__, 'add_pickup_days_column'), 999);
        add_filter('manage_edit-location_columns', array(__CLASS__, 'add_pickup_days_column'), 999);

        // Populate column
        add_filter('manage_locations_custom_column', array(__CLASS__, 'populate_pickup_days_column'), 999, 3);
        add_filter('manage_location_custom_column', array(__CLASS__, 'populate_pickup_days_column'), 999, 3);

        // Styles
        add_action('admin_head', array(__CLASS__, 'admin_styles'));
    }

    public static function debug_screen_id()
    {
        $screen = get_current_screen();
        if ($screen) {
            echo '<div style="background:#ff0;padding:10px;font-size:14px;font-weight:bold;position:fixed;top:32px;left:160px;z-index:9999;">Screen ID: ' . esc_html($screen->id) . '</div>';
        }
    }

    public static function add_pickup_days_column($columns)
    {
        $columns['pickup_days'] = 'Pickup Days';
        return $columns;
    }

    public static function populate_pickup_days_column($content, $column_name, $term_id)
    {
        if ($column_name !== 'pickup_days') {
            return $content;
        }

        $days = array(
            'sunday' => 'Sun',
            'monday' => 'Mon',
            'tuesday' => 'Tue',
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
        );

        $output = '<div class="rk-pickup-days">';

        foreach ($days as $day_key => $day_label) {
            // Using 'term_' . $term_id as specified in the snippet
            $enabled = function_exists('get_field') ? get_field('region_pickup_' . $day_key . '_enabled', 'term_' . $term_id) : false;

            if ($enabled) {
                $output .= '
					<div class="rk-day-item rk-day-enabled">
						<span class="rk-day-icon">&#10003;</span>
						<span class="rk-day-label">' . esc_html($day_label) . '</span>
					</div>';
            } else {
                $output .= '
					<div class="rk-day-item rk-day-disabled">
						<span class="rk-day-icon">&#10007;</span>
						<span class="rk-day-label">' . esc_html($day_label) . '</span>
					</div>';
            }
        }

        $output .= '</div>';

        return $output;
    }

    public static function admin_styles()
    {
        $screen = get_current_screen();

        if (!$screen || ($screen->id !== 'edit-locations' && $screen->id !== 'edit-location')) {
            return;
        }
        ?>
        <style>
            .column-pickup_days {
                width: 300px;
            }

            .rk-pickup-days {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }

            .rk-day-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                min-width: 34px;
            }

            .rk-day-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                font-size: 13px;
                font-weight: 700;
                line-height: 1;
            }

            .rk-day-enabled .rk-day-icon {
                background-color: #00a32a;
                color: #fff;
            }

            .rk-day-disabled .rk-day-icon {
                background-color: #f0f0f1;
                color: #b0b0b0;
            }

            .rk-day-label {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            .rk-day-enabled .rk-day-label {
                color: #00a32a;
            }

            .rk-day-disabled .rk-day-label {
                color: #b0b0b0;
            }
        </style>
        <?php
    }
}
