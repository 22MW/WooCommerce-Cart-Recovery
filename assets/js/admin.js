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

	document.addEventListener( 'click', handleCopyClick );
	document.addEventListener( 'click', handleLocaleTabClick );
	document.addEventListener( 'submit', handleDeleteSubmit );
}() );
