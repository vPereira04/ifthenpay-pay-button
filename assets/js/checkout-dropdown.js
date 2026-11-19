// The checkout form's own dropdown: a button and a listbox over the native <select>, drawn in the form's colours.
// The <select> stays in the form (name, value, validity, change events), hidden; checkout.js keeps reading it.
( function () {
	'use strict';

	var selector = '.iftp-btn-form__select > select';
	var chevronSvg = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	var checkSvg = '<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3.5 8.5 3 3 6-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	// The top layer keeps the list above the checkout <dialog> and out of its clipping; older browsers get it absolutely placed.
	var hasPopover = Object.prototype.hasOwnProperty.call( HTMLElement.prototype, 'popover' );
	var uid = 0;

	function part( tag, className, text ) {
		var node = document.createElement( tag );

		node.className = className;

		if ( text ) {
			node.textContent = text;
		}

		return node;
	}

	function enhance( select ) {
		if ( select.iftpDropdown || select.multiple ) {
			return;
		}

		var id = select.id || 'iftp-btn-form-select-' + ++uid;
		var holder = select.parentNode;
		var button = part( 'button', 'iftp-btn-dropdown__button ' + select.className );
		var label = part( 'span', 'iftp-btn-dropdown__value' );
		var chevron = part( 'span', 'iftp-btn-dropdown__chevron' );
		var list = part( 'ul', 'iftp-btn-dropdown__list' );
		var active = -1;
		var typed = '';
		var typedTimer = 0;

		holder.classList.add( 'iftp-btn-dropdown' );

		button.type = 'button';
		button.setAttribute( 'aria-haspopup', 'listbox' );
		button.setAttribute( 'aria-expanded', 'false' );

		[ 'aria-describedby', 'aria-required' ].forEach( function ( name ) {
			if ( select.hasAttribute( name ) ) {
				button.setAttribute( name, select.getAttribute( name ) );
			}
		} );

		if ( select.required ) {
			button.setAttribute( 'aria-required', 'true' );
		}

		chevron.innerHTML = chevronSvg;
		button.append( label, chevron );

		list.setAttribute( 'role', 'listbox' );
		list.tabIndex = -1;

		if ( hasPopover ) {
			list.setAttribute( 'popover', 'manual' );
		} else {
			list.hidden = true;
		}

		// The <label for> now points at the button, so a click on it opens the list and screen readers name it.
		select.id = id + '-native';
		button.id = id;
		list.id = id + '-list';
		button.setAttribute( 'aria-controls', list.id );
		list.setAttribute( 'aria-labelledby', id );

		select.classList.add( 'iftp-btn-dropdown__native' );
		select.tabIndex = -1;
		select.setAttribute( 'aria-hidden', 'true' );
		holder.append( button, list );

		function isOpen() {
			return 'true' === button.getAttribute( 'aria-expanded' );
		}

		function sync() {
			var option = select.options[ select.selectedIndex ];

			label.textContent = option ? option.textContent.trim() : '';
			// A picked option shows its name and price, like a native select; the badge stays in the list.
			if ( option && option.dataset.label && '' !== option.value ) {
				label.textContent = option.dataset.label + ( option.dataset.meta ? ' · ' + option.dataset.meta : '' );
			}

			button.disabled = select.disabled;
			holder.classList.toggle( 'iftp-btn-dropdown--empty', ! option || '' === option.value );
		}

		// Rebuilt on every open, so a sold-out option or a changed price is current.
		function build() {
			list.textContent = '';

			Array.prototype.forEach.call( select.options, function ( option ) {
				if ( option.hidden ) {
					return;
				}

				var item = part( 'li', 'iftp-btn-dropdown__option' );
				var name = part( 'span', 'iftp-btn-dropdown__name', option.dataset.label || option.textContent.trim() );
				var side = part( 'span', 'iftp-btn-dropdown__side' );
				var tick = part( 'span', 'iftp-btn-dropdown__tick' );

				item.id = list.id + '-' + option.index;
				item.setAttribute( 'role', 'option' );
				item.setAttribute( 'aria-selected', option.selected ? 'true' : 'false' );
				item.setAttribute( 'data-index', option.index );

				if ( option.disabled ) {
					item.setAttribute( 'aria-disabled', 'true' );
				}

				if ( '' === option.value ) {
					item.classList.add( 'iftp-btn-dropdown__option--placeholder' );
				}

				if ( option.dataset.meta ) {
					side.appendChild( part( 'span', 'iftp-btn-dropdown__meta', option.dataset.meta ) );
				}

				if ( option.dataset.badge ) {
					side.appendChild( part( 'span', 'iftp-btn-dropdown__badge', option.dataset.badge ) );
				}

				tick.innerHTML = checkSvg;
				side.appendChild( tick );
				item.append( name, side );
				list.appendChild( item );
			} );
		}

		function items() {
			return Array.prototype.slice.call( list.children );
		}

		function usable( item ) {
			return item && 'true' !== item.getAttribute( 'aria-disabled' );
		}

		function setActive( index ) {
			var all = items();

			active = Math.max( 0, Math.min( index, all.length - 1 ) );
			all.forEach( function ( item, i ) {
				item.classList.toggle( 'iftp-btn-dropdown__option--active', i === active );
			} );

			if ( all[ active ] ) {
				list.setAttribute( 'aria-activedescendant', all[ active ].id );
				keepInView( all[ active ] );
			}
		}

		// Scrolls the list only; scrollIntoView() would scroll the page too, and a page scroll could close the list.
		function keepInView( item ) {
			if ( item.offsetTop < list.scrollTop ) {
				list.scrollTop = item.offsetTop - 6;
			} else if ( item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight ) {
				list.scrollTop = item.offsetTop + item.offsetHeight - list.clientHeight + 6;
			}
		}

		// The next usable option in a direction; the ends stop, like a native select.
		function step( from, direction ) {
			var all = items();

			for ( var i = from + direction; i >= 0 && i < all.length; i += direction ) {
				if ( usable( all[ i ] ) ) {
					return i;
				}
			}

			return from;
		}

		// Below the button when it fits, above when there's more room there; kept inside the viewport.
		function position() {
			if ( ! hasPopover ) {
				return;
			}

			var rect = button.getBoundingClientRect();
			var below = window.innerHeight - rect.bottom - 14;
			var above = rect.top - 14;
			var up = list.scrollHeight > below && above > below;

			list.style.minWidth = rect.width + 'px';
			list.style.maxHeight = Math.max( 140, Math.min( 360, up ? above : below ) ) + 'px';

			var height = list.offsetHeight;

			list.style.top = ( up ? Math.max( 8, rect.top - height - 6 ) : rect.bottom + 6 ) + 'px';
			list.style.left = Math.max( 8, Math.min( rect.left, window.innerWidth - list.offsetWidth - 8 ) ) + 'px';
		}

		function onOutside( event ) {
			if ( ! holder.contains( event.target ) ) {
				close( false );
			}
		}

		// Scrolling or resizing keeps the list on its button; it closes once the button is off screen.
		function onViewportChange( event ) {
			if ( event && list.contains( event.target ) ) {
				return;
			}

			var rect = button.getBoundingClientRect();

			if ( rect.bottom < 0 || rect.top > window.innerHeight || ! button.offsetWidth ) {
				close( false );
				return;
			}

			position();
		}

		// Follows the button for as long as the list is open: a resize, a scroll or the dialog still settling
		// all move it, and events alone fire before the layout has caught up.
		var frame = 0;
		var seen = '';

		function track() {
			var rect = button.getBoundingClientRect();
			var now = [ rect.left, rect.top, rect.width, rect.height, window.innerWidth, window.innerHeight ].join();

			if ( now !== seen ) {
				seen = now;
				onViewportChange();
			}

			frame = isOpen() ? window.requestAnimationFrame( track ) : 0;
		}

		function open() {
			if ( isOpen() || select.disabled ) {
				return;
			}

			build();

			if ( hasPopover ) {
				list.showPopover();
			} else {
				list.hidden = false;
			}

			holder.classList.add( 'iftp-btn-dropdown--open' );
			button.setAttribute( 'aria-expanded', 'true' );
			position();
			seen = '';
			frame = window.requestAnimationFrame( track );

			var selected = items().findIndex( function ( item ) {
				return 'true' === item.getAttribute( 'aria-selected' ) && usable( item );
			} );

			setActive( -1 !== selected ? selected : step( -1, 1 ) );
			list.focus( { preventScroll: true } );

			document.addEventListener( 'pointerdown', onOutside, true );
			window.addEventListener( 'resize', onViewportChange );
			window.addEventListener( 'scroll', onViewportChange, true );
		}

		function close( returnFocus ) {
			if ( ! isOpen() ) {
				return;
			}

			if ( hasPopover ) {
				list.hidePopover();
			} else {
				list.hidden = true;
			}

			holder.classList.remove( 'iftp-btn-dropdown--open' );
			button.setAttribute( 'aria-expanded', 'false' );
			list.removeAttribute( 'aria-activedescendant' );

			window.cancelAnimationFrame( frame );
			document.removeEventListener( 'pointerdown', onOutside, true );
			window.removeEventListener( 'resize', onViewportChange );
			window.removeEventListener( 'scroll', onViewportChange, true );

			if ( returnFocus ) {
				button.focus();
			}
		}

		// Commits like a native select: a change event only when the value actually changed.
		function choose( item ) {
			if ( ! usable( item ) ) {
				return;
			}

			var index = parseInt( item.getAttribute( 'data-index' ), 10 );
			var changed = select.selectedIndex !== index;

			select.selectedIndex = index;
			close( true );

			if ( changed ) {
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}

		// Type-ahead: letters typed in quick succession jump to the first option that starts with them.
		function typeAhead( key ) {
			clearTimeout( typedTimer );
			typed += key.toLowerCase();
			typedTimer = setTimeout( function () {
				typed = '';
			}, 600 );

			var match = items().findIndex( function ( item ) {
				return usable( item ) && 0 === item.firstChild.textContent.trim().toLowerCase().indexOf( typed );
			} );

			if ( -1 !== match ) {
				setActive( match );
			}
		}

		button.addEventListener( 'click', function () {
			if ( isOpen() ) {
				close( true );
			} else {
				open();
			}
		} );

		button.addEventListener( 'keydown', function ( event ) {
			if ( -1 !== [ 'ArrowDown', 'ArrowUp', 'Enter', ' ' ].indexOf( event.key ) ) {
				event.preventDefault();
				open();
			}
		} );

		list.addEventListener( 'keydown', function ( event ) {
			var all = items();

			switch ( event.key ) {
				case 'ArrowDown':
					setActive( step( active, 1 ) );
					break;
				case 'ArrowUp':
					setActive( step( active, -1 ) );
					break;
				case 'Home':
					setActive( step( -1, 1 ) );
					break;
				case 'End':
					setActive( step( all.length, -1 ) );
					break;
				case 'Enter':
				case ' ':
					choose( all[ active ] );
					break;
				case 'Escape':
					// Stops here, so the Escape meant for the list doesn't also close the checkout around it.
					event.stopPropagation();
					close( true );
					break;
				case 'Tab':
					close( true );
					break;
				default:
					if ( 1 === event.key.length && ! event.ctrlKey && ! event.metaKey && ! event.altKey ) {
						typeAhead( event.key );
						break;
					}

					return;
			}

			event.preventDefault();
		} );

		list.addEventListener( 'click', function ( event ) {
			choose( event.target.closest( '[role="option"]' ) );
		} );

		list.addEventListener( 'pointermove', function ( event ) {
			var item = event.target.closest( '[role="option"]' );

			if ( item && usable( item ) ) {
				setActive( items().indexOf( item ) );
			}
		} );

		// checkout.js sets select.value directly (prefill, reset) and focuses an invalid select, so the label follows
		// the value and the focus lands on the button that stands in for it.
		[ 'value', 'selectedIndex' ].forEach( function ( prop ) {
			var native = Object.getOwnPropertyDescriptor( HTMLSelectElement.prototype, prop );

			Object.defineProperty( select, prop, {
				configurable: true,
				get() {
					return native.get.call( this );
				},
				set( next ) {
					native.set.call( this, next );
					sync();
				},
			} );
		} );

		select.focus = function ( options ) {
			button.focus( options );
		};

		select.addEventListener( 'change', sync );

		// A condition switches the select off (and the sheet closing hides it); the button follows.
		if ( 'MutationObserver' in window ) {
			new MutationObserver( function () {
				sync();

				if ( select.disabled ) {
					close( false );
				}
			} ).observe( select, { attributes: true, attributeFilter: [ 'disabled' ] } );
		}

		if ( select.form ) {
			select.form.addEventListener( 'reset', function () {
				setTimeout( sync, 0 );
			} );
		}

		select.iftpDropdown = { button: button, sync: sync, close: close };
		sync();
	}

	function init( root ) {
		( root || document ).querySelectorAll( selector ).forEach( enhance );
	}

	window.iftpBtnCheckoutDropdowns = { init: init };

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	// Forms drawn later (the editor's live preview) get theirs too.
	if ( 'MutationObserver' in window ) {
		new MutationObserver( function ( records ) {
			records.forEach( function ( record ) {
				record.addedNodes.forEach( function ( node ) {
					if ( 1 === node.nodeType ) {
						if ( node.matches( selector ) ) {
							enhance( node );
						}

						init( node );
					}
				} );
			} );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
