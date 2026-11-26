/**
 * Buttons and Checkout screens, the pay page's look: its background samples, the look controls and the message editor.
 */
( function ( ns ) {
	'use strict';

	const { bindSwatches, channels, config, hex, i18n, onColor, perFrame } = ns;

	ns.bindLook = bindLook;
	ns.bindMessages = bindMessages;
	ns.bindOutcomes = bindOutcomes;

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

	// Same liquid as CheckoutLook::liquid(), blob for blob.
	function liquid( size, color, color2, still ) {
		const blobs = [
			[ 180, 220, 90, 140, 90, 19, 0 ],
			[ 420, 140, 68, -110, 120, 23, 1 ],
			[ 760, 260, 105, -130, 100, 21, 0 ],
			[ 860, 640, 83, -90, -140, 25, 1 ],
			[ 560, 820, 113, 120, -90, 17, 0 ],
			[ 200, 700, 98, 110, -120, 24, 1 ],
			[ 500, 480, 60, -160, -60, 15, 0 ],
			[ 680, 520, 53, 140, 110, 20, 1 ],
			[ 320, 460, 45, 80, 140, 18, 0 ],
		];
		const scale = Math.min( 1.8, Math.max( 0.5, size / 140 ) );
		const spline = "calcMode='spline' keyTimes='0;.5;1' keySplines='.45 0 .55 1;.45 0 .55 1' repeatCount='indefinite'";
		let shapes = '';

		const animate = function ( name, from, to, dur ) {
			return "<animate attributeName='" + name + "' values='" + from + ';' + to + ';' + from + "' dur='" + dur + "s' " + spline + '/>';
		};

		blobs.forEach( function ( blob ) {
			const radius = Math.round( blob[ 2 ] * scale );
			const motion = still ? '' : animate( 'cx', blob[ 0 ], blob[ 0 ] + blob[ 3 ], blob[ 5 ] ) + animate( 'cy', blob[ 1 ], blob[ 1 ] + blob[ 4 ], blob[ 5 ] + 4 ) + animate( 'r', radius, Math.round( radius * 1.25 ), blob[ 5 ] + 7 );

			shapes += "<circle cx='" + blob[ 0 ] + "' cy='" + blob[ 1 ] + "' r='" + radius + "' fill='" + ( blob[ 6 ] ? color2 : color ) + "'>" + motion + '</circle>';
		} );

		return svgUrl(
			"<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" +
			"<filter id='g' filterUnits='userSpaceOnUse' x='-200' y='-200' width='1400' height='1400' color-interpolation-filters='sRGB'>" +
			"<feGaussianBlur in='SourceGraphic' stdDeviation='30' result='b'/>" +
			"<feColorMatrix in='b' values='1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 28 -11' result='goo'/>" +
			"<feGaussianBlur in='goo' stdDeviation='9' result='soft'/>" +
			"<feSpecularLighting in='soft' surfaceScale='7' specularConstant='.95' specularExponent='26' lighting-color='#fff' result='light'><feDistantLight azimuth='225' elevation='52'/></feSpecularLighting>" +
			"<feComposite in='light' in2='goo' operator='in' result='shine'/>" +
			"<feComposite in='shine' in2='goo' operator='arithmetic' k2='.85' k3='1'/>" +
			"</filter><g filter='url(#g)'>" + shapes + '</g></svg>'
		);
	}

	// Same mesh as CheckoutLook::mesh(), cloud for cloud.
	function mesh( size, color, color2, still ) {
		const clouds = [
			[ 200, 250, 320, 260, 180, 26, 0 ],
			[ 800, 200, 300, -240, 220, 31, 1 ],
			[ 700, 800, 340, -200, -240, 29, 0 ],
			[ 250, 780, 280, 240, -200, 34, 1 ],
			[ 520, 480, 220, -180, 160, 23, 2 ],
		];
		const scale = Math.min( 1.8, Math.max( 0.5, size / 140 ) );
		const spline = "calcMode='spline' keyTimes='0;.5;1' keySplines='.45 0 .55 1;.45 0 .55 1' repeatCount='indefinite'";
		const tones = [ color, color2, '#ffffff' ];
		let shapes = '';

		const animate = function ( name, from, to, dur ) {
			return "<animate attributeName='" + name + "' values='" + from + ';' + to + ';' + from + "' dur='" + dur + "s' " + spline + '/>';
		};

		clouds.forEach( function ( cloud ) {
			const motion = still ? '' : animate( 'cx', cloud[ 0 ], cloud[ 0 ] + cloud[ 3 ], cloud[ 5 ] ) + animate( 'cy', cloud[ 1 ], cloud[ 1 ] + cloud[ 4 ], cloud[ 5 ] + 6 );

			shapes += "<circle cx='" + cloud[ 0 ] + "' cy='" + cloud[ 1 ] + "' r='" + Math.round( cloud[ 2 ] * scale ) + "' fill='" + tones[ cloud[ 6 ] ] + "'>" + motion + '</circle>';
		} );

		return svgUrl(
			"<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" +
			"<filter id='m' filterUnits='userSpaceOnUse' x='-300' y='-300' width='1600' height='1600'><feGaussianBlur stdDeviation='90'/></filter>" +
			"<g filter='url(#m)' fill-opacity='.85'>" + shapes + '</g></svg>'
		);
	}

	// Same tide as CheckoutLook::tide(), wave for wave.
	function tide( size, color, color2, still ) {
		const layers = [
			[ 620, 40, 500, 16, 1, 30 ],
			[ 700, 50, 640, 12, 0, 38 ],
			[ 780, 36, 420, 10, 1, 48 ],
			[ 860, 46, 520, 8, 0, 60 ],
		];
		const scale = Math.min( 1.8, Math.max( 0.5, size / 140 ) );
		let waves = '';

		layers.forEach( function ( layer, at ) {
			const base = layer[ 0 ];
			const amp = Math.round( layer[ 1 ] * scale );
			const quarter = Math.round( layer[ 2 ] * scale / 4 );
			const start = -4 * quarter * Math.ceil( 1000 / ( 4 * quarter ) ) - 4 * quarter;
			let end = start + 2 * quarter;
			let path = 'M' + start + ' ' + base + 'Q' + ( start + quarter ) + ' ' + ( base - amp ) + ' ' + end + ' ' + base;

			while ( end < 2000 + 4 * quarter ) {
				end += 2 * quarter;
				path += 'T' + end + ' ' + base;
			}

			path += 'L' + end + ' 1200L' + start + ' 1200Z';

			const roll = still ? '' : "<animateTransform attributeName='transform' type='translate' values='0 0;" + ( at % 2 ? -4 : 4 ) * quarter + " 0' dur='" + layer[ 3 ] + "s' repeatCount='indefinite'/>";

			waves += "<path d='" + path + "' fill='" + ( layer[ 4 ] ? color2 : color ) + "' fill-opacity='" + layer[ 5 ] / 100 + "' stroke='#fff' stroke-opacity='.35' stroke-width='2' vector-effect='non-scaling-stroke'>" + roll + '</path>';
		} );

		return svgUrl( "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='none'>" + waves + '</svg>' );
	}

	// Park–Miller, as in the PHP: the same seed gives the same drawing.
	function seeded( seed ) {
		return function () {
			seed = ( seed * 16807 ) % 2147483647;

			return seed / 2147483647;
		};
	}

	// Same flecks as Backgrounds::confetti(), fleck for fleck.
	function confetti( size, color, color2, still ) {
		const scale = Math.min( 1.8, Math.max( 0.5, size / 140 ) );
		const random = seeded( 20261113 );
		const tones = [ color, color2, '#ffffff' ];
		let flecks = '';

		for ( let n = 0; n < 72; n++ ) {
			const x = Math.floor( random() * 1000 );
			const y = Math.floor( random() * 1000 );
			const w = Math.floor( ( 6 + random() * 8 ) * scale );
			const h = Math.floor( ( 10 + random() * 12 ) * scale );
			const tone = Math.floor( random() * 3 );
			const angle = Math.floor( random() * 360 );
			const fall = 8 + Math.floor( random() * 9 );
			const spin = 2 + Math.floor( random() * 4 );
			const from = Math.floor( random() * fall );
			const turn = random() < 0.5 ? 360 : -360;
			const midX = Math.floor( w / 2 );
			const midY = Math.floor( h / 2 );
			const fill = "fill='" + tones[ tone ] + "' fill-opacity='" + ( tone === 2 ? '.8' : '.88' ) + "'";

			if ( still ) {
				flecks += "<g transform='translate(" + x + ' ' + y + ")'><rect width='" + w + "' height='" + h + "' rx='1' " + fill + " transform='rotate(" + angle + ' ' + midX + ' ' + midY + ")'/></g>";
				continue;
			}

			flecks += "<g transform='translate(" + x + " -40)'><g><animateTransform attributeName='transform' type='translate' values='0 0;0 1100' dur='" + fall + "s' begin='-" + from + "s' repeatCount='indefinite'/>" +
				"<rect width='" + w + "' height='" + h + "' rx='1' " + fill + "><animateTransform attributeName='transform' type='rotate' values='" + angle + ' ' + midX + ' ' + midY + ';' + ( angle + turn ) + ' ' + midX + ' ' + midY + "' dur='" + spin + "s' repeatCount='indefinite'/></rect></g></g>";
		}

		return svgUrl( "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" + flecks + '</svg>' );
	}

	// Same bubbles as Backgrounds::bubbles(), bubble for bubble.
	function bubbles( size, color, color2, still ) {
		const scale = Math.min( 1.8, Math.max( 0.5, size / 140 ) );
		const random = seeded( 20261114 );
		const tones = [ color, color2 ];
		let list = '';

		for ( let n = 0; n < 40; n++ ) {
			const x = Math.floor( random() * 1000 );
			const y = Math.floor( random() * 1000 );
			const grow = random();
			const r = Math.max( 3, Math.floor( ( 7 + grow * grow * 40 ) * scale ) );
			const tone = Math.floor( random() * 2 );
			const rise = 12 + Math.floor( random() * 17 );
			const from = Math.floor( random() * rise );
			const sway = 6 + Math.floor( random() * 19 );
			const beat = 3 + Math.floor( random() * 5 );
			const glow = Math.floor( r * 0.35 );
			const glit = Math.max( 1, Math.floor( r * 0.18 ) );
			const body = "<circle r='" + r + "' fill='" + tones[ tone ] + "' fill-opacity='.07' stroke='" + tones[ tone ] + "' stroke-opacity='.55' stroke-width='1.6'/><circle cx='-" + glow + "' cy='-" + glow + "' r='" + glit + "' fill='#ffffff' fill-opacity='.55'/>";

			if ( still ) {
				list += "<g transform='translate(" + x + ' ' + y + ")'>" + body + '</g>';
				continue;
			}

			list += "<g transform='translate(" + x + ' ' + ( 1040 + r ) + ")'><g><animateTransform attributeName='transform' type='translate' values='0 0;0 -" + ( 1080 + 2 * r ) + "' dur='" + rise + "s' begin='-" + from + "s' repeatCount='indefinite'/>" +
				"<g><animateTransform attributeName='transform' type='translate' values='0 0;" + sway + ' 0;0 0;-' + sway + " 0;0 0' dur='" + beat + "s' repeatCount='indefinite'/>" + body + '</g></g></g>';
		}

		return svgUrl( "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" + list + '</svg>' );
	}

	// Same lights as Backgrounds::fireflies(), light for light.
	function fireflies( size, color, color2, still ) {
		const scale = Math.min( 1.8, Math.max( 0.5, size / 140 ) );
		const random = seeded( 20261115 );
		const tone = color2 !== '' ? color2 : color;
		const defs = "<defs><radialGradient id='f'><stop offset='0' stop-color='#ffffff' stop-opacity='.95'/><stop offset='.2' stop-color='" + tone + "' stop-opacity='.7'/><stop offset='1' stop-color='" + tone + "' stop-opacity='0'/></radialGradient></defs>";
		let lights = '';

		for ( let n = 0; n < 28; n++ ) {
			const x = Math.floor( random() * 1000 );
			const y = Math.floor( random() * 1000 );
			const r = Math.floor( ( 16 + random() * 30 ) * scale );
			const blink = 2 + Math.floor( random() * 5 );
			const from = Math.floor( random() * blink );
			const dx = Math.floor( random() * 160 ) - 80;
			const dy = Math.floor( random() * 160 ) - 80;
			const drift = 14 + Math.floor( random() * 21 );

			if ( still ) {
				lights += "<circle cx='" + x + "' cy='" + y + "' r='" + r + "' fill='url(#f)' opacity='.8'/>";
				continue;
			}

			lights += "<g transform='translate(" + x + ' ' + y + ")'><g><animateTransform attributeName='transform' type='translate' values='0 0;" + dx + ' ' + dy + ";0 0' dur='" + drift + "s' repeatCount='indefinite'/>" +
				"<circle r='" + r + "' fill='url(#f)' opacity='.2'><animate attributeName='opacity' values='.15;1;.15' dur='" + blink + "s' begin='-" + from + "s' repeatCount='indefinite'/></circle></g></g>";
		}

		return svgUrl( "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" + defs + lights + '</svg>' );
	}

	// Same as CheckoutLook::moving(): the effects that move, or their still versions.
	function moving( fx, size, color, color2, still ) {
		if ( fx === 'liquid' ) {
			return liquid( size, color, color2, still );
		}

		if ( fx === 'mesh' ) {
			return mesh( size, color, color2, still );
		}

		if ( fx === 'tide' ) {
			return tide( size, color, color2, still );
		}

		if ( fx === 'confetti' ) {
			return confetti( size, color, color2, still );
		}

		if ( fx === 'bubbles' ) {
			return bubbles( size, color, color2, still );
		}

		if ( fx === 'fireflies' ) {
			return fireflies( size, color, color2, still );
		}

		return 'none';
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
			pixel: [ 'var(--iftp-chip-bg)', 'var(--iftp-ink)', '3px', '0', '12px' ],
			well: [ 'var(--iftp-inset)', 'transparent', '1px', null, '14px', 'solid' ],
			stamp: [ 'transparent', 'var(--iftp-line-strong)', '2px', null, '12px', 'dashed' ],
			marker: [ 'var(--iftp-inset)', 'var(--iftp-brand)', '0 0 0 4px', '0 8px 8px 0', '14px' ],
			doily: [ 'radial-gradient(ellipse at 50% 50%, color-mix(in srgb, var(--iftp-brand) 9%, var(--iftp-chip-bg)), var(--iftp-chip-bg) 78%)', 'color-mix(in srgb, var(--iftp-brand) 20%, var(--iftp-line-strong))', '1px', null, '18px' ],
		};
		// Same as CheckoutLook::card_awning(): eight stripes with a scalloped valance, as a layer over the card's top.
		const cardAwning = function ( name ) {
			if ( name !== 'awning' ) {
				return { '--iftp-edge-on': 'none', '--iftp-edge-pos': 'relative', '--iftp-edge-h': '0px', '--iftp-edge-bg': 'none', '--iftp-edge-fx': 'none', '--iftp-card-pad': '0px' };
			}

			const white = 'color-mix(in srgb, var(--iftp-brand) 6%, #fff)';

			return {
				'--iftp-edge-on': '""',
				'--iftp-edge-pos': 'relative',
				'--iftp-edge-h': '36px',
				'--iftp-edge-bg': [
					'radial-gradient(ellipse 25% 100% at 25% 0, var(--iftp-brand) 97%, transparent) 0 23px / 25% 13px repeat-x',
					'radial-gradient(ellipse 25% 100% at 75% 0, ' + white + ' 97%, transparent) 0 23px / 25% 13px repeat-x',
					'linear-gradient(rgb(255 255 255 / 32%), rgb(255 255 255 / 0%) 55%) 0 0 / 100% 24px no-repeat',
					'repeating-linear-gradient(90deg, var(--iftp-brand) 0 12.5%, ' + white + ' 12.5% 25%) 0 0 / 100% 24px no-repeat',
				].join( ', ' ),
				'--iftp-edge-fx': 'drop-shadow(0 3px 3px rgb(0 0 0 / 22%))',
				'--iftp-card-pad': '32px',
			};
		};
		const systemFont = '-apple-system, blinkmacsystemfont, "Segoe UI", "Inter", roboto, "Helvetica Neue", arial, sans-serif';

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
		// Configuration has no look controls at all, so it always shows the site's.
		const siteMode = function () {
			const mode = form.querySelector( '[data-iftp-look-mode]:checked' );

			if ( ! mode && ! form.querySelector( '[data-iftp-look]' ) ) {
				return !! config.siteLook;
			}

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

		let view = 'page';

		// How a payer on a light or dark device sees it: the Light / System / Dark pick shared by every preview, light until chosen.
		const schemes = window.iftpBtnScheme;
		let scheme = schemes ? schemes.effective() : 'light';
		const patterns = {};

		// The pay page shows the same checkout card, on the page's background instead of over a dimmed site.
		const show = function ( next ) {
			// There is no separate checkout view any more: it is the pay page.
			view = next === 'checkout' ? 'page' : next;
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

		// Same as the deepening in CheckoutLook::dark_page(): the colour's hue and saturation (capped), at most 16% light.
		const deepen = function ( color ) {
			const c = channels( color ).map( function ( v ) {
				return v / 255;
			} );
			const max = Math.max( c[ 0 ], c[ 1 ], c[ 2 ] );
			const min = Math.min( c[ 0 ], c[ 1 ], c[ 2 ] );
			const span = max - min;
			let light = ( max + min ) / 2;
			let sat = span === 0 ? 0 : span / ( 1 - Math.abs( 2 * light - 1 ) );
			let hue = 0;

			if ( span !== 0 ) {
				hue = max === c[ 0 ] ? ( ( c[ 1 ] - c[ 2 ] ) / span ) % 6 : max === c[ 1 ] ? ( c[ 2 ] - c[ 0 ] ) / span + 2 : ( c[ 0 ] - c[ 1 ] ) / span + 4;
			}

			hue = ( hue * 60 + 360 ) % 360;
			light = Math.min( light, 0.16 );
			sat = Math.min( sat, 0.6 );

			const chroma = ( 1 - Math.abs( 2 * light - 1 ) ) * sat;
			const x = chroma * ( 1 - Math.abs( ( ( hue / 60 ) % 2 ) - 1 ) );
			const m = light - chroma / 2;
			const rgb = [ [ chroma, x, 0 ], [ x, chroma, 0 ], [ 0, chroma, x ], [ 0, x, chroma ], [ x, 0, chroma ], [ chroma, 0, x ] ][ Math.floor( hue / 60 ) % 6 ];

			return '#' + rgb.map( function ( v ) {
				return ( '0' + Math.round( ( v + m ) * 255 ).toString( 16 ) ).slice( -2 );
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
					patterns[ key ] = moving( fx, size, accent, second, false );
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

			// Same as CheckoutLook::variables(): the card's edge, then the glass ring.
			const cardRingParts = function ( opacity ) {
				const longColor = 'color-mix(in srgb, var(--iftp-brand) 35%, var(--iftp-surface))';
				const edges = {
					hairline: [ 'inset 0 0 0 1px color-mix(in srgb, var(--iftp-second) 65%, transparent)' ],
					gilded: [
						'inset 0 0 0 1px color-mix(in srgb, var(--iftp-second) 70%, transparent)',
						'inset 0 1px 0 0 rgb(255 255 255 / 28%)',
						'0 0 0 1px color-mix(in srgb, var(--iftp-second) 28%, transparent)',
						'0 22px 60px -22px color-mix(in srgb, var(--iftp-second) 55%, transparent)',
					],
					pixel: [ 'inset 0 0 0 4px var(--iftp-ink)', '8px 8px 0 0 color-mix(in srgb, var(--iftp-ink) 35%, transparent)' ],
					matte: [
						'inset 0 0 0 8px color-mix(in srgb, var(--iftp-brand) 9%, transparent)',
						'inset 0 0 0 9px color-mix(in srgb, var(--iftp-brand) 35%, transparent)',
					],
					glow: [
						'inset 0 0 0 1px color-mix(in srgb, var(--iftp-brand) 80%, transparent)',
						'0 0 0 1px color-mix(in srgb, var(--iftp-brand) 40%, transparent)',
						'0 0 26px 2px color-mix(in srgb, var(--iftp-brand) 45%, transparent)',
					],
					awning: [ 'inset 0 0 0 1px color-mix(in srgb, var(--iftp-brand) 28%, transparent)' ],
					long: [ 3, 6, 9, 12 ].map( function ( step ) {
						return step + 'px ' + step + 'px 0 0 ' + longColor;
					} ),
				};

				return edges[ read( 'frame' ) ] || ( opacity < 100 ? [ 'inset 0 0 0 1px rgb(255 255 255 / 22%)' ] : [] );
			};
			const joinRing = function ( parts ) {
				return parts.length ? parts.join( ', ' ) : '0 0 #0000';
			};
			const cardRing = function ( opacity ) {
				return joinRing( cardRingParts( opacity ) );
			};
			// Same as CheckoutLook::variables(): the button wears the fields' and the card edge's touch too.
			const sharp = read( 'frame' ) === 'pixel' || read( 'fields' ) === 'pixel';
			const fieldBevels = {
				well: 'inset 0 -4px 0 0 rgb(0 0 0 / 24%), inset 0 2px 0 0 rgb(255 255 255 / 22%)',
				marker: 'inset 6px 0 0 0 rgb(0 0 0 / 28%)',
				stamp: 'inset 0 0 0 3px var(--iftp-surface)',
				doily: 'inset 0 0 0 3px var(--iftp-brand), inset 0 0 0 4px color-mix(in srgb, var(--iftp-on-brand) 45%, transparent)',
			};
			const edgeBevels = {
				matte: 'inset 0 0 0 2px color-mix(in srgb, var(--iftp-on-brand) 35%, transparent)',
				glow: '0 0 18px -2px color-mix(in srgb, var(--iftp-brand) 60%, transparent)',
				long: '3px 3px 0 0 color-mix(in srgb, var(--iftp-brand) 35%, var(--iftp-surface))',
			};
			const submitBevel = [
				sharp ? 'inset -4px -4px 0 rgb(0 0 0 / 26%), inset 4px 4px 0 rgb(255 255 255 / 28%)' : fieldBevels[ read( 'fields' ) ] || '',
				edgeBevels[ read( 'frame' ) ] || '',
			].filter( Boolean );
			const darkOn = read( 'dark' ) === 'auto';
			const darkAccentAuto = !! read( 'dark_accent_auto' );
			const darkAccent = darkAccentAuto ? accent : hex( read( 'dark_accent' ) ) || accent;

			// Same as CheckoutLook::skin_layers(): the card design's background layers under the card colour.
			const skin = ( config.lookSkins || {} )[ read( 'card_skin' ) ];
			const skinLayers = skin && skin.bg ? skin.bg + ', ' : '';

			// Same as CheckoutLook::palette().
			const palette = function ( c ) {
				return {
					'--iftp-brand': c.accent,
					'--iftp-on-brand': c.onBrand,
					'--iftp-surface': c.surface,
					'--iftp-ink': c.ink,
					'--iftp-card-bg': skinLayers + ( card < 100 ? alpha( c.surface, card ) : c.surface ),
					'--iftp-chip-bg': card < 100 ? alpha( c.surface, Math.min( 100, card + 30 ) ) : c.surface,
					'--iftp-logo-filter': onColor( c.surface ) === '#ffffff' ? 'brightness(0) invert(1)' : 'none',
					'--iftp-page-bg': c.page,
					'--iftp-page-glow': alpha( c.accent, 16 ),
					'--iftp-page-glow2': alpha( c.second, 16 ),
					'--iftp-page-orb': alpha( c.accent, 45 ),
					'--iftp-page-orb2': alpha( c.second, 45 ),
					'--iftp-second': c.second,
				};
			};

			let colors = palette( { accent, onBrand, surface, ink, page, second } );

			// Same as CheckoutLook::dark_palette(). With dark mode off, a dark device still gets the light look.
			if ( scheme === 'dark' && darkOn ) {
				const darkSurface = hex( read( 'dark_surface' ) ) || '#131a2a';
				const darkInk = hex( read( 'dark_ink' ) ) || '#eef2f8';

				// Same as CheckoutLook::dark_page(): their own pick, else the light page colour kept in its hue but deep.
				const lightPage = pageAuto ? '' : hex( read( 'pay_color' ) );
				const darkPage = ! read( 'dark_page_auto' ) && hex( read( 'dark_page' ) ) ? hex( read( 'dark_page' ) ) : lightPage ? deepen( lightPage ) : pageColor( darkInk, darkSurface );

				colors = palette( {
					accent: darkAccent,
					onBrand: darkAccentAuto ? onBrand : onColor( darkAccent ),
					surface: darkSurface,
					ink: darkInk,
					page: darkPage,
					second: ownSecond || darkAccent,
				} );
			}

			const vars = Object.assign( colors, cardAwning( read( 'frame' ) ), {
				'--iftp-paid': hex( read( 'paid' ) ) || '#0b6b35',
				'--iftp-pending': hex( read( 'pending' ) ) || '#8a5300',
				'--iftp-failed': hex( read( 'failed' ) ) || '#a3201f',
				'--iftp-font': ( config.lookFonts || {} )[ read( 'font' ) ] || systemFont,
				'--iftp-radius': ( parseInt( read( 'radius' ), 10 ) || 0 ) + 'px',
				'--iftp-field-r': field[ 3 ] === null ? ( read( 'fields' ) === 'doily' ? Math.max( 10, parseInt( fieldRadius, 10 ) || 0 ) + 'px' : fieldRadius ) : field[ 3 ],
				'--iftp-field-bg': field[ 0 ],
				'--iftp-field-bd': field[ 1 ],
				'--iftp-field-bw': field[ 2 ],
				'--iftp-field-bs': field[ 5 ] || 'solid',
				'--iftp-field-shadow': read( 'fields' ) === 'well' ? 'inset 0 2px 5px color-mix(in srgb, var(--iftp-ink) 22%, transparent)' : '0 0 0 0 transparent',
				'--iftp-field-outline': read( 'fields' ) === 'doily' ? '1px dotted color-mix(in srgb, var(--iftp-brand) 75%, transparent)' : 'none',
				'--iftp-field-outline-o': read( 'fields' ) === 'doily' ? '-5px' : '0px',
				'--iftp-field-px': field[ 4 ],
				'--iftp-field-focus': { underline: '0 1px 0 0 var(--iftp-brand)', pixel: '4px 4px 0 0 var(--iftp-brand)', well: 'inset 0 2px 5px color-mix(in srgb, var(--iftp-ink) 22%, transparent), var(--iftp-ring)' }[ read( 'fields' ) ] || 'var(--iftp-ring)',
				'--iftp-submit-r': sharp ? '0' : read( 'fields' ) === 'marker' ? '0 8px 8px 0' : 'calc(var(--iftp-field-r) + 2px)',
				'--iftp-submit-bevel': submitBevel.length ? submitBevel.join( ', ' ) : 'none',
				'--iftp-submit-bd': read( 'fields' ) === 'stamp' ? '2px dashed var(--iftp-brand)' : '0',
				'--iftp-submit-clip': read( 'fields' ) === 'stamp' ? 'padding-box' : 'border-box',
				'--iftp-sheet-w': ( ( config.lookWidths || {} )[ read( 'width' ) ] || 440 ) + 'px',
				'--iftp-result-align': read( 'popup_align' ) === 'left' ? 'start' : 'center',
				'--iftp-card-filter': card < 100 && glassBlur > 0 ? 'blur(' + glassBlur + 'px) saturate(1.6)' : 'none',
				'--iftp-card-ring': cardRing( card ),
				'--iftp-card-ring-in': joinRing( cardRingParts( card ).filter( function ( shadow ) {
					return shadow.indexOf( 'inset' ) === 0;
				} ) ),
				'--iftp-skin-a': String( Math.max( 0, Math.min( 100, parseInt( read( 'card_skin_strength' ), 10 ) ) ) / 100 ),
				'--iftp-mark-size': Math.max( 40, Math.min( 480, parseInt( read( 'pay_mark_size' ), 10 ) || 160 ) ) + 'px',
				'--iftp-body-bg': card < 100 || read( 'card_skin' ) !== 'none' ? 'transparent' : 'initial',
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
			vars[ '--iftp-page-pattern-still' ] = moving( read( 'pay_fx' ), crystalSize, accent, second, true );
			vars[ '--iftp-page-pattern-tile' ] = crystalSize * 6 + 'px';
			vars[ '--iftp-page-fx-light' ] = 'rgb(255 255 255 / ' + Math.round( strength * 0.5 ) + '%)';
			vars[ '--iftp-page-fx-dark' ] = 'rgb(0 0 0 / ' + Math.round( strength * 0.28 ) + '%)';
			vars[ '--iftp-page-fx-opacity' ] = String( Math.round( strength ) / 100 );
			vars[ '--iftp-page-blur' ] = blur;
			vars[ '--iftp-page-blur-filter' ] = blurType === 'frosted' ? 'blur(' + blur + ') saturate(1.6) brightness(1.06)' : 'blur(' + blur + ')';
			vars[ '--iftp-page-blur-mask' ] = masks[ blurType ] || 'none';
			vars[ '--iftp-page-frost' ] = blurType === 'frosted' ? 'rgb(255 255 255 / 16%)' : 'transparent';

			// The step style and effect cards wear the look too.
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
				pageLayer.className = 'iftp-btn-look-preview__pagebg iftp-btn-pagebg--' + background + ' iftp-btn-pagegrad--' + ( read( 'pay_grad' ) || 'soft' ) + ' iftp-btn-pagefx--' + ( read( 'pay_fx' ) || 'none' ) + ' iftp-btn-pageblur--' + blurType + ( read( 'pay_fx_still' ) ? ' iftp-btn-pagestill' : '' );
				// Only while the background is on show, so a hidden preview doesn't keep a video playing.
				showVideo( pageLayer, background === 'video' && ( view === 'page' || read( 'scene' ) ) ? video : null );
			}

			// The effect cards: each its own pattern, scaled to the card, over the background the look has now.
			const fxBackground = [ 'glow', 'plain', 'gradient' ].indexOf( background ) > -1 ? background : 'glow';
			const fxSize = Math.max( 16, Math.round( crystalSize * 0.4 ) );

			form.querySelectorAll( '[data-iftp-fx-sample]' ).forEach( function ( sample ) {
				const fx = sample.dataset.iftpFxSample;
				// The moving ones cover the card rather than tiling, so they keep their full size.
				const size = [ 'liquid', 'mesh', 'tide', 'confetti', 'bubbles', 'fireflies' ].indexOf( fx ) > -1 ? crystalSize : fxSize;

				sample.style.setProperty( '--iftp-page-crystal', fxSize + 'px' );
				sample.style.setProperty( '--iftp-page-pattern-tile', fxSize * 6 + 'px' );
				sample.style.setProperty( '--iftp-page-pattern', pattern( fx, size, strength, accent, second ) );
				sample.style.setProperty( '--iftp-page-pattern-still', moving( fx, size, accent, second, true ) );
				sample.className = 'iftp-btn-fxstyles__sample iftp-btn-pagebg--' + fxBackground + ' iftp-btn-pagegrad--' + ( read( 'pay_grad' ) || 'soft' ) + ' iftp-btn-pagefx--' + fx + ( read( 'pay_fx_still' ) ? ' iftp-btn-pagestill' : '' );
			} );

			const secondBox = form.querySelector( '[data-iftp-second-color]' );

			if ( secondBox && ! siteMode() ) {
				secondBox.classList.toggle( 'is-auto', !! read( 'pay_color2_auto' ) );

				if ( read( 'pay_color2_auto' ) ) {
					set( 'pay_color2', accent );
				}
			}

			const darkPageBox = form.querySelector( '[data-iftp-dark-page]' );

			if ( darkPageBox && ! siteMode() ) {
				darkPageBox.classList.toggle( 'is-auto', !! read( 'dark_page_auto' ) );
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

			// The picture above or in the card: the site's logo or the picked image, placed where the look says.
			const mark = frame.querySelector( '[data-iftp-look-chrome="pay_logo"]' );
			const markInput = form.querySelector( '[data-iftp-look="pay_mark_id"]' );
			const markUrl = read( 'pay_mark' ) === 'image' && markInput ? markInput.dataset.url || '' : '';

			if ( mark ) {
				if ( undefined === mark.dataset.siteHtml ) {
					mark.dataset.siteHtml = mark.innerHTML;
				}

				if ( markUrl ) {
					mark.innerHTML = '<img class="iftp-btn-page__logo-image" alt="">';
					mark.firstChild.src = markUrl;
				} else {
					mark.innerHTML = mark.dataset.siteHtml;
				}

				const inside = read( 'pay_mark_place' ) === 'inside';
				const cardEl = frame.querySelector( '.iftp-btn-look-preview__sheet .iftp-btn-form' );

				mark.classList.toggle( 'iftp-btn-page__logo--custom', !! markUrl );
				mark.classList.toggle( 'iftp-btn-form__mark', inside );
				mark.classList.toggle( 'iftp-btn-page__logo', ! inside );

				if ( inside && cardEl && mark.parentNode !== cardEl ) {
					cardEl.insertBefore( mark, cardEl.firstChild );
				} else if ( ! inside && mark.parentNode !== frame ) {
					frame.insertBefore( mark, frame.firstChild );
				}
			}

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

				[ 'accent', 'surface', 'ink', 'radius', 'field_radius', 'fields', 'frame', 'font', 'pay_grad', 'pay_fx', 'pay_fx_size', 'pay_fx_strength', 'pay_fx_still', 'card_opacity', 'card_blur' ].forEach( function ( key ) {
					set( key, preset[ key ] );
				} );
				set( 'accent_text_auto', true );

				// The dark side: a look without one (the dark looks) turns dark mode off, the others bring their colours.
				set( 'dark', preset.dark || 'off' );

				if ( preset.dark ) {
					set( 'dark_surface', preset.dark_surface );
					set( 'dark_ink', preset.dark_ink );
					set( 'dark_accent_auto', ! preset.dark_accent );
					set( 'dark_accent', preset.dark_accent || read( 'accent' ) );
					set( 'dark_page_auto', ! preset.dark_page );
					set( 'dark_page', preset.dark_page || preset.dark_surface );
				}

				const darkPicked = form.querySelector( '[data-iftp-look="dark"]:checked' );

				if ( darkPicked ) {
					darkPicked.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}

				// Only the Pixel look sets the progress style; picking another look drops a pixel one.
				if ( preset.steps ) {
					set( 'steps', preset.steps );
				} else if ( read( 'steps' ) === 'pixel' ) {
					set( 'steps', 'bars' );
				}

				// The background too; a blank colour means "auto", like the boxes' own checkboxes.
				set( 'pay_bg', preset.pay_bg );
				[ 'pay_color', 'pay_color2' ].forEach( function ( key ) {
					set( key + '_auto', ! preset[ key ] );

					if ( preset[ key ] ) {
						set( key, preset[ key ] );
					}
				} );

				// Radios that show or hide parts of the card (background, effect) need their change to fire.
				[ 'pay_bg', 'pay_fx' ].forEach( function ( key ) {
					const picked = form.querySelector( '[data-iftp-look="' + key + '"]:checked' );

					if ( picked ) {
						picked.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				} );

				const accent = form.querySelector( '[data-iftp-look="accent"]' );

				accent.dispatchEvent( new Event( 'change', { bubbles: true } ) );

				// The colour pickers follow their boxes, auto ones included now that apply() filled them.
				form.querySelectorAll( '[data-iftp-color-for]' ).forEach( function ( swatch ) {
					const field = form.querySelector( '#' + swatch.dataset.iftpColorFor );
					const color = field && field.matches( '[data-iftp-look]' ) ? hex( field.value ) : '';

					if ( color ) {
						swatch.value = color;
					}
				} );

				show( 'page' );
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

		ns.bindReveal( form, 'input[data-iftp-look="pay_bg"]', 'data-iftp-when-bg' );
		ns.bindReveal( form, 'input[data-iftp-look="pay_fx"]', 'data-iftp-when-fx' );
		ns.bindReveal( form, 'input[data-iftp-look="pay_blur_type"]', 'data-iftp-when-blur' );
		ns.bindReveal( form, 'input[data-iftp-look="scene"]', 'data-iftp-when-scene' );
		ns.bindReveal( form, 'input[data-iftp-look="pay_video_src"]', 'data-iftp-when-vsrc' );
		ns.bindReveal( form, 'input[data-iftp-look="pay_video_loop"]', 'data-iftp-when-loop' );
		ns.bindReveal( form, 'input[data-iftp-look="dark"]', 'data-iftp-when-dark' );
		ns.bindReveal( form, 'input[data-iftp-look="pay_logo"]', 'data-iftp-when-logo' );
		ns.bindReveal( form, 'input[data-iftp-look="pay_mark"]', 'data-iftp-when-mark' );

		const pickScheme = function ( next ) {
			if ( schemes ) {
				// Only for now: working on the dark colours shows them without changing the mode saved for the next preview.
				schemes.set( next, false );
			} else {
				scheme = next;
				apply();
			}
		};

		if ( schemes ) {
			schemes.subscribe( function ( mode, shown ) {
				scheme = shown;
				apply();
			} );
		}

		// Working on the dark colours shows them.
		form.querySelectorAll( '[data-iftp-look-section="dark"]' ).forEach( function ( section ) {
			section.addEventListener( 'focusin', function () {
				if ( scheme !== 'dark' ) {
					pickScheme( 'dark' );
				}
			} );
		} );

		// Touching the dark page colour means they want their own.
		[ form.querySelector( '[data-iftp-look="dark_page"]' ), form.querySelector( '[data-iftp-color-for="iftp-btn-look-dark_page"]' ) ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					const auto = form.querySelector( '[data-iftp-look="dark_page_auto"]' );

					if ( auto && auto.checked ) {
						auto.checked = false;
					}
				} );
			}
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
		ns.bindReveal( form, '[data-iftp-look-mode]', 'data-iftp-when-lookmode' );

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

		// The logo or picture shown with the card: its own media picker, working like the background image's.
		const markPick = form.querySelector( '[data-iftp-mark-pick]' );
		const markClear = form.querySelector( '[data-iftp-mark-clear]' );
		const markThumb = form.querySelector( '[data-iftp-mark-thumb]' );
		const markField = form.querySelector( '[data-iftp-look="pay_mark_id"]' );
		let markFrame = null;

		const setMark = function ( id, full, small ) {
			markField.value = id;
			markField.dataset.url = full;
			markThumb.style.backgroundImage = small ? 'url("' + small.replace( /["\\]/g, '' ) + '")' : '';
			markClear.hidden = ! id;
			markField.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		if ( markPick && markField && window.wp && window.wp.media ) {
			markPick.addEventListener( 'click', function () {
				if ( ! markFrame ) {
					markFrame = window.wp.media( {
						title: i18n.pickImage,
						button: { text: i18n.useImage },
						library: { type: 'image' },
						multiple: false,
					} );

					markFrame.on( 'select', function () {
						const chosen = markFrame.state().get( 'selection' ).first().toJSON();
						const sizes = chosen.sizes || {};

						setMark( String( chosen.id ), ( sizes.large || chosen ).url, ( sizes.medium || sizes.full || chosen ).url );
					} );
				}

				markFrame.open();
			} );

			markClear.addEventListener( 'click', function () {
				setMark( '0', '', '' );
			} );
		}

		// "Default look": the look saved for the site, all of it, image and video included.
		form.querySelectorAll( '[data-iftp-look-saved]' ).forEach( function ( item ) {
			let saved = null;

			try {
				saved = JSON.parse( item.dataset.iftpLookSaved );
			} catch ( e ) {}

			if ( ! saved ) {
				return;
			}

			const clock = function ( seconds ) {
				const total = parseInt( seconds, 10 ) || 0;

				return total > 0 ? Math.floor( total / 60 ) + ':' + String( total % 60 ).padStart( 2, '0' ) : '';
			};

			item.addEventListener( 'click', function () {
				const skip = [ 'css', 'pay_image', 'pay_image_url', 'pay_video', 'pay_video_file_url', 'pay_mark_id' ];

				Object.keys( saved ).forEach( function ( key ) {
					if ( skip.indexOf( key ) !== -1 ) {
						return;
					}

					let value = saved[ key ];

					if ( key === 'pay_video_start' || key === 'pay_video_end' ) {
						value = clock( value );
					}

					// Blank colours mean "auto", like the boxes' own checkboxes.
					set( key + '_auto', value === '' );

					if ( value !== '' || ! form.querySelector( '[data-iftp-look="' + key + '_auto"]' ) ) {
						set( key, value );
					}
				} );

				if ( imageField ) {
					setImage( String( saved.pay_image || 0 ), saved.pay_image_url || '', saved.pay_image_url || '' );
				}

				if ( videoField ) {
					const file = saved.pay_video_file_url || '';

					setVideo( String( saved.pay_video || 0 ), file, file.split( '/' ).pop() );
				}

				form.querySelectorAll( 'input[data-iftp-look][type="radio"]:checked' ).forEach( function ( picked ) {
					picked.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );

				const accent = form.querySelector( '[data-iftp-look="accent"]' );

				if ( accent ) {
					accent.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}

				form.querySelectorAll( '[data-iftp-color-for]' ).forEach( function ( swatch ) {
					const field = form.querySelector( '#' + swatch.dataset.iftpColorFor );
					const color = field && field.matches( '[data-iftp-look]' ) ? hex( field.value ) : '';

					if ( color ) {
						swatch.value = color;
					}
				} );

				show( 'page' );
			} );
		} );

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

		// The opening animation: Play shows it on the preview card, and picking one plays it too.
		const animFrame = document.querySelector( '[data-iftp-look-frame]' );
		const replay = form.querySelector( '[data-iftp-look-replay]' );

		function playAnim() {
			if ( animFrame && window.iftpBtnOpenFx ) {
				window.iftpBtnOpenFx.preview( animFrame, read( 'anim' ) || 'grow' );
			}
		}

		if ( replay ) {
			replay.addEventListener( 'click', playAnim );
		}

		form.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.matches && event.target.matches( '[data-iftp-look="anim"]' ) ) {
				playAnim();
			}
		} );

		form.addEventListener( 'input', perFrame( apply ) );
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

	/* ---------------------------------------------------------------- Configuration and Default Design */

	// Messages show one outcome at a time, and the preview follows the pick. Returns a function that
	// shows the picked outcome's popup, for when the Messages come on screen.
	function bindOutcomes( root ) {
		const picks = root.querySelectorAll( '[data-iftp-outcome-pick]' );

		picks.forEach( function ( pick ) {
			pick.addEventListener( 'click', function () {
				const outcome = pick.dataset.iftpOutcomePick;
				const view = root.querySelector( '[data-iftp-look-view-pick="' + outcome + '"]' );

				picks.forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === pick ? 'true' : 'false' );
				} );

				root.querySelectorAll( '[data-iftp-outcome]' ).forEach( function ( fieldset ) {
					fieldset.hidden = fieldset.dataset.iftpOutcome !== outcome;
				} );

				if ( view ) {
					view.click();
				}
			} );
		} );

		return function () {
			const picked = root.querySelector( '[data-iftp-outcome-pick][aria-pressed="true"]' );

			if ( picked ) {
				picked.click();
			}
		};
	}

	// Two views of one message: the textarea is what's submitted, the contenteditable mirrors it.
	function bindMessages( root ) {
		root.querySelectorAll( '[data-iftp-msg]' ).forEach( function ( box ) {
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
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
