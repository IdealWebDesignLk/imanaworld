<?php
defined( 'ABSPATH' ) || exit;
/**
 * Sent to a branch's own contact email when a new order needs accepting.
 * Confirmed live (issue #51): this used to be plain text while every
 * customer email got the styled HTML template, an inconsistency that read
 * as broken once seen side by side in the Email Log. Same shell/helpers as
 * the customer emails now, just addressed to branch staff instead.
 *
 * @var WC_Order $order Required.
 * @var object   $branch Required. Row from IPN_Branch::get().
 */

if ( ! isset( $order ) ) {
	return;
}

include __DIR__ . '/partials/header.php';
?>

<h1 style="font-size:19px;margin:0 0 12px;letter-spacing:-0.01em;"><?php esc_html_e( 'A new order needs accepting.', 'ipn' ); ?></h1>

<p style="margin:0 0 14px;color:#3a3934;"><?php esc_html_e( 'A new Click & Collect order is waiting in your queue.', 'ipn' ); ?></p>

<?php ipn_email_card_open(); ?>
<?php ipn_email_row( __( 'Order', 'ipn' ), $order->get_order_number() ); ?>
<?php ipn_email_row( __( 'Customer', 'ipn' ), IPN_Order::customer_name( $order ) ); ?>
<?php ipn_email_row( __( 'Items', 'ipn' ), $order->get_item_count() ); ?>
<?php if ( isset( $branch->name ) ) : ?>
	<?php ipn_email_row( __( 'Branch', 'ipn' ), $branch->name ); ?>
<?php endif; ?>
<?php ipn_email_card_close(); ?>

<p style="margin:0 0 14px;color:#3a3934;"><?php esc_html_e( 'Log in to your branch staff dashboard to accept it.', 'ipn' ); ?></p>

<?php ipn_email_button( IPN_Pages::staff_dashboard_url(), __( 'Open staff dashboard', 'ipn' ) ); ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
