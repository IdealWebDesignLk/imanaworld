<?php
defined( 'ABSPATH' ) || exit;
/** @var array $branches */

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
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Branches — Choppies', 'ipn' ); ?></div>
		<button type="button" class="btn btn-primary" onclick="ipnOpenBranchModal(null)">
			<?php esc_html_e( '+ Add branch', 'ipn' ); ?>
		</button>
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
								<?php if ( ! empty( $branch->disabled_reason ) ) : ?>
									<div class="hint"><?php echo esc_html( $branch->disabled_reason ); ?></div>
								<?php endif; ?>
							</td>
							<td>
								<button
									type="button"
									class="btn btn-ghost btn-sm"
									onclick="ipnOpenBranchModal(this)"
									data-name="<?php echo esc_attr( $branch->name ); ?>"
									data-code="<?php echo esc_attr( $branch->code ); ?>"
									data-address="<?php echo esc_attr( $branch->address ); ?>"
									data-phone="<?php echo esc_attr( $branch->phone ); ?>"
									data-email="<?php echo esc_attr( $branch->email ); ?>"
									data-gps="<?php echo esc_attr( $gps ); ?>"
									data-active="<?php echo $is_active ? '1' : '0'; ?>"
									data-reason="<?php echo esc_attr( $branch->disabled_reason ); ?>"
								><?php esc_html_e( 'Edit', 'ipn' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<!-- Branch add/edit modal — visual only for now; Save shows a toast, nothing is persisted yet. -->
<div class="modal-scrim" id="ipn-branch-modal-scrim">
	<div class="modal">
		<div class="modal-head">
			<div class="modal-title" id="bm-title" data-add-label="<?php esc_attr_e( 'Add branch', 'ipn' ); ?>" data-edit-label="<?php esc_attr_e( 'Edit branch', 'ipn' ); ?>">
				<?php esc_html_e( 'Add branch', 'ipn' ); ?>
			</div>
			<button type="button" class="modal-close" onclick="ipnCloseModal('ipn-branch-modal-scrim')">✕</button>
		</div>
		<div class="modal-body">
			<div class="field">
				<label for="bm-name"><?php esc_html_e( 'Branch name', 'ipn' ); ?></label>
				<input id="bm-name" type="text" placeholder="<?php esc_attr_e( 'e.g. Tlokweng Mall', 'ipn' ); ?>" />
			</div>
			<div class="field-row">
				<div class="field">
					<label for="bm-code"><?php esc_html_e( 'Branch code', 'ipn' ); ?></label>
					<input id="bm-code" type="text" placeholder="<?php esc_attr_e( 'e.g. TLK01', 'ipn' ); ?>" />
				</div>
				<div class="field">
					<label for="bm-phone"><?php esc_html_e( 'Phone', 'ipn' ); ?></label>
					<input id="bm-phone" type="text" placeholder="+267 71 234 567" />
				</div>
			</div>
			<div class="field">
				<label for="bm-address"><?php esc_html_e( 'Address', 'ipn' ); ?></label>
				<input id="bm-address" type="text" placeholder="<?php esc_attr_e( 'Street, area, Gaborone', 'ipn' ); ?>" />
			</div>
			<div class="field-row">
				<div class="field">
					<label for="bm-email"><?php esc_html_e( 'Contact email', 'ipn' ); ?></label>
					<input id="bm-email" type="text" placeholder="branch@choppies.co.bw" />
				</div>
				<div class="field">
					<label for="bm-gps"><?php esc_html_e( 'GPS coordinates', 'ipn' ); ?></label>
					<input id="bm-gps" type="text" placeholder="-24.65, 25.91" />
				</div>
			</div>
			<div class="field" style="display:flex;align-items:center;gap:10px;">
				<label class="switch"><input id="bm-active" type="checkbox" checked /><span class="switch-track"></span></label>
				<label style="margin:0;"><?php esc_html_e( 'Active — visible in storefront branch selector', 'ipn' ); ?></label>
			</div>
			<div class="field">
				<label for="bm-reason"><?php esc_html_e( 'Disable reason / note (optional)', 'ipn' ); ?></label>
				<input id="bm-reason" type="text" placeholder="<?php esc_attr_e( 'e.g. renovation, stock issue', 'ipn' ); ?>" />
			</div>
		</div>
		<div class="modal-foot">
			<button type="button" class="btn btn-ghost" onclick="ipnCloseModal('ipn-branch-modal-scrim')"><?php esc_html_e( 'Cancel', 'ipn' ); ?></button>
			<button type="button" class="btn btn-primary" data-ipn-toast="<?php esc_attr_e( 'Not implemented yet.', 'ipn' ); ?>" onclick="ipnCloseModal('ipn-branch-modal-scrim')"><?php esc_html_e( 'Save branch', 'ipn' ); ?></button>
		</div>
	</div>
</div>
