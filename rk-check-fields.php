<?php
/**
 * Plugin Name: RK Checkout Fields
 * Plugin URI:  https://example.com/
 * Description: Adds Region and City fields to the WooCommerce checkout, saves them to order meta, and displays them in admin and order emails.
 * Version:     1.0.0
 * Author:      RK
 * Text Domain: rk-check-fields
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class RK_Checkout_Fields {
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init() {
        // Only run when WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Add fields into checkout shipping fields (placed after shipping phone when possible)
        add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );

        // Validate
        add_action( 'woocommerce_checkout_process', array( $this, 'checkout_validate' ) );

        // Save
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );

        // Display in admin (after shipping address)
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_admin_order_meta' ), 10, 1 );

        // Add to emails
        add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_order_meta_fields' ), 10, 3 );

        // Enqueue frontend assets on checkout
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Print a hidden container with locations JSON so frontend can read it from the DOM
        add_action( 'woocommerce_before_checkout_form', array( $this, 'print_locations_div' ) );

        // AJAX endpoint to provide locations JSON if needed
        add_action( 'wp_ajax_nopriv_rk_get_locations', array( $this, 'ajax_get_locations' ) );
        add_action( 'wp_ajax_rk_get_locations', array( $this, 'ajax_get_locations' ) );

        // Admin settings
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Load translations
        load_plugin_textdomain( 'rk-check-fields', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    }

    /**
     * Echo a hidden div with the locations JSON for the frontend to read
     */
    public function print_locations_div() {
        if ( ! is_checkout() ) {
            return;
        }

        $locations = $this->get_locations_data();
        if ( empty( $locations ) ) {
            return;
        }

        $locations_json = htmlspecialchars( wp_json_encode( $locations ), ENT_QUOTES, 'UTF-8' );

        echo '<div id="rk-check-fields-data" style="display:none" data-regions="' . $locations_json . '"></div>';
    }

    /**
     * AJAX handler to return locations JSON
     */
    public function ajax_get_locations() {
        $locations = $this->get_locations_data();
        wp_send_json_success( $locations );
    }

    /**
     * Enqueue scripts/styles on checkout page
     */
    public function enqueue_assets() {
        if ( ! is_checkout() ) {
            return;
        }

        // flatpickr (CDN)
        wp_enqueue_style( 'rk-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), null );
        wp_enqueue_script( 'rk-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array(), null, true );

        // Our checkout CSS & script
        wp_enqueue_style( 'rk-checkout-css', plugin_dir_url( __FILE__ ) . 'assets/css/rk-checkout.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/rk-checkout.css' ) );
        wp_enqueue_script( 'rk-checkout', plugin_dir_url( __FILE__ ) . 'assets/js/rk-checkout.js', array( 'rk-flatpickr', 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/rk-checkout.js' ), true );

        // Localize data for frontend
        $data = $this->get_locations_data();
        wp_localize_script( 'rk-checkout', 'rk_check_fields_data', $data );
        // Provide AJAX URL for JS fallback
        wp_localize_script( 'rk-checkout', 'rk_check_fields_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

        // Localize plugin options (include all settings for frontend)
        $opts = $this->get_plugin_options();
        wp_localize_script( 'rk-checkout', 'rk_check_fields_options', $opts );
    }

    /**
     * Build regions + cities data from 'locations' taxonomy
     * Returns a structured array suitable for JSON.
     *
     * @return array
     */
    public function get_locations_data() {
        $regions = get_terms( array(
            'taxonomy'   => 'locations',
            'hide_empty' => false,
            'parent'     => 0,
        ) );

        $data = array();

        if ( is_wp_error( $regions ) || empty( $regions ) ) {
            return $data;
        }

        foreach ( $regions as $region ) {
            $regionData = array(
                'region_name' => $region->name,
                'region_id'   => $region->term_id,
                'region_delivery_days' => array(),
                'pickup'      => array(),
                'cities'      => array(),
            );

            // Try to read ACF fields if available, fallback to term meta
            if ( function_exists( 'get_field' ) ) {
                $regionData['region_id'] = get_field( 'region_id', $region ) ?: $region->term_id;
                $regionData['region_delivery_days'] = get_field( 'region_delivery_days', $region ) ?: array();

                $days = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
                foreach ( $days as $day ) {
                    $regionData['pickup'][ $day ] = array(
                        'enabled' => (bool) get_field( "region_pickup_{$day}_enabled", $region ),
                        'start'   => get_field( "region_pickup_{$day}_start_time", $region ),
                        'end'     => get_field( "region_pickup_{$day}_end_time", $region ),
                    );
                }
            } else {
                // fallback: read term meta fields
                $regionData['region_id'] = get_term_meta( $region->term_id, 'region_id', true ) ?: $region->term_id;
                $regionData['region_delivery_days'] = get_term_meta( $region->term_id, 'region_delivery_days', true ) ?: array();

                $days = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
                foreach ( $days as $day ) {
                    $regionData['pickup'][ $day ] = array(
                        'enabled' => (bool) get_term_meta( $region->term_id, "region_pickup_{$day}_enabled", true ),
                        'start'   => get_term_meta( $region->term_id, "region_pickup_{$day}_start_time", true ),
                        'end'     => get_term_meta( $region->term_id, "region_pickup_{$day}_end_time", true ),
                    );
                }
            

            }
            // Cities (children of region)
            $cities = get_terms( array(
                'taxonomy'   => 'locations',
                'hide_empty' => false,
                'parent'     => $region->term_id,
            ) );

            if ( ! is_wp_error( $cities ) && ! empty( $cities ) ) {
                foreach ( $cities as $city ) {
                    $regionData['cities'][] = array(
                        'city_name' => $city->name,
                        'city_id'   => $city->term_id,
                    );
                }
            }

            $data[] = $regionData;
        }

        return $data;
    }

    /**
     * Admin menu & settings
     */
    public function add_admin_menu() {
        add_submenu_page( 'woocommerce', 'RK Checkout Fields', 'RK Checkout Fields', 'manage_woocommerce', 'rk-checkout-fields', array( $this, 'settings_page' ) );
    }

    public function register_settings() {
        register_setting( 'rk_cf_settings', 'rk_cf_options', array( $this, 'sanitize_options' ) );

        // Payment Settings Section
        add_settings_section( 'rk_cf_payment', __( 'Payment Settings', 'rk-check-fields' ), array( $this, 'section_payment_callback' ), 'rk-checkout-fields' );

        add_settings_field( 'enable_auto_payment', __( 'Enable auto payment selection', 'rk-check-fields' ), array( $this, 'field_enable_auto_payment' ), 'rk-checkout-fields', 'rk_cf_payment' );
        add_settings_field( 'payment_found', __( 'Payment method (city found)', 'rk-check-fields' ), array( $this, 'field_payment_found' ), 'rk-checkout-fields', 'rk_cf_payment' );
        add_settings_field( 'payment_not_found', __( 'Payment method (city not found)', 'rk-check-fields' ), array( $this, 'field_payment_not_found' ), 'rk-checkout-fields', 'rk_cf_payment' );

        // Display Settings Section
        add_settings_section( 'rk_cf_display', __( 'Display Settings', 'rk-check-fields' ), array( $this, 'section_display_callback' ), 'rk-checkout-fields' );

        add_settings_field( 'add_body_classes', __( 'Add body classes', 'rk-check-fields' ), array( $this, 'field_add_body_classes' ), 'rk-checkout-fields', 'rk_cf_display' );
        add_settings_field( 'date_format', __( 'Date picker format', 'rk-check-fields' ), array( $this, 'field_date_format' ), 'rk-checkout-fields', 'rk_cf_display' );

        // Messages Section
        add_settings_section( 'rk_cf_messages', __( 'Messages', 'rk-check-fields' ), array( $this, 'section_messages_callback' ), 'rk-checkout-fields' );

        add_settings_field( 'mailin_message', __( 'Mail-in message (no match)', 'rk-check-fields' ), array( $this, 'field_mailin_message' ), 'rk-checkout-fields', 'rk_cf_messages' );
        add_settings_field( 'city_found_message', __( 'City found message', 'rk-check-fields' ), array( $this, 'field_city_found_message' ), 'rk-checkout-fields', 'rk_cf_messages' );
        add_settings_field( 'city_selected_message', __( 'City selected message', 'rk-check-fields' ), array( $this, 'field_city_selected_message' ), 'rk-checkout-fields', 'rk_cf_messages' );

        // Field Labels Section
        add_settings_section( 'rk_cf_labels', __( 'Field Labels', 'rk-check-fields' ), array( $this, 'section_labels_callback' ), 'rk-checkout-fields' );

        add_settings_field( 'region_label', __( 'Region field label', 'rk-check-fields' ), array( $this, 'field_region_label' ), 'rk-checkout-fields', 'rk_cf_labels' );
        add_settings_field( 'city_search_label', __( 'City search field label', 'rk-check-fields' ), array( $this, 'field_city_search_label' ), 'rk-checkout-fields', 'rk_cf_labels' );
        add_settings_field( 'pickup_date_label', __( 'Pickup date field label', 'rk-check-fields' ), array( $this, 'field_pickup_date_label' ), 'rk-checkout-fields', 'rk_cf_labels' );
        add_settings_field( 'city_search_placeholder', __( 'City search placeholder', 'rk-check-fields' ), array( $this, 'field_city_search_placeholder' ), 'rk-checkout-fields', 'rk_cf_labels' );
    }

    public function section_payment_callback() {
        echo '<p>' . esc_html__( 'Configure automatic payment method selection based on city availability.', 'rk-check-fields' ) . '</p>';
    }

    public function section_display_callback() {
        echo '<p>' . esc_html__( 'Configure display options and styling features.', 'rk-check-fields' ) . '</p>';
    }

    public function section_messages_callback() {
        echo '<p>' . esc_html__( 'Customize messages shown to customers during checkout.', 'rk-check-fields' ) . '</p>';
    }

    public function section_labels_callback() {
        echo '<p>' . esc_html__( 'Customize field labels and placeholders shown on the checkout page.', 'rk-check-fields' ) . '</p>';
    }

    public function sanitize_options( $input ) {
        $defaults = $this->get_plugin_options();
        $out = array();
        $out['enable_auto_payment'] = ! empty( $input['enable_auto_payment'] ) ? 1 : 0;
        $out['payment_found'] = sanitize_text_field( $input['payment_found'] ?: $defaults['payment_found'] );
        $out['payment_not_found'] = sanitize_text_field( $input['payment_not_found'] ?: $defaults['payment_not_found'] );
        $out['add_body_classes'] = ! empty( $input['add_body_classes'] ) ? 1 : 0;
        $out['date_format'] = sanitize_text_field( $input['date_format'] ?: $defaults['date_format'] );
        $out['mailin_message'] = sanitize_textarea_field( $input['mailin_message'] ?: $defaults['mailin_message'] );
        $out['city_found_message'] = sanitize_textarea_field( $input['city_found_message'] ?: $defaults['city_found_message'] );
        $out['city_selected_message'] = sanitize_textarea_field( $input['city_selected_message'] ?: $defaults['city_selected_message'] );
        $out['region_label'] = sanitize_text_field( $input['region_label'] ?: $defaults['region_label'] );
        $out['city_search_label'] = sanitize_text_field( $input['city_search_label'] ?: $defaults['city_search_label'] );
        $out['pickup_date_label'] = sanitize_text_field( $input['pickup_date_label'] ?: $defaults['pickup_date_label'] );
        $out['city_search_placeholder'] = sanitize_text_field( $input['city_search_placeholder'] ?: $defaults['city_search_placeholder'] );
        return $out;
    }

    public function get_plugin_options() {
        $defaults = array(
            'enable_auto_payment' => 1,
            'payment_found' => 'cod',
            'payment_not_found' => 'other_payment',
            'add_body_classes' => 1,
            'date_format' => 'd-m-Y',
            'mailin_message' => "Good news!\n\nWhile your location is outside our door-to-door coverage area, you can mail in your knives using our premium mail-in service.\n\nWe'll send you a complete mailing kit and sharpen them to perfection.",
            'city_found_message' => "Hooray! You're within our door-to-door service area.\nSimply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.",
            'city_selected_message' => "Hooray! You're within our door-to-door service area.\nSimply pick a preferred pick-up date, and we'll take care of the rest—collecting your knives, sharpening them to perfection, and delivering them back to you promptly.",
            'region_label' => __( 'Region', 'rk-check-fields' ),
            'city_search_label' => __( 'City (search)', 'rk-check-fields' ),
            'pickup_date_label' => __( 'Pickup date', 'rk-check-fields' ),
            'city_search_placeholder' => __( 'Search your city', 'rk-check-fields' ),
        );
        $opts = get_option( 'rk_cf_options', array() );
        return wp_parse_args( $opts, $defaults );
    }

    /**
     * Get available WooCommerce payment methods
     */
    public function get_payment_methods() {
        $methods = array();
        if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
            $wc = WC();
            if ( $wc && isset( $wc->payment_gateways ) ) {
                $available_gateways = $wc->payment_gateways->get_available_payment_gateways();
                if ( is_array( $available_gateways ) ) {
                    foreach ( $available_gateways as $gateway_id => $gateway ) {
                        if ( is_object( $gateway ) && method_exists( $gateway, 'get_title' ) ) {
                            $methods[ $gateway_id ] = $gateway->get_title();
                        }
                    }
                }
            }
        }
        return $methods;
    }

    public function field_enable_auto_payment() {
        $opts = $this->get_plugin_options();
        echo '<label><input type="checkbox" name="rk_cf_options[enable_auto_payment]" value="1" ' . checked( 1, $opts['enable_auto_payment'], false ) . ' /> ' . esc_html__( 'Automatically select payment method based on city availability', 'rk-check-fields' ) . '</label>';
        echo '<p class="description">' . esc_html__( 'When enabled, the plugin will automatically select the configured payment method when a city is found or not found.', 'rk-check-fields' ) . '</p>';
    }

    public function field_payment_found() {
        $opts = $this->get_plugin_options();
        $methods = $this->get_payment_methods();
        
        if ( ! empty( $methods ) ) {
            echo '<select name="rk_cf_options[payment_found]" class="regular-text">';
            echo '<option value="">' . esc_html__( '-- Select Payment Method --', 'rk-check-fields' ) . '</option>';
            foreach ( $methods as $method_id => $method_title ) {
                echo '<option value="' . esc_attr( $method_id ) . '" ' . selected( $opts['payment_found'], $method_id, false ) . '>' . esc_html( $method_title ) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="rk_cf_options[payment_found]" value="' . esc_attr( $opts['payment_found'] ) . '" class="regular-text" placeholder="e.g., cod" />';
            echo '<p class="description">' . esc_html__( 'Enter payment method ID (e.g., cod, bacs). Available methods will appear as dropdown if WooCommerce is active.', 'rk-check-fields' ) . '</p>';
        }
        echo '<p class="description">' . esc_html__( 'Payment method to automatically select when a city is found in the service area.', 'rk-check-fields' ) . '</p>';
    }

    public function field_payment_not_found() {
        $opts = $this->get_plugin_options();
        $methods = $this->get_payment_methods();
        
        if ( ! empty( $methods ) ) {
            echo '<select name="rk_cf_options[payment_not_found]" class="regular-text">';
            echo '<option value="">' . esc_html__( '-- Select Payment Method --', 'rk-check-fields' ) . '</option>';
            foreach ( $methods as $method_id => $method_title ) {
                echo '<option value="' . esc_attr( $method_id ) . '" ' . selected( $opts['payment_not_found'], $method_id, false ) . '>' . esc_html( $method_title ) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="rk_cf_options[payment_not_found]" value="' . esc_attr( $opts['payment_not_found'] ) . '" class="regular-text" placeholder="e.g., other_payment" />';
            echo '<p class="description">' . esc_html__( 'Enter payment method ID (e.g., cod, bacs). Available methods will appear as dropdown if WooCommerce is active.', 'rk-check-fields' ) . '</p>';
        }
        echo '<p class="description">' . esc_html__( 'Payment method to automatically select when a city is NOT found in the service area.', 'rk-check-fields' ) . '</p>';
    }

    public function field_add_body_classes() {
        $opts = $this->get_plugin_options();
        echo '<label><input type="checkbox" name="rk_cf_options[add_body_classes]" value="1" ' . checked( 1, $opts['add_body_classes'], false ) . ' /> ' . esc_html__( 'Add CSS classes to body element', 'rk-check-fields' ) . '</label>';
        echo '<p class="description">' . esc_html__( 'Adds classes like "rk-city-found", "rk-city-not-found", and "rk-city-selected" to the body element for custom styling.', 'rk-check-fields' ) . '</p>';
    }

    public function field_date_format() {
        $opts = $this->get_plugin_options();
        $formats = array(
            'd-m-Y' => 'DD-MM-YYYY (e.g., 25-12-2024)',
            'm-d-Y' => 'MM-DD-YYYY (e.g., 12-25-2024)',
            'Y-m-d' => 'YYYY-MM-DD (e.g., 2024-12-25)',
            'd/m/Y' => 'DD/MM/YYYY (e.g., 25/12/2024)',
            'm/d/Y' => 'MM/DD/YYYY (e.g., 12/25/2024)',
            'F j, Y' => 'Month Day, Year (e.g., December 25, 2024)',
        );
        
        echo '<select name="rk_cf_options[date_format]" class="regular-text">';
        foreach ( $formats as $format => $label ) {
            echo '<option value="' . esc_attr( $format ) . '" ' . selected( $opts['date_format'], $format, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Date format for the pickup date picker. Uses flatpickr date format syntax.', 'rk-check-fields' ) . '</p>';
    }

    public function field_mailin_message() {
        $opts = $this->get_plugin_options();
        echo '<textarea name="rk_cf_options[mailin_message]" rows="6" cols="60" class="large-text">' . esc_textarea( $opts['mailin_message'] ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Message shown when customer searches for a city that is not in the service area. Supports line breaks.', 'rk-check-fields' ) . '</p>';
    }

    public function field_city_found_message() {
        $opts = $this->get_plugin_options();
        echo '<textarea name="rk_cf_options[city_found_message]" rows="4" cols="60" class="large-text">' . esc_textarea( $opts['city_found_message'] ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Message shown when matching cities are found. Supports line breaks.', 'rk-check-fields' ) . '</p>';
    }

    public function field_city_selected_message() {
        $opts = $this->get_plugin_options();
        echo '<textarea name="rk_cf_options[city_selected_message]" rows="4" cols="60" class="large-text">' . esc_textarea( $opts['city_selected_message'] ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Message shown after a city is selected. Supports line breaks.', 'rk-check-fields' ) . '</p>';
    }

    public function field_region_label() {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[region_label]" value="' . esc_attr( $opts['region_label'] ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Label for the region field on checkout.', 'rk-check-fields' ) . '</p>';
    }

    public function field_city_search_label() {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[city_search_label]" value="' . esc_attr( $opts['city_search_label'] ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Label for the city search field on checkout.', 'rk-check-fields' ) . '</p>';
    }

    public function field_pickup_date_label() {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[pickup_date_label]" value="' . esc_attr( $opts['pickup_date_label'] ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Label for the pickup date field on checkout.', 'rk-check-fields' ) . '</p>';
    }

    public function field_city_search_placeholder() {
        $opts = $this->get_plugin_options();
        echo '<input type="text" name="rk_cf_options[city_search_placeholder]" value="' . esc_attr( $opts['city_search_placeholder'] ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Placeholder text for the city search input field.', 'rk-check-fields' ) . '</p>';
    }

    public function settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Show success message
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error( 'rk_cf_messages', 'rk_cf_message', __( 'Settings saved successfully!', 'rk-check-fields' ), 'success' );
        }

        settings_errors( 'rk_cf_messages' );
        ?>
        <div class="wrap rk-checkout-fields-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p class="description"><?php esc_html_e( 'Configure the RK Checkout Fields plugin settings to customize the checkout experience.', 'rk-check-fields' ); ?></p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'rk_cf_settings' );
                do_settings_sections( 'rk-checkout-fields' );
                submit_button( __( 'Save Settings', 'rk-check-fields' ) );
                ?>
            </form>
        </div>
        <style>
            .rk-checkout-fields-settings .form-table th {
                width: 250px;
                padding: 20px 10px 20px 0;
            }
            .rk-checkout-fields-settings .form-table td {
                padding: 15px 10px;
            }
            .rk-checkout-fields-settings .description {
                color: #646970;
                font-style: italic;
                margin-top: 5px;
            }
            .rk-checkout-fields-settings h2 {
                margin-top: 30px;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }
            .rk-checkout-fields-settings h2:first-of-type {
                margin-top: 20px;
            }
        </style>
        <?php
    }

    /**
     * Default regions (filterable via 'rk_regions_list')
     *
     * @return array
     */
    public function get_regions() {
        $regions = array(
            'north' => __( 'North', 'rk-check-fields' ),
            'south' => __( 'South', 'rk-check-fields' ),
            'east'  => __( 'East', 'rk-check-fields' ),
            'west'  => __( 'West', 'rk-check-fields' ),
        );

        return apply_filters( 'rk_regions_list', $regions );
    }

    /**
     * Add fields to shipping section on checkout (and billing as fallback)
     */
    public function checkout_fields( $fields ) {
        // Determine a priority so we can place the fields after phone or city when available
        $priority = 100;
        if ( isset( $fields['shipping']['shipping_phone']['priority'] ) ) {
            $priority = $fields['shipping']['shipping_phone']['priority'] + 1;
        } elseif ( isset( $fields['shipping']['shipping_city']['priority'] ) ) {
            $priority = $fields['shipping']['shipping_city']['priority'] + 1;
        } elseif ( isset( $fields['shipping']['city']['priority'] ) ) {
            $priority = $fields['shipping']['city']['priority'] + 1;
        }

        // Prepare locations JSON for the frontend and attach as a data attribute on the search field
        $locations = $this->get_locations_data();
        $locations_json = htmlspecialchars( wp_json_encode( $locations ), ENT_QUOTES, 'UTF-8' );

        // Get dynamic labels from settings
        $opts = $this->get_plugin_options();

        // Shipping fields (primary placement)
        $fields['shipping']['rk_region'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => $opts['region_label'],
            'required' => false,
            'priority' => $priority,
        );

        // Visible search input (user types to find city) — will populate rk_region/rk_city and show date picker
        $fields['shipping']['rk_city_search'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => $opts['city_search_label'],
            'required' => true,
            'placeholder' => $opts['city_search_placeholder'],
            'custom_attributes' => array( 'data-regions' => $locations_json ),
            'priority' => $priority + 1,
        );

        // Hidden to store selected city name (used by JS) — only one visible search input is shown
        $fields['shipping']['rk_city'] = array(
            'type'     => 'hidden',
            'class'    => array( 'form-row-wide' ),
            'required' => false,
            'priority' => $priority + 2,
        );

        // Pickup date (populated by flatpickr when a city is selected)
        $fields['shipping']['rk_pickup_date'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => $opts['pickup_date_label'],
            'required' => false,
            'priority' => $priority + 3,
        );

        // Billing fallback (so fields are visible if shipping is not used/displayed)
        $billing_priority = 100;
        if ( isset( $fields['billing']['billing_phone']['priority'] ) ) {
            $billing_priority = $fields['billing']['billing_phone']['priority'] + 1;
        } elseif ( isset( $fields['billing']['billing_city']['priority'] ) ) {
            $billing_priority = $fields['billing']['billing_city']['priority'] + 1;
        } elseif ( isset( $fields['billing']['city']['priority'] ) ) {
            $billing_priority = $fields['billing']['city']['priority'] + 1;
        }

        $fields['billing']['rk_region'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => $opts['region_label'],
            'required' => true,
            'priority' => $billing_priority,
        );

        // Visible search input for billing
        $fields['billing']['rk_city_search'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => $opts['city_search_label'],
            'required' => true,
            'placeholder' => $opts['city_search_placeholder'],
            'custom_attributes' => array( 'data-regions' => $locations_json ),
            'priority' => $billing_priority + 1,
        );

        // Hidden billing city to store selected city name (used by JS)
        $fields['billing']['rk_city'] = array(
            'type'     => 'hidden',
            'class'    => array( 'form-row-wide' ),
            'required' => false,
            'priority' => $billing_priority + 2,
        );

        $fields['billing']['rk_pickup_date'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => $opts['pickup_date_label'],
            'required' => false,
            'priority' => $billing_priority + 3,
        );

        return $fields;
    }

    /**
     * Validate the fields on checkout (accepts shipping or billing inputs)
     */
    public function checkout_validate() {
        $region = '';
        $city   = '';

        if ( ! empty( $_POST['shipping_rk_region'] ) ) {
            $region = wp_unslash( $_POST['shipping_rk_region'] );
        } elseif ( ! empty( $_POST['billing_rk_region'] ) ) {
            $region = wp_unslash( $_POST['billing_rk_region'] );
        }

        if ( ! empty( $_POST['shipping_rk_city'] ) ) {
            $city = wp_unslash( $_POST['shipping_rk_city'] );
        } elseif ( ! empty( $_POST['billing_rk_city'] ) ) {
            $city = wp_unslash( $_POST['billing_rk_city'] );
        }

        if ( empty( $region ) ) {
            wc_add_notice( __( 'Please enter a region.', 'rk-check-fields' ), 'error' );
        }

        if ( empty( $city ) ) {
            wc_add_notice( __( 'Please enter a city.', 'rk-check-fields' ), 'error' );
        }

        // Require pickup date only when a selected city exists (shipping preferred, billing fallback)
        if ( ! empty( $_POST['shipping_rk_city'] ) ) {
            if ( empty( $_POST['shipping_rk_pickup_date'] ) ) {
                wc_add_notice( __( 'Please select a pickup date for your city.', 'rk-check-fields' ), 'error' );
            }
        } elseif ( ! empty( $_POST['billing_rk_city'] ) ) {
            if ( empty( $_POST['billing_rk_pickup_date'] ) ) {
                wc_add_notice( __( 'Please select a pickup date for your city.', 'rk-check-fields' ), 'error' );
            }
        }
    }

    /**
     * Save the fields to order meta (shipping preferred, billing fallback)
     */
    public function save_order_meta( $order_id ) {
        $region = '';
        $city   = '';

        if ( isset( $_POST['shipping_rk_region'] ) && '' !== trim( wp_unslash( $_POST['shipping_rk_region'] ) ) ) {
            $region = sanitize_text_field( wp_unslash( $_POST['shipping_rk_region'] ) );
        } elseif ( isset( $_POST['billing_rk_region'] ) ) {
            $region = sanitize_text_field( wp_unslash( $_POST['billing_rk_region'] ) );
        }

        if ( isset( $_POST['shipping_rk_city'] ) && '' !== trim( wp_unslash( $_POST['shipping_rk_city'] ) ) ) {
            $city = sanitize_text_field( wp_unslash( $_POST['shipping_rk_city'] ) );
        } elseif ( isset( $_POST['billing_rk_city'] ) ) {
            $city = sanitize_text_field( wp_unslash( $_POST['billing_rk_city'] ) );
        }

        if ( $region !== '' ) {
            update_post_meta( $order_id, 'rk_region', $region );
        }

        if ( $city !== '' ) {
            update_post_meta( $order_id, 'rk_city', $city );
        }

        // Pickup date (shipping preferred, billing fallback)
        $pickup = '';
        if ( isset( $_POST['shipping_rk_pickup_date'] ) && '' !== trim( wp_unslash( $_POST['shipping_rk_pickup_date'] ) ) ) {
            $pickup = sanitize_text_field( wp_unslash( $_POST['shipping_rk_pickup_date'] ) );
        } elseif ( isset( $_POST['billing_rk_pickup_date'] ) ) {
            $pickup = sanitize_text_field( wp_unslash( $_POST['billing_rk_pickup_date'] ) );
        }

        if ( $pickup !== '' ) {
            update_post_meta( $order_id, 'rk_pickup_date', $pickup );
        }
    }

    /**
     * Display in admin order details
     */
    public function display_admin_order_meta( $order ) {
        $region = get_post_meta( $order->get_id(), 'rk_region', true );
        $city   = get_post_meta( $order->get_id(), 'rk_city', true );
        $pickup = get_post_meta( $order->get_id(), 'rk_pickup_date', true );

        if ( $region || $city || $pickup ) {
            if ( $region ) {
                echo '<p><strong>' . esc_html__( 'Region', 'rk-check-fields' ) . ':</strong> ' . esc_html( $region ) . '</p>';
            }

            if ( $city ) {
                echo '<p><strong>' . esc_html__( 'City', 'rk-check-fields' ) . ':</strong> ' . esc_html( $city ) . '</p>';
            }

            if ( $pickup ) {
                echo '<p><strong>' . esc_html__( 'Pickup date', 'rk-check-fields' ) . ':</strong> ' . esc_html( $pickup ) . '</p>';
            }
        }
    }

    /**
     * Add to order emails
     */
    public function email_order_meta_fields( $fields, $sent_to_admin, $order ) {
        $region = get_post_meta( $order->get_id(), 'rk_region', true );
        $city   = get_post_meta( $order->get_id(), 'rk_city', true );
        $pickup = get_post_meta( $order->get_id(), 'rk_pickup_date', true );

        if ( $region ) {
            $fields['rk_region'] = array(
                'label' => __( 'Region', 'rk-check-fields' ),
                'value' => $region,
            );
        }

        if ( $city ) {
            $fields['rk_city'] = array(
                'label' => __( 'City', 'rk-check-fields' ),
                'value' => $city,
            );
        }

        if ( $pickup ) {
            $fields['rk_pickup_date'] = array(
                'label' => __( 'Pickup date', 'rk-check-fields' ),
                'value' => $pickup,
            );
        }

        return $fields;
    }
}

new RK_Checkout_Fields();
