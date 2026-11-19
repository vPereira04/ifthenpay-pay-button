/**
 * Buttons and Checkout screens, the delete, help and QR code dialogs.
 */
( function ( ns ) {
	'use strict';

	const { bindTags, copyText, i18n, toast } = ns;

	document.querySelectorAll( '[data-iftp-copy]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			const source = document.getElementById( button.dataset.iftpCopy );
			const label = button.textContent;

			if ( ! source ) {
				return;
			}

			copyText( source.value ).then( function ( ok ) {
				if ( ! ok ) {
					source.select();
					toast( i18n.copyFailed, true );

					return;
				}

				button.textContent = i18n.copied;
				window.setTimeout( function () {
					button.textContent = label;
				}, 1600 );
			} );
		} );
	} );

	document.querySelectorAll( '[data-iftp-copy-text]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			copyText( button.dataset.iftpCopyText ).then( function ( ok ) {
				toast( ok ? button.dataset.iftpCopyDone || i18n.copied : i18n.copyFailed, ! ok );

				if ( ok ) {
					button.classList.add( 'is-done' );
					window.setTimeout( function () {
						button.classList.remove( 'is-done' );
					}, 1400 );
				}
			} );
		} );
	} );

	document.querySelectorAll( '[data-iftp-copy-source]' ).forEach( function ( input ) {
		input.addEventListener( 'focus', function () {
			input.select();
		} );
	} );

	/* ---------------------------------------------------------------- Delete dialog */

	// Set while a click is on its way somewhere on purpose, so the unsaved-changes guard stays quiet.
	ns.leaving = false;
	const deleteDialog = document.querySelector( '[data-iftp-delete-dialog]' );

	document.querySelectorAll( '[data-iftp-confirm-delete]' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			const name = link.dataset.iftpConfirmDelete;

			// No <dialog> is only an old browser; the delete is just a trash with Undo, so I go straight on.
			if ( ! deleteDialog || typeof deleteDialog.showModal !== 'function' ) {
				ns.leaving = true;
				window.location.href = link.href;

				return;
			}

			if ( name ) {
				deleteDialog.querySelector( '[data-iftp-delete-title]' ).textContent = ( i18n.deleteNamed || '%s' ).replace( '%s', name );
			}

			deleteDialog.querySelector( '[data-iftp-delete-ok]' ).href = link.href;
			deleteDialog.showModal();
		} );
	} );

	if ( deleteDialog ) {
		deleteDialog.querySelector( '[data-iftp-delete-cancel]' ).addEventListener( 'click', function () {
			deleteDialog.close();
		} );

		deleteDialog.querySelector( '[data-iftp-delete-ok]' ).addEventListener( 'click', function () {
			ns.leaving = true;
		} );

		// A click on the backdrop lands on the dialog itself.
		deleteDialog.addEventListener( 'click', function ( event ) {
			if ( event.target === deleteDialog ) {
				deleteDialog.close();
			}
		} );
	}

	/* ---------------------------------------------------------------- Help dialog */

	const helpDialog = document.querySelector( '[data-iftp-help-dialog]' );

	if ( helpDialog && typeof helpDialog.showModal === 'function' ) {
		document.querySelectorAll( '[data-iftp-help-open]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				helpDialog.showModal();
			} );
		} );

		helpDialog.querySelector( '[data-iftp-help-close]' ).addEventListener( 'click', function () {
			helpDialog.close();
		} );

		// A click on the backdrop lands on the dialog itself.
		helpDialog.addEventListener( 'click', function ( event ) {
			if ( event.target === helpDialog ) {
				helpDialog.close();
			}
		} );
	}

	/* ---------------------------------------------------------------- QR code */

	const qrDialog = document.querySelector( '[data-iftp-qr-dialog]' );
	let qrState = null;

	// The code as a grid of modules (4 of quiet zone round it), or null when the link won't encode.
	function qrModules( text ) {
		if ( typeof window.qrcode !== 'function' ) {
			return null;
		}

		try {
			const qr = window.qrcode( 0, 'M' );

			qr.addData( text, 'Byte' );
			qr.make();

			return { count: qr.getModuleCount(), dark: qr.isDark.bind( qr ) };
		} catch ( e ) {
			return null;
		}
	}

	function qrSvg( modules ) {
		const size = modules.count + 8;
		let path = '';

		for ( let row = 0; row < modules.count; row++ ) {
			for ( let col = 0; col < modules.count; col++ ) {
				if ( modules.dark( row, col ) ) {
					path += 'M' + ( col + 4 ) + ' ' + ( row + 4 ) + 'h1v1h-1z';
				}
			}
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges"><rect width="' + size + '" height="' + size + '" fill="#fff"/><path d="' + path + '" fill="#000"/></svg>';
	}

	function qrFileName( name, ext ) {
		const base = ( name || 'pay-button' ).toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).replace( /[^a-z0-9]+/g, '-' ).replace( /^-|-$/g, '' );

		return 'qr-' + ( base || 'pay-button' ) + '.' + ext;
	}

	function saveBlob( blob, fileName ) {
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );

		a.href = url;
		a.download = fileName;
		document.body.appendChild( a );
		a.click();
		a.remove();
		window.setTimeout( function () {
			URL.revokeObjectURL( url );
		}, 1000 );
	}

	function downloadQr( kind ) {
		if ( ! qrState ) {
			return;
		}

		if ( kind === 'svg' ) {
			saveBlob( new Blob( [ qrSvg( qrState.modules ) ], { type: 'image/svg+xml' } ), qrFileName( qrState.name, 'svg' ) );

			return;
		}

		// PNG: a whole number of pixels per module so the edges stay sharp when printed.
		const total = qrState.modules.count + 8;
		const cell = Math.max( 1, Math.floor( 1024 / total ) );
		const canvas = document.createElement( 'canvas' );
		const ctx = canvas.getContext( '2d' );

		canvas.width = total * cell;
		canvas.height = total * cell;
		ctx.fillStyle = '#fff';
		ctx.fillRect( 0, 0, canvas.width, canvas.height );
		ctx.fillStyle = '#000';

		for ( let row = 0; row < qrState.modules.count; row++ ) {
			for ( let col = 0; col < qrState.modules.count; col++ ) {
				if ( qrState.modules.dark( row, col ) ) {
					ctx.fillRect( ( col + 4 ) * cell, ( row + 4 ) * cell, cell, cell );
				}
			}
		}

		canvas.toBlob( function ( blob ) {
			if ( blob ) {
				saveBlob( blob, qrFileName( qrState.name, 'png' ) );
			} else {
				toast( i18n.qrFailed, true );
			}
		}, 'image/png' );
	}

	document.querySelectorAll( '[data-iftp-qr]' ).forEach( function ( trigger ) {
		trigger.addEventListener( 'click', function () {
			const link = trigger.dataset.iftpQr;
			const modules = qrModules( link );

			if ( ! qrDialog || ! modules || typeof qrDialog.showModal !== 'function' ) {
				toast( i18n.qrFailed, true );

				return;
			}

			qrState = { modules: modules, name: trigger.dataset.iftpQrName || '' };
			// The SVG is made here from the module grid, never from page text.
			qrDialog.querySelector( '[data-iftp-qr-preview]' ).innerHTML = qrSvg( modules );
			qrDialog.querySelector( '[data-iftp-qr-link]' ).textContent = link;
			qrDialog.showModal();
		} );
	} );

	if ( qrDialog ) {
		qrDialog.querySelector( '[data-iftp-qr-close]' ).addEventListener( 'click', function () {
			qrDialog.close();
		} );

		qrDialog.querySelectorAll( '[data-iftp-qr-download]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				downloadQr( button.dataset.iftpQrDownload );
			} );
		} );

		qrDialog.addEventListener( 'click', function ( event ) {
			if ( event.target === qrDialog ) {
				qrDialog.close();
			}
		} );
	}

	bindTags( document );
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
