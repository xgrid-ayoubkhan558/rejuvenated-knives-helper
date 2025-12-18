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

        // Load translations
        load_plugin_textdomain( 'rk-check-fields', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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

        // Shipping fields (primary placement)
        $fields['shipping']['rk_region'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => __( 'Region', 'rk-check-fields' ),
            'required' => true,
            'priority' => $priority,
        );

        $fields['shipping']['rk_city'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => __( 'City', 'rk-check-fields' ),
            'required' => true,
            'priority' => $priority + 1,
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

        $fields['billing']['rk_city'] = array(
            'type'     => 'text',
            'class'    => array( 'form-row-wide' ),
            'label'    => __( 'City', 'rk-check-fields' ),
            'required' => true,
            'priority' => $billing_priority + 1,
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
    }

    /**
     * Display in admin order details
     */
    public function display_admin_order_meta( $order ) {
        $region = get_post_meta( $order->get_id(), 'rk_region', true );
        $city   = get_post_meta( $order->get_id(), 'rk_city', true );

        if ( $region || $city ) {
            echo '<p><strong>' . esc_html__( 'Region', 'rk-check-fields' ) . ':</strong> ' . esc_html( $region ) . '</p>';
            echo '<p><strong>' . esc_html__( 'City', 'rk-check-fields' ) . ':</strong> ' . esc_html( $city ) . '</p>';
        }
    }

    /**
     * Add to order emails
     */
    public function email_order_meta_fields( $fields, $sent_to_admin, $order ) {
        $region = get_post_meta( $order->get_id(), 'rk_region', true );
        $city   = get_post_meta( $order->get_id(), 'rk_city', true );

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

        return $fields;
    }
}

new RK_Checkout_Fields();
