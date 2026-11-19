/* global iftpBtnEntries */
/**
 * Entries: the single entry page, and the start-up that wires the list or the entry once the page is ready.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { bindBulkActions, bindColumnsMenu, bindCopy, bindEmailSettings, bindExportMenu, bindFilterPanel, bindInsights, bindNavLoading, bindNewEntry, bindPerPage, bindRowDelete, bindScrollButton, bindSelection, bindSelectionCancel, confirmAction, enhance, errorOf, i18n, post, removeRefsFromSelection, restoreSelectionState, showToast } = ns;

	// ---- Single entry page ----

	function bindSingleEntry( wrap ) {
		var ref = wrap.getAttribute( 'data-iftp-ref' );
		var deleteBtn = wrap.querySelector( '[data-iftp-entry-delete]' );
		var statusSelect = wrap.querySelector( '[data-iftp-entry-status-select]' );
		var statusApply = wrap.querySelector( '[data-iftp-entry-status-apply]' );
		var resendBtn = wrap.querySelector( '[data-iftp-resend-email]' );
		var receiptBtn = wrap.querySelector( '[data-iftp-resend-receipt]' );

		// The single page has no list view to re-render, so it posts the default one.
		function postFor( action, extra ) {
			return post( action, Object.assign( { refs: JSON.stringify( [ ref ] ), view_status: '', per_page: 1 }, extra ) );
		}

		if ( deleteBtn ) {
			deleteBtn.addEventListener( 'click', function () {
				confirmAction( i18n.confirmDeleteOneTitle, i18n.confirmDeleteMessage, i18n.confirmDeleteButton, true, function () {
					deleteBtn.disabled = true;

					postFor( 'iftp_btn_delete_entries', {} )
						.then( function ( response ) {
							if ( ! response || ! response.success || ! response.data.deleted ) {
								return Promise.reject( errorOf( response, i18n.deleteError ) );
							}

							removeRefsFromSelection( [ ref ] );
							window.location.assign( iftpBtnEntries.listUrl );
						} )
						.catch( function ( message ) {
							deleteBtn.disabled = false;
							showToast( 'string' === typeof message ? message : i18n.deleteError, true );
						} );
				} );
			} );
		}

		if ( statusSelect && statusApply ) {
			statusApply.addEventListener( 'click', function () {
				var target = statusSelect.value;

				if ( target === statusApply.getAttribute( 'data-iftp-current-status' ) ) {
					return;
				}

				confirmAction( i18n.confirmStatusTitle, i18n.confirmStatusOne.replace( '%s', iftpBtnEntries.statusLabels[ target ] || target ), i18n.confirmStatusButton, false, function () {
					statusApply.disabled = true;

					postFor( 'iftp_btn_update_status', { status: target } )
						.then( function ( response ) {
							if ( ! response || ! response.success ) {
								return Promise.reject( errorOf( response, i18n.updateStatusError ) );
							}

							window.location.reload();
						} )
						.catch( function ( message ) {
							statusApply.disabled = false;
							showToast( 'string' === typeof message ? message : i18n.updateStatusError, true );
						} );
				} );
			} );
		}

		if ( receiptBtn ) {
			receiptBtn.addEventListener( 'click', function () {
				confirmAction( i18n.confirmReceiptTitle, i18n.confirmReceiptMessage.replace( '%s', receiptBtn.getAttribute( 'data-iftp-resend-to' ) ), i18n.bulkReceiptButton, false, function () {
					var label = receiptBtn.querySelector( '[data-iftp-resend-label]' );
					var idle = label.textContent;

					receiptBtn.disabled = true;
					label.textContent = i18n.resendSending;

					postFor( 'iftp_btn_resend_receipts', {} )
						.then( function ( response ) {
							var ok = response && response.success && Array.isArray( response.data.sent ) && 1 === response.data.sent.length;

							showToast( ok ? i18n.receiptSent : errorOf( response, i18n.receiptError ), ! ok );
						} )
						.catch( function () {
							showToast( i18n.receiptError, true );
						} )
						.then( function () {
							receiptBtn.disabled = false;
							label.textContent = idle;
						} );
				} );
			} );
		}

		// Confirm first, since it lands in the customer's inbox. The send may create the link, so I reload after.
		if ( resendBtn ) {
			resendBtn.addEventListener( 'click', function () {
				confirmAction( i18n.confirmResendTitle, i18n.confirmResendMessage.replace( '%s', resendBtn.getAttribute( 'data-iftp-resend-to' ) ), i18n.confirmResendButton, false, function () {
					var label = resendBtn.querySelector( '[data-iftp-resend-label]' );
					var idle = label.textContent;

					resendBtn.disabled = true;
					label.textContent = i18n.resendSending;

					post( 'iftp_btn_resend_payment_email', { ref } )
						.then( function ( response ) {
							var ok = response && response.success;

							showToast( errorOf( response, i18n.resendError ), ! ok );

							if ( ok ) {
								setTimeout( function () {
									window.location.reload();
								}, 1500 );
							}
						} )
						.catch( function () {
							showToast( i18n.resendError, true );
						} )
						.then( function () {
							resendBtn.disabled = false;
							label.textContent = idle;
						} );
				} );
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var entry = document.querySelector( '[data-iftp-entry]' );

		bindCopy( document );

		if ( entry ) {
			bindSingleEntry( entry );
			return;
		}

		var list = document.querySelector( '[data-iftp-entries]' );

		if ( ! list ) {
			return;
		}

		enhance( list );
		bindSelection( list );
		bindSelectionCancel( list );
		bindRowDelete( list );
		bindBulkActions( list );
		bindNewEntry( list );
		bindInsights( list );
		bindEmailSettings( list );
		bindScrollButton( list );
		bindColumnsMenu( list );
		bindExportMenu( list );
		bindFilterPanel( list );
		bindPerPage( list );
		// Last, so it sees defaultPrevented from every other click/submit handler.
		bindNavLoading( list );

		restoreSelectionState( list );
	} );
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
