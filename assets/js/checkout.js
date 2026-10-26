/**
 * Pay buttons on the frontend: open the checkout, start the payment, and show the outcome when the payer comes back.
 */
( function () {
	'use strict';

	const config = window.iftpBtnCheckout;

	if ( ! config ) {
		return;
	}

	const i18n = config.i18n || {};

	// Fast at first, since most returns resolve within seconds, then slow for Multibanco/Payshop paid hours later.
	const fastTries = 10;
	const fastDelay = 3000;
	const slowDelay = 10000;
	const budget = 25 * 60 * 1000;

	/* ---------------------------------------------------------------- Checkout */

	// Same shape as the server's format_money(): the site's separators, two decimals, then the euro sign.
	function formatMoney( amount ) {
		const number = config.number || { decimal: '.', thousands: ',' };
		const parts = ( Math.round( amount * 100 ) / 100 ).toFixed( 2 ).split( '.' );

		return parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, number.thousands ) + number.decimal + parts[ 1 ] + ' €';
	}

	// The picked options of the field the spots are split by: ticked radios or boxes, or the selected <option>.
	function pickedSpots( form ) {
		const ticked = Array.prototype.slice.call( form.querySelectorAll( 'input[data-left]:checked, input[data-price]:checked' ) );

		if ( ticked.length ) {
			return ticked;
		}

		const select = form.querySelector( 'select option[data-left], select option[data-price]' );
		const option = select ? select.parentElement.selectedOptions[ 0 ] : null;

		return option && option.value ? [ option ] : [];
	}

	// A fixed amount is per spot: the total follows the option's price and the quantity.
	function bindSpots( form ) {
		const quantity = form.querySelector( '[data-iftp-btn-quantity]' );
		const total = form.querySelector( '[data-iftp-btn-total]' );
		if ( ! quantity && ! form.querySelector( '[data-left], [data-price]' ) ) {
			return;
		}

		const startText = total ? total.textContent : '';

		const clamp = function () {
			if ( ! quantity ) {
				return 1;
			}

			const max = parseInt( quantity.max, 10 ) || 1;
			const value = Math.min( max, Math.max( 1, parseInt( quantity.value, 10 ) || 1 ) );

			if ( String( value ) !== quantity.value ) {
				quantity.value = String( value );
			}

			form.querySelectorAll( '[data-iftp-btn-qty]' ).forEach( function ( step ) {
				step.disabled = step.dataset.iftpBtnQty === '1' ? value >= max : value <= 1;
			} );

			return value;
		};

		const update = function () {
			const picked = pickedSpots( form );

			// The most a payer can take is the button's own cap, or what's left on the tightest picked option.
			if ( quantity ) {
				const left = picked.reduce( function ( least, option ) {
					return option.dataset.left !== undefined ? Math.min( least, parseInt( option.dataset.left, 10 ) ) : least;
				}, Infinity );

				quantity.max = String( Math.max( 1, Math.min( parseInt( quantity.dataset.maxEach, 10 ) || 1, left ) ) );
			}

			const count = clamp();

			if ( total && form.dataset.unit ) {
				// Each ticked option adds its own price.
				const unit = picked.length
					? picked.reduce( function ( sum, option ) {
						return sum + ( option.dataset.price ? parseFloat( option.dataset.price ) : parseFloat( form.dataset.unit ) );
					}, 0 )
					: parseFloat( form.dataset.unit );

				// Options with their own prices show "from …" until one is picked.
				total.textContent = ! picked.length && form.querySelector( '[data-price]' ) ? startText : formatMoney( unit * count );
			}
		};

		form.querySelectorAll( '[data-iftp-btn-qty]' ).forEach( function ( step ) {
			step.addEventListener( 'click', function () {
				quantity.value = String( ( parseInt( quantity.value, 10 ) || 1 ) + parseInt( step.dataset.iftpBtnQty, 10 ) );
				update();
				quantity.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );
		} );

		form.addEventListener( 'change', update );

		if ( quantity ) {
			quantity.addEventListener( 'input', function () {
				// Let the payer clear the box while typing; it settles on change.
				if ( quantity.value !== '' ) {
					update();
				}
			} );
		}

		update();
	}

	// Once per button per page view: opening, closing and opening again is still one payer looking.
	const counted = {};

	function countOpen( buttonId ) {
		if ( ! config.countOpens || ! buttonId || counted[ buttonId ] ) {
			return;
		}

		counted[ buttonId ] = true;

		fetch( config.opensUrl, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: true,
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { button: parseInt( buttonId, 10 ) } ),
		} ).catch( function () {} );
	}

	// A checkout on a video background only loads and starts it now, so a page of buttons doesn't load one each.
	function playVideo( dialog ) {
		dialog.querySelectorAll( '[data-iftp-video] [data-src]' ).forEach( function ( media ) {
			media.src = media.dataset.src;
			media.removeAttribute( 'data-src' );
		} );

		dialog.querySelectorAll( '[data-iftp-video] video' ).forEach( function ( video ) {
			const playing = video.play();

			if ( playing && playing.catch ) {
				playing.catch( function () {} );
			}
		} );
	}

	// 'close' doesn't bubble, so this listens on the way down.
	document.addEventListener( 'close', function ( event ) {
		if ( event.target && event.target.querySelectorAll ) {
			event.target.querySelectorAll( '[data-iftp-video] video' ).forEach( function ( video ) {
				video.pause();
			} );
		}
	}, true );

	function openDialog( dialog ) {
		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );
		}

		playVideo( dialog );

		const first = dialog.querySelector( '.iftp-btn-form__input:not([type="hidden"])' );

		if ( first ) {
			first.focus();
		}
	}

	function closeDialog( dialog ) {
		if ( typeof dialog.close === 'function' ) {
			dialog.close();
		} else {
			dialog.removeAttribute( 'open' );
		}
	}

	function showError( form, message ) {
		const box = form.querySelector( '[data-iftp-btn-error]' );

		if ( box ) {
			box.textContent = message;
			box.hidden = ! message;
		}
	}

	function setBusy( form, busy ) {
		const submit = form.querySelector( '[data-iftp-btn-submit]' );

		form.classList.toggle( 'is-busy', busy );

		if ( submit ) {
			submit.disabled = busy;
			submit.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		}
	}

	// Everything a payer answers in a scope (the form, or one step), minus the honeypot and what a condition hides.
	function answerables( scope ) {
		return Array.prototype.filter.call( scope.querySelectorAll( 'input, select, textarea' ), function ( input ) {
			return input.type !== 'hidden' && ! input.closest( '.iftp-btn-form__trap' ) && ! input.closest( '[data-iftp-btn-when][hidden]' );
		} );
	}

	// What a field holds, as the server's FormFields::answers() reads it: the picked options, or '1' for a ticked agreement.
	function answersOf( form, name ) {
		const values = [];

		form.querySelectorAll( '[name="' + name + '"]' ).forEach( function ( input ) {
			if ( input.tagName === 'SELECT' ) {
				if ( input.value ) {
					values.push( input.value );
				}
			} else if ( input.checked ) {
				values.push( input.value );
			}
		} );

		return values;
	}

	// Fields and text blocks that show only for some answers. A hidden one's inputs are disabled, so the
	// browser neither checks nor sends them; the ones a sold-out option disabled stay disabled.
	function bindConditions( form ) {
		const items = Array.prototype.slice.call( form.querySelectorAll( '[data-iftp-btn-when]' ) );

		if ( ! items.length ) {
			return;
		}

		const rules = items.map( function ( item ) {
			try {
				return JSON.parse( item.dataset.iftpBtnWhen );
			} catch ( e ) {
				return null;
			}
		} );

		const toggle = function ( item, show ) {
			if ( item.hidden === ! show ) {
				return;
			}

			item.hidden = ! show;
			item.classList.toggle( 'is-revealed', show );

			item.querySelectorAll( 'input, select, textarea' ).forEach( function ( input ) {
				if ( ! show && ! input.disabled ) {
					input.disabled = true;
					input.dataset.iftpBtnOff = '1';
				} else if ( show && input.dataset.iftpBtnOff ) {
					input.disabled = false;
					delete input.dataset.iftpBtnOff;
				}
			} );
		};

		const apply = function () {
			// In form order, so a field hidden by its own condition hides the ones that hang on it.
			items.forEach( function ( item, index ) {
				const rule = rules[ index ];

				if ( ! rule ) {
					return;
				}

				const controller = form.querySelector( '[name="' + rule.field + '"]' );
				const holder = controller ? controller.closest( '[data-iftp-btn-when]' ) : null;
				const live = ! holder || ! holder.hidden;
				const match = live && answersOf( form, rule.field ).some( function ( value ) {
					return rule.values.indexOf( value ) !== -1;
				} );

				toggle( item, live && ( rule.op === 'not' ? ! match : match ) );
			} );
		};

		items.forEach( function ( item ) {
			// The server hid what the prefill didn't meet; its inputs still need switching off.
			if ( item.hidden ) {
				item.hidden = false;
				toggle( item, false );
			}
		} );

		form.addEventListener( 'change', apply );
		apply();
	}

	// The browser's own checks, so the messages come in the payer's language for free. A required
	// "multiple choice" has no native check, so I do that one.
	function firstInvalid( scope ) {
		const inputs = answerables( scope );

		for ( let i = 0; i < inputs.length; i++ ) {
			if ( ! inputs[ i ].checkValidity() ) {
				return { input: inputs[ i ], message: inputs[ i ].validationMessage };
			}
		}

		const groups = scope.querySelectorAll( '[data-iftp-btn-required-group]:not([hidden])' );

		for ( let i = 0; i < groups.length; i++ ) {
			if ( ! groups[ i ].querySelector( 'input:checked' ) ) {
				return { input: groups[ i ].querySelector( 'input' ), message: i18n.chooseOne };
			}
		}

		return null;
	}

	// Ticked boxes share a name, so their values are joined; the server splits them on the newline.
	function collect( form ) {
		const payload = { button: parseInt( form.dataset.button, 10 ), source: window.location.href };

		new FormData( form ).forEach( function ( value, key ) {
			const text = typeof value === 'string' ? value : '';

			payload[ key ] = Object.prototype.hasOwnProperty.call( payload, key ) && key.indexOf( 'cf_' ) === 0 ? payload[ key ] + '\n' + text : text;
		} );

		return payload;
	}

	function start( form ) {
		const invalid = firstInvalid( form );

		if ( invalid ) {
			// It may sit on an earlier step, so I take the payer there first.
			if ( form.iftpGoTo ) {
				form.iftpGoTo( invalid.input );
			}

			showError( form, invalid.message );
			invalid.input.focus();

			return Promise.resolve( false );
		}

		// The AI studio's preview of an unsaved button: everything works except paying.
		if ( form.dataset.preview ) {
			showError( form, form.dataset.preview );

			return Promise.resolve( false );
		}

		showError( form, '' );
		setBusy( form, true );

		return fetch( config.checkoutUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify( collect( form ) ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return { ok: response.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok && result.body && result.body.redirect ) {
					window.location.assign( result.body.redirect );

					return true;
				}

				throw new Error( ( result.body && result.body.message ) || i18n.failed );
			} )
			.catch( function ( error ) {
				setBusy( form, false );
				showError( form, error && error.message ? error.message : i18n.failed );

				return false;
			} );
	}

	function bindForm( form ) {
		const steps = Array.prototype.slice.call( form.querySelectorAll( '[data-iftp-btn-step]' ) );
		const bars = form.querySelectorAll( '[data-iftp-btn-bar]' );
		const pct = form.querySelector( '[data-iftp-btn-step-pct]' );
		const back = form.querySelector( '[data-iftp-btn-back]' );
		const submit = form.querySelector( '[data-iftp-btn-submit]' );
		const body = form.querySelector( '[data-iftp-btn-body]' );
		let current = 0;

		// The current step's segment fills as its fields get answered.
		const progress = function () {
			if ( ! bars.length ) {
				return;
			}

			const names = {};

			answerables( steps[ current ] ).forEach( function ( input ) {
				const answered = input.type === 'checkbox' || input.type === 'radio' ? input.checked : input.value.trim() !== '';

				names[ input.name ] = names[ input.name ] || answered;
			} );

			const keys = Object.keys( names );
			const done = keys.filter( function ( key ) {
				return names[ key ];
			} ).length;

			const fill = keys.length ? done / keys.length : 1;

			bars[ current ].style.setProperty( '--iftp-fill', String( fill ) );

			// The whole form's share, for the One line style.
			if ( pct ) {
				pct.textContent = Math.round( ( current + fill ) / bars.length * 100 ) + '%';
			}
		};

		const show = function ( index ) {
			if ( index === current || ! steps[ index ] ) {
				return;
			}

			const forward = index > current;

			steps[ current ].hidden = true;
			steps[ index ].hidden = false;
			steps[ index ].classList.remove( 'is-forward', 'is-backward' );
			// Reading offsetWidth restarts the animation when the same class comes back.
			void steps[ index ].offsetWidth; // eslint-disable-line no-void
			steps[ index ].classList.add( forward ? 'is-forward' : 'is-backward' );
			current = index;

			bars.forEach( function ( bar, at ) {
				bar.classList.toggle( 'is-done', at < index );
				bar.classList.toggle( 'is-current', at === index );

				if ( at === index ) {
					bar.setAttribute( 'aria-current', 'step' );
				} else {
					bar.removeAttribute( 'aria-current' );
				}
			} );

			const title = form.querySelector( '[data-iftp-btn-step-title]' );
			const now = form.querySelector( '[data-iftp-btn-step-now]' );

			if ( title && bars[ index ] ) {
				title.textContent = bars[ index ].dataset.title;
			}

			if ( now ) {
				now.textContent = String( index + 1 );
			}

			if ( back ) {
				back.hidden = index === 0;
			}

			submit.querySelector( '.iftp-btn-form__submit-text' ).textContent = index === steps.length - 1 ? submit.dataset.pay : submit.dataset.next;
			showError( form, '' );
			progress();

			if ( body ) {
				body.scrollTop = 0;
			}

			const first = answerables( steps[ index ] )[ 0 ];

			if ( first ) {
				first.focus( { preventScroll: true } );
			}
		};

		// start() calls this when the first problem is on another step.
		form.iftpGoTo = function ( input ) {
			const step = input.closest( '[data-iftp-btn-step]' );

			if ( step ) {
				show( steps.indexOf( step ) );
			}
		};

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( current < steps.length - 1 ) {
				const invalid = firstInvalid( steps[ current ] );

				if ( invalid ) {
					showError( form, invalid.message );
					invalid.input.focus();

					return;
				}

				show( current + 1 );

				return;
			}

			start( form );
		} );

		if ( back ) {
			back.addEventListener( 'click', function () {
				show( current - 1 );
			} );
		}

		// Before progress, so the step bar counts only what shows.
		bindConditions( form );
		form.addEventListener( 'input', progress );
		form.addEventListener( 'change', progress );
		progress();
		bindSpots( form );

		form.querySelectorAll( '[data-iftp-btn-preset]' ).forEach( function ( preset ) {
			preset.addEventListener( 'click', function () {
				const input = form.querySelector( 'input[name="amount"]' );

				if ( input ) {
					input.value = preset.dataset.iftpBtnPreset;
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				}

				form.querySelectorAll( '[data-iftp-btn-preset]' ).forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === preset ? 'true' : 'false' );
				} );
			} );
		} );

		const amount = form.querySelector( 'input[name="amount"]:not([type="hidden"])' );

		if ( amount ) {
			amount.addEventListener( 'input', function () {
				form.querySelectorAll( '[data-iftp-btn-preset]' ).forEach( function ( preset ) {
					preset.setAttribute( 'aria-pressed', preset.dataset.iftpBtnPreset === amount.value ? 'true' : 'false' );
				} );
			} );
		}

		const close = form.querySelector( '[data-iftp-btn-close]' );

		if ( close ) {
			close.addEventListener( 'click', function () {
				closeDialog( form.closest( 'dialog' ) );
			} );
		}
	}

	document.querySelectorAll( '[data-iftp-btn-form]' ).forEach( bindForm );

	document.querySelectorAll( '[data-iftp-btn-open]' ).forEach( function ( opener ) {
		const dialog = document.getElementById( opener.dataset.iftpBtnOpen );

		if ( ! dialog ) {
			return;
		}

		opener.addEventListener( 'click', function () {
			const form = dialog.querySelector( '[data-iftp-btn-form]' );

			openDialog( dialog );
			countOpen( form ? form.dataset.button : '' );
		} );
	} );

	/* ---------------------------------------------------------------- Pay page: skip the form when the link filled it in */

	const params = new URLSearchParams( window.location.search );
	const returning = params.has( config.resultVar );
	const pageForm = document.querySelector( '.iftp-btn-page [data-iftp-btn-form]' );

	// The pay page is the checkout already open; coming back to it from ifthenpay isn't a new look.
	if ( pageForm && ! returning && ! pageForm.dataset.preview ) {
		countOpen( pageForm.dataset.button );
	}

	function autoStart() {
		const form = document.querySelector( '.iftp-btn-page [data-iftp-btn-form][data-skip="1"]' );

		if ( ! form || returning || form.dataset.preview || firstInvalid( form ) ) {
			return;
		}

		// Coming back with the Back button must not start a second payment.
		const key = 'iftp-btn-auto:' + window.location.href;

		try {
			if ( window.sessionStorage.getItem( key ) ) {
				return;
			}

			window.sessionStorage.setItem( key, '1' );
		} catch ( e ) {
			return;
		}

		form.classList.add( 'is-auto' );
		form.querySelector( '.iftp-btn-form__submit-text' ).textContent = i18n.starting;
		start( form );
	}

	autoStart();

	/* ---------------------------------------------------------------- Outcome popup */

	const icons = {
		paid: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		pending: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		failed: '<path d="M7 7l10 10M17 7 7 17"/>',
		cancelled: '<path d="M7 7l10 10M17 7 7 17"/>',
	};

	function outcomeOf( status ) {
		if ( status === 'paid' || status === 'pending' || status === 'cancelled' ) {
			return status;
		}

		return 'failed';
	}

	function ownLook( look ) {
		return look && typeof look.style === 'string' ? look : null;
	}

	// The popup's background video, built like CheckoutLook::video_markup(); kept while it's the same one.
	function videoLayer( dialog, video ) {
		const src = video && video.src ? video.src : '';
		const key = src ? [ src, video.start, video.end ].join( '|' ) : '';
		let layer = dialog.querySelector( '[data-iftp-video]' );

		if ( layer && layer.dataset.for === key ) {
			return;
		}

		if ( layer ) {
			layer.remove();
		}

		if ( ! src ) {
			return;
		}

		const embed = video.kind === 'embed';
		const media = document.createElement( embed ? 'iframe' : 'video' );

		layer = document.createElement( 'div' );
		layer.className = 'iftp-btn-pagevideo is-waiting';
		layer.setAttribute( 'aria-hidden', 'true' );
		layer.dataset.iftpVideo = '';
		layer.dataset.for = key;
		media.className = 'iftp-btn-pagevideo__media' + ( embed ? ' iftp-btn-pagevideo__media--embed' : '' );
		media.dataset.src = src;

		// Looping part of it, as CheckoutLook::video_markup() marks it.
		if ( video.start !== null && video.start !== undefined ) {
			media.dataset.loopStart = video.start;
			media.dataset.loopEnd = video.end || 0;
		}

		if ( window.iftpBtnVideoLoop ) {
			window.iftpBtnVideoLoop( media );
		}

		if ( embed ) {
			media.title = '';
			media.tabIndex = -1;
			media.setAttribute( 'allow', 'autoplay; encrypted-media; picture-in-picture' );
			media.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		} else {
			media.muted = true;
			media.loop = true;
			media.playsInline = true;
			media.setAttribute( 'muted', '' );
		}

		layer.appendChild( media );
		dialog.insertBefore( layer, dialog.firstChild );

		if ( dialog.open ) {
			playVideo( dialog );
		}
	}

	// A button with its own look sends it along; otherwise the site's look from the stylesheet applies.
	function dress( dialog, look, state ) {
		const icon = look ? look.icon : config.popupIcon;
		const scene = look ? look.scene : config.scene;
		const dark = look ? look.dark : config.dark;

		dialog.className = 'iftp-btn-sheet iftp-btn-result ' + state + ( icon === 'solid' ? ' iftp-btn-result--solid' : '' ) + ( look ? ' iftp-btn-font--' + look.font : '' ) + ( scene ? ' ' + scene : '' ) + ( dark ? ' iftp-btn-dark' : '' );

		if ( look ) {
			dialog.setAttribute( 'style', look.style );
		} else {
			dialog.removeAttribute( 'style' );
		}

		let video = null;

		if ( scene ) {
			video = look ? look.video : config.sceneVideo;
		}

		videoLayer( dialog, video );
	}

	function buildResultDialog() {
		const dialog = document.createElement( 'dialog' );

		dialog.setAttribute( 'aria-labelledby', 'iftp-btn-result-title' );
		dialog.setAttribute( 'aria-live', 'polite' );
		dialog.innerHTML =
			'<div class="iftp-btn-result__box">' +
			'<span class="iftp-btn-result__icon" aria-hidden="true"><span class="iftp-btn-result__spinner"></span>' +
			'<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"></svg></span>' +
			'<h2 class="iftp-btn-result__title" id="iftp-btn-result-title"></h2>' +
			'<div class="iftp-btn-result__message"></div>' +
			'<dl class="iftp-btn-result__details" hidden></dl>' +
			'<button type="button" class="iftp-btn-form__submit iftp-btn-result__close" hidden></button>' +
			'</div>';

		// After the box, so the video layer it may add isn't written over.
		dress( dialog, ownLook( config.resultLook ), 'is-checking' );
		dialog.querySelector( '.iftp-btn-result__title' ).textContent = i18n.checking;

		const close = dialog.querySelector( '.iftp-btn-result__close' );

		close.textContent = i18n.close;
		close.addEventListener( 'click', function () {
			closeDialog( dialog );
		} );

		document.body.appendChild( dialog );

		return dialog;
	}

	function renderResult( dialog, data ) {
		const outcome = outcomeOf( data.status );
		const titles = i18n.titles || {};
		const details = dialog.querySelector( '.iftp-btn-result__details' );

		dress( dialog, ownLook( data.look ), 'iftp-btn-result--' + outcome );
		dialog.querySelector( 'svg' ).innerHTML = icons[ outcome ];
		dialog.querySelector( '.iftp-btn-result__title' ).textContent = data.title || titles[ outcome ] || '';

		// Admin-written HTML with this payment's tags filled in, through wp_kses_post() on the server.
		dialog.querySelector( '.iftp-btn-result__message' ).innerHTML = typeof data.message === 'string' ? data.message : ( config.messages && config.messages[ outcome ] ) || '';

		details.textContent = '';

		if ( config.showDetails ) {
			[
				[ i18n.amount, data.amount ],
				[ i18n.reference, data.reference ],
				[ i18n.method, data.method ],
			].forEach( function ( pair ) {
				if ( ! pair[ 1 ] ) {
					return;
				}

				const dt = document.createElement( 'dt' );
				const dd = document.createElement( 'dd' );

				dt.textContent = pair[ 0 ];
				dd.textContent = pair[ 1 ];
				details.appendChild( dt );
				details.appendChild( dd );
			} );
		}

		details.hidden = ! details.children.length;
		dialog.querySelector( '.iftp-btn-result__close' ).hidden = false;
	}

	function watchResult( ref, sig ) {
		const dialog = buildResultDialog();
		const started = Date.now();
		let tries = 0;
		let timer = null;
		let done = false;
		let shown = false;
		let lastOutcome = '';

		openDialog( dialog );

		function fetchStatus( quick ) {
			const url = config.statusUrl + encodeURIComponent( ref ) + '?sig=' + encodeURIComponent( sig ) + ( quick ? '&check=1' : '' );

			return fetch( url, { credentials: 'same-origin', headers: { Accept: 'application/json' } } ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'status' );
				}

				return response.json();
			} );
		}

		function schedule() {
			if ( done || Date.now() - started > budget ) {
				return;
			}

			window.clearTimeout( timer );
			timer = window.setTimeout( check, tries < fastTries ? fastDelay : slowDelay );
		}

		function check( quick ) {
			tries++;

			fetchStatus( quick === true )
				.then( function ( data ) {
					const outcome = outcomeOf( data.status );
					const changed = outcome !== lastOutcome;

					lastOutcome = outcome;

					if ( data.status === 'paid' ) {
						done = true;

						if ( data.redirect ) {
							if ( ! dialog.hasAttribute( 'open' ) ) {
								openDialog( dialog );
							}

							dialog.querySelector( '.iftp-btn-result__title' ).textContent = i18n.redirecting;
							window.location.assign( data.redirect );

							return;
						}
					}

					shown = true;
					renderResult( dialog, data );

					// Closing the popup only hides that outcome: pending turning into paid opens it again.
					if ( changed && ! dialog.hasAttribute( 'open' ) ) {
						openDialog( dialog );
					}

					schedule();
				} )
				.catch( function () {
					if ( ! shown ) {
						closeDialog( dialog );
						done = true;

						return;
					}

					schedule();
				} );
		}

		// A backgrounded tab throttles timers; the payer switching back from their banking app should see it at once.
		document.addEventListener( 'visibilitychange', function () {
			if ( document.visibilityState === 'visible' && ! done && shown ) {
				check();
			}
		} );

		check( true );
	}

	if ( returning ) {
		const ref = params.get( config.resultVar ) || '';
		const sig = params.get( config.resultSig ) || '';

		// A reload or a shared URL shouldn't replay the popup.
		params.delete( config.resultVar );
		params.delete( config.resultSig );

		if ( window.history && window.history.replaceState ) {
			const query = params.toString();

			window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash );
		}

		if ( ref && sig ) {
			watchResult( ref, sig );
		}
	}
} )();
