/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * ردیاب سبک Heatmap (نسخه تجاری): فقط مختصات نسبی کلیک و عمق اسکرول واقعی کاربر را
 * ثبت می‌کند؛ هیچ داده‌ی حساس یا مقدار فرمی جمع‌آوری نمی‌شود. رویدادها دسته‌ای و با
 * فاصله‌ی زمانی ارسال می‌شوند تا کمترین اثر روی سرعت سایت داشته باشند.
 */
( function () {
	'use strict';

	if ( typeof aawHeatmapConfig === 'undefined' || ! aawHeatmapConfig.endpoint ) {
		return;
	}

	var queue = [];
	var maxScroll = 0;
	var flushTimer = null;

	function docHeight() {
		return Math.max( document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0, window.innerHeight );
	}

	function onClick( e ) {
		var height = docHeight();
		var x = ( e.clientX / window.innerWidth ) * 100;
		var y = ( ( window.scrollY + e.clientY ) / height ) * 100;

		queue.push( {
			type: 'click',
			x: Math.round( Math.max( 0, Math.min( 100, x ) ) * 100 ) / 100,
			y: Math.round( Math.max( 0, Math.min( 100, y ) ) * 100 ) / 100,
			vw: window.innerWidth,
			vh: window.innerHeight,
		} );

		scheduleFlush();
	}

	var scrollTicking = false;
	function onScroll() {
		if ( scrollTicking ) {
			return;
		}
		scrollTicking = true;
		requestAnimationFrame( function () {
			var height = docHeight();
			var percent = Math.round( ( ( window.scrollY + window.innerHeight ) / height ) * 100 );
			percent = Math.max( 0, Math.min( 100, percent ) );
			if ( percent > maxScroll ) {
				maxScroll = percent;
			}
			scrollTicking = false;
		} );
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

	function scheduleFlush() {
		if ( flushTimer ) {
			return;
		}
		flushTimer = setTimeout( function () {
			flushTimer = null;
			flush();
		}, 4000 );
	}

	function flush( useBeacon ) {
		var events = queue.slice();
		queue = [];

		if ( maxScroll > 0 ) {
			events.push( { type: 'scroll', scroll: maxScroll } );
			maxScroll = 0;
		}

		if ( ! events.length ) {
			return;
		}

		var payload = JSON.stringify( {
			url: window.location.href,
			device: detectDevice(),
			events: events,
		} );

		if ( useBeacon && navigator.sendBeacon ) {
			navigator.sendBeacon( aawHeatmapConfig.endpoint, new Blob( [ payload ], { type: 'application/json' } ) );
			return;
		}

		fetch( aawHeatmapConfig.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: payload,
			keepalive: true,
		} ).catch( function () {
			/* بی‌اهمیت: خطای شبکه فقط باعث از‌دست‌رفتن این دسته از رویدادها می‌شود. */
		} );
	}

	document.addEventListener( 'click', onClick, { passive: true } );
	window.addEventListener( 'scroll', onScroll, { passive: true } );

	document.addEventListener( 'visibilitychange', function () {
		if ( 'hidden' === document.visibilityState ) {
			flush( true );
		}
	} );

	window.addEventListener( 'pagehide', function () {
		flush( true );
	} );
} )();
