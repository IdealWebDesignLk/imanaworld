<?php
defined( 'ABSPATH' ) || exit;
/**
 * Vendor dashboard -> Orders. Every Click & Collect order across this
 * vendor's branches, or narrowed to one, with the next step available inline.
 *
 * Advancing an order is shared with the branch staff dashboard rather than
 * owned by either: a branch may have no staff account of its own, and its
 * queue still has to be workable. Both surfaces read the same transition
 * table (IPN_Order::NEXT_STATUS), so neither can move an order somewhere the
 * other would not.
 *
 * The last step is the exception, and stays on the staff dashboard: an order
 * becomes Collected only against the collection code the customer presents at
 * the counter.
 *
 * @var array    $branches         This vendor's branches.
 * @var int      $orders_branch_id 0 = all of this vendor's branches.
 * @var object[] $orders
 */

$ipn_next_steps = IPN_Order::NEXT_STATUS;

$ipn_next_labels = array(
	'new'       => __( 'Accept order', 'ipn' ),
	'accepted'  => __( 'Mark as preparing', 'ipn' ),
	'preparing' => __( 'Mark ready for collection', 'ipn' ),
);

// Why a row has no button, where the reason is worth saying out loud.
$ipn_no_action_hint = array(
	'ready' => __( 'Collection code', 'ipn' ),
);
?>
<form method="get" class="ipn-vd__bar">
	<input type="hidden" name="ipn_tab" value="orders" />
	<label class="ipn-vd__field ipn-vd__field--inline">
		<span><?php esc_html_e( 'Branch', 'ipn' ); ?></span>
		<select name="branch_id" onchange="this.form.submit();">
			<option value="0"><?php esc_html_e( 'All my branches', 'ipn' ); ?></option>
			<?php foreach ( $branches as $ipn_b ) : ?>
				<option value="<?php echo esc_attr( $ipn_b->id ); ?>" <?php selected( (int) $orders_branch_id, (int) $ipn_b->id ); ?>>
					<?php echo esc_html( $ipn_b->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>
	<span class="ipn-vd__count">
		<?php
		printf(
			/* translators: %d: number of orders shown */
			esc_html( _n( '%d order', '%d orders', count( $orders ), 'ipn' ) ),
			count( $orders )
		);
		?>
	</span>
</form>

<?php if ( empty( $orders ) ) : ?>
	<div class="ipn-vd__empty">
		<p><?php esc_html_e( 'No Click & Collect orders for this selection yet.', 'ipn' ); ?></p>
	</div>
<?php else : ?>
	<div class="ipn-vd__table-wrap">
		<table class="ipn-vd__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Placed', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Collection code', 'ipn' ); ?></th>
					<th class="ipn-vd__num"><?php esc_html_e( 'Total', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Next step', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $ipn_o ) : ?>
					<?php $ipn_step = isset( $ipn_next_steps[ $ipn_o->status ] ) ? $ipn_next_steps[ $ipn_o->status ] : null; ?>
					<tr>
						<td>
							<b><?php echo esc_html( $ipn_o->order_number ); ?></b>
							<?php if ( 'express' === $ipn_o->type ) : ?>
								<span class="ipn-vd__pill is-express"><?php esc_html_e( 'Express', 'ipn' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $ipn_o->date_label ); ?></td>
						<td><?php echo esc_html( $ipn_o->branch_name ); ?></td>
						<td><?php echo esc_html( $ipn_o->customer_name ); ?></td>
						<td>
							<span class="ipn-vd__pill is-status-<?php echo esc_attr( $ipn_o->status ); ?>">
								<?php echo esc_html( IPN_Order::status_label( $ipn_o->status ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( '' !== $ipn_o->otp_code ) : ?>
								<span class="ipn-vd__code<?php echo 'active' === $ipn_o->otp_status ? '' : ' is-spent'; ?>"><?php echo esc_html( $ipn_o->otp_code ); ?></span>
							<?php else : ?>
								<span class="ipn-vd__muted">&mdash;</span>
							<?php endif; ?>
						</td>
						<td class="ipn-vd__num"><?php echo wp_kses_post( wc_price( $ipn_o->total ) ); ?></td>
						<td class="ipn-vd__actions">
							<?php if ( $ipn_step ) : ?>
								<form method="post">
									<?php wp_nonce_field( 'ipn_vendor_advance_order' ); ?>
									<input type="hidden" name="ipn_vendor_action" value="advance_order" />
									<input type="hidden" name="order_id" value="<?php echo esc_attr( $ipn_o->order_id ); ?>" />
									<input type="hidden" name="to_status" value="<?php echo esc_attr( $ipn_step[1] ); ?>" />
									<button type="submit" class="ipn-vd__btn ipn-vd__btn--small ipn-vd__btn--primary">
										<?php echo esc_html( $ipn_next_labels[ $ipn_o->status ] ); ?>
									</button>
								</form>
							<?php elseif ( 'awaiting-payment' === $ipn_o->status ) : ?>
								<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Confirm that payment for this order has been received? It will join the queue as New and its stock will be reserved at the branch.', 'ipn' ) ); ?>');">
									<?php wp_nonce_field( 'ipn_vendor_mark_paid' ); ?>
									<input type="hidden" name="ipn_vendor_action" value="mark_paid" />
									<input type="hidden" name="order_id" value="<?php echo esc_attr( $ipn_o->order_id ); ?>" />
									<button type="submit" class="ipn-vd__btn ipn-vd__btn--small">
										<?php esc_html_e( 'Mark payment received', 'ipn' ); ?>
									</button>
								</form>
							<?php elseif ( isset( $ipn_no_action_hint[ $ipn_o->status ] ) ) : ?>
								<span class="ipn-vd__muted"><?php echo esc_html( $ipn_no_action_hint[ $ipn_o->status ] ); ?></span>
							<?php else : ?>
								<span class="ipn-vd__muted">&mdash;</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="ipn-vd__hint">
		<?php esc_html_e( 'The collection code is what the customer brings to the counter. Check it against what they show you rather than reading it out to them, and treat this column the way you would treat their password.', 'ipn' ); ?>
	</p>
	<p class="ipn-vd__hint">
		<?php esc_html_e( 'You and your branch staff work the same queue, and either of you can move an order along. The last step is different: an order is only marked Collected once the collection code the customer brings to the counter has been checked, which happens on the branch dashboard.', 'ipn' ); ?>
	</p>
	<p class="ipn-vd__hint">
		<?php esc_html_e( 'An order placed by bank transfer, cheque, or payment at the counter arrives awaiting payment, and its stock is not reserved yet. Marking payment received puts it in the queue as New and reserves the stock, so only do it once the money has actually arrived.', 'ipn' ); ?>
	</p>
<?php endif; ?>
