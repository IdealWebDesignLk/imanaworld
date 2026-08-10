<?php
defined( 'ABSPATH' ) || exit;
/**
 * The daily digest is driven by IPN_Uncollected_Workflow, whose
 * run_daily_check() is a Phase 3 build-out stub (see
 * classes/class-ipn-uncollected-workflow.php) — it doesn't produce an
 * expired/uncollected order list yet, so every value here is an honest
 * placeholder rather than fabricated counts.
 */
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( "Today's digest", 'ipn' ); ?></div>
		<button type="button" class="btn btn-secondary" data-ipn-toast="<?php esc_attr_e( 'Digest email preview is not implemented yet.', 'ipn' ); ?>">
			<?php esc_html_e( '✉ Preview digest email', 'ipn' ); ?>
		</button>
	</div>

	<div class="grid cols-3">
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Expired last 24h', 'ipn' ); ?></div>
			<div class="stat-value">—</div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Refunds auto-issued', 'ipn' ); ?></div>
			<div class="stat-value">—</div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Refunds needing review', 'ipn' ); ?></div>
			<div class="stat-value">—</div>
		</div>
	</div>

	<div class="section-head"><div class="section-title"><?php esc_html_e( 'Expired / uncollected orders', 'ipn' ); ?></div></div>
	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Ready since', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Expired', 'ipn' ); ?></th>
					<th class="num"><?php esc_html_e( 'Total (BWP)', 'ipn' ); ?></th>
					<th><?php esc_html_e( 'Refund', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="7">
						<div class="empty-state"><?php esc_html_e( 'The uncollected-orders daily digest is not implemented yet.', 'ipn' ); ?></div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="hint" style="margin-top:10px;">
		<?php
		printf(
			/* translators: %s: link to the IPN settings page */
			esc_html__( 'Once built, this list — and a summary email in the same format — will be sent to the IMANAWORLD admin automatically every morning. Refund handling will follow the mode set in %s.', 'ipn' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=ipn-settings' ) ) . '" style="color:var(--brand-700);font-weight:650;">' . esc_html__( 'IPN settings', 'ipn' ) . '</a>'
		);
		?>
	</div>
</div>
