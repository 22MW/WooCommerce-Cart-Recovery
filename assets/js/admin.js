( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.wccr-copy-url' );
		if ( ! button ) {
			return;
		}

		var url = button.getAttribute( 'data-url' );
		if ( ! url ) {
			return;
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( function () {
				button.textContent = 'Copied';
				window.setTimeout( function () {
					button.textContent = 'Copy URL';
				}, 1500 );
			} );
			return;
		}

		var textarea = document.createElement( 'textarea' );
		textarea.value = url;
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
		button.textContent = 'Copied';
		window.setTimeout( function () {
			button.textContent = 'Copy URL';
		}, 1500 );
	} );
}() );
