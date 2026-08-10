<?php
defined( 'ABSPATH' ) || exit;
/**
 * Sent when the branch accepts the order and starts preparing it.
 *
 * @var WC_Order $order Required.
 * @var object   $branch Required. Row from IPN_Branch::get().
 */

if ( ! isset( $order ) ) {
	return;
}

include __DIR__ . '/partials/header.php';

$collection_type = $order->get_meta( '_ipn_collection_type' );

if ( 'express' === $collection_type && isset( $branch->express_prep_minutes ) ) {
	$mins = (int) $branch->express_prep_minutes;
	$collection_type_label = ( $mins >= 60 && 0 === $mins % 60 )
		? sprintf(
			/* translators: %d: number of hours */
			__( 'Express — ready within %d hour(s)', 'ipn' ),
			(int) ( $mins / 60 )
		)
		: sprintf(
			/* translators: %d: number of minutes */
			__( 'Express — ready within %d minutes', 'ipn' ),
			$mins
		);
} else {
	$collection_type_label = __( 'Standard collection', 'ipn' );
}
?>

<h1 style="font-size:19px;margin:0 0 12px;letter-spacing:-0.01em;"><?php esc_html_e( 'Good news — your order is being prepared.', 'ipn' ); ?></h1>

<p style="margin:0 0 14px;color:#3a3934;">
	<?php
	printf(
		/* translators: 1: branch name, 2: order number */
		esc_html__( '%1$s has accepted order %2$s and your items are now being picked and packed.', 'ipn' ),
		esc_html( isset( $branch->name ) ? $branch->name : __( 'Your branch', 'ipn' ) ),
		'<b>' . esc_html( $order->get_order_number() ) . '</b>'
	);
	?>
</p>

<?php ipn_email_card_open(); ?>
<?php ipn_email_row( __( 'Order', 'ipn' ), $order->get_order_number() ); ?>
<?php ipn_email_row( __( 'Collection type', 'ipn' ), $collection_type_label ); ?>
<?php if ( isset( $branch->name ) ) : ?>
	<?php ipn_email_row( __( 'Branch', 'ipn' ), $branch->name ); ?>
<?php endif; ?>
<?php ipn_email_card_close(); ?>

<p style="margin:0 0 14px;color:#3a3934;"><?php esc_html_e( "We'll email you again the moment it's ready, along with your collection code.", 'ipn' ); ?></p>

<?php ipn_email_button( $order->get_view_order_url(), __( 'Track my order', 'ipn' ) ); ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
