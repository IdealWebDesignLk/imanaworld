<?php
defined( 'ABSPATH' ) || exit;
/**
 * Disputed/rejected-collection orders aren't tracked by any data model yet
 * (order status flow lives in a future phase — see templates/admin/orders.php
 * for the same "not implemented yet" honesty). This page keeps the mockup's
 * structure so it's ready to wire up once that data exists.
 */
?>
<div class="wrap ipn-admin">
	<div class="section-head">
		<div class="section-title"><?php esc_html_e( 'Disputes & returns queue', 'ipn' ); ?></div>
	</div>

	<div class="panel">
		<div class="empty-state"><?php esc_html_e( 'Dispute and return tracking is not implemented yet.', 'ipn' ); ?></div>
	</div>
</div>
