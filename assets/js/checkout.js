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

	/* ---------------------------------------------------------------- Picture preview */

	// The magnifier on a picture card opens the picture larger, in a dialog of its own over the checkout.
	( function () {
		let box = null;

		function close() {
			if ( box && box.open ) {
				box.close();
			}
		}

		function open( trigger ) {
			close();

			const alt = trigger.getAttribute( 'data-iftp-zoom-alt' ) || '';
			const img = document.createElement( 'img' );
			const closer = document.createElement( 'button' );
			const caption = document.createElement( 'p' );
			const dialog = document.createElement( 'dialog' );

			dialog.className = 'iftp-btn-lightbox';
			dialog.setAttribute( 'aria-label', alt );

			img.className = 'iftp-btn-lightbox__img';
			img.src = trigger.getAttribute( 'data-iftp-zoom' );
			img.alt = alt;

			closer.type = 'button';
			closer.className = 'iftp-btn-lightbox__close';
			closer.setAttribute( 'aria-label', i18n.close || 'Close' );
			closer.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>';
			closer.addEventListener( 'click', close );

			caption.className = 'iftp-btn-lightbox__caption';
			caption.textContent = alt;

			dialog.appendChild( img );
			dialog.appendChild( caption );
			dialog.appendChild( closer );

			// A click on the dimmed backdrop (the dialog itself) closes it; one on the picture doesn't.
			dialog.addEventListener( 'click', function ( event ) {
				if ( event.target === dialog ) {
					close();
				}
			} );

			dialog.addEventListener( 'close', function () {
				dialog.remove();
				box = null;
				trigger.focus();
			} );

			// Inside the checkout's own dialog when there is one, so it keeps the look and sits above it.
			( trigger.closest( 'dialog' ) || document.body ).appendChild( dialog );
			box = dialog;
			dialog.showModal();
		}

		document.addEventListener( 'click', function ( event ) {
			const trigger = event.target.closest ? event.target.closest( '[data-iftp-zoom]' ) : null;

			if ( trigger ) {
				// It sits inside the option's label, so it must not tick the option.
				event.preventDefault();
				open( trigger );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			const trigger = event.target.closest ? event.target.closest( '[data-iftp-zoom]' ) : null;

			if ( trigger && ( event.key === 'Enter' || event.key === ' ' ) ) {
				event.preventDefault();
				open( trigger );
			}
		} );
	}() );

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

	// The picked options of the other fields that have spots of their own, ticked or selected.
	function pickedLimited( form ) {
		const picked = Array.prototype.slice.call( form.querySelectorAll( 'input[data-extra-left]:checked:not(:disabled)' ) );

		form.querySelectorAll( 'select:not(:disabled)' ).forEach( function ( select ) {
			const option = select.selectedOptions[ 0 ];

			if ( option && option.value && option.dataset.extraLeft !== undefined ) {
				picked.push( option );
			}
		} );

		return picked;
	}

	// What the picked options of the other fields add to every spot; a hidden field's inputs are disabled.
	function pickedExtras( form ) {
		let sum = 0;

		form.querySelectorAll( 'input[data-extra]:checked:not(:disabled)' ).forEach( function ( input ) {
			sum += parseFloat( input.dataset.extra ) || 0;
		} );

		form.querySelectorAll( 'select:not(:disabled)' ).forEach( function ( select ) {
			const option = select.selectedOptions[ 0 ];

			sum += option && option.dataset.extra ? parseFloat( option.dataset.extra ) || 0 : 0;
		} );

		return sum;
	}

	// A fixed amount is per spot: the total follows the option's price and the quantity.
	function bindSpots( form ) {
		const quantity = form.querySelector( '[data-iftp-btn-quantity]' );
		const total = form.querySelector( '[data-iftp-btn-total]' );
		if ( ! quantity && ! form.querySelector( '[data-left], [data-price], [data-extra], [data-extra-left]' ) ) {
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

			// The most a payer can take is the button's own cap, or what's left on the tightest picked option, on any field.
			if ( quantity ) {
				const left = picked.concat( pickedLimited( form ) ).reduce( function ( least, option ) {
					const own = option.dataset.left !== undefined ? option.dataset.left : option.dataset.extraLeft;

					return own !== undefined ? Math.min( least, parseInt( own, 10 ) ) : least;
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
				total.textContent = ! picked.length && form.querySelector( '[data-price]' ) ? startText : formatMoney( ( unit + pickedExtras( form ) ) * count );
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
		document.documentElement.style.removeProperty( '--iftp-btn-sbw' );

		if ( event.target && event.target.querySelectorAll ) {
			event.target.querySelectorAll( '[data-iftp-video] video' ).forEach( function ( video ) {
				video.pause();
			} );
		}
	}, true );

	// The popup grows out of the button that was clicked: this sets where it grows from, relative to the popup itself.
	function growFrom( dialog, opener ) {
		dialog.style.removeProperty( '--iftp-btn-ox' );
		dialog.style.removeProperty( '--iftp-btn-oy' );
		dialog.style.removeProperty( '--iftp-btn-cx' );
		dialog.style.removeProperty( '--iftp-btn-cy' );

		if ( ! opener ) {
			return;
		}

		// A full-screen scene grows its card; a plain popup grows itself. Layout offsets ignore the animation's own scale.
		const scene = dialog.classList.contains( 'iftp-btn-sheet--scene' );
		const card = scene ? dialog.querySelector( ':scope > .iftp-btn-form, :scope > .iftp-btn-result__box' ) : dialog;
		const from = opener.getBoundingClientRect();

		if ( ! card || ! from.width ) {
			return;
		}

		// A scene's background opens like an iris from the button's centre on the screen.
		dialog.style.setProperty( '--iftp-btn-cx', Math.round( from.left + from.width / 2 ) + 'px' );
		dialog.style.setProperty( '--iftp-btn-cy', Math.round( from.top + from.height / 2 ) + 'px' );

		const left = ( scene ? dialog.offsetLeft + card.offsetLeft : card.offsetLeft );
		const top = ( scene ? dialog.offsetTop + card.offsetTop : card.offsetTop );

		dialog.style.setProperty( '--iftp-btn-ox', Math.round( from.left + from.width / 2 - left ) + 'px' );
		dialog.style.setProperty( '--iftp-btn-oy', Math.round( from.top + from.height / 2 - top ) + 'px' );
	}

	function openDialog( dialog, opener ) {
		const root = document.documentElement;

		root.style.setProperty( '--iftp-btn-sbw', Math.max( 0, window.innerWidth - root.clientWidth ) + 'px' );

		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );
		}

		growFrom( dialog, opener );

		if ( window.iftpBtnOpenFx ) {
			window.iftpBtnOpenFx.open( dialog );
		}

		playVideo( dialog );

		const first = dialog.querySelector( '.iftp-btn-form__input:not([type="hidden"])' );

		if ( first ) {
			first.focus();
		}
	}

	// Closing plays the opening backwards (the popup shrinks into the button), then really closes. Escape does the same.
	function closeDialog( dialog ) {
		if ( ! dialog || ! dialog.hasAttribute( 'open' ) || dialog.classList.contains( 'is-closing' ) ) {
			return;
		}

		const finish = function () {
			dialog.classList.remove( 'is-closing' );

			if ( typeof dialog.close === 'function' ) {
				dialog.close();
			} else {
				dialog.removeAttribute( 'open' );
			}
		};

		// The animation says how long it needs (voxel and waves lay a layer over the popup first).
		const wait = window.iftpBtnOpenFx ? window.iftpBtnOpenFx.close( dialog ) : 450;

		if ( wait <= 0 ) {
			finish();

			return;
		}

		dialog.classList.add( 'is-closing' );
		window.setTimeout( finish, wait );
	}

	document.addEventListener( 'cancel', function ( event ) {
		if ( event.target && event.target.classList && event.target.classList.contains( 'iftp-btn-sheet' ) ) {
			event.preventDefault();
			closeDialog( event.target );
		}
	}, true );

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
			return input.type !== 'hidden' && ! input.closest( '.iftp-btn-form__trap' ) && ! input.closest( '[data-iftp-btn-when][hidden], [data-iftp-btn-skip="1"]' );
		} );
	}

	// What a field holds, as the server's FormFields::answers() reads it: the picked options, '1' for a ticked agreement,
	// or the one text or number typed (a number may use a decimal comma).
	function answersOf( form, name ) {
		const values = [];

		form.querySelectorAll( '[name="' + name + '"]' ).forEach( function ( input ) {
			if ( input.tagName === 'SELECT' ) {
				if ( input.value ) {
					values.push( input.value );
				}
			} else if ( input.type === 'checkbox' || input.type === 'radio' ) {
				if ( input.checked ) {
					values.push( input.value );
				}
			} else if ( input.value.trim() ) {
				values.push( input.hasAttribute( 'data-iftp-btn-number' ) || input.inputMode === 'decimal' || input.inputMode === 'numeric' ? input.value.trim().replace( ',', '.' ) : input.value.trim() );
			}
		} );

		return values;
	}

	// The same test as FormFields::meets().
	function meets( rule, picked, typed ) {
		const op = rule.op;
		const want = ( rule.values || [] )[ 0 ];

		if ( op === 'filled' ) {
			return picked.length > 0;
		}

		if ( op === 'empty' ) {
			return picked.length === 0;
		}

		if ( op === 'gt' || op === 'lt' ) {
			const have = parseFloat( picked[ 0 ] );
			const limit = parseFloat( want );

			if ( isNaN( have ) || isNaN( limit ) ) {
				return false;
			}

			return op === 'gt' ? have > limit : have < limit;
		}

		if ( op === 'contains' ) {
			return !! want && String( picked[ 0 ] || '' ).toLowerCase().indexOf( String( want ).toLowerCase() ) !== -1;
		}

		let match = picked.some( function ( value ) {
			return rule.values.indexOf( value ) !== -1;
		} );

		// A typed answer is the same whatever its capitals.
		if ( ! match && typed && picked.length && want !== undefined ) {
			match = String( picked[ 0 ] ).toLowerCase() === String( want ).toLowerCase();
		}

		if ( ! match && picked.length && ! isNaN( Number( picked[ 0 ] ) ) ) {
			match = rule.values.some( function ( value ) {
				return value !== '' && ! isNaN( Number( value ) ) && Number( value ) === Number( picked[ 0 ] );
			} );
		}

		return op === 'not' ? ! match : match;
	}

	// Fields, text blocks and whole pages that show only for some answers. A hidden one's inputs are disabled, so the
	// browser neither checks nor sends them; the ones a sold-out option disabled stay disabled. A skipped page is marked
	// data-iftp-btn-skip, and onSteps hears about it so the step bar and the walk follow.
	function bindConditions( form, onSteps ) {
		const nodes = Array.prototype.slice.call( form.querySelectorAll( '[data-iftp-btn-when], [data-iftp-btn-step-when]' ) );

		if ( ! nodes.length ) {
			return;
		}

		const rules = nodes.map( function ( node ) {
			try {
				return JSON.parse( node.dataset.iftpBtnWhen || node.dataset.iftpBtnStepWhen );
			} catch ( e ) {
				return null;
			}
		} );

		// Off by a flag of its own, so an item hidden by its rule and a skipped page don't undo each other.
		const switchInputs = function ( node, off, flag ) {
			node.querySelectorAll( 'input, select, textarea' ).forEach( function ( input ) {
				if ( off && ! input.disabled ) {
					input.disabled = true;
					input.dataset[ flag ] = '1';
				} else if ( ! off && input.dataset[ flag ] ) {
					input.disabled = false;
					delete input.dataset[ flag ];
				}
			} );
		};

		const skipped = function ( node ) {
			return !! node.closest( '[data-iftp-btn-skip="1"]' );
		};

		const toggle = function ( node, show ) {
			if ( node.hasAttribute( 'data-iftp-btn-step-when' ) ) {
				if ( show ) {
					node.removeAttribute( 'data-iftp-btn-skip' );
				} else {
					node.setAttribute( 'data-iftp-btn-skip', '1' );
				}

				switchInputs( node, ! show, 'iftpBtnSkipOff' );

				return;
			}

			node.hidden = ! show;
			node.classList.toggle( 'is-revealed', show );
			switchInputs( node, ! show, 'iftpBtnOff' );
		};

		const apply = function () {
			// In form order, so a field hidden by its own condition hides the ones that hang on it.
			nodes.forEach( function ( node, index ) {
				const rule = rules[ index ];

				if ( ! rule ) {
					return;
				}

				const controller = form.querySelector( '[name="' + rule.field + '"]' );
				const holder = controller ? controller.closest( '[data-iftp-btn-when]' ) : null;
				const live = ! controller || ( ( ! holder || ! holder.hidden ) && ! skipped( controller ) );
				const typed = !! controller && controller.tagName !== 'SELECT' && controller.type !== 'checkbox' && controller.type !== 'radio';
				const show = live && meets( rule, answersOf( form, rule.field ), typed );

				toggle( node, node.hasAttribute( 'data-iftp-btn-step-when' ) ? show : show && ! skipped( node ) );
			} );

			if ( onSteps ) {
				onSteps();
			}
		};

		nodes.forEach( function ( node ) {
			// The server hid what the prefill didn't meet; its inputs still need switching off.
			if ( node.hidden && node.hasAttribute( 'data-iftp-btn-when' ) ) {
				node.hidden = false;
			}
		} );

		// A text or number answer changes on every key, a choice on change.
		form.addEventListener( 'change', apply );
		form.addEventListener( 'input', apply );
		apply();
	}

	// Live tags: {first_name} in a text block, a label or a help line shows what's typed in that field, or its fallback.
	// Only textContent is ever set, so what's typed can't add markup. A field a rule hid counts as empty.
	function bindTags( form ) {
		const tags = Array.prototype.slice.call( form.querySelectorAll( '[data-iftp-btn-tag]' ) );

		if ( ! tags.length ) {
			return;
		}

		const update = function () {
			tags.forEach( function ( tag ) {
				const values = [];

				form.querySelectorAll( '[name="' + tag.dataset.iftpBtnTag + '"]' ).forEach( function ( input ) {
					if ( input.disabled ) {
						return;
					}

					if ( input.type === 'checkbox' || input.type === 'radio' ) {
						if ( input.checked && input.value !== '1' ) {
							values.push( input.value );
						}
					} else if ( input.value.trim() ) {
						values.push( input.value.trim() );
					}
				} );

				const text = values.length ? values.join( ', ' ) : tag.dataset.iftpBtnFallback || '';

				if ( tag.textContent !== text ) {
					tag.textContent = text;
				}
			} );
		};

		form.addEventListener( 'input', update );
		form.addEventListener( 'change', update );
		update();
	}

	// A Number's limits, as messages in the payer's language with the field's own label. The server holds them too.
	function bindNumbers( form ) {
		const words = i18n.number || {};
		const say = function ( text, input, extra ) {
			return String( text || '' ).replace( '{field}', input.dataset.label || '' ).replace( '{min}', extra.min || '' ).replace( '{max}', extra.max || '' );
		};

		form.querySelectorAll( '[data-iftp-btn-number]' ).forEach( function ( input ) {
			const check = function () {
				const raw = input.value.trim().replace( ',', '.' );
				const value = parseFloat( raw );
				let message = '';

				if ( raw !== '' && ! isNaN( Number( raw ) ) ) {
					if ( input.dataset.whole && value !== Math.floor( value ) ) {
						message = say( words.whole, input, {} );
					} else if ( input.dataset.min !== undefined && value < parseFloat( input.dataset.min ) ) {
						message = say( words.min, input, { min: input.dataset.min } );
					} else if ( input.dataset.max !== undefined && value > parseFloat( input.dataset.max ) ) {
						message = say( words.max, input, { max: input.dataset.max } );
					}
				}

				input.setCustomValidity( message );
			};

			input.addEventListener( 'input', check );
			check();
		} );
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
		const strip = form.querySelector( '.iftp-btn-steps' );
		const track = strip ? strip.querySelector( '.iftp-btn-steps__track' ) : null;
		let current = 0;

		// A page the answers skip is left out of the walk, the step bar and the count.
		const skipped = function ( index ) {
			return steps[ index ].getAttribute( 'data-iftp-btn-skip' ) === '1';
		};

		const lastStep = function () {
			for ( let i = steps.length - 1; i > 0; i-- ) {
				if ( ! skipped( i ) ) {
					return i;
				}
			}

			return 0;
		};

		// The next page to show from here, going forward or back, or -1.
		const neighbour = function ( dir ) {
			for ( let i = current + dir; i >= 0 && i < steps.length; i += dir ) {
				if ( ! skipped( i ) ) {
					return i;
				}
			}

			return -1;
		};

		const position = function ( index ) {
			let at = 0;

			for ( let i = 0; i < index; i++ ) {
				at += skipped( i ) ? 0 : 1;
			}

			return at;
		};

		const total = function () {
			return position( steps.length );
		};

		// With many pages the pills and dots scroll: the current one stays in view, and the edge with more behind it fades.
		const edges = function () {
			strip.classList.toggle( 'has-before', track.scrollLeft > 2 );
			strip.classList.toggle( 'has-after', track.scrollLeft + track.clientWidth < track.scrollWidth - 2 );
		};

		const keepInView = function ( smooth ) {
			const bar = track && track.querySelector( '.is-current' );

			if ( ! bar ) {
				return;
			}

			// The bar's offsetParent isn't the track, so measure against the track's own box.
			const at = bar.getBoundingClientRect();
			const left = track.scrollLeft + at.left - track.getBoundingClientRect().left - ( track.clientWidth - at.width ) / 2;

			track.scrollTo( { left: Math.max( 0, left ), behavior: smooth && ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'smooth' : 'auto' } );
		};

		if ( track ) {
			track.addEventListener( 'scroll', edges, { passive: true } );
			window.addEventListener( 'resize', edges );
			edges();
		}

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
				pct.textContent = Math.round( ( position( current ) + fill ) / total() * 100 ) + '%';
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

			if ( track ) {
				keepInView( true );
				// The current chip is still opening up for a moment; centre it again once it has.
				window.setTimeout( function () {
					keepInView( true );
					edges();
				}, 450 );
			}

			const title = form.querySelector( '[data-iftp-btn-step-title]' );
			const now = form.querySelector( '[data-iftp-btn-step-now]' );

			if ( title && bars[ index ] ) {
				title.textContent = bars[ index ].dataset.title;
			}

			if ( now ) {
				now.textContent = String( position( index ) + 1 );
			}

			if ( back ) {
				back.hidden = index === 0;
			}

			submit.querySelector( '.iftp-btn-form__submit-text' ).textContent = index === lastStep() ? submit.dataset.pay : submit.dataset.next;
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

			if ( current < lastStep() ) {
				const invalid = firstInvalid( steps[ current ] );

				if ( invalid ) {
					showError( form, invalid.message );
					invalid.input.focus();

					return;
				}

				show( neighbour( 1 ) );

				return;
			}

			start( form );
		} );

		if ( back ) {
			back.addEventListener( 'click', function () {
				show( neighbour( -1 ) );
			} );
		}

		// The bar follows the pages the answers skip: they vanish, and the rest are numbered again.
		const refresh = function () {
			if ( steps.length < 2 ) {
				return;
			}

			let place = 0;

			bars.forEach( function ( bar, at ) {
				const gone = skipped( at );

				bar.hidden = gone;

				if ( ! gone ) {
					bar.style.setProperty( '--iftp-i', String( place ) );

					const num = bar.querySelector( '.iftp-btn-steps__num' );

					if ( num ) {
						num.textContent = String( place + 1 );
					}

					place++;
				}
			} );

			if ( strip ) {
				strip.style.setProperty( '--iftp-steps', String( place ) );
			}

			const all = form.querySelector( '[data-iftp-btn-step-total]' );

			if ( all ) {
				all.textContent = String( place );
			}

			const now = form.querySelector( '[data-iftp-btn-step-now]' );

			if ( now ) {
				now.textContent = String( position( current ) + 1 );
			}

			submit.querySelector( '.iftp-btn-form__submit-text' ).textContent = current === lastStep() ? submit.dataset.pay : submit.dataset.next;
		};

		// Before progress, so the step bar counts only what shows.
		bindConditions( form, refresh );
		bindNumbers( form );
		bindTags( form );
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

			openDialog( dialog, opener );
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
