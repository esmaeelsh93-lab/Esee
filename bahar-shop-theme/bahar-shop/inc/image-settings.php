<?php
/**
 * Product / gallery image fit + dimension settings.
 *
 * «حالت پریسا» = object-fit:contain (حالت فعلی امن).
 * «پر کردن قاب» = object-fit:cover بدون کش آمدن عکس.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default image display settings.
 *
 * @return array<string,mixed>
 */
function bahar_shop_image_settings_defaults() {
	return array(
		'fit_mode'              => 'cover',
		'card_height_desktop'   => 360,
		'card_height_mobile'    => 280,
		'gallery_fit'           => 'cover',
	);
}

/**
 * Saved image settings.
 *
 * @return array<string,mixed>
 */
function bahar_shop_image_settings() {
	$saved = get_option( 'bahar_shop_image_settings', array() );
	$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_image_settings_defaults() );

	$fit = isset( $out['fit_mode'] ) ? (string) $out['fit_mode'] : 'cover';
	$out['fit_mode'] = in_array( $fit, array( 'cover', 'contain', 'parisa' ), true )
		? ( 'parisa' === $fit ? 'contain' : $fit )
		: 'cover';

	$gallery = isset( $out['gallery_fit'] ) ? (string) $out['gallery_fit'] : $out['fit_mode'];
	$out['gallery_fit'] = in_array( $gallery, array( 'cover', 'contain', 'parisa' ), true )
		? ( 'parisa' === $gallery ? 'contain' : $gallery )
		: $out['fit_mode'];

	$out['card_height_desktop'] = max( 180, min( 640, (int) $out['card_height_desktop'] ) );
	$out['card_height_mobile']  = max( 140, min( 480, (int) $out['card_height_mobile'] ) );

	return $out;
}

add_action( 'admin_init', 'bahar_shop_register_image_settings' );

/**
 * Register image settings option.
 */
function bahar_shop_register_image_settings() {
	register_setting(
		'bahar_shop_settings',
		'bahar_shop_image_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bahar_shop_sanitize_image_settings',
			'default'           => bahar_shop_image_settings_defaults(),
		)
	);
}

/**
 * Sanitize image settings.
 *
 * @param mixed $input Raw.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_image_settings( $input ) {
	$out = bahar_shop_image_settings_defaults();
	if ( ! is_array( $input ) ) {
		return $out;
	}

	if ( ! empty( $input['fit_mode'] ) ) {
		$fit = sanitize_key( (string) $input['fit_mode'] );
		if ( 'parisa' === $fit ) {
			$out['fit_mode'] = 'contain';
		} elseif ( in_array( $fit, array( 'cover', 'contain' ), true ) ) {
			$out['fit_mode'] = $fit;
		}
	}

	if ( ! empty( $input['gallery_fit'] ) ) {
		$fit = sanitize_key( (string) $input['gallery_fit'] );
		if ( 'parisa' === $fit ) {
			$out['gallery_fit'] = 'contain';
		} elseif ( in_array( $fit, array( 'cover', 'contain' ), true ) ) {
			$out['gallery_fit'] = $fit;
		}
	}

	if ( isset( $input['card_height_desktop'] ) ) {
		$out['card_height_desktop'] = max( 180, min( 640, (int) $input['card_height_desktop'] ) );
	}
	if ( isset( $input['card_height_mobile'] ) ) {
		$out['card_height_mobile'] = max( 140, min( 480, (int) $input['card_height_mobile'] ) );
	}

	return $out;
}

add_action( 'wp_head', 'bahar_shop_image_settings_css_vars', 22 );
add_filter( 'body_class', 'bahar_shop_image_settings_body_class' );

/**
 * Expose fit mode as body class for CSS hooks.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function bahar_shop_image_settings_body_class( $classes ) {
	$s = bahar_shop_image_settings();
	$classes[] = 'bahar-img-fit--' . $s['fit_mode'];
	$classes[] = 'bahar-gallery-fit--' . $s['gallery_fit'];
	return $classes;
}

/**
 * Inline CSS vars for card image dimensions + fit.
 */
function bahar_shop_image_settings_css_vars() {
	$s = bahar_shop_image_settings();
	printf(
		'<style id="bahar-image-settings-vars">:root{--bahar-card-img-fit:%1$s;--bahar-gallery-img-fit:%2$s;--bahar-card-img-h-desktop:%3$dpx;--bahar-card-img-h-mobile:%4$dpx;}</style>' . "\n",
		esc_attr( $s['fit_mode'] ),
		esc_attr( $s['gallery_fit'] ),
		(int) $s['card_height_desktop'],
		(int) $s['card_height_mobile']
	);
}
