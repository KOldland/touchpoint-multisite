( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		const notifyBtn = document.getElementById( 'angie-agents-notify-btn' );
		if ( ! notifyBtn ) {
			return;
		}

		const LS_KEY = 'angie_agents_notify_registered';
		const IFRAME_MSG_TYPE = 'angie_agents_notify_request';
		const YOU_ARE_ALREADY_ON_THE_LIST = "You're already on the list";
		const GOT_IT_WE_LL_BE_IN_TOUCH_SOON = "Got it, we'll be in touch soon";

		function setConfirmedState( btn, text ) {
			btn.disabled = true;
			btn.classList.add( 'angie-agents-notify-confirmed' );
			btn.innerHTML =
				'<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">' +
				'<circle cx="10" cy="10" r="9" stroke="#0A875A" stroke-width="1.5" fill="none"/>' +
				'<path d="M6 10.5L8.5 13L14 7.5" stroke="#0A875A" stroke-width="1.5" ' +
				'stroke-linecap="round" stroke-linejoin="round"/></svg>' +
				text;
		}

		function getAngieIframe() {
			return document.getElementById( 'angie-iframe' ) || document.querySelector( 'iframe[src*="angie/"]' );
		}

		if ( window.localStorage.getItem( LS_KEY ) === 'true' ) {
			setConfirmedState( notifyBtn, YOU_ARE_ALREADY_ON_THE_LIST || '' );
		}

		notifyBtn.addEventListener( 'click', function() {
			if ( notifyBtn.classList.contains( 'angie-agents-notify-confirmed' ) ) {
				return;
			}

			window.localStorage.setItem( LS_KEY, 'true' );
			setConfirmedState( notifyBtn, GOT_IT_WE_LL_BE_IN_TOUCH_SOON || '' );

			const iframe = getAngieIframe();
			if ( iframe && iframe.contentWindow ) {
				iframe.contentWindow.postMessage(
					{
						type: IFRAME_MSG_TYPE,
					},
					'*'
				);
			}
		} );
	} );
}() );
