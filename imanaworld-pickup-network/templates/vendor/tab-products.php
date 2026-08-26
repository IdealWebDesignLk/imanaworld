<?php
defined( 'ABSPATH' ) || exit;
/**
 * Vendor dashboard → Products. Bringing a new line into the network, one at a
 * time or from a file.
 *
 * Branch stock can only ever stock a product that already exists; this is
 * where one gets created. Both routes set per-branch stock at the same time,
 * because a Click & Collect product with no branch stock is invisible to
 * customers and would look like the creation had silently failed.
 *
 * @var array    $branches    This vendor's branches.
 * @var object[] $import_runs The last few import runs, for the log below.
 */
?>
<div class="ipn-vd__panel">
	<p class="ipn-vd__hint" style="margin:0;">
		<?php esc_html_e( 'Products created here belong to your store and are stocked at the branch you pick. To change stock later, use the Branch stock tab.', 'ipn' ); ?>
	</p>
</div>

<form method="post" class="ipn-vd__form">
	<?php wp_nonce_field( 'ipn_vendor_create_product' ); ?>
	<input type="hidden" name="ipn_vendor_action" value="create_product" />

	<h3 class="ipn-vd__form-title"><?php esc_html_e( 'Add one product', 'ipn' ); ?></h3>

	<div class="ipn-vd__grid">
		<label class="ipn-vd__field ipn-vd__field--wide">
			<span><?php esc_html_e( 'Product name', 'ipn' ); ?></span>
			<input type="text" name="name" required="required" />
		</label>
		<label class="ipn-vd__field">
			<span><?php esc_html_e( 'Price', 'ipn' ); ?></span>
			<input type="number" name="price" min="0" step="0.01" required="required" />
		</label>
		<label class="ipn-vd__field">
			<span><?php esc_html_e( 'SKU (optional)', 'ipn' ); ?></span>
			<input type="text" name="sku" />
			<small><?php esc_html_e( 'Needed if you plan to update this product by file later.', 'ipn' ); ?></small>
		</label>
		<label class="ipn-vd__field">
			<span><?php esc_html_e( 'Branch', 'ipn' ); ?></span>
			<select name="branch_id" required="required">
				<?php foreach ( $branches as $ipn_b ) : ?>
					<option value="<?php echo esc_attr( $ipn_b->id ); ?>"><?php echo esc_html( $ipn_b->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="ipn-vd__field">
			<span><?php esc_html_e( 'Opening stock at that branch', 'ipn' ); ?></span>
			<input type="number" name="stock" min="0" value="0" />
		</label>
	</div>

	<div class="ipn-vd__form-foot">
		<button type="submit" class="ipn-vd__btn ipn-vd__btn--primary"><?php esc_html_e( 'Create product', 'ipn' ); ?></button>
	</div>
</form>

<form method="post" enctype="multipart/form-data" class="ipn-vd__form">
	<?php wp_nonce_field( 'ipn_vendor_import_products' ); ?>
	<input type="hidden" name="ipn_vendor_action" value="import_products" />

	<h3 class="ipn-vd__form-title"><?php esc_html_e( 'Add products in bulk', 'ipn' ); ?></h3>

	<p class="ipn-vd__hint" style="margin-top:0;">
		<?php esc_html_e( 'Upload a .csv or .xlsx file with these columns: SKU, Product Name, Regular Price, Branch Code, Stock Quantity. Description, Category, Sale Price and Image URL are optional. A row whose SKU already exists updates that product instead of creating a second one.', 'ipn' ); ?>
	</p>

	<p class="ipn-vd__hint">
		<strong><?php esc_html_e( 'Your branch codes:', 'ipn' ); ?></strong>
		<?php
		$ipn_codes = array();
		foreach ( $branches as $ipn_b ) {
			$ipn_codes[] = $ipn_b->code . ' (' . $ipn_b->name . ')';
		}
		echo esc_html( implode( ' · ', $ipn_codes ) );
		?>
	</p>

	<div class="ipn-vd__grid">
		<label class="ipn-vd__field ipn-vd__field--wide">
			<span><?php esc_html_e( 'Catalogue file', 'ipn' ); ?></span>
			<input type="file" name="ipn_vendor_catalogue" accept=".csv,.xlsx" required="required" />
			<small><?php esc_html_e( 'Rows naming a branch or a SKU that is not yours are rejected individually; the rest of the file still runs.', 'ipn' ); ?></small>
		</label>
	</div>

	<div class="ipn-vd__form-foot">
		<button type="submit" class="ipn-vd__btn ipn-vd__btn--primary"><?php esc_html_e( 'Upload and import', 'ipn' ); ?></button>
	</div>
</form>

<?php if ( ! empty( $import_runs ) ) : ?>
	<h4 class="ipn-vd__form-sub"><?php esc_html_e( 'Recent imports', 'ipn' ); ?></h4>
	<div class="ipn-vd__table-wrap">
		<table class="ipn-vd__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'File', 'ipn' ); ?></th>
					<th class="ipn-vd__num"><?php esc_html_e( 'Created', 'ipn' ); ?></th>
					<th class="ipn-vd__num"><?php esc_html_e( 'Updated', 'ipn' ); ?></th>
					<th class="ipn-vd__num"><?php esc_html_e( 'Failed', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'When', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $import_runs as $ipn_run ) : ?>
					<tr>
						<td><?php echo esc_html( $ipn_run->filename ); ?></td>
						<td class="ipn-vd__num"><?php echo esc_html( number_format_i18n( $ipn_run->created_count ) ); ?></td>
						<td class="ipn-vd__num"><?php echo esc_html( number_format_i18n( $ipn_run->updated_count ) ); ?></td>
						<td class="ipn-vd__num"><?php echo esc_html( number_format_i18n( $ipn_run->failed_count ) ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ipn_run->created_at ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="ipn-vd__hint">
		<?php esc_html_e( 'This log covers every import on the site, including ones an administrator ran. Only rows for your own branches and products could have been changed by yours.', 'ipn' ); ?>
	</p>
<?php endif; ?>
