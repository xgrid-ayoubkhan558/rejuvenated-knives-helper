<?php
/**
 * RK_Body_Classes Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RK_Body_Classes {

    public function __construct() {
        add_filter( 'body_class', array( $this, 'add_cart_total_body_class' ) );
    }

    /**
     * Adds the current cart total as a body class (e.g., cart-total-15000)
     */
    public function add_cart_total_body_class( $classes ) {
        // Ensure WooCommerce cart is available
        if ( function_exists( 'WC' ) && null !== WC()->cart ) {
            
            $cart_total = WC()->cart->get_total( 'edit' ); // Get raw total
            
            // Format to a clean string for CSS (e.g., 120.50 becomes 12050)
            $formatted_total = number_format( $cart_total, 2, '', '' );
            
            $classes[] = 'cart-total-' . $formatted_total;
            
            // Optional: Add a class if cart is over a specific value
            if ( $cart_total > 100 ) {
                $classes[] = 'rk-high-value-cart';
            }
        }
        
        return $classes;
    }
}