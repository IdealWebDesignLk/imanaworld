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
	function ipnOpenBranchModal( trigger, defaultVendorId ) {
		var d       = trigger ? trigger.dataset : {};
		var titleEl = document.getElementById( 'bm-title' );

		if ( titleEl ) {
			titleEl.textContent = trigger ? titleEl.getAttribute( 'data-edit-label' ) : titleEl.getAttribute( 'data-add-label' );
		}

		setVal( 'bm-id', d.id );
		setVal( 'bm-name', d.name );
		setVal( 'bm-code', d.code );
		setVal( 'bm-address', d.address );
		setVal( 'bm-phone', d.phone );
		setVal( 'bm-email', d.email );
		setVal( 'bm-gps', d.gps );
		setChecked( 'bm-active', trigger ? d.active === '1' : true );
		setVal( 'bm-reason', d.reason );

		var vendorSelect = document.getElementById( 'bm-vendor' );
		if ( vendorSelect ) {
			// Editing: always that branch's own vendor. Adding new: the
			// partner context this page is scoped to (or the only vendor
			// in use so far) — see IPN_Admin::render_branches() — so the
			// common case of adding another branch to an existing partner
			// never shows a blank "select vendor" prompt.
			vendorSelect.value = d.vendorId || ( defaultVendorId ? String( defaultVendorId ) : '' );
			ipnSyncSelectedPartner();
		}

		var hoursByDay = {};
		if ( d.hours ) {
			try {
				JSON.parse( d.hours ).forEach( function ( row ) {
					hoursByDay[ row.day_of_week ] = row;
				} );
			} catch ( e ) {
				hoursByDay = {};
			}
		}

		for ( var day = 0; day <= 6; day++ ) {
			var row = hoursByDay[ day ] || null;

			if ( trigger ) {
				// Editing: reflect whatever this branch actually has saved
				// (no row at all reads the same as "closed" — unconfigured).
				setChecked( 'hours-closed-' + day, ! row || !! Number( row.is_closed ) );
				setVal( 'hours-open-' + day, row && row.open_time ? row.open_time.substring( 0, 5 ) : '' );
				setVal( 'hours-close-' + day, row && row.close_time ? row.close_time.substring( 0, 5 ) : '' );
			} else {
				// New branch: default to a plausible retail week (open daily,
				// 08:00-19:00) rather than starting fully unconfigured.
				setChecked( 'hours-closed-' + day, false );
				setVal( 'hours-open-' + day, '08:00' );
				setVal( 'hours-close-' + day, '19:00' );
			}

			ipnSyncHoursRow( day );
		}

		ipnOpenModal( 'ipn-branch-modal-scrim' );
	}

	/**
	 * Mirrors the partner dropdown into a plain "Selected Partner: X" line, so
	 * the branch you are editing states who it belongs to rather than making
	 * you read it out of a <select>. Kept in sync on change, since the partner
	 * can be reassigned from the same control.
	 */
	function ipnSyncSelectedPartner() {
		var select = document.getElementById( 'bm-vendor' );
		var wrap   = document.getElementById( 'bm-partner-current' );
		var name   = document.getElementById( 'bm-partner-name' );

		if ( ! select || ! wrap || ! name ) {
			return;
		}

		var chosen = select.options[ select.selectedIndex ];

		if ( select.value && chosen ) {
			name.textContent = chosen.textContent.trim();
			wrap.removeAttribute( 'hidden' );
		} else {
			name.textContent = '';
			wrap.setAttribute( 'hidden', 'hidden' );
		}
	}
	window.ipnSyncSelectedPartner = ipnSyncSelectedPartner;

	/**
	 * Greys out (and stops requiring) a day's open/close time inputs while
	 * its "Closed" checkbox is ticked.
	 */
	function ipnSyncHoursRow( day ) {
		var closed = document.getElementById( 'hours-closed-' + day );
		var open   = document.getElementById( 'hours-open-' + day );
		var close  = document.getElementById( 'hours-close-' + day );

		if ( ! closed || ! open || ! close ) {
			return;
		}

		open.disabled = close.disabled = closed.checked;
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

	/* ---------------- branch closures modal ---------------- */
	function ipnOpenClosuresModal( trigger ) {
		var d      = trigger.dataset;
		var scrim  = document.getElementById( 'ipn-closures-modal-scrim' );
		var list   = document.getElementById( 'cm-list' );
		var nonce  = scrim.getAttribute( 'data-delete-nonce' );

		document.getElementById( 'cm-title' ).textContent = 'Closures — ' + d.name;
		setVal( 'cm-branch-id', d.id );

		var closures = [];
		try {
			closures = JSON.parse( d.closures );
		} catch ( e ) {
			closures = [];
		}

		list.innerHTML = '';

		if ( ! closures.length ) {
			list.innerHTML = '<div class="empty-state">No closure dates set.</div>';
		} else {
			closures.forEach( function ( closure ) {
				var row = document.createElement( 'div' );
				row.className = 'import-log-row';

				var label = document.createElement( 'span' );
				label.textContent = closure.closure_date + ( closure.reason ? ' — ' + closure.reason : '' );

				var del = document.createElement( 'a' );
				del.className = 'btn btn-ghost btn-sm';
				del.textContent = 'Delete';
				del.href = '?page=ipn-branches&ipn_delete_closure=' + encodeURIComponent( closure.id ) + '&_wpnonce=' + encodeURIComponent( nonce );

				row.appendChild( label );
				row.appendChild( del );
				list.appendChild( row );
			} );
		}

		ipnOpenModal( 'ipn-closures-modal-scrim' );
	}
	window.ipnOpenClosuresModal = ipnOpenClosuresModal;

	/* ---------------- stock adjust modal ---------------- */
	function ipnOpenStockAdjustModal( trigger ) {
		var d = trigger.dataset;

		setVal( 'sm-product-id', d.productId );
		setVal( 'sm-branch-id', d.branchId );
		setVal( 'sm-product-name', d.productName );
		setVal( 'sm-branch-name', d.branchName );
		setVal( 'sm-total', d.total );

		ipnOpenModal( 'ipn-stock-modal-scrim' );
	}
	window.ipnOpenStockAdjustModal = ipnOpenStockAdjustModal;

	/* ---------------- stock per-branch drill-down ---------------- */
	/**
	 * Expands/collapses a product's per-branch stock rows on the Stock
	 * screen. The rows are already server-rendered for the current page
	 * (one query for the whole page), so this is pure show/hide — no
	 * request, nothing to load.
	 */
	function ipnToggleStockBranches( button, detailRowId ) {
		var row = document.getElementById( detailRowId );

		if ( ! row ) {
			return;
		}

		var opening = row.hasAttribute( 'hidden' );

		if ( opening ) {
			row.removeAttribute( 'hidden' );
		} else {
			row.setAttribute( 'hidden', 'hidden' );
		}

		button.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
	}
	window.ipnToggleStockBranches = ipnToggleStockBranches;

	/* ---------------- order detail modal (Orders & Disputes / Disputes & Returns) ---------------- */
	function ipnOpenOrderModal( row ) {
		var data;

		try {
			data = JSON.parse( row.getAttribute( 'data-order' ) );
		} catch ( e ) {
			return;
		}

		var statusLabels = {
			new: 'New', accepted: 'Accepted', preparing: 'Preparing', ready: 'Ready',
			collected: 'Collected', disputed: 'Disputed', expired: 'Expired'
		};

		document.getElementById( 'ipn-om-title' ).textContent = data.order_number + ' — ' + data.customer_name;

		var chips = document.getElementById( 'ipn-om-chips' );
		chips.innerHTML = '';
		chips.appendChild( ipnChip( 'express' === data.type ? 'Express' : 'Standard', 'chip-' + data.type ) );
		chips.appendChild( ipnChip( statusLabels[ data.status ] || data.status, 'chip-' + data.status ) );
		if ( data.branch_name ) {
			chips.appendChild( ipnChip( data.branch_name, 'chip-standard' ) );
		}

		var disputeEl = document.getElementById( 'ipn-om-dispute' );
		if ( 'disputed' === data.status && data.dispute_reason ) {
			disputeEl.textContent = 'Rejected — ' + data.dispute_reason;
			disputeEl.style.display = '';
		} else {
			disputeEl.style.display = 'none';
		}

		var itemsEl = document.getElementById( 'ipn-om-items' );
		itemsEl.innerHTML = '';
		( data.items || [] ).forEach( function ( item ) {
			var row2 = document.createElement( 'div' );
			row2.className = 'import-log-row';
			row2.innerHTML = '<span></span><span></span>';
			row2.children[ 0 ].textContent = item.name;
			row2.children[ 1 ].textContent = '×' + item.qty;
			itemsEl.appendChild( row2 );
		} );

		var recipientEl = document.getElementById( 'ipn-om-recipient' );
		if ( data.recipient ) {
			recipientEl.innerHTML = '<div class="panel-title" style="margin-bottom:8px;">Nominated recipient</div>';
			[ [ 'Name', data.recipient.name ], [ 'Phone', data.recipient.phone ], [ 'ID number', data.recipient.id_number ] ].forEach( function ( pair ) {
				var row2 = document.createElement( 'div' );
				row2.className = 'import-log-row';
				row2.innerHTML = '<span></span><span></span>';
				row2.children[ 0 ].textContent = pair[ 0 ];
				row2.children[ 1 ].textContent = pair[ 1 ] || '—';
				recipientEl.appendChild( row2 );
			} );
			recipientEl.style.display = '';
		} else {
			recipientEl.style.display = 'none';
		}

		var auditEl = document.getElementById( 'ipn-om-audit' );
		auditEl.innerHTML = '';
		if ( ! data.audit || ! data.audit.length ) {
			auditEl.innerHTML = '<div class="empty-state">No audit events yet.</div>';
		} else {
			data.audit.forEach( function ( entry ) {
				var row2 = document.createElement( 'div' );
				row2.className = 'audit-item';
				row2.innerHTML = '<div class="audit-dot"></div><div><div class="audit-text"></div><div class="audit-meta"></div></div>';
				row2.querySelector( '.audit-text' ).textContent = entry.text;
				row2.querySelector( '.audit-meta' ).textContent = entry.time;
				auditEl.appendChild( row2 );
			} );
		}

		var editLink = document.getElementById( 'ipn-om-edit-link' );
		if ( editLink && data.edit_url ) {
			editLink.href = data.edit_url;
		}

		ipnOpenModal( 'ipn-order-modal-scrim' );
	}

	function ipnChip( text, className ) {
		var span       = document.createElement( 'span' );
		span.className = 'chip ' + className;
		span.textContent = text;
		return span;
	}

	/**
	 * Drives the status filter from the counter tiles above the Orders
	 * table, so "3 New" is something you can click rather than a number you
	 * then have to go and reproduce in the dropdown.
	 */
	function ipnSetOrderStatusFilter( status ) {
		var select = document.getElementById( 'ipn-orders-status-filter' );

		if ( ! select ) {
			return;
		}

		select.value = status;
		select.dispatchEvent( new Event( 'change' ) );
	}
	window.ipnSetOrderStatusFilter = ipnSetOrderStatusFilter;

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
	window.ipnSyncHoursRow    = ipnSyncHoursRow;
	window.ipnBindFilters     = ipnBindFilters;

	$( function () {
		// Any button/link that only has a visual affordance so far shows a
		// toast instead of silently doing nothing or faking a save.
		var ipnPartnerSelect = document.getElementById( 'bm-vendor' );
		if ( ipnPartnerSelect ) {
			ipnPartnerSelect.addEventListener( 'change', ipnSyncSelectedPartner );
		}

		document.querySelectorAll( '[data-ipn-toast]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				ipnShowToast( el.getAttribute( 'data-ipn-toast' ) );
			} );
		} );

		// Orders & Disputes / Disputes & Returns: click a row to open its detail modal.
		document.querySelectorAll( '[data-order]' ).forEach( function ( row ) {
			row.addEventListener( 'click', function () {
				ipnOpenOrderModal( row );
			} );
		} );

		// All orders: search + status filter over server-rendered rows.
		// (Branch is filtered server-side — see templates/admin/orders.php.)
		ipnBindFilters( 'ipn-orders-tbody', 'tr[data-ipn-row]', {
			searchInputId: 'ipn-orders-search',
			selects: [ { id: 'ipn-orders-status-filter', attr: 'status' } ],
			emptyHtml: '<tr class="ipn-filter-empty"><td colspan="7"><div class="empty-state">No orders match.</div></td></tr>'
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
