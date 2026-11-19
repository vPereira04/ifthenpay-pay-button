/* global iftpBtnEntries */
/**
 * Entries list: the no-JS enhancement, the loading state, and applying a new view's columns and rows in place.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { navLoadingStorageKey } = ns;

	ns.applyColumnOrder = applyColumnOrder;
	ns.applyHiddenColumns = applyHiddenColumns;
	ns.applyViewResponse = applyViewResponse;
	ns.attrList = attrList;
	ns.bindNavLoading = bindNavLoading;
	ns.currentViewParams = currentViewParams;
	ns.enhance = enhance;
	ns.setLoading = setLoading;

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

		window.clearTimeout( ns.loadingTimer );

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

		ns.loadingTimer = window.setTimeout(
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
		ns.restoreSelectionState( wrap );
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
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
