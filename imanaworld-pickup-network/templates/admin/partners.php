<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var object[] $partners See IPN_Admin::render_partners() — one row per Dokan
 *                          seller, with a real branch_count and ipn_enabled
 *                          flag (true when that vendor has at least one
 *                          branch). There's no separate "partner" data model
 *                          in this plugin — a partner *is* a Dokan vendor
 *                          account, one-to-one, per the confirmed
 *                          architecture (see IPN_Project_Context.md).
 */
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Dokan vendors with IPN mode', 'ipn' ); ?></div>
	</div>

	<?php if ( empty( $partners ) ) : ?>
		<div class="empty-state"><?php esc_html_e( 'No Dokan vendor accounts found yet — create one (e.g. Choppies) in Dokan first, then add its branches from here.', 'ipn' ); ?></div>
	<?php else : ?>
		<div class="table-wrap">
			<table class="data">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Vendor', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'IPN mode', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'Branches', 'ipn' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $partners as $partner ) : ?>
						<tr>
							<td><b><?php echo esc_html( $partner->display_name ); ?></b></td>
							<td>
								<?php if ( $partner->ipn_enabled ) : ?>
									<span class="chip chip-active"><span class="chip-dot"></span><?php esc_html_e( 'On', 'ipn' ); ?></span>
								<?php else : ?>
									<span class="chip chip-inactive"><?php esc_html_e( 'Off — no branches yet', 'ipn' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $partner->branch_count ); ?></td>
							<td>
								<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-branches&vendor_id=' . $partner->vendor_id ) ); ?>">
									<?php echo $partner->branch_count > 0 ? esc_html__( 'Manage branches', 'ipn' ) : esc_html__( 'Add first branch', 'ipn' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="hint"><?php esc_html_e( '"IPN mode" is on automatically as soon as a vendor has at least one branch — there\'s no separate toggle to flip. To onboard a new partner: create their Dokan vendor account, then add their first branch from here.', 'ipn' ); ?></p>
	<?php endif; ?>
</div>
