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
		$loader->add_action( 'woocommerce_single_product_summary', $this, 'render_product_availability', 25 );
		$loader->add_action( 'woocommerce_product_query', $this, 'filter_products_by_branch' );
		$loader->add_filter( 'woocommerce_get_availability', $this, 'filter_product_availability', 10, 2 );
		$loader->add_filter( 'woocommerce_add_to_cart_validation', $this, 'validate_branch_stock', 10, 3 );
		$loader->add_action( 'woocommerce_check_cart_items', $this, 'check_cart_against_branch' );
		// woocommerce_check_cart_items covers the cart page, but only the
		// classic checkout template is guaranteed to run it before payment —
		// IPN_Checkout's own branch validation already relies on
		// woocommerce_checkout_process to actually stop a submission
		// (class-ipn-checkout.php), so the same hook is used here rather than
		// assuming the cart hook alone reaches checkout on every theme.
		$loader->add_action( 'woocommerce_checkout_process', $this, 'check_cart_against_branch' );
		$loader->add_action( 'woocommerce_before_add_to_cart_button', $this, 'render_branch_required_prompt' );
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
	 * Forces WooCommerce to send its session cookie with THIS response,
	 * instead of leaving it to whichever later hook normally does that.
	 *
	 * WooCommerce defers setting wp_woocommerce_session_* for a brand-new
	 * anonymous visitor until something gives it a reason to — normally a
	 * cart mutation, handled deep inside WC_Cart_Session on hooks later in
	 * the page lifecycle than wp_loaded. Every branch in
	 * maybe_handle_branch_actions() writes to WC()->session or WC()->cart
	 * and then calls wp_safe_redirect(); exit(); on wp_loaded itself — before
	 * any of those later hooks run. WC_Session_Handler::save_data() still
	 * fires on shutdown and persists the data server-side, but the cookie
	 * naming which session to load never reaches the browser, so the next
	 * request looks like a brand-new anonymous visitor and the write is
	 * orphaned. This was invisible in ordinary use because a shopper who has
	 * already viewed a page or two first arrives here with a session cookie
	 * a normal page load already set; it broke for whichever visit is
	 * genuinely this browser's first request to the store — proven live on
	 * v0.9.8 with a fresh session: the branch never stuck across the redirect.
	 *
	 * Safe to call repeatedly and a no-op once the cookie already matches.
	 */
	protected function ensure_session_cookie() {
		if ( function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
			WC()->session->set_customer_session_cookie( true );
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
					$this->set_selected_branch( $branch_id );

					// The cart used to be emptied outright on every branch
					// change, which threw away a shop the customer had built
					// even when every item was stocked at the new branch too.
					// It is kept now; check_cart_against_branch() reports
					// anything the new branch cannot fulfil and offers a way
					// out, and blocks checkout until it is resolved (#37).
				}

				$this->ensure_session_cookie();
				wp_safe_redirect( remove_query_arg( array( 'ipn_branch', 'ipn_change_branch', '_wpnonce' ) ) );
				exit;
			}
		} elseif ( isset( $_GET['ipn_cart_fix'], $_GET['_wpnonce'] ) ) {
			$fix   = sanitize_key( wp_unslash( $_GET['ipn_cart_fix'] ) );
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( wp_verify_nonce( $nonce, 'ipn_cart_fix_' . $fix ) && function_exists( 'WC' ) && WC()->cart ) {
				if ( 'clear' === $fix ) {
					WC()->cart->empty_cart();
					wc_add_notice( __( 'Your cart has been cleared. Everything you add now will be collected from your selected branch.', 'ipn' ), 'success' );
				} elseif ( 'remove_unavailable' === $fix ) {
					$removed = 0;

					foreach ( $this->unavailable_cart_items( $this->get_selected_branch_id() ) as $ipn_bad ) {
						WC()->cart->remove_cart_item( $ipn_bad['key'] );
						$removed++;
					}

					if ( $removed ) {
						wc_add_notice(
							sprintf(
								/* translators: %d: number of items removed from the cart */
								_n( '%d item that your branch cannot supply was removed from your cart.', '%d items that your branch cannot supply were removed from your cart.', $removed, 'ipn' ),
								$removed
							),
							'success'
						);
					}
				}
			}

			$this->ensure_session_cookie();
			wp_safe_redirect( remove_query_arg( array( 'ipn_cart_fix', '_wpnonce' ) ) );
			exit;
		} elseif ( isset( $_GET['ipn_change_branch'], $_GET['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( wp_verify_nonce( $nonce, 'ipn_change_branch' ) ) {
				$this->set_selected_branch( 0 );
			}

			$this->ensure_session_cookie();
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
	 * "Available at" panel on the single product page: which branches
	 * currently have this product, and how many units each has left.
	 *
	 * Renders just above the add-to-cart form for any product that's in the
	 * per-branch stock model at all. Until this existed, a shopper looking
	 * at a product had no way of telling where they could actually collect
	 * it (issue #8) — WooCommerce's own "In stock" line is a single global
	 * number that means nothing under per-branch stock. When no branch is
	 * selected yet, each row doubles as a branch picker, since a collection
	 * branch is now required before the item can go in the cart.
	 */
	public function render_product_availability() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$product_id = $product->get_id();
		$rows       = IPN_Branch_Stock::get_availability_by_branch( $product_id );

		// No rows can mean either "not a Click & Collect product" (leave the
		// page completely alone) or "tracked, but no active branch stocks it"
		// (say so) — only the second needs the extra lookup to tell apart.
		if ( ! $rows && ! IPN_Branch_Stock::is_tracked( $product_id ) ) {
			return;
		}

		$selected_branch_id = $this->get_selected_branch_id();

		include IPN_PLUGIN_DIR . 'templates/storefront/product-availability.php';
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
	 * selected branch's real per-branch figure — but only for a product that
	 * actually has an IPN stock row at that branch (i.e. was brought into
	 * the per-branch model, typically via catalogue import) and only while
	 * the branch itself is active. Everything else keeps WC's normal
	 * display, matching what get_availability_by_branch() would show.
	 */
	public function filter_product_availability( $availability, $product ) {
		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			return $availability;
		}

		$branch = IPN_Branch::get( $branch_id );

		if ( ! $branch || 'active' !== $branch->status ) {
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
	 * Whether a branch can supply a quantity of a product, as one rule that
	 * add-to-cart and the cart check both read (#37). Two screens deciding
	 * "available" separately is how a cart ends up holding something the
	 * branch was always going to refuse.
	 *
	 * Products the branch's own vendor does not own, and products outside the
	 * per-branch stock model altogether, are none of this plugin's business
	 * and come back as fine.
	 *
	 * @return array|null null when it can be supplied; otherwise 'reason'
	 *                    ('not_stocked' or 'short') and 'available'.
	 */
	protected function branch_availability_problem( $product_id, $quantity, $branch_id ) {
		if ( ! IPN_Branch_Stock::is_tracked( $product_id ) ) {
			return null;
		}

		// Whether a branch can fulfil an order for this product is decided
		// the same way IPN_Branch_Stock::get_availability_by_branch() decides
		// what to SHOW a shopper as available in the first place: an active
		// branch, and a stock row joining it to the product. That row is what
		// "this branch carries this product" actually means; there is no
		// separate vendor-ownership fact to reconcile it against.
		//
		// A vendor-match gate against get_post_field( 'post_author', ... )
		// used to sit here, and defeated itself: on a mismatch it treated the
		// pairing as "not this function's concern" and returned null — no
		// problem — which let checkout complete for a quantity the branch
		// could not supply. Reproduced live: 21 units added against a branch
		// stocking 15, order placed successfully. It never touched the
		// display path above, which is why the same order looked completely
		// normal on the product page throughout.
		$branch = IPN_Branch::get( $branch_id );

		// A missing or inactive branch fails CLOSED, not open: nothing can be
		// collected from a branch that has gone away or shut down, whatever
		// the stock table still says. get_availability_by_branch() reaches
		// the same outcome by simply excluding such a branch from its JOIN.
		if ( ! $branch || 'active' !== $branch->status ) {
			return array( 'reason' => 'not_stocked', 'available' => 0 );
		}

		// A Click & Collect product this branch holds no stock row for
		// cannot be collected here at all. It used to be waved through,
		// which is how an item reached the cart from a direct link and was
		// only refused later.
		if ( ! IPN_Branch_Stock::get_row( $product_id, $branch_id ) ) {
			return array( 'reason' => 'not_stocked', 'available' => 0 );
		}

		$available = IPN_Branch_Stock::get_available( $product_id, $branch_id );

		if ( $quantity > $available ) {
			return array( 'reason' => 'short', 'available' => (int) $available );
		}

		return null;
	}

	/**
	 * Everything in the cart the given branch cannot supply.
	 *
	 * @return array[] Each with 'key', 'name', 'quantity', 'reason', 'available'.
	 */
	public function unavailable_cart_items( $branch_id ) {
		$out = array();

		if ( ! $branch_id || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}

		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$product_id = ! empty( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$quantity   = ! empty( $item['quantity'] ) ? (int) $item['quantity'] : 0;

			if ( ! $product_id ) {
				continue;
			}

			$problem = $this->branch_availability_problem( $product_id, $quantity, $branch_id );

			if ( ! $problem ) {
				continue;
			}

			$product = isset( $item['data'] ) && $item['data'] instanceof WC_Product ? $item['data'] : wc_get_product( $product_id );

			$out[] = array(
				'key'       => $key,
				'name'      => $product ? $product->get_name() : sprintf( /* translators: %d: product ID */ __( 'Product #%d', 'ipn' ), $product_id ),
				'quantity'  => $quantity,
				'reason'    => $problem['reason'],
				'available' => $problem['available'],
			);
		}

		return $out;
	}

	/**
	 * Reports anything in the cart the selected branch cannot supply, on both
	 * the cart and the checkout (#37). Bound to two hooks: woocommerce_check_cart_items
	 * so the notice appears while browsing the cart, and woocommerce_checkout_process
	 * so a submission is actually stopped — the same hook IPN_Checkout already
	 * relies on to block a bad checkout, chosen over trusting that the cart
	 * hook alone runs ahead of every theme's checkout submission.
	 */
	public function check_cart_against_branch() {
		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			return;
		}

		$unavailable = $this->unavailable_cart_items( $branch_id );

		if ( ! $unavailable ) {
			return;
		}

		$branch = IPN_Branch::get( $branch_id );
		$names  = array();

		foreach ( $unavailable as $item ) {
			$names[] = 'short' === $item['reason'] && $item['available'] > 0
				? sprintf(
					/* translators: 1: product name, 2: units available at the branch */
					__( '%1$s (only %2$d left here)', 'ipn' ),
					$item['name'],
					$item['available']
				)
				: $item['name'];
		}

		wc_add_notice(
			sprintf(
				/* translators: 1: branch name, 2: list of product names */
				__( 'Not available at %1$s: %2$s. Please choose another product, or clear your cart and shop what this branch has.', 'ipn' ),
				$branch ? esc_html( $branch->name ) : esc_html__( 'your selected branch', 'ipn' ),
				esc_html( implode( ', ', $names ) )
			)
			. ' <a href="' . esc_url( $this->cart_fix_url( 'remove_unavailable' ) ) . '">' . esc_html__( 'Remove those items', 'ipn' ) . '</a>'
			. ' &middot; <a href="' . esc_url( $this->cart_fix_url( 'clear' ) ) . '">' . esc_html__( 'Clear my cart', 'ipn' ) . '</a>',
			'error'
		);
	}

	/**
	 * A nonce-guarded link that resolves the cart against the chosen branch.
	 *
	 * Deliberately anchored to the cart page rather than built from the
	 * current request. check_cart_against_branch() also runs on
	 * woocommerce_checkout_process, which on the block/AJAX checkout fires
	 * inside the wc-ajax=checkout endpoint — add_query_arg() with no base
	 * would point this link at that endpoint instead of a real page. The
	 * cart page is a sensible landing spot from either context: the cart
	 * gets fixed there, and checkout is one click away again.
	 */
	protected function cart_fix_url( $fix ) {
		$base = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );

		return add_query_arg(
			array(
				'ipn_cart_fix' => $fix,
				'_wpnonce'     => wp_create_nonce( 'ipn_cart_fix_' . $fix ),
			),
			$base
		);
	}

	/**
	 * Says so above the Add to Cart button when no branch has been chosen yet
	 * (#36), rather than letting the customer press it and be refused.
	 *
	 * The refusal in validate_branch_stock() is what actually enforces this —
	 * this is the part that makes the requirement visible beforehand, which
	 * matters because a themed AJAX add-to-cart can swallow a notice raised
	 * during validation and simply appear to do nothing.
	 */
	public function render_branch_required_prompt() {
		global $product;

		if ( ! $product instanceof WC_Product || $this->get_selected_branch_id() ) {
			return;
		}

		if ( ! IPN_Branch_Stock::is_tracked( $product->get_id() ) ) {
			return;
		}

		echo '<p class="ipn-branch-required">'
			. esc_html__( 'Please select a vendor branch before adding this product to your cart — pick one from the Click & Collect availability list above.', 'ipn' )
			. '</p>';
	}

	/**
	 * Blocks adding more of an IPN-tracked product to the cart than the
	 * selected branch actually has available, and — since a branch must now
	 * be chosen before a Click & Collect product can be added at all (#36) —
	 * blocks adding one before any branch is selected. This is a UX-level
	 * guard: the real oversell protection is IPN_Branch_Stock::reserve()'s
	 * atomic guarded UPDATE at payment time, which this can't (and doesn't
	 * need to) replace.
	 *
	 * The per-branch check itself is branch_availability_problem(), the same
	 * rule the cart and checkout re-check once an item is already in — see
	 * #37 — so a product that passes here cannot later turn out to have been
	 * let through by a different, disagreeing rule.
	 */
	public function validate_branch_stock( $passed, $product_id, $quantity ) {
		$branch_id = $this->get_selected_branch_id();

		if ( ! $branch_id ) {
			// "In stock" is meaningless for a Click & Collect product until
			// we know which branch it's being collected from, so the branch
			// has to be chosen before the item can go in the cart at all
			// (issue #8) — otherwise the customer only discovers the
			// requirement at checkout, with a cart already built.
			if ( IPN_Branch_Stock::is_tracked( $product_id ) ) {
				wc_add_notice(
					sprintf(
						/* translators: %s: link to the shop page, where the branch selector lives */
						__( 'Please select a vendor branch before adding this product to your cart. %s', 'ipn' ),
						'<a href="' . esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ) . '">' . esc_html__( 'Choose a branch', 'ipn' ) . '</a>'
					),
					'error'
				);
				return false;
			}

			return $passed;
		}

		$problem = $this->branch_availability_problem( $product_id, $quantity, $branch_id );

		if ( ! $problem ) {
			return $passed;
		}

		wc_add_notice(
			$problem['available'] > 0
				? sprintf(
					/* translators: %d: units available at the selected branch */
					__( 'Sorry, only %d of this item is available at your selected branch.', 'ipn' ),
					$problem['available']
				)
				: __( 'This product is not available at the selected branch. Please choose another product.', 'ipn' ),
			'error'
		);

		return false;
	}
}
