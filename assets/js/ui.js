// Shared UI for Configuration and Entries: our own dropdowns over the native <select>s.
// The <select> stays in the form (name, value, change events, no-JS), hidden; a button + listbox replace its look.
( function () {
	'use strict';

	var selector = '.iftp-btn-settings select, .iftp-btn-entries select, .iftp-btn-entry select, .iftp-btn-dialog select, .iftp-btn-btoolbar select';
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

			wrap.classList.add( 'iftp-btn-dropdown--open' );
			button.setAttribute( 'aria-expanded', 'true' );
			position();
			seen = '';
			frame = window.requestAnimationFrame( track );

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

// A notice here is asked for by the address (?iftp_notice=...), so it comes back on every refresh. Closing it with the X takes it out of the address.
( function () {
	'use strict';

	var args = [ 'iftp_notice', 'iftp_restore', 'iftp_op', 'iftp_n' ];

	document.addEventListener( 'click', function ( event ) {
		if ( ! event.target.closest || ! event.target.closest( '.notice-dismiss' ) || ! window.history.replaceState ) {
			return;
		}

		var url = new URL( window.location.href );
		var had = false;

		args.forEach( function ( key ) {
			had = url.searchParams.has( key ) || had;
			url.searchParams.delete( key );
		} );

		if ( had ) {
			window.history.replaceState( null, '', url.pathname + url.search + url.hash );
		}
	} );
}() );

// The burger button lists the plugin's screens in a popover (top layer, so the masthead can't clip it). Native popover handles
// open, Esc and click-outside; this only places the list under the button and keeps aria-expanded honest.
( function () {
	'use strict';

	var button = document.querySelector( '[data-iftp-nav-toggle]' );
	var menu = document.getElementById( 'iftp-btn-nav' );

	if ( ! button || ! menu || typeof menu.showPopover !== 'function' ) {
		return;
	}

	function close() {
		if ( menu.matches( ':popover-open' ) ) {
			menu.hidePopover();
		}
	}

	menu.addEventListener( 'toggle', function ( event ) {
		var open = event.newState === 'open';
		var rect;

		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

		if ( open ) {
			rect = button.getBoundingClientRect();
			menu.style.position = 'fixed';
			menu.style.top = Math.round( rect.bottom + 8 ) + 'px';
			menu.style.left = Math.max( 8, Math.min( Math.round( rect.left ), window.innerWidth - menu.offsetWidth - 8 ) ) + 'px';
			window.addEventListener( 'scroll', close, { passive: true, once: true } );
			window.addEventListener( 'resize', close, { once: true } );
		}
	} );
}() );

// The sidebar button in the header drives WordPress's own "Collapse Menu" button, so the fold is saved per user like any other.
( function () {
	'use strict';

	var toggle = document.querySelector( '[data-iftp-menu-toggle]' );
	var core = document.getElementById( 'collapse-button' );
	var wrap = document.getElementById( 'adminmenuwrap' );
	var burger = document.querySelector( '[data-iftp-nav-toggle]' );
	var menu = document.getElementById( 'iftp-btn-nav' );

	if ( ! toggle || ! core || ! wrap ) {
		return;
	}

	// Folded is whatever the menu actually looks like: WordPress also folds it by itself on medium screens.
	function sync() {
		var folded = wrap.offsetWidth < 100;
		var label = folded ? toggle.dataset.show : toggle.dataset.hide;

		toggle.setAttribute( 'aria-expanded', folded ? 'false' : 'true' );
		toggle.setAttribute( 'aria-label', label );
		toggle.title = label;

		// The burger only shows while the WordPress menu is folded away; with it open you already have the menu.
		if ( burger ) {
			burger.hidden = ! folded;

			if ( ! folded && menu && menu.matches( ':popover-open' ) ) {
				menu.hidePopover();
			}
		}
	}

	toggle.hidden = false;

	toggle.addEventListener( 'click', function () {
		core.click();
	} );

	if ( 'ResizeObserver' in window ) {
		new ResizeObserver( sync ).observe( wrap );
	} else {
		window.addEventListener( 'resize', sync );
	}

	sync();
}() );

// Switches: press and hold, and the thumb swells into a glass bubble that moves like a drop of liquid: it follows the
// pointer on a soft spring and stretches a little with its speed, for as long as it moves. Let go and it drops into place.
// A plain click still toggles, since the checkbox underneath does that by itself.
( function () {
	'use strict';

	var selector = 'input.iftp-btn-switch, .iftp-btn-bcard__toggle input.iftp-btn-bcard__check';
	var held = null;
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function isSwitch( node ) {
		// The card's on/off is only a switch in the glass look; otherwise it's a plain checkbox.
		return !! node && !! node.matches && node.matches( selector ) && ! node.disabled && ( node.classList.contains( 'iftp-btn-switch' ) || !! node.closest( '.iftp-btn-glass' ) );
	}

	// The thumb's travel: the bubble is 30px wide and overhangs the 40px track by 2px at each end, so it moves 14px.
	function travel( input ) {
		return input.getBoundingClientRect().width - 26;
	}

	function frame() {
		var state = held;
		var stretch;
		var lean;

		if ( ! state ) {
			return;
		}

		// A damped spring toward the pointer: it eases in, and only just overshoots when the pointer stops.
		state.vel += ( state.target - state.pos ) * 0.18;
		state.vel *= 0.74;
		state.pos += state.vel;

		// Deformation follows the speed, smoothed so it comes and goes gently, and keeps the bubble's volume:
		// what it gains in length it loses in height.
		state.speed += ( Math.min( Math.abs( state.vel ), 6 ) - state.speed ) * 0.25;
		stretch = 1 + state.speed * 0.022;
		lean = Math.max( -1, Math.min( 1, state.vel / 5 ) );

		state.input.style.setProperty( '--iftp-drag', state.pos.toFixed( 2 ) + 'px' );
		state.input.style.setProperty( '--iftp-sx', stretch.toFixed( 3 ) );
		state.input.style.setProperty( '--iftp-sy', ( 1 / stretch ).toFixed( 3 ) );
		state.input.style.setProperty( '--iftp-ty', '0px' );
		state.input.style.setProperty( '--iftp-br', ( 50 + lean * 5 ).toFixed( 1 ) + '% ' + ( 50 - lean * 5 ).toFixed( 1 ) + '% ' + ( 50 - lean * 5 ).toFixed( 1 ) + '% ' + ( 50 + lean * 5 ).toFixed( 1 ) + '% / 50% 50% 50% 50%' );

		state.raf = window.requestAnimationFrame( frame );
	}

	document.addEventListener( 'pointerdown', function ( event ) {
		var input = event.target;
		var rest;

		if ( 0 !== event.button || ! isSwitch( input ) ) {
			return;
		}

		rest = input.checked ? travel( input ) : 0;
		held = { input: input, x: event.clientX, start: input.checked, moved: false, pos: rest, target: rest, vel: 0, speed: 0, raf: 0 };
		input.classList.add( 'is-held' );

		if ( input.setPointerCapture ) {
			input.setPointerCapture( event.pointerId );
		}

		if ( ! reduced ) {
			input.classList.add( 'is-dragging' );
			held.raf = window.requestAnimationFrame( frame );
		}
	} );

	document.addEventListener( 'pointermove', function ( event ) {
		var rect;

		if ( ! held ) {
			return;
		}

		rect = held.input.getBoundingClientRect();

		if ( Math.abs( event.clientX - held.x ) > 3 ) {
			held.moved = true;
		}

		if ( held.moved ) {
			// Past the middle of the track is on, before it is off.
			held.input.checked = event.clientX > rect.left + rect.width / 2;
			held.target = Math.max( 0, Math.min( travel( held.input ), event.clientX - rect.left - 15 ) );
		}
	} );

	function release() {
		var input;
		var changed;
		var stop;

		if ( ! held ) {
			return;
		}

		input = held.input;
		changed = held.moved && input.checked !== held.start;
		window.cancelAnimationFrame( held.raf );
		input.classList.remove( 'is-held', 'is-dragging' );

		// The inline numbers go, and the CSS spring carries the bubble from where it is to where it belongs.
		[ '--iftp-drag', '--iftp-sx', '--iftp-sy', '--iftp-ty', '--iftp-br' ].forEach( function ( name ) {
			input.style.removeProperty( name );
		} );

		if ( held.moved ) {
			// The click that follows a drag must not toggle it back.
			stop = function ( click ) {
				click.preventDefault();
			};

			input.addEventListener( 'click', stop, { once: true, capture: true } );
			window.setTimeout( function () {
				input.removeEventListener( 'click', stop, true );
			}, 60 );

			if ( changed ) {
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}

		held = null;
	}

	document.addEventListener( 'pointerup', release );
	document.addEventListener( 'pointercancel', release );
}() );

// Segmented options and tabs: one glass pill slides to whichever option is picked, stretching toward it on the way,
// instead of each option lighting up on its own. The options keep their own markup and events; this only draws the pill.
( function () {
	'use strict';

	var selector = '.iftp-btn-seg, .iftp-btn-bfilter, .iftp-btn-tabs, .iftp-btn-segmented, .iftp-btn-pills, .iftp-btn-msg__views';
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function picked( box ) {
		var on = box.querySelector( '[aria-pressed="true"], [aria-selected="true"]' );
		var radio = on ? null : box.querySelector( 'input:checked' );

		return on || ( radio ? radio.closest( 'label' ) : null );
	}

	function enhance( box ) {
		var slider = document.createElement( 'span' );
		var last = null;

		if ( box.iftpSlider ) {
			return;
		}

		box.iftpSlider = true;
		slider.className = 'iftp-btn-slider';
		slider.setAttribute( 'aria-hidden', 'true' );
		box.insertBefore( slider, box.firstChild );
		box.classList.add( 'has-slider' );

		function place( animate ) {
			var option = picked( box );
			var from;
			var to;
			var forward;

			if ( ! option || ! box.offsetWidth ) {
				slider.hidden = true;
				return;
			}

			from = last;
			to = { left: option.offsetLeft, top: option.offsetTop, width: option.offsetWidth, height: option.offsetHeight };
			slider.hidden = false;

			if ( ! last || ! animate || reduced ) {
				slider.classList.add( 'is-still' );
			} else {
				slider.classList.remove( 'is-still' );
			}

			slider.style.left = to.left + 'px';
			slider.style.top = to.top + 'px';
			slider.style.width = to.width + 'px';
			slider.style.height = to.height + 'px';

			// The pill stretches toward where it is going, anchored at the side it leaves.
			if ( from && animate && ! reduced && from.left !== to.left && slider.animate ) {
				forward = to.left > from.left;
				slider.style.transformOrigin = forward ? 'left center' : 'right center';
				slider.animate( [ { transform: 'scale(1, 1)' }, { transform: 'scale(1.14, 1)' }, { transform: 'scale(1, 1)' } ], { duration: 440, easing: 'ease' } );
			}

			if ( ! last ) {
				// The first placement is instant; reading a layout property makes the next one animate.
				void slider.offsetWidth;
				slider.classList.remove( 'is-still' );
			}

			last = to;
		}

		new MutationObserver( function () {
			place( true );
		} ).observe( box, { attributes: true, subtree: true, attributeFilter: [ 'aria-pressed', 'aria-selected' ] } );

		box.addEventListener( 'change', function () {
			place( true );
		} );

		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( function () {
				// A resize, or a hidden box coming on screen, puts the pill straight under the pick.
				last = null;
				place( false );
			} ).observe( box );
		}

		place( false );
	}

	function scan( root ) {
		( root || document ).querySelectorAll( selector ).forEach( enhance );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			scan();
		} );
	} else {
		scan();
	}

	// Boxes that appear later (the editor draws some).
	new MutationObserver( function ( records ) {
		records.forEach( function ( record ) {
			record.addedNodes.forEach( function ( node ) {
				if ( 1 === node.nodeType ) {
					if ( node.matches( selector ) ) {
						enhance( node );
					}

					scan( node );
				}
			} );
		} );
	} ).observe( document.documentElement, { childList: true, subtree: true } );
}() );

// The messages the server queued after an action: they close themselves after their time (0 = they stay),
// pause while hovered or focused, and have a close button.
( function () {
	'use strict';

	function leave( toast ) {
		if ( toast.classList.contains( 'is-leaving' ) ) {
			return;
		}

		toast.classList.add( 'is-leaving' );
		window.setTimeout( function () {
			toast.remove();
		}, 250 );
	}

	function arm( toast ) {
		var wait = parseInt( toast.getAttribute( 'data-iftp-flash' ), 10 ) || 0;
		var timer = 0;
		var close = toast.querySelector( '[data-iftp-flash-close]' );

		function start() {
			window.clearTimeout( timer );

			if ( wait > 0 ) {
				timer = window.setTimeout( function () {
					leave( toast );
				}, wait );
			}
		}

		function stop() {
			window.clearTimeout( timer );
		}

		if ( close ) {
			close.addEventListener( 'click', function () {
				leave( toast );
			} );
		}

		toast.addEventListener( 'mouseenter', stop );
		toast.addEventListener( 'mouseleave', start );
		toast.addEventListener( 'focusin', stop );
		toast.addEventListener( 'focusout', start );
		start();
	}

	function init() {
		document.querySelectorAll( '[data-iftp-flash]' ).forEach( arm );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
