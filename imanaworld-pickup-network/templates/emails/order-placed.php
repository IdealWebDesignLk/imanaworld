<?php
defined( 'ABSPATH' ) || exit;
/**
 * Sent the moment payment is confirmed for a Click & Collect order.
 *
 * @var WC_Order $order Required.
 * @var string   $otp   Required. The 6-digit collection code from IPN_OTP::generate().
 * @var object   $branch Required. Row from IPN_Branch::get().
 */

if ( ! isset( $order ) ) {
	return;
}

include __DIR__ . '/partials/header.php';

$first_name = $order->get_billing_first_name();
$currency   = $order->get_currency();
$expiry_hrs = isset( $branch->otp_expiry_hours ) ? (int) $branch->otp_expiry_hours : 72;

$recipient_name = $order->get_meta( '_ipn_nominated_recipient_name' );
$collection_type = $order->get_meta( '_ipn_collection_type' );
?>

<h1 style="font-size:19px;margin:0 0 12px;letter-spacing:-0.01em;">
	<?php
	printf(
		/* translators: %s: customer first name */
		esc_html__( 'Thanks for your order, %s.', 'ipn' ),
		esc_html( $first_name ? $first_name : __( 'there', 'ipn' ) )
	);
	?>
</h1>

<p style="margin:0 0 14px;color:#3a3934;"><?php esc_html_e( "We've received your Click & Collect order and payment has been confirmed. Here's your collection code — you'll need it when you collect at the branch counter.", 'ipn' ); ?></p>

<?php
ipn_email_otp_box(
	isset( $otp ) ? $otp : '',
	sprintf(
		/* translators: %d: number of hours the collection code stays valid */
		esc_html__( 'Valid for %d hours · also visible in My Account', 'ipn' ),
		$expiry_hrs
	)
);
?>

<?php if ( $order->get_items() || $order->get_items( 'fee' ) ) : ?>
	<?php ipn_email_card_open(); ?>
	<?php foreach ( $order->get_items() as $item ) : ?>
		<?php
		ipn_email_row(
			sprintf( '%s × %d', $item->get_name(), $item->get_quantity() ),
			ipn_email_money( $item->get_total(), $currency )
		);
		?>
	<?php endforeach; ?>
	<?php foreach ( $order->get_items( 'fee' ) as $fee ) : ?>
		<?php ipn_email_row( $fee->get_name(), ipn_email_money( $fee->get_total(), $currency ) ); ?>
	<?php endforeach; ?>
	<?php ipn_email_row( __( 'Total paid', 'ipn' ), ipn_email_money( $order->get_total(), $currency ), true ); ?>
	<?php ipn_email_card_close(); ?>
<?php endif; ?>

<p style="margin:0 0 14px;color:#3a3934;">
	<strong><?php esc_html_e( 'Collecting from:', 'ipn' ); ?></strong>
	<?php echo esc_html( isset( $branch->name ) ? $branch->name : '' ); ?><?php echo isset( $branch->address ) && $branch->address ? ', ' . esc_html( $branch->address ) : ''; ?>
</p>

<?php if ( 'express' === $collection_type && isset( $branch->express_prep_minutes ) ) : ?>
	<p style="margin:0 0 14px;color:#3a3934;">
		<strong><?php esc_html_e( 'Express Collection —', 'ipn' ); ?></strong>
		<?php
		$mins = (int) $branch->express_prep_minutes;
		if ( $mins >= 60 && 0 === $mins % 60 ) {
			printf(
				/* translators: %d: number of hours */
				esc_html__( ' the branch commits to having this ready within %d hour(s).', 'ipn' ),
				(int) ( $mins / 60 )
			);
		} else {
			printf(
				/* translators: %d: number of minutes */
				esc_html__( ' the branch commits to having this ready within %d minutes.', 'ipn' ),
				$mins
			);
		}
		?>
	</p>
<?php endif; ?>

<?php if ( $recipient_name ) : ?>
	<p style="margin:0 0 14px;color:#3a3934;">
		<strong><?php esc_html_e( 'Nominated recipient —', 'ipn' ); ?></strong>
		<?php
		printf(
			/* translators: %s: nominated recipient's name */
			esc_html__( ' %s may also collect this order on your behalf, with matching ID and this code.', 'ipn' ),
			esc_html( $recipient_name )
		);
		?>
	</p>
<?php endif; ?>

<?php ipn_email_button( $order->get_view_order_url(), __( 'Track my order', 'ipn' ) ); ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
