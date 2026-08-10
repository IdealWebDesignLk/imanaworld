<?php
defined( 'ABSPATH' ) || exit;
/**
 * Sent when an order is cancelled — either the automatic expiry cancel
 * (collection window closed) or a manual cancellation.
 *
 * @var WC_Order $order  Required.
 * @var string   $reason Required. Human-readable cancellation reason.
 * @var object   $branch Required. Row from IPN_Branch::get().
 */

if ( ! isset( $order ) ) {
	return;
}

include __DIR__ . '/partials/header.php';

$currency = $order->get_currency();
$placed   = $order->get_date_created();
$placed_on = $placed ? $placed->date_i18n( 'd M Y' ) : '';
?>

<h1 style="font-size:19px;margin:0 0 12px;letter-spacing:-0.01em;"><?php esc_html_e( 'Your order has been cancelled.', 'ipn' ); ?></h1>

<p style="margin:0 0 14px;color:#3a3934;">
	<?php
	printf(
		/* translators: 1: order number, 2: branch name */
		esc_html__( 'Order %1$s from %2$s has been cancelled.', 'ipn' ),
		'<b>' . esc_html( $order->get_order_number() ) . '</b>',
		esc_html( isset( $branch->name ) ? $branch->name : __( 'the branch', 'ipn' ) )
	);
	?>
</p>

<?php if ( ! empty( $reason ) ) : ?>
	<p style="margin:0 0 14px;color:#3a3934;"><?php echo esc_html( $reason ); ?></p>
<?php endif; ?>

<?php
ipn_email_notice(
	sprintf(
		/* translators: %s: refunded amount, e.g. "BWP 222.20" */
		__( '↩ A refund of %s has been initiated to your original payment method. Refunds are usually reflected within 3–5 business days.', 'ipn' ),
		ipn_email_money( $order->get_total(), $currency )
	),
	'cancel'
);
?>

<?php if ( $placed_on ) : ?>
	<?php ipn_email_card_open(); ?>
	<?php ipn_email_row( __( 'Order placed', 'ipn' ), $placed_on ); ?>
	<?php ipn_email_row( __( 'Order cancelled', 'ipn' ), current_time( 'd M Y' ) ); ?>
	<?php ipn_email_card_close(); ?>
<?php endif; ?>

<p style="margin:0 0 14px;color:#3a3934;"><?php esc_html_e( "Changed your mind or ran out of time? You're welcome to place a new order any time.", 'ipn' ); ?></p>

<?php
$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
ipn_email_button( $shop_url, __( 'Shop again', 'ipn' ) );
?>

<?php include __DIR__ . '/partials/footer.php'; ?>
