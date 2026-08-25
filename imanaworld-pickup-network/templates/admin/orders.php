<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var object[] $orders           See IPN_Admin::get_all_ipn_orders() for the shape.
 * @var array    $branches         Every branch, for the branch filter.
 * @var int      $filter_branch_id 0 = all branches.
 * @var array    $status_counts    Row count per display status, across the loaded set.
 */

$ipn_statuses = array(
	'new'       => __( 'New', 'ipn' ),
	'accepted'  => __( 'Accepted', 'ipn' ),
	'preparing' => __( 'Preparing', 'ipn' ),
	'ready'     => __( 'Ready', 'ipn' ),
	'collected' => __( 'Collected', 'ipn' ),
	'disputed'  => __( 'Disputed', 'ipn' ),
	'expired'   => __( 'Expired', 'ipn' ),
);

// The statuses worth pulling out as counters above the table — an admin
// opening this screen is looking for what still needs action, which was
// impossible to see when new orders sat undifferentiated among hundreds of
// Collected ones (issue #16). Collected/expired are deliberately not here.
$ipn_attention_statuses = array( 'new', 'accepted', 'preparing', 'ready', 'disputed' );
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'All IPN orders', 'ipn' ); ?></div>
		<span class="hint"><?php esc_html_e( 'Orders reverted to Disputed status via a branch Reject Collection are reviewed under Disputes & Returns.', 'ipn' ); ?></span>
	</div>

	<?php if ( ! empty( $orders ) ) : ?>
		<div class="status-counters">
			<button type="button" class="status-counter" onclick="ipnSetOrderStatusFilter('all')">
				<span class="status-counter__n"><?php echo esc_html( number_format_i18n( count( $orders ) ) ); ?></span>
				<span class="status-counter__l"><?php esc_html_e( 'All', 'ipn' ); ?></span>
			</button>
			<?php foreach ( $ipn_attention_statuses as $ipn_status_key ) : ?>
				<button type="button" class="status-counter status-counter--<?php echo esc_attr( $ipn_status_key ); ?>" onclick="ipnSetOrderStatusFilter('<?php echo esc_js( $ipn_status_key ); ?>')">
					<span class="status-counter__n"><?php echo esc_html( number_format_i18n( isset( $status_counts[ $ipn_status_key ] ) ? $status_counts[ $ipn_status_key ] : 0 ) ); ?></span>
					<span class="status-counter__l"><?php echo esc_html( $ipn_statuses[ $ipn_status_key ] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="toolbar">
		<!-- Search and status filter the rows already on the page; the branch
		     filter is a real query (its own form), because restricting the
		     loaded set to one branch is the only way its older orders can be
		     reached at all. -->
		<input type="text" id="ipn-orders-search" placeholder="<?php esc_attr_e( 'Search order ID or customer…', 'ipn' ); ?>" />
		<select id="ipn-orders-status-filter">
			<option value="all"><?php esc_html_e( 'All statuses', 'ipn' ); ?></option>
			<?php foreach ( $ipn_statuses as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<form method="get">
			<input type="hidden" name="page" value="ipn-orders" />
			<select name="branch_id" onchange="this.form.submit();">
				<option value="0"><?php esc_html_e( 'All branches', 'ipn' ); ?></option>
				<?php foreach ( $branches as $ipn_branch_option ) : ?>
					<option value="<?php echo esc_attr( $ipn_branch_option->id ); ?>" <?php selected( (int) $filter_branch_id, (int) $ipn_branch_option->id ); ?>>
						<?php echo esc_html( $ipn_branch_option->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Filter', 'ipn' ); ?></button></noscript>
		</form>
	</div>

	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Placed', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Type', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ipn' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total (BWP)', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody id="ipn-orders-tbody">
				<?php if ( empty( $orders ) ) : ?>
					<tr>
						<td colspan="7">
							<div class="empty-state">
								<?php if ( $filter_branch_id ) : ?>
									<?php esc_html_e( 'No Click & Collect orders for this branch yet.', 'ipn' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'No Click & Collect orders yet.', 'ipn' ); ?>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $orders as $order ) : ?>
						<tr
							data-ipn-row="1"
							data-status="<?php echo esc_attr( $order->status ); ?>"
							data-search="<?php echo esc_attr( strtolower( $order->order_number . ' ' . $order->customer_name ) ); ?>"
							data-order="<?php echo esc_attr( wp_json_encode( $order ) ); ?>"
							class="ipn-order-row<?php echo 'new' === $order->status ? ' ipn-order-row--new' : ''; ?>"
							style="cursor:pointer;"
						>
							<td><?php echo esc_html( $order->order_number ); ?></td>
							<td>
								<?php echo esc_html( $order->date_label ); ?>
								<?php if ( $order->age_label ) : ?>
									<div class="hint"><?php echo esc_html( $order->age_label ); ?></div>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $order->branch_name ); ?></td>
							<td><?php echo esc_html( $order->customer_name ); ?></td>
							<td>
								<span class="chip chip-<?php echo esc_attr( $order->type ); ?>">
									<?php echo 'express' === $order->type ? esc_html__( 'Express', 'ipn' ) : esc_html__( 'Standard', 'ipn' ); ?>
								</span>
							</td>
							<td>
								<span class="chip chip-<?php echo esc_attr( $order->status ); ?>">
									<span class="chip-dot"></span><?php echo esc_html( isset( $ipn_statuses[ $order->status ] ) ? $ipn_statuses[ $order->status ] : ucfirst( $order->status ) ); ?>
								</span>
							</td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) $order->total, 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Nested inside .wrap.ipn-admin deliberately — admin.css's modal rules
	     are scoped as ".ipn-admin .modal-scrim" etc., so a modal placed
	     outside this wrapper renders unstyled and permanently visible
	     instead of as a hidden overlay. -->
	<div class="modal-scrim" id="ipn-order-modal-scrim">
	<div class="modal">
		<div class="modal-head">
			<div class="modal-title" id="ipn-om-title"><?php esc_html_e( 'Order detail', 'ipn' ); ?></div>
			<button type="button" class="modal-close" onclick="ipnCloseModal('ipn-order-modal-scrim')">✕</button>
		</div>
		<div class="modal-body">
			<div id="ipn-om-chips" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;"></div>
			<div class="panel" id="ipn-om-dispute" style="margin-bottom:12px;display:none;"></div>
			<div class="panel" style="margin-bottom:12px;">
				<div class="panel-title" style="margin-bottom:8px;"><?php esc_html_e( 'Items', 'ipn' ); ?></div>
				<div id="ipn-om-items"></div>
			</div>
			<div class="panel" id="ipn-om-recipient" style="margin-bottom:12px;display:none;"></div>
			<div class="panel">
				<div class="panel-title" style="margin-bottom:8px;"><?php esc_html_e( 'Audit trail', 'ipn' ); ?></div>
				<div id="ipn-om-audit"></div>
			</div>
		</div>
		<div class="modal-foot">
			<a id="ipn-om-edit-link" class="btn btn-secondary" href="#" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Edit / refund in WooCommerce', 'ipn' ); ?></a>
			<button type="button" class="btn btn-ghost" onclick="ipnCloseModal('ipn-order-modal-scrim')"><?php esc_html_e( 'Close', 'ipn' ); ?></button>
		</div>
	</div>
</div>
</div>
