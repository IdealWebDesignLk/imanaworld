<?php
defined( 'ABSPATH' ) || exit;
/** @var WC_Order $order */

/**
 * Structural stage timeline (mockup: view-account). There is no real IPN
 * order-meta yet — collection type, branch, current stage, and OTP aren't
 * stored on orders in this codebase yet (that's Phase 3/4 work) — so every
 * stage renders inert/neutral rather than faking a specific current stage.
 * Once order-meta lands, this is where a stage would get an
 * "ipn-tracker-step--done" / "ipn-tracker-step--current" class and the
 * OTP/branch/recipient panels below would populate for real.
 */
$stages = array(
	'received'  => __( 'Order Received', 'ipn' ),
	'accepted'  => __( 'Accepted', 'ipn' ),
	'preparing' => __( 'Preparing', 'ipn' ),
	'ready'     => __( 'Ready for Collection', 'ipn' ),
	'collected' => __( 'Collected', 'ipn' ),
);
?>
<div class="ipn-order-tracker">
	<h3 class="ipn-order-tracker__heading"><?php esc_html_e( 'Click & Collect Status', 'ipn' ); ?></h3>

	<ol class="ipn-order-tracker__stages">
		<?php foreach ( $stages as $stage_key => $stage_label ) : ?>
			<li class="ipn-tracker-step" data-stage="<?php echo esc_attr( $stage_key ); ?>">
				<span class="ipn-tracker-dot" aria-hidden="true"></span>
				<span class="ipn-tracker-label"><?php echo esc_html( $stage_label ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>

	<p class="ipn-order-tracker__notice">
		<?php esc_html_e( 'Live Click & Collect status tracking for this order is not implemented yet — the current stage, collection code, branch details, and nominated recipient will appear here once order routing is built.', 'ipn' ); ?>
	</p>
</div>
