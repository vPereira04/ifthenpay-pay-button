/* global iftpBtnEntries */
/**
 * Entries list: the bulk actions (delete, payment emails, status), their locking, and the export.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { applyViewResponse, currentViewParams, deletableRefs, errorOf, formatCount, i18n, post, removeRefsFromSelection, restoreSelectionState, selectedRefsSet, setLoading } = ns;

	ns.bindBulkActions = bindBulkActions;
	ns.runBulk = runBulk;

	// Posts refs a chunk at a time, re-rendering after each so the table and counts move as it goes.
	// On an error I stop, leaving the rest selected. Resolves to whether every chunk went through.
	function runBulk( wrap, action, refs, extra, text ) {
		var size = Math.max( 1, parseInt( iftpBtnEntries.bulkChunkSize, 10 ) || 250 );
		var chunks = [];
		var done = 0;

		for ( var i = 0; i < refs.length; i += size ) {
			chunks.push( refs.slice( i, i + size ) );
		}

		function progress() {
			// One request finishes too fast for a progress message to be worth showing.
			if ( chunks.length > 1 ) {
				ns.showToast( text.progress.replace( '%1$s', formatCount( done ) ).replace( '%2$s', formatCount( refs.length ) ), false, true );
			}
		}

		function fail( message ) {
			if ( done > 0 ) {
				message = i18n.bulkPartialError.replace( '%1$s', formatCount( done ) ).replace( '%2$s', formatCount( refs.length ) );
			}

			ns.showToast( message || text.error, true );

			return false;
		}

		setLoading( wrap, true );
		progress();

		return chunks
			.reduce( function ( chain, chunk ) {
				return chain.then( function ( ok ) {
					if ( ! ok ) {
						return false;
					}

					return post( action, Object.assign( { refs: JSON.stringify( chunk ) }, extra, currentViewParams( '' ) ) ).then( function ( response ) {
						if ( ! response || ! response.success ) {
							return fail( errorOf( response, text.error ) );
						}

						done += chunk.length;
						removeRefsFromSelection( chunk );
						applyViewResponse( wrap, response.data );
						progress();

						return true;
					} );
				} );
			}, Promise.resolve( true ) )
			.then(
				function ( ok ) {
					if ( ok ) {
						ns.showToast( text.done );
					}

					return ok;
				},
				function () {
					return fail();
				}
			)
			.then( function ( ok ) {
				setLoading( wrap, false );

				return ok;
			} );
	}

	// Its own runner: a request may hand back fewer refs than I sent (it ran out of time), and the outcome is a
	// per-ref tally. Sent and skipped refs leave the selection; failed ones stay, so Apply again retries just those.
	function sendPaymentEmails( wrap, refs, action, text ) {
		var size = Math.max( 1, parseInt( iftpBtnEntries.emailChunkSize, 10 ) || 10 );
		var queue = refs.slice();
		var tally = { sent: 0, skipped: 0, failed: 0 };

		action = action || 'iftp_btn_send_payment_emails';
		text = text || { progress: i18n.bulkEmailing, sent: i18n.bulkEmailSent, skipped: i18n.bulkEmailSkipped, failed: i18n.bulkEmailFailed };

		function handled() {
			return tally.sent + tally.skipped + tally.failed;
		}

		function progress() {
			ns.showToast( text.progress.replace( '%1$s', formatCount( handled() ) ).replace( '%2$s', formatCount( refs.length ) ), false, true );
		}

		function summary() {
			var parts = [ text.sent.replace( '%s', formatCount( tally.sent ) ) ];

			if ( tally.skipped ) {
				parts.push( text.skipped.replace( '%s', formatCount( tally.skipped ) ) );
			}

			if ( tally.failed ) {
				parts.push( text.failed.replace( '%s', formatCount( tally.failed ) ) );
			}

			return parts.join( ' ' );
		}

		function next() {
			if ( 0 === queue.length ) {
				return Promise.resolve( true );
			}

			var chunk = queue.slice( 0, size );
			// Only the last chunk asks for the re-rendered table; the ones before would redraw it for nothing.
			var params = { refs: JSON.stringify( chunk ), render: chunk.length === queue.length ? '1' : '' };

			return post( action, Object.assign( params, currentViewParams( '' ) ) ).then( function ( response ) {
				var data = response && response.data;

				if ( ! response || ! response.success || ! Array.isArray( data.sent ) ) {
					return Promise.reject( errorOf( response, i18n.resendError ) );
				}

				var done = new Set( data.sent.concat( data.skipped, data.failed ) );

				// Nothing handled would loop forever, so it counts as an error.
				if ( 0 === done.size ) {
					return Promise.reject( i18n.resendError );
				}

				queue = queue.filter( function ( ref ) {
					return ! done.has( ref );
				} );

				tally.sent += data.sent.length;
				tally.skipped += data.skipped.length;
				tally.failed += data.failed.length;

				removeRefsFromSelection( data.sent.concat( data.skipped ) );

				if ( 'string' === typeof data.rowsHtml ) {
					applyViewResponse( wrap, data );
				} else {
					restoreSelectionState( wrap );
				}

				progress();

				return next();
			} );
		}

		setLoading( wrap, true );
		progress();

		return next()
			.then(
				function () {
					ns.showToast( summary(), tally.failed > 0 );

					return 0 === tally.failed;
				},
				function ( message ) {
					// What's left (the unsent chunk onwards, plus any failures) is still selected.
					ns.showToast( handled() > 0 ? summary() + ' ' + i18n.bulkPartialError.replace( '%1$s', formatCount( handled() ) ).replace( '%2$s', formatCount( refs.length ) ) : message, true );

					return false;
				}
			)
			.then( function ( ok ) {
				setLoading( wrap, false );

				return ok;
			} );
	}

	function warnBeforeLeave( event ) {
		event.preventDefault();
		event.returnValue = i18n.bulkLeaveWarning;

		return event.returnValue;
	}

	// Locks the bulk bar while a run is in flight, and warns before leaving mid-run.
	function runLocked( wrap, run ) {
		function setBusy( busy ) {
			wrap.toggleAttribute( 'data-iftp-bulk-busy', busy );

			wrap.querySelectorAll( '[data-iftp-bulk-apply], [data-iftp-bulk-select], [data-iftp-selection-cancel]' ).forEach( function ( el ) {
				el.disabled = busy;
			} );

			if ( busy ) {
				window.addEventListener( 'beforeunload', warnBeforeLeave );
			} else {
				window.removeEventListener( 'beforeunload', warnBeforeLeave );
			}
		}

		setBusy( true );
		run().then( function () {
			setBusy( false );
		} );
	}

	function bindBulkActions( wrap ) {
		var applyBtn = wrap.querySelector( '[data-iftp-bulk-apply]' );
		var select = wrap.querySelector( '[data-iftp-bulk-select]' );

		if ( ! applyBtn || ! select ) {
			return;
		}

		applyBtn.addEventListener( 'click', function () {
			var value = select.value;
			var refs = Array.from( selectedRefsSet );

			// No confirm here: exporting changes nothing.
			if ( 'export-csv' === value || 'export-json' === value || 'export-accounting' === value ) {
				if ( 0 === refs.length ) {
					ns.showToast( i18n.exportNothingSelected, true );
					return;
				}

				exportEntries( refs, value.slice( 'export-'.length ) );
				select.value = '';
				return;
			}

			if ( '' === value || 0 === refs.length ) {
				return;
			}

			if ( 'delete' === value ) {
				var deletable = deletableRefs( refs );
				var keptPaid = refs.length - deletable.length;

				if ( 0 === deletable.length ) {
					return;
				}

				ns.confirmAction(
					i18n.confirmDeleteTitle,
					i18n.confirmDeleteMessage + ( keptPaid > 0 ? ' ' + i18n.deleteKeepsPaid.replace( '%s', formatCount( keptPaid ) ) : '' ),
					i18n.confirmDeleteButton,
					true,
					function () {
						select.value = '';
						runLocked( wrap, function () {
							return runBulk( wrap, 'iftp_btn_delete_entries', deletable, {}, {
								progress: i18n.bulkDeleting,
								done: i18n.deleteToastSuccess,
								error: i18n.deleteError,
							} );
						} );
					}
				);
				return;
			}

			if ( 'receipt' === value ) {
				ns.confirmAction( i18n.bulkReceiptTitle, i18n.bulkReceiptMessage.replace( '%s', formatCount( refs.length ) ), i18n.bulkReceiptButton, false, function () {
					select.value = '';
					runLocked( wrap, function () {
						return sendPaymentEmails( wrap, refs, 'iftp_btn_resend_receipts', {
							progress: i18n.bulkReceipting,
							sent: i18n.bulkReceiptSent,
							skipped: i18n.bulkReceiptSkipped,
							failed: i18n.bulkReceiptFailed,
						} );
					} );
				} );
				return;
			}

			if ( 'email' === value ) {
				ns.confirmAction( i18n.bulkEmailTitle, i18n.bulkEmailMessage.replace( '%s', formatCount( refs.length ) ), i18n.bulkEmailButton, false, function () {
					select.value = '';
					runLocked( wrap, function () {
						return sendPaymentEmails( wrap, refs );
					} );
				} );
				return;
			}

			ns.confirmAction(
				i18n.confirmStatusTitle,
				i18n.confirmStatusMessage.replace( '%s', iftpBtnEntries.statusLabels[ value ] || value ),
				i18n.confirmStatusButton,
				false,
				function () {
					select.value = '';
					runLocked( wrap, function () {
						return runBulk( wrap, 'iftp_btn_update_status', refs, { status: value }, {
							progress: i18n.bulkUpdating,
							done: i18n.statusToastSuccess,
							error: i18n.updateStatusError,
						} );
					} );
				}
			);
		} );
	}

	// A throwaway form post, so the browser handles the download itself.
	function exportEntries( refs, format ) {
		var url = new URL( iftpBtnEntries.exportUrl, window.location.href );
		var form = document.createElement( 'form' );
		var f = iftpBtnEntries.currentFilters;

		url.searchParams.set( 'action', iftpBtnEntries.exportAction );
		form.method = 'post';
		form.action = url.toString();
		form.hidden = true;

		function addField( name, value ) {
			var input = document.createElement( 'input' );

			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.appendChild( input );
		}

		addField( '_wpnonce', iftpBtnEntries.exportNonce );
		addField( 'format', format );
		addField( 'orderby', f.orderby || '' );
		addField( 'order', f.order || '' );
		// One JSON field, not refs[] — PHP drops input vars past max_input_vars (1000 by default).
		addField( 'refs', JSON.stringify( refs ) );

		document.body.appendChild( form );
		form.submit();
		form.remove();
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
