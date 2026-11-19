/* global iftpBtnGateway */
/**
 * Buttons editor: wires the tabs, design, form builder, link builder and the rest on the editor screen.
 */
( function ( ns ) {
	'use strict';

	const { bindDesignControls, bindDirty, bindFormBuilder, bindLook, bindMessages, bindOutcomes, bindReveal, bindSpots, bindTabs, i18n, money, perFrame, post, renderButton, renderMock, shownDesign } = ns;

	/* ---------------------------------------------------------------- Editor */

	const editor = document.querySelector( '[data-iftp-editor]' );

	if ( editor ) {
		bindReveal( editor, 'input[name="config[amount_mode]"]', 'data-iftp-when-mode' );
		bindReveal( editor, '[data-iftp-paid-type]', 'data-iftp-when-paid' );
		bindReveal( editor, 'input[data-iftp-design="source"]', 'data-iftp-when-source' );
		bindReveal( editor, 'input[data-iftp-design="variant"]', 'data-iftp-when-variant' );
		bindReveal( editor, 'input[name="config[spots][mode]"]', 'data-iftp-when-spots' );
		bindReveal( editor, 'input[name="config[spots][quantity]"]', 'data-iftp-when-quantity' );
		bindReveal( editor, 'input[name="config[spots][show_left]"]', 'data-iftp-when-left' );
		bindReveal( editor, 'input[name="config[invoice_hook][enabled]"]', 'data-iftp-when-invoice' );

		// The Design tab shows the button or its checkout, one at a time, and remembers which.
		const designViewKey = 'iftpBtnDesignView';
		const showDesign = function ( view ) {
			editor.dataset.designView = view;

			editor.querySelectorAll( '[data-iftp-design-view]' ).forEach( function ( group ) {
				group.hidden = group.dataset.iftpDesignView !== view;
			} );

			editor.querySelectorAll( '[data-iftp-design-view-pick]' ).forEach( function ( pick ) {
				pick.setAttribute( 'aria-pressed', pick.dataset.iftpDesignViewPick === view ? 'true' : 'false' );
			} );

			try {
				window.localStorage.setItem( designViewKey, view );
			} catch ( e ) {}
		};

		editor.querySelectorAll( '[data-iftp-design-view-pick]' ).forEach( function ( pick ) {
			pick.addEventListener( 'click', function () {
				showDesign( pick.dataset.iftpDesignViewPick );
			} );
		} );

		// Links from before the tabs were regrouped: #look was the checkout design, #checkout the first tab.
		if ( window.location.hash === '#look' ) {
			showDesign( 'checkout' );
			window.history.replaceState( null, '', '#design' );
		} else {
			let lastView = 'button';

			try {
				lastView = window.localStorage.getItem( designViewKey ) || 'button';
			} catch ( e ) {}

			showDesign( lastView === 'checkout' ? 'checkout' : 'button' );
		}

		// A required field in the hidden design opens it first.
		editor.addEventListener(
			'invalid',
			function ( event ) {
				const group = event.target.closest( '[data-iftp-design-view]' );

				if ( group && group.hidden ) {
					showDesign( group.dataset.iftpDesignView );
				}
			},
			true
		);

		// Configuration shows the gateway or the popup messages, one at a time.
		const showOutcome = bindOutcomes( editor );
		const showConfig = function ( view ) {
			editor.dataset.configView = view;

			editor.querySelectorAll( '[data-iftp-config-view]' ).forEach( function ( group ) {
				group.hidden = group.dataset.iftpConfigView !== view;
			} );

			editor.querySelectorAll( '[data-iftp-config-view-pick]' ).forEach( function ( pick ) {
				pick.setAttribute( 'aria-pressed', pick.dataset.iftpConfigViewPick === view ? 'true' : 'false' );
			} );

			// The preview beside it shows the popup of the outcome being written.
			if ( view === 'messages' && editor.dataset.tab === 'configuration' ) {
				showOutcome();
			}
		};

		editor.querySelectorAll( '[data-iftp-config-view-pick]' ).forEach( function ( pick ) {
			pick.addEventListener( 'click', function () {
				showConfig( pick.dataset.iftpConfigViewPick );
			} );
		} );

		// A required field in the hidden part opens it first.
		editor.addEventListener(
			'invalid',
			function ( event ) {
				const group = event.target.closest( '[data-iftp-config-view]' );

				if ( group && group.hidden ) {
					showConfig( group.dataset.iftpConfigView );
				}
			},
			true
		);

		showConfig( 'gateway' );

		bindTabs( editor );

		editor.querySelectorAll( '[data-iftp-tab="configuration"]' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				showConfig( editor.dataset.configView || 'gateway' );
			} );
		} );

		// "After payment" links to the messages.
		editor.querySelectorAll( '[data-iftp-goto-config]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				const tab = editor.querySelector( '[data-iftp-tab="configuration"]' );

				if ( tab ) {
					tab.click();
				}

				showConfig( link.dataset.iftpGotoConfig );
			} );
		} );

		if ( editor.dataset.tab === 'configuration' ) {
			showConfig( editor.dataset.configView || 'gateway' );
		}

		bindMessages( editor );
		bindGateway( editor );
		bindDesignControls( editor );
		bindFormBuilder( editor );
		bindSpots( editor );
		bindLook( editor );
		bindDirty( editor );

		// The icon pickers beside the payer fields: the summary shows the pick, and a pick (or a click elsewhere) folds the grid.
		editor.querySelectorAll( '[data-iftp-iconpick]' ).forEach( function ( picker ) {
			picker.addEventListener( 'change', function ( event ) {
				const glyph = event.target.closest( 'label' ).querySelector( '.iftp-btn-fb__icon-glyph' );

				picker.querySelector( '[data-iftp-iconpick-current]' ).innerHTML = glyph.innerHTML;
				picker.open = false;
			} );
		} );

		document.addEventListener( 'click', function ( event ) {
			editor.querySelectorAll( '[data-iftp-iconpick][open]' ).forEach( function ( picker ) {
				if ( ! picker.contains( event.target ) ) {
					picker.open = false;
				}
			} );
		} );

		const value = function ( name ) {
			const input = editor.querySelector( '[data-iftp-preview="' + name + '"]' );

			if ( ! input ) {
				return '';
			}

			return input.type === 'checkbox' ? input.checked : input.value;
		};

		const mode = function () {
			const checked = editor.querySelector( 'input[name="config[amount_mode]"]:checked' );

			return checked ? checked.value : 'fixed';
		};

		const renderPreview = function () {
			const current = mode();
			const fixed = current === 'fixed';
			let label = value( 'label' ) || i18n.payNow;

			const prices = editor.iftpSpotPrices ? editor.iftpSpotPrices() : [];

			// An amount of 0 is fine when the options carry the prices.
			if ( fixed && value( 'show_amount' ) && ( value( 'amount' ) || prices.length ) ) {
				const low = prices.length ? Math.min.apply( null, prices ) : 0;
				const price = prices.length && low < Math.max.apply( null, prices ) ? ( i18n.from || '{price}' ).replace( '{price}', money( low ) ) : money( prices.length ? low : value( 'amount' ) );

				label = ( i18n.withAmount || '%1$s · %2$s' ).replace( '%1$s', label ).replace( '%2$s', price );
			}

			renderButton( editor, label );
			// The old mock preview is gone from the editor, but the Checkout settings screen still draws one.
			if ( editor.querySelector( '[data-iftp-preview-title]' ) ) {
				editor.querySelector( '[data-iftp-preview-title]' ).textContent = value( 'title' ) || '…';

				const summary = editor.querySelector( '[data-iftp-preview-summary]' );

				summary.textContent = value( 'summary' );
				summary.hidden = ! value( 'summary' );

				editor.querySelector( '[data-iftp-preview-amount]' ).textContent = fixed ? money( value( 'amount' ) ) : '';

				renderMock( editor, {
					mode: current,
					range: ( i18n.between || '' ).replace( '{min}', money( value( 'min' ) ) ).replace( '{max}', money( value( 'max' ) ) ),
					presets: String( value( 'presets' ) )
						.split( ',' )
						.map( function ( part ) {
							return part.trim();
						} )
						.filter( function ( part ) {
							return part && isFinite( parseFloat( part ) );
						} )
						.slice( 0, 6 )
						.map( money ),
				} );

				const methods = editor.querySelector( '[data-iftp-preview-methods]' );
				const picks = editor.querySelectorAll( '[data-iftp-preview="method"]' );
				const picked = editor.querySelectorAll( '[data-iftp-preview="method"]:checked' );

				const strip = editor.querySelector( '[data-iftp-preview-logos]' );
				const list = strip.querySelector( '[data-iftp-preview-logo-list]' );
				const logosOn = shownDesign( editor ).logos;

				methods.textContent = '';
				list.textContent = '';

				( picked.length ? picked : picks ).forEach( function ( pick ) {
					const logo = ( pick.closest( '[data-iftp-method-row]' ) || pick.parentElement ).querySelector( '[data-iftp-method-logo]' );

					if ( logo ) {
						const item = document.createElement( 'li' );
						const image = document.createElement( 'img' );

						// The light logo, even if the picker's was already swapped; method-logos.js picks for the preview's own background.
						image.src = logo.getAttribute( 'data-logo-light' ) || logo.getAttribute( 'src' );
						image.alt = '';

						if ( logo.hasAttribute( 'data-logo-dark' ) ) {
							image.setAttribute( 'data-logo-dark', logo.getAttribute( 'data-logo-dark' ) );
						}
						item.appendChild( image );
						methods.appendChild( item );
						list.appendChild( image.cloneNode() );
					}
				} );

				strip.hidden = ! logosOn || ! list.children.length;
			}

			updateBuilder();
		};

		/* Link builder: the redirect link a form plugin is given, with its own merge tags left as they are. */
		const builder = editor.querySelector( '[data-iftp-builder]' );

		function updateBuilder() {
			if ( ! builder ) {
				return;
			}

			const output = document.getElementById( 'iftp-btn-builder-out' );
			const warning = builder.querySelector( '[data-iftp-builder-warning]' );
			const parts = [];
			let wantsAmount = false;

			builder.querySelectorAll( '[data-iftp-builder-use]' ).forEach( function ( use ) {
				const param = use.dataset.iftpBuilderUse;
				const tag = builder.querySelector( '[data-iftp-builder-tag="' + param + '"]' );

				if ( use.checked && tag && tag.value.trim() ) {
					// The tag stays raw: encoding its braces would stop the other plugin from finding it.
					parts.push( param + '=' + tag.value.trim() );
					wantsAmount = wantsAmount || param === 'amount';
				}
			} );

			const base = builder.dataset.base;
			const mirror = document.getElementById( 'iftp-btn-amount-link-out' );

			output.value = parts.length ? base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + parts.join( '&' ) : base;
			warning.hidden = ! ( wantsAmount && mode() === 'fixed' );

			// The Amount card shows the same link under "From the link".
			if ( mirror ) {
				mirror.value = output.value;
			}
		}

		if ( builder ) {
			builder.addEventListener( 'input', updateBuilder );
			builder.addEventListener( 'change', updateBuilder );

			// Switching to "From the link" wants the amount in the link, even if Fixed had it unticked.
			editor.querySelectorAll( 'input[name="config[amount_mode]"]' ).forEach( function ( radio ) {
				radio.addEventListener( 'change', function () {
					const amount = builder.querySelector( '[data-iftp-builder-use="amount"]' );

					if ( radio.checked && radio.value === 'link' && amount ) {
						amount.checked = true;
						updateBuilder();
					}
				} );
			} );
		}

		/* Demo page: the form's current values, posted to the frame, so the preview is what's being edited. */
		const demo = editor.querySelector( '[data-iftp-demo]' );
		const frame = demo ? demo.querySelector( '[data-iftp-demo-frame]' ) : null;
		const sender = document.createElement( 'form' );
		let demoTimer = 0;

		sender.method = 'post';
		sender.hidden = true;

		// Posts the draft to a frame by name, or to a new tab.
		const sendDraft = function ( target ) {
			const data = new FormData( editor );

			sender.textContent = '';
			sender.action = demo.dataset.url;
			sender.target = target;

			data.forEach( function ( value, name ) {
				if ( name !== 'title' && name.indexOf( 'config[' ) !== 0 ) {
					return;
				}

				const field = document.createElement( 'input' );

				field.type = 'hidden';
				field.name = name;
				field.value = typeof value === 'string' ? value : '';
				sender.appendChild( field );
			} );

			// A form with no config at all would open the saved version, so a blank draft still says it is one.
			if ( ! sender.querySelector( '[name^="config["]' ) ) {
				sender.insertAdjacentHTML( 'beforeend', '<input type="hidden" name="config[label]" value="">' );
			}

			sender.submit();
		};

		if ( demo && frame ) {
			document.body.appendChild( sender );

			const refreshDemo = function ( wait ) {
				window.clearTimeout( demoTimer );
				demoTimer = window.setTimeout( function () {
					sendDraft( frame.name );
				}, wait );
			};

			// Typing waits for a pause; a click on a choice shows at once. Checkout popups' fields in the frame don't count.
			editor.addEventListener( 'input', function ( event ) {
				if ( event.target.name && ! event.target.closest( 'dialog' ) ) {
					refreshDemo( 700 );
				}
			} );
			editor.addEventListener( 'change', function ( event ) {
				if ( event.target.name && ! event.target.closest( 'dialog' ) ) {
					refreshDemo( 150 );
				}
			} );

			sendDraft( frame.name );
		}

		// Preview opens the same page full size in a new tab.
		editor.parentNode.querySelectorAll( '[data-iftp-demo-open]' ).forEach( function ( open ) {
			open.addEventListener( 'click', function () {
				if ( demo ) {
					sendDraft( '_blank' );
				}
			} );
		} );

		editor.addEventListener( 'input', perFrame( renderPreview ) );
		editor.addEventListener( 'change', renderPreview );
		renderPreview();

		/* The slug follows the title until someone types their own. */
		const title = editor.querySelector( '#iftp-btn-title' );
		const slug = editor.querySelector( '#iftp-btn-slug' );

		if ( title && slug && ! slug.value ) {
			slug.dataset.auto = '1';

			title.addEventListener( 'input', function () {
				if ( slug.dataset.auto ) {
					slug.placeholder = title.value
						.toLowerCase()
						.normalize( 'NFD' )
						.replace( /[̀-ͯ]/g, '' )
						.replace( /[^a-z0-9]+/g, '-' )
						.replace( /^-+|-+$/g, '' ) || slug.placeholder;
				}
			} );

			slug.addEventListener( 'input', function () {
				delete slug.dataset.auto;
			} );
		}

		/* Webhook test */
		editor.querySelectorAll( '[data-iftp-test-webhook]' ).forEach( function ( button ) {
			const result = editor.querySelector( '[data-iftp-test-result]' );
			const url = editor.querySelector( '#iftp-btn-webhook-url' );
			const saved = url ? url.value : '';
			const label = button.textContent;

			if ( url ) {
				// The test goes to the saved address, so it waits for a save after an edit.
				url.addEventListener( 'input', function () {
					button.disabled = ! url.value || url.value !== saved;
				} );
			}

			button.addEventListener( 'click', function () {
				button.disabled = true;
				button.textContent = i18n.sending;
				result.textContent = '';

				post( 'iftp_btn_test_webhook', { button: button.dataset.iftpTestWebhook } )
					.then( function ( response ) {
						result.textContent = ( response && response.data && response.data.message ) || i18n.error;
						result.classList.toggle( 'is-error', ! ( response && response.success ) );
					} )
					.catch( function () {
						result.textContent = i18n.error;
						result.classList.add( 'is-error' );
					} )
					.finally( function () {
						button.disabled = false;
						button.textContent = label;
					} );
			} );
		} );

		/* Invoicing app test: what the card holds now, saved or not */
		editor.querySelectorAll( '[data-iftp-test-invoice]' ).forEach( function ( button ) {
			const card = button.closest( '[data-iftp-invoice]' );
			const result = card.querySelector( '[data-iftp-invoice-result]' );
			const reply = card.querySelector( '[data-iftp-invoice-reply]' );
			const label = button.textContent;

			button.addEventListener( 'click', function () {
				const data = { button: button.dataset.iftpTestInvoice };

				card.querySelectorAll( '[data-iftp-invoice-key]' ).forEach( function ( input ) {
					data[ 'hook[' + input.dataset.iftpInvoiceKey + ']' ] = input.value;
				} );

				button.disabled = true;
				button.textContent = i18n.sending;
				result.textContent = '';
				reply.hidden = true;

				post( 'iftp_btn_test_invoice', data )
					.then( function ( response ) {
						const payload = ( response && response.data ) || {};

						result.textContent = payload.message || i18n.error;
						result.classList.toggle( 'is-error', ! ( response && response.success ) );
						// What the app said back, so a refusal explains itself.
						reply.textContent = payload.body || '';
						reply.hidden = ! payload.body;
					} )
					.catch( function () {
						result.textContent = i18n.error;
						result.classList.add( 'is-error' );
					} )
					.finally( function () {
						button.disabled = false;
						button.textContent = label;
					} );
			} );
		} );
	}

	/* ---------------------------------------------------------------- Gateway tab */

	// The Gateway Key's methods: switching the key swaps the table, the star follows the ticked methods, and a method that isn't activated can be asked for.
	function bindGateway( editor ) {
		if ( 'undefined' === typeof iftpBtnGateway ) {
			return;
		}

		const keyField = editor.querySelector( '[data-iftp-gateway-key]' );
		const table = editor.querySelector( '[data-iftp-gateway-methods]' );
		const status = editor.querySelector( '[data-iftp-gateway-status]' );

		if ( ! keyField || ! table ) {
			return;
		}

		const say = function ( message, isError ) {
			status.textContent = message;
			status.classList.toggle( 'iftp-btn-field__error', !! isError );
		};

		const post = function ( action, data ) {
			const body = new URLSearchParams();

			body.set( 'action', action );
			body.set( 'nonce', iftpBtnGateway.nonce );
			Object.keys( data ).forEach( function ( key ) {
				body.set( key, data[ key ] );
			} );

			return fetch( iftpBtnGateway.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } ).then( function ( response ) {
				return response.json();
			} );
		};

		const reason = function ( response ) {
			return ( response && response.data && response.data.message ) || iftpBtnGateway.i18n.error;
		};

		// The star only exists while its row is offered; taking the default method off clears the default.
		const syncRow = function ( row, offered ) {
			const radio = row.querySelector( '[data-iftp-method-default]' );
			const star = row.querySelector( '[data-iftp-star]' );
			const badge = row.querySelector( '[data-iftp-method-status]' );

			radio.disabled = ! offered;
			star.hidden = ! offered;
			star.classList.toggle( 'iftp-btn-star--hidden', ! offered );

			if ( ! offered ) {
				radio.checked = false;
			}

			if ( badge ) {
				badge.classList.toggle( 'iftp-btn-method-status--enabled', offered );
				badge.classList.toggle( 'iftp-btn-method-status--activated', ! offered );
				badge.textContent = badge.getAttribute( offered ? 'data-label-enabled' : 'data-label-activated' );
			}
		};

		const syncStars = function () {
			table.querySelectorAll( '[data-iftp-method-default]' ).forEach( function ( radio ) {
				const star = radio.closest( 'td' ).querySelector( '[data-iftp-star]' );

				star.classList.toggle( 'iftp-btn-star--checked', radio.checked );
				star.querySelector( '[data-iftp-star-glyph]' ).textContent = radio.checked ? '\u2605' : '\u2606';
			} );
		};

		table.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( '[data-iftp-method-enabled]' ) ) {
				syncRow( event.target.closest( '[data-iftp-method-row]' ), event.target.checked );
			}

			if ( event.target.matches( '[data-iftp-method-enabled], [data-iftp-method-default]' ) ) {
				syncStars();
			}
		} );

		keyField.addEventListener( 'change', function () {
			table.setAttribute( 'aria-busy', 'true' );
			keyField.disabled = true;
			say( iftpBtnGateway.i18n.loading, false );

			post( 'iftp_btn_gateway_methods', { gateway_key: keyField.value } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						say( reason( response ), true );
						return;
					}

					table.innerHTML = response.data.tableHtml;
					say( '', false );

					// The preview's logos and the unsaved flag follow the new table.
					editor.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} )
				.catch( function () {
					say( iftpBtnGateway.i18n.error, true );
				} )
				.then( function () {
					table.removeAttribute( 'aria-busy' );
					keyField.disabled = false;
				} );
		} );

		// Each method's 24h cooldown is enforced on the server; the disabled "Requested" button only mirrors it.
		table.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '[data-iftp-request-activation]' );

			if ( ! button ) {
				return;
			}

			const original = button.textContent;

			button.disabled = true;
			button.textContent = iftpBtnGateway.i18n.requesting;

			post( 'iftp_btn_request_activation', { entity: button.getAttribute( 'data-iftp-request-activation' ), gateway_key: button.getAttribute( 'data-iftp-gateway-key' ) || keyField.value } )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						return Promise.reject( reason( response ) );
					}

					button.textContent = iftpBtnGateway.i18n.requested;
					say( iftpBtnGateway.i18n.requestSent, false );
				} )
				.catch( function ( error ) {
					button.disabled = false;
					button.textContent = original;
					say( 'string' === typeof error ? error : iftpBtnGateway.i18n.error, true );
				} );
		} );
	}
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
