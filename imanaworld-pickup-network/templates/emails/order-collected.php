<?php
defined( 'ABSPATH' ) || exit;
/**
 * Sent once staff verify the OTP and mark the order collected.
 *
 * @var WC_Order $order Required.
 * @var object   $branch Required. Row from IPN_Branch::get().
 */

if ( ! isset( $order ) ) {
	return;
}

include __DIR__ . '/partials/header.php';

$first_name  = $order->get_billing_first_name();
$currency    = $order->get_currency();
$completed   = $order->get_date_completed();
$collected_on = $completed ? $completed->date_i18n( 'd M Y, g:i A' ) : '';
?>

<h1 style="font-size:19px;margin:0 0 12px;letter-spacing:-0.01em;">
	<?php
	printf(
		/* translators: %s: customer first name */
		esc_html__( 'Order complete — thanks, %s!', 'ipn' ),
		esc_html( $first_name ? $first_name : __( 'there', 'ipn' ) )
	);
	?>
</h1>

<p style="margin:0 0 14px;color:#3a3934;">
	<?php
	printf(
		/* translators: 1: order number, 2: branch name */
		esc_html__( 'Order %1$s was successfully collected from %2$s. We hope everything was exactly as ordered.', 'ipn' ),
		'<b>' . esc_html( $order->get_order_number() ) . '</b>',
		esc_html( isset( $branch->name ) ? $branch->name : __( 'the branch', 'ipn' ) )
	);
	?>
</p>

<?php ipn_email_card_open(); ?>
<?php if ( $collected_on ) : ?>
	<?php ipn_email_row( __( 'Collected on', 'ipn' ), $collected_on ); ?>
<?php endif; ?>
<?php if ( isset( $branch->name ) ) : ?>
	<?php ipn_email_row( __( 'Branch', 'ipn' ), $branch->name ); ?>
<?php endif; ?>
<?php ipn_email_row( __( 'Total paid', 'ipn' ), ipn_email_money( $order->get_total(), $currency ), true ); ?>
<?php ipn_email_card_close(); ?>

<p style="margin:0 0 14px;color:#3a3934;"><?php esc_html_e( 'Something not right with your order? You can raise a return from your order history within 7 days of collection.', 'ipn' ); ?></p>

<?php ipn_email_button( $order->get_view_order_url(), __( 'View receipt', 'ipn' ) ); ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
