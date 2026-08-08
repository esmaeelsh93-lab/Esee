( function () {
	'use strict';

	if ( typeof Chart === 'undefined' || typeof cvsChartData === 'undefined' ) {
		return;
	}

	var app = document.querySelector( '.cvs-app' );
	if ( ! app ) {
		return;
	}

	var styles = window.getComputedStyle( app );
	var color = function ( token, fallback ) {
		return styles.getPropertyValue( token ).trim() || fallback;
	};
	var numberLocale = cvsChartData.i18n && cvsChartData.i18n.numberLocale || 'fa-IR';
	var faNumber = new Intl.NumberFormat( numberLocale );
	var isMobile = window.matchMedia( '(max-width: 767px)' ).matches;

	var PRIMARY     = color( '--stat-color-primary', '#4f46e5' );
	var SUCCESS     = color( '--stat-color-success', '#10b981' );
	var GRID_COLOR  = color( '--cvs-chart-grid', 'rgba(100, 116, 139, 0.14)' );
	var TEXT_COLOR  = color( '--stat-text-secondary', '#64748b' );
	var SURFACE     = color( '--stat-color-surface', '#ffffff' );
	var FALLBACK    = '#94a3b8';

	Chart.defaults.color = TEXT_COLOR;
	Chart.defaults.font.family = "'Vazirmatn', Tahoma, sans-serif";
	Chart.defaults.font.size = 12;
	Chart.defaults.plugins.legend.labels.usePointStyle = true;
	Chart.defaults.plugins.tooltip.rtl = true;
	Chart.defaults.plugins.tooltip.textDirection = 'rtl';

	function rgba( hex, alpha ) {
		var parsed = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec( hex );
		if ( ! parsed ) {
			return hex;
		}
		return 'rgba(' +
			parseInt( parsed[ 1 ], 16 ) + ',' +
			parseInt( parsed[ 2 ], 16 ) + ',' +
			parseInt( parsed[ 3 ], 16 ) + ',' + alpha + ')';
	}

	function tooltipLabel( context ) {
		var value = context.parsed.y;
		if ( typeof value === 'undefined' ) {
			value = context.parsed;
		}
		return context.dataset.label + ': ' + faNumber.format( Number( value ) || 0 );
	}

	var colors        = cvsChartData.colors || {};
	var dates         = cvsChartData.daily.datesFormatted || cvsChartData.daily.dates || [];
	var rawDates      = cvsChartData.daily.dates || [];
	var sources       = cvsChartData.daily.sources || {};
	var breakdownList = cvsChartData.sources || [];
	var dailyTotals   = cvsChartData.dailyTotals || [];
	var maxTicks      = isMobile ? 4 : 10;

	function baseScales( stacked ) {
		return {
			x: {
				stacked: !! stacked,
				grid: { display: false },
				ticks: { color: TEXT_COLOR, maxTicksLimit: maxTicks, maxRotation: 0 },
				border: { display: false },
			},
			y: {
				stacked: !! stacked,
				beginAtZero: true,
				grid: { color: GRID_COLOR },
				ticks: {
					color: TEXT_COLOR,
					precision: 0,
					callback: function ( value ) { return faNumber.format( value ); },
				},
				border: { display: false },
			},
		};
	}

	function renderCharts() {
		var areaCanvas = document.getElementById( 'cvsAreaChart' );
		if ( areaCanvas ) {
			var areaContext = areaCanvas.getContext( '2d' );
			var gradient = areaContext.createLinearGradient( 0, 0, 0, 300 );
			gradient.addColorStop( 0, rgba( PRIMARY, 0.28 ) );
			gradient.addColorStop( 1, rgba( PRIMARY, 0 ) );

			new Chart( areaContext, {
				type: 'line',
				data: {
					labels: dates,
					datasets: [ {
						label: cvsChartData.i18n.totalLabel,
						data: dailyTotals,
						borderColor: PRIMARY,
						backgroundColor: gradient,
						borderWidth: 2.5,
						fill: true,
						tension: 0.35,
						pointRadius: isMobile ? 0 : 3,
						pointHoverRadius: 5,
						pointBackgroundColor: SURFACE,
						pointBorderColor: PRIMARY,
						pointBorderWidth: 2,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { intersect: false, mode: 'index' },
					scales: baseScales( false ),
					plugins: {
						legend: { display: false },
						tooltip: { callbacks: { label: tooltipLabel } },
					},
				},
			} );
		}

		var barCanvas = document.getElementById( 'cvsBarChart' );
		if ( barCanvas ) {
			var barDatasets = Object.keys( sources ).map( function ( key ) {
				return {
					label: sources[ key ].label,
					data: rawDates.map( function ( date ) {
						return sources[ key ].data[ date ] || 0;
					} ),
					backgroundColor: colors[ key ] || FALLBACK,
					borderRadius: 5,
					borderSkipped: false,
					maxBarThickness: 28,
				};
			} );

			new Chart( barCanvas.getContext( '2d' ), {
				type: 'bar',
				data: { labels: dates, datasets: barDatasets },
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: baseScales( true ),
					plugins: {
						legend: {
							position: 'bottom',
							rtl: true,
							labels: { color: TEXT_COLOR, boxWidth: 9, boxHeight: 9, padding: 18 },
						},
						tooltip: { callbacks: { label: tooltipLabel } },
					},
				},
			} );
		}

		var donutCanvas = document.getElementById( 'cvsDonutChart' );
		if ( donutCanvas && breakdownList.length ) {
			new Chart( donutCanvas.getContext( '2d' ), {
				type: 'doughnut',
				data: {
					labels: breakdownList.map( function ( source ) { return source.label; } ),
					datasets: [ {
						data: breakdownList.map( function ( source ) { return source.total; } ),
						backgroundColor: breakdownList.map( function ( source ) {
							return colors[ source.key ] || FALLBACK;
						} ),
						borderColor: SURFACE,
						borderWidth: 3,
						hoverOffset: 5,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutout: '70%',
					plugins: {
						legend: { display: false },
						tooltip: {
							callbacks: {
								label: function ( context ) {
									return context.label + ': ' + faNumber.format( context.parsed || 0 );
								},
							},
						},
					},
				},
			} );
		}

		var lineCanvas = document.getElementById( 'cvsLineChart' );
		if ( lineCanvas ) {
			var total = 0;
			var cumulative = dailyTotals.map( function ( value ) {
				total += Number( value ) || 0;
				return total;
			} );

			new Chart( lineCanvas.getContext( '2d' ), {
				type: 'line',
				data: {
					labels: dates,
					datasets: [ {
						label: cvsChartData.i18n.cumulativeLabel,
						data: cumulative,
						borderColor: SUCCESS,
						backgroundColor: rgba( SUCCESS, 0.1 ),
						fill: true,
						tension: 0.3,
						pointRadius: 2,
					} ],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: baseScales( false ),
					plugins: { legend: { display: false }, tooltip: { callbacks: { label: tooltipLabel } } },
				},
			} );
		}
	}

	if ( document.fonts && document.fonts.ready ) {
		document.fonts.ready.then( renderCharts ).catch( renderCharts );
	} else {
		renderCharts();
	}
} )();
