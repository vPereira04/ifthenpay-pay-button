// Our own SVG charts for the Entries Performance card and the Dashboard widget. No library.
// One area chart: a smooth gradient line with a light wash under it, hairline grid, and a crosshair tooltip on
// hover and on the arrow keys.
// Every container with data-iftp-chart='{…}' renders itself, and redraws when its size changes.
( function () {
	'use strict';

	var ns = 'http://www.w3.org/2000/svg';
	var uid = 0;

	function svgEl( name, attrs ) {
		var node = document.createElementNS( ns, name );

		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );

		return node;
	}

	function cssVar( el, name, fallback ) {
		var value = window.getComputedStyle( el ).getPropertyValue( name ).trim();

		return value || fallback;
	}

	// The axis top: a clean number just above the data, so ticks read 0 / 50 / 100 / 150.
	function niceMax( value ) {
		if ( value <= 0 ) {
			return 1;
		}

		var exp = Math.pow( 10, Math.floor( Math.log10( value ) ) );
		var f = value / exp;
		var nice = 10;

		if ( f <= 1.2 ) {
			nice = 1.2;
		} else if ( f <= 1.5 ) {
			nice = 1.5;
		} else if ( f <= 2 ) {
			nice = 2;
		} else if ( f <= 3 ) {
			nice = 3;
		} else if ( f <= 4 ) {
			nice = 4;
		} else if ( f <= 5 ) {
			nice = 5;
		} else if ( f <= 6 ) {
			nice = 6;
		} else if ( f <= 8 ) {
			nice = 8;
		}

		return nice * exp;
	}

	// Catmull-Rom through every point, as cubic Béziers: round, relaxed curves. Control points are kept between
	// the plot's top and its baseline, so a curve never dips below zero or bulges past the frame.
	function smoothPath( pts, top, base ) {
		if ( pts.length < 2 ) {
			return pts.length ? 'M' + pts[ 0 ][ 0 ] + ',' + pts[ 0 ][ 1 ] : '';
		}

		var clamp = function ( y ) {
			return Math.max( top, Math.min( base, y ) );
		};
		var d = 'M' + pts[ 0 ][ 0 ] + ',' + pts[ 0 ][ 1 ];

		for ( var i = 0; i < pts.length - 1; i++ ) {
			var p0 = pts[ i - 1 ] || pts[ i ];
			var p1 = pts[ i ];
			var p2 = pts[ i + 1 ];
			var p3 = pts[ i + 2 ] || p2;

			d +=
				' C' + ( p1[ 0 ] + ( p2[ 0 ] - p0[ 0 ] ) / 6 ) + ',' + clamp( p1[ 1 ] + ( p2[ 1 ] - p0[ 1 ] ) / 6 ) +
				' ' + ( p2[ 0 ] - ( p3[ 0 ] - p1[ 0 ] ) / 6 ) + ',' + clamp( p2[ 1 ] - ( p3[ 1 ] - p1[ 1 ] ) / 6 ) +
				' ' + p2[ 0 ] + ',' + p2[ 1 ];
		}

		return d;
	}

	// Amounts written the way PHP's number_format_i18n() . ' €' writes them everywhere else: "1,234.56 €".
	function formatters( data ) {
		var decimal = data.decimal || '.';
		var thousands = undefined === data.thousands ? ',' : data.thousands;

		function group( digits ) {
			return digits.replace( /\B(?=(\d{3})+(?!\d))/g, thousands );
		}

		function full( value ) {
			var parts = Math.abs( value ).toFixed( 2 ).split( '.' );

			return ( value < 0 ? '-' : '' ) + group( parts[ 0 ] ) + decimal + parts[ 1 ] + ' €';
		}

		// Axis ticks: 0 €, 450 €, 1.5K €, 12K €.
		function compact( value ) {
			if ( value >= 1000 ) {
				var k = value / 1000;

				return ( Number.isInteger( k ) ? String( k ) : k.toFixed( 1 ).replace( '.', decimal ) ) + 'K €';
			}

			return group( String( Math.round( value ) ) ) + ' €';
		}

		return { full, compact };
	}

	function area( container, data ) {
		var values = ( data.values || [] ).map( Number );
		var fmt = formatters( data );
		var id = 'iftp-btn-chart-' + ++uid;
		var compact = !! data.compact;
		var interactive = ! compact;
		var index = values.length - 1;
		var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var animated = false;
		var lastWidth = 0;
		var geometry = null;

		var tooltip = document.createElement( 'div' );
		var live = document.createElement( 'span' );

		tooltip.className = 'iftp-btn-chart__tooltip';
		tooltip.setAttribute( 'aria-hidden', 'true' );

		// Arrow keys walk the points; the live region reads each one out.
		live.className = 'screen-reader-text';
		live.setAttribute( 'aria-live', 'polite' );

		if ( interactive ) {
			container.tabIndex = 0;
			container.setAttribute( 'role', 'group' );
			container.setAttribute( 'aria-label', ( data.label || '' ) + '. ' + ( container.getAttribute( 'data-iftp-chart-hint' ) || '' ) );
		}

		function draw() {
			var width = container.clientWidth;

			if ( ! width || width === lastWidth ) {
				return;
			}

			lastWidth = width;

			var height = Number( data.height ) || 220;
			var pad = compact ? { t: 10, r: 8, b: 6, l: 8 } : { t: 18, r: 20, b: 34, l: 62 };
			var plotW = width - pad.l - pad.r;
			var plotH = height - pad.t - pad.b;
			var base = pad.t + plotH;
			var max = niceMax( Math.max.apply( null, values.concat( [ 0 ] ) ) );
			var lineFrom = cssVar( container, '--iftp-chart-from', '#00a7e2' );
			var lineTo = cssVar( container, '--iftp-chart-to', '#00609c' );
			var grid = cssVar( container, '--iftp-chart-grid', 'rgba(18,26,44,0.07)' );
			var surface = cssVar( container, '--iftp-chart-surface', '#fafbfd' );
			var step = values.length > 1 ? plotW / ( values.length - 1 ) : 0;
			var points = values.map( function ( v, i ) {
				return [ pad.l + i * step, base - ( v / max ) * plotH ];
			} );
			var svg = svgEl( 'svg', { width, height, viewBox: '0 0 ' + width + ' ' + height, class: 'iftp-btn-chart__svg', 'aria-hidden': 'true', focusable: 'false' } );
			var defs = svgEl( 'defs' );
			var stroke = svgEl( 'linearGradient', { id: id + '-stroke', x1: pad.l, y1: 0, x2: width - pad.r, y2: 0, gradientUnits: 'userSpaceOnUse' } );
			var wash = svgEl( 'linearGradient', { id: id + '-wash', x1: 0, y1: pad.t, x2: 0, y2: base, gradientUnits: 'userSpaceOnUse' } );

			stroke.append( svgEl( 'stop', { offset: '0%', 'stop-color': lineFrom } ), svgEl( 'stop', { offset: '100%', 'stop-color': lineTo } ) );
			wash.append(
				svgEl( 'stop', { offset: '0%', 'stop-color': lineTo, 'stop-opacity': compact ? 0.1 : 0.09 } ),
				svgEl( 'stop', { offset: '65%', 'stop-color': lineFrom, 'stop-opacity': 0.025 } ),
				svgEl( 'stop', { offset: '100%', 'stop-color': lineFrom, 'stop-opacity': 0 } )
			);
			defs.append( stroke, wash );
			svg.appendChild( defs );

			// Recessive, solid hairline grid and the y ticks.
			if ( ! compact ) {
				for ( var g = 0; g <= 4; g++ ) {
					var gy = Math.round( base - ( g / 4 ) * plotH ) + 0.5;
					var tick = svgEl( 'text', { x: pad.l - 14, y: gy + 4, 'text-anchor': 'end', class: 'iftp-btn-chart__tick' } );

					svg.appendChild( svgEl( 'line', { x1: pad.l, x2: width - pad.r, y1: gy, y2: gy, stroke: grid, 'stroke-width': 1 } ) );
					tick.textContent = fmt.compact( ( max * g ) / 4 );
					svg.appendChild( tick );
				}

				// A week or a year of months has room for every label; longer ranges get about six, always the last one, never two crammed together.
				var every = values.length <= 12 ? 1 : Math.ceil( values.length / 6 );

				( data.labels || [] ).forEach( function ( text, i ) {
					var isLast = i === values.length - 1;

					if ( ( i % every && ! isLast ) || ( ! isLast && values.length - 1 - i < every / 2 ) ) {
						return;
					}

					var anchor = 'middle';

					if ( 0 === i ) {
						anchor = 'start';
					} else if ( isLast ) {
						anchor = 'end';
					}

					var label = svgEl( 'text', { x: points[ i ][ 0 ], y: height - 10, 'text-anchor': anchor, class: 'iftp-btn-chart__tick' } );

					label.textContent = text;
					svg.appendChild( label );
				} );
			}

			var lineD = smoothPath( points, pad.t, base );
			var last = points[ points.length - 1 ];
			var areaPath = svgEl( 'path', {
				d: lineD + ' L' + last[ 0 ] + ',' + base + ' L' + points[ 0 ][ 0 ] + ',' + base + ' Z',
				fill: 'url(#' + id + '-wash)',
				class: 'iftp-btn-chart__area',
			} );
			var linePath = svgEl( 'path', { d: lineD, fill: 'none', stroke: 'url(#' + id + '-stroke)', 'stroke-width': compact ? 2 : 2.5, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', class: 'iftp-btn-chart__line' } );

			svg.append( areaPath, linePath );

			// The end-dot, with a surface ring so it stays clear of the line.
			svg.append( svgEl( 'circle', { cx: last[ 0 ], cy: last[ 1 ], r: 4.5, fill: lineTo, stroke: surface, 'stroke-width': 2.5 } ) );

			// Hover layer: a spotlight column and a crosshair that glide along x, and a haloed dot that glides to
			// the point. They move by CSS transform, so the browser eases every step between points.
			var spot = svgEl( 'linearGradient', { id: id + '-spot', x1: 0, y1: pad.t, x2: 0, y2: base, gradientUnits: 'userSpaceOnUse' } );

			spot.append(
				svgEl( 'stop', { offset: '0%', 'stop-color': lineTo, 'stop-opacity': 0 } ),
				svgEl( 'stop', { offset: '100%', 'stop-color': lineTo, 'stop-opacity': 0.09 } )
			);
			defs.appendChild( spot );

			var band = Math.max( 18, Math.min( 56, step * 0.9 ) );
			var cross = svgEl( 'g', { class: 'iftp-btn-chart__cross' } );
			var dot = svgEl( 'g', { class: 'iftp-btn-chart__focus' } );

			cross.append(
				svgEl( 'rect', { x: -band / 2, y: pad.t, width: band, height: plotH, rx: 8, fill: 'url(#' + id + '-spot)' } ),
				svgEl( 'line', { x1: 0, x2: 0, y1: pad.t, y2: base, stroke: lineTo, 'stroke-opacity': 0.3, 'stroke-width': 1 } )
			);
			dot.append(
				svgEl( 'circle', { r: 13, fill: lineTo, 'fill-opacity': 0.14 } ),
				svgEl( 'circle', { r: 5.5, fill: lineTo, stroke: surface, 'stroke-width': 3, class: 'iftp-btn-chart__dot' } )
			);

			svg.append( cross, dot );

			container.textContent = '';
			container.append( svg, tooltip, live );

			// The line draws itself once, on first paint; the wash fades in behind it.
			if ( ! animated && ! reduceMotion && linePath.getTotalLength ) {
				var length = linePath.getTotalLength();

				linePath.style.strokeDasharray = length;
				linePath.style.strokeDashoffset = length;
				areaPath.style.opacity = '0';
				linePath.getBoundingClientRect();
				linePath.style.transition = 'stroke-dashoffset 1100ms cubic-bezier(0.65, 0, 0.35, 1)';
				linePath.style.strokeDashoffset = '0';
				areaPath.style.transition = 'opacity 900ms ease 300ms';
				areaPath.style.opacity = '1';
			}

			animated = true;
			geometry = { points, cross, dot, pad, step, width };
		}

		function show( i, announce ) {
			if ( ! geometry || ! geometry.points.length ) {
				return;
			}

			var next = Math.max( 0, Math.min( i, geometry.points.length - 1 ) );

			// Same point as before: nothing moves, so nothing to redo.
			if ( next === index && container.classList.contains( 'iftp-btn-chart--active' ) && ! announce ) {
				return;
			}

			index = next;

			var p = geometry.points[ index ];
			var title = ( data.tooltips && data.tooltips[ index ] ) || ( data.labels && data.labels[ index ] ) || '';
			var value = values[ index ] || 0;
			var spoken = title + ': ' + fmt.full( value );

			// The first show snaps into place; after that every move eases.
			if ( ! container.classList.contains( 'iftp-btn-chart--active' ) ) {
				container.classList.add( 'iftp-btn-chart--snap' );
			}

			geometry.cross.style.transform = 'translateX(' + p[ 0 ] + 'px)';
			geometry.dot.style.transform = 'translate(' + p[ 0 ] + 'px, ' + p[ 1 ] + 'px)';

			tooltip.textContent = '';

			var head = document.createElement( 'span' );
			var now = document.createElement( 'span' );

			head.className = 'iftp-btn-chart__tooltip-label';
			head.textContent = title;
			now.className = 'iftp-btn-chart__tooltip-value';
			now.textContent = fmt.full( value );
			tooltip.append( head, now );

			// Above the point, kept inside the chart near either edge; it slides there rather than jumping.
			var tipW = tooltip.offsetWidth;
			var x = Math.max( 4, Math.min( p[ 0 ] - tipW / 2, geometry.width - tipW - 4 ) );
			var y = Math.max( 0, p[ 1 ] - tooltip.offsetHeight - 22 );

			tooltip.style.transform = 'translate3d(' + x + 'px, ' + y + 'px, 0)';
			container.classList.add( 'iftp-btn-chart--active' );

			window.requestAnimationFrame( function () {
				container.classList.remove( 'iftp-btn-chart--snap' );
			} );

			if ( announce ) {
				live.textContent = spoken;
			}
		}

		// Fades out where it is; the next show starts from there.
		function hide() {
			container.classList.remove( 'iftp-btn-chart--active' );
		}

		if ( interactive ) {
			// The whole plot is the hit target: the nearest point by x, not a dot to aim at.
			container.addEventListener( 'pointermove', function ( event ) {
				if ( ! geometry ) {
					return;
				}

				var x = event.clientX - container.getBoundingClientRect().left - geometry.pad.l;

				show( geometry.step ? Math.round( x / geometry.step ) : 0, false );
			} );

			container.addEventListener( 'pointerleave', function () {
				if ( container.ownerDocument.activeElement !== container ) {
					hide();
				}
			} );

			container.addEventListener( 'focus', function () {
				show( index, true );
			} );

			container.addEventListener( 'blur', hide );

			container.addEventListener( 'keydown', function ( event ) {
				var moves = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: values.length - 1 };

				if ( event.key in moves ) {
					event.preventDefault();
					show( moves[ event.key ], true );
				}
			} );
		}

		// Hidden panels have no width yet; the observer draws them the moment they show.
		if ( window.ResizeObserver ) {
			new window.ResizeObserver( draw ).observe( container );
		} else {
			window.addEventListener( 'resize', draw );
		}

		draw();
	}

	function init() {
		document.querySelectorAll( '[data-iftp-chart]' ).forEach( function ( container ) {
			try {
				area( container, JSON.parse( container.getAttribute( 'data-iftp-chart' ) ) );
			} catch ( e ) {
				// Bad data: the table next to it still has the numbers.
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
