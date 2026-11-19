// The checkout form's own date picker: a text box (type the date) and a calendar panel over the native <input type="date">, drawn in the form's colours.
// The input stays in the form (name, ISO value, validity, change events), hidden; checkout.js keeps reading it.
( function () {
	'use strict';

	var selector = '.iftp-btn-form__field--date > input[type="date"]';
	var hasPopover = Object.prototype.hasOwnProperty.call( HTMLElement.prototype, 'popover' );
	var lang = document.documentElement.lang || undefined;
	var uid = 0;
	var icons = {
		calendar: '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M8 2.5v4M16 2.5v4M3 10h18"/></svg>',
		prev: '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m10 3.5-4.5 4.5 4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		next: '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m6 3.5 4.5 4.5L6 12.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	};

	function part( tag, className, text ) {
		var node = document.createElement( tag );

		node.className = className;

		if ( text ) {
			node.textContent = text;
		}

		return node;
	}

	function pad( number ) {
		return ( number < 10 ? '0' : '' ) + number;
	}

	function toIso( date ) {
		return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() );
	}

	function parse( value ) {
		var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( value || '' );

		if ( ! match ) {
			return null;
		}

		var date = new Date( +match[ 1 ], +match[ 2 ] - 1, +match[ 3 ] );

		return date.getMonth() === +match[ 2 ] - 1 ? date : null;
	}

	function addDays( date, days ) {
		return new Date( date.getFullYear(), date.getMonth(), date.getDate() + days );
	}

	// A month or year step that keeps the day in range (31 Jan + 1 month is the end of Feb, not March).
	function addMonths( date, months ) {
		var target = new Date( date.getFullYear(), date.getMonth() + months, 1 );
		var last = new Date( target.getFullYear(), target.getMonth() + 1, 0 ).getDate();

		return new Date( target.getFullYear(), target.getMonth(), Math.min( date.getDate(), last ) );
	}

	function format( options, date ) {
		return new Intl.DateTimeFormat( lang, options ).format( date );
	}

	// The month a word in the text names ("jul", "July"), in the payer's language; -1 if none.
	function namedMonth( text ) {
		var lower = text.toLowerCase();
		var found = -1;

		[ 'long', 'short' ].some( function ( style ) {
			for ( var m = 0; m < 12; m++ ) {
				var name = format( { month: style }, new Date( 2023, m, 1 ) ).toLowerCase().replace( /\.$/, '' );

				if ( name && -1 !== lower.indexOf( name ) ) {
					found = m;
					return true;
				}
			}

			return false;
		} );

		return found;
	}

	// What was typed as a date: "14 07 2026", "14/7/26", "2026-07-14", "14072026" or "July 14, 2026". When the first number is
	// above 12 it's the day; when the second is, it's the month; otherwise the language's own order decides. Null if it isn't one.
	function parseText( text, order ) {
		var value = ( text || '' ).trim();
		var parts;
		var found = {};
		var month = namedMonth( value );

		if ( parse( value ) ) {
			return parse( value );
		}

		if ( -1 !== month ) {
			parts = value.match( /\d+/g );

			if ( ! parts || 2 !== parts.length ) {
				return null;
			}

			found.month = month + 1;
			found.day = parseInt( parts[ parseInt( parts[ 0 ], 10 ) > 31 || parts[ 0 ].length > 2 ? 1 : 0 ], 10 );
			found.year = parseInt( parts[ parseInt( parts[ 0 ], 10 ) > 31 || parts[ 0 ].length > 2 ? 0 : 1 ], 10 );
		} else {
			if ( /^\d{8}$/.test( value ) ) {
				parts = 'year' === order[ 0 ] ? [ value.slice( 0, 4 ), value.slice( 4, 6 ), value.slice( 6 ) ] : [ value.slice( 0, 2 ), value.slice( 2, 4 ), value.slice( 4 ) ];
			} else {
				parts = /[^\d\s./\-,]/.test( value ) ? null : value.match( /\d+/g );
			}

			if ( ! parts || 3 !== parts.length ) {
				return null;
			}

			var numbers = parts.map( function ( part ) {
				return parseInt( part, 10 );
			} );
			var local = order.filter( function ( type ) {
				return 'year' !== type;
			} );

			if ( 'year' === order[ 0 ] || parts[ 0 ].length === 4 ) {
				found.year = numbers[ 0 ];
				found.month = numbers[ 1 ];
				found.day = numbers[ 2 ];
			} else {
				found.year = numbers[ 2 ];

				// Day first unless the language writes the month first and the numbers allow it.
				var monthFirst = 'month' === local[ 0 ] && numbers[ 0 ] <= 12 || numbers[ 1 ] > 12 && numbers[ 0 ] <= 12;

				if ( numbers[ 0 ] > 12 ) {
					monthFirst = false;
				}

				found.month = monthFirst ? numbers[ 0 ] : numbers[ 1 ];
				found.day = monthFirst ? numbers[ 1 ] : numbers[ 0 ];
			}
		}

		if ( found.year < 100 ) {
			found.year += found.year <= new Date().getFullYear() % 100 + 10 ? 2000 : 1900;
		}

		var date = new Date( found.year, found.month - 1, found.day );

		return date.getFullYear() === found.year && date.getMonth() === found.month - 1 && date.getDate() === found.day ? date : null;
	}

	function enhance( input ) {
		if ( input.iftpDatepicker ) {
			return;
		}

		var id = input.id || 'iftp-btn-form-date-' + ++uid;
		var field = input.parentNode;
		var holder = part( 'span', 'iftp-btn-form__select iftp-btn-dropdown iftp-btn-datepick' );
		var box = part( 'input', 'iftp-btn-datepick__text ' + input.className );
		var button = part( 'button', 'iftp-btn-datepick__toggle' );
		var order = ( input.dataset.order || '' ).split( ',' );
		var shown = '';
		var buffer = '';
		var invalid = false;
		var panel = part( 'div', 'iftp-btn-datepick__panel' );
		var weekStart = parseInt( input.dataset.weekStart, 10 ) || 0;
		var placeholder = input.dataset.placeholder || '';
		var letters = { month: input.dataset.mm || 'MM', day: input.dataset.dd || 'DD', year: input.dataset.yyyy || 'YYYY' };
		var lengths;
		var min = parse( input.min );
		var max = parse( input.max );
		var view = 'days';
		var cursor = new Date();
		var frame = 0;
		var seen = '';
		var nativeValue = Object.getOwnPropertyDescriptor( HTMLInputElement.prototype, 'value' );

		if ( 3 !== order.length || [ 'month', 'day', 'year' ].some( function ( type ) {
			return -1 === order.indexOf( type );
		} ) ) {
			order = [ 'month', 'day', 'year' ];
		}

		lengths = order.map( function ( type ) {
			return 'year' === type ? 4 : 2;
		} );

		field.insertBefore( holder, input );
		holder.append( input, box, button, panel );

		box.type = 'text';
		box.setAttribute( 'inputmode', 'numeric' );
		box.setAttribute( 'autocomplete', 'off' );
		box.placeholder = placeholder;
		button.type = 'button';
		button.setAttribute( 'aria-haspopup', 'dialog' );
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-label', input.dataset.pick || '' );
		button.innerHTML = icons.calendar;

		[ 'aria-describedby', 'aria-required' ].forEach( function ( name ) {
			if ( input.hasAttribute( name ) ) {
				box.setAttribute( name, input.getAttribute( name ) );
			}
		} );

		if ( input.required ) {
			box.setAttribute( 'aria-required', 'true' );
		}

		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-modal', 'false' );
		panel.tabIndex = -1;

		if ( hasPopover ) {
			panel.setAttribute( 'popover', 'manual' );
		} else {
			panel.hidden = true;
		}

		// The <label for> now points at the text box, so a click on it focuses it and screen readers name it.
		input.id = id + '-native';
		box.id = id;
		button.id = id + '-toggle';
		panel.id = id + '-panel';
		button.setAttribute( 'aria-controls', panel.id );
		panel.setAttribute( 'aria-labelledby', id );

		input.classList.add( 'iftp-btn-dropdown__native' );
		input.tabIndex = -1;
		input.setAttribute( 'aria-hidden', 'true' );

		function isOpen() {
			return 'true' === button.getAttribute( 'aria-expanded' );
		}

		function value() {
			return parse( nativeValue.get.call( input ) );
		}

		function allowed( date ) {
			return ! ( min && date < min ) && ! ( max && date > max );
		}

		function clamp( date ) {
			if ( min && date < min ) {
				return min;
			}

			return max && date > max ? max : date;
		}

		function sync() {
			var date = value();

			shown = date ? format( { dateStyle: 'long' }, date ) : '';
			box.value = shown;
			// What the box holds, so leaving it keeps a date picked in the calendar instead of reading an empty buffer as "clear".
			buffer = date ? digitsOf( date ) : '';
			invalid = false;
			box.disabled = input.disabled;
			button.disabled = input.disabled;
			box.removeAttribute( 'aria-invalid' );
		}

		// The digits typed so far split into the slots, in the site's order: [ '07', '1', '' ].
		function slotsOf( digits ) {
			var at = 0;

			return lengths.map( function ( length ) {
				var slot = digits.substr( at, length );

				at += length;

				return slot;
			} );
		}

		// "07 / 14 / YYYY": what's typed, then what's still to type, with the separators always there.
		function maskText( digits ) {
			var slots = slotsOf( digits );

			return order.map( function ( type, index ) {
				return slots[ index ] + letters[ type ].slice( slots[ index ].length );
			} ).join( ' / ' );
		}

		function digitsOf( date ) {
			var parts = { month: pad( date.getMonth() + 1 ), day: pad( date.getDate() ), year: String( date.getFullYear() ) };

			return order.map( function ( type ) {
				return parts[ type ];
			} ).join( '' );
		}

		// Shows the mask with the caret right after the last digit, in the next slot once one is full.
		function showMask() {
			var at = 0;
			var left = buffer.length;
			var i;

			box.value = maskText( buffer );

			for ( i = 0; i < 3; i++ ) {
				if ( left >= lengths[ i ] ) {
					at += lengths[ i ] + ( i < 2 ? 3 : 0 );
					left -= lengths[ i ];
				} else {
					at += left;
					break;
				}
			}

			if ( document.activeElement === box ) {
				box.setSelectionRange( at, at );
			}
		}

		// A digit goes into the slot being filled; a month that can only be 2-9 or a day from 4-9 is padded and moves on.
		function push( digit ) {
			var at = 0;
			var index = 0;

			while ( index < 3 && buffer.length >= at + lengths[ index ] ) {
				at += lengths[ index ];
				index++;
			}

			// Every slot is full: the next digit starts over.
			if ( 3 === index ) {
				buffer = '';
				index = 0;
				at = 0;
			}

			if ( buffer.length === at && 'year' !== order[ index ] && parseInt( digit, 10 ) > ( 'month' === order[ index ] ? 1 : 3 ) ) {
				buffer += '0' + digit;
			} else {
				buffer += digit;
			}

			invalid = false;
			box.removeAttribute( 'aria-invalid' );
		}

		// A separator typed after one digit pads that slot ("7/" becomes "07").
		function separator() {
			var at = 0;
			var index = 0;

			while ( index < 3 && buffer.length >= at + lengths[ index ] ) {
				at += lengths[ index ];
				index++;
			}

			if ( index < 3 && 'year' !== order[ index ] && buffer.length === at + 1 ) {
				buffer = buffer.slice( 0, -1 ) + '0' + buffer.slice( -1 );
			}
		}

		// The slots as a date. Typing the month first in a day-first site (14 07) or the reverse is put right when only one reading works.
		function dateOf( digits ) {
			var slots = slotsOf( digits );
			var found = {};

			if ( 8 !== digits.length ) {
				return null;
			}

			order.forEach( function ( type, index ) {
				found[ type ] = parseInt( slots[ index ], 10 );
			} );

			if ( found.month > 12 && found.day <= 12 ) {
				var swap = found.month;

				found.month = found.day;
				found.day = swap;
			}

			var date = new Date( found.year, found.month - 1, found.day );

			return date.getFullYear() === found.year && date.getMonth() === found.month - 1 && date.getDate() === found.day ? date : null;
		}

		// Leaving the box or pressing Enter: a real date within the limits sets it, nothing clears it, anything else is flagged.
		function commitBuffer() {
			var date = dateOf( buffer );

			if ( '' === buffer ) {
				store( null );
			} else if ( date && allowed( date ) ) {
				store( date );
			} else {
				invalid = true;
				box.setAttribute( 'aria-invalid', 'true' );
				box.value = maskText( buffer );
			}
		}

		// A pasted date in any usual form; failing that, its digits go in the slots.
		function pasteText( text ) {
			var date = parseText( text, order );

			if ( date && allowed( date ) ) {
				store( date );
				return;
			}

			buffer = text.replace( /\D/g, '' ).slice( 0, 8 );
			showMask();
		}

		function store( date ) {
			var next = date ? toIso( date ) : '';

			if ( nativeValue.get.call( input ) === next ) {
				sync();
				return;
			}

			nativeValue.set.call( input, next );
			sync();
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		function commit( date ) {
			close( true );
			store( date );
		}

		function navButton( direction, text, step ) {
			var nav = part( 'button', 'iftp-btn-datepick__nav' );

			nav.type = 'button';
			nav.setAttribute( 'aria-label', text );
			nav.innerHTML = icons[ direction < 0 ? 'prev' : 'next' ];
			nav.addEventListener( 'click', function () {
				cursor = step( cursor, direction );
				render( false );
			} );

			return nav;
		}

		function cell( text, className, pressed, onPick, disabled, aria ) {
			var item = part( 'button', 'iftp-btn-datepick__cell ' + className, text );

			item.type = 'button';
			item.tabIndex = -1;

			if ( aria ) {
				item.setAttribute( 'aria-label', aria );
			}

			if ( pressed ) {
				item.setAttribute( 'aria-pressed', 'true' );
			}

			if ( disabled ) {
				item.disabled = true;
			} else {
				item.addEventListener( 'click', onPick );
			}

			return item;
		}

		function render( focus ) {
			var date = value();
			var today = new Date();
			var head = part( 'div', 'iftp-btn-datepick__head' );
			var title = part( 'button', 'iftp-btn-datepick__title' );
			var body = part( 'div', 'iftp-btn-datepick__body' );
			var focusTarget = null;

			title.type = 'button';
			panel.textContent = '';

			if ( 'days' === view ) {
				title.textContent = format( { month: 'long', year: 'numeric' }, cursor );
				title.setAttribute( 'aria-label', title.textContent );
				head.append( navButton( -1, input.dataset.prevMonth || '', addMonths ), title, navButton( 1, input.dataset.nextMonth || '', addMonths ) );

				var names = part( 'div', 'iftp-btn-datepick__weekdays' );
				var grid = part( 'div', 'iftp-btn-datepick__grid' );
				var first = new Date( cursor.getFullYear(), cursor.getMonth(), 1 );
				var lead = ( first.getDay() - weekStart + 7 ) % 7;
				var day;
				var i;

				// 2023-01-01 was a Sunday, so these are the weekday names in the site's order.
				for ( i = 0; i < 7; i++ ) {
					names.appendChild( part( 'span', 'iftp-btn-datepick__weekday', format( { weekday: 'narrow' }, new Date( 2023, 0, 1 + ( weekStart + i ) ) ) ) );
				}

				for ( i = 0; i < 42; i++ ) {
					day = addDays( first, i - lead );

					var item = cell(
						String( day.getDate() ),
						'iftp-btn-datepick__day' +
							( day.getMonth() !== cursor.getMonth() ? ' is-outside' : '' ) +
							( toIso( day ) === toIso( today ) ? ' is-today' : '' ),
						date && toIso( day ) === toIso( date ),
						pickDay.bind( null, day ),
						! allowed( day ),
						format( { dateStyle: 'full' }, day )
					);

					item.dataset.date = toIso( day );

					if ( toIso( day ) === toIso( cursor ) ) {
						item.tabIndex = 0;
						focusTarget = item;
					}

					grid.appendChild( item );
				}

				body.append( names, grid );
			} else if ( 'months' === view ) {
				title.textContent = String( cursor.getFullYear() );
				head.append( navButton( -1, input.dataset.prevYear || '', function ( d, n ) { return addMonths( d, n * 12 ); } ), title, navButton( 1, input.dataset.nextYear || '', function ( d, n ) { return addMonths( d, n * 12 ); } ) );

				var months = part( 'div', 'iftp-btn-datepick__months' );

				for ( var m = 0; m < 12; m++ ) {
					var monthDate = new Date( cursor.getFullYear(), m, 1 );
					var monthItem = cell(
						format( { month: 'short' }, monthDate ),
						'iftp-btn-datepick__month',
						m === cursor.getMonth(),
						pickMonth.bind( null, m ),
						false
					);

					if ( m === cursor.getMonth() ) {
						monthItem.tabIndex = 0;
						focusTarget = monthItem;
					}

					months.appendChild( monthItem );
				}

				body.appendChild( months );
			} else {
				var start = cursor.getFullYear() - ( ( cursor.getFullYear() - 2000 ) % 12 + 12 ) % 12;

				title.textContent = start + ' – ' + ( start + 11 );
				head.append( navButton( -1, input.dataset.prevYears || '', function ( d, n ) { return addMonths( d, n * 144 ); } ), title, navButton( 1, input.dataset.nextYears || '', function ( d, n ) { return addMonths( d, n * 144 ); } ) );

				var years = part( 'div', 'iftp-btn-datepick__months' );

				for ( var y = start; y < start + 12; y++ ) {
					var yearItem = cell( String( y ), 'iftp-btn-datepick__month', y === cursor.getFullYear(), pickYear.bind( null, y ), false );

					if ( y === cursor.getFullYear() ) {
						yearItem.tabIndex = 0;
						focusTarget = yearItem;
					}

					years.appendChild( yearItem );
				}

				body.appendChild( years );
			}

			title.addEventListener( 'click', function () {
				view = 'days' === view ? 'months' : 'years';
				render( true );
			} );

			panel.append( head, body );

			if ( 'days' === view ) {
				var foot = part( 'div', 'iftp-btn-datepick__foot' );
				var todayButton = part( 'button', 'iftp-btn-datepick__action', input.dataset.today || '' );

				todayButton.type = 'button';
				todayButton.disabled = ! allowed( new Date( today.getFullYear(), today.getMonth(), today.getDate() ) );
				todayButton.addEventListener( 'click', function () {
					commit( new Date( today.getFullYear(), today.getMonth(), today.getDate() ) );
				} );
				foot.appendChild( todayButton );

				if ( ! input.required ) {
					var clearButton = part( 'button', 'iftp-btn-datepick__action iftp-btn-datepick__action--quiet', input.dataset.clear || '' );

					clearButton.type = 'button';
					clearButton.addEventListener( 'click', function () {
						commit( null );
					} );
					foot.appendChild( clearButton );
				}

				panel.appendChild( foot );
			}

			if ( focus && focusTarget ) {
				focusTarget.focus( { preventScroll: true } );
			}

			position();
		}

		function pickDay( day ) {
			commit( day );
		}

		function pickMonth( month ) {
			cursor = new Date( cursor.getFullYear(), month, Math.min( cursor.getDate(), new Date( cursor.getFullYear(), month + 1, 0 ).getDate() ) );
			view = 'days';
			render( true );
		}

		function pickYear( year ) {
			cursor = addMonths( cursor, ( year - cursor.getFullYear() ) * 12 );
			view = 'months';
			render( true );
		}

		// Below the button when it fits, above when there's more room there; kept inside the viewport.
		function position() {
			if ( ! hasPopover || ! isOpen() ) {
				return;
			}

			var rect = box.getBoundingClientRect();
			var height = panel.offsetHeight;
			var width = panel.offsetWidth;
			var below = window.innerHeight - rect.bottom - 14;
			var above = rect.top - 14;
			var up = height > below && above > below;

			panel.style.top = Math.max( 8, up ? rect.top - height - 6 : Math.min( rect.bottom + 6, window.innerHeight - height - 8 ) ) + 'px';
			panel.style.left = Math.max( 8, Math.min( rect.left, window.innerWidth - width - 8 ) ) + 'px';
		}

		function onOutside( event ) {
			if ( ! holder.contains( event.target ) ) {
				close( false );
			}
		}

		function onFocusIn( event ) {
			if ( ! holder.contains( event.target ) ) {
				close( false );
			}
		}

		function onViewportChange( event ) {
			if ( event && panel.contains( event.target ) ) {
				return;
			}

			var rect = box.getBoundingClientRect();

			if ( rect.bottom < 0 || rect.top > window.innerHeight || ! box.offsetWidth ) {
				close( false );
				return;
			}

			position();
		}

		// Follows the button for as long as the calendar is open (a resize, a scroll or the dialog still settling move it).
		function track() {
			var rect = box.getBoundingClientRect();
			var now = [ rect.left, rect.top, rect.width, rect.height, window.innerWidth, window.innerHeight ].join();

			if ( now !== seen ) {
				seen = now;
				onViewportChange();
			}

			frame = isOpen() ? window.requestAnimationFrame( track ) : 0;
		}

		function open() {
			if ( isOpen() || input.disabled ) {
				return;
			}

			cursor = clamp( value() || new Date() );
			cursor = new Date( cursor.getFullYear(), cursor.getMonth(), cursor.getDate() );
			view = 'days';

			if ( hasPopover ) {
				panel.showPopover();
			} else {
				panel.hidden = false;
			}

			holder.classList.add( 'iftp-btn-dropdown--open' );
			button.setAttribute( 'aria-expanded', 'true' );
			render( true );
			seen = '';
			frame = window.requestAnimationFrame( track );

			document.addEventListener( 'pointerdown', onOutside, true );
			document.addEventListener( 'focusin', onFocusIn );
			window.addEventListener( 'resize', onViewportChange );
			window.addEventListener( 'scroll', onViewportChange, true );
		}

		function close( returnFocus ) {
			if ( ! isOpen() ) {
				return;
			}

			if ( hasPopover ) {
				panel.hidePopover();
			} else {
				panel.hidden = true;
			}

			holder.classList.remove( 'iftp-btn-dropdown--open' );
			button.setAttribute( 'aria-expanded', 'false' );

			window.cancelAnimationFrame( frame );
			document.removeEventListener( 'pointerdown', onOutside, true );
			document.removeEventListener( 'focusin', onFocusIn );
			window.removeEventListener( 'resize', onViewportChange );
			window.removeEventListener( 'scroll', onViewportChange, true );

			if ( returnFocus ) {
				box.focus();
			}
		}

		button.addEventListener( 'click', function () {
			if ( isOpen() ) {
				close( true );
			} else {
				open();
			}
		} );

		// Typing fills the slots of the mask; the digits are handled here, so the separators never move.
		box.addEventListener( 'focus', function () {
			var date = value();

			box.placeholder = maskText( '' );

			if ( invalid ) {
				showMask();
				return;
			}

			buffer = date ? digitsOf( date ) : '';

			if ( '' !== buffer ) {
				showMask();
			} else {
				box.value = '';
			}
		} );

		box.addEventListener( 'blur', function () {
			box.placeholder = placeholder;
			commitBuffer();
		} );

		box.addEventListener( 'beforeinput', function ( event ) {
			var type = event.inputType;

			if ( 'insertText' === type || 'insertCompositionText' === type ) {
				event.preventDefault();

				( event.data || '' ).split( '' ).forEach( function ( character ) {
					if ( /\d/.test( character ) ) {
						push( character );
					} else if ( /[\s/.\-]/.test( character ) ) {
						separator();
					}
				} );

				showMask();
			} else if ( 0 === type.indexOf( 'deleteContent' ) || 'deleteByCut' === type ) {
				event.preventDefault();
				buffer = box.selectionStart === 0 && box.selectionEnd === box.value.length ? '' : buffer.slice( 0, -1 );
				invalid = false;
				box.removeAttribute( 'aria-invalid' );

				if ( '' === buffer ) {
					box.value = '';
				} else {
					showMask();
				}
			} else if ( 0 === type.indexOf( 'insertFromPaste' ) || 'insertFromDrop' === type ) {
				event.preventDefault();
				pasteText( ( event.dataTransfer ? event.dataTransfer.getData( 'text' ) : event.data ) || '' );
			}
		} );

		// Enter sets the date; Alt+Down opens the calendar; Escape closes it.
		box.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				commitBuffer();
			} else if ( 'ArrowDown' === event.key && event.altKey ) {
				event.preventDefault();
				open();
			} else if ( 'Escape' === event.key && isOpen() ) {
				event.stopPropagation();
				close( true );
			}
		} );

		// The caret stays after the last digit: a click or an arrow key can't land it inside the separators.
		box.addEventListener( 'click', function () {
			if ( '' !== buffer ) {
				showMask();
			}
		} );

		// Arrow keys move the highlighted day (or month, or year); Page keys change month; Escape closes.
		panel.addEventListener( 'keydown', function ( event ) {
			var key = event.key;
			var moves = {
				ArrowLeft: -1,
				ArrowRight: 1,
				ArrowUp: 'days' === view ? -7 : -3,
				ArrowDown: 'days' === view ? 7 : 3,
			};

			if ( 'Escape' === key ) {
				event.stopPropagation();
				event.preventDefault();
				close( true );
				return;
			}

			if ( ! event.target.closest( '.iftp-btn-datepick__body' ) ) {
				return;
			}

			if ( key in moves ) {
				if ( 'days' === view ) {
					cursor = addDays( cursor, moves[ key ] );
				} else if ( 'months' === view ) {
					cursor = addMonths( cursor, moves[ key ] );
				} else {
					cursor = addMonths( cursor, moves[ key ] * 12 );
				}
			} else if ( 'days' === view && ( 'PageUp' === key || 'PageDown' === key ) ) {
				cursor = addMonths( cursor, ( 'PageUp' === key ? -1 : 1 ) * ( event.shiftKey ? 12 : 1 ) );
			} else if ( 'days' === view && ( 'Home' === key || 'End' === key ) ) {
				var offset = ( cursor.getDay() - weekStart + 7 ) % 7;

				cursor = addDays( cursor, 'Home' === key ? -offset : 6 - offset );
			} else {
				return;
			}

			event.preventDefault();
			render( true );
		} );

		// checkout.js sets input.value directly (prefill, reset) and focuses an invalid input, so the label follows
		// the value and the focus lands on the button that stands in for it.
		Object.defineProperty( input, 'value', {
			configurable: true,
			get: function () {
				return nativeValue.get.call( this );
			},
			set: function ( next ) {
				nativeValue.set.call( this, next );
				sync();
			},
		} );

		input.focus = function ( options ) {
			box.focus( options );
		};

		input.addEventListener( 'change', sync );

		if ( 'MutationObserver' in window ) {
			new MutationObserver( function () {
				sync();

				if ( input.disabled ) {
					close( false );
				}
			} ).observe( input, { attributes: true, attributeFilter: [ 'disabled' ] } );
		}

		if ( input.form ) {
			input.form.addEventListener( 'reset', function () {
				setTimeout( sync, 0 );
			} );
		}

		input.iftpDatepicker = { button: box, sync: sync, close: close };
		sync();
	}

	function init( root ) {
		( root || document ).querySelectorAll( selector ).forEach( enhance );
	}

	window.iftpBtnCheckoutDatepickers = { init: init };

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
