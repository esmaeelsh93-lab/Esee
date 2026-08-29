( function () {
	'use strict';

	if ( typeof Chart === 'undefined' || typeof cvsChartData === 'undefined' ) {
		return;
	}

	var GRID_COLOR  = 'rgba(255, 255, 255, 0.06)';
	var TEXT_COLOR  = '#8b95b3';
	var FALLBACK    = '#64748b';

	Chart.defaults.color = TEXT_COLOR;
	Chart.defaults.font.family = "'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', Tahoma, sans-serif";
	Chart.defaults.plugins.legend.labels.usePointStyle = true;

	function hexToRgba( hex, alpha ) {
		var parsed = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec( hex );
		if ( ! parsed ) {
			return hex;
		}
		var r = parseInt( parsed[ 1 ], 16 );
		var g = parseInt( parsed[ 2 ], 16 );
		var b = parseInt( parsed[ 3 ], 16 );
		return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
	}

	var colors        = cvsChartData.colors || {};
	var dates         = cvsChartData.daily.datesFormatted || cvsChartData.daily.dates || [];
	var sources       = cvsChartData.daily.sources || {};
	var breakdownList = cvsChartData.sources || [];
	var dailyTotals   = cvsChartData.dailyTotals || [];

	function renderCharts() {

	/* نمودار ستونی روند ورودی به تفکیک منبع (پشته‌ای) */
	var barCanvas = document.getElementById( 'cvsBarChart' );
	if ( barCanvas ) {
		var barDatasets = Object.keys( sources ).map( function ( key ) {
			var color = colors[ key ] || FALLBACK;
			return {
				label: sources[ key ].label,
				data: dates.map( function ( d, i ) {
					var rawDates = cvsChartData.daily.dates || [];
					return sources[ key ].data[ rawDates[ i ] ] || 0;
				} ),
				backgroundColor: color,
				borderRadius: 4,
				maxBarThickness: 26,
			};
		} );

		new Chart( barCanvas.getContext( '2d' ), {
			type: 'bar',
			data: {
				labels: dates,
				datasets: barDatasets,
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: { stacked: true, grid: { color: GRID_COLOR }, ticks: { color: TEXT_COLOR } },
					y: { stacked: true, beginAtZero: true, grid: { color: GRID_COLOR }, ticks: { color: TEXT_COLOR, precision: 0 } },
				},
				plugins: {
					legend: { position: 'bottom', labels: { color: TEXT_COLOR, boxWidth: 10, boxHeight: 10 } },
				},
			},
		} );
	}

	/* نمودار ناحیه‌ای مجموع ورودی روزانه */
	var areaCanvas = document.getElementById( 'cvsAreaChart' );
	if ( areaCanvas ) {
		var ctx = areaCanvas.getContext( '2d' );
		var gradient = ctx.createLinearGradient( 0, 0, 0, 260 );
		gradient.addColorStop( 0, 'rgba(74, 144, 247, 0.45)' );
		gradient.addColorStop( 1, 'rgba(74, 144, 247, 0)' );

		new Chart( ctx, {
			type: 'line',
			data: {
				labels: dates,
				datasets: [ {
					label: cvsChartData.i18n.totalLabel,
					data: dailyTotals,
					borderColor: '#4a90f7',
					backgroundColor: gradient,
					fill: true,
					tension: 0.35,
					pointRadius: 2,
					pointBackgroundColor: '#4a90f7',
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: { grid: { display: false }, ticks: { color: TEXT_COLOR, maxTicksLimit: 8 } },
					y: { beginAtZero: true, grid: { color: GRID_COLOR }, ticks: { color: TEXT_COLOR, precision: 0 } },
				},
				plugins: { legend: { display: false } },
			},
		} );
	}

	/* نمودار دونات توزیع منابع */
	var donutCanvas = document.getElementById( 'cvsDonutChart' );
	if ( donutCanvas && breakdownList.length ) {
		new Chart( donutCanvas.getContext( '2d' ), {
			type: 'doughnut',
			data: {
				labels: breakdownList.map( function ( s ) { return s.label; } ),
				datasets: [ {
					data: breakdownList.map( function ( s ) { return s.total; } ),
					backgroundColor: breakdownList.map( function ( s ) { return colors[ s.key ] || FALLBACK; } ),
					borderColor: '#1a2338',
					borderWidth: 2,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '68%',
				plugins: { legend: { display: false } },
			},
		} );
	}

	/* نمودار خطی روند تجمعی ورودی‌ها */
	var lineCanvas = document.getElementById( 'cvsLineChart' );
	if ( lineCanvas ) {
		var cumulative = [];
		var runningTotal = 0;
		dailyTotals.forEach( function ( v ) {
			runningTotal += v;
			cumulative.push( runningTotal );
		} );

		new Chart( lineCanvas.getContext( '2d' ), {
			type: 'line',
			data: {
				labels: dates,
				datasets: [ {
					label: cvsChartData.i18n.cumulativeLabel,
					data: cumulative,
					borderColor: '#22c9a8',
					backgroundColor: hexToRgba( '#22c9a8', 0.12 ),
					fill: true,
					tension: 0.3,
					pointRadius: 3,
					pointBackgroundColor: '#22c9a8',
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: { grid: { color: GRID_COLOR }, ticks: { color: TEXT_COLOR, maxTicksLimit: 10 } },
					y: { beginAtZero: true, grid: { color: GRID_COLOR }, ticks: { color: TEXT_COLOR, precision: 0 } },
				},
				plugins: { legend: { display: false } },
			},
		} );
	}

	}

	if ( document.fonts && document.fonts.ready ) {
		document.fonts.load( "700 16px Vazirmatn" );
		document.fonts.load( "400 16px Vazirmatn" );
		document.fonts.ready.then( renderCharts ).catch( renderCharts );
	} else {
		renderCharts();
	}
} )();
