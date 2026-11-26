/* global iftpBtnEntries */
/**
 * Entries list: the insights cards.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	ns.bindInsights = bindInsights;

	// Performance: the period tabs (arrow keys move between them, like any tablist).
	function bindInsights( wrap ) {
		var section = wrap.querySelector( '[data-iftp-insights]' );

		if ( ! section ) {
			return;
		}

		var tabs = Array.prototype.slice.call( section.querySelectorAll( '[data-iftp-period]' ) );

		function select( tab, focus ) {
			tabs.forEach( function ( other ) {
				var on = other === tab;

				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.tabIndex = on ? 0 : -1;
				section.querySelector( '[data-iftp-period-panel="' + other.getAttribute( 'data-iftp-period' ) + '"]' ).hidden = ! on;
			} );

			// Revenue by method follows the chart's period.
			wrap.querySelectorAll( '[data-iftp-period-delta]' ).forEach( function ( body ) {
				body.hidden = body.getAttribute( 'data-iftp-period-delta' ) !== tab.getAttribute( 'data-iftp-period' );
			} );

			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[ event.key ];

				if ( undefined !== next ) {
					event.preventDefault();
					select( tabs[ ( next + tabs.length ) % tabs.length ], true );
				}
			} );
		} );
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
