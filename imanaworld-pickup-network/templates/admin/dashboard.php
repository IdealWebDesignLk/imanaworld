<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var object[] $branches         IPN_Admin_Context::branches() — already scoped to the partner in context.
 * @var object[] $import_runs      IPN_CSV_Import::get_recent_runs() — network-wide (imports carry no vendor_id).
 * @var object[] $audit_entries    IPN_Audit_Log::query() — already scoped.
 * @var object[] $orders_by_branch IPN_Reports::orders_by_branch(), last 7 days — already scoped.
 * @var object[] $needs_attention  Disputed or expired orders, last 7 days — already scoped.
 * @var array    $express_split    IPN_Reports::express_vs_standard_split(), last 7 days — already scoped.
 * @var array    $collection_success IPN_Reports::collection_success_rate(), last 7 days — already scoped.
 * @var object[] $revenue_trend    IPN_Reports::revenue_trend(), last 7 days — already scoped (issue #59).
 * @var array    $status_split     IPN_Reports::order_status_split(), last 7 days — already scoped (issue #59).
 */

require_once __DIR__ . '/partials/charts.php';

// Same slugs IPN_Order::display_status() already uses for chip-{slug} colours
// elsewhere (Orders & Disputes, staff dashboard) — reused here rather than
// inventing a second status-to-colour mapping just for this chart.
$ipn_status_meta = array(
	'awaiting-payment' => array(
		'label' => __( 'Awaiting payment', 'ipn' ),
		'color' => 'var(--preparing)',
	),
	'new'              => array(
		'label' => __( 'New', 'ipn' ),
		'color' => 'var(--new)',
	),
	'accepted'         => array(
		'label' => __( 'Accepted', 'ipn' ),
		'color' => 'var(--new)',
	),
	'preparing'        => array(
		'label' => __( 'Preparing', 'ipn' ),
		'color' => 'var(--preparing)',
	),
	'ready'            => array(
		'label' => __( 'Ready for collection', 'ipn' ),
		'color' => 'var(--ready)',
	),
	'collected'        => array(
		'label' => __( 'Collected', 'ipn' ),
		'color' => 'var(--muted)',
	),
	'disputed'         => array(
		'label' => __( 'Disputed', 'ipn' ),
		'color' => 'var(--danger)',
	),
	'expired'          => array(
		'label' => __( 'Expired', 'ipn' ),
		'color' => 'var(--danger)',
	),
	'cancelled'        => array(
		'label' => __( 'Cancelled', 'ipn' ),
		'color' => 'var(--danger)',
	),
);

$ipn_status_total    = array_sum( $status_split );
$ipn_status_segments = array();

foreach ( $status_split as $ipn_slug => $ipn_count ) {
	$ipn_meta = isset( $ipn_status_meta[ $ipn_slug ] ) ? $ipn_status_meta[ $ipn_slug ] : array(
		'label' => ucfirst( $ipn_slug ),
		'color' => 'var(--muted)',
	);

	$ipn_status_segments[] = array(
		'slug'  => $ipn_slug,
		'label' => $ipn_meta['label'],
		'color' => $ipn_meta['color'],
		'count' => $ipn_count,
	);
}

usort( $ipn_status_segments, function ( $a, $b ) {
	return $b['count'] <=> $a['count'];
} );

$ipn_active_count = count(
	array_filter(
		$branches,
		function ( $b ) {
			return 'active' === $b->status;
		}
	)
);

$ipn_orders_total = array_sum( wp_list_pluck( $orders_by_branch, 'count' ) );
$ipn_max_branch    = $ipn_orders_total ? max( wp_list_pluck( $orders_by_branch, 'count' ) ) : 0;

$ipn_express_total = $express_split['standard']['count'] + $express_split['express']['count'];
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'IMANAWORLD Pickup Network', 'ipn' ); ?></div>
	</div>

	<div class="grid cols-4">
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Branches', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( count( $branches ) ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Active branches', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( $ipn_active_count ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Catalogue imports run', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( count( $import_runs ) ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Audit events logged', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( count( $audit_entries ) ); ?></div>
		</div>
	</div>

	<div class="section-head"><div class="section-title"><?php esc_html_e( 'Orders by branch — last 7 days', 'ipn' ); ?></div></div>
	<div class="panel">
		<?php if ( empty( $orders_by_branch ) || ! $ipn_orders_total ) : ?>
			<div class="empty-state"><?php esc_html_e( 'No orders in the last 7 days.', 'ipn' ); ?></div>
		<?php else : ?>
			<div class="dash-bars">
				<?php foreach ( $orders_by_branch as $row ) : ?>
					<?php $ipn_pct = $ipn_max_branch ? round( ( $row->count / $ipn_max_branch ) * 100 ) : 0; ?>
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

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Needs attention', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Expired or disputed orders, last 7 days', 'ipn' ); ?></div>
			<?php if ( empty( $needs_attention ) ) : ?>
				<div class="empty-state"><?php esc_html_e( 'Nothing needs attention right now.', 'ipn' ); ?></div>
			<?php else : ?>
				<table class="data">
					<tbody>
						<?php foreach ( array_slice( $needs_attention, 0, 8 ) as $order ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $order->edit_url ); ?>">#<?php echo esc_html( $order->order_number ); ?></a></td>
								<td><?php echo esc_html( $order->branch_name ); ?></td>
								<td><span class="chip chip-<?php echo esc_attr( $order->status ); ?>"><?php echo esc_html( ucfirst( $order->status ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $needs_attention ) > 8 ) : ?>
					<p class="panel-sub">
						<?php
						printf(
							/* translators: %d: number of additional orders not shown in this preview list */
							esc_html__( '+ %d more — see Orders & Disputes.', 'ipn' ),
							count( $needs_attention ) - 8
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Express vs Standard split', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Volume share, last 7 days', 'ipn' ); ?></div>
			<?php if ( ! $ipn_express_total ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No orders in the last 7 days.', 'ipn' ); ?></div>
			<?php else : ?>
				<?php
				$ipn_standard_pct = round( ( $express_split['standard']['count'] / $ipn_express_total ) * 100 );
				$ipn_express_pct  = 100 - $ipn_standard_pct;
				?>
				<div class="dash-bars">
					<div class="dash-bar-row">
						<div class="dash-bar-label"><?php esc_html_e( 'Standard', 'ipn' ); ?></div>
						<div class="dash-bar-track">
							<div class="dash-bar-fill" style="width:<?php echo esc_attr( $ipn_standard_pct ); ?>%;"></div>
						</div>
						<div class="dash-bar-value"><?php echo esc_html( $express_split['standard']['count'] ); ?> (<?php echo esc_html( $ipn_standard_pct ); ?>%)</div>
					</div>
					<div class="dash-bar-row">
						<div class="dash-bar-label"><?php esc_html_e( 'Express', 'ipn' ); ?></div>
						<div class="dash-bar-track">
							<div class="dash-bar-fill dash-bar-fill--express" style="width:<?php echo esc_attr( $ipn_express_pct ); ?>%;"></div>
						</div>
						<div class="dash-bar-value"><?php echo esc_html( $express_split['express']['count'] ); ?> (<?php echo esc_html( $ipn_express_pct ); ?>%)</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="grid cols-2" style="margin-top:14px;">
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Revenue trend', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Collected orders, last 7 days', 'ipn' ); ?></div>
			<?php ipn_admin_render_trend_chart( $revenue_trend, function ( $amount ) { return number_format_i18n( (float) $amount, 2 ); } ); ?>
		</div>
		<div class="panel">
			<div class="panel-title"><?php esc_html_e( 'Order status split', 'ipn' ); ?></div>
			<div class="panel-sub"><?php esc_html_e( 'Last 7 days', 'ipn' ); ?></div>
			<?php if ( ! $ipn_status_total ) : ?>
				<div class="empty-state"><?php esc_html_e( 'No orders in the last 7 days.', 'ipn' ); ?></div>
			<?php else : ?>
				<div class="donut-row">
					<?php ipn_admin_render_donut( $ipn_status_segments ); ?>
					<div class="donut-legend">
						<?php foreach ( $ipn_status_segments as $ipn_segment ) : ?>
							<?php if ( ! $ipn_segment['count'] ) : continue; endif; ?>
							<div class="donut-legend-row">
								<span class="donut-legend-swatch" style="background:<?php echo esc_attr( $ipn_segment['color'] ); ?>;"></span>
								<span class="donut-legend-label"><?php echo esc_html( $ipn_segment['label'] ); ?></span>
								<span class="donut-legend-value"><?php echo esc_html( $ipn_segment['count'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="section-head"><div class="section-title"><?php esc_html_e( 'Reports', 'ipn' ); ?></div></div>
	<div class="panel">
		<div class="grid cols-4">
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Orders, last 7 days', 'ipn' ); ?></div>
				<div class="stat-value"><?php echo esc_html( $ipn_orders_total ); ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Collection success rate', 'ipn' ); ?></div>
				<div class="stat-value"><?php echo null === $collection_success['success_rate'] ? '—' : esc_html( $collection_success['success_rate'] ) . '%'; ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Collected', 'ipn' ); ?></div>
				<div class="stat-value"><?php echo esc_html( $collection_success['collected'] ); ?></div>
			</div>
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Lost (cancelled/expired)', 'ipn' ); ?></div>
				<div class="stat-value"><?php echo esc_html( $collection_success['lost'] ); ?></div>
			</div>
		</div>
		<p class="panel-sub" style="margin-top:12px;">
			<a class="btn btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=ipn-reports' ) ); ?>"><?php esc_html_e( 'View full reports', 'ipn' ); ?></a>
		</p>
	</div>
</div>
