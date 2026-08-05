/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * نمایشگر Heatmap در پیشخوان: نقاط کلیک واقعی ثبت‌شده را به‌صورت نقشه‌ی حرارتی
 * روی یک canvas شفاف بالای iframe صفحه‌ی واقعی سایت رسم می‌کند.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrap = document.getElementById( 'aawHeatmapFrameWrap' );
		if ( ! wrap ) {
			return;
		}

		var canvas = document.getElementById( 'aawHeatmapCanvas' );
		var points = [];
		try {
			points = JSON.parse( wrap.getAttribute( 'data-points' ) || '[]' );
		} catch ( e ) {
			points = [];
		}

		function draw() {
			var rect = wrap.getBoundingClientRect();
			canvas.width = rect.width;
			canvas.height = rect.height;

			var ctx = canvas.getContext( '2d' );
			ctx.clearRect( 0, 0, canvas.width, canvas.height );

			points.forEach( function ( point ) {
				var x = ( parseFloat( point.x_percent ) / 100 ) * canvas.width;
				var y = ( parseFloat( point.y_percent ) / 100 ) * canvas.height;
				var radius = Math.max( 24, canvas.width * 0.03 );

				var gradient = ctx.createRadialGradient( x, y, 0, x, y, radius );
				gradient.addColorStop( 0, 'rgba(244, 63, 94, 0.55)' );
				gradient.addColorStop( 0.5, 'rgba(249, 115, 22, 0.35)' );
				gradient.addColorStop( 1, 'rgba(249, 115, 22, 0)' );

				ctx.fillStyle = gradient;
				ctx.beginPath();
				ctx.arc( x, y, radius, 0, Math.PI * 2 );
				ctx.fill();
			} );
		}

		var frame = document.getElementById( 'aawHeatmapFrame' );
		if ( frame ) {
			frame.addEventListener( 'load', draw );
		}

		window.addEventListener( 'resize', draw );
		setTimeout( draw, 400 );
		setTimeout( draw, 1200 );
	} );
} )();
