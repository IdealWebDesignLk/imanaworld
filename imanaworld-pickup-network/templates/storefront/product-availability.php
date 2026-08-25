<?php
defined( 'ABSPATH' ) || exit;
/**
 * "Available at" panel on the single product page — see
 * IPN_Storefront::render_product_availability().
 *
 * @var WC_Product $product
 * @var int        $product_id
 * @var object[]   $rows               One row per active branch stocking this product,
 *                                      from IPN_Branch_Stock::get_availability_by_branch().
 * @var int        $selected_branch_id 0 when the shopper hasn't chosen a branch yet.
 */

$ipn_in_stock_rows = array_values( array_filter( $rows, function ( $row ) {
	return $row->available > 0;
} ) );
?>
<div class="ipn-product-availability">
	<div class="ipn-product-availability__head">
		<span class="ipn-product-availability__title"><?php esc_html_e( 'Click & Collect availability', 'ipn' ); ?></span>
		<?php if ( $ipn_in_stock_rows ) : ?>
			<span class="ipn-product-availability__count">
				<?php
				printf(
					/* translators: %d: number of branches with this product in stock */
					esc_html( _n( 'In stock at %d branch', 'In stock at %d branches', count( $ipn_in_stock_rows ), 'ipn' ) ),
					count( $ipn_in_stock_rows )
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( empty( $rows ) ) : ?>

		<p class="ipn-product-availability__empty">
			<?php esc_html_e( 'This item is not currently stocked at any collection branch.', 'ipn' ); ?>
		</p>

	<?php else : ?>

		<?php if ( ! $selected_branch_id ) : ?>
			<p class="ipn-product-availability__prompt">
				<?php esc_html_e( 'Choose where you want to collect this item — we check that branch\'s stock before it goes in your cart.', 'ipn' ); ?>
			</p>
		<?php endif; ?>

		<ul class="ipn-availability-list">
			<?php foreach ( $rows as $ipn_row ) : ?>
				<?php
				$ipn_is_selected = ( (int) $ipn_row->branch_id === (int) $selected_branch_id );
				$ipn_available   = (int) $ipn_row->available;
				$ipn_select_url  = add_query_arg(
					array(
						'ipn_branch' => $ipn_row->branch_id,
						'_wpnonce'   => wp_create_nonce( 'ipn_select_branch_' . $ipn_row->branch_id ),
					)
				);
				?>
				<li class="ipn-availability-row<?php echo $ipn_is_selected ? ' ipn-availability-row--selected' : ''; ?>">
					<div class="ipn-availability-row__branch">
						<span class="ipn-availability-row__name"><?php echo esc_html( $ipn_row->branch_name ); ?></span>
						<?php if ( ! empty( $ipn_row->address ) ) : ?>
							<span class="ipn-availability-row__address"><?php echo esc_html( $ipn_row->address ); ?></span>
						<?php endif; ?>
					</div>

					<span class="ipn-status-pill <?php echo $ipn_available > 0 ? 'ipn-status-pill--open' : 'ipn-status-pill--closed'; ?>">
						<?php
						if ( $ipn_available > 0 ) {
							printf(
								/* translators: %d: units available at this branch */
								esc_html( _n( '%d in stock', '%d in stock', $ipn_available, 'ipn' ) ),
								$ipn_available
							);
						} else {
							esc_html_e( 'Out of stock', 'ipn' );
						}
						?>
					</span>

					<?php if ( $ipn_is_selected ) : ?>
						<span class="ipn-availability-row__selected"><?php esc_html_e( 'Your branch', 'ipn' ); ?></span>
					<?php elseif ( $ipn_available > 0 ) : ?>
						<a class="ipn-availability-row__select" href="<?php echo esc_url( $ipn_select_url ); ?>">
							<?php echo $selected_branch_id ? esc_html__( 'Switch here', 'ipn' ) : esc_html__( 'Collect here', 'ipn' ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $selected_branch_id ) : ?>
			<p class="ipn-product-availability__note">
				<?php esc_html_e( 'Switching branch empties your cart, since stock is held per branch.', 'ipn' ); ?>
			</p>
		<?php endif; ?>

	<?php endif; ?>
</div>
