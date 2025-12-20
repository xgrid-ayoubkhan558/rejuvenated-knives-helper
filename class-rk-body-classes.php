<?php
/**
 * RK_Body_Classes – Final Version (Safe & Optimized)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RK_Body_Classes {

    private $cart_total = 0.0;

    public function __construct() {
        add_action( 'wp', [ $this, 'init_cart_total' ] );
        add_filter( 'body_class', [ $this, 'add_cart_body_class' ] );

        // Output buffer only for body attribute
        add_action( 'wp_loaded', [ $this, 'start_buffer' ] );
        add_action( 'shutdown', [ $this, 'end_buffer' ], 0 );

        // AJAX fragments
        add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'add_fragments' ] );
        add_action( 'wp_footer', [ $this, 'ajax_update_script' ], 20 );
    }

    /**
     * Initialize cart total safely
     */
    public function init_cart_total() {
        if ( function_exists( 'WC' ) && WC()->cart ) {
            $this->cart_total = (float) WC()->cart->get_cart_contents_total();
        }
    }

    /**
     * Add body class based on cart total
     */
    public function add_cart_body_class( $classes ) {
        if ( $this->cart_total > 0 ) {
            $formatted = str_replace( '.', '', number_format( $this->cart_total, 2 ) );
            $classes[] = 'cart-total-' . $formatted;
        } else {
            $classes[] = 'cart-is-empty';
        }
        return $classes;
    }

    /**
     * Start output buffering (frontend only)
     */
    public function start_buffer() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }
        ob_start( [ $this, 'modify_buffer' ] );
    }

    /**
     * Inject data-cart-total attribute into <body>
     */
    public function modify_buffer( $buffer ) {
        $total_attr = number_format( $this->cart_total, 2, '.', '' );
        return preg_replace(
            '/<body([^>]*)>/i',
            '<body$1 data-cart-total="' . esc_attr( $total_attr ) . '">',
            $buffer,
            1
        );
    }

    /**
     * End buffer
     */
    public function end_buffer() {
        if ( ob_get_length() ) {
            ob_end_flush();
        }
    }

    /**
     * Add cart info fragments
     */
    public function add_fragments( $fragments ) {
        $total = 0.0;

        if ( function_exists( 'WC' ) && WC()->cart ) {
            $total = (float) WC()->cart->get_cart_contents_total();
        }

        $fragments['rk_cart_info'] = [
            'total'       => number_format( $total, 2, '.', '' ),
            'total_class' => 'cart-total-' . str_replace( '.', '', number_format( $total, 2 ) ),
            'empty'       => $total <= 0,
        ];

        return $fragments;
    }

    /**
     * Vanilla JS cart updater
     */
    public function ajax_update_script() {
        ?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    function getFragments() {
        try {
            const stored = sessionStorage.getItem('wc_fragments');
            return stored ? JSON.parse(stored) : {};
        } catch (e) {
            return {};
        }
    }

    function updateBodyCartData(fragments) {
        if (!fragments || !fragments.rk_cart_info) return;

        const info = fragments.rk_cart_info;
        const body = document.body;

        // Update attribute
        body.setAttribute('data-cart-total', info.total);

        // Remove old cart classes safely
        [...body.classList].forEach(cls => {
            if (cls.startsWith('cart-total-') || cls === 'cart-is-empty') {
                body.classList.remove(cls);
            }
        });

        // Add new class
        if (info.empty) {
            body.classList.add('cart-is-empty');
        } else {
            body.classList.add(info.total_class);
        }

        console.log('Cart total changed:', info.total);
    }

    function refreshFromStorage() {
        updateBodyCartData(getFragments());
    }

    // Initial load (cached pages safe)
    refreshFromStorage();

    // WooCommerce AJAX lifecycle
    document.body.addEventListener('wc_fragments_refreshed', refreshFromStorage);
    document.body.addEventListener('added_to_cart', refreshFromStorage);
    document.body.addEventListener('removed_from_cart', refreshFromStorage);
});
</script>
        <?php
    }
}

// new RK_Body_Classes();