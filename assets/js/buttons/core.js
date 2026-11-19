/**
 * Buttons and Checkout screens, shared helpers: the screen's data, the AJAX post, money, the toast and copying.
 */
( function ( ns ) {
	'use strict';

	ns.copyText = copyText;
	ns.money = money;
	ns.post = post;
	ns.toast = toast;

	const config = window.iftpBtnButtons || { i18n: {} };
	ns.config = config;
	const i18n = config.i18n || {};
	ns.i18n = i18n;

	function post( action, data ) {
		const body = new URLSearchParams( { action: action, _ajax_nonce: config.nonce } );

		Object.keys( data ).forEach( function ( key ) {
			// A list goes as repeated fields, which PHP reads as an array.
			[].concat( data[ key ] ).forEach( function ( value ) {
				body.append( key, value );
			} );
		} );

		return fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
			return response.json();
		} );
	}

	function money( value ) {
		const number = parseFloat( value );

		if ( ! isFinite( number ) ) {
			return '';
		}

		return number.toLocaleString( document.documentElement.lang || undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } ) + ' €';
	}

	/* ---------------------------------------------------------------- Feedback */

	let toastTimer = 0;

	function toast( message, isError, tone ) {
		const box = document.querySelector( '[data-iftp-toast]' );

		if ( ! box ) {
			return;
		}

		box.querySelector( '[data-iftp-toast-message]' ).textContent = message;
		box.classList.toggle( 'iftp-btn-toast--error', !! isError );
		box.classList.toggle( 'iftp-btn-toast--muted', tone === 'muted' );
		box.hidden = false;

		window.clearTimeout( toastTimer );
		toastTimer = window.setTimeout( function () {
			box.hidden = true;
		}, 3200 );
	}

	/* ---------------------------------------------------------------- Copying */

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).then(
				function () {
					return true;
				},
				function () {
					return false;
				}
			);
		}

		// Plain http: the old way, through a throwaway field.
		const field = document.createElement( 'textarea' );

		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.opacity = '0';
		document.body.appendChild( field );
		field.select();

		let ok = false;

		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}

		field.remove();

		return Promise.resolve( ok );
	}
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
