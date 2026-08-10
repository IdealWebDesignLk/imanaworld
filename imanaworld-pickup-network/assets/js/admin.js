/**
 * IPN admin panel behaviour — ported from the approved mockup
 * (UI for IPN/admin/index.html). Only pure client-side UI behaviour lives
 * here: modal open/close, toasts, and filtering of already server-rendered
 * table rows. There is no mock data here — every table row on screen was
 * rendered by PHP from real data (or is an honest empty state).
 */
( function ( $ ) {
	'use strict';

	/* ---------------- toast ---------------- */
	var toastTimer;

	function ipnToastEl() {
		var wrap = document.querySelector( '.ipn-admin' );
		if ( ! wrap ) {
			return null;
		}
		var el = wrap.querySelector( '.ipn-toast' );
		if ( ! el ) {
			el = document.createElement( 'div' );
			el.className = 'toast ipn-toast';
			wrap.appendChild( el );
		}
		return el;
	}

	function ipnShowToast( msg ) {
		var el = ipnToastEl();
		if ( ! el ) {
			return;
		}
		el.textContent = msg;
		el.classList.add( 'show' );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () {
			el.classList.remove( 'show' );
		}, 2800 );
	}

	/* ---------------- generic modal open/close ---------------- */
	function ipnOpenModal( id ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.classList.add( 'show' );
		}
	}

	function ipnCloseModal( id ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.classList.remove( 'show' );
		}
	}

	// Click on the scrim backdrop (not the modal card itself) closes it.
	document.addEventListener( 'click', function ( e ) {
		if ( e.target && e.target.classList && e.target.classList.contains( 'modal-scrim' ) ) {
			e.target.classList.remove( 'show' );
		}
	} );

	// Escape closes any open modal.
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) {
			return;
		}
		document.querySelectorAll( '.modal-scrim.show' ).forEach( function ( el ) {
			el.classList.remove( 'show' );
		} );
	} );

	/* ---------------- branch add/edit modal ---------------- */
	function ipnOpenBranchModal( trigger ) {
		var d       = trigger ? trigger.dataset : {};
		var titleEl = document.getElementById( 'bm-title' );

		if ( titleEl ) {
			titleEl.textContent = trigger ? titleEl.getAttribute( 'data-edit-label' ) : titleEl.getAttribute( 'data-add-label' );
		}

		setVal( 'bm-name', d.name );
		setVal( 'bm-code', d.code );
		setVal( 'bm-address', d.address );
		setVal( 'bm-phone', d.phone );
		setVal( 'bm-email', d.email );
		setVal( 'bm-gps', d.gps );
		setChecked( 'bm-active', d.active === '1' );
		setVal( 'bm-reason', d.reason );

		ipnOpenModal( 'ipn-branch-modal-scrim' );
	}

	function setVal( id, val ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.value = val || '';
		}
	}

	function setChecked( id, checked ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.checked = !! checked;
		}
	}

	/* ---------------- table / list filtering ---------------- */
	/**
	 * Filters already-rendered rows inside a container. Rows opt in with
	 * data-ipn-row="1" and expose their searchable/filterable values via
	 * data-search plus whatever data-* attributes the select filters target.
	 */
	function ipnBindFilters( containerId, rowSelector, opts ) {
		var container = document.getElementById( containerId );
		if ( ! container || 0 === container.querySelectorAll( rowSelector ).length ) {
			// Nothing to filter yet (e.g. the underlying data source isn't
			// implemented yet) — leave the search/filter inputs inert rather
			// than layering a "no results" message onto an existing
			// "not implemented" placeholder row.
			return;
		}

		var searchInput = opts.searchInputId ? document.getElementById( opts.searchInputId ) : null;
		var selects      = ( opts.selects || [] ).map( function ( s ) {
			return { el: document.getElementById( s.id ), attr: s.attr };
		} ).filter( function ( s ) {
			return !! s.el;
		} );

		function apply() {
			var rows    = container.querySelectorAll( rowSelector );
			var visible = 0;
			var q       = searchInput ? searchInput.value.trim().toLowerCase() : '';

			rows.forEach( function ( row ) {
				var match = true;

				if ( q ) {
					var haystack = ( row.getAttribute( 'data-search' ) || row.textContent || '' ).toLowerCase();
					if ( -1 === haystack.indexOf( q ) ) {
						match = false;
					}
				}

				selects.forEach( function ( s ) {
					var val = s.el.value;
					if ( match && val && 'all' !== val && row.getAttribute( 'data-' + s.attr ) !== val ) {
						match = false;
					}
				} );

				row.style.display = match ? '' : 'none';
				if ( match ) {
					visible++;
				}
			} );

			var emptyRow = container.querySelector( '.ipn-filter-empty' );
			if ( 0 === visible ) {
				if ( ! emptyRow && opts.emptyHtml ) {
					container.insertAdjacentHTML( 'beforeend', opts.emptyHtml );
				}
			} else if ( emptyRow ) {
				emptyRow.remove();
			}
		}

		if ( searchInput ) {
			searchInput.addEventListener( 'input', apply );
		}
		selects.forEach( function ( s ) {
			s.el.addEventListener( 'change', apply );
		} );
	}

	/* ---------------- expose ---------------- */
	window.ipnShowToast       = ipnShowToast;
	window.ipnOpenModal       = ipnOpenModal;
	window.ipnCloseModal      = ipnCloseModal;
	window.ipnOpenBranchModal = ipnOpenBranchModal;
	window.ipnBindFilters     = ipnBindFilters;

	$( function () {
		// Any button/link that only has a visual affordance so far shows a
		// toast instead of silently doing nothing or faking a save.
		document.querySelectorAll( '[data-ipn-toast]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				ipnShowToast( el.getAttribute( 'data-ipn-toast' ) );
			} );
		} );

		// Stock overview: search + branch filter over server-rendered rows.
		ipnBindFilters( 'ipn-stock-tbody', 'tr[data-ipn-row]', {
			searchInputId: 'ipn-stock-search',
			selects: [ { id: 'ipn-stock-branch-filter', attr: 'branch' } ],
			emptyHtml: '<tr class="ipn-filter-empty"><td colspan="6"><div class="empty-state">No matching stock rows.</div></td></tr>'
		} );

		// All orders: search + status filter over server-rendered rows.
		ipnBindFilters( 'ipn-orders-tbody', 'tr[data-ipn-row]', {
			searchInputId: 'ipn-orders-search',
			selects: [ { id: 'ipn-orders-status-filter', attr: 'status' } ],
			emptyHtml: '<tr class="ipn-filter-empty"><td colspan="6"><div class="empty-state">No orders match.</div></td></tr>'
		} );

		// Audit trail: branch + event type filter over server-rendered rows.
		ipnBindFilters( 'ipn-audit-panel', '.audit-item[data-ipn-row]', {
			selects: [
				{ id: 'ipn-audit-branch-filter', attr: 'branch' },
				{ id: 'ipn-audit-type-filter', attr: 'type' }
			],
			emptyHtml: '<div class="empty-state ipn-filter-empty">No matching events.</div>'
		} );
	} );
} )( jQuery );
