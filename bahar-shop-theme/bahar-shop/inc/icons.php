<?php
/**
 * Safe girly SVG icons (theme-local only).
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowed icon basenames (no path, no user-uploaded files).
 *
 * @return string[]
 */
function bahar_shop_icon_allowlist() {
	return array(
		'badge-percent',
		'bell-ring',
		'circle-user-round',
		'crown',
		'flower-2',
		'footprints',
		'gem',
		'gift',
		'heart',
		'house-heart',
		'layout-grid',
		'menu',
		'message-circle-heart',
		'palette',
		'party-popper',
		'rotate-ccw',
		'ruler',
		'search',
		'share-2',
		'shield-check',
		'shirt',
		'shopping-bag',
		'sparkles',
		'spray-can',
		'star',
		'tags',
		'truck',
		'wand-sparkles',
		'x',
	);
}

/**
 * Absolute path to a theme icon, or empty if invalid.
 *
 * @param string $name Icon basename without .svg.
 * @return string
 */
function bahar_shop_icon_path( $name ) {
	$name = sanitize_file_name( (string) $name );
	$name = preg_replace( '/\.svg$/i', '', $name );
	if ( ! $name || ! in_array( $name, bahar_shop_icon_allowlist(), true ) ) {
		return '';
	}
	$path = BAHAR_SHOP_DIR . '/assets/icons/girly/' . $name . '.svg';
	return is_readable( $path ) ? $path : '';
}

/**
 * Strip risky SVG bits (defense in depth for static theme files).
 *
 * @param string $svg Raw SVG.
 * @return string
 */
function bahar_shop_sanitize_svg_markup( $svg ) {
	$svg = (string) $svg;
	$svg = preg_replace( '/<\?xml[^>]*\?>/i', '', $svg );
	$svg = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $svg );
	$svg = preg_replace( '/<!--.*?-->/s', '', $svg );
	$svg = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $svg );
	$svg = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg );
	$svg = preg_replace( '/javascript\s*:/i', '', $svg );
	$svg = preg_replace( '/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $svg );
	$svg = preg_replace( '/xlink:href\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg );
	return trim( $svg );
}

/**
 * Inline SVG markup for a theme icon.
 *
 * @param string               $name Allowlisted icon name.
 * @param array<string,string> $atts Extra attrs: class.
 * @return string
 */
function bahar_shop_icon( $name, $atts = array() ) {
	$path = bahar_shop_icon_path( $name );
	if ( ! $path ) {
		return '';
	}

	$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme asset.
	if ( false === $raw ) {
		return '';
	}

	$svg = bahar_shop_sanitize_svg_markup( $raw );
	if ( ! preg_match( '/<svg\b/i', $svg ) ) {
		return '';
	}

	$extra = '';
	if ( ! empty( $atts['class'] ) ) {
		$parts = preg_split( '/\s+/', (string) $atts['class'] );
		$clean = array();
		foreach ( (array) $parts as $part ) {
			$part = sanitize_html_class( $part );
			if ( $part ) {
				$clean[] = $part;
			}
		}
		$extra = implode( ' ', $clean );
	}

	$class = trim( 'bahar-icon bahar-icon--' . sanitize_html_class( $name ) . ' ' . $extra );

	$svg = preg_replace( '/\s(width|height)="24"/i', '', $svg );
	$svg = preg_replace(
		'/<svg\b([^>]*)>/i',
		'<svg class="' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"$1>',
		$svg,
		1
	);

	return $svg;
}

/**
 * Echo icon.
 *
 * @param string               $name Icon name.
 * @param array<string,string> $atts Attributes.
 */
function bahar_shop_the_icon( $name, $atts = array() ) {
	// Sanitized allowlisted SVG from theme directory only.
	echo bahar_shop_icon( $name, $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
