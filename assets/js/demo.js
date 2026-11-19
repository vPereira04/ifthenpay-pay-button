/**
 * The demo page: the made-up site behind the button follows the preview's colour mode.
 */
( function () {
	'use strict';

	// In the editor's frame there is nothing to go back to, so the button hides.
	try {
		if ( window.self !== window.top ) {
			document.body.classList.add( 'is-framed' );
		}
	} catch ( e ) {
		document.body.classList.add( 'is-framed' );
	}

	// Opened in its own tab from the editor: Close shuts that tab, and the editor is still open where it was.
	// A browser only lets a script close a tab it may close; when this one stays open, the link goes back to the editor instead.
	const close = document.querySelector( '[data-iftp-demo-back]' );

	if ( close ) {
		close.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			window.close();

			window.setTimeout( function () {
				if ( ! window.closed ) {
					window.location.href = close.href;
				}
			}, 200 );
		} );
	}

	// The made-up site behind the button follows the preview's colour mode (Light, System or Dark), and so does the checkout.
	const scheme = window.iftpBtnScheme;

	if ( scheme ) {
		document.body.dataset.theme = scheme.effective();
		scheme.subscribe( function ( mode, shown ) {
			document.body.dataset.theme = shown;
		} );
	}
}() );
