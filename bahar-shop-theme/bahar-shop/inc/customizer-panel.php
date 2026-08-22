<?php
/**
 * Extended theme settings: hero, header, footer, buttons, load-more.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hero defaults — image fields stay empty so saved uploads are never overwritten by hardcoded URLs.
 *
 * @return array<string,mixed>
 */
function bahar_shop_hero_defaults() {
	return array(
		'brand'             => 'بهار شاپ',
		'tagline'           => 'انتخاب دخترای تاپ',
		'cta_text'          => 'دیدن جدیدترین‌ها',
		'cta_url'           => '',
		'brand_color'       => '#ffffff',
		'tagline_color'     => '#ffffff',
		'cta_bg'            => '',
		'cta_color'         => '#211827',
		'align_desktop'     => 'center',
		'align_mobile'      => 'top-center-cta-bottom-left',
		'img_desktop'       => '',
		'img_mobile'        => '',
		'img_desktop_id'    => 0,
		'img_mobile_id'     => 0,
		'img_alt'           => 'بهار شاپ',
		'extra_text'        => '',
		'extra_text_color'  => '#ffffff',
		'extra_btn_text'    => '',
		'extra_btn_url'     => '',
		'custom_css'        => '',
	);
}

/**
 * Resolve attachment URL from ID (preferred) or stored URL.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $fallback_url  Stored URL fallback.
 * @return string
 */
function bahar_shop_hero_resolve_image_url( $attachment_id, $fallback_url = '' ) {
	$attachment_id = absint( $attachment_id );
	if ( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	$fallback_url = is_string( $fallback_url ) ? trim( $fallback_url ) : '';
	return $fallback_url ? esc_url_raw( $fallback_url ) : '';
}

/**
 * @return array<string,mixed>
 */
function bahar_shop_hero_settings() {
	$saved = get_option( 'bahar_shop_hero', array() );
	$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_hero_defaults() );
	$aligns = array( 'center', 'top-center', 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'bottom-center', 'top-center-cta-bottom-left' );
	if ( ! in_array( $out['align_desktop'], $aligns, true ) ) {
		$out['align_desktop'] = 'center';
	}
	if ( ! in_array( $out['align_mobile'], $aligns, true ) ) {
		$out['align_mobile'] = 'top-center-cta-bottom-left';
	}
	$out['img_desktop_id'] = absint( $out['img_desktop_id'] ?? 0 );
	$out['img_mobile_id']  = absint( $out['img_mobile_id'] ?? 0 );
	$out['img_desktop']    = bahar_shop_hero_resolve_image_url( $out['img_desktop_id'], $out['img_desktop'] ?? '' );
	$out['img_mobile']     = bahar_shop_hero_resolve_image_url( $out['img_mobile_id'], $out['img_mobile'] ?? '' );
	return $out;
}

/**
 * Header defaults (theme toggle removed).
 *
 * @return array<string,mixed>
 */
function bahar_shop_header_defaults() {
	return array(
		'bg'             => '',
		'text_color'     => '',
		'show_account'   => 1,
		'show_cart'      => 1,
		'show_call'      => 0,
		'instagram_url'  => 'https://instagram.com/baharcollectionss',
		'whatsapp_url'   => 'https://wa.me/989035233046',
		'telegram_url'   => '',
		'phone_url'      => 'tel:+989035233046',
		'show_instagram' => 1,
		'show_whatsapp'  => 1,
		'show_telegram'  => 0,
		'custom_css'     => '',
	);
}

/**
 * @return array<string,mixed>
 */
function bahar_shop_header_settings() {
	$saved = get_option( 'bahar_shop_header', array() );
	$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_header_defaults() );
	foreach ( array( 'show_account', 'show_cart', 'show_call', 'show_instagram', 'show_whatsapp', 'show_telegram' ) as $flag ) {
		$out[ $flag ] = ! empty( $out[ $flag ] ) ? 1 : 0;
	}
	return $out;
}

/**
 * Footer extras defaults.
 *
 * @return array<string,mixed>
 */
function bahar_shop_footer_defaults() {
	return array(
		'description'   => 'فروشگاه آنلاین پوشاک دخترانه — استایل‌های کیوت و لباس‌های روزمره و ترند.',
		'extra_heading' => '',
		'extra_text'    => '',
		'btn1_text'     => '',
		'btn1_url'      => '',
		'btn2_text'     => '',
		'btn2_url'      => '',
		'icon1_label'   => '',
		'icon1_url'     => '',
		'icon2_label'   => '',
		'icon2_url'     => '',
		'custom_css'    => '',
	);
}

/**
 * @return array<string,mixed>
 */
function bahar_shop_footer_settings() {
	$saved = get_option( 'bahar_shop_footer', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_footer_defaults() );
}

/**
 * Button style defaults (empty = theme default).
 *
 * @return array<string,mixed>
 */
function bahar_shop_button_defaults() {
	return array(
		'visit_bg'        => '#FFF1F7',
		'visit_color'     => '#7C3AED',
		'visit_border'    => '#F1DCE8',
		'variations_bg'   => '',
		'variations_color'=> '',
		'add_cart_bg'     => '',
		'add_cart_color'  => '',
		'radius'          => 12,
		'custom_css'      => '',
	);
}

/**
 * @return array<string,mixed>
 */
function bahar_shop_button_settings() {
	$saved = get_option( 'bahar_shop_buttons', array() );
	$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_button_defaults() );
	$out['radius'] = max( 0, min( 40, (int) $out['radius'] ) );
	return $out;
}

/**
 * Load-more defaults.
 *
 * @return array<string,mixed>
 */
function bahar_shop_load_more_defaults() {
	return array(
		'enabled' => 1,
		'label'   => 'بارگذاری بیشتر',
	);
}

/**
 * @return array<string,mixed>
 */
function bahar_shop_load_more_settings() {
	$saved = get_option( 'bahar_shop_load_more', array() );
	$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_load_more_defaults() );
	$out['enabled'] = ! empty( $out['enabled'] ) ? 1 : 0;
	$out['label']   = $out['label'] ? (string) $out['label'] : 'بارگذاری بیشتر';
	return $out;
}

add_action( 'admin_init', 'bahar_shop_register_extended_settings' );

/**
 * Register extended options.
 */
function bahar_shop_register_extended_settings() {
	$opts = array(
		'bahar_shop_hero'      => 'bahar_shop_sanitize_hero',
		'bahar_shop_header'    => 'bahar_shop_sanitize_header',
		'bahar_shop_footer'    => 'bahar_shop_sanitize_footer',
		'bahar_shop_buttons'   => 'bahar_shop_sanitize_buttons',
		'bahar_shop_load_more' => 'bahar_shop_sanitize_load_more',
	);
	foreach ( $opts as $option => $callback ) {
		register_setting(
			'bahar_shop_settings',
			$option,
			array(
				'type'              => 'array',
				'sanitize_callback' => $callback,
			)
		);
	}
}

/**
 * @param mixed $input Raw.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_hero( $input ) {
	$current = get_option( 'bahar_shop_hero', array() );
	$out     = wp_parse_args( is_array( $current ) ? $current : array(), bahar_shop_hero_defaults() );
	if ( ! is_array( $input ) ) {
		return $out;
	}
	$text_keys = array( 'brand', 'tagline', 'cta_text', 'extra_text', 'extra_btn_text', 'img_alt' );
	foreach ( $text_keys as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$out[ $key ] = sanitize_text_field( $input[ $key ] );
		}
	}
	foreach ( array( 'cta_url', 'extra_btn_url' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$out[ $key ] = esc_url_raw( trim( (string) $input[ $key ] ) );
		}
	}

	foreach ( array( 'desktop', 'mobile' ) as $slot ) {
		$id_key  = 'img_' . $slot . '_id';
		$url_key = 'img_' . $slot;
		if ( isset( $input[ $id_key ] ) ) {
			$out[ $id_key ] = absint( $input[ $id_key ] );
		}
		if ( ! empty( $out[ $id_key ] ) ) {
			$resolved = wp_get_attachment_image_url( (int) $out[ $id_key ], 'full' );
			$out[ $url_key ] = $resolved ? esc_url_raw( $resolved ) : '';
		} elseif ( isset( $input[ $url_key ] ) ) {
			$out[ $url_key ] = esc_url_raw( trim( (string) $input[ $url_key ] ) );
			$out[ $id_key ]  = 0;
		}
	}

	foreach ( array( 'brand_color', 'tagline_color', 'cta_bg', 'cta_color', 'extra_text_color' ) as $key ) {
		if ( ! empty( $input[ $key ] ) ) {
			$color = sanitize_hex_color( $input[ $key ] );
			if ( $color ) {
				$out[ $key ] = $color;
			}
		} elseif ( isset( $input[ $key ] ) && '' === $input[ $key ] ) {
			$out[ $key ] = '';
		}
	}
	$aligns = array( 'center', 'top-center', 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'bottom-center', 'top-center-cta-bottom-left' );
	foreach ( array( 'align_desktop', 'align_mobile' ) as $key ) {
		if ( ! empty( $input[ $key ] ) && in_array( $input[ $key ], $aligns, true ) ) {
			$out[ $key ] = $input[ $key ];
		}
	}
	if ( isset( $input['custom_css'] ) ) {
		$out['custom_css'] = bahar_shop_sanitize_custom_css( $input['custom_css'] );
	}
	return $out;
}

/**
 * @param mixed $input Raw.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_header( $input ) {
	$current = get_option( 'bahar_shop_header', array() );
	$out     = wp_parse_args( is_array( $current ) ? $current : array(), bahar_shop_header_defaults() );
	if ( ! is_array( $input ) ) {
		return $out;
	}
	foreach ( array( 'bg', 'text_color' ) as $key ) {
		if ( ! empty( $input[ $key ] ) ) {
			$color = sanitize_hex_color( $input[ $key ] );
			$out[ $key ] = $color ? $color : '';
		} else {
			$out[ $key ] = '';
		}
	}
	foreach ( array( 'instagram_url', 'whatsapp_url', 'telegram_url' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$out[ $key ] = esc_url_raw( trim( (string) $input[ $key ] ) );
		}
	}
	if ( isset( $input['phone_url'] ) ) {
		$phone = trim( (string) $input['phone_url'] );
		if ( 0 === strpos( $phone, 'tel:' ) ) {
			$digits = preg_replace( '/[^0-9+]/', '', substr( $phone, 4 ) );
			$out['phone_url'] = $digits ? 'tel:' . $digits : '';
		} else {
			$out['phone_url'] = esc_url_raw( $phone );
		}
	}
	foreach ( array( 'show_account', 'show_cart', 'show_call', 'show_instagram', 'show_whatsapp', 'show_telegram' ) as $flag ) {
		$out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
	}
	unset( $out['show_theme_toggle'] );
	if ( isset( $input['custom_css'] ) ) {
		$out['custom_css'] = bahar_shop_sanitize_custom_css( $input['custom_css'] );
	}
	return $out;
}

/**
 * @param mixed $input Raw.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_footer( $input ) {
	$current = get_option( 'bahar_shop_footer', array() );
	$out     = wp_parse_args( is_array( $current ) ? $current : array(), bahar_shop_footer_defaults() );
	if ( ! is_array( $input ) ) {
		return $out;
	}
	foreach ( array( 'description', 'extra_heading', 'extra_text', 'btn1_text', 'btn2_text', 'icon1_label', 'icon2_label' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$out[ $key ] = sanitize_text_field( $input[ $key ] );
		}
	}
	foreach ( array( 'btn1_url', 'btn2_url', 'icon1_url', 'icon2_url' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$out[ $key ] = esc_url_raw( trim( (string) $input[ $key ] ) );
		}
	}
	if ( isset( $input['custom_css'] ) ) {
		$out['custom_css'] = bahar_shop_sanitize_custom_css( $input['custom_css'] );
	}
	return $out;
}

/**
 * @param mixed $input Raw.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_buttons( $input ) {
	$current = get_option( 'bahar_shop_buttons', array() );
	$out     = wp_parse_args( is_array( $current ) ? $current : array(), bahar_shop_button_defaults() );
	if ( ! is_array( $input ) ) {
		return $out;
	}
	foreach ( array( 'visit_bg', 'visit_color', 'visit_border', 'variations_bg', 'variations_color', 'add_cart_bg', 'add_cart_color' ) as $key ) {
		if ( ! empty( $input[ $key ] ) ) {
			$color = sanitize_hex_color( $input[ $key ] );
			$out[ $key ] = $color ? $color : $out[ $key ];
		} elseif ( isset( $input[ $key ] ) && '' === $input[ $key ] ) {
			$out[ $key ] = '';
		}
	}
	if ( isset( $input['radius'] ) ) {
		$out['radius'] = max( 0, min( 40, (int) $input['radius'] ) );
	}
	if ( isset( $input['custom_css'] ) ) {
		$out['custom_css'] = bahar_shop_sanitize_custom_css( $input['custom_css'] );
	}
	return $out;
}

/**
 * @param mixed $input Raw.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_load_more( $input ) {
	$current = get_option( 'bahar_shop_load_more', array() );
	$out     = wp_parse_args( is_array( $current ) ? $current : array(), bahar_shop_load_more_defaults() );
	if ( ! is_array( $input ) ) {
		return $out;
	}
	$out['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
	if ( isset( $input['label'] ) ) {
		$out['label'] = sanitize_text_field( $input['label'] );
	}
	return $out;
}

/**
 * Strip dangerous CSS while keeping useful rules.
 *
 * @param mixed $css Raw CSS.
 * @return string
 */
function bahar_shop_sanitize_custom_css( $css ) {
	$css = is_string( $css ) ? $css : '';
	$css = wp_check_invalid_utf8( $css );
	$css = preg_replace( '/@import\b[^;]*;/i', '', $css );
	$css = preg_replace( '/expression\s*\(/i', '', $css );
	$css = preg_replace( '/javascript\s*:/i', '', $css );
	$css = preg_replace( '/behavior\s*:/i', '', $css );
	$css = preg_replace( '/-moz-binding\s*:/i', '', $css );
	return trim( (string) $css );
}

add_action( 'wp_head', 'bahar_shop_print_customizer_css', 30 );

/**
 * Print CSS vars + custom CSS from settings.
 */
function bahar_shop_print_customizer_css() {
	$hero    = bahar_shop_hero_settings();
	$header  = bahar_shop_header_settings();
	$footer  = bahar_shop_footer_settings();
	$buttons = bahar_shop_button_settings();
	$nav     = bahar_shop_bottom_nav_colors();

	$rules = array();
	$rules[] = ':root{';
	$rules[] = '--bahar-hero-brand-color:' . esc_attr( $hero['brand_color'] ) . ';';
	$rules[] = '--bahar-hero-tagline-color:' . esc_attr( $hero['tagline_color'] ) . ';';
	$rules[] = '--bahar-hero-cta-color:' . esc_attr( $hero['cta_color'] ) . ';';
	if ( ! empty( $hero['cta_bg'] ) ) {
		$rules[] = '--bahar-hero-cta-bg:' . esc_attr( $hero['cta_bg'] ) . ';';
	}
	if ( ! empty( $header['bg'] ) ) {
		$rules[] = '--bahar-header-bg:' . esc_attr( $header['bg'] ) . ';';
	}
	if ( ! empty( $header['text_color'] ) ) {
		$rules[] = '--bahar-header-text:' . esc_attr( $header['text_color'] ) . ';';
	}
	if ( ! empty( $buttons['visit_bg'] ) ) {
		$rules[] = '--bahar-btn-visit-bg:' . esc_attr( $buttons['visit_bg'] ) . ';';
	}
	if ( ! empty( $buttons['visit_color'] ) ) {
		$rules[] = '--bahar-btn-visit-color:' . esc_attr( $buttons['visit_color'] ) . ';';
	}
	if ( ! empty( $buttons['visit_border'] ) ) {
		$rules[] = '--bahar-btn-visit-border:' . esc_attr( $buttons['visit_border'] ) . ';';
	}
	if ( ! empty( $buttons['variations_bg'] ) ) {
		$rules[] = '--bahar-btn-variations-bg:' . esc_attr( $buttons['variations_bg'] ) . ';';
	}
	if ( ! empty( $buttons['variations_color'] ) ) {
		$rules[] = '--bahar-btn-variations-color:' . esc_attr( $buttons['variations_color'] ) . ';';
	}
	if ( ! empty( $buttons['add_cart_bg'] ) ) {
		$rules[] = '--bahar-btn-cart-bg:' . esc_attr( $buttons['add_cart_bg'] ) . ';';
	}
	if ( ! empty( $buttons['add_cart_color'] ) ) {
		$rules[] = '--bahar-btn-cart-color:' . esc_attr( $buttons['add_cart_color'] ) . ';';
	}
	$rules[] = '--bahar-btn-radius:' . (int) $buttons['radius'] . 'px;';
	$rules[] = '}';

	if ( ! empty( $header['bg'] ) ) {
		$rules[] = '.main-header.glass-bar{background:' . esc_attr( $header['bg'] ) . ' !important;}';
	}
	if ( ! empty( $header['text_color'] ) ) {
		$rules[] = '.main-header,.main-header a,.main-header .icon{color:' . esc_attr( $header['text_color'] ) . ';}';
	}

	$rules[] = '.hero--photo .hero__brand{color:var(--bahar-hero-brand-color,#fff);}';
	$rules[] = '.hero--photo .hero__tagline{color:var(--bahar-hero-tagline-color,#fff);}';
	$rules[] = '.hero--photo .hero__cta{color:var(--bahar-hero-cta-color,#2a2438);}';
	$rules[] = '.hero--photo .hero__cta{background:var(--bahar-hero-cta-bg,linear-gradient(135deg,#7C3AED,#EC4899));}';

	$rules[] = '.bahar-product-card__visit{background:var(--bahar-btn-visit-bg,#FFF1F7);color:var(--bahar-btn-visit-color,#7C3AED);border-color:var(--bahar-btn-visit-border,#F1DCE8);border-radius:var(--bahar-btn-radius,12px);}';
	$rules[] = '.bahar-variations-ui .bahar-attr-btn,.bahar-product-card__actions .button{border-radius:var(--bahar-btn-radius,12px);}';
	if ( ! empty( $buttons['variations_bg'] ) || ! empty( $buttons['variations_color'] ) ) {
		$rules[] = '.bahar-variations-ui .bahar-attr-btn,.single_variation_wrap .button{';
		if ( ! empty( $buttons['variations_bg'] ) ) {
			$rules[] = 'background:' . esc_attr( $buttons['variations_bg'] ) . ' !important;';
		}
		if ( ! empty( $buttons['variations_color'] ) ) {
			$rules[] = 'color:' . esc_attr( $buttons['variations_color'] ) . ' !important;';
		}
		$rules[] = '}';
	}
	if ( ! empty( $buttons['add_cart_bg'] ) || ! empty( $buttons['add_cart_color'] ) ) {
		$rules[] = '.bahar-product-card__actions .button,.single_add_to_cart_button,.bahar-sticky-cart__button{';
		if ( ! empty( $buttons['add_cart_bg'] ) ) {
			$rules[] = 'background:' . esc_attr( $buttons['add_cart_bg'] ) . ' !important;';
		}
		if ( ! empty( $buttons['add_cart_color'] ) ) {
			$rules[] = 'color:' . esc_attr( $buttons['add_cart_color'] ) . ' !important;';
		}
		$rules[] = '}';
	}

	echo '<style id="bahar-customizer-css">' . implode( '', $rules ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	$chunks = array();
	if ( ! empty( $hero['custom_css'] ) ) {
		$chunks[] = "/* hero */\n" . $hero['custom_css'];
	}
	if ( ! empty( $header['custom_css'] ) ) {
		$chunks[] = "/* header */\n" . $header['custom_css'];
	}
	if ( ! empty( $footer['custom_css'] ) ) {
		$chunks[] = "/* footer */\n" . $footer['custom_css'];
	}
	if ( ! empty( $buttons['custom_css'] ) ) {
		$chunks[] = "/* buttons */\n" . $buttons['custom_css'];
	}
	if ( ! empty( $nav['custom_css'] ) ) {
		$chunks[] = "/* bottom-nav */\n" . $nav['custom_css'];
	}
	if ( $chunks ) {
		echo '<style id="bahar-user-custom-css">' . implode( "\n", $chunks ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
