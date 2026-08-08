( function () {
	'use strict';

	var config = window.cvsTrackerSettings || {};
	if ( ! config.enabled || ! config.endpoint || window.__cvsPageTracked ) {
		return;
	}

	if ( document.prerendering || document.visibilityState === 'prerender' ) {
		document.addEventListener( 'prerenderingchange', start, { once: true } );
		return;
	}

	function randomToken() {
		if ( window.crypto && window.crypto.getRandomValues ) {
			var values = new Uint32Array( 4 );
			window.crypto.getRandomValues( values );
			return Array.prototype.map.call( values, function ( value ) {
				return value.toString( 36 ).padStart( 7, '0' );
			} ).join( '' );
		}
		return String( Date.now() ) + Math.random().toString( 36 ).slice( 2 );
	}

	function getCookie( name ) {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + name.replace( /([.$?*|{}()[\]\\/+^])/g, '\\$1' ) + '=([^;]*)' ) );
		return match ? decodeURIComponent( match[ 1 ] ) : '';
	}

	function setCookie( name, value, maxAge ) {
		document.cookie = name + '=' + encodeURIComponent( value ) +
			'; path=/; max-age=' + maxAge + '; SameSite=Lax' +
			( window.location.protocol === 'https:' ? '; Secure' : '' );
	}

	function getStoredId( key, cookieName, maxAge ) {
		var value = '';
		try {
			value = window.sessionStorage.getItem( key ) || '';
		} catch ( error ) {
			value = '';
		}

		if ( ! config.cookieLess ) {
			value = getCookie( cookieName ) || value;
		}

		if ( ! value ) {
			value = randomToken();
		}

		try {
			window.sessionStorage.setItem( key, value );
		} catch ( error ) {
			// مرورگرهایی با ذخیره‌سازی مسدودشده همچنان با شناسه‌ی حافظه‌ای کار می‌کنند.
		}

		if ( ! config.cookieLess ) {
			setCookie( cookieName, value, maxAge );
		}
		return value;
	}

	function getSessionId( maxAge ) {
		var now = Date.now();
		var value = '';
		var lastSeen = 0;
		var cookieValue = '';
		try {
			value = window.sessionStorage.getItem( 'cvs_session_id' ) || '';
			lastSeen = Number( window.sessionStorage.getItem( 'cvs_session_last' ) || 0 );
		} catch ( error ) {
			value = '';
		}

		if ( ! config.cookieLess ) {
			cookieValue = getCookie( 'cvs_sid' );
			value = cookieValue || value;
		}
		if ( ! value || ( lastSeen && now - lastSeen > maxAge * 1000 ) || ( ! lastSeen && ! cookieValue ) ) {
			value = randomToken();
		}

		try {
			window.sessionStorage.setItem( 'cvs_session_id', value );
			window.sessionStorage.setItem( 'cvs_session_last', String( now ) );
		} catch ( error ) {
			// ادامه با شناسه‌ی حافظه‌ای.
		}
		if ( ! config.cookieLess ) {
			setCookie( 'cvs_sid', value, maxAge );
		}
		return value;
	}

	function sendPageview() {
		var sessionAge = Math.max( 1, Number( config.sessionMinutes ) || 30 ) * 60;
		var sessionId  = getSessionId( sessionAge );
		var visitorId  = getStoredId( 'cvs_visitor_id', 'cvs_vid', 31536000 );
		var params     = new URLSearchParams( window.location.search );
		var loadKey    = 'cvs_event_' + String( window.performance && window.performance.timeOrigin || Date.now() );
		var eventId    = getStoredId( loadKey, 'cvs_no_cookie', 1 );

		if ( ! config.cookieLess ) {
			// کوکی ساختگی بالا فقط برای استفاده‌ی مشترک تابع است و فوراً حذف می‌شود.
			document.cookie = 'cvs_no_cookie=; path=/; max-age=0; SameSite=Lax';
		}

		var payload = {
			event_id: eventId,
			session_id: sessionId,
			visitor_id: visitorId,
			page_url: window.location.href,
			referrer: document.referrer || '',
			utm: {
				source: params.get( 'utm_source' ) || '',
				medium: params.get( 'utm_medium' ) || '',
				campaign: params.get( 'utm_campaign' ) || '',
			},
		};

		window.__cvsPageTracked = true;
		var startedAt = Date.now();

		window.fetch( config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: true,
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( payload ),
		} ).catch( function () {
			// تحلیل‌گر نباید تجربه‌ی بازدیدکننده را مختل کند.
		} );

		function reportDuration() {
			if ( ! config.sessionEndpoint ) {
				return;
			}
			var duration = Math.max( 0, Math.round( ( Date.now() - startedAt ) / 1000 ) );
			var body = JSON.stringify( { session_id: sessionId, duration: duration } );
			if ( navigator.sendBeacon ) {
				navigator.sendBeacon( config.sessionEndpoint, new Blob( [ body ], { type: 'application/json' } ) );
			}
		}

		window.setInterval( function () {
			var duration = Math.max( 0, Math.round( ( Date.now() - startedAt ) / 1000 ) );
			window.fetch( config.sessionEndpoint, {
				method: 'POST',
				credentials: 'same-origin',
				keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { session_id: sessionId, duration: duration } ),
			} ).catch( function () {} );
		}, 120000 );
		window.addEventListener( 'pagehide', reportDuration, { once: true } );
	}

	function start() {
		if ( document.readyState === 'complete' ) {
			sendPageview();
		} else {
			window.addEventListener( 'load', sendPageview, { once: true } );
		}
	}

	start();
} )();
