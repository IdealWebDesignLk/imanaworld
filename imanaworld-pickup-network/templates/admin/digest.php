<?php
defined( 'ABSPATH' ) || exit;
/**
 * Driven by IPN_Uncollected_Workflow's hourly cron — every expired order it
 * finds logs a collection_expired audit event, which this screen queries
 * for the last 24 hours.
 *
 * @var object[] $expired_orders See IPN_Admin::get_expired_orders_digest() for the shape.
 */

$needing_review = count( array_filter( $expired_orders, function ( $row ) {
	return empty( $row->refunded );
} ) );
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( "Today's digest", 'ipn' ); ?></div>
		<a
			class="btn btn-secondary"
			target="_blank"
			rel="noopener noreferrer"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ipn_preview_digest_email' ), 'ipn_preview_digest_email' ) ); ?>"
		>
			<?php esc_html_e( '✉ Preview digest email', 'ipn' ); ?>
		</a>
	</div>

	<div class="grid cols-2">
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Expired last 24h', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( count( $expired_orders ) ); ?></div>
		</div>
		<div class="stat-card">
			<div class="stat-label"><?php esc_html_e( 'Refunds needing review', 'ipn' ); ?></div>
			<div class="stat-value"><?php echo esc_html( $needing_review ); ?></div>
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
				<?php if ( empty( $expired_orders ) ) : ?>
					<tr>
						<td colspan="7">
							<div class="empty-state"><?php esc_html_e( 'No orders expired in the last 24 hours.', 'ipn' ); ?></div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $expired_orders as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->order_number ); ?></td>
							<td><?php echo esc_html( $row->branch_name ); ?></td>
							<td><?php echo esc_html( $row->customer_name ); ?></td>
							<td><?php echo esc_html( $row->ready_since ); ?></td>
							<td><?php echo esc_html( $row->expired_at ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) $row->total, 2 ) ); ?></td>
							<td>
								<?php if ( $row->refunded ) : ?>
									<span class="chip chip-collected"><?php esc_html_e( 'Refunded', 'ipn' ); ?></span>
								<?php else : ?>
									<span class="chip chip-disputed"><?php esc_html_e( 'Needs review', 'ipn' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<div class="hint" style="margin-top:10px;">
		<?php esc_html_e( 'Refunds are always manual — process them from the order in WooCommerce, same as any other refund. This list does not auto-refund anything.', 'ipn' ); ?>
	</div>
</div>
