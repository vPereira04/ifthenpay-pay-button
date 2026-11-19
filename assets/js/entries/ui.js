/* global iftpBtnEntries */
/**
 * Entries: the toast, scroll to top, copy on click and the popovers.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { i18n } = ns;

	ns.bindCopy = bindCopy;
	ns.bindExportMenu = bindExportMenu;
	ns.bindPopover = bindPopover;
	ns.bindScrollButton = bindScrollButton;
	ns.showToast = showToast;

	// ---- Feedback ----

	var toastTimer = null;

	// A sticky toast stays up until the next one replaces it (bulk progress).
	function showToast( message, isError, sticky ) {
		var toast = document.querySelector( '[data-iftp-toast]' );

		if ( ! toast ) {
			return;
		}

		toast.querySelector( '[data-iftp-toast-message]' ).textContent = message;
		toast.classList.toggle( 'iftp-btn-toast--error', !! isError );
		toast.hidden = false;

		clearTimeout( toastTimer );

		if ( ! sticky ) {
			toastTimer = setTimeout( function () {
				toast.hidden = true;
			}, 5000 );
		}
	}

	function bindScrollButton( wrap ) {
		var btn = wrap.querySelector( '[data-iftp-scroll-top]' );

		if ( ! btn ) {
			return;
		}

		var reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		var queued = false;

		window.addEventListener(
			'scroll',
			function () {
				if ( queued ) {
					return;
				}

				queued = true;
				window.requestAnimationFrame( function () {
					queued = false;
					btn.hidden = window.scrollY <= 400;
				} );
			},
			{ passive: true }
		);

		btn.addEventListener( 'click', function () {
			window.scrollTo( { top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' } );
		} );
	}

	// Delegated, since rows get swapped in by AJAX. Covers Copy link and the reference itself, on both pages.
	function bindCopy( scope ) {
		scope.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-iftp-copy-link], [data-iftp-copy-ref]' );

			if ( ! btn ) {
				return;
			}

			var isRef = btn.hasAttribute( 'data-iftp-copy-ref' );

			copyText( btn.getAttribute( isRef ? 'data-iftp-copy-ref' : 'data-iftp-copy-link' ), btn )
				.then( function () {
					btn.classList.add( 'iftp-btn-copied' );
					clearTimeout( btn.iftpCopiedTimer );
					btn.iftpCopiedTimer = setTimeout( function () {
						btn.classList.remove( 'iftp-btn-copied' );
					}, 2000 );

					showToast( isRef ? i18n.copyRefSuccess : i18n.copyLinkSuccess );
				} )
				.catch( function () {
					showToast( isRef ? i18n.copyRefError : i18n.copyLinkError, true );
				} );
		} );
	}

	function copyText( text, returnFocusTo ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return legacyCopy( text, returnFocusTo );
			} );
		}

		return legacyCopy( text, returnFocusTo );
	}

	// Plain-http admins have no Clipboard API, so I fall back to execCommand, then hand focus back.
	function legacyCopy( text, returnFocusTo ) {
		var textarea = document.createElement( 'textarea' );
		var copied = false;

		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'fixed';
		textarea.style.top = '0';
		textarea.style.left = '-9999px';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();

		try {
			copied = document.execCommand( 'copy' );
		} catch ( e ) {
			copied = false;
		}

		textarea.remove();

		if ( returnFocusTo && document.body.contains( returnFocusTo ) ) {
			returnFocusTo.focus();
		}

		return copied ? Promise.resolve() : Promise.reject( new Error( 'copy failed' ) );
	}

	// ---- Toolbar popovers and the filter panel ----

	// Shared popover rules: the trigger toggles; Escape, an outside click or focus leaving closes.
	function bindPopover( trigger, panel, onClose ) {
		function isOpen() {
			return ! panel.hidden;
		}

		function setOpen( open ) {
			panel.hidden = ! open;
			trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( ! open && onClose ) {
				onClose();
			}
		}

		function isInside( node ) {
			return !! node && ( trigger.contains( node ) || panel.contains( node ) );
		}

		function onFocusOut( event ) {
			// A null relatedTarget is a click on nothing focusable; the click handler covers that.
			if ( isOpen() && event.relatedTarget && ! isInside( event.relatedTarget ) ) {
				setOpen( false );
			}
		}

		trigger.addEventListener( 'click', function () {
			setOpen( ! isOpen() );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && isOpen() ) {
				setOpen( false );
				trigger.focus();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( isOpen() && ! isInside( event.target ) ) {
				setOpen( false );
			}
		} );

		trigger.addEventListener( 'focusout', onFocusOut );
		panel.addEventListener( 'focusout', onFocusOut );

		return setOpen;
	}

	function bindExportMenu( wrap ) {
		var trigger = wrap.querySelector( '[data-iftp-export-trigger]' );
		var panel = wrap.querySelector( '[data-iftp-export-menu]' );

		if ( ! trigger || ! panel ) {
			return;
		}

		var setOpen = bindPopover( trigger, panel );

		// The download doesn't leave the page, so I close the menu myself.
		panel.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-iftp-export-option]' ) ) {
				setOpen( false );
				trigger.focus();
			}
		} );
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
