<?php
/**
 * Neon emoji-style SVG icons — white bg, soft glow.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared SVG defs for neon glow.
 *
 * @return string
 */
function bahar_neon_svg_defs() {
	return '<defs>
		<linearGradient id="baharNeon" x1="0%" y1="0%" x2="100%" y2="100%">
			<stop offset="0%" stop-color="#ff6ec4"/>
			<stop offset="45%" stop-color="#c084fc"/>
			<stop offset="100%" stop-color="#60a5fa"/>
		</linearGradient>
		<filter id="baharGlow" x="-20%" y="-20%" width="140%" height="140%">
			<feGaussianBlur stdDeviation="1.8" result="b"/>
			<feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
		</filter>
	</defs>';
}

/**
 * Get inline SVG illustration for a category type.
 *
 * @param string $type Icon type slug.
 * @return string
 */
function bahar_get_category_svg( $type ) {
	$defs = bahar_neon_svg_defs();
	$g    = 'stroke="url(#baharNeon)" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" filter="url(#baharGlow)"';

	$icons = array(
		'crop' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<path ' . $g . ' d="M20 26c0-5 4-9 16-9s16 4 16 9v3l5 2.5V54H15V31.5l5-2.5v-3z"/>
			<path ' . $g . ' d="M27 19c2-3 5-5 9-5s7 2 9 5"/>
			<circle cx="27" cy="22" r="4.5" ' . $g . '/>
			<circle cx="45" cy="22" r="4.5" ' . $g . '/>
		</svg>',

		'tee' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<path ' . $g . ' d="M16 28l7-7 5 3.5 9-11 9 11 5-3.5 7 7v24H16V28z"/>
			<path ' . $g . ' d="M32 36c0-2.5 2-4.5 4-4.5s4 2 4 4.5"/>
		</svg>',

		'skirt' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<rect x="26" y="20" width="20" height="7" rx="2.5" ' . $g . '/>
			<path ' . $g . ' d="M20 27h32l-7 26c-1 3-4 5-9 5s-8-2-9-5L20 27z"/>
			<path ' . $g . ' d="M26 34h4M34 38h4M42 34h4" opacity=".7"/>
		</svg>',

		'knit' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<path ' . $g . ' d="M22 28c0-5 4-9 14-9s14 4 14 9v3c0 2-1 3-3 3H25c-2 0-3-1-3-3v-3z"/>
			<path ' . $g . ' d="M18 34h36v22c0 4-3 7-8 7H26c-5 0-8-3-8-7V34z"/>
			<path ' . $g . ' d="M36 34v22"/>
			<path ' . $g . ' d="M28 42h16M26 48h20M28 54h16" opacity=".65"/>
			<path ' . $g . ' d="M30 38c2-4 4-6 6-6s4 2 6 6" opacity=".65"/>
		</svg>',

		'sweat' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<path ' . $g . ' d="M26 24c0-4 3-7 10-7s10 3 10 7v2h4l5 5v22H17V31l5-5h4v-2z"/>
			<path ' . $g . ' d="M26 24c2-4 5-6 10-6s8 2 10 6"/>
			<rect x="30" y="38" width="12" height="10" rx="2" ' . $g . '/>
		</svg>',

		'turtle' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<ellipse cx="36" cy="24" rx="13" ry="9" ' . $g . '/>
			<path ' . $g . ' d="M23 28h26v26c0 5-4 9-13 9s-13-4-13-9V28z"/>
			<path ' . $g . ' d="M23 28c4-4 8-6 13-6s9 2 13 6"/>
			<path ' . $g . ' d="M30 40h12"/>
		</svg>',

		'shorts' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<path ' . $g . ' d="M22 28h28l-2 5H24l-2-5z"/>
			<path ' . $g . ' d="M20 33h32l-4 20c-.5 3-3 5-7 5h-5c-4 0-6.5-2-7-5l-4-20z"/>
			<path ' . $g . ' d="M36 33v20"/>
		</svg>',

		'set' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<path ' . $g . ' d="M26 16h20l2 5H24l2-5z"/>
			<path ' . $g . ' d="M24 21h24v12H24V21z"/>
			<path ' . $g . ' d="M22 33h28l-3 17c-.5 2-2 4-5 4h-12c-3 0-4.5-2-5-4l-3-17z"/>
		</svg>',

		'default' => '<svg class="bahar-neon-icon" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs . '
			<path ' . $g . ' d="M26 16h20l-3 7H29l-3-7z"/>
			<path ' . $g . ' d="M22 23h28v4c0 2-1 3-3 3H25c-2 0-3-1-3-3v-4z"/>
			<path ' . $g . ' d="M28 30v26M44 30v26"/>
			<path ' . $g . ' d="M24 56h24"/>
			<path ' . $g . ' d="M36 38c0-3 2-5 0-8"/>
			<path ' . $g . ' d="M34 36c0-2 1.5-3.5 2-3.5s2 1.5 2 3.5"/>
		</svg>',
	);

	$key = isset( $icons[ $type ] ) ? $type : 'default';
	return $icons[ $key ];
}
