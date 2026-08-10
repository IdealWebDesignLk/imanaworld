<?php
defined( 'ABSPATH' ) || exit;
/**
 * Shared HTML email shell — opening half. Included by every
 * templates/emails/*.php file before its own body markup, and closed by
 * partials/footer.php. Ported from the "email-frame"/"email-head" rules in
 * the approved mockup ("UI for IPN/emails/index.html") as inline styles,
 * since email clients can't be relied on to load a <style> block.
 *
 * Also pulls in the ipn_email_*() render helpers (functions.php) used by
 * the body templates for card rows, the OTP box, buttons and notices.
 */

require_once __DIR__ . '/functions.php';
?>
<!doctype html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php esc_html_e( 'IMANAWORLD Pickup Network', 'ipn' ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:#f6f5f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background-color:#f6f5f0;padding:32px 16px;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
<tr>
<td style="background-color:#1b5e20;color:#ffffff;padding:20px 28px;text-align:center;">
<span style="display:inline-block;width:26px;height:26px;border-radius:7px;background-color:rgba(255,255,255,.16);text-align:center;line-height:26px;font-size:11px;font-weight:700;vertical-align:middle;">IPN</span>
<span style="font-weight:700;font-size:14px;vertical-align:middle;padding-left:8px;"><?php echo esc_html__( 'Choppies · Click & Collect', 'ipn' ); ?></span>
</td>
</tr>
<tr>
<td style="padding:30px 28px;font-size:14px;line-height:1.65;color:#1c1b18;">
