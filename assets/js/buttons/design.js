/**
 * Buttons and Checkout screens, the editor's tabs and design controls: swatches, merge tags, the live button and its samples.
 */
( function ( ns ) {
	'use strict';

	const { config, i18n } = ns;

	ns.bindDesignControls = bindDesignControls;
	ns.bindSwatches = bindSwatches;
	ns.bindTabs = bindTabs;
	ns.bindTags = bindTags;
	ns.channels = channels;
	ns.hex = hex;
	ns.onColor = onColor;
	ns.perFrame = perFrame;
	ns.readDesign = readDesign;
	ns.renderButton = renderButton;
	ns.shownDesign = shownDesign;
	ns.turn = turn;

	/* ---------------------------------------------------------------- Editor tabs */

	function bindTabs( form ) {
		const list = form.querySelector( '[data-iftp-tabs]' );

		if ( ! list ) {
			return;
		}

		const tabs = Array.prototype.slice.call( list.querySelectorAll( '[data-iftp-tab]' ) );
		const input = form.querySelector( '[data-iftp-tab-input]' );

		const select = function ( name, focus ) {
			const tab = tabs.filter( function ( one ) {
				return one.dataset.iftpTab === name;
			} )[ 0 ] || tabs[ 0 ];

			tabs.forEach( function ( one ) {
				const on = one === tab;

				one.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				one.tabIndex = on ? 0 : -1;
				form.querySelector( '[data-iftp-panel="' + one.dataset.iftpTab + '"]' ).hidden = ! on;
			} );

			input.value = tab.dataset.iftpTab;
			form.dataset.tab = tab.dataset.iftpTab;

			if ( focus ) {
				tab.focus();
			}

			if ( window.history.replaceState ) {
				window.history.replaceState( null, '', tab === tabs[ 0 ] ? window.location.pathname + window.location.search : '#' + tab.dataset.iftpTab );
			}
		};

		list.hidden = false;

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				select( tab.dataset.iftpTab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				const keys = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };

				if ( ! Object.prototype.hasOwnProperty.call( keys, event.key ) ) {
					return;
				}

				event.preventDefault();
				select( tabs[ ( keys[ event.key ] + tabs.length ) % tabs.length ].dataset.iftpTab, true );
			} );
		} );

		form.querySelectorAll( '[data-iftp-goto-tab]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				select( link.dataset.iftpGotoTab, true );
			} );
		} );

		// A required field on a hidden tab can't show its message, so I open its tab first.
		form.addEventListener(
			'invalid',
			function ( event ) {
				const panel = event.target.closest( '[data-iftp-panel]' );

				if ( panel && panel.hidden ) {
					select( panel.dataset.iftpPanel, false );
				}
			},
			true
		);

		select( window.location.hash.replace( '#', '' ), false );
	}

	/* ---------------------------------------------------------------- Button design */

	// A colour drag fires input many times a second; redraw once per frame with the latest values.
	function perFrame( draw ) {
		let queued = false;
		let last;

		return function ( event ) {
			last = event;

			if ( queued ) {
				return;
			}

			queued = true;
			window.requestAnimationFrame( function () {
				queued = false;
				draw( last );
			} );
		};
	}

	function hex( raw ) {
		let color = String( raw || '' ).trim().toLowerCase();
		const short = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec( color );

		if ( short ) {
			color = '#' + short[ 1 ] + short[ 1 ] + short[ 2 ] + short[ 2 ] + short[ 3 ] + short[ 3 ];
		}

		return /^#[0-9a-f]{6}$/.test( color ) ? color : '';
	}

	function channels( color ) {
		return [ 1, 3, 5 ].map( function ( at ) {
			return parseInt( color.substr( at, 2 ), 16 );
		} );
	}

	// Same rule as ButtonDesign::on_color(): above 0.179 luminance, black text out-contrasts white.
	function onColor( color ) {
		const lum = channels( color ).map( function ( channel ) {
			const v = channel / 255;

			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );

		return 0.2126 * lum[ 0 ] + 0.7152 * lum[ 1 ] + 0.0722 * lum[ 2 ] > 0.179 ? '#121a2c' : '#ffffff';
	}

	// Same as ButtonDesign::text_color() without a picked colour.
	function autoText( design ) {
		if ( design.variant === 'spectrum' ) {
			return ns.spectrumOn( design.stops );
		}

		if ( design.variant === 'aurora' || design.variant === 'luxe' ) {
			return onColor( mix( design.color, design.color2 ) );
		}

		// The glazed donut's label sits on its icing, the second colour.
		if ( design.variant === 'glazed' ) {
			return onColor( design.color2 );
		}

		if ( design.variant === 'beam' || design.variant === 'scanline' ) {
			return '#ffffff';
		}

		return [ 'filled', 'split', 'halo', 'glass', '3d', 'brutal', 'clay', 'ticket', 'gloss', 'ring', 'stripe', 'cut', 'checker', 'hazard', 'marquee', 'pixel', 'frosting', 'pizza', 'football', 'basketball', 'deadlift', 'running', 'medic', 'music', 'pilot', 'school', 'book', 'cartoon' ].indexOf( design.variant ) === -1 ? design.color : onColor( design.color );
	}

	function toHex( parts ) {
		return '#' + parts.map( function ( part ) {
			return ( '0' + Math.floor( part + 0.5 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	// Same as ButtonDesign::mix().
	function mix( one, two ) {
		const a = channels( one );
		const b = channels( two );

		return toHex( [ 0, 1, 2 ].map( function ( at ) {
			return ( a[ at ] + b[ at ] ) / 2;
		} ) );
	}

	// Same as ButtonDesign::FOOD_SECOND: the food styles' own second colour.
	const FOOD_SECOND = { glazed: '#ff8fb8', frosting: '#fff3ee', pizza: '#c8321f' };

	// Same as ButtonDesign::turn(): round the colour wheel, same lightness and saturation.
	function turn( color, degrees ) {
		const rgb = channels( color ).map( function ( channel ) {
			return channel / 255;
		} );
		const max = Math.max.apply( null, rgb );
		const min = Math.min.apply( null, rgb );
		const light = ( max + min ) / 2;
		const range = max - min;

		if ( range <= 0 ) {
			return color;
		}

		const sat = light > 0.5 ? range / ( 2 - max - min ) : range / ( max + min );
		let hue;

		if ( max === rgb[ 0 ] ) {
			hue = ( rgb[ 1 ] - rgb[ 2 ] ) / range + ( rgb[ 1 ] < rgb[ 2 ] ? 6 : 0 );
		} else if ( max === rgb[ 1 ] ) {
			hue = ( rgb[ 2 ] - rgb[ 0 ] ) / range + 2;
		} else {
			hue = ( rgb[ 0 ] - rgb[ 1 ] ) / range + 4;
		}

		hue = ( ( hue * 60 + degrees ) % 360 ) / 360;

		const q = light < 0.5 ? light * ( 1 + sat ) : light + sat - light * sat;
		const p = 2 * light - q;
		const to = function ( t ) {
			if ( t < 0 ) {
				++t;
			} else if ( t > 1 ) {
				--t;
			}

			if ( t < 1 / 6 ) {
				return p + ( q - p ) * 6 * t;
			}

			if ( t < 1 / 2 ) {
				return q;
			}

			return t < 2 / 3 ? p + ( q - p ) * ( 2 / 3 - t ) * 6 : p;
		};

		return toHex( [ to( hue + 1 / 3 ) * 255, to( hue ) * 255, to( hue - 1 / 3 ) * 255 ] );
	}

	// Two-way sync between each colour swatch and the hex field it's for; the hex field is the one submitted.
	function bindSwatches( form ) {
		form.querySelectorAll( '[data-iftp-color-for]' ).forEach( function ( swatch ) {
			const field = form.querySelector( '#' + swatch.dataset.iftpColorFor );

			// The editor binds its button and checkout designs on the same form; once each is enough.
			if ( ! field || swatch.dataset.iftpBound ) {
				return;
			}

			swatch.dataset.iftpBound = '1';

			swatch.addEventListener( 'input', function () {
				field.value = swatch.value;
				field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} );

			field.addEventListener( 'input', function () {
				const color = hex( field.value );

				if ( color ) {
					swatch.value = color;
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------- Merge tags */

	// A chip drops its tag where the cursor was, in the field last focused inside the same scope.
	function bindTags( root ) {
		root.querySelectorAll( '[data-iftp-tag-scope]' ).forEach( function ( scope ) {
			let last = null;

			scope.addEventListener( 'focusin', function ( event ) {
				if ( event.target.matches( '[data-iftp-tag-target]' ) ) {
					last = event.target;
				}
			} );

			scope.querySelectorAll( '[data-iftp-tag]' ).forEach( function ( chip ) {
				// Keeps the cursor where it was in the field.
				chip.addEventListener( 'mousedown', function ( event ) {
					event.preventDefault();
				} );

				chip.addEventListener( 'click', function () {
					const visible = Array.prototype.filter.call( scope.querySelectorAll( '[data-iftp-tag-target]' ), function ( target ) {
						return ! target.hidden && target.offsetParent !== null;
					} );
					const target = last && ! last.hidden && last.offsetParent !== null ? last : visible[ visible.length - 1 ];
					// {field:id} goes in open, so the cursor lands where the field's id belongs.
					const tag = chip.dataset.iftpTag === '{field:id}' ? '{field:}' : chip.dataset.iftpTag;

					if ( ! target ) {
						return;
					}

					target.focus();

					if ( target.isContentEditable ) {
						document.execCommand( 'insertText', false, tag );

						return;
					}

					const start = target.selectionStart === null ? target.value.length : target.selectionStart;
					const end = target.selectionEnd === null ? start : target.selectionEnd;

					target.setRangeText( tag, start, end, 'end' );

					if ( tag === '{field:}' ) {
						target.setSelectionRange( start + tag.length - 1, start + tag.length - 1 );
					}

					target.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				} );
			} );
		} );
	}

	function readDesign( form ) {
		const pick = function ( key ) {
			const input = form.querySelector( '[data-iftp-design="' + key + '"]:checked' ) || form.querySelector( '[data-iftp-design="' + key + '"]:not([type="radio"])' );

			if ( ! input ) {
				return '';
			}

			return input.type === 'checkbox' ? input.checked : input.value;
		};

		const design = {
			source: pick( 'source' ) || 'theme',
			variant: pick( 'variant' ) || 'filled',
			color: hex( pick( 'color' ) ) || '#00609c',
			radius: parseInt( pick( 'radius' ), 10 ) || 0,
			borderWidth: parseInt( pick( 'border_width' ), 10 ) || 0,
			borderStyle: pick( 'border_style' ) || 'solid',
			borderColor: pick( 'border_color_auto' ) ? '' : hex( pick( 'border_color' ) ),
			size: pick( 'size' ) || 'md',
			icon: pick( 'icon' ) || 'none',
			iconStyle: pick( 'icon_style' ) || 'outline',
			full: pick( 'full' ) === '1',
			shadow: !! pick( 'shadow' ),
			effect: pick( 'effect' ) || 'none',
			attention: pick( 'attention' ) || 'none',
			upper: !! pick( 'upper' ),
			font: pick( 'font' ) || '',
			still: !! pick( 'still' ),
			logos: !! pick( 'logos' ),
			stops: ns.readStops ? ns.readStops( form ) : [],
			stopsShape: pick( 'stops_shape' ) || 'linear',
			stopsAngle: parseInt( pick( 'stops_angle' ), 10 ) || 0,
			stopsFlow: !! pick( 'stops_flow' ),
			advanced: pick( 'advanced' ) || '',
			advancedColors: [ 0, 1, 2, 3, 4, 5 ].map( function ( index ) {
				return hex( pick( 'advanced_color_' + index ) );
			} ),
		};

		// wp_localize_script() hands numbers over as strings.
		const turnBy = parseInt( config.gradientTurn, 10 ) || 40;

		const autoSecond = FOOD_SECOND[ design.variant ] || turn( design.color, turnBy );
		design.color2 = pick( 'color2_auto' ) ? autoSecond : hex( pick( 'color2' ) ) || autoSecond;

		// The samples redraw it for other styles, so the picked one is kept apart from the auto one.
		design.ownText = pick( 'text_auto' ) ? '' : hex( pick( 'text' ) );
		design.text = design.ownText || autoText( design );

		return design;
	}

	// A stored design (ButtonDesign's keys) in readDesign()'s shape.
	function designFrom( stored ) {
		const turnBy = parseInt( config.gradientTurn, 10 ) || 40;
		const design = {
			source: stored.source,
			variant: stored.variant,
			color: stored.color,
			radius: parseInt( stored.radius, 10 ) || 0,
			borderWidth: parseInt( stored.border_width, 10 ) || 0,
			borderStyle: stored.border_style,
			borderColor: stored.border_color || '',
			size: stored.size,
			icon: stored.icon,
			iconStyle: stored.icon_style || 'outline',
			// wp_localize_script() turns false into '' and true into '1'.
			full: !! stored.full,
			shadow: !! stored.shadow,
			effect: stored.effect,
			attention: stored.attention,
			upper: !! stored.upper,
			font: stored.font || '',
			still: !! stored.still,
			logos: !! stored.logos,
			color2: stored.color2 || FOOD_SECOND[ stored.variant ] || turn( stored.color, turnBy ),
			ownText: stored.text || '',
			stops: ns.stopsFrom( stored.stops ),
			stopsShape: stored.stops_shape || 'linear',
			stopsAngle: parseInt( stored.stops_angle, 10 ) || 0,
			stopsFlow: !! stored.stops_flow,
			advanced: stored.advanced || '',
			advancedColors: ( Array.isArray( stored.advanced_colors ) ? stored.advanced_colors : [] ).map( hex ),
		};

		design.text = design.ownText || autoText( design );

		return design;
	}

	// What the button wears: its own controls, or the site's default while it follows that.
	function shownDesign( form ) {
		const design = readDesign( form );

		return design.source === 'site' && config.siteDesign ? designFrom( config.siteDesign ) : design;
	}

	// The same markup as ButtonDesign::preview_markup().
	function buildButton( form, design, label ) {
		const custom = design.source === 'custom';
		const classes = [ 'iftp-btn-pay__button' ];
		const button = document.createElement( 'span' );
		const text = document.createElement( 'span' );
		// Same as ButtonDesign::inner_html(): split's end block always holds an icon, after the text.
		const split = custom && design.variant === 'split';
		const iconSource = form.querySelector( '[data-iftp-icon-svg="' + ( split && design.icon === 'none' ? 'arrow' : design.icon ) + '"]' );

		if ( custom ) {
			const spectrum = design.variant === 'spectrum' && design.stops.length;
			// The spectrum has no single colour; its glow and tints take the middle stop's.
			const accent = spectrum ? ns.spectrumMiddle( design.stops ) : design.color;
			const rgb = channels( accent ).join( ' ' );

			classes.push( 'iftp-btn-pay__button--custom', 'iftp-btn-pay__button--' + design.variant, 'iftp-btn-pay__button--' + design.size );

			if ( design.shadow ) {
				classes.push( 'iftp-btn-pay__button--shadow' );
			}

			if ( design.effect !== 'none' ) {
				classes.push( 'iftp-btn-pay__button--fx-' + design.effect );
			}

			if ( design.attention !== 'none' ) {
				classes.push( 'iftp-btn-pay__button--cta-' + design.attention );
			}

			if ( design.borderColor ) {
				classes.push( 'iftp-btn-pay__button--own-border' );
				button.style.setProperty( '--iftp-pb-bc', design.borderColor );
			}

			if ( design.upper ) {
				classes.push( 'iftp-btn-pay__button--upper' );
			}

			if ( design.still ) {
				classes.push( 'iftp-btn-pay__button--still' );
			}

			if ( spectrum ) {
				button.style.setProperty( '--iftp-pb-spectrum', ns.spectrumGradient( design ) );

				if ( design.stopsFlow && design.stopsShape === 'linear' ) {
					classes.push( 'iftp-btn-pay__button--flow' );
				}
			}

			button.style.setProperty( '--iftp-pb-color', accent );
			button.style.setProperty( '--iftp-pb-color2', design.color2 );
			button.style.setProperty( '--iftp-pb-text', design.text );
			button.style.setProperty( '--iftp-pb-on', onColor( accent ) );
			button.style.setProperty( '--iftp-pb-on2', onColor( design.color2 ) );
			button.style.setProperty( '--iftp-pb-radius', design.radius >= ( parseInt( config.pill, 10 ) || 32 ) ? '999px' : design.radius + 'px' );
			button.style.setProperty( '--iftp-pb-bw', design.borderWidth + 'px' );
			button.style.setProperty( '--iftp-pb-bs', design.borderStyle );
			button.style.setProperty( '--iftp-pb-tint', 'rgb(' + rgb + ' / 14%)' );
			button.style.setProperty( '--iftp-pb-tint-strong', 'rgb(' + rgb + ' / 24%)' );
			button.style.setProperty( '--iftp-pb-glass', 'rgb(' + rgb + ' / 62%)' );
			button.style.setProperty( '--iftp-pb-glow', 'rgb(' + rgb + ' / 45%)' );

			if ( ( config.lookFonts || {} )[ design.font ] ) {
				button.style.setProperty( '--iftp-pb-font', config.lookFonts[ design.font ] );
			}
		} else {
			classes.push( 'iftp-btn-pay__button--theme' );
		}

		if ( design.full ) {
			classes.push( 'iftp-btn-pay__button--full' );
		}

		button.className = classes.join( ' ' );
		text.className = 'iftp-btn-pay__label';
		text.textContent = label;
		button.appendChild( text );

		if ( iconSource && iconSource.firstElementChild ) {
			const icon = iconSource.firstElementChild.cloneNode( true );

			// Filled and duotone are drawn by the stylesheet, from this class.
			icon.classList.remove( 'iftp-btn-icon--filled', 'iftp-btn-icon--duotone' );

			if ( design.icon !== 'arrow' && ( design.iconStyle === 'filled' || design.iconStyle === 'duotone' ) ) {
				icon.classList.add( 'iftp-btn-icon--' + design.iconStyle );
			}

			if ( split || design.icon === 'arrow' ) {
				button.appendChild( icon );
			} else {
				button.insertBefore( icon, text );
			}
		}

		return button;
	}

	// Same classes means the same animation: update the button already there so a colour drag doesn't restart it.
	function showButton( holder, next ) {
		const current = holder.firstElementChild;

		if ( current && current.className === next.className ) {
			current.setAttribute( 'style', next.getAttribute( 'style' ) || '' );
			current.replaceChildren.apply( current, Array.prototype.slice.call( next.childNodes ) );

			return;
		}

		holder.textContent = '';
		holder.appendChild( next );
	}

	function renderButton( form, label ) {
		const wrap = form.querySelector( '[data-iftp-preview-button]' );
		const shown = shownDesign( form );

		if ( wrap ) {
			// An Advanced design is drawn by its own code; the others by the plugin's classes.
			if ( shown.source === 'advanced' && shown.advanced ) {
				ns.advancedPreview( form, wrap, shown, label );
			} else {
				if ( wrap.dataset.iftpAdvanced ) {
					delete wrap.dataset.iftpAdvanced;
					wrap.removeAttribute( 'data-iftp-code' );
					wrap.removeAttribute( 'style' );
					wrap.textContent = '';
				}

				showButton( wrap, buildButton( form, shown, label ) );
			}
		}

		const note = form.querySelector( '[data-iftp-theme-note]' );

		if ( note ) {
			note.hidden = shown.source === 'custom' || shown.source === 'advanced';
		}

		renderSamples( form, readDesign( form ) );
	}

	// The picker tiles, in the current colours. Same overrides as ButtonEditor::render_fx_picker().
	function renderSamples( form, design ) {
		form.querySelectorAll( '[data-iftp-fx-sample]' ).forEach( function ( stage ) {
			const sample = Object.assign( {}, design, { source: 'custom', size: 'sm', full: false, effect: 'none', attention: 'none' } );
			const label = stage.querySelector( '.iftp-btn-pay__label' );
			const pay = document.createElement( 'span' );

			sample[ stage.dataset.iftpFxSample ] = stage.dataset.iftpFxValue;

			if ( sample.effect === 'nudge' && sample.icon === 'none' ) {
				sample.icon = 'arrow';
			}

			// An automatic second colour is each style's own, so a food tile shows its icing and not the current style's.
			const auto2 = form.querySelector( '[data-iftp-design="color2_auto"]' );

			if ( auto2 && auto2.checked && stage.dataset.iftpFxSample === 'variant' ) {
				sample.color2 = FOOD_SECOND[ sample.variant ] || turn( design.color, parseInt( config.gradientTurn, 10 ) || 40 );
			}

			sample.text = design.ownText || autoText( sample );
			const built = buildButton( form, sample, label ? label.textContent : '' );

			// A hover sample being pointed at keeps showing its hover.
			if ( stage.parentElement.matches( ':hover, :focus-within' ) && stage.dataset.iftpFxSample === 'effect' ) {
				built.classList.add( 'is-hover' );
			}

			pay.className = 'iftp-btn-pay';
			pay.appendChild( built );

			const shown = stage.firstElementChild;

			if ( shown && shown.className === pay.className ) {
				showButton( shown, built );
			} else {
				stage.textContent = '';
				stage.appendChild( pay );
			}
		} );
	}

	// Pointing at (or tabbing to) a hover tile plays its hover on the sample.
	function bindHoverSamples( form ) {
		form.querySelectorAll( '.iftp-btn-fxpick--hover .iftp-btn-fxpick__item' ).forEach( function ( item ) {
			const set = function ( on ) {
				const button = item.querySelector( '.iftp-btn-pay__button' );

				if ( button ) {
					button.classList.toggle( 'is-hover', on );
				}
			};

			item.addEventListener( 'mouseenter', function () {
				set( true );
			} );
			item.addEventListener( 'mouseleave', function () {
				set( item.matches( ':focus-within' ) );
			} );
			item.addEventListener( 'focusin', function () {
				set( true );
			} );
			item.addEventListener( 'focusout', function () {
				set( item.matches( ':hover' ) );
			} );
		} );
	}

	function bindDesignControls( form ) {
		const fire = function ( input ) {
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		bindSwatches( form );
		ns.bindSpectrum( form );
		ns.bindAdvanced( form );
		bindHoverSamples( form );

		const textAuto = form.querySelector( '[data-iftp-design="text_auto"]' );
		const textBox = form.querySelector( '[data-iftp-text-color]' );
		const textField = form.querySelector( '[data-iftp-design="text"]' );
		const textSwatch = textBox ? textBox.querySelector( '[data-iftp-color-for]' ) : null;

		// Touching the text colour means they want their own, so "pick it for me" lets go.
		[ textField, textSwatch ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					if ( textAuto && textAuto.checked ) {
						textAuto.checked = false;
						fire( textAuto );
					}
				} );
			}
		} );

		const radius = form.querySelector( '[data-iftp-design="radius"]' );
		const radiusOut = form.querySelector( '[data-iftp-radius-out]' );
		const borderOut = form.querySelector( '[data-iftp-border-out]' );
		const borderAuto = form.querySelector( '[data-iftp-design="border_color_auto"]' );
		const borderBox = form.querySelector( '[data-iftp-border-color]' );
		const borderField = form.querySelector( '[data-iftp-design="border_color"]' );
		const borderSwatch = borderBox ? borderBox.querySelector( '[data-iftp-color-for]' ) : null;

		// And for the border colour.
		[ borderField, borderSwatch ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					if ( borderAuto && borderAuto.checked ) {
						borderAuto.checked = false;
						fire( borderAuto );
					}
				} );
			}
		} );
		const color2Auto = form.querySelector( '[data-iftp-design="color2_auto"]' );
		const color2Box = form.querySelector( '[data-iftp-color2]' );
		const color2Field = form.querySelector( '[data-iftp-design="color2"]' );
		const color2Swatch = color2Box ? color2Box.querySelector( '[data-iftp-color-for]' ) : null;

		// Same for the gradient's second colour.
		[ color2Field, color2Swatch ].forEach( function ( input ) {
			if ( input ) {
				input.addEventListener( 'input', function () {
					if ( color2Auto && color2Auto.checked ) {
						color2Auto.checked = false;
						fire( color2Auto );
					}
				} );
			}
		} );

		const sync = function () {
			const design = readDesign( form );

			if ( color2Box && color2Auto ) {
				color2Box.classList.toggle( 'is-auto', color2Auto.checked );

				if ( color2Auto.checked ) {
					color2Field.value = design.color2;
					color2Swatch.value = design.color2;
				}
			}

			if ( textBox && textAuto ) {
				textBox.classList.toggle( 'is-auto', textAuto.checked );

				if ( textAuto.checked ) {
					textField.value = design.text;
					textSwatch.value = design.text;
				}
			}

			if ( borderBox && borderAuto ) {
				borderBox.classList.toggle( 'is-auto', borderAuto.checked );

				// Brutal's own border is ink; every other style's is its colour.
				if ( borderAuto.checked ) {
					borderField.value = design.variant === 'brutal' ? '#121a2c' : design.color;
					borderSwatch.value = borderField.value;
				}
			}

			if ( radius && radiusOut ) {
				radiusOut.textContent = design.radius >= ( config.pill || 32 ) ? i18n.pill : ( i18n.px || '%d px' ).replace( '%d', design.radius );
			}

			if ( borderOut ) {
				borderOut.textContent = ( i18n.px || '%d px' ).replace( '%d', design.borderWidth );
			}
		};

		form.addEventListener( 'input', perFrame( sync ) );
		form.addEventListener( 'change', sync );
		sync();

		form.querySelectorAll( '[data-iftp-preset]' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				const preset = ( config.presets || {} )[ item.dataset.iftpPreset ];

				if ( ! preset ) {
					return;
				}

				const set = function ( key, value ) {
					form.querySelectorAll( '[data-iftp-design="' + key + '"]' ).forEach( function ( input ) {
						if ( input.type === 'radio' ) {
							input.checked = input.value === String( value );
						} else if ( input.type === 'checkbox' ) {
							input.checked = !! value;
						} else {
							input.value = value;
						}
					} );
				};

				set( 'variant', preset.variant );
				set( 'color', preset.color );
				set( 'radius', preset.radius );
				set( 'border_width', preset.border_width );
				set( 'border_style', preset.border_style );
				set( 'size', preset.size );
				set( 'icon', preset.icon );
				set( 'shadow', preset.shadow );
				set( 'effect', preset.effect );
				set( 'attention', preset.attention );
				set( 'upper', preset.upper );
				set( 'font', preset.font || '' );
				set( 'text_auto', ! preset.text );
				set( 'color2_auto', ! preset.color2 );
				set( 'border_color_auto', ! preset.border_color );

				if ( preset.stops ) {
					set( 'stops_shape', preset.stops_shape );
					set( 'stops_angle', preset.stops_angle );
					set( 'stops_flow', preset.stops_flow );
					ns.setStops( form, preset.stops );
				}

				if ( preset.border_color ) {
					set( 'border_color', preset.border_color );
				}

				if ( preset.color2 ) {
					set( 'color2', preset.color2 );
				}

				if ( preset.text ) {
					set( 'text', preset.text );
				}

				form.querySelectorAll( '[data-iftp-color-for]' ).forEach( function ( swatch ) {
					const color = hex( form.querySelector( '#' + swatch.dataset.iftpColorFor ).value );

					if ( color ) {
						swatch.value = color;
					}
				} );

				fire( form.querySelector( '[data-iftp-design="color"]' ) );
			} );
		} );

		// "Default look": the design saved as the site's default, all of it.
		form.querySelectorAll( '[data-iftp-preset-saved]' ).forEach( function ( item ) {
			let saved = null;

			try {
				saved = JSON.parse( item.dataset.iftpPresetSaved );
			} catch ( e ) {}

			if ( ! saved ) {
				return;
			}

			item.addEventListener( 'click', function () {
				const set = function ( key, value ) {
					form.querySelectorAll( '[data-iftp-design="' + key + '"]' ).forEach( function ( input ) {
						if ( input.type === 'radio' ) {
							input.checked = input.value === String( value );
						} else if ( input.type === 'checkbox' ) {
							input.checked = !! value;
						} else {
							input.value = value;
						}
					} );
				};

				[ 'variant', 'color', 'radius', 'border_width', 'border_style', 'size', 'full', 'icon', 'icon_style', 'shadow', 'effect', 'attention', 'upper', 'font', 'still', 'logos', 'stops_shape', 'stops_angle', 'stops_flow' ].forEach( function ( key ) {
					set( key, saved[ key ] );
				} );

				ns.setStops( form, saved.stops );

				// Blank colours mean "auto", like the boxes' own checkboxes.
				[ 'text', 'color2', 'border_color' ].forEach( function ( key ) {
					set( key + '_auto', ! saved[ key ] );

					if ( saved[ key ] ) {
						set( key, saved[ key ] );
					}
				} );

				form.querySelectorAll( '[data-iftp-color-for]' ).forEach( function ( swatch ) {
					const color = hex( form.querySelector( '#' + swatch.dataset.iftpColorFor ).value );

					if ( color ) {
						swatch.value = color;
					}
				} );

				// The saved design may be "match my theme"; the source radios show or hide the custom controls.
				set( 'source', saved.source );
				const picked = form.querySelector( '[data-iftp-design="source"]:checked' );

				if ( picked ) {
					fire( picked );
				}

				fire( form.querySelector( '[data-iftp-design="color"]' ) );
			} );
		} );

		// The button's preview sits on a light or dark background by the colour mode shared by every preview.
		const stage = form.querySelector( '[data-iftp-stage]' );
		const schemes = window.iftpBtnScheme;

		if ( stage && schemes ) {
			stage.dataset.bg = schemes.effective();
			schemes.subscribe( function ( mode, shown ) {
				stage.dataset.bg = shown;
			} );
		}
	}
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
