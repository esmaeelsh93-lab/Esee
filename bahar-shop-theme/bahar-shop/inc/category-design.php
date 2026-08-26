<?php
/**
 * Per-category visual design mapping.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve category card style from slug and name.
 *
 * @param WP_Term $term Category term.
 * @return array{class: string, label: string, icon: string}
 */
function bahar_get_category_style( $term ) {
	$slug = urldecode( $term->slug );
	$name = $term->name;

	$map = array(
		'کراپ'            => array( 'class' => 'cat-card--crop', 'label' => 'ترند و شاین', 'icon' => 'crop' ),
		'%da%a9%d8%b1%d8%a7%d9%be' => array( 'class' => 'cat-card--crop', 'label' => 'ترند و شاین', 'icon' => 'crop' ),
		'baft'            => array( 'class' => 'cat-card--knit', 'label' => 'نرم و گرم', 'icon' => 'knit' ),
		'dors'            => array( 'class' => 'cat-card--sweat', 'label' => 'کژوال و دخترونه', 'icon' => 'sweat' ),
		't-shirt-womens'  => array( 'class' => 'cat-card--tee', 'label' => 'روزمره شیک', 'icon' => 'tee' ),
		'yaghe-eski'      => array( 'class' => 'cat-card--turtle', 'label' => 'پاییزه نرم', 'icon' => 'turtle' ),
		'shalvarak'       => array( 'class' => 'cat-card--shorts', 'label' => 'تابستانه کیوت', 'icon' => 'shorts' ),
		'daman'           => array( 'class' => 'cat-card--skirt', 'label' => 'لطیف و روان', 'icon' => 'skirt' ),
	);

	if ( isset( $map[ $slug ] ) ) {
		return $map[ $slug ];
	}

	$keywords = array(
		'کراپ'   => array( 'class' => 'cat-card--crop', 'label' => 'ترند و شاین', 'icon' => 'crop' ),
		'بافت'   => array( 'class' => 'cat-card--knit', 'label' => 'نرم و گرم', 'icon' => 'knit' ),
		'دورس'   => array( 'class' => 'cat-card--sweat', 'label' => 'کژوال و دخترونه', 'icon' => 'sweat' ),
		'تی'     => array( 'class' => 'cat-card--tee', 'label' => 'روزمره شیک', 'icon' => 'tee' ),
		'یقه'    => array( 'class' => 'cat-card--turtle', 'label' => 'پاییزه نرم', 'icon' => 'turtle' ),
		'شلوارک' => array( 'class' => 'cat-card--shorts', 'label' => 'تابستانه کیوت', 'icon' => 'shorts' ),
		'شرت'    => array( 'class' => 'cat-card--shorts', 'label' => 'تابستانه کیوت', 'icon' => 'shorts' ),
		'دامن'   => array( 'class' => 'cat-card--skirt', 'label' => 'لطیف و روان', 'icon' => 'skirt' ),
		'ست'     => array( 'class' => 'cat-card--set', 'label' => 'ست آماده', 'icon' => 'set' ),
	);

	foreach ( $keywords as $keyword => $style ) {
		if ( mb_strpos( $name, $keyword ) !== false ) {
			return $style;
		}
	}

	return array(
		'class' => 'cat-card--default',
		'label' => 'مجموعه بهار شاپ',
		'icon'  => 'default',
	);
}

/**
 * Get WooCommerce category thumbnail URL.
 *
 * @param WP_Term $term Category term.
 * @return string
 */
function bahar_get_category_image( $term ) {
	$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( $thumb_id ) {
		$url = wp_get_attachment_image_url( (int) $thumb_id, 'woocommerce_thumbnail' );
		if ( $url ) {
			return $url;
		}
	}

	$woodmart_keys = array( 'title_image', 'category_icon', 'category_icon_alt' );
	foreach ( $woodmart_keys as $key ) {
		$raw = get_term_meta( $term->term_id, $key, true );
		if ( empty( $raw ) ) {
			continue;
		}
		$data = maybe_unserialize( $raw );
		if ( is_array( $data ) ) {
			if ( ! empty( $data['id'] ) ) {
				$url = wp_get_attachment_image_url( (int) $data['id'], 'woocommerce_thumbnail' );
				if ( $url ) {
					return $url;
				}
			}
			if ( ! empty( $data['url'] ) ) {
				return esc_url( $data['url'] );
			}
		}
	}

	return BAHAR_SHOP_URI . '/assets/images/category-placeholder.svg';
}
