<?php
defined( 'ABSPATH' ) || exit;

/**
 * Checkout additions (Sections 3.5–3.6): Standard/Express collection type
 * with per-branch surcharge, and the optional nominated-recipient fields.
 * Field rendering is built out below; validation and order-meta persistence
 * are Phase 3 build-out (there is no real IPN order-meta shape yet).
 */
class IPN_Checkout {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'woocommerce_after_order_notes', $this, 'render_collection_fields' );
		$loader->add_action( 'woocommerce_checkout_process', $this, 'validate_collection_fields' );
		$loader->add_action( 'woocommerce_checkout_update_order_meta', $this, 'save_collection_fields' );
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
	 * Not implemented yet — will validate that a branch is selected and,
	 * when the recipient toggle is on, that recipient name/phone are
	 * present. Order-meta persistence (below) needs to land first so
	 * there's a settled shape for what's actually being validated.
	 */
	public function validate_collection_fields() {}

	/**
	 * Not implemented yet — will persist collection type, express
	 * surcharge charged, branch id, and nominated recipient details onto
	 * the order (Phase 3 build-out).
	 */
	public function save_collection_fields( $order_id ) {}
}
