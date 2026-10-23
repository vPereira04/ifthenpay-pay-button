/* global iftpBtnEntriesStore */
// Puts back the admin's saved view (sessionStorage) when the Entries list opens bare; per-page is saved server-side.
// Runs in <head>, so the redirect happens before the default view paints.
( function () {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntriesStore || ! iftpBtnEntriesStore || ! iftpBtnEntriesStore.key ) {
		return;
	}

	var store = iftpBtnEntriesStore;
	var viewKeys = [ 's', 'status', 'date_from', 'date_to', 'pay_method', 'amount_min', 'amount_max', 'orderby', 'order', 'paged' ];
	var viewItem = store.key + ':view';
	var restoringItem = store.key + ':restoring';
	var navLoadingItem = 'iftpBtnEntriesNavLoading'; // set by entries.js on the click that led here
	var root = document.documentElement;

	// Hooks for the future loader/skeleton styles: loading until the footer scripts ran, "continue" when a click led here.
	root.classList.add( 'iftp-btn-page-loading' );

	if ( null !== read( navLoadingItem ) ) {
		root.classList.add( 'iftp-btn-page-continue' );
		write( navLoadingItem, null );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		root.classList.remove( 'iftp-btn-page-loading', 'iftp-btn-page-continue' );
		root.classList.add( 'iftp-btn-page-ready' );
	} );

	// Storage can be missing or throw (private mode, blocked, quota); I just carry on without it.
	function read( item ) {
		try {
			return window.sessionStorage.getItem( item );
		} catch ( e ) {
			return null;
		}
	}

	function write( item, value ) {
		try {
			if ( null === value ) {
				window.sessionStorage.removeItem( item );
			} else {
				window.sessionStorage.setItem( item, value );
			}
		} catch ( e ) {
			// Nothing saved; the page works the same without it.
		}
	}

	// Known keys with non-empty string values only. Page 1 is the default, so I drop it.
	function cleanView( raw ) {
		var view = {};

		if ( ! raw || '[object Object]' !== Object.prototype.toString.call( raw ) ) {
			return view;
		}

		viewKeys.forEach( function ( key ) {
			var value = raw[ key ];

			if ( 'string' === typeof value && '' !== value && ! ( 'paged' === key && '1' === value ) ) {
				view[ key ] = value;
			}
		} );

		return view;
	}

	function isBare() {
		var bare = true;

		new URLSearchParams( window.location.search ).forEach( function ( value, name ) {
			if ( 'page' !== name ) {
				bare = false;
			}
		} );

		return bare;
	}

	// A view with params: remember it as the one to come back to.
	if ( ! isBare() ) {
		var view = cleanView( store.view );

		write( restoringItem, null );
		write( viewItem, Object.keys( view ).length ? JSON.stringify( view ) : null );
		return;
	}

	// A restore that landed bare again would loop, so I only try once.
	if ( null !== read( restoringItem ) ) {
		write( restoringItem, null );
		return;
	}

	var saved;

	try {
		saved = cleanView( JSON.parse( read( viewItem ) || 'null' ) );
	} catch ( e ) {
		saved = {};
	}

	var params = new URLSearchParams();

	viewKeys.forEach( function ( key ) {
		if ( Object.prototype.hasOwnProperty.call( saved, key ) ) {
			params.set( key, saved[ key ] );
		}
	} );

	if ( ! params.toString() ) {
		return;
	}

	write( restoringItem, '1' );
	window.location.replace( window.location.pathname + '?page=' + encodeURIComponent( store.pageSlug ) + '&' + params.toString() + window.location.hash );
} )();
