<?php
defined( 'ABSPATH' ) || exit;
/**
 * Shared inline-SVG chart helpers for the wp-admin IPN screens (Dashboard,
 * Reports — issues #52, #59). Plain functions rather than a JS charting
 * library: this plugin has no build step to bundle one into, and a donut
 * and a line chart are simple enough not to need one. Included once per
 * request via require_once from whichever admin template needs it.
 */

/**
 * Renders a multi-segment donut built from concentric stroke-dasharray
 * arcs — one ring, running dashoffset per segment. Zero-count segments are
 * skipped so the ring only ever shows real data.
 *
 * @param array $segments Each: array{count:int, color:string} (color is any
 *                         valid CSS color, typically a var(--x) token).
 */
function ipn_admin_render_donut( array $segments, $size = 96 ) {
	$total = array_sum( wp_list_pluck( $segments, 'count' ) );

	if ( ! $total ) {
		return;
	}

	$radius        = ( $size / 2 ) - 12;
	$circumference = 2 * M_PI * $radius;
	$center        = $size / 2;
	$offset        = 0;
	?>
	<svg class="donut-svg" width="<?php echo esc_attr( $size ); ?>" height="<?php echo esc_attr( $size ); ?>" viewBox="0 0 <?php echo esc_attr( $size ); ?> <?php echo esc_attr( $size ); ?>">
		<?php foreach ( $segments as $segment ) : ?>
			<?php
			if ( ! $segment['count'] ) {
				continue;
			}
			$length = ( $segment['count'] / $total ) * $circumference;
			?>
			<circle
				cx="<?php echo esc_attr( $center ); ?>" cy="<?php echo esc_attr( $center ); ?>" r="<?php echo esc_attr( $radius ); ?>"
				fill="none" stroke="<?php echo esc_attr( $segment['color'] ); ?>" stroke-width="14"
				stroke-dasharray="<?php echo esc_attr( round( $length, 2 ) . ' ' . round( $circumference, 2 ) ); ?>"
				stroke-dashoffset="<?php echo esc_attr( round( -$offset, 2 ) ); ?>"
				transform="rotate(-90 <?php echo esc_attr( $center ); ?> <?php echo esc_attr( $center ); ?>)"
			/>
			<?php $offset += $length; ?>
		<?php endforeach; ?>
	</svg>
	<?php
}

/**
 * Renders $rows (date/revenue objects, see IPN_Reports::revenue_trend()) as
 * a filled SVG line chart. Point count isn't fixed — a 90-day range
 * produces as many points as a 7-day one, just closer together.
 */
function ipn_admin_render_trend_chart( array $rows, $money_formatter ) {
	$count = count( $rows );

	$width      = 600;
	$height     = 140;
	$pad_x      = 4;
	$pad_top    = 16;
	$pad_bottom = 24;
	$plot_w     = $width - ( $pad_x * 2 );
	$plot_h     = $height - $pad_top - $pad_bottom;

	// $rows is normally never empty for a real date range — the caller
	// zero-fills one entry per day — but an empty array is handled the
	// same as an all-zero one rather than rendering nothing.
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
		<text class="trend-chart-axis" x="<?php echo esc_attr( $width - $pad_x ); ?>" y="<?php echo esc_attr( $pad_top ); ?>" text-anchor="end"><?php echo esc_html( 'BWP ' . call_user_func( $money_formatter, $max ) ); ?></text>
	</svg>
	<?php
}
