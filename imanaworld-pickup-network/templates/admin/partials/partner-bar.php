<?php
defined( 'ABSPATH' ) || exit;
/**
 * The "which partner am I working on" bar, rendered above every IPN admin
 * screen by IPN_Admin::view().
 *
 * It carries its own .wrap.ipn-admin wrapper because admin.css scopes every
 * rule under .ipn-admin — a bar rendered outside that wrapper would come out
 * unstyled, which is the same trap that produced the always-visible modals
 * in 0.5.2.
 */

$ipn_ctx_partners = IPN_Vendor::get_partners();
$ipn_ctx_id       = IPN_Admin_Context::get_partner_id();
$ipn_ctx_label    = IPN_Admin_Context::partner_label();

// With no partners at all the bar has nothing to say that the Partners screen
// does not say better, so it stays out of the way.
if ( empty( $ipn_ctx_partners ) ) {
	return;
}
?>
<div class="wrap ipn-admin ipn-context">
	<div class="partner-bar">
		<div class="partner-bar__who">
			<?php if ( $ipn_ctx_id ) : ?>
				<span class="partner-bar__label"><?php esc_html_e( 'Selected Partner:', 'ipn' ); ?></span>
				<b class="partner-bar__name"><?php echo esc_html( $ipn_ctx_label ); ?></b>
			<?php else : ?>
				<span class="partner-bar__label"><?php esc_html_e( 'Selected Partner:', 'ipn' ); ?></span>
				<b class="partner-bar__name partner-bar__name--all"><?php esc_html_e( 'All partners', 'ipn' ); ?></b>
				<span class="partner-bar__hint"><?php esc_html_e( '— every screen is showing the whole network', 'ipn' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="partner-bar__switch">
			<label class="screen-reader-text" for="ipn-partner-switch"><?php esc_html_e( 'Change partner', 'ipn' ); ?></label>
			<select id="ipn-partner-switch" onchange="if(this.value){window.location=this.value;}">
				<option value=""><?php esc_html_e( 'Change partner…', 'ipn' ); ?></option>
				<?php if ( $ipn_ctx_id ) : ?>
					<option value="<?php echo esc_url( IPN_Admin_Context::switch_url( 0 ) ); ?>">
						<?php esc_html_e( 'All partners', 'ipn' ); ?>
					</option>
				<?php endif; ?>
				<?php foreach ( $ipn_ctx_partners as $ipn_ctx_p ) : ?>
					<?php if ( (int) $ipn_ctx_p->ID === (int) $ipn_ctx_id ) { continue; } ?>
					<option value="<?php echo esc_url( IPN_Admin_Context::switch_url( $ipn_ctx_p->ID ) ); ?>">
						<?php echo esc_html( IPN_Admin_Context::partner_label( $ipn_ctx_p->ID ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
</div>
