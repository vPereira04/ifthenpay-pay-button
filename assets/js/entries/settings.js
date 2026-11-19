/* global iftpBtnEntries */
/**
 * Entries list: the insights cards and the payment email settings.
 */
( function ( ns ) {
	'use strict';

	if ( 'undefined' === typeof iftpBtnEntries ) {
		return;
	}

	const { closeOnBackdrop, errorOf, i18n, post } = ns;

	ns.bindEmailSettings = bindEmailSettings;
	ns.bindInsights = bindInsights;

	// Performance: the period tabs (arrow keys move between them, like any tablist).
	function bindInsights( wrap ) {
		var section = wrap.querySelector( '[data-iftp-insights]' );

		if ( ! section ) {
			return;
		}

		var tabs = Array.prototype.slice.call( section.querySelectorAll( '[data-iftp-period]' ) );

		function select( tab, focus ) {
			tabs.forEach( function ( other ) {
				var on = other === tab;

				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.tabIndex = on ? 0 : -1;
				section.querySelector( '[data-iftp-period-panel="' + other.getAttribute( 'data-iftp-period' ) + '"]' ).hidden = ! on;
			} );

			// Revenue by method follows the chart's period.
			wrap.querySelectorAll( '[data-iftp-period-delta]' ).forEach( function ( body ) {
				body.hidden = body.getAttribute( 'data-iftp-period-delta' ) !== tab.getAttribute( 'data-iftp-period' );
			} );

			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[ event.key ];

				if ( undefined !== next ) {
					event.preventDefault();
					select( tabs[ ( next + tabs.length ) % tabs.length ], true );
				}
			} );
		} );
	}

	// The payment email editor. Closing throws away unsaved edits, so with any pending only Cancel closes it.
	function bindEmailSettings( wrap ) {
		var opener = wrap.querySelector( '[data-iftp-email-open]' );
		var dialog = wrap.querySelector( '[data-iftp-email-dialog]' );
		var config = iftpBtnEntries.emailSettings;

		if ( ! opener || ! dialog || ! config ) {
			return;
		}

		var form = dialog.querySelector( '[data-iftp-email-form]' );
		var subjectInput = dialog.querySelector( '[data-iftp-email-subject]' );
		var bodyArea = dialog.querySelector( '[data-iftp-email-body]' );
		var errorEl = dialog.querySelector( '[data-iftp-email-error]' );
		var submitBtn = dialog.querySelector( '[data-iftp-email-submit]' );
		var submitLabel = submitBtn.textContent;
		var previewWrap = dialog.querySelector( '[data-iftp-email-preview-wrap]' );
		var previewFrame = dialog.querySelector( '[data-iftp-email-preview]' );
		var previewSubject = dialog.querySelector( '[data-iftp-email-preview-subject]' );
		var tagDesc = dialog.querySelector( '[data-iftp-email-tag-desc]' );
		var tagDescDefault = tagDesc.textContent;
		var mediaBtn = dialog.querySelector( '[data-iftp-email-media]' );

		var saved = { subject: subjectInput.value, body: bodyArea.value };
		var editor = null;
		// Where a clicked tag goes: whichever field was focused last.
		var tagTarget = 'body';
		var previewTimer = null;
		var previewSeq = 0;
		var mediaFrame = null;

		// The email body is ~536px wide inside its card, so I cap the image there.
		var imageMaxWidth = 536;

		function getBody() {
			return editor ? editor.getValue() : bodyArea.value;
		}

		function setBody( value ) {
			if ( editor ) {
				editor.setValue( value );
				editor.clearHistory();
			} else {
				bodyArea.value = value;
			}
		}

		// CodeMirror can't measure itself inside a closed dialog, so I only start it on first open.
		function ensureEditor() {
			if ( editor || ! config.editor || ! window.wp || ! window.wp.codeEditor ) {
				return;
			}

			editor = window.wp.codeEditor.initialize( bodyArea, config.editor ).codemirror;
			editor.on( 'change', schedulePreview );
			editor.on( 'focus', function () {
				tagTarget = 'body';
			} );
		}

		function isDirty() {
			return subjectInput.value !== saved.subject || getBody() !== saved.body;
		}

		function showError( message ) {
			errorEl.textContent = message;
			errorEl.hidden = false;
		}

		function schedulePreview() {
			if ( ! dialog.open ) {
				return;
			}

			clearTimeout( previewTimer );
			previewTimer = setTimeout( refreshPreview, parseInt( config.previewDelay, 10 ) || 400 );
		}

		function refreshPreview() {
			var seq = ++previewSeq;

			previewWrap.setAttribute( 'aria-busy', 'true' );

			post( 'iftp_btn_preview_payment_email', { subject: subjectInput.value, body: getBody() } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						return Promise.reject( errorOf( response, i18n.emailPreviewError ) );
					}

					// A slower, older preview mustn't land on top of a newer one.
					if ( seq === previewSeq ) {
						previewSubject.textContent = response.data.subject;
						previewFrame.srcdoc = response.data.html;
					}
				} )
				.catch( function ( message ) {
					if ( seq === previewSeq ) {
						previewSubject.textContent = 'string' === typeof message ? message : i18n.emailPreviewError;
					}
				} )
				.then( function () {
					if ( seq === previewSeq ) {
						previewWrap.removeAttribute( 'aria-busy' );
					}
				} );
		}

		function insertIntoBody( text ) {
			if ( editor ) {
				editor.replaceSelection( text );
				editor.focus();
				return;
			}

			bodyArea.setRangeText( text, bodyArea.selectionStart, bodyArea.selectionEnd, 'end' );
			bodyArea.focus();
			schedulePreview();
		}

		function insertTag( tag ) {
			if ( 'subject' === tagTarget ) {
				subjectInput.setRangeText( tag, subjectInput.selectionStart, subjectInput.selectionEnd, 'end' );
				subjectInput.focus();
				schedulePreview();
				return;
			}

			insertIntoBody( tag );
		}

		// Built through the DOM, so the URL and alt text come out properly escaped.
		function imageHtml( attachment ) {
			var size = ( attachment.sizes && ( attachment.sizes.large || attachment.sizes.full ) ) || attachment;
			var img = document.createElement( 'img' );

			img.setAttribute( 'src', size.url );
			img.setAttribute( 'alt', attachment.alt || attachment.title || '' );

			if ( size.width ) {
				img.setAttribute( 'width', String( Math.min( parseInt( size.width, 10 ), imageMaxWidth ) ) );
			}

			img.setAttribute( 'style', 'display:block;max-width:100%;height:auto;border:0;' );

			return img.outerHTML;
		}

		function openMedia() {
			if ( ! mediaFrame ) {
				mediaFrame = window.wp.media( {
					title: i18n.emailMediaTitle,
					button: { text: i18n.emailMediaButton },
					library: { type: 'image' },
					multiple: false,
				} );

				mediaFrame.on( 'select', function () {
					var attachment = mediaFrame.state().get( 'selection' ).first();

					if ( attachment ) {
						insertIntoBody( imageHtml( attachment.toJSON() ) );
					}
				} );
			}

			mediaFrame.open();
		}

		opener.addEventListener( 'click', function () {
			errorEl.hidden = true;
			dialog.showModal();
			ensureEditor();

			if ( editor ) {
				editor.refresh();
			}

			subjectInput.focus();
			refreshPreview();
		} );

		// Whatever closed it, unsaved edits go back to what's stored.
		dialog.addEventListener( 'close', function () {
			clearTimeout( previewTimer );
			subjectInput.value = saved.subject;
			setBody( saved.body );
			tagDesc.textContent = tagDescDefault;
			opener.focus();
		} );

		// Escape on unsaved edits does nothing; Cancel is the way to throw them away.
		dialog.addEventListener( 'cancel', function ( event ) {
			if ( isDirty() ) {
				event.preventDefault();
			}
		} );

		closeOnBackdrop( dialog, function () {
			return ! isDirty();
		} );

		dialog.querySelector( '[data-iftp-email-cancel]' ).addEventListener( 'click', function () {
			dialog.close();
		} );

		// The Media Library sits outside the dialog, so it only works when wp.media is around.
		if ( mediaBtn && window.wp && window.wp.media ) {
			mediaBtn.hidden = false;
			mediaBtn.addEventListener( 'click', openMedia );
		}

		subjectInput.addEventListener( 'focus', function () {
			tagTarget = 'subject';
		} );
		bodyArea.addEventListener( 'focus', function () {
			tagTarget = 'body';
		} );
		subjectInput.addEventListener( 'input', schedulePreview );
		bodyArea.addEventListener( 'input', schedulePreview );

		dialog.querySelectorAll( '[data-iftp-email-tag]' ).forEach( function ( tagBtn ) {
			function describe() {
				tagDesc.textContent = tagBtn.getAttribute( 'data-iftp-email-tag' ) + ' — ' + tagBtn.getAttribute( 'data-iftp-email-tag-help' );
			}

			function undescribe() {
				tagDesc.textContent = tagDescDefault;
			}

			tagBtn.addEventListener( 'click', function () {
				insertTag( tagBtn.getAttribute( 'data-iftp-email-tag' ) );
			} );
			tagBtn.addEventListener( 'mouseenter', describe );
			tagBtn.addEventListener( 'focus', describe );
			tagBtn.addEventListener( 'mouseleave', undescribe );
			tagBtn.addEventListener( 'blur', undescribe );
		} );

		dialog.querySelector( '[data-iftp-email-reset]' ).addEventListener( 'click', function () {
			subjectInput.value = config.defaults.subject;
			setBody( config.defaults.body );
			errorEl.hidden = true;
			schedulePreview();
			ns.showToast( i18n.emailResetDone );
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			submitBtn.disabled = true;
			submitBtn.textContent = i18n.emailSaving;
			errorEl.hidden = true;

			post( 'iftp_btn_save_payment_email', { subject: subjectInput.value, body: getBody() } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						showError( errorOf( response, i18n.emailSaveError ) );
						return;
					}

					// What was stored, after sanitizing, so reopening shows exactly what goes out.
					saved = { subject: response.data.subject, body: response.data.body };
					dialog.close();
					ns.showToast( response.data.message );
				} )
				.catch( function () {
					showError( i18n.emailSaveError );
				} )
				.then( function () {
					submitBtn.disabled = false;
					submitBtn.textContent = submitLabel;
				} );
		} );
	}
} )( ( window.iftpBtnEntriesParts = window.iftpBtnEntriesParts || {} ) );
