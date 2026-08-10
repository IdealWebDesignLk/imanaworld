<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="ipn-staff-dashboard">
	<div class="device">
		<section class="screen login-screen">
			<div class="login-mark">
				<div class="login-mark-badge">IPN</div>
				<div class="login-mark-text">
					<div class="t1"><?php esc_html_e( 'IMANAWORLD Pickup Network', 'ipn' ); ?></div>
					<div class="t2"><?php esc_html_e( 'Branch Staff', 'ipn' ); ?></div>
				</div>
			</div>

			<div class="login-title"><?php esc_html_e( 'Sign in to your branch', 'ipn' ); ?></div>
			<div class="login-sub"><?php esc_html_e( 'Use the staff login your branch manager set up for this Click & Collect counter.', 'ipn' ); ?></div>

			<?php
			wp_login_form(
				array(
					'redirect'       => get_permalink(),
					'label_username' => __( 'Staff username', 'ipn' ),
					'label_password' => __( 'PIN / password', 'ipn' ),
					'label_log_in'   => __( 'Sign in', 'ipn' ),
					'remember'       => false,
				)
			);
			?>

			<div class="login-demo">
				<b><?php esc_html_e( 'Need access?', 'ipn' ); ?></b>
				<?php esc_html_e( 'Ask your branch manager for a staff login — IMANAWORLD admin assigns each account to one branch.', 'ipn' ); ?>
			</div>
		</section>
	</div>
</div>
