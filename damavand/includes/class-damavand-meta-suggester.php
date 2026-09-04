<?php
/**
 * Systemic meta title/description suggestions from product copy (no AI).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Meta_Suggester
 */
final class Damavand_Meta_Suggester {

	private const TITLE_TARGET = 68.0;
	private const DESC_TARGET  = 165.0;

	/**
	 * Build meta title + description from short/long product text.
	 *
	 * @param int    $post_id    Post ID (context).
	 * @param string $short_desc Product short description / excerpt HTML.
	 * @param string $long_desc  Product long description HTML.
	 * @param string $focus      Focus keyword.
	 * @param string $title      Product title.
	 * @return array{title:string,desc:string}
	 */
	public static function suggest( int $post_id, string $short_desc, string $long_desc, string $focus, string $title ): array {
		$short = self::plain( $short_desc );
		$long  = self::plain( $long_desc );
		$focus = self::plain( $focus );
		$title = self::plain( $title );

		if ( '' === $focus ) {
			$focus = $title;
		}

		$desc_source = '' !== $short ? $short : self::lead_from_long( $long );
		if ( '' === $desc_source && '' !== $long ) {
			$desc_source = $long;
		}
		if ( '' === $desc_source ) {
			$desc_source = $focus;
		}

		return array(
			'title' => self::suggest_title( $post_id, $focus, $title, $short, $long ),
			'desc'  => self::suggest_description( $desc_source, $focus ),
		);
	}

	/**
	 * @param string $html Raw HTML/text.
	 */
	private static function plain( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', trim( (string) $text ) );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * First meaningful block from long HTML.
	 */
	private static function lead_from_long( string $long ): string {
		if ( '' === $long ) {
			return '';
		}
		$parts = preg_split( '/(?:\.\s+|!\s+|\?\s+|۔\s+)/u', $long );
		if ( ! is_array( $parts ) ) {
			return self::trim_chars( $long, 220 );
		}
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( mb_strlen( $part, 'UTF-8' ) >= 20 ) {
				return $part;
			}
		}
		return self::trim_chars( $long, 220 );
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $focus   Focus keyword.
	 * @param string $title   Product title.
	 * @param string $short   Short desc plain.
	 * @param string $long    Long desc plain.
	 */
	private static function suggest_title( int $post_id, string $focus, string $title, string $short, string $long ): string {
		unset( $long );
		$sep  = class_exists( 'Shojaei_SEO_General_Meta' ) ? Shojaei_SEO_General_Meta::get_separator() : '-';
		$site = wp_strip_all_tags( get_bloginfo( 'name' ) );

		$candidates = array();

		if ( false === mb_stripos( $focus, 'خرید', 0, 'UTF-8' ) ) {
			$candidates[] = 'خرید ' . $focus;
		} else {
			$candidates[] = $focus;
		}

		if ( '' !== $short ) {
			$phrase = self::first_sentence( $short, 48 );
			if ( '' !== $phrase && mb_strtolower( $phrase, 'UTF-8' ) !== mb_strtolower( $focus, 'UTF-8' ) ) {
				if ( false === mb_stripos( $phrase, 'خرید', 0, 'UTF-8' ) ) {
					$candidates[] = trim( $phrase . ' ' . $sep . ' خرید' );
				} else {
					$candidates[] = $phrase;
				}
			}
		}

		$candidates[] = trim( $focus . ' ' . $sep . ' ' . $site );
		if ( false === mb_stripos( $title, 'خرید', 0, 'UTF-8' ) && $title !== $focus ) {
			$candidates[] = 'خرید ' . $title;
		}

		$best = '';
		foreach ( $candidates as $candidate ) {
			$candidate = Damavand_SEO_Templates::cleanup( $candidate );
			if ( '' === $candidate ) {
				continue;
			}
			$w = self::weighted( $candidate );
			if ( '' === $best || ( $w <= self::TITLE_TARGET && $w > self::weighted( $best ) ) ) {
				$best = $candidate;
			}
			if ( $w >= 38 && $w <= self::TITLE_TARGET ) {
				return $candidate;
			}
		}

		return self::trim_by_weight( '' !== $best ? $best : $candidates[0], self::TITLE_TARGET );
	}

	/**
	 * @param string $source Plain text source.
	 * @param string $focus  Focus keyword for fallback.
	 */
	private static function suggest_description( string $source, string $focus ): string {
		$desc = self::first_sentence( $source, 280 );
		if ( '' === $desc ) {
			$desc = $focus;
		}
		$desc = self::ensure_buy_cta( $desc );
		return self::trim_by_weight( $desc, self::DESC_TARGET );
	}

	/**
	 * @param string $text   Text.
	 * @param float  $target Weight target.
	 */
	private static function trim_by_weight( string $text, float $target ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text ) {
			return '';
		}
		if ( self::weighted( $text ) <= $target ) {
			return $text;
		}
		$words = preg_split( '/\s+/u', $text );
		if ( ! is_array( $words ) ) {
			return self::trim_chars( $text, (int) floor( $target * 1.2 ) );
		}
		$out = '';
		foreach ( $words as $word ) {
			$try = trim( $out . ' ' . $word );
			if ( self::weighted( $try ) > $target ) {
				break;
			}
			$out = $try;
		}
		$out = rtrim( $out, '،,.؛' );
		if ( '' === $out ) {
			return self::trim_chars( $text, (int) floor( $target * 1.2 ) );
		}
		return $out;
	}

	/**
	 * @param string $text Text.
	 */
	private static function ensure_buy_cta( string $text ): string {
		$cta = 'همین حالا سفارش دهید.';
		if ( false !== mb_stripos( $text, 'سفارش', 0, 'UTF-8' ) || false !== mb_stripos( $text, 'خرید', 0, 'UTF-8' ) ) {
			return $text;
		}
		$with = trim( $text . ' ' . $cta );
		if ( self::weighted( $with ) <= self::DESC_TARGET ) {
			return $with;
		}
		return $text;
	}

	/**
	 * @param string $text       Text.
	 * @param int    $max_chars  Max characters for first chunk.
	 */
	private static function first_sentence( string $text, int $max_chars ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		if ( preg_match( '/^(.+?(?:[.!?؟]|$))/u', $text, $m ) ) {
			$text = trim( (string) $m[1] );
		}
		return self::trim_chars( $text, $max_chars );
	}

	/**
	 * @param string $text Text.
	 * @param int    $max  Max UTF-8 chars.
	 */
	private static function trim_chars( string $text, int $max ): string {
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, max( 1, $max - 1 ), 'UTF-8' );
		return rtrim( $cut, '،,.؛ ' ) . '…';
	}

	/**
	 * @param string $text Text.
	 */
	private static function weighted( string $text ): float {
		if ( class_exists( 'Damavand_Persian_Text' ) ) {
			return Damavand_Persian_Text::weighted_length( $text );
		}
		return (float) mb_strlen( $text, 'UTF-8' );
	}
}
