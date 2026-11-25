/**
 * Advanced designs: the tiles and the live preview draw the shipped drawings from a library the page asks for once, and the
 * colour boxes follow the design picked. The real button on a site is drawn by PHP; this is only the admin's look at it.
 */
( function ( ns ) {
	'use strict';

	const { config } = ns;

	ns.advancedPreview = advancedPreview;
	ns.bindAdvanced = bindAdvanced;

	// slug => { css, js, ... }, once it has arrived.
	let library = null;
	let request = null;
	const waiting = [];
	const runners = {};

	function load( then ) {
		if ( library ) {
			then();

			return;
		}

		waiting.push( then );

		if ( request || ! config.advanced || ! config.advanced.url ) {
			return;
		}

		const body = new window.FormData();

		body.append( 'action', 'iftp_btn_advanced' );
		body.append( '_ajax_nonce', config.advanced.nonce );

		request = window.fetch( config.advanced.url, { method: 'POST', credentials: 'same-origin', body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( answer ) {
				if ( ! answer || ! answer.success ) {
					return;
				}

				library = answer.data;

				const style = document.createElement( 'style' );

				style.setAttribute( 'data-iftp-advanced-library', '' );
				style.textContent = Object.keys( library ).map( function ( slug ) {
					return library[ slug ].css;
				} ).join( '\n' );
				document.head.appendChild( style );

				waiting.splice( 0 ).forEach( function ( done ) {
					done();
				} );
			} )
			.catch( function () {
				request = null;
			} );
	}

	// Runs a design's script on elements, the way the site runs it on a button's own.
	function run( slug, elements ) {
		if ( ! library || ! library[ slug ] || ! library[ slug ].js ) {
			return;
		}

		if ( ! runners[ slug ] ) {
			try {
				runners[ slug ] = new window.Function( 'scope', 'try{' + library[ slug ].js + '\n}catch(e){}' );
			} catch ( error ) {
				runners[ slug ] = function () {};
			}
		}

		runners[ slug ]( elements );
	}

	function paint( element, colors ) {
		( colors || [] ).forEach( function ( color, index ) {
			if ( color ) {
				element.style.setProperty( '--a' + ( index + 1 ), color );
			}
		} );
	}

	// Shrinks a drawing that is wider than the room it has, so it is seen whole; zoom 1 when it fits.
	function fitWidth( element, room ) {
		element.style.zoom = '';

		// A drawing wider than its box spills out of it, so the box's own width isn't the drawing's.
		const natural = Math.max( element.getBoundingClientRect().width, element.scrollWidth );

		if ( room > 0 && natural > room ) {
			element.style.zoom = String( Math.floor( ( room / natural ) * 100 ) / 100 );
		}
	}

	// The design's own colours, for the slug, from its tile.
	function defaults( form, slug ) {
		const item = form.querySelector( '[data-iftp-advanced-item="' + slug + '"]' );

		return item && item.dataset.colors ? item.dataset.colors.split( ',' ) : [];
	}

	// What the design calls each of its colours, from its tile.
	function names( form, slug ) {
		const item = form.querySelector( '[data-iftp-advanced-item="' + slug + '"]' );

		return item && item.dataset.roles ? item.dataset.roles.split( '|' ) : [];
	}

	// The preview button on the Default Design screen, drawn by the picked design in the picked colours.
	function advancedPreview( form, holder, design, label ) {
		const slug = design.advanced;
		const colors = defaults( form, slug ).map( function ( own, index ) {
			return ( design.advancedColors || [] )[ index ] || own;
		} );

		const draw = function () {
			const key = slug + '|' + label;

			if ( holder.dataset.iftpAdvanced !== key ) {
				const button = document.createElement( 'span' );
				const text = document.createElement( 'span' );

				button.className = 'iftp-btn-pay__button iftp-btn-pay__button--code';
				text.className = 'iftp-btn-pay__label';
				text.textContent = label;
				button.appendChild( text );
				holder.textContent = '';
				holder.appendChild( button );
				holder.setAttribute( 'data-iftp-code', 'adv-' + slug );
				holder.dataset.iftpAdvanced = key;
				run( slug, [ holder ] );
			}

			paint( holder, colors );

			// The merchant's size is on the button alone; the holder's own zoom is only the squeeze to fit the room.
			const drawn = holder.querySelector( '.iftp-btn-pay__button' );
			const size = design.advancedSize || 100;

			if ( drawn ) {
				drawn.style.zoom = 100 === size ? '' : String( size / 100 );
			}

			const stage = holder.closest( '[data-iftp-stage]' ) || holder.parentElement;

			fitWidth( holder, stage.clientWidth - 48 );
		};

		load( draw );
	}

	function bindAdvanced( form ) {
		const box = form.querySelector( '[data-iftp-advanced]' );

		if ( ! box || box.dataset.iftpBound ) {
			return;
		}

		box.dataset.iftpBound = '1';

		const items = Array.prototype.slice.call( box.querySelectorAll( '[data-iftp-advanced-item]' ) );
		const rows = Array.prototype.slice.call( box.querySelectorAll( '[data-iftp-advanced-color]' ) );
		const roles = ( config.advanced && config.advanced.roles ) || [];
		const source = function () {
			const input = form.querySelector( 'input[data-iftp-design="source"]:checked' );

			return input ? input.value : '';
		};
		const picked = function () {
			const input = box.querySelector( 'input[data-iftp-design="advanced"]:checked' );

			return input ? input.value : '';
		};
		const field = function ( index ) {
			return rows[ index ].querySelector( '.iftp-btn-color__hex' );
		};
		const colors = function () {
			return rows.map( function ( row, index ) {
				return ns.hex( field( index ).value );
			} );
		};

		// The tiles come alive once the library is here; each plays its own motion when pointed at.
		const drawTiles = function () {
			items.forEach( function ( item ) {
				const sample = item.querySelector( '[data-iftp-advanced-sample]' );

				if ( sample && ! sample.dataset.iftpDrawn ) {
					sample.dataset.iftpDrawn = '1';
					run( item.dataset.iftpAdvancedItem, [ sample ] );
				}
			} );
			tint();
			fitTiles();
		};

		// Each drawing is as wide as it was drawn, so it is shrunk to fit its tile.
		const fitTiles = function () {
			items.forEach( function ( item ) {
				const stage = item.querySelector( '.iftp-btn-advpick__stage' );
				const sample = item.querySelector( '[data-iftp-advanced-sample]' );

				if ( stage && sample && stage.clientWidth > 0 ) {
					fitWidth( sample, stage.clientWidth - 24 );
				}
			} );
		};

		if ( window.ResizeObserver ) {
			// The list is hidden until Advanced is picked and its fold may be closed; this fits it once it has a size.
			new window.ResizeObserver( function () {
				if ( library ) {
					fitTiles();
				}
			} ).observe( box.querySelector( '[data-iftp-advanced-grid]' ) );
		}

		// The picked tile shows the colours being chosen; the others keep their own.
		const tint = function () {
			items.forEach( function ( item ) {
				const sample = item.querySelector( '[data-iftp-advanced-sample]' );

				if ( ! sample ) {
					return;
				}

				if ( item.querySelector( 'input' ).checked ) {
					paint( sample, colors() );
				} else {
					// Back to the design's own colours; the zoom that fits it to the tile stays.
					rows.forEach( function ( row, index ) {
						sample.style.removeProperty( '--a' + ( index + 1 ) );
					} );
				}
			} );
		};

		// The colour boxes take the picked design's own colours and as many boxes as it has.
		const fit = function ( reset ) {
			const slug = picked();
			const own = slug ? defaults( form, slug ) : [];
			const called = slug ? names( form, slug ) : [];

			rows.forEach( function ( row, index ) {
				const input = field( index );
				const swatch = row.querySelector( 'input[type="color"]' );
				const name = called[ index ] || roles[ index ] || '';

				row.hidden = ! own[ index ];
				input.dataset.default = own[ index ] || '';

				if ( name ) {
					row.querySelector( '[data-iftp-advanced-role]' ).textContent = name;
					swatch.setAttribute( 'aria-label', name );
				}

				if ( reset || ( ! ns.hex( input.value ) && own[ index ] ) ) {
					input.value = own[ index ] || '';

					if ( own[ index ] ) {
						swatch.value = own[ index ];
					}
				}
			} );
		};

		const fire = function ( input ) {
			input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
			input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
		};

		// Picking a different design starts from its own colours.
		box.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( 'input[data-iftp-design="advanced"]' ) ) {
				fit( true );
				tint();
			}
		} );

		box.addEventListener( 'input', function ( event ) {
			if ( event.target.matches( '.iftp-btn-color__hex, input[type="color"]' ) ) {
				tint();
			}
		} );

		const reset = box.querySelector( '[data-iftp-advanced-reset]' );

		if ( reset ) {
			reset.addEventListener( 'click', function () {
				fit( true );
				tint();
				fire( field( 0 ) );
			} );
		}

		// Choosing Advanced with nothing picked yet picks the first design, so the button is never left blank.
		form.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( 'input[data-iftp-design="source"]' ) && event.target.value === 'advanced' ) {
				if ( ! picked() && items.length ) {
					const first = items[ 0 ].querySelector( 'input' );

					first.checked = true;
					fit( true );
					fire( first );
				}

				load( drawTiles );
			}
		} );

		fit( false );

		if ( source() === 'advanced' ) {
			load( function () {
				drawTiles();

				// The Default Design screen's preview draws itself from the form, so it is asked to again now there is something to draw with.
				const input = form.querySelector( 'input[data-iftp-design="source"]:checked' );

				if ( input && form.querySelector( '[data-iftp-preview-button]' ) ) {
					input.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
				}
			} );
		}
	}
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
