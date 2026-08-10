<?php
defined( 'ABSPATH' ) || exit;

/**
 * Customer-facing store-first shopping experience (Section 3.1): branch
 * selector on the Choppies storefront, session-based branch persistence,
 * a persistent branch indicator/switcher across the storefront, and
 * catalogue filtering by the selected branch's stock.
 */
class IPN_Storefront {

	const SESSION_KEY = 'ipn_selected_branch';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'wp_loaded', $this, 'maybe_handle_branch_actions' );
		$loader->add_action( 'woocommerce_before_main_content', $this, 'render_branch_indicator_bar' );
		$loader->add_action( 'woocommerce_before_shop_loop', $this, 'render_branch_selector' );
		$loader->add_action( 'woocommerce_product_query', $this, 'filter_products_by_branch' );
		$loader->add_filter( 'woocommerce_get_availability', $this, 'filter_product_availability', 10, 2 );
		$loader->add_filter( 'woocommerce_add_to_cart_validation', $this, 'validate_branch_stock', 10, 3 );
	}

	public function get_selected_branch_id() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			return (int) WC()->session->get( self::SESSION_KEY );
		}
		return 0;
	}

	public function set_selected_branch( $branch_id ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, (int) $branch_id );
		}
	}

	/**
	 * Handles the branch-selector card links and the "Change branch" link
	 * from the indicator bar. Both are plain nonce-guarded GET requests
	 * (no JS required) that redirect back to the page the user was on.
	 * Runs on wp_loaded rather than init so WC()->session is guaranteed
	 * to be available.
	 */
	public function maybe_handle_branch_actions() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( isset( $_GET['ipn_branch'], $_GET['_wpnonce'] ) ) {
			$branch_id = absint( $_GET['ipn_branch'] );
			$nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( $branch_id && wp_verify_nonce( $nonce, 'ipn_select_branch_' . $branch_id ) ) {
				$branch = IPN_Branch::get( $branch_id );

				if ( $branch && 'active' === $branch->status ) {
					$previous = $this->get_selected_branch_id();
					$this->set_selected_branch( $branch_id );

					if ( $previous && $previous !== $branch_id && function_exists( 'WC' ) && WC()->cart ) {
						WC()->cart->empty_cart();
					}
				}

				wp_safe_redirect( remove_query_arg( array( 'ipn_branch', 'ipn_change_branch', '_wpnonce' ) ) );
				exit;
			}
		} elseif ( isset( $_GET['ipn_change_branch'], $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( wp_verify_nonce( $nonce, 'ipn_change_branch' ) ) {
				$this->set_selected_branch( 0 );
			}

			wp_safe_redirect( remove_query_arg( array( 'ipn_branch', 'ipn_change_branch', '_wpnonce' ) ) );
			exit;
		}
	}

	/**
	 * Full branch chooser (mockup: view-branch). Only shown when the
	 * shopper hasn't picked a branch yet — once one is selected, the
	 * indicator bar (below) is the switcher, so the shop loop isn't
	 * interrupted by the chooser on every page load.
	 */
	public function render_branch_selector() {
		if ( $this->get_selected_branch_id() ) {
			return;
		}

		$branches = IPN_Branch::get_all( array( 'status' => 'active' ) );
		include IPN_PLUGIN_DIR . 'templates/storefront/branch-selector.php';
	}

	/**
	 * Persistent "Shopping at <branch> · Change branch" bar. Hooked onto
	 * woocommerce_before_main_content so it appears above every WooCommerce
	 * template (shop, product, cart, checkout, my account) without touching
	 * the theme header — satisfies "branch switcher available at any point
	 * during shopping" without a parallel catalogue/product UI.
	 */
	public function render_branch_indicator_bar() {
		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			return;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch ) {
			return;
		}

		$change_url = add_query_arg(
			array(
				'ipn_change_branch' => 1,
				'_wpnonce'          => wp_create_nonce( 'ipn_change_branch' ),
			),
			function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' )
		);

		include IPN_PLUGIN_DIR . 'templates/storefront/branch-indicator-bar.php';
	}

	/**
	 * Hides a branch's vendor's out-of-stock-at-this-branch products from
	 * whatever product query is currently running, by adding to
	 * post__not_in rather than forcing author/post__in — that way this
	 * only ever *removes* specific products, and can never hijack an
	 * unrelated query (another vendor's store page, search results,
	 * related products) into showing just this one vendor's catalogue.
	 * Products this vendor never imported into the IPN stock model at all
	 * are left alone entirely (nothing to compare their availability
	 * against), so plain non-C&C listings are unaffected.
	 */
	public function filter_products_by_branch( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			return;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch ) {
			return;
		}

		$vendor_product_ids = IPN_Branch_Stock::get_product_ids_for_vendor( $branch->vendor_id );

		if ( ! $vendor_product_ids ) {
			return;
		}

		$in_stock_ids = IPN_Branch_Stock::get_in_stock_product_ids( $branch_id );
		$out_of_stock = array_diff( $vendor_product_ids, $in_stock_ids );

		if ( ! $out_of_stock ) {
			return;
		}

		$existing = array_map( 'intval', (array) $query->get( 'post__not_in', array() ) );
		$query->set( 'post__not_in', array_merge( $existing, array_map( 'intval', $out_of_stock ) ) );
	}

	/**
	 * Replaces WooCommerce's native "In stock"/"Out of stock" text with the
	 * selected branch's real per-branch figure — but only for products that
	 * both belong to that branch's vendor AND actually have an IPN stock
	 * row (i.e. were brought into the per-branch model, typically via
	 * catalogue import). Everything else keeps WC's normal display.
	 */
	public function filter_product_availability( $availability, $product ) {
		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			return $availability;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch || (int) $branch->vendor_id !== (int) get_post_field( 'post_author', $product->get_id() ) ) {
			return $availability;
		}

		if ( ! IPN_Branch_Stock::get_row( $product->get_id(), $branch_id ) ) {
			return $availability;
		}

		$available = IPN_Branch_Stock::get_available( $product->get_id(), $branch_id );

		return array(
			'availability' => $available > 0
				? sprintf(
					/* translators: %d: units available at the selected branch */
					_n( '%d in stock at this branch', '%d in stock at this branch', $available, 'ipn' ),
					$available
				)
				: __( 'Unavailable at this branch', 'ipn' ),
			'class' => $available > 0 ? 'in-stock' : 'out-of-stock',
		);
	}

	/**
	 * Blocks adding more of an IPN-tracked product to the cart than the
	 * selected branch actually has available. This is a UX-level guard —
	 * the real oversell protection is IPN_Branch_Stock::reserve()'s atomic
	 * guarded UPDATE at payment time, which this can't (and doesn't need
	 * to) replace.
	 */
	public function validate_branch_stock( $passed, $product_id, $quantity ) {
		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			return $passed;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch || (int) $branch->vendor_id !== (int) get_post_field( 'post_author', $product_id ) ) {
			return $passed;
		}

		if ( ! IPN_Branch_Stock::get_row( $product_id, $branch_id ) ) {
			return $passed;
		}

		$available = IPN_Branch_Stock::get_available( $product_id, $branch_id );

		if ( $quantity > $available ) {
			wc_add_notice(
				$available > 0
					? sprintf(
						/* translators: %d: units available at the selected branch */
						__( 'Sorry, only %d of this item is available at your selected branch.', 'ipn' ),
						$available
					)
					: __( 'Sorry, this item is unavailable at your selected branch.', 'ipn' ),
				'error'
			);
			return false;
		}

		return $passed;
	}
}
