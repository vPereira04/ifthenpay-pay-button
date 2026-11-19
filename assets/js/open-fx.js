/**
 * The openings that are built from layers laid over the popup: Voxel, Waves, Shutter, Curtain, Iris, Ripple, Pixel dissolve,
 * Shatter, Stripes and Confetti. The rest are plain CSS. The checkout and the editor's preview both use this.
 */
( function () {
	'use strict';

	// How long each one takes to close, so the caller knows when to really close.
	const CLOSE_MS = { none: 0, voxel: 700, waves: 730, shutter: 700, curtain: 700, iris: 700, ripple: 710, pixelate: 700, shatter: 700, stripes: 700, confetti: 740 };
	const LAYERED = Object.keys( CLOSE_MS ).filter( function ( key ) {
		return 'none' !== key;
	} );
	const DEFAULT_CLOSE_MS = 450;

	function reduced() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	function nameOf( el ) {
		const found = /iftp-btn-anim--([a-z]+)/.exec( el.className || '' );

		return found ? found[ 1 ] : 'grow';
	}

	function clear( el ) {
		el.querySelectorAll( ':scope > .iftp-btn-fx' ).forEach( function ( layer ) {
			layer.remove();
		} );
	}

	function add( layer, tag, className, vars ) {
		const part = document.createElement( tag );

		if ( className ) {
			part.className = className;
		}

		Object.keys( vars || {} ).forEach( function ( key ) {
			part.style.setProperty( key, vars[ key ] );
		} );
		layer.appendChild( part );

		return part;
	}

	// The layer is made of the popup's own background (its colour, gradient or image), cut into pieces; each piece shows
	// the part of it that lies under that piece, so the cover looks like the popup itself breaking up. The pieces are
	// then lit and shaded in CSS.
	function paint( layer, el ) {
		const style = window.getComputedStyle( el );
		let colour = style.backgroundColor;

		// The cover must hide the form, so a see-through card colour is made solid.
		colour = colour.replace( /rgba\(([^,]+),([^,]+),([^,]+),[^)]+\)/, 'rgb($1,$2,$3)' );

		if ( 'transparent' === colour ) {
			colour = 'var(--iftp-surface, #fff)';
		}

		layer.style.setProperty( '--fx-col', colour );
		layer.style.setProperty( '--fx-img', style.backgroundImage );
		layer.style.setProperty( '--lw', el.offsetWidth + 'px' );
		layer.style.setProperty( '--lh', el.offsetHeight + 'px' );
	}

	// Where a piece sits in the layer, which says which part of the background it shows.
	function at( x, y ) {
		return { '--bx': Math.round( x * 10 ) / 10 + 'px', '--by': Math.round( y * 10 ) / 10 + 'px' };
	}

	function mixed( base, extra ) {
		return Object.assign( {}, base, extra );
	}

	// How far a circle must reach from the middle to cover the whole popup.
	function reach( el ) {
		return Math.ceil( Math.hypot( el.offsetWidth, el.offsetHeight ) * 1.04 );
	}

	// Voxel: blocks flip up from the middle, then fall away from the middle.
	function voxel( layer, el ) {
		const scene = el.classList.contains( 'iftp-btn-sheet--scene' );
		const size = scene ? 64 : 36;
		const cols = Math.max( 4, Math.round( el.offsetWidth / size ) );
		const rows = Math.max( 4, Math.round( el.offsetHeight / size ) );
		const mid = { x: ( cols - 1 ) / 2, y: ( rows - 1 ) / 2 };
		const far = Math.hypot( mid.x, mid.y ) || 1;

		layer.style.setProperty( '--cols', cols );
		layer.style.setProperty( '--rows', rows );

		for ( let row = 0; row < rows; row++ ) {
			for ( let col = 0; col < cols; col++ ) {
				// How far from the middle it sits (0 to 1): the cover builds and then breaks away as a ripple from there.
				const away = Math.hypot( col - mid.x, row - mid.y ) / far;

				add( layer, 'i', '', mixed( at( col * el.offsetWidth / cols, row * el.offsetHeight / rows ), {
					'--d': Math.round( away * 80 + Math.random() * 28 ) + 'ms',
					'--e': Math.round( away * 110 + Math.random() * 40 ) + 'ms',
					'--br': ( 0.96 + Math.random() * 0.08 ).toFixed( 2 ),
					'--r': Math.round( Math.random() * 50 - 25 ) + 'deg'
				} ) );
			}
		}
	}

	// Waves: three layers from each side, the last one in front.
	function waves( layer ) {
		[ 'is-back is-left', 'is-back is-right', 'is-mid is-left', 'is-mid is-right', 'is-front is-left', 'is-front is-right' ].forEach( function ( classes ) {
			add( layer, 'i', classes );
		} );
	}

	// Shutter: slats that flip shut one after another, then flip on to open.
	function shutter( layer, el ) {
		const count = Math.max( 6, Math.min( 14, Math.round( el.offsetHeight / 46 ) ) );

		for ( let i = 0; i < count; i++ ) {
			add( layer, 'i', i % 2 ? 'is-odd' : '', mixed( at( 0, i * el.offsetHeight / count ), { '--i': i, '--n': count } ) );
		}
	}

	// Curtain: a panel from each side, with pleats.
	function curtain( layer ) {
		add( layer, 'i', 'is-left' );
		add( layer, 'i', 'is-right' );
	}

	// Iris: a frame closes in to the middle like an aperture, with a lighter rim leading it, then opens again.
	function iris( layer, el ) {
		layer.style.setProperty( '--diag', reach( el ) + 'px' );
		add( layer, 'i', 'is-ring' );
		add( layer, 'i', 'is-disc' );
	}

	// Ripple: discs spread out one behind another, then each opens a hole from the middle, so rings travel outward.
	function ripple( layer, el ) {
		layer.style.setProperty( '--diag', reach( el ) + 'px' );

		for ( let i = 0; i < 4; i++ ) {
			add( layer, 'i', '', { '--k': i } );
		}
	}

	// Pixel dissolve: small flat tiles that switch on in a random order, then off again.
	function pixelate( layer, el ) {
		const area = el.offsetWidth * el.offsetHeight;
		const size = Math.max( 20, Math.ceil( Math.sqrt( area / 900 ) ) );
		const cols = Math.max( 4, Math.round( el.offsetWidth / size ) );
		const rows = Math.max( 4, Math.round( el.offsetHeight / size ) );

		layer.style.setProperty( '--cols', cols );
		layer.style.setProperty( '--rows', rows );

		for ( let i = 0; i < cols * rows; i++ ) {
			add( layer, 'i', '', mixed( at( ( i % cols ) * el.offsetWidth / cols, Math.floor( i / cols ) * el.offsetHeight / rows ), {
				'--d': Math.round( Math.random() * 230 ) + 'ms',
				'--e': Math.round( Math.random() * 230 ) + 'ms',
				'--br': ( 0.97 + Math.random() * 0.06 ).toFixed( 2 )
			} ) );
		}
	}

	// Shatter: every cell is two glass triangles; they settle in, then fall away from the middle.
	function shatter( layer, el ) {
		const cols = Math.max( 3, Math.round( el.offsetWidth / 92 ) );
		const rows = Math.max( 3, Math.round( el.offsetHeight / 92 ) );
		const mid = { x: ( cols - 1 ) / 2, y: ( rows - 1 ) / 2 };
		const far = Math.hypot( mid.x, mid.y ) || 1;

		layer.style.setProperty( '--cols', cols );
		layer.style.setProperty( '--rows', rows );

		for ( let row = 0; row < rows; row++ ) {
			for ( let col = 0; col < cols; col++ ) {
				const away = Math.hypot( col - mid.x, row - mid.y ) / far;

				[ 'is-a', 'is-b' ].forEach( function ( half ) {
					add( layer, 'i', half, mixed( at( col * el.offsetWidth / cols, row * el.offsetHeight / rows ), {
						'grid-area': ( row + 1 ) + ' / ' + ( col + 1 ),
						'--d': Math.round( Math.random() * 70 + ( 1 - away ) * 60 ) + 'ms',
						'--e': Math.round( away * 60 + Math.random() * 40 ) + 'ms',
						'--x': Math.round( Math.random() * 28 - 14 ) + 'px',
						'--y': Math.round( Math.random() * 28 - 14 ) + 'px',
						'--r': Math.round( Math.random() * 80 - 40 ) + 'deg',
						'--g': Math.round( Math.random() * 360 ) + 'deg',
						'--f': Math.round( 8 + Math.random() * 22 ) + '%'
					} ) );
				} );
			}
		}
	}

	// Stripes: bars grow from alternating edges, then leave through the opposite ones.
	function stripes( layer, el ) {
		const count = Math.max( 6, Math.min( 16, Math.round( el.offsetWidth / 46 ) ) );

		layer.style.setProperty( '--cols', count );

		for ( let i = 0; i < count; i++ ) {
			add( layer, 'i', i % 2 ? 'is-odd' : '', mixed( at( i * el.offsetWidth / count, 0 ), { '--i': i, '--n': count } ) );
		}
	}

	// Confetti: a colour fill with flecks bursting out of the middle, which keep flying once the popup shows.
	function confetti( layer, el ) {
		add( layer, 'b', 'is-fill' );

		for ( let i = 0; i < 46; i++ ) {
			const angle = Math.random() * Math.PI * 2;
			const power = 0.3 + Math.random() * 0.8;

			add( layer, 'i', '', {
				'--x': Math.round( Math.cos( angle ) * power * el.offsetWidth * 0.5 ) + 'px',
				'--y': Math.round( Math.sin( angle ) * power * el.offsetHeight * 0.5 + 40 ) + 'px',
				'--r': Math.round( Math.random() * 900 - 450 ) + 'deg',
				'--w': Math.round( 6 + Math.random() * 8 ) + 'px',
				'--h': Math.round( 10 + Math.random() * 12 ) + 'px',
				'--d': Math.round( Math.random() * 90 ) + 'ms'
			} );
		}
	}

	const BUILDERS = { voxel: voxel, waves: waves, shutter: shutter, curtain: curtain, iris: iris, ripple: ripple, pixelate: pixelate, shatter: shatter, stripes: stripes, confetti: confetti };

	// Puts the layer on el: it covers the popup, then clears. Closing plays the same.
	function build( el, name ) {
		clear( el );

		if ( reduced() || LAYERED.indexOf( name ) < 0 ) {
			return;
		}

		if ( window.getComputedStyle( el ).position === 'static' ) {
			el.style.position = 'relative';
		}

		const layer = document.createElement( 'span' );

		layer.className = 'iftp-btn-fx iftp-btn-fx--' + name;
		layer.setAttribute( 'aria-hidden', 'true' );
		paint( layer, el );
		BUILDERS[ name ]( layer, el );
		el.appendChild( layer );

		// Opening and closing play the same cover-then-clear, so the layer just goes when it is done.
		window.setTimeout( function () {
			layer.remove();
		}, 1000 );
	}

	window.iftpBtnOpenFx = {
		// A popup has just opened.
		open: function ( dialog ) {
			build( dialog, nameOf( dialog ) );
		},

		// A popup is about to close: the layer covers it and clears to the page; says how many ms until it can really close.
		close: function ( dialog ) {
			const name = nameOf( dialog );

			build( dialog, name );

			return reduced() ? 0 : ( Object.prototype.hasOwnProperty.call( CLOSE_MS, name ) ? CLOSE_MS[ name ] : DEFAULT_CLOSE_MS );
		},

		// The editor's preview: plays an opening on its card.
		preview: function ( frame, name ) {
			frame.className = frame.className.replace( /\biftp-btn-anim--[a-z]+\b/g, '' ).trim() + ' iftp-btn-anim--' + name;
			frame.classList.remove( 'is-anim-play' );
			clear( frame );
			// Restarts the animation.
			void frame.offsetWidth;

			if ( reduced() || name === 'none' ) {
				return;
			}

			frame.classList.add( 'is-anim-play' );
			build( frame, name );
			window.setTimeout( function () {
				frame.classList.remove( 'is-anim-play' );
			}, 1200 );
		}
	};
}() );
