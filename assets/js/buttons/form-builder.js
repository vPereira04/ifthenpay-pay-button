/**
 * Buttons and Checkout screens, the checkout form builder and the spots per option.
 */
( function ( ns ) {
	'use strict';

	const { i18n } = ns;

	ns.bindFormBuilder = bindFormBuilder;
	ns.bindSpots = bindSpots;
	ns.el = el;
	ns.visibleItems = visibleItems;

	/* ---------------------------------------------------------------- Checkout form builder */

	const fbIcons = {
		amount: '<circle cx="12" cy="12" r="8.5"/><path d="M15 8.8a3.6 3.6 0 1 0 0 6.4M7.5 11h6M7.5 13.2h6"/>',
		details: '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
		text: '<path d="M5 7h14M5 12h10M5 17h7"/>',
		textarea: '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M7.5 9h9M7.5 12.5h9M7.5 16h5"/>',
		email: '<rect x="3.5" y="6" width="17" height="12" rx="2.5"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5"/>',
		tel: '<path d="M6.5 4h3l1.5 4-2 1.3a10 10 0 0 0 5.7 5.7l1.3-2 4 1.5v3a2 2 0 0 1-2 2A15.5 15.5 0 0 1 4.5 6a2 2 0 0 1 2-2z"/>',
		number: '<path d="M10 4 8 20M16 4l-2 16M5 9h15M4 15h15"/>',
		date: '<rect x="4" y="5.5" width="16" height="14" rx="2.5"/><path d="M4 10h16M8.5 3.5v4M15.5 3.5v4"/>',
		select: '<rect x="3.5" y="6.5" width="17" height="11" rx="2.5"/><path d="m14 11 2 2 2-2"/>',
		radio: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.2" fill="currentColor" stroke="none"/>',
		checkboxes: '<rect x="4" y="4" width="16" height="16" rx="3.5"/><path d="m8.5 12.3 2.4 2.4 4.8-5.2"/>',
		checkbox: '<path d="M12 3.5 5 6.5v4.8c0 4.2 3 8 7 9.2 4-1.2 7-5 7-9.2V6.5z"/><path d="m9.2 12 2 2 3.8-4"/>',
		content: '<path d="M5 6h14M5 10h14M5 14h9M5 18h6"/>',
		page: '<path d="M7 3.5h10v5H7zM7 15.5h10v5H7z"/><path d="M3.5 12h3M10.5 12h3M17.5 12h3"/>',
		image: '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.7"/><path d="m4 17.5 5-5 4 4 2.5-2.5 4.5 4.5"/>',
	};

	const fbInputs = [ 'text', 'textarea', 'email', 'tel', 'number', 'date', 'select', 'radio', 'checkboxes', 'checkbox' ];
	const fbChoices = [ 'select', 'radio', 'checkboxes' ];
	// The choices whose options can carry a picture.
	const fbPictured = [ 'radio', 'checkboxes' ];
	const fbPlaceholder = [ 'text', 'textarea', 'email', 'tel', 'number', 'select' ];
	// What a condition can hang on: any input. An agreement's one answer is "ticked" ('1').
	const fbConditions = fbInputs;
	const fbValueless = [ 'filled', 'empty' ];
	const fbTextRules = [ 'text', 'textarea', 'tel' ];
	const fbMax = 40;

	function noCondition() {
		return { field: '', op: 'is', values: [] };
	}

	function noRules() {
		return { min: '', max: '', whole: false, min_len: 0, max_len: 0 };
	}

	// What each kind of field can be compared by, like FormFields::ops_for().
	function opsFor( type ) {
		if ( fbChoices.indexOf( type ) !== -1 || type === 'checkbox' ) {
			return [ 'is', 'not' ];
		}

		return type === 'number' ? [ 'gt', 'lt', 'is', 'not', 'filled', 'empty' ] : [ 'is', 'not', 'contains', 'filled', 'empty' ];
	}

	// Whether a condition does anything: it names a field, and has what its op needs.
	function isActive( when ) {
		return !! ( when && when.field && ( fbValueless.indexOf( when.op ) !== -1 || ( when.values || [] ).length ) );
	}

	// The same rules as FormFields::tidy_conditions(): an earlier field, an op it offers, and only values it still has.
	function tidyConditions( items ) {
		const earlier = {};

		items.forEach( function ( item ) {
			const when = item.when || noCondition();
			let controller = when.field ? earlier[ when.field ] : null;

			item.rules = item.rules || noRules();

			if ( controller ) {
				if ( opsFor( controller.type ).indexOf( when.op ) === -1 ) {
					controller = null;
				} else if ( fbValueless.indexOf( when.op ) !== -1 ) {
					when.values = [];
				} else if ( controller.type === 'number' ) {
					when.values = ( when.values || [] ).filter( function ( value ) {
						return value !== '' && ! isNaN( Number( value ) );
					} ).slice( 0, 1 );
				} else if ( fbChoices.indexOf( controller.type ) === -1 && controller.type !== 'checkbox' ) {
					when.values = ( when.values || [] ).slice( 0, 1 );
				} else {
					const allowed = controller.type === 'checkbox' ? [ '1' ] : controller.options || [];

					when.values = ( when.values || [] ).filter( function ( value ) {
						return allowed.indexOf( value ) !== -1;
					} );
				}
			}

			item.when = controller ? when : noCondition();

			if ( fbConditions.indexOf( item.type ) !== -1 ) {
				earlier[ item.id ] = item;
			}
		} );

		// A page that holds the amount or the payer details can't be hidden.
		let page = null;

		items.forEach( function ( item ) {
			if ( item.type === 'page' ) {
				page = item;
			} else if ( page && ( item.type === 'amount' || item.type === 'details' ) ) {
				page.when = noCondition();
			}
		} );
	}

	// The same test as FormFields::meets().
	function meets( when, picked, typed ) {
		const want = ( when.values || [] )[ 0 ];

		if ( when.op === 'filled' ) {
			return picked.length > 0;
		}

		if ( when.op === 'empty' ) {
			return picked.length === 0;
		}

		if ( when.op === 'gt' || when.op === 'lt' ) {
			const have = parseFloat( picked[ 0 ] );
			const limit = parseFloat( want );

			if ( isNaN( have ) || isNaN( limit ) ) {
				return false;
			}

			return when.op === 'gt' ? have > limit : have < limit;
		}

		if ( when.op === 'contains' ) {
			return !! want && String( picked[ 0 ] || '' ).toLowerCase().indexOf( String( want ).toLowerCase() ) !== -1;
		}

		let match = picked.some( function ( value ) {
			return when.values.indexOf( value ) !== -1;
		} );

		if ( ! match && typed && picked.length && want !== undefined ) {
			match = String( picked[ 0 ] ).toLowerCase() === String( want ).toLowerCase();
		}

		if ( ! match && picked.length && ! isNaN( Number( picked[ 0 ] ) ) ) {
			match = when.values.some( function ( value ) {
				return value !== '' && ! isNaN( Number( value ) ) && Number( value ) === Number( picked[ 0 ] );
			} );
		}

		return when.op === 'not' ? ! match : match;
	}

	// Which items show for some answers, like FormFields::visibility(); the editor's mock uses it.
	function visibleItems( items, answers ) {
		const shown = {};
		const types = {};
		let page = true;

		items.forEach( function ( item ) {
			const when = item.when || noCondition();
			const show = ! isActive( when ) || ( !! shown[ when.field ] && meets( when, answers[ when.field ] || [], fbChoices.indexOf( types[ when.field ] ) === -1 && types[ when.field ] !== 'checkbox' ) );

			types[ item.id ] = item.type;

			if ( item.type === 'page' ) {
				page = show;
				shown[ item.id ] = show;

				return;
			}

			shown[ item.id ] = page && show;
		} );

		return shown;
	}

	function fbIcon( type ) {
		return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ( fbIcons[ type ] || '' ) + '</svg>';
	}

	function slug( text ) {
		return String( text || '' )
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' )
			.slice( 0, 40 );
	}

	function el( tag, className, text ) {
		const node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( text !== undefined ) {
			node.textContent = text;
		}

		return node;
	}

	function bindFormBuilder( form ) {
		const root = form.querySelector( '[data-iftp-fb]' );

		if ( ! root ) {
			form.iftpFormState = [];

			return;
		}

		const input = root.querySelector( '[data-iftp-fb-input]' );
		const list = root.querySelector( '[data-iftp-fb-list]' );
		const first = root.querySelector( '[data-iftp-fb-first]' );
		const types = JSON.parse( root.dataset.types || '{}' );
		const fb = i18n.fb || {};
		// UI-only flags (open, fresh) live beside the items, so they never reach the saved JSON.
		const ui = new WeakMap();
		let items = [];

		try {
			items = JSON.parse( input.value || '[]' );
		} catch ( e ) {
			items = [];
		}

		items = Array.isArray( items ) ? items : [];

		// PHP writes an empty map as [], which can't hold a picture.
		items.forEach( function ( item ) {
			if ( ! item.images || Array.isArray( item.images ) ) {
				item.images = {};
			}
		} );

		form.iftpFormState = items;

		root.querySelectorAll( '[data-iftp-fb-icon]' ).forEach( function ( slot ) {
			slot.innerHTML = fbIcon( slot.dataset.iftpFbIcon );
		} );

		// The pictures' thumbnails by media ID, for the picker. Not saved: the server resolves the IDs itself.
		const thumbs = {};
		let pictureFrame = null;

		const thumbFor = function ( id ) {
			if ( thumbs[ id ] !== undefined ) {
				return;
			}

			thumbs[ id ] = '';

			if ( ! window.wp || ! window.wp.media || ! window.wp.media.attachment ) {
				return;
			}

			window.wp.media.attachment( id ).fetch().then( function () {
				const data = window.wp.media.attachment( id ).toJSON();
				const sizes = data.sizes || {};

				thumbs[ id ] = ( sizes.thumbnail || sizes.medium || data ).url || '';
				root.querySelectorAll( '[data-iftp-fb-pics]' ).forEach( function ( block ) {
					const holder = block.closest( '[data-index]' );

					if ( holder && items[ parseInt( holder.dataset.index, 10 ) ] ) {
						fillPictures( block, items[ parseInt( holder.dataset.index, 10 ) ] );
					}
				} );
			} );
		};

		// One row per option: its picture to pick or change, and a way to take it off.
		function fillPictures( block, item ) {
			block.textContent = '';

			( item.options || [] ).forEach( function ( option, index ) {
				const id = ( item.images || {} )[ option ];
				const row = el( 'div', 'iftp-btn-fb__pic' );
				const pick = el( 'button', 'iftp-btn-fb__pic-thumb' + ( id ? ' has-image' : '' ) );
				const name = el( 'span', 'iftp-btn-fb__pic-name', option );

				pick.type = 'button';
				pick.dataset.iftpFbPic = 'pick';
				pick.dataset.option = String( index );
				pick.setAttribute( 'aria-label', ( id ? fb.changePicture : fb.addPicture ) + ': ' + option );

				if ( id ) {
					thumbFor( id );

					if ( thumbs[ id ] ) {
						pick.style.backgroundImage = 'url("' + thumbs[ id ].replace( /["\\]/g, '' ) + '")';
					}
				} else {
					pick.innerHTML = fbIcon( 'image' );
				}

				row.appendChild( pick );
				row.appendChild( name );

				if ( id ) {
					const clear = el( 'button', 'iftp-btn-fb__pic-clear' );

					clear.type = 'button';
					clear.dataset.iftpFbPic = 'clear';
					clear.dataset.option = String( index );
					clear.textContent = fb.removePicture;
					clear.setAttribute( 'aria-label', fb.removePicture + ': ' + option );
					row.appendChild( clear );
				}

				block.appendChild( row );
			} );
		}

		const flag = function ( item ) {
			if ( ! ui.has( item ) ) {
				ui.set( item, { open: false, fresh: false } );
			}

			return ui.get( item );
		};

		const isAnchor = function ( item ) {
			return item.type === 'amount' || item.type === 'details';
		};

		const uniqueId = function ( wanted, self ) {
			const base = wanted || 'field';
			let id = base;
			let n = 2;

			const taken = function ( candidate ) {
				return candidate === 'amount' || candidate === 'details' || items.some( function ( other ) {
					return other !== self && other.id === candidate;
				} );
			};

			while ( taken( id ) ) {
				id = base.slice( 0, 36 ) + '_' + n++;
			}

			return id;
		};

		const save = function () {
			tidyConditions( items );

			items.forEach( function ( item ) {
				if ( form.iftpSpotsField && form.iftpSpotsField() === item.id ) {
					item.when = noCondition();
				}
			} );

			input.value = JSON.stringify(
				items.map( function ( item ) {
					return {
						id: item.id,
						type: item.type,
						label: item.label || '',
						required: !! item.required,
						placeholder: item.placeholder || '',
						help: item.help || '',
						options: item.options || [],
						prices: item.prices || {},
						limits: item.limits || {},
						images: fbPictured.indexOf( item.type ) !== -1 ? item.images || {} : {},
						pictures: fbPictured.indexOf( item.type ) !== -1 && !! item.pictures,
						picture_shape: item.picture_shape || 'auto',
						picture_layout: item.picture_layout || 'grid',
						picture_cols: parseInt( item.picture_cols, 10 ) || 0,
						picture_size: item.picture_size || 'medium',
						icon: fbInputs.indexOf( item.type ) !== -1 && item.type !== 'checkbox' ? item.icon || '' : '',
						width: item.width === 'half' ? 'half' : 'full',
						when: isActive( item.when ) ? item.when : noCondition(),
						rules: item.rules || noRules(),
					};
				} )
			);
			first.hidden = ! items.some( function ( item ) {
				return item.type === 'page';
			} );
			root.querySelectorAll( '[data-iftp-fb-add]' ).forEach( function ( add ) {
				add.disabled = items.length >= fbMax + 2;
			} );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		const meta = function ( item ) {
			const parts = [ types[ item.type ] || item.type ];

			if ( fbInputs.indexOf( item.type ) !== -1 ) {
				parts.push( item.required ? fb.required : fb.optional );
			}

			if ( item.width === 'half' ) {
				parts.push( fb.half );
			}

			if ( isActive( item.when ) ) {
				parts.push( fb.conditional );
			}

			return parts.join( ' · ' );
		};

		const control = function ( labelText, node, wide ) {
			const field = el( 'label', 'iftp-btn-field' + ( wide ? ' iftp-btn-fb__wide' : '' ) );

			field.appendChild( el( 'span', 'iftp-btn-field__label', labelText ) );
			field.appendChild( node );

			return field;
		};

		const textInput = function ( value, key, placeholder ) {
			const node = el( 'input', 'iftp-btn-field__control' );

			node.type = 'text';
			node.value = value || '';
			node.dataset.iftpFbKey = key;

			if ( placeholder ) {
				node.placeholder = placeholder;
			}

			return node;
		};

		// Why a page or field can't be hidden, or ''.
		const lockedWhy = function ( item ) {
			const spots = form.iftpSpotsField ? form.iftpSpotsField() : '';

			if ( item.type !== 'page' ) {
				return spots === item.id ? fb.whenSpots : '';
			}

			const from = items.indexOf( item );

			for ( let i = from + 1; i < items.length && items[ i ].type !== 'page'; i++ ) {
				if ( items[ i ].type === 'amount' || items[ i ].type === 'details' ) {
					return fb.whenPageAnchors;
				}

				if ( spots && items[ i ].id === spots ) {
					return fb.whenPageSpots;
				}
			}

			return '';
		};

		const opLabels = { is: fb.whenIs, not: fb.whenNot, gt: fb.opGt, lt: fb.opLt, contains: fb.opContains, filled: fb.opFilled, empty: fb.opEmpty };
		// On a typed field "is one of" reads wrong: it is one answer.
		const typedLabels = { is: fb.opEquals, not: fb.opNotEquals };

		// "Show this only when an earlier answer is (or isn't) one of these", more or less than a number, or filled in: a workshop
		// day, a group over ten. A page break takes the same rule, and hides the page with everything on it.
		const conditionBox = function ( item ) {
			const box = el( 'div', 'iftp-btn-fb__when' );
			const isPage = item.type === 'page';

			box.appendChild( el( 'span', 'iftp-btn-field__label', isPage ? fb.showPage : fb.showWhen ) );

			// The field the spots are split by, and the amount or details, have to be answered, so they can't be hidden.
			const why = lockedWhy( item );

			if ( why ) {
				box.appendChild( el( 'p', 'iftp-btn-field__help', why ) );

				return box;
			}

			const when = item.when && item.when.field ? item.when : noCondition();
			const controllers = items.slice( 0, items.indexOf( item ) ).filter( function ( other ) {
				return fbConditions.indexOf( other.type ) !== -1;
			} );

			if ( ! controllers.length ) {
				box.appendChild( el( 'p', 'iftp-btn-field__help', fb.whenNone ) );

				return box;
			}

			const line = el( 'div', 'iftp-btn-fb__when-line' );
			const pick = el( 'select', 'iftp-btn-field__control' );
			const always = el( 'option', '', fb.always );

			always.value = '';
			pick.appendChild( always );
			pick.dataset.iftpFbKey = 'when-field';
			pick.setAttribute( 'aria-label', fb.showWhen );

			controllers.forEach( function ( controller ) {
				const option = el( 'option', '', ( fb.whenField || '%s' ).replace( '%s', controller.label || types[ controller.type ] ) );

				option.value = controller.id;
				option.selected = controller.id === when.field;
				pick.appendChild( option );
			} );

			line.appendChild( pick );

			const controller = controllers.filter( function ( other ) {
				return other.id === when.field;
			} )[ 0 ];
			const choice = controller && ( fbChoices.indexOf( controller.type ) !== -1 || controller.type === 'checkbox' );

			if ( controller && choice ) {
				const ops = el( 'span', 'iftp-btn-seg' );

				[ 'is', 'not' ].forEach( function ( op ) {
					const option = el( 'label', 'iftp-btn-seg__option' );
					const radio = el( 'input' );

					radio.type = 'radio';
					radio.name = 'iftp-fb-when-' + item.id + '-' + items.indexOf( item );
					radio.value = op;
					radio.checked = when.op === op;
					radio.dataset.iftpFbKey = 'when-op';
					option.appendChild( radio );
					option.appendChild( el( 'span', '', opLabels[ op ] ) );
					ops.appendChild( option );
				} );

				line.appendChild( ops );
			} else if ( controller ) {
				const ops = el( 'select', 'iftp-btn-field__control' );

				opsFor( controller.type ).forEach( function ( op ) {
					const option = el( 'option', '', ( typedLabels[ op ] || opLabels[ op ] ) );

					option.value = op;
					option.selected = when.op === op;
					ops.appendChild( option );
				} );

				ops.dataset.iftpFbKey = 'when-op';
				ops.setAttribute( 'aria-label', fb.showWhen );
				line.appendChild( ops );

				if ( fbValueless.indexOf( when.op ) === -1 ) {
					const isNumber = controller.type === 'number';
					const num = textInput( ( when.values || [] )[ 0 ] || '', 'when-num', isNumber ? fb.whenNumber : fb.whenText );

					num.inputMode = isNumber ? 'decimal' : 'text';
					num.setAttribute( 'aria-label', isNumber ? fb.whenNumber : fb.whenText );
					line.appendChild( num );
				}
			}

			box.appendChild( line );

			if ( controller && choice ) {
				const values = el( 'div', 'iftp-btn-fb__when-values' );
				const options = controller.type === 'checkbox' ? [ [ '1', fb.ticked ] ] : ( controller.options || [] ).map( function ( option ) {
					return [ option, option ];
				} );

				options.forEach( function ( pair ) {
					const label = el( 'label', 'iftp-btn-check' );
					const tick = el( 'input' );

					tick.type = 'checkbox';
					tick.value = pair[ 0 ];
					tick.checked = when.values.indexOf( pair[ 0 ] ) !== -1;
					tick.dataset.iftpFbKey = 'when-value';
					label.appendChild( tick );
					label.appendChild( document.createTextNode( ' ' + pair[ 1 ] ) );
					values.appendChild( label );
				} );

				box.appendChild( values );

				if ( ! when.values.length ) {
					box.appendChild( el( 'p', 'iftp-btn-field__help', fb.whenPick ) );
				}
			} else if ( controller && fbValueless.indexOf( when.op ) === -1 && ! ( when.values || [] ).length ) {
				box.appendChild( el( 'p', 'iftp-btn-field__help', controller.type === 'number' ? fb.whenType : fb.whenTypeText ) );
			}

			return box;
		};

		// What a Number or a text field will accept: the server holds the payer to it, and the browser says so as they type.
		const rulesBox = function ( item ) {
			const rules = item.rules || noRules();
			const box = el( 'div', 'iftp-btn-fb__rules' );
			const grid = el( 'div', 'iftp-btn-fb__grid' );
			const number = function ( value, key ) {
				const node = textInput( value ? String( value ) : '', key, fb.noLimit );

				node.inputMode = key === 'rule-min' || key === 'rule-max' ? 'decimal' : 'numeric';

				return node;
			};

			box.appendChild( el( 'span', 'iftp-btn-field__label', fb.rules ) );

			if ( item.type === 'number' ) {
				grid.appendChild( control( fb.ruleMin, number( rules.min, 'rule-min' ), false ) );
				grid.appendChild( control( fb.ruleMax, number( rules.max, 'rule-max' ), false ) );

				const whole = el( 'label', 'iftp-btn-check iftp-btn-fb__wide' );
				const tick = el( 'input' );

				tick.type = 'checkbox';
				tick.checked = !! rules.whole;
				tick.dataset.iftpFbKey = 'rule-whole';
				whole.appendChild( tick );
				whole.appendChild( document.createTextNode( ' ' + fb.ruleWhole ) );
				grid.appendChild( whole );
			} else {
				grid.appendChild( control( fb.ruleMinLen, number( rules.min_len, 'rule-min_len' ), false ) );
				grid.appendChild( control( fb.ruleMaxLen, number( rules.max_len, 'rule-max_len' ), false ) );
			}

			box.appendChild( grid );
			box.appendChild( el( 'p', 'iftp-btn-field__help', item.type === 'number' ? fb.ruleNumberHint : fb.ruleTextHint ) );

			return box;
		};

		const panel = function ( item ) {
			const box = el( 'div', 'iftp-btn-fb__panel' );
			const grid = el( 'div', 'iftp-btn-fb__grid' );
			const isContent = item.type === 'content';

			grid.appendChild( control( isContent ? fb.heading : fb.label, textInput( item.label, 'label' ), false ) );

			if ( ! isContent ) {
				const typeSelect = el( 'select', 'iftp-btn-field__control' );

				fbInputs.forEach( function ( type ) {
					const option = el( 'option', '', types[ type ] );

					option.value = type;
					option.selected = type === item.type;
					typeSelect.appendChild( option );
				} );

				typeSelect.dataset.iftpFbKey = 'type';
				grid.appendChild( control( fb.type, typeSelect, false ) );

				const tag = el( 'span', 'iftp-btn-fb__tag' );
				const idInput = textInput( item.id, 'id' );

				idInput.maxLength = 40;
				idInput.spellcheck = false;
				tag.appendChild( el( 'span', 'iftp-btn-fb__tag-brace', '{field:' ) );
				tag.appendChild( idInput );
				tag.appendChild( el( 'span', 'iftp-btn-fb__tag-brace', '}' ) );
				grid.appendChild( control( fb.tag, tag, false ) );
			}

			if ( fbPlaceholder.indexOf( item.type ) !== -1 ) {
				grid.appendChild( control( item.type === 'select' ? fb.firstOption : fb.placeholder, textInput( item.placeholder, 'placeholder' ), false ) );
			}

			if ( fbChoices.indexOf( item.type ) !== -1 ) {
				const options = el( 'textarea', 'iftp-btn-field__control' );

				options.rows = 4;
				options.value = ( item.options || [] ).join( '\n' );
				options.placeholder = fb.optionsHint;
				options.dataset.iftpFbKey = 'options';
				grid.appendChild( control( fb.options, options, true ) );

				if ( fbPictured.indexOf( item.type ) !== -1 ) {
					const pics = el( 'div', 'iftp-btn-field iftp-btn-fb__wide' );
					const block = el( 'div', 'iftp-btn-fb__pics' );

					const toggle = el( 'label', 'iftp-btn-check' );
					const on = el( 'input' );

					on.type = 'checkbox';
					on.checked = !! item.pictures;
					on.dataset.iftpFbKey = 'pictures';
					toggle.appendChild( on );
					toggle.appendChild( document.createTextNode( ' ' + fb.showPictures ) );
					pics.appendChild( toggle );

					// The rows only matter while pictures are on; what's picked stays saved when it's off.
					const body = el( 'div', 'iftp-btn-fb__pics-body' );

					body.hidden = ! item.pictures;

					// How the cards look: their shape (Auto follows the pictures when they all share one), a grid or a sideways row,
					// how many to a line, and how big. Each is a row of radios saved straight on the item.
					const choiceRow = function ( label, key, values, current, hint ) {
						const row = el( 'div', 'iftp-btn-field' );
						const group = el( 'span', 'iftp-btn-seg' );

						row.appendChild( el( 'span', 'iftp-btn-field__label', label ) );

						Object.keys( values ).forEach( function ( value ) {
							const option = el( 'label', 'iftp-btn-seg__option' );
							const radio = el( 'input' );

							radio.type = 'radio';
							radio.name = 'iftp-fb-' + key + '-' + items.indexOf( item );
							radio.value = value;
							radio.checked = String( current ) === value;
							radio.dataset.iftpFbKey = key;
							option.appendChild( radio );
							option.appendChild( el( 'span', '', values[ value ] ) );
							group.appendChild( option );
						} );

						row.appendChild( group );

						if ( hint ) {
							row.appendChild( el( 'span', 'iftp-btn-field__help', hint ) );
						}

						return row;
					};
					const perLine = { 0: fb.colsAuto };

					[ 2, 3, 4, 5, 6 ].forEach( function ( count ) {
						perLine[ count ] = String( count );
					} );

					body.appendChild( choiceRow( fb.layoutLabel, 'picture_layout', fb.layouts || {}, item.picture_layout || 'grid' ) );
					body.appendChild( choiceRow( fb.colsLabel, 'picture_cols', perLine, item.picture_cols || 0, fb.colsHint ) );
					body.appendChild( choiceRow( fb.sizeLabel, 'picture_size', fb.sizes || {}, item.picture_size || 'medium', fb.sizeHint ) );
					body.appendChild( choiceRow( fb.pictureShape, 'picture_shape', fb.shapes || {}, item.picture_shape || 'auto', fb.shapeHint ) );
					block.dataset.iftpFbPics = '';
					fillPictures( block, item );
					body.appendChild( block );
					body.appendChild( el( 'span', 'iftp-btn-fb__pics-hint', fb.picturesHint ) );
					pics.appendChild( body );
					grid.appendChild( pics );
				}
			}

			// A small icon before the field's title; a single checkbox's label is the consent line, so it has none.
			if ( fbInputs.indexOf( item.type ) !== -1 && item.type !== 'checkbox' ) {
				const picker = el( 'div', 'iftp-btn-fb__icons' );
				const group = el( 'div', 'iftp-btn-field iftp-btn-fb__wide' );

				picker.setAttribute( 'role', 'radiogroup' );
				[ '' ].concat( Object.keys( fb.icons || {} ) ).forEach( function ( name ) {
					const option = el( 'label', 'iftp-btn-fb__icon' );
					const radio = el( 'input' );
					const glyph = el( 'span', 'iftp-btn-fb__icon-glyph' );

					radio.type = 'radio';
					radio.name = 'iftp-fb-icon-' + item.id + '-' + items.indexOf( item );
					radio.value = name;
					radio.checked = ( item.icon || '' ) === name;
					radio.dataset.iftpFbKey = 'icon';
					option.title = name ? name : fb.noIcon;
					glyph.innerHTML = name ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + fb.icons[ name ] + '</svg>' : '<span class="iftp-btn-fb__icon-none" aria-hidden="true"></span>';
					option.appendChild( radio );
					option.appendChild( glyph );
					option.appendChild( el( 'span', 'screen-reader-text', name ? name : fb.noIcon ) );
					picker.appendChild( option );
				} );

				group.appendChild( el( 'span', 'iftp-btn-field__label', fb.icon ) );
				group.appendChild( picker );
				grid.appendChild( group );
			}

			const help = el( 'textarea', 'iftp-btn-field__control' );

			help.rows = isContent ? 3 : 2;
			help.value = item.help || '';
			help.dataset.iftpFbKey = 'help';
			grid.appendChild( control( isContent ? fb.text : fb.help, help, true ) );

			if ( isContent ) {
				grid.appendChild( el( 'p', 'iftp-btn-field__help iftp-btn-fb__wide', fb.liveHint ) );
			}

			box.appendChild( grid );

			if ( item.type === 'number' || fbTextRules.indexOf( item.type ) !== -1 ) {
				box.appendChild( rulesBox( item ) );
			}

			if ( fbInputs.indexOf( item.type ) !== -1 ) {
				const row = el( 'div', 'iftp-btn-fb__switches' );
				const required = el( 'label', 'iftp-btn-check' );
				const tick = el( 'input' );
				const width = el( 'span', 'iftp-btn-seg' );

				tick.type = 'checkbox';
				tick.checked = !! item.required;
				tick.dataset.iftpFbKey = 'required';

				// The field the spots are split by has to be answered, so its tick stays on.
				if ( form.iftpSpotsField && form.iftpSpotsField() === item.id ) {
					tick.checked = true;
					tick.disabled = true;
					required.title = fb.spotsRequired || '';
				}
				required.appendChild( tick );
				required.appendChild( document.createTextNode( ' ' + fb.mustAnswer ) );
				row.appendChild( required );

				[ [ 'full', fb.full ], [ 'half', fb.halfWidth ] ].forEach( function ( pair ) {
					const option = el( 'label', 'iftp-btn-seg__option' );
					const radio = el( 'input' );

					radio.type = 'radio';
					radio.name = 'iftp-fb-width-' + item.id;
					radio.value = pair[ 0 ];
					radio.checked = ( item.width || 'full' ) === pair[ 0 ];
					radio.dataset.iftpFbKey = 'width';
					option.appendChild( radio );
					option.appendChild( el( 'span', '', pair[ 1 ] ) );
					width.appendChild( option );
				} );

				row.appendChild( width );
				box.appendChild( row );
			}

			box.appendChild( conditionBox( item ) );

			return box;
		};

		const toolButton = function ( action, label, icon, disabled ) {
			const button = el( 'button', 'iftp-btn-tool' + ( action === 'remove' ? ' iftp-btn-tool--danger' : '' ) );

			button.type = 'button';
			button.dataset.iftpFbAction = action;
			button.setAttribute( 'aria-label', label );
			button.dataset.iftpTip = label;
			button.disabled = !! disabled;
			button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + icon + '</svg>';

			return button;
		};

		const render = function ( focus ) {
			let page = 1;

			list.textContent = '';

			items.forEach( function ( item, index ) {
				const li = el( 'li', 'iftp-btn-fb__item iftp-btn-fb__item--' + item.type );
				const row = el( 'div', 'iftp-btn-fb__row' );
				const icon = el( 'span', 'iftp-btn-fb__icon' );
				const tools = el( 'span', 'iftp-btn-fb__tools' );
				const state = flag( item );

				li.dataset.index = String( index );
				icon.innerHTML = fbIcon( item.type );
				row.appendChild( icon );

				if ( item.type === 'page' ) {
					page++;
					li.classList.add( 'iftp-btn-fb__item--break' );
					row.appendChild( el( 'span', 'iftp-btn-fb__page', ( fb.page || '%d' ).replace( '%d', page ) ) );

					const title = textInput( item.label, 'label', fb.pageTitle );

					title.className = 'iftp-btn-field__control iftp-btn-fb__page-title';
					title.setAttribute( 'aria-label', fb.pageTitle );
					row.appendChild( title );

					if ( isActive( item.when ) ) {
						row.appendChild( el( 'span', 'iftp-btn-fb__meta', fb.conditional ) );
					}

					li.classList.toggle( 'is-open', state.open );
				} else if ( isAnchor( item ) ) {
					li.classList.add( 'iftp-btn-fb__item--anchor' );

					const name = el( 'span', 'iftp-btn-fb__name' );

					name.appendChild( el( 'strong', '', item.type === 'amount' ? fb.amount : fb.details ) );
					name.appendChild( el( 'span', 'iftp-btn-fb__meta', item.type === 'amount' ? fb.amountHint : fb.detailsHint ) );
					row.appendChild( name );
				} else {
					const name = el( 'button', 'iftp-btn-fb__name' );

					name.type = 'button';
					name.dataset.iftpFbAction = 'toggle';
					name.setAttribute( 'aria-expanded', state.open ? 'true' : 'false' );
					name.appendChild( el( 'strong', 'iftp-btn-fb__label', item.label || types[ item.type ] ) );
					name.appendChild( el( 'span', 'iftp-btn-fb__meta', meta( item ) ) );
					row.appendChild( name );
					li.classList.toggle( 'is-open', state.open );
				}

				tools.appendChild( toolButton( 'up', fb.up, '<path d="m6 15 6-6 6 6"/>', index === 0 ) );
				tools.appendChild( toolButton( 'down', fb.down, '<path d="m6 9 6 6 6-6"/>', index === items.length - 1 ) );

				if ( ! isAnchor( item ) ) {
					tools.appendChild( toolButton( 'remove', fb.remove, '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>' ) );
				}

				if ( item.type === 'page' ) {
					const rules = toolButton( 'toggle', fb.pageRules, '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>' );

					rules.setAttribute( 'aria-expanded', state.open ? 'true' : 'false' );
					tools.insertBefore( rules, tools.firstChild );
				}

				row.appendChild( tools );
				li.appendChild( row );

				if ( state.open && item.type === 'page' ) {
					const pagePanel = el( 'div', 'iftp-btn-fb__panel' );

					pagePanel.appendChild( conditionBox( item ) );
					li.appendChild( pagePanel );
				} else if ( state.open && ! isAnchor( item ) ) {
					li.appendChild( panel( item ) );
				}

				list.appendChild( li );
			} );

			if ( focus ) {
				const target = list.querySelector( focus );

				if ( target ) {
					target.focus();
				}
			}
		};

		root.querySelectorAll( '[data-iftp-fb-add]' ).forEach( function ( add ) {
			add.addEventListener( 'click', function () {
				const type = add.dataset.iftpFbAdd;
				const label = type === 'page' ? '' : types[ type ];
				const item = { id: '', type: type, label: label, required: false, placeholder: '', help: '', options: [], prices: {}, limits: {}, images: {}, pictures: false, picture_shape: 'auto', picture_layout: 'grid', picture_cols: 0, picture_size: 'medium', icon: '', width: 'full', when: noCondition(), rules: noRules() };

				if ( fbChoices.indexOf( type ) !== -1 ) {
					item.options = [ fb.option + ' 1', fb.option + ' 2' ];
				}

				if ( type !== 'page' ) {
					item.id = uniqueId( slug( label ), item );
				}

				items.push( item );
				flag( item ).open = type !== 'page';
				flag( item ).fresh = true;
				render( '[data-index="' + ( items.length - 1 ) + '"] ' + ( type === 'page' ? '.iftp-btn-fb__page-title' : '[data-iftp-fb-key="label"]' ) );
				save();

				const added = list.querySelector( '[data-index="' + ( items.length - 1 ) + '"]' );

				if ( added && added.scrollIntoView ) {
					added.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
				}
			} );
		} );

		list.addEventListener( 'click', function ( event ) {
			const pic = event.target.closest( '[data-iftp-fb-pic]' );

			if ( ! pic ) {
				return;
			}

			const holder = pic.closest( '[data-index]' );
			const item = holder ? items[ parseInt( holder.dataset.index, 10 ) ] : null;
			const option = item ? ( item.options || [] )[ parseInt( pic.dataset.option, 10 ) ] : undefined;
			const block = pic.closest( '[data-iftp-fb-pics]' );

			if ( ! item || option === undefined || ! block ) {
				return;
			}

			item.images = item.images || {};

			if ( pic.dataset.iftpFbPic === 'clear' ) {
				delete item.images[ option ];
				fillPictures( block, item );
				save();

				return;
			}

			if ( ! window.wp || ! window.wp.media ) {
				return;
			}

			if ( ! pictureFrame ) {
				pictureFrame = window.wp.media( {
					title: fb.pictureTitle,
					button: { text: fb.usePicture },
					library: { type: 'image' },
					multiple: false,
				} );
			}

			// A fresh listener each time, so the pick lands on the option that was clicked.
			pictureFrame.off( 'select' );
			pictureFrame.on( 'select', function () {
				const chosen = pictureFrame.state().get( 'selection' ).first().toJSON();
				const sizes = chosen.sizes || {};

				item.images[ option ] = chosen.id;
				thumbs[ chosen.id ] = ( sizes.thumbnail || sizes.medium || chosen ).url || '';
				fillPictures( block, item );
				save();
			} );
			pictureFrame.open();
		} );

		list.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '[data-iftp-fb-action]' );

			if ( ! button ) {
				return;
			}

			const index = parseInt( button.closest( '[data-index]' ).dataset.index, 10 );
			const action = button.dataset.iftpFbAction;
			const item = items[ index ];

			if ( action === 'toggle' ) {
				flag( item ).open = ! flag( item ).open;
				render( '[data-index="' + index + '"] [data-iftp-fb-action="toggle"]' );

				return;
			}

			if ( action === 'remove' ) {
				items.splice( index, 1 );
				render( '[data-index="' + Math.min( index, items.length - 1 ) + '"] [data-iftp-fb-action="up"]' );
				save();

				return;
			}

			const to = action === 'up' ? index - 1 : index + 1;

			if ( to < 0 || to >= items.length ) {
				return;
			}

			items.splice( to, 0, items.splice( index, 1 )[ 0 ] );
			render( '[data-index="' + to + '"] [data-iftp-fb-action="' + action + '"]' );
			save();
		} );

		const update = function ( event ) {
			const key = event.target.dataset.iftpFbKey;
			const holder = event.target.closest( '[data-index]' );

			if ( ! key || ! holder ) {
				return;
			}

			const item = items[ parseInt( holder.dataset.index, 10 ) ];
			const state = flag( item );
			const target = event.target;
			const before = item.id;

			if ( key === 'when-field' || key === 'when-op' || key === 'when-value' ) {
				if ( event.type !== 'change' ) {
					return;
				}

				if ( key === 'when-field' ) {
					const picked = items.filter( function ( other ) {
						return other.id === target.value;
					} )[ 0 ];

					item.when = picked ? { field: target.value, op: opsFor( picked.type )[ 0 ], values: [] } : noCondition();
				} else if ( key === 'when-op' ) {
					item.when.op = target.value;

					if ( fbValueless.indexOf( target.value ) !== -1 ) {
						item.when.values = [];
					}
				} else {
					item.when.values = Array.prototype.map.call( holder.querySelectorAll( '[data-iftp-fb-key="when-value"]:checked' ), function ( tick ) {
						return tick.value;
					} );
				}

				save();
				render( '[data-index="' + holder.dataset.index + '"] [data-iftp-fb-key="' + key + '"]' + ( key === 'when-field' ? '' : '[value="' + CSS.escape( target.value ) + '"]' ) );

				return;
			}

			if ( key === 'when-num' ) {
				const numeric = ( items.filter( function ( other ) {
					return other.id === item.when.field;
				} )[ 0 ] || {} ).type === 'number';

				item.when.values = target.value.trim() !== '' ? [ numeric ? target.value.trim().replace( ',', '.' ) : target.value.trim() ] : [];
				save();

				return;
			}

			if ( key.indexOf( 'rule-' ) === 0 ) {
				const name = key.slice( 5 );

				item.rules = item.rules || noRules();
				item.rules[ name ] = name === 'whole' ? target.checked : target.value.trim().replace( ',', '.' );
				save();

				return;
			}

			if ( key === 'required' ) {
				item.required = target.checked;
			} else if ( key === 'pictures' ) {
				item.pictures = target.checked;

				const body = holder.querySelector( '.iftp-btn-fb__pics-body' );

				if ( body ) {
					body.hidden = ! target.checked;
				}
			} else if ( key === 'picture_shape' || key === 'picture_layout' || key === 'picture_size' ) {
				item[ key ] = target.value;
			} else if ( key === 'picture_cols' ) {
				item.picture_cols = parseInt( target.value, 10 ) || 0;
			} else if ( key === 'width' ) {
				item.width = target.value;
			} else if ( key === 'options' ) {
				// Prices and spots live in the Spots card; they stay with an option while it keeps its text.
				const kept = { prices: item.prices || {}, limits: item.limits || {}, images: item.images || {} };

				item.options = target.value.split( '\n' ).map( function ( line ) {
					return line.trim();
				} ).filter( function ( line, index, all ) {
					return line && all.indexOf( line ) === index;
				} );
				item.prices = {};
				item.limits = {};
				item.images = {};

				item.options.forEach( function ( option ) {
					[ 'prices', 'limits', 'images' ].forEach( function ( map ) {
						if ( kept[ map ][ option ] ) {
							item[ map ][ option ] = kept[ map ][ option ];
						}
					} );
				} );
			} else if ( key === 'id' ) {
				state.fresh = false;
				item.id = slug( target.value ) || item.id;

				if ( event.type === 'change' ) {
					item.id = uniqueId( item.id, item );
					target.value = item.id;
				}
			} else if ( key === 'type' ) {
				item.type = target.value;

				if ( fbChoices.indexOf( item.type ) !== -1 && ! ( item.options || [] ).length ) {
					item.options = [ fb.option + ' 1', fb.option + ' 2' ];
				}

				render( '[data-index="' + holder.dataset.index + '"] [data-iftp-fb-key="type"]' );
			} else {
				item[ key ] = target.value;
			}

			// Only while typing: the blur's change event would redraw the rows under a click on one of them.
			if ( key === 'options' && event.type === 'input' ) {
				const block = holder.querySelector( '[data-iftp-fb-pics]' );

				if ( block ) {
					fillPictures( block, item );
				}
			}

			// A new field's tag follows its label until someone names the tag.
			if ( key === 'label' && state.fresh && item.type !== 'page' && item.type !== 'content' ) {
				item.id = uniqueId( slug( item.label ) || 'field', item );

				const idInput = holder.querySelector( '[data-iftp-fb-key="id"]' );

				if ( idInput ) {
					idInput.value = item.id;
				}
			}

			// Conditions follow a field when its tag changes.
			if ( item.id !== before ) {
				items.forEach( function ( other ) {
					if ( other.when && other.when.field === before ) {
						other.when.field = item.id;
					}
				} );
			}

			const label = holder.querySelector( '.iftp-btn-fb__label' );
			const metaLine = holder.querySelector( '.iftp-btn-fb__name .iftp-btn-fb__meta' );

			if ( label ) {
				label.textContent = item.label || types[ item.type ];
			}

			if ( metaLine && ! isAnchor( item ) ) {
				metaLine.textContent = meta( item );
			}

			save();

		};

		list.addEventListener( 'input', update );
		list.addEventListener( 'change', update );

		// The Spots card makes its field required from outside.
		form.iftpFormRefresh = function () {
			render();
			save();
		};

		render();
		save();
	}

	/* ---------------------------------------------------------------- Spots */

	function bindSpots( form ) {
		const root = form.querySelector( '[data-iftp-spots]' );

		if ( ! root ) {
			return;
		}

		const select = root.querySelector( '[data-iftp-spots-field]' );
		const rows = root.querySelector( '[data-iftp-spots-rows]' );
		const grid = root.querySelector( '[data-iftp-spots-grid]' );
		const empty = root.querySelector( '[data-iftp-spots-empty]' );
		const sum = root.querySelector( '[data-iftp-spots-sum]' );
		const total = form.querySelector( '#iftp-btn-spots-total' );
		const amount = form.querySelector( '#iftp-btn-amount' );
		const sp = i18n.spots || {};
		const builder = form.querySelector( '[data-iftp-fb]' );
		let typeNames = {};

		try {
			typeNames = JSON.parse( ( builder && builder.dataset.types ) || '{}' );
		} catch ( e ) {}

		// Each option's numbers by its label, so they survive the options being reordered or the rows redrawn.
		const values = {};
		let wanted = select.dataset.saved || '';

		try {
			JSON.parse( root.dataset.saved || '[]' ).forEach( function ( option ) {
				values[ option.label ] = { spots: option.spots ? String( option.spots ) : '', price: option.price ? Number( option.price ).toFixed( 2 ) : '' };
			} );
		} catch ( e ) {}

		const spotsMode = function () {
			const checked = form.querySelector( 'input[name="config[spots][mode]"]:checked' );

			return checked ? checked.value : 'none';
		};

		const fixed = function () {
			const checked = form.querySelector( 'input[name="config[amount_mode]"]:checked' );

			return ! checked || checked.value === 'fixed';
		};

		const choiceFields = function () {
			return ( form.iftpFormState || [] ).filter( function ( item ) {
				return [ 'select', 'radio', 'checkboxes' ].indexOf( item.type ) !== -1 && ( item.options || [] ).length;
			} );
		};

		const current = function () {
			return choiceFields().filter( function ( item ) {
				return item.id === select.value;
			} )[ 0 ];
		};

		form.iftpSpotsField = function () {
			return spotsMode() === 'options' && current() ? select.value : '';
		};

		// The unit price of every option, for "from …" on the button preview.
		form.iftpSpotPrices = function () {
			if ( spotsMode() !== 'options' || ! current() ) {
				return [];
			}

			return current().options.map( function ( option ) {
				const own = parseFloat( ( values[ option ] || {} ).price );

				return own > 0 ? own : parseFloat( amount ? amount.value : '0' ) || 0;
			} );
		};

		const describe = function () {
			const options = current() ? current().options : [];
			const counts = options.map( function ( option ) {
				return parseInt( ( values[ option ] || {} ).spots, 10 ) || 0;
			} );
			const open = counts.indexOf( 0 ) !== -1;
			const added = counts.reduce( function ( a, b ) {
				return a + b;
			}, 0 );

			if ( ! options.length ) {
				sum.textContent = '';
			} else if ( ! open ) {
				sum.textContent = ( sp.sum || '%d' ).replace( '%d', added );
			} else {
				sum.textContent = total && parseInt( total.value, 10 ) > 0 ? sp.shareTotal : sp.noLimit;
			}
		};

		const drawRows = function () {
			const item = current();
			const priced = fixed();

			rows.textContent = '';
			root.querySelectorAll( '[data-iftp-spots-price]' ).forEach( function ( cell ) {
				cell.hidden = ! priced;
			} );

			( item ? item.options : [] ).forEach( function ( option, index ) {
				const saved = values[ option ] || { spots: '', price: '' };
				const row = el( 'div', 'iftp-btn-spots__row' );
				const name = el( 'span', 'iftp-btn-spots__option', option );
				const label = el( 'input' );
				const base = 'config[spots][options][' + index + ']';

				label.type = 'hidden';
				label.name = base + '[label]';
				label.value = option;
				name.appendChild( label );
				row.appendChild( name );

				[ [ 'spots', sp.unlimited, '1', '0' ], [ 'price', sp.samePrice, '0.01', '0' ] ].forEach( function ( spec ) {
					const cell = el( 'span' );
					const input = el( 'input', 'iftp-btn-field__control iftp-btn-field__control--short' );

					input.type = 'number';
					input.name = base + '[' + spec[ 0 ] + ']';
					input.value = saved[ spec[ 0 ] ];
					input.placeholder = spec[ 1 ];
					input.step = spec[ 2 ];
					input.min = spec[ 3 ];
					input.inputMode = spec[ 0 ] === 'price' ? 'decimal' : 'numeric';
					input.dataset.iftpSpotsKey = spec[ 0 ];
					input.dataset.iftpSpotsOption = option;
					input.setAttribute( 'aria-label', option + ' · ' + ( spec[ 0 ] === 'price' ? sp.price : sp.spots ) );

					if ( spec[ 0 ] === 'price' ) {
						cell.dataset.iftpSpotsPrice = '';
						cell.hidden = ! priced;
					}

					cell.appendChild( input );
					row.appendChild( cell );
				} );

				rows.appendChild( row );
			} );

			describe();
		};

		// A half-set-up card mustn't save as if it were fine; the browser shows these on the field, on its tab.
		const validate = function () {
			const mode = spotsMode();

			select.required = mode === 'options';
			select.setCustomValidity( mode === 'options' && ! current() ? sp.needField || '' : '' );

			if ( total ) {
				total.setCustomValidity( mode === 'total' && ! ( parseInt( total.value, 10 ) > 0 ) ? sp.needTotal || '' : '' );
			}
		};

		/* The other fields with their own spots and prices, one block each. A "+" adds another. */
		const extraWrap = form.querySelector( '[data-iftp-spots-extras]' );
		const addExtra = form.querySelector( '[data-iftp-spots-add]' );
		let extraIds = null;
		let drawnExtras = '';

		const fieldById = function ( id ) {
			return ( form.iftpFormState || [] ).filter( function ( item ) {
				return item.id === id;
			} )[ 0 ];
		};

		const hasValues = function ( item ) {
			return Object.keys( item.prices || {} ).length > 0 || Object.keys( item.limits || {} ).length > 0;
		};

		// The choice fields that can take a block: every one but the main Spots field's.
		const eligible = function () {
			return choiceFields().filter( function ( item ) {
				return ! ( spotsMode() === 'options' && item.id === select.value );
			} );
		};

		const drawExtras = function ( force ) {
			if ( ! extraWrap ) {
				return;
			}

			const fields = eligible();
			const priced = fixed();

			// A saved button starts with the fields that already have numbers; the "+" adds blank ones after.
			if ( extraIds === null ) {
				extraIds = fields.filter( hasValues ).map( function ( item ) {
					return item.id;
				} );
			}

			extraIds = extraIds.filter( function ( id ) {
				return id === '' || fields.some( function ( item ) {
					return item.id === id;
				} );
			} );

			const signature = JSON.stringify( [ extraIds, priced, extraIds.map( function ( id ) {
				return ( fieldById( id ) || {} ).options;
			} ), fields.map( function ( item ) {
				return item.id + ( item.label || '' );
			} ) ] );

			if ( ! force && signature === drawnExtras ) {
				return;
			}

			drawnExtras = signature;
			extraWrap.textContent = '';
			addExtra.hidden = ! fields.length || extraIds.length >= fields.length;

			extraIds.forEach( function ( id, index ) {
				const item = fieldById( id );
				const block = el( 'div', 'iftp-btn-spots__extra' );
				const head = el( 'div', 'iftp-btn-spots__extra-head' );
				const pick = el( 'select', 'iftp-btn-field__control' );
				const remove = el( 'button', 'iftp-btn-tool iftp-btn-tool--danger' );

				pick.dataset.iftpExtraPick = String( index );
				pick.setAttribute( 'aria-label', sp.pickField );
				pick.appendChild( Object.assign( el( 'option', '', sp.pickField ), { value: '' } ) );

				fields.filter( function ( candidate ) {
					return candidate.id === id || extraIds.indexOf( candidate.id ) === -1;
				} ).forEach( function ( candidate ) {
					const name = candidate.label || candidate.id;
					const type = typeNames[ candidate.type ] || candidate.type;

					pick.appendChild( Object.assign( el( 'option', '', name === type ? name : name + ' · ' + type ), { value: candidate.id } ) );
				} );

				pick.value = id;

				remove.type = 'button';
				remove.dataset.iftpExtraRemove = String( index );
				remove.setAttribute( 'aria-label', sp.removeField );
				remove.dataset.iftpTip = sp.removeField;
				remove.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';

				head.appendChild( pick );
				head.appendChild( remove );
				block.appendChild( head );

				if ( item ) {
					const table = el( 'div', 'iftp-btn-spots__grid' );
					const top = el( 'div', 'iftp-btn-spots__row iftp-btn-spots__row--head' );

					top.setAttribute( 'aria-hidden', 'true' );
					[ sp.option, sp.spots ].concat( priced ? [ sp.priceEur ] : [] ).forEach( function ( text ) {
						top.appendChild( el( 'span', '', text ) );
					} );
					table.appendChild( top );

					item.options.forEach( function ( option ) {
						const row = el( 'div', 'iftp-btn-spots__row' );

						row.appendChild( el( 'span', 'iftp-btn-spots__option', option ) );

						[ [ 'limits', sp.unlimited, '1', true ], [ 'prices', sp.noPrice, '0.01', priced ] ].forEach( function ( spec ) {
							if ( ! spec[ 3 ] ) {
								return;
							}

							const cell = el( 'span' );
							const input = el( 'input', 'iftp-btn-field__control iftp-btn-field__control--short' );
							const saved = ( item[ spec[ 0 ] ] || {} )[ option ];

							input.type = 'number';
							input.min = '0';
							input.step = spec[ 2 ];
							input.inputMode = spec[ 0 ] === 'prices' ? 'decimal' : 'numeric';
							input.value = saved ? String( saved ) : '';
							input.placeholder = spec[ 1 ];
							input.dataset.iftpExtraKey = spec[ 0 ];
							input.dataset.iftpExtraField = id;
							input.dataset.iftpExtraOption = option;
							input.setAttribute( 'aria-label', option + ' · ' + ( spec[ 0 ] === 'prices' ? sp.price : sp.spots ) );
							cell.appendChild( input );
							row.appendChild( cell );
						} );

						table.appendChild( row );
					} );

					block.appendChild( table );
				}

				extraWrap.appendChild( block );
			} );
		};

		if ( extraWrap ) {
			addExtra.addEventListener( 'click', function () {
				extraIds = ( extraIds || [] ).concat( [ '' ] );
				drawExtras( true );

				const last = extraWrap.querySelector( '.iftp-btn-spots__extra:last-child select' );

				if ( last ) {
					last.focus();
				}
			} );

			extraWrap.addEventListener( 'change', function ( event ) {
				const target = event.target;

				if ( target.dataset.iftpExtraPick !== undefined ) {
					extraIds[ parseInt( target.dataset.iftpExtraPick, 10 ) ] = target.value;
					drawExtras( true );

					return;
				}

				const item = fieldById( target.dataset.iftpExtraField );

				if ( ! item || ! target.dataset.iftpExtraKey ) {
					return;
				}

				const key = target.dataset.iftpExtraKey;
				const number = key === 'prices' ? parseFloat( target.value.replace( ',', '.' ) ) : parseInt( target.value, 10 );

				item[ key ] = item[ key ] || {};

				if ( number > 0 ) {
					item[ key ][ target.dataset.iftpExtraOption ] = number;
				} else {
					delete item[ key ][ target.dataset.iftpExtraOption ];
				}

				// The builder keeps these numbers, so it saves and shows them in the options box too.
				if ( form.iftpFormRefresh ) {
					form.iftpFormRefresh();
				}
			} );

			extraWrap.addEventListener( 'click', function ( event ) {
				const remove = event.target.closest( '[data-iftp-extra-remove]' );

				if ( ! remove ) {
					return;
				}

				const index = parseInt( remove.dataset.iftpExtraRemove, 10 );
				const item = fieldById( extraIds[ index ] );

				if ( item ) {
					item.prices = {};
					item.limits = {};
				}

				extraIds.splice( index, 1 );
				drawExtras( true );

				if ( item && form.iftpFormRefresh ) {
					form.iftpFormRefresh();
				}
			} );
		}

		const refresh = function () {
			const fields = choiceFields();
			const keep = select.value || wanted;

			select.textContent = '';

			if ( ! fields.length ) {
				const none = el( 'option', '', sp.noField );

				none.value = '';
				select.appendChild( none );
			}

			fields.forEach( function ( item ) {
				const name = item.label || item.id;
				const type = typeNames[ item.type ] || item.type;
				const option = el( 'option', '', name === type ? name : name + ' · ' + type );

				option.value = item.id;
				select.appendChild( option );
			} );

			if ( fields.some( function ( item ) {
				return item.id === keep;
			} ) ) {
				select.value = keep;
			}

			wanted = select.value || wanted;

			grid.hidden = ! fields.length;
			empty.hidden = !! fields.length;
			drawRows();
			drawExtras( false );
			validate();

			// The picked field turns required in the builder too, so the editor shows what the payer will get.
			const item = current();

			if ( spotsMode() === 'options' && item && ! item.required && form.iftpFormRefresh ) {
				item.required = true;
				form.iftpFormRefresh();
			}
		};

		rows.addEventListener( 'input', function ( event ) {
			const key = event.target.dataset.iftpSpotsKey;
			const option = event.target.dataset.iftpSpotsOption;

			if ( key ) {
				values[ option ] = values[ option ] || { spots: '', price: '' };
				values[ option ][ key ] = event.target.value;
				describe();
			}
		} );

		select.addEventListener( 'change', function () {
			wanted = select.value;
			refresh();
		} );

		// The form builder fires change on its JSON input whenever a field or its options change.
		form.querySelector( '[data-iftp-fb-input]' ).addEventListener( 'change', refresh );

		form.querySelectorAll( 'input[name="config[spots][mode]"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				refresh();

				// Leaving "Spots per option" gives the field back its own tick.
				if ( form.iftpFormRefresh ) {
					form.iftpFormRefresh();
				}
			} );
		} );

		form.querySelectorAll( 'input[name="config[amount_mode]"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				drawRows();
				drawExtras( false );
			} );
		} );

		if ( total ) {
			total.addEventListener( 'input', function () {
				describe();
				validate();
			} );
		}

		refresh();
	}
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
