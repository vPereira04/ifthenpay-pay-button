/* global iftpBtnAi */
// The AI assistant: the connect form and model picker (Gateway Configuration and the studio), and the studio's
// chat, live pay page preview, undo and start over. The key goes to the server once and never comes back.
( function () {
	'use strict';

	if ( 'undefined' === typeof iftpBtnAi ) {
		return;
	}

	const i18n = iftpBtnAi.i18n || {};

	function post( action, data ) {
		const body = new URLSearchParams( { action, _ajax_nonce: iftpBtnAi.nonce } );

		Object.keys( data || {} ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( iftpBtnAi.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } ).then( function ( response ) {
			// A proxy's timeout page isn't JSON: the work may still be going on.
			return response.json().catch( function () {
				return { success: false, lost: true };
			} );
		} );
	}

	function errorOf( response ) {
		return ( response && response.data && response.data.message ) || i18n.error;
	}

	function toast( message, isError ) {
		const box = document.querySelector( '[data-iftp-toast]' );

		if ( ! box ) {
			return;
		}

		box.querySelector( '[data-iftp-toast-message]' ).textContent = message;
		box.classList.toggle( 'iftp-btn-toast--error', !! isError );
		box.hidden = false;

		window.clearTimeout( toast.timer );
		toast.timer = window.setTimeout( function () {
			box.hidden = true;
		}, 3200 );
	}

	function el( tag, className, text ) {
		const node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( text ) {
			node.textContent = text;
		}

		return node;
	}

	/* ---------------------------------------------------------------- Connect */

	function bindConnect() {
		const form = document.querySelector( '[data-iftp-ai-connect-form]' );

		if ( ! form ) {
			return;
		}

		const dialog = form.closest( 'dialog' );
		const opener = document.querySelector( '[data-iftp-ai-connect-open]' );
		const key = form.querySelector( '[data-iftp-ai-key]' );
		const error = form.querySelector( '[data-iftp-ai-connect-error]' );
		const submit = form.querySelector( '[data-iftp-ai-connect-submit]' );
		const link = form.querySelector( '[data-iftp-ai-key-link]' );

		function provider() {
			const checked = form.querySelector( '[data-iftp-ai-provider]:checked' );

			return checked ? checked.value : '';
		}

		function fail( message ) {
			error.textContent = message;
			key.setAttribute( 'aria-invalid', 'true' );
			key.focus();
			submit.disabled = false;
			submit.textContent = i18n.connect;
		}

		// "Get a … key" follows the picked provider.
		form.addEventListener( 'change', function ( event ) {
			const meta = iftpBtnAi.providers[ provider() ];

			if ( ! event.target.matches( '[data-iftp-ai-provider]' ) || ! meta || ! link ) {
				return;
			}

			link.href = meta.keyUrl;
			link.firstChild.nodeValue = ( i18n.getKey || '%s' ).replace( '%s', meta.label );
		} );

		if ( opener && dialog ) {
			opener.addEventListener( 'click', function () {
				error.textContent = '';
				key.removeAttribute( 'aria-invalid' );
				dialog.showModal();
				key.focus();
			} );

			dialog.addEventListener( 'close', function () {
				// The key shouldn't sit in the page once the popup is gone.
				key.value = '';
				opener.focus();
			} );

			dialog.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-iftp-dialog-close]' ) ) {
					dialog.close();
				}
			} );
		}

		form.addEventListener( 'submit', function ( event ) {
			const value = String( key.value || '' ).trim();

			event.preventDefault();

			if ( ! value ) {
				fail( i18n.needKey );
				return;
			}

			error.textContent = '';
			key.removeAttribute( 'aria-invalid' );
			submit.disabled = true;
			submit.textContent = i18n.connecting;

			post( 'iftp_btn_ai_connect', { provider: provider(), key: value } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						fail( errorOf( response ) );
						return;
					}

					key.value = '';
					// The connected state and the chat are drawn by the server.
					window.location.reload();
				} )
				.catch( function () {
					fail( i18n.error );
				} );
		} );
	}

	function bindStatus() {
		const model = document.querySelector( '[data-iftp-ai-model]' );
		const opener = document.querySelector( '[data-iftp-ai-disconnect-open]' );
		const dialog = document.querySelector( '[data-iftp-ai-disconnect-dialog]' );

		if ( model ) {
			model.addEventListener( 'change', function () {
				post( 'iftp_btn_ai_model', { model: model.value } ).then( function ( response ) {
					toast( response && response.success ? i18n.modelSaved : errorOf( response ), ! ( response && response.success ) );
				} );
			} );
		}

		if ( ! opener || ! dialog ) {
			return;
		}

		opener.addEventListener( 'click', function () {
			dialog.showModal();
		} );

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-iftp-dialog-close]' ) ) {
				dialog.close();
			}

			if ( ! event.target.closest( '[data-iftp-ai-disconnect-confirm]' ) ) {
				return;
			}

			event.target.disabled = true;
			post( 'iftp_btn_ai_disconnect', {} ).then( function ( response ) {
				if ( response && response.success ) {
					window.location.reload();
					return;
				}

				event.target.disabled = false;
				toast( errorOf( response ), true );
			} );
		} );
	}

	/* ---------------------------------------------------------------- Studio */

	function bindStudio() {
		const root = document.querySelector( '[data-iftp-studio]' );
		const studio = iftpBtnAi.studio || {};

		if ( ! root || ! studio.view ) {
			return;
		}

		const log = root.querySelector( '[data-iftp-studio-log]' );
		const ideas = root.querySelector( '[data-iftp-studio-ideas]' );
		const form = root.querySelector( '[data-iftp-studio-form]' );
		const input = root.querySelector( '[data-iftp-studio-input]' );
		const send = root.querySelector( '[data-iftp-studio-send]' );
		const frame = root.querySelector( '[data-iftp-studio-frame]' );
		const shell = root.querySelector( '[data-iftp-studio-shell]' );
		const iframe = root.querySelector( '[data-iftp-studio-iframe]' );
		const url = root.querySelector( '[data-iftp-studio-url]' );
		const open = root.querySelector( '[data-iftp-studio-open]' );
		const buildingText = root.querySelector( '[data-iftp-studio-building-text]' );
		const empty = root.querySelector( '[data-iftp-studio-empty]' );
		const building = root.querySelector( '[data-iftp-studio-building]' );
		const buttonSlot = root.querySelector( '[data-iftp-studio-button]' );
		const review = root.querySelector( '[data-iftp-studio-review]' );
		const prices = root.querySelector( '[data-iftp-studio-prices]' );
		const undo = root.querySelector( '[data-iftp-studio-undo]' );
		const reset = root.querySelector( '[data-iftp-studio-reset]' );
		const resetDialog = root.querySelector( '[data-iftp-studio-reset-dialog]' );
		let view = studio.view;
		let busy = false;
		let lastMessage = '';
		let thinkingTimer = 0;

		/* ---- Messages */

		function avatar( role ) {
			const mark = el( 'span', 'iftp-btn-studio__avatar iftp-btn-studio__avatar--' + role );

			mark.setAttribute( 'aria-hidden', 'true' );

			if ( 'assistant' === role ) {
				mark.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M10.5 3.5c.4 3.9 2.6 6.1 6.5 6.5-3.9.4-6.1 2.6-6.5 6.5-.4-3.9-2.6-6.1-6.5-6.5 3.9-.4 6.1-2.6 6.5-6.5z"/><path d="M18 14c.2 1.9 1.1 2.8 3 3-1.9.2-2.8 1.1-3 3-.2-1.9-1.1-2.8-3-3 1.9-.2 2.8-1.1 3-3z"/></svg>';
			}

			return mark;
		}

		function bubble( role, text, extra ) {
			const row = el( 'div', 'iftp-btn-studio__msg iftp-btn-studio__msg--' + role + ( extra ? ' ' + extra : '' ) );
			const body = el( 'div', 'iftp-btn-studio__bubble' );
			const who = el( 'span', 'screen-reader-text', ( 'user' === role ? i18n.you : i18n.assistant ) + ': ' );

			body.appendChild( who );
			body.appendChild( document.createTextNode( text ) );
			row.appendChild( avatar( role ) );
			row.appendChild( body );

			return row;
		}

		function changeList( changes ) {
			const list = el( 'ul', 'iftp-btn-studio__changes' );

			changes.forEach( function ( change ) {
				const item = el( 'li', 'iftp-btn-studio__change' + ( change.money ? ' iftp-btn-studio__change--money' : '' ), change.text );

				if ( change.money ) {
					item.title = i18n.checkMoney;
				}

				list.appendChild( item );
			} );

			return list;
		}

		function addMessage( message ) {
			const row = bubble( message.role, message.text );

			if ( message.changes && message.changes.length ) {
				row.querySelector( '.iftp-btn-studio__bubble' ).appendChild( changeList( message.changes ) );
			}

			if ( message.note ) {
				row.querySelector( '.iftp-btn-studio__bubble' ).appendChild( el( 'span', 'iftp-btn-studio__note-line', message.note ) );
			}

			log.appendChild( row );

			return row;
		}

		function renderLog() {
			log.textContent = '';
			log.appendChild( bubble( 'assistant', studio.isNew ? i18n.welcomeNew : ( i18n.welcomeEdit || '%s' ).replace( '%s', studio.title ), 'iftp-btn-studio__msg--welcome' ) );

			view.messages.forEach( function ( message ) {
				addMessage( message );
			} );

			scrollDown();
		}

		function scrollDown() {
			log.scrollTop = log.scrollHeight;
		}

		/* ---- Ideas */

		function renderIdeas() {
			const list = studio.isNew ? i18n.ideasNew : i18n.ideasEdit;

			ideas.textContent = '';
			ideas.hidden = view.messages.length > 0;

			( list || [] ).forEach( function ( idea ) {
				const chip = el( 'button', 'iftp-btn-studio__idea', idea );

				chip.type = 'button';
				chip.addEventListener( 'click', function () {
					submitMessage( idea );
				} );
				ideas.appendChild( chip );
			} );
		}

		/* ---- Preview */

		// The phone is drawn at a real phone's size and shrunk to fit; the desktop fills the stage.
		const phoneSize = { width: 390, height: 844 };

		// A comfortable size for the screen it's on, never tiny: the stage grows to fit it instead.
		function fit() {
			const phone = 'phone' === frame.dataset.device;

			root.classList.toggle( 'is-phone', phone );

			if ( ! phone ) {
				frame.style.removeProperty( '--iftp-scale' );
				return;
			}

			const scale = Math.min( 0.9, Math.max( 0.74, ( window.innerHeight - 200 ) / phoneSize.height ), ( frame.clientWidth - 48 ) / phoneSize.width );

			frame.style.setProperty( '--iftp-scale', scale.toFixed( 3 ) );
		}

		function slugOf( title ) {
			return String( title || '' )
				.toLowerCase()
				.normalize( 'NFD' )
				.replace( /[\u0300-\u036f]/g, '' )
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-+|-+$/g, '' );
		}

		function loadPreview() {
			const show = view.built || ! studio.isNew;

			empty.hidden = show;
			shell.hidden = ! show;
			review.hidden = ! view.built;
			buttonSlot.parentNode.hidden = ! show;
			open.hidden = ! show;
			open.href = view.preview;
			frame.classList.toggle( 'has-preview', show );

			if ( reset ) {
				reset.hidden = ! view.messages.length;
			}

			undo.disabled = ! view.canUndo;
			// Server-built, escaped markup from ButtonDesign::preview_markup().
			buttonSlot.innerHTML = view.button;
			url.textContent = ( studio.payBase || '' ) + ( studio.isNew ? slugOf( view.title ) : studio.slug );

			prices.textContent = '';

			if ( view.built && view.prices.length ) {
				prices.appendChild( el( 'span', 'iftp-btn-studio__prices-label', i18n.checkPrices ) );
				view.prices.forEach( function ( price ) {
					prices.appendChild( el( 'span', 'iftp-btn-studio__price', price ) );
				} );
			}

			if ( ! show ) {
				return;
			}

			fit();

			if ( iframe.getAttribute( 'src' ) !== view.preview ) {
				frame.classList.add( 'is-loading' );
				iframe.setAttribute( 'src', view.preview );
			}
		}

		iframe.addEventListener( 'load', function () {
			frame.classList.remove( 'is-loading' );
		} );

		root.querySelectorAll( '[data-iftp-studio-device]' ).forEach( function ( toggle, index, all ) {
			toggle.addEventListener( 'click', function () {
				all.forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === toggle ? 'true' : 'false' );
				} );
				frame.dataset.device = toggle.dataset.iftpStudioDevice;
				fit();
			} );
		} );

		window.addEventListener( 'resize', fit );

		/* ---- Sending */

		// One line at a time, each sliding in over the last; the stage shows the same line under the card it draws.
		function say( holder, line ) {
			const next = el( 'span', 'iftp-btn-studio__line', line );
			const prev = holder.querySelector( '.iftp-btn-studio__line:not(.is-out)' );

			if ( prev ) {
				prev.classList.add( 'is-out' );
				window.setTimeout( function () {
					prev.remove();
				}, 400 );
			}

			holder.appendChild( next );
		}

		function thinking( on ) {
			const existing = log.querySelector( '.iftp-btn-studio__msg--thinking' );

			window.clearInterval( thinkingTimer );

			if ( existing ) {
				existing.remove();
			}

			building.hidden = ! on;
			frame.classList.toggle( 'is-building', on );
			buildingText.textContent = '';

			if ( ! on ) {
				return;
			}

			const lines = i18n.thinking || [ '…' ];
			const row = bubble( 'assistant', '', 'iftp-btn-studio__msg--thinking' );
			const text = el( 'span', 'iftp-btn-studio__thinking' );
			const dots = el( 'span', 'iftp-btn-studio__dots' );
			let step = 0;

			dots.setAttribute( 'aria-hidden', 'true' );
			dots.innerHTML = '<i></i><i></i><i></i>';
			row.querySelector( '.iftp-btn-studio__bubble' ).append( dots, text );
			log.appendChild( row );
			say( text, lines[ 0 ] );
			say( buildingText, lines[ 0 ] );
			scrollDown();

			// The last line stays once it's reached; a long answer just keeps thinking on it.
			thinkingTimer = window.setInterval( function () {
				if ( step >= lines.length - 1 ) {
					return;
				}

				step++;
				say( text, lines[ step ] );
				say( buildingText, lines[ step ] );
			}, 2800 );
		}

		function setBusy( on ) {
			busy = on;
			send.disabled = on;
			input.setAttribute( 'aria-busy', on ? 'true' : 'false' );
			undo.disabled = on || ! view.canUndo;
			root.classList.toggle( 'is-busy', on );
		}

		function showError( message ) {
			const row = bubble( 'assistant', message, 'iftp-btn-studio__msg--error' );
			const retry = el( 'button', 'iftp-btn-studio__retry', i18n.retry );

			retry.type = 'button';
			retry.addEventListener( 'click', function () {
				row.remove();
				submitMessage( lastMessage, true );
			} );
			row.querySelector( '.iftp-btn-studio__bubble' ).appendChild( retry );
			log.appendChild( row );
			scrollDown();
		}

		function submitMessage( text, isRetry ) {
			const message = String( text || '' ).trim();

			if ( busy || ! message ) {
				return;
			}

			if ( message.length > ( iftpBtnAi.maxLength || 2000 ) ) {
				toast( i18n.tooLong, true );
				return;
			}

			lastMessage = message;
			ideas.hidden = true;

			if ( ! isRetry ) {
				addMessage( { role: 'user', text: message } );
			}

			input.value = '';
			autosize();
			setBusy( true );
			thinking( true );

			const job = Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 10 );

			function done( response ) {
				thinking( false );
				setBusy( false );

				if ( ! response || ! response.success ) {
					showError( errorOf( response ) );
					input.focus();
					return;
				}

				view = response.data;
				addMessage( view.messages[ view.messages.length - 1 ] );
				scrollDown();
				loadPreview();
				input.focus();
			}

			// The request was cut off on the way back, but PHP carries on: ask how it went until it says.
			function poll( tries ) {
				window.setTimeout( function () {
					post( 'iftp_btn_ai_poll', { target: studio.target, job } )
						.then( function ( response ) {
							const state = response && response.success ? response.data.state : 'lost';

							if ( 'done' === state ) {
								done( response );
							} else if ( 'error' === state ) {
								done( { success: false, data: { message: response.data.message } } );
							} else if ( tries > 0 && 'unknown' !== state ) {
								poll( tries - 1 );
							} else {
								done( { success: false } );
							}
						} )
						.catch( function () {
							return tries > 0 ? poll( tries - 1 ) : done( { success: false } );
						} );
				}, 3000 );
			}

			post( 'iftp_btn_ai_send', { target: studio.target, message, job } )
				.then( function ( response ) {
					return response && response.lost ? poll( 80 ) : done( response );
				} )
				.catch( function () {
					poll( 80 );
				} );
		}

		function autosize() {
			input.style.height = 'auto';
			input.style.height = Math.min( input.scrollHeight, 180 ) + 'px';
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submitMessage( input.value );
		} );

		input.addEventListener( 'input', autosize );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key && ! event.shiftKey && ! event.isComposing ) {
				event.preventDefault();
				submitMessage( input.value );
			}
		} );

		/* ---- Undo and start over */

		function replace( response, note ) {
			if ( ! response || ! response.success ) {
				toast( errorOf( response ), true );
				return;
			}

			view = response.data;
			renderLog();
			renderIdeas();
			loadPreview();

			if ( note ) {
				toast( note );
			}
		}

		undo.addEventListener( 'click', function () {
			if ( busy ) {
				return;
			}

			setBusy( true );
			post( 'iftp_btn_ai_undo', { target: studio.target } ).then( function ( response ) {
				setBusy( false );
				replace( response, i18n.undone );
			} );
		} );

		if ( reset && resetDialog ) {
			reset.addEventListener( 'click', function () {
				resetDialog.showModal();
			} );

			resetDialog.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-iftp-dialog-close]' ) ) {
					resetDialog.close();
				}

				if ( ! event.target.closest( '[data-iftp-studio-reset-confirm]' ) ) {
					return;
				}

				resetDialog.close();
				post( 'iftp_btn_ai_reset', { target: studio.target } ).then( function ( response ) {
					replace( response );
					input.focus();
				} );
			} );
		}

		renderLog();
		renderIdeas();
		loadPreview();
		input.focus();
	}

	function init() {
		bindConnect();
		bindStatus();
		bindStudio();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
