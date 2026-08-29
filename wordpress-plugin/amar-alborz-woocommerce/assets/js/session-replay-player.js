/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * پخش‌کننده‌ی Session Replay در پیشخوان: مسیر واقعی حرکت/کلیک/اسکرول ضبط‌شده را
 * به‌صورت انیمیشن روی canvas بالای iframe صفحه‌ی واقعی سایت پخش می‌کند.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'aawReplayFrameWrap' );
		if ( ! wrap ) {
			return;
		}

		var canvas   = document.getElementById( 'aawReplayCanvas' );
		var frame    = document.getElementById( 'aawReplayFrame' );
		var ctx      = canvas.getContext( '2d' );
		var seek     = document.getElementById( 'aawReplaySeek' );
		var playBtn  = document.getElementById( 'aawReplayPlay' );
		var pauseBtn = document.getElementById( 'aawReplayPause' );
		var speedSel = document.getElementById( 'aawReplaySpeed' );

		var events = [];
		try {
			events = JSON.parse( wrap.getAttribute( 'data-events' ) || '[]' );
		} catch ( e ) {
			events = [];
		}

		if ( ! events.length ) {
			return;
		}

		var maxT = parseInt( events[ events.length - 1 ].t_offset_ms, 10 ) || 1000;
		seek.max = maxT;

		var currentTime  = 0;
		var playing      = false;
		var wallClockRef = 0;
		var timeRef      = 0;
		var currentUrl   = frame.getAttribute( 'src' );

		function resizeCanvas() {
			var rect = wrap.getBoundingClientRect();
			canvas.width = rect.width;
			canvas.height = rect.height;
		}

		function findLastEventAt( time, types ) {
			var found = null;
			for ( var i = 0; i < events.length; i++ ) {
				var evt = events[ i ];
				if ( parseInt( evt.t_offset_ms, 10 ) > time ) {
					break;
				}
				if ( types.indexOf( evt.event_type ) !== -1 ) {
					found = evt;
				}
			}
			return found;
		}

		function maybeUpdatePage( time ) {
			var pv = findLastEventAt( time, [ 'pageview' ] );
			if ( pv && pv.page_url && pv.page_url !== currentUrl ) {
				currentUrl = pv.page_url;
				frame.setAttribute( 'src', currentUrl );
			}
		}

		function render( time ) {
			resizeCanvas();
			ctx.clearRect( 0, 0, canvas.width, canvas.height );

			var cursorEvt = findLastEventAt( time, [ 'move', 'click' ] );
			if ( ! cursorEvt || null === cursorEvt.x_percent ) {
				return;
			}

			var x = ( parseFloat( cursorEvt.x_percent ) / 100 ) * canvas.width;
			var y = ( parseFloat( cursorEvt.y_percent ) / 100 ) * canvas.height;

			ctx.beginPath();
			ctx.arc( x, y, 7, 0, Math.PI * 2 );
			ctx.fillStyle = 'rgba(74, 144, 247, 0.9)';
			ctx.fill();
			ctx.lineWidth = 2;
			ctx.strokeStyle = '#ffffff';
			ctx.stroke();

			var recentClick = findLastEventAt( time, [ 'click' ] );
			if ( recentClick && ( time - parseInt( recentClick.t_offset_ms, 10 ) ) < 500 ) {
				var progress = ( time - parseInt( recentClick.t_offset_ms, 10 ) ) / 500;
				ctx.beginPath();
				ctx.arc( x, y, 8 + progress * 20, 0, Math.PI * 2 );
				ctx.strokeStyle = 'rgba(244, 63, 94, ' + ( 1 - progress ) + ')';
				ctx.lineWidth = 3;
				ctx.stroke();
			}
		}

		function seekTo( time ) {
			currentTime = Math.max( 0, Math.min( maxT, time ) );
			seek.value = currentTime;
			maybeUpdatePage( currentTime );
			render( currentTime );
		}

		function tick() {
			if ( ! playing ) {
				return;
			}
			var speed = parseFloat( speedSel.value ) || 1;
			var now   = Date.now();
			var elapsed = ( now - wallClockRef ) * speed;
			var time = timeRef + elapsed;

			if ( time >= maxT ) {
				seekTo( maxT );
				playing = false;
				return;
			}

			seekTo( time );
			requestAnimationFrame( tick );
		}

		playBtn.addEventListener( 'click', function () {
			if ( playing ) {
				return;
			}
			playing = true;
			wallClockRef = Date.now();
			timeRef = currentTime;
			requestAnimationFrame( tick );
		} );

		pauseBtn.addEventListener( 'click', function () {
			playing = false;
		} );

		seek.addEventListener( 'input', function () {
			playing = false;
			seekTo( parseFloat( seek.value ) );
		} );

		window.addEventListener( 'resize', function () {
			render( currentTime );
		} );

		frame.addEventListener( 'load', function () {
			render( currentTime );
		} );

		setTimeout( function () {
			seekTo( 0 );
		}, 400 );
	} );
} )();
