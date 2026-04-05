( function () {
	'use strict';

	var lastPayload = '';
	var timer = null;

	function findField( selectors ) {
		for ( var i = 0; i < selectors.length; i++ ) {
			var field = document.querySelector( selectors[ i ] );
			if ( field && field.value ) {
				return field.value.trim();
			}
		}

		return '';
	}

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

		if ( ! email || payload === lastPayload || 'undefined' === typeof WCCRCheckoutCapture ) {
			return;
		}

		lastPayload = payload;

		var formData = new FormData();
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

	function scheduleCapture() {
		window.clearTimeout( timer );
		timer = window.setTimeout( sendCapture, 700 );
	}

	document.addEventListener( 'input', scheduleCapture );
	document.addEventListener( 'change', scheduleCapture );
	window.addEventListener( 'load', scheduleCapture );
}() );
