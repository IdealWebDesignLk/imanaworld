<?php
defined( 'ABSPATH' ) || exit;
/**
 * Staff dashboard → Hours. The one branch setting the people standing in the
 * shop are best placed to keep accurate; everything else about a branch stays
 * with the vendor and the admin.
 *
 * @var int                  $branch_id
 * @var object|null          $branch
 * @var array                $hours        Rows from IPN_Branch::get_hours().
 * @var string|WP_Error|null $hours_result Outcome of a save posted this request.
 */

$ipn_days = array(
	1 => __( 'Monday', 'ipn' ),
	2 => __( 'Tuesday', 'ipn' ),
	3 => __( 'Wednesday', 'ipn' ),
	4 => __( 'Thursday', 'ipn' ),
	5 => __( 'Friday', 'ipn' ),
	6 => __( 'Saturday', 'ipn' ),
	0 => __( 'Sunday', 'ipn' ),
);

$ipn_by_day = array();

foreach ( $hours as $ipn_row ) {
	$ipn_by_day[ (int) $ipn_row->day_of_week ] = $ipn_row;
}

$ipn_state = $branch_id ? IPN_Branch::open_state( $branch_id ) : null;
?>
<div class="ipn-staff-dashboard">
	<div class="device">
		<section class="screen">
			<div class="topbar">
				<div class="topbar-row">
					<div>
						<div class="topbar-brand"><?php echo $branch ? esc_html( $branch->name ) : esc_html__( 'Branch Staff', 'ipn' ); ?></div>
						<div class="topbar-branch"><?php esc_html_e( 'Opening hours', 'ipn' ); ?></div>
					</div>
				</div>
			</div>

			<?php if ( ! $branch_id ) : ?>
				<div class="content">
					<div class="empty-state"><?php esc_html_e( 'Your account is not yet assigned to a branch. Contact IMANAWORLD admin.', 'ipn' ); ?></div>
				</div>
			<?php else : ?>
				<div class="content">
					<?php if ( $hours_result instanceof WP_Error ) : ?>
						<div class="staff-notice staff-notice--error"><?php echo esc_html( $hours_result->get_error_message() ); ?></div>
					<?php elseif ( is_string( $hours_result ) && '' !== $hours_result ) : ?>
						<div class="staff-notice staff-notice--ok"><?php echo esc_html( $hours_result ); ?></div>
					<?php endif; ?>

					<?php if ( $ipn_state ) : ?>
						<div class="hours-now">
							<span class="hours-now__pill <?php echo $ipn_state->is_open ? 'is-open' : 'is-closed'; ?>">
								<?php echo esc_html( $ipn_state->label ); ?>
							</span>
							<span class="hours-now__detail"><?php echo esc_html( $ipn_state->detail ); ?></span>
						</div>
					<?php endif; ?>

					<form method="post" class="hours-form">
						<?php wp_nonce_field( 'ipn_staff_save_hours_' . $branch_id, 'ipn_staff_hours_nonce' ); ?>
						<input type="hidden" name="ipn_staff_save_hours" value="1" />

						<?php foreach ( $ipn_days as $ipn_dow => $ipn_label ) : ?>
							<?php
							$ipn_row   = isset( $ipn_by_day[ $ipn_dow ] ) ? $ipn_by_day[ $ipn_dow ] : null;
							$ipn_shut  = ! $ipn_row || ! empty( $ipn_row->is_closed );
							$ipn_open  = ( $ipn_row && $ipn_row->open_time ) ? substr( $ipn_row->open_time, 0, 5 ) : '08:00';
							$ipn_close = ( $ipn_row && $ipn_row->close_time ) ? substr( $ipn_row->close_time, 0, 5 ) : '19:00';
							?>
							<div class="hours-row">
								<div class="hours-row__day"><?php echo esc_html( $ipn_label ); ?></div>
								<label class="hours-row__closed">
									<input type="checkbox" name="hours_closed_<?php echo esc_attr( $ipn_dow ); ?>" value="1" <?php checked( $ipn_shut ); ?> />
									<?php esc_html_e( 'Closed', 'ipn' ); ?>
								</label>
								<div class="hours-row__times">
									<input type="time" name="hours_open_<?php echo esc_attr( $ipn_dow ); ?>" value="<?php echo esc_attr( $ipn_open ); ?>" />
									<span>–</span>
									<input type="time" name="hours_close_<?php echo esc_attr( $ipn_dow ); ?>" value="<?php echo esc_attr( $ipn_close ); ?>" />
								</div>
							</div>
						<?php endforeach; ?>

						<p class="hours-note">
							<?php esc_html_e( 'Hours are advisory: customers can still order while the branch is closed, and are told preparation starts when it reopens.', 'ipn' ); ?>
						</p>

						<button type="submit" class="hours-save"><?php esc_html_e( 'Save opening hours', 'ipn' ); ?></button>
					</form>
				</div>
			<?php endif; ?>

			<?php
			$ipn_active_tab = 'hours';
			include IPN_PLUGIN_DIR . 'templates/staff/partials/tabbar.php';
			?>
		</section>
	</div>
</div>
