<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shared front-end asset loading for storefront, checkout, My Account,
 * and the staff dashboard shortcode.
 */
class IPN_Public {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_assets' );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'ipn-storefront', IPN_PLUGIN_URL . 'assets/css/storefront.css', array(), IPN_VERSION );

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			wp_enqueue_script( 'ipn-storefront', IPN_PLUGIN_URL . 'assets/js/storefront.js', array( 'jquery' ), IPN_VERSION, true );
		}

		// The single-vendor-cart popup (issue #40) only has anything to do on
		// a single product page — its own Add to Cart form is what it hooks.
		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_script( 'ipn-single-vendor-cart', IPN_PLUGIN_URL . 'assets/js/single-vendor-cart.js', array(), IPN_VERSION, true );

			wp_localize_script(
				'ipn-single-vendor-cart',
				'IPN_Vendor_Cart',
				array(
					'ajax_url'     => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'ipn_vendor_cart_check' ),
					'clear_label'  => __( 'Clear Cart & Continue', 'ipn' ),
					'cancel_label' => __( 'Cancel', 'ipn' ),
				)
			);
		}

		// Dokan's vendor dashboard, where IPN adds its "Click & Collect"
		// section. Guarded on the function existing so a site running IPN
		// without Dokan's dashboard doesn't fatal here.
		if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
			wp_enqueue_style( 'ipn-vendor-dashboard', IPN_PLUGIN_URL . 'assets/css/vendor-dashboard.css', array(), IPN_VERSION );
		}

		if ( is_page() || is_singular() ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'ipn_staff_dashboard' ) ) {
				wp_enqueue_style( 'ipn-staff-dashboard', IPN_PLUGIN_URL . 'assets/css/staff-dashboard.css', array(), IPN_VERSION );
				wp_enqueue_script( 'ipn-staff-dashboard', IPN_PLUGIN_URL . 'assets/js/staff-dashboard.js', array( 'jquery' ), IPN_VERSION, true );

				// The admin's chosen colour, as a redefinition of the custom
				// properties the stylesheet is written against. Appended after
				// it, so equal specificity resolves on source order.
				$ipn_palette = IPN_Theme::css_variables();

				if ( '' !== $ipn_palette ) {
					wp_add_inline_style( 'ipn-staff-dashboard', $ipn_palette );
				}
			}
		}
	}
}
