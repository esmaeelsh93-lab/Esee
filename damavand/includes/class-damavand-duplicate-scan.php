<?php
/**
 * Duplicate SEO title / meta description scanner (admin).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Duplicate_Scan
 */
final class Damavand_Duplicate_Scan {

	/**
	 * Scan published products for duplicate SEO titles or descriptions.
	 *
	 * @param int $limit Max products to inspect.
	 * @return array{titles:array,descriptions:array,scanned:int}
	 */
	public static function scan_products( int $limit = 500 ): array {
		$limit = max( 50, min( 2000, $limit ) );

		$q = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
			)
		);

		$by_title = array();
		$by_desc  = array();

		foreach ( (array) $q->posts as $pid ) {
			$pid = (int) $pid;
			if ( $pid < 1 ) {
				continue;
			}
			$title = '';
			$desc  = '';
			if ( class_exists( 'Damavand_SEO_Meta' ) ) {
				$title = Damavand_SEO_Meta::get_title( $pid, true );
				$desc  = Damavand_SEO_Meta::get_description( $pid, true );
			}
			$title_key = self::norm_key( $title );
			$desc_key  = self::norm_key( $desc );
			if ( '' !== $title_key ) {
				$by_title[ $title_key ][] = array(
					'id'    => $pid,
					'title' => $title,
					'edit'  => get_edit_post_link( $pid, 'raw' ),
				);
			}
			if ( '' !== $desc_key && mb_strlen( $desc_key ) >= 20 ) {
				$by_desc[ $desc_key ][] = array(
					'id'   => $pid,
					'desc' => $desc,
					'edit' => get_edit_post_link( $pid, 'raw' ),
				);
			}
		}

		return array(
			'titles'       => self::only_dupes( $by_title ),
			'descriptions' => self::only_dupes( $by_desc ),
			'scanned'      => count( (array) $q->posts ),
		);
	}

	/**
	 * @param string $text Text.
	 */
	private static function norm_key( string $text ): string {
		$text = trim( wp_strip_all_tags( $text ) );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return mb_strtolower( (string) $text, 'UTF-8' );
	}

	/**
	 * @param array<string,array> $map Grouped items.
	 * @return array<int,array{key:string,count:int,items:array}>
	 */
	private static function only_dupes( array $map ): array {
		$out = array();
		foreach ( $map as $key => $items ) {
			if ( count( $items ) < 2 ) {
				continue;
			}
			$out[] = array(
				'key'   => $key,
				'count' => count( $items ),
				'items' => $items,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return (int) $b['count'] <=> (int) $a['count'];
			}
		);
		return $out;
	}
}
