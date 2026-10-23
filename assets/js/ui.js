// Shared UI for Gateway Configuration and Entries: our own dropdowns over the native <select>s.
// The <select> stays in the form (name, value, change events, no-JS), hidden; a button + listbox replace its look.
( function () {
	'use strict';

	var selector = '.iftp-btn-settings select, .iftp-btn-entries select, .iftp-btn-entry select, .iftp-btn-dialog select';
	var chevronSvg = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	var checkSvg = '<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3.5 8.5 3 3 6-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	// The top layer keeps the list above dialogs and out of any overflow clipping; older browsers get it absolutely positioned.
	var hasPopover = Object.prototype.hasOwnProperty.call( HTMLElement.prototype, 'popover' );
	var uid = 0;

	function enhance( select ) {
		if ( select.iftpDropdown || select.multiple ) {
			return;
		}

		var id = select.id || 'iftp-btn-select-' + ++uid;
		var wrap = document.createElement( 'span' );
		var button = document.createElement( 'button' );
		var label = document.createElement( 'span' );
		var chevron = document.createElement( 'span' );
		var list = document.createElement( 'ul' );
		var active = -1;
		var typed = '';
		var typedTimer = 0;

		wrap.className = 'iftp-btn-dropdown';

		// The button takes the select's classes, so it sizes and looks like the control it replaces.
		button.type = 'button';
		button.className = 'iftp-btn-dropdown__button ' + select.className;
		button.setAttribute( 'aria-haspopup', 'listbox' );
		button.setAttribute( 'aria-expanded', 'false' );

		if ( select.hasAttribute( 'aria-describedby' ) ) {
			button.setAttribute( 'aria-describedby', select.getAttribute( 'aria-describedby' ) );
		}

		label.className = 'iftp-btn-dropdown__value';
		chevron.className = 'iftp-btn-dropdown__chevron';
		chevron.innerHTML = chevronSvg;
		button.append( label, chevron );

		list.className = 'iftp-btn-dropdown__list';
		list.setAttribute( 'role', 'listbox' );
		list.tabIndex = -1;

		if ( hasPopover ) {
			list.setAttribute( 'popover', 'manual' );
		} else {
			list.hidden = true;
		}

		// The <label for> now points at the button: a click on it opens the list, and screen readers name it.
		select.id = id + '-native';
		button.id = id;
		list.id = id + '-list';
		button.setAttribute( 'aria-controls', list.id );
		list.setAttribute( 'aria-labelledby', id );

		select.classList.add( 'iftp-btn-dropdown__native' );
		select.tabIndex = -1;
		select.setAttribute( 'aria-hidden', 'true' );
		select.parentNode.insertBefore( wrap, select );
		wrap.append( select, button, list );

		function isOpen() {
			return 'true' === button.getAttribute( 'aria-expanded' );
		}

		function sync() {
			var option = select.options[ select.selectedIndex ];

			label.textContent = option ? option.textContent.trim() : '';
			button.disabled = select.disabled;
			wrap.classList.toggle( 'iftp-btn-dropdown--empty', ! option || '' === option.value );
		}

		// Rebuilt on every open, so options shown, hidden or disabled since (the bulk actions do that) are current.
		function build() {
			list.textContent = '';

			Array.prototype.forEach.call( select.options, function ( option ) {
				if ( option.hidden ) {
					return;
				}

				var item = document.createElement( 'li' );
				var text = document.createElement( 'span' );
				var tick = document.createElement( 'span' );

				item.className = 'iftp-btn-dropdown__option';
				item.id = list.id + '-' + option.index;
				item.setAttribute( 'role', 'option' );
				item.setAttribute( 'aria-selected', option.selected ? 'true' : 'false' );
				item.setAttribute( 'data-index', option.index );

				if ( option.disabled ) {
					item.setAttribute( 'aria-disabled', 'true' );
				}

				text.textContent = option.textContent.trim();
				tick.className = 'iftp-btn-dropdown__tick';
				tick.innerHTML = checkSvg;
				item.append( text, tick );
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

		// Scrolls the list only; scrollIntoView() would scroll the page too, and a page scroll closes the list.
		function keepInView( item ) {
			if ( item.offsetTop < list.scrollTop ) {
				list.scrollTop = item.offsetTop - 6;
			} else if ( item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight ) {
				list.scrollTop = item.offsetTop + item.offsetHeight - list.clientHeight + 6;
			}
		}

		// Next usable option in a direction, wrapping never: the ends stop, like a native select.
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
			var height = list.offsetHeight;
			var below = window.innerHeight - rect.bottom;

			list.style.minWidth = rect.width + 'px';
			list.style.top = ( height + 12 > below && rect.top > below ? rect.top - height - 6 : rect.bottom + 6 ) + 'px';
			list.style.left = Math.max( 8, Math.min( rect.left, window.innerWidth - list.offsetWidth - 8 ) ) + 'px';
		}

		function onOutside( event ) {
			if ( ! wrap.contains( event.target ) && ! list.contains( event.target ) ) {
				close( false );
			}
		}

		// Scrolling or resizing keeps the list on its button; it only closes once the button is off screen.
		function onViewportChange( event ) {
			if ( event && list.contains( event.target ) ) {
				return;
			}

			var rect = button.getBoundingClientRect();

			if ( rect.bottom < 0 || rect.top > window.innerHeight ) {
				close( false );
				return;
			}

			position();
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

			wrap.classList.add( 'iftp-btn-dropdown--open' );
			button.setAttribute( 'aria-expanded', 'true' );
			position();

			var selected = items().findIndex( function ( item ) {
				return 'true' === item.getAttribute( 'aria-selected' );
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

			wrap.classList.remove( 'iftp-btn-dropdown--open' );
			button.setAttribute( 'aria-expanded', 'false' );
			list.removeAttribute( 'aria-activedescendant' );

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
				return usable( item ) && 0 === item.textContent.trim().toLowerCase().indexOf( typed );
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
					// Stops here, so an Escape meant for the list doesn't also close the dialog around it.
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

		// Other scripts set select.value directly (the bulk bar resets itself that way), so the label follows those too.
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

		select.addEventListener( 'change', sync );

		if ( select.form ) {
			select.form.addEventListener( 'reset', function () {
				setTimeout( sync, 0 );
			} );
		}

		select.iftpDropdown = { button, sync, close };
		sync();
	}

	function init( root ) {
		( root || document ).querySelectorAll( selector ).forEach( enhance );
	}

	// For scripts that move focus to a select: it has to land on the button that stands in for it.
	function focus( select ) {
		( select && select.iftpDropdown ? select.iftpDropdown.button : select ).focus();
	}

	window.iftpBtnDropdowns = { init, focus };

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}
} )();
