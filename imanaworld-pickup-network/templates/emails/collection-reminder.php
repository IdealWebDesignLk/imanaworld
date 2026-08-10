<?php
defined( 'ABSPATH' ) || exit;
/**
 * Sent reminder_after_hours after the order was marked ready, if it still
 * hasn't been collected.
 *
 * @var WC_Order $order Required.
 * @var string   $otp   Required. The 6-digit collection code from IPN_OTP::generate().
 * @var object   $branch Required. Row from IPN_Branch::get().
 */

if ( ! isset( $order ) ) {
	return;
}

include __DIR__ . '/partials/header.php';

$expiry_hrs   = isset( $branch->otp_expiry_hours ) ? (int) $branch->otp_expiry_hours : 72;
$reminder_hrs = isset( $branch->reminder_after_hours ) ? (int) $branch->reminder_after_hours : 48;
$remaining    = max( 0, $expiry_hrs - $reminder_hrs );
?>

<h1 style="font-size:19px;margin:0 0 12px;letter-spacing:-0.01em;"><?php esc_html_e( "Don't forget to collect your order.", 'ipn' ); ?></h1>

<p style="margin:0 0 14px;color:#3a3934;">
	<?php
	printf(
		/* translators: 1: hours since ready, 2: order number, 3: branch name */
		esc_html__( "It's been %1\$d hours since order %2\$s was marked ready at %3\$s. Your collection code is still valid, but not for much longer.", 'ipn' ),
		$reminder_hrs,
		'<b>' . esc_html( $order->get_order_number() ) . '</b>',
		esc_html( isset( $branch->name ) ? $branch->name : __( 'your branch', 'ipn' ) )
	);
	?>
</p>

<?php
ipn_email_otp_box(
	isset( $otp ) ? $otp : '',
	sprintf(
		/* translators: %d: number of hours before the collection code expires */
		esc_html__( 'Expires in %d hours', 'ipn' ),
		$remaining
	)
);

ipn_email_notice(
	sprintf(
		/* translators: %d: number of hours before auto-cancellation */
		__( "⚠ If this order isn't collected within %d hours, it will be automatically cancelled and refunded to your original payment method.", 'ipn' ),
		$remaining
	),
	'warn'
);
?>

<p style="margin:0 0 14px;color:#3a3934;">
	<strong><?php esc_html_e( 'Collect from:', 'ipn' ); ?></strong>
	<?php echo esc_html( isset( $branch->name ) ? $branch->name : '' ); ?><?php echo isset( $branch->address ) && $branch->address ? ', ' . esc_html( $branch->address ) : ''; ?>
</p>

<?php ipn_email_button( $order->get_view_order_url(), __( 'View collection details', 'ipn' ) ); ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
