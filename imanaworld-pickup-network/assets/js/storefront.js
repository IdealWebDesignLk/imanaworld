/**
 * Storefront checkout enhancements. Enqueued only on the checkout page —
 * see IPN_Public::enqueue_assets().
 *
 * 1. Shows/hides the nominated-recipient fields when the "Someone else will
 *    collect this order for me" checkbox (checkout-fields.php) is toggled.
 * 2. Triggers WooCommerce's own checkout total refresh when the customer
 *    switches Standard/Express, so IPN_Checkout::add_express_surcharge()
 *    (hooked on woocommerce_cart_calculate_fees) can add or remove the
 *    branch's express fee live, without a full page reload.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.getElementById( 'ipn_recipient_toggle' );
		var fields = document.getElementById( 'ipn-recipient-fields' );

		if ( toggle && fields ) {
			var sync = function () {
				fields.classList.toggle( 'ipn-recipient-fields--visible', toggle.checked );
			};

			toggle.addEventListener( 'change', sync );
			sync();
		}

		var collectionRadios = document.querySelectorAll( 'input[name="ipn_collection_type"]' );

		if ( collectionRadios.length && window.jQuery ) {
			collectionRadios.forEach( function ( radio ) {
				radio.addEventListener( 'change', function () {
					window.jQuery( document.body ).trigger( 'update_checkout' );
				} );
			} );
		}
	} );
}() );
