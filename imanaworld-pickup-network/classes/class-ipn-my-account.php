<?php
defined( 'ABSPATH' ) || exit;

/**
 * Customer live order tracker in My Account (Section 3.12): status timeline,
 * OTP display when Ready for Collection, branch details, nominated recipient
 * info. Phase 4 build-out.
 */
class IPN_My_Account {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'woocommerce_order_details_after_order_table', $this, 'render_order_tracker' );
	}

	public function render_order_tracker( $order ) {
		include IPN_PLUGIN_DIR . 'templates/my-account/order-tracker.php';
	}
}
