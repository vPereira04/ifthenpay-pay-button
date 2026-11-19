/* global iftpBtnEntries */
/**
 * Entries list: the selected rows, kept across pages, and the bulk bar that follows them.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { formatCount, i18n, receiptStorageKey, selectedRefsSet, selectionStorageKey, statusStorageKey } = ns;

	ns.bindRowDelete = bindRowDelete;
	ns.bindSelection = bindSelection;
	ns.bindSelectionCancel = bindSelectionCancel;
	ns.deletableRefs = deletableRefs;
	ns.removeRefsFromSelection = removeRefsFromSelection;
	ns.restoreSelectionState = restoreSelectionState;

	function persistSelection() {
		var statuses = {};
		var receipts = {};

		// Pruned to the selection, so it can't grow past it.
		selectedRefsSet.forEach( function ( ref ) {
			if ( ns.selectedStatus[ ref ] ) {
				statuses[ ref ] = ns.selectedStatus[ ref ];
			}

			if ( ns.selectedReceipt[ ref ] ) {
				receipts[ ref ] = true;
			}
		} );

		ns.selectedStatus = statuses;
		ns.selectedReceipt = receipts;

		try {
			sessionStorage.setItem( selectionStorageKey, JSON.stringify( Array.from( selectedRefsSet ) ) );
			sessionStorage.setItem( statusStorageKey, JSON.stringify( ns.selectedStatus ) );
			sessionStorage.setItem( receiptStorageKey, JSON.stringify( ns.selectedReceipt ) );
		} catch ( e ) {
			// No storage, so the selection just won't survive a page turn.
		}
	}

	function visibleRowCheckboxes( wrap ) {
		return Array.prototype.slice.call( wrap.querySelectorAll( '[data-iftp-row-check]' ) );
	}

	// Only drops the refs an action consumed, so a single-row delete never wipes a cross-page selection.
	function removeRefsFromSelection( refs ) {
		var changed = false;

		refs.forEach( function ( ref ) {
			if ( selectedRefsSet.delete( ref ) ) {
				changed = true;
			}
		} );

		if ( changed ) {
			persistSelection();
		}
	}

	function selectRow( checkbox ) {
		selectedRefsSet.add( checkbox.value );
		ns.selectedStatus[ checkbox.value ] = checkbox.getAttribute( 'data-iftp-status' ) || '';

		if ( '1' === checkbox.getAttribute( 'data-iftp-receipt' ) ) {
			ns.selectedReceipt[ checkbox.value ] = true;
		} else {
			delete ns.selectedReceipt[ checkbox.value ];
		}
	}

	// Freshly rendered rows start unchecked, so I re-sync them (and select-all) to the stored selection.
	// Their statuses are the freshest I have, so they overwrite what I stored.
	function restoreSelectionState( wrap ) {
		var checkboxes = visibleRowCheckboxes( wrap );
		var statusChanged = false;
		var selectAll = wrap.querySelector( '[data-iftp-select-all]' );

		checkboxes.forEach( function ( checkbox ) {
			checkbox.checked = selectedRefsSet.has( checkbox.value );

			if ( checkbox.checked && ( ns.selectedStatus[ checkbox.value ] !== checkbox.getAttribute( 'data-iftp-status' ) || !! ns.selectedReceipt[ checkbox.value ] !== ( '1' === checkbox.getAttribute( 'data-iftp-receipt' ) ) ) ) {
				selectRow( checkbox );
				statusChanged = true;
			}

			checkbox.closest( '[data-iftp-row]' ).classList.toggle( 'iftp-btn-table__row--selected', checkbox.checked );
		} );

		if ( statusChanged ) {
			persistSelection();
		}

		if ( selectAll ) {
			selectAll.checked = checkboxes.length > 0 && checkboxes.every( function ( checkbox ) {
				return checkbox.checked;
			} );
		}

		refreshSelectionUI( wrap );
	}

	function bindSelection( wrap ) {
		var selectAll = wrap.querySelector( '[data-iftp-select-all]' );

		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				visibleRowCheckboxes( wrap ).forEach( function ( checkbox ) {
					if ( selectAll.checked ) {
						selectRow( checkbox );
					} else {
						selectedRefsSet.delete( checkbox.value );
					}
				} );

				persistSelection();
				restoreSelectionState( wrap );
			} );
		}

		wrap.addEventListener( 'change', function ( event ) {
			if ( ! event.target.matches( '[data-iftp-row-check]' ) ) {
				return;
			}

			if ( event.target.checked ) {
				selectRow( event.target );
			} else {
				selectedRefsSet.delete( event.target.value );
			}

			persistSelection();
			restoreSelectionState( wrap );
		} );
	}

	// Both Cancel buttons hide themselves once the selection clears, so focus moves to select-all.
	function bindSelectionCancel( wrap ) {
		wrap.querySelectorAll( '[data-iftp-selection-cancel]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var selectAll = wrap.querySelector( '[data-iftp-select-all]' );

				selectedRefsSet.clear();
				persistSelection();
				restoreSelectionState( wrap );

				if ( selectAll ) {
					selectAll.focus();
				}
			} );
		} );
	}

	function refreshSelectionUI( wrap ) {
		var bar = wrap.querySelector( '[data-iftp-bulk-actions]' );
		var tabs = wrap.querySelector( '.iftp-btn-tabs' );
		var range = wrap.querySelector( '[data-iftp-total-count]' );
		var summary = wrap.querySelector( '[data-iftp-selection-summary]' );
		var text = wrap.querySelector( '[data-iftp-selection-text]' );
		var bulkCount = wrap.querySelector( '[data-iftp-bulk-count]' );
		var count = selectedRefsSet.size;
		var hasSelection = count > 0;
		var total = range ? parseInt( range.getAttribute( 'data-iftp-total' ) || '0', 10 ) : 0;

		wrap.toggleAttribute( 'data-iftp-has-selection', hasSelection );

		// The bulk bar takes the tabs' place while something is selected.
		if ( bar ) {
			bar.hidden = ! hasSelection;
		}

		if ( tabs ) {
			tabs.hidden = hasSelection;
		}

		if ( bulkCount ) {
			bulkCount.textContent = i18n.bulkSelectedCount.replace( '%s', formatCount( count ) );
		}

		refreshBulkOptions( wrap );

		if ( range ) {
			range.hidden = hasSelection;
		}

		if ( summary ) {
			summary.hidden = ! hasSelection;
		}

		if ( text ) {
			text.textContent = i18n.selectedOfTotal.replace( '%1$s', formatCount( count ) ).replace( '%2$s', formatCount( total ) );
		}
	}

	// "Send payment email" only when every selected entry is pending, "Resend receipt" when every one is paid with an email;
	// Delete when at least one isn't paid.
	// The server re-checks each one anyway.
	function refreshBulkOptions( wrap ) {
		var refs = Array.from( selectedRefsSet );

		setBulkOption(
			wrap.querySelector( '[data-iftp-bulk-email]' ),
			refs.length > 0 && refs.every( function ( ref ) {
				return 'pending' === ns.selectedStatus[ ref ];
			} ),
			true
		);

		setBulkOption(
			wrap.querySelector( '[data-iftp-bulk-receipt]' ),
			refs.length > 0 && refs.every( function ( ref ) {
				return !! ns.selectedReceipt[ ref ];
			} ),
			true
		);

		setBulkOption( wrap.querySelector( '[data-iftp-bulk-delete]' ), deletableRefs( refs ).length > 0, false );
	}

	// Safari ignores hidden on <option>, so disabled keeps it unpickable there.
	function setBulkOption( option, available, hideWhenOff ) {
		if ( ! option ) {
			return;
		}

		option.disabled = ! available;

		if ( hideWhenOff ) {
			option.hidden = ! available;
		}

		if ( ! available && option.selected ) {
			option.parentNode.value = '';
		}
	}

	// Paid entries are payment history: the server won't delete them, so I don't send them.
	function deletableRefs( refs ) {
		return refs.filter( function ( ref ) {
			return 'paid' !== ns.selectedStatus[ ref ];
		} );
	}

	// ---- Bulk actions ----

	function bindRowDelete( wrap ) {
		wrap.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-iftp-row-delete]' );

			if ( ! trigger ) {
				return;
			}

			ns.confirmAction( i18n.confirmDeleteOneTitle, i18n.confirmDeleteMessage, i18n.confirmDeleteButton, true, function () {
				ns.runBulk( wrap, 'iftp_btn_delete_entries', [ trigger.getAttribute( 'data-iftp-row-delete' ) ], {}, {
					progress: i18n.bulkDeleting,
					done: i18n.deleteToastSuccess,
					error: i18n.deleteError,
				} );
			} );
		} );
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
