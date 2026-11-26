/* global iftpBtnAi */
// The AI assistant: the connect form and model picker (Configuration › AI and the studio), and the studio's
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
		const progress = root.querySelector( '[data-iftp-studio-progress]' );
		const empty = root.querySelector( '[data-iftp-studio-empty]' );
		const building = root.querySelector( '[data-iftp-studio-building]' );
		const review = root.querySelector( '[data-iftp-studio-review]' );
		const prices = root.querySelector( '[data-iftp-studio-prices]' );
		const undo = root.querySelector( '[data-iftp-studio-undo]' );
		const reset = root.querySelector( '[data-iftp-studio-reset]' );
		const resetDialog = root.querySelector( '[data-iftp-studio-reset-dialog]' );
		const draft = root.querySelector( '[data-iftp-studio-draft]' );
		const rail = root.querySelector( '[data-iftp-studio-history]' );
		const chatList = root.querySelector( '[data-iftp-studio-chats]' );
		const chatTitle = root.querySelector( '[data-iftp-studio-chat-title]' );
		const historyFine = root.querySelector( '[data-iftp-studio-history-fine]' );
		const deleteDialog = root.querySelector( '[data-iftp-studio-delete-dialog]' );
		let view = studio.view;
		let chat = studio.chat;
		let busy = false;
		let lastMessage = '';
		let thinkingTimer = 0;
		let thinkingStage = '';

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
			log.appendChild( bubble( 'assistant', studio.welcome || ( studio.isNew ? i18n.welcomeNew : ( i18n.welcomeEdit || '%s' ).replace( '%s', studio.title ) ), 'iftp-btn-studio__msg--welcome' ) );

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
			const list = studio.ideas || ( studio.isNew ? i18n.ideasNew : i18n.ideasEdit );

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
			open.hidden = ! show;
			open.href = view.preview;
			frame.classList.toggle( 'has-preview', show );

			if ( reset ) {
				reset.hidden = ! view.messages.length;
			}

			undo.disabled = ! view.canUndo;
			draft.href = studio.draftUrl + ( studio.draftUrl.indexOf( '?' ) > -1 ? '&' : '?' ) + 'ai_draft=' + encodeURIComponent( chat );
			renderChats();
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

		// Light, System or Dark, from the switch above the preview. The sandboxed demo page inside the frame can't read the saved pick,
		// so it is told here, and the full-page tab follows the same way.
		const scheme = window.iftpBtnScheme;

		function paintScheme() {
			if ( ! scheme ) {
				return;
			}

			// The frame is sandboxed, so its document can't be reached: it is told, and paints itself (see scheme.js).
			if ( iframe.contentWindow ) {
				iframe.contentWindow.postMessage( { iftpBtnScheme: scheme.mode() }, '*' );
			}
		}

		iframe.addEventListener( 'load', function () {
			paintScheme();
			frame.classList.remove( 'is-loading' );
		} );

		if ( scheme ) {
			scheme.subscribe( paintScheme );
		}

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

		// While the answer is on its way: the chat walks through what it's doing, then, if it runs long, says so
		// honestly, with the seconds ticking, so a slow answer never looks like a stuck one.
		function thinking( on ) {
			const existing = log.querySelector( '.iftp-btn-studio__msg--thinking' );

			window.clearInterval( thinkingTimer );
			thinkingStage = '';

			if ( existing ) {
				existing.remove();
			}

			building.hidden = ! on;
			frame.classList.toggle( 'is-building', on );
			buildingText.textContent = '';

			if ( progress ) {
				progress.style.setProperty( '--iftp-progress', '0' );
			}

			if ( ! on ) {
				return;
			}

			const lines = i18n.thinking || [ '…' ];
			const slow = i18n.thinkingSlow || [];
			const row = bubble( 'assistant', '', 'iftp-btn-studio__msg--thinking' );
			const text = el( 'span', 'iftp-btn-studio__thinking' );
			const dots = el( 'span', 'iftp-btn-studio__dots' );
			const clock = el( 'span', 'iftp-btn-studio__clock', '0s' );
			const started = Date.now();
			let shown = lines[ 0 ];

			dots.setAttribute( 'aria-hidden', 'true' );
			dots.innerHTML = '<i></i><i></i><i></i>';
			clock.setAttribute( 'aria-hidden', 'true' );
			row.querySelector( '.iftp-btn-studio__bubble' ).append( dots, text, clock );
			log.appendChild( row );
			say( text, shown );
			say( buildingText, shown );
			scrollDown();

			thinkingTimer = window.setInterval( function () {
				const seconds = Math.floor( ( Date.now() - started ) / 1000 );
				let line = lines[ Math.min( Math.floor( seconds / 3 ), lines.length - 1 ) ];

				// Past the usual lines, one that fits how long it's been: [ after seconds, text ].
				slow.forEach( function ( pair ) {
					if ( seconds >= pair[ 0 ] ) {
						line = pair[ 1 ];
					}
				} );

				// What the server says it's doing beats a guess.
				line = thinkingStage || line;

				clock.textContent = seconds < 60 ? seconds + 's' : Math.floor( seconds / 60 ) + ':' + String( seconds % 60 ).padStart( 2, '0' );

				// Fills fast, then ever slower: always moving, never full before the answer is.
				if ( progress ) {
					progress.style.setProperty( '--iftp-progress', ( 0.94 * ( 1 - Math.exp( -seconds / 28 ) ) ).toFixed( 3 ) );
				}

				if ( line !== shown ) {
					shown = line;
					say( text, line );
					say( buildingText, line );
				}
			}, 1000 );
		}

		function setStage( status ) {
			if ( ! status || ! status.stage ) {
				return;
			}

			thinkingStage = ( ( 'retry' === status.stage ? i18n.stageRetry : i18n.stageFallback ) || '' ).replace( '%s', status.model || '' );
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

			const job = Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 10 );
			const waiting = wait( job );

			post( 'iftp_btn_ai_send', { target: studio.target, chat, message, job } )
				.then( function ( response ) {
					return response && response.lost ? waiting.poll() : waiting.done( response );
				} )
				.catch( waiting.poll );
		}

		// Waits on a message the server is answering, and asks every few seconds how it's going: the stage
		// lines while the send is open, and the answer itself if the send got cut off (or the page was reloaded).
		function wait( job ) {
			let settled = false;
			let tries = 80;

			setBusy( true );
			thinking( true );

			function done( response ) {
				if ( settled ) {
					return;
				}

				settled = true;
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
				syncUrl();
				input.focus();
			}

			function check( finish ) {
				return post( 'iftp_btn_ai_poll', { target: studio.target, chat, job } ).then( function ( response ) {
					const state = response && response.success ? response.data.state : 'lost';

					if ( 'done' === state ) {
						done( response );
					} else if ( 'error' === state ) {
						done( { success: false, data: { message: response.data.message } } );
					} else if ( 'working' === state ) {
						setStage( response.data );
					} else if ( finish || 'unknown' === state ) {
						done( { success: false } );
					}
				} );
			}

			// The request was cut off on the way back, but PHP carries on: ask how it went until it says.
			function poll() {
				if ( settled ) {
					return;
				}

				window.setTimeout( function () {
					check( tries <= 0 ).catch( function () {} ).then( function () {
						tries--;
						poll();
					} );
				}, 3000 );
			}

			// Just the stage lines while the send is still open; its own answer settles it.
			( function peek() {
				window.setTimeout( function () {
					if ( settled ) {
						return;
					}

					post( 'iftp_btn_ai_poll', { target: studio.target, chat, job } )
						.then( function ( response ) {
							if ( response && response.success && 'working' === response.data.state ) {
								setStage( response.data );
							}
						} )
						.catch( function () {} )
						.then( peek );
				}, 3000 );
			}() );

			return { done, poll };
		}

		// A message that was on its way when the page loaded: pick the wait back up, or offer to send it again.
		function resume() {
			const pending = view.pending;

			if ( ! pending || ! pending.text ) {
				return;
			}

			lastMessage = pending.text;
			ideas.hidden = true;
			addMessage( { role: 'user', text: pending.text } );

			if ( pending.error || ! pending.job ) {
				showError( pending.error || i18n.error );
				return;
			}

			post( 'iftp_btn_ai_poll', { target: studio.target, chat, job: pending.job } ).then( function ( response ) {
				const status = response && response.success ? response.data : {};

				if ( 'working' === status.state ) {
					wait( pending.job ).poll();
				} else if ( 'done' === status.state ) {
					view = status;
					renderLog();
					loadPreview();
				} else {
					showError( status.message || i18n.error );
				}
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

		/* ---- Chats */

		let toDelete = null;

		function newId() {
			const letters = 'abcdefghijklmnopqrstuvwxyz0123456789';
			const bytes = window.crypto.getRandomValues( new Uint8Array( 10 ) );

			return Array.prototype.map.call( bytes, function ( byte ) {
				return letters.charAt( byte % letters.length );
			} ).join( '' );
		}

		function chatsBusy() {
			if ( busy ) {
				toast( i18n.chatsBusy, true );
			}

			return busy;
		}

		function setHistory( open ) {
			root.classList.toggle( 'is-history-open', open );
			root.querySelectorAll( '.iftp-btn-studio__chats-toggle' ).forEach( function ( toggle ) {
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}

		// The address follows the open chat, so a reload or a shared link comes back to it.
		function syncUrl() {
			const address = new URL( window.location.href );

			if ( address.searchParams.get( 'chat' ) !== chat ) {
				address.searchParams.set( 'chat', chat );
				window.history.replaceState( null, '', address.toString() );
			}
		}

		function chatName( item ) {
			return item.title || i18n.untitled;
		}

		function renameInline( item, title ) {
			const field = el( 'input', 'iftp-btn-studio__chat-rename' );
			let done = false;

			field.type = 'text';
			field.value = item.title;
			field.maxLength = 60;
			field.setAttribute( 'aria-label', i18n.renameLabel );
			title.replaceWith( field );
			field.focus();
			field.select();

			function finish( save ) {
				if ( done ) {
					return;
				}

				done = true;

				if ( ! save || field.value.trim() === item.title ) {
					renderChats();
					return;
				}

				post( 'iftp_btn_ai_chat_rename', { chat: item.id, title: field.value.trim() } ).then( function ( response ) {
					if ( response && response.success ) {
						view.chats = response.data.chats;
					} else {
						toast( errorOf( response ), true );
					}

					renderChats();
				} );
			}

			field.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					finish( true );
				} else if ( 'Escape' === event.key ) {
					event.preventDefault();
					finish( false );
				}
			} );
			field.addEventListener( 'blur', function () {
				finish( true );
			} );
		}

		function iconButton( label, path, className ) {
			const button = el( 'button', 'iftp-btn-studio__chat-action ' + className );

			button.type = 'button';
			button.title = label;
			button.setAttribute( 'aria-label', label );
			button.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + path + '</svg>';

			return button;
		}

		function renderChats() {
			const list = view.chats || [];
			const known = list.some( function ( item ) {
				return item.id === chat;
			} );
			const days = studio.keepDays || 0;

			chatList.textContent = '';

			// A chat that has no message yet isn't kept; it still shows, so the list says where you are.
			if ( ! known ) {
				const draftRow = el( 'li', 'iftp-btn-studio__chat-item is-current is-draft' );
				const draftOpen = el( 'span', 'iftp-btn-studio__chat-open' );

				draftOpen.appendChild( el( 'span', 'iftp-btn-studio__chat-name', i18n.newChat ) );
				draftRow.appendChild( draftOpen );
				chatList.appendChild( draftRow );
			}

			list.forEach( function ( item ) {
				const row = el( 'li', 'iftp-btn-studio__chat-item' + ( item.id === chat ? ' is-current' : '' ) );
				const open = el( 'button', 'iftp-btn-studio__chat-open' );
				const name = el( 'span', 'iftp-btn-studio__chat-name', chatName( item ) );
				const meta = el( 'span', 'iftp-btn-studio__chat-meta' );
				const actions = el( 'span', 'iftp-btn-studio__chat-actions' );
				const rename = iconButton( i18n.rename, '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17z"/><path d="m14.5 7.5 3 3"/>', 'iftp-btn-studio__chat-action--rename' );
				const remove = iconButton( i18n.delete, '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a1.5 1.5 0 0 0 1.5 1.5h7A1.5 1.5 0 0 0 17 19l1-12M9 7V4.5h6V7"/>', 'iftp-btn-studio__chat-action--delete' );

				open.type = 'button';
				open.title = chatName( item );

				if ( item.id === chat ) {
					open.setAttribute( 'aria-current', 'true' );
				}

				meta.append( el( 'span', 'iftp-btn-studio__chat-sub', item.sub ), el( 'span', 'iftp-btn-studio__chat-when', '· ' + item.when ) );
				open.append( name, meta );
				open.addEventListener( 'click', function () {
					openChat( item );
				} );
				rename.addEventListener( 'click', function () {
					if ( ! chatsBusy() ) {
						renameInline( item, name );
					}
				} );
				remove.addEventListener( 'click', function () {
					if ( chatsBusy() ) {
						return;
					}

					toDelete = item;
					deleteDialog.querySelector( '[data-iftp-studio-delete-name]' ).textContent = '“' + chatName( item ) + '”';
					deleteDialog.showModal();
				} );
				actions.append( rename, remove );
				row.append( open, actions );
				chatList.appendChild( row );
			} );

			if ( ! list.length ) {
				chatList.appendChild( el( 'li', 'iftp-btn-studio__chat-empty', i18n.noChats ) );
			}

			const current = list.filter( function ( item ) {
				return item.id === chat;
			} )[ 0 ];

			chatTitle.textContent = current ? chatName( current ) : i18n.newChat;
			historyFine.textContent = days ? ( 1 === days ? i18n.keptForOne : ( i18n.keptFor || '' ).replace( '%d', days ) ) : '';
		}

		function show( response ) {
			if ( ! response || ! response.success ) {
				toast( errorOf( response ), true );
				return false;
			}

			view = response.data;
			chat = view.chat;
			renderLog();
			renderIdeas();
			loadPreview();
			syncUrl();
			setHistory( false );
			input.value = '';
			autosize();
			input.focus();

			return true;
		}

		function openChat( item ) {
			if ( item.id === chat || chatsBusy() ) {
				setHistory( false );
				return;
			}

			// Another button's chat lives on that button's own screen.
			if ( String( item.target ) !== String( studio.target ) ) {
				window.location.href = item.url;
				return;
			}

			post( 'iftp_btn_ai_open', { target: studio.target, chat: item.id } ).then( show );
		}

		function newChat() {
			if ( chatsBusy() ) {
				return;
			}

			// Already on an empty one: nothing to make.
			if ( ! view.messages.length && ! view.pending ) {
				setHistory( false );
				input.focus();
				return;
			}

			post( 'iftp_btn_ai_open', { target: studio.target, chat: newId() } ).then( show );
		}

		root.querySelector( '[data-iftp-studio-new]' ).addEventListener( 'click', newChat );

		// On a wide screen the list can fold into a slim strip, so the conversation gets the room; it remembers.
		const collapse = root.querySelector( '[data-iftp-studio-collapse]' );

		function setCollapsed( folded ) {
			root.classList.toggle( 'is-rail-collapsed', folded );
			collapse.setAttribute( 'aria-expanded', folded ? 'false' : 'true' );
			collapse.setAttribute( 'aria-label', folded ? i18n.expandChats : i18n.collapseChats );

			try {
				window.localStorage.setItem( 'iftpBtnChatsFolded', folded ? '1' : '0' );
			} catch ( e ) {}
		}

		collapse.addEventListener( 'click', function () {
			setCollapsed( ! root.classList.contains( 'is-rail-collapsed' ) );
		} );

		try {
			setCollapsed( '1' === window.localStorage.getItem( 'iftpBtnChatsFolded' ) );
		} catch ( e ) {}

		root.querySelectorAll( '[data-iftp-studio-history-toggle]' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				setHistory( ! root.classList.contains( 'is-history-open' ) );
			} );
		} );

		rail.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && root.classList.contains( 'is-history-open' ) ) {
				setHistory( false );
			}
		} );

		deleteDialog.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-iftp-dialog-close]' ) ) {
				deleteDialog.close();
			}

			if ( ! event.target.closest( '[data-iftp-studio-delete-confirm]' ) || ! toDelete ) {
				return;
			}

			const gone = toDelete;

			toDelete = null;
			deleteDialog.close();

			post( 'iftp_btn_ai_chat_delete', { chat: gone.id } ).then( function ( response ) {
				if ( ! response || ! response.success ) {
					toast( errorOf( response ), true );
					return;
				}

				view.chats = response.data.chats;
				toast( i18n.chatDeleted );

				if ( gone.id !== chat ) {
					renderChats();
					return;
				}

				// The open one went: the next of this button's chats, or a fresh one.
				const next = view.chats.filter( function ( item ) {
					return String( item.target ) === String( studio.target );
				} )[ 0 ];

				post( 'iftp_btn_ai_open', { target: studio.target, chat: next ? next.id : newId() } ).then( show );
			} );
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
			post( 'iftp_btn_ai_undo', { target: studio.target, chat } ).then( function ( response ) {
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
				post( 'iftp_btn_ai_reset', { target: studio.target, chat } ).then( function ( response ) {
					replace( response );
					input.focus();
				} );
			} );
		}

		renderLog();
		renderIdeas();
		loadPreview();
		syncUrl();
		resume();
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
