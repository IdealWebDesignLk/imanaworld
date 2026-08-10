<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small inline-styled render helpers shared by templates/emails/*.php.
 * Included once per render via partials/header.php (require_once, so it's
 * safe even though render_template() may run several times per request).
 * Colours/spacing are ported 1:1 from the approved mockup's .email-* rules
 * (see "UI for IPN/emails/index.html"), just written as inline styles
 * instead of a <style> block since email clients can't be trusted to
 * support one.
 */

/**
 * Formats an amount as "<CUR> 000.00", matching the mockup's "BWP 222.20" style.
 */
function ipn_email_money( $amount, $currency = '' ) {
	$formatted = number_format( (float) $amount, 2 );
	return $currency ? trim( $currency . ' ' . $formatted ) : $formatted;
}

/**
 * Echoes one label/value <tr> for use inside ipn_email_card_open()/close().
 */
function ipn_email_row( $label, $value, $is_total = false ) {
	$cell_style = $is_total
		? 'padding:10px 0 0;font-size:13px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;font-weight:700;color:#1c1b18;border-top:1px solid #e1dfd7;'
		: 'padding:5px 0;font-size:13px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:#1c1b18;';
	?>
	<tr>
		<td style="<?php echo esc_attr( $cell_style ); ?>text-align:left;"><?php echo esc_html( $label ); ?></td>
		<td style="<?php echo esc_attr( $cell_style ); ?>text-align:right;"><?php echo esc_html( $value ); ?></td>
	</tr>
	<?php
}

/**
 * Opens the ".email-card" equivalent — a sunken rounded box containing a
 * plain <table> of ipn_email_row() rows. Pair with ipn_email_card_close().
 */
function ipn_email_card_open() {
	echo '<div style="background-color:#f6f5f0;border-radius:10px;padding:16px 18px;margin:16px 0;">';
	echo '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">';
}

function ipn_email_card_close() {
	echo '</table></div>';
}

/**
 * Echoes the ".email-otp" green collection-code block.
 */
function ipn_email_otp_box( $code, $note = '' ) {
	?>
	<div style="text-align:center;background-color:#e8f5e9;color:#1b5e20;border-radius:10px;padding:18px;margin:20px 0;">
		<div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;opacity:.85;"><?php esc_html_e( 'Collection code', 'ipn' ); ?></div>
		<div style="font-family:ui-monospace,'SF Mono','Cascadia Code',Menlo,Consolas,monospace;font-size:28px;font-weight:700;letter-spacing:.22em;"><?php echo esc_html( $code ); ?></div>
		<?php if ( $note ) : ?>
			<div style="font-size:11.5px;margin-top:8px;opacity:.8;"><?php echo esc_html( $note ); ?></div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Echoes the ".email-btn" call-to-action button.
 */
function ipn_email_button( $url, $text ) {
	?>
	<a href="<?php echo esc_url( $url ); ?>" style="display:inline-block;background-color:#2e7d32;color:#ffffff;text-decoration:none;font-weight:650;font-size:13.5px;padding:11px 22px;border-radius:9px;margin-top:6px;"><?php echo esc_html( $text ); ?></a>
	<?php
}

/**
 * Echoes a ".email-warn" (amber) or ".email-cancel" (red) notice box.
 */
function ipn_email_notice( $text, $type = 'warn' ) {
	$colors = 'cancel' === $type
		? array(
			'bg' => '#fbeae9',
			'fg' => '#b3261e',
		)
		: array(
			'bg' => '#fbeee0',
			'fg' => '#a8530a',
		);
	?>
	<div style="background-color:<?php echo esc_attr( $colors['bg'] ); ?>;color:<?php echo esc_attr( $colors['fg'] ); ?>;border-radius:10px;padding:12px 16px;font-size:12.5px;font-weight:650;margin:16px 0;">
		<?php echo esc_html( $text ); ?>
	</div>
	<?php
}
