<?php

if (!defined('ABSPATH')) {
	exit;
}

final class RK_Woo_Ajax_Cart_Count_Admin
{
	public static function init()
	{
		add_action('admin_init', array(__CLASS__, 'register_settings'));
		add_action('admin_menu', array(__CLASS__, 'menu'));
	}

	public static function register_settings()
	{
		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_woo_ajax_cart_count_min_order_value',
			array(
				'type' => 'number',
				'sanitize_callback' => function ($value) {
					$value = is_numeric($value) ? (float) $value : 0;
					return max(0, $value);
				},
				'default' => 0,
			)
		);

		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_woo_ajax_cart_count_attributes_only',
			array(
				'type' => 'boolean',
				'sanitize_callback' => function ($value) {
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
				'sanitize_callback' => function ($value) {
					return in_array($value, array('subtotal', 'total')) ? $value : 'subtotal';
				},
				'default' => 'subtotal',
			)
		);

		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_overwrite_cart_quantity',
			array(
				'type' => 'boolean',
				'sanitize_callback' => function ($value) {
					return $value ? 1 : 0;
				},
				'default' => 0,
			)
		);

		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_enable_advanced_pickup_columns',
			array(
				'type' => 'boolean',
				'sanitize_callback' => function ($value) {
					return $value ? 1 : 0;
				},
				'default' => 1,
			)
		);

		register_setting(
			'rk_woo_ajax_cart_count_settings',
			'rk_enable_admin_debug',
			array(
				'type' => 'boolean',
				'sanitize_callback' => function ($value) {
					return $value ? 1 : 0;
				},
				'default' => 0,
			)
		);
	}

	public static function menu()
	{
		add_submenu_page(
			'woocommerce',
			'RK Helper Settings',
			'RK Helper',
			'manage_options',
			'rk-woo-ajax-cart-count',
			array(__CLASS__, 'page')
		);
	}

	public static function page()
	{
		if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
			return;
		}

		$min_order_value = get_option('rk_woo_ajax_cart_count_min_order_value', 0);
		$attributes_only = (int) get_option('rk_woo_ajax_cart_count_attributes_only', 0);
		$minimum_formula = get_option('rk_woo_ajax_cart_count_minimum_formula', 'subtotal');
		$overwrite_quantity = (int) get_option('rk_overwrite_cart_quantity', 0);
		$enable_pickup_columns = (int) get_option('rk_enable_advanced_pickup_columns', 1);
		$enable_debug = (int) get_option('rk_enable_admin_debug', 0);

		echo '<div class="wrap">';
		echo '<h1>Rejuvenated Knives Helper Settings</h1>';

		echo '<form method="post" action="options.php">';
		settings_fields('rk_woo_ajax_cart_count_settings');

		echo '<h2>Cart Count & Quantity Settings</h2>';
		echo '<p>Use shortcode: <code>[WooAjaxCartCount]</code></p>';
		echo '<table class="form-table" role="presentation">';
		echo '<tr>';
		echo '<th scope="row"><label for="rk_woo_ajax_cart_count_min_order_value">Minimum order value</label></th>';
		echo '<td><input name="rk_woo_ajax_cart_count_min_order_value" id="rk_woo_ajax_cart_count_min_order_value" type="number" min="0" step="0.01" value="' . esc_attr($min_order_value) . '" class="regular-text" /></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="rk_woo_ajax_cart_count_minimum_formula">Minimum value formula</label></th>';
		echo '<td><select name="rk_woo_ajax_cart_count_minimum_formula" id="rk_woo_ajax_cart_count_minimum_formula">';
		echo '<option value="subtotal" ' . selected('subtotal', $minimum_formula, false) . '>Use cart subtotal</option>';
		echo '<option value="total" ' . selected('total', $minimum_formula, false) . '>Use cart total</option>';
		echo '</select></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">Fragments mode</th>';
		echo '<td><label><input name="rk_woo_ajax_cart_count_attributes_only" type="checkbox" value="1" ' . checked(1, $attributes_only, false) . ' /> Attributes only (do not update visible cart HTML on AJAX)</label></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">Overwrite Cart Quantity</th>';
		echo '<td><label><input name="rk_overwrite_cart_quantity" type="checkbox" value="1" ' . checked(1, $overwrite_quantity, false) . ' /> Overwrite cart quantity with selected quantity (instead of adding)</label></td>';
		echo '</tr>';
		echo '</table>';

		echo '<h2>Pickup Days Column Settings</h2>';
		echo '<table class="form-table" role="presentation">';
		echo '<tr>';
		echo '<th scope="row">Enable Advanced Column</th>';
		echo '<td><label><input name="rk_enable_advanced_pickup_columns" type="checkbox" value="1" ' . checked(1, $enable_pickup_columns, false) . ' /> Enable styled Pickup Days column in Locations admin</label></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row">Admin Debug Mode</th>';
		echo '<td><label><input name="rk_enable_admin_debug" type="checkbox" value="1" ' . checked(1, $enable_debug, false) . ' /> Show Screen ID at the top of admin pages (helpful for debugging hooks)</label></td>';
		echo '</tr>';
		echo '</table>';

		submit_button();
		echo '</form>';

		echo '<p>Development info:</p>';
		echo '<pre>body[data-woo-cart-total][data-woo-cart-subtotal][data-woo-cart-currency][data-woo-min-order-value][data-woo-meets-min-order][data-woo-minimum-value-reached]</pre>';
		echo '</div>';
	}
}
