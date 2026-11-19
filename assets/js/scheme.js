/**
 * The preview colour mode: Light, System or Dark. One choice, kept in this browser, shared by every preview
 * (the AI studio, the editor's demo page, the checkout and button previews) and every tab that is open.
 *
 * Light is the default, so a preview never opens dark by surprise. System follows the device.
 */
( function () {
	'use strict';

	var KEY = 'iftpBtnPreviewScheme';
	var MODES = [ 'light', 'system', 'dark' ];
	var listeners = [];
	var query = window.matchMedia ? window.matchMedia( '(prefers-color-scheme: dark)' ) : null;
	var current = read();

	function read() {
		try {
			var saved = window.localStorage.getItem( KEY );

			return MODES.indexOf( saved ) !== -1 ? saved : 'light';
		} catch ( e ) {
			return 'light';
		}
	}

	// What the preview actually shows: System turns into whatever the device is using right now.
	function effective( mode ) {
		mode = mode || current;

		if ( 'system' === mode ) {
			return query && query.matches ? 'dark' : 'light';
		}

		return mode;
	}

	function paint() {
		document.querySelectorAll( '[data-iftp-scheme-pick]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', button.dataset.iftpSchemePick === current ? 'true' : 'false' );
		} );

		if ( window.iftpBtnSchemeSelf ) {
			applyTo( document, current );
		}

		listeners.slice().forEach( function ( listener ) {
			listener( current, effective() );
		} );
	}

	// Light and Dark are forced on a page; System takes the force off, so the page follows the device itself.
	function applyTo( doc, mode ) {
		var root = doc && doc.documentElement;

		if ( ! root ) {
			return;
		}

		if ( 'light' === mode || 'dark' === mode ) {
			root.setAttribute( 'data-iftp-scheme', mode );
		} else {
			root.removeAttribute( 'data-iftp-scheme' );
		}

	}

	// persist=false is for a preview that switches itself (e.g. while editing the dark colours): it doesn't change the saved choice.
	function set( mode, persist ) {
		if ( MODES.indexOf( mode ) === -1 ) {
			return;
		}

		current = mode;

		if ( false !== persist ) {
			try {
				window.localStorage.setItem( KEY, mode );
			} catch ( e ) {}
		}

		paint();
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-iftp-scheme-pick]' ) : null;

		if ( button ) {
			set( button.dataset.iftpSchemePick );
		}
	} );

	// Another tab, or the page inside the preview frame, changed it.
	window.addEventListener( 'storage', function ( event ) {
		if ( event.key === KEY ) {
			current = read();
			paint();
		}
	} );

	if ( query && query.addEventListener ) {
		query.addEventListener( 'change', function () {
			if ( 'system' === current ) {
				paint();
			}
		} );
	}

	window.iftpBtnScheme = {
		mode: function () {
			return current;
		},
		effective: effective,
		set: set,
		applyTo: applyTo,
		subscribe: function ( listener ) {
			listeners.push( listener );
		},
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', paint );
	} else {
		paint();
	}
}() );
