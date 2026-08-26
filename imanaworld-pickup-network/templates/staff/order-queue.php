<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var int         $branch_id
 * @var object|null $branch  Row from IPN_Branch::get().
 * @var array       $orders  See IPN_Staff_Dashboard::get_branch_orders() for the expected shape.
 *                            Always empty today — order routing isn't implemented yet.
 */

$statuses = array(
	'all'       => __( 'All', 'ipn' ),
	'new'       => __( 'New', 'ipn' ),
	'accepted'  => __( 'Accepted', 'ipn' ),
	'preparing' => __( 'Preparing', 'ipn' ),
	'ready'     => __( 'Ready', 'ipn' ),
	'collected' => __( 'Collected', 'ipn' ),
);

$active_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! isset( $statuses[ $active_status ] ) ) {
	$active_status = 'all';
}

$status_count = function ( $status ) use ( $orders ) {
	if ( 'all' === $status ) {
		return count( $orders );
	}
	return count(
		array_filter(
			$orders,
			function ( $order ) use ( $status ) {
				return isset( $order->status ) && $status === $order->status;
			}
		)
	);
};

$visible_orders = 'all' === $active_status
	? $orders
	: array_values(
		array_filter(
			$orders,
			function ( $order ) use ( $active_status ) {
				return isset( $order->status ) && $active_status === $order->status;
			}
		)
	);

$new_count = $status_count( 'new' );
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
					<?php if ( $branch_id ) : ?>
						<span class="open-pill">
							<?php echo IPN_Branch::is_open_now( $branch_id ) ? esc_html__( 'Open now', 'ipn' ) : esc_html__( 'Closed now', 'ipn' ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! $branch_id ) : ?>
				<div class="content">
					<div class="empty-state"><?php esc_html_e( 'Your account is not yet assigned to a branch. Contact IMANAWORLD admin.', 'ipn' ); ?></div>
				</div>
			<?php else : ?>
				<div class="filters">
					<?php foreach ( $statuses as $key => $label ) : ?>
						<a class="filter-tab<?php echo $key === $active_status ? ' active' : ''; ?>" href="<?php echo IPN_Staff_Dashboard::screen_url( 'queue', array( 'status' => $key ) ); ?>">
							<?php echo esc_html( $label ); ?> <span class="n"><?php echo esc_html( $status_count( $key ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="content">
					<?php if ( empty( $visible_orders ) ) : ?>
						<div class="empty-state">
							<?php esc_html_e( 'No orders in this view right now.', 'ipn' ); ?>
						</div>
					<?php else : ?>
						<?php foreach ( $visible_orders as $order ) : ?>
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
