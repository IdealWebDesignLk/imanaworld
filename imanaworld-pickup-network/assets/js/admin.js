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
			vendorSelect.value = d.vendorId || '';
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
