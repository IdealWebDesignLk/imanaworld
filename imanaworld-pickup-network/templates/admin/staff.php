<?php
defined( 'ABSPATH' ) || exit;

$staff = get_users( array( 'role' => IPN_Roles::ROLE ) );
?>
<div class="wrap ipn-admin">
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
						<?php
						$branch_id = IPN_Roles::get_branch_id( $user->ID );
						$branch    = $branch_id ? IPN_Branch::get( $branch_id ) : null;
						?>
						<tr>
							<td><span style="font-family:var(--font-mono);font-size:12.5px;"><?php echo esc_html( $user->user_login ); ?></span></td>
							<td><?php echo esc_html( $user->display_name ); ?></td>
							<td><?php echo $branch ? esc_html( $branch->name ) : esc_html__( 'Unassigned', 'ipn' ); ?></td>
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
