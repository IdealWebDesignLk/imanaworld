<?php
defined( 'ABSPATH' ) || exit;
/**
 * Vendor dashboard → Staff. Add a branch login, move one between branches,
 * or remove one from the network.
 *
 * @var array    $branches This vendor's branches.
 * @var object[] $staff    Staff accounts assigned to any of them.
 */
?>
<div class="ipn-vd__bar">
	<span class="ipn-vd__count">
		<?php
		printf(
			/* translators: %d: number of staff accounts */
			esc_html( _n( '%d staff member', '%d staff members', count( $staff ), 'ipn' ) ),
			count( $staff )
		);
		?>
	</span>
</div>

<?php if ( empty( $staff ) ) : ?>
	<div class="ipn-vd__empty">
		<p><?php esc_html_e( 'No staff accounts yet. Add one below and they will be emailed a link to set their own password.', 'ipn' ); ?></p>
	</div>
<?php else : ?>
	<div class="ipn-vd__table-wrap">
		<table class="ipn-vd__table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Staff member', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $staff as $ipn_person ) : ?>
					<tr>
						<td>
							<b><?php echo esc_html( $ipn_person->display_name ); ?></b>
							<div class="ipn-vd__muted"><?php echo esc_html( $ipn_person->email ); ?></div>
						</td>
						<td>
							<form method="post" class="ipn-vd__inline-form">
								<?php wp_nonce_field( 'ipn_vendor_save_staff' ); ?>
								<input type="hidden" name="ipn_vendor_action" value="save_staff" />
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $ipn_person->user_id ); ?>" />
								<select name="branch_id" onchange="this.form.submit();">
									<?php foreach ( $branches as $ipn_b ) : ?>
										<option value="<?php echo esc_attr( $ipn_b->id ); ?>" <?php selected( (int) $ipn_person->branch_id, (int) $ipn_b->id ); ?>>
											<?php echo esc_html( $ipn_b->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<noscript><button type="submit" class="ipn-vd__btn"><?php esc_html_e( 'Move', 'ipn' ); ?></button></noscript>
							</form>
						</td>
						<td class="ipn-vd__actions">
							<form method="post" class="ipn-vd__inline-form">
								<?php wp_nonce_field( 'ipn_vendor_set_staff_password' ); ?>
								<input type="hidden" name="ipn_vendor_action" value="set_staff_password" />
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $ipn_person->user_id ); ?>" />
								<input
									type="text"
									name="password"
									class="ipn-vd__pw"
									autocomplete="off"
									placeholder="<?php esc_attr_e( 'New password', 'ipn' ); ?>"
								/>
								<button type="submit" class="ipn-vd__btn"><?php esc_html_e( 'Set', 'ipn' ); ?></button>
							</form>
							<form method="post">
								<?php wp_nonce_field( 'ipn_vendor_email_staff_reset' ); ?>
								<input type="hidden" name="ipn_vendor_action" value="email_staff_reset" />
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $ipn_person->user_id ); ?>" />
								<button type="submit" class="ipn-vd__btn"><?php esc_html_e( 'Email reset link', 'ipn' ); ?></button>
							</form>
							<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this staff member from the pickup network?', 'ipn' ) ); ?>');">
								<?php wp_nonce_field( 'ipn_vendor_delete_staff' ); ?>
								<input type="hidden" name="ipn_vendor_action" value="delete_staff" />
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $ipn_person->user_id ); ?>" />
								<button type="submit" class="ipn-vd__btn ipn-vd__btn--danger"><?php esc_html_e( 'Remove', 'ipn' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<p class="ipn-vd__hint">
		<?php esc_html_e( 'Removing a staff member revokes their access to the branch dashboard. Their WordPress account itself is kept, because orders and audit entries may reference it.', 'ipn' ); ?>
	</p>
<?php endif; ?>

<form method="post" class="ipn-vd__form">
	<?php wp_nonce_field( 'ipn_vendor_save_staff' ); ?>
	<input type="hidden" name="ipn_vendor_action" value="save_staff" />

	<h3 class="ipn-vd__form-title"><?php esc_html_e( 'Add staff member', 'ipn' ); ?></h3>

	<div class="ipn-vd__grid">
		<label class="ipn-vd__field">
			<span><?php esc_html_e( 'Full name', 'ipn' ); ?></span>
			<input type="text" name="name" required="required" />
		</label>
		<label class="ipn-vd__field">
			<span><?php esc_html_e( 'Email address', 'ipn' ); ?></span>
			<input type="email" name="email" required="required" />
		</label>
		<label class="ipn-vd__field">
			<span><?php esc_html_e( 'Branch', 'ipn' ); ?></span>
			<select name="branch_id" required="required">
				<?php foreach ( $branches as $ipn_b ) : ?>
					<option value="<?php echo esc_attr( $ipn_b->id ); ?>"><?php echo esc_html( $ipn_b->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="ipn-vd__field ipn-vd__field--wide">
			<span><?php esc_html_e( 'Password (optional)', 'ipn' ); ?></span>
			<input type="text" name="password" autocomplete="off" placeholder="<?php esc_attr_e( 'Leave blank to email them a set-your-own link', 'ipn' ); ?>" />
			<small><?php esc_html_e( 'At least 8 characters. Fill this in to hand credentials over in person — useful when counter staff have no working email. It is shown back to you once and never stored anywhere you can read it again.', 'ipn' ); ?></small>
		</label>
	</div>

	<p class="ipn-vd__hint">
		<?php esc_html_e( 'Leaving the password blank is the safer default: the account gets a random one and only the staff member ever knows it.', 'ipn' ); ?>
	</p>

	<div class="ipn-vd__form-foot">
		<button type="submit" class="ipn-vd__btn ipn-vd__btn--primary"><?php esc_html_e( 'Add staff member', 'ipn' ); ?></button>
	</div>
</form>
