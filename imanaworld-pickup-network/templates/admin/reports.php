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
 * @var object[] $revenue_trend      See IPN_Reports::revenue_trend() — one row per day in range.
 * @var float   $total_revenue       Sum of $branch_sales revenue (issue #52).
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

$ipn_orders_total_count = array_sum( wp_list_pluck( $orders_by_branch, 'count' ) );
$ipn_max_branch_orders  = $ipn_orders_total_count ? max( wp_list_pluck( $orders_by_branch, 'count' ) ) : 0;

$ipn_max_branch_revenue = $branch_sales ? max( wp_list_pluck( $branch_sales, 'revenue' ) ) : 0;

$ipn_express_total_count = $express_split['standard']['count'] + $express_split['express']['count'];

/**
 * Renders the Express/Standard split as an SVG donut (order-count share) —
 * a plain two-segment ring built from stroke-dasharray, no charting library.
 */
$ipn_render_split_donut = function ( array $split, $total ) {
	if ( ! $total ) {
		return;
	}

	$radius = 36;
	$circumference = 2 * M_PI * $radius;
	$standard_pct = $split['standard']['count'] / $total;
	$standard_len = $standard_pct * $circumference;
	?>
	<svg class="donut-svg" width="96" height="96" viewBox="0 0 96 96">
		<circle cx="48" cy="48" r="<?php echo esc_attr( $radius ); ?>" fill="none" stroke="var(--express)" stroke-width="14" />
		<circle
			cx="48" cy="48" r="<?php echo esc_attr( $radius ); ?>" fill="none" stroke="var(--brand-600)" stroke-width="14"
			stroke-dasharray="<?php echo esc_attr( round( $standard_len, 2 ) . ' ' . round( $circumference, 2 ) ); ?>"
			transform="rotate(-90 48 48)"
		/>
	</svg>
	<?php
};

/**
 * Renders $rows (date/revenue objects, see IPN_Reports::revenue_trend()) as
 * a filled SVG line chart. Point count isn't fixed — the 90-day range
 * produces as many points as the 7-day one, just closer together.
 */
$ipn_render_revenue_trend = function ( array $rows, $money_formatter ) {
	$count = count( $rows );

	$width  = 600;
	$height = 140;
	$pad_x  = 4;
	$pad_top = 16;
	$pad_bottom = 24;
	$plot_w = $width - ( $pad_x * 2 );
	$plot_h = $height - $pad_top - $pad_bottom;

	// $rows is normally never empty — revenue_trend() zero-fills one entry
	// per day in range — but an empty array is handled the same as an
	// all-zero one rather than rendering nothing, in case date_from ever
	// ends up after date_to.
	$max = $count ? max( wp_list_pluck( $rows, 'revenue' ) ) : 0;

	if ( ! $max ) {
		?>
		<svg class="trend-chart" viewBox="0 0 <?php echo esc_attr( $width ); ?> <?php echo esc_attr( $height ); ?>" preserveAspectRatio="none">
			<text class="trend-chart-empty" x="<?php echo esc_attr( $width / 2 ); ?>" y="<?php echo esc_attr( $height / 2 ); ?>"><?php esc_html_e( 'No revenue in this period yet.', 'ipn' ); ?></text>
		</svg>
		<?php
		return;
	}

	$points = array();

	foreach ( $rows as $i => $row ) {
		$x = $count > 1 ? $pad_x + ( $i / ( $count - 1 ) ) * $plot_w : $pad_x + ( $plot_w / 2 );
		$y = $pad_top + $plot_h - ( ( $row->revenue / $max ) * $plot_h );
		$points[] = array( round( $x, 1 ), round( $y, 1 ) );
	}

	$line_points = implode( ' ', array_map( function ( $p ) {
		return $p[0] . ',' . $p[1];
	}, $points ) );

	$area_points = $line_points . ' ' . ( $width - $pad_x ) . ',' . ( $height - $pad_bottom ) . ' ' . $pad_x . ',' . ( $height - $pad_bottom );

	$first_date = date_i18n( 'd M', strtotime( $rows[0]->date ) );
	$last_date  = date_i18n( 'd M', strtotime( $rows[ $count - 1 ]->date ) );
	?>
	<svg class="trend-chart" viewBox="0 0 <?php echo esc_attr( $width ); ?> <?php echo esc_attr( $height ); ?>" preserveAspectRatio="none">
		<polygon class="trend-chart-area" points="<?php echo esc_attr( $area_points ); ?>"></polygon>
		<polyline class="trend-chart-line" points="<?php echo esc_attr( $line_points ); ?>"></polyline>
		<?php if ( $count <= 31 ) : ?>
			<?php foreach ( $points as $p ) : ?>
				<circle class="trend-chart-dot" cx="<?php echo esc_attr( $p[0] ); ?>" cy="<?php echo esc_attr( $p[1] ); ?>" r="2.5"></circle>
			<?php endforeach; ?>
		<?php endif; ?>
		<text class="trend-chart-axis" x="<?php echo esc_attr( $pad_x ); ?>" y="<?php echo esc_attr( $height - 6 ); ?>"><?php echo esc_html( $first_date ); ?></text>
		<text class="trend-chart-axis" x="<?php echo esc_attr( $width - $pad_x ); ?>" y="<?php echo esc_attr( $height - 6 ); ?>" text-anchor="end"><?php echo esc_html( $last_date ); ?></text>
		<text class="trend-chart-axis" x="<?php echo esc_attr( $width - $pad_x ); ?>" y="<?php echo esc_attr( $pad_top ); ?>" text-anchor="end"><?php echo esc_html( 'BWP ' . $money_formatter( $max ) ); ?></text>
	</svg>
	<?php
};
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

	<div class="section-head"><div class="section-title"><?php esc_html_e( 'Revenue', 'ipn' ); ?></div></div>
	<div class="grid cols-2">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Total revenue', 'ipn' ); ?></div>
			<div class="panel-sub">
				<?php
				printf(
					/* translators: %s: number of days in the selected range */
					esc_html__( 'Collected orders, %s days', 'ipn' ),
					esc_html( $range )
				);
				?>
			</div>
			<div class="stat-value">BWP <?php echo esc_html( $ipn_money( $total_revenue ) ); ?></div>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Revenue trend', 'ipn' ); ?></div>
			<div class="panel-sub">
				<?php
				printf(
					/* translators: %s: number of days in the selected range */
					esc_html__( 'Daily, last %s days', 'ipn' ),
					esc_html( $range )
				);
				?>
			</div>
			<?php $ipn_render_revenue_trend( $revenue_trend, $ipn_money ); ?>
		</div>
	</div>

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Orders by branch', 'ipn' ); ?></div>
			<?php if ( empty( $orders_by_branch ) || ! $ipn_orders_total_count ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No orders in this period yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<div class="dash-bars">
					<?php foreach ( $orders_by_branch as $row ) : ?>
						<?php $ipn_pct = $ipn_max_branch_orders ? round( ( $row->count / $ipn_max_branch_orders ) * 100 ) : 0; ?>
						<div class="dash-bar-row">
							<div class="dash-bar-label"><?php echo esc_html( $row->branch_name ); ?></div>
							<div class="dash-bar-track">
								<div class="dash-bar-fill" style="width:<?php echo esc_attr( $ipn_pct ); ?>%;"></div>
							</div>
							<div class="dash-bar-value"><?php echo esc_html( $row->count ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
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
			<?php if ( empty( $branch_sales ) || ! $ipn_max_branch_revenue ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No collected orders in this period yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<div class="dash-bars">
					<?php foreach ( $branch_sales as $row ) : ?>
						<?php $ipn_pct = $ipn_max_branch_revenue ? round( ( $row->revenue / $ipn_max_branch_revenue ) * 100 ) : 0; ?>
						<div class="dash-bar-row">
							<div class="dash-bar-label"><?php echo esc_html( $row->branch_name ); ?></div>
							<div class="dash-bar-track">
								<div class="dash-bar-fill" style="width:<?php echo esc_attr( $ipn_pct ); ?>%;"></div>
							</div>
							<div class="dash-bar-value">
								<?php
								printf(
									/* translators: 1: revenue amount, 2: order count */
									esc_html__( 'BWP %1$s · %2$d', 'ipn' ),
									esc_html( $ipn_money( $row->revenue ) ),
									(int) $row->orders
								);
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Express vs Standard split', 'ipn' ); ?></div>
			<?php if ( ! $ipn_express_total_count ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No orders in this period yet.', 'ipn' ); ?></div>
			<?php else : ?>
				<div class="donut-row">
					<?php $ipn_render_split_donut( $express_split, $ipn_express_total_count ); ?>
					<div class="donut-legend">
						<div class="donut-legend-row">
							<span class="donut-legend-swatch donut-legend-swatch--standard"></span>
							<span class="donut-legend-label"><?php esc_html_e( 'Standard', 'ipn' ); ?></span>
							<span class="donut-legend-value">
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
						<div class="donut-legend-row">
							<span class="donut-legend-swatch donut-legend-swatch--express"></span>
							<span class="donut-legend-label"><?php esc_html_e( 'Express', 'ipn' ); ?></span>
							<span class="donut-legend-value">
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
					</div>
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
