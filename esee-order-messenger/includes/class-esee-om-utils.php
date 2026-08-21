<?php
/**
 * Shared helpers: phone numbers and message templates.
 *
 * @package Esee_Order_Messenger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Esee_OM_Utils {

	/**
	 * Normalize an Iranian (or international) phone to digits with country code, no plus.
	 * Examples: 09121234567 → 989121234567, +98 912 123 4567 → 989121234567
	 */
	public static function normalize_phone( $phone ) {
		$phone = preg_replace( '/\D+/', '', (string) $phone );

		if ( '' === $phone ) {
			return '';
		}

		if ( 0 === strpos( $phone, '0098' ) ) {
			$phone = substr( $phone, 4 );
		} elseif ( 0 === strpos( $phone, '98' ) ) {
			$phone = substr( $phone, 2 );
		}

		if ( 0 === strpos( $phone, '0' ) ) {
			$phone = substr( $phone, 1 );
		}

		if ( 10 === strlen( $phone ) ) {
			return '98' . $phone;
		}

		if ( strlen( $phone ) >= 10 ) {
			return '98' . ltrim( $phone, '0' );
		}

		return '';
	}

	/**
	 * @param string $template
	 * @param array  $tokens   key => value, without braces.
	 */
	public static function render_template( $template, array $tokens ) {
		$replacements = array();
		foreach ( $tokens as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = (string) $value;
		}

		return strtr( (string) $template, $replacements );
	}

	/**
	 * @param mixed $channels
	 * @return string[]
	 */
	public static function sanitize_channels( $channels ) {
		$allowed = array( 'whatsapp', 'rubika', 'bale' );
		$out     = array();

		if ( ! is_array( $channels ) ) {
			$channels = array( $channels );
		}

		foreach ( $channels as $channel ) {
			$channel = strtolower( (string) $channel );
			$channel = preg_replace( '/[^a-z0-9_]/', '', $channel );
			if ( in_array( $channel, $allowed, true ) ) {
				$out[] = $channel;
			}
		}

		return array_values( array_unique( $out ) );
	}
}
