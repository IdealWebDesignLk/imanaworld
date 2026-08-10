<?php
defined( 'ABSPATH' ) || exit;

/**
 * Customer email notifications (Section 3.8). Each method corresponds to one
 * row of the notification matrix and renders the matching file under
 * templates/emails/. Hook wiring (order status transitions, OTP events)
 * is Phase 4 build-out.
 */
class IPN_Notifications {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'ipn_order_placed', $this, 'send_order_placed' );
		$loader->add_action( 'ipn_order_accepted', $this, 'send_order_accepted' );
		$loader->add_action( 'ipn_order_ready_for_collection', $this, 'send_ready_for_collection' );
		$loader->add_action( 'ipn_order_collection_reminder', $this, 'send_collection_reminder' );
		$loader->add_action( 'ipn_order_collected', $this, 'send_order_collected' );
		$loader->add_action( 'ipn_order_cancelled', $this, 'send_order_cancelled' );
	}

	public function send_order_placed( $order_id ) {}

	public function send_order_accepted( $order_id ) {}

	public function send_ready_for_collection( $order_id ) {}

	public function send_collection_reminder( $order_id ) {}

	public function send_order_collected( $order_id ) {}

	public function send_order_cancelled( $order_id ) {}

	protected function render_template( $template, array $vars = array() ) {
		$path = IPN_PLUGIN_DIR . 'templates/emails/' . $template . '.php';

		if ( ! file_exists( $path ) ) {
			return '';
		}

		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		ob_start();
		include $path;
		return ob_get_clean();
	}
}
