<?php
defined( 'ABSPATH' ) || exit;
/**
 * Vendor dashboard → Orders. Read-only across this vendor's branches, or
 * narrowed to one. Advancing an order's status is deliberately not here:
 * that belongs to the branch actually preparing and handing it over, and
 * happens on the staff dashboard against the collection code.
 *
 * @var array    $branches         This vendor's branches.
 * @var int      $orders_branch_id 0 = all of this vendor's branches.
 * @var object[] $orders
 */

$ipn_status_labels = array(
	'awaiting-payment' => __( 'Awaiting payment', 'ipn' ),
	'new'       => __( 'New', 'ipn' ),
	'accepted'  => __( 'Accepted', 'ipn' ),
	'preparing' => __( 'Preparing', 'ipn' ),
	'ready'     => __( 'Ready', 'ipn' ),
	'collected' => __( 'Collected', 'ipn' ),
	'disputed'  => __( 'Disputed', 'ipn' ),
	'expired'   => __( 'Expired', 'ipn' ),
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
					<th class="ipn-vd__num"><?php esc_html_e( 'Total', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $ipn_o ) : ?>
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
								<?php echo esc_html( isset( $ipn_status_labels[ $ipn_o->status ] ) ? $ipn_status_labels[ $ipn_o->status ] : $ipn_o->status ); ?>
							</span>
						</td>
						<td class="ipn-vd__num"><?php echo wp_kses_post( wc_price( $ipn_o->total ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="ipn-vd__hint">
		<?php esc_html_e( 'Order status is advanced by the branch handling the collection, from their own dashboard.', 'ipn' ); ?>
	</p>
<?php endif; ?>
