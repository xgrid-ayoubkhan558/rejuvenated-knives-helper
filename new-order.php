<?php
/**
 * Admin new order email
 *
 * @package WooCommerce\Templates\Emails\HTML
 * @version 10.4.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined('ABSPATH') || exit;

$email_improvements_enabled = FeaturesUtil::feature_is_enabled('email_improvements');

// Safety check: Ensure $order is a valid WC_Order object
if (!isset($order) || !is_a($order, 'WC_Order')) {
    return;
}

/*
 * Email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

echo $email_improvements_enabled ? '<div class="email-introduction">' : '';

$text = __('You’ve received the following order from %s:', 'woocommerce');
if ($email_improvements_enabled) {
    $text = __('You’ve received a new order from %s:', 'woocommerce');
}
?>
<p><?php printf(esc_html($text), esc_html($order->get_formatted_billing_full_name())); ?></p>

<?php
/**
 * ===============================
 * SERVICE TYPE (FIXED)
 * ===============================
 */
$service_type_raw = $order->get_meta('rk_service_type');

// Normalize value (IMPORTANT) - now handles both spaces and hyphens
$service_type = strtolower(str_replace(array(' ', '-'), '_', trim($service_type_raw)));

if ($service_type): ?>
    <p>
        <strong><?php esc_html_e('Service Type:', 'rk-helper'); ?></strong><br>
        <?php
        if ($service_type === 'mail_in' || $service_type === 'mail') {
            esc_html_e('Mail-in service — send mailing kit to customer.', 'rk-helper');
        } elseif ($service_type === 'door_to_door') {
            esc_html_e('Door-to-door service — schedule courier pickup.', 'rk-helper');
        } else {
            echo esc_html(ucwords(str_replace(array('_', '-'), ' ', $service_type_raw)));
        }
        ?>
    </p>
<?php endif; ?>

<?php
echo $email_improvements_enabled ? '</div>' : '';

/*
 * Order details table
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * Order meta
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * Customer details
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

/**
 * Additional content
 */
if ($additional_content) {
    echo $email_improvements_enabled
        ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">'
        : '';

    echo wp_kses_post(wpautop(wptexturize($additional_content)));

    echo $email_improvements_enabled
        ? '</td></tr></table>'
        : '';
}

/*
 * Email footer
 */
do_action('woocommerce_email_footer', $email);