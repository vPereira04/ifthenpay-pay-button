/**
 * The guided tour: the screen dims and blurs, the step's target stays sharp inside a ring, and a card explains it.
 * Some steps run a scripted demo (typing into the editor, a made-up AI chat); none of it is saved or sent.
 */
( function () {
	'use strict';

	const data = window.iftpBtnTour;

	if ( ! data ) {
		return;
	}

	const i18n = data.i18n;
	const demo = i18n.demo;
	let tour = null;
	let actToken = 0;

	function post( action ) {
		const body = new URLSearchParams( { action: action, _ajax_nonce: data.nonce } );

		return fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
	}

	function visible( element ) {
		return !! element && element.getClientRects().length > 0;
	}

	function pause( ms ) {
		return new Promise( function ( resolve ) {
			window.setTimeout( resolve, ms );
		} );
	}

	function setValue( field, value ) {
		field.value = value;
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	// Types a letter at a time; if the step is left meanwhile, the rest lands at once.
	function type( field, text, token ) {
		return new Promise( function ( resolve ) {
			let at = 0;

			if ( ! field || field.value === text ) {
				resolve();

				return;
			}

			setValue( field, '' );

			( function tick() {
				if ( token !== actToken ) {
					setValue( field, text );
					resolve();

					return;
				}

				at++;
				setValue( field, text.slice( 0, at ) );

				if ( at >= text.length ) {
					resolve();

					return;
				}

				window.setTimeout( tick, 40 );
			}() );
		} );
	}

	/* ---------------------------------------------------------------- Scripted demos */

	const form = document.querySelector( '[data-iftp-editor]' );
	const studio = document.querySelector( '[data-iftp-studio]' );

	function selectTab( name ) {
		const tab = document.querySelector( '[data-iftp-tab="' + name + '"]' );

		if ( tab && 'true' !== tab.getAttribute( 'aria-selected' ) ) {
			tab.click();
		}
	}

	function aiParts() {
		return {
			log: studio.querySelector( '[data-iftp-studio-log]' ),
			input: studio.querySelector( '[data-iftp-studio-input]' ),
			building: studio.querySelector( '[data-iftp-studio-building]' ),
			frame: studio.querySelector( '[data-iftp-studio-frame]' ),
			text: studio.querySelector( '[data-iftp-studio-building-text]' ),
			empty: studio.querySelector( '[data-iftp-studio-empty]' ),
			shell: studio.querySelector( '[data-iftp-studio-shell]' ),
			review: studio.querySelector( '[data-iftp-studio-review]' ),
			prices: studio.querySelector( '[data-iftp-studio-prices]' ),
		};
	}

	function bubble( role, content ) {
		const row = document.createElement( 'div' );
		const mark = document.createElement( 'span' );
		const body = document.createElement( 'div' );

		row.className = 'iftp-btn-studio__msg iftp-btn-studio__msg--' + role + ' iftp-btn-tour-fake';
		mark.className = 'iftp-btn-studio__avatar iftp-btn-studio__avatar--' + role;
		mark.setAttribute( 'aria-hidden', 'true' );
		body.className = 'iftp-btn-studio__bubble';

		if ( 'assistant' === role ) {
			mark.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M10.5 3.5c.4 3.9 2.6 6.1 6.5 6.5-3.9.4-6.1 2.6-6.5 6.5-.4-3.9-2.6-6.1-6.5-6.5 3.9-.4 6.1-2.6 6.5-6.5z"/><path d="M18 14c.2 1.9 1.1 2.8 3 3-1.9.2-2.8 1.1-3 3-.2-1.9-1.1-2.8-3-3 1.9-.2 2.8-1.1 3-3z"/></svg>';
		}

		body.append( content );
		row.append( mark, body );

		return row;
	}

	// What the studio showed before the demo, so it goes back exactly as it was.
	let aiBefore = null;

	function rememberAi() {
		if ( aiBefore || ! studio || ! aiParts().input ) {
			return;
		}

		const parts = aiParts();

		aiBefore = { empty: parts.empty.hidden, shell: parts.shell.hidden, review: parts.review.hidden, prices: parts.prices.innerHTML };
	}

	// Takes away everything the made-up chat added, so the real one is as it was.
	function resetAi() {
		if ( ! studio ) {
			return;
		}

		const parts = aiParts();

		studio.querySelectorAll( '.iftp-btn-tour-fake' ).forEach( function ( node ) {
			node.remove();
		} );

		if ( parts.input && aiBefore ) {
			parts.input.value = '';
			parts.input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			parts.building.hidden = true;
			parts.frame.classList.remove( 'is-building' );
			parts.text.textContent = '';
			parts.empty.hidden = aiBefore.empty;
			parts.shell.hidden = aiBefore.shell;
			parts.review.hidden = aiBefore.review;
			parts.prices.innerHTML = aiBefore.prices;
		}
	}

	// The merchant's own checkout, built by the server with the checkout preview's markup, in the preview.
	function showCheckout( parts ) {
		const card = document.createElement( 'div' );

		if ( parts.frame.querySelector( '.iftp-btn-tour-checkout' ) ) {
			return;
		}

		card.className = 'iftp-btn-tour-fake iftp-btn-tour-checkout';
		// Escaped on the server, from the plugin's own checkout template.
		card.innerHTML = demo.checkout;
		parts.empty.hidden = true;
		parts.shell.hidden = true;
		parts.frame.append( card );
	}

	// The "check the prices" line the review shows, as the real one does.
	function showReview( parts ) {
		const label = document.createElement( 'span' );

		parts.prices.textContent = '';
		label.className = 'iftp-btn-studio__prices-label';
		label.textContent = demo.checkPrices;
		parts.prices.append( label );
		demo.prices.forEach( function ( price ) {
			const chip = document.createElement( 'span' );

			chip.className = 'iftp-btn-studio__price';
			chip.textContent = price;
			parts.prices.append( chip );
		} );
		parts.review.hidden = false;
	}

	function showAnswer( parts ) {
		const list = document.createElement( 'ul' );
		const wrap = document.createDocumentFragment();
		const line = document.createElement( 'span' );

		list.className = 'iftp-btn-studio__changes';
		demo.changes.forEach( function ( change ) {
			const item = document.createElement( 'li' );

			item.className = 'iftp-btn-studio__change' + ( change[ 1 ] ? ' iftp-btn-studio__change--money' : '' );
			item.textContent = change[ 0 ];
			list.append( item );
		} );
		line.textContent = demo.reply;
		wrap.append( line, list );
		parts.log.append( bubble( 'assistant', wrap ) );
		parts.log.scrollTop = parts.log.scrollHeight;
	}

	function asked( parts ) {
		const text = document.createElement( 'span' );

		text.textContent = demo.prompt;
		parts.input.value = '';
		parts.log.append( bubble( 'user', text ) );
		parts.log.scrollTop = parts.log.scrollHeight;
	}

	const acts = {
		basics: function ( token ) {
			return type( form.querySelector( '[name="title"]' ), demo.title, token ).then( function () {
				return type( form.querySelector( '[name="config[summary]"]' ), demo.summary, token );
			} );
		},
		amount: function ( token ) {
			const mode = form.querySelector( '[name="config[amount_mode]"][value="fixed"]' );

			if ( mode && ! mode.checked ) {
				mode.checked = true;
				mode.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}

			return type( form.querySelector( '[name="config[amount]"]' ), demo.amount, token );
		},
		fields: function () {
			const phone = form.querySelector( '[name="config[fields][phone]"][value="optional"]' );

			if ( phone && ! phone.checked ) {
				phone.checked = true;
				phone.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		},
		reset: function () {
			resetAi();
		},
		prompt: function ( token ) {
			resetAi();

			return type( aiParts().input, demo.prompt, token );
		},
		build: function ( token ) {
			const parts = aiParts();

			rememberAi();
			resetAi();
			asked( parts );
			parts.building.hidden = false;
			parts.frame.classList.add( 'is-building' );
			parts.text.textContent = demo.working;

			return pause( 2600 ).then( function () {
				if ( token !== actToken ) {
					return;
				}

				parts.building.hidden = true;
				parts.frame.classList.remove( 'is-building' );
				parts.text.textContent = '';
				showAnswer( parts );
				showCheckout( parts );
			} );
		},
		// The finished chat and checkout, at once; whichever steps come after a build use it.
		checkout: function () {
			const parts = aiParts();

			rememberAi();

			if ( ! studio.querySelector( '.iftp-btn-tour-fake' ) ) {
				asked( parts );
				showAnswer( parts );
			} else if ( 1 === studio.querySelectorAll( '.iftp-btn-studio__msg.iftp-btn-tour-fake' ).length ) {
				showAnswer( parts );
			}

			parts.building.hidden = true;
			parts.frame.classList.remove( 'is-building' );
			showCheckout( parts );
		},
		review: function () {
			const parts = aiParts();

			acts.checkout();
			showReview( parts );
		},
		designButton: function ( token ) {
			const custom = form.querySelector( '[name="config[design][source]"][value="custom"]' );

			if ( custom && ! custom.checked ) {
				custom.click();
			}

			const preset = form.querySelector( '[data-iftp-preset="sunset"]' ) || form.querySelector( '[data-iftp-preset]:not([data-iftp-preset="ifthenpay"])' );

			if ( preset ) {
				preset.click();
			}

			return type( form.querySelector( '[name="config[label]"]' ), demo.label, token );
		},
		designCheckout: function () {
			const own = form.querySelector( '[name="config[look_mode]"][value="own"]' );

			if ( own && ! own.checked ) {
				own.click();
			}

			const preset = form.querySelector( '[data-iftp-look-preset="midnight"]' ) || form.querySelector( '[data-iftp-look-preset]:not([data-iftp-look-preset="ifthenpay"])' );

			if ( preset ) {
				preset.click();
			}
		},
		designBackground: function () {
			const fold = form.querySelector( '[data-iftp-look-section="page"]' );

			if ( fold ) {
				fold.open = true;
			}

			[ [ 'pay_bg', 'gradient' ], [ 'pay_grad', 'aurora' ], [ 'pay_fx', 'liquid' ] ].forEach( function ( pick ) {
				const radio = form.querySelector( '[name="config[look][' + pick[ 0 ] + ']"][value="' + pick[ 1 ] + '"]' );

				if ( radio && ! radio.checked ) {
					radio.click();
				}
			} );
		},
	};

	/* ---------------------------------------------------------------- The tour */

	function leave( url ) {
		// The class stays so the editor doesn't ask about its unsaved demo.
		window.location.href = url;
	}

	function start() {
		const chapter = data.chapter;

		if ( tour ) {
			return;
		}

		const steps = chapter.steps.filter( function ( step ) {
			if ( step.tab ) {
				return !! document.querySelector( '[data-iftp-tab="' + step.tab + '"]' );
			}

			// A step with a demo may be the one that makes its target appear.
			if ( step.act ) {
				return '' === step.target || !! document.querySelector( step.target );
			}

			return '' === step.target || visible( document.querySelector( step.target ) );
		} );

		if ( ! steps.length ) {
			return;
		}

		const shade = document.createElement( 'div' );
		const ring = document.createElement( 'div' );
		const card = document.createElement( 'div' );
		let index = 0;
		let rect = null;

		shade.className = 'iftp-btn-tour__shade';
		ring.className = 'iftp-btn-tour__ring';
		card.className = 'iftp-btn-tour__card';
		card.setAttribute( 'role', 'dialog' );
		card.setAttribute( 'aria-label', i18n.label );
		card.tabIndex = -1;
		document.body.append( shade, ring, card );
		document.body.classList.add( 'iftp-btn-touring' );

		// Seen from the first step on, so however they leave it, it never starts by itself again.
		post( 'iftp_btn_tour_seen' ).catch( function () {} );

		function target() {
			const step = steps[ index ];

			return '' === step.target ? null : document.querySelector( step.target );
		}

		// Puts the target where the card can sit beside it: tall ones at the top, the rest in the middle.
		function scrollToTarget( element ) {
			const bar = ( document.getElementById( 'wpadminbar' ) || { offsetHeight: 0 } ).offsetHeight;
			const vh = window.innerHeight;
			const box = element.getBoundingClientRect();
			const tall = box.height > vh * 0.5;

			window.scrollBy( { top: tall ? box.top - ( bar + 24 ) : box.top + box.height / 2 - vh * 0.42, behavior: 'instant' } );
		}

		function layout() {
			const vw = document.documentElement.clientWidth;
			const vh = window.innerHeight;
			const bar = ( document.getElementById( 'wpadminbar' ) || { offsetHeight: 0 } ).offsetHeight;
			const element = target();
			const pad = 8;
			const r = 14;

			rect = null;

			if ( element ) {
				const box = element.getBoundingClientRect();
				const top = Math.max( bar, box.top - pad );
				const bottom = Math.min( box.bottom + pad, top + vh * 0.5 );
				const left = Math.max( 4, box.left - pad );

				rect = { left: left, top: top, width: Math.min( vw - left - 4, box.width + pad * 2 ), height: Math.max( 24, bottom - top ) };
				shade.style.clipPath = 'path(evenodd, "M0 0H' + vw + 'V' + vh + 'H0Z M' + ( rect.left + r ) + ' ' + rect.top + 'H' + ( rect.left + rect.width - r ) + 'A' + r + ' ' + r + ' 0 0 1 ' + ( rect.left + rect.width ) + ' ' + ( rect.top + r ) + 'V' + ( rect.top + rect.height - r ) + 'A' + r + ' ' + r + ' 0 0 1 ' + ( rect.left + rect.width - r ) + ' ' + ( rect.top + rect.height ) + 'H' + ( rect.left + r ) + 'A' + r + ' ' + r + ' 0 0 1 ' + rect.left + ' ' + ( rect.top + rect.height - r ) + 'V' + ( rect.top + r ) + 'A' + r + ' ' + r + ' 0 0 1 ' + ( rect.left + r ) + ' ' + rect.top + 'Z")';
				ring.style.cssText = 'display:block;left:' + rect.left + 'px;top:' + rect.top + 'px;width:' + rect.width + 'px;height:' + rect.height + 'px';
			} else {
				shade.style.clipPath = 'none';
				ring.style.display = 'none';
			}

			const cw = card.offsetWidth;
			const ch = card.offsetHeight;
			let left = ( vw - cw ) / 2;
			let top = ( vh - ch ) / 2;

			// Under the target when it fits, else over it, else along the bottom.
			if ( rect ) {
				left = Math.min( Math.max( 12, rect.left ), vw - cw - 12 );

				if ( rect.top + rect.height + 16 + ch <= vh - 12 ) {
					top = rect.top + rect.height + 16;
				} else if ( rect.top - 16 - ch >= bar + 8 ) {
					top = rect.top - 16 - ch;
				} else {
					top = vh - ch - 12;
				}
			}

			card.style.left = Math.max( 12, left ) + 'px';
			card.style.top = Math.max( 12, top ) + 'px';
		}

		function show() {
			const step = steps[ index ];
			const last = index === steps.length - 1;
			const token = ++actToken;
			const count = document.createElement( 'span' );
			const title = document.createElement( 'h2' );
			const text = document.createElement( 'p' );
			const actions = document.createElement( 'div' );
			const skip = document.createElement( 'button' );
			const nav = document.createElement( 'span' );
			const next = document.createElement( 'button' );

			if ( step.tab ) {
				selectTab( step.tab );
			}

			if ( step.view ) {
				const view = document.querySelector( '[data-iftp-design-view-pick="' + step.view + '"]' );

				if ( view && 'true' !== view.getAttribute( 'aria-pressed' ) ) {
					view.click();
				}
			}

			count.className = 'iftp-btn-tour__count';
			count.textContent = i18n.count.replace( '%1$s', chapter.label ).replace( '%2$s', index + 1 ).replace( '%3$s', steps.length );
			title.className = 'iftp-btn-tour__title';
			title.textContent = step.title;
			text.className = 'iftp-btn-tour__text';
			text.textContent = step.text;
			actions.className = 'iftp-btn-tour__actions';
			nav.className = 'iftp-btn-tour__nav';

			skip.type = 'button';
			skip.className = 'iftp-btn-btn iftp-btn-btn--link';
			skip.textContent = i18n.skip;
			skip.addEventListener( 'click', skipTour );
			actions.append( skip );

			if ( index > 0 ) {
				const back = document.createElement( 'button' );

				back.type = 'button';
				back.className = 'iftp-btn-btn';
				back.textContent = i18n.back;
				back.addEventListener( 'click', function () {
					index--;
					show();
				} );
				nav.append( back );
			}

			next.type = 'button';
			next.className = 'iftp-btn-btn iftp-btn-btn--primary';
			next.textContent = last ? chapter.cta : i18n.next;
			next.addEventListener( 'click', function () {
				if ( ! last ) {
					index++;
					show();

					return;
				}

				if ( chapter.next ) {
					leave( chapter.next );
				} else {
					finish();
				}
			} );
			nav.append( next );
			actions.append( nav );
			card.replaceChildren( count, title, text, actions );

			// The demo goes first: its first moves (opening a fold, showing a review) change what is measured.
			const running = step.act && acts[ step.act ] ? Promise.resolve( acts[ step.act ]( token ) ) : null;
			const element = target();

			if ( element ) {
				scrollToTarget( element );
			}

			layout();
			next.focus( { preventScroll: true } );

			if ( running ) {
				running.then( function () {
					if ( token === actToken ) {
						layout();
					}
				} );
			}
		}

		function onKey( event ) {
			if ( 'Escape' === event.key ) {
				skipTour();
			}
		}

		function skipTour() {
			// Away from the editor and the studio the tour has nothing left to show, so it goes back to the list.
			if ( 'editor' === data.name || 'ai' === data.name ) {
				leave( data.listUrl );

				return;
			}

			finish();
		}

		function finish() {
			window.removeEventListener( 'resize', layout );
			window.removeEventListener( 'scroll', layout );
			document.removeEventListener( 'keydown', onKey, true );
			actToken++;
			resetAi();
			shade.remove();
			ring.remove();
			card.remove();
			document.body.classList.remove( 'iftp-btn-touring' );
			tour = null;

			if ( window.history.replaceState && /[?&]iftp_tour=/.test( window.location.search ) ) {
				window.history.replaceState( null, '', data.listUrl );
			}
		}

		tour = { finish: finish };
		window.addEventListener( 'resize', layout );
		window.addEventListener( 'scroll', layout, { passive: true } );
		document.addEventListener( 'keydown', onKey, true );
		show();
	}

	// "Take the tour again" in the help popup: from the list's own chapter it starts at once, else it goes there.
	document.querySelectorAll( '[data-iftp-tour-start]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			const dialog = button.closest( 'dialog' );

			if ( dialog ) {
				dialog.close();
			}

			if ( 'list' === data.name ) {
				start();
			} else {
				window.location.href = data.replay;
			}
		} );
	} );

	if ( data.auto ) {
		window.setTimeout( start, 500 );
	}
}() );
