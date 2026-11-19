/* global iftpBtnEntries */
/**
 * Entries list: the confirm dialog and the New payment dialog.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { applyViewResponse, currentViewParams, errorOf, formatCount, i18n, post, setLoading } = ns;

	ns.bindNewEntry = bindNewEntry;
	ns.closeOnBackdrop = closeOnBackdrop;
	ns.confirmAction = confirmAction;

	// ---- Dialogs ----

	// <dialog> brings focus trapping, Escape and the top layer. A click on the backdrop lands on the dialog
	// itself, outside its box, so that's what I check for.
	function closeOnBackdrop( dialog, canClose ) {
		dialog.addEventListener( 'click', function ( event ) {
			var box = dialog.getBoundingClientRect();
			var outside = event.target === dialog && ( event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom );

			if ( outside && ( ! canClose || canClose() ) ) {
				dialog.close();
			}
		} );
	}

	// One shared confirm dialog, filled per call, in place of window.confirm().
	function confirmAction( title, message, confirmLabel, danger, onConfirm ) {
		var dialog = document.querySelector( '[data-iftp-confirm]' );

		if ( ! dialog || 'function' !== typeof dialog.showModal ) {
			if ( window.confirm( title + '\n\n' + message ) ) {
				onConfirm();
			}

			return;
		}

		var okBtn = dialog.querySelector( '[data-iftp-confirm-ok]' );
		var cancelBtn = dialog.querySelector( '[data-iftp-confirm-cancel]' );
		var confirmed = false;

		dialog.querySelector( '[data-iftp-confirm-title]' ).textContent = title;
		dialog.querySelector( '[data-iftp-confirm-message]' ).textContent = message;
		okBtn.textContent = confirmLabel;
		okBtn.classList.toggle( 'iftp-btn-btn--danger', !! danger );
		okBtn.classList.toggle( 'iftp-btn-btn--primary', ! danger );

		function onOk() {
			confirmed = true;
			dialog.close();
		}

		function onCancel() {
			dialog.close();
		}

		function onClose() {
			okBtn.removeEventListener( 'click', onOk );
			cancelBtn.removeEventListener( 'click', onCancel );
			dialog.removeEventListener( 'close', onClose );

			if ( confirmed ) {
				onConfirm();
			}
		}

		okBtn.addEventListener( 'click', onOk );
		cancelBtn.addEventListener( 'click', onCancel );
		dialog.addEventListener( 'close', onClose );

		if ( ! dialog.hasAttribute( 'data-iftp-backdrop' ) ) {
			dialog.setAttribute( 'data-iftp-backdrop', '' );
			closeOnBackdrop( dialog );
		}

		dialog.showModal();
		// Cancel first for destructive actions, so Enter doesn't delete by accident.
		( danger ? cancelBtn : okBtn ).focus();
	}

	// "New payment": live avatar, quick amounts, status cards, optional email, then a success step in the same dialog.
	function bindNewEntry( wrap ) {
		var opener = wrap.querySelector( '[data-iftp-new-entry-open]' );
		var dialog = wrap.querySelector( '[data-iftp-new-entry-dialog]' );

		if ( ! opener || ! dialog ) {
			return;
		}

		var form = dialog.querySelector( '[data-iftp-new-entry-form]' );
		var done = dialog.querySelector( '[data-iftp-np-done]' );
		var errorEl = dialog.querySelector( '[data-iftp-new-entry-error]' );
		var submitBtn = dialog.querySelector( '[data-iftp-new-entry-submit]' );
		var amount = dialog.querySelector( '[data-iftp-np-amount]' );
		var nameInput = dialog.querySelector( '[data-iftp-np-name]' );
		var emailInput = dialog.querySelector( '[data-iftp-np-email]' );
		var avatar = dialog.querySelector( '[data-iftp-np-avatar]' );
		var initials = dialog.querySelector( '[data-iftp-np-initials]' );
		var glyph = avatar.querySelector( '.iftp-btn-avatar__glyph' );
		var sendRow = dialog.querySelector( '[data-iftp-np-email-row]' );
		var send = dialog.querySelector( '[data-iftp-np-send]' );
		var sendHint = dialog.querySelector( '[data-iftp-np-send-hint]' );
		var sendHintDefault = sendHint.textContent;
		var linksReady = ! send.disabled;
		var summary = dialog.querySelector( '[data-iftp-np-summary]' );
		var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		function status() {
			var checked = form.querySelector( '[data-iftp-np-status]:checked' );

			return checked ? checked.value : 'pending';
		}

		function parsedAmount() {
			var value = parseFloat( String( amount.value || '' ).replace( /\s/g, '' ).replace( ',', '.' ) );

			return isFinite( value ) ? value : 0;
		}

		// Written like PHP's number_format_i18n() . ' €', so it matches the amount the list will show.
		function money( value ) {
			var parts = value.toFixed( 2 ).split( '.' );
			var format = iftpBtnEntries.numberFormat || {};

			return formatCount( parts[ 0 ] ) + ( format.decimal || '.' ) + parts[ 1 ] + ' €';
		}

		// The same initials and tint rule the server uses, so the preview matches the row it becomes.
		function syncAvatar() {
			var source = nameInput.value.trim() || emailInput.value.split( '@' )[ 0 ].trim();
			var letters = source
				.split( /[\s._-]+/ )
				.filter( Boolean )
				.slice( 0, 2 )
				.map( function ( word ) {
					return word.charAt( 0 ).toUpperCase();
				} )
				.join( '' );

			initials.textContent = letters;
			initials.hidden = ! letters;
			glyph.style.display = letters ? 'none' : '';
			avatar.className = avatar.className.replace( /iftp-btn-avatar--tone-\d/, 'iftp-btn-avatar--tone-' + ( letters ? 1 + ( letters.charCodeAt( 0 ) % 6 ) : 0 ) );
		}

		// The submit label and the email toggle follow what the form will actually do.
		function syncState() {
			var isPending = 'pending' === status();
			var hasEmail = emailPattern.test( emailInput.value.trim() );
			var value = parsedAmount();

			submitBtn.textContent = isPending ? i18n.newEntryCreateLink : i18n.newEntryRecord;
			summary.textContent = value > 0 ? money( value ) : '';
			sendRow.hidden = ! isPending;
			send.disabled = ! linksReady || ! hasEmail;

			if ( send.disabled ) {
				send.checked = false;
			}

			sendHint.textContent = hasEmail ? emailInput.value.trim() : sendHintDefault;

			dialog.querySelectorAll( '[data-iftp-np-quick]' ).forEach( function ( chip ) {
				chip.classList.toggle( 'iftp-btn-np__chip--active', parseFloat( chip.getAttribute( 'data-iftp-np-quick' ) ) === value );
			} );
		}

		function showError( message, field ) {
			errorEl.textContent = message;
			errorEl.hidden = false;

			if ( field ) {
				field.setAttribute( 'aria-invalid', 'true' );
				field.focus();
			}
		}

		function reset() {
			form.reset();
			form.hidden = false;
			done.hidden = true;
			errorEl.hidden = true;
			[ amount, emailInput ].forEach( function ( field ) {
				field.removeAttribute( 'aria-invalid' );
			} );
			syncAvatar();
			syncState();
		}

		opener.addEventListener( 'click', function () {
			reset();
			dialog.showModal();
			amount.focus();
		} );

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-iftp-dialog-close]' ) ) {
				dialog.close();
			}
		} );

		dialog.addEventListener( 'close', function () {
			opener.focus();
		} );

		closeOnBackdrop( dialog );

		dialog.querySelectorAll( '[data-iftp-np-quick]' ).forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				amount.value = chip.getAttribute( 'data-iftp-np-quick' );
				amount.removeAttribute( 'aria-invalid' );
				syncState();
				amount.focus();
			} );
		} );

		form.addEventListener( 'input', function ( event ) {
			event.target.removeAttribute( 'aria-invalid' );
			syncAvatar();
			syncState();
		} );

		form.addEventListener( 'change', syncState );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			errorEl.hidden = true;

			var value = parsedAmount();

			if ( value <= 0 ) {
				showError( i18n.newEntryAmountError, amount );
				return;
			}

			var email = emailInput.value.trim();

			if ( email && ! emailPattern.test( email ) ) {
				showError( i18n.newEntryEmailError, emailInput );
				return;
			}

			var label = submitBtn.textContent;

			submitBtn.disabled = true;
			submitBtn.textContent = i18n.newEntryCreating;
			setLoading( wrap, true );

			post(
				'iftp_btn_create_entry',
				Object.assign(
					{
						customer_name: nameInput.value.trim(),
						customer_email: email,
						amount: String( value ),
						status: status(),
						send_email: send.checked ? '1' : '',
					},
					currentViewParams( 'view_' )
				)
			)
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showError( errorOf( response, i18n.newEntryError ) );
						return;
					}

					applyViewResponse( wrap, response.data );
					showDone( response.data );
				} )
				.catch( function () {
					showError( i18n.newEntryError );
				} )
				.then( function () {
					setLoading( wrap, false );
					submitBtn.disabled = false;
					submitBtn.textContent = label;
				} );
		} );

		// Step two: what happened, the link to copy, and the ways on.
		function showDone( data ) {
			var linkRow = done.querySelector( '[data-iftp-np-link-row]' );
			var linkInput = done.querySelector( '[data-iftp-np-link]' );

			done.querySelector( '[data-iftp-np-done-title]' ).textContent = data.doneTitle || '';
			done.querySelector( '[data-iftp-np-done-amount]' ).textContent = data.amountLabel || '';
			done.querySelector( '[data-iftp-np-done-text]' ).textContent = data.doneText || '';
			done.querySelector( '[data-iftp-np-open]' ).href = data.entryUrl || '#';
			linkInput.value = data.paymentUrl || '';
			linkRow.hidden = ! data.paymentUrl;
			done.querySelector( '[data-iftp-np-copy]' ).setAttribute( 'data-iftp-copy-link', data.paymentUrl || '' );

			form.hidden = true;
			done.hidden = false;
			done.focus();
		}

		done.querySelector( '[data-iftp-np-again]' ).addEventListener( 'click', function () {
			reset();
			amount.focus();
		} );

		done.querySelector( '[data-iftp-np-link]' ).addEventListener( 'focus', function ( event ) {
			event.target.select();
		} );
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
