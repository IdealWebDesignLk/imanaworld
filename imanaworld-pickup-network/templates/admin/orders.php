<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'All IPN orders', 'ipn' ); ?></div>
		<span class="hint"><?php esc_html_e( 'Orders reverted to Disputed status via a branch Reject Collection are reviewed under Disputes & Returns.', 'ipn' ); ?></span>
	</div>

	<div class="toolbar">
		<input type="text" id="ipn-orders-search" placeholder="<?php esc_attr_e( 'Search order ID or customer…', 'ipn' ); ?>" />
		<select id="ipn-orders-status-filter">
			<option value="all"><?php esc_html_e( 'All statuses', 'ipn' ); ?></option>
			<option value="new"><?php esc_html_e( 'New', 'ipn' ); ?></option>
			<option value="accepted"><?php esc_html_e( 'Accepted', 'ipn' ); ?></option>
			<option value="preparing"><?php esc_html_e( 'Preparing', 'ipn' ); ?></option>
			<option value="ready"><?php esc_html_e( 'Ready', 'ipn' ); ?></option>
			<option value="collected"><?php esc_html_e( 'Collected', 'ipn' ); ?></option>
			<option value="disputed"><?php esc_html_e( 'Disputed', 'ipn' ); ?></option>
			<option value="expired"><?php esc_html_e( 'Expired', 'ipn' ); ?></option>
		</select>
	</div>

	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Type', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ipn' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total (BWP)', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody id="ipn-orders-tbody">
				<tr>
					<td colspan="6">
						<div class="empty-state"><?php esc_html_e( 'Order routing and dispute review are not implemented yet.', 'ipn' ); ?></div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<!-- Order detail modal — markup only for now; nothing on this page opens it yet
     because there is no real order data source to populate it from. -->
<div class="modal-scrim" id="ipn-order-modal-scrim">
	<div class="modal">
		<div class="modal-head">
			<div class="modal-title" id="ipn-om-title"><?php esc_html_e( 'Order detail', 'ipn' ); ?></div>
			<button type="button" class="modal-close" onclick="ipnCloseModal('ipn-order-modal-scrim')">✕</button>
		</div>
		<div class="modal-body">
			<div id="ipn-om-chips" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;"></div>
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
			<button type="button" class="btn btn-ghost" onclick="ipnCloseModal('ipn-order-modal-scrim')"><?php esc_html_e( 'Close', 'ipn' ); ?></button>
		</div>
	</div>
</div>
