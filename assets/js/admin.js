/**
 * Handle copy and delete interactions on the recovery admin screen.
 */
( function () {
	'use strict';

	/**
	 * Return a localized admin label with fallback.
	 *
	 * @param {string} key          Translation key.
	 * @param {string} defaultValue Fallback value.
	 * @return {string}
	 */
	function getLabel( key, defaultValue ) {
		if ( 'undefined' === typeof WCCRAdminI18n || ! WCCRAdminI18n[ key ] ) {
			return defaultValue;
		}

		return WCCRAdminI18n[ key ];
	}

	/**
	 * Temporarily replace button text after an action.
	 *
	 * @param {HTMLButtonElement} button       Action button.
	 * @param {string}            temporary    Temporary label.
	 * @param {string}            defaultLabel Default label.
	 * @return {void}
	 */
	function flashButtonText( button, temporary, defaultLabel ) {
		button.textContent = temporary;
		window.setTimeout( function () {
			button.textContent = defaultLabel;
		}, 1500 );
	}

	/**
	 * Copy a recovery URL to the clipboard using the best available API.
	 *
	 * @param {HTMLButtonElement} button Source button.
	 * @return {void}
	 */
	function copyRecoveryUrl( button ) {
		var url = button.getAttribute( 'data-url' );
		if ( ! url ) {
			return;
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( function () {
				flashButtonText( button, getLabel( 'copiedLabel', 'Copied' ), getLabel( 'copyLabel', 'Copy URL' ) );
			} );
			return;
		}

		var textarea = document.createElement( 'textarea' );
		textarea.value = url;
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
		flashButtonText( button, getLabel( 'copiedLabel', 'Copied' ), getLabel( 'copyLabel', 'Copy URL' ) );
	}

	/**
	 * Bind delegated click handling for copy buttons.
	 *
	 * @param {MouseEvent} event Browser click event.
	 * @return {void}
	 */
	function handleCopyClick( event ) {
		var button = event.target.closest( '.wccr-copy-url' );
		if ( ! button ) {
			return;
		}

		copyRecoveryUrl( button );
	}

	/**
	 * Ask for confirmation before deleting a recovery item.
	 *
	 * @param {SubmitEvent} event Browser submit event.
	 * @return {void}
	 */
	function handleDeleteSubmit( event ) {
		var form = event.target.closest( '.wccr-delete-form' );
		if ( ! form ) {
			return;
		}

		if ( ! window.confirm( getLabel( 'deleteConfirm', 'Delete this recovery item?' ) ) ) {
			event.preventDefault();
		}
	}

	/**
	 * Activate one locale tab and its matching panel.
	 *
	 * @param {HTMLElement} button Selected tab button.
	 * @return {void}
	 */
	function activateLocaleTab( button ) {
		var locale = button.getAttribute( 'data-locale-tab' );
		var container = button.closest( '.wccr-locale-tabs' );

		if ( ! locale || ! container ) {
			return;
		}

		container.querySelectorAll( '.wccr-locale-tabs__button' ).forEach( function ( item ) {
			var isActive = item === button;
			item.classList.toggle( 'is-active', isActive );
			item.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		container.querySelectorAll( '.wccr-locale-tabs__panel' ).forEach( function ( panel ) {
			var isActive = panel.getAttribute( 'data-locale-panel' ) === locale;
			panel.classList.toggle( 'is-active', isActive );
		} );
	}

	/**
	 * Handle delegated click events for locale tab buttons.
	 *
	 * @param {MouseEvent} event Browser click event.
	 * @return {void}
	 */
	function handleLocaleTabClick( event ) {
		var button = event.target.closest( '.wccr-locale-tabs__button' );
		if ( ! button ) {
			return;
		}

		activateLocaleTab( button );
	}

	/**
	 * Toggle email step visibility inside one recovery card.
	 *
	 * @param {MouseEvent} event Browser click event.
	 * @return {void}
	 */
	function handleEmailToggleClick( event ) {
		var button = event.target.closest( '.wccr-email-toggle' );
		var targetId;
		var panel;
		var isExpanded;

		if ( ! button ) {
			return;
		}

		targetId = button.getAttribute( 'data-target' );
		panel = targetId ? document.getElementById( targetId ) : null;
		if ( ! panel ) {
			return;
		}

		isExpanded = button.getAttribute( 'aria-expanded' ) === 'true';
		button.setAttribute( 'aria-expanded', isExpanded ? 'false' : 'true' );
		button.textContent = isExpanded ? getLabel( 'showEmailsLabel', 'View email details' ) : getLabel( 'hideEmailsLabel', 'Hide email details' );
		panel.hidden = isExpanded;
	}

	/**
	 * Build one selected exclusion chip.
	 *
	 * @param {string} inputName Hidden input name.
	 * @param {Object} item      Selected item.
	 * @return {HTMLElement}
	 */
	function buildExclusionChip( inputName, item ) {
		var chip = document.createElement( 'span' );
		var label = document.createElement( 'span' );
		var remove = document.createElement( 'button' );
		var input = document.createElement( 'input' );

		chip.className = 'wccr-exclusion-chip';
		chip.setAttribute( 'data-id', String( item.id ) );

		label.className = 'wccr-exclusion-chip__label';
		label.textContent = item.label;

		remove.type = 'button';
		remove.className = 'wccr-exclusion-chip__remove';
		remove.setAttribute( 'aria-label', 'Remove exclusion' );
		remove.textContent = '×';

		input.type = 'hidden';
		input.name = inputName;
		input.value = String( item.id );

		chip.appendChild( label );
		chip.appendChild( remove );
		chip.appendChild( input );

		return chip;
	}

	/**
	 * Hide results for one exclusion field.
	 *
	 * @param {HTMLElement} field Field wrapper.
	 * @return {void}
	 */
	function hideExclusionResults( field ) {
		var results = field.querySelector( '.wccr-exclusion-field__results' );
		if ( ! results ) {
			return;
		}

		results.innerHTML = '';
		results.hidden = true;
	}

	/**
	 * Render result buttons for one exclusion search.
	 *
	 * @param {HTMLElement} field   Field wrapper.
	 * @param {Array}       results Result items.
	 * @return {void}
	 */
	function renderExclusionResults( field, results ) {
		var resultsBox = field.querySelector( '.wccr-exclusion-field__results' );
		var selected = field.querySelector( '.wccr-exclusion-field__selected' );
		var inputName = field.getAttribute( 'data-input-name' );

		if ( ! resultsBox || ! selected || ! inputName ) {
			return;
		}

		resultsBox.innerHTML = '';

		if ( ! results.length ) {
			resultsBox.textContent = getLabel( 'noResultsLabel', 'No matches found.' );
			resultsBox.hidden = false;
			return;
		}

		results.forEach( function ( item ) {
			var button;

			if ( selected.querySelector( '[data-id="' + item.id + '"]' ) ) {
				return;
			}

			button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'wccr-exclusion-field__result';
			button.textContent = item.label;
			button.setAttribute( 'data-id', String( item.id ) );
			button.setAttribute( 'data-label', item.label );
			resultsBox.appendChild( button );
		} );

		resultsBox.hidden = ! resultsBox.childNodes.length;
	}

	/**
	 * Fetch autocomplete matches for one exclusion field.
	 *
	 * @param {HTMLInputElement} input Search input.
	 * @return {void}
	 */
	function fetchExclusionResults( input ) {
		var field = input.closest( '.wccr-exclusion-field' );
		var type = field ? field.getAttribute( 'data-type' ) : '';
		var action = 'products' === type ? 'wccr_search_excluded_products' : 'wccr_search_excluded_terms';
		var term = input.value.trim();
		var resultsBox = field ? field.querySelector( '.wccr-exclusion-field__results' ) : null;

		if ( ! field || ! resultsBox ) {
			return;
		}

		if ( term.length < 2 ) {
			hideExclusionResults( field );
			return;
		}

		resultsBox.hidden = false;
		resultsBox.textContent = getLabel( 'searchingLabel', 'Searching…' );

		window.fetch(
			WCCRAdminI18n.ajaxUrl + '?action=' + encodeURIComponent( action ) + '&nonce=' + encodeURIComponent( WCCRAdminI18n.exclusionNonce ) + '&term=' + encodeURIComponent( term ),
			{ credentials: 'same-origin' }
		)
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				renderExclusionResults( field, payload && payload.success && Array.isArray( payload.data ) ? payload.data : [] );
			} )
			.catch( function () {
				hideExclusionResults( field );
			} );
	}

	/**
	 * Handle search input for exclusion fields.
	 *
	 * @param {InputEvent} event Browser input event.
	 * @return {void}
	 */
	function handleExclusionSearchInput( event ) {
		var input = event.target.closest( '.wccr-exclusion-field__search' );
		if ( ! input ) {
			return;
		}

		window.clearTimeout( input._wccrTimer );
		input._wccrTimer = window.setTimeout( function () {
			fetchExclusionResults( input );
		}, 180 );
	}

	/**
	 * Handle clicks on exclusion search results and remove buttons.
	 *
	 * @param {MouseEvent} event Browser click event.
	 * @return {void}
	 */
	function handleExclusionFieldClick( event ) {
		var remove = event.target.closest( '.wccr-exclusion-chip__remove' );
		var result = event.target.closest( '.wccr-exclusion-field__result' );
		var field;
		var selected;
		var input;

		if ( remove ) {
			remove.closest( '.wccr-exclusion-chip' ).remove();
			return;
		}

		if ( ! result ) {
			return;
		}

		field = result.closest( '.wccr-exclusion-field' );
		selected = field ? field.querySelector( '.wccr-exclusion-field__selected' ) : null;
		input = field ? field.querySelector( '.wccr-exclusion-field__search' ) : null;

		if ( ! field || ! selected ) {
			return;
		}

		selected.appendChild(
			buildExclusionChip(
				field.getAttribute( 'data-input-name' ),
				{
					id: result.getAttribute( 'data-id' ),
					label: result.getAttribute( 'data-label' )
				}
			)
		);

		if ( input ) {
			input.value = '';
		}

		hideExclusionResults( field );
	}

	/**
	 * Handle clicks on "Reset to translated defaults" buttons.
	 *
	 * @param {MouseEvent} event Browser click event.
	 * @return {void}
	 */
	function handleResetClick( event ) {
		var button = event.target.closest( '.wccr-reset-translation' );
		if ( ! button ) {
			return;
		}

		var step   = button.getAttribute( 'data-step' );
		var locale = button.getAttribute( 'data-locale' );
		if ( ! step || ! locale ) {
			return;
		}

		var originalLabel = typeof WCCRAdminI18n !== 'undefined' && WCCRAdminI18n.resetLabel
			? WCCRAdminI18n.resetLabel
			: button.textContent;
		var resettingLabel = typeof WCCRAdminI18n !== 'undefined' && WCCRAdminI18n.resettingLabel
			? WCCRAdminI18n.resettingLabel
			: originalLabel;

		button.disabled     = true;
		button.textContent  = resettingLabel;

		var body = new window.FormData();
		body.append( 'action', 'wccr_reset_step_locale' );
		body.append( 'nonce',  WCCRAdminI18n.resetNonce );
		body.append( 'step',   step );
		body.append( 'locale', locale );

		window.fetch( WCCRAdminI18n.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			body:        body
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( payload && payload.success && payload.data ) {
					var card    = button.closest( '.wccr-card' );
					var subject = card ? card.querySelector( '[name*="[subject]"]' ) : null;
					var bodyEl  = card ? card.querySelector( '[name*="[body]"]' ) : null;
					if ( subject ) {
						subject.value = payload.data.subject || '';
					}
					if ( bodyEl ) {
						bodyEl.value = payload.data.body || '';
					}
				}
			} )
			.catch( function () {
				// Silent fail — keep existing values.
			} )
			.finally( function () {
				button.disabled    = false;
				button.textContent = originalLabel;
			} );
	}

	document.addEventListener( 'click', handleCopyClick );
	document.addEventListener( 'click', handleLocaleTabClick );
	document.addEventListener( 'click', handleEmailToggleClick );
	document.addEventListener( 'click', handleExclusionFieldClick );
	document.addEventListener( 'click', handleResetClick );
	document.addEventListener( 'input', handleExclusionSearchInput );
	document.addEventListener( 'submit', handleDeleteSubmit );
}() );
