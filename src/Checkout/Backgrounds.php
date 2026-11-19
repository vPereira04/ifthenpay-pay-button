<?php
/**
 * The generated SVG backgrounds of the pay page (crystals, waves, bokeh, liquid, mesh, tide, confetti, bubbles, fireflies), as CSS url() values.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure drawing: the same arguments always give the same SVG, and buttons.js draws the same ones for the preview.
 */
final class Backgrounds {

	// The generated patterns repeat every this many facets (or wave spacings) each way.
	public const CRYSTAL_CELLS = 6;

	// Lines in one wave tile, and lights in one bokeh tile.
	private const WAVE_LINES   = 12;
	private const BOKEH_LIGHTS = 22;

	// The liquid's blobs: centre, radius, how far it drifts each way, seconds a round trip, accent (0) or second (1).
	private const LIQUID_BLOBS = array(
		array( 180, 220, 90, 140, 90, 19, 0 ),
		array( 420, 140, 68, -110, 120, 23, 1 ),
		array( 760, 260, 105, -130, 100, 21, 0 ),
		array( 860, 640, 83, -90, -140, 25, 1 ),
		array( 560, 820, 113, 120, -90, 17, 0 ),
		array( 200, 700, 98, 110, -120, 24, 1 ),
		array( 500, 480, 60, -160, -60, 15, 0 ),
		array( 680, 520, 53, 140, 110, 20, 1 ),
		array( 320, 460, 45, 80, 140, 18, 0 ),
	);

	// The mesh's clouds: centre, radius, how far it drifts each way, seconds a round trip, accent (0), second (1) or white (2).
	private const MESH_CLOUDS = array(
		array( 200, 250, 320, 260, 180, 26, 0 ),
		array( 800, 200, 300, -240, 220, 31, 1 ),
		array( 700, 800, 340, -200, -240, 29, 0 ),
		array( 250, 780, 280, 240, -200, 34, 1 ),
		array( 520, 480, 220, -180, 160, 23, 2 ),
	);

	// The tide's waves, back to front: baseline, height, length, seconds a wavelength, accent (0) or second (1), opacity %.
	private const TIDE_LAYERS = array(
		array( 620, 40, 500, 16, 1, 30 ),
		array( 700, 50, 640, 12, 0, 38 ),
		array( 780, 36, 420, 10, 1, 48 ),
		array( 860, 46, 520, 8, 0, 60 ),
	);

	/**
	 * The crystal mesh as an SVG data URI: a jittered grid of points joined into triangles, so every facet
	 * shares its edges with its neighbours. Points on the tile's far edges repeat the near ones, so it tiles
	 * without seams. A fixed seed keeps it the same on every page; buttons.js draws the same mesh for the preview.
	 *
	 * @param int $size     The facet size in pixels.
	 * @param int $strength 0–100, how much light and shade the facets get.
	 */
	public static function crystals( int $size, int $strength ): string {
		$cells = self::CRYSTAL_CELLS;
		$tile  = $cells * $size;
		$seed  = 20261022;
		// Park–Miller: small enough to give the same numbers in PHP and JavaScript.
		$random = static function () use ( &$seed ): float {
			$seed = ( $seed * 16807 ) % 2147483647;

			return $seed / 2147483647;
		};
		$points = array();

		for ( $i = 0; $i < $cells; $i++ ) {
			for ( $j = 0; $j < $cells; $j++ ) {
				$points[ $i ][ $j ] = array( $i * $size + ( $random() - 0.5 ) * $size * 0.8, $j * $size + ( $random() - 0.5 ) * $size * 0.8 );
			}
		}

		$point = static function ( int $i, int $j ) use ( $points, $cells, $tile ): array {
			$base = $points[ $i % $cells ][ $j % $cells ];

			return array( $base[0] + ( $i >= $cells ? $tile : 0 ), $base[1] + ( $j >= $cells ? $tile : 0 ) );
		};

		$light  = $strength / 100;
		$shapes = '';

		for ( $i = 0; $i < $cells; $i++ ) {
			for ( $j = 0; $j < $cells; $j++ ) {
				$a = $point( $i, $j );
				$b = $point( $i + 1, $j );
				$c = $point( $i + 1, $j + 1 );
				$d = $point( $i, $j + 1 );

				$triangles = $random() < 0.5 ? array( array( $a, $b, $c ), array( $a, $c, $d ) ) : array( array( $a, $b, $d ), array( $b, $c, $d ) );

				foreach ( $triangles as $triangle ) {
					$shade = $random() * 2 - 1;
					$fill  = $shade > 0 ? '#fff' : '#000';
					$alpha = round( abs( $shade ) * $light * ( $shade > 0 ? 0.55 : 0.25 ), 3 );

					// A facet that crosses the tile's edge is drawn again on the other side, so the seams close.
					foreach ( self::tile_offsets( $triangle, $tile ) as $offset ) {
						$path = '';

						foreach ( $triangle as $at => $corner ) {
							$path .= ( 0 === $at ? 'M' : 'L' ) . round( $corner[0] + $offset[0], 1 ) . ' ' . round( $corner[1] + $offset[1], 1 );
						}

						$shapes .= sprintf( "<path d='%sZ' fill='%s' fill-opacity='%s'/>", $path, $fill, $alpha );
					}
				}
			}
		}

		$svg = sprintf(
			"<svg xmlns='http://www.w3.org/2000/svg' width='%1\$d' height='%1\$d' viewBox='0 0 %1\$d %1\$d'><g stroke='#fff' stroke-opacity='%2\$s' stroke-width='1' stroke-linejoin='round'>%3\$s</g></svg>",
			$tile,
			round( $light * 0.22, 3 ),
			$shapes
		);

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Where else a triangle has to be drawn so a tile repeats seamlessly: shifted a tile back from any edge it crosses.
	 *
	 * @param array<int, array{0: float, 1: float}> $triangle Its corners.
	 * @param int                                   $tile     The tile's size.
	 * @return array<int, array{0: int, 1: int}>
	 */
	private static function tile_offsets( array $triangle, int $tile ): array {
		$xs = array_column( $triangle, 0 );
		$ys = array_column( $triangle, 1 );
		$dx = array( 0 );
		$dy = array( 0 );

		if ( min( $xs ) < 0 ) {
			$dx[] = $tile;
		}

		if ( max( $xs ) > $tile ) {
			$dx[] = -$tile;
		}

		if ( min( $ys ) < 0 ) {
			$dy[] = $tile;
		}

		if ( max( $ys ) > $tile ) {
			$dy[] = -$tile;
		}

		$offsets = array();

		foreach ( $dx as $x ) {
			foreach ( $dy as $y ) {
				$offsets[] = array( $x, $y );
			}
		}

		return $offsets;
	}

	/**
	 * Waves as an SVG data URI: contour lines that drift closer and further apart, like silk or a relief map,
	 * with the bands between them lit and shaded. Each line's shape comes round again after a tile both ways,
	 * so it tiles without seams. buttons.js draws the same waves for the preview.
	 *
	 * @param int $size     A wave's spacing times two, in pixels.
	 * @param int $strength 0–100, how much light and shade the bands get.
	 */
	public static function waves( int $size, int $strength ): string {
		$tile   = self::CRYSTAL_CELLS * $size;
		$lines  = self::WAVE_LINES;
		$gap    = $tile / $lines;
		$steps  = 12;
		$light  = $strength / 100;
		$circle = 2 * M_PI;
		// The phases drift from line to line but meet again after a tile, so neighbours never cross.
		$y   = static function ( int $k, int $at ) use ( $gap, $steps, $lines, $circle ): float {
			$x = $at / $steps;

			return ( $k + 0.5 ) * $gap + 1.2 * $gap * ( sin( $circle * $x + 0.6 + 0.9 * sin( $circle * $k / $lines ) ) + 0.4 * sin( 2 * $circle * $x + 2.1 + 0.7 * cos( $circle * $k / $lines ) ) );
		};
		$num = static fn( float $value ): string => (string) self::js_round( $value, 10 );
		$x   = static fn( int $at ): float => $at * $tile / $steps;
		// A line as smooth curves (Catmull-Rom), from its left end or, backwards, from its right.
		$curve = static function ( int $k, bool $back, float $dy ) use ( $y, $x, $steps, $num ): string {
			$path = '';

			for ( $n = 0; $n < $steps; $n++ ) {
				$from = $back ? $steps - $n : $n;
				$to   = $back ? $from - 1 : $from + 1;
				$prev = $back ? $from + 1 : $from - 1;
				$next = $back ? $to - 1 : $to + 1;

				$path .= 'C' . $num( $x( $from ) + ( $x( $to ) - $x( $prev ) ) / 6 ) . ' ' . $num( $y( $k, $from ) + ( $y( $k, $to ) - $y( $k, $prev ) ) / 6 + $dy )
					. ' ' . $num( $x( $to ) - ( $x( $next ) - $x( $from ) ) / 6 ) . ' ' . $num( $y( $k, $to ) - ( $y( $k, $next ) - $y( $k, $from ) ) / 6 + $dy )
					. ' ' . $num( $x( $to ) ) . ' ' . $num( $y( $k, $to ) + $dy );
			}

			return $path;
		};
		// Where a line reaches, top and bottom, and so where else it has to be drawn for the seams to close.
		$shifts = static function ( int $top, int $low ) use ( $y, $steps, $tile ): array {
			$tops  = array();
			$lows  = array();
			$shift = array( 0 );

			for ( $at = 0; $at <= $steps; $at++ ) {
				$tops[] = $y( $top, $at );
				$lows[] = $y( $low, $at );
			}

			if ( min( $tops ) < 0 ) {
				$shift[] = $tile;
			}

			if ( max( $lows ) > $tile ) {
				$shift[] = -$tile;
			}

			return $shift;
		};
		$bands  = '';
		$edges  = '';

		for ( $k = 0; $k < $lines; $k++ ) {
			$shade = sin( $circle * 2 * ( $k + 0.5 ) / $lines );
			$fill  = $shade > 0 ? '#fff' : '#000';
			$alpha = self::js_round( abs( $shade ) * $light * ( $shade > 0 ? 0.5 : 0.3 ), 1000 );

			foreach ( $shifts( $k, $k + 1 ) as $dy ) {
				$bands .= sprintf( "<path d='M0 %s%sL%d %s%sZ' fill='%s' fill-opacity='%s'/>", $num( $y( $k, 0 ) + $dy ), $curve( $k, false, $dy ), $tile, $num( $y( $k + 1, $steps ) + $dy ), $curve( $k + 1, true, $dy ), $fill, $alpha );
			}

			foreach ( $shifts( $k, $k ) as $dy ) {
				$edges .= 'M0 ' . $num( $y( $k, 0 ) + $dy ) . $curve( $k, false, $dy );
			}
		}

		$svg = sprintf(
			"<svg xmlns='http://www.w3.org/2000/svg' width='%1\$d' height='%1\$d' viewBox='0 0 %1\$d %1\$d'>%2\$s<path d='%3\$s' fill='none' stroke='#fff' stroke-opacity='%4\$s' stroke-width='1.2'/></svg>",
			$tile,
			$bands,
			$edges,
			self::js_round( $light * 0.7, 1000 )
		);

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Bokeh as an SVG data URI: out-of-focus lights in the accent, the second colour and white, the big ones
	 * fainter, each with the brighter rim a real lens gives. Lights over the tile's edge are drawn again on the
	 * other side, so it tiles without seams. buttons.js draws the same lights for the preview.
	 *
	 * @param int    $size     A middling light's radius, in pixels.
	 * @param int    $strength 0–100, how bright the lights are.
	 * @param string $color    The accent.
	 * @param string $color2   The second colour.
	 */
	public static function bokeh( int $size, int $strength, string $color, string $color2 ): string {
		$tile = self::CRYSTAL_CELLS * $size;
		$seed = 20261023;
		// Park–Miller, as in crystals().
		$random = static function () use ( &$seed ): float {
			$seed = ( $seed * 16807 ) % 2147483647;

			return $seed / 2147483647;
		};
		$light  = $strength / 100;
		$defs   = '';
		$shapes = '';

		foreach ( array( $color, $color2, '#ffffff' ) as $at => $hex ) {
			$defs .= sprintf( "<radialGradient id='b%1\$d'><stop offset='0' stop-color='%2\$s' stop-opacity='.62'/><stop offset='.8' stop-color='%2\$s' stop-opacity='.5'/><stop offset='.93' stop-color='%2\$s' stop-opacity='.6'/><stop offset='1' stop-color='%2\$s' stop-opacity='0'/></radialGradient>", $at, $hex );
		}

		for ( $n = 0; $n < self::BOKEH_LIGHTS; $n++ ) {
			$x     = $random() * $tile;
			$y     = $random() * $tile;
			$grow  = $random();
			$pick  = $random();
			$r     = $size * ( 0.2 + $grow * 0.95 );
			$fill  = $pick < 0.42 ? 0 : 1;
			$fill  = $pick < 0.8 ? $fill : 2;
			$alpha = self::js_round( $light * ( 0.75 - $grow * 0.45 ), 1000 );

			foreach ( self::tile_offsets( array( array( $x - $r, $y - $r ), array( $x + $r, $y + $r ) ), $tile ) as $offset ) {
				$shapes .= sprintf( "<circle cx='%s' cy='%s' r='%s' fill='url(#b%d)' opacity='%s'/>", self::js_round( $x + $offset[0], 10 ), self::js_round( $y + $offset[1], 10 ), self::js_round( $r, 10 ), $fill, $alpha );
			}
		}

		$svg = sprintf( "<svg xmlns='http://www.w3.org/2000/svg' width='%1\$d' height='%1\$d' viewBox='0 0 %1\$d %1\$d'><defs>%2\$s</defs>%3\$s</svg>", $tile, $defs, $shapes );

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Liquid as an SVG data URI: blobs in the accent and the second colour that drift, swell, merge and split.
	 * A goo filter (blur, then a hard alpha edge) melts them together, and a light across the blurred shape
	 * gives the wet shine. It covers the page rather than tiling. buttons.js draws the same liquid for the preview.
	 *
	 * @param int    $size   How big the blobs are; 140 is the drawing's own size.
	 * @param string $color  The accent.
	 * @param string $color2 The second colour.
	 * @param bool   $still  Without the motion, for payers who ask for less.
	 */
	public static function liquid( int $size, string $color, string $color2, bool $still = false ): string {
		$scale  = min( 1.8, max( 0.5, $size / 140 ) );
		$spline = "calcMode='spline' keyTimes='0;.5;1' keySplines='.45 0 .55 1;.45 0 .55 1' repeatCount='indefinite'";
		$blobs  = '';

		foreach ( self::LIQUID_BLOBS as $blob ) {
			list( $x, $y, $r, $dx, $dy, $dur, $tone ) = $blob;

			$radius = (int) self::js_round( $r * $scale, 1 );
			$motion = '';

			if ( ! $still ) {
				$motion = sprintf( "<animate attributeName='cx' values='%1\$d;%2\$d;%1\$d' dur='%3\$ds' %4\$s/>", $x, $x + $dx, $dur, $spline )
					. sprintf( "<animate attributeName='cy' values='%1\$d;%2\$d;%1\$d' dur='%3\$ds' %4\$s/>", $y, $y + $dy, $dur + 4, $spline )
					. sprintf( "<animate attributeName='r' values='%1\$d;%2\$d;%1\$d' dur='%3\$ds' %4\$s/>", $radius, (int) self::js_round( $radius * 1.25, 1 ), $dur + 7, $spline );
			}

			$blobs .= sprintf( "<circle cx='%d' cy='%d' r='%d' fill='%s'>%s</circle>", $x, $y, $radius, $tone ? $color2 : $color, $motion );
		}

		$svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>"
			. "<filter id='g' filterUnits='userSpaceOnUse' x='-200' y='-200' width='1400' height='1400' color-interpolation-filters='sRGB'>"
			. "<feGaussianBlur in='SourceGraphic' stdDeviation='30' result='b'/>"
			. "<feColorMatrix in='b' values='1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 28 -11' result='goo'/>"
			. "<feGaussianBlur in='goo' stdDeviation='9' result='soft'/>"
			. "<feSpecularLighting in='soft' surfaceScale='7' specularConstant='.95' specularExponent='26' lighting-color='#fff' result='light'><feDistantLight azimuth='225' elevation='52'/></feSpecularLighting>"
			. "<feComposite in='light' in2='goo' operator='in' result='shine'/>"
			. "<feComposite in='shine' in2='goo' operator='arithmetic' k2='.85' k3='1'/>"
			. "</filter><g filter='url(#g)'>" . $blobs . '</g></svg>';

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * The mesh as an SVG data URI: big clouds of the accent, the second colour and white, blurred until they melt
	 * into one another, drifting so the colours keep shifting like a moving mesh gradient. It covers the page
	 * rather than tiling. buttons.js draws the same mesh for the preview.
	 *
	 * @param int    $size   How big the clouds are; 140 is the drawing's own size.
	 * @param string $color  The accent.
	 * @param string $color2 The second colour.
	 * @param bool   $still  Without the motion, for payers who ask for less.
	 */
	public static function mesh( int $size, string $color, string $color2, bool $still = false ): string {
		$scale  = min( 1.8, max( 0.5, $size / 140 ) );
		$spline = "calcMode='spline' keyTimes='0;.5;1' keySplines='.45 0 .55 1;.45 0 .55 1' repeatCount='indefinite'";
		$tones  = array( $color, $color2, '#ffffff' );
		$clouds = '';

		foreach ( self::MESH_CLOUDS as $cloud ) {
			list( $x, $y, $r, $dx, $dy, $dur, $tone ) = $cloud;

			$radius = (int) self::js_round( $r * $scale, 1 );
			$motion = '';

			if ( ! $still ) {
				$motion = sprintf( "<animate attributeName='cx' values='%1\$d;%2\$d;%1\$d' dur='%3\$ds' %4\$s/>", $x, $x + $dx, $dur, $spline )
					. sprintf( "<animate attributeName='cy' values='%1\$d;%2\$d;%1\$d' dur='%3\$ds' %4\$s/>", $y, $y + $dy, $dur + 6, $spline );
			}

			$clouds .= sprintf( "<circle cx='%d' cy='%d' r='%d' fill='%s'>%s</circle>", $x, $y, $radius, $tones[ $tone ], $motion );
		}

		$svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>"
			. "<filter id='m' filterUnits='userSpaceOnUse' x='-300' y='-300' width='1600' height='1600'><feGaussianBlur stdDeviation='90'/></filter>"
			. "<g filter='url(#m)' fill-opacity='.85'>" . $clouds . '</g></svg>';

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * The tide as an SVG data URI: layered waves in the accent and the second colour, each rolling sideways a
	 * wavelength at a time, the front ones faster, so the loop never shows a seam. It stretches to the screen,
	 * so the waves fill the same lower part of a phone and a wide monitor. buttons.js draws the same tide for the preview.
	 *
	 * @param int    $size   How tall and long the waves are; 140 is the drawing's own size.
	 * @param string $color  The accent.
	 * @param string $color2 The second colour.
	 * @param bool   $still  Without the motion, for payers who ask for less.
	 */
	public static function tide( int $size, string $color, string $color2, bool $still = false ): string {
		$scale = min( 1.8, max( 0.5, $size / 140 ) );
		$waves = '';

		foreach ( self::TIDE_LAYERS as $at => $layer ) {
			list( $base, $height, $length, $dur, $tone, $alpha ) = $layer;

			$amp     = (int) self::js_round( $height * $scale, 1 );
			$quarter = (int) self::js_round( $length * $scale / 4, 1 );
			$start   = -4 * $quarter * (int) ceil( 1000 / ( 4 * $quarter ) ) - 4 * $quarter;
			$end     = $start + 2 * $quarter;
			$path    = 'M' . $start . ' ' . $base . 'Q' . ( $start + $quarter ) . ' ' . ( $base - $amp ) . ' ' . $end . ' ' . $base;

			// Each T mirrors the last curve, so the crests and troughs alternate like a sine.
			while ( $end < 2000 + 4 * $quarter ) {
				$end  += 2 * $quarter;
				$path .= 'T' . $end . ' ' . $base;
			}

			$path .= 'L' . $end . ' 1200L' . $start . ' 1200Z';
			$roll  = '';

			if ( ! $still ) {
				$roll = sprintf( "<animateTransform attributeName='transform' type='translate' values='0 0;%d 0' dur='%ds' repeatCount='indefinite'/>", ( $at % 2 ? -4 : 4 ) * $quarter, $dur );
			}

			$waves .= sprintf( "<path d='%s' fill='%s' fill-opacity='%s' stroke='#fff' stroke-opacity='.35' stroke-width='2' vector-effect='non-scaling-stroke'>%s</path>", $path, $tone ? $color2 : $color, $alpha / 100, $roll );
		}

		$svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='none'>" . $waves . '</svg>';

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Confetti as an SVG data URI: flecks of the accent, the second colour and white, each at its own place, size,
	 * speed and spin, falling from the top and starting over. Nothing tiles: it is one drawing that covers the page.
	 * buttons.js draws the same flecks for the preview.
	 *
	 * @param int    $size   How big the flecks are; 140 is the drawing's own size.
	 * @param string $color  The accent.
	 * @param string $color2 The second colour.
	 * @param bool   $still  Without the motion, for payers who ask for less.
	 */
	public static function confetti( int $size, string $color, string $color2, bool $still = false ): string {
		$scale  = min( 1.8, max( 0.5, $size / 140 ) );
		$seed   = 20261113;
		$random = static function () use ( &$seed ): float {
			$seed = ( $seed * 16807 ) % 2147483647;

			return $seed / 2147483647;
		};
		$tones  = array( $color, $color2, '#ffffff' );
		$flecks = '';

		for ( $n = 0; $n < 72; $n++ ) {
			$x     = (int) floor( $random() * 1000 );
			$y     = (int) floor( $random() * 1000 );
			$w     = (int) floor( ( 6 + $random() * 8 ) * $scale );
			$h     = (int) floor( ( 10 + $random() * 12 ) * $scale );
			$tone  = (int) floor( $random() * 3 );
			$angle = (int) floor( $random() * 360 );
			$fall  = 8 + (int) floor( $random() * 9 );
			$spin  = 2 + (int) floor( $random() * 4 );
			$from  = (int) floor( $random() * $fall );
			$turn  = $random() < 0.5 ? 360 : -360;
			$mid_x = (int) floor( $w / 2 );
			$mid_y = (int) floor( $h / 2 );
			$fill  = sprintf( "fill='%s' fill-opacity='%s'", $tones[ $tone ], 2 === $tone ? '.8' : '.88' );

			if ( $still ) {
				$flecks .= sprintf( "<g transform='translate(%d %d)'><rect width='%d' height='%d' rx='1' %s transform='rotate(%d %d %d)'/></g>", $x, $y, $w, $h, $fill, $angle, $mid_x, $mid_y );

				continue;
			}

			$flecks .= sprintf(
				"<g transform='translate(%d -40)'><g><animateTransform attributeName='transform' type='translate' values='0 0;0 1100' dur='%ds' begin='-%ds' repeatCount='indefinite'/><rect width='%d' height='%d' rx='1' %s><animateTransform attributeName='transform' type='rotate' values='%d %d %d;%d %d %d' dur='%ds' repeatCount='indefinite'/></rect></g></g>",
				$x,
				$fall,
				$from,
				$w,
				$h,
				$fill,
				$angle,
				$mid_x,
				$mid_y,
				$angle + $turn,
				$mid_x,
				$mid_y,
				$spin
			);
		}

		$svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" . $flecks . '</svg>';

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Bubbles as an SVG data URI: clear rings in the accent and the second colour, each at its own place and size
	 * (mostly small), rising and swaying at its own pace, with a small glint. One drawing that covers the page.
	 * buttons.js draws the same bubbles for the preview.
	 *
	 * @param int    $size   How big the bubbles are; 140 is the drawing's own size.
	 * @param string $color  The accent.
	 * @param string $color2 The second colour.
	 * @param bool   $still  Without the motion, for payers who ask for less.
	 */
	public static function bubbles( int $size, string $color, string $color2, bool $still = false ): string {
		$scale   = min( 1.8, max( 0.5, $size / 140 ) );
		$seed    = 20261114;
		$random  = static function () use ( &$seed ): float {
			$seed = ( $seed * 16807 ) % 2147483647;

			return $seed / 2147483647;
		};
		$tones   = array( $color, $color2 );
		$bubbles = '';

		for ( $n = 0; $n < 40; $n++ ) {
			$x    = (int) floor( $random() * 1000 );
			$y    = (int) floor( $random() * 1000 );
			$grow = $random();
			$r    = max( 3, (int) floor( ( 7 + $grow * $grow * 40 ) * $scale ) );
			$tone = (int) floor( $random() * 2 );
			$rise = 12 + (int) floor( $random() * 17 );
			$from = (int) floor( $random() * $rise );
			$sway = 6 + (int) floor( $random() * 19 );
			$beat = 3 + (int) floor( $random() * 5 );
			$glow = (int) floor( $r * 0.35 );
			$glit = max( 1, (int) floor( $r * 0.18 ) );
			$body = sprintf( "<circle r='%1\$d' fill='%2\$s' fill-opacity='.07' stroke='%2\$s' stroke-opacity='.55' stroke-width='1.6'/><circle cx='-%3\$d' cy='-%3\$d' r='%4\$d' fill='#ffffff' fill-opacity='.55'/>", $r, $tones[ $tone ], $glow, $glit );

			if ( $still ) {
				$bubbles .= sprintf( "<g transform='translate(%d %d)'>%s</g>", $x, $y, $body );

				continue;
			}

			$bubbles .= sprintf(
				"<g transform='translate(%d %d)'><g><animateTransform attributeName='transform' type='translate' values='0 0;0 -%d' dur='%ds' begin='-%ds' repeatCount='indefinite'/><g><animateTransform attributeName='transform' type='translate' values='0 0;%d 0;0 0;-%d 0;0 0' dur='%ds' repeatCount='indefinite'/>%s</g></g></g>",
				$x,
				1040 + $r,
				1080 + 2 * $r,
				$rise,
				$from,
				$sway,
				$sway,
				$beat,
				$body
			);
		}

		$svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" . $bubbles . '</svg>';

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Fireflies as an SVG data URI: warm lights in the second colour, each at its own place and size, drifting
	 * slowly and twinkling on its own beat. One drawing that covers the page. buttons.js draws the same lights
	 * for the preview.
	 *
	 * @param int    $size   How big the lights are; 140 is the drawing's own size.
	 * @param string $color  The accent (unused: the lights are warm, in the second colour).
	 * @param string $color2 The second colour.
	 * @param bool   $still  Without the motion, for payers who ask for less.
	 */
	public static function fireflies( int $size, string $color, string $color2, bool $still = false ): string {
		$scale  = min( 1.8, max( 0.5, $size / 140 ) );
		$seed   = 20261115;
		$random = static function () use ( &$seed ): float {
			$seed = ( $seed * 16807 ) % 2147483647;

			return $seed / 2147483647;
		};
		$tone   = '' !== $color2 ? $color2 : $color;
		$lights = '';
		$defs   = sprintf( "<defs><radialGradient id='f'><stop offset='0' stop-color='#ffffff' stop-opacity='.95'/><stop offset='.2' stop-color='%1\$s' stop-opacity='.7'/><stop offset='1' stop-color='%1\$s' stop-opacity='0'/></radialGradient></defs>", $tone );

		for ( $n = 0; $n < 28; $n++ ) {
			$x     = (int) floor( $random() * 1000 );
			$y     = (int) floor( $random() * 1000 );
			$r     = (int) floor( ( 16 + $random() * 30 ) * $scale );
			$blink = 2 + (int) floor( $random() * 5 );
			$from  = (int) floor( $random() * $blink );
			$dx    = (int) floor( $random() * 160 ) - 80;
			$dy    = (int) floor( $random() * 160 ) - 80;
			$drift = 14 + (int) floor( $random() * 21 );

			if ( $still ) {
				$lights .= sprintf( "<circle cx='%d' cy='%d' r='%d' fill='url(#f)' opacity='.8'/>", $x, $y, $r );

				continue;
			}

			$lights .= sprintf(
				"<g transform='translate(%d %d)'><g><animateTransform attributeName='transform' type='translate' values='0 0;%d %d;0 0' dur='%ds' repeatCount='indefinite'/><circle r='%d' fill='url(#f)' opacity='.2'><animate attributeName='opacity' values='.15;1;.15' dur='%ds' begin='-%ds' repeatCount='indefinite'/></circle></g></g>",
				$x,
				$y,
				$dx,
				$dy,
				$drift,
				$r,
				$blink,
				$from
			);
		}

		$svg = "<svg xmlns='http://www.w3.org/2000/svg' width='1000' height='1000' viewBox='0 0 1000 1000' preserveAspectRatio='xMidYMid slice'>" . $defs . $lights . '</svg>';

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Rounds like JavaScript's Math.round( value * scale ) / scale. PHP's round() nudges near-ties first, which
	 * would make the waves and bokeh differ from the preview's by a digit here and there.
	 *
	 * @param float $value The number.
	 * @param int   $scale 10 for one decimal, 1000 for three.
	 */
	private static function js_round( float $value, int $scale ): float {
		return floor( $value * $scale + 0.5 ) / $scale;
	}
}
