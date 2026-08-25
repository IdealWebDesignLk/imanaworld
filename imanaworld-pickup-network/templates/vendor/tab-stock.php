<?php
defined( 'ABSPATH' ) || exit;
/**
 * Vendor dashboard → Branch stock. Stock is held per branch, so this screen
 * works one branch at a time rather than showing a combined table: "how many
 * do we have" is only ever a question about a specific shop.
 *
 * @var array    $branches        This vendor's branches.
 * @var int      $stock_branch_id The branch in view.
 * @var object[] $stock_products  Current page of products stocked there.
 * @var int      $stock_total     Total matching products, for the pager.
 * @var string   $stock_search
 * @var int      $stock_page
 * @var object[] $addable         Vendor products matching the search, for adding.
 */

$ipn_pages = (int) ceil( $stock_total / 20 );
?>
<form method="get" class="ipn-vd__bar">
	<?php // Dokan routes the dashboard by path, so the tab has to travel with the query. ?>
	<input type="hidden" name="ipn_tab" value="stock" />
	<label class="ipn-vd__field ipn-vd__field--inline">
		<span><?php esc_html_e( 'Branch', 'ipn' ); ?></span>
		<select name="branch_id" onchange="this.form.submit();">
			<?php foreach ( $branches as $ipn_b ) : ?>
				<option value="<?php echo esc_attr( $ipn_b->id ); ?>" <?php selected( (int) $stock_branch_id, (int) $ipn_b->id ); ?>>
					<?php echo esc_html( $ipn_b->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>
	<label class="ipn-vd__field ipn-vd__field--inline">
		<span><?php esc_html_e( 'Search products', 'ipn' ); ?></span>
		<input type="text" name="s" value="<?php echo esc_attr( $stock_search ); ?>" placeholder="<?php esc_attr_e( 'Product name…', 'ipn' ); ?>" />
	</label>
	<button type="submit" class="ipn-vd__btn"><?php esc_html_e( 'Search', 'ipn' ); ?></button>
</form>

<?php if ( '' !== $stock_search && ! empty( $addable ) ) : ?>
	<div class="ipn-vd__panel">
		<h4 class="ipn-vd__form-sub"><?php esc_html_e( 'Add a product to this branch', 'ipn' ); ?></h4>
		<div class="ipn-vd__table-wrap">
			<table class="ipn-vd__table">
				<tbody>
					<?php foreach ( $addable as $ipn_p ) : ?>
						<tr>
							<td><?php echo esc_html( $ipn_p->name ); ?></td>
							<td>
								<?php if ( $ipn_p->stocked ) : ?>
									<span class="ipn-vd__muted"><?php esc_html_e( 'Already stocked here', 'ipn' ); ?></span>
								<?php else : ?>
									<form method="post" class="ipn-vd__inline-form">
										<?php wp_nonce_field( 'ipn_vendor_save_stock' ); ?>
										<input type="hidden" name="ipn_vendor_action" value="save_stock" />
										<input type="hidden" name="branch_id" value="<?php echo esc_attr( $stock_branch_id ); ?>" />
										<input type="hidden" name="product_id" value="<?php echo esc_attr( $ipn_p->product_id ); ?>" />
										<input type="number" name="total_stock" min="0" value="0" class="ipn-vd__qty" />
										<button type="submit" class="ipn-vd__btn ipn-vd__btn--primary"><?php esc_html_e( 'Add', 'ipn' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>

<?php if ( empty( $stock_products ) ) : ?>
	<div class="ipn-vd__empty">
		<?php if ( '' !== $stock_search ) : ?>
			<p><?php esc_html_e( 'No products stocked at this branch match that search.', 'ipn' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'This branch has no stock yet. Search for one of your products above to add it.', 'ipn' ); ?></p>
		<?php endif; ?>
	</div>
<?php else : ?>
	<div class="ipn-vd__table-wrap">
		<table class="ipn-vd__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'ipn' ); ?></th>
					<th class="ipn-vd__num"><?php esc_html_e( 'Reserved', 'ipn' ); ?></th>
					<th class="ipn-vd__num"><?php esc_html_e( 'Available', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Set total', 'ipn' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $stock_products as $ipn_p ) : ?>
					<?php
					$ipn_total     = (int) $ipn_p->total_stock;
					$ipn_reserved  = (int) $ipn_p->reserved_stock;
					$ipn_available = max( 0, $ipn_total - $ipn_reserved );
					$ipn_name      = $ipn_p->product_name ? $ipn_p->product_name : sprintf( /* translators: %d: product ID */ __( 'Product #%d', 'ipn' ), $ipn_p->product_id );
					?>
					<tr>
						<td><b><?php echo esc_html( $ipn_name ); ?></b></td>
						<td class="ipn-vd__num"><?php echo esc_html( number_format_i18n( $ipn_reserved ) ); ?></td>
						<td class="ipn-vd__num"><?php echo esc_html( number_format_i18n( $ipn_available ) ); ?></td>
						<td>
							<form method="post" class="ipn-vd__inline-form">
								<?php wp_nonce_field( 'ipn_vendor_save_stock' ); ?>
								<input type="hidden" name="ipn_vendor_action" value="save_stock" />
								<input type="hidden" name="branch_id" value="<?php echo esc_attr( $stock_branch_id ); ?>" />
								<input type="hidden" name="product_id" value="<?php echo esc_attr( $ipn_p->product_id ); ?>" />
								<input type="number" name="total_stock" min="0" value="<?php echo esc_attr( $ipn_total ); ?>" class="ipn-vd__qty" />
								<button type="submit" class="ipn-vd__btn"><?php esc_html_e( 'Save', 'ipn' ); ?></button>
							</form>
						</td>
						<td class="ipn-vd__actions">
							<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this product from this branch?', 'ipn' ) ); ?>');">
								<?php wp_nonce_field( 'ipn_vendor_delete_stock' ); ?>
								<input type="hidden" name="ipn_vendor_action" value="delete_stock" />
								<input type="hidden" name="branch_id" value="<?php echo esc_attr( $stock_branch_id ); ?>" />
								<input type="hidden" name="product_id" value="<?php echo esc_attr( $ipn_p->product_id ); ?>" />
								<button type="submit" class="ipn-vd__btn ipn-vd__btn--danger"><?php esc_html_e( 'Remove', 'ipn' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $ipn_pages > 1 ) : ?>
		<div class="ipn-vd__pager">
			<?php if ( $stock_page > 1 ) : ?>
				<a class="ipn-vd__btn" href="<?php echo esc_url( IPN_Vendor_Dashboard::tab_url( 'stock', array( 'branch_id' => $stock_branch_id, 's' => $stock_search, 'paged' => $stock_page - 1 ) ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'ipn' ); ?></a>
			<?php endif; ?>
			<span class="ipn-vd__muted">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'ipn' ),
					(int) $stock_page,
					(int) $ipn_pages
				);
				?>
			</span>
			<?php if ( $stock_page < $ipn_pages ) : ?>
				<a class="ipn-vd__btn" href="<?php echo esc_url( IPN_Vendor_Dashboard::tab_url( 'stock', array( 'branch_id' => $stock_branch_id, 's' => $stock_search, 'paged' => $stock_page + 1 ) ) ); ?>"><?php esc_html_e( 'Next', 'ipn' ); ?> &rarr;</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<p class="ipn-vd__hint">
		<?php esc_html_e( 'Reserved units belong to orders that have been paid for but not collected yet, so they are subtracted from what customers can buy. A product cannot be removed while any of its units are reserved.', 'ipn' ); ?>
	</p>
<?php endif; ?>
