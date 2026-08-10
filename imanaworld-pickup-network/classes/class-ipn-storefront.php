<?php
defined( 'ABSPATH' ) || exit;

/**
 * Customer-facing store-first shopping experience (Section 3.1): branch
 * selector on the Choppies storefront, session-based branch persistence,
 * a persistent branch indicator/switcher across the storefront, and
 * catalogue filtering by the selected branch's stock. Phase 2 build-out.
 */
class IPN_Storefront {

	const SESSION_KEY = 'ipn_selected_branch';

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'wp_loaded', $this, 'maybe_handle_branch_actions' );
		$loader->add_action( 'woocommerce_before_main_content', $this, 'render_branch_indicator_bar' );
		$loader->add_action( 'woocommerce_before_shop_loop', $this, 'render_branch_selector' );
		$loader->add_filter( 'woocommerce_product_query_meta_query', $this, 'filter_products_by_branch' );
		$loader->add_filter( 'woocommerce_get_availability', $this, 'filter_product_availability', 10, 2 );
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

	public function filter_products_by_branch( $meta_query ) {
		return $meta_query;
	}

	/**
	 * Stub hook point for a branch stock badge / "Unavailable at this
	 * branch" state on the catalogue and single product pages (mockup:
	 * view-catalogue, view-product). Not implemented yet — once IPN's
	 * per-branch stock (IPN_Branch_Stock) replaces WooCommerce's native
	 * stock display for IPN-enabled vendor products, this is where the
	 * selected branch's availability would be checked and rendered.
	 * Left as a documented no-op rather than guessing at markup the
	 * mockup doesn't fully specify per product.
	 */
	public function filter_product_availability( $availability, $product ) {
		return $availability;
	}
}
