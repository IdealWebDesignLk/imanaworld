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

			var formData = new FormData( form );

			// A simple product's "add-to-cart" field lives only on the submit
			// button itself (name="add-to-cart" value="{id}"), not a hidden
			// input — FormData(form) alone only picks that up on browsers new
			// enough to support the FormData(form, submitter) constructor, so
			// it's added explicitly here to work regardless.
			if ( event.submitter && event.submitter.name && ! formData.has( event.submitter.name ) ) {
				formData.append( event.submitter.name, event.submitter.value );
			}

			formData.append( 'action', 'ipn_vendor_cart_check' );
			formData.append( 'nonce', IPN_Vendor_Cart.nonce );

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

					submitFormNormally();
				} )
				.catch( function () {
					submitFormNormally();
				} );
		} );

		function submitFormNormally() {
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
