/**
 * Checkout settings screen: checks the custom CSS and JavaScript boxes as they're typed. The same rules as
 * CustomCode.php, which has the final say when the form is saved.
 */
( function ( ns ) {
	'use strict';

	const i18n = ns.i18n || {};
	const MAX = 30000;
	const CSS_UNNESTABLE = /@(?:import|charset|namespace|layer|page|counter-style)\b/i;
	const JS_BANNED = /<\/script|<!--/i;
	const PAIRS = { '}': '{', ')': '(', ']': '[' };

	// Every bracket opens and closes, ignoring comments and what's in quotes.
	function balanced( css ) {
		const stack = [];
		let quote = '';

		for ( let i = 0; i < css.length; i++ ) {
			const char = css[ i ];

			if ( '\\' === char ) {
				i++;
				continue;
			}

			if ( quote ) {
				if ( char === quote ) {
					quote = '';
				} else if ( '\n' === char ) {
					return false;
				}
				continue;
			}

			if ( '/' === char && '*' === css[ i + 1 ] ) {
				const end = css.indexOf( '*/', i + 2 );

				if ( -1 === end ) {
					return false;
				}

				i = end + 1;
				continue;
			}

			if ( '"' === char || "'" === char ) {
				quote = char;
			} else if ( '{' === char || '(' === char || '[' === char ) {
				stack.push( char );
			} else if ( PAIRS[ char ] && stack.pop() !== PAIRS[ char ] ) {
				return false;
			}
		}

		return ! quote && 0 === stack.length;
	}

	// Comments and CSS escapes can't hide a banned word.
	function unescapeCss( css ) {
		return css
			.replace( /\/\*[\s\S]*?\*\//g, '' )
			.replace( /\\([0-9a-f]{1,6})\s?/gi, function ( match, hexa ) {
				try {
					return String.fromCodePoint( parseInt( hexa, 16 ) );
				} catch ( e ) {
					return '';
				}
			} )
			.replace( /\\([\s\S])/g, '$1' );
	}

	// '' when the code is fine, else what's wrong with it.
	function problem( kind, code, scoped ) {
		if ( code.length > MAX ) {
			return i18n.codeLength;
		}

		if ( 'css' === kind ) {
			if ( scoped && CSS_UNNESTABLE.test( unescapeCss( code ) ) ) {
				return i18n.codeNest;
			}

			return balanced( code ) ? '' : i18n.codeOpen;
		}

		if ( JS_BANNED.test( code ) ) {
			return i18n.codeBanned;
		}

		try {
			// Compiled, never called: it only finds out whether the code parses.
			new Function( code ); // eslint-disable-line no-new-func
		} catch ( error ) {
			// A page that forbids eval can't be checked here; the server still checks what it can.
			if ( error instanceof EvalError ) {
				return '';
			}

			return ( i18n.codeSyntax || '%s' ).replace( '%s', error && error.message ? error.message : '' );
		}

		return '';
	}

	function check( box ) {
		const state = box.parentElement.querySelector( '[data-iftp-code-state]' );
		const code = box.value.trim();
		// A box that's hidden (another mode is picked) can't show its message, so it must not block the save.
		const scoped = 0 === box.name.indexOf( 'config[code]' );
		const message = '' === code || box.closest( '[hidden]' ) ? '' : problem( box.dataset.iftpCodebox, code, scoped );

		box.setCustomValidity( message );
		box.setAttribute( 'aria-invalid', message ? 'true' : 'false' );

		if ( state ) {
			state.textContent = message || ( '' === code ? '' : i18n.codeOk );
			state.classList.toggle( 'is-error', '' !== message );
			state.classList.toggle( 'is-ok', '' === message && '' !== code );
		}
	}

	document.addEventListener( 'input', function ( event ) {
		if ( event.target.matches && event.target.matches( '[data-iftp-codebox]' ) ) {
			check( event.target );
		}
	} );

	// Picking another mode shows or hides boxes, so they are looked at again.
	document.addEventListener( 'change', function () {
		window.requestAnimationFrame( function () {
			document.querySelectorAll( '[data-iftp-codebox]' ).forEach( check );
		} );
	} );

	// Boxes that already hold code say so on load.
	document.querySelectorAll( '[data-iftp-codebox]' ).forEach( check );
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
