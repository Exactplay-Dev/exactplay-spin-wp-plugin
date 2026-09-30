/**
 * Game Library: copy shortcodes to the clipboard.
 */
( function () {
	'use strict';

	var strings = window.exactplaySpinAdmin || { copied: 'Copied!' };

	function flash( button ) {
		if ( ! button.hasAttribute( 'data-label' ) ) {
			button.setAttribute( 'data-label', button.textContent );
		}
		button.textContent = strings.copied;
		clearTimeout( button.exactplaySpinTimer );
		button.exactplaySpinTimer = setTimeout( function () {
			button.textContent = button.getAttribute( 'data-label' );
		}, 1500 );
	}

	// Clipboard API needs HTTPS (or localhost); older browsers fall back to execCommand.
	function fallbackCopy( text, button ) {
		var field = document.createElement( 'textarea' );
		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.opacity = '0';
		document.body.appendChild( field );
		field.select();
		document.execCommand( 'copy' );
		document.body.removeChild( field );
		button.focus();
		flash( button );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest && event.target.closest( '[data-exactplay-spin-copy]' );
		if ( ! button ) {
			return;
		}
		var text = button.getAttribute( 'data-exactplay-spin-copy' );

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then(
				function () {
					flash( button );
				},
				function () {
					fallbackCopy( text, button );
				}
			);
		} else {
			fallbackCopy( text, button );
		}
	} );
} )();
