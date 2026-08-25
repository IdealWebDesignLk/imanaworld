<?php
defined( 'ABSPATH' ) || exit;
/**
 * Vendor dashboard → Branches. List, add, edit, delete.
 *
 * The edit form is a separate page state (`?edit=<id>`) rather than a modal:
 * it carries a full week of operating hours, which is far more form than a
 * dialog wants, and a plain page state keeps it usable on a phone.
 *
 * @var array $branches This vendor's branches — the only ones reachable here.
 */

$ipn_edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$ipn_editing = null;

// Only ever match against branches already scoped to this vendor, so an
// ?edit= pointing at somebody else's branch simply finds nothing.
foreach ( $branches as $ipn_b ) {
	if ( (int) $ipn_b->id === $ipn_edit_id ) {
		$ipn_editing = $ipn_b;
		break;
	}
}

$ipn_adding = isset( $_GET['add'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$ipn_form   = $ipn_editing || $ipn_adding;

$ipn_days = array(
	1 => __( 'Monday', 'ipn' ),
	2 => __( 'Tuesday', 'ipn' ),
	3 => __( 'Wednesday', 'ipn' ),
	4 => __( 'Thursday', 'ipn' ),
	5 => __( 'Friday', 'ipn' ),
	6 => __( 'Saturday', 'ipn' ),
	0 => __( 'Sunday', 'ipn' ),
);

$ipn_hours = array();

if ( $ipn_editing ) {
	foreach ( IPN_Branch::get_hours( $ipn_editing->id ) as $ipn_row ) {
		$ipn_hours[ (int) $ipn_row->day_of_week ] = $ipn_row;
	}
}

$ipn_val = function ( $field, $default = '' ) use ( $ipn_editing ) {
	return $ipn_editing && isset( $ipn_editing->$field ) ? $ipn_editing->$field : $default;
};
?>

<?php if ( ! $ipn_form ) : ?>

	<div class="ipn-vd__bar">
		<span class="ipn-vd__count">
			<?php
			printf(
				/* translators: %d: number of branches */
				esc_html( _n( '%d branch', '%d branches', count( $branches ), 'ipn' ) ),
				count( $branches )
			);
			?>
		</span>
		<a class="ipn-vd__btn ipn-vd__btn--primary" href="<?php echo esc_url( IPN_Vendor_Dashboard::tab_url( 'branches', array( 'add' => 1 ) ) ); ?>">
			<?php esc_html_e( '+ Add branch', 'ipn' ); ?>
		</a>
	</div>

	<?php if ( empty( $branches ) ) : ?>
		<div class="ipn-vd__empty">
			<p><?php esc_html_e( 'No branches yet. Add the first pickup point customers can collect from.', 'ipn' ); ?></p>
		</div>
	<?php else : ?>
		<div class="ipn-vd__table-wrap">
			<table class="ipn-vd__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'Address', 'ipn' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ipn' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $branches as $ipn_b ) : ?>
						<?php $ipn_state = IPN_Branch::open_state( $ipn_b->id ); ?>
						<tr>
							<td>
								<b><?php echo esc_html( $ipn_b->name ); ?></b>
								<div class="ipn-vd__muted"><?php echo esc_html( $ipn_b->code ); ?></div>
							</td>
							<td>
								<?php echo esc_html( $ipn_b->address ); ?>
								<?php if ( $ipn_b->phone ) : ?>
									<div class="ipn-vd__muted"><?php echo esc_html( $ipn_b->phone ); ?></div>
								<?php endif; ?>
							</td>
							<td>
								<span class="ipn-vd__pill <?php echo 'active' === $ipn_b->status ? 'is-on' : 'is-off'; ?>">
									<?php echo esc_html( ucfirst( $ipn_b->status ) ); ?>
								</span>
								<div class="ipn-vd__muted"><?php echo esc_html( $ipn_state->label . ' — ' . $ipn_state->detail ); ?></div>
							</td>
							<td class="ipn-vd__actions">
								<a class="ipn-vd__btn" href="<?php echo esc_url( IPN_Vendor_Dashboard::tab_url( 'branches', array( 'edit' => $ipn_b->id ) ) ); ?>">
									<?php esc_html_e( 'Edit', 'ipn' ); ?>
								</a>
								<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this branch? This cannot be undone.', 'ipn' ) ); ?>');">
									<?php wp_nonce_field( 'ipn_vendor_delete_branch' ); ?>
									<input type="hidden" name="ipn_vendor_action" value="delete_branch" />
									<input type="hidden" name="branch_id" value="<?php echo esc_attr( $ipn_b->id ); ?>" />
									<button type="submit" class="ipn-vd__btn ipn-vd__btn--danger"><?php esc_html_e( 'Delete', 'ipn' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="ipn-vd__hint">
			<?php esc_html_e( 'A branch with orders still in progress cannot be deleted — set it to Inactive instead, which stops new orders while the current ones finish.', 'ipn' ); ?>
		</p>
	<?php endif; ?>

<?php else : ?>

	<form method="post" class="ipn-vd__form">
		<?php wp_nonce_field( 'ipn_vendor_save_branch' ); ?>
		<input type="hidden" name="ipn_vendor_action" value="save_branch" />
		<input type="hidden" name="branch_id" value="<?php echo esc_attr( $ipn_editing ? $ipn_editing->id : 0 ); ?>" />

		<h3 class="ipn-vd__form-title">
			<?php echo $ipn_editing ? esc_html__( 'Edit branch', 'ipn' ) : esc_html__( 'Add branch', 'ipn' ); ?>
		</h3>

		<div class="ipn-vd__grid">
			<label class="ipn-vd__field">
				<span><?php esc_html_e( 'Branch name', 'ipn' ); ?></span>
				<input type="text" name="name" required="required" value="<?php echo esc_attr( $ipn_val( 'name' ) ); ?>" />
			</label>
			<label class="ipn-vd__field">
				<span><?php esc_html_e( 'Branch code', 'ipn' ); ?></span>
				<input type="text" name="code" required="required" value="<?php echo esc_attr( $ipn_val( 'code' ) ); ?>" placeholder="CHP-GBE-01" />
			</label>
			<label class="ipn-vd__field ipn-vd__field--wide">
				<span><?php esc_html_e( 'Address', 'ipn' ); ?></span>
				<input type="text" name="address" value="<?php echo esc_attr( $ipn_val( 'address' ) ); ?>" />
			</label>
			<label class="ipn-vd__field">
				<span><?php esc_html_e( 'Phone', 'ipn' ); ?></span>
				<input type="text" name="phone" value="<?php echo esc_attr( $ipn_val( 'phone' ) ); ?>" />
			</label>
			<label class="ipn-vd__field">
				<span><?php esc_html_e( 'Contact email', 'ipn' ); ?></span>
				<input type="email" name="email" value="<?php echo esc_attr( $ipn_val( 'email' ) ); ?>" />
				<small><?php esc_html_e( 'New-order alerts for this branch go here.', 'ipn' ); ?></small>
			</label>
		</div>

		<h4 class="ipn-vd__form-sub"><?php esc_html_e( 'Collection settings', 'ipn' ); ?></h4>
		<div class="ipn-vd__grid">
			<label class="ipn-vd__field">
				<span><?php esc_html_e( 'Standard preparation (minutes)', 'ipn' ); ?></span>
				<input type="number" min="0" name="standard_prep_minutes" value="<?php echo esc_attr( $ipn_val( 'standard_prep_minutes', 1440 ) ); ?>" />
			</label>
			<label class="ipn-vd__field ipn-vd__field--check">
				<input type="checkbox" name="express_enabled" value="1" <?php checked( (bool) $ipn_val( 'express_enabled', 0 ) ); ?> />
				<span><?php esc_html_e( 'Offer Express Collection', 'ipn' ); ?></span>
			</label>
			<label class="ipn-vd__field">
				<span><?php esc_html_e( 'Express surcharge', 'ipn' ); ?></span>
				<input type="number" min="0" step="0.01" name="express_surcharge" value="<?php echo esc_attr( $ipn_val( 'express_surcharge', 0 ) ); ?>" />
			</label>
			<label class="ipn-vd__field">
				<span><?php esc_html_e( 'Express preparation (minutes)', 'ipn' ); ?></span>
				<input type="number" min="0" name="express_prep_minutes" value="<?php echo esc_attr( $ipn_val( 'express_prep_minutes', 60 ) ); ?>" />
			</label>
			<label class="ipn-vd__field ipn-vd__field--check">
				<input type="checkbox" name="active" value="1" <?php checked( 'inactive' !== $ipn_val( 'status', 'active' ) ); ?> />
				<span><?php esc_html_e( 'Branch is active and accepting orders', 'ipn' ); ?></span>
			</label>
		</div>

		<h4 class="ipn-vd__form-sub"><?php esc_html_e( 'Operating hours', 'ipn' ); ?></h4>
		<div class="ipn-vd__hours">
			<?php foreach ( $ipn_days as $ipn_dow => $ipn_day_label ) : ?>
				<?php
				$ipn_row      = isset( $ipn_hours[ $ipn_dow ] ) ? $ipn_hours[ $ipn_dow ] : null;
				$ipn_closed   = $ipn_editing ? ( ! $ipn_row || ! empty( $ipn_row->is_closed ) ) : false;
				$ipn_open_at  = ( $ipn_row && $ipn_row->open_time ) ? substr( $ipn_row->open_time, 0, 5 ) : '08:00';
				$ipn_close_at = ( $ipn_row && $ipn_row->close_time ) ? substr( $ipn_row->close_time, 0, 5 ) : '19:00';
				?>
				<div class="ipn-vd__hours-row">
					<span class="ipn-vd__hours-day"><?php echo esc_html( $ipn_day_label ); ?></span>
					<label class="ipn-vd__hours-closed">
						<input type="checkbox" name="hours_closed_<?php echo esc_attr( $ipn_dow ); ?>" value="1" <?php checked( $ipn_closed ); ?> />
						<?php esc_html_e( 'Closed', 'ipn' ); ?>
					</label>
					<input type="time" name="hours_open_<?php echo esc_attr( $ipn_dow ); ?>" value="<?php echo esc_attr( $ipn_open_at ); ?>" />
					<span class="ipn-vd__hours-sep">–</span>
					<input type="time" name="hours_close_<?php echo esc_attr( $ipn_dow ); ?>" value="<?php echo esc_attr( $ipn_close_at ); ?>" />
				</div>
			<?php endforeach; ?>
		</div>

		<div class="ipn-vd__form-foot">
			<a class="ipn-vd__btn" href="<?php echo esc_url( IPN_Vendor_Dashboard::tab_url( 'branches' ) ); ?>"><?php esc_html_e( 'Cancel', 'ipn' ); ?></a>
			<button type="submit" class="ipn-vd__btn ipn-vd__btn--primary"><?php esc_html_e( 'Save branch', 'ipn' ); ?></button>
		</div>
	</form>

<?php endif; ?>
