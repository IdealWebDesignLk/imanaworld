<?php
defined( 'ABSPATH' ) || exit;

/**
 * Restricts a cart to one vendor at a time (issue #39).
 *
 * This is a storewide marketplace policy, not a Click & Collect one — it
 * applies to every vendor on Imanaworld, C&C or not, which is why it lives in
 * its own class rather than inside IPN_Storefront. Dokan itself is happy to
 * split a mixed-vendor cart into one sub-order per vendor at checkout; this
 * class exists to stop that cart from ever being built in the first place,
 * per the site owner's explicit request.
 *
 * The add-to-cart gate is what actually enforces the rule — everything
 * downstream (the cart page, checkout) can then simply assume a cart never
 * holds more than one vendor. A checkout-time re-check is kept anyway as a
 * backstop, the same defence-in-depth shape #37's branch/stock check used,
 * for whatever reaches the cart by some path this class did not anticipate.
 */
class IPN_Single_Vendor_Cart {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'wp_loaded', $this, 'maybe_handle_cart_fix' );
		$loader->add_filter( 'woocommerce_add_to_cart_validation', $this, 'validate_single_vendor', 20, 3 );
		$loader->add_action( 'woocommerce_check_cart_items', $this, 'check_cart_single_vendor' );
		$loader->add_action( 'woocommerce_checkout_process', $this, 'check_cart_single_vendor' );
	}

	/**
	 * The one vendor already in the cart, or 0 for an empty cart. The add-to-
	 * cart gate is what keeps this well-defined — a cart this class allowed
	 * to be built never holds more than one.
	 */
	protected function cart_vendor_id() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				return (int) get_post_field( 'post_author', $item['product_id'] );
			}
		}

		return 0;
	}

	/**
	 * Blocks adding a product from a different vendor than what the cart
	 * already holds, and offers the same choice the issue specifies: clear
	 * the cart and add this one instead, or leave the cart as it is.
	 */
	public function validate_single_vendor( $passed, $product_id, $quantity ) {
		if ( ! $passed ) {
			return $passed;
		}

		$cart_vendor_id = $this->cart_vendor_id();

		if ( ! $cart_vendor_id ) {
			return $passed;
		}

		$product_vendor_id = (int) get_post_field( 'post_author', $product_id );

		if ( $product_vendor_id === $cart_vendor_id ) {
			return $passed;
		}

		wc_add_notice(
			__( 'This product is from a different vendor. You can only purchase products from one vendor at a time. If you want to continue with this product, you\'ll need to clear your current cart.', 'ipn' )
			. ' <a href="' . esc_url( $this->cart_fix_url( $product_id, $quantity ) ) . '">' . esc_html__( 'Clear Cart & Continue', 'ipn' ) . '</a>'
			// Points at the cart rather than back at the same product page.
			// Nothing was ever added to the cart on this path, so this link's
			// job is to visibly PROVE that, not just avoid changing anything —
			// a link that reloads the exact page the customer is already
			// looking at leaves nothing on screen to confirm the click did
			// anything, which reads as the button being broken. Landing on
			// the cart shows their original vendor's items, unchanged.
			. ' &middot; <a href="' . esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : remove_query_arg( array( 'ipn_vendor_cart_fix', 'ipn_product_id', 'ipn_quantity', '_wpnonce' ) ) ) . '">' . esc_html__( 'Cancel', 'ipn' ) . '</a>',
			'error'
		);

		return false;
	}

	/**
	 * Backstop for the cart page and checkout, in case a mixed-vendor cart
	 * ever reaches either by some path the add-to-cart gate did not cover.
	 * Unlike validate_single_vendor() this cannot offer "clear and continue"
	 * — there is no single product left to re-add — so it only explains the
	 * problem and points at the fix.
	 */
	public function check_cart_single_vendor() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$vendor_ids = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$vendor_ids[ (int) get_post_field( 'post_author', $item['product_id'] ) ] = true;
			}
		}

		if ( count( $vendor_ids ) <= 1 ) {
			return;
		}

		wc_add_notice(
			__( 'Your cart has products from more than one vendor. You can only purchase products from one vendor at a time — please remove the extra items or clear your cart before continuing.', 'ipn' )
			. ' <a href="' . esc_url( $this->cart_fix_url( 0, 0 ) ) . '">' . esc_html__( 'Clear my cart', 'ipn' ) . '</a>',
			'error'
		);
	}

	/**
	 * A nonce-guarded link that clears the cart and, when a product/quantity
	 * is given, adds that product straight back in — "Clear Cart & Continue"
	 * in one click rather than two.
	 *
	 * Built against an explicit page rather than add_query_arg()'s default of
	 * "whatever the current request's URL is" — that default is wrong here
	 * specifically because it is not always safe to assume. Reproduced live:
	 * the shop-loop's Add to Cart button is WooCommerce's own AJAX one, and
	 * when this notice is raised from inside THAT request, the "current URL"
	 * is the ?wc-ajax=add_to_cart endpoint itself. A link built from it sends
	 * the next click straight into WooCommerce's own AJAX handler, which has
	 * nothing to do with this query string, dies before this class's own
	 * wp_loaded handler ever runs, and the browser is left looking at
	 * whatever that handler happened to output — a blank screen.
	 *
	 * The product's own permalink is what "Clear Cart & Continue" actually
	 * means anyway — back to the product being added, not wherever the click
	 * that got here happened to originate.
	 */
	protected function cart_fix_url( $product_id, $quantity ) {
		$product_id = (int) $product_id;
		$base       = $product_id && get_permalink( $product_id ) ? get_permalink( $product_id ) : ( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );

		return add_query_arg(
			array(
				'ipn_vendor_cart_fix' => 'clear',
				'ipn_product_id'      => $product_id,
				'ipn_quantity'        => max( 1, (int) $quantity ),
				'_wpnonce'            => wp_create_nonce( 'ipn_vendor_cart_fix' ),
			),
			$base
		);
	}

	/**
	 * Handles the "Clear Cart & Continue" / "Clear my cart" links above.
	 * Runs on wp_loaded, the same hook IPN_Storefront's own cart-fix actions
	 * use, and for the same reason: WC()->cart is guaranteed to exist there.
	 */
	public function maybe_handle_cart_fix() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! isset( $_GET['ipn_vendor_cart_fix'], $_GET['_wpnonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'ipn_vendor_cart_fix' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		WC()->cart->empty_cart();

		$product_id = isset( $_GET['ipn_product_id'] ) ? absint( $_GET['ipn_product_id'] ) : 0;
		$quantity   = isset( $_GET['ipn_quantity'] ) ? max( 1, absint( $_GET['ipn_quantity'] ) ) : 1;

		if ( $product_id ) {
			// The cart is empty now, so this product's own vendor is free to
			// become the cart's new (and only) vendor — validate_single_vendor()
			// will not object to it.
			if ( WC()->cart->add_to_cart( $product_id, $quantity ) ) {
				wc_add_notice( __( 'Your cart was cleared and this product was added.', 'ipn' ), 'success' );
			}
		} else {
			wc_add_notice( __( 'Your cart has been cleared.', 'ipn' ), 'success' );
		}

		// The cart page rather than remove_query_arg() on whatever page this
		// request landed on — same reasoning as the link that led here: an
		// explicit, always-real destination beats trusting the current
		// request's URL, and landing on the cart is the clearest confirmation
		// of what just happened either way.
		wp_safe_redirect( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) );
		exit;
	}
}
