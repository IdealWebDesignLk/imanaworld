<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var array             $branches
 * @var true|WP_Error|null $assign_result Result of a branch assignment posted this request, if any.
 */

$staff = get_users( array( 'role' => IPN_Roles::ROLE ) );
?>
<div class="wrap ipn-admin">
	<?php if ( $assign_result instanceof WP_Error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $assign_result->get_error_message() ); ?></p></div>
	<?php elseif ( true === $assign_result ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Branch assignment saved.', 'ipn' ); ?></p></div>
	<?php endif; ?>

	<?php
	$ipn_staff_url = IPN_Pages::staff_dashboard_url();
	?>
	<?php if ( $ipn_staff_url ) : ?>
		<div class="panel" style="margin-bottom:16px;">
			<div class="panel-title"><?php esc_html_e( 'Staff sign-in link', 'ipn' ); ?></div>
			<div class="panel-sub">
				<?php esc_html_e( 'This is the whole interface for branch staff — they have no wp-admin access. Give them this URL.', 'ipn' ); ?>
			</div>
			<code style="display:block;padding:8px 10px;user-select:all;"><?php echo esc_html( $ipn_staff_url ); ?></code>
			<div class="hint">
				<?php esc_html_e( 'Created automatically by the plugin. Opening it while signed in as an administrator shows a sign-in prompt, not the dashboard — the gate is the IPN Branch Staff role, so preview it in a private window signed in as a staff account.', 'ipn' ); ?>
			</div>
		</div>
	<?php else : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'The branch staff dashboard page could not be created automatically. Create a page containing the [ipn_staff_dashboard] shortcode and it will be picked up here.', 'ipn' ); ?></p></div>
	<?php endif; ?>

	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Branch staff accounts', 'ipn' ); ?></div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'user-new.php?role=' . IPN_Roles::ROLE ) ); ?>">
			<?php esc_html_e( '+ Create staff login', 'ipn' ); ?>
		</a>
	</div>

	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Username', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Display name', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Role', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'ipn' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $staff ) ) : ?>
					<tr>
						<td colspan="6">
							<div class="empty-state"><?php esc_html_e( 'No branch staff accounts yet.', 'ipn' ); ?></div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $staff as $user ) : ?>
						<?php $branch_id = IPN_Roles::get_branch_id( $user->ID ); ?>
						<tr>
							<td><span style="font-family:var(--font-mono);font-size:12.5px;"><?php echo esc_html( $user->user_login ); ?></span></td>
							<td><?php echo esc_html( $user->display_name ); ?></td>
							<td>
								<form method="post" style="display:flex;gap:6px;align-items:center;">
									<?php wp_nonce_field( 'ipn_assign_staff_branch' ); ?>
									<input type="hidden" name="ipn_assign_staff_branch" value="1" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
									<select name="branch_id" onchange="this.form.submit()">
										<option value="0"><?php esc_html_e( 'Unassigned', 'ipn' ); ?></option>
										<?php foreach ( $branches as $branch_option ) : ?>
											<option value="<?php echo esc_attr( $branch_option->id ); ?>" <?php selected( $branch_id, $branch_option->id ); ?>><?php echo esc_html( $branch_option->name ); ?></option>
										<?php endforeach; ?>
									</select>
								</form>
							</td>
							<td><span class="chip chip-inactive"><?php esc_html_e( 'Branch Staff', 'ipn' ); ?></span></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></td>
							<td>
								<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ); ?>">
									<?php esc_html_e( 'Manage login', 'ipn' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
