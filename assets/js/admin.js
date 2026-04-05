/**
 * Handle copy and delete interactions on the recovery admin screen.
 */
( function () {
	'use strict';

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
				flashButtonText( button, 'Copied', 'Copy URL' );
			} );
			return;
		}

		var textarea = document.createElement( 'textarea' );
		textarea.value = url;
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
		flashButtonText( button, 'Copied', 'Copy URL' );
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

		if ( ! window.confirm( 'Delete this recovery item?' ) ) {
			event.preventDefault();
		}
	}

	document.addEventListener( 'click', handleCopyClick );
	document.addEventListener( 'submit', handleDeleteSubmit );
}() );
