<?php
/**
 * Plugin settings storage and sanitization.
 *
 * @package WooCommerce_Bulk_Order_Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings helper.
 */
class WBOP_Settings {

	const OPTION = 'wbop_settings';

	const PRINTED_OPTION = 'wbop_printed_order_ids';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'sender_name'    => '',
			'sender_address' => '',
			'sender_phone'   => '',
			'header_image'   => 0,
			'paper_type'     => 'a5',
			'paper_width'    => 14.8,
			'paper_height'   => 21.0,
			'print_margin'   => 7.0,
			// One step larger than the original compact 11px print size.
			'font_size'      => 'lg',
		);
	}

	/**
	 * Allowed paper types.
	 *
	 * @return array
	 */
	public static function paper_types() {
		return array(
			'a4'     => 'A4',
			'a5'     => 'A5',
			'a6'     => 'A6',
			'letter' => 'Letter',
			'custom' => 'ابعاد سفارشی',
		);
	}

	/**
	 * Allowed print font size presets (px for body / title / meta).
	 *
	 * @return array
	 */
	public static function font_sizes() {
		return array(
			'xs'  => array(
				'label' => 'خیلی کوچک',
				'body'  => 9,
				'title' => 11,
				'meta'  => 8,
			),
			'sm'  => array(
				'label' => 'کوچک',
				'body'  => 10,
				'title' => 12,
				'meta'  => 9,
			),
			'md'  => array(
				'label' => 'معمولی (قبلی)',
				'body'  => 11,
				'title' => 14,
				'meta'  => 10,
			),
			'lg'  => array(
				'label' => 'کمی بزرگ (پیشنهادی)',
				'body'  => 12,
				'title' => 15,
				'meta'  => 11,
			),
			'xl'  => array(
				'label' => 'بزرگ',
				'body'  => 13,
				'title' => 16,
				'meta'  => 12,
			),
			'xxl' => array(
				'label' => 'خیلی بزرگ',
				'body'  => 15,
				'title' => 18,
				'meta'  => 13,
			),
		);
	}

	/**
	 * Resolve a safe font size preset.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_font_size( $value ) {
		$key = sanitize_key( (string) $value );
		$sizes = self::font_sizes();
		if ( ! array_key_exists( $key, $sizes ) ) {
			return 'lg';
		}
		return $key;
	}

	/**
	 * Font metrics for print CSS.
	 *
	 * @param array|null $settings Settings override.
	 * @return array{body:int,title:int,meta:int}
	 */
	public static function font_metrics( $settings = null ) {
		$settings = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : self::get();
		$key      = self::sanitize_font_size( isset( $settings['font_size'] ) ? $settings['font_size'] : 'lg' );
		$sizes    = self::font_sizes();
		return array(
			'body'  => (int) $sizes[ $key ]['body'],
			'title' => (int) $sizes[ $key ]['title'],
			'meta'  => (int) $sizes[ $key ]['meta'],
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get one setting field.
	 *
	 * @param string $key     Field key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_field( $key, $default = '' ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Sanitize a custom dimension in centimeters.
	 *
	 * @param mixed  $value   Raw value.
	 * @param float  $fallback Fallback.
	 * @return float
	 */
	public static function sanitize_dimension( $value, $fallback ) {
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', $value );
		}

		if ( ! is_numeric( $value ) ) {
			return (float) $fallback;
		}

		$value = round( (float) $value, 1 );
		if ( $value < 5 || $value > 100 ) {
			return (float) $fallback;
		}

		return $value;
	}

	/**
	 * Sanitize print margin in millimeters.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function sanitize_margin( $value ) {
		$defaults = self::defaults();
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', $value );
		}
		if ( ! is_numeric( $value ) ) {
			return (float) $defaults['print_margin'];
		}

		$value = round( (float) $value, 1 );
		if ( $value < 3 || $value > 30 ) {
			return (float) $defaults['print_margin'];
		}

		return $value;
	}

	/**
	 * Sanitize settings array from form submission.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;

		$out['sender_name']    = isset( $input['sender_name'] ) ? sanitize_text_field( wp_unslash( $input['sender_name'] ) ) : '';
		$out['sender_address'] = isset( $input['sender_address'] ) ? sanitize_textarea_field( wp_unslash( $input['sender_address'] ) ) : '';
		$out['sender_phone']   = isset( $input['sender_phone'] ) ? sanitize_text_field( wp_unslash( $input['sender_phone'] ) ) : '';

		$header_image = isset( $input['header_image'] ) ? absint( $input['header_image'] ) : 0;
		if ( $header_image > 0 ) {
			$mime = get_post_mime_type( $header_image );
			if ( ! $mime || 0 !== strpos( $mime, 'image/' ) ) {
				$header_image = 0;
			}
		}
		$out['header_image'] = $header_image;

		$paper_type = isset( $input['paper_type'] ) ? sanitize_key( wp_unslash( $input['paper_type'] ) ) : 'a5';
		if ( ! array_key_exists( $paper_type, self::paper_types() ) ) {
			$paper_type = 'a5';
		}
		$out['paper_type'] = $paper_type;

		$out['paper_width']  = self::sanitize_dimension(
			isset( $input['paper_width'] ) ? wp_unslash( $input['paper_width'] ) : $defaults['paper_width'],
			$defaults['paper_width']
		);
		$out['paper_height'] = self::sanitize_dimension(
			isset( $input['paper_height'] ) ? wp_unslash( $input['paper_height'] ) : $defaults['paper_height'],
			$defaults['paper_height']
		);
		$out['print_margin'] = self::sanitize_margin(
			isset( $input['print_margin'] ) ? wp_unslash( $input['print_margin'] ) : $defaults['print_margin']
		);

		$out['font_size'] = self::sanitize_font_size(
			isset( $input['font_size'] ) ? wp_unslash( $input['font_size'] ) : $defaults['font_size']
		);

		return $out;
	}

	/**
	 * Build safe @page CSS size declaration.
	 *
	 * @param array|null $settings Settings override.
	 * @return string
	 */
	public static function page_size_css( $settings = null ) {
		$settings = is_array( $settings ) ? wp_parse_args( $settings, self::defaults() ) : self::get();
		$margin   = self::sanitize_margin( $settings['print_margin'] );
		$type     = isset( $settings['paper_type'] ) ? sanitize_key( $settings['paper_type'] ) : 'a5';

		$map = array(
			'a4'     => 'A4 portrait',
			'a5'     => 'A5 portrait',
			'a6'     => 'A6 portrait',
			'letter' => 'Letter portrait',
		);

		if ( isset( $map[ $type ] ) ) {
			$size = $map[ $type ];
		} else {
			$width  = self::sanitize_dimension( $settings['paper_width'], 14.8 );
			$height = self::sanitize_dimension( $settings['paper_height'], 21.0 );
			$size   = $width . 'cm ' . $height . 'cm';
		}

		return '@page{size:' . $size . ';margin:' . $margin . 'mm;}';
	}

	/**
	 * Get printed order IDs map.
	 *
	 * @return array<int,int> order_id => timestamp
	 */
	public static function get_printed_map() {
		$map = get_option( self::PRINTED_OPTION, array() );
		if ( ! is_array( $map ) ) {
			return array();
		}

		$clean = array();
		foreach ( $map as $order_id => $timestamp ) {
			$order_id  = absint( $order_id );
			$timestamp = absint( $timestamp );
			if ( $order_id > 0 && $timestamp > 0 ) {
				$clean[ $order_id ] = $timestamp;
			}
		}

		return $clean;
	}

	/**
	 * Whether an order was printed before.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public static function is_printed( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return false;
		}
		$map = self::get_printed_map();
		return isset( $map[ $order_id ] );
	}

	/**
	 * Mark order IDs as printed.
	 *
	 * @param array $order_ids Order IDs.
	 * @return void
	 */
	public static function mark_printed( $order_ids ) {
		$map = self::get_printed_map();
		$now = time();
		foreach ( (array) $order_ids as $order_id ) {
			$order_id = absint( $order_id );
			if ( $order_id > 0 ) {
				$map[ $order_id ] = $now;
			}
		}

		// Keep map from growing forever.
		if ( count( $map ) > 5000 ) {
			asort( $map );
			$map = array_slice( $map, -4000, null, true );
		}

		update_option( self::PRINTED_OPTION, $map, false );
	}
}
