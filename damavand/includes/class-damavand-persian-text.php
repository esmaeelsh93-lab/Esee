<?php
/**
 * Central Persian text normalization for SEO analysis.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Persian_Text
 */
final class Damavand_Persian_Text {

	/**
	 * Normalize Persian text for matching and counting.
	 *
	 * @param string $text Raw text.
	 */
	public static function normalize( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = str_replace(
			array( 'ي', 'ك', 'ة', 'ؤ', 'أ', 'إ', 'ۀ', 'ـ' ),
			array( 'ی', 'ک', 'ه', 'و', 'ا', 'ا', 'ه', '' ),
			$text
		);
		$text = self::normalize_digits( $text );
		$text = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( mb_strtolower( (string) $text, 'UTF-8' ) );
	}

	/**
	 * Unify Persian / Arabic / Latin digits to ASCII.
	 *
	 * @param string $text Text.
	 */
	public static function normalize_digits( string $text ): string {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), $text );
	}

	/**
	 * Light Persian stem for density checks.
	 *
	 * @param string $word Word.
	 */
	public static function stem( string $word ): string {
		$word = self::normalize( $word );
		if ( mb_strlen( $word, 'UTF-8' ) < 3 ) {
			return $word;
		}
		$suffixes = array( 'هایی', 'های', 'ها', 'تان', 'تون', 'مان', 'مون', 'شان', 'شون', 'ترین', 'تر', 'ات', 'ان', 'ین', 'ون', 'ی' );
		foreach ( $suffixes as $suf ) {
			$len = mb_strlen( $suf, 'UTF-8' );
			if ( mb_strlen( $word, 'UTF-8' ) > $len + 2 && mb_substr( $word, -$len, null, 'UTF-8' ) === $suf ) {
				return mb_substr( $word, 0, mb_strlen( $word, 'UTF-8' ) - $len, 'UTF-8' );
			}
		}
		return $word;
	}

	/**
	 * SERP weighted length (Persian glyphs wider than Latin).
	 *
	 * @param string $text Text.
	 */
	public static function weighted_length( string $text ): float {
		$text = wp_strip_all_tags( $text );
		$len  = mb_strlen( $text, 'UTF-8' );
		$w    = 0.0;
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = mb_substr( $text, $i, 1, 'UTF-8' );
			if ( preg_match( '/[\x{0600}-\x{06FF}]/u', $ch ) ) {
				$w += 1.4;
			} elseif ( ' ' === $ch ) {
				$w += 0.5;
			} else {
				$w += 1.0;
			}
		}
		return round( $w, 1 );
	}

	/**
	 * Keyword presence with normalization and light stemming.
	 *
	 * @param string $haystack Content.
	 * @param string $needle   Focus keyword.
	 */
	public static function contains_keyword( string $haystack, string $needle ): bool {
		$needle = self::normalize( $needle );
		if ( '' === $needle ) {
			return false;
		}
		$hay = self::normalize( $haystack );
		if ( false !== mb_strpos( $hay, $needle, 0, 'UTF-8' ) ) {
			return true;
		}
		$stem = self::stem( $needle );
		if ( $stem !== $needle && false !== mb_strpos( $hay, $stem, 0, 'UTF-8' ) ) {
			return true;
		}
		$compact_h = preg_replace( '/\s+/u', '', $hay );
		$compact_n = preg_replace( '/\s+/u', '', $needle );
		return $compact_n && false !== mb_strpos( (string) $compact_h, (string) $compact_n, 0, 'UTF-8' );
	}

	/**
	 * Arabic ye/ke in text.
	 *
	 * @param string $text Text.
	 */
	public static function has_arabic_ye_ke( string $text ): bool {
		return (bool) preg_match( '/[يك]/u', $text );
	}

	/**
	 * Focus keyword near start of title (first ~40% weighted).
	 *
	 * @param string $title Title.
	 * @param string $focus Focus keyword.
	 */
	public static function focus_near_start( string $title, string $focus ): bool {
		$title = self::normalize( $title );
		$focus = self::normalize( $focus );
		if ( '' === $focus || '' === $title ) {
			return false;
		}
		$cut = (int) max( 8, mb_strlen( $title, 'UTF-8' ) * 0.4 );
		$head = mb_substr( $title, 0, $cut, 'UTF-8' );
		return self::contains_keyword( $head, $focus );
	}

	/**
	 * Word count (Persian-aware, ZWNJ kept inside tokens).
	 *
	 * @param string $plain Plain text.
	 */
	public static function word_count( string $plain ): int {
		$plain = self::normalize( $plain );
		if ( '' === $plain ) {
			return 0;
		}
		$words = preg_split( '/\s+/u', $plain );
		return is_array( $words ) ? count( array_filter( $words ) ) : 0;
	}

	/**
	 * Structural readability metrics for Persian content (guide §3.5).
	 *
	 * @param string $plain Plain text.
	 * @param string $html  Optional HTML for subheading proximity.
	 * @return array<string,int|float>
	 */
	public static function readability_metrics( string $plain, string $html = '' ): array {
		$plain = wp_strip_all_tags( $plain );
		if ( '' === trim( $plain ) ) {
			return array(
				'avg_sentence'    => 0,
				'long_pct'        => 0,
				'passive_count'   => 0,
				'para_no_h_pct'   => 0,
				'sentence_count'  => 0,
			);
		}

		$sentences = preg_split( '/[.!?؟۔\n]+/u', $plain );
		$sentences = is_array( $sentences ) ? array_values( array_filter( array_map( 'trim', $sentences ) ) ) : array();
		$s_count   = count( $sentences );
		$total_w   = 0;
		$long      = 0;
		foreach ( $sentences as $s ) {
			$wc = self::word_count( $s );
			$total_w += $wc;
			if ( $wc > 25 ) {
				++$long;
			}
		}

		$passive = 0;
		if ( preg_match_all( '/[^\n.!?؟۔]{10,120}?(?:شد|می‌شود|گردید|می‌گردد|بود|می‌باشد)[.!?؟۔\n]/u', $plain, $pm ) ) {
			$passive = count( $pm[0] );
		}

		$para_no_h = 0;
		$para_total = 0;
		if ( '' !== $html && preg_match_all( '/<p[^>]*>(.*?)<\/p>/isu', $html, $paras ) && ! empty( $paras[0] ) ) {
			$chunks = $paras[0];
		} else {
			$chunks = preg_split( '/\n{2,}/u', $plain );
			$chunks = is_array( $chunks ) ? $chunks : array();
		}
		$full = $html ?: $plain;
		foreach ( $chunks as $i => $chunk ) {
			++$para_total;
			$pos = mb_strpos( $full, is_string( $chunk ) ? $chunk : '', 0, 'UTF-8' );
			if ( false === $pos ) {
				continue;
			}
			$before = mb_substr( $full, max( 0, $pos - 600 ), 600, 'UTF-8' );
			if ( ! preg_match( '/<h[23][\s>]/i', $before ) ) {
				++$para_no_h;
			}
		}

		return array(
			'avg_sentence'   => $s_count > 0 ? round( $total_w / $s_count, 1 ) : 0,
			'long_pct'       => $s_count > 0 ? (int) round( ( $long / $s_count ) * 100 ) : 0,
			'passive_count'  => $passive,
			'para_no_h_pct'  => $para_total > 0 ? (int) round( ( $para_no_h / $para_total ) * 100 ) : 0,
			'sentence_count' => $s_count,
		);
	}
}

if ( ! function_exists( 'damavand_normalize_fa_text' ) ) {
	/**
	 * Global helper — guide section 3.4.
	 *
	 * @param string $text Text.
	 */
	function damavand_normalize_fa_text( string $text ): string {
		return Damavand_Persian_Text::normalize( $text );
	}
}
