<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ipn-admin">
	<div class="section-title" style="margin-bottom:14px;"><?php esc_html_e( 'Global IPN settings', 'ipn' ); ?></div>

	<div class="grid cols-2">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Collection & OTP', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Applies to all branches unless overridden per branch.', 'ipn' ); ?></div>

			<div class="field">
				<label for="ipn_otp_expiry_hours"><?php esc_html_e( 'Email OTP expiry (hours)', 'ipn' ); ?></label>
				<input type="number" id="ipn_otp_expiry_hours" value="<?php echo esc_attr( get_option( 'ipn_otp_expiry_hours', 72 ) ); ?>" />
			</div>
			<div class="field">
				<label for="ipn_max_otp_attempts"><?php esc_html_e( 'Failed attempts before admin alert', 'ipn' ); ?></label>
				<input type="number" id="ipn_max_otp_attempts" value="<?php echo esc_attr( get_option( 'ipn_max_otp_attempts', 3 ) ); ?>" />
			</div>
			<div class="field">
				<label for="ipn_default_express_surcharge"><?php esc_html_e( 'Default Express Collection surcharge (BWP)', 'ipn' ); ?></label>
				<input type="number" id="ipn_default_express_surcharge" value="<?php echo esc_attr( get_option( 'ipn_default_express_surcharge', '15.00' ) ); ?>" step="0.01" />
			</div>
		</div>

		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Uncollected orders workflow', 'ipn' ); ?></div>
			<div class="panel-sub">&nbsp;</div>

			<div class="field">
				<label for="ipn_reminder_after_hours"><?php esc_html_e( 'Reminder sent after (hours)', 'ipn' ); ?></label>
				<input type="number" id="ipn_reminder_after_hours" value="<?php echo esc_attr( get_option( 'ipn_reminder_after_hours', 48 ) ); ?>" />
			</div>
			<div class="field">
				<label for="ipn_collection_window_days"><?php esc_html_e( 'Collection window expires after (days)', 'ipn' ); ?></label>
				<input type="number" id="ipn_collection_window_days" value="<?php echo esc_attr( get_option( 'ipn_collection_window_days', 5 ) ); ?>" />
			</div>
			<div class="field" style="display:flex;align-items:center;gap:10px;">
				<label class="switch">
					<input type="checkbox" id="ipn_auto_cancel_expired" <?php checked( get_option( 'ipn_auto_cancel_expired', '1' ), '1' ); ?> />
					<span class="switch-track"></span>
				</label>
				<label for="ipn_auto_cancel_expired" style="margin:0;"><?php esc_html_e( 'Auto-cancel expired orders', 'ipn' ); ?></label>
			</div>
			<div class="field">
				<label for="ipn_auto_refund_mode"><?php esc_html_e( 'Refund handling', 'ipn' ); ?></label>
				<select id="ipn_auto_refund_mode">
					<option value="auto" <?php selected( get_option( 'ipn_auto_refund_mode', 'manual' ), 'auto' ); ?>><?php esc_html_e( 'Automatic refund on expiry', 'ipn' ); ?></option>
					<option value="manual" <?php selected( get_option( 'ipn_auto_refund_mode', 'manual' ), 'manual' ); ?>><?php esc_html_e( 'Flag for admin review', 'ipn' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<div style="margin-top:16px;">
		<button type="button" class="btn btn-primary" data-ipn-toast="<?php esc_attr_e( 'Settings saving is not implemented yet.', 'ipn' ); ?>">
			<?php esc_html_e( 'Save settings', 'ipn' ); ?>
		</button>
		<p class="hint"><?php esc_html_e( 'Values shown reflect current options; the save action is not wired up yet.', 'ipn' ); ?></p>
	</div>
</div>
