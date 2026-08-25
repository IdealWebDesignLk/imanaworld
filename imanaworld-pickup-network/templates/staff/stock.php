<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var int         $branch_id
 * @var object|null $branch
 * @var object[]    $stock          Current page of rows from IPN_Branch_Stock::query_products(),
 *                                   scoped to this branch. Each row has ->product_id,
 *                                   ->product_name, ->total_stock, ->reserved_stock.
 * @var int         $stock_total    Total products matching the search, for the pager.
 * @var int         $stock_page     1-based current page.
 * @var int         $stock_per_page
 * @var string      $stock_search
 * @var object[]    $stock_addable  This branch's own products matching the search, for adding.
 * @var string|WP_Error|null $stock_result Outcome of an add/remove posted this request.
 */

$ipn_total_pages = (int) ceil( $stock_total / max( 1, $stock_per_page ) );
?>
<div class="ipn-staff-dashboard">
	<div class="device">
		<section class="screen">
			<div class="topbar">
				<div class="topbar-row">
					<div>
						<div class="topbar-brand"><?php echo $branch ? esc_html( $branch->name ) : esc_html__( 'Branch Staff', 'ipn' ); ?></div>
						<div class="topbar-branch"><?php esc_html_e( 'Branch stock', 'ipn' ); ?></div>
					</div>
				</div>
			</div>

			<?php if ( ! $branch_id ) : ?>
				<div class="content">
					<div class="empty-state"><?php esc_html_e( 'Your account is not yet assigned to a branch. Contact IMANAWORLD admin.', 'ipn' ); ?></div>
				</div>
			<?php else : ?>
				<div class="stock-search">
					<form method="get">
						<?php foreach ( $_GET as $ipn_key => $ipn_value ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
							<?php
							// stock_page is dropped as well as stock_q — a new
							// search starts back at page 1 rather than landing
							// on a page number that no longer exists.
							if ( in_array( $ipn_key, array( 'stock_q', 'stock_page' ), true ) || ! is_scalar( $ipn_value ) ) {
								continue;
							}
							?>
							<input type="hidden" name="<?php echo esc_attr( $ipn_key ); ?>" value="<?php echo esc_attr( wp_unslash( $ipn_value ) ); ?>" />
						<?php endforeach; ?>
						<input type="text" name="stock_q" value="<?php echo esc_attr( $stock_search ); ?>" placeholder="<?php esc_attr_e( 'Search products…', 'ipn' ); ?>" />
					</form>
				</div>

				<div class="content" style="padding-top:0;">
					<?php if ( $stock_result instanceof WP_Error ) : ?>
						<div class="staff-notice staff-notice--error"><?php echo esc_html( $stock_result->get_error_message() ); ?></div>
					<?php elseif ( is_string( $stock_result ) && '' !== $stock_result ) : ?>
						<div class="staff-notice staff-notice--ok"><?php echo esc_html( $stock_result ); ?></div>
					<?php endif; ?>

					<?php if ( ! empty( $stock_addable ) ) : ?>
						<div class="stock-add">
							<div class="stock-add__title"><?php esc_html_e( 'Add a product to this branch', 'ipn' ); ?></div>
							<?php foreach ( $stock_addable as $ipn_candidate ) : ?>
								<div class="stock-add__row">
									<span class="stock-add__name"><?php echo esc_html( $ipn_candidate->name ); ?></span>
									<?php if ( $ipn_candidate->stocked ) : ?>
										<span class="stock-add__done"><?php esc_html_e( 'Already here', 'ipn' ); ?></span>
									<?php else : ?>
										<form method="post" class="stock-add__form">
											<?php wp_nonce_field( 'ipn_staff_stock_add_' . $branch_id, 'ipn_staff_stock_nonce' ); ?>
											<input type="hidden" name="ipn_staff_stock_action" value="add" />
											<input type="hidden" name="product_id" value="<?php echo esc_attr( $ipn_candidate->product_id ); ?>" />
											<input type="number" name="total_stock" min="0" value="0" />
											<button type="submit"><?php esc_html_e( 'Add', 'ipn' ); ?></button>
										</form>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( empty( $stock ) ) : ?>
						<div class="empty-state">
							<?php if ( '' === $stock_search ) : ?>
								<?php esc_html_e( 'No stock records for this branch yet.', 'ipn' ); ?>
							<?php else : ?>
								<?php
								/* translators: %s: the search term that matched nothing. */
								echo esc_html( sprintf( __( 'No products match "%s".', 'ipn' ), $stock_search ) );
								?>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<?php foreach ( $stock as $row ) : ?>
							<?php
							$available = max( 0, (int) $row->total_stock - (int) $row->reserved_stock );
							/* translators: %d: product ID, used when the product has no title. */
							$name = $row->product_name ? $row->product_name : sprintf( __( 'Product #%d', 'ipn' ), $row->product_id );
							?>
							<div class="stock-row">
								<div class="stock-name"><?php echo esc_html( $name ); ?></div>
								<div class="stock-figs">
									<div class="stock-fig"><div class="n"><?php echo esc_html( $row->total_stock ); ?></div><div class="l"><?php esc_html_e( 'On hand', 'ipn' ); ?></div></div>
									<div class="stock-fig reserved"><div class="n"><?php echo esc_html( $row->reserved_stock ); ?></div><div class="l"><?php esc_html_e( 'Reserved', 'ipn' ); ?></div></div>
									<div class="stock-fig available"><div class="n"><?php echo esc_html( $available ); ?></div><div class="l"><?php esc_html_e( 'Available', 'ipn' ); ?></div></div>
								</div>
								<form method="post" class="stock-edit">
									<?php wp_nonce_field( 'ipn_staff_adjust_stock_' . $branch_id, 'ipn_staff_stock_nonce' ); ?>
									<input type="hidden" name="ipn_staff_adjust_stock" value="1" />
									<input type="hidden" name="product_id" value="<?php echo esc_attr( $row->product_id ); ?>" />
									<label for="ipn-stock-<?php echo esc_attr( $row->product_id ); ?>"><?php esc_html_e( 'Adjust total', 'ipn' ); ?></label>
									<input type="number" id="ipn-stock-<?php echo esc_attr( $row->product_id ); ?>" name="total_stock" min="0" value="<?php echo esc_attr( $row->total_stock ); ?>" />
									<button type="submit"><?php esc_html_e( 'Save', 'ipn' ); ?></button>
								</form>
								<form method="post" class="stock-remove" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this product from this branch?', 'ipn' ) ); ?>');">
									<?php wp_nonce_field( 'ipn_staff_stock_delete_' . $branch_id, 'ipn_staff_stock_nonce' ); ?>
									<input type="hidden" name="ipn_staff_stock_action" value="delete" />
									<input type="hidden" name="product_id" value="<?php echo esc_attr( $row->product_id ); ?>" />
									<button type="submit"><?php esc_html_e( 'Remove from branch', 'ipn' ); ?></button>
								</form>
							</div>
						<?php endforeach; ?>

						<?php if ( $ipn_total_pages > 1 ) : ?>
							<div class="stock-pager">
								<?php if ( $stock_page > 1 ) : ?>
									<a href="<?php echo IPN_Staff_Dashboard::screen_url( 'stock', array( 'stock_page' => $stock_page - 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- screen_url() returns an escaped URL. ?>">&larr; <?php esc_html_e( 'Previous', 'ipn' ); ?></a>
								<?php else : ?>
									<span></span>
								<?php endif; ?>
								<span class="stock-pager-pos">
									<?php
									printf(
										/* translators: 1: current page number, 2: total pages */
										esc_html__( 'Page %1$d of %2$d', 'ipn' ),
										(int) $stock_page,
										(int) $ipn_total_pages
									);
									?>
								</span>
								<?php if ( $stock_page < $ipn_total_pages ) : ?>
									<a href="<?php echo IPN_Staff_Dashboard::screen_url( 'stock', array( 'stock_page' => $stock_page + 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- screen_url() returns an escaped URL. ?>"><?php esc_html_e( 'Next', 'ipn' ); ?> &rarr;</a>
								<?php else : ?>
									<span></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			$ipn_active_tab = 'stock';
			include IPN_PLUGIN_DIR . 'templates/staff/partials/tabbar.php';
			?>
		</section>
	</div>
</div>
