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
	<div class="section-title" style="margin-bottom:14px;"><?php esc_html_e( 'Global IPN settings', 'ipn' ); ?>	<script>
	/* Swatch and HEX box are two views of one value; the HEX box is the one
	   that posts, so a typed code is what gets saved. Preview only — nothing
	   here decides what is stored. */
	( function () {
		function readableOn( hex ) {
			var r = parseInt( hex.substr( 1, 2 ), 16 ) / 255,
				g = parseInt( hex.substr( 3, 2 ), 16 ) / 255,
				b = parseInt( hex.substr( 5, 2 ), 16 ) / 255;
			function lin( c ) { return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 ); }
			var l = 0.2126 * lin( r ) + 0.7152 * lin( g ) + 0.0722 * lin( b );
			return ( 1.05 / ( l + 0.05 ) ) >= ( ( l + 0.05 ) / 0.05 ) ? '#ffffff' : '#1c1b18';
		}

		function tint( hex, amount ) {
			var out = '#', i, c;
			for ( i = 1; i < 7; i += 2 ) {
				c = parseInt( hex.substr( i, 2 ), 16 );
				c = Math.round( c + ( 255 - c ) * amount );
				out += ( '0' + c.toString( 16 ) ).slice( -2 );
			}
			return out;
		}

		function valid( v ) { return /^#[0-9a-fA-F]{6}$/.test( v ); }

		function get( id ) {
			var el = document.getElementById( id + '_hex' );
			return el && valid( el.value ) ? el.value.toLowerCase() : null;
		}

		function paint() {
			var primary   = get( 'ipn_staff_primary_color' ),
				secondary = get( 'ipn_staff_secondary_color' ),
				accent    = get( 'ipn_staff_accent_color' ),
				bar  = document.getElementById( 'ipn-colour-preview-bar' ),
				btn  = document.getElementById( 'ipn-colour-preview-btn' ),
				chip = document.getElementById( 'ipn-colour-preview-chip' );

			if ( bar && primary ) { bar.style.background = primary; bar.style.color = readableOn( primary ); }
			if ( btn && secondary ) { btn.style.background = secondary; btn.style.color = readableOn( secondary ); }
			if ( chip && accent ) { chip.style.background = tint( accent, 0.88 ); chip.style.color = accent; }
		}

		Array.prototype.forEach.call( document.querySelectorAll( '.ipn-colour-swatch' ), function ( swatch ) {
			swatch.addEventListener( 'input', function () {
				var hex = document.getElementById( swatch.getAttribute( 'data-hex-for' ) + '_hex' );
				if ( hex ) { hex.value = swatch.value; }
				paint();
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '.ipn-colour-hex' ), function ( field ) {
			field.addEventListener( 'input', function () {
				var v = field.value.trim();
				if ( v && v.charAt( 0 ) !== '#' ) { v = '#' + v; }
				if ( ! valid( v ) ) { return; }
				var swatch = document.getElementById( field.getAttribute( 'data-swatch-for' ) );
				if ( swatch ) { swatch.value = v.toLowerCase(); }
				paint();
			} );
		} );

		paint();
	}() );
	</script>
</div>

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
		<div class="panel-title"><?php esc_html_e( 'Global theme colours', 'ipn' ); ?></div>
		<div class="panel-sub"><?php esc_html_e( 'Three colours drive the whole branch staff dashboard. Pick them with the swatch or type a HEX code such as #4054B2.', 'ipn' ); ?></div>

		<?php
		$ipn_colour_fields = array(
			array(
				'option' => IPN_Theme::OPTION_PRIMARY,
				'label'  => __( 'Primary colour', 'ipn' ),
				'value'  => IPN_Theme::primary(),
				'shipped' => IPN_Theme::DEFAULT_PRIMARY,
				'role'   => __( 'Surfaces you look at: the dashboard header, the active tab, links and headings.', 'ipn' ),
			),
			array(
				'option' => IPN_Theme::OPTION_SECONDARY,
				'label'  => __( 'Secondary colour', 'ipn' ),
				'value'  => IPN_Theme::secondary(),
				'shipped' => IPN_Theme::DEFAULT_SECONDARY,
				'role'   => __( 'Things you press: buttons, and the marks that lead to them.', 'ipn' ),
			),
			array(
				'option' => IPN_Theme::OPTION_ACCENT,
				'label'  => __( 'Accent colour', 'ipn' ),
				'value'  => IPN_Theme::accent(),
				'shipped' => IPN_Theme::DEFAULT_ACCENT,
				'role'   => __( 'Quiet highlights: soft fills behind badges and notes, and the keyboard focus ring.', 'ipn' ),
			),
		);
		?>

		<?php foreach ( $ipn_colour_fields as $ipn_cf ) : ?>
			<div class="field" style="max-width:460px;">
				<label for="<?php echo esc_attr( $ipn_cf['option'] ); ?>"><?php echo esc_html( $ipn_cf['label'] ); ?></label>
				<div style="display:flex;align-items:center;gap:10px;">
					<input type="color"
						id="<?php echo esc_attr( $ipn_cf['option'] ); ?>"
						class="ipn-colour-swatch"
						data-hex-for="<?php echo esc_attr( $ipn_cf['option'] ); ?>"
						value="<?php echo esc_attr( $ipn_cf['value'] ); ?>"
						style="width:52px;height:36px;padding:2px;" />
					<input type="text"
						id="<?php echo esc_attr( $ipn_cf['option'] ); ?>_hex"
						name="<?php echo esc_attr( $ipn_cf['option'] ); ?>"
						class="ipn-colour-hex"
						data-swatch-for="<?php echo esc_attr( $ipn_cf['option'] ); ?>"
						value="<?php echo esc_attr( $ipn_cf['value'] ); ?>"
						maxlength="7" spellcheck="false"
						style="width:120px;font-family:monospace;text-transform:lowercase;"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: colour field name */ __( '%s HEX code', 'ipn' ), $ipn_cf['label'] ) ); ?>" />
					<span class="hint" style="margin:0;"><?php echo esc_html( $ipn_cf['role'] ); ?></span>
				</div>
				<p class="hint" style="margin-top:6px;">
					<?php
					printf(
						/* translators: %s: the shipped default colour, e.g. #1b5e20 */
						esc_html__( 'Shipped default %s.', 'ipn' ),
						esc_html( $ipn_cf['shipped'] )
					);
					?>
				</p>
			</div>
		<?php endforeach; ?>

		<p class="hint">
			<?php esc_html_e( 'The text that sits on the primary and secondary colours is worked out by contrast rather than chosen, so a pale colour cannot end up with unreadable text on it. Order status badges keep their own colours — those carry meaning, and a disputed order has to read as a problem whatever the brand colours are.', 'ipn' ); ?>
		</p>

		<div class="field" style="max-width:520px;">
			<label><?php esc_html_e( 'Preview', 'ipn' ); ?></label>
			<div id="ipn-colour-preview" style="border-radius:10px;overflow:hidden;border:1px solid #dcdcde;">
				<div id="ipn-colour-preview-bar" style="padding:12px 14px;">
					<div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.8;"><?php esc_html_e( 'Branch Staff', 'ipn' ); ?></div>
					<div style="font-size:16px;font-weight:700;"><?php esc_html_e( 'Kandy Mall', 'ipn' ); ?></div>
				</div>
				<div id="ipn-colour-preview-body" style="padding:12px 14px;background:#fff;display:flex;align-items:center;gap:10px;">
					<span id="ipn-colour-preview-btn" style="display:inline-block;padding:7px 14px;border-radius:9px;font-size:13px;font-weight:600;"><?php esc_html_e( 'Accept order', 'ipn' ); ?></span>
					<span id="ipn-colour-preview-chip" style="display:inline-block;padding:5px 11px;border-radius:20px;font-size:12px;font-weight:600;"><?php esc_html_e( 'Ready', 'ipn' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<div style="margin-top:16px;">
		<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Save settings', 'ipn' ); ?></button>
	</div>
	</form>

</div>
