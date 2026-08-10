<?php
defined( 'ABSPATH' ) || exit;
/**
 * @var array $entries  Rows from IPN_Audit_Log::query() (most recent 500).
 * @var array $branches Rows from IPN_Branch::get_all(), for the branch filter.
 */

/**
 * event_type values are free-form (branch_created, stock_reserved,
 * stock_released, stock_sold, ...). Bucket them into the same four
 * categories the mockup filters by, purely for the dropdown/filter UI.
 */
$ipn_audit_category = function ( $event_type ) {
	foreach ( array( 'otp', 'refund', 'stock' ) as $needle ) {
		if ( false !== strpos( $event_type, $needle ) ) {
			return $needle;
		}
	}
	return 'order';
};
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Operational audit trail', 'ipn' ); ?></div>
		<a class="btn btn-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ipn_export_audit_log' ), 'ipn_export_audit_log' ) ); ?>">
			<?php esc_html_e( '⇩ Export CSV', 'ipn' ); ?>
		</a>
	</div>

	<div class="toolbar">
		<select id="ipn-audit-branch-filter">
			<option value="all"><?php esc_html_e( 'All branches', 'ipn' ); ?></option>
			<?php foreach ( $branches as $branch ) : ?>
				<option value="<?php echo esc_attr( $branch->id ); ?>"><?php echo esc_html( $branch->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<select id="ipn-audit-type-filter">
			<option value="all"><?php esc_html_e( 'All event types', 'ipn' ); ?></option>
			<option value="order"><?php esc_html_e( 'Order events', 'ipn' ); ?></option>
			<option value="stock"><?php esc_html_e( 'Stock events', 'ipn' ); ?></option>
			<option value="otp"><?php esc_html_e( 'OTP events', 'ipn' ); ?></option>
			<option value="refund"><?php esc_html_e( 'Refund events', 'ipn' ); ?></option>
		</select>
	</div>

	<div class="panel" id="ipn-audit-panel">
		<?php if ( empty( $entries ) ) : ?>
			<div class="empty-state"><?php esc_html_e( 'No audit events logged yet.', 'ipn' ); ?></div>
		<?php else : ?>
			<?php foreach ( $entries as $entry ) : ?>
				<?php
				$branch_name = '—';
				if ( ! empty( $entry->branch_id ) ) {
					$branch = IPN_Branch::get( $entry->branch_id );
					if ( $branch ) {
						$branch_name = $branch->name;
					}
				}
				$text = IPN_Audit_Log::describe_event( $entry->event_type );
				if ( ! empty( $entry->order_id ) ) {
					/* translators: 1: event description, 2: order ID */
					$text = sprintf( __( '%1$s — order #%2$d', 'ipn' ), $text, $entry->order_id );
				}
				?>
				<div class="audit-item" data-ipn-row="1" data-branch="<?php echo esc_attr( $entry->branch_id ); ?>" data-type="<?php echo esc_attr( $ipn_audit_category( $entry->event_type ) ); ?>">
					<div class="audit-dot"></div>
					<div>
						<div><?php echo esc_html( $text ); ?></div>
						<div class="audit-meta">
							<?php echo esc_html( $branch_name ); ?> ·
							<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry->created_at ) ); ?>
							· <?php echo esc_html( ucfirst( $entry->actor_type ) ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
