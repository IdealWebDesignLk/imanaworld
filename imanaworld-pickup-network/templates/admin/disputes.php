<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var object[] $orders Disputed orders only — see IPN_Admin::get_all_ipn_orders() for the shape.
 */
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Disputes & returns queue', 'ipn' ); ?></div>
		<span class="hint"><?php esc_html_e( 'Orders a branch marked Reject Collection. Refunds are always manual — process them in WooCommerce.', 'ipn' ); ?></span>
	</div>

	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'ipn' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total (BWP)', 'ipn' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $orders ) ) : ?>
					<tr>
						<td colspan="6">
							<div class="empty-state"><?php esc_html_e( 'No disputed orders right now.', 'ipn' ); ?></div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $orders as $order ) : ?>
						<tr data-order="<?php echo esc_attr( wp_json_encode( $order ) ); ?>" style="cursor:pointer;">
							<td><?php echo esc_html( $order->order_number ); ?></td>
							<td><?php echo esc_html( $order->branch_name ); ?></td>
							<td><?php echo esc_html( $order->customer_name ); ?></td>
							<td><?php echo esc_html( $order->dispute_reason ? $order->dispute_reason : '—' ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) $order->total, 2 ) ); ?></td>
							<td><a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $order->edit_url ); ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();"><?php esc_html_e( 'Refund', 'ipn' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<!-- Order detail modal — same shell as Orders & Disputes, populated from the clicked row's data-order attribute. -->
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
