<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var array              $branches
 * @var array              $vendors           WP_User-shaped objects (ID, display_name) — Dokan sellers.
 * @var int|WP_Error|null  $save_result       Result of a branch save posted this request, if any.
 * @var int                $filter_vendor_id  0 = showing all branches across every vendor.
 * @var WP_User|null       $filter_vendor     Set when $filter_vendor_id is, from IPN_Admin::find_vendor().
 * @var int                $default_vendor_id Pre-selected in "+ Add branch" — the filtered vendor, or the
 *                                             only vendor in use so far, or 0 if neither applies.
 */

$ipn_days = array(
	0 => __( 'Sunday', 'ipn' ),
	1 => __( 'Monday', 'ipn' ),
	2 => __( 'Tuesday', 'ipn' ),
	3 => __( 'Wednesday', 'ipn' ),
	4 => __( 'Thursday', 'ipn' ),
	5 => __( 'Friday', 'ipn' ),
	6 => __( 'Saturday', 'ipn' ),
);

/**
 * Summarises a branch's weekly opening hours from ipn_branch_hours into a
 * single readable string for the table — real data, not a mock.
 */
$ipn_branch_hours_summary = function ( $branch_id ) {
	$hours = IPN_Branch::get_hours( $branch_id );

	if ( empty( $hours ) ) {
		return __( 'Not set', 'ipn' );
	}

	$open_days = array_filter( $hours, function ( $h ) {
		return empty( $h->is_closed );
	} );

	if ( empty( $open_days ) ) {
		return __( 'Closed all week', 'ipn' );
	}

	$first = reset( $open_days );

	return sprintf(
		/* translators: 1: open time, 2: close time, 3: number of open days */
		__( '%1$s–%2$s · %3$d/7 days', 'ipn' ),
		substr( (string) $first->open_time, 0, 5 ),
		substr( (string) $first->close_time, 0, 5 ),
		count( $open_days )
	);
};
?>
<div class="wrap ipn-admin">
	<?php if ( $save_result instanceof WP_Error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $save_result->get_error_message() ); ?></p></div>
	<?php elseif ( is_int( $save_result ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Branch saved.', 'ipn' ); ?></p></div>
	<?php endif; ?>

	<div class="section-head">
		<div>
			<div class="section-title">
				<?php if ( $filter_vendor ) : ?>
					<?php
					printf(
						/* translators: %s: partner (Dokan vendor) display name */
						esc_html__( 'Branches — %s', 'ipn' ),
						esc_html( $filter_vendor->display_name )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'All branches', 'ipn' ); ?>
				<?php endif; ?>
			</div>
			<?php if ( $filter_vendor ) : ?>
				<a class="hint" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-partners' ) ); ?>">&larr; <?php esc_html_e( 'All partners', 'ipn' ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( empty( $vendors ) ) : ?>
			<span class="hint"><?php esc_html_e( 'No Dokan vendor accounts found — create the partner\'s vendor account first.', 'ipn' ); ?></span>
		<?php else : ?>
			<button type="button" class="btn btn-primary" onclick="ipnOpenBranchModal(null, <?php echo (int) $default_vendor_id; ?>)">
				<?php esc_html_e( '+ Add branch', 'ipn' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Address', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Hours', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ipn' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $branches ) ) : ?>
					<tr>
						<td colspan="5">
							<div class="empty-state"><?php esc_html_e( 'No branches yet. Add the Choppies pilot stores to get started.', 'ipn' ); ?></div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $branches as $branch ) : ?>
						<?php
						$is_active = 'active' === $branch->status;
						$gps       = ( $branch->latitude && $branch->longitude ) ? $branch->latitude . ', ' . $branch->longitude : '';

						// Lifecycle status ("is this branch part of the network")
						// and today's operating state ("is it open right now")
						// are two different things — a branch shut for the day
						// used to read only as "Active" here, which is what
						// prompted issue #12's follow-up.
						$open_state = IPN_Branch::open_state( $branch->id );
						?>
						<tr>
							<td>
								<b><?php echo esc_html( $branch->name ); ?></b>
								<div class="hint"><?php echo esc_html( $branch->code ); ?></div>
								<?php if ( ! empty( $branch->express_enabled ) ) : ?>
									<span class="chip chip-express" style="margin-top:4px;"><?php esc_html_e( 'Express', 'ipn' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $branch->address ); ?>
								<?php if ( ! empty( $branch->email ) || ! empty( $branch->phone ) ) : ?>
									<div class="hint"><?php echo esc_html( trim( $branch->email . ( $branch->phone ? ' · ' . $branch->phone : '' ), ' ·' ) ); ?></div>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $ipn_branch_hours_summary( $branch->id ) ); ?></td>
							<td>
								<span class="chip <?php echo $is_active ? 'chip-active' : 'chip-inactive'; ?>">
									<span class="chip-dot"></span><?php echo esc_html( ucfirst( $branch->status ) ); ?>
								</span>
								<?php if ( $is_active ) : ?>
									<span class="chip <?php echo $open_state->is_open ? 'chip-ready' : 'chip-disputed'; ?>" style="margin-left:4px;">
										<span class="chip-dot"></span><?php echo esc_html( $open_state->label ); ?>
									</span>
									<div class="hint"><?php echo esc_html( $open_state->detail ); ?></div>
								<?php endif; ?>
								<?php if ( ! empty( $branch->disabled_reason ) ) : ?>
									<div class="hint"><?php echo esc_html( $branch->disabled_reason ); ?></div>
								<?php endif; ?>
							</td>
							<td>
								<button
									type="button"
									class="btn btn-ghost btn-sm"
									onclick="ipnOpenBranchModal(this)"
									data-id="<?php echo esc_attr( $branch->id ); ?>"
									data-name="<?php echo esc_attr( $branch->name ); ?>"
									data-code="<?php echo esc_attr( $branch->code ); ?>"
									data-vendor-id="<?php echo esc_attr( $branch->vendor_id ); ?>"
									data-address="<?php echo esc_attr( $branch->address ); ?>"
									data-phone="<?php echo esc_attr( $branch->phone ); ?>"
									data-email="<?php echo esc_attr( $branch->email ); ?>"
									data-gps="<?php echo esc_attr( $gps ); ?>"
									data-active="<?php echo $is_active ? '1' : '0'; ?>"
									data-reason="<?php echo esc_attr( $branch->disabled_reason ); ?>"
									data-hours="<?php echo esc_attr( wp_json_encode( IPN_Branch::get_hours( $branch->id ) ) ); ?>"
								><?php esc_html_e( 'Edit', 'ipn' ); ?></button>
								<button
									type="button"
									class="btn btn-ghost btn-sm"
									onclick="ipnOpenClosuresModal(this)"
									data-id="<?php echo esc_attr( $branch->id ); ?>"
									data-name="<?php echo esc_attr( $branch->name ); ?>"
									data-closures="<?php echo esc_attr( wp_json_encode( IPN_Branch::get_closures( $branch->id ) ) ); ?>"
								><?php esc_html_e( 'Closures', 'ipn' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Nested inside .wrap.ipn-admin deliberately, along with the
	     closures modal below — admin.css's modal rules are scoped as
	     ".ipn-admin .modal-scrim" etc., so a modal placed outside this
	     wrapper renders unstyled and permanently visible instead of as
	     a hidden overlay. This is a real form now; posts back to this
	     same admin page. -->
<div class="modal-scrim" id="ipn-branch-modal-scrim">
	<div class="modal">
		<form method="post">
			<?php wp_nonce_field( 'ipn_save_branch' ); ?>
			<input type="hidden" name="ipn_save_branch" value="1" />
			<input type="hidden" name="branch_id" id="bm-id" value="" />

			<div class="modal-head">
				<div class="modal-title" id="bm-title" data-add-label="<?php esc_attr_e( 'Add branch', 'ipn' ); ?>" data-edit-label="<?php esc_attr_e( 'Edit branch', 'ipn' ); ?>">
					<?php esc_html_e( 'Add branch', 'ipn' ); ?>
				</div>
				<button type="button" class="modal-close" onclick="ipnCloseModal('ipn-branch-modal-scrim')">✕</button>
			</div>
			<div class="modal-body">
				<div class="field">
					<label for="bm-name"><?php esc_html_e( 'Branch name', 'ipn' ); ?></label>
					<input id="bm-name" name="name" type="text" required="required" placeholder="<?php esc_attr_e( 'e.g. Tlokweng Mall', 'ipn' ); ?>" />
				</div>
				<div class="field-row">
					<div class="field">
						<label for="bm-code"><?php esc_html_e( 'Branch code', 'ipn' ); ?></label>
						<input id="bm-code" name="code" type="text" required="required" placeholder="<?php esc_attr_e( 'e.g. CHP-GBE-01', 'ipn' ); ?>" />
					</div>
					<div class="field">
						<label for="bm-phone"><?php esc_html_e( 'Phone', 'ipn' ); ?></label>
						<input id="bm-phone" name="phone" type="text" placeholder="+267 71 234 567" />
					</div>
				</div>
				<div class="field">
					<label for="bm-vendor"><?php esc_html_e( 'Partner (Dokan vendor account)', 'ipn' ); ?></label>
					<select id="bm-vendor" name="vendor_id" required="required">
						<option value=""><?php esc_html_e( '— Select partner —', 'ipn' ); ?></option>
						<?php foreach ( $vendors as $vendor ) : ?>
							<option value="<?php echo esc_attr( $vendor->ID ); ?>"><?php echo esc_html( $vendor->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="hint"><?php esc_html_e( 'One Dokan vendor account per partner (e.g. Choppies) — every branch belonging to the same partner should use the same one. This is pre-filled when adding another branch to a partner that already has one.', 'ipn' ); ?></div>
				</div>
				<div class="field">
					<label for="bm-address"><?php esc_html_e( 'Address', 'ipn' ); ?></label>
					<input id="bm-address" name="address" type="text" placeholder="<?php esc_attr_e( 'Street, area, Gaborone', 'ipn' ); ?>" />
				</div>
				<div class="field-row">
					<div class="field">
						<label for="bm-email"><?php esc_html_e( 'Contact email', 'ipn' ); ?></label>
						<input id="bm-email" name="email" type="email" placeholder="branch@choppies.co.bw" />
					</div>
					<div class="field">
						<label for="bm-gps"><?php esc_html_e( 'GPS coordinates', 'ipn' ); ?></label>
						<input id="bm-gps" name="gps" type="text" placeholder="-24.65, 25.91" />
					</div>
				</div>
				<div class="field" style="display:flex;align-items:center;gap:10px;">
					<label class="switch"><input id="bm-active" name="active" type="checkbox" value="1" checked="checked" /><span class="switch-track"></span></label>
					<label style="margin:0;"><?php esc_html_e( 'Active — visible in storefront branch selector', 'ipn' ); ?></label>
				</div>
				<div class="field">
					<label for="bm-reason"><?php esc_html_e( 'Disable reason / note (optional)', 'ipn' ); ?></label>
					<input id="bm-reason" name="reason" type="text" placeholder="<?php esc_attr_e( 'e.g. renovation, stock issue', 'ipn' ); ?>" />
				</div>

				<div class="field">
					<label><?php esc_html_e( 'Operating hours', 'ipn' ); ?></label>
					<div class="ipn-hours-editor">
						<?php foreach ( $ipn_days as $day_num => $day_label ) : ?>
							<div class="ipn-hours-row">
								<span class="ipn-hours-day"><?php echo esc_html( $day_label ); ?></span>
								<label class="ipn-hours-closed">
									<input type="checkbox" id="hours-closed-<?php echo esc_attr( $day_num ); ?>" name="hours_closed_<?php echo esc_attr( $day_num ); ?>" value="1" onchange="ipnSyncHoursRow(<?php echo esc_attr( $day_num ); ?>)" />
									<?php esc_html_e( 'Closed', 'ipn' ); ?>
								</label>
								<input type="time" id="hours-open-<?php echo esc_attr( $day_num ); ?>" name="hours_open_<?php echo esc_attr( $day_num ); ?>" />
								<span class="ipn-hours-sep">–</span>
								<input type="time" id="hours-close-<?php echo esc_attr( $day_num ); ?>" name="hours_close_<?php echo esc_attr( $day_num ); ?>" />
							</div>
						<?php endforeach; ?>
					</div>
					<div class="hint" style="margin-top:6px;"><?php esc_html_e( 'One-off holiday closures are managed separately — see the "Closures" button on this branch\'s row.', 'ipn' ); ?></div>
				</div>
			</div>
			<div class="modal-foot">
				<button type="button" class="btn btn-ghost" onclick="ipnCloseModal('ipn-branch-modal-scrim')"><?php esc_html_e( 'Cancel', 'ipn' ); ?></button>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save branch', 'ipn' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Closures modal — kept separate from the branch edit form above so its
     per-closure "Delete" links (rendered client-side from data-closures)
     never end up as forms nested inside that form. -->
<div class="modal-scrim" id="ipn-closures-modal-scrim" data-delete-nonce="<?php echo esc_attr( wp_create_nonce( 'ipn_delete_closure' ) ); ?>">
	<div class="modal">
		<div class="modal-head">
			<div class="modal-title" id="cm-title"><?php esc_html_e( 'Closures', 'ipn' ); ?></div>
			<button type="button" class="modal-close" onclick="ipnCloseModal('ipn-closures-modal-scrim')">✕</button>
		</div>
		<div class="modal-body">
			<div id="cm-list" style="margin-bottom:14px;"></div>

			<form method="post">
				<?php wp_nonce_field( 'ipn_add_closure' ); ?>
				<input type="hidden" name="ipn_add_closure" value="1" />
				<input type="hidden" name="branch_id" id="cm-branch-id" value="" />
				<div class="field">
					<label for="cm-date"><?php esc_html_e( 'Date', 'ipn' ); ?></label>
					<input type="date" id="cm-date" name="closure_date" required="required" style="max-width:220px;" />
				</div>
				<div class="field">
					<label for="cm-note"><?php esc_html_e( 'Note (optional)', 'ipn' ); ?></label>
					<input type="text" id="cm-note" name="note" placeholder="<?php esc_attr_e( 'e.g. Public holiday', 'ipn' ); ?>" />
				</div>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( '+ Add closure date', 'ipn' ); ?></button>
			</form>
		</div>
		<div class="modal-foot">
			<button type="button" class="btn btn-ghost" onclick="ipnCloseModal('ipn-closures-modal-scrim')"><?php esc_html_e( 'Close', 'ipn' ); ?></button>
		</div>
	</div>
</div>
</div>
