<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var array             $branches
 * @var true|WP_Error|null $adjust_result Result of a stock adjustment posted this request, if any.
 */

// Aggregate stock across every branch — same IPN_Branch_Stock::get_stock_for_branch()
// query the stock page already used, just run once per branch instead of one at a time.
$ipn_stock_rows = array();
foreach ( $branches as $branch ) {
	foreach ( IPN_Branch_Stock::get_stock_for_branch( $branch->id ) as $row ) {
		$row->branch_name = $branch->name;
		$ipn_stock_rows[]  = $row;
	}
}
?>
<div class="wrap ipn-admin">
	<?php if ( $adjust_result instanceof WP_Error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $adjust_result->get_error_message() ); ?></p></div>
	<?php elseif ( true === $adjust_result ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Stock updated.', 'ipn' ); ?></p></div>
	<?php endif; ?>

	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Stock overview — all branches', 'ipn' ); ?></div>
	</div>

	<?php if ( empty( $branches ) ) : ?>
		<div class="empty-state"><?php esc_html_e( 'Add a branch first, then import or set stock per product.', 'ipn' ); ?></div>
	<?php else : ?>
		<div class="toolbar">
			<input type="text" id="ipn-stock-search" placeholder="<?php esc_attr_e( 'Search products…', 'ipn' ); ?>" />
			<select id="ipn-stock-branch-filter">
				<option value="all"><?php esc_html_e( 'All branches', 'ipn' ); ?></option>
				<?php foreach ( $branches as $branch ) : ?>
					<option value="<?php echo esc_attr( $branch->name ); ?>"><?php echo esc_html( $branch->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="table-wrap">
			<table class="data">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
						<th class="num"><?php esc_html_e( 'Total', 'ipn' ); ?></th>
						<th class="num"><?php esc_html_e( 'Reserved', 'ipn' ); ?></th>
						<th class="num"><?php esc_html_e( 'Available', 'ipn' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="ipn-stock-tbody">
					<?php if ( empty( $ipn_stock_rows ) ) : ?>
						<tr>
							<td colspan="6">
								<div class="empty-state"><?php esc_html_e( 'No stock records yet — run a catalogue import.', 'ipn' ); ?></div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $ipn_stock_rows as $row ) : ?>
							<?php
							$product_name = sprintf( /* translators: %d: product ID */ __( 'Product #%d', 'ipn' ), $row->product_id );
							if ( function_exists( 'wc_get_product' ) ) {
								$product = wc_get_product( $row->product_id );
								if ( $product ) {
									$product_name = $product->get_name();
								}
							}
							$available = max( 0, (int) $row->total_stock - (int) $row->reserved_stock );
							?>
							<tr data-ipn-row="1" data-branch="<?php echo esc_attr( $row->branch_name ); ?>" data-search="<?php echo esc_attr( strtolower( $product_name ) ); ?>">
								<td><?php echo esc_html( $product_name ); ?></td>
								<td><?php echo esc_html( $row->branch_name ); ?></td>
								<td class="num"><?php echo esc_html( $row->total_stock ); ?></td>
								<td class="num"><?php echo esc_html( $row->reserved_stock ); ?></td>
								<td class="num"><?php echo esc_html( $available ); ?></td>
								<td>
									<button
										type="button"
										class="btn btn-ghost btn-sm"
										onclick="ipnOpenStockAdjustModal(this)"
										data-product-id="<?php echo esc_attr( $row->product_id ); ?>"
										data-branch-id="<?php echo esc_attr( $row->branch_id ); ?>"
										data-product-name="<?php echo esc_attr( $product_name ); ?>"
										data-branch-name="<?php echo esc_attr( $row->branch_name ); ?>"
										data-total="<?php echo esc_attr( $row->total_stock ); ?>"
									>
										<?php esc_html_e( 'Adjust', 'ipn' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<div class="modal-scrim" id="ipn-stock-modal-scrim">
	<div class="modal">
		<form method="post">
			<?php wp_nonce_field( 'ipn_adjust_stock' ); ?>
			<input type="hidden" name="ipn_adjust_stock" value="1" />
			<input type="hidden" name="product_id" id="sm-product-id" value="" />
			<input type="hidden" name="branch_id" id="sm-branch-id" value="" />

			<div class="modal-head">
				<div class="modal-title"><?php esc_html_e( 'Adjust stock', 'ipn' ); ?></div>
				<button type="button" class="modal-close" onclick="ipnCloseModal('ipn-stock-modal-scrim')">✕</button>
			</div>
			<div class="modal-body">
				<div class="field">
					<label><?php esc_html_e( 'Product', 'ipn' ); ?></label>
					<input type="text" id="sm-product-name" disabled="disabled" />
				</div>
				<div class="field">
					<label><?php esc_html_e( 'Branch', 'ipn' ); ?></label>
					<input type="text" id="sm-branch-name" disabled="disabled" />
				</div>
				<div class="field">
					<label for="sm-total"><?php esc_html_e( 'Total stock on hand', 'ipn' ); ?></label>
					<input type="number" id="sm-total" name="total_stock" min="0" required="required" />
					<div class="hint"><?php esc_html_e( 'This sets the total; reserved stock (from unfulfilled orders) is unchanged and still subtracted to work out what\'s actually available.', 'ipn' ); ?></div>
				</div>
			</div>
			<div class="modal-foot">
				<button type="button" class="btn btn-ghost" onclick="ipnCloseModal('ipn-stock-modal-scrim')"><?php esc_html_e( 'Cancel', 'ipn' ); ?></button>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save', 'ipn' ); ?></button>
			</div>
		</form>
	</div>
</div>
