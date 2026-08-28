<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var int                $branch_id
 * @var object|null        $branch
 * @var int                $order_id
 * @var object|null        $order       See IPN_Staff_Dashboard::get_order_detail() for the shape.
 *                                      Null when the order doesn't exist or isn't this branch's.
 * @var true|WP_Error|null $otp_result  Result of an OTP verification attempt made on this request,
 *                                      if any.
 * @var bool               $resend_sent True when a "resend collection code" action just succeeded.
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

$ipn_otp_status_labels = array(
	'active'     => __( 'Issued and waiting', 'ipn' ),
	'used'       => __( 'Verified — order collected', 'ipn' ),
	'expired'    => __( 'Expired', 'ipn' ),
	'superseded' => __( 'Replaced by a newer code', 'ipn' ),
);

$ipn_when = function ( $sql_date ) {
	return $sql_date && '0000-00-00 00:00:00' !== $sql_date
		? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sql_date ) )
		: '';
};

$next_status_key = array(
	'new'       => 'ipn-accepted',
	'accepted'  => 'ipn-preparing',
	'preparing' => 'ipn-ready',
);
?>
<div class="ipn-staff-dashboard">
	<div class="device">
		<section class="screen">
			<div class="ipn-sd-topbar">
				<div class="ipn-sd-topbar-row">
					<div class="back-row">
						<a class="icon-btn" href="<?php echo IPN_Staff_Dashboard::screen_url( 'queue' ); ?>" aria-label="<?php esc_attr_e( 'Back to queue', 'ipn' ); ?>">&larr;</a>
						<div class="ipn-sd-topbar-brand"><?php esc_html_e( 'Order detail', 'ipn' ); ?></div>
					</div>
					<?php include IPN_PLUGIN_DIR . 'templates/staff/partials/topbar-signout.php'; ?>
				</div>
			</div>

			<div class="content">
				<?php if ( ! $order ) : ?>
					<div class="empty-state">
						<?php esc_html_e( 'Order not found, or it does not belong to your branch.', 'ipn' ); ?>
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

					<div class="card card--half">
						<div class="card-title"><?php esc_html_e( 'Order', 'ipn' ); ?></div>
						<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Order status', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->wc_status ); ?></span></div>
						<?php if ( $order->payment_method ) : ?>
							<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Payment method', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->payment_method ); ?></span></div>
						<?php endif; ?>
						<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Currency', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->currency ); ?></span></div>
					</div>

					<div class="card card--half">
						<div class="card-title"><?php esc_html_e( 'Customer', 'ipn' ); ?></div>
						<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Name', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->customer_name ); ?></span></div>
						<?php if ( $order->billing->email ) : ?>
							<div class="kv-row">
								<span class="kv-label"><?php esc_html_e( 'Email', 'ipn' ); ?></span>
								<span class="kv-value"><a href="mailto:<?php echo esc_attr( $order->billing->email ); ?>"><?php echo esc_html( $order->billing->email ); ?></a></span>
							</div>
						<?php endif; ?>
						<?php if ( $order->billing->phone ) : ?>
							<div class="kv-row">
								<span class="kv-label"><?php esc_html_e( 'Phone', 'ipn' ); ?></span>
								<span class="kv-value"><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $order->billing->phone ) ); ?>"><?php echo esc_html( $order->billing->phone ); ?></a></span>
							</div>
						<?php endif; ?>
						<?php if ( $order->customer->is_guest ) : ?>
							<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Account', 'ipn' ); ?></span><span class="kv-value"><?php esc_html_e( 'Guest checkout', 'ipn' ); ?></span></div>
						<?php else : ?>
							<div class="kv-row">
								<span class="kv-label"><?php esc_html_e( 'Order history', 'ipn' ); ?></span>
								<span class="kv-value">
									<?php
									printf(
										/* translators: 1: number of orders, 2: total spent */
										esc_html( _n( '%1$s order, %2$s spent', '%1$s orders, %2$s spent', $order->customer->order_count, 'ipn' ) ),
										esc_html( number_format_i18n( $order->customer->order_count ) ),
										wp_kses_post( wc_price( $order->customer->total_spent, array( 'currency' => $order->currency ) ) )
									);
									?>
								</span>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $order->billing->address ) : ?>
						<div class="card card--half">
							<div class="card-title"><?php esc_html_e( 'Billing address', 'ipn' ); ?></div>
							<?php if ( $order->billing->company ) : ?>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Company', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->billing->company ); ?></span></div>
							<?php endif; ?>
							<div class="address-block"><?php echo wp_kses_post( $order->billing->address ); ?></div>
						</div>
					<?php endif; ?>

					<?php if ( $order->shipping ) : ?>
						<div class="card card--half">
							<div class="card-title"><?php esc_html_e( 'Shipping address', 'ipn' ); ?></div>
							<?php if ( $order->shipping->company ) : ?>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Company', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->shipping->company ); ?></span></div>
							<?php endif; ?>
							<div class="address-block"><?php echo wp_kses_post( $order->shipping->address ); ?></div>
							<?php if ( $order->shipping->phone ) : ?>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Phone', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->shipping->phone ); ?></span></div>
							<?php endif; ?>
							<?php if ( $order->shipping->method ) : ?>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Method', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $order->shipping->method ); ?></span></div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="card">
						<div class="card-title"><?php esc_html_e( 'Items', 'ipn' ); ?></div>
						<?php foreach ( $order->items as $item ) : ?>
							<div class="line-item">
								<div class="line-item__head">
									<span class="item-name"><?php echo esc_html( $item['name'] ); ?></span>
									<span class="line-item__total"><?php echo wp_kses_post( wc_price( $item['total'], array( 'currency' => $order->currency ) ) ); ?></span>
								</div>
								<div class="line-item__meta">
									<?php if ( $item['sku'] ) : ?>
										<span><?php esc_html_e( 'SKU', 'ipn' ); ?> <?php echo esc_html( $item['sku'] ); ?></span>
									<?php endif; ?>
									<span><?php echo wp_kses_post( wc_price( $item['price'], array( 'currency' => $order->currency ) ) ); ?> &times; <?php echo esc_html( $item['qty'] ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>

						<div class="totals">
							<div class="totals__row"><span><?php esc_html_e( 'Subtotal', 'ipn' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->totals->subtotal, array( 'currency' => $order->currency ) ) ); ?></span></div>
							<?php if ( $order->totals->discount > 0 ) : ?>
								<div class="totals__row"><span><?php esc_html_e( 'Discount', 'ipn' ); ?></span><span>&minus;<?php echo wp_kses_post( wc_price( $order->totals->discount, array( 'currency' => $order->currency ) ) ); ?></span></div>
							<?php endif; ?>
							<div class="totals__row"><span><?php esc_html_e( 'Shipping', 'ipn' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->totals->shipping, array( 'currency' => $order->currency ) ) ); ?></span></div>
							<?php if ( $order->totals->tax > 0 ) : ?>
								<div class="totals__row"><span><?php esc_html_e( 'Tax', 'ipn' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->totals->tax, array( 'currency' => $order->currency ) ) ); ?></span></div>
							<?php endif; ?>
							<div class="totals__row totals__row--grand"><span><?php esc_html_e( 'Order total', 'ipn' ); ?></span><span><?php echo wp_kses_post( wc_price( $order->totals->total, array( 'currency' => $order->currency ) ) ); ?></span></div>
						</div>
					</div>

					<?php if ( $order->commission ) : ?>
						<div class="card card--half">
							<div class="card-title"><?php esc_html_e( 'Commission', 'ipn' ); ?></div>
							<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Store earnings', 'ipn' ); ?></span><span class="kv-value"><?php echo wp_kses_post( wc_price( $order->commission['vendor_earning'], array( 'currency' => $order->currency ) ) ); ?></span></div>
							<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Marketplace commission', 'ipn' ); ?></span><span class="kv-value"><?php echo wp_kses_post( wc_price( $order->commission['commission'], array( 'currency' => $order->currency ) ) ); ?></span></div>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Shipping fees', 'ipn' ); ?></span><span class="kv-value"><?php echo wp_kses_post( wc_price( $order->commission['shipping'], array( 'currency' => $order->currency ) ) ); ?></span></div>
							<div class="otp-hint"><?php esc_html_e( 'Store earnings come from Dokan. The commission is what is left of the order total after them.', 'ipn' ); ?></div>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $order->attribution ) ) : ?>
						<div class="card card--half">
							<div class="card-title"><?php esc_html_e( 'Where this order came from', 'ipn' ); ?></div>
							<?php foreach ( $order->attribution as $ipn_att_label => $ipn_att_value ) : ?>
								<div class="kv-row"><span class="kv-label"><?php echo esc_html( $ipn_att_label ); ?></span><span class="kv-value"><?php echo esc_html( $ipn_att_value ); ?></span></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $order->notes ) ) : ?>
						<div class="card">
							<div class="card-title"><?php esc_html_e( 'Order notes', 'ipn' ); ?></div>
							<?php foreach ( $order->notes as $ipn_note ) : ?>
								<div class="order-note<?php echo $ipn_note['customer'] ? ' order-note--customer' : ''; ?>">
									<div class="order-note__body"><?php echo wp_kses_post( wpautop( $ipn_note['content'] ) ); ?></div>
									<div class="order-note__meta">
										<?php
										printf(
											/* translators: 1: who added the note, 2: when */
											esc_html__( '%1$s · %2$s', 'ipn' ),
											esc_html( $ipn_note['added_by'] ),
											esc_html( $ipn_note['date'] )
										);
										echo $ipn_note['customer'] ? ' · ' . esc_html__( 'sent to customer', 'ipn' ) : '';
										?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $order->otp ) : ?>
						<div class="card card--half">
							<div class="card-title"><?php esc_html_e( 'Collection code record', 'ipn' ); ?></div>
							<div class="kv-row">
								<span class="kv-label"><?php esc_html_e( 'Status', 'ipn' ); ?></span>
								<span class="kv-value"><?php echo esc_html( isset( $ipn_otp_status_labels[ $order->otp->status ] ) ? $ipn_otp_status_labels[ $order->otp->status ] : $order->otp->status ); ?></span>
							</div>
							<?php if ( $ipn_when( $order->otp->created_at ) ) : ?>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Issued', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $ipn_when( $order->otp->created_at ) ); ?></span></div>
							<?php endif; ?>
							<?php if ( 'used' !== $order->otp->status && $ipn_when( $order->otp->expires_at ) ) : ?>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Expires', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $ipn_when( $order->otp->expires_at ) ); ?></span></div>
							<?php endif; ?>
							<?php if ( $ipn_when( $order->otp->verified_at ) ) : ?>
								<div class="kv-row"><span class="kv-label"><?php esc_html_e( 'Verified', 'ipn' ); ?></span><span class="kv-value"><?php echo esc_html( $ipn_when( $order->otp->verified_at ) ); ?></span></div>
							<?php endif; ?>
							<?php if ( (int) $order->otp->failed_attempts > 0 ) : ?>
								<div class="kv-row">
									<span class="kv-label"><?php esc_html_e( 'Failed entries', 'ipn' ); ?></span>
									<span class="kv-value"><?php echo esc_html( number_format_i18n( (int) $order->otp->failed_attempts ) ); ?></span>
								</div>
							<?php endif; ?>
							<div class="otp-hint">
								<?php esc_html_e( 'The code itself is stored the way a password is and is never shown here. It is emailed to the customer, and it is what proves the person at the counter is the one who placed the order.', 'ipn' ); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( 'ready' === $order->status ) : ?>
						<div class="card">
							<div class="card-title"><?php esc_html_e( 'Verify collection', 'ipn' ); ?></div>
							<div class="otp-hint"><?php esc_html_e( 'Ask the customer (or nominated recipient) for their 6-digit email OTP and enter it below to complete the order.', 'ipn' ); ?></div>

							<?php if ( $otp_result instanceof WP_Error ) : ?>
								<div class="banner banner-danger"><?php echo esc_html( $otp_result->get_error_message() ); ?></div>
							<?php elseif ( true === $otp_result ) : ?>
								<div class="banner banner-success"><?php esc_html_e( 'Collection code verified. Order marked Collected.', 'ipn' ); ?></div>
							<?php endif; ?>

							<?php if ( $resend_sent ) : ?>
								<div class="banner banner-success"><?php esc_html_e( 'A new collection code was emailed to the customer.', 'ipn' ); ?></div>
							<?php endif; ?>

							<form method="post" class="otp-form">
								<?php wp_nonce_field( 'ipn_verify_otp_' . $order_id, 'ipn_otp_nonce' ); ?>
								<div class="otp-input-row">
									<input type="text" name="ipn_otp_code" maxlength="6" inputmode="numeric" pattern="[0-9]*" placeholder="000000" required="required" />
									<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Verify', 'ipn' ); ?></button>
								</div>
							</form>

							<form method="post" style="margin-top:8px;">
								<?php wp_nonce_field( 'ipn_resend_otp_' . $order_id, 'ipn_resend_nonce' ); ?>
								<input type="hidden" name="ipn_resend_otp" value="1" />
								<button type="submit" class="btn btn-ghost"><?php esc_html_e( "Customer lost their code — resend it", 'ipn' ); ?></button>
							</form>
						</div>

						<div class="reject-panel">
							<button type="button" class="btn btn-danger js-ipn-toggle-reject" aria-expanded="false" aria-controls="ipn-reject-panel">
								<?php esc_html_e( 'Reject collection instead', 'ipn' ); ?>
							</button>
							<form method="post" class="js-ipn-reject-panel" id="ipn-reject-panel" hidden="hidden" style="margin-top:10px;">
								<?php wp_nonce_field( 'ipn_reject_collection_' . $order_id, 'ipn_reject_nonce' ); ?>
								<div class="field">
									<label for="ipn-reject-reason"><?php esc_html_e( 'Reason', 'ipn' ); ?></label>
									<select id="ipn-reject-reason" name="ipn_reject_reason">
										<option value="damaged"><?php esc_html_e( 'Item damaged', 'ipn' ); ?></option>
										<option value="wrong_item"><?php esc_html_e( 'Wrong item picked', 'ipn' ); ?></option>
										<option value="customer_changed_mind"><?php esc_html_e( 'Customer changed mind', 'ipn' ); ?></option>
										<option value="other"><?php esc_html_e( 'Other', 'ipn' ); ?></option>
									</select>
								</div>
								<p class="otp-hint"><?php esc_html_e( 'Rejecting moves the order to Disputed and notifies IMANAWORLD admin to process the refund.', 'ipn' ); ?></p>
								<button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Confirm rejection', 'ipn' ); ?></button>
							</form>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $order->audit ) ) : ?>
						<div class="card card--half">
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

			<?php if ( $order && isset( $next_action_label[ $order->status ], $next_status_key[ $order->status ] ) ) : ?>
				<form method="post" class="sticky-actions">
					<?php wp_nonce_field( 'ipn_advance_status_' . $order_id, 'ipn_advance_nonce' ); ?>
					<input type="hidden" name="ipn_advance_to" value="<?php echo esc_attr( $next_status_key[ $order->status ] ); ?>" />
					<button type="submit" class="btn btn-primary">
						<?php echo esc_html( $next_action_label[ $order->status ] ); ?>
					</button>
				</form>
			<?php endif; ?>
		</section>
	</div>
</div>
