<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var WC_Order    $order
 * @var object|null  $meta    Row from IPN_Order::get_meta(), or null when this isn't an IPN order.
 * @var object|null  $branch  Row from IPN_Branch::get(), when $meta->branch_id is set.
 */

if ( ! $meta || ! $meta->branch_id ) {
	return; // Not a Click & Collect order — nothing to show.
}

$stages = array(
	'received'  => __( 'Order Received', 'ipn' ),
	'accepted'  => __( 'Accepted', 'ipn' ),
	'preparing' => __( 'Preparing', 'ipn' ),
	'ready'     => __( 'Ready for Collection', 'ipn' ),
	'collected' => __( 'Collected', 'ipn' ),
);

$stage_order = array_keys( $stages );

$status_to_stage = array(
	'processing'    => 'received',
	'ipn-accepted'  => 'accepted',
	'ipn-preparing' => 'preparing',
	'ipn-ready'     => 'ready',
	'completed'     => 'collected',
);

$wc_status     = $order->get_status();
$current_stage = isset( $status_to_stage[ $wc_status ] ) ? $status_to_stage[ $wc_status ] : null;
$current_index = $current_stage ? array_search( $current_stage, $stage_order, true ) : -1;
$is_terminal   = in_array( $wc_status, array( 'cancelled', 'ipn-disputed', 'ipn-expired', 'refunded' ), true );
?>
<div class="ipn-order-tracker">
	<h3 class="ipn-order-tracker__heading"><?php esc_html_e( 'Click & Collect Status', 'ipn' ); ?></h3>

	<?php if ( $is_terminal ) : ?>
		<p class="ipn-order-tracker__notice">
			<?php if ( 'ipn-disputed' === $wc_status ) : ?>
				<?php esc_html_e( 'A collection issue was raised for this order. IMANAWORLD support will contact you about a refund.', 'ipn' ); ?>
			<?php elseif ( 'ipn-expired' === $wc_status ) : ?>
				<?php esc_html_e( 'The collection window for this order expired. It has been cancelled and any payment will be refunded.', 'ipn' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'This order was cancelled.', 'ipn' ); ?>
			<?php endif; ?>
		</p>
	<?php else : ?>
		<ol class="ipn-order-tracker__stages">
			<?php foreach ( $stage_order as $index => $stage_key ) : ?>
				<?php
				$step_class = '';
				if ( $current_index >= 0 && $index < $current_index ) {
					$step_class = 'ipn-tracker-step--done';
				} elseif ( $index === $current_index ) {
					$step_class = 'ipn-tracker-step--current';
				}
				?>
				<li class="ipn-tracker-step <?php echo esc_attr( $step_class ); ?>" data-stage="<?php echo esc_attr( $stage_key ); ?>">
					<span class="ipn-tracker-dot" aria-hidden="true"></span>
					<span class="ipn-tracker-label"><?php echo esc_html( $stages[ $stage_key ] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

		<?php if ( 'ready' === $current_stage ) : ?>
			<p class="ipn-order-tracker__notice">
				<?php esc_html_e( 'Your order is ready! Bring the 6-digit collection code from your email (or SMS) to the branch. Didn\'t get it? Contact IMANAWORLD support to have it resent.', 'ipn' ); ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $branch ) : ?>
		<div class="ipn-order-tracker__branch">
			<strong><?php echo esc_html( $branch->name ); ?></strong>
			<?php if ( $branch->address ) : ?>
				<div><?php echo esc_html( $branch->address ); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $meta->nominated_name ) ) : ?>
		<div class="ipn-order-tracker__recipient">
			<?php
			printf(
				/* translators: %s: nominated recipient's name. */
				esc_html__( 'Nominated to collect: %s', 'ipn' ),
				esc_html( $meta->nominated_name )
			);
			?>
		</div>
	<?php endif; ?>
</div>
