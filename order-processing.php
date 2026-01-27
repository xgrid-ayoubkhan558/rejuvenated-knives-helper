<?php
/**
 * Customer processing order email
 *
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (!defined('ABSPATH')) {
    exit;
}

$email_improvements_enabled = FeaturesUtil::feature_is_enabled('email_improvements');

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

// Safety check: Ensure $order is a valid WC_Order object
if (!isset($order) || !is_a($order, 'WC_Order')) {
    return;
}
?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<p>
    <?php
    if (!empty($order->get_billing_first_name())) {
        /* translators: %s: Customer first name */
        printf(esc_html__('Hi %s,', 'woocommerce'), esc_html($order->get_billing_first_name()));
    } else {
        printf(esc_html__('Hi,', 'woocommerce'));
    }
    ?>
</p>
<?php if ($email_improvements_enabled): ?>
    <p><?php esc_html_e('Just to let you know &mdash; we’ve received your order, and it is now being processed.', 'woocommerce'); ?>
    </p>
    <p><?php esc_html_e('Here’s a reminder of what you’ve ordered:', 'woocommerce'); ?></p>
<?php else: ?>
    <?php /* translators: %s: Order number */ ?>
    <p><?php printf(esc_html__('Just to let you know &mdash; we\'ve received your order #%s, and it is now being processed:', 'woocommerce'), esc_html($order->get_order_number())); ?>
    </p>
<?php endif; ?>

<?php
/**
 * ===============================
 * SERVICE TYPE (CUSTOMER FACING)
 * ===============================
 */
$service_type_raw = $order->get_meta('rk_service_type');
$service_type = strtolower(str_replace(array(' ', '-'), '_', trim($service_type_raw)));

if ($service_type): ?>
    <div class="service-type-details">
        <?php
        if ($service_type === 'door_to_door') {
            ?>
            <p><?php esc_html_e('Thank you for your mail-in sharpening order with Rejuvenated Knives. We’re excited to help restore your knives to peak performance.', 'rk-helper'); ?>
            </p>
            <p><strong><?php esc_html_e('What happens next', 'rk-helper'); ?></strong></p>
            <p><?php esc_html_e('You’ll receive a text message with your prepaid shipping label and mail-in instructions.', 'rk-helper'); ?>
            </p>
            <p><i><?php esc_html_e('Please note: We communicate primarily by text to ensure faster updates.', 'rk-helper'); ?></i>
            </p>
            <ul style="margin-bottom: 20px;">
                <li><?php esc_html_e('A prepaid USPS shipping label is provided', 'rk-helper'); ?></li>
                <li><?php esc_html_e('Do not drop off at UPS or FedEx — USPS only', 'rk-helper'); ?></li>
                <li><?php esc_html_e('Once your knives arrive, our standard turnaround time is 48 hours, not including shipping time', 'rk-helper'); ?>
                </li>
                <li><?php esc_html_e('After sharpening is complete, your knives will be carefully packaged and shipped back to you.', 'rk-helper'); ?>
                </li>
            </ul>
            <?php
        } elseif ($service_type === 'mail_in') {
            ?>
            <p><?php esc_html_e('Thank you for your order with Rejuvenated Knives. We’re glad to help you get your knives sharpened conveniently from your doorstep!', 'rk-helper'); ?>
            </p>
            <p><?php esc_html_e('The next step, you will receive a text message confirming the pickup day and time frame. Please expect this confirmation by the end of the day you submit your request. The confirmation is important as we need to confirm the sharpener’s route for that day and we are sometimes fully booked.', 'rk-helper'); ?>
            </p>
            <p><i><?php esc_html_e('Please note: We communicate primarily by text message to ensure faster updates and confirmations.', 'rk-helper'); ?></i>
            </p>
            <?php
        } else {
            echo '<p><strong>' . esc_html__('Service Type:', 'rk-helper') . '</strong> ' . esc_html(ucwords(str_replace(array('_', '-'), ' ', $service_type_raw))) . '</p>';
        }
        ?>
    </div>
<?php endif; ?>

<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 */
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 */
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

/**
 * Show user-defined additional content
 */
if ($additional_content) {
    echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">' : '';
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
    echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);