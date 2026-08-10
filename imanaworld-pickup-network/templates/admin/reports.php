<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var string  $range               '7' | '30' | '90'
 * @var int     $branch_id           0 = all branches
 * @var string  $date_from
 * @var string  $date_to
 * @var object[] $orders_by_branch   See IPN_Reports::orders_by_branch().
 * @var array   $collection_success  See IPN_Reports::collection_success_rate().
 * @var object[] $uncollected        See IPN_Reports::uncollected_orders().
 * @var object[] $prep_time          See IPN_Reports::average_preparation_time().
 * @var object[] $turnaround         See IPN_Reports::collection_turnaround_time().
 * @var object[] $product_performance See IPN_Reports::product_performance_by_branch().
 * @var object[] $branch_sales       See IPN_Reports::branch_sales_performance().
 * @var array   $express_split       See IPN_Reports::express_vs_standard_split().
 */

/**
 * Formats an average-seconds value (or null, meaning no samples yet) as a
 * short duration, reusing the same helper the checkout prep-time copy uses.
 */
$ipn_format_duration = function ( $avg_seconds ) {
	if ( null === $avg_seconds ) {
		return __( '—', 'ipn' );
	}
	return IPN_Checkout::format_minutes( (int) round( $avg_seconds / 60 ) );
};

$ipn_money = function ( $amount ) {
	return number_format_i18n( (float) $amount, 2 );
};

// Merge prep/turnaround/uncollected-count into one row per branch for the combined table below.
$ipn_uncollected_by_branch = array();
foreach ( $uncollected as $row ) {
	$ipn_uncollected_by_branch[ $row->branch_name ] = ( isset( $ipn_uncollected_by_branch[ $row->branch_name ] ) ? $ipn_uncollected_by_branch[ $row->branch_name ] : 0 ) + 1;
}

$ipn_turnaround_by_branch = array();
foreach ( $turnaround as $row ) {
	$ipn_turnaround_by_branch[ $row->branch_id ] = $row;
}
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'IPN operational reporting', 'ipn' ); ?></div>
	</div>

	<form method="get" class="toolbar">
		<input type="hidden" name="page" value="ipn-reports" />
		<select name="range" onchange="this.form.submit()">
			<option value="7" <?php selected( $range, '7' ); ?>><?php esc_html_e( 'Last 7 days', 'ipn' ); ?></option>
			<option value="30" <?php selected( $range, '30' ); ?>><?php esc_html_e( 'Last 30 days', 'ipn' ); ?></option>
			<option value="90" <?php selected( $range, '90' ); ?>><?php esc_html_e( 'Last 90 days', 'ipn' ); ?></option>
		</select>
		<select name="branch_id" id="ipn-report-branch-filter" onchange="this.form.submit()">
			<option value="0"><?php esc_html_e( 'All branches', 'ipn' ); ?></option>
			<?php foreach ( IPN_Branch::get_all() as $branch ) : ?>
				<option value="<?php echo esc_attr( $branch->id ); ?>" <?php selected( $branch_id, $branch->id ); ?>><?php echo esc_html( $branch->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<a
			class="btn btn-secondary"
			style="margin-left:auto;"
			href="<?php echo esc_url( wp_nonce_url( admin_url( add_query_arg( array( 'action' => 'ipn_export_reports', 'range' => $range, 'branch_id' => $branch_id ), 'admin-post.php' ) ), 'ipn_export_reports' ) ); ?>"
		>
			<?php esc_html_e( '⇩ Export CSV', 'ipn' ); ?>
		</a>
	</form>

	<div class="grid cols-2">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Orders by branch', 'ipn' ); ?></div>
			<?php if ( empty( $orders_by_branch ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No branches configured yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<?php foreach ( $orders_by_branch as $row ) : ?>
					<div class="import-log-row">
						<span><?php echo esc_html( $row->branch_name ); ?></span>
						<span><b><?php echo esc_html( $row->count ); ?></b></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Collection success rate', 'ipn' ); ?></div>
			<?php if ( ! $collection_success['total'] ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No collected, cancelled, expired, or disputed orders in this period yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<div class="stat-value" style="margin-bottom:8px;">
					<?php echo esc_html( $collection_success['success_rate'] ); ?>%
				</div>
				<div class="hint">
					<?php
					printf(
						/* translators: 1: collected count, 2: cancelled/expired/disputed count */
						esc_html__( '%1$d collected · %2$d cancelled, expired, or disputed', 'ipn' ),
						(int) $collection_success['collected'],
						(int) $collection_success['lost']
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Product performance by branch — top sellers', 'ipn' ); ?></div>
			<?php if ( empty( $product_performance ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No collected orders in this period yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<?php foreach ( $product_performance as $row ) : ?>
					<div class="import-log-row">
						<span><?php echo esc_html( $row->name ); ?></span>
						<span>
							<?php
							printf(
								/* translators: %d: units sold */
								esc_html__( '%d sold', 'ipn' ),
								(int) $row->qty_sold
							);
							?>
						</span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div class="panel">
			<div class="panel-title">
				<?php
				printf(
					/* translators: %s: number of days in the selected range */
					esc_html__( 'Branch sales performance (BWP, %s days)', 'ipn' ),
					esc_html( $range )
				);
				?>
			</div>
			<?php if ( empty( $branch_sales ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No branches configured yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<?php foreach ( $branch_sales as $row ) : ?>
					<div class="import-log-row">
						<span><?php echo esc_html( $row->branch_name ); ?></span>
						<span>
							<?php
							printf(
								/* translators: 1: revenue amount, 2: order count */
								esc_html__( 'BWP %1$s · %2$d orders', 'ipn' ),
								esc_html( $ipn_money( $row->revenue ) ),
								(int) $row->orders
							);
							?>
						</span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Express vs Standard split', 'ipn' ); ?></div>
			<?php if ( ! $express_split['standard']['count'] && ! $express_split['express']['count'] ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No orders in this period yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<div class="import-log-row">
					<span><?php esc_html_e( 'Standard', 'ipn' ); ?></span>
					<span>
						<?php
						printf(
							/* translators: 1: order count, 2: revenue amount */
							esc_html__( '%1$d orders · BWP %2$s', 'ipn' ),
							(int) $express_split['standard']['count'],
							esc_html( $ipn_money( $express_split['standard']['revenue'] ) )
						);
						?>
					</span>
				</div>
				<div class="import-log-row">
					<span><?php esc_html_e( 'Express', 'ipn' ); ?></span>
					<span>
						<?php
						printf(
							/* translators: 1: order count, 2: revenue amount */
							esc_html__( '%1$d orders · BWP %2$s', 'ipn' ),
							(int) $express_split['express']['count'],
							esc_html( $ipn_money( $express_split['express']['revenue'] ) )
						);
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Uncollected orders (currently Ready)', 'ipn' ); ?></div>
			<?php if ( empty( $uncollected ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'Nothing waiting on collection right now.', 'ipn' ); ?></div>
			<?php else : ?>
				<?php foreach ( $uncollected as $row ) : ?>
					<div class="import-log-row">
						<span>
							<?php echo esc_html( $row->order_number ); ?>
							<div class="hint"><?php echo esc_html( $row->branch_name ); ?></div>
						</span>
						<span><?php echo esc_html( $row->ready_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->ready_at ) : '' ); ?></span>
					</div>
				<?php endforeach; ?>
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
					<th class="num"><?php esc_html_e( 'Uncollected now', 'ipn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $prep_time ) ) : ?>
					<tr>
						<td colspan="4">
							<div class="empty-state"><?php esc_html_e( 'No branches configured yet.', 'ipn' ); ?></div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $prep_time as $row ) : ?>
						<?php $turnaround_row = isset( $ipn_turnaround_by_branch[ $row->branch_id ] ) ? $ipn_turnaround_by_branch[ $row->branch_id ] : null; ?>
						<tr>
							<td><?php echo esc_html( $row->branch_name ); ?></td>
							<td class="num"><?php echo esc_html( $ipn_format_duration( $row->avg_seconds ) ); ?></td>
							<td class="num"><?php echo esc_html( $ipn_format_duration( $turnaround_row ? $turnaround_row->avg_seconds : null ) ); ?></td>
							<td class="num"><?php echo esc_html( isset( $ipn_uncollected_by_branch[ $row->branch_name ] ) ? $ipn_uncollected_by_branch[ $row->branch_name ] : 0 ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
