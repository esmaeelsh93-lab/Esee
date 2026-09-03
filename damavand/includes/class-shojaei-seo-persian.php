<?php
/**
 * Persian text normalization helpers.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Persian
 */
class Shojaei_SEO_Persian {

	/**
	 * Normalize Persian/Arabic letters and half-spaces for comparison.
	 *
	 * @param string $text Input text.
	 */
	public static function normalize( string $text ): string {
		if ( '' === $text ) {
			return '';
		}

		// Arabic Yeh/Kaf/Teh Marbuta → Persian forms.
		$map = array(
			'ي' => 'ی', // Arabic yeh
			'ى' => 'ی', // Alef maksura
			'ك' => 'ک', // Arabic kaf
			'ة' => 'ه',
			'ؤ' => 'و',
			'إ' => 'ا',
			'أ' => 'ا',
			'آ' => 'ا',
			'ۀ' => 'ه',
		);
		$text = strtr( $text, $map );

		// Remove ZWNJ (نیم‌فاصله), soft hyphen, tatweel.
		$text = preg_replace( '/[\x{200C}\x{200B}\x{00AD}\x{0640}]/u', '', $text );

		// Collapse whitespace.
		$text = preg_replace( '/\s+/u', ' ', $text );

		return mb_strtolower( trim( (string) $text ), 'UTF-8' );
	}

	/**
	 * Strip common Persian plural / possessive suffixes for looser matching.
	 *
	 * @param string $text Already-normalized text.
	 */
	public static function strip_suffixes( string $text ): string {
		$suffixes = array( 'هایی', 'های', 'ها', 'ان', 'ات', 'اش', 'مان', 'تان', 'شان' );
		foreach ( $suffixes as $suffix ) {
			$len = mb_strlen( $suffix, 'UTF-8' );
			if ( mb_strlen( $text, 'UTF-8' ) > $len + 2 && mb_substr( $text, -$len, null, 'UTF-8' ) === $suffix ) {
				return mb_substr( $text, 0, -$len, 'UTF-8' );
			}
		}
		return $text;
	}

	/**
	 * Build a Unicode-aware regex that tolerates half-spaces and ی/ک variants.
	 *
	 * @param string $keyword Keyword from rules.
	 * @return string Regex with delimiters.
	 */
	public static function keyword_pattern( string $keyword ): string {
		$base = self::normalize( $keyword );
		$base = self::strip_suffixes( $base );

		if ( '' === $base ) {
			return '/(?!)/'; // never matches
		}

		$chars = preg_split( '//u', $base, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $chars ) || empty( $chars ) ) {
			return '/(?!)/';
		}

		$inner = '';
		$count = count( $chars );
		foreach ( $chars as $i => $ch ) {
			$inner .= self::char_class( $ch );
			if ( $i < $count - 1 ) {
				// Optional نیم‌فاصله or regular space between letters.
				$inner .= '(?:[\x{200C}\s])?';
			}
		}

		// Optional plural / possessive after match (with optional half-space).
		$suffix = '(?:[\x{200C}\s])?(?:ها(?:ی)?|ان|ات)?';

		return '/(?<![\p{L}\p{N}_])' . $inner . $suffix . '(?![\p{L}\p{N}_])/u';
	}

	/**
	 * Character class allowing Arabic/Persian yeh/kaf equivalents.
	 *
	 * @param string $ch Single character.
	 */
	private static function char_class( string $ch ): string {
		if ( 'ی' === $ch ) {
			return '[یيى]';
		}
		if ( 'ک' === $ch ) {
			return '[کك]';
		}
		if ( 'ه' === $ch ) {
			return '[هةۀ]';
		}
		return preg_quote( $ch, '/' );
	}
}
