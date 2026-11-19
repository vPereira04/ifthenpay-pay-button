/**
 * Buttons and Checkout screens, the preview mock and the unsaved-changes guard.
 */
( function ( ns ) {
	'use strict';

	const { el, i18n, visibleItems } = ns;

	ns.bindDirty = bindDirty;
	ns.renderMock = renderMock;

	// The checkout mock in the editor's preview: the first page of the form, and the step bar if there are more.
	function renderMock( form, amount ) {
		const items = form.iftpFormState || [];
		const step = form.querySelector( '[data-iftp-preview-step]' );
		const fb = i18n.fb || {};
		const titles = [ ( form.querySelector( '[data-iftp-preview="first_step"]' ) || {} ).value || fb.firstStep ];
		const firstItems = [];

		items.forEach( function ( item ) {
			if ( item.type === 'page' ) {
				titles.push( item.label || fb.pageTitle );
			} else if ( titles.length === 1 ) {
				firstItems.push( item );
			}
		} );

		step.querySelectorAll( '[data-iftp-preview-item]' ).forEach( function ( node ) {
			node.remove();
		} );

		// The mock has its first Single choice option picked and nothing else, so it shows what that would.
		const mockAnswers = {};

		items.forEach( function ( item ) {
			mockAnswers[ item.id ] = item.type === 'radio' && ( item.options || [] ).length ? [ item.options[ 0 ] ] : [];
		} );

		const mockShown = visibleItems( items, mockAnswers );

		const styleRadio = form.querySelector( '[data-iftp-preview="field_icon_style"]:checked' );
		const iconStyle = styleRadio ? styleRadio.value : 'outline';

		// The icon before a title, drawn from the same set the form builder offers.
		const iconNode = function ( name ) {
			const path = ( fb.icons || {} )[ name ];

			if ( ! path ) {
				return null;
			}

			const node = el( 'span', 'iftp-btn-form__label-icon' );

			node.setAttribute( 'aria-hidden', 'true' );
			node.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">' + path + '</svg>';

			if ( iconStyle === 'filled' || iconStyle === 'duotone' ) {
				node.firstChild.setAttribute( 'class', 'iftp-btn-icon--' + iconStyle );
			}

			return node;
		};

		const field = function ( labelText, required, half, icon ) {
			const node = el( 'div', 'iftp-btn-form__field' + ( half ? ' iftp-btn-form__field--half' : '' ) );
			const label = el( 'span', 'iftp-btn-form__label', labelText );
			const mark = iconNode( icon );

			if ( mark ) {
				label.insertBefore( mark, label.firstChild );
			}

			if ( ! required ) {
				label.appendChild( el( 'span', 'iftp-btn-form__optional', ' ' + ( fb.optionalWord || '' ) ) );
			}

			node.dataset.iftpPreviewItem = '';
			node.appendChild( label );

			return node;
		};

		const fake = function ( text, tall ) {
			const node = el( 'span', 'iftp-btn-form__input iftp-btn-form__input--fake' + ( tall ? ' iftp-btn-form__input--tall' : '' ), text || '' );

			return node;
		};

		const tick = function ( type, checked ) {
			const node = el( 'input', 'iftp-btn-form__tick' );

			node.type = type;
			node.checked = !! checked;
			node.tabIndex = -1;
			node.disabled = true;

			return node;
		};

		firstItems.forEach( function ( item ) {
			let node = null;

			if ( mockShown[ item.id ] === false ) {
				return;
			}

			if ( item.type === 'amount' ) {
				if ( amount.mode !== 'payer' ) {
					return;
				}

				node = field( fb.amount, true, false );

				if ( amount.presets.length ) {
					const chips = el( 'div', 'iftp-btn-form__presets' );

					amount.presets.forEach( function ( preset ) {
						chips.appendChild( el( 'span', 'iftp-btn-form__preset', preset ) );
					} );

					node.appendChild( chips );
				}

				node.appendChild( fake( '' ) );
				node.appendChild( el( 'span', 'iftp-btn-form__help', amount.range ) );
			} else if ( item.type === 'details' ) {
				form.querySelectorAll( '[data-iftp-field]:checked' ).forEach( function ( radio ) {
					if ( radio.value === 'off' ) {
						return;
					}

					const row = radio.closest( '.iftp-btn-fields-list__row' );
					const custom = row.querySelector( 'input[type="text"]' );
					const name = custom ? custom.value || custom.placeholder : row.querySelector( '.iftp-btn-fields-list__name' ).textContent.trim();
					const picked = form.querySelector( '[data-iftp-icon-field="' + radio.dataset.iftpField + '"]:checked' );
					const detail = field( name, radio.value === 'required', false, picked ? picked.value : '' );

					detail.appendChild( fake( '' ) );
					step.appendChild( detail );
				} );

				return;
			} else if ( item.type === 'content' ) {
				node = el( 'div', 'iftp-btn-form__content' );
				node.dataset.iftpPreviewItem = '';
				node.appendChild( el( 'p', 'iftp-btn-form__heading', item.label ) );

				if ( item.help ) {
					node.appendChild( el( 'p', 'iftp-btn-form__help', item.help ) );
				}
			} else if ( item.type === 'checkbox' ) {
				node = el( 'label', 'iftp-btn-form__agree' );
				node.dataset.iftpPreviewItem = '';
				node.appendChild( tick( 'checkbox', false ) );
				node.appendChild( el( 'span', '', item.label ) );
			} else if ( item.type === 'radio' || item.type === 'checkboxes' ) {
				node = field( item.label, item.required, item.width === 'half', item.icon );

				const choices = el( 'div', 'iftp-btn-form__choices' );

				( item.options || [] ).slice( 0, 4 ).forEach( function ( option, index ) {
					const choice = el( 'span', 'iftp-btn-form__choice' );

					choice.appendChild( tick( item.type === 'radio' ? 'radio' : 'checkbox', index === 0 && item.type === 'radio' ) );
					choice.appendChild( el( 'span', '', option ) );
					choices.appendChild( choice );
				} );

				node.appendChild( choices );
			} else {
				node = field( item.label, item.required, item.width === 'half', item.icon );

				if ( item.type === 'select' ) {
					const wrap = el( 'span', 'iftp-btn-form__select' );

					wrap.appendChild( fake( item.placeholder || fb.choose ) );
					node.appendChild( wrap );
				} else {
					node.appendChild( fake( item.placeholder, item.type === 'textarea' ) );
				}
			}

			if ( node ) {
				step.appendChild( node );
			}
		} );

		const bar = form.querySelector( '[data-iftp-preview-steps]' );
		const track = form.querySelector( '[data-iftp-preview-track]' );
		const submit = form.querySelector( '[data-iftp-preview-submit]' );

		bar.hidden = titles.length < 2;
		track.textContent = '';
		track.style.setProperty( '--iftp-steps', String( titles.length ) );
		bar.style.setProperty( '--iftp-steps', String( titles.length ) );

		// The same markup as StepBar::render(), on the first page, a third answered.
		titles.forEach( function ( title, index ) {
			const segment = el( 'li', 'iftp-btn-steps__bar' + ( index === 0 ? ' is-current' : '' ) );
			const seg = el( 'span', 'iftp-btn-steps__seg' );

			segment.style.setProperty( '--iftp-i', String( index ) );

			if ( index === 0 ) {
				segment.style.setProperty( '--iftp-fill', '0.35' );
			}

			seg.appendChild( el( 'span', 'iftp-btn-steps__fill' ) );
			segment.appendChild( seg );
			segment.appendChild( el( 'span', 'iftp-btn-steps__num', String( index + 1 ) ) );
			segment.appendChild( el( 'span', 'iftp-btn-steps__label', title ) );
			track.appendChild( segment );
		} );

		form.querySelector( '[data-iftp-preview-step-pct]' ).textContent = Math.round( 0.35 / titles.length * 100 ) + '%';

		form.querySelector( '[data-iftp-preview-step-title]' ).textContent = titles[ 0 ];
		form.querySelector( '[data-iftp-preview-step-count]' ).textContent = ( fb.stepOf || '{step} / {steps}' ).replace( '{step}', '1' ).replace( '{steps}', String( titles.length ) );
		submit.textContent = titles.length > 1 ? submit.dataset.next : submit.dataset.pay;
	}

	/* ---------------------------------------------------------------- Unsaved changes */

	function bindDirty( form ) {
		const flag = form.querySelector( '[data-iftp-dirty]' );
		// A version the AI built is unsaved from the start.
		let dirty = form.hasAttribute( 'data-iftp-start-dirty' );

		if ( dirty && flag ) {
			flag.hidden = false;
		}

		const mark = function ( event ) {
			// Tabs, the stage background, the link builder, popups and parts that save themselves don't change what's saved here.
			if ( dirty || ! event.target.name || event.target.closest( '[data-iftp-builder], [data-iftp-no-dirty], dialog' ) ) {
				return;
			}

			dirty = true;

			if ( flag ) {
				flag.hidden = false;
			}
		};

		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );

		// A form that saves over AJAX stops its own submit, so only a real one leaves the page.
		form.addEventListener( 'submit', function ( event ) {
			if ( ! event.defaultPrevented ) {
				ns.leaving = true;
			}
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			// The tour fills a demo form on purpose and leaves it, so it never asks.
			if ( dirty && ! ns.leaving && ! document.body.classList.contains( 'iftp-btn-touring' ) ) {
				event.preventDefault();
				event.returnValue = i18n.unsaved || '';
			}
		} );
	}
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
