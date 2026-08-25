<?php
defined( 'ABSPATH' ) || exit;
/**
 * Stock overview. Aggregated one-row-per-product by default, with a
 * per-branch drill-down, and searched/filtered/paged in SQL rather than in
 * the browser — the flat product×branch table this replaced could only ever
 * work at pilot size (issue #7).
 *
 * @var array              $branches         Every branch, for the filter select.
 * @var object[]           $stock_products   Current page of aggregated product rows.
 * @var array              $stock_breakdown  Per-branch rows keyed by product_id, for the whole page.
 * @var int                $filter_branch_id 0 = aggregated across all branches.
 * @var string             $search
 * @var int                $page
 * @var int                $per_page
 * @var int                $total_products
 * @var true|WP_Error|null $adjust_result    Result of a stock adjustment posted this request, if any.
 */

$ipn_total_pages   = (int) ceil( $total_products / max( 1, $per_page ) );
$ipn_filter_branch = null;

foreach ( $branches as $ipn_branch_option ) {
	if ( (int) $ipn_branch_option->id === (int) $filter_branch_id ) {
		$ipn_filter_branch = $ipn_branch_option;
		break;
	}
}

$ipn_page_url = function ( $page_number ) use ( $filter_branch_id, $search ) {
	$args = array( 'page' => 'ipn-stock' );

	if ( $filter_branch_id ) {
		$args['branch_id'] = $filter_branch_id;
	}
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	if ( $page_number > 1 ) {
		$args['paged'] = $page_number;
	}

	return admin_url( 'admin.php?' . http_build_query( $args ) );
};

$ipn_columns = $filter_branch_id ? 5 : 6;
?>
<div class="wrap ipn-admin">
	<?php if ( $adjust_result instanceof WP_Error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $adjust_result->get_error_message() ); ?></p></div>
	<?php elseif ( true === $adjust_result ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Stock updated.', 'ipn' ); ?></p></div>
	<?php endif; ?>

	<div class="section-head">
		<div class="section-title">
			<?php if ( $ipn_filter_branch ) : ?>
				<?php
				printf(
					/* translators: %s: branch name */
					esc_html__( 'Stock overview — %s', 'ipn' ),
					esc_html( $ipn_filter_branch->name )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'Stock overview — all branches', 'ipn' ); ?>
			<?php endif; ?>
		</div>
		<?php if ( $total_products ) : ?>
			<span class="hint">
				<?php
				printf(
					/* translators: 1: first product on this page, 2: last product on this page, 3: total matching products */
					esc_html__( 'Showing %1$d–%2$d of %3$s products', 'ipn' ),
					( ( $page - 1 ) * $per_page ) + 1,
					min( $total_products, $page * $per_page ),
					esc_html( number_format_i18n( $total_products ) )
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( empty( $branches ) ) : ?>
		<div class="empty-state"><?php esc_html_e( 'Add a branch first, then import or set stock per product.', 'ipn' ); ?></div>
	<?php else : ?>
		<form method="get" class="toolbar">
			<input type="hidden" name="page" value="ipn-stock" />
			<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search products…', 'ipn' ); ?>" />
			<select name="branch_id" onchange="this.form.submit();">
				<option value="0"><?php esc_html_e( 'All branches', 'ipn' ); ?></option>
				<?php foreach ( $branches as $ipn_branch_option ) : ?>
					<option value="<?php echo esc_attr( $ipn_branch_option->id ); ?>" <?php selected( (int) $filter_branch_id, (int) $ipn_branch_option->id ); ?>>
						<?php echo esc_html( $ipn_branch_option->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Search', 'ipn' ); ?></button>
			<?php if ( '' !== $search || $filter_branch_id ) : ?>
				<a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-stock' ) ); ?>"><?php esc_html_e( 'Reset', 'ipn' ); ?></a>
			<?php endif; ?>
		</form>

		<div class="table-wrap">
			<table class="data">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'ipn' ); ?></th>
						<?php if ( ! $filter_branch_id ) : ?>
							<th><?php esc_html_e( 'Branches', 'ipn' ); ?></th>
						<?php endif; ?>
						<th class="num"><?php esc_html_e( 'Total', 'ipn' ); ?></th>
						<th class="num"><?php esc_html_e( 'Reserved', 'ipn' ); ?></th>
						<th class="num"><?php esc_html_e( 'Available', 'ipn' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $stock_products ) ) : ?>
						<tr>
							<td colspan="<?php echo (int) $ipn_columns; ?>">
								<div class="empty-state">
									<?php if ( '' !== $search || $filter_branch_id ) : ?>
										<?php esc_html_e( 'No products match this search.', 'ipn' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'No stock records yet — run a catalogue import, or set branch stock on a product from its WooCommerce edit screen.', 'ipn' ); ?>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $stock_products as $ipn_product ) : ?>
							<?php
							$ipn_product_id   = (int) $ipn_product->product_id;
							$ipn_product_name = $ipn_product->product_name ? $ipn_product->product_name : sprintf( /* translators: %d: product ID */ __( 'Product #%d', 'ipn' ), $ipn_product_id );
							$ipn_total        = (int) $ipn_product->total_stock;
							$ipn_reserved     = (int) $ipn_product->reserved_stock;
							$ipn_available    = max( 0, $ipn_total - $ipn_reserved );
							$ipn_rows         = isset( $stock_breakdown[ $ipn_product_id ] ) ? $stock_breakdown[ $ipn_product_id ] : array();
							$ipn_single_row   = ( 1 === count( $ipn_rows ) ) ? $ipn_rows[0] : null;
							$ipn_edit_link    = get_edit_post_link( $ipn_product_id );
							?>
							<tr>
								<td>
									<b><?php echo esc_html( $ipn_product_name ); ?></b>
									<div class="hint">
										<?php if ( $ipn_edit_link ) : ?>
											<a href="<?php echo esc_url( $ipn_edit_link ); ?>">
												<?php
												printf(
													/* translators: %d: product ID */
													esc_html__( 'Product #%d', 'ipn' ),
													$ipn_product_id
												);
												?>
											</a>
										<?php else : ?>
											<?php
											printf(
												/* translators: %d: product ID */
												esc_html__( 'Product #%d', 'ipn' ),
												$ipn_product_id
											);
											?>
										<?php endif; ?>
									</div>
								</td>
								<?php if ( ! $filter_branch_id ) : ?>
									<td>
										<?php
										printf(
											/* translators: %d: number of branches stocking this product */
											esc_html( _n( '%d branch', '%d branches', (int) $ipn_product->branch_count, 'ipn' ) ),
											(int) $ipn_product->branch_count
										);
										?>
									</td>
								<?php endif; ?>
								<td class="num"><?php echo esc_html( number_format_i18n( $ipn_total ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $ipn_reserved ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $ipn_available ) ); ?></td>
								<td>
									<?php if ( $ipn_single_row ) : ?>
										<button
											type="button"
											class="btn btn-ghost btn-sm"
											onclick="ipnOpenStockAdjustModal(this)"
											data-product-id="<?php echo esc_attr( $ipn_product_id ); ?>"
											data-branch-id="<?php echo esc_attr( $ipn_single_row->branch_id ); ?>"
											data-product-name="<?php echo esc_attr( $ipn_product_name ); ?>"
											data-branch-name="<?php echo esc_attr( $ipn_single_row->branch_name ); ?>"
											data-total="<?php echo esc_attr( $ipn_single_row->total_stock ); ?>"
										><?php esc_html_e( 'Adjust', 'ipn' ); ?></button>
									<?php elseif ( $ipn_rows ) : ?>
										<button
											type="button"
											class="btn btn-ghost btn-sm"
											aria-expanded="false"
											aria-controls="ipn-stock-detail-<?php echo esc_attr( $ipn_product_id ); ?>"
											onclick="ipnToggleStockBranches(this, 'ipn-stock-detail-<?php echo esc_attr( $ipn_product_id ); ?>')"
										><?php esc_html_e( 'Per branch', 'ipn' ); ?></button>
									<?php endif; ?>
								</td>
							</tr>
							<?php if ( ! $ipn_single_row && $ipn_rows ) : ?>
								<tr id="ipn-stock-detail-<?php echo esc_attr( $ipn_product_id ); ?>" class="ipn-stock-detail" hidden="hidden">
									<td colspan="<?php echo (int) $ipn_columns; ?>">
										<table class="data ipn-stock-detail-table">
											<thead>
												<tr>
													<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
													<th class="num"><?php esc_html_e( 'Total', 'ipn' ); ?></th>
													<th class="num"><?php esc_html_e( 'Reserved', 'ipn' ); ?></th>
													<th class="num"><?php esc_html_e( 'Available', 'ipn' ); ?></th>
													<th></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $ipn_rows as $ipn_row ) : ?>
													<tr>
														<td>
															<?php echo esc_html( $ipn_row->branch_name ); ?>
															<?php if ( 'active' !== $ipn_row->branch_status ) : ?>
																<span class="chip chip-inactive"><?php echo esc_html( ucfirst( $ipn_row->branch_status ) ); ?></span>
															<?php endif; ?>
														</td>
														<td class="num"><?php echo esc_html( number_format_i18n( (int) $ipn_row->total_stock ) ); ?></td>
														<td class="num"><?php echo esc_html( number_format_i18n( (int) $ipn_row->reserved_stock ) ); ?></td>
														<td class="num"><?php echo esc_html( number_format_i18n( max( 0, (int) $ipn_row->total_stock - (int) $ipn_row->reserved_stock ) ) ); ?></td>
														<td>
															<button
																type="button"
																class="btn btn-ghost btn-sm"
																onclick="ipnOpenStockAdjustModal(this)"
																data-product-id="<?php echo esc_attr( $ipn_product_id ); ?>"
																data-branch-id="<?php echo esc_attr( $ipn_row->branch_id ); ?>"
																data-product-name="<?php echo esc_attr( $ipn_product_name ); ?>"
																data-branch-name="<?php echo esc_attr( $ipn_row->branch_name ); ?>"
																data-total="<?php echo esc_attr( $ipn_row->total_stock ); ?>"
															><?php esc_html_e( 'Adjust', 'ipn' ); ?></button>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $ipn_total_pages > 1 ) : ?>
			<div class="pager">
				<?php if ( $page > 1 ) : ?>
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $ipn_page_url( $page - 1 ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'ipn' ); ?></a>
				<?php endif; ?>
				<span class="hint">
					<?php
					printf(
						/* translators: 1: current page number, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'ipn' ),
						(int) $page,
						(int) $ipn_total_pages
					);
					?>
				</span>
				<?php if ( $page < $ipn_total_pages ) : ?>
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $ipn_page_url( $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'ipn' ); ?> &rarr;</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<!-- Nested inside .wrap.ipn-admin deliberately — admin.css's modal rules
	     are scoped as ".ipn-admin .modal-scrim" etc., so a modal placed
	     outside this wrapper renders completely unstyled and permanently
	     visible instead of as a hidden overlay. -->
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
</div>
