<?php
/**
 * Plugin Name: Rejuvenated Knives Helper
 * Plugin URI:  https://www.xgrid.co/
 * Description: Custom site logic: Checkout field modifications, dynamic body classes, and conditional checkout rules.
 * Version:     1.0.1
 * Author:      Ayoub Khan 
 * Text Domain: rk-helper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function rk_helper_init() {
    // Your code here
    
    $includes = array(
        'class-rk-checkout-fields.php',
        'class-rk-body-classes.php',
    );

    foreach ( $includes as $file ) {
        $path = plugin_dir_path( __FILE__ ) . $file; // Simplified path
        
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    if ( class_exists( 'RK_Checkout_Fields' ) ) {
        new RK_Checkout_Fields();
    }

    if ( class_exists( 'RK_Body_Classes' ) ) {
        new RK_Body_Classes();
    }
}

add_action( 'plugins_loaded', 'rk_helper_init' );