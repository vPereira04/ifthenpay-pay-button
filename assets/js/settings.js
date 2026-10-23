/* global iftpBtnSettings */
// Gateway Configuration: the Connect / Disconnect dialogs, the Gateway Key switch, the methods table and the save.
( function () {
	'use strict';

	if ( 'undefined' === typeof iftpBtnSettings ) {
		return;
	}

	var i18n = iftpBtnSettings.i18n;

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
		bindSettingsForm( wrap );
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

					// The button turns into Disconnect and the configuration appears, both rendered server-side.
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

	function bindSettingsForm( wrap ) {
		var form = wrap.querySelector( '[data-iftp-settings-form]' );

		if ( ! form ) {
			return;
		}

		var methods = form.querySelector( '[data-iftp-methods]' );
		var gatewayKey = form.querySelector( '[data-iftp-gateway-key]' );
		var saveBtn = form.querySelector( '[data-iftp-save]' );
		var saveLabel = saveBtn.textContent;
		var status = form.querySelector( '[data-iftp-save-status]' );
		var statusTimer = null;

		// One live region by Save for every message on this screen. Errors stay; a success clears after a while.
		function showStatus( message, isError ) {
			clearTimeout( statusTimer );
			status.textContent = message;
			status.classList.toggle( 'iftp-btn-save-status--error', !! isError );

			if ( ! isError ) {
				statusTimer = setTimeout( function () {
					status.textContent = '';
				}, 4000 );
			}
		}

		function swapTable( html ) {
			methods.innerHTML = html;
		}

		gatewayKey.addEventListener( 'change', function () {
			methods.setAttribute( 'aria-busy', 'true' );
			gatewayKey.disabled = true;
			showStatus( i18n.loading, false );

			post( 'iftp_btn_select_gateway_key', { gateway_key: gatewayKey.value } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showStatus( errorMessage( response ), true );
						return;
					}

					swapTable( response.data.tableHtml );
					showStatus( '', false );
				} )
				.catch( function () {
					showStatus( i18n.error, true );
				} )
				.then( function () {
					methods.removeAttribute( 'aria-busy' );
					gatewayKey.disabled = false;
				} );
		} );

		// The star only exists while its row is enabled; disabling the default method clears the default.
		methods.addEventListener( 'change', function ( event ) {
			var target = event.target;

			if ( target.matches( '[data-iftp-method-enabled]' ) ) {
				syncRow( target.closest( '[data-iftp-method-row]' ), target.checked );
				syncStars( methods );
			} else if ( target.matches( '[data-iftp-method-default]' ) ) {
				syncStars( methods );
				wink( target.closest( 'td' ).querySelector( '[data-iftp-star]' ) );
			}
		} );

		// The display mode previews the moment it's picked; Save keeps it.
		form.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches( '[data-iftp-display-mode]' ) ) {
				return;
			}

			document.body.className = document.body.className.replace( /\biftp-btn-mode-\w+/g, '' ).trim() + ' iftp-btn-mode-' + event.target.value;

			form.querySelectorAll( '[data-iftp-focus-only]' ).forEach( function ( tools ) {
				tools.hidden = 'focus' !== event.target.value;
			} );
		} );

		// Sends the summary now, to whatever address is in the field, saved or not.
		var testBtn = form.querySelector( '[data-iftp-summary-test]' );

		if ( testBtn ) {
			testBtn.addEventListener( 'click', function () {
				var label = testBtn.textContent;

				testBtn.disabled = true;
				testBtn.textContent = i18n.sending;

				post( 'iftp_btn_send_summary_test', { summary_email: form.querySelector( '[data-iftp-summary-email]' ).value.trim() } )
					.then( function ( response ) {
						showStatus( errorMessage( response ), ! ( response && response.success ) );
					} )
					.catch( function () {
						showStatus( i18n.error, true );
					} )
					.then( function () {
						testBtn.disabled = false;
						testBtn.textContent = label;
					} );
			} );
		}

		methods.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-iftp-request-activation]' );

			if ( button ) {
				requestActivation( button, showStatus );
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			var data = {};

			event.preventDefault();

			if ( ! form.reportValidity() ) {
				return;
			}

			new FormData( form ).forEach( function ( value, name ) {
				if ( 'enabled_methods[]' === name ) {
					data.enabled_methods = ( data.enabled_methods || [] ).concat( value );
				} else {
					data[ name ] = value;
				}
			} );

			saveBtn.disabled = true;
			saveBtn.textContent = i18n.saving;

			post( 'iftp_btn_save_settings', data )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showStatus( errorMessage( response ), true );
						return;
					}

					swapTable( response.data.tableHtml );
					showStatus( i18n.saved, false );
				} )
				.catch( function () {
					showStatus( i18n.error, true );
				} )
				.then( function () {
					saveBtn.disabled = false;
					saveBtn.textContent = saveLabel;
				} );
		} );
	}

	function syncRow( row, enabled ) {
		var radio = row.querySelector( '[data-iftp-method-default]' );
		var star = row.querySelector( '[data-iftp-star]' );
		var badge = row.querySelector( '[data-iftp-method-status]' );

		radio.disabled = ! enabled;
		star.hidden = ! enabled;
		star.classList.toggle( 'iftp-btn-star--hidden', ! enabled );

		if ( ! enabled ) {
			radio.checked = false;
		}

		// Only a preview; it's stored with the rest of the screen on Save.
		if ( badge ) {
			badge.classList.toggle( 'iftp-btn-method-status--enabled', enabled );
			badge.classList.toggle( 'iftp-btn-method-status--activated', ! enabled );
			badge.textContent = badge.getAttribute( enabled ? 'data-label-enabled' : 'data-label-activated' );
		}
	}

	// Filled star for the checked radio, outline for the rest, so the default reads even without styles.
	function syncStars( scope ) {
		scope.querySelectorAll( '[data-iftp-method-default]' ).forEach( function ( radio ) {
			var star = radio.closest( 'td' ).querySelector( '[data-iftp-star]' );
			var glyph = star.querySelector( '[data-iftp-star-glyph]' );

			star.classList.toggle( 'iftp-btn-star--checked', radio.checked );
			glyph.textContent = radio.checked ? '★' : '☆';
		} );
	}

	// Remove, reflow, re-add: that replays the wink even when the same star is picked again.
	function wink( star ) {
		if ( ! star ) {
			return;
		}

		star.classList.remove( 'iftp-btn-star--wink' );
		void star.offsetWidth;
		star.classList.add( 'iftp-btn-star--wink' );
	}

	// Each method's 24h cooldown is enforced server-side; the disabled "Requested" button only mirrors it.
	function requestActivation( button, showStatus ) {
		var original = button.textContent;

		button.disabled = true;
		button.textContent = i18n.requesting;

		post( 'iftp_btn_request_activation', { entity: button.getAttribute( 'data-iftp-request-activation' ) } )
			.then( function ( response ) {
				if ( ! response || ! response.success ) {
					return Promise.reject( errorMessage( response ) );
				}

				button.textContent = i18n.requested;
				showStatus( i18n.requestSent, false );
			} )
			.catch( function ( error ) {
				button.disabled = false;
				button.textContent = original;
				showStatus( 'string' === typeof error ? error : i18n.error, true );
			} );
	}
} )();
