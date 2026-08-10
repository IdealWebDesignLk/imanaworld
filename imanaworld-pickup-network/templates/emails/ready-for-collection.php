<?php
defined( 'ABSPATH' ) || exit;
/**
 * Primary collection trigger — sent when the branch marks the order ready.
 *
 * @var WC_Order $order Required.
 * @var string   $otp   Required. The 6-digit collection code from IPN_OTP::generate().
 * @var object   $branch Required. Row from IPN_Branch::get().
 */

if ( ! isset( $order ) ) {
	return;
}

include __DIR__ . '/partials/header.php';

$expiry_hrs      = isset( $branch->otp_expiry_hours ) ? (int) $branch->otp_expiry_hours : 72;
$recipient_name  = $order->get_meta( '_ipn_nominated_recipient_name' );
?>

<h1 style="font-size:19px;margin:0 0 12px;letter-spacing:-0.01em;"><?php esc_html_e( 'Your order is ready for collection.', 'ipn' ); ?></h1>

<p style="margin:0 0 14px;color:#3a3934;">
	<?php
	printf(
		/* translators: %s: order number */
		esc_html__( 'Order %s is packed and waiting at the branch counter. Show this code to staff to collect.', 'ipn' ),
		'<b>' . esc_html( $order->get_order_number() ) . '</b>'
	);
	?>
</p>

<?php
ipn_email_otp_box(
	isset( $otp ) ? $otp : '',
	sprintf(
		/* translators: %d: number of hours the collection code stays valid */
		esc_html__( 'Valid for %d hours from now', 'ipn' ),
		$expiry_hrs
	)
);
?>

<p style="margin:0 0 14px;color:#3a3934;">
	<strong><?php esc_html_e( 'Collect from:', 'ipn' ); ?></strong>
	<?php echo esc_html( isset( $branch->name ) ? $branch->name : '' ); ?><?php echo isset( $branch->address ) && $branch->address ? ', ' . esc_html( $branch->address ) : ''; ?>
</p>

<?php if ( $recipient_name ) : ?>
	<p style="margin:0 0 14px;color:#3a3934;">
		<strong><?php esc_html_e( 'Nominated recipient —', 'ipn' ); ?></strong>
		<?php
		printf(
			/* translators: %s: nominated recipient's name */
			esc_html__( ' %s may collect on your behalf. They\'ll need to show ID matching the name on file along with this code.', 'ipn' ),
			esc_html( $recipient_name )
		);
		?>
	</p>
<?php endif; ?>

<?php ipn_email_button( $order->get_view_order_url(), __( 'View collection details', 'ipn' ) ); ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
