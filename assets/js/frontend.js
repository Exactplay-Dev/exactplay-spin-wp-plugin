/**
 * Front-end behavior for Exactplay Spin embeds: click-to-play, fullscreen and the pop-up player.
 *
 * Everything degrades to plain links that open the game in a new tab.
 */
( function () {
	'use strict';

	var strings = window.exactplaySpinView || { close: 'Close', newTab: 'Open in new tab' };
	var dialog = null;

	function createFrame( src, title ) {
		var frame = document.createElement( 'iframe' );
		frame.className = 'exactplay-spin-game__frame';
		frame.src = src;
		frame.title = title || '';
		frame.setAttribute( 'allow', 'autoplay; fullscreen; screen-wake-lock' );
		frame.setAttribute( 'allowfullscreen', '' );
		frame.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		return frame;
	}

	function startGame( poster ) {
		var frame = createFrame( poster.getAttribute( 'data-exactplay-spin-src' ), poster.getAttribute( 'data-exactplay-spin-title' ) );
		poster.parentNode.replaceChild( frame, poster );
		frame.focus();
	}

	function canFullscreen( element ) {
		return !! ( element.requestFullscreen || element.webkitRequestFullscreen );
	}

	function enterFullscreen( element ) {
		var request = element.requestFullscreen || element.webkitRequestFullscreen;
		var result = request.call( element );
		if ( result && typeof result.catch === 'function' ) {
			result.catch( function () {} );
		}
	}

	function getDialog() {
		if ( dialog ) {
			return dialog;
		}

		dialog = document.createElement( 'dialog' );
		dialog.className = 'exactplay-spin-modal';
		dialog.innerHTML =
			'<div class="exactplay-spin-modal__inner">' +
			'<div class="exactplay-spin-modal__bar">' +
			'<h2 class="exactplay-spin-modal__title"></h2>' +
			'<a class="exactplay-spin-modal__newtab" target="_blank" rel="nofollow noopener"></a>' +
			'<button type="button" class="exactplay-spin-modal__close"><span aria-hidden="true">&times;</span></button>' +
			'</div>' +
			'<div class="exactplay-spin-modal__stage"></div>' +
			'</div>';

		var close = dialog.querySelector( '.exactplay-spin-modal__close' );
		close.setAttribute( 'aria-label', strings.close );
		close.addEventListener( 'click', function () {
			dialog.close();
		} );
		dialog.querySelector( '.exactplay-spin-modal__newtab' ).textContent = strings.newTab;

		// Remove the game on close so its sound stops.
		dialog.addEventListener( 'close', function () {
			dialog.querySelector( '.exactplay-spin-modal__stage' ).textContent = '';
		} );

		// Clicking the backdrop closes the player.
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		document.body.appendChild( dialog );
		return dialog;
	}

	function openModal( src, title ) {
		var modal = getDialog();
		var stage = modal.querySelector( '.exactplay-spin-modal__stage' );

		modal.querySelector( '.exactplay-spin-modal__title' ).textContent = title;
		modal.querySelector( '.exactplay-spin-modal__newtab' ).href = src;
		stage.textContent = '';
		stage.appendChild( createFrame( src, title ) );
		modal.showModal();
	}

	function hasModifier( event ) {
		return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( event.defaultPrevented || ! target || typeof target.closest !== 'function' ) {
			return;
		}

		var poster = target.closest( '.exactplay-spin-game__poster' );
		if ( poster ) {
			startGame( poster );
			return;
		}

		var fullscreen = target.closest( '.exactplay-spin-game__fullscreen' );
		if ( fullscreen ) {
			var figure = fullscreen.closest( '.exactplay-spin-game' );
			var stage = figure && figure.querySelector( '.exactplay-spin-game__stage' );
			// Without the Fullscreen API (e.g. iPhone Safari) the link opens the game in a new tab instead.
			if ( stage && canFullscreen( stage ) && ! hasModifier( event ) ) {
				event.preventDefault();
				var waiting = stage.querySelector( '.exactplay-spin-game__poster' );
				if ( waiting ) {
					startGame( waiting );
				}
				enterFullscreen( stage );
			}
			return;
		}

		var tile = target.closest( '[data-exactplay-spin-modal]' );
		if ( tile && ! hasModifier( event ) && typeof window.HTMLDialogElement === 'function' ) {
			event.preventDefault();
			openModal( tile.getAttribute( 'data-exactplay-spin-modal' ), tile.getAttribute( 'data-exactplay-spin-title' ) || '' );
		}
	} );
} )();
