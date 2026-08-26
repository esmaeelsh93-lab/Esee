<?php
/**
 * Dynamic glassmorphic mega menu for shop categories.
 *
 * Builds a cached HTML block of product categories (with image, count and
 * sub-categories) to be rendered inside the main navigation "فروشگاه" item.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SHJ_Dynamic_Mega_Menu
 */
class SHJ_Dynamic_Mega_Menu {

	/**
	 * Transient cache key.
	 *
	 * @var string
	 */
	private static $transient_key = 'shj_dynamic_mega_menu_html';

	/**
	 * Hook cache invalidation.
	 */
	public static function init() {
		add_action( 'created_product_cat', array( __CLASS__, 'clear_menu_cache' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'clear_menu_cache' ) );
		add_action( 'delete_product_cat', array( __CLASS__, 'clear_menu_cache' ) );
		// Refresh counts when products are added/removed.
		add_action( 'save_post_product', array( __CLASS__, 'clear_menu_cache' ) );
	}

	/**
	 * Delete the cached menu HTML.
	 */
	public static function clear_menu_cache() {
		delete_transient( self::$transient_key );
	}

	/**
	 * Resolve the base parent category id (so the columns are real clothing
	 * categories, not a single top-level wrapper term).
	 *
	 * @return int
	 */
	private static function base_parent_id() {
		$parent = get_term_by( 'slug', 'all', 'product_cat' );
		if ( ! $parent ) {
			$parent = get_term_by( 'name', 'پوشاک دخترانه', 'product_cat' );
		}

		return ( $parent && ! is_wp_error( $parent ) ) ? (int) $parent->term_id : 0;
	}

	/**
	 * Render (and cache) the mega menu markup.
	 *
	 * @return string
	 */
	public static function render_shop_mega_menu() {
		$cached_html = get_transient( self::$transient_key );
		if ( false !== $cached_html ) {
			return $cached_html;
		}

		$base_parent = self::base_parent_id();

		$parent_cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => $base_parent,
			)
		);

		// If the wrapper has no children, fall back to the real top-level terms.
		if ( $base_parent && ( empty( $parent_cats ) || is_wp_error( $parent_cats ) ) ) {
			$parent_cats = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'parent'     => 0,
				)
			);
		}

		ob_start();

		if ( ! empty( $parent_cats ) && ! is_wp_error( $parent_cats ) ) {
			foreach ( $parent_cats as $cat ) {
				if ( 'uncategorized' === $cat->slug ) {
					continue;
				}

				$term_link = get_term_link( $cat );
				if ( is_wp_error( $term_link ) ) {
					continue;
				}

				$thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
				$image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';
				if ( ! $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
					$image_url = wc_placeholder_img_src();
				}

				echo '<li class="shj-mega-col">';
				echo '<a href="' . esc_url( $term_link ) . '" class="shj-mega-parent-link">';
				if ( $image_url ) {
					echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $cat->name ) . '" class="shj-cat-thumb" loading="lazy" width="44" height="44" />';
				}
				echo '<span class="shj-cat-info">';
				echo '<strong class="shj-cat-name">' . esc_html( $cat->name ) . '</strong>';
				echo '<span class="shj-cat-count">' . esc_html( number_format_i18n( (int) $cat->count ) ) . ' محصول</span>';
				echo '</span>';
				echo '</a>';

				$child_cats = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'hide_empty' => true,
						'parent'     => $cat->term_id,
					)
				);

				if ( ! empty( $child_cats ) && ! is_wp_error( $child_cats ) ) {
					echo '<ul class="shj-mega-child-list">';
					foreach ( $child_cats as $child ) {
						$child_link = get_term_link( $child );
						if ( is_wp_error( $child_link ) ) {
							continue;
						}
						echo '<li><a href="' . esc_url( $child_link ) . '">' . esc_html( $child->name ) . '</a></li>';
					}
					echo '</ul>';
				}

				echo '</li>';
			}
		}

		$html = ob_get_clean();

		if ( '' === trim( (string) $html ) ) {
			return '';
		}

		set_transient( self::$transient_key, $html, 12 * HOUR_IN_SECONDS );

		return $html;
	}
}

SHJ_Dynamic_Mega_Menu::init();
