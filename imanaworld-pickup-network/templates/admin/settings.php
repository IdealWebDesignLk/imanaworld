<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var string|null $settings_saved Message to show when a save just succeeded.
 *
 * Every field carries a name= as well as an id=. Until 0.9.0 they carried only
 * id=, so nothing was ever posted and the Save button was a toast that admitted
 * as much — the values on screen could never be changed from this screen.
 */
?>
<div class="wrap ipn-admin">
	<div class="section-title" style="margin-bottom:14px;"><?php esc_html_e( 'Global IPN settings', 'ipn' ); ?></div>

	<?php if ( ! empty( $settings_saved ) ) : ?>
		<div class="notice notice-success" style="margin:0 0 14px;"><p><?php echo esc_html( $settings_saved ); ?></p></div>
	<?php endif; ?>

	<form method="post">
	<?php wp_nonce_field( 'ipn_save_settings', 'ipn_settings_nonce' ); ?>

	<div class="grid cols-2">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Collection & OTP', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Applies to all branches unless overridden per branch.', 'ipn' ); ?></div>

			<div class="field">
				<label for="ipn_otp_expiry_hours"><?php esc_html_e( 'Email OTP expiry (hours)', 'ipn' ); ?></label>
				<input type="number" id="ipn_otp_expiry_hours" name="ipn_otp_expiry_hours" value="<?php echo esc_attr( get_option( 'ipn_otp_expiry_hours', 72 ) ); ?>" />
			</div>
			<div class="field">
				<label for="ipn_max_otp_attempts"><?php esc_html_e( 'Failed attempts before admin alert', 'ipn' ); ?></label>
				<input type="number" id="ipn_max_otp_attempts" name="ipn_max_otp_attempts" value="<?php echo esc_attr( get_option( 'ipn_max_otp_attempts', 3 ) ); ?>" />
			</div>
			<div class="field">
				<label for="ipn_default_express_surcharge"><?php esc_html_e( 'Default Express Collection surcharge (BWP)', 'ipn' ); ?></label>
				<input type="number" id="ipn_default_express_surcharge" name="ipn_default_express_surcharge" value="<?php echo esc_attr( get_option( 'ipn_default_express_surcharge', '15.00' ) ); ?>" step="0.01" />
			</div>
		</div>

		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Uncollected orders workflow', 'ipn' ); ?></div>
			<div class="panel-sub">&nbsp;</div>

			<div class="field">
				<label for="ipn_reminder_after_hours"><?php esc_html_e( 'Reminder sent after (hours)', 'ipn' ); ?></label>
				<input type="number" id="ipn_reminder_after_hours" name="ipn_reminder_after_hours" value="<?php echo esc_attr( get_option( 'ipn_reminder_after_hours', 48 ) ); ?>" />
			</div>
			<div class="field">
				<label for="ipn_collection_window_days"><?php esc_html_e( 'Collection window expires after (days)', 'ipn' ); ?></label>
				<input type="number" id="ipn_collection_window_days" name="ipn_collection_window_days" value="<?php echo esc_attr( get_option( 'ipn_collection_window_days', 5 ) ); ?>" />
			</div>
			<div class="field" style="display:flex;align-items:center;gap:10px;">
				<label class="switch">
					<input type="checkbox" id="ipn_auto_cancel_expired" name="ipn_auto_cancel_expired" value="1" <?php checked( get_option( 'ipn_auto_cancel_expired', '1' ), '1' ); ?> />
					<span class="switch-track"></span>
				</label>
				<label for="ipn_auto_cancel_expired" style="margin:0;"><?php esc_html_e( 'Auto-cancel expired orders', 'ipn' ); ?></label>
			</div>
			<div class="field">
				<label for="ipn_auto_refund_mode"><?php esc_html_e( 'Refund handling', 'ipn' ); ?></label>
				<select id="ipn_auto_refund_mode" name="ipn_auto_refund_mode">
					<option value="auto" <?php selected( get_option( 'ipn_auto_refund_mode', 'manual' ), 'auto' ); ?>><?php esc_html_e( 'Automatic refund on expiry', 'ipn' ); ?></option>
					<option value="manual" <?php selected( get_option( 'ipn_auto_refund_mode', 'manual' ), 'manual' ); ?>><?php esc_html_e( 'Flag for admin review', 'ipn' ); ?></option>
				</select>
			</div>
		</div>
	</div>

	<div class="panel" style="margin-top:16px;">
		<div class="panel-title"><?php esc_html_e( 'Staff dashboard colour', 'ipn' ); ?></div>
		<div class="panel-sub"><?php esc_html_e( 'One colour drives the whole branch staff dashboard: the header, buttons, tabs, active states, links and icons all follow it.', 'ipn' ); ?></div>

		<div class="field" style="max-width:320px;">
			<label for="ipn_staff_primary_color"><?php esc_html_e( 'Dashboard colour', 'ipn' ); ?></label>
			<div style="display:flex;align-items:center;gap:10px;">
				<input type="color" id="ipn_staff_primary_color" name="ipn_staff_primary_color" value="<?php echo esc_attr( IPN_Theme::primary() ); ?>" style="width:52px;height:36px;padding:2px;" />
				<input type="text" id="ipn_staff_primary_color_hex" value="<?php echo esc_attr( IPN_Theme::primary() ); ?>" readonly="readonly" style="width:110px;font-family:monospace;" aria-label="<?php esc_attr_e( 'Selected colour', 'ipn' ); ?>" />
			</div>
			<p class="hint" style="margin-top:8px;">
				<?php
				printf(
					/* translators: %s: the plugin's shipped default colour, e.g. #1b5e20 */
					esc_html__( 'Shipped default is %s. The darker and lighter shades, and the text colour that sits on this one, are worked out from it so they always stay readable together.', 'ipn' ),
					esc_html( IPN_Theme::DEFAULT_PRIMARY )
				);
				?>
			</p>
			<p class="hint">
				<?php esc_html_e( 'Order status badges keep their own colours. Those carry meaning — a disputed order has to read as a problem whatever the brand colour is.', 'ipn' ); ?>
			</p>
		</div>

		<div class="field" style="max-width:520px;">
			<label><?php esc_html_e( 'Preview', 'ipn' ); ?></label>
			<div id="ipn-colour-preview" style="border-radius:10px;overflow:hidden;border:1px solid #dcdcde;">
				<div id="ipn-colour-preview-bar" style="padding:12px 14px;background:<?php echo esc_attr( IPN_Theme::primary() ); ?>;color:<?php echo esc_attr( IPN_Theme::readable_on( IPN_Theme::primary() ) ); ?>;">
					<div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.8;"><?php esc_html_e( 'Branch Staff', 'ipn' ); ?></div>
					<div style="font-size:16px;font-weight:700;"><?php esc_html_e( 'Kandy Mall', 'ipn' ); ?></div>
				</div>
				<div style="padding:12px 14px;background:#fff;">
					<span id="ipn-colour-preview-btn" style="display:inline-block;padding:7px 14px;border-radius:9px;font-size:13px;font-weight:600;background:<?php echo esc_attr( IPN_Theme::primary() ); ?>;color:<?php echo esc_attr( IPN_Theme::readable_on( IPN_Theme::primary() ) ); ?>;"><?php esc_html_e( 'Accept order', 'ipn' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<div style="margin-top:16px;">
		<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save settings', 'ipn' ); ?></button>
	</div>
	</form>

	<script>
	/* Live preview only — the saved value is whatever the colour input posts. */
	( function () {
		var input = document.getElementById( 'ipn_staff_primary_color' );
		if ( ! input ) { return; }

		function readableOn( hex ) {
			var r = parseInt( hex.substr( 1, 2 ), 16 ) / 255,
				g = parseInt( hex.substr( 3, 2 ), 16 ) / 255,
				b = parseInt( hex.substr( 5, 2 ), 16 ) / 255;
			function lin( c ) { return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 ); }
			var l = 0.2126 * lin( r ) + 0.7152 * lin( g ) + 0.0722 * lin( b );
			return ( 1.05 / ( l + 0.05 ) ) >= ( ( l + 0.05 ) / 0.05 ) ? '#ffffff' : '#1c1b18';
		}

		input.addEventListener( 'input', function () {
			var hex = input.value, ink = readableOn( hex ), i;
			var hexField = document.getElementById( 'ipn_staff_primary_color_hex' );
			if ( hexField ) { hexField.value = hex; }
			var targets = [ 'ipn-colour-preview-bar', 'ipn-colour-preview-btn' ];
			for ( i = 0; i < targets.length; i++ ) {
				var el = document.getElementById( targets[ i ] );
				if ( el ) { el.style.background = hex; el.style.color = ink; }
			}
		} );
	}() );
	</script>
</div>
