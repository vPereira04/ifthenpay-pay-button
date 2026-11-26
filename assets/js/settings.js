/* global iftpBtnSettings */
// Configuration › Backoffice: the Connect / Disconnect dialogs, and the weekly summary's test send.
( function () {
	'use strict';

	if ( 'undefined' === typeof iftpBtnSettings ) {
		return;
	}

	var i18n = iftpBtnSettings.i18n;

	// A required field inside a closed card can't show its message, so the card opens first.
	document.addEventListener(
		'invalid',
		function ( event ) {
			var card = event.target.closest ? event.target.closest( 'details:not([open])' ) : null;

			if ( card ) {
				card.open = true;
			}
		},
		true
	);

	function post( action, data ) {
		var body = new URLSearchParams();

		body.set( 'action', action );
		body.set( 'nonce', iftpBtnSettings.nonce );

		Object.keys( data || {} ).forEach( function ( key ) {
			if ( Array.isArray( data[ key ] ) ) {
				data[ key ].forEach( function ( value ) {
					body.append( key + '[]', value );
				} );
			} else {
				body.set( key, data[ key ] );
			}
		} );

		return fetch( iftpBtnSettings.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	function errorMessage( response ) {
		return ( response && response.data && response.data.message ) || i18n.error;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'iftp-btn-settings' );

		if ( ! wrap ) {
			return;
		}

		bindDialogs( wrap );
		bindConnect( wrap );
		bindDisconnect( wrap );
		bindSummaryTest( wrap );
	} );

	// Every Cancel button closes its own dialog; Escape and the backdrop come with <dialog> for free.
	function bindDialogs( wrap ) {
		wrap.addEventListener( 'click', function ( event ) {
			var closeBtn = event.target.closest( '[data-iftp-dialog-close]' );
			var dialog = closeBtn ? closeBtn.closest( 'dialog' ) : null;

			if ( dialog ) {
				dialog.close();
			}
		} );
	}

	function bindConnect( wrap ) {
		var opener = wrap.querySelector( '[data-iftp-connect-open]' );
		var dialog = wrap.querySelector( '[data-iftp-connect-dialog]' );

		if ( ! opener || ! dialog ) {
			return;
		}

		var form = dialog.querySelector( '[data-iftp-connect-form]' );
		var input = dialog.querySelector( '[data-iftp-backoffice-key]' );
		var errorEl = dialog.querySelector( '[data-iftp-connect-error]' );
		var submitBtn = dialog.querySelector( '[data-iftp-connect-submit]' );

		function fail( message ) {
			errorEl.textContent = message;
			input.setAttribute( 'aria-invalid', 'true' );
			input.focus();
			submitBtn.disabled = false;
			submitBtn.textContent = i18n.connect;
		}

		opener.addEventListener( 'click', function () {
			errorEl.textContent = '';
			input.removeAttribute( 'aria-invalid' );
			dialog.showModal();
			input.focus();
		} );

		// Focus goes back to Connect however the dialog closed.
		dialog.addEventListener( 'close', function () {
			opener.focus();
		} );

		form.addEventListener( 'submit', function ( event ) {
			var key = String( input.value || '' ).trim();

			event.preventDefault();

			// Same format the server checks, so a typo is caught without a round-trip.
			if ( ! /^\d{4}-\d{4}-\d{4}-\d{4}$/.test( key ) ) {
				fail( i18n.invalidKey );
				return;
			}

			errorEl.textContent = '';
			input.removeAttribute( 'aria-invalid' );
			submitBtn.disabled = true;
			submitBtn.textContent = i18n.connecting;

			post( 'iftp_btn_connect', { backoffice_key: key } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						fail( errorMessage( response ) );
						return;
					}

					// The button turns into Disconnect, rendered server-side.
					window.location.reload();
				} )
				.catch( function () {
					fail( i18n.error );
				} );
		} );
	}

	function bindDisconnect( wrap ) {
		var opener = wrap.querySelector( '[data-iftp-disconnect-open]' );
		var dialog = wrap.querySelector( '[data-iftp-disconnect-dialog]' );

		if ( ! opener || ! dialog ) {
			return;
		}

		var message = wrap.querySelector( '[data-iftp-connection-message]' );

		var confirmBtn = dialog.querySelector( '[data-iftp-disconnect-confirm]' );

		opener.addEventListener( 'click', function () {
			dialog.showModal();
		} );

		dialog.addEventListener( 'close', function () {
			if ( ! confirmBtn.disabled ) {
				opener.focus();
			}
		} );

		confirmBtn.addEventListener( 'click', function () {
			confirmBtn.disabled = true;

			post( 'iftp_btn_disconnect', {} )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						return Promise.reject( errorMessage( response ) );
					}

					window.location.reload();
				} )
				.catch( function ( error ) {
					confirmBtn.disabled = false;
					dialog.close();
					message.textContent = 'string' === typeof error ? error : i18n.error;
				} );
		} );
	}

	// Configuration › Emails: sends the summary now, to whatever address is in the field, saved or not.
	function bindSummaryTest( wrap ) {
		var testBtn = wrap.querySelector( '[data-iftp-summary-test]' );
		var status = wrap.querySelector( '[data-iftp-summary-status]' );

		if ( ! testBtn || ! status ) {
			return;
		}

		testBtn.addEventListener( 'click', function () {
			var label = testBtn.textContent;

			testBtn.disabled = true;
			testBtn.textContent = i18n.sending;
			status.textContent = '';

			post( 'iftp_btn_send_summary_test', { summary_email: wrap.querySelector( '[data-iftp-summary-email]' ).value.trim() } )
				.then( function ( response ) {
					// Both ways the server says what happened, e.g. "Summary sent to …".
					status.textContent = errorMessage( response );
					status.classList.toggle( 'iftp-btn-field__error', ! ( response && response.success ) );
				} )
				.catch( function () {
					status.textContent = i18n.error;
					status.classList.add( 'iftp-btn-field__error' );
				} )
				.then( function () {
					testBtn.disabled = false;
					testBtn.textContent = label;
				} );
		} );
	}
	// Receipt logo: the picker only shows for "An image of mine", and fills the hidden id the form saves.
	( function () {
		var box = document.querySelector( '[data-iftp-receipt]' );

		if ( ! box ) {
			return;
		}

		var choice = box.querySelector( '[data-iftp-receipt-logo]' );
		var row = box.querySelector( '[data-iftp-receipt-image]' );
		var pick = box.querySelector( '[data-iftp-receipt-pick]' );
		var clear = box.querySelector( '[data-iftp-receipt-clear]' );
		var thumb = box.querySelector( '[data-iftp-receipt-thumb]' );
		var field = box.querySelector( '[data-iftp-receipt-id]' );
		var frame = null;

		function set( id, url ) {
			field.value = id;
			thumb.style.backgroundImage = url ? 'url("' + url.replace( /["\\]/g, '' ) + '")' : '';
			clear.hidden = ! id;
		}

		choice.addEventListener( 'change', function () {
			row.hidden = 'image' !== choice.value;
		} );

		// The colour picker only matters while colours are on.
		var colours = box.querySelector( '[data-iftp-receipt-colour]' );
		var accent = box.querySelector( '[data-iftp-receipt-accent]' );

		if ( colours && accent ) {
			colours.addEventListener( 'change', function () {
				accent.hidden = ! colours.checked;
			} );
		}

		// The swatch and the hex field are one colour: either one moves the other.
		var swatch = box.querySelector( '[data-iftp-receipt-swatch]' );
		var hex = box.querySelector( '[data-iftp-receipt-hex]' );

		if ( swatch && hex ) {
			swatch.addEventListener( 'input', function () {
				hex.value = swatch.value;
			} );

			hex.addEventListener( 'input', function () {
				if ( /^#[0-9a-f]{6}$/i.test( hex.value ) ) {
					swatch.value = hex.value;
				}
			} );
		}

		// The Media Library sits outside the form, so it only works when wp.media is around.
		if ( window.wp && window.wp.media ) {
			pick.addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = window.wp.media( {
						title: i18n.pickImage,
						button: { text: i18n.useImage },
						library: { type: 'image' },
						multiple: false,
					} );

					frame.on( 'select', function () {
						var chosen = frame.state().get( 'selection' ).first().toJSON();
						var sizes = chosen.sizes || {};

						set( String( chosen.id ), ( sizes.medium || sizes.full || chosen ).url );
					} );
				}

				frame.open();
			} );

			clear.addEventListener( 'click', function () {
				set( '0', '' );
			} );
		}
	}() );
} )();
