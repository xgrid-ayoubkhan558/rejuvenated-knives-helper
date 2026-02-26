<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RK_Woo_Ajax_Cart_Count_Logic
{
    public static function init()
    {
        add_shortcode('WooAjaxCartCount', array(__CLASS__, 'shortcode'));
        add_filter('woocommerce_add_to_cart_fragments', array(__CLASS__, 'fragments'));
        add_filter('woocommerce_add_cart_item_data', array(__CLASS__, 'overwrite_cart_quantity'), 10, 3);
    }

    public static function shortcode($atts = array())
    {
        if (!function_exists('WC') || !WC()->cart) {
            return '';
        }

        wp_enqueue_script(
            'rk-woo-ajax-cart-count',
            plugins_url('assets/js/rk-woo-ajax-cart-count.js', RK_HELPER_FILE),
            array(),
            '1.0.0',
            true
        );

        $html = self::render();

        return $html;
    }

    public static function fragments($fragments)
    {
        if (!function_exists('WC') || !WC()->cart) {
            return $fragments;
        }

        $attributes_only = (int) get_option('rk_woo_ajax_cart_count_attributes_only', 0);
        if ($attributes_only) {
            $fragments['span.rk-woo-ajax-cart-count__cart-total-data'] = self::render_data_span();
            return $fragments;
        }

        $fragments['a.cart-custom-location'] = self::render();
        return $fragments;
    }

    private static function render()
    {
        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '#';

        $cart_total_raw = method_exists(WC()->cart, 'get_total') ? WC()->cart->get_total('edit') : WC()->cart->total;
        $cart_subtotal_raw = method_exists(WC()->cart, 'get_subtotal') ? WC()->cart->get_subtotal() : '';
        $cart_currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
        $cart_count = method_exists(WC()->cart, 'get_cart_contents_count') ? WC()->cart->get_cart_contents_count() : WC()->cart->cart_contents_count;
        $min_order_value = (float) get_option('rk_woo_ajax_cart_count_min_order_value', 0);
        $minimum_formula = get_option('rk_woo_ajax_cart_count_minimum_formula', 'subtotal');
        $meets_min_order = ((float) $cart_total_raw >= $min_order_value) ? '1' : '0';
        $minimum_value_reached = (('subtotal' === $minimum_formula) ? (float) $cart_subtotal_raw : (float) $cart_total_raw) >= $min_order_value ? 'true' : 'false';

        ob_start();
        ?>
        <a class="cart-custom-location rk-ajax-cart-count" href="<?php echo esc_url($cart_url); ?>"
            title="<?php echo esc_attr__('View Shopping Cart', 'rk-helper'); ?>">
            <span style="padding: 10px" class="" aria-hidden="true"></span>
            <?php echo esc_html(sprintf(_n('%d item ', '%d items ', $cart_count, 'rk-helper'), $cart_count)); ?>&nbsp;
            <?php echo wp_kses_post(WC()->cart->get_cart_total()); ?>
            <?php echo self::render_data_span($cart_total_raw, $cart_subtotal_raw, $cart_currency, $min_order_value, $meets_min_order, $minimum_value_reached); ?>
        </a>
        <?php
        return ob_get_clean();
    }

    private static function render_data_span($cart_total_raw = null, $cart_subtotal_raw = null, $cart_currency = null, $min_order_value = null, $meets_min_order = null, $minimum_value_reached = null)
    {
        if ($cart_total_raw === null) {
            $cart_total_raw = method_exists(WC()->cart, 'get_total') ? WC()->cart->get_total('edit') : WC()->cart->total;
        }
        if ($cart_subtotal_raw === null) {
            $cart_subtotal_raw = method_exists(WC()->cart, 'get_subtotal') ? WC()->cart->get_subtotal() : '';
        }
        if ($cart_currency === null) {
            $cart_currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
        }
        if ($min_order_value === null) {
            $min_order_value = (float) get_option('rk_woo_ajax_cart_count_min_order_value', 0);
        }
        if ($meets_min_order === null) {
            $meets_min_order = ((float) $cart_total_raw >= (float) $min_order_value) ? '1' : '0';
        }
        if ($minimum_value_reached === null) {
            $minimum_formula = get_option('rk_woo_ajax_cart_count_minimum_formula', 'subtotal');
            $minimum_value_reached = (('subtotal' === $minimum_formula) ? (float) $cart_subtotal_raw : (float) $cart_total_raw) >= (float) $min_order_value ? 'true' : 'false';
        }

        return '<span class="rk-woo-ajax-cart-count__cart-total-data" data-woo-cart-total="' . esc_attr($cart_total_raw) . '" data-woo-cart-subtotal="' . esc_attr($cart_subtotal_raw) . '" data-woo-cart-currency="' . esc_attr($cart_currency) . '" data-woo-min-order-value="' . esc_attr($min_order_value) . '" data-woo-meets-min-order="' . esc_attr($meets_min_order) . '" data-woo-minimum-value-reached="' . esc_attr($minimum_value_reached) . '" style="display:none"></span>';
    }

    /**
     * Overwrite cart quantity with selected quantity (instead of adding)
     */
    public static function overwrite_cart_quantity($cart_item_data, $product_id, $variation_id)
    {
        $overwrite_quantity = (int) get_option('rk_overwrite_cart_quantity', 0);

        if (!$overwrite_quantity) {
            return $cart_item_data;
        }

        if (!function_exists('WC') || !WC()->cart) {
            return $cart_item_data;
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $product_id) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }

        return $cart_item_data;
    }
}
