<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var int         $branch_id
 * @var object|null $branch
 * @var array       $stock  Rows from IPN_Branch_Stock::get_stock_for_branch(). Each row has
 *                          ->product_id, ->total_stock, ->reserved_stock, ->updated_at — this
 *                          is real backing data, unlike the queue/detail screens.
 */

$search = isset( $_GET['stock_q'] ) ? sanitize_text_field( wp_unslash( $_GET['stock_q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$rows = $stock;
if ( '' !== $search ) {
	$rows = array_values(
		array_filter(
			$stock,
			function ( $row ) use ( $search ) {
				$product = wc_get_product( $row->product_id );
				$name    = $product ? $product->get_name() : '';
				return false !== stripos( $name . ' ' . $row->product_id, $search );
			}
		)
	);
}
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
						<?php foreach ( $_GET as $key => $value ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
							<?php
							if ( 'stock_q' === $key || ! is_scalar( $value ) ) {
								continue;
							}
							?>
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( wp_unslash( $value ) ); ?>" />
						<?php endforeach; ?>
						<input type="text" name="stock_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search products…', 'ipn' ); ?>" />
					</form>
					<p class="otp-hint" style="margin-top:8px;">
						<?php esc_html_e( 'Stock updates from this screen are not implemented yet — contact IMANAWORLD admin to adjust stock.', 'ipn' ); ?>
					</p>
				</div>

				<div class="content" style="padding-top:0;">
					<?php if ( empty( $rows ) ) : ?>
						<div class="empty-state">
							<?php if ( empty( $stock ) ) : ?>
								<?php esc_html_e( 'No stock records for this branch yet.', 'ipn' ); ?>
							<?php else : ?>
								<?php
								/* translators: %s: the search term that matched nothing. */
								echo esc_html( sprintf( __( 'No products match "%s".', 'ipn' ), $search ) );
								?>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$available = max( 0, (int) $row->total_stock - (int) $row->reserved_stock );
							$product   = wc_get_product( $row->product_id );
							/* translators: %d: product ID, used when the product itself can't be loaded. */
							$name = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'ipn' ), $row->product_id );
							?>
							<div class="stock-row">
								<div class="stock-name"><?php echo esc_html( $name ); ?></div>
								<div class="stock-figs">
									<div class="stock-fig"><div class="n"><?php echo esc_html( $row->total_stock ); ?></div><div class="l"><?php esc_html_e( 'On hand', 'ipn' ); ?></div></div>
									<div class="stock-fig reserved"><div class="n"><?php echo esc_html( $row->reserved_stock ); ?></div><div class="l"><?php esc_html_e( 'Reserved', 'ipn' ); ?></div></div>
									<div class="stock-fig available"><div class="n"><?php echo esc_html( $available ); ?></div><div class="l"><?php esc_html_e( 'Available', 'ipn' ); ?></div></div>
								</div>
								<div class="stock-edit">
									<label for="ipn-stock-<?php echo esc_attr( $row->product_id ); ?>"><?php esc_html_e( 'Adjust total', 'ipn' ); ?></label>
									<input type="number" id="ipn-stock-<?php echo esc_attr( $row->product_id ); ?>" min="0" value="<?php echo esc_attr( $row->total_stock ); ?>" disabled="disabled" />
									<button type="button" disabled="disabled"><?php esc_html_e( 'Save', 'ipn' ); ?></button>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="tabbar">
				<a class="tab" href="<?php echo IPN_Staff_Dashboard::screen_url( 'queue' ); ?>">
					<span class="tab-ico" aria-hidden="true">&#128203;</span>
					<span class="tab-lbl"><?php esc_html_e( 'Queue', 'ipn' ); ?></span>
				</a>
				<a class="tab active" href="<?php echo IPN_Staff_Dashboard::screen_url( 'stock' ); ?>">
					<span class="tab-ico" aria-hidden="true">&#128230;</span>
					<span class="tab-lbl"><?php esc_html_e( 'Branch stock', 'ipn' ); ?></span>
				</a>
				<a class="tab" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
					<span class="tab-ico" aria-hidden="true">&#8617;</span>
					<span class="tab-lbl"><?php esc_html_e( 'Sign out', 'ipn' ); ?></span>
				</a>
			</div>
		</section>
	</div>
</div>
