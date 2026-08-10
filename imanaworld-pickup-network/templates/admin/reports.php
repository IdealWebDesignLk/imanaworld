<?php
defined( 'ABSPATH' ) || exit;
/**
 * IPN_Reports' query methods are Phase 5 build-out stubs that currently
 * return empty arrays (see classes/class-ipn-reports.php), so every panel
 * below renders its real (empty) result as an honest empty state rather
 * than fabricated chart data.
 */
$date_from = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
$date_to   = gmdate( 'Y-m-d' );

$orders_by_branch     = IPN_Reports::orders_by_branch( $date_from, $date_to );
$collection_success   = IPN_Reports::collection_success_rate( $date_from, $date_to );
$product_performance  = IPN_Reports::product_performance_by_branch( $date_from, $date_to );
$branch_sales         = IPN_Reports::branch_sales_performance( $date_from, $date_to );
$turnaround           = IPN_Reports::collection_turnaround_time( $date_from, $date_to );
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'IPN operational reporting', 'ipn' ); ?></div>
	</div>

	<div class="toolbar">
		<select disabled>
			<option><?php esc_html_e( 'Last 7 days', 'ipn' ); ?></option>
		</select>
		<select id="ipn-report-branch-filter" disabled>
			<option value="all"><?php esc_html_e( 'All branches', 'ipn' ); ?></option>
			<?php foreach ( IPN_Branch::get_all() as $branch ) : ?>
				<option value="<?php echo esc_attr( $branch->id ); ?>"><?php echo esc_html( $branch->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="btn btn-secondary" style="margin-left:auto;" data-ipn-toast="<?php esc_attr_e( 'Report export is not implemented yet.', 'ipn' ); ?>">
			<?php esc_html_e( '⇩ Export CSV', 'ipn' ); ?>
		</button>
	</div>

	<div class="grid cols-2">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Orders by branch', 'ipn' ); ?></div>
			<?php if ( empty( $orders_by_branch ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'Not implemented yet.', 'ipn' ); ?></div>
			<?php endif; ?>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Collection success rate', 'ipn' ); ?></div>
			<?php if ( empty( $collection_success ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'Not implemented yet.', 'ipn' ); ?></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Product performance by branch — top sellers', 'ipn' ); ?></div>
			<?php if ( empty( $product_performance ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'Not implemented yet.', 'ipn' ); ?></div>
			<?php endif; ?>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Branch sales performance (BWP, 7 days)', 'ipn' ); ?></div>
			<?php if ( empty( $branch_sales ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'Not implemented yet.', 'ipn' ); ?></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Preparation & collection turnaround', 'ipn' ); ?></div>
	</div>
	<div class="table-wrap">
		<table class="data">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Branch', 'ipn' ); ?></th>
					<th class="num"><?php esc_html_e( 'Avg. prep time', 'ipn' ); ?></th>
					<th class="num"><?php esc_html_e( 'Avg. turnaround', 'ipn' ); ?></th>
					<th class="num"><?php esc_html_e( 'Uncollected (7d)', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $turnaround ) ) : ?>
					<tr>
						<td colspan="4">
							<div class="empty-state"><?php esc_html_e( 'Not implemented yet.', 'ipn' ); ?></div>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
