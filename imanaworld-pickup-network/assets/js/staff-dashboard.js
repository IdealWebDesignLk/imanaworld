( function ( $ ) {
	'use strict';

	// Pure-UI behaviour ported from the branch-staff mockup's toggleReject().
	// Everything else in this dashboard (navigation, filters, OTP verify) is
	// a plain server-rendered link/form — no SPA router here.
	$( function () {
		$( '.ipn-staff-dashboard' ).on( 'click', '.js-ipn-toggle-reject', function () {
			var $btn   = $( this );
			var $panel = $( '#' + $btn.attr( 'aria-controls' ) );

			if ( ! $panel.length ) {
				return;
			}

			var expanded = 'true' === $btn.attr( 'aria-expanded' );

			$btn.attr( 'aria-expanded', expanded ? 'false' : 'true' );
			$panel.prop( 'hidden', expanded );
		} );
	} );
} )( jQuery );
