/**
 * Single product page: intercepts the classic Add to Cart form so a
 * vendor conflict shows as an immediate popup instead of a page reload
 * (issue #40). Enqueued only on single product pages — see
 * IPN_Public::enqueue_assets() — and localizes IPN_Vendor_Cart with the
 * AJAX URL and nonce for IPN_Single_Vendor_Cart::ajax_check_vendor_cart().
 *
 * Same vendor (or no conflict at all): the form submits normally, so the
 * add and the site's own success redirect to the cart happen exactly as
 * they always have — this script only ever intervenes on a conflict.
 *
 * If the pre-check request itself fails (network error, etc.), the form
 * is let through rather than blocked — the server-side validation in
 * IPN_Single_Vendor_Cart::validate_single_vendor() is still the real gate
 * and falls back to its own page-reload notice in that case.
 */
( function () {
	'use strict';

	if ( typeof IPN_Vendor_Cart === 'undefined' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( 'form.cart' );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( event ) {
			if ( form.dataset.ipnChecked === '1' ) {
				delete form.dataset.ipnChecked;
				return;
			}

			event.preventDefault();

			// A product's real "add-to-cart" id lives either on the submit
			// button itself (simple products: name="add-to-cart" value="{id}")
			// or a hidden input (variable products, once a variation is
			// chosen) — check both rather than assuming one shape.
			var productId = event.submitter && event.submitter.name === 'add-to-cart'
				? event.submitter.value
				: ( form.querySelector( 'input[name="add-to-cart"]' ) || {} ).value;

			var quantityField = form.querySelector( 'input[name="quantity"], input.qty' );
			var variationField = form.querySelector( 'input[name="variation_id"]' );

			// Deliberately NOT sent as add-to-cart / quantity / variation_id:
			// those are WooCommerce's own field names, and WC_Form_Handler's
			// classic add-to-cart processing runs on wp_loaded — which fires
			// for every WordPress request, including this admin-ajax.php one.
			// Using them here let WooCommerce's real handler hijack this
			// "read-only" pre-check as a genuine add (redirecting and exiting
			// before ipn_vendor_cart_check's own handler ever ran), so the
			// actual submission below could silently double up or, depending
			// on timing, never happen at all. Renamed so nothing recognizes
			// them as a real add-to-cart request.
			var formData = new FormData();
			formData.append( 'action', 'ipn_vendor_cart_check' );
			formData.append( 'nonce', IPN_Vendor_Cart.nonce );
			formData.append( 'ipn_check_product_id', productId || '' );
			formData.append( 'ipn_check_quantity', quantityField ? quantityField.value : '1' );

			if ( variationField && variationField.value ) {
				formData.append( 'ipn_check_variation_id', variationField.value );
			}

			fetch( IPN_Vendor_Cart.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					if ( result && result.success === false && result.data ) {
						showConflictPopup( result.data );
						return;
					}

					submitFormNormally( productId );
				} )
				.catch( function () {
					submitFormNormally( productId );
				} );
		} );

		function submitFormNormally( productId ) {
			// Resubmitting the form here isn't a real click, so nothing
			// guarantees the browser reconstructs a submit button's own
			// name/value pair the way it would for one — confirmed live: a
			// simple product's add-to-cart id lives only on the button
			// itself, and without it this resubmission carried no product id
			// at all, so WooCommerce's handler saw nothing to add. Adding it
			// as a hidden input (only if the form doesn't already carry one,
			// which a variable product's own variation markup already does)
			// makes the resubmission carry it regardless of how the browser
			// would otherwise have resolved the submitter.
			if ( productId && ! form.querySelector( 'input[name="add-to-cart"]' ) ) {
				var hidden = document.createElement( 'input' );
				hidden.type = 'hidden';
				hidden.name = 'add-to-cart';
				hidden.value = productId;
				form.appendChild( hidden );
			}

			form.dataset.ipnChecked = '1';

			if ( form.requestSubmit ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		}

		function showConflictPopup( data ) {
			var opener = document.activeElement;

			var overlay = document.createElement( 'div' );
			overlay.className = 'ipn-vendor-modal';
			overlay.setAttribute( 'role', 'dialog' );
			overlay.setAttribute( 'aria-modal', 'true' );

			var dialog = document.createElement( 'div' );
			dialog.className = 'ipn-vendor-modal__dialog';

			var message = document.createElement( 'p' );
			message.className = 'ipn-vendor-modal__message';
			message.textContent = data.message;

			var actions = document.createElement( 'div' );
			actions.className = 'ipn-vendor-modal__actions';

			var cancelButton = document.createElement( 'button' );
			cancelButton.type = 'button';
			cancelButton.className = 'ipn-vendor-modal__button ipn-vendor-modal__button--secondary';
			cancelButton.textContent = IPN_Vendor_Cart.cancel_label;
			cancelButton.addEventListener( 'click', close );

			var clearLink = document.createElement( 'a' );
			clearLink.className = 'ipn-vendor-modal__button ipn-vendor-modal__button--primary';
			clearLink.textContent = IPN_Vendor_Cart.clear_label;
			clearLink.href = data.clear_url;

			actions.appendChild( cancelButton );
			actions.appendChild( clearLink );
			dialog.appendChild( message );
			dialog.appendChild( actions );
			overlay.appendChild( dialog );
			document.body.appendChild( overlay );

			cancelButton.focus();

			overlay.addEventListener( 'click', function ( event ) {
				if ( event.target === overlay ) {
					close();
				}
			} );

			document.addEventListener( 'keydown', onKeydown );

			function onKeydown( event ) {
				if ( event.key === 'Escape' ) {
					close();
				}
			}

			function close() {
				document.removeEventListener( 'keydown', onKeydown );
				overlay.remove();

				if ( opener && opener.focus ) {
					opener.focus();
				}
			}
		}
	} );
}() );
