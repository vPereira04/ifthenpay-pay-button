/* global iftpBtnEntries */
/**
 * Entries, shared state and helpers: the text and selection kept across pages, form posting and errors.
 */
( function ( ns ) {
	'use strict';

	ns.errorOf = errorOf;
	ns.formatCount = formatCount;
	ns.post = post;
	ns.submitForm = submitForm;

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	var i18n = iftpBtnEntries.i18n;
	ns.i18n = i18n;

	// Pagination is a full reload, so I keep the selection in sessionStorage to carry it across pages.
	var selectionStorageKey = 'iftpBtnEntriesSelectedRefs';
	ns.selectionStorageKey = selectionStorageKey;
	var statusStorageKey = 'iftpBtnEntriesSelectedStatus';
	ns.statusStorageKey = statusStorageKey;
	// Tells the next page (entries-restore.js reads it) that it was reached from this one.
	var receiptStorageKey = 'iftpBtnEntriesSelectedReceipt';
	ns.receiptStorageKey = receiptStorageKey;
	var navLoadingStorageKey = 'iftpBtnEntriesNavLoading';
	ns.navLoadingStorageKey = navLoadingStorageKey;

	var selectedRefsSet = new Set( loadPersistedSelection() );
	ns.selectedRefsSet = selectedRefsSet;
	// ref => status for the selected rows, so the bulk bar knows statuses on pages that aren't showing.
	ns.selectedStatus = loadPersistedStatus();
	// ref => true for the selected paid rows that have an email to send a receipt to.
	ns.selectedReceipt = loadPersistedMap( receiptStorageKey );
	ns.loadingTimer = 0;

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
		return loadPersistedMap( statusStorageKey );
	}

	function loadPersistedMap( key ) {
		try {
			var map = JSON.parse( sessionStorage.getItem( key ) || '{}' );

			return map && 'object' === typeof map ? map : {};
		} catch ( e ) {
			return {};
		}
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
