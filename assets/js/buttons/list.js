/**
 * Buttons list: the cards' search, filter, sort, view and counts, and the folds.
 */
( function ( ns ) {
	'use strict';

	const { i18n, post, toast } = ns;

	ns.bindReveal = bindReveal;

	/* ---------------------------------------------------------------- List */

	const cards = Array.prototype.slice.call( document.querySelectorAll( '[data-iftp-bcard]' ) );
	const grid = document.querySelector( '[data-iftp-bcards]' );
	const toolbar = document.querySelector( '[data-iftp-btoolbar]' );
	const search = document.querySelector( '[data-iftp-bsearch]' );
	const typeSelect = document.querySelector( '[data-iftp-btype]' );
	const sortSelect = document.querySelector( '[data-iftp-bsort]' );
	const listKey = 'iftpBtnList';
	// Select mode's parts sit up here because the first applyFilter() call already syncs the bar.
	const batchToggle = document.querySelector( '[data-iftp-bbatch-toggle]' );
	const batchBar = document.querySelector( '[data-iftp-bbatch]' );
	const batchDialog = document.querySelector( '[data-iftp-bbatch-dialog]' );
	let batching = false;

	const perPage = 19; // plus the new-button tile makes 20 cards a page.
	let filter = 'all';
	let page = 1;

	// The sort and the layout are only this browser's preference, so storage failing just means the defaults.
	function remembered() {
		try {
			return JSON.parse( window.localStorage.getItem( listKey ) ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function remember( key, value ) {
		try {
			const saved = remembered();
			saved[ key ] = value;
			window.localStorage.setItem( listKey, JSON.stringify( saved ) );
		} catch ( e ) {}
	}

	function applyFilter( keepPage ) {
		const term = search ? search.value.trim().toLowerCase().normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ) : '';
		const type = typeSelect ? typeSelect.value : '';
		const matches = [];

		// The DOM order is the sorted order, so the pages follow it.
		Array.prototype.forEach.call( grid.querySelectorAll( '[data-iftp-bcard]' ), function ( card ) {
			const on = card.dataset.active === '1';
			const status = filter === 'all' || ( filter === 'sold' ? card.dataset.sold === '1' : ( filter === 'on' ) === on );
			const visible = status && ( ! type || card.dataset.mode === type ) && ( ! term || card.dataset.title.indexOf( term ) !== -1 );

			card.hidden = true;

			if ( visible ) {
				matches.push( card );
			}
		} );

		const pages = Math.max( 1, Math.ceil( matches.length / perPage ) );

		page = keepPage === true ? Math.min( page, pages ) : 1;
		matches.slice( ( page - 1 ) * perPage, page * perPage ).forEach( function ( card ) {
			card.hidden = false;
		} );

		const none = document.querySelector( '[data-iftp-bnone]' );
		if ( none ) {
			none.hidden = matches.length > 0;
		}

		// The new-button tile only belongs to the unfiltered list.
		document.querySelectorAll( '[data-iftp-bcard-new]' ).forEach( function ( tile ) {
			tile.hidden = filter !== 'all' || !! term || !! type;
		} );

		syncBatch();

		const pager = document.querySelector( '[data-iftp-bpager]' );
		if ( pager ) {
			const status = pager.querySelector( '[data-iftp-bpager-status]' );

			pager.hidden = pages < 2;
			status.textContent = status.dataset.template.replace( '%1$s', page ).replace( '%2$s', pages );
			pager.querySelector( '[data-iftp-bpage="prev"]' ).disabled = page <= 1;
			pager.querySelector( '[data-iftp-bpage="next"]' ).disabled = page >= pages;
		}
	}

	function applySort( key ) {
		const first = grid.querySelector( '[data-iftp-bcard-new]' );
		const by = {
			collected: function ( a, b ) {
				return parseFloat( b.dataset.collected ) - parseFloat( a.dataset.collected );
			},
			paid: function ( a, b ) {
				return parseInt( b.dataset.paid, 10 ) - parseInt( a.dataset.paid, 10 );
			},
			newest: function ( a, b ) {
				return parseInt( a.dataset.order, 10 ) - parseInt( b.dataset.order, 10 );
			},
			edited: function ( a, b ) {
				return parseInt( b.dataset.edited, 10 ) - parseInt( a.dataset.edited, 10 );
			},
			oldest: function ( a, b ) {
				return parseInt( b.dataset.order, 10 ) - parseInt( a.dataset.order, 10 );
			},
			title: function ( a, b ) {
				return a.dataset.title.localeCompare( b.dataset.title );
			},
		};

		if ( ! by[ key ] ) {
			return;
		}

		// Ties keep their date order, newest first.
		cards.slice().sort( function ( a, b ) {
			return by[ key ]( a, b ) || parseInt( a.dataset.order, 10 ) - parseInt( b.dataset.order, 10 );
		} ).forEach( function ( card ) {
			grid.insertBefore( card, first );
		} );
	}

	function applyView( view ) {
		grid.classList.toggle( 'iftp-btn-bcards--list', view === 'list' );

		toolbar.querySelectorAll( '[data-iftp-bview]' ).forEach( function ( option ) {
			option.setAttribute( 'aria-pressed', option.dataset.iftpBview === view ? 'true' : 'false' );
		} );
	}

	function updateCounts() {
		const on = cards.filter( function ( card ) {
			return card.dataset.active === '1';
		} ).length;
		const counts = { all: cards.length, on: on, off: cards.length - on };

		Object.keys( counts ).forEach( function ( key ) {
			const badge = document.querySelector( '[data-iftp-bfilter-count="' + key + '"]' );

			if ( badge ) {
				badge.textContent = counts[ key ].toLocaleString();
			}
		} );
	}

	if ( toolbar && cards.length ) {
		const saved = remembered();

		toolbar.hidden = false;

		search.addEventListener( 'input', function () {
			applyFilter();
		} );
		typeSelect.addEventListener( 'change', function () {
			applyFilter();
		} );

		toolbar.parentNode.querySelectorAll( '[data-iftp-bpage]' ).forEach( function ( step ) {
			step.addEventListener( 'click', function () {
				page += step.dataset.iftpBpage === 'next' ? 1 : -1;
				applyFilter( true );
				grid.scrollIntoView( { block: 'start', behavior: 'smooth' } );
			} );
		} );

		sortSelect.addEventListener( 'change', function () {
			applySort( sortSelect.value );
			applyFilter();
			remember( 'sort', sortSelect.value );
		} );

		toolbar.querySelectorAll( '[data-iftp-bfilter]' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				filter = option.dataset.iftpBfilter;

				toolbar.querySelectorAll( '[data-iftp-bfilter]' ).forEach( function ( other ) {
					other.setAttribute( 'aria-pressed', other === option ? 'true' : 'false' );
				} );

				applyFilter();
			} );
		} );

		toolbar.querySelectorAll( '[data-iftp-bview]' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				applyView( option.dataset.iftpBview );
				remember( 'view', option.dataset.iftpBview );
			} );
		} );

		// The page arrives sorted by Newest in the grid; a remembered choice replaces either.
		if ( saved.sort && saved.sort !== sortSelect.value && sortSelect.querySelector( 'option[value="' + saved.sort + '"]' ) ) {
			sortSelect.value = saved.sort;
			applySort( saved.sort );

			if ( sortSelect.iftpDropdown ) {
				sortSelect.iftpDropdown.sync();
			}
		}

		applyFilter();

		if ( saved.view === 'list' ) {
			applyView( 'list' );
		}
	}

	/* ---------------------------------------------------------------- Batch */

	function picked() {
		return cards.filter( function ( card ) {
			return card.classList.contains( 'is-selected' );
		} );
	}

	function setPicked( card, on ) {
		card.classList.toggle( 'is-selected', on );
		card.setAttribute( 'aria-checked', on ? 'true' : 'false' );
		syncBatch();
	}

	// A card the filter hides can't stay picked, or Apply would reach buttons nobody can see.
	function syncBatch() {
		if ( ! batchBar ) {
			return;
		}

		cards.forEach( function ( card ) {
			if ( card.hidden && card.classList.contains( 'is-selected' ) ) {
				card.classList.remove( 'is-selected' );
				card.setAttribute( 'aria-checked', 'false' );
			}
		} );

		const count = picked().length;
		const words = i18n.batchCount || {};
		const label = batchBar.querySelector( '[data-iftp-bbatch-count]' );

		label.textContent = count === 0 ? words.none : ( count === 1 ? words.one : words.other.replace( '%s', count ) );
		batchBar.querySelector( '[data-iftp-bbatch-apply]' ).disabled = count === 0 || ! batchBar.querySelector( '[data-iftp-bbatch-op]' ).value;
	}

	function setBatching( on ) {
		batching = on;
		grid.classList.toggle( 'iftp-btn-bcards--batch', on );
		batchBar.hidden = ! on;
		batchToggle.setAttribute( 'aria-pressed', on ? 'true' : 'false' );

		cards.forEach( function ( card ) {
			if ( on ) {
				card.setAttribute( 'role', 'checkbox' );
				card.setAttribute( 'aria-checked', 'false' );
				card.tabIndex = 0;
			} else {
				card.classList.remove( 'is-selected' );
				card.removeAttribute( 'role' );
				card.removeAttribute( 'aria-checked' );
				card.removeAttribute( 'tabindex' );
			}
		} );

		syncBatch();
	}

	function applyBatch( op, ids ) {
		const apply = batchBar.querySelector( '[data-iftp-bbatch-apply]' );

		apply.disabled = true;

		post( 'iftp_btn_batch_buttons', { op: op, 'ids[]': ids } )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( ( result && result.data && result.data.message ) || i18n.error );
				}

				ns.leaving = true;
				window.location.href = result.data.url;
			} )
			.catch( function ( error ) {
				toast( error.message, true );
				syncBatch();
			} );
	}

	if ( batchToggle && batchBar && grid ) {
		const opSelect = batchBar.querySelector( '[data-iftp-bbatch-op]' );

		batchToggle.addEventListener( 'click', function () {
			setBatching( ! batching );
		} );
		batchBar.querySelector( '[data-iftp-bbatch-done]' ).addEventListener( 'click', function () {
			setBatching( false );
		} );
		batchBar.querySelector( '[data-iftp-bbatch-clear]' ).addEventListener( 'click', function () {
			cards.forEach( function ( card ) {
				setPicked( card, false );
			} );
		} );
		batchBar.querySelector( '[data-iftp-bbatch-all]' ).addEventListener( 'click', function () {
			cards.forEach( function ( card ) {
				setPicked( card, ! card.hidden );
			} );
		} );
		opSelect.addEventListener( 'change', syncBatch );

		batchBar.querySelector( '[data-iftp-bbatch-apply]' ).addEventListener( 'click', function () {
			const ids = picked().map( function ( card ) {
				return card.dataset.id;
			} );

			if ( ! ids.length || ! opSelect.value ) {
				toast( i18n.batchNone, true );

				return;
			}

			if ( opSelect.value === 'delete' && batchDialog && typeof batchDialog.showModal === 'function' ) {
				batchDialog.querySelector( '[data-iftp-bbatch-title]' ).textContent = ( i18n.batchDelete || '%s' ).replace( '%s', ids.length );
				batchDialog.showModal();

				return;
			}

			applyBatch( opSelect.value, ids );
		} );

		if ( batchDialog ) {
			batchDialog.querySelector( '[data-iftp-bbatch-cancel]' ).addEventListener( 'click', function () {
				batchDialog.close();
			} );
			batchDialog.querySelector( '[data-iftp-bbatch-confirm]' ).addEventListener( 'click', function () {
				batchDialog.close();
				applyBatch( 'delete', picked().map( function ( card ) {
					return card.dataset.id;
				} ) );
			} );
			batchDialog.addEventListener( 'click', function ( event ) {
				if ( event.target === batchDialog ) {
					batchDialog.close();
				}
			} );
		}

		// In select mode a click anywhere on a card picks it, so its links and switch don't fire.
		grid.addEventListener(
			'click',
			function ( event ) {
				const card = batching ? event.target.closest( '[data-iftp-bcard]' ) : null;

				if ( ! card ) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();
				setPicked( card, ! card.classList.contains( 'is-selected' ) );
			},
			true
		);

		grid.addEventListener( 'keydown', function ( event ) {
			if ( batching && event.target.matches( '[data-iftp-bcard]' ) && ( event.key === ' ' || event.key === 'Enter' ) ) {
				event.preventDefault();
				setPicked( event.target, ! event.target.classList.contains( 'is-selected' ) );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( batching && event.key === 'Escape' && ! ( batchDialog && batchDialog.open ) ) {
				setBatching( false );
			}
		} );
	}

	// The preview ignores the pointer, so pointing at an active card plays its button's hover effect.
	function previewHover( card, on ) {
		const button = card.querySelector( '.iftp-btn-bcard__stage .iftp-btn-pay__button' );

		if ( button ) {
			button.classList.toggle( 'is-hover', on && ! card.classList.contains( 'iftp-btn-bcard--off' ) );
		}
	}

	cards.forEach( function ( card ) {
		card.addEventListener( 'mouseenter', function () {
			previewHover( card, true );
		} );
		card.addEventListener( 'mouseleave', function () {
			previewHover( card, false );
		} );
		card.addEventListener( 'focusin', function () {
			previewHover( card, true );
		} );
		card.addEventListener( 'focusout', function () {
			previewHover( card, false );
		} );
	} );

	document.querySelectorAll( '[data-iftp-toggle-button]' ).forEach( function ( toggle ) {
		toggle.addEventListener( 'change', function () {
			const card = toggle.closest( '[data-iftp-bcard]' );
			const wanted = toggle.checked;

			toggle.disabled = true;

			post( 'iftp_btn_toggle_button', { button: toggle.dataset.iftpToggleButton, active: wanted ? '1' : '0' } )
				.then( function ( result ) {
					if ( ! result || ! result.success ) {
						throw new Error( ( result && result.data && result.data.message ) || i18n.error );
					}

					card.classList.toggle( 'iftp-btn-bcard--off', ! wanted );
					card.dataset.active = wanted ? '1' : '0';

					const status = card.querySelector( '.iftp-btn-bcard__status' );

					if ( status ) {
						status.textContent = wanted ? i18n.active : i18n.inactive;
					}

					updateCounts();
					applyFilter();
					toast( wanted ? i18n.turnedOn : i18n.turnedOff, false, wanted ? '' : 'muted' );
				} )
				.catch( function ( error ) {
					toggle.checked = ! wanted;
					toast( error.message, true );
				} )
				.finally( function () {
					toggle.disabled = false;
				} );
		} );
	} );

	// Activate on an inactive card ticks its checkbox, so it goes through the same save as the toggle.
	document.querySelectorAll( '[data-iftp-activate-button]' ).forEach( function ( activate ) {
		activate.addEventListener( 'click', function () {
			const toggle = activate.closest( '[data-iftp-bcard]' ).querySelector( '[data-iftp-toggle-button]' );

			if ( toggle && ! toggle.checked && ! toggle.disabled ) {
				toggle.checked = true;
				toggle.dispatchEvent( new Event( 'change' ) );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Show/hide by a choice */

	function bindReveal( scope, inputSelector, attribute ) {
		const inputs = scope.querySelectorAll( inputSelector );

		if ( ! inputs.length ) {
			return;
		}

		const current = function () {
			let value = '';

			inputs.forEach( function ( input ) {
				if ( input.tagName === 'SELECT' || input.checked ) {
					value = input.value;
				}
			} );

			return value;
		};

		const apply = function () {
			const value = current();

			scope.querySelectorAll( '[' + attribute + ']' ).forEach( function ( target ) {
				target.hidden = target.getAttribute( attribute ).split( ' ' ).indexOf( value ) === -1;
			} );
		};

		inputs.forEach( function ( input ) {
			input.addEventListener( 'change', apply );
		} );

		apply();
	}

	/* ---------------------------------------------------------------- Folds */

	// Which folds are open, per fold, so a save or a reload comes back the same way.
	const foldKey = 'iftpBtnFolds';
	let folds = {};

	try {
		folds = JSON.parse( window.localStorage.getItem( foldKey ) || '{}' ) || {};
	} catch ( e ) {
		folds = {};
	}

	document.querySelectorAll( 'details[data-iftp-fold]' ).forEach( function ( fold ) {
		const id = fold.dataset.iftpFold;

		if ( Object.prototype.hasOwnProperty.call( folds, id ) ) {
			fold.open = !! folds[ id ];
		}

		fold.addEventListener( 'toggle', function () {
			folds[ id ] = fold.open;

			try {
				window.localStorage.setItem( foldKey, JSON.stringify( folds ) );
			} catch ( e ) {}
		} );
	} );

	// A field in a closed fold can't show its message, so I open the fold first.
	document.addEventListener(
		'invalid',
		function ( event ) {
			let fold = event.target.closest( 'details[data-iftp-fold]' );

			while ( fold ) {
				fold.open = true;
				fold = fold.parentElement.closest( 'details[data-iftp-fold]' );
			}
		},
		true
	);

	/* ---------------------------------------------------------------- Show all tiles */

	// The "Show all" under a tile list flips it between its first few tiles and every one.
	document.addEventListener( 'click', function ( event ) {
		const toggle = event.target.closest( '[data-iftp-more]' );

		if ( ! toggle ) {
			return;
		}

		const list = toggle.parentElement.querySelector( '[data-iftp-more-list]' );

		if ( ! list ) {
			return;
		}

		const folded = list.classList.toggle( 'is-folded' );

		toggle.setAttribute( 'aria-expanded', folded ? 'false' : 'true' );
		toggle.querySelector( '[data-iftp-more-closed]' ).hidden = ! folded;
		toggle.querySelector( '[data-iftp-more-open]' ).hidden = folded;
	} );
} )( ( window.iftpBtnButtonsParts = window.iftpBtnButtonsParts || {} ) );
