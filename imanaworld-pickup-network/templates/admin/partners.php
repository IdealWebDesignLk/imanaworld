<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var object[] $partners      One row per vendor flagged "Make IPN Partner" — see
 *                               IPN_Admin::render_partners(). There is no separate
 *                               "partner" data model in this plugin: a partner *is*
 *                               a Dokan vendor account, one-to-one, per the confirmed
 *                               architecture (see IPN_Project_Context.md).
 * @var int      $total_vendors
 * @var string   $search
 * @var int      $page
 * @var int      $per_page
 */

$ipn_total_pages = (int) ceil( $total_vendors / max( 1, $per_page ) );

$ipn_page_url = function ( $page_number ) use ( $search ) {
	$args = array( 'page' => 'ipn-partners' );

	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	if ( $page_number > 1 ) {
		$args['paged'] = $page_number;
	}

	return admin_url( 'admin.php?' . http_build_query( $args ) );
};
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div>
			<div class="section-title"><?php esc_html_e( 'IPN Partners', 'ipn' ); ?></div>
			<?php if ( $total_vendors ) : ?>
				<span class="hint">
					<?php
					printf(
						/* translators: 1: first partner on this page, 2: last partner on this page, 3: total partners */
						esc_html__( 'Showing %1$d–%2$d of %3$s partners', 'ipn' ),
						( ( $page - 1 ) * $per_page ) + 1,
						min( $total_vendors, $page * $per_page ),
						esc_html( number_format_i18n( $total_vendors ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<span class="hint">
			<?php esc_html_e( 'A vendor becomes a partner by ticking "Make IPN Partner" on their user profile.', 'ipn' ); ?>
		</span>
	</div>

	<?php if ( $total_vendors || '' !== $search ) : ?>
		<form method="get" class="toolbar">
			<input type="hidden" name="page" value="ipn-partners" />
			<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search partners…', 'ipn' ); ?>" />
			<button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Search', 'ipn' ); ?></button>
			<?php if ( '' !== $search ) : ?>
				<a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-partners' ) ); ?>"><?php esc_html_e( 'Reset', 'ipn' ); ?></a>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php if ( empty( $partners ) ) : ?>
		<div class="empty-state">
			<?php if ( '' !== $search ) : ?>
				<?php esc_html_e( 'No partners match this search.', 'ipn' ); ?>
			<?php else : ?>
				<p><?php esc_html_e( 'No Click & Collect partners yet.', 'ipn' ); ?></p>
				<p>
					<?php
					printf(
						/* translators: %s: link to the WordPress users list */
						wp_kses_post( __( 'To add one: open the vendor\'s account under %s, tick <strong>Make IPN Partner</strong>, and save. They will appear here, and become selectable when you add a branch.', 'ipn' ) ),
						'<a href="' . esc_url( admin_url( 'users.php' ) ) . '">' . esc_html__( 'Users', 'ipn' ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="table-wrap">
			<table class="data">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Partner', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'IPN mode', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'Branches', 'ipn' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $partners as $partner ) : ?>
						<?php $ipn_is_current = ( (int) $partner->vendor_id === (int) IPN_Admin_Context::get_partner_id() ); ?>
						<tr>
							<td>
								<b><?php echo esc_html( $partner->store_name ? $partner->store_name : $partner->display_name ); ?></b>
								<?php if ( $ipn_is_current ) : ?>
									<span class="chip chip-active"><span class="chip-dot"></span><?php esc_html_e( 'Selected', 'ipn' ); ?></span>
								<?php endif; ?>
								<div class="hint"><?php echo esc_html( $partner->email ); ?></div>
							</td>
							<td>
								<?php if ( $partner->ipn_enabled ) : ?>
									<span class="chip chip-active"><span class="chip-dot"></span><?php esc_html_e( 'On', 'ipn' ); ?></span>
								<?php else : ?>
									<span class="chip chip-inactive"><?php esc_html_e( 'Off — no branches yet', 'ipn' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( $partner->branch_count ) ); ?></td>
							<td>
								<div class="row-actions-inline">
									<?php if ( ! $ipn_is_current ) : ?>
										<a class="btn btn-primary btn-sm" href="<?php echo esc_url( IPN_Admin_Context::switch_url( $partner->vendor_id ) ); ?>">
											<?php esc_html_e( 'Work on this partner', 'ipn' ); ?>
										</a>
									<?php endif; ?>
									<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-branches&vendor_id=' . $partner->vendor_id ) ); ?>">
										<?php echo $partner->branch_count > 0 ? esc_html__( 'Manage branches', 'ipn' ) : esc_html__( 'Add first branch', 'ipn' ); ?>
									</a>
									<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_edit_user_link( $partner->vendor_id ) ); ?>">
										<?php esc_html_e( 'Edit account', 'ipn' ); ?>
									</a>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
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

		<p class="hint">
			<?php esc_html_e( 'Choosing "Work on this partner" scopes every other IPN screen — Branches, Staff, Stock, Orders, Disputes, Digest, Audit Trail and Reports — to that partner until you change it.', 'ipn' ); ?>
		</p>
		<p class="hint">
			<?php esc_html_e( '"IPN mode" is on automatically as soon as a partner has at least one branch — there is no separate toggle. To remove a partner from the network, untick "Make IPN Partner" on their user profile; their branches are kept, not deleted.', 'ipn' ); ?>
		</p>
	<?php endif; ?>
</div>
