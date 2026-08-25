<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var object[]            $partners      One row per Dokan vendor on this page — see
 *                                          IPN_Admin::render_partners(). There's no separate
 *                                          "partner" data model in this plugin: a partner *is*
 *                                          a Dokan vendor account, one-to-one, per the confirmed
 *                                          architecture (see IPN_Project_Context.md).
 * @var int                 $total_vendors
 * @var string              $search
 * @var int                 $page
 * @var int                 $per_page
 * @var string|WP_Error|null $action_result Result of an add/approve/disable posted this request.
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

$ipn_state_labels = array(
	'enabled'  => __( 'Selling', 'ipn' ),
	'pending'  => __( 'Pending approval', 'ipn' ),
	'disabled' => __( 'Disabled', 'ipn' ),
);
?>
<div class="wrap ipn-admin">
	<?php if ( $action_result instanceof WP_Error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $action_result->get_error_message() ); ?></p></div>
	<?php elseif ( is_string( $action_result ) && '' !== $action_result ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $action_result ); ?></p></div>
	<?php endif; ?>

	<div class="section-head">
		<div>
			<div class="section-title"><?php esc_html_e( 'Partners (Dokan vendors)', 'ipn' ); ?></div>
			<?php if ( $total_vendors ) : ?>
				<span class="hint">
					<?php
					printf(
						/* translators: 1: first vendor on this page, 2: last vendor on this page, 3: total matching vendors */
						esc_html__( 'Showing %1$d–%2$d of %3$s vendor accounts', 'ipn' ),
						( ( $page - 1 ) * $per_page ) + 1,
						min( $total_vendors, $page * $per_page ),
						esc_html( number_format_i18n( $total_vendors ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<button type="button" class="btn btn-primary" onclick="ipnOpenModal('ipn-vendor-modal-scrim')">
			<?php esc_html_e( '+ Add vendor', 'ipn' ); ?>
		</button>
	</div>

	<form method="get" class="toolbar">
		<input type="hidden" name="page" value="ipn-partners" />
		<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search vendors…', 'ipn' ); ?>" />
		<button type="submit" class="btn btn-secondary"><?php esc_html_e( 'Search', 'ipn' ); ?></button>
		<?php if ( '' !== $search ) : ?>
			<a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-partners' ) ); ?>"><?php esc_html_e( 'Reset', 'ipn' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( empty( $partners ) ) : ?>
		<div class="empty-state">
			<?php if ( '' !== $search ) : ?>
				<?php esc_html_e( 'No vendor accounts match this search.', 'ipn' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'No Dokan vendor accounts yet — add the partner\'s vendor account here, then add its branches.', 'ipn' ); ?>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="table-wrap">
			<table class="data">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Vendor', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'Selling status', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'IPN mode', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'Branches', 'ipn' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $partners as $partner ) : ?>
						<?php
						$ipn_is_enabled  = ( 'enabled' === $partner->state );
						$ipn_state_label = isset( $ipn_state_labels[ $partner->state ] ) ? $ipn_state_labels[ $partner->state ] : $partner->state;
						?>
						<tr>
							<td>
								<b><?php echo esc_html( $partner->store_name ? $partner->store_name : $partner->display_name ); ?></b>
								<div class="hint"><?php echo esc_html( $partner->email ); ?></div>
							</td>
							<td>
								<span class="chip <?php echo $ipn_is_enabled ? 'chip-active' : ( 'pending' === $partner->state ? 'chip-preparing' : 'chip-inactive' ); ?>">
									<span class="chip-dot"></span><?php echo esc_html( $ipn_state_label ); ?>
								</span>
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
									<form method="post">
										<?php wp_nonce_field( 'ipn_vendor_status' ); ?>
										<input type="hidden" name="ipn_vendor_status" value="1" />
										<input type="hidden" name="vendor_id" value="<?php echo esc_attr( $partner->vendor_id ); ?>" />
										<input type="hidden" name="enable" value="<?php echo $ipn_is_enabled ? '0' : '1'; ?>" />
										<button type="submit" class="btn <?php echo $ipn_is_enabled ? 'btn-danger-outline' : 'btn-secondary'; ?> btn-sm">
											<?php
											if ( $ipn_is_enabled ) {
												esc_html_e( 'Deactivate', 'ipn' );
											} elseif ( 'pending' === $partner->state ) {
												esc_html_e( 'Approve', 'ipn' );
											} else {
												esc_html_e( 'Activate', 'ipn' );
											}
											?>
										</button>
									</form>
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
			<?php esc_html_e( '"IPN mode" is on automatically as soon as a vendor has at least one branch — there is no separate toggle. "Selling status" is Dokan\'s own vendor approval flag: a vendor that is pending or disabled cannot sell, whether or not they have branches.', 'ipn' ); ?>
		</p>
	<?php endif; ?>

	<!-- Nested inside .wrap.ipn-admin deliberately — admin.css's modal rules
	     are scoped as ".ipn-admin .modal-scrim" etc., so a modal placed
	     outside this wrapper renders unstyled and permanently visible
	     instead of as a hidden overlay. -->
	<div class="modal-scrim" id="ipn-vendor-modal-scrim">
		<div class="modal">
			<form method="post">
				<?php wp_nonce_field( 'ipn_add_vendor' ); ?>
				<input type="hidden" name="ipn_add_vendor" value="1" />

				<div class="modal-head">
					<div class="modal-title"><?php esc_html_e( 'Add vendor', 'ipn' ); ?></div>
					<button type="button" class="modal-close" onclick="ipnCloseModal('ipn-vendor-modal-scrim')">✕</button>
				</div>
				<div class="modal-body">
					<div class="field">
						<label for="vm-store-name"><?php esc_html_e( 'Store name', 'ipn' ); ?></label>
						<input type="text" id="vm-store-name" name="store_name" required="required" placeholder="<?php esc_attr_e( 'e.g. Choppies', 'ipn' ); ?>" />
					</div>
					<div class="field-row">
						<div class="field">
							<label for="vm-first-name"><?php esc_html_e( 'First name', 'ipn' ); ?></label>
							<input type="text" id="vm-first-name" name="first_name" />
						</div>
						<div class="field">
							<label for="vm-last-name"><?php esc_html_e( 'Last name', 'ipn' ); ?></label>
							<input type="text" id="vm-last-name" name="last_name" />
						</div>
					</div>
					<div class="field">
						<label for="vm-email"><?php esc_html_e( 'Email address', 'ipn' ); ?></label>
						<input type="email" id="vm-email" name="email" required="required" />
					</div>
					<div class="field-row">
						<div class="field">
							<label for="vm-username"><?php esc_html_e( 'Username', 'ipn' ); ?></label>
							<input type="text" id="vm-username" name="username" placeholder="<?php esc_attr_e( 'Defaults to the email name', 'ipn' ); ?>" />
						</div>
						<div class="field">
							<label for="vm-phone"><?php esc_html_e( 'Phone', 'ipn' ); ?></label>
							<input type="text" id="vm-phone" name="phone" />
						</div>
					</div>
					<div class="field">
						<label for="vm-enable-selling" style="display:flex;align-items:center;gap:8px;">
							<input type="checkbox" id="vm-enable-selling" name="enable_selling" value="1" checked="checked" style="width:auto;" />
							<?php esc_html_e( 'Allow this vendor to sell straight away', 'ipn' ); ?>
						</label>
						<div class="hint"><?php esc_html_e( 'Leave unticked to create the account as Pending approval instead.', 'ipn' ); ?></div>
					</div>
					<p class="hint">
						<?php esc_html_e( 'No password is set here — the vendor is emailed a link to choose their own, the same as any other WordPress account.', 'ipn' ); ?>
					</p>
				</div>
				<div class="modal-foot">
					<button type="button" class="btn btn-ghost" onclick="ipnCloseModal('ipn-vendor-modal-scrim')"><?php esc_html_e( 'Cancel', 'ipn' ); ?></button>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Create vendor', 'ipn' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
