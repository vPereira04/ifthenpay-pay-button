/**
 * Configuration and Default Design screens: wires their tabs, look and messages.
 */
( function ( ns ) {
	'use strict';

	const { bindDesignControls, bindDirty, bindLook, bindMessages, bindOutcomes, bindReveal, bindTabs, perFrame, readDesign, renderButton } = ns;

	const configPage = document.querySelector( '[data-iftp-config]' );

	if ( configPage ) {
		bindTabs( configPage );
		bindLook( configPage );

		// The Backoffice tab connects over AJAX, so it's marked out of the unsaved-changes flag.
		bindDirty( configPage );
	}

	const checkout = document.querySelector( '.iftp-btn-checkout-settings' );

	if ( checkout ) {
		const settingsForm = checkout.querySelector( '[data-iftp-csettings]' );

		if ( settingsForm ) {
			bindTabs( settingsForm );
			bindDirty( settingsForm );
			bindLook( settingsForm );
			bindReveal( settingsForm, '[data-iftp-paid-type]', 'data-iftp-when-paid' );
			bindMessages( settingsForm );
			bindReveal( settingsForm, 'input[data-iftp-design="source"]', 'data-iftp-when-source' );
			bindReveal( settingsForm, 'input[data-iftp-design="variant"]', 'data-iftp-when-variant' );
			bindDesignControls( settingsForm );

			// Opening Messages shows the picked outcome's popup.
			const showOutcome = bindOutcomes( settingsForm );

			settingsForm.querySelectorAll( '[data-iftp-tab="messages"]' ).forEach( function ( tab ) {
				tab.addEventListener( 'click', showOutcome );
			} );

			if ( settingsForm.dataset.tab === 'messages' ) {
				showOutcome();
			}

			// The Button tab's preview: the default design on the site's own pay text, with the enabled logos.
			const strip = settingsForm.querySelector( '[data-iftp-preview-logos]' );
			const label = settingsForm.querySelector( '[data-iftp-preview-button] .iftp-btn-pay__label' );
			const payText = label ? label.textContent : '';
			const drawButton = function ( event ) {
				if ( ! event.target.closest( '[data-iftp-panel="button"]' ) ) {
					return;
				}

				renderButton( settingsForm, payText );

				if ( strip ) {
					strip.hidden = ! readDesign( settingsForm ).logos || ! strip.children.length;
				}
			};

			settingsForm.addEventListener( 'input', perFrame( drawButton ) );
			settingsForm.addEventListener( 'change', drawButton );
		}
	}
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
