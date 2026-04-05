/**
 * Capture checkout contact details early for WooCommerce Checkout Blocks.
 */
( function () {
	'use strict';

	var lastPayload = '';
	var timer = null;

	/**
	 * Find the first non-empty field value from a selector list.
	 *
	 * @param {string[]} selectors DOM selectors.
	 * @return {string}
	 */
	function findField( selectors ) {
		var i;
		var field;

		for ( i = 0; i < selectors.length; i++ ) {
			field = document.querySelector( selectors[ i ] );
			if ( field && field.value ) {
				return field.value.trim();
			}
		}

		return '';
	}

	/**
	 * Send the current email/name payload to the server.
	 *
	 * @return {void}
	 */
	function sendCapture() {
		var email = findField( [
			'input[type="email"]',
			'input[name="email"]',
			'input[name="billing_email"]',
			'#email'
		] );
		var firstName = findField( [ 'input[name="billing-first_name"]', 'input[name="billing_first_name"]', '#billing-first_name', '#billing_first_name' ] );
		var lastName = findField( [ 'input[name="billing-last_name"]', 'input[name="billing_last_name"]', '#billing-last_name', '#billing_last_name' ] );
		var name = ( firstName + ' ' + lastName ).trim();
		var payload = email + '|' + name;
		var formData;

		if ( ! email || payload === lastPayload || 'undefined' === typeof WCCRCheckoutCapture ) {
			return;
		}

		lastPayload = payload;
		formData = new FormData();
		formData.append( 'action', 'wccr_capture_checkout_contact' );
		formData.append( 'nonce', WCCRCheckoutCapture.nonce );
		formData.append( 'email', email );
		formData.append( 'name', name );

		fetch( WCCRCheckoutCapture.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} ).catch( function () {} );
	}

	/**
	 * Debounce contact capture while the shopper types.
	 *
	 * @return {void}
	 */
	function scheduleCapture() {
		window.clearTimeout( timer );
		timer = window.setTimeout( sendCapture, 700 );
	}

	document.addEventListener( 'input', scheduleCapture );
	document.addEventListener( 'change', scheduleCapture );
	window.addEventListener( 'load', scheduleCapture );
}() );
