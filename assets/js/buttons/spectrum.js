/**
 * The Spectrum style's colour stops: a bar with a handle per colour, a row of controls for each, and the same gradient
 * maths as ButtonDesign::spectrum_gradient() so the preview matches what the payer sees.
 */
( function ( ns ) {
	'use strict';

	const { channels, hex } = ns;

	const INK = '#121a2c';

	ns.bindSpectrum = bindSpectrum;
	ns.readStops = readStops;
	ns.setStops = setStops;
	ns.spectrumGradient = spectrumGradient;
	ns.spectrumMiddle = spectrumMiddle;
	ns.spectrumOn = spectrumOn;
	ns.stopsFrom = stopsFrom;

	const clamp = function ( value, low, high ) {
		const number = parseInt( value, 10 );

		return isNaN( number ) ? low : Math.max( low, Math.min( high, number ) );
	};

	// Stored stops come through wp_localize_script() with their numbers as strings.
	function stopsFrom( stored ) {
		return ( Array.isArray( stored ) ? stored : [] ).map( function ( stop ) {
			return {
				color: hex( stop.color ) || '#000000',
				pos: clamp( stop.pos, 0, 100 ),
				spread: clamp( stop.spread, 0, 100 ),
				blur: clamp( stop.blur, 0, 100 ),
			};
		} );
	}

	function sorted( stops ) {
		return stops.slice().sort( function ( a, b ) {
			return a.pos - b.pos;
		} );
	}

	// Same as ButtonDesign::spectrum_gradient(): each colour holds its place, `spread` wide, and `blur` of that melts into its neighbours.
	function spectrumGradient( design ) {
		const parts = [];
		let floor = 0;

		sorted( design.stops ).forEach( function ( stop ) {
			const half = stop.spread / 2;
			const soft = stop.blur / 100 * half;
			const start = stop.pos - half;
			const end = stop.pos + half;
			// A side that runs off the button has no neighbour to melt into.
			const from = Math.max( floor, Math.min( 100, Math.max( 0, start ) + ( start < 0 ? 0 : soft ) ) );
			const to = Math.max( from, Math.min( 100, end - ( end > 100 ? 0 : soft ) ) );

			parts.push( stop.color + ' ' + ( +from.toFixed( 2 ) ) + '%' );

			if ( to > from ) {
				parts.push( stop.color + ' ' + ( +to.toFixed( 2 ) ) + '%' );
			}

			floor = to;
		} );

		const list = parts.join( ',' );

		if ( design.stopsShape === 'radial' ) {
			return 'radial-gradient(ellipse at center,' + list + ')';
		}

		if ( design.stopsShape === 'conic' ) {
			return 'conic-gradient(from ' + design.stopsAngle + 'deg at 50% 50%,' + list + ')';
		}

		return 'linear-gradient(' + design.stopsAngle + 'deg,' + list + ')';
	}

	// The middle stop's colour: what the glow and tints of a spectrum button take.
	function spectrumMiddle( stops ) {
		const ordered = sorted( stops );

		return ordered.length ? ordered[ Math.floor( ordered.length / 2 ) ].color : '#00609c';
	}

	function luminance( color ) {
		const lum = channels( color ).map( function ( channel ) {
			const v = channel / 255;

			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );

		return 0.2126 * lum[ 0 ] + 0.7152 * lum[ 1 ] + 0.0722 * lum[ 2 ];
	}

	// Same as ButtonDesign::spectrum_on_color(): black or white, whichever has the better worst contrast.
	function spectrumOn( stops ) {
		let ink = 22;
		let white = 22;

		stops.forEach( function ( stop ) {
			const lum = luminance( stop.color );

			ink = Math.min( ink, ( lum + 0.05 ) / ( luminance( INK ) + 0.05 ) );
			white = Math.min( white, 1.05 / ( lum + 0.05 ) );
		} );

		return stops.length && ink < white ? '#ffffff' : INK;
	}

	function rowsOf( root ) {
		return Array.prototype.slice.call( root.querySelectorAll( '[data-iftp-stop-row]' ) );
	}

	function readStops( form ) {
		const root = form.querySelector( '[data-iftp-spectrum]' );

		if ( ! root ) {
			return [];
		}

		return rowsOf( root ).map( function ( row ) {
			const get = function ( key ) {
				return row.querySelector( '[data-iftp-stop="' + key + '"]' ).value;
			};

			return {
				color: hex( get( 'color' ) ) || '#000000',
				pos: clamp( get( 'pos' ), 0, 100 ),
				spread: clamp( get( 'spread' ), 0, 100 ),
				blur: clamp( get( 'blur' ), 0, 100 ),
			};
		} );
	}

	function addRow( root, values ) {
		const template = root.querySelector( 'template' );
		const list = root.querySelector( '[data-iftp-spectrum-rows]' );
		const next = ( parseInt( root.dataset.iftpNext, 10 ) || 0 ) + 1;

		root.dataset.iftpNext = String( next );

		const holder = document.createElement( 'div' );

		holder.innerHTML = template.innerHTML.replace( /__i__/g, 'n' + next );

		const row = holder.firstElementChild;

		[ 'color', 'pos', 'spread', 'blur' ].forEach( function ( key ) {
			row.querySelector( '[data-iftp-stop="' + key + '"]' ).value = values[ key ];
		} );

		row.querySelector( '[data-iftp-color-for]' ).value = values.color;
		list.appendChild( row );

		return row;
	}

	// Replaces every row, for a preset or the saved default.
	function setStops( form, stops ) {
		const root = form.querySelector( '[data-iftp-spectrum]' );

		if ( ! root || ! stops || ! stops.length ) {
			return;
		}

		rowsOf( root ).forEach( function ( row ) {
			row.remove();
		} );

		stopsFrom( stops ).forEach( function ( stop ) {
			addRow( root, stop );
		} );

		ns.bindSwatches( form );
		root.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function bindSpectrum( form ) {
		const root = form.querySelector( '[data-iftp-spectrum]' );

		if ( ! root || root.dataset.iftpBound ) {
			return;
		}

		root.dataset.iftpBound = '1';

		const max = parseInt( root.dataset.iftpMax, 10 ) || 8;
		const min = parseInt( root.dataset.iftpMin, 10 ) || 2;
		const bar = root.querySelector( '[data-iftp-spectrum-bar]' );
		const fill = root.querySelector( '[data-iftp-spectrum-fill]' );
		const add = root.querySelector( '[data-iftp-spectrum-add]' );
		const angleBox = root.querySelector( '[data-iftp-spectrum-angle]' );
		const flowBox = root.querySelector( '[data-iftp-spectrum-flow]' );
		let active = null;

		const shape = function () {
			const picked = root.querySelector( '[data-iftp-design="stops_shape"]:checked' );

			return picked ? picked.value : 'linear';
		};

		const handleFor = function ( row ) {
			return bar.querySelector( '[data-iftp-handle="' + row.dataset.iftpStopRow + '"]' );
		};

		const refresh = function () {
			const rows = rowsOf( root );
			const stops = readStops( form );

			// The bar always runs left to right, so a position reads as where it sits on it.
			fill.style.background = spectrumGradient( { stops: stops, stopsShape: 'linear', stopsAngle: 90 } );

			rows.forEach( function ( row, index ) {
				const stop = stops[ index ];
				let handle = handleFor( row );

				if ( ! handle ) {
					handle = document.createElement( 'button' );
					handle.type = 'button';
					handle.className = 'iftp-btn-spectrum__handle';
					handle.dataset.iftpHandle = row.dataset.iftpStopRow;
					bar.appendChild( handle );
				}

				handle.style.left = stop.pos + '%';
				handle.style.setProperty( '--iftp-stop', stop.color );
				handle.setAttribute( 'aria-label', ( ns.i18n.stopAt || 'Colour at %d%' ).replace( '%d', stop.pos ) );
				handle.classList.toggle( 'is-active', row === active );
				row.classList.toggle( 'is-active', row === active );

				[ 'pos', 'spread', 'blur' ].forEach( function ( key ) {
					const out = row.querySelector( '[data-iftp-stop-out="' + key + '"]' );

					if ( out ) {
						out.textContent = stop[ key ] + '%';
					}
				} );

				row.querySelector( '[data-iftp-stop-remove]' ).disabled = rows.length <= min;
			} );

			// A handle whose row was removed.
			Array.prototype.forEach.call( bar.querySelectorAll( '[data-iftp-handle]' ), function ( handle ) {
				if ( ! root.querySelector( '[data-iftp-stop-row="' + handle.dataset.iftpHandle + '"]' ) ) {
					handle.remove();
				}
			} );

			add.disabled = rows.length >= max;

			const angle = root.querySelector( '[data-iftp-design="stops_angle"]' );
			const angleOut = root.querySelector( '[data-iftp-angle-out]' );

			if ( angle && angleOut ) {
				angleOut.textContent = angle.value + '°';
			}

			if ( angleBox ) {
				angleBox.hidden = shape() === 'radial';
			}

			if ( flowBox ) {
				flowBox.hidden = shape() !== 'linear';
			}
		};

		const changed = function () {
			root.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		root.addEventListener( 'input', function ( event ) {
			const row = event.target.closest ? event.target.closest( '[data-iftp-stop-row]' ) : null;

			if ( row ) {
				active = row;
			}

			refresh();
		} );

		root.addEventListener( 'change', refresh );

		// A new colour goes in the widest gap, a step round the colour wheel from its neighbour.
		add.addEventListener( 'click', function () {
			const stops = sorted( readStops( form ) );
			let from = 0;
			let best = -1;
			let colour = '#00609c';
			let at = 50;

			stops.concat( [ { pos: 100, color: colour } ] ).forEach( function ( stop, index ) {
				const gap = stop.pos - from;

				if ( gap > best ) {
					best = gap;
					at = Math.round( from + gap / 2 );
					colour = ns.turn( stops[ Math.max( 0, index - 1 ) ].color, 50 );
				}

				from = stop.pos;
			} );

			active = addRow( root, { color: colour, pos: at, spread: 20, blur: 100 } );
			ns.bindSwatches( form );
			refresh();
			changed();
		} );

		root.addEventListener( 'click', function ( event ) {
			const remove = event.target.closest( '[data-iftp-stop-remove]' );

			if ( ! remove || rowsOf( root ).length <= min ) {
				return;
			}

			const row = remove.closest( '[data-iftp-stop-row]' );

			if ( row === active ) {
				active = null;
			}

			row.remove();
			refresh();
			changed();
		} );

		// Drag a handle along the bar; the arrow keys nudge it.
		const move = function ( row, pos ) {
			const input = row.querySelector( '[data-iftp-stop="pos"]' );

			input.value = clamp( pos, 0, 100 );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		};

		bar.addEventListener( 'pointerdown', function ( event ) {
			const handle = event.target.closest( '[data-iftp-handle]' );

			if ( ! handle ) {
				return;
			}

			const row = root.querySelector( '[data-iftp-stop-row="' + handle.dataset.iftpHandle + '"]' );
			const box = bar.getBoundingClientRect();

			event.preventDefault();
			handle.setPointerCapture( event.pointerId );
			active = row;
			refresh();

			const drag = function ( at ) {
				move( row, Math.round( ( at.clientX - box.left ) / box.width * 100 ) );
			};

			const stop = function () {
				handle.removeEventListener( 'pointermove', drag );
				handle.removeEventListener( 'pointerup', stop );
				handle.removeEventListener( 'pointercancel', stop );
				changed();
			};

			handle.addEventListener( 'pointermove', drag );
			handle.addEventListener( 'pointerup', stop );
			handle.addEventListener( 'pointercancel', stop );
		} );

		bar.addEventListener( 'keydown', function ( event ) {
			const handle = event.target.closest( '[data-iftp-handle]' );
			const step = { ArrowLeft: -1, ArrowDown: -1, ArrowRight: 1, ArrowUp: 1 }[ event.key ];

			if ( ! handle || ! step ) {
				return;
			}

			const row = root.querySelector( '[data-iftp-stop-row="' + handle.dataset.iftpHandle + '"]' );

			event.preventDefault();
			active = row;
			move( row, clamp( row.querySelector( '[data-iftp-stop="pos"]' ).value, 0, 100 ) + step * ( event.shiftKey ? 10 : 1 ) );
		} );

		ns.bindSwatches( form );
		refresh();
	}

} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
