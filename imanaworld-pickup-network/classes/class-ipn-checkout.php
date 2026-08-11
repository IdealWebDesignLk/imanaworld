<?php
defined( 'ABSPATH' ) || exit;

/**
 * Checkout additions (Sections 3.5–3.6): Standard/Express collection type
 * with per-branch surcharge, and the optional nominated-recipient fields.
 * Validated and persisted into ipn_order_meta via IPN_Order — that table is
 * what IPN_Order's status hooks (reserve stock, generate OTP, etc.) read.
 */
class IPN_Checkout {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'woocommerce_after_order_notes', $this, 'render_collection_fields' );
		$loader->add_action( 'woocommerce_checkout_process', $this, 'validate_collection_fields' );
		$loader->add_action( 'woocommerce_checkout_update_order_meta', $this, 'save_collection_fields' );
		$loader->add_action( 'woocommerce_cart_calculate_fees', $this, 'add_express_surcharge' );
	}

	/**
	 * Renders Standard/Express collection type plus the optional nominated
	 * recipient fields (mockup: view-checkout). Reads the branch chosen via
	 * IPN_Storefront's session key directly rather than instantiating that
	 * class, since only the session constant is needed here.
	 */
	public function render_collection_fields( $checkout ) {
		$branch_id = 0;

		if ( function_exists( 'WC' ) && WC()->session ) {
			$branch_id = (int) WC()->session->get( IPN_Storefront::SESSION_KEY );
		}

		$branch = $branch_id ? IPN_Branch::get( $branch_id ) : null;

		// A customer can land on checkout without ever having visited the
		// shop loop (direct product link, an old cart, session loss) — in
		// that case there's otherwise no branch selector anywhere on this
		// page, just a dead-end "please select a branch" notice with no
		// way to act on it. Give the same branch-picker grid a home here
		// too rather than only before the shop loop.
		$branches = $branch ? array() : IPN_Branch::get_all( array( 'status' => 'active' ) );

		include IPN_PLUGIN_DIR . 'templates/storefront/checkout-fields.php';
	}

	/**
	 * Formats a minute count as a short human-readable duration, e.g.
	 * 90 => "1h 30m", 45 => "45 minutes", 120 => "2 hours".
	 */
	public static function format_minutes( $minutes ) {
		$minutes = max( 0, (int) $minutes );

		if ( $minutes < 60 ) {
			/* translators: %d: number of minutes */
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'ipn' ), $minutes );
		}

		$hours     = (int) floor( $minutes / 60 );
		$remainder = $minutes % 60;

		if ( $remainder ) {
			return sprintf(
				/* translators: 1: whole hours, 2: remaining minutes */
				__( '%1$dh %2$dm', 'ipn' ),
				$hours,
				$remainder
			);
		}

		/* translators: %d: number of hours */
		return sprintf( _n( '%d hour', '%d hours', $hours, 'ipn' ), $hours );
	}

	/**
	 * Requires a branch to be selected and still active, re-checks branch
	 * stock against the cart in case it changed since items were added
	 * (someone else bought the last unit, admin adjusted it down, etc.),
	 * and — when the recipient toggle is on — that recipient name and
	 * phone are present. Runs on woocommerce_checkout_process, i.e. right
	 * before payment, so this is the last checkpoint before money moves.
	 */
	public function validate_collection_fields() {
		if ( ! $this->nonce_ok() ) {
			return;
		}

		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			wc_add_notice( __( 'Please select a Click & Collect branch before checking out.', 'ipn' ), 'error' );
			return;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch || 'active' !== $branch->status ) {
			wc_add_notice( __( 'Your selected branch is no longer available for Click & Collect. Please choose a different branch.', 'ipn' ), 'error' );
			return;
		}

		$this->revalidate_cart_stock( $branch );

		if ( ! empty( $_POST['ipn_recipient_enabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$name  = isset( $_POST['ipn_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ipn_recipient_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$phone = isset( $_POST['ipn_recipient_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ipn_recipient_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( '' === $name || '' === $phone ) {
				wc_add_notice( __( 'Please provide the nominated recipient\'s name and phone number, or turn off "someone else will collect this order for me".', 'ipn' ), 'error' );
			}
		}
	}

	/**
	 * Re-checks every IPN-tracked cart item against the branch's current
	 * stock. IPN_Storefront::validate_branch_stock() already guards
	 * add-to-cart, but stock can still move between then and checkout
	 * (another customer buying the last unit, an admin adjustment) — this
	 * is the last checkpoint before payment.
	 */
	protected function revalidate_cart_stock( $branch ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = (int) $cart_item['product_id'];
			$quantity   = (int) $cart_item['quantity'];

			if ( (int) get_post_field( 'post_author', $product_id ) !== (int) $branch->vendor_id ) {
				continue; // Not this branch's vendor — not an IPN-tracked stock concern.
			}

			if ( ! IPN_Branch_Stock::get_row( $product_id, $branch->id ) ) {
				continue; // This vendor's product was never brought into the per-branch stock model.
			}

			$available = IPN_Branch_Stock::get_available( $product_id, $branch->id );

			if ( $quantity > $available ) {
				$product = wc_get_product( $product_id );

				wc_add_notice(
					sprintf(
						/* translators: 1: product name, 2: units available at the branch */
						__( '%1$s: only %2$d left at your selected branch. Please update the quantity in your cart before continuing.', 'ipn' ),
						$product ? $product->get_name() : __( 'An item in your cart', 'ipn' ),
						$available
					),
					'error'
				);
			}
		}
	}

	/**
	 * Persists collection type, branch id, and nominated recipient details
	 * into ipn_order_meta.
	 */
	public function save_collection_fields( $order_id ) {
		if ( ! $this->nonce_ok() ) {
			return;
		}

		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			return;
		}

		$recipient_enabled = ! empty( $_POST['ipn_recipient_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		IPN_Order::save_checkout_meta( $order_id, array(
			'branch_id'           => $branch_id,
			'collection_type'     => $this->get_posted_collection_type(),
			'nominated_name'      => $recipient_enabled && isset( $_POST['ipn_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ipn_recipient_name'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'nominated_phone'     => $recipient_enabled && isset( $_POST['ipn_recipient_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['ipn_recipient_phone'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'nominated_id_number' => $recipient_enabled && isset( $_POST['ipn_recipient_id_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ipn_recipient_id_number'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) );
	}

	/**
	 * Adds the branch's flat Express Collection surcharge to the cart total
	 * when that's the selected collection type. Fires on every cart/checkout
	 * total calculation, including the AJAX refresh storefront.js triggers
	 * when the customer switches the Standard/Express radio.
	 */
	public function add_express_surcharge( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id || 'express' !== $this->get_posted_collection_type() ) {
			return;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch || empty( $branch->express_enabled ) || (float) $branch->express_surcharge <= 0 ) {
			return;
		}

		$cart->add_fee( __( 'Express Collection', 'ipn' ), (float) $branch->express_surcharge, false );
	}

	protected function nonce_ok() {
		return isset( $_POST['ipn_checkout_fields_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ipn_checkout_fields_nonce'] ) ), 'ipn_checkout_fields' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	protected function get_selected_branch_id() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return (int) WC()->session->get( IPN_Storefront::SESSION_KEY );
		}
		return 0;
	}

	/**
	 * Reads the customer's selected collection type from wherever it
	 * currently lives: WooCommerce's update_order_review AJAX (triggered by
	 * storefront.js on radio change) serializes the whole checkout form into
	 * post_data, while the final checkout submission posts the field
	 * directly.
	 */
	protected function get_posted_collection_type() {
		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			parse_str( wp_unslash( $_POST['post_data'] ), $posted ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
			return isset( $posted['ipn_collection_type'] ) && 'express' === $posted['ipn_collection_type'] ? 'express' : 'standard';
		}

		if ( isset( $_POST['ipn_collection_type'] ) && 'express' === $_POST['ipn_collection_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return 'express';
		}

		return 'standard';
	}
}
