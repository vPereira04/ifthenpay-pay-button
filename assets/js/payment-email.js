/* global iftpBtnPaymentEmail */
/**
 * Configuration › Emails: the payment request email's editor — tags, image, live preview, reset and its own Save.
 */
( function () {
	'use strict';

	if ( 'undefined' === typeof iftpBtnPaymentEmail ) {
		return;
	}

	const config = iftpBtnPaymentEmail;
	const i18n = config.i18n;
	const panel = document.querySelector( '[data-iftp-email]' );

	if ( ! panel ) {
		return;
	}

	const subjectInput = panel.querySelector( '[data-iftp-email-subject]' );
	const bodyArea = panel.querySelector( '[data-iftp-email-body]' );
	const errorEl = panel.querySelector( '[data-iftp-email-error]' );
	const statusEl = panel.querySelector( '[data-iftp-email-status]' );
	const saveBtn = panel.querySelector( '[data-iftp-email-save]' );
	const saveLabel = saveBtn.textContent;
	const previewWrap = panel.querySelector( '[data-iftp-email-preview-wrap]' );
	const previewFrame = panel.querySelector( '[data-iftp-email-preview]' );
	const previewSubject = panel.querySelector( '[data-iftp-email-preview-subject]' );
	const tagDesc = panel.querySelector( '[data-iftp-email-tag-desc]' );
	const tagDescDefault = tagDesc.textContent;
	const mediaBtn = panel.querySelector( '[data-iftp-email-media]' );

	let editor = null;
	// Where a clicked tag goes: whichever field was focused last.
	let tagTarget = 'body';
	let previewTimer = null;
	let previewSeq = 0;
	let mediaFrame = null;

	// The email body is ~536px wide inside its card, so I cap the image there.
	const imageMaxWidth = 536;

	function post( action, data ) {
		const body = new URLSearchParams();

		body.set( 'action', action );
		body.set( 'nonce', config.nonce );

		Object.keys( data ).forEach( function ( key ) {
			body.set( key, data[ key ] );
		} );

		return fetch( config.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } ).then( function ( response ) {
			return response.json();
		} );
	}

	function errorOf( response, fallback ) {
		return ( response && response.data && response.data.message ) || fallback;
	}

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

	function say( message, isError ) {
		statusEl.textContent = message;
		statusEl.classList.toggle( 'is-error', !! isError );
	}

	function showError( message ) {
		errorEl.textContent = message;
		errorEl.hidden = false;
	}

	function schedulePreview() {
		clearTimeout( previewTimer );
		previewTimer = setTimeout( refreshPreview, parseInt( config.previewDelay, 10 ) || 400 );
	}

	function refreshPreview() {
		const seq = ++previewSeq;

		previewWrap.setAttribute( 'aria-busy', 'true' );

		post( 'iftp_btn_preview_payment_email', { subject: subjectInput.value, body: getBody() } )
			.then( function ( response ) {
				if ( ! response || ! response.success ) {
					return Promise.reject( errorOf( response, i18n.previewError ) );
				}

				// A slower, older preview mustn't land on top of a newer one.
				if ( seq === previewSeq ) {
					previewSubject.textContent = response.data.subject;
					previewFrame.srcdoc = response.data.html;
				}
			} )
			.catch( function ( message ) {
				if ( seq === previewSeq ) {
					previewSubject.textContent = 'string' === typeof message ? message : i18n.previewError;
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
		const size = ( attachment.sizes && ( attachment.sizes.large || attachment.sizes.full ) ) || attachment;
		const img = document.createElement( 'img' );

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
				title: i18n.mediaTitle,
				button: { text: i18n.mediaButton },
				library: { type: 'image' },
				multiple: false,
			} );

			mediaFrame.on( 'select', function () {
				const attachment = mediaFrame.state().get( 'selection' ).first();

				if ( attachment ) {
					insertIntoBody( imageHtml( attachment.toJSON() ) );
				}
			} );
		}

		mediaFrame.open();
	}

	// WP's CodeMirror for the body, when the admin has syntax highlighting on; the plain textarea otherwise.
	if ( config.editor && window.wp && window.wp.codeEditor ) {
		editor = window.wp.codeEditor.initialize( bodyArea, config.editor ).codemirror;
		editor.on( 'change', schedulePreview );
		editor.on( 'focus', function () {
			tagTarget = 'body';
		} );

		// The Emails tab can start hidden, where CodeMirror can't measure itself.
		const tab = document.querySelector( '[data-iftp-tab="emails"]' );

		if ( tab ) {
			tab.addEventListener( 'click', function () {
				window.setTimeout( function () {
					// Closed, it has nothing to measure or preview yet; opening it starts both.
					if ( panel.open ) {
						editor.refresh();
						refreshPreview();
					}
				}, 0 );
			} );
		}
	}

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
	// Enter in the subject mustn't submit the page's own form.
	subjectInput.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
		}
	} );
	subjectInput.addEventListener( 'input', schedulePreview );
	bodyArea.addEventListener( 'input', schedulePreview );

	panel.querySelectorAll( '[data-iftp-email-tag]' ).forEach( function ( tagBtn ) {
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

	panel.querySelector( '[data-iftp-email-reset]' ).addEventListener( 'click', function () {
		subjectInput.value = config.defaults.subject;
		setBody( config.defaults.body );
		errorEl.hidden = true;
		say( i18n.resetDone, false );
		schedulePreview();
	} );

	saveBtn.addEventListener( 'click', function () {
		saveBtn.disabled = true;
		saveBtn.textContent = i18n.saving;
		errorEl.hidden = true;
		say( '', false );

		post( 'iftp_btn_save_payment_email', { subject: subjectInput.value, body: getBody() } )
			.then( function ( response ) {
				if ( ! response || ! response.success ) {
					showError( errorOf( response, i18n.saveError ) );
					return;
				}

				// What was stored, after sanitizing, so the editor shows exactly what goes out.
				subjectInput.value = response.data.subject;
				setBody( response.data.body );
				say( response.data.message, false );
			} )
			.catch( function () {
				showError( i18n.saveError );
			} )
			.then( function () {
				saveBtn.disabled = false;
				saveBtn.textContent = saveLabel;
			} );
	} );

	// CodeMirror keeps its own copy; the page's Save Settings posts the textarea, so it gets written back first.
	const pageForm = bodyArea.closest( 'form' );

	if ( pageForm ) {
		pageForm.addEventListener( 'submit', function () {
			if ( editor ) {
				editor.save();
			}
		} );
	}

	// Closed until opened: CodeMirror can't measure itself while hidden, and the preview waits until it is wanted.
	const fold = panel.closest( 'details' ) || panel;
	let started = false;

	function start() {
		if ( started ) {
			return;
		}

		started = true;

		if ( editor ) {
			editor.refresh();
		}

		refreshPreview();
	}

	if ( 'DETAILS' === fold.tagName && ! fold.open ) {
		fold.addEventListener( 'toggle', function () {
			if ( fold.open ) {
				start();
			}
		} );
	} else {
		start();
	}
}() );
