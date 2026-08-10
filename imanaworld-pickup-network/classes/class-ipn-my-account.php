<?php
defined( 'ABSPATH' ) || exit;

/**
 * Customer live order tracker in My Account (Section 3.12): status timeline,
 * branch details, nominated recipient info. The collection OTP itself is
 * not redisplayed here — IPN_OTP only ever stores a hash (Section 3.7's
 * "single-use, hashed" requirement), so once it's emailed there is no
 * plaintext copy left to show. The tracker points the customer at their
 * email/SMS instead of re-exposing a stored secret.
 */
class IPN_My_Account {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'woocommerce_order_details_after_order_table', $this, 'render_order_tracker' );
	}

	public function render_order_tracker( $order ) {
		$meta   = IPN_Order::get_meta( $order->get_id() );
		$branch = ( $meta && $meta->branch_id ) ? IPN_Branch::get( $meta->branch_id ) : null;

		include IPN_PLUGIN_DIR . 'templates/my-account/order-tracker.php';
	}
}
