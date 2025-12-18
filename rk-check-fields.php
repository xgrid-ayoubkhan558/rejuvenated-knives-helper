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

            // Cities
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

        // Shipping fields (primary placement)
        $fields['shipping']['rk_region'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => __( 'Region', 'rk-check-fields' ),
            'required' => true,
            'priority' => $priority,
        );

        // Visible search input (user types to find city) — will populate rk_region/rk_city and show date picker
        $fields['shipping']['rk_city_search'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => __( 'City (search)', 'rk-check-fields' ),
            'required' => true,
            'placeholder' => __( 'Search your city', 'rk-check-fields' ),
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
            'label'    => __( 'Pickup date', 'rk-check-fields' ),
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
            'label'    => __( 'Region', 'rk-check-fields' ),
            'required' => true,
            'priority' => $billing_priority,
        );

        // Visible search input for billing
        $fields['billing']['rk_city_search'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => __( 'City (search)', 'rk-check-fields' ),
            'required' => true,
            'placeholder' => __( 'Search your city', 'rk-check-fields' ),
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
            'label'    => __( 'Pickup date', 'rk-check-fields' ),
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
