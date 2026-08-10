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

		if ( is_page() || is_singular() ) {
			global $post;
			if ( $post && has_shortcode( $post->post_content, 'ipn_staff_dashboard' ) ) {
				wp_enqueue_style( 'ipn-staff-dashboard', IPN_PLUGIN_URL . 'assets/css/staff-dashboard.css', array(), IPN_VERSION );
				wp_enqueue_script( 'ipn-staff-dashboard', IPN_PLUGIN_URL . 'assets/js/staff-dashboard.js', array( 'jquery' ), IPN_VERSION, true );
			}
		}
	}
}
