/* global iftpBtnEntries, iftpBtnEntriesStore */
// Entries: the in-place AJAX actions (bulk, new payment, payment email, columns) and the UI around them, on the
// list and on the single entry page. Sort, filter and pagination stay plain GET navigations.
( function () {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	var i18n = iftpBtnEntries.i18n;

	// Pagination is a full reload, so I keep the selection in sessionStorage to carry it across pages.
	var selectionStorageKey = 'iftpBtnEntriesSelectedRefs';
	var statusStorageKey = 'iftpBtnEntriesSelectedStatus';
	// Tells the next page (entries-restore.js reads it) that it was reached from this one.
	var navLoadingStorageKey = 'iftpBtnEntriesNavLoading';

	var selectedRefsSet = new Set( loadPersistedSelection() );
	// ref => status for the selected rows, so the bulk bar knows statuses on pages that aren't showing.
	var selectedStatus = loadPersistedStatus();
	var loadingTimer = 0;

	document.addEventListener( 'DOMContentLoaded', function () {
		var entry = document.querySelector( '[data-iftp-entry]' );

		bindCopy( document );

		if ( entry ) {
			bindSingleEntry( entry );
			return;
		}

		var list = document.querySelector( '[data-iftp-entries]' );

		if ( ! list ) {
			return;
		}

		enhance( list );
		bindSelection( list );
		bindSelectionCancel( list );
		bindRowDelete( list );
		bindBulkActions( list );
		bindNewEntry( list );
		bindInsights( list );
		bindReminders( list );
		bindEditor( list );
		bindEmailSettings( list );
		bindScrollButton( list );
		bindColumnsMenu( list );
		bindExportMenu( list );
		bindFilterPanel( list );
		bindPerPage( list );
		// Last, so it sees defaultPrevented from every other click/submit handler.
		bindNavLoading( list );

		restoreSelectionState( list );
	} );

	// The server renders the no-JS state; from here on the JS-only tools exist.
	function enhance( wrap ) {
		wrap.classList.remove( 'iftp-btn-entries--no-js' );

		wrap.querySelectorAll( '[data-iftp-filters-toggle], [data-iftp-columns-trigger], [data-iftp-export-trigger]' ).forEach( function ( tool ) {
			tool.hidden = false;
		} );
	}

	// The loader only shows after 150ms, so a fast request never flashes it. 'nav' means the page is about to change.
	function setLoading( wrap, loading, kind ) {
		var body = wrap.querySelector( '[data-iftp-entries-body]' );
		var loader = wrap.querySelector( '[data-iftp-loader]' );

		window.clearTimeout( loadingTimer );

		if ( body ) {
			body.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		}

		if ( ! loading ) {
			wrap.removeAttribute( 'data-iftp-loading' );

			if ( loader ) {
				loader.hidden = true;
			}

			return;
		}

		wrap.setAttribute( 'data-iftp-loading', kind || 'ajax' );

		loadingTimer = window.setTimeout(
			function () {
				if ( loader ) {
					loader.hidden = false;
				}
			},
			'nav' === kind ? 0 : 150
		);
	}

	// Sort, filter, tab and pagination are full page loads — I show the loader until the next page paints.
	function bindNavLoading( wrap ) {
		var currentPage = new URL( window.location.href ).searchParams.get( 'page' );

		function startNav() {
			setLoading( wrap, true, 'nav' );

			try {
				sessionStorage.setItem( navLoadingStorageKey, '1' );
			} catch ( e ) {
				// The next page just doesn't know it was navigated to.
			}
		}

		function isViewUrl( url, pageSlug ) {
			return url.origin === window.location.origin && url.pathname === window.location.pathname && pageSlug === currentPage;
		}

		wrap.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( 'a[href]' );

			if ( ! link || event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
				return;
			}

			if ( ( link.target && '_self' !== link.target ) || link.hasAttribute( 'download' ) || '#' === link.getAttribute( 'href' ).charAt( 0 ) ) {
				return;
			}

			var url = new URL( link.href, window.location.href );

			if ( isViewUrl( url, url.searchParams.get( 'page' ) ) && ! wrap.hasAttribute( 'data-iftp-bulk-busy' ) ) {
				startNav();
			}
		} );

		wrap.addEventListener( 'submit', function ( event ) {
			var form = event.target;
			var page = form.querySelector( '[name="page"]' );

			if ( event.defaultPrevented || 'get' !== form.method || ! page ) {
				return;
			}

			if ( isViewUrl( new URL( form.action, window.location.href ), page.value ) ) {
				startNav();
			}
		} );

		// Back/forward cache brings the page back exactly as it left — mid-load.
		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) {
				setLoading( wrap, false );
			}
		} );
	}

	// Counts in the site's thousands separator, like number_format_i18n() on the PHP side.
	function formatCount( value ) {
		var sep = iftpBtnEntries.numberFormat ? iftpBtnEntries.numberFormat.thousands : ',';

		return String( parseInt( value, 10 ) || 0 ).replace( /\B(?=(\d{3})+(?!\d))/g, function () {
			return sep;
		} );
	}

	function submitForm( form ) {
		if ( 'function' === typeof form.requestSubmit ) {
			form.requestSubmit(); // runs native validation first
		} else {
			form.submit();
		}
	}

	function post( action, data ) {
		var body = new URLSearchParams();

		body.set( 'action', action );
		body.set( 'nonce', iftpBtnEntries.nonce );

		Object.keys( data ).forEach( function ( key ) {
			if ( Array.isArray( data[ key ] ) ) {
				data[ key ].forEach( function ( value ) {
					body.append( key + '[]', value );
				} );
			} else if ( null !== data[ key ] && undefined !== data[ key ] ) {
				body.set( key, data[ key ] );
			}
		} );

		return fetch( iftpBtnEntries.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	function errorOf( response, fallback ) {
		return ( response && response.data && response.data.message ) || fallback || i18n.genericError;
	}

	// Table cells can't use CSS order, so I move the real cells. Check + Reference stay first.
	function applyColumnOrder( wrap, order ) {
		wrap.querySelectorAll( '.iftp-btn-table thead tr, .iftp-btn-table [data-iftp-row]' ).forEach( function ( row ) {
			var cells = {};

			Array.prototype.forEach.call( row.children, function ( cell ) {
				cells[ cell.getAttribute( 'data-col' ) ] = cell;
			} );

			order.forEach( function ( slug ) {
				if ( cells[ slug ] ) {
					row.appendChild( cells[ slug ] );
				}
			} );
		} );
	}

	// The cells carry `hidden` themselves, so a toggle works with no stylesheet at all.
	function applyHiddenColumns( wrap, hidden ) {
		wrap.querySelectorAll( '.iftp-btn-table [data-col]' ).forEach( function ( cell ) {
			if ( 'ref' !== cell.getAttribute( 'data-col' ) ) {
				cell.hidden = -1 !== hidden.indexOf( cell.getAttribute( 'data-col' ) );
			}
		} );
	}

	function attrList( wrap, name ) {
		return ( wrap.getAttribute( name ) || '' ).split( ' ' ).filter( Boolean );
	}

	// Every AJAX action answers with a re-rendered view, swapped in here instead of a reload.
	function applyViewResponse( wrap, data ) {
		var body = wrap.querySelector( '[data-iftp-entries-body]' );
		var pagination = wrap.querySelector( '[data-iftp-pagination]' );
		var totalCount = wrap.querySelector( '[data-iftp-total-count]' );
		var summary = wrap.querySelector( '[data-iftp-summary]' );

		if ( body && 'string' === typeof data.rowsHtml ) {
			wrap.setAttribute( 'data-iftp-ajaxed', '' );
			body.innerHTML = data.rowsHtml;
			// A column save may still be in flight, so the server's rows can be in the old order.
			applyColumnOrder( wrap, attrList( wrap, 'data-iftp-col-order' ) );
			applyHiddenColumns( wrap, attrList( wrap, 'data-iftp-hidden-cols' ) );
		}

		if ( pagination && 'string' === typeof data.paginationHtml ) {
			pagination.innerHTML = data.paginationHtml;
		}

		if ( summary && 'string' === typeof data.summaryHtml ) {
			summary.innerHTML = data.summaryHtml;
			syncPeriodDeltas( wrap );
		}

		if ( totalCount && 'string' === typeof data.totalLabel ) {
			totalCount.textContent = data.totalLabel;
		}

		if ( totalCount && 'number' === typeof data.total ) {
			totalCount.setAttribute( 'data-iftp-total', data.total );
		}

		if ( 'number' === typeof data.paged ) {
			syncPaged( data.paged );
		}

		// Re-check the rows still in the cross-page selection rather than clearing it.
		restoreSelectionState( wrap );
	}

	// Emptying the last page makes the server fall back a page, so the URL and my next posts follow it.
	function syncPaged( paged ) {
		var url = new URL( window.location.href );

		iftpBtnEntries.currentFilters.paged = paged;

		if ( paged > 1 ) {
			url.searchParams.set( 'paged', paged );
		} else {
			url.searchParams.delete( 'paged' );
		}

		if ( url.href !== window.location.href ) {
			window.history.replaceState( window.history.state, '', url.href );
		}
	}

	// prefix is '' or 'view_', matching ViewState::from_post().
	function currentViewParams( prefix ) {
		var f = iftpBtnEntries.currentFilters;
		var params = {};

		// Always view_status: a bare 'status' would clash with the bulk status change's target status.
		params.view_status = f.status;
		[ 's', 'date_from', 'date_to', 'pay_method', 'amount_min', 'amount_max', 'orderby', 'order', 'per_page', 'paged' ].forEach( function ( key ) {
			params[ prefix + key ] = null === f[ key ] || undefined === f[ key ] ? '' : f[ key ];
		} );

		return params;
	}

	// ---- Selection ----

	function loadPersistedSelection() {
		try {
			var refs = JSON.parse( sessionStorage.getItem( selectionStorageKey ) || '[]' );

			return Array.isArray( refs ) ? refs : [];
		} catch ( e ) {
			return [];
		}
	}

	function loadPersistedStatus() {
		try {
			var map = JSON.parse( sessionStorage.getItem( statusStorageKey ) || '{}' );

			return map && 'object' === typeof map ? map : {};
		} catch ( e ) {
			return {};
		}
	}

	function persistSelection() {
		var statuses = {};

		// Pruned to the selection, so it can't grow past it.
		selectedRefsSet.forEach( function ( ref ) {
			if ( selectedStatus[ ref ] ) {
				statuses[ ref ] = selectedStatus[ ref ];
			}
		} );

		selectedStatus = statuses;

		try {
			sessionStorage.setItem( selectionStorageKey, JSON.stringify( Array.from( selectedRefsSet ) ) );
			sessionStorage.setItem( statusStorageKey, JSON.stringify( selectedStatus ) );
		} catch ( e ) {
			// No storage, so the selection just won't survive a page turn.
		}
	}

	function visibleRowCheckboxes( wrap ) {
		return Array.prototype.slice.call( wrap.querySelectorAll( '[data-iftp-row-check]' ) );
	}

	// Only drops the refs an action consumed, so a single-row delete never wipes a cross-page selection.
	function removeRefsFromSelection( refs ) {
		var changed = false;

		refs.forEach( function ( ref ) {
			if ( selectedRefsSet.delete( ref ) ) {
				changed = true;
			}
		} );

		if ( changed ) {
			persistSelection();
		}
	}

	function selectRow( checkbox ) {
		selectedRefsSet.add( checkbox.value );
		selectedStatus[ checkbox.value ] = checkbox.getAttribute( 'data-iftp-status' ) || '';
	}

	// Freshly rendered rows start unchecked, so I re-sync them (and select-all) to the stored selection.
	// Their statuses are the freshest I have, so they overwrite what I stored.
	function restoreSelectionState( wrap ) {
		var checkboxes = visibleRowCheckboxes( wrap );
		var statusChanged = false;
		var selectAll = wrap.querySelector( '[data-iftp-select-all]' );

		checkboxes.forEach( function ( checkbox ) {
			checkbox.checked = selectedRefsSet.has( checkbox.value );

			if ( checkbox.checked && selectedStatus[ checkbox.value ] !== checkbox.getAttribute( 'data-iftp-status' ) ) {
				selectRow( checkbox );
				statusChanged = true;
			}

			checkbox.closest( '[data-iftp-row]' ).classList.toggle( 'iftp-btn-table__row--selected', checkbox.checked );
		} );

		if ( statusChanged ) {
			persistSelection();
		}

		if ( selectAll ) {
			selectAll.checked = checkboxes.length > 0 && checkboxes.every( function ( checkbox ) {
				return checkbox.checked;
			} );
		}

		refreshSelectionUI( wrap );
	}

	function bindSelection( wrap ) {
		var selectAll = wrap.querySelector( '[data-iftp-select-all]' );

		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				visibleRowCheckboxes( wrap ).forEach( function ( checkbox ) {
					if ( selectAll.checked ) {
						selectRow( checkbox );
					} else {
						selectedRefsSet.delete( checkbox.value );
					}
				} );

				persistSelection();
				restoreSelectionState( wrap );
			} );
		}

		wrap.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches( '[data-iftp-row-check]' ) ) {
				return;
			}

			if ( event.target.checked ) {
				selectRow( event.target );
			} else {
				selectedRefsSet.delete( event.target.value );
			}

			persistSelection();
			restoreSelectionState( wrap );
		} );
	}

	// Both Cancel buttons hide themselves once the selection clears, so focus moves to select-all.
	function bindSelectionCancel( wrap ) {
		wrap.querySelectorAll( '[data-iftp-selection-cancel]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var selectAll = wrap.querySelector( '[data-iftp-select-all]' );

				selectedRefsSet.clear();
				persistSelection();
				restoreSelectionState( wrap );

				if ( selectAll ) {
					selectAll.focus();
				}
			} );
		} );
	}

	function refreshSelectionUI( wrap ) {
		var bar = wrap.querySelector( '[data-iftp-bulk-actions]' );
		var tabs = wrap.querySelector( '.iftp-btn-tabs' );
		var range = wrap.querySelector( '[data-iftp-total-count]' );
		var summary = wrap.querySelector( '[data-iftp-selection-summary]' );
		var text = wrap.querySelector( '[data-iftp-selection-text]' );
		var bulkCount = wrap.querySelector( '[data-iftp-bulk-count]' );
		var count = selectedRefsSet.size;
		var hasSelection = count > 0;
		var total = range ? parseInt( range.getAttribute( 'data-iftp-total' ) || '0', 10 ) : 0;

		wrap.toggleAttribute( 'data-iftp-has-selection', hasSelection );

		// The bulk bar takes the tabs' place while something is selected.
		if ( bar ) {
			bar.hidden = ! hasSelection;
		}

		if ( tabs ) {
			tabs.hidden = hasSelection;
		}

		if ( bulkCount ) {
			bulkCount.textContent = i18n.bulkSelectedCount.replace( '%s', formatCount( count ) );
		}

		refreshBulkOptions( wrap );

		if ( range ) {
			range.hidden = hasSelection;
		}

		if ( summary ) {
			summary.hidden = ! hasSelection;
		}

		if ( text ) {
			text.textContent = i18n.selectedOfTotal.replace( '%1$s', formatCount( count ) ).replace( '%2$s', formatCount( total ) );
		}
	}

	// "Send payment email" only when every selected entry is pending; Delete when at least one isn't paid.
	// The server re-checks each one anyway.
	function refreshBulkOptions( wrap ) {
		var refs = Array.from( selectedRefsSet );

		setBulkOption(
			wrap.querySelector( '[data-iftp-bulk-email]' ),
			refs.length > 0 && refs.every( function ( ref ) {
				return 'pending' === selectedStatus[ ref ];
			} ),
			true
		);

		setBulkOption( wrap.querySelector( '[data-iftp-bulk-delete]' ), deletableRefs( refs ).length > 0, false );
	}

	// Safari ignores hidden on <option>, so disabled keeps it unpickable there.
	function setBulkOption( option, available, hideWhenOff ) {
		if ( ! option ) {
			return;
		}

		option.disabled = ! available;

		if ( hideWhenOff ) {
			option.hidden = ! available;
		}

		if ( ! available && option.selected ) {
			option.parentNode.value = '';
		}
	}

	// Paid entries are payment history: the server won't delete them, so I don't send them.
	function deletableRefs( refs ) {
		return refs.filter( function ( ref ) {
			return 'paid' !== selectedStatus[ ref ];
		} );
	}

	// ---- Bulk actions ----

	function bindRowDelete( wrap ) {
		wrap.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-iftp-row-delete]' );

			if ( ! trigger ) {
				return;
			}

			confirmAction( i18n.confirmDeleteOneTitle, i18n.confirmDeleteMessage, i18n.confirmDeleteButton, true, function () {
				runBulk( wrap, 'iftp_btn_delete_entries', [ trigger.getAttribute( 'data-iftp-row-delete' ) ], {}, {
					progress: i18n.bulkDeleting,
					done: i18n.deleteToastSuccess,
					error: i18n.deleteError,
				} );
			} );
		} );
	}

	// Posts refs a chunk at a time, re-rendering after each so the table and counts move as it goes.
	// On an error I stop, leaving the rest selected. Resolves to whether every chunk went through.
	function runBulk( wrap, action, refs, extra, text ) {
		var size = Math.max( 1, parseInt( iftpBtnEntries.bulkChunkSize, 10 ) || 250 );
		var chunks = [];
		var done = 0;

		for ( var i = 0; i < refs.length; i += size ) {
			chunks.push( refs.slice( i, i + size ) );
		}

		function progress() {
			// One request finishes too fast for a progress message to be worth showing.
			if ( chunks.length > 1 ) {
				showToast( text.progress.replace( '%1$s', formatCount( done ) ).replace( '%2$s', formatCount( refs.length ) ), false, true );
			}
		}

		function fail( message ) {
			if ( done > 0 ) {
				message = i18n.bulkPartialError.replace( '%1$s', formatCount( done ) ).replace( '%2$s', formatCount( refs.length ) );
			}

			showToast( message || text.error, true );

			return false;
		}

		setLoading( wrap, true );
		progress();

		return chunks
			.reduce( function ( chain, chunk ) {
				return chain.then( function ( ok ) {
					if ( ! ok ) {
						return false;
					}

					return post( action, Object.assign( { refs: JSON.stringify( chunk ) }, extra, currentViewParams( '' ) ) ).then( function ( response ) {
						if ( ! response || ! response.success ) {
							return fail( errorOf( response, text.error ) );
						}

						done += chunk.length;
						removeRefsFromSelection( chunk );
						applyViewResponse( wrap, response.data );
						progress();

						return true;
					} );
				} );
			}, Promise.resolve( true ) )
			.then(
				function ( ok ) {
					if ( ok ) {
						showToast( text.done );
					}

					return ok;
				},
				function () {
					return fail();
				}
			)
			.then( function ( ok ) {
				setLoading( wrap, false );

				return ok;
			} );
	}

	// Its own runner: a request may hand back fewer refs than I sent (it ran out of time), and the outcome is a
	// per-ref tally. Sent and skipped refs leave the selection; failed ones stay, so Apply again retries just those.
	function sendPaymentEmails( wrap, refs ) {
		var size = Math.max( 1, parseInt( iftpBtnEntries.emailChunkSize, 10 ) || 10 );
		var queue = refs.slice();
		var tally = { sent: 0, skipped: 0, failed: 0 };

		function handled() {
			return tally.sent + tally.skipped + tally.failed;
		}

		function progress() {
			showToast( i18n.bulkEmailing.replace( '%1$s', formatCount( handled() ) ).replace( '%2$s', formatCount( refs.length ) ), false, true );
		}

		function summary() {
			var parts = [ i18n.bulkEmailSent.replace( '%s', formatCount( tally.sent ) ) ];

			if ( tally.skipped ) {
				parts.push( i18n.bulkEmailSkipped.replace( '%s', formatCount( tally.skipped ) ) );
			}

			if ( tally.failed ) {
				parts.push( i18n.bulkEmailFailed.replace( '%s', formatCount( tally.failed ) ) );
			}

			return parts.join( ' ' );
		}

		function next() {
			if ( 0 === queue.length ) {
				return Promise.resolve( true );
			}

			var chunk = queue.slice( 0, size );
			// Only the last chunk asks for the re-rendered table; the ones before would redraw it for nothing.
			var params = { refs: JSON.stringify( chunk ), render: chunk.length === queue.length ? '1' : '' };

			return post( 'iftp_btn_send_payment_emails', Object.assign( params, currentViewParams( '' ) ) ).then( function ( response ) {
				var data = response && response.data;

				if ( ! response || ! response.success || ! Array.isArray( data.sent ) ) {
					return Promise.reject( errorOf( response, i18n.resendError ) );
				}

				var done = new Set( data.sent.concat( data.skipped, data.failed ) );

				// Nothing handled would loop forever, so it counts as an error.
				if ( 0 === done.size ) {
					return Promise.reject( i18n.resendError );
				}

				queue = queue.filter( function ( ref ) {
					return ! done.has( ref );
				} );

				tally.sent += data.sent.length;
				tally.skipped += data.skipped.length;
				tally.failed += data.failed.length;

				removeRefsFromSelection( data.sent.concat( data.skipped ) );

				if ( 'string' === typeof data.rowsHtml ) {
					applyViewResponse( wrap, data );
				} else {
					restoreSelectionState( wrap );
				}

				progress();

				return next();
			} );
		}

		setLoading( wrap, true );
		progress();

		return next()
			.then(
				function () {
					showToast( summary(), tally.failed > 0 );

					return 0 === tally.failed;
				},
				function ( message ) {
					// What's left (the unsent chunk onwards, plus any failures) is still selected.
					showToast( handled() > 0 ? summary() + ' ' + i18n.bulkPartialError.replace( '%1$s', formatCount( handled() ) ).replace( '%2$s', formatCount( refs.length ) ) : message, true );

					return false;
				}
			)
			.then( function ( ok ) {
				setLoading( wrap, false );

				return ok;
			} );
	}

	function warnBeforeLeave( event ) {
		event.preventDefault();
		event.returnValue = i18n.bulkLeaveWarning;

		return event.returnValue;
	}

	// Locks the bulk bar while a run is in flight, and warns before leaving mid-run.
	function runLocked( wrap, run ) {
		function setBusy( busy ) {
			wrap.toggleAttribute( 'data-iftp-bulk-busy', busy );

			wrap.querySelectorAll( '[data-iftp-bulk-apply], [data-iftp-bulk-select], [data-iftp-selection-cancel]' ).forEach( function ( el ) {
				el.disabled = busy;
			} );

			if ( busy ) {
				window.addEventListener( 'beforeunload', warnBeforeLeave );
			} else {
				window.removeEventListener( 'beforeunload', warnBeforeLeave );
			}
		}

		setBusy( true );
		run().then( function () {
			setBusy( false );
		} );
	}

	function bindBulkActions( wrap ) {
		var applyBtn = wrap.querySelector( '[data-iftp-bulk-apply]' );
		var select = wrap.querySelector( '[data-iftp-bulk-select]' );

		if ( ! applyBtn || ! select ) {
			return;
		}

		applyBtn.addEventListener( 'click', function () {
			var value = select.value;
			var refs = Array.from( selectedRefsSet );

			// No confirm here: exporting changes nothing.
			if ( 'export-csv' === value || 'export-json' === value ) {
				if ( 0 === refs.length ) {
					showToast( i18n.exportNothingSelected, true );
					return;
				}

				exportEntries( refs, value.slice( 'export-'.length ) );
				select.value = '';
				return;
			}

			if ( '' === value || 0 === refs.length ) {
				return;
			}

			if ( 'delete' === value ) {
				var deletable = deletableRefs( refs );
				var keptPaid = refs.length - deletable.length;

				if ( 0 === deletable.length ) {
					return;
				}

				confirmAction(
					i18n.confirmDeleteTitle,
					i18n.confirmDeleteMessage + ( keptPaid > 0 ? ' ' + i18n.deleteKeepsPaid.replace( '%s', formatCount( keptPaid ) ) : '' ),
					i18n.confirmDeleteButton,
					true,
					function () {
						select.value = '';
						runLocked( wrap, function () {
							return runBulk( wrap, 'iftp_btn_delete_entries', deletable, {}, {
								progress: i18n.bulkDeleting,
								done: i18n.deleteToastSuccess,
								error: i18n.deleteError,
							} );
						} );
					}
				);
				return;
			}

			if ( 'email' === value ) {
				confirmAction( i18n.bulkEmailTitle, i18n.bulkEmailMessage.replace( '%s', formatCount( refs.length ) ), i18n.bulkEmailButton, false, function () {
					select.value = '';
					runLocked( wrap, function () {
						return sendPaymentEmails( wrap, refs );
					} );
				} );
				return;
			}

			confirmAction(
				i18n.confirmStatusTitle,
				i18n.confirmStatusMessage.replace( '%s', iftpBtnEntries.statusLabels[ value ] || value ),
				i18n.confirmStatusButton,
				false,
				function () {
					select.value = '';
					runLocked( wrap, function () {
						return runBulk( wrap, 'iftp_btn_update_status', refs, { status: value }, {
							progress: i18n.bulkUpdating,
							done: i18n.statusToastSuccess,
							error: i18n.updateStatusError,
						} );
					} );
				}
			);
		} );
	}

	// A throwaway form post, so the browser handles the download itself.
	function exportEntries( refs, format ) {
		var url = new URL( iftpBtnEntries.exportUrl, window.location.href );
		var form = document.createElement( 'form' );
		var f = iftpBtnEntries.currentFilters;

		url.searchParams.set( 'action', iftpBtnEntries.exportAction );
		form.method = 'post';
		form.action = url.toString();
		form.hidden = true;

		function addField( name, value ) {
			var input = document.createElement( 'input' );

			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.appendChild( input );
		}

		addField( '_wpnonce', iftpBtnEntries.exportNonce );
		addField( 'format', format );
		addField( 'orderby', f.orderby || '' );
		addField( 'order', f.order || '' );
		// One JSON field, not refs[] — PHP drops input vars past max_input_vars (1000 by default).
		addField( 'refs', JSON.stringify( refs ) );

		document.body.appendChild( form );
		form.submit();
		form.remove();
	}

	// ---- Dialogs ----

	// <dialog> brings focus trapping, Escape and the top layer. A click on the backdrop lands on the dialog
	// itself, outside its box, so that's what I check for.
	function closeOnBackdrop( dialog, canClose ) {
		dialog.addEventListener( 'click', function ( event ) {
			var box = dialog.getBoundingClientRect();
			var outside = event.target === dialog && ( event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom );

			if ( outside && ( ! canClose || canClose() ) ) {
				dialog.close();
			}
		} );
	}

	// One shared confirm dialog, filled per call, in place of window.confirm().
	function confirmAction( title, message, confirmLabel, danger, onConfirm ) {
		var dialog = document.querySelector( '[data-iftp-confirm]' );

		if ( ! dialog || 'function' !== typeof dialog.showModal ) {
			if ( window.confirm( title + '\n\n' + message ) ) {
				onConfirm();
			}

			return;
		}

		var okBtn = dialog.querySelector( '[data-iftp-confirm-ok]' );
		var cancelBtn = dialog.querySelector( '[data-iftp-confirm-cancel]' );
		var confirmed = false;

		dialog.querySelector( '[data-iftp-confirm-title]' ).textContent = title;
		dialog.querySelector( '[data-iftp-confirm-message]' ).textContent = message;
		okBtn.textContent = confirmLabel;
		okBtn.classList.toggle( 'iftp-btn-btn--danger', !! danger );
		okBtn.classList.toggle( 'iftp-btn-btn--primary', ! danger );

		function onOk() {
			confirmed = true;
			dialog.close();
		}

		function onCancel() {
			dialog.close();
		}

		function onClose() {
			okBtn.removeEventListener( 'click', onOk );
			cancelBtn.removeEventListener( 'click', onCancel );
			dialog.removeEventListener( 'close', onClose );

			if ( confirmed ) {
				onConfirm();
			}
		}

		okBtn.addEventListener( 'click', onOk );
		cancelBtn.addEventListener( 'click', onCancel );
		dialog.addEventListener( 'close', onClose );

		if ( ! dialog.hasAttribute( 'data-iftp-backdrop' ) ) {
			dialog.setAttribute( 'data-iftp-backdrop', '' );
			closeOnBackdrop( dialog );
		}

		dialog.showModal();
		// Cancel first for destructive actions, so Enter doesn't delete by accident.
		( danger ? cancelBtn : okBtn ).focus();
	}

	// "New payment": live avatar, quick amounts, status cards, optional email, then a success step in the same dialog.
	function bindNewEntry( wrap ) {
		var opener = wrap.querySelector( '[data-iftp-new-entry-open]' );
		var dialog = wrap.querySelector( '[data-iftp-new-entry-dialog]' );

		if ( ! opener || ! dialog ) {
			return;
		}

		var form = dialog.querySelector( '[data-iftp-new-entry-form]' );
		var done = dialog.querySelector( '[data-iftp-np-done]' );
		var errorEl = dialog.querySelector( '[data-iftp-new-entry-error]' );
		var submitBtn = dialog.querySelector( '[data-iftp-new-entry-submit]' );
		var amount = dialog.querySelector( '[data-iftp-np-amount]' );
		var nameInput = dialog.querySelector( '[data-iftp-np-name]' );
		var emailInput = dialog.querySelector( '[data-iftp-np-email]' );
		var avatar = dialog.querySelector( '[data-iftp-np-avatar]' );
		var initials = dialog.querySelector( '[data-iftp-np-initials]' );
		var glyph = avatar.querySelector( '.iftp-btn-avatar__glyph' );
		var sendRow = dialog.querySelector( '[data-iftp-np-email-row]' );
		var send = dialog.querySelector( '[data-iftp-np-send]' );
		var sendHint = dialog.querySelector( '[data-iftp-np-send-hint]' );
		var sendHintDefault = sendHint.textContent;
		var linksReady = ! send.disabled;
		var summary = dialog.querySelector( '[data-iftp-np-summary]' );
		var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		function status() {
			var checked = form.querySelector( '[data-iftp-np-status]:checked' );

			return checked ? checked.value : 'pending';
		}

		function parsedAmount() {
			var value = parseFloat( String( amount.value || '' ).replace( /\s/g, '' ).replace( ',', '.' ) );

			return isFinite( value ) ? value : 0;
		}

		// Written like PHP's number_format_i18n() . ' €', so it matches the amount the list will show.
		function money( value ) {
			var parts = value.toFixed( 2 ).split( '.' );
			var format = iftpBtnEntries.numberFormat || {};

			return formatCount( parts[ 0 ] ) + ( format.decimal || '.' ) + parts[ 1 ] + ' €';
		}

		// The same initials and tint rule the server uses, so the preview matches the row it becomes.
		function syncAvatar() {
			var source = nameInput.value.trim() || emailInput.value.split( '@' )[ 0 ].trim();
			var letters = source
				.split( /[\s._-]+/ )
				.filter( Boolean )
				.slice( 0, 2 )
				.map( function ( word ) {
					return word.charAt( 0 ).toUpperCase();
				} )
				.join( '' );

			initials.textContent = letters;
			initials.hidden = ! letters;
			glyph.style.display = letters ? 'none' : '';
			avatar.className = avatar.className.replace( /iftp-btn-avatar--tone-\d/, 'iftp-btn-avatar--tone-' + ( letters ? 1 + ( letters.charCodeAt( 0 ) % 6 ) : 0 ) );
		}

		// The submit label and the email toggle follow what the form will actually do.
		function syncState() {
			var isPending = 'pending' === status();
			var hasEmail = emailPattern.test( emailInput.value.trim() );
			var value = parsedAmount();

			submitBtn.textContent = isPending ? i18n.newEntryCreateLink : i18n.newEntryRecord;
			summary.textContent = value > 0 ? money( value ) : '';
			sendRow.hidden = ! isPending;
			send.disabled = ! linksReady || ! hasEmail;

			if ( send.disabled ) {
				send.checked = false;
			}

			sendHint.textContent = hasEmail ? emailInput.value.trim() : sendHintDefault;

			dialog.querySelectorAll( '[data-iftp-np-quick]' ).forEach( function ( chip ) {
				chip.classList.toggle( 'iftp-btn-np__chip--active', parseFloat( chip.getAttribute( 'data-iftp-np-quick' ) ) === value );
			} );
		}

		function showError( message, field ) {
			errorEl.textContent = message;
			errorEl.hidden = false;

			if ( field ) {
				field.setAttribute( 'aria-invalid', 'true' );
				field.focus();
			}
		}

		function reset() {
			form.reset();
			form.hidden = false;
			done.hidden = true;
			errorEl.hidden = true;
			[ amount, emailInput ].forEach( function ( field ) {
				field.removeAttribute( 'aria-invalid' );
			} );
			syncAvatar();
			syncState();
		}

		opener.addEventListener( 'click', function () {
			reset();
			dialog.showModal();
			amount.focus();
		} );

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-iftp-dialog-close]' ) ) {
				dialog.close();
			}
		} );

		dialog.addEventListener( 'close', function () {
			opener.focus();
		} );

		closeOnBackdrop( dialog );

		dialog.querySelectorAll( '[data-iftp-np-quick]' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				amount.value = chip.getAttribute( 'data-iftp-np-quick' );
				amount.removeAttribute( 'aria-invalid' );
				syncState();
				amount.focus();
			} );
		} );

		form.addEventListener( 'input', function ( event ) {
			event.target.removeAttribute( 'aria-invalid' );
			syncAvatar();
			syncState();
		} );

		form.addEventListener( 'change', syncState );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			errorEl.hidden = true;

			var value = parsedAmount();

			if ( value <= 0 ) {
				showError( i18n.newEntryAmountError, amount );
				return;
			}

			var email = emailInput.value.trim();

			if ( email && ! emailPattern.test( email ) ) {
				showError( i18n.newEntryEmailError, emailInput );
				return;
			}

			var label = submitBtn.textContent;

			submitBtn.disabled = true;
			submitBtn.textContent = i18n.newEntryCreating;
			setLoading( wrap, true );

			post(
				'iftp_btn_create_entry',
				Object.assign(
					{
						customer_name: nameInput.value.trim(),
						customer_email: email,
						amount: String( value ),
						status: status(),
						send_email: send.checked ? '1' : '',
					},
					currentViewParams( 'view_' )
				)
			)
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showError( errorOf( response, i18n.newEntryError ) );
						return;
					}

					applyViewResponse( wrap, response.data );
					showDone( response.data );
				} )
				.catch( function () {
					showError( i18n.newEntryError );
				} )
				.then( function () {
					setLoading( wrap, false );
					submitBtn.disabled = false;
					submitBtn.textContent = label;
				} );
		} );

		// Step two: what happened, the link to copy, and the ways on.
		function showDone( data ) {
			var linkRow = done.querySelector( '[data-iftp-np-link-row]' );
			var linkInput = done.querySelector( '[data-iftp-np-link]' );

			done.querySelector( '[data-iftp-np-done-title]' ).textContent = data.doneTitle || '';
			done.querySelector( '[data-iftp-np-done-amount]' ).textContent = data.amountLabel || '';
			done.querySelector( '[data-iftp-np-done-text]' ).textContent = data.doneText || '';
			done.querySelector( '[data-iftp-np-open]' ).href = data.entryUrl || '#';
			linkInput.value = data.paymentUrl || '';
			linkRow.hidden = ! data.paymentUrl;
			done.querySelector( '[data-iftp-np-copy]' ).setAttribute( 'data-iftp-copy-link', data.paymentUrl || '' );

			form.hidden = true;
			done.hidden = false;
			done.focus();
		}

		done.querySelector( '[data-iftp-np-again]' ).addEventListener( 'click', function () {
			reset();
			amount.focus();
		} );

		done.querySelector( '[data-iftp-np-link]' ).addEventListener( 'focus', function ( event ) {
			event.target.select();
		} );
	}

	// ---- Editor mode ----
	// The pencil turns every block above the table into an editable one: move it, hide it, and tune Performance and
	// the status cards. Changes apply on the spot and save as they happen (per admin); Finish editing goes back.
	function bindEditor( wrap ) {
		var toggle = wrap.querySelector( '[data-iftp-editor-toggle]' );
		var bar = wrap.querySelector( '[data-iftp-editor-bar]' );
		var holder = wrap.querySelector( '[data-iftp-blocks]' );

		if ( ! toggle || ! bar || ! holder ) {
			return;
		}

		var layout = clone( iftpBtnEntries.layout );
		var statusEl = bar.querySelector( '[data-iftp-editor-status]' );
		var saveTimer = null;
		var icons = {
			up: 'M4 10l4-4 4 4',
			down: 'M4 6l4 4 4-4',
			hide: 'M2 8s2.25-4.5 6-4.5S14 8 14 8s-2.25 4.5-6 4.5S2 8 2 8Zm6 2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
			show: 'M2.5 2.5l11 11M6.6 4.1A5.7 5.7 0 0 1 8 3.5c3.75 0 6 4.5 6 4.5a10.6 10.6 0 0 1-1.6 2.1M4.2 5.2A10.5 10.5 0 0 0 2 8s2.25 4.5 6 4.5c.9 0 1.7-.25 2.4-.6',
			options: 'M3 4.5h10M3 8h10M3 11.5h10M10 3v3M6 6.5v3M11 10v3',
			lock: 'M4.75 7.25V5.5a3.25 3.25 0 0 1 6.5 0v1.75M3.75 7.25h8.5v6h-8.5z',
		};

		function clone( value ) {
			return JSON.parse( JSON.stringify( value ) );
		}

		function block( id ) {
			return holder.querySelector( ':scope > [data-iftp-block="' + id + '"]' );
		}

		// Only the blocks this page has (the display mode decides which exist), in the layout's order.
		function onPage() {
			return layout.order.filter( function ( id ) {
				return !! block( id );
			} );
		}

		function icon( name ) {
			return '<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="' + icons[ name ] + '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		// Two half blocks pair up when they're next to each other and both showing; the rest take the whole row.
		function applyLayout() {
			var ids = onPage();
			var showing = ids.filter( function ( id ) {
				return -1 === layout.hidden.indexOf( id );
			} );
			var paired = [];

			for ( var i = 0; i < showing.length - 1; i++ ) {
				if ( -1 !== iftpBtnEntries.halfBlocks.indexOf( showing[ i ] ) && -1 !== iftpBtnEntries.halfBlocks.indexOf( showing[ i + 1 ] ) ) {
					paired.push( showing[ i ], showing[ i + 1 ] );
					i++;
				}
			}

			ids.forEach( function ( id, position ) {
				var el = block( id );

				el.style.order = position;
				el.classList.toggle( 'iftp-btn-block--paired', -1 !== paired.indexOf( id ) );
				el.toggleAttribute( 'data-iftp-block-hidden', -1 !== layout.hidden.indexOf( id ) );
			} );

			var perf = block( 'performance' );

			if ( perf ) {
				[ 'compact', 'regular', 'tall' ].forEach( function ( height ) {
					perf.classList.toggle( 'iftp-btn-perf--' + height, height === layout.perf.height );
				} );
				perf.classList.toggle( 'iftp-btn-perf--no-headline', ! layout.perf.headline );
				perf.classList.toggle( 'iftp-btn-perf--no-compare', ! layout.perf.compare );

				if ( window.iftpBtnCharts ) {
					window.iftpBtnCharts.refresh( perf );
				}
			}

			var status = block( 'status' );

			if ( status ) {
				status.classList.toggle( 'iftp-btn-status--no-deltas', ! layout.status.deltas );
			}

			syncTools();
		}

		function save() {
			statusEl.textContent = i18n.editorSaving;

			return post( 'iftp_btn_save_layout', { layout: JSON.stringify( layout ) } )
				.then( function ( response ) {
					statusEl.textContent = response && response.success ? i18n.editorSaved : errorOf( response, i18n.editorSaveError );
				} )
				.catch( function () {
					statusEl.textContent = i18n.editorSaveError;
				} );
		}

		function scheduleSave() {
			clearTimeout( saveTimer );
			saveTimer = setTimeout( save, 350 );
		}

		function button( tool, label, iconName ) {
			return '<button type="button" class="iftp-btn-block-tools__btn" data-iftp-tool="' + tool + '" aria-label="' + label + '" title="' + label + '">' + icon( iconName ) + '</button>';
		}

		function escapeHtml( text ) {
			var div = document.createElement( 'div' );

			div.textContent = text;

			return div.innerHTML;
		}

		// The floating toolbar on top of each block.
		function addTools( el ) {
			var name = escapeHtml( el.getAttribute( 'data-iftp-block-label' ) || '' );
			var tools = document.createElement( 'div' );
			var html = '<span class="iftp-btn-block-tools__name">' + name + '</span>';

			tools.className = 'iftp-btn-block-tools';
			tools.setAttribute( 'data-iftp-block-tools', '' );
			tools.setAttribute( 'role', 'toolbar' );
			tools.setAttribute( 'aria-label', el.getAttribute( 'data-iftp-block-label' ) || '' );

			if ( el.hasAttribute( 'data-iftp-block-locked' ) ) {
				html += '<span class="iftp-btn-block-tools__lock">' + icon( 'lock' ) + escapeHtml( i18n.editorLocked ) + '</span>';
			} else {
				var id = el.getAttribute( 'data-iftp-block' );

				html += '<span class="iftp-btn-block-tools__badge" data-iftp-hidden-badge>' + escapeHtml( i18n.editorHidden ) + '</span>';
				html += button( 'up', escapeHtml( i18n.editorMoveUp + ': ' + el.getAttribute( 'data-iftp-block-label' ) ), 'up' );
				html += button( 'down', escapeHtml( i18n.editorMoveDown + ': ' + el.getAttribute( 'data-iftp-block-label' ) ), 'down' );
				html += button( 'hide', escapeHtml( i18n.editorHide ), 'hide' );

				if ( 'performance' === id || 'status' === id ) {
					html += button( 'options', escapeHtml( i18n.editorOptions ), 'options' );
				}
			}

			tools.innerHTML = html;
			el.prepend( tools );
		}

		// Up/down at the ends are off; Hide reads Show (and gets the other eye) on a hidden block.
		function syncTools() {
			var ids = onPage();

			ids.forEach( function ( id, position ) {
				var el = block( id );
				var tools = el.querySelector( ':scope > [data-iftp-block-tools]' );

				if ( ! tools ) {
					return;
				}

				var hidden = -1 !== layout.hidden.indexOf( id );
				var hideBtn = tools.querySelector( '[data-iftp-tool="hide"]' );

				tools.querySelector( '[data-iftp-tool="up"]' ).disabled = 0 === position;
				tools.querySelector( '[data-iftp-tool="down"]' ).disabled = ids.length - 1 === position;
				hideBtn.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
				hideBtn.setAttribute( 'aria-label', hidden ? i18n.editorShow : i18n.editorHide );
				hideBtn.title = hidden ? i18n.editorShow : i18n.editorHide;
				hideBtn.innerHTML = icon( hidden ? 'show' : 'hide' );
			} );
		}

		// The options popover: Performance's chart height, headline, previous line and opening period; the status cards' %.
		function optionsPanel( id ) {
			var panel = document.createElement( 'div' );
			var html = '';

			function radios( name, legend, choices, current ) {
				var out = '<fieldset class="iftp-btn-block-options__group"><legend class="iftp-btn-block-options__legend">' + escapeHtml( legend ) + '</legend><span class="iftp-btn-segmented">';

				choices.forEach( function ( choice ) {
					out += '<label class="iftp-btn-segmented__option iftp-btn-segmented__option--radio"><input type="radio" class="screen-reader-text" name="iftp-btn-opt-' + name + '" value="' + choice[ 0 ] + '" data-iftp-opt="' + name + '"' + ( choice[ 0 ] === current ? ' checked' : '' ) + '>' + escapeHtml( choice[ 1 ] ) + '</label>';
				} );

				return out + '</span></fieldset>';
			}

			function switchRow( name, label, on ) {
				return '<label class="iftp-btn-block-options__switch"><input type="checkbox" class="iftp-btn-switch" data-iftp-opt="' + name + '"' + ( on ? ' checked' : '' ) + '><span>' + escapeHtml( label ) + '</span></label>';
			}

			if ( 'performance' === id ) {
				html += radios( 'height', i18n.editorChartHeight, [ [ 'compact', i18n.editorCompact ], [ 'regular', i18n.editorRegular ], [ 'tall', i18n.editorTall ] ], layout.perf.height );
				html += radios( 'period', i18n.editorPeriod, [ [ '7d', i18n.period7d ], [ '30d', i18n.period30d ], [ '12m', i18n.period12m ] ], layout.perf.period );
				html += switchRow( 'headline', i18n.editorHeadline, layout.perf.headline );
				html += switchRow( 'compare', i18n.editorCompare, layout.perf.compare );
			} else {
				html += switchRow( 'deltas', i18n.editorDeltas, layout.status.deltas );
			}

			panel.className = 'iftp-btn-popover iftp-btn-block-options';
			panel.setAttribute( 'data-iftp-block-options', '' );
			panel.innerHTML = html;

			return panel;
		}

		function closeOptions() {
			holder.querySelectorAll( '[data-iftp-block-options]' ).forEach( function ( panel ) {
				panel.remove();
			} );
			holder.querySelectorAll( '[data-iftp-tool="options"]' ).forEach( function ( btn ) {
				btn.setAttribute( 'aria-expanded', 'false' );
			} );
		}

		function enter() {
			wrap.setAttribute( 'data-iftp-editing', '' );
			toggle.setAttribute( 'aria-pressed', 'true' );
			bar.hidden = false;
			statusEl.textContent = statusEl.getAttribute( 'data-iftp-default' ) || statusEl.textContent;
			statusEl.setAttribute( 'data-iftp-default', statusEl.textContent );
			holder.querySelectorAll( ':scope > [data-iftp-block]' ).forEach( addTools );
			syncTools();

			var first = holder.querySelector( '[data-iftp-tool]' );

			if ( first ) {
				first.focus();
			}
		}

		function finish() {
			closeOptions();
			holder.querySelectorAll( '[data-iftp-block-tools]' ).forEach( function ( tools ) {
				tools.remove();
			} );
			wrap.removeAttribute( 'data-iftp-editing' );
			toggle.setAttribute( 'aria-pressed', 'false' );
			bar.hidden = true;

			// Anything still waiting to save goes now.
			if ( saveTimer ) {
				clearTimeout( saveTimer );
				saveTimer = null;
				save();
			}

			showToast( i18n.editorDone );
			toggle.focus();
		}

		toggle.addEventListener( 'click', function () {
			if ( wrap.hasAttribute( 'data-iftp-editing' ) ) {
				finish();
			} else {
				enter();
			}
		} );

		bar.querySelector( '[data-iftp-editor-finish]' ).addEventListener( 'click', finish );

		bar.querySelector( '[data-iftp-editor-reset]' ).addEventListener( 'click', function () {
			closeOptions();
			layout = clone( iftpBtnEntries.layoutDefaults );
			applyLayout();
			selectPeriod( layout.perf.period );
			scheduleSave();
			showToast( i18n.editorResetDone );
		} );

		// The period the tabs open on: picking it also switches the tabs now, so the change is visible.
		function selectPeriod( period ) {
			var tab = wrap.querySelector( '[data-iftp-period="' + period + '"]' );

			if ( tab && 'true' !== tab.getAttribute( 'aria-selected' ) ) {
				tab.click();
			}
		}

		holder.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-iftp-tool]' );

			if ( ! btn ) {
				return;
			}

			var el = btn.closest( '[data-iftp-block]' );
			var id = el.getAttribute( 'data-iftp-block' );
			var tool = btn.getAttribute( 'data-iftp-tool' );
			var ids = onPage();
			var at = ids.indexOf( id );

			if ( 'up' === tool || 'down' === tool ) {
				var swapWith = ids[ 'up' === tool ? at - 1 : at + 1 ];

				if ( undefined === swapWith ) {
					return;
				}

				layout.order[ layout.order.indexOf( id ) ] = '';
				layout.order[ layout.order.indexOf( swapWith ) ] = id;
				layout.order[ layout.order.indexOf( '' ) ] = swapWith;
				applyLayout();
				btn.focus();
				statusEl.textContent = i18n.editorMoved
					.replace( '%1$s', el.getAttribute( 'data-iftp-block-label' ) )
					.replace( '%2$s', onPage().indexOf( id ) + 1 )
					.replace( '%3$s', onPage().length );
				scheduleSave();
			} else if ( 'hide' === tool ) {
				var hiddenAt = layout.hidden.indexOf( id );

				if ( -1 === hiddenAt ) {
					layout.hidden.push( id );
				} else {
					layout.hidden.splice( hiddenAt, 1 );
				}

				applyLayout();
				btn.focus();
				scheduleSave();
			} else if ( 'options' === tool ) {
				var open = 'true' === btn.getAttribute( 'aria-expanded' );

				closeOptions();

				if ( ! open ) {
					btn.setAttribute( 'aria-expanded', 'true' );
					btn.closest( '[data-iftp-block-tools]' ).appendChild( optionsPanel( id ) );
				}
			}
		} );

		holder.addEventListener( 'change', function ( event ) {
			var opt = event.target.getAttribute( 'data-iftp-opt' );

			if ( ! opt ) {
				return;
			}

			if ( 'height' === opt || 'period' === opt ) {
				layout.perf[ opt ] = event.target.value;
			} else if ( 'deltas' === opt ) {
				layout.status.deltas = event.target.checked;
			} else {
				layout.perf[ opt ] = event.target.checked;
			}

			if ( 'period' === opt ) {
				selectPeriod( layout.perf.period );
			}

			applyLayout();
			scheduleSave();
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && holder.querySelector( '[data-iftp-block-options]' ) ) {
				closeOptions();
			}
		} );

		// A real click outside closes the options; the period tab I click for "Opens on" doesn't count.
		document.addEventListener( 'click', function ( event ) {
			if ( event.isTrusted && ! event.target.closest( '[data-iftp-block-options], [data-iftp-tool="options"]' ) ) {
				closeOptions();
			}
		} );
	}

	// Money waiting: Remind (one) and Remind all. Both confirm first, since they land in customers' inboxes, and go
	// through the same chunked sender as the bulk "Send payment email".
	function bindReminders( wrap ) {
		function markSent( refs ) {
			refs.forEach( function ( ref ) {
				var button = wrap.querySelector( '[data-iftp-remind="' + ref + '"]' );

				if ( button ) {
					button.disabled = true;
					button.textContent = i18n.reminded;
				}
			} );
		}

		wrap.addEventListener( 'click', function ( event ) {
			var one = event.target.closest( '[data-iftp-remind]' );
			var all = event.target.closest( '[data-iftp-remind-all]' );

			if ( one ) {
				confirmAction( i18n.remindTitle, i18n.remindMessage.replace( '%s', one.getAttribute( 'data-iftp-remind-to' ) ), i18n.remindButton, false, function () {
					one.disabled = true;
					sendPaymentEmails( wrap, [ one.getAttribute( 'data-iftp-remind' ) ] ).then( function ( ok ) {
						if ( ok ) {
							markSent( [ one.getAttribute( 'data-iftp-remind' ) ] );
						} else {
							one.disabled = false;
						}
					} );
				} );
			} else if ( all ) {
				var refs = JSON.parse( all.getAttribute( 'data-iftp-remind-all' ) || '[]' );

				confirmAction( i18n.remindAllTitle, i18n.remindAllMessage.replace( '%s', formatCount( refs.length ) ), i18n.remindAllButton, false, function () {
					all.disabled = true;
					runLocked( wrap, function () {
						return sendPaymentEmails( wrap, refs ).then( function ( ok ) {
							markSent( refs );

							if ( ! ok ) {
								all.disabled = false;
							}
						} );
					} );
				} );
			}
		} );
	}

	// The status cards' change pills follow the chart's period, including after an AJAX re-render of the cards.
	function syncPeriodDeltas( wrap ) {
		var section = wrap.querySelector( '[data-iftp-insights]' );
		var period = section ? section.getAttribute( 'data-iftp-current-period' ) : '';

		if ( ! period ) {
			return;
		}

		wrap.querySelectorAll( '[data-iftp-period-delta]' ).forEach( function ( delta ) {
			delta.hidden = delta.getAttribute( 'data-iftp-period-delta' ) !== period;
		} );
	}

	// Performance: the period tabs (arrow keys move between them, like any tablist).
	function bindInsights( wrap ) {
		var section = wrap.querySelector( '[data-iftp-insights]' );

		if ( ! section ) {
			return;
		}

		var tabs = Array.prototype.slice.call( section.querySelectorAll( '[data-iftp-period]' ) );

		function select( tab, focus ) {
			tabs.forEach( function ( other ) {
				var on = other === tab;

				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.tabIndex = on ? 0 : -1;
				section.querySelector( '[data-iftp-period-panel="' + other.getAttribute( 'data-iftp-period' ) + '"]' ).hidden = ! on;
			} );

			section.setAttribute( 'data-iftp-current-period', tab.getAttribute( 'data-iftp-period' ) );
			syncPeriodDeltas( wrap );

			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[ event.key ];

				if ( undefined !== next ) {
					event.preventDefault();
					select( tabs[ ( next + tabs.length ) % tabs.length ], true );
				}
			} );
		} );
	}

	// The payment email editor. Closing throws away unsaved edits, so with any pending only Cancel closes it.
	function bindEmailSettings( wrap ) {
		var opener = wrap.querySelector( '[data-iftp-email-open]' );
		var dialog = wrap.querySelector( '[data-iftp-email-dialog]' );
		var config = iftpBtnEntries.emailSettings;

		if ( ! opener || ! dialog || ! config ) {
			return;
		}

		var form = dialog.querySelector( '[data-iftp-email-form]' );
		var subjectInput = dialog.querySelector( '[data-iftp-email-subject]' );
		var bodyArea = dialog.querySelector( '[data-iftp-email-body]' );
		var errorEl = dialog.querySelector( '[data-iftp-email-error]' );
		var submitBtn = dialog.querySelector( '[data-iftp-email-submit]' );
		var submitLabel = submitBtn.textContent;
		var previewWrap = dialog.querySelector( '[data-iftp-email-preview-wrap]' );
		var previewFrame = dialog.querySelector( '[data-iftp-email-preview]' );
		var previewSubject = dialog.querySelector( '[data-iftp-email-preview-subject]' );
		var tagDesc = dialog.querySelector( '[data-iftp-email-tag-desc]' );
		var tagDescDefault = tagDesc.textContent;
		var mediaBtn = dialog.querySelector( '[data-iftp-email-media]' );

		var saved = { subject: subjectInput.value, body: bodyArea.value };
		var editor = null;
		// Where a clicked tag goes: whichever field was focused last.
		var tagTarget = 'body';
		var previewTimer = null;
		var previewSeq = 0;
		var mediaFrame = null;

		// The email body is ~536px wide inside its card, so I cap the image there.
		var imageMaxWidth = 536;

		function getBody() {
			return editor ? editor.getValue() : bodyArea.value;
		}

		function setBody( value ) {
			if ( editor ) {
				editor.setValue( value );
				editor.clearHistory();
			} else {
				bodyArea.value = value;
			}
		}

		// CodeMirror can't measure itself inside a closed dialog, so I only start it on first open.
		function ensureEditor() {
			if ( editor || ! config.editor || ! window.wp || ! window.wp.codeEditor ) {
				return;
			}

			editor = window.wp.codeEditor.initialize( bodyArea, config.editor ).codemirror;
			editor.on( 'change', schedulePreview );
			editor.on( 'focus', function () {
				tagTarget = 'body';
			} );
		}

		function isDirty() {
			return subjectInput.value !== saved.subject || getBody() !== saved.body;
		}

		function showError( message ) {
			errorEl.textContent = message;
			errorEl.hidden = false;
		}

		function schedulePreview() {
			if ( ! dialog.open ) {
				return;
			}

			clearTimeout( previewTimer );
			previewTimer = setTimeout( refreshPreview, parseInt( config.previewDelay, 10 ) || 400 );
		}

		function refreshPreview() {
			var seq = ++previewSeq;

			previewWrap.setAttribute( 'aria-busy', 'true' );

			post( 'iftp_btn_preview_payment_email', { subject: subjectInput.value, body: getBody() } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						return Promise.reject( errorOf( response, i18n.emailPreviewError ) );
					}

					// A slower, older preview mustn't land on top of a newer one.
					if ( seq === previewSeq ) {
						previewSubject.textContent = response.data.subject;
						previewFrame.srcdoc = response.data.html;
					}
				} )
				.catch( function ( message ) {
					if ( seq === previewSeq ) {
						previewSubject.textContent = 'string' === typeof message ? message : i18n.emailPreviewError;
					}
				} )
				.then( function () {
					if ( seq === previewSeq ) {
						previewWrap.removeAttribute( 'aria-busy' );
					}
				} );
		}

		function insertIntoBody( text ) {
			if ( editor ) {
				editor.replaceSelection( text );
				editor.focus();
				return;
			}

			bodyArea.setRangeText( text, bodyArea.selectionStart, bodyArea.selectionEnd, 'end' );
			bodyArea.focus();
			schedulePreview();
		}

		function insertTag( tag ) {
			if ( 'subject' === tagTarget ) {
				subjectInput.setRangeText( tag, subjectInput.selectionStart, subjectInput.selectionEnd, 'end' );
				subjectInput.focus();
				schedulePreview();
				return;
			}

			insertIntoBody( tag );
		}

		// Built through the DOM, so the URL and alt text come out properly escaped.
		function imageHtml( attachment ) {
			var size = ( attachment.sizes && ( attachment.sizes.large || attachment.sizes.full ) ) || attachment;
			var img = document.createElement( 'img' );

			img.setAttribute( 'src', size.url );
			img.setAttribute( 'alt', attachment.alt || attachment.title || '' );

			if ( size.width ) {
				img.setAttribute( 'width', String( Math.min( parseInt( size.width, 10 ), imageMaxWidth ) ) );
			}

			img.setAttribute( 'style', 'display:block;max-width:100%;height:auto;border:0;' );

			return img.outerHTML;
		}

		function openMedia() {
			if ( ! mediaFrame ) {
				mediaFrame = window.wp.media( {
					title: i18n.emailMediaTitle,
					button: { text: i18n.emailMediaButton },
					library: { type: 'image' },
					multiple: false,
				} );

				mediaFrame.on( 'select', function () {
					var attachment = mediaFrame.state().get( 'selection' ).first();

					if ( attachment ) {
						insertIntoBody( imageHtml( attachment.toJSON() ) );
					}
				} );
			}

			mediaFrame.open();
		}

		opener.addEventListener( 'click', function () {
			errorEl.hidden = true;
			dialog.showModal();
			ensureEditor();

			if ( editor ) {
				editor.refresh();
			}

			subjectInput.focus();
			refreshPreview();
		} );

		// Whatever closed it, unsaved edits go back to what's stored.
		dialog.addEventListener( 'close', function () {
			clearTimeout( previewTimer );
			subjectInput.value = saved.subject;
			setBody( saved.body );
			tagDesc.textContent = tagDescDefault;
			opener.focus();
		} );

		// Escape on unsaved edits does nothing; Cancel is the way to throw them away.
		dialog.addEventListener( 'cancel', function ( event ) {
			if ( isDirty() ) {
				event.preventDefault();
			}
		} );

		closeOnBackdrop( dialog, function () {
			return ! isDirty();
		} );

		dialog.querySelector( '[data-iftp-email-cancel]' ).addEventListener( 'click', function () {
			dialog.close();
		} );

		// The Media Library sits outside the dialog, so it only works when wp.media is around.
		if ( mediaBtn && window.wp && window.wp.media ) {
			mediaBtn.hidden = false;
			mediaBtn.addEventListener( 'click', openMedia );
		}

		subjectInput.addEventListener( 'focus', function () {
			tagTarget = 'subject';
		} );
		bodyArea.addEventListener( 'focus', function () {
			tagTarget = 'body';
		} );
		subjectInput.addEventListener( 'input', schedulePreview );
		bodyArea.addEventListener( 'input', schedulePreview );

		dialog.querySelectorAll( '[data-iftp-email-tag]' ).forEach( function ( tagBtn ) {
			function describe() {
				tagDesc.textContent = tagBtn.getAttribute( 'data-iftp-email-tag' ) + ' — ' + tagBtn.getAttribute( 'data-iftp-email-tag-help' );
			}

			function undescribe() {
				tagDesc.textContent = tagDescDefault;
			}

			tagBtn.addEventListener( 'click', function () {
				insertTag( tagBtn.getAttribute( 'data-iftp-email-tag' ) );
			} );
			tagBtn.addEventListener( 'mouseenter', describe );
			tagBtn.addEventListener( 'focus', describe );
			tagBtn.addEventListener( 'mouseleave', undescribe );
			tagBtn.addEventListener( 'blur', undescribe );
		} );

		dialog.querySelector( '[data-iftp-email-reset]' ).addEventListener( 'click', function () {
			subjectInput.value = config.defaults.subject;
			setBody( config.defaults.body );
			errorEl.hidden = true;
			schedulePreview();
			showToast( i18n.emailResetDone );
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			submitBtn.disabled = true;
			submitBtn.textContent = i18n.emailSaving;
			errorEl.hidden = true;

			post( 'iftp_btn_save_payment_email', { subject: subjectInput.value, body: getBody() } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showError( errorOf( response, i18n.emailSaveError ) );
						return;
					}

					// What was stored, after sanitizing, so reopening shows exactly what goes out.
					saved = { subject: response.data.subject, body: response.data.body };
					dialog.close();
					showToast( response.data.message );
				} )
				.catch( function () {
					showError( i18n.emailSaveError );
				} )
				.then( function () {
					submitBtn.disabled = false;
					submitBtn.textContent = submitLabel;
				} );
		} );
	}

	// ---- Feedback ----

	var toastTimer = null;

	// A sticky toast stays up until the next one replaces it (bulk progress).
	function showToast( message, isError, sticky ) {
		var toast = document.querySelector( '[data-iftp-toast]' );

		if ( ! toast ) {
			return;
		}

		toast.querySelector( '[data-iftp-toast-message]' ).textContent = message;
		toast.classList.toggle( 'iftp-btn-toast--error', !! isError );
		toast.hidden = false;

		clearTimeout( toastTimer );

		if ( ! sticky ) {
			toastTimer = setTimeout( function () {
				toast.hidden = true;
			}, 5000 );
		}
	}

	function bindScrollButton( wrap ) {
		var btn = wrap.querySelector( '[data-iftp-scroll-top]' );

		if ( ! btn ) {
			return;
		}

		var reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		var queued = false;

		window.addEventListener(
			'scroll',
			function () {
				if ( queued ) {
					return;
				}

				queued = true;
				window.requestAnimationFrame( function () {
					queued = false;
					btn.hidden = window.scrollY <= 400;
				} );
			},
			{ passive: true }
		);

		btn.addEventListener( 'click', function () {
			window.scrollTo( { top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' } );
		} );
	}

	// Delegated, since rows get swapped in by AJAX. Covers Copy link and the reference itself, on both pages.
	function bindCopy( scope ) {
		scope.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-iftp-copy-link], [data-iftp-copy-ref]' );

			if ( ! btn ) {
				return;
			}

			var isRef = btn.hasAttribute( 'data-iftp-copy-ref' );

			copyText( btn.getAttribute( isRef ? 'data-iftp-copy-ref' : 'data-iftp-copy-link' ), btn )
				.then( function () {
					btn.classList.add( 'iftp-btn-copied' );
					clearTimeout( btn.iftpCopiedTimer );
					btn.iftpCopiedTimer = setTimeout( function () {
						btn.classList.remove( 'iftp-btn-copied' );
					}, 2000 );

					showToast( isRef ? i18n.copyRefSuccess : i18n.copyLinkSuccess );
				} )
				.catch( function () {
					showToast( isRef ? i18n.copyRefError : i18n.copyLinkError, true );
				} );
		} );
	}

	function copyText( text, returnFocusTo ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return legacyCopy( text, returnFocusTo );
			} );
		}

		return legacyCopy( text, returnFocusTo );
	}

	// Plain-http admins have no Clipboard API, so I fall back to execCommand, then hand focus back.
	function legacyCopy( text, returnFocusTo ) {
		var textarea = document.createElement( 'textarea' );
		var copied = false;

		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'fixed';
		textarea.style.top = '0';
		textarea.style.left = '-9999px';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();

		try {
			copied = document.execCommand( 'copy' );
		} catch ( e ) {
			copied = false;
		}

		textarea.remove();

		if ( returnFocusTo && document.body.contains( returnFocusTo ) ) {
			returnFocusTo.focus();
		}

		return copied ? Promise.resolve() : Promise.reject( new Error( 'copy failed' ) );
	}

	// ---- Toolbar popovers and the filter panel ----

	// Shared popover rules: the trigger toggles; Escape, an outside click or focus leaving closes.
	function bindPopover( trigger, panel, onClose ) {
		function isOpen() {
			return ! panel.hidden;
		}

		function setOpen( open ) {
			panel.hidden = ! open;
			trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( ! open && onClose ) {
				onClose();
			}
		}

		function isInside( node ) {
			return !! node && ( trigger.contains( node ) || panel.contains( node ) );
		}

		function onFocusOut( event ) {
			// A null relatedTarget is a click on nothing focusable; the click handler covers that.
			if ( isOpen() && event.relatedTarget && ! isInside( event.relatedTarget ) ) {
				setOpen( false );
			}
		}

		trigger.addEventListener( 'click', function () {
			setOpen( ! isOpen() );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && isOpen() ) {
				setOpen( false );
				trigger.focus();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( isOpen() && ! isInside( event.target ) ) {
				setOpen( false );
			}
		} );

		trigger.addEventListener( 'focusout', onFocusOut );
		panel.addEventListener( 'focusout', onFocusOut );

		return setOpen;
	}

	function bindExportMenu( wrap ) {
		var trigger = wrap.querySelector( '[data-iftp-export-trigger]' );
		var panel = wrap.querySelector( '[data-iftp-export-menu]' );

		if ( ! trigger || ! panel ) {
			return;
		}

		var setOpen = bindPopover( trigger, panel );

		// The download doesn't leave the page, so I close the menu myself.
		panel.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-iftp-export-option]' ) ) {
				setOpen( false );
				trigger.focus();
			}
		} );
	}

	function bindColumnsMenu( wrap ) {
		var trigger = wrap.querySelector( '[data-iftp-columns-trigger]' );
		var panel = wrap.querySelector( '[data-iftp-columns-menu]' );

		if ( ! trigger || ! panel ) {
			return;
		}

		var toggles = Array.prototype.slice.call( panel.querySelectorAll( '[data-iftp-col-toggle]' ) );
		var list = panel.querySelector( '[data-iftp-col-list]' );
		var status = wrap.querySelector( '[data-iftp-col-status]' );
		var reset = panel.querySelector( '[data-iftp-col-reset]' );
		var draggingClass = 'iftp-btn-columns__item--dragging';
		var dropBeforeClass = 'iftp-btn-columns__item--drop-before';
		var dropAfterClass = 'iftp-btn-columns__item--drop-after';
		var saveTimer = null;
		var announceTimer = null;
		var dragging = null;

		bindPopover( trigger, panel );

		function hiddenCols() {
			return toggles
				.filter( function ( input ) {
					return ! input.checked;
				} )
				.map( function ( input ) {
					return input.getAttribute( 'data-iftp-col-toggle' );
				} );
		}

		function items() {
			return Array.prototype.slice.call( list.querySelectorAll( '[data-iftp-col-item]' ) );
		}

		function readOrder() {
			return items().map( function ( item ) {
				return item.getAttribute( 'data-iftp-col-item' );
			} );
		}

		function save() {
			post( 'iftp_btn_save_columns', { hidden: hiddenCols(), order: readOrder() } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showToast( errorOf( response, i18n.columnsSaveError ), true );
					}
				} )
				.catch( function () {
					showToast( i18n.columnsSaveError, true );
				} );
		}

		// The view updates right away; only the save waits.
		function scheduleSave() {
			clearTimeout( saveTimer );
			saveTimer = setTimeout( save, 400 );
		}

		function syncMoveButtons() {
			var all = items();

			all.forEach( function ( item, i ) {
				[ [ 'up', 0 === i ], [ 'down', all.length - 1 === i ] ].forEach( function ( pair ) {
					var btn = item.querySelector( '[data-iftp-col-move="' + pair[ 0 ] + '"]' );

					if ( pair[ 1 ] ) {
						btn.setAttribute( 'aria-disabled', 'true' );
					} else {
						btn.removeAttribute( 'aria-disabled' );
					}
				} );
			} );
		}

		// Cleared first, so the same message twice in a row still gets spoken.
		function announce( message ) {
			if ( ! status || ! message ) {
				return;
			}

			status.textContent = '';
			clearTimeout( announceTimer );
			announceTimer = setTimeout( function () {
				status.textContent = message;
			}, 100 );
		}

		function commit( item ) {
			var order = readOrder();

			// A drop back where it started changes nothing, so there's nothing to save.
			if ( order.join( ' ' ) === attrList( wrap, 'data-iftp-col-order' ).join( ' ' ) ) {
				return;
			}

			var label = item.querySelector( '.iftp-btn-columns__option-label' );
			var slug = item.getAttribute( 'data-iftp-col-item' );

			wrap.setAttribute( 'data-iftp-col-order', order.join( ' ' ) );
			applyColumnOrder( wrap, order );
			syncMoveButtons();
			announce(
				i18n.columnMoved
					.replace( '%1$s', label ? label.textContent.trim() : slug )
					.replace( '%2$s', order.indexOf( slug ) + 1 )
					.replace( '%3$s', order.length )
			);
			scheduleSave();
		}

		function clearDropMarks() {
			items().forEach( function ( item ) {
				item.classList.remove( dropBeforeClass, dropAfterClass );
			} );
		}

		// Text-selection drags can target a text node, which has no closest().
		function closestItem( node ) {
			var el = node && 1 !== node.nodeType ? node.parentElement : node;

			return el && el.closest ? el.closest( '[data-iftp-col-item]' ) : null;
		}

		panel.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches( '[data-iftp-col-toggle]' ) ) {
				return;
			}

			wrap.setAttribute( 'data-iftp-hidden-cols', hiddenCols().join( ' ' ) );
			applyHiddenColumns( wrap, hiddenCols() );
			scheduleSave();
		} );

		// I move the neighbour, not the item, so the focused button never leaves the DOM.
		panel.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-iftp-col-move]' );
			var item = btn ? btn.closest( '[data-iftp-col-item]' ) : null;
			var neighbour;

			if ( ! item || 'true' === btn.getAttribute( 'aria-disabled' ) ) {
				return;
			}

			if ( 'up' === btn.getAttribute( 'data-iftp-col-move' ) ) {
				neighbour = item.previousElementSibling;

				if ( neighbour ) {
					list.insertBefore( neighbour, item.nextElementSibling );
				}
			} else {
				neighbour = item.nextElementSibling;

				if ( neighbour ) {
					list.insertBefore( neighbour, item );
				}
			}

			commit( item );
			btn.focus();
		} );

		list.addEventListener( 'dragstart', function ( event ) {
			var el = event.target && 1 === event.target.nodeType ? event.target : null;
			var handle = el ? el.closest( '[data-iftp-col-drag]' ) : null;
			var item = handle ? handle.closest( '[data-iftp-col-item]' ) : null;

			if ( ! item ) {
				return;
			}

			dragging = item;
			event.dataTransfer.effectAllowed = 'move';
			// Firefox won't start a drag without data. A custom type keeps the slug from dropping into the search field.
			event.dataTransfer.setData( 'application/x-iftp-btn-column', item.getAttribute( 'data-iftp-col-item' ) );

			if ( 'function' === typeof event.dataTransfer.setDragImage ) {
				event.dataTransfer.setDragImage( item, 16, item.offsetHeight / 2 );
			}

			// One tick later, so the drag ghost isn't the dimmed row.
			setTimeout( function () {
				if ( dragging === item ) {
					item.classList.add( draggingClass );
				}
			}, 0 );
		} );

		list.addEventListener( 'dragover', function ( event ) {
			var target = dragging ? closestItem( event.target ) : null;

			// Files and drags from outside the list aren't mine.
			if ( ! dragging ) {
				return;
			}

			if ( ! target || target === dragging ) {
				clearDropMarks();
				return;
			}

			var rect = target.getBoundingClientRect();
			var before = event.clientY < rect.top + rect.height / 2;

			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';

			items().forEach( function ( item ) {
				item.classList.toggle( dropBeforeClass, item === target && before );
				item.classList.toggle( dropAfterClass, item === target && ! before );
			} );
		} );

		list.addEventListener( 'dragleave', function ( event ) {
			if ( ! list.contains( event.relatedTarget ) ) {
				clearDropMarks();
			}
		} );

		list.addEventListener( 'drop', function ( event ) {
			var item = dragging;
			var target;

			if ( ! item ) {
				return;
			}

			event.preventDefault();
			target = list.querySelector( '.' + dropBeforeClass + ', .' + dropAfterClass );

			if ( target && target !== item ) {
				list.insertBefore( item, target.classList.contains( dropBeforeClass ) ? target : target.nextElementSibling );
			}

			clearDropMarks();

			if ( target ) {
				commit( item );
			}
		} );

		// Covers Escape-cancelled drags and drops outside the popover too.
		list.addEventListener( 'dragend', function () {
			if ( dragging ) {
				dragging.classList.remove( draggingClass );
			}

			clearDropMarks();
			dragging = null;
		} );

		if ( reset ) {
			reset.addEventListener( 'click', function () {
				var defaults = iftpBtnEntries.defaultColumnOrder;
				var bySlug = {};

				items().forEach( function ( item ) {
					bySlug[ item.getAttribute( 'data-iftp-col-item' ) ] = item;
				} );

				defaults.forEach( function ( slug ) {
					if ( bySlug[ slug ] ) {
						list.appendChild( bySlug[ slug ] );
					}
				} );

				toggles.forEach( function ( input ) {
					input.checked = true;
				} );

				wrap.setAttribute( 'data-iftp-hidden-cols', '' );
				wrap.setAttribute( 'data-iftp-col-order', defaults.join( ' ' ) );
				applyColumnOrder( wrap, defaults );
				applyHiddenColumns( wrap, [] );
				syncMoveButtons();
				announce( i18n.columnsReset );
				scheduleSave();
			} );
		}
	}

	// An inline disclosure, not a popover: only the toggle and Escape close it.
	function bindFilterPanel( wrap ) {
		var toggle = wrap.querySelector( '[data-iftp-filters-toggle]' );
		var panel = wrap.querySelector( '[data-iftp-filter-panel]' );
		var form = panel ? panel.querySelector( 'form' ) : null;

		if ( ! toggle || ! panel || ! form ) {
			return;
		}

		var firstField = panel.querySelector( '[data-iftp-filter-first]' );
		var dateFrom = panel.querySelector( '[data-iftp-date-from]' );
		var dateTo = panel.querySelector( '[data-iftp-date-to]' );
		var panelItem = 'undefined' !== typeof iftpBtnEntriesStore && iftpBtnEntriesStore.key ? iftpBtnEntriesStore.key + ':panel' : '';

		function setOpen( open ) {
			panel.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		// Remembered for the tab's session; Escape counts as closing it too.
		function persistOpen( open ) {
			if ( ! panelItem ) {
				return;
			}

			try {
				if ( open ) {
					sessionStorage.setItem( panelItem, '1' );
				} else {
					sessionStorage.removeItem( panelItem );
				}
			} catch ( e ) {
				// No storage, so it just starts closed next time.
			}
		}

		function wasOpen() {
			try {
				return !! panelItem && '1' === sessionStorage.getItem( panelItem );
			} catch ( e ) {
				return false;
			}
		}

		// It renders open for no-JS; with JS it starts closed unless the admin left it open.
		setOpen( wasOpen() );

		toggle.addEventListener( 'click', function () {
			var open = panel.hidden;

			setOpen( open );
			persistOpen( open );

			// It sits after the tools in tab order, so I take the admin straight to it.
			if ( open && firstField ) {
				// The select sits under our own dropdown, so focus goes to the dropdown's button.
				if ( window.iftpBtnDropdowns ) {
					window.iftpBtnDropdowns.focus( firstField );
				} else {
					firstField.focus();
				}
			}
		} );

		panel.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! panel.hidden ) {
				setOpen( false );
				persistOpen( false );
				toggle.focus();
			}
		} );

		// Submitting the form (not following the link) keeps unapplied method/amount edits.
		panel.addEventListener( 'click', function ( event ) {
			var preset = event.target.closest( '[data-iftp-date-preset]' );

			if ( ! preset || ! dateFrom || ! dateTo || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) {
				return;
			}

			event.preventDefault();
			dateFrom.value = preset.getAttribute( 'data-iftp-preset-from' ) || '';
			dateTo.value = preset.getAttribute( 'data-iftp-preset-to' ) || '';
			submitForm( form );
		} );
	}

	// Arrowing a closed select fires change on Windows, so I don't reload on those (WCAG 3.2.2).
	function bindPerPage( wrap ) {
		var form = wrap.querySelector( '[data-iftp-per-page-form]' );
		var select = form ? form.querySelector( '[data-iftp-per-page]' ) : null;
		var go = form ? form.querySelector( '[data-iftp-per-page-submit]' ) : null;
		var custom = form ? form.querySelector( '[data-iftp-per-page-custom]' ) : null;
		var customInput = custom ? custom.querySelector( 'input' ) : null;
		var navKeys = [ 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown' ];
		var keyNav = false;

		if ( ! select ) {
			return;
		}

		// Off "custom", the number box is disabled too, so a stale bad value can't block a preset's submit.
		function syncCustom() {
			var isCustom = 'custom' === select.value;

			if ( custom ) {
				custom.hidden = ! isCustom;
			}

			if ( customInput ) {
				customInput.disabled = ! isCustom;
				customInput.required = isCustom;
			}

			if ( go ) {
				go.hidden = ! isCustom;
			}

			return isCustom;
		}

		function focusCustom() {
			if ( customInput ) {
				customInput.focus();
				customInput.select();
			}
		}

		syncCustom();

		select.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();

				if ( 'custom' === select.value ) {
					focusCustom();
				} else {
					submitForm( form );
				}

				return;
			}

			// Type-ahead letters change the value too; Space and Alt+Arrow just open the list.
			keyNav = ! event.altKey && ( -1 !== navKeys.indexOf( event.key ) || ( 1 === event.key.length && ' ' !== event.key && ! event.ctrlKey && ! event.metaKey ) );
		} );

		select.addEventListener( 'pointerdown', function () {
			keyNav = false;
		} );

		select.addEventListener( 'change', function () {
			// While arrowing I only reveal the box; jumping focus would strand the admin mid-list.
			if ( syncCustom() ) {
				if ( ! keyNav ) {
					focusCustom();
				}

				return;
			}

			if ( keyNav ) {
				if ( go ) {
					go.hidden = false;
				}

				return;
			}

			submitForm( form );
		} );
	}

	// ---- Single entry page ----

	function bindSingleEntry( wrap ) {
		var ref = wrap.getAttribute( 'data-iftp-ref' );
		var deleteBtn = wrap.querySelector( '[data-iftp-entry-delete]' );
		var statusSelect = wrap.querySelector( '[data-iftp-entry-status-select]' );
		var statusApply = wrap.querySelector( '[data-iftp-entry-status-apply]' );
		var resendBtn = wrap.querySelector( '[data-iftp-resend-email]' );

		// The single page has no list view to re-render, so it posts the default one.
		function postFor( action, extra ) {
			return post( action, Object.assign( { refs: JSON.stringify( [ ref ] ), view_status: '', per_page: 1 }, extra ) );
		}

		if ( deleteBtn ) {
			deleteBtn.addEventListener( 'click', function () {
				confirmAction( i18n.confirmDeleteOneTitle, i18n.confirmDeleteMessage, i18n.confirmDeleteButton, true, function () {
					deleteBtn.disabled = true;

					postFor( 'iftp_btn_delete_entries', {} )
						.then( function ( response ) {
							if ( ! response || ! response.success || ! response.data.deleted ) {
								return Promise.reject( errorOf( response, i18n.deleteError ) );
							}

							removeRefsFromSelection( [ ref ] );
							window.location.assign( iftpBtnEntries.listUrl );
						} )
						.catch( function ( message ) {
							deleteBtn.disabled = false;
							showToast( 'string' === typeof message ? message : i18n.deleteError, true );
						} );
				} );
			} );
		}

		if ( statusSelect && statusApply ) {
			statusApply.addEventListener( 'click', function () {
				var target = statusSelect.value;

				if ( target === statusApply.getAttribute( 'data-iftp-current-status' ) ) {
					return;
				}

				confirmAction( i18n.confirmStatusTitle, i18n.confirmStatusOne.replace( '%s', iftpBtnEntries.statusLabels[ target ] || target ), i18n.confirmStatusButton, false, function () {
					statusApply.disabled = true;

					postFor( 'iftp_btn_update_status', { status: target } )
						.then( function ( response ) {
							if ( ! response || ! response.success ) {
								return Promise.reject( errorOf( response, i18n.updateStatusError ) );
							}

							window.location.reload();
						} )
						.catch( function ( message ) {
							statusApply.disabled = false;
							showToast( 'string' === typeof message ? message : i18n.updateStatusError, true );
						} );
				} );
			} );
		}

		// Confirm first, since it lands in the customer's inbox. The send may create the link, so I reload after.
		if ( resendBtn ) {
			resendBtn.addEventListener( 'click', function () {
				confirmAction( i18n.confirmResendTitle, i18n.confirmResendMessage.replace( '%s', resendBtn.getAttribute( 'data-iftp-resend-to' ) ), i18n.confirmResendButton, false, function () {
					var label = resendBtn.querySelector( '[data-iftp-resend-label]' );
					var idle = label.textContent;

					resendBtn.disabled = true;
					label.textContent = i18n.resendSending;

					post( 'iftp_btn_resend_payment_email', { ref } )
						.then( function ( response ) {
							var ok = response && response.success;

							showToast( errorOf( response, i18n.resendError ), ! ok );

							if ( ok ) {
								setTimeout( function () {
									window.location.reload();
								}, 1500 );
							}
						} )
						.catch( function () {
							showToast( i18n.resendError, true );
						} )
						.then( function () {
							resendBtn.disabled = false;
							label.textContent = idle;
						} );
				} );
			} );
		}
	}
} )();
