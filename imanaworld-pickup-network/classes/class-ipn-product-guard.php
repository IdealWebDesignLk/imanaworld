<?php
defined( 'ABSPATH' ) || exit;

/**
 * Keeps one vendor's hands off another vendor's products.
 *
 * Dokan's seller role already lacks the "edit others' products" capabilities,
 * so this is defence in depth: a hard check on WordPress's meta-capability
 * mapping, which every route to a product (Dokan dashboard, wp-admin, the
 * WooCommerce/Dokan REST API, AJAX handlers) ends up asking. A vendor is only
 * ever allowed to edit or delete a product whose post_author is themselves.
 *
 * Deliberately narrow: shop managers and administrators are untouched, and so
 * is a Dokan vendor-staff account (they are not "sellers", and Dokan maps them
 * onto their employer's products itself).
 */
class IPN_Product_Guard {

	const GUARDED_CAPS = array( 'edit_post', 'delete_post', 'edit_product', 'delete_product' );

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_filter( 'map_meta_cap', $this, 'guard_foreign_products', 20, 4 );
	}

	/**
	 * @param string[] $caps    Primitive capabilities required.
	 * @param string   $cap     The meta capability being checked.
	 * @param int      $user_id The user being checked.
	 * @param array    $args    Extra arguments — the first is the post ID.
	 * @return string[]
	 */
	public function guard_foreign_products( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, self::GUARDED_CAPS, true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$post = get_post( (int) $args[0] );

		// Variations carry the parent product's ownership.
		if ( $post && 'product_variation' === $post->post_type && $post->post_parent ) {
			$post = get_post( (int) $post->post_parent );
		}

		if ( ! $post || 'product' !== $post->post_type ) {
			return $caps;
		}

		if ( ! self::is_vendor_only( $user_id ) ) {
			return $caps;
		}

		if ( (int) $post->post_author !== (int) $user_id ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * A marketplace vendor with no store-wide powers of their own.
	 */
	protected static function is_vendor_only( $user_id ) {
		if ( ! function_exists( 'dokan_is_user_seller' ) || ! dokan_is_user_seller( $user_id ) ) {
			return false;
		}

		// manage_woocommerce / manage_options are primitive capabilities, so
		// asking about them does not loop back through this filter.
		return ! user_can( $user_id, 'manage_woocommerce' ) && ! user_can( $user_id, 'manage_options' );
	}
}
