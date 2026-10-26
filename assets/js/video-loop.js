// Watches a pay page background video: says when its first frame is showing, so the card can wait for it, and
// loops it between two moments (data-loop-start / data-loop-end, in seconds). A file is moved with currentTime;
// YouTube and Vimeo players are told where to go through their postMessage APIs, so no player library has to load.
// Without a data-loop-start the video just loops whole.
( function () {
	'use strict';

	var players = [];

	// How long a card waits for its video before showing anyway (checkout.css has the same, for no JS).
	var WAIT = 5000;

	// The layer starts is-waiting, which keeps the card back under a spinner; the first frame lifts it.
	function shown( media ) {
		var layer = media.closest( '[data-iftp-video]' );

		if ( layer && layer.classList.contains( 'is-waiting' ) ) {
			layer.classList.remove( 'is-waiting' );
			layer.classList.add( 'is-ready' );
		}
	}

	// Counted from when it starts loading: a checkout's video only gets its src when the checkout opens.
	function failsafe( media ) {
		var later = function () {
			setTimeout( function () {
				shown( media );
			}, WAIT );
		};

		if ( media.getAttribute( 'src' ) ) {
			later();

			return;
		}

		new MutationObserver( function ( changes, observer ) {
			observer.disconnect();
			later();
		} ).observe( media, { attributes: true, attributeFilter: [ 'src' ] } );
	}

	// Back to the start once it reaches the end, or when the player wraps round to 0 on its own.
	function keep( media, seconds, seek ) {
		var start = parseFloat( media.getAttribute( 'data-loop-start' ) ) || 0;
		var end = parseFloat( media.getAttribute( 'data-loop-end' ) ) || 0;
		var last = parseFloat( media.getAttribute( 'data-loop-seek' ) ) || 0;

		// A player reports the old time for a moment after a seek; one jump at a time.
		if ( ! media.hasAttribute( 'data-loop-start' ) || Date.now() - last < 1000 ) {
			return;
		}

		if ( seconds < start - 0.5 || ( end > start && seconds >= end ) ) {
			media.setAttribute( 'data-loop-seek', String( Date.now() ) );
			seek( start );
		}
	}

	function post( frame, message ) {
		if ( frame.contentWindow ) {
			frame.contentWindow.postMessage( JSON.stringify( message ), '*' );
		}
	}

	// Asks the player to report its time: Vimeo per event, YouTube as a running stream once it's listened to.
	function hello( frame ) {
		if ( /vimeo\.com/.test( frame.src ) ) {
			post( frame, { method: 'addEventListener', value: 'timeupdate' } );
		} else {
			post( frame, { event: 'listening', id: 1, channel: 'widget' } );
		}
	}

	// A player only answers once its own script is up, so this asks again until it does, as YouTube's API does.
	function greet( frame ) {
		var tries = 0;
		var timer = setInterval( function () {
			if ( frame.hasAttribute( 'data-video-heard' ) || ++tries > WAIT / 250 ) {
				clearInterval( timer );

				return;
			}

			hello( frame );
		}, 250 );
	}

	function bind( media ) {
		if ( ! media || media.hasAttribute( 'data-video-bound' ) ) {
			return;
		}

		media.setAttribute( 'data-video-bound', '' );
		failsafe( media );

		if ( media.tagName === 'VIDEO' ) {
			// HAVE_CURRENT_DATA: a frame is already there.
			if ( media.readyState >= 2 ) {
				shown( media );
			}

			media.addEventListener( 'loadeddata', function () {
				shown( media );
			} );

			media.addEventListener( 'error', function () {
				shown( media );
			} );

			if ( ! media.hasAttribute( 'data-loop-start' ) ) {
				return;
			}

			media.addEventListener( 'loadedmetadata', function () {
				media.currentTime = parseFloat( media.getAttribute( 'data-loop-start' ) ) || 0;
			} );

			media.addEventListener( 'timeupdate', function () {
				keep( media, media.currentTime, function ( to ) {
					media.currentTime = to;
				} );
			} );

			return;
		}

		players.push( media );
		media.addEventListener( 'load', function () {
			greet( media );
		} );

		if ( media.getAttribute( 'src' ) ) {
			greet( media );
		}
	}

	window.addEventListener( 'message', function ( event ) {
		var frame = null;
		var data = null;

		players.forEach( function ( player ) {
			if ( player.contentWindow === event.source ) {
				frame = player;
			}
		} );

		if ( ! frame ) {
			return;
		}

		try {
			data = typeof event.data === 'string' ? JSON.parse( event.data ) : event.data;
		} catch ( e ) {
			return;
		}

		if ( ! data ) {
			return;
		}

		frame.setAttribute( 'data-video-heard', '' );

		// Vimeo says when it's ready for the listener, which may be after the iframe's own load.
		if ( data.event === 'ready' ) {
			hello( frame );
		} else if ( data.event === 'timeupdate' && data.data ) {
			shown( frame );
			keep( frame, data.data.seconds, function ( to ) {
				post( frame, { method: 'setCurrentTime', value: to } );
			} );
		} else if ( data.event === 'infoDelivery' && data.info ) {
			// 1 is YouTube's "playing".
			if ( data.info.playerState === 1 ) {
				shown( frame );
			}

			if ( typeof data.info.currentTime === 'number' ) {
				keep( frame, data.info.currentTime, function ( to ) {
					post( frame, { event: 'command', func: 'seekTo', args: [ to, true ] } );
				} );
			}
		}
	} );

	window.iftpBtnVideoLoop = bind;

	document.querySelectorAll( '[data-iftp-video] .iftp-btn-pagevideo__media' ).forEach( bind );
} )();
