<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var int                $branch_id
 * @var object|null        $branch
 * @var int                $order_id
 * @var object|null        $order       See IPN_Staff_Dashboard::get_order_detail() for the expected
 *                                      shape. Always null today — order routing isn't implemented yet.
 * @var true|WP_Error|null $otp_result  Result of an OTP verification attempt made on this request,
 *                                      if any (IPN_OTP::verify() is real and wired up below).
 */

$statuses = array(
	'new'       => __( 'New', 'ipn' ),
	'accepted'  => __( 'Accepted', 'ipn' ),
	'preparing' => __( 'Preparing', 'ipn' ),
	'ready'     => __( 'Ready', 'ipn' ),
	'collected' => __( 'Collected', 'ipn' ),
	'disputed'  => __( 'Disputed', 'ipn' ),
);

$next_action_label = array(
	'new'       => __( 'Accept order', 'ipn' ),
	'accepted'  => __( 'Mark as preparing', 'ipn' ),
	'preparing' => __( 'Mark ready for collection', 'ipn' ),
);
?>
<div class="ipn-staff-dashboard">
	<div class="device">
		<section class="screen">
			<div class="topbar">
				<div class="back-row">
					<a class="icon-btn" href="<?php echo IPN_Staff_Dashboard::screen_url( 'queue' ); ?>" aria-label="<?php esc_attr_e( 'Back to queue', 'ipn' ); ?>">&larr;</a>
					<div class="topbar-brand"><?php esc_html_e( 'Order detail', 'ipn' ); ?></div>
				</div>
			</div>

			<div class="content">
				<?php if ( ! $order ) : ?>
					<div class="empty-state">
						<?php esc_html_e( 'No order to show — order routing is not implemented yet.', 'ipn' ); ?>
					</div>
				<?php else : ?>
					<div class="detail-header">
						<div class="detail-idrow">
							<span class="detail-id"><?php echo esc_html( $order->id ); ?></span>
							<span class="order-time" style="font-size:12px;color:var(--muted);"><?php echo esc_html( $order->time_label ); ?></span>
						</div>
						<div class="detail-customer"><?php echo esc_html( $order->customer_name ); ?></div>
						<div class="detail-chips">
							<span class="chip chip-<?php echo esc_attr( $order->type ); ?>">
								<?php echo 'express' === $order->type ? esc_html__( 'Express', 'ipn' ) : esc_html__( 'Standard', 'ipn' ); ?>
							</span>
							<span class="chip chip-<?php echo esc_attr( $order->status ); ?>">
								<span class="chip-dot"></span><?php echo esc_html( isset( $statuses[ $order->status ] ) ? $statuses[ $order->status ] : ucfirst( $order->status ) ); ?>
							</span>
							<?php if ( 'express' === $order->type && ! empty( $order->surcharge ) ) : ?>
								<span class="chip chip-standard">
									<?php
									/* translators: %s: express surcharge amount. */
									echo esc_html( sprintf( __( 'BWP %s surcharge', 'ipn' ), number_format_i18n( (float) $order->surcharge, 2 ) ) );
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( 'collected' === $order->status ) : ?>
						<div class="banner banner-success">&#10003; <?php esc_html_e( 'Collected. Order complete and closed out.', 'ipn' ); ?></div>
					<?php elseif ( 'disputed' === $order->status && ! empty( $order->dispute_reason ) ) : ?>
						<div class="banner banner-danger">
							<?php
							/* translators: %s: dispute/rejection reason. */
							echo esc_html( sprintf( __( 'Marked Disputed — reason: "%s". IMANAWORLD admin has been notified and will process the refund.', 'ipn' ), $order->dispute_reason ) );
							?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $order->recipient ) ) : ?>
						<div class="recipient-banner">
							<span><?php esc_html_e( 'Nominated recipient will collect — verify their ID before releasing this order.', 'ipn' ); ?></span>
						</div>
						<div class="card">
							<div class="card-title"><?php esc_html_e( 'Nominated recipient', 'ipn' ); ?></div>
							<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Name', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->recipient->name ); ?></span></div>
							<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Phone', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->recipient->phone ); ?></span></div>
							<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'ID number', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->recipient->id_number ); ?></span></div>
						</div>
					<?php endif; ?>

					<div class="card">
						<div class="card-title"><?php esc_html_e( 'Items', 'ipn' ); ?></div>
						<?php foreach ( $order->items as $item ) : ?>
							<div class="item-row">
								<span class="item-name"><?php echo esc_html( $item['name'] ); ?></span>
								<span class="item-qty">&times;<?php echo esc_html( $item['qty'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( 'ready' === $order->status ) : ?>
						<div class="card">
							<div class="card-title"><?php esc_html_e( 'Collection code', 'ipn' ); ?></div>
							<div class="otp-hint"><?php esc_html_e( 'Ask the customer (or nominated recipient) for their 6-digit email OTP and enter it below to complete the order.', 'ipn' ); ?></div>

							<?php if ( $otp_result instanceof WP_Error ) : ?>
								<div class="banner banner-danger"><?php echo esc_html( $otp_result->get_error_message() ); ?></div>
							<?php elseif ( true === $otp_result ) : ?>
								<div class="banner banner-success"><?php esc_html_e( 'Collection code verified. (Automatically moving the order to Collected is not implemented yet — update it manually if your process needs that today.)', 'ipn' ); ?></div>
							<?php endif; ?>

							<form method="post" class="otp-form">
								<?php wp_nonce_field( 'ipn_verify_otp_' . $order_id, 'ipn_otp_nonce' ); ?>
								<div class="otp-input-row">
									<input type="text" name="ipn_otp_code" maxlength="6" inputmode="numeric" pattern="[0-9]*" placeholder="000000" required="required" />
									<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Verify', 'ipn' ); ?></button>
								</div>
							</form>
						</div>

						<div class="reject-panel">
							<button type="button" class="btn btn-danger js-ipn-toggle-reject" aria-expanded="false" aria-controls="ipn-reject-panel">
								<?php esc_html_e( 'Reject collection instead', 'ipn' ); ?>
							</button>
							<div class="js-ipn-reject-panel" id="ipn-reject-panel" hidden="hidden" style="margin-top:10px;">
								<div class="field">
									<label for="ipn-reject-reason"><?php esc_html_e( 'Reason', 'ipn' ); ?></label>
									<select id="ipn-reject-reason" disabled="disabled">
										<option><?php esc_html_e( 'Item damaged', 'ipn' ); ?></option>
										<option><?php esc_html_e( 'Wrong item picked', 'ipn' ); ?></option>
										<option><?php esc_html_e( 'Customer changed mind', 'ipn' ); ?></option>
										<option><?php esc_html_e( 'Other', 'ipn' ); ?></option>
									</select>
								</div>
								<p class="otp-hint"><?php esc_html_e( 'Rejecting a collection is not implemented yet — this will notify IMANAWORLD admin once dispute handling is built.', 'ipn' ); ?></p>
								<button type="button" class="btn btn-secondary" disabled="disabled"><?php esc_html_e( 'Confirm rejection', 'ipn' ); ?></button>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $order->audit ) ) : ?>
						<div class="card">
							<div class="card-title"><?php esc_html_e( 'Audit trail', 'ipn' ); ?></div>
							<div class="audit-list">
								<?php foreach ( $order->audit as $entry ) : ?>
									<div class="audit-item">
										<div class="audit-dot"></div>
										<div>
											<div class="audit-text"><?php echo esc_html( $entry['text'] ); ?></div>
											<div class="audit-time"><?php echo esc_html( $entry['time'] ); ?></div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php if ( $order && isset( $next_action_label[ $order->status ] ) ) : ?>
				<div class="sticky-actions">
					<button type="button" class="btn btn-primary" disabled="disabled" title="<?php esc_attr_e( 'Advancing order status is not implemented yet.', 'ipn' ); ?>">
						<?php echo esc_html( $next_action_label[ $order->status ] ); ?>
					</button>
				</div>
			<?php endif; ?>
		</section>
	</div>
</div>
