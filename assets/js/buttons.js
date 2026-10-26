/**
 * The Buttons and Checkout screens: copying, the list's search, filter and on/off switches, the delete dialog,
 * the editor's tabs, design controls, live preview and link builder, the webhook test, and the two-view message editor.
 */
( function () {
	'use strict';

	const config = window.iftpBtnButtons || { i18n: {} };
	const i18n = config.i18n || {};

	function post( action, data ) {
		const body = new URLSearchParams( { action: action, _ajax_nonce: config.nonce } );

		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
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

	function toast( message, isError ) {
		const box = document.querySelector( '[data-iftp-toast]' );

		if ( ! box ) {
			return;
		}

		box.querySelector( '[data-iftp-toast-message]' ).textContent = message;
		box.classList.toggle( 'iftp-btn-toast--error', !! isError );
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
	let leaving = false;
	const deleteDialog = document.querySelector( '[data-iftp-delete-dialog]' );

	document.querySelectorAll( '[data-iftp-confirm-delete]' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			const name = link.dataset.iftpConfirmDelete;

			if ( ! deleteDialog || typeof deleteDialog.showModal !== 'function' ) {
				// eslint-disable-next-line no-alert
				if ( window.confirm( ( i18n.deleteNamed || '%s' ).replace( '%s', name ) ) ) {
					leaving = true;
					window.location.href = link.href;
				}

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
			leaving = true;
		} );

		// A click on the backdrop lands on the dialog itself.
		deleteDialog.addEventListener( 'click', function ( event ) {
			if ( event.target === deleteDialog ) {
				deleteDialog.close();
			}
		} );
	}

	bindTags( document );

	/* ---------------------------------------------------------------- List */

	const cards = Array.prototype.slice.call( document.querySelectorAll( '[data-iftp-bcard]' ) );
	const grid = document.querySelector( '[data-iftp-bcards]' );
	const toolbar = document.querySelector( '[data-iftp-btoolbar]' );
	const search = document.querySelector( '[data-iftp-bsearch]' );
	const typeSelect = document.querySelector( '[data-iftp-btype]' );
	const sortSelect = document.querySelector( '[data-iftp-bsort]' );
	const listKey = 'iftpBtnList';
	let filter = 'all';

	// The sort and the layout are only this browser's preference, so storage failing just means the defaults.
	function remembered() {
		try {
			return JSON.parse( window.localStorage.getItem( listKey ) ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function remember( key, value ) {
		try {
			const saved = remembered();
			saved[ key ] = value;
			window.localStorage.setItem( listKey, JSON.stringify( saved ) );
		} catch ( e ) {}
	}

	function applyFilter() {
		const term = search ? search.value.trim().toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ) : '';
		const type = typeSelect ? typeSelect.value : '';
		let shown = 0;

		cards.forEach( function ( card ) {
			const on = card.dataset.active === '1';
			const status = filter === 'all' || ( filter === 'sold' ? card.dataset.sold === '1' : ( filter === 'on' ) === on );
			const visible = status && ( ! type || card.dataset.mode === type ) && ( ! term || card.dataset.title.indexOf( term ) !== -1 );

			card.hidden = ! visible;
			shown += visible ? 1 : 0;
		} );

		const none = document.querySelector( '[data-iftp-bnone]' );
		if ( none ) {
			none.hidden = shown > 0;
		}

		// The "New button" and "Build with AI" tiles only belong to the unfiltered list.
		document.querySelectorAll( '[data-iftp-bcard-new]' ).forEach( function ( tile ) {
			tile.hidden = filter !== 'all' || !! term || !! type;
		} );
	}

	function applySort( key ) {
		const first = grid.querySelector( '[data-iftp-bcard-new]' );
		const by = {
			collected: function ( a, b ) {
				return parseFloat( b.dataset.collected ) - parseFloat( a.dataset.collected );
			},
			paid: function ( a, b ) {
				return parseInt( b.dataset.paid, 10 ) - parseInt( a.dataset.paid, 10 );
			},
			newest: function ( a, b ) {
				return parseInt( a.dataset.order, 10 ) - parseInt( b.dataset.order, 10 );
			},
			oldest: function ( a, b ) {
				return parseInt( b.dataset.order, 10 ) - parseInt( a.dataset.order, 10 );
			},
			title: function ( a, b ) {
				return a.dataset.title.localeCompare( b.dataset.title );
			},
		};

		if ( ! by[ key ] ) {
			return;
		}

		// Ties keep their date order, newest first.
		cards.slice().sort( function ( a, b ) {
			return by[ key ]( a, b ) || parseInt( a.dataset.order, 10 ) - parseInt( b.dataset.order, 10 );
		} ).forEach( function ( card ) {
			grid.insertBefore( card, first );
		} );
	}

	function applyView( view ) {
		grid.classList.toggle( 'iftp-btn-bcards--list', view === 'list' );

		toolbar.querySelectorAll( '[data-iftp-bview]' ).forEach( function ( option ) {
			option.setAttribute( 'aria-pressed', option.dataset.iftpBview === view ? 'true' : 'false' );
		} );
	}

	function updateCounts() {
		const on = cards.filter( function ( card ) {
			return card.dataset.active === '1';
		} ).length;
		const counts = { all: cards.length, on: on, off: cards.length - on };

		Object.keys( counts ).forEach( function ( key ) {
			const badge = document.querySelector( '[data-iftp-bfilter-count="' + key + '"]' );

			if ( badge ) {
				badge.textContent = counts[ key ].toLocaleString();
			}
		} );
	}

	if ( toolbar && cards.length ) {
		const saved = remembered();

		toolbar.hidden = false;

		search.addEventListener( 'input', applyFilter );
		typeSelect.addEventListener( 'change', applyFilter );

		sortSelect.addEventListener( 'change', function () {
			applySort( sortSelect.value );
			remember( 'sort', sortSelect.value );
		} );

		toolbar.querySelectorAll( '[data-iftp-bfilter]' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				filter = option.dataset.iftpBfilter;

				toolbar.querySelectorAll( '[data-iftp-bfilter]' ).forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === option ? 'true' : 'false' );
				} );

				applyFilter();
			} );
		} );

		toolbar.querySelectorAll( '[data-iftp-bview]' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				applyView( option.dataset.iftpBview );
				remember( 'view', option.dataset.iftpBview );
			} );
		} );

		// The page arrives sorted by Most collected in the grid; a remembered choice replaces either.
		if ( saved.sort && saved.sort !== sortSelect.value && sortSelect.querySelector( 'option[value="' + saved.sort + '"]' ) ) {
			sortSelect.value = saved.sort;
			applySort( saved.sort );

			if ( sortSelect.iftpDropdown ) {
				sortSelect.iftpDropdown.sync();
			}
		}

		if ( saved.view === 'list' ) {
			applyView( 'list' );
		}
	}

	document.querySelectorAll( '[data-iftp-toggle-button]' ).forEach( function ( toggle ) {
		toggle.addEventListener( 'change', function () {
			const card = toggle.closest( '[data-iftp-bcard]' );
			const wanted = toggle.checked;

			toggle.disabled = true;

			post( 'iftp_btn_toggle_button', { button: toggle.dataset.iftpToggleButton, active: wanted ? '1' : '0' } )
				.then( function ( result ) {
					if ( ! result || ! result.success ) {
						throw new Error( ( result && result.data && result.data.message ) || i18n.error );
					}

					card.classList.toggle( 'iftp-btn-bcard--off', ! wanted );
					card.dataset.active = wanted ? '1' : '0';
					updateCounts();
					applyFilter();
					toast( wanted ? i18n.turnedOn : i18n.turnedOff );
				} )
				.catch( function ( error ) {
					toggle.checked = ! wanted;
					toast( error.message, true );
				} )
				.finally( function () {
					toggle.disabled = false;
				} );
		} );
	} );

	/* ---------------------------------------------------------------- Show/hide by a choice */

	function bindReveal( scope, inputSelector, attribute ) {
		const inputs = scope.querySelectorAll( inputSelector );

		if ( ! inputs.length ) {
			return;
		}

		const current = function () {
			let value = '';

			inputs.forEach( function ( input ) {
				if ( input.tagName === 'SELECT' || input.checked ) {
					value = input.value;
				}
			} );

			return value;
		};

		const apply = function () {
			const value = current();

			scope.querySelectorAll( '[' + attribute + ']' ).forEach( function ( target ) {
				target.hidden = target.getAttribute( attribute ).split( ' ' ).indexOf( value ) === -1;
			} );
		};

		inputs.forEach( function ( input ) {
			input.addEventListener( 'change', apply );
		} );

		apply();
	}

	/* ---------------------------------------------------------------- Editor tabs */

	function bindTabs( form ) {
		const list = form.querySelector( '[data-iftp-tabs]' );

		if ( ! list ) {
			return;
		}

		const tabs = Array.prototype.slice.call( list.querySelectorAll( '[data-iftp-tab]' ) );
		const input = form.querySelector( '[data-iftp-tab-input]' );

		const select = function ( name, focus ) {
			const tab = tabs.filter( function ( one ) {
				return one.dataset.iftpTab === name;
			} )[ 0 ] || tabs[ 0 ];

			tabs.forEach( function ( one ) {
				const on = one === tab;

				one.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				one.tabIndex = on ? 0 : -1;
				form.querySelector( '[data-iftp-panel="' + one.dataset.iftpTab + '"]' ).hidden = ! on;
			} );

			input.value = tab.dataset.iftpTab;
			form.dataset.tab = tab.dataset.iftpTab;

			if ( focus ) {
				tab.focus();
			}

			if ( window.history.replaceState ) {
				window.history.replaceState( null, '', tab === tabs[ 0 ] ? window.location.pathname + window.location.search : '#' + tab.dataset.iftpTab );
			}
		};

		list.hidden = false;

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				select( tab.dataset.iftpTab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				const keys = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };

				if ( ! Object.prototype.hasOwnProperty.call( keys, event.key ) ) {
					return;
				}

				event.preventDefault();
				select( tabs[ ( keys[ event.key ] + tabs.length ) % tabs.length ].dataset.iftpTab, true );
			} );
		} );

		form.querySelectorAll( '[data-iftp-goto-tab]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				select( link.dataset.iftpGotoTab, true );
			} );
		} );

		// A required field on a hidden tab can't show its message, so I open its tab first.
		form.addEventListener(
			'invalid',
			function ( event ) {
				const panel = event.target.closest( '[data-iftp-panel]' );

				if ( panel && panel.hidden ) {
					select( panel.dataset.iftpPanel, false );
				}
			},
			true
		);

		select( window.location.hash.replace( '#', '' ), false );
	}

	/* ---------------------------------------------------------------- Button design */

	function hex( raw ) {
		let color = String( raw || '' ).trim().toLowerCase();
		const short = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec( color );

		if ( short ) {
			color = '#' + short[ 1 ] + short[ 1 ] + short[ 2 ] + short[ 2 ] + short[ 3 ] + short[ 3 ];
		}

		return /^#[0-9a-f]{6}$/.test( color ) ? color : '';
	}

	function channels( color ) {
		return [ 1, 3, 5 ].map( function ( at ) {
			return parseInt( color.substr( at, 2 ), 16 );
		} );
	}

	// Same rule as ButtonDesign::on_color(): above 0.179 luminance, black text out-contrasts white.
	function onColor( color ) {
		const lum = channels( color ).map( function ( channel ) {
			const v = channel / 255;

			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );

		return 0.2126 * lum[ 0 ] + 0.7152 * lum[ 1 ] + 0.0722 * lum[ 2 ] > 0.179 ? '#121a2c' : '#ffffff';
	}

	// Same as ButtonDesign::text_color() without a picked colour.
	function autoText( design ) {
		if ( design.variant === 'gradient' ) {
			return onColor( mix( design.color, design.color2 ) );
		}

		return [ 'filled', 'glass', '3d', 'brutal' ].indexOf( design.variant ) === -1 ? design.color : onColor( design.color );
	}

	function toHex( parts ) {
		return '#' + parts.map( function ( part ) {
			return ( '0' + Math.floor( part + 0.5 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	// Same as ButtonDesign::mix().
	function mix( one, two ) {
		const a = channels( one );
		const b = channels( two );

		return toHex( [ 0, 1, 2 ].map( function ( at ) {
			return ( a[ at ] + b[ at ] ) / 2;
		} ) );
	}

	// Same as ButtonDesign::turn(): round the colour wheel, same lightness and saturation.
	function turn( color, degrees ) {
		const rgb = channels( color ).map( function ( channel ) {
			return channel / 255;
		} );
		const max = Math.max.apply( null, rgb );
		const min = Math.min.apply( null, rgb );
		const light = ( max + min ) / 2;
		const range = max - min;

		if ( range <= 0 ) {
			return color;
		}

		const sat = light > 0.5 ? range / ( 2 - max - min ) : range / ( max + min );
		let hue;

		if ( max === rgb[ 0 ] ) {
			hue = ( rgb[ 1 ] - rgb[ 2 ] ) / range + ( rgb[ 1 ] < rgb[ 2 ] ? 6 : 0 );
		} else if ( max === rgb[ 1 ] ) {
			hue = ( rgb[ 2 ] - rgb[ 0 ] ) / range + 2;
		} else {
			hue = ( rgb[ 0 ] - rgb[ 1 ] ) / range + 4;
		}

		hue = ( ( hue * 60 + degrees ) % 360 ) / 360;

		const q = light < 0.5 ? light * ( 1 + sat ) : light + sat - light * sat;
		const p = 2 * light - q;
		const to = function ( t ) {
			if ( t < 0 ) {
				++t;
			} else if ( t > 1 ) {
				--t;
			}

			if ( t < 1 / 6 ) {
				return p + ( q - p ) * 6 * t;
			}

			if ( t < 1 / 2 ) {
				return q;
			}

			return t < 2 / 3 ? p + ( q - p ) * ( 2 / 3 - t ) * 6 : p;
		};

		return toHex( [ to( hue + 1 / 3 ) * 255, to( hue ) * 255, to( hue - 1 / 3 ) * 255 ] );
	}

	// Two-way sync between each colour swatch and the hex field it's for; the hex field is the one submitted.
	function bindSwatches( form ) {
		form.querySelectorAll( '[data-iftp-color-for]' ).forEach( function ( swatch ) {
			const field = form.querySelector( '#' + swatch.dataset.iftpColorFor );

			// The editor binds its button and checkout designs on the same form; once each is enough.
			if ( ! field || swatch.dataset.iftpBound ) {
				return;
			}

			swatch.dataset.iftpBound = '1';

			swatch.addEventListener( 'input', function () {
				field.value = swatch.value;
				field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );

			field.addEventListener( 'input', function () {
				const color = hex( field.value );

				if ( color ) {
					swatch.value = color;
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------- Merge tags */

	// A chip drops its tag where the cursor was, in the field last focused inside the same scope.
	function bindTags( root ) {
		root.querySelectorAll( '[data-iftp-tag-scope]' ).forEach( function ( scope ) {
			let last = null;

			scope.addEventListener( 'focusin', function ( event ) {
				if ( event.target.matches( '[data-iftp-tag-target]' ) ) {
					last = event.target;
				}
			} );

			scope.querySelectorAll( '[data-iftp-tag]' ).forEach( function ( chip ) {
				// Keeps the cursor where it was in the field.
				chip.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
				} );

				chip.addEventListener( 'click', function () {
					const visible = Array.prototype.filter.call( scope.querySelectorAll( '[data-iftp-tag-target]' ), function ( target ) {
						return ! target.hidden && target.offsetParent !== null;
					} );
					const target = last && ! last.hidden && last.offsetParent !== null ? last : visible[ visible.length - 1 ];
					// {field:id} goes in open, so the cursor lands where the field's id belongs.
					const tag = chip.dataset.iftpTag === '{field:id}' ? '{field:}' : chip.dataset.iftpTag;

					if ( ! target ) {
						return;
					}

					target.focus();

					if ( target.isContentEditable ) {
						document.execCommand( 'insertText', false, tag );

						return;
					}

					const start = target.selectionStart === null ? target.value.length : target.selectionStart;
					const end = target.selectionEnd === null ? start : target.selectionEnd;

					target.setRangeText( tag, start, end, 'end' );

					if ( tag === '{field:}' ) {
						target.setSelectionRange( start + tag.length - 1, start + tag.length - 1 );
					}

					target.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				} );
			} );
		} );
	}

	function readDesign( form ) {
		const pick = function ( key ) {
			const input = form.querySelector( '[data-iftp-design="' + key + '"]:checked' ) || form.querySelector( '[data-iftp-design="' + key + '"]:not([type="radio"])' );

			if ( ! input ) {
				return '';
			}

			return input.type === 'checkbox' ? input.checked : input.value;
		};

		const design = {
			source: pick( 'source' ) || 'theme',
			variant: pick( 'variant' ) || 'filled',
			color: hex( pick( 'color' ) ) || '#00609c',
			radius: parseInt( pick( 'radius' ), 10 ) || 0,
			borderWidth: parseInt( pick( 'border_width' ), 10 ) || 0,
			borderStyle: pick( 'border_style' ) || 'solid',
			borderColor: pick( 'border_color_auto' ) ? '' : hex( pick( 'border_color' ) ),
			size: pick( 'size' ) || 'md',
			icon: pick( 'icon' ) || 'none',
			full: pick( 'full' ) === '1',
			shadow: !! pick( 'shadow' ),
			effect: pick( 'effect' ) || 'none',
			attention: pick( 'attention' ) || 'none',
			upper: !! pick( 'upper' ),
		};

		// wp_localize_script() hands numbers over as strings.
		const turnBy = parseInt( config.gradientTurn, 10 ) || 40;

		design.color2 = pick( 'color2_auto' ) ? turn( design.color, turnBy ) : hex( pick( 'color2' ) ) || turn( design.color, turnBy );

		// The samples redraw it for other styles, so the picked one is kept apart from the auto one.
		design.ownText = pick( 'text_auto' ) ? '' : hex( pick( 'text' ) );
		design.text = design.ownText || autoText( design );

		return design;
	}

	// The same markup as ButtonDesign::preview_markup().
	function buildButton( form, design, label ) {
		const custom = design.source === 'custom';
		const classes = [ 'iftp-btn-pay__button' ];
		const button = document.createElement( 'span' );
		const text = document.createElement( 'span' );
		const iconSource = form.querySelector( '[data-iftp-icon-svg="' + design.icon + '"]' );

		if ( custom ) {
			const rgb = channels( design.color ).join( ' ' );

			classes.push( 'iftp-btn-pay__button--custom', 'iftp-btn-pay__button--' + design.variant, 'iftp-btn-pay__button--' + design.size );

			if ( design.shadow ) {
				classes.push( 'iftp-btn-pay__button--shadow' );
			}

			if ( design.effect !== 'none' ) {
				classes.push( 'iftp-btn-pay__button--fx-' + design.effect );
			}

			if ( design.attention !== 'none' ) {
				classes.push( 'iftp-btn-pay__button--cta-' + design.attention );
			}

			if ( design.borderColor ) {
				classes.push( 'iftp-btn-pay__button--own-border' );
				button.style.setProperty( '--iftp-pb-bc', design.borderColor );
			}

			if ( design.upper ) {
				classes.push( 'iftp-btn-pay__button--upper' );
			}

			button.style.setProperty( '--iftp-pb-color', design.color );
			button.style.setProperty( '--iftp-pb-color2', design.color2 );
			button.style.setProperty( '--iftp-pb-text', design.text );
			button.style.setProperty( '--iftp-pb-on', onColor( design.color ) );
			button.style.setProperty( '--iftp-pb-radius', design.radius >= ( parseInt( config.pill, 10 ) || 32 ) ? '999px' : design.radius + 'px' );
			button.style.setProperty( '--iftp-pb-bw', design.borderWidth + 'px' );
			button.style.setProperty( '--iftp-pb-bs', design.borderStyle );
			button.style.setProperty( '--iftp-pb-tint', 'rgb(' + rgb + ' / 14%)' );
			button.style.setProperty( '--iftp-pb-tint-strong', 'rgb(' + rgb + ' / 24%)' );
			button.style.setProperty( '--iftp-pb-glass', 'rgb(' + rgb + ' / 62%)' );
			button.style.setProperty( '--iftp-pb-glow', 'rgb(' + rgb + ' / 45%)' );
		} else {
			classes.push( 'iftp-btn-pay__button--theme' );
		}

		if ( design.full ) {
			classes.push( 'iftp-btn-pay__button--full' );
		}

		button.className = classes.join( ' ' );
		text.className = 'iftp-btn-pay__label';
		text.textContent = label;
		button.appendChild( text );

		if ( iconSource && iconSource.firstElementChild ) {
			const icon = iconSource.firstElementChild.cloneNode( true );

			if ( design.icon === 'arrow' ) {
				button.appendChild( icon );
			} else {
				button.insertBefore( icon, text );
			}
		}

		return button;
	}

	function renderButton( form, label ) {
		const wrap = form.querySelector( '[data-iftp-preview-button]' );
		const design = readDesign( form );

		wrap.textContent = '';
		wrap.appendChild( buildButton( form, design, label ) );

		const note = form.querySelector( '[data-iftp-theme-note]' );

		if ( note ) {
			note.hidden = design.source === 'custom';
		}

		renderSamples( form, design );
	}

	// The picker tiles, in the current colours. Same overrides as ButtonEditor::render_fx_picker().
	function renderSamples( form, design ) {
		form.querySelectorAll( '[data-iftp-fx-sample]' ).forEach( function ( stage ) {
			const sample = Object.assign( {}, design, { source: 'custom', size: 'sm', full: false, effect: 'none', attention: 'none' } );
			const label = stage.querySelector( '.iftp-btn-pay__label' );
			const pay = document.createElement( 'span' );

			sample[ stage.dataset.iftpFxSample ] = stage.dataset.iftpFxValue;

			if ( sample.effect === 'nudge' && sample.icon === 'none' ) {
				sample.icon = 'arrow';
			}

			sample.text = design.ownText || autoText( sample );
			pay.className = 'iftp-btn-pay';
			pay.appendChild( buildButton( form, sample, label ? label.textContent : '' ) );

			// A hover sample being pointed at keeps showing its hover.
			if ( stage.parentElement.matches( ':hover, :focus-within' ) && stage.dataset.iftpFxSample === 'effect' ) {
				pay.firstChild.classList.add( 'is-hover' );
			}

			stage.textContent = '';
			stage.appendChild( pay );
		} );
	}

	// Pointing at (or tabbing to) a hover tile plays its hover on the sample.
	function bindHoverSamples( form ) {
		form.querySelectorAll( '.iftp-btn-fxpick--hover .iftp-btn-fxpick__item' ).forEach( function ( item ) {
			const set = function ( on ) {
				const button = item.querySelector( '.iftp-btn-pay__button' );

				if ( button ) {
					button.classList.toggle( 'is-hover', on );
				}
			};

			item.addEventListener( 'mouseenter', function () {
				set( true );
			} );
			item.addEventListener( 'mouseleave', function () {
				set( item.matches( ':focus-within' ) );
			} );
			item.addEventListener( 'focusin', function () {
				set( true );
			} );
			item.addEventListener( 'focusout', function () {
				set( item.matches( ':hover' ) );
			} );
		} );
	}

	function bindDesignControls( form ) {
		const fire = function ( input ) {
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		bindSwatches( form );
		bindHoverSamples( form );

		const textAuto = form.querySelector( '[data-iftp-design="text_auto"]' );
		const textBox = form.querySelector( '[data-iftp-text-color]' );
		const textField = form.querySelector( '[data-iftp-design="text"]' );
		const textSwatch = textBox ? textBox.querySelector( '[data-iftp-color-for]' ) : null;

		// Touching the text colour means they want their own, so "pick it for me" lets go.
		[ textField, textSwatch ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					if ( textAuto && textAuto.checked ) {
						textAuto.checked = false;
						fire( textAuto );
					}
				} );
			}
		} );

		const radius = form.querySelector( '[data-iftp-design="radius"]' );
		const radiusOut = form.querySelector( '[data-iftp-radius-out]' );
		const borderOut = form.querySelector( '[data-iftp-border-out]' );
		const borderAuto = form.querySelector( '[data-iftp-design="border_color_auto"]' );
		const borderBox = form.querySelector( '[data-iftp-border-color]' );
		const borderField = form.querySelector( '[data-iftp-design="border_color"]' );
		const borderSwatch = borderBox ? borderBox.querySelector( '[data-iftp-color-for]' ) : null;

		// And for the border colour.
		[ borderField, borderSwatch ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					if ( borderAuto && borderAuto.checked ) {
						borderAuto.checked = false;
						fire( borderAuto );
					}
				} );
			}
		} );
		const color2Auto = form.querySelector( '[data-iftp-design="color2_auto"]' );
		const color2Box = form.querySelector( '[data-iftp-color2]' );
		const color2Field = form.querySelector( '[data-iftp-design="color2"]' );
		const color2Swatch = color2Box ? color2Box.querySelector( '[data-iftp-color-for]' ) : null;

		// Same for the gradient's second colour.
		[ color2Field, color2Swatch ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					if ( color2Auto && color2Auto.checked ) {
						color2Auto.checked = false;
						fire( color2Auto );
					}
				} );
			}
		} );

		const sync = function () {
			const design = readDesign( form );

			if ( color2Box && color2Auto ) {
				color2Box.classList.toggle( 'is-auto', color2Auto.checked );

				if ( color2Auto.checked ) {
					color2Field.value = design.color2;
					color2Swatch.value = design.color2;
				}
			}

			if ( textBox && textAuto ) {
				textBox.classList.toggle( 'is-auto', textAuto.checked );

				if ( textAuto.checked ) {
					textField.value = design.text;
					textSwatch.value = design.text;
				}
			}

			if ( borderBox && borderAuto ) {
				borderBox.classList.toggle( 'is-auto', borderAuto.checked );

				// Brutal's own border is ink; every other style's is its colour.
				if ( borderAuto.checked ) {
					borderField.value = design.variant === 'brutal' ? '#121a2c' : design.color;
					borderSwatch.value = borderField.value;
				}
			}

			if ( radius && radiusOut ) {
				radiusOut.textContent = design.radius >= ( config.pill || 32 ) ? i18n.pill : ( i18n.px || '%d px' ).replace( '%d', design.radius );
			}

			if ( borderOut ) {
				borderOut.textContent = ( i18n.px || '%d px' ).replace( '%d', design.borderWidth );
			}
		};

		form.addEventListener( 'input', sync );
		form.addEventListener( 'change', sync );
		sync();

		form.querySelectorAll( '[data-iftp-preset]' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				const preset = ( config.presets || {} )[ item.dataset.iftpPreset ];

				if ( ! preset ) {
					return;
				}

				const set = function ( key, value ) {
					form.querySelectorAll( '[data-iftp-design="' + key + '"]' ).forEach( function ( input ) {
						if ( input.type === 'radio' ) {
							input.checked = input.value === String( value );
						} else if ( input.type === 'checkbox' ) {
							input.checked = !! value;
						} else {
							input.value = value;
						}
					} );
				};

				set( 'variant', preset.variant );
				set( 'color', preset.color );
				set( 'radius', preset.radius );
				set( 'border_width', preset.border_width );
				set( 'border_style', preset.border_style );
				set( 'size', preset.size );
				set( 'icon', preset.icon );
				set( 'shadow', preset.shadow );
				set( 'effect', preset.effect );
				set( 'attention', preset.attention );
				set( 'upper', preset.upper );
				set( 'text_auto', ! preset.text );
				set( 'color2_auto', ! preset.color2 );
				set( 'border_color_auto', ! preset.border_color );

				if ( preset.border_color ) {
					set( 'border_color', preset.border_color );
				}

				if ( preset.color2 ) {
					set( 'color2', preset.color2 );
				}

				if ( preset.text ) {
					set( 'text', preset.text );
				}

				form.querySelectorAll( '[data-iftp-color-for]' ).forEach( function ( swatch ) {
					const color = hex( form.querySelector( '#' + swatch.dataset.iftpColorFor ).value );

					if ( color ) {
						swatch.value = color;
					}
				} );

				fire( form.querySelector( '[data-iftp-design="color"]' ) );
			} );
		} );

		form.querySelectorAll( '[data-iftp-stage-bg]' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				form.querySelector( '[data-iftp-stage]' ).dataset.bg = option.dataset.iftpStageBg;

				form.querySelectorAll( '[data-iftp-stage-bg]' ).forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === option ? 'true' : 'false' );
				} );
			} );
		} );
	}

	/* ---------------------------------------------------------------- Checkout form builder */

	const fbIcons = {
		amount: '<circle cx="12" cy="12" r="8.5"/><path d="M15 8.8a3.6 3.6 0 1 0 0 6.4M7.5 11h6M7.5 13.2h6"/>',
		details: '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
		text: '<path d="M5 7h14M5 12h10M5 17h7"/>',
		textarea: '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M7.5 9h9M7.5 12.5h9M7.5 16h5"/>',
		email: '<rect x="3.5" y="6" width="17" height="12" rx="2.5"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5"/>',
		tel: '<path d="M6.5 4h3l1.5 4-2 1.3a10 10 0 0 0 5.7 5.7l1.3-2 4 1.5v3a2 2 0 0 1-2 2A15.5 15.5 0 0 1 4.5 6a2 2 0 0 1 2-2z"/>',
		number: '<path d="M10 4 8 20M16 4l-2 16M5 9h15M4 15h15"/>',
		date: '<rect x="4" y="5.5" width="16" height="14" rx="2.5"/><path d="M4 10h16M8.5 3.5v4M15.5 3.5v4"/>',
		select: '<rect x="3.5" y="6.5" width="17" height="11" rx="2.5"/><path d="m14 11 2 2 2-2"/>',
		radio: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.2" fill="currentColor" stroke="none"/>',
		checkboxes: '<rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="m8.5 12.3 2.4 2.4 4.8-5.2"/>',
		checkbox: '<path d="M12 3.5 5 6.5v4.8c0 4.2 3 8 7 9.2 4-1.2 7-5 7-9.2V6.5z"/><path d="m9.2 12 2 2 3.8-4"/>',
		content: '<path d="M5 6h14M5 10h14M5 14h9M5 18h6"/>',
		page: '<path d="M7 3.5h10v5H7zM7 15.5h10v5H7z"/><path d="M3.5 12h3M10.5 12h3M17.5 12h3"/>',
	};

	const fbInputs = [ 'text', 'textarea', 'email', 'tel', 'number', 'date', 'select', 'radio', 'checkboxes', 'checkbox' ];
	const fbChoices = [ 'select', 'radio', 'checkboxes' ];
	const fbPlaceholder = [ 'text', 'textarea', 'email', 'tel', 'number', 'select' ];
	// What a condition can hang on; an agreement's one answer is "ticked" ('1').
	const fbConditions = [ 'select', 'radio', 'checkboxes', 'checkbox' ];
	const fbMax = 40;

	function noCondition() {
		return { field: '', op: 'is', values: [] };
	}

	// The same rules as FormFields::tidy_conditions(): an earlier choice or agreement, and only options it still has.
	function tidyConditions( items ) {
		const earlier = {};

		items.forEach( function ( item ) {
			const when = item.when || noCondition();
			const controller = when.field ? earlier[ when.field ] : null;

			if ( controller ) {
				const allowed = controller.type === 'checkbox' ? [ '1' ] : controller.options || [];

				when.values = ( when.values || [] ).filter( function ( value ) {
					return allowed.indexOf( value ) !== -1;
				} );
			}

			item.when = controller ? when : noCondition();

			if ( fbConditions.indexOf( item.type ) !== -1 ) {
				earlier[ item.id ] = item;
			}
		} );
	}

	// Which items show for some answers, like FormFields::visibility(); the editor's mock uses it.
	function visibleItems( items, answers ) {
		const shown = {};

		items.forEach( function ( item ) {
			const when = item.when || noCondition();

			if ( ! when.field || ! when.values.length ) {
				shown[ item.id ] = true;

				return;
			}

			const live = !! shown[ when.field ];
			const match = live && ( answers[ when.field ] || [] ).some( function ( value ) {
				return when.values.indexOf( value ) !== -1;
			} );

			shown[ item.id ] = live && ( when.op === 'not' ? ! match : match );
		} );

		return shown;
	}

	function fbIcon( type ) {
		return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ( fbIcons[ type ] || '' ) + '</svg>';
	}

	function slug( text ) {
		return String( text || '' )
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' )
			.slice( 0, 40 );
	}

	function el( tag, className, text ) {
		const node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( text !== undefined ) {
			node.textContent = text;
		}

		return node;
	}

	function bindFormBuilder( form ) {
		const root = form.querySelector( '[data-iftp-fb]' );

		if ( ! root ) {
			form.iftpFormState = [];

			return;
		}

		const input = root.querySelector( '[data-iftp-fb-input]' );
		const list = root.querySelector( '[data-iftp-fb-list]' );
		const first = root.querySelector( '[data-iftp-fb-first]' );
		const types = JSON.parse( root.dataset.types || '{}' );
		const fb = i18n.fb || {};
		// UI-only flags (open, fresh) live beside the items, so they never reach the saved JSON.
		const ui = new WeakMap();
		let items = [];

		try {
			items = JSON.parse( input.value || '[]' );
		} catch ( e ) {
			items = [];
		}

		items = Array.isArray( items ) ? items : [];
		form.iftpFormState = items;

		root.querySelectorAll( '[data-iftp-fb-icon]' ).forEach( function ( slot ) {
			slot.innerHTML = fbIcon( slot.dataset.iftpFbIcon );
		} );

		const flag = function ( item ) {
			if ( ! ui.has( item ) ) {
				ui.set( item, { open: false, fresh: false } );
			}

			return ui.get( item );
		};

		const isAnchor = function ( item ) {
			return item.type === 'amount' || item.type === 'details';
		};

		const uniqueId = function ( wanted, self ) {
			const base = wanted || 'field';
			let id = base;
			let n = 2;

			const taken = function ( candidate ) {
				return candidate === 'amount' || candidate === 'details' || items.some( function ( other ) {
					return other !== self && other.id === candidate;
				} );
			};

			while ( taken( id ) ) {
				id = base.slice( 0, 36 ) + '_' + n++;
			}

			return id;
		};

		const save = function () {
			tidyConditions( items );

			items.forEach( function ( item ) {
				if ( form.iftpSpotsField && form.iftpSpotsField() === item.id ) {
					item.when = noCondition();
				}
			} );

			input.value = JSON.stringify(
				items.map( function ( item ) {
					return {
						id: item.id,
						type: item.type,
						label: item.label || '',
						required: !! item.required,
						placeholder: item.placeholder || '',
						help: item.help || '',
						options: item.options || [],
						width: item.width === 'half' ? 'half' : 'full',
						when: item.when && item.when.field ? item.when : noCondition(),
					};
				} )
			);
			first.hidden = ! items.some( function ( item ) {
				return item.type === 'page';
			} );
			root.querySelectorAll( '[data-iftp-fb-add]' ).forEach( function ( add ) {
				add.disabled = items.length >= fbMax + 2;
			} );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		const meta = function ( item ) {
			const parts = [ types[ item.type ] || item.type ];

			if ( fbInputs.indexOf( item.type ) !== -1 ) {
				parts.push( item.required ? fb.required : fb.optional );
			}

			if ( item.width === 'half' ) {
				parts.push( fb.half );
			}

			if ( item.when && item.when.field && item.when.values.length ) {
				parts.push( fb.conditional );
			}

			return parts.join( ' · ' );
		};

		const control = function ( labelText, node, wide ) {
			const field = el( 'label', 'iftp-btn-field' + ( wide ? ' iftp-btn-fb__wide' : '' ) );

			field.appendChild( el( 'span', 'iftp-btn-field__label', labelText ) );
			field.appendChild( node );

			return field;
		};

		const textInput = function ( value, key, placeholder ) {
			const node = el( 'input', 'iftp-btn-field__control' );

			node.type = 'text';
			node.value = value || '';
			node.dataset.iftpFbKey = key;

			if ( placeholder ) {
				node.placeholder = placeholder;
			}

			return node;
		};

		// "Show this only when an earlier answer is (or isn't) one of these", e.g. a workshop day.
		const conditionBox = function ( item ) {
			const box = el( 'div', 'iftp-btn-fb__when' );

			box.appendChild( el( 'span', 'iftp-btn-field__label', fb.showWhen ) );

			// The field the spots are split by has to be answered, so it can't be hidden.
			if ( form.iftpSpotsField && form.iftpSpotsField() === item.id ) {
				box.appendChild( el( 'p', 'iftp-btn-field__help', fb.whenSpots ) );

				return box;
			}

			const when = item.when && item.when.field ? item.when : noCondition();
			const controllers = items.slice( 0, items.indexOf( item ) ).filter( function ( other ) {
				return fbConditions.indexOf( other.type ) !== -1;
			} );

			if ( ! controllers.length ) {
				box.appendChild( el( 'p', 'iftp-btn-field__help', fb.whenNone ) );

				return box;
			}

			const line = el( 'div', 'iftp-btn-fb__when-line' );
			const pick = el( 'select', 'iftp-btn-field__control' );
			const always = el( 'option', '', fb.always );

			always.value = '';
			pick.appendChild( always );
			pick.dataset.iftpFbKey = 'when-field';
			pick.setAttribute( 'aria-label', fb.showWhen );

			controllers.forEach( function ( controller ) {
				const option = el( 'option', '', ( fb.whenField || '%s' ).replace( '%s', controller.label || types[ controller.type ] ) );

				option.value = controller.id;
				option.selected = controller.id === when.field;
				pick.appendChild( option );
			} );

			line.appendChild( pick );

			const controller = controllers.filter( function ( other ) {
				return other.id === when.field;
			} )[ 0 ];

			if ( controller ) {
				const ops = el( 'span', 'iftp-btn-seg' );

				[ [ 'is', fb.whenIs ], [ 'not', fb.whenNot ] ].forEach( function ( pair ) {
					const option = el( 'label', 'iftp-btn-seg__option' );
					const radio = el( 'input' );

					radio.type = 'radio';
					radio.name = 'iftp-fb-when-' + item.id;
					radio.value = pair[ 0 ];
					radio.checked = when.op === pair[ 0 ];
					radio.dataset.iftpFbKey = 'when-op';
					option.appendChild( radio );
					option.appendChild( el( 'span', '', pair[ 1 ] ) );
					ops.appendChild( option );
				} );

				line.appendChild( ops );
			}

			box.appendChild( line );

			if ( controller ) {
				const values = el( 'div', 'iftp-btn-fb__when-values' );
				const choices = controller.type === 'checkbox' ? [ [ '1', fb.ticked ] ] : ( controller.options || [] ).map( function ( option ) {
					return [ option, option ];
				} );

				choices.forEach( function ( pair ) {
					const label = el( 'label', 'iftp-btn-check' );
					const tick = el( 'input' );

					tick.type = 'checkbox';
					tick.value = pair[ 0 ];
					tick.checked = when.values.indexOf( pair[ 0 ] ) !== -1;
					tick.dataset.iftpFbKey = 'when-value';
					label.appendChild( tick );
					label.appendChild( document.createTextNode( ' ' + pair[ 1 ] ) );
					values.appendChild( label );
				} );

				box.appendChild( values );

				if ( ! when.values.length ) {
					box.appendChild( el( 'p', 'iftp-btn-field__help', fb.whenPick ) );
				}
			}

			return box;
		};

		const panel = function ( item ) {
			const box = el( 'div', 'iftp-btn-fb__panel' );
			const grid = el( 'div', 'iftp-btn-fb__grid' );
			const isContent = item.type === 'content';

			grid.appendChild( control( isContent ? fb.heading : fb.label, textInput( item.label, 'label' ), false ) );

			if ( ! isContent ) {
				const typeSelect = el( 'select', 'iftp-btn-field__control' );

				fbInputs.forEach( function ( type ) {
					const option = el( 'option', '', types[ type ] );

					option.value = type;
					option.selected = type === item.type;
					typeSelect.appendChild( option );
				} );

				typeSelect.dataset.iftpFbKey = 'type';
				grid.appendChild( control( fb.type, typeSelect, false ) );

				const tag = el( 'span', 'iftp-btn-fb__tag' );
				const idInput = textInput( item.id, 'id' );

				idInput.maxLength = 40;
				idInput.spellcheck = false;
				tag.appendChild( el( 'span', 'iftp-btn-fb__tag-brace', '{field:' ) );
				tag.appendChild( idInput );
				tag.appendChild( el( 'span', 'iftp-btn-fb__tag-brace', '}' ) );
				grid.appendChild( control( fb.tag, tag, false ) );
			}

			if ( fbPlaceholder.indexOf( item.type ) !== -1 ) {
				grid.appendChild( control( item.type === 'select' ? fb.firstOption : fb.placeholder, textInput( item.placeholder, 'placeholder' ), false ) );
			}

			if ( fbChoices.indexOf( item.type ) !== -1 ) {
				const options = el( 'textarea', 'iftp-btn-field__control' );

				options.rows = 4;
				options.value = ( item.options || [] ).join( '\n' );
				options.placeholder = fb.optionsHint;
				options.dataset.iftpFbKey = 'options';
				grid.appendChild( control( fb.options, options, true ) );
			}

			const help = el( 'textarea', 'iftp-btn-field__control' );

			help.rows = isContent ? 3 : 2;
			help.value = item.help || '';
			help.dataset.iftpFbKey = 'help';
			grid.appendChild( control( isContent ? fb.text : fb.help, help, true ) );
			box.appendChild( grid );

			if ( fbInputs.indexOf( item.type ) !== -1 ) {
				const row = el( 'div', 'iftp-btn-fb__switches' );
				const required = el( 'label', 'iftp-btn-check' );
				const tick = el( 'input' );
				const width = el( 'span', 'iftp-btn-seg' );

				tick.type = 'checkbox';
				tick.checked = !! item.required;
				tick.dataset.iftpFbKey = 'required';

				// The field the spots are split by has to be answered, so its tick stays on.
				if ( form.iftpSpotsField && form.iftpSpotsField() === item.id ) {
					tick.checked = true;
					tick.disabled = true;
					required.title = fb.spotsRequired || '';
				}
				required.appendChild( tick );
				required.appendChild( document.createTextNode( ' ' + fb.mustAnswer ) );
				row.appendChild( required );

				[ [ 'full', fb.full ], [ 'half', fb.halfWidth ] ].forEach( function ( pair ) {
					const option = el( 'label', 'iftp-btn-seg__option' );
					const radio = el( 'input' );

					radio.type = 'radio';
					radio.name = 'iftp-fb-width-' + item.id;
					radio.value = pair[ 0 ];
					radio.checked = ( item.width || 'full' ) === pair[ 0 ];
					radio.dataset.iftpFbKey = 'width';
					option.appendChild( radio );
					option.appendChild( el( 'span', '', pair[ 1 ] ) );
					width.appendChild( option );
				} );

				row.appendChild( width );
				box.appendChild( row );
			}

			box.appendChild( conditionBox( item ) );

			return box;
		};

		const toolButton = function ( action, label, icon, disabled ) {
			const button = el( 'button', 'iftp-btn-tool' + ( action === 'remove' ? ' iftp-btn-tool--danger' : '' ) );

			button.type = 'button';
			button.dataset.iftpFbAction = action;
			button.setAttribute( 'aria-label', label );
			button.dataset.iftpTip = label;
			button.disabled = !! disabled;
			button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + icon + '</svg>';

			return button;
		};

		const render = function ( focus ) {
			let page = 1;

			list.textContent = '';

			items.forEach( function ( item, index ) {
				const li = el( 'li', 'iftp-btn-fb__item iftp-btn-fb__item--' + item.type );
				const row = el( 'div', 'iftp-btn-fb__row' );
				const icon = el( 'span', 'iftp-btn-fb__icon' );
				const tools = el( 'span', 'iftp-btn-fb__tools' );
				const state = flag( item );

				li.dataset.index = String( index );
				icon.innerHTML = fbIcon( item.type );
				row.appendChild( icon );

				if ( item.type === 'page' ) {
					page++;
					li.classList.add( 'iftp-btn-fb__item--break' );
					row.appendChild( el( 'span', 'iftp-btn-fb__page', ( fb.page || '%d' ).replace( '%d', page ) ) );

					const title = textInput( item.label, 'label', fb.pageTitle );

					title.className = 'iftp-btn-field__control iftp-btn-fb__page-title';
					title.setAttribute( 'aria-label', fb.pageTitle );
					row.appendChild( title );
				} else if ( isAnchor( item ) ) {
					li.classList.add( 'iftp-btn-fb__item--anchor' );

					const name = el( 'span', 'iftp-btn-fb__name' );

					name.appendChild( el( 'strong', '', item.type === 'amount' ? fb.amount : fb.details ) );
					name.appendChild( el( 'span', 'iftp-btn-fb__meta', item.type === 'amount' ? fb.amountHint : fb.detailsHint ) );
					row.appendChild( name );
				} else {
					const name = el( 'button', 'iftp-btn-fb__name' );

					name.type = 'button';
					name.dataset.iftpFbAction = 'toggle';
					name.setAttribute( 'aria-expanded', state.open ? 'true' : 'false' );
					name.appendChild( el( 'strong', 'iftp-btn-fb__label', item.label || types[ item.type ] ) );
					name.appendChild( el( 'span', 'iftp-btn-fb__meta', meta( item ) ) );
					row.appendChild( name );
					li.classList.toggle( 'is-open', state.open );
				}

				tools.appendChild( toolButton( 'up', fb.up, '<path d="m6 15 6-6 6 6"/>', index === 0 ) );
				tools.appendChild( toolButton( 'down', fb.down, '<path d="m6 9 6 6 6-6"/>', index === items.length - 1 ) );

				if ( ! isAnchor( item ) ) {
					tools.appendChild( toolButton( 'remove', fb.remove, '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>' ) );
				}

				row.appendChild( tools );
				li.appendChild( row );

				if ( state.open && item.type !== 'page' && ! isAnchor( item ) ) {
					li.appendChild( panel( item ) );
				}

				list.appendChild( li );
			} );

			if ( focus ) {
				const target = list.querySelector( focus );

				if ( target ) {
					target.focus();
				}
			}
		};

		root.querySelectorAll( '[data-iftp-fb-add]' ).forEach( function ( add ) {
			add.addEventListener( 'click', function () {
				const type = add.dataset.iftpFbAdd;
				const label = type === 'page' ? '' : types[ type ];
				const item = { id: '', type: type, label: label, required: false, placeholder: '', help: '', options: [], width: 'full', when: noCondition() };

				if ( fbChoices.indexOf( type ) !== -1 ) {
					item.options = [ fb.option + ' 1', fb.option + ' 2' ];
				}

				if ( type !== 'page' ) {
					item.id = uniqueId( slug( label ), item );
				}

				items.push( item );
				flag( item ).open = type !== 'page';
				flag( item ).fresh = true;
				render( '[data-index="' + ( items.length - 1 ) + '"] ' + ( type === 'page' ? '.iftp-btn-fb__page-title' : '[data-iftp-fb-key="label"]' ) );
				save();

				const added = list.querySelector( '[data-index="' + ( items.length - 1 ) + '"]' );

				if ( added && added.scrollIntoView ) {
					added.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
				}
			} );
		} );

		list.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '[data-iftp-fb-action]' );

			if ( ! button ) {
				return;
			}

			const index = parseInt( button.closest( '[data-index]' ).dataset.index, 10 );
			const action = button.dataset.iftpFbAction;
			const item = items[ index ];

			if ( action === 'toggle' ) {
				flag( item ).open = ! flag( item ).open;
				render( '[data-index="' + index + '"] [data-iftp-fb-action="toggle"]' );

				return;
			}

			if ( action === 'remove' ) {
				items.splice( index, 1 );
				render( '[data-index="' + Math.min( index, items.length - 1 ) + '"] [data-iftp-fb-action="up"]' );
				save();

				return;
			}

			const to = action === 'up' ? index - 1 : index + 1;

			if ( to < 0 || to >= items.length ) {
				return;
			}

			items.splice( to, 0, items.splice( index, 1 )[ 0 ] );
			render( '[data-index="' + to + '"] [data-iftp-fb-action="' + action + '"]' );
			save();
		} );

		const update = function ( event ) {
			const key = event.target.dataset.iftpFbKey;
			const holder = event.target.closest( '[data-index]' );

			if ( ! key || ! holder ) {
				return;
			}

			const item = items[ parseInt( holder.dataset.index, 10 ) ];
			const state = flag( item );
			const target = event.target;
			const before = item.id;

			if ( key === 'when-field' || key === 'when-op' || key === 'when-value' ) {
				if ( event.type !== 'change' ) {
					return;
				}

				if ( key === 'when-field' ) {
					item.when = target.value ? { field: target.value, op: 'is', values: [] } : noCondition();
				} else if ( key === 'when-op' ) {
					item.when.op = target.value;
				} else {
					item.when.values = Array.prototype.map.call( holder.querySelectorAll( '[data-iftp-fb-key="when-value"]:checked' ), function ( tick ) {
						return tick.value;
					} );
				}

				save();
				render( '[data-index="' + holder.dataset.index + '"] [data-iftp-fb-key="' + key + '"]' + ( key === 'when-field' ? '' : '[value="' + CSS.escape( target.value ) + '"]' ) );

				return;
			}

			if ( key === 'required' ) {
				item.required = target.checked;
			} else if ( key === 'width' ) {
				item.width = target.value;
			} else if ( key === 'options' ) {
				item.options = target.value.split( '\n' ).map( function ( line ) {
					return line.trim();
				} ).filter( Boolean );
			} else if ( key === 'id' ) {
				state.fresh = false;
				item.id = slug( target.value ) || item.id;

				if ( event.type === 'change' ) {
					item.id = uniqueId( item.id, item );
					target.value = item.id;
				}
			} else if ( key === 'type' ) {
				item.type = target.value;

				if ( fbChoices.indexOf( item.type ) !== -1 && ! ( item.options || [] ).length ) {
					item.options = [ fb.option + ' 1', fb.option + ' 2' ];
				}

				render( '[data-index="' + holder.dataset.index + '"] [data-iftp-fb-key="type"]' );
			} else {
				item[ key ] = target.value;
			}

			// A new field's tag follows its label until someone names the tag.
			if ( key === 'label' && state.fresh && item.type !== 'page' && item.type !== 'content' ) {
				item.id = uniqueId( slug( item.label ) || 'field', item );

				const idInput = holder.querySelector( '[data-iftp-fb-key="id"]' );

				if ( idInput ) {
					idInput.value = item.id;
				}
			}

			// Conditions follow a field when its tag changes.
			if ( item.id !== before ) {
				items.forEach( function ( other ) {
					if ( other.when && other.when.field === before ) {
						other.when.field = item.id;
					}
				} );
			}

			const label = holder.querySelector( '.iftp-btn-fb__label' );
			const metaLine = holder.querySelector( '.iftp-btn-fb__name .iftp-btn-fb__meta' );

			if ( label ) {
				label.textContent = item.label || types[ item.type ];
			}

			if ( metaLine && ! isAnchor( item ) ) {
				metaLine.textContent = meta( item );
			}

			save();
		};

		list.addEventListener( 'input', update );
		list.addEventListener( 'change', update );

		// The Spots card makes its field required from outside.
		form.iftpFormRefresh = function () {
			render();
			save();
		};

		render();
		save();
	}

	/* ---------------------------------------------------------------- Spots */

	function bindSpots( form ) {
		const root = form.querySelector( '[data-iftp-spots]' );

		if ( ! root ) {
			return;
		}

		const select = root.querySelector( '[data-iftp-spots-field]' );
		const rows = root.querySelector( '[data-iftp-spots-rows]' );
		const grid = root.querySelector( '[data-iftp-spots-grid]' );
		const empty = root.querySelector( '[data-iftp-spots-empty]' );
		const sum = root.querySelector( '[data-iftp-spots-sum]' );
		const total = form.querySelector( '#iftp-btn-spots-total' );
		const amount = form.querySelector( '#iftp-btn-amount' );
		const sp = i18n.spots || {};
		const builder = form.querySelector( '[data-iftp-fb]' );
		let typeNames = {};

		try {
			typeNames = JSON.parse( ( builder && builder.dataset.types ) || '{}' );
		} catch ( e ) {}

		// Each option's numbers by its label, so they survive the options being reordered or the rows redrawn.
		const values = {};
		let wanted = select.dataset.saved || '';

		try {
			JSON.parse( root.dataset.saved || '[]' ).forEach( function ( option ) {
				values[ option.label ] = { spots: option.spots ? String( option.spots ) : '', price: option.price ? Number( option.price ).toFixed( 2 ) : '' };
			} );
		} catch ( e ) {}

		const spotsMode = function () {
			const checked = form.querySelector( 'input[name="config[spots][mode]"]:checked' );

			return checked ? checked.value : 'none';
		};

		const fixed = function () {
			const checked = form.querySelector( 'input[name="config[amount_mode]"]:checked' );

			return ! checked || checked.value === 'fixed';
		};

		const choiceFields = function () {
			return ( form.iftpFormState || [] ).filter( function ( item ) {
				return [ 'select', 'radio', 'checkboxes' ].indexOf( item.type ) !== -1 && ( item.options || [] ).length;
			} );
		};

		const current = function () {
			return choiceFields().filter( function ( item ) {
				return item.id === select.value;
			} )[ 0 ];
		};

		form.iftpSpotsField = function () {
			return spotsMode() === 'options' && current() ? select.value : '';
		};

		// The unit price of every option, for "from …" on the button preview.
		form.iftpSpotPrices = function () {
			if ( spotsMode() !== 'options' || ! current() ) {
				return [];
			}

			return current().options.map( function ( option ) {
				const own = parseFloat( ( values[ option ] || {} ).price );

				return own > 0 ? own : parseFloat( amount ? amount.value : '0' ) || 0;
			} );
		};

		const describe = function () {
			const options = current() ? current().options : [];
			const counts = options.map( function ( option ) {
				return parseInt( ( values[ option ] || {} ).spots, 10 ) || 0;
			} );
			const open = counts.indexOf( 0 ) !== -1;
			const added = counts.reduce( function ( a, b ) {
				return a + b;
			}, 0 );

			if ( ! options.length ) {
				sum.textContent = '';
			} else if ( ! open ) {
				sum.textContent = ( sp.sum || '%d' ).replace( '%d', added );
			} else {
				sum.textContent = total && parseInt( total.value, 10 ) > 0 ? sp.shareTotal : sp.noLimit;
			}
		};

		const drawRows = function () {
			const item = current();
			const priced = fixed();

			rows.textContent = '';
			root.querySelectorAll( '[data-iftp-spots-price]' ).forEach( function ( cell ) {
				cell.hidden = ! priced;
			} );

			( item ? item.options : [] ).forEach( function ( option, index ) {
				const saved = values[ option ] || { spots: '', price: '' };
				const row = el( 'div', 'iftp-btn-spots__row' );
				const name = el( 'span', 'iftp-btn-spots__option', option );
				const label = el( 'input' );
				const base = 'config[spots][options][' + index + ']';

				label.type = 'hidden';
				label.name = base + '[label]';
				label.value = option;
				name.appendChild( label );
				row.appendChild( name );

				[ [ 'spots', sp.unlimited, '1', '0' ], [ 'price', sp.samePrice, '0.01', '0' ] ].forEach( function ( spec ) {
					const cell = el( 'span' );
					const input = el( 'input', 'iftp-btn-field__control iftp-btn-field__control--short' );

					input.type = 'number';
					input.name = base + '[' + spec[ 0 ] + ']';
					input.value = saved[ spec[ 0 ] ];
					input.placeholder = spec[ 1 ];
					input.step = spec[ 2 ];
					input.min = spec[ 3 ];
					input.inputMode = spec[ 0 ] === 'price' ? 'decimal' : 'numeric';
					input.dataset.iftpSpotsKey = spec[ 0 ];
					input.dataset.iftpSpotsOption = option;
					input.setAttribute( 'aria-label', option + ' · ' + ( spec[ 0 ] === 'price' ? sp.price : sp.spots ) );

					if ( spec[ 0 ] === 'price' ) {
						cell.dataset.iftpSpotsPrice = '';
						cell.hidden = ! priced;
					}

					cell.appendChild( input );
					row.appendChild( cell );
				} );

				rows.appendChild( row );
			} );

			describe();
		};

		// A half-set-up card mustn't save as if it were fine; the browser shows these on the field, on its tab.
		const validate = function () {
			const mode = spotsMode();

			select.required = mode === 'options';
			select.setCustomValidity( mode === 'options' && ! current() ? sp.needField || '' : '' );

			if ( total ) {
				total.setCustomValidity( mode === 'total' && ! ( parseInt( total.value, 10 ) > 0 ) ? sp.needTotal || '' : '' );
			}
		};

		const refresh = function () {
			const fields = choiceFields();
			const keep = select.value || wanted;

			select.textContent = '';

			if ( ! fields.length ) {
				const none = el( 'option', '', sp.noField );

				none.value = '';
				select.appendChild( none );
			}

			fields.forEach( function ( item ) {
				const name = item.label || item.id;
				const type = typeNames[ item.type ] || item.type;
				const option = el( 'option', '', name === type ? name : name + ' · ' + type );

				option.value = item.id;
				select.appendChild( option );
			} );

			if ( fields.some( function ( item ) {
				return item.id === keep;
			} ) ) {
				select.value = keep;
			}

			wanted = select.value || wanted;
			grid.hidden = ! fields.length;
			empty.hidden = !! fields.length;
			drawRows();
			validate();

			// The picked field turns required in the builder too, so the editor shows what the payer will get.
			const item = current();

			if ( spotsMode() === 'options' && item && ! item.required && form.iftpFormRefresh ) {
				item.required = true;
				form.iftpFormRefresh();
			}
		};

		rows.addEventListener( 'input', function ( event ) {
			const key = event.target.dataset.iftpSpotsKey;
			const option = event.target.dataset.iftpSpotsOption;

			if ( key ) {
				values[ option ] = values[ option ] || { spots: '', price: '' };
				values[ option ][ key ] = event.target.value;
				describe();
			}
		} );

		select.addEventListener( 'change', function () {
			wanted = select.value;
			refresh();
		} );

		// The form builder fires change on its JSON input whenever a field or its options change.
		form.querySelector( '[data-iftp-fb-input]' ).addEventListener( 'change', refresh );

		form.querySelectorAll( 'input[name="config[spots][mode]"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				refresh();

				// Leaving "Spots per option" gives the field back its own tick.
				if ( form.iftpFormRefresh ) {
					form.iftpFormRefresh();
				}
			} );
		} );

		form.querySelectorAll( 'input[name="config[amount_mode]"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', drawRows );
		} );

		if ( total ) {
			total.addEventListener( 'input', function () {
				describe();
				validate();
			} );
		}

		refresh();
	}

	// The checkout mock in the editor's preview: the first page of the form, and the step bar if there are more.
	function renderMock( form, amount ) {
		const items = form.iftpFormState || [];
		const step = form.querySelector( '[data-iftp-preview-step]' );
		const fb = i18n.fb || {};
		const titles = [ ( form.querySelector( '[data-iftp-preview="first_step"]' ) || {} ).value || fb.firstStep ];
		const firstItems = [];

		items.forEach( function ( item ) {
			if ( item.type === 'page' ) {
				titles.push( item.label || fb.pageTitle );
			} else if ( titles.length === 1 ) {
				firstItems.push( item );
			}
		} );

		step.querySelectorAll( '[data-iftp-preview-item]' ).forEach( function ( node ) {
			node.remove();
		} );

		// The mock has its first Single choice option picked and nothing else, so it shows what that would.
		const mockAnswers = {};

		items.forEach( function ( item ) {
			mockAnswers[ item.id ] = item.type === 'radio' && ( item.options || [] ).length ? [ item.options[ 0 ] ] : [];
		} );

		const mockShown = visibleItems( items, mockAnswers );

		const field = function ( labelText, required, half ) {
			const node = el( 'div', 'iftp-btn-form__field' + ( half ? ' iftp-btn-form__field--half' : '' ) );
			const label = el( 'span', 'iftp-btn-form__label', labelText );

			if ( ! required ) {
				label.appendChild( el( 'span', 'iftp-btn-form__optional', ' ' + ( fb.optionalWord || '' ) ) );
			}

			node.dataset.iftpPreviewItem = '';
			node.appendChild( label );

			return node;
		};

		const fake = function ( text, tall ) {
			const node = el( 'span', 'iftp-btn-form__input iftp-btn-form__input--fake' + ( tall ? ' iftp-btn-form__input--tall' : '' ), text || '' );

			return node;
		};

		const tick = function ( type, checked ) {
			const node = el( 'input', 'iftp-btn-form__tick' );

			node.type = type;
			node.checked = !! checked;
			node.tabIndex = -1;
			node.disabled = true;

			return node;
		};

		firstItems.forEach( function ( item ) {
			let node = null;

			if ( mockShown[ item.id ] === false ) {
				return;
			}

			if ( item.type === 'amount' ) {
				if ( amount.mode !== 'payer' ) {
					return;
				}

				node = field( fb.amount, true, false );

				if ( amount.presets.length ) {
					const chips = el( 'div', 'iftp-btn-form__presets' );

					amount.presets.forEach( function ( preset ) {
						chips.appendChild( el( 'span', 'iftp-btn-form__preset', preset ) );
					} );

					node.appendChild( chips );
				}

				node.appendChild( fake( '' ) );
				node.appendChild( el( 'span', 'iftp-btn-form__help', amount.range ) );
			} else if ( item.type === 'details' ) {
				form.querySelectorAll( '[data-iftp-field]:checked' ).forEach( function ( radio ) {
					if ( radio.value === 'off' ) {
						return;
					}

					const row = radio.closest( '.iftp-btn-fields-list__row' );
					const custom = row.querySelector( 'input[type="text"]' );
					const name = custom ? custom.value || custom.placeholder : row.querySelector( '.iftp-btn-fields-list__name' ).textContent.trim();
					const detail = field( name, radio.value === 'required', false );

					detail.appendChild( fake( '' ) );
					step.appendChild( detail );
				} );

				return;
			} else if ( item.type === 'content' ) {
				node = el( 'div', 'iftp-btn-form__content' );
				node.dataset.iftpPreviewItem = '';
				node.appendChild( el( 'p', 'iftp-btn-form__heading', item.label ) );

				if ( item.help ) {
					node.appendChild( el( 'p', 'iftp-btn-form__help', item.help ) );
				}
			} else if ( item.type === 'checkbox' ) {
				node = el( 'label', 'iftp-btn-form__agree' );
				node.dataset.iftpPreviewItem = '';
				node.appendChild( tick( 'checkbox', false ) );
				node.appendChild( el( 'span', '', item.label ) );
			} else if ( item.type === 'radio' || item.type === 'checkboxes' ) {
				node = field( item.label, item.required, item.width === 'half' );

				const choices = el( 'div', 'iftp-btn-form__choices' );

				( item.options || [] ).slice( 0, 4 ).forEach( function ( option, index ) {
					const choice = el( 'span', 'iftp-btn-form__choice' );

					choice.appendChild( tick( item.type === 'radio' ? 'radio' : 'checkbox', index === 0 && item.type === 'radio' ) );
					choice.appendChild( el( 'span', '', option ) );
					choices.appendChild( choice );
				} );

				node.appendChild( choices );
			} else {
				node = field( item.label, item.required, item.width === 'half' );

				if ( item.type === 'select' ) {
					const wrap = el( 'span', 'iftp-btn-form__select' );

					wrap.appendChild( fake( item.placeholder || fb.choose ) );
					node.appendChild( wrap );
				} else {
					node.appendChild( fake( item.placeholder, item.type === 'textarea' ) );
				}
			}

			if ( node ) {
				step.appendChild( node );
			}
		} );

		const bar = form.querySelector( '[data-iftp-preview-steps]' );
		const track = form.querySelector( '[data-iftp-preview-track]' );
		const submit = form.querySelector( '[data-iftp-preview-submit]' );

		bar.hidden = titles.length < 2;
		track.textContent = '';
		track.style.setProperty( '--iftp-steps', String( titles.length ) );
		bar.style.setProperty( '--iftp-steps', String( titles.length ) );

		// The same markup as StepBar::render(), on the first page, a third answered.
		titles.forEach( function ( title, index ) {
			const segment = el( 'li', 'iftp-btn-steps__bar' + ( index === 0 ? ' is-current' : '' ) );
			const seg = el( 'span', 'iftp-btn-steps__seg' );

			segment.style.setProperty( '--iftp-i', String( index ) );

			if ( index === 0 ) {
				segment.style.setProperty( '--iftp-fill', '0.35' );
			}

			seg.appendChild( el( 'span', 'iftp-btn-steps__fill' ) );
			segment.appendChild( seg );
			segment.appendChild( el( 'span', 'iftp-btn-steps__num', String( index + 1 ) ) );
			segment.appendChild( el( 'span', 'iftp-btn-steps__label', title ) );
			track.appendChild( segment );
		} );

		form.querySelector( '[data-iftp-preview-step-pct]' ).textContent = Math.round( 0.35 / titles.length * 100 ) + '%';

		form.querySelector( '[data-iftp-preview-step-title]' ).textContent = titles[ 0 ];
		form.querySelector( '[data-iftp-preview-step-count]' ).textContent = ( fb.stepOf || '{step} / {steps}' ).replace( '{step}', '1' ).replace( '{steps}', String( titles.length ) );
		submit.textContent = titles.length > 1 ? submit.dataset.next : submit.dataset.pay;
	}

	/* ---------------------------------------------------------------- Unsaved changes */

	function bindDirty( form ) {
		const flag = form.querySelector( '[data-iftp-dirty]' );
		// A version the AI built is unsaved from the start.
		let dirty = form.hasAttribute( 'data-iftp-start-dirty' );

		if ( dirty && flag ) {
			flag.hidden = false;
		}

		const mark = function ( event ) {
			// Tabs, the stage background and the link builder don't change what's saved.
			if ( dirty || ! event.target.name || event.target.closest( '[data-iftp-builder]' ) ) {
				return;
			}

			dirty = true;

			if ( flag ) {
				flag.hidden = false;
			}
		};

		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );

		form.addEventListener( 'submit', function () {
			leaving = true;
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty && ! leaving ) {
				event.preventDefault();
				event.returnValue = i18n.unsaved || '';
			}
		} );
	}

	/* ---------------------------------------------------------------- Editor */

	const editor = document.querySelector( '[data-iftp-editor]' );

	if ( editor ) {
		bindReveal( editor, 'input[name="config[amount_mode]"]', 'data-iftp-when-mode' );
		bindReveal( editor, '[data-iftp-paid-type]', 'data-iftp-when-paid' );
		bindReveal( editor, 'input[name="config[design][source]"]', 'data-iftp-when-source' );
		bindReveal( editor, 'input[name="config[design][variant]"]', 'data-iftp-when-variant' );
		bindReveal( editor, 'input[name="config[spots][mode]"]', 'data-iftp-when-spots' );
		bindReveal( editor, 'input[name="config[spots][quantity]"]', 'data-iftp-when-quantity' );
		bindReveal( editor, 'input[name="config[spots][show_left]"]', 'data-iftp-when-left' );
		bindReveal( editor, 'input[name="config[invoice_hook][enabled]"]', 'data-iftp-when-invoice' );
		bindTabs( editor );
		bindDesignControls( editor );
		bindFormBuilder( editor );
		bindSpots( editor );
		bindLook( editor );
		bindDirty( editor );

		const value = function ( name ) {
			const input = editor.querySelector( '[data-iftp-preview="' + name + '"]' );

			if ( ! input ) {
				return '';
			}

			return input.type === 'checkbox' ? input.checked : input.value;
		};

		const mode = function () {
			const checked = editor.querySelector( 'input[name="config[amount_mode]"]:checked' );

			return checked ? checked.value : 'fixed';
		};

		const renderPreview = function () {
			const current = mode();
			const fixed = current === 'fixed';
			let label = value( 'label' ) || i18n.payNow;

			if ( fixed && value( 'show_amount' ) && value( 'amount' ) ) {
				const prices = editor.iftpSpotPrices ? editor.iftpSpotPrices() : [];
				const low = prices.length ? Math.min.apply( null, prices ) : 0;
				const price = prices.length && low < Math.max.apply( null, prices ) ? ( i18n.from || '{price}' ).replace( '{price}', money( low ) ) : money( prices.length ? low : value( 'amount' ) );

				label = ( i18n.withAmount || '%1$s · %2$s' ).replace( '%1$s', label ).replace( '%2$s', price );
			}

			renderButton( editor, label );
			editor.querySelector( '[data-iftp-preview-title]' ).textContent = value( 'title' ) || '…';

			const summary = editor.querySelector( '[data-iftp-preview-summary]' );

			summary.textContent = value( 'summary' );
			summary.hidden = ! value( 'summary' );

			editor.querySelector( '[data-iftp-preview-amount]' ).textContent = fixed ? money( value( 'amount' ) ) : '';

			renderMock( editor, {
				mode: current,
				range: ( i18n.between || '' ).replace( '{min}', money( value( 'min' ) ) ).replace( '{max}', money( value( 'max' ) ) ),
				presets: String( value( 'presets' ) )
					.split( ',' )
					.map( function ( part ) {
						return part.trim();
					} )
					.filter( function ( part ) {
						return part && isFinite( parseFloat( part ) );
					} )
					.slice( 0, 6 )
					.map( money ),
			} );

			const methods = editor.querySelector( '[data-iftp-preview-methods]' );
			const picks = editor.querySelectorAll( '[data-iftp-preview="method"]' );
			const picked = editor.querySelectorAll( '[data-iftp-preview="method"]:checked' );

			const strip = editor.querySelector( '[data-iftp-preview-logos]' );
			const logosOn = !! editor.querySelector( 'input[name="config[design][logos]"][type="checkbox"]:checked' );

			methods.textContent = '';
			strip.textContent = '';

			( picked.length ? picked : picks ).forEach( function ( pick ) {
				const logo = pick.parentElement.querySelector( '[data-iftp-method-logo]' );

				if ( logo ) {
					const item = document.createElement( 'li' );
					const image = document.createElement( 'img' );

					// The light logo, even if the picker's was already swapped; method-logos.js picks for the preview's own background.
					image.src = logo.getAttribute( 'data-logo-light' ) || logo.getAttribute( 'src' );
					image.alt = '';

					if ( logo.hasAttribute( 'data-logo-dark' ) ) {
						image.setAttribute( 'data-logo-dark', logo.getAttribute( 'data-logo-dark' ) );
					}
					item.appendChild( image );
					methods.appendChild( item );
					strip.appendChild( image.cloneNode() );
				}
			} );

			strip.hidden = ! logosOn || ! strip.children.length;

			updateBuilder();
		};

		/* Link builder: the redirect link a form plugin is given, with its own merge tags left as they are. */
		const builder = editor.querySelector( '[data-iftp-builder]' );

		function updateBuilder() {
			if ( ! builder ) {
				return;
			}

			const output = document.getElementById( 'iftp-btn-builder-out' );
			const warning = builder.querySelector( '[data-iftp-builder-warning]' );
			const parts = [];
			let wantsAmount = false;

			builder.querySelectorAll( '[data-iftp-builder-use]' ).forEach( function ( use ) {
				const param = use.dataset.iftpBuilderUse;
				const tag = builder.querySelector( '[data-iftp-builder-tag="' + param + '"]' );

				if ( use.checked && tag && tag.value.trim() ) {
					// The tag stays raw: encoding its braces would stop the other plugin from finding it.
					parts.push( param + '=' + tag.value.trim() );
					wantsAmount = wantsAmount || param === 'amount';
				}
			} );

			const base = builder.dataset.base;
			const mirror = document.getElementById( 'iftp-btn-amount-link-out' );

			output.value = parts.length ? base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + parts.join( '&' ) : base;
			warning.hidden = ! ( wantsAmount && mode() === 'fixed' );

			// The Amount card shows the same link under "From the link".
			if ( mirror ) {
				mirror.value = output.value;
			}
		}

		if ( builder ) {
			builder.addEventListener( 'input', updateBuilder );
			builder.addEventListener( 'change', updateBuilder );

			// Switching to "From the link" wants the amount in the link, even if Fixed had it unticked.
			editor.querySelectorAll( 'input[name="config[amount_mode]"]' ).forEach( function ( radio ) {
				radio.addEventListener( 'change', function () {
					const amount = builder.querySelector( '[data-iftp-builder-use="amount"]' );

					if ( radio.checked && radio.value === 'link' && amount ) {
						amount.checked = true;
						updateBuilder();
					}
				} );
			} );
		}

		editor.addEventListener( 'input', renderPreview );
		editor.addEventListener( 'change', renderPreview );
		renderPreview();

		/* The slug follows the title until someone types their own. */
		const title = editor.querySelector( '#iftp-btn-title' );
		const slug = editor.querySelector( '#iftp-btn-slug' );

		if ( title && slug && ! slug.value ) {
			slug.dataset.auto = '1';

			title.addEventListener( 'input', function () {
				if ( slug.dataset.auto ) {
					slug.placeholder = title.value
						.toLowerCase()
						.normalize( 'NFD' )
						.replace( /[̀-ͯ]/g, '' )
						.replace( /[^a-z0-9]+/g, '-' )
						.replace( /^-+|-+$/g, '' ) || slug.placeholder;
				}
			} );

			slug.addEventListener( 'input', function () {
				delete slug.dataset.auto;
			} );
		}

		/* Webhook test */
		editor.querySelectorAll( '[data-iftp-test-webhook]' ).forEach( function ( button ) {
			const result = editor.querySelector( '[data-iftp-test-result]' );
			const url = editor.querySelector( '#iftp-btn-webhook-url' );
			const saved = url ? url.value : '';
			const label = button.textContent;

			if ( url ) {
				// The test goes to the saved address, so it waits for a save after an edit.
				url.addEventListener( 'input', function () {
					button.disabled = ! url.value || url.value !== saved;
				} );
			}

			button.addEventListener( 'click', function () {
				button.disabled = true;
				button.textContent = i18n.sending;
				result.textContent = '';

				post( 'iftp_btn_test_webhook', { button: button.dataset.iftpTestWebhook } )
					.then( function ( response ) {
						result.textContent = ( response && response.data && response.data.message ) || i18n.error;
						result.classList.toggle( 'is-error', ! ( response && response.success ) );
					} )
					.catch( function () {
						result.textContent = i18n.error;
						result.classList.add( 'is-error' );
					} )
					.finally( function () {
						button.disabled = false;
						button.textContent = label;
					} );
			} );
		} );

		/* Invoicing app test: what the card holds now, saved or not */
		editor.querySelectorAll( '[data-iftp-test-invoice]' ).forEach( function ( button ) {
			const card = button.closest( '[data-iftp-invoice]' );
			const result = card.querySelector( '[data-iftp-invoice-result]' );
			const reply = card.querySelector( '[data-iftp-invoice-reply]' );
			const label = button.textContent;

			button.addEventListener( 'click', function () {
				const data = { button: button.dataset.iftpTestInvoice };

				card.querySelectorAll( '[data-iftp-invoice-key]' ).forEach( function ( input ) {
					data[ 'hook[' + input.dataset.iftpInvoiceKey + ']' ] = input.value;
				} );

				button.disabled = true;
				button.textContent = i18n.sending;
				result.textContent = '';
				reply.hidden = true;

				post( 'iftp_btn_test_invoice', data )
					.then( function ( response ) {
						const payload = ( response && response.data ) || {};

						result.textContent = payload.message || i18n.error;
						result.classList.toggle( 'is-error', ! ( response && response.success ) );
						// What the app said back, so a refusal explains itself.
						reply.textContent = payload.body || '';
						reply.hidden = ! payload.body;
					} )
					.catch( function () {
						result.textContent = i18n.error;
						result.classList.add( 'is-error' );
					} )
					.finally( function () {
						button.disabled = false;
						button.textContent = label;
					} );
			} );
		} );
	}

	/* ---------------------------------------------------------------- Checkout look preview */

	// Same mesh as CheckoutLook::crystals(): same seed, same steps, so the preview matches the site.
	function crystals( size, strength ) {
		const cells = 6;
		const tile = cells * size;
		const light = strength / 100;
		const points = [];
		let seed = 20261022;
		let shapes = '';

		const random = function () {
			seed = ( seed * 16807 ) % 2147483647;

			return seed / 2147483647;
		};

		for ( let i = 0; i < cells; i++ ) {
			points[ i ] = [];

			for ( let j = 0; j < cells; j++ ) {
				points[ i ][ j ] = [ i * size + ( random() - 0.5 ) * size * 0.8, j * size + ( random() - 0.5 ) * size * 0.8 ];
			}
		}

		const point = function ( i, j ) {
			const base = points[ i % cells ][ j % cells ];

			return [ base[ 0 ] + ( i >= cells ? tile : 0 ), base[ 1 ] + ( j >= cells ? tile : 0 ) ];
		};

		for ( let i = 0; i < cells; i++ ) {
			for ( let j = 0; j < cells; j++ ) {
				const a = point( i, j );
				const b = point( i + 1, j );
				const c = point( i + 1, j + 1 );
				const d = point( i, j + 1 );
				const triangles = random() < 0.5 ? [ [ a, b, c ], [ a, c, d ] ] : [ [ a, b, d ], [ b, c, d ] ];

				triangles.forEach( function ( triangle ) {
					const shade = random() * 2 - 1;
					const fill = shade > 0 ? '#fff' : '#000';
					const alpha = Math.round( Math.abs( shade ) * light * ( shade > 0 ? 0.55 : 0.25 ) * 1000 ) / 1000;

					tileOffsets( triangle, tile ).forEach( function ( offset ) {
						const path = triangle.map( function ( corner, at ) {
							return ( at === 0 ? 'M' : 'L' ) + Math.round( ( corner[ 0 ] + offset[ 0 ] ) * 10 ) / 10 + ' ' + Math.round( ( corner[ 1 ] + offset[ 1 ] ) * 10 ) / 10;
						} ).join( '' );

						shapes += "<path d='" + path + "Z' fill='" + fill + "' fill-opacity='" + alpha + "'/>";
					} );
				} );
			}
		}

		const svg = "<svg xmlns='http://www.w3.org/2000/svg' width='" + tile + "' height='" + tile + "' viewBox='0 0 " + tile + ' ' + tile + "'><g stroke='#fff' stroke-opacity='" + Math.round( light * 0.22 * 1000 ) / 1000 + "' stroke-width='1' stroke-linejoin='round'>" + shapes + '</g></svg>';

		return svgUrl( svg );
	}

	// Same as CheckoutLook::tile_offsets(): where else a shape is drawn so the tile repeats without seams.
	function tileOffsets( points, tile ) {
		const xs = points.map( function ( corner ) {
			return corner[ 0 ];
		} );
		const ys = points.map( function ( corner ) {
			return corner[ 1 ];
		} );
		const dx = [ 0 ];
		const dy = [ 0 ];
		const all = [];

		if ( Math.min.apply( null, xs ) < 0 ) {
			dx.push( tile );
		}

		if ( Math.max.apply( null, xs ) > tile ) {
			dx.push( -tile );
		}

		if ( Math.min.apply( null, ys ) < 0 ) {
			dy.push( tile );
		}

		if ( Math.max.apply( null, ys ) > tile ) {
			dy.push( -tile );
		}

		dx.forEach( function ( x ) {
			dy.forEach( function ( y ) {
				all.push( [ x, y ] );
			} );
		} );

		return all;
	}

	function svgUrl( svg ) {
		return 'url("data:image/svg+xml,' + svg.replace( /</g, '%3C' ).replace( />/g, '%3E' ).replace( /#/g, '%23' ) + '")';
	}

	// Same waves as CheckoutLook::waves(), step for step.
	function waves( size, strength ) {
		const tile = 6 * size;
		const lines = 12;
		const gap = tile / lines;
		const steps = 12;
		const light = strength / 100;
		const circle = 2 * Math.PI;
		let bands = '';
		let edges = '';

		const num = function ( value ) {
			return String( Math.round( value * 10 ) / 10 );
		};

		const x = function ( at ) {
			return at * tile / steps;
		};

		const y = function ( k, at ) {
			const along = at / steps;

			return ( k + 0.5 ) * gap + 1.2 * gap * ( Math.sin( circle * along + 0.6 + 0.9 * Math.sin( circle * k / lines ) ) + 0.4 * Math.sin( 2 * circle * along + 2.1 + 0.7 * Math.cos( circle * k / lines ) ) );
		};

		const curve = function ( k, back, dy ) {
			let path = '';

			for ( let n = 0; n < steps; n++ ) {
				const from = back ? steps - n : n;
				const to = back ? from - 1 : from + 1;
				const prev = back ? from + 1 : from - 1;
				const next = back ? to - 1 : to + 1;

				path += 'C' + num( x( from ) + ( x( to ) - x( prev ) ) / 6 ) + ' ' + num( y( k, from ) + ( y( k, to ) - y( k, prev ) ) / 6 + dy ) +
					' ' + num( x( to ) - ( x( next ) - x( from ) ) / 6 ) + ' ' + num( y( k, to ) - ( y( k, next ) - y( k, from ) ) / 6 + dy ) +
					' ' + num( x( to ) ) + ' ' + num( y( k, to ) + dy );
			}

			return path;
		};

		const shifts = function ( top, low ) {
			const tops = [];
			const lows = [];
			const shift = [ 0 ];

			for ( let at = 0; at <= steps; at++ ) {
				tops.push( y( top, at ) );
				lows.push( y( low, at ) );
			}

			if ( Math.min.apply( null, tops ) < 0 ) {
				shift.push( tile );
			}

			if ( Math.max.apply( null, lows ) > tile ) {
				shift.push( -tile );
			}

			return shift;
		};

		for ( let k = 0; k < lines; k++ ) {
			const shade = Math.sin( circle * 2 * ( k + 0.5 ) / lines );
			const fill = shade > 0 ? '#fff' : '#000';
			const alpha = Math.round( Math.abs( shade ) * light * ( shade > 0 ? 0.5 : 0.3 ) * 1000 ) / 1000;

			shifts( k, k + 1 ).forEach( function ( dy ) {
				bands += "<path d='M0 " + num( y( k, 0 ) + dy ) + curve( k, false, dy ) + 'L' + tile + ' ' + num( y( k + 1, steps ) + dy ) + curve( k + 1, true, dy ) + "Z' fill='" + fill + "' fill-opacity='" + alpha + "'/>";
			} );

			shifts( k, k ).forEach( function ( dy ) {
				edges += 'M0 ' + num( y( k, 0 ) + dy ) + curve( k, false, dy );
			} );
		}

		return svgUrl( "<svg xmlns='http://www.w3.org/2000/svg' width='" + tile + "' height='" + tile + "' viewBox='0 0 " + tile + ' ' + tile + "'>" + bands + "<path d='" + edges + "' fill='none' stroke='#fff' stroke-opacity='" + Math.round( light * 0.7 * 1000 ) / 1000 + "' stroke-width='1.2'/></svg>" );
	}

	// Same lights as CheckoutLook::bokeh(): same seed, same steps.
	function bokeh( size, strength, color, color2 ) {
		const tile = 6 * size;
		const light = strength / 100;
		let seed = 20261023;
		let defs = '';
		let shapes = '';

		const random = function () {
			seed = ( seed * 16807 ) % 2147483647;

			return seed / 2147483647;
		};

		[ color, color2, '#ffffff' ].forEach( function ( value, at ) {
			defs += "<radialGradient id='b" + at + "'><stop offset='0' stop-color='" + value + "' stop-opacity='.62'/><stop offset='.8' stop-color='" + value + "' stop-opacity='.5'/><stop offset='.93' stop-color='" + value + "' stop-opacity='.6'/><stop offset='1' stop-color='" + value + "' stop-opacity='0'/></radialGradient>";
		} );

		for ( let n = 0; n < 22; n++ ) {
			const x = random() * tile;
			const y = random() * tile;
			const grow = random();
			const pick = random();
			const r = size * ( 0.2 + grow * 0.95 );
			const fill = [ 0.42, 0.8, 2 ].findIndex( function ( edge ) {
				return pick < edge;
			} );
			const alpha = Math.round( light * ( 0.75 - grow * 0.45 ) * 1000 ) / 1000;

			tileOffsets( [ [ x - r, y - r ], [ x + r, y + r ] ], tile ).forEach( function ( offset ) {
				shapes += "<circle cx='" + Math.round( ( x + offset[ 0 ] ) * 10 ) / 10 + "' cy='" + Math.round( ( y + offset[ 1 ] ) * 10 ) / 10 + "' r='" + Math.round( r * 10 ) / 10 + "' fill='url(#b" + fill + ")' opacity='" + alpha + "'/>";
			} );
		}

		return svgUrl( "<svg xmlns='http://www.w3.org/2000/svg' width='" + tile + "' height='" + tile + "' viewBox='0 0 " + tile + ' ' + tile + "'><defs>" + defs + '</defs>' + shapes + '</svg>' );
	}

	// Same as CheckoutLook::embed_url(): a YouTube or Vimeo link as a muted, looping player.
	function embedUrl( url ) {
		let match = /^https?:\/\/(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:[^#]*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/.exec( url );

		if ( match ) {
			return 'https://www.youtube-nocookie.com/embed/' + match[ 1 ] + '?autoplay=1&mute=1&loop=1&playlist=' + match[ 1 ] + '&controls=0&playsinline=1&rel=0&disablekb=1&iv_load_policy=3&enablejsapi=1';
		}

		match = /^https?:\/\/(?:www\.|player\.)?vimeo\.com\/(?:video\/)?(\d+)(?:\/([0-9a-f]+))?/.exec( url );

		if ( match ) {
			const query = /[?&]h=([0-9a-f]+)/.exec( url );
			const hash = match[ 2 ] || ( query ? query[ 1 ] : '' );

			return 'https://player.vimeo.com/video/' + match[ 1 ] + '?background=1&autoplay=1&loop=1&muted=1&dnt=1' + ( hash ? '&h=' + hash : '' );
		}

		return '';
	}

	// Same as CheckoutLook::seconds(): 1:30, 1:02:03 or 90, in seconds.
	function seconds( value ) {
		const parts = /^\s*(?:(\d+):)?(?:(\d+):)?(\d+)\s*$/.exec( String( value || '' ) );

		if ( ! parts ) {
			return 0;
		}

		return Math.min( 86400, parts.slice( 1 ).filter( function ( part ) {
			return part !== undefined;
		} ).reduce( function ( total, part ) {
			return total * 60 + parseInt( part, 10 );
		}, 0 ) );
	}

	// Only simple formatting reaches the preview, whatever is typed in Raw.
	function safeHtml( html ) {
		const allowed = [ 'STRONG', 'B', 'EM', 'I', 'U', 'A', 'P', 'BR', 'SPAN' ];
		const doc = new DOMParser().parseFromString( '<div>' + html + '</div>', 'text/html' );
		const box = doc.body.firstChild;

		box.querySelectorAll( '*' ).forEach( function ( node ) {
			if ( allowed.indexOf( node.tagName ) === -1 ) {
				node.replaceWith( document.createTextNode( node.textContent ) );

				return;
			}

			Array.prototype.slice.call( node.attributes ).forEach( function ( attribute ) {
				const keep = node.tagName === 'A' && attribute.name === 'href' && /^(https?:|mailto:)/i.test( attribute.value );

				if ( ! keep ) {
					node.removeAttribute( attribute.name );
				}
			} );
		} );

		return box.innerHTML;
	}

	function fillSample( text ) {
		const sample = config.sample || {};

		return String( text || '' ).replace( /\{([a-z_]+)(?::([a-z0-9_]+))?\}/g, function ( match, key, field ) {
			if ( field ) {
				return key === 'field' ? '[' + field + ']' : match;
			}

			return Object.prototype.hasOwnProperty.call( sample, key ) ? sample[ key ] : match;
		} );
	}

	function bindLook( form ) {
		// The same mapping as CheckoutLook::variables(), so the preview matches what the site prints.
		const lookFields = {
			outline: [ 'var(--iftp-surface)', 'var(--iftp-line-strong)', '1px', null, '12px' ],
			soft: [ 'var(--iftp-inset)', 'transparent', '1px', null, '12px' ],
			underline: [ 'transparent', 'var(--iftp-line-strong)', '0 0 1.5px', '0', '2px' ],
		};

		// Matches the zoom on .iftp-btn-look-preview__frame in the pay page view.
		const pageZoom = 0.62;
		const viewKey = 'iftp-btn-look-view';

		const lookBackdrops = {
			blur: [ 'rgb(12 22 40 / 48%)', '3px' ],
			dim: [ 'rgb(12 22 40 / 62%)', '0px' ],
			light: [ 'rgb(250 251 253 / 70%)', '8px' ],
		};

		const frame = form.querySelector( '[data-iftp-look-frame]' );
		const stage = form.querySelector( '[data-iftp-look-stage]' );

		if ( ! frame ) {
			return;
		}

		bindSwatches( form );

		// A button set to the site's look previews the site's look, whatever its own controls hold.
		const siteMode = function () {
			const mode = form.querySelector( '[data-iftp-look-mode]:checked' );

			return !! mode && mode.value === 'site' && !! config.siteLook;
		};

		const read = function ( key ) {
			if ( siteMode() ) {
				const site = config.siteLook;

				// "Auto" colours are stored as ''.
				if ( /_auto$/.test( key ) ) {
					return site[ key.replace( '_auto', '' ) ] === '';
				}

				return typeof site[ key ] === 'boolean' ? site[ key ] : String( site[ key ] === undefined || site[ key ] === null ? '' : site[ key ] );
			}

			const input = form.querySelector( '[data-iftp-look="' + key + '"]:checked' ) || form.querySelector( '[data-iftp-look="' + key + '"]:not([type="radio"])' );

			if ( ! input ) {
				return '';
			}

			return input.type === 'checkbox' ? input.checked : input.value;
		};

		const set = function ( key, value ) {
			form.querySelectorAll( '[data-iftp-look="' + key + '"]' ).forEach( function ( input ) {
				if ( input.type === 'radio' ) {
					input.checked = input.value === String( value );
				} else if ( input.type === 'checkbox' ) {
					input.checked = !! value;
				} else {
					input.value = value;
				}
			} );

			const swatch = form.querySelector( '[data-iftp-color-for="iftp-btn-look-' + key + '"]' );

			if ( swatch && hex( String( value ) ) ) {
				swatch.value = hex( String( value ) );
			}
		};

		let view = 'checkout';

		// Sun or moon: how a payer on a light or dark device sees it.
		let scheme = 'light';
		const patterns = {};

		// The pay page shows the same checkout card, on the page's background instead of over a dimmed site.
		const show = function ( next ) {
			view = next;
			stage.dataset.view = view;

			// Remembered, so a save or a refresh comes back to the view they were looking at.
			try {
				window.localStorage.setItem( viewKey, view );
			} catch ( e ) {}

			frame.querySelectorAll( '[data-iftp-look-view]' ).forEach( function ( sheet ) {
				sheet.hidden = sheet.dataset.iftpLookView !== ( view === 'page' ? 'checkout' : view );
			} );

			apply();

			form.querySelectorAll( '[data-iftp-look-view-pick]' ).forEach( function ( pick ) {
				pick.setAttribute( 'aria-pressed', pick.dataset.iftpLookViewPick === view ? 'true' : 'false' );
			} );
		};

		// Same as CheckoutLook::page_color(): the checkout background with 6% of its text.
		const pageColor = function ( ink, surface ) {
			const a = channels( ink );
			const b = channels( surface );

			return '#' + [ 0, 1, 2 ].map( function ( at ) {
				return ( '0' + Math.round( 0.06 * a[ at ] + 0.94 * b[ at ] ).toString( 16 ) ).slice( -2 );
			} ).join( '' );
		};

		const words = function () {
			// Without the Messages inputs (the button editor), the saved words still get their sample values.
			frame.querySelectorAll( '[data-iftp-look-title]' ).forEach( function ( title ) {
				if ( ! form.querySelector( '[data-iftp-outcome-title="' + title.dataset.iftpLookTitle + '"]' ) ) {
					title.dataset.raw = title.dataset.raw || title.textContent;
					title.textContent = fillSample( title.dataset.raw );
				}
			} );

			frame.querySelectorAll( '[data-iftp-look-message]' ).forEach( function ( message ) {
				if ( ! form.querySelector( '[data-iftp-outcome-message="' + message.dataset.iftpLookMessage + '"]' ) ) {
					message.dataset.raw = message.dataset.raw || message.innerHTML;
					message.innerHTML = safeHtml( fillSample( message.dataset.raw ) );
				}
			} );

			form.querySelectorAll( '[data-iftp-outcome-title]' ).forEach( function ( input ) {
				const target = frame.querySelector( '[data-iftp-look-title="' + input.dataset.iftpOutcomeTitle + '"]' );

				if ( target ) {
					target.textContent = fillSample( input.value || input.placeholder );
				}
			} );

			form.querySelectorAll( '[data-iftp-outcome-message]' ).forEach( function ( box ) {
				const raw = box.querySelector( '[data-iftp-msg-raw]' );
				const target = frame.querySelector( '[data-iftp-look-message="' + box.dataset.iftpOutcomeMessage + '"]' );

				if ( raw && target ) {
					target.innerHTML = safeHtml( fillSample( raw.value ) );
				}
			} );

			// The Texts tab: the merchant's word, or the original while the box is blank.
			form.querySelectorAll( '[data-iftp-text-input]' ).forEach( function ( input ) {
				const text = input.value || input.placeholder;

				frame.querySelectorAll( '[data-iftp-text="' + input.dataset.iftpTextInput + '"]' ).forEach( function ( target ) {
					target.textContent = text.replace( '{step}', target.dataset.step || '1' ).replace( '{steps}', target.dataset.steps || '2' );
				} );
			} );

			const details = form.querySelector( '[data-iftp-show-details]' );

			frame.querySelectorAll( '[data-iftp-look-details]' ).forEach( function ( list ) {
				list.hidden = !! details && ! details.checked;
			} );
		};

		// Same as CheckoutLook::alpha().
		const alpha = function ( color, percent ) {
			return 'rgb(' + channels( color ).join( ' ' ) + ' / ' + percent + '%)';
		};

		// The patterns are big strings, so each is made once per setting.
		const pattern = function ( fx, size, strength, accent, second ) {
			const key = [ fx, size, strength, accent, second ].join( '|' );

			if ( ! patterns[ key ] ) {
				if ( fx === 'crystals' ) {
					patterns[ key ] = crystals( size, strength );
				} else if ( fx === 'waves' ) {
					patterns[ key ] = waves( size, strength );
				} else if ( fx === 'bokeh' ) {
					patterns[ key ] = bokeh( size, strength, accent, second );
				} else {
					patterns[ key ] = 'none';
				}
			}

			return patterns[ key ];
		};

		// Same as CheckoutLook::video(): the file picked, or the YouTube or Vimeo player, and the part that loops.
		const pickedVideo = function () {
			const part = read( 'pay_video_loop' ) === 'part';
			const start = part ? seconds( read( 'pay_video_start' ) ) : null;
			let end = part ? seconds( read( 'pay_video_end' ) ) : null;

			if ( part && end <= start ) {
				end = 0;
			}

			if ( read( 'pay_video_src' ) === 'link' ) {
				let embed = embedUrl( String( read( 'pay_video_url' ) ).trim() );

				// Same as CheckoutLook::embed_from().
				if ( embed && part ) {
					embed += /vimeo\.com/.test( embed ) ? '#t=' + start + 's' : '&start=' + start;
				}

				return embed ? { kind: 'embed', src: embed, start, end } : null;
			}

			const fileInput = form.querySelector( '[data-iftp-look="pay_video"]' );
			const url = siteMode() ? config.siteLook.pay_video_file_url || '' : ( fileInput && fileInput.dataset.url ) || '';

			return url ? { kind: 'file', src: url, start, end } : null;
		};

		// The pay page's video, as CheckoutLook::video_markup() prints it; rebuilt only when it changes.
		const showVideo = function ( layer, video ) {
			const src = video ? video.src : '';
			const key = video ? [ src, video.start, video.end ].join( '|' ) : '';
			let box = layer.querySelector( '[data-iftp-video]' );

			if ( box && box.dataset.for === key ) {
				return;
			}

			if ( box ) {
				box.remove();
			}

			if ( ! src ) {
				return;
			}

			const embed = video.kind === 'embed';
			const media = document.createElement( embed ? 'iframe' : 'video' );

			box = document.createElement( 'div' );
			box.className = 'iftp-btn-pagevideo';
			box.dataset.iftpVideo = '';
			box.dataset.for = key;
			media.className = 'iftp-btn-pagevideo__media' + ( embed ? ' iftp-btn-pagevideo__media--embed' : '' );

			if ( video.start !== null ) {
				media.dataset.loopStart = video.start;
				media.dataset.loopEnd = video.end;
			}

			if ( embed ) {
				media.title = '';
				media.tabIndex = -1;
				media.setAttribute( 'allow', 'autoplay; encrypted-media; picture-in-picture' );
			} else {
				media.muted = true;
				media.loop = true;
				media.autoplay = true;
				media.playsInline = true;
			}

			if ( window.iftpBtnVideoLoop ) {
				window.iftpBtnVideoLoop( media );
			}

			media.src = src;
			box.appendChild( media );
			layer.insertBefore( box, layer.firstChild );
		};

		const apply = function () {
			const accent = hex( read( 'accent' ) ) || '#00609c';
			const auto = !! read( 'accent_text_auto' );
			const onBrand = auto ? onColor( accent ) : hex( read( 'accent_text' ) ) || onColor( accent );
			const field = lookFields[ read( 'fields' ) ] || lookFields.outline;
			const backdrop = lookBackdrops[ read( 'backdrop' ) ] || lookBackdrops.blur;
			const fieldRadius = ( parseInt( read( 'field_radius' ), 10 ) || 0 ) + 'px';
			const ink = hex( read( 'ink' ) ) || '#121a2c';
			const surface = hex( read( 'surface' ) ) || '#fdfdfe';
			const pageAuto = !! read( 'pay_color_auto' );
			const page = pageAuto ? pageColor( ink, surface ) : hex( read( 'pay_color' ) ) || pageColor( ink, surface );
			const ownSecond = siteMode() || ! read( 'pay_color2_auto' ) ? hex( read( 'pay_color2' ) ) : '';
			const second = ownSecond || accent;
			const opacity = parseInt( read( 'card_opacity' ), 10 );
			const card = isNaN( opacity ) ? 100 : Math.min( 100, opacity );
			const glassBlur = parseInt( read( 'card_blur' ), 10 ) || 0;
			const darkOn = read( 'dark' ) === 'auto';
			const darkAccentAuto = !! read( 'dark_accent_auto' );
			const darkAccent = darkAccentAuto ? accent : hex( read( 'dark_accent' ) ) || accent;

			// Same as CheckoutLook::palette().
			const palette = function ( c ) {
				return {
					'--iftp-brand': c.accent,
					'--iftp-on-brand': c.onBrand,
					'--iftp-surface': c.surface,
					'--iftp-ink': c.ink,
					'--iftp-card-bg': card < 100 ? alpha( c.surface, card ) : c.surface,
					'--iftp-chip-bg': card < 100 ? alpha( c.surface, Math.min( 100, card + 30 ) ) : c.surface,
					'--iftp-logo-filter': onColor( c.surface ) === '#ffffff' ? 'brightness(0) invert(1)' : 'none',
					'--iftp-page-bg': c.page,
					'--iftp-page-glow': alpha( c.accent, 16 ),
					'--iftp-page-glow2': alpha( c.second, 16 ),
					'--iftp-page-orb': alpha( c.accent, 45 ),
					'--iftp-page-orb2': alpha( c.second, 45 ),
				};
			};

			let colors = palette( { accent, onBrand, surface, ink, page, second } );

			// Same as CheckoutLook::dark_palette(). With dark mode off, a dark device still gets the light look.
			if ( scheme === 'dark' && darkOn ) {
				const darkSurface = hex( read( 'dark_surface' ) ) || '#131a2a';
				const darkInk = hex( read( 'dark_ink' ) ) || '#eef2f8';

				colors = palette( {
					accent: darkAccent,
					onBrand: darkAccentAuto ? onBrand : onColor( darkAccent ),
					surface: darkSurface,
					ink: darkInk,
					page: pageColor( darkInk, darkSurface ),
					second: ownSecond || darkAccent,
				} );
			}

			const vars = Object.assign( colors, {
				'--iftp-paid': hex( read( 'paid' ) ) || '#0b6b35',
				'--iftp-pending': hex( read( 'pending' ) ) || '#8a5300',
				'--iftp-failed': hex( read( 'failed' ) ) || '#a3201f',
				'--iftp-radius': ( parseInt( read( 'radius' ), 10 ) || 0 ) + 'px',
				'--iftp-field-r': field[ 3 ] === null ? fieldRadius : field[ 3 ],
				'--iftp-field-bg': field[ 0 ],
				'--iftp-field-bd': field[ 1 ],
				'--iftp-field-bw': field[ 2 ],
				'--iftp-field-px': field[ 4 ],
				'--iftp-field-focus': read( 'fields' ) === 'underline' ? '0 1px 0 0 var(--iftp-brand)' : 'var(--iftp-ring)',
				'--iftp-sheet-w': ( ( config.lookWidths || {} )[ read( 'width' ) ] || 440 ) + 'px',
				'--iftp-result-align': read( 'popup_align' ) === 'left' ? 'start' : 'center',
				'--iftp-card-filter': card < 100 && glassBlur > 0 ? 'blur(' + glassBlur + 'px) saturate(1.6)' : 'none',
				'--iftp-card-ring': card < 100 ? 'inset 0 0 0 1px rgb(255 255 255 / 22%)' : '0 0 #0000',
				'--iftp-body-bg': card < 100 ? 'transparent' : 'initial',
				'--iftp-page-video-blur': ( parseInt( read( 'pay_video_blur' ), 10 ) || 0 ) + 'px',
			} );

			const imageInput = form.querySelector( '[data-iftp-look="pay_image"]' );
			const imageUrl = siteMode() ? config.siteLook.pay_image_url || '' : ( imageInput && imageInput.dataset.url ) || '';
			const video = pickedVideo();
			let background = read( 'pay_bg' ) || 'glow';

			// Same fallbacks as CheckoutLook::sanitize().
			if ( ( background === 'image' && ! imageUrl ) || ( background === 'video' && ! video ) ) {
				background = 'glow';
			}

			vars[ '--iftp-page-image' ] = imageUrl ? 'url("' + imageUrl.replace( /["\\]/g, '' ) + '")' : 'none';
			vars[ '--iftp-page-dim' ] = 'rgb(0 0 0 / ' + ( parseInt( read( 'pay_dim' ), 10 ) || 0 ) + '%)';

			// Same as CheckoutLook::page_effects().
			const strength = parseInt( read( 'pay_fx_strength' ), 10 ) || 0;
			const blurType = read( 'pay_blur_type' ) || 'none';
			const blur = ( parseInt( read( 'pay_blur' ), 10 ) || 0 ) + 'px';
			const masks = {
				tilt: 'linear-gradient(to bottom, #000 0%, transparent 32%, transparent 68%, #000 100%)',
				vignette: 'radial-gradient(ellipse at center, transparent 30%, #000 78%)',
			};
			const crystalSize = parseInt( read( 'pay_fx_size' ), 10 ) || 140;

			vars[ '--iftp-page-crystal' ] = crystalSize + 'px';
			vars[ '--iftp-page-pattern' ] = pattern( read( 'pay_fx' ), crystalSize, strength, accent, second );
			vars[ '--iftp-page-pattern-tile' ] = crystalSize * 6 + 'px';
			vars[ '--iftp-page-fx-light' ] = 'rgb(255 255 255 / ' + Math.round( strength * 0.5 ) + '%)';
			vars[ '--iftp-page-fx-dark' ] = 'rgb(0 0 0 / ' + Math.round( strength * 0.28 ) + '%)';
			vars[ '--iftp-page-fx-opacity' ] = String( Math.round( strength ) / 100 );
			vars[ '--iftp-page-blur' ] = blur;
			vars[ '--iftp-page-blur-filter' ] = blurType === 'frosted' ? 'blur(' + blur + ') saturate(1.6) brightness(1.06)' : 'blur(' + blur + ')';
			vars[ '--iftp-page-blur-mask' ] = masks[ blurType ] || 'none';
			vars[ '--iftp-page-frost' ] = blurType === 'frosted' ? 'rgb(255 255 255 / 16%)' : 'transparent';

			// The step style cards wear the look too.
			const samples = form.querySelectorAll( '[data-iftp-look-sample]' );

			Object.keys( vars ).forEach( function ( name ) {
				frame.style.setProperty( name, vars[ name ] );
				stage.style.setProperty( name, vars[ name ] );
				samples.forEach( function ( sample ) {
					sample.style.setProperty( name, vars[ name ] );
				} );
			} );

			const stepStyle = read( 'steps' ) || 'bars';

			form.querySelectorAll( '[data-iftp-look-frame] .iftp-btn-steps, [data-iftp-preview-steps]' ).forEach( function ( bar ) {
				bar.className = 'iftp-btn-steps iftp-btn-steps--' + stepStyle;
			} );

			stage.dataset.scheme = scheme;

			const darkNote = form.querySelector( '[data-iftp-look-dark-note]' );

			if ( darkNote ) {
				darkNote.hidden = scheme !== 'dark' || darkOn;
			}

			// The editor's checkout mock follows along; on the site's look it takes the stylesheet's again.
			form.querySelectorAll( '[data-iftp-look-mirror]' ).forEach( function ( mirror ) {
				if ( siteMode() ) {
					mirror.removeAttribute( 'style' );

					return;
				}

				Object.keys( vars ).forEach( function ( name ) {
					mirror.style.setProperty( name, vars[ name ] );
				} );
			} );

			const pageLayer = stage.querySelector( '[data-iftp-look-pagebg]' );

			// The checkout and popups sit on the pay page's background too, when the look opens them there.
			stage.classList.toggle( 'is-scene', view !== 'page' && !! read( 'scene' ) );

			if ( pageLayer ) {
				// The pay page view is zoomed out, so its textures shrink with it.
				const zoom = view === 'page' ? pageZoom : 1;

				pageLayer.style.setProperty( '--iftp-page-pattern-tile', Math.round( crystalSize * 6 * zoom ) + 'px' );
				pageLayer.style.setProperty( '--iftp-page-video-blur', Math.round( ( parseInt( read( 'pay_video_blur' ), 10 ) || 0 ) * zoom ) + 'px' );
				pageLayer.style.setProperty( '--iftp-page-crystal', Math.round( crystalSize * zoom ) + 'px' );
				pageLayer.style.setProperty( '--iftp-page-blur', Math.round( ( parseInt( read( 'pay_blur' ), 10 ) || 0 ) * zoom ) + 'px' );
				pageLayer.style.setProperty( '--iftp-page-blur-filter', vars[ '--iftp-page-blur-filter' ].replace( /blur\(\d+px\)/, 'blur(' + Math.round( ( parseInt( read( 'pay_blur' ), 10 ) || 0 ) * zoom ) + 'px)' ) );
				pageLayer.className = 'iftp-btn-look-preview__pagebg iftp-btn-pagebg--' + background + ' iftp-btn-pagegrad--' + ( read( 'pay_grad' ) || 'soft' ) + ' iftp-btn-pagefx--' + ( read( 'pay_fx' ) || 'none' ) + ' iftp-btn-pageblur--' + blurType;
				// Only while the background is on show, so a hidden preview doesn't keep a video playing.
				showVideo( pageLayer, background === 'video' && ( view === 'page' || read( 'scene' ) ) ? video : null );
			}

			const secondBox = form.querySelector( '[data-iftp-second-color]' );

			if ( secondBox && ! siteMode() ) {
				secondBox.classList.toggle( 'is-auto', !! read( 'pay_color2_auto' ) );

				if ( read( 'pay_color2_auto' ) ) {
					set( 'pay_color2', accent );
				}
			}

			const darkAccentBox = form.querySelector( '[data-iftp-dark-accent]' );

			if ( darkAccentBox && ! siteMode() ) {
				darkAccentBox.classList.toggle( 'is-auto', darkAccentAuto );

				if ( darkAccentAuto ) {
					set( 'dark_accent', accent );
				}
			}

			frame.querySelectorAll( '[data-iftp-look-chrome]' ).forEach( function ( chrome ) {
				chrome.hidden = view !== 'page' || ! read( chrome.dataset.iftpLookChrome );
			} );

			const pageBox = form.querySelector( '[data-iftp-page-color]' );

			if ( pageBox && ! siteMode() ) {
				pageBox.classList.toggle( 'is-auto', pageAuto );

				if ( pageAuto ) {
					set( 'pay_color', page );
				}
			}

			stage.style.setProperty( '--iftp-stage-backdrop', backdrop[ 0 ] );
			stage.style.setProperty( '--iftp-stage-blur', backdrop[ 1 ] );
			frame.classList.toggle( 'is-theme-font', read( 'font' ) === 'theme' );

			frame.querySelectorAll( '[data-iftp-look-result]' ).forEach( function ( sheet ) {
				sheet.classList.toggle( 'iftp-btn-result--solid', read( 'popup_icon' ) === 'solid' );
			} );

			const logos = frame.querySelector( '[data-iftp-look-logos]' );
			const secure = frame.querySelector( '[data-iftp-look-secure]' );

			logos.hidden = ! read( 'logos' );
			secure.hidden = ! read( 'secure' );
			frame.querySelector( '[data-iftp-look-footer]' ).hidden = logos.hidden && secure.hidden;

			const autoBox = form.querySelector( '[data-iftp-auto-color]' );

			if ( autoBox && ! siteMode() ) {
				autoBox.classList.toggle( 'is-auto', auto );

				if ( auto ) {
					set( 'accent_text', onBrand );
				}
			}

			form.querySelectorAll( '[data-iftp-range-out]' ).forEach( function ( out ) {
				const range = form.querySelector( '#' + out.getAttribute( 'for' ) );

				out.textContent = out.dataset.unit === '%' ? range.value + '%' : ( i18n.px || '%d px' ).replace( '%d', range.value );
			} );

			words();
		};

		form.querySelectorAll( '[data-iftp-look-preset]' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				const preset = ( config.lookPresets || {} )[ item.dataset.iftpLookPreset ];

				if ( ! preset ) {
					return;
				}

				[ 'accent', 'surface', 'ink', 'radius', 'field_radius', 'fields' ].forEach( function ( key ) {
					set( key, preset[ key ] );
				} );
				set( 'accent_text_auto', true );

				const accent = form.querySelector( '[data-iftp-look="accent"]' );

				accent.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				show( 'checkout' );
			} );
		} );

		form.querySelectorAll( '[data-iftp-look-view-pick]' ).forEach( function ( pick ) {
			pick.addEventListener( 'click', function () {
				show( pick.dataset.iftpLookViewPick );
			} );
		} );

		// Working on the pay page shows it.
		form.querySelectorAll( '[data-iftp-look-section="page"]' ).forEach( function ( section ) {
			section.addEventListener( 'focusin', function () {
				if ( view !== 'page' ) {
					show( 'page' );
				}
			} );
		} );

		bindReveal( form, 'input[data-iftp-look="pay_bg"]', 'data-iftp-when-bg' );
		bindReveal( form, 'input[data-iftp-look="pay_fx"]', 'data-iftp-when-fx' );
		bindReveal( form, 'input[data-iftp-look="pay_blur_type"]', 'data-iftp-when-blur' );
		bindReveal( form, 'input[data-iftp-look="scene"]', 'data-iftp-when-scene' );
		bindReveal( form, 'input[data-iftp-look="pay_video_src"]', 'data-iftp-when-vsrc' );
		bindReveal( form, 'input[data-iftp-look="pay_video_loop"]', 'data-iftp-when-loop' );
		bindReveal( form, 'input[data-iftp-look="dark"]', 'data-iftp-when-dark' );

		const pickScheme = function ( next ) {
			scheme = next;

			form.querySelectorAll( '[data-iftp-look-scheme]' ).forEach( function ( option ) {
				option.setAttribute( 'aria-pressed', option.dataset.iftpLookScheme === scheme ? 'true' : 'false' );
			} );

			apply();
		};

		form.querySelectorAll( '[data-iftp-look-scheme]' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				pickScheme( option.dataset.iftpLookScheme );
			} );
		} );

		// Working on the dark colours shows them.
		form.querySelectorAll( '[data-iftp-look-section="dark"]' ).forEach( function ( section ) {
			section.addEventListener( 'focusin', function () {
				if ( scheme !== 'dark' ) {
					pickScheme( 'dark' );
				}
			} );
		} );

		// Touching the dark accent means they want their own.
		[ form.querySelector( '[data-iftp-look="dark_accent"]' ), form.querySelector( '[data-iftp-color-for="iftp-btn-look-dark_accent"]' ) ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					const darkAuto = form.querySelector( '[data-iftp-look="dark_accent_auto"]' );

					if ( darkAuto && darkAuto.checked ) {
						darkAuto.checked = false;
					}
				} );
			}
		} );

		const videoPick = form.querySelector( '[data-iftp-video-pick]' );
		const videoClear = form.querySelector( '[data-iftp-video-clear]' );
		const videoName = form.querySelector( '[data-iftp-video-name]' );
		const videoField = form.querySelector( '[data-iftp-look="pay_video"]' );
		let videoFrame = null;

		const setVideo = function ( id, url, name ) {
			videoField.value = id;
			videoField.dataset.url = url;
			videoName.textContent = name || videoName.dataset.empty;
			videoClear.hidden = ! id || id === '0';
			videoField.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		if ( videoPick && window.wp && window.wp.media ) {
			videoPick.addEventListener( 'click', function () {
				if ( ! videoFrame ) {
					videoFrame = window.wp.media( {
						title: i18n.pickVideo,
						button: { text: i18n.useVideo },
						library: { type: [ 'video/mp4', 'video/webm' ] },
						multiple: false,
					} );

					videoFrame.on( 'select', function () {
						const chosen = videoFrame.state().get( 'selection' ).first().toJSON();

						setVideo( String( chosen.id ), chosen.url, chosen.filename || chosen.title || '' );
					} );
				}

				videoFrame.open();
			} );

			videoClear.addEventListener( 'click', function () {
				setVideo( '0', '', '' );
			} );
		}
		bindReveal( form, '[data-iftp-look-mode]', 'data-iftp-when-lookmode' );

		const pick = form.querySelector( '[data-iftp-image-pick]' );
		const clear = form.querySelector( '[data-iftp-image-clear]' );
		const thumb = form.querySelector( '[data-iftp-image-thumb]' );
		const imageField = form.querySelector( '[data-iftp-look="pay_image"]' );
		let mediaFrame = null;

		const setImage = function ( id, full, small ) {
			imageField.value = id;
			imageField.dataset.url = full;
			thumb.style.backgroundImage = small ? 'url("' + small.replace( /["\\]/g, '' ) + '")' : '';
			clear.hidden = ! id;
			imageField.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		if ( pick && window.wp && window.wp.media ) {
			pick.addEventListener( 'click', function () {
				if ( ! mediaFrame ) {
					mediaFrame = window.wp.media( {
						title: i18n.pickImage,
						button: { text: i18n.useImage },
						library: { type: 'image' },
						multiple: false,
					} );

					mediaFrame.on( 'select', function () {
						const chosen = mediaFrame.state().get( 'selection' ).first().toJSON();
						const sizes = chosen.sizes || {};

						setImage( String( chosen.id ), chosen.url, ( sizes.medium || sizes.full || chosen ).url );
					} );
				}

				mediaFrame.open();
			} );

			clear.addEventListener( 'click', function () {
				setImage( '0', '', '' );
			} );
		}

		// Editing an outcome's words shows that popup.
		form.querySelectorAll( '.iftp-btn-outcome' ).forEach( function ( fieldset ) {
			fieldset.addEventListener( 'focusin', function () {
				const outcome = /iftp-btn-outcome--(\w+)/.exec( fieldset.className );

				if ( outcome ) {
					show( outcome[ 1 ] );
				}
			} );
		} );

		// Touching the accent text colour means they want their own.
		[ form.querySelector( '[data-iftp-look="accent_text"]' ), form.querySelector( '[data-iftp-color-for="iftp-btn-look-accent_text"]' ) ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					const auto = form.querySelector( '[data-iftp-look="accent_text_auto"]' );

					if ( auto && auto.checked ) {
						auto.checked = false;
					}
				} );
			}
		} );

		// Working on a group of texts shows where they appear, unless that's already in view.
		form.querySelectorAll( '[data-iftp-text-view]' ).forEach( function ( section ) {
			section.addEventListener( 'focusin', function () {
				const wanted = section.dataset.iftpTextView;

				if ( view !== wanted && ! ( wanted === 'checkout' && view === 'page' ) && ! ( wanted === 'paid' && [ 'paid', 'pending', 'failed', 'cancelled' ].indexOf( view ) !== -1 ) ) {
					show( wanted );
				}
			} );
		} );

		form.addEventListener( 'input', apply );
		form.addEventListener( 'change', apply );

		let saved = '';

		try {
			saved = window.localStorage.getItem( viewKey ) || '';
		} catch ( e ) {
			saved = '';
		}

		if ( saved && form.querySelector( '[data-iftp-look-view-pick="' + saved + '"]' ) ) {
			show( saved );
		} else {
			apply();
		}
	}

	/* ---------------------------------------------------------------- Checkout settings */

	const checkout = document.querySelector( '.iftp-btn-checkout-settings' );

	if ( checkout ) {
		bindReveal( checkout, '[data-iftp-paid-type]', 'data-iftp-when-paid' );

		const settingsForm = checkout.querySelector( '[data-iftp-csettings]' );

		if ( settingsForm ) {
			bindTabs( settingsForm );
			bindDirty( settingsForm );
			bindLook( settingsForm );
		}

		// Two views of one message: the textarea is what's submitted, the contenteditable mirrors it.
		checkout.querySelectorAll( '[data-iftp-msg]' ).forEach( function ( box ) {
			const normal = box.querySelector( '[data-iftp-msg-normal]' );
			const raw = box.querySelector( '[data-iftp-msg-raw]' );
			const tools = box.querySelectorAll( '[data-iftp-msg-cmd]' );

			const show = function ( view ) {
				const isRaw = view === 'raw';

				if ( isRaw ) {
					raw.value = normal.innerHTML;
				} else {
					normal.innerHTML = raw.value;
				}

				normal.hidden = isRaw;
				raw.hidden = ! isRaw;

				tools.forEach( function ( tool ) {
					tool.disabled = isRaw;
				} );

				box.querySelectorAll( '[data-iftp-msg-view]' ).forEach( function ( toggle ) {
					toggle.setAttribute( 'aria-pressed', toggle.dataset.iftpMsgView === view ? 'true' : 'false' );
				} );
			};

			// The textarea is the named field, so it carries the change on to the preview and the unsaved flag.
			normal.addEventListener( 'input', function () {
				raw.value = normal.innerHTML;
				raw.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );

			tools.forEach( function ( tool ) {
				tool.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
				} );

				tool.addEventListener( 'click', function () {
					const command = tool.dataset.iftpMsgCmd;
					let argument = null;

					if ( command === 'createLink' ) {
						argument = window.prompt( i18n.linkPrompt, 'https://' ); // eslint-disable-line no-alert

						if ( ! argument ) {
							return;
						}
					}

					normal.focus();
					document.execCommand( command, false, argument );
					raw.value = normal.innerHTML;
				} );
			} );

			box.querySelectorAll( '[data-iftp-msg-view]' ).forEach( function ( toggle ) {
				toggle.addEventListener( 'click', function () {
					show( toggle.dataset.iftpMsgView );
				} );
			} );

			show( 'normal' );
		} );
	}
} )();
