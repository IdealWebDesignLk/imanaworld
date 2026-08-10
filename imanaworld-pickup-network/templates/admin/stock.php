<?php
defined( 'ABSPATH' ) || exit;
/** @var array $branches */

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
									<button type="button" class="btn btn-ghost btn-sm" data-ipn-toast="<?php esc_attr_e( 'Not implemented yet.', 'ipn' ); ?>">
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
