<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var int         $branch_id
 * @var object|null $branch  Row from IPN_Branch::get().
 * @var array       $orders  See IPN_Staff_Dashboard::get_branch_orders() for the expected shape.
 */

/**
 * Labels for the status chip on each order card. Not a filter any more
 * (issue #25 follow-up) — with a branch's order volume this small, six
 * mostly-empty count tabs (plus "All") were pure clutter, and Expired and
 * Disputed had no tab at all, so an order in either state was invisible
 * unless you happened to already be on "All". One list, every order, with
 * its status readable straight off the card is simpler and misses nothing.
 */
$statuses = array(
	'awaiting-payment' => __( 'Awaiting payment', 'ipn' ),
	'new'       => __( 'New', 'ipn' ),
	'accepted'  => __( 'Accepted', 'ipn' ),
	'preparing' => __( 'Preparing', 'ipn' ),
	'ready'     => __( 'Ready', 'ipn' ),
	'collected' => __( 'Collected', 'ipn' ),
	'disputed'  => __( 'Disputed', 'ipn' ),
	'expired'   => __( 'Expired', 'ipn' ),
);

// Still used by the tab bar below, to badge the Queue tab with how many
// orders are waiting to be accepted — unrelated to the removed filter tabs.
$new_count = count(
	array_filter(
		$orders,
		function ( $order ) {
			return isset( $order->status ) && 'new' === $order->status;
		}
	)
);
?>
<div class="ipn-staff-dashboard">
	<div class="device">
		<section class="screen">
			<div class="ipn-sd-topbar">
				<div class="ipn-sd-topbar-row">
					<div>
						<div class="ipn-sd-topbar-brand"><?php esc_html_e( 'Branch Staff', 'ipn' ); ?></div>
						<div class="ipn-sd-topbar-branch"><?php echo $branch ? esc_html( $branch->name ) : esc_html__( 'No branch assigned', 'ipn' ); ?></div>
					</div>
					<?php include IPN_PLUGIN_DIR . 'templates/staff/partials/topbar-signout.php'; ?>
				</div>
			</div>

			<?php if ( ! $branch_id ) : ?>
				<div class="content">
					<div class="empty-state"><?php esc_html_e( 'Your account is not yet assigned to a branch. Contact IMANAWORLD admin.', 'ipn' ); ?></div>
				</div>
			<?php else : ?>
				<div class="content">
					<?php if ( empty( $orders ) ) : ?>
						<div class="empty-state">
							<?php esc_html_e( 'No orders in this view right now.', 'ipn' ); ?>
						</div>
					<?php else : ?>
						<?php foreach ( $orders as $order ) : ?>
							<a class="order-card" href="<?php echo IPN_Staff_Dashboard::screen_url( 'detail', array( 'order_id' => $order->order_id ) ); ?>">
								<div class="order-top">
									<div>
										<div class="order-id"><?php echo esc_html( $order->id ); ?></div>
										<div class="order-customer"><?php echo esc_html( $order->customer_name ); ?></div>
									</div>
									<div class="order-chips">
										<span class="chip chip-<?php echo esc_attr( $order->type ); ?>">
											<?php echo 'express' === $order->type ? esc_html__( 'Express', 'ipn' ) : esc_html__( 'Standard', 'ipn' ); ?>
										</span>
										<span class="chip chip-<?php echo esc_attr( $order->status ); ?>">
											<span class="chip-dot"></span><?php echo esc_html( isset( $statuses[ $order->status ] ) ? $statuses[ $order->status ] : ucfirst( $order->status ) ); ?>
										</span>
									</div>
								</div>
								<div class="order-meta">
									<span class="order-time"><?php echo esc_html( $order->time_label ); ?></span>
									<span>&middot;</span>
									<span>
										<?php
										/* translators: %d: number of items in the order. */
										echo esc_html( sprintf( _n( '%d item', '%d items', (int) $order->item_count, 'ipn' ), (int) $order->item_count ) );
										?>
									</span>
									<?php if ( ! empty( $order->has_recipient ) ) : ?>
										<span>&middot; <?php esc_html_e( 'Nominated recipient', 'ipn' ); ?></span>
									<?php endif; ?>
								</div>
							</a>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			$ipn_active_tab = 'queue';
			include IPN_PLUGIN_DIR . 'templates/staff/partials/tabbar.php';
			?>
		</section>
	</div>
</div>
