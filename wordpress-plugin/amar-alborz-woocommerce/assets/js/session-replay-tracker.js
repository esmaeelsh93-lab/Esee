/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * ردیاب Session Replay (نسخه تجاری): فقط مسیر حرکت نسبی ماوس/لمس، کلیک و اسکرول را ثبت
 * می‌کند. هرگز به مقدار فیلدهای فرم (رمز عبور، ایمیل، شماره کارت و ...) دسترسی پیدا
 * نمی‌کند و آن را ذخیره نمی‌کند؛ فقط مختصات و زمان نسبی هر رویداد.
 */
( function () {
	'use strict';

	if ( typeof aawReplayConfig === 'undefined' || ! aawReplayConfig.endpoint ) {
		return;
	}

	function getCookie( name ) {
		var match = document.cookie.match( '(?:^|; )' + name.replace( /([.$?*|{}()[\]\\/+^])/g, '\\$1' ) + '=([^;]*)' );
		return match ? decodeURIComponent( match[ 1 ] ) : null;
	}

	var sessionId = getCookie( aawReplayConfig.cookieName );
	if ( ! sessionId ) {
		return; // بدون نشست معتبر (مثلاً کوکی مسدود است)، ضبطی انجام نمی‌شود.
	}

	var storageKey = 'aaw_replay_meta';
	var stored     = null;
	try {
		stored = JSON.parse( window.localStorage.getItem( storageKey ) || 'null' );
	} catch ( e ) {
		stored = null;
	}

	var startTime;
	if ( stored && stored.sid === sessionId ) {
		startTime = stored.start;
	} else {
		startTime = Date.now();
		try {
			window.localStorage.setItem( storageKey, JSON.stringify( { sid: sessionId, start: startTime } ) );
		} catch ( e ) {
			/* بی‌اهمیت: در صورت نبود localStorage، فقط زمان‌بندی نسبی این صفحه استفاده می‌شود. */
		}
	}

	function detectDevice() {
		var w = window.innerWidth;
		if ( w < 640 ) {
			return 'mobile';
		}
		if ( w < 1024 ) {
			return 'tablet';
		}
		return 'desktop';
	}

	function detectBrowser() {
		var ua = navigator.userAgent.toLowerCase();
		if ( ua.indexOf( 'edg/' ) > -1 ) { return 'مایکروسافت اج'; }
		if ( ua.indexOf( 'opr/' ) > -1 || ua.indexOf( 'opera' ) > -1 ) { return 'اپرا'; }
		if ( ua.indexOf( 'firefox' ) > -1 ) { return 'فایرفاکس'; }
		if ( ua.indexOf( 'chrome' ) > -1 ) { return 'کروم'; }
		if ( ua.indexOf( 'safari' ) > -1 ) { return 'سافاری'; }
		return 'سایر مرورگرها';
	}

	function docHeight() {
		return Math.max( document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0, window.innerHeight );
	}

	function tOffset() {
		return Date.now() - startTime;
	}

	var queue = [];
	var isFirstFlushOfPage = true;
	var flushTimer = null;

	function pushEvent( type, x, y, scroll ) {
		var event = { type: type, t: tOffset() };
		if ( undefined !== x ) { event.x = Math.round( Math.max( 0, Math.min( 100, x ) ) * 100 ) / 100; }
		if ( undefined !== y ) { event.y = Math.round( Math.max( 0, Math.min( 100, y ) ) * 100 ) / 100; }
		if ( undefined !== scroll ) { event.scroll = scroll; }
		queue.push( event );
		scheduleFlush();
	}

	pushEvent( 'pageview' );

	var lastMove = 0;
	document.addEventListener( 'mousemove', function ( e ) {
		var now = Date.now();
		if ( now - lastMove < 150 ) {
			return;
		}
		lastMove = now;
		pushEvent( 'move', ( e.clientX / window.innerWidth ) * 100, ( ( window.scrollY + e.clientY ) / docHeight() ) * 100 );
	}, { passive: true } );

	document.addEventListener( 'touchmove', function ( e ) {
		var now = Date.now();
		if ( now - lastMove < 150 || ! e.touches || ! e.touches.length ) {
			return;
		}
		lastMove = now;
		var touch = e.touches[ 0 ];
		pushEvent( 'move', ( touch.clientX / window.innerWidth ) * 100, ( ( window.scrollY + touch.clientY ) / docHeight() ) * 100 );
	}, { passive: true } );

	document.addEventListener( 'click', function ( e ) {
		pushEvent( 'click', ( e.clientX / window.innerWidth ) * 100, ( ( window.scrollY + e.clientY ) / docHeight() ) * 100 );
	}, { passive: true } );

	var scrollTicking = false;
	window.addEventListener( 'scroll', function () {
		if ( scrollTicking ) {
			return;
		}
		scrollTicking = true;
		requestAnimationFrame( function () {
			var percent = Math.round( ( ( window.scrollY + window.innerHeight ) / docHeight() ) * 100 );
			pushEvent( 'scroll', undefined, undefined, Math.max( 0, Math.min( 100, percent ) ) );
			scrollTicking = false;
		} );
	}, { passive: true } );

	function scheduleFlush() {
		if ( flushTimer ) {
			return;
		}
		flushTimer = setTimeout( function () {
			flushTimer = null;
			flush();
		}, 3000 );
	}

	function flush( useBeacon ) {
		if ( ! queue.length ) {
			return;
		}

		var events = queue.slice();
		queue = [];

		var payload = JSON.stringify( {
			sid: sessionId,
			url: window.location.href,
			events: events,
			meta: {
				device: detectDevice(),
				browser: detectBrowser(),
				new_page: isFirstFlushOfPage,
			},
		} );
		isFirstFlushOfPage = false;

		if ( useBeacon && navigator.sendBeacon ) {
			navigator.sendBeacon( aawReplayConfig.endpoint, new Blob( [ payload ], { type: 'application/json' } ) );
			return;
		}

		fetch( aawReplayConfig.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: payload,
			keepalive: true,
		} ).catch( function () {
			/* بی‌اهمیت: خطای شبکه فقط باعث از‌دست‌رفتن این دسته از رویدادها می‌شود. */
		} );
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( 'hidden' === document.visibilityState ) {
			flush( true );
		}
	} );

	window.addEventListener( 'pagehide', function () {
		flush( true );
	} );
} )();
