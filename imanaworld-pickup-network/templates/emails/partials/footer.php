<?php
defined( 'ABSPATH' ) || exit;
/**
 * Shared HTML email shell — closing half. Closes the body cell opened by
 * partials/header.php, renders the ".email-foot" footer row, then closes
 * out the outer wrapper table/body/html tags.
 */
?>
</td>
</tr>
<tr>
<td style="padding:18px 28px 24px;text-align:center;font-size:11px;color:#9a988e;border-top:1px solid #efeee7;">
<?php echo esc_html__( "IMANAWORLD Pickup Network · This is an automated message, please don't reply directly to this email.", 'ipn' ); ?>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
