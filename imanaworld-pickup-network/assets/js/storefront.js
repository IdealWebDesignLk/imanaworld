/**
 * Storefront checkout enhancement: shows/hides the nominated-recipient
 * fields when the "Someone else will collect this order for me" checkbox
 * (rendered by templates/storefront/checkout-fields.php) is toggled.
 * Enqueued only on the checkout page — see IPN_Public::enqueue_assets().
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.getElementById( 'ipn_recipient_toggle' );
		var fields = document.getElementById( 'ipn-recipient-fields' );

		if ( ! toggle || ! fields ) {
			return;
		}

		function sync() {
			fields.classList.toggle( 'ipn-recipient-fields--visible', toggle.checked );
		}

		toggle.addEventListener( 'change', sync );
		sync();
	} );
}() );
