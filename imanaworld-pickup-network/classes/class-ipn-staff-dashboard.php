<?php
defined( 'ABSPATH' ) || exit;

/**
 * Branch staff order dashboard (Section 3.4) — a front-end, non wp-admin
 * area reached via the [ipn_staff_dashboard] shortcode. Staff only ever see
 * orders where order meta _ipn_branch_id matches their assigned branch
 * (IPN_Roles::get_branch_id). Queue/detail rendering is Phase 3 build-out.
 */
class IPN_Staff_Dashboard {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'init', $this, 'register_shortcode' );
	}

	public function register_shortcode() {
		add_shortcode( 'ipn_staff_dashboard', array( $this, 'render' ) );
	}

	/**
	 * Screens reachable via ?ipn_screen= on the page carrying the shortcode.
	 * There is no client-side router here (unlike the mockup this dashboard
	 * is built from) — each screen is a full page render.
	 */
	const SCREENS = array( 'queue', 'detail', 'stock' );

	public function render() {
		if ( ! is_user_logged_in() || ! IPN_Roles::is_branch_staff( get_current_user_id() ) ) {
			ob_start();
			include IPN_PLUGIN_DIR . 'templates/staff/login.php';
			return ob_get_clean();
		}

		$branch_id = IPN_Roles::get_branch_id( get_current_user_id() );
		$branch    = $branch_id ? IPN_Branch::get( $branch_id ) : null;

		$screen = isset( $_GET['ipn_screen'] ) ? sanitize_key( wp_unslash( $_GET['ipn_screen'] ) ) : 'queue'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $screen, self::SCREENS, true ) ) {
			$screen = 'queue';
		}

		ob_start();

		switch ( $screen ) {
			case 'detail':
				$order_id   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$otp_result = null;

				if ( $order_id && isset( $_POST['ipn_otp_code'], $_POST['ipn_otp_nonce'] )
					&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_otp_nonce'] ) ), 'ipn_verify_otp_' . $order_id ) ) {
					$otp_result = IPN_OTP::verify(
						$order_id,
						sanitize_text_field( wp_unslash( $_POST['ipn_otp_code'] ) ),
						get_current_user_id()
					);
				}

				$order = $order_id ? $this->get_order_detail( $order_id ) : null;

				include IPN_PLUGIN_DIR . 'templates/staff/order-detail.php';
				break;

			case 'stock':
				$stock = $branch_id ? IPN_Branch_Stock::get_stock_for_branch( $branch_id ) : array();
				include IPN_PLUGIN_DIR . 'templates/staff/stock.php';
				break;

			default:
				$orders = $branch_id ? $this->get_branch_orders( $branch_id ) : array();
				include IPN_PLUGIN_DIR . 'templates/staff/order-queue.php';
				break;
		}

		return ob_get_clean();
	}

	/**
	 * Builds a link to another dashboard screen on the current page, e.g.
	 * IPN_Staff_Dashboard::screen_url( 'stock' ) or
	 * IPN_Staff_Dashboard::screen_url( 'detail', array( 'order_id' => 123 ) ).
	 *
	 * @param string $screen One of self::SCREENS.
	 * @param array  $args   Extra query args to merge in (e.g. order_id, status).
	 * @return string Escaped URL.
	 */
	public static function screen_url( $screen, array $args = array() ) {
		$args = array_merge( array( 'ipn_screen' => $screen ), $args );
		return esc_url( add_query_arg( $args ) );
	}

	/**
	 * Returns this branch's order queue. Order-routing/order-meta is not
	 * implemented yet (Phase 3 build-out), so this always returns an empty
	 * array today. Once wired up, each element is expected to be an object
	 * shaped like:
	 *
	 *   ->id             string  Display order number, e.g. "CHP-10432"
	 *   ->order_id       int     Underlying WooCommerce/Dokan order ID
	 *   ->customer_name  string
	 *   ->time_label     string  e.g. "10:42 AM" or "Yesterday · 4:22 PM"
	 *   ->type           string  'standard' | 'express'
	 *   ->status         string  'new' | 'accepted' | 'preparing' | 'ready' | 'collected' | 'disputed'
	 *   ->item_count     int
	 *   ->has_recipient  bool    True when a nominated recipient (not the buyer) will collect.
	 *
	 * templates/staff/order-queue.php is written against this shape so a
	 * future dev can wire this method up without touching the template.
	 *
	 * @param int $branch_id
	 * @return array
	 */
	public function get_branch_orders( $branch_id ) {
		return array();
	}

	/**
	 * Returns full detail for a single order for the detail screen.
	 * Order-routing/order-meta is not implemented yet, so this always
	 * returns null today. Once wired up, the expected shape is an object
	 * with:
	 *
	 *   ->id              string       Display order number, e.g. "CHP-10432"
	 *   ->order_id        int          Underlying WooCommerce/Dokan order ID
	 *   ->customer_name   string
	 *   ->time_label      string
	 *   ->type            string       'standard' | 'express'
	 *   ->surcharge       float        Only relevant when type === 'express'.
	 *   ->status          string       'new' | 'accepted' | 'preparing' | 'ready' | 'collected' | 'disputed'
	 *   ->dispute_reason  string       Only set when status === 'disputed'.
	 *   ->items           array        Each item: array( 'name' => string, 'qty' => int ).
	 *   ->recipient       object|null  Nominated recipient, or null when the buyer collects
	 *                                  in person. When set: ->name, ->phone, ->id_number.
	 *   ->audit           array        Each entry: array( 'text' => string, 'time' => string ).
	 *
	 * templates/staff/order-detail.php is written against this shape so a
	 * future dev can wire this method up without touching the template.
	 *
	 * @param int $order_id
	 * @return object|null
	 */
	public function get_order_detail( $order_id ) {
		return null;
	}
}
