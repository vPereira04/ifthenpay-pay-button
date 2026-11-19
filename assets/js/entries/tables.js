/* global iftpBtnEntries, iftpBtnEntriesStore */
/**
 * Entries list: the Columns menu, the filter panel and the per-page choice.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { applyColumnOrder, applyHiddenColumns, attrList, bindPopover, errorOf, i18n, post, showToast, submitForm } = ns;

	ns.bindColumnsMenu = bindColumnsMenu;
	ns.bindFilterPanel = bindFilterPanel;
	ns.bindPerPage = bindPerPage;

	function bindColumnsMenu( wrap ) {
		var trigger = wrap.querySelector( '[data-iftp-columns-trigger]' );
		var panel = wrap.querySelector( '[data-iftp-columns-menu]' );

		if ( ! trigger || ! panel ) {
			return;
		}

		var toggles = Array.prototype.slice.call( panel.querySelectorAll( '[data-iftp-col-toggle]' ) );
		var list = panel.querySelector( '[data-iftp-col-list]' );
		var status = wrap.querySelector( '[data-iftp-col-status]' );
		var reset = panel.querySelector( '[data-iftp-col-reset]' );
		var draggingClass = 'iftp-btn-columns__item--dragging';
		var dropBeforeClass = 'iftp-btn-columns__item--drop-before';
		var dropAfterClass = 'iftp-btn-columns__item--drop-after';
		var saveTimer = null;
		var announceTimer = null;
		var dragging = null;

		bindPopover( trigger, panel );

		function hiddenCols() {
			return toggles
				.filter( function ( input ) {
					return ! input.checked;
				} )
				.map( function ( input ) {
					return input.getAttribute( 'data-iftp-col-toggle' );
				} );
		}

		function items() {
			return Array.prototype.slice.call( list.querySelectorAll( '[data-iftp-col-item]' ) );
		}

		function readOrder() {
			return items().map( function ( item ) {
				return item.getAttribute( 'data-iftp-col-item' );
			} );
		}

		function save() {
			post( 'iftp_btn_save_columns', { hidden: hiddenCols(), order: readOrder() } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showToast( errorOf( response, i18n.columnsSaveError ), true );
					}
				} )
				.catch( function () {
					showToast( i18n.columnsSaveError, true );
				} );
		}

		// The view updates right away; only the save waits.
		function scheduleSave() {
			clearTimeout( saveTimer );
			saveTimer = setTimeout( save, 400 );
		}

		function syncMoveButtons() {
			var all = items();

			all.forEach( function ( item, i ) {
				[ [ 'up', 0 === i ], [ 'down', all.length - 1 === i ] ].forEach( function ( pair ) {
					var btn = item.querySelector( '[data-iftp-col-move="' + pair[ 0 ] + '"]' );

					if ( pair[ 1 ] ) {
						btn.setAttribute( 'aria-disabled', 'true' );
					} else {
						btn.removeAttribute( 'aria-disabled' );
					}
				} );
			} );
		}

		// Cleared first, so the same message twice in a row still gets spoken.
		function announce( message ) {
			if ( ! status || ! message ) {
				return;
			}

			status.textContent = '';
			clearTimeout( announceTimer );
			announceTimer = setTimeout( function () {
				status.textContent = message;
			}, 100 );
		}

		function commit( item ) {
			var order = readOrder();

			// A drop back where it started changes nothing, so there's nothing to save.
			if ( order.join( ' ' ) === attrList( wrap, 'data-iftp-col-order' ).join( ' ' ) ) {
				return;
			}

			var label = item.querySelector( '.iftp-btn-columns__option-label' );
			var slug = item.getAttribute( 'data-iftp-col-item' );

			wrap.setAttribute( 'data-iftp-col-order', order.join( ' ' ) );
			applyColumnOrder( wrap, order );
			syncMoveButtons();
			announce(
				i18n.columnMoved
					.replace( '%1$s', label ? label.textContent.trim() : slug )
					.replace( '%2$s', order.indexOf( slug ) + 1 )
					.replace( '%3$s', order.length )
			);
			scheduleSave();
		}

		function clearDropMarks() {
			items().forEach( function ( item ) {
				item.classList.remove( dropBeforeClass, dropAfterClass );
			} );
		}

		// Text-selection drags can target a text node, which has no closest().
		function closestItem( node ) {
			var el = node && 1 !== node.nodeType ? node.parentElement : node;

			return el && el.closest ? el.closest( '[data-iftp-col-item]' ) : null;
		}

		panel.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches( '[data-iftp-col-toggle]' ) ) {
				return;
			}

			wrap.setAttribute( 'data-iftp-hidden-cols', hiddenCols().join( ' ' ) );
			applyHiddenColumns( wrap, hiddenCols() );
			scheduleSave();
		} );

		// I move the neighbour, not the item, so the focused button never leaves the DOM.
		panel.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-iftp-col-move]' );
			var item = btn ? btn.closest( '[data-iftp-col-item]' ) : null;
			var neighbour;

			if ( ! item || 'true' === btn.getAttribute( 'aria-disabled' ) ) {
				return;
			}

			if ( 'up' === btn.getAttribute( 'data-iftp-col-move' ) ) {
				neighbour = item.previousElementSibling;

				if ( neighbour ) {
					list.insertBefore( neighbour, item.nextElementSibling );
				}
			} else {
				neighbour = item.nextElementSibling;

				if ( neighbour ) {
					list.insertBefore( neighbour, item );
				}
			}

			commit( item );
			btn.focus();
		} );

		list.addEventListener( 'dragstart', function ( event ) {
			var el = event.target && 1 === event.target.nodeType ? event.target : null;
			var handle = el ? el.closest( '[data-iftp-col-drag]' ) : null;
			var item = handle ? handle.closest( '[data-iftp-col-item]' ) : null;

			if ( ! item ) {
				return;
			}

			dragging = item;
			event.dataTransfer.effectAllowed = 'move';
			// Firefox won't start a drag without data. A custom type keeps the slug from dropping into the search field.
			event.dataTransfer.setData( 'application/x-iftp-btn-column', item.getAttribute( 'data-iftp-col-item' ) );

			if ( 'function' === typeof event.dataTransfer.setDragImage ) {
				event.dataTransfer.setDragImage( item, 16, item.offsetHeight / 2 );
			}

			// One tick later, so the drag ghost isn't the dimmed row.
			setTimeout( function () {
				if ( dragging === item ) {
					item.classList.add( draggingClass );
				}
			}, 0 );
		} );

		list.addEventListener( 'dragover', function ( event ) {
			var target = dragging ? closestItem( event.target ) : null;

			// Files and drags from outside the list aren't mine.
			if ( ! dragging ) {
				return;
			}

			if ( ! target || target === dragging ) {
				clearDropMarks();
				return;
			}

			var rect = target.getBoundingClientRect();
			var before = event.clientY < rect.top + rect.height / 2;

			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';

			items().forEach( function ( item ) {
				item.classList.toggle( dropBeforeClass, item === target && before );
				item.classList.toggle( dropAfterClass, item === target && ! before );
			} );
		} );

		list.addEventListener( 'dragleave', function ( event ) {
			if ( ! list.contains( event.relatedTarget ) ) {
				clearDropMarks();
			}
		} );

		list.addEventListener( 'drop', function ( event ) {
			var item = dragging;
			var target;

			if ( ! item ) {
				return;
			}

			event.preventDefault();
			target = list.querySelector( '.' + dropBeforeClass + ', .' + dropAfterClass );

			if ( target && target !== item ) {
				list.insertBefore( item, target.classList.contains( dropBeforeClass ) ? target : target.nextElementSibling );
			}

			clearDropMarks();

			if ( target ) {
				commit( item );
			}
		} );

		// Covers Escape-cancelled drags and drops outside the popover too.
		list.addEventListener( 'dragend', function () {
			if ( dragging ) {
				dragging.classList.remove( draggingClass );
			}

			clearDropMarks();
			dragging = null;
		} );

		if ( reset ) {
			reset.addEventListener( 'click', function () {
				var defaults = iftpBtnEntries.defaultColumnOrder;
				var bySlug = {};

				items().forEach( function ( item ) {
					bySlug[ item.getAttribute( 'data-iftp-col-item' ) ] = item;
				} );

				defaults.forEach( function ( slug ) {
					if ( bySlug[ slug ] ) {
						list.appendChild( bySlug[ slug ] );
					}
				} );

				toggles.forEach( function ( input ) {
					input.checked = true;
				} );

				wrap.setAttribute( 'data-iftp-hidden-cols', '' );
				wrap.setAttribute( 'data-iftp-col-order', defaults.join( ' ' ) );
				applyColumnOrder( wrap, defaults );
				applyHiddenColumns( wrap, [] );
				syncMoveButtons();
				announce( i18n.columnsReset );
				scheduleSave();
			} );
		}
	}

	// An inline disclosure, not a popover: only the toggle and Escape close it.
	function bindFilterPanel( wrap ) {
		var toggle = wrap.querySelector( '[data-iftp-filters-toggle]' );
		var panel = wrap.querySelector( '[data-iftp-filter-panel]' );
		var form = panel ? panel.querySelector( 'form' ) : null;

		if ( ! toggle || ! panel || ! form ) {
			return;
		}

		var firstField = panel.querySelector( '[data-iftp-filter-first]' );
		var dateFrom = panel.querySelector( '[data-iftp-date-from]' );
		var dateTo = panel.querySelector( '[data-iftp-date-to]' );
		var panelItem = 'undefined' !== typeof iftpBtnEntriesStore && iftpBtnEntriesStore.key ? iftpBtnEntriesStore.key + ':panel' : '';

		function setOpen( open ) {
			panel.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		// Remembered for the tab's session; Escape counts as closing it too.
		function persistOpen( open ) {
			if ( ! panelItem ) {
				return;
			}

			try {
				if ( open ) {
					sessionStorage.setItem( panelItem, '1' );
				} else {
					sessionStorage.removeItem( panelItem );
				}
			} catch ( e ) {
				// No storage, so it just starts closed next time.
			}
		}

		function wasOpen() {
			try {
				return !! panelItem && '1' === sessionStorage.getItem( panelItem );
			} catch ( e ) {
				return false;
			}
		}

		// It renders open for no-JS; with JS it starts closed unless the admin left it open.
		setOpen( wasOpen() );

		toggle.addEventListener( 'click', function () {
			var open = panel.hidden;

			setOpen( open );
			persistOpen( open );

			// It sits after the tools in tab order, so I take the admin straight to it.
			if ( open && firstField ) {
				// The select sits under our own dropdown, so focus goes to the dropdown's button.
				if ( window.iftpBtnDropdowns ) {
					window.iftpBtnDropdowns.focus( firstField );
				} else {
					firstField.focus();
				}
			}
		} );

		panel.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! panel.hidden ) {
				setOpen( false );
				persistOpen( false );
				toggle.focus();
			}
		} );

		// Submitting the form (not following the link) keeps unapplied method/amount edits.
		panel.addEventListener( 'click', function ( event ) {
			var preset = event.target.closest( '[data-iftp-date-preset]' );

			if ( ! preset || ! dateFrom || ! dateTo || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) {
				return;
			}

			event.preventDefault();
			dateFrom.value = preset.getAttribute( 'data-iftp-preset-from' ) || '';
			dateTo.value = preset.getAttribute( 'data-iftp-preset-to' ) || '';
			submitForm( form );
		} );
	}

	// Arrowing a closed select fires change on Windows, so I don't reload on those (WCAG 3.2.2).
	function bindPerPage( wrap ) {
		var form = wrap.querySelector( '[data-iftp-per-page-form]' );
		var select = form ? form.querySelector( '[data-iftp-per-page]' ) : null;
		var go = form ? form.querySelector( '[data-iftp-per-page-submit]' ) : null;
		var custom = form ? form.querySelector( '[data-iftp-per-page-custom]' ) : null;
		var customInput = custom ? custom.querySelector( 'input' ) : null;
		var navKeys = [ 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown' ];
		var keyNav = false;

		if ( ! select ) {
			return;
		}

		// Off "custom", the number box is disabled too, so a stale bad value can't block a preset's submit.
		function syncCustom() {
			var isCustom = 'custom' === select.value;

			if ( custom ) {
				custom.hidden = ! isCustom;
			}

			if ( customInput ) {
				customInput.disabled = ! isCustom;
				customInput.required = isCustom;
			}

			if ( go ) {
				go.hidden = ! isCustom;
			}

			return isCustom;
		}

		function focusCustom() {
			if ( customInput ) {
				customInput.focus();
				customInput.select();
			}
		}

		syncCustom();

		select.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();

				if ( 'custom' === select.value ) {
					focusCustom();
				} else {
					submitForm( form );
				}

				return;
			}

			// Type-ahead letters change the value too; Space and Alt+Arrow just open the list.
			keyNav = ! event.altKey && ( -1 !== navKeys.indexOf( event.key ) || ( 1 === event.key.length && ' ' !== event.key && ! event.ctrlKey && ! event.metaKey ) );
		} );

		select.addEventListener( 'pointerdown', function () {
			keyNav = false;
		} );

		select.addEventListener( 'change', function () {
			// While arrowing I only reveal the box; jumping focus would strand the admin mid-list.
			if ( syncCustom() ) {
				if ( ! keyNav ) {
					focusCustom();
				}

				return;
			}

			if ( keyNav ) {
				if ( go ) {
					go.hidden = false;
				}

				return;
			}

			submitForm( form );
		} );
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
