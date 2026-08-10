<?php
defined( 'ABSPATH' ) || exit;

$ipn_branches       = IPN_Branch::get_all();
$ipn_active_count   = count(
	array_filter(
		$ipn_branches,
		function ( $b ) {
			return 'active' === $b->status;
		}
	)
);
$ipn_import_runs    = IPN_CSV_Import::get_recent_runs();
$ipn_audit_entries  = IPN_Audit_Log::query();
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'IMANAWORLD Pickup Network', 'ipn' ); ?></div>
		<span class="hint"><?php esc_html_e( 'Click & Collect fulfilment network — pilot partner: Choppies.', 'ipn' ); ?></span>
	</div>

	<div class="grid cols-4">
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Branches', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( count( $ipn_branches ) ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Active branches', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( $ipn_active_count ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Catalogue imports run', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( count( $ipn_import_runs ) ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Audit events logged', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( count( $ipn_audit_entries ) ); ?></div>
		</div>
	</div>

	<div class="section-head"><div class="section-title"><?php esc_html_e( 'Orders by branch — last 7 days', 'ipn' ); ?></div></div>
	<div class="panel">
		<div class="empty-state"><?php esc_html_e( 'Order volume reporting is not implemented yet.', 'ipn' ); ?></div>
	</div>

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Needs attention', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Expired or disputed orders awaiting action', 'ipn' ); ?></div>
			<div class="empty-state"><?php esc_html_e( 'Order dispute and expiry tracking is not implemented yet — see Disputes & returns.', 'ipn' ); ?></div>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Express vs Standard split', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Volume share, last 7 days', 'ipn' ); ?></div>
			<div class="empty-state"><?php esc_html_e( 'Not implemented yet.', 'ipn' ); ?></div>
		</div>
	</div>

	<div class="section-head"><div class="section-title"><?php esc_html_e( 'Quick links', 'ipn' ); ?></div></div>
	<div class="grid cols-4">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Branches', 'ipn' ); ?></div>
			<p class="panel-sub"><?php esc_html_e( 'Manage Choppies pickup locations.', 'ipn' ); ?></p>
			<a class="btn btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-branches' ) ); ?>"><?php esc_html_e( 'Manage branches', 'ipn' ); ?></a>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Catalogue import', 'ipn' ); ?></div>
			<p class="panel-sub"><?php esc_html_e( 'Upload the Choppies product CSV/Excel file.', 'ipn' ); ?></p>
			<a class="btn btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-import' ) ); ?>"><?php esc_html_e( 'Go to import', 'ipn' ); ?></a>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Reports', 'ipn' ); ?></div>
			<p class="panel-sub"><?php esc_html_e( 'Orders by branch, collection success rate, and more.', 'ipn' ); ?></p>
			<a class="btn btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-reports' ) ); ?>"><?php esc_html_e( 'View reports', 'ipn' ); ?></a>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Audit Trail', 'ipn' ); ?></div>
			<p class="panel-sub"><?php esc_html_e( 'Full operational history for every order.', 'ipn' ); ?></p>
			<a class="btn btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-audit-log' ) ); ?>"><?php esc_html_e( 'View audit log', 'ipn' ); ?></a>
		</div>
	</div>
</div>
