<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RK_Woo_Ajax_Cart_Count_Admin {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function register_settings() {
		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_woo_ajax_cart_count_min_order_value',
			array(
				'type' => 'number',
				'sanitize_callback' => function ( $value ) {
					$value = is_numeric( $value ) ? (float) $value : 0;
					return max( 0, $value );
				},
				'default' => 0,
			)
		);

		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_woo_ajax_cart_count_attributes_only',
			array(
				'type' => 'boolean',
				'sanitize_callback' => function ( $value ) {
					return $value ? 1 : 0;
				},
				'default' => 0,
			)
		);

		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_woo_ajax_cart_count_minimum_formula',
			array(
				'type' => 'string',
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 'subtotal', 'total' ) ) ? $value : 'subtotal';
				},
				'default' => 'subtotal',
			)
		);
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			'RK Cart Count',
			'RK Cart Count',
			'manage_woocommerce',
			'rk-woo-ajax-cart-count',
			array( __CLASS__, 'page' )
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$min_order_value = get_option( 'rk_woo_ajax_cart_count_min_order_value', 0 );
		$attributes_only = (int) get_option( 'rk_woo_ajax_cart_count_attributes_only', 0 );
		$minimum_formula = get_option( 'rk_woo_ajax_cart_count_minimum_formula', 'subtotal' );

		echo '<div class="wrap">';
		echo '<h1>RK Cart Count</h1>';
		echo '<p>Use shortcode: <code>[WooAjaxCartCount]</code></p>';

		echo '<form method="post" action="options.php">';
		settings_fields( 'rk_woo_ajax_cart_count_settings' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr>'; 
		echo '<th scope="row"><label for="rk_woo_ajax_cart_count_min_order_value">Minimum order value</label></th>';
		echo '<td><input name="rk_woo_ajax_cart_count_min_order_value" id="rk_woo_ajax_cart_count_min_order_value" type="number" min="0" step="0.01" value="' . esc_attr( $min_order_value ) . '" class="regular-text" /></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="rk_woo_ajax_cart_count_minimum_formula">Minimum value formula</label></th>';
		echo '<td><select name="rk_woo_ajax_cart_count_minimum_formula" id="rk_woo_ajax_cart_count_minimum_formula">';
		echo '<option value="subtotal" ' . selected( 'subtotal', $minimum_formula, false ) . '>Use cart subtotal</option>';
		echo '<option value="total" ' . selected( 'total', $minimum_formula, false ) . '>Use cart total</option>';
		echo '</select></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">Fragments mode</th>';
		echo '<td><label><input name="rk_woo_ajax_cart_count_attributes_only" type="checkbox" value="1" ' . checked( 1, $attributes_only, false ) . ' /> Attributes only (do not update visible cart HTML on AJAX)</label></td>';
		echo '</tr>';
		echo '</table>';
		submit_button();
		echo '</form>';

		echo '<p>When the shortcode is present, the plugin also sets:</p>';
		echo '<pre>body[data-woo-cart-total][data-woo-cart-subtotal][data-woo-cart-currency][data-woo-min-order-value][data-woo-meets-min-order][data-woo-minimum-value-reached]</pre>';
		echo '</div>';
	}
}
