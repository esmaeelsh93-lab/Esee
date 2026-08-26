<?php
/**
 * Theme setup and assets.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'bahar_shop_setup' );

/**
 * Register theme features.
 */
function bahar_shop_setup() {
	load_theme_textdomain( 'bahar-shop', BAHAR_SHOP_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	register_nav_menus(
		array(
			'primary' => __( 'منوی اصلی', 'bahar-shop' ),
		)
	);
}

add_action( 'wp_enqueue_scripts', 'bahar_shop_enqueue_assets' );

/**
 * Resolve asset URI — prefer .min.css / .min.js when present (production).
 *
 * @param string $relative Relative path under theme, e.g. assets/css/main.css.
 * @return string Full URI.
 */
function bahar_shop_asset_uri( $relative ) {
	$relative = ltrim( (string) $relative, '/' );
	$path     = BAHAR_SHOP_DIR . '/' . $relative;
	$info     = pathinfo( $relative );
	$ext      = isset( $info['extension'] ) ? $info['extension'] : '';

	if ( in_array( $ext, array( 'css', 'js' ), true ) ) {
		$min_rel  = $info['dirname'] . '/' . $info['filename'] . '.min.' . $ext;
		$min_path = BAHAR_SHOP_DIR . '/' . $min_rel;
		if ( file_exists( $min_path ) ) {
			return BAHAR_SHOP_URI . '/' . $min_rel;
		}
	}

	return BAHAR_SHOP_URI . '/' . $relative;
}

/**
 * Enqueue styles and scripts.
 */
function bahar_shop_enqueue_assets() {
	wp_enqueue_style(
		'bahar-shop-fonts',
		bahar_shop_asset_uri( 'assets/css/fonts.css' ),
		array(),
		BAHAR_SHOP_VERSION
	);

	wp_enqueue_style(
		'bahar-shop-main',
		bahar_shop_asset_uri( 'assets/css/main.css' ),
		array( 'bahar-shop-fonts' ),
		BAHAR_SHOP_VERSION
	);

	wp_enqueue_style(
		'bahar-shop-icons',
		bahar_shop_asset_uri( 'assets/css/icon-theme.css' ),
		array( 'bahar-shop-main' ),
		BAHAR_SHOP_VERSION
	);

	wp_enqueue_style(
		'bahar-shop-bottom-nav',
		bahar_shop_asset_uri( 'assets/css/bottom-nav.css' ),
		array( 'bahar-shop-main', 'bahar-shop-icons' ),
		BAHAR_SHOP_VERSION
	);

	wp_enqueue_style(
		'bahar-shop-categories',
		bahar_shop_asset_uri( 'assets/css/categories.css' ),
		array( 'bahar-shop-main' ),
		BAHAR_SHOP_VERSION
	);

	if ( class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style(
			'bahar-shop-woocommerce',
			bahar_shop_asset_uri( 'assets/css/woocommerce.css' ),
			array( 'bahar-shop-main' ),
			BAHAR_SHOP_VERSION
		);

		if ( is_product() ) {
			wp_enqueue_style(
				'bahar-shop-gallery',
				bahar_shop_asset_uri( 'assets/css/gallery.css' ),
				array( 'bahar-shop-woocommerce' ),
				BAHAR_SHOP_VERSION
			);
		}

		if ( is_cart() || is_checkout() ) {
			wp_enqueue_style(
				'bahar-shop-cart-checkout',
				bahar_shop_asset_uri( 'assets/css/cart-checkout.css' ),
				array( 'bahar-shop-woocommerce' ),
				BAHAR_SHOP_VERSION
			);
		}
	}

	if ( is_product() ) {
		wp_enqueue_script(
			'bahar-shop-variations',
			bahar_shop_asset_uri( 'assets/js/variations.js' ),
			array( 'jquery', 'wc-add-to-cart-variation' ),
			BAHAR_SHOP_VERSION,
			true
		);
		wp_enqueue_script(
			'bahar-shop-sticky-cart',
			bahar_shop_asset_uri( 'assets/js/sticky-cart.js' ),
			array( 'jquery', 'wc-add-to-cart-variation', 'bahar-shop-variations' ),
			BAHAR_SHOP_VERSION,
			true
		);
		wp_enqueue_script(
			'bahar-shop-quantity-stepper',
			bahar_shop_asset_uri( 'assets/js/quantity-stepper.js' ),
			array( 'jquery' ),
			BAHAR_SHOP_VERSION,
			true
		);
		wp_enqueue_script(
			'bahar-shop-gallery',
			bahar_shop_asset_uri( 'assets/js/gallery.js' ),
			array( 'jquery', 'wc-add-to-cart-variation', 'bahar-shop-variations' ),
			BAHAR_SHOP_VERSION,
			true
		);
	}

	if ( class_exists( 'WooCommerce' ) && ( is_shop() || is_product_taxonomy() || is_front_page() || is_product() ) ) {
		wp_enqueue_script(
			'bahar-shop-product-cards',
			bahar_shop_asset_uri( 'assets/js/product-cards.js' ),
			array(),
			BAHAR_SHOP_VERSION,
			true
		);
		wp_enqueue_script(
			'bahar-shop-cart-toast',
			bahar_shop_asset_uri( 'assets/js/cart-toast.js' ),
			array( 'jquery' ),
			BAHAR_SHOP_VERSION,
			true
		);
	}

	wp_enqueue_script(
		'bahar-shop-main',
		bahar_shop_asset_uri( 'assets/js/main.js' ),
		array(),
		BAHAR_SHOP_VERSION,
		true
	);

	$load_motion = is_front_page() || ( class_exists( 'WooCommerce' ) && ( is_shop() || is_product_taxonomy() ) );
	if ( $load_motion ) {
		$gsap_rel   = 'assets/js/vendor/gsap.min.js';
		$scroll_rel = 'assets/js/vendor/ScrollTrigger.min.js';
		$gsap_src   = file_exists( BAHAR_SHOP_DIR . '/' . $gsap_rel )
			? bahar_shop_asset_uri( $gsap_rel )
			: 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js';
		$scroll_src = file_exists( BAHAR_SHOP_DIR . '/' . $scroll_rel )
			? bahar_shop_asset_uri( $scroll_rel )
			: 'https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js';

		wp_enqueue_script( 'gsap', $gsap_src, array(), '3.12.5', array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_enqueue_script( 'gsap-scrolltrigger', $scroll_src, array( 'gsap' ), '3.12.5', array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_enqueue_script(
			'bahar-shop-motion',
			bahar_shop_asset_uri( 'assets/js/motion.js' ),
			array( 'gsap', 'gsap-scrolltrigger' ),
			BAHAR_SHOP_VERSION,
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}

	if ( is_front_page() && class_exists( 'WooCommerce' ) ) {
		wp_enqueue_script(
			'bahar-shop-search',
			BAHAR_SHOP_URI . '/assets/js/search.js',
			array(),
			BAHAR_SHOP_VERSION,
			true
		);
		wp_localize_script(
			'bahar-shop-search',
			'baharSearch',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bahar_search' ),
			)
		);
	}

	$logo_path = BAHAR_SHOP_DIR . '/assets/images/logo.png';
	if ( ! file_exists( $logo_path ) ) {
		$upload_logo = WP_CONTENT_DIR . '/uploads/2026/06/baharrshopp-logo.png';
		if ( file_exists( $upload_logo ) ) {
			$logo_url = content_url( 'uploads/2026/06/baharrshopp-logo.png' );
		} else {
			$logo_url = 'https://baharrshopp.ir/wp-content/uploads/2026/06/baharrshopp-logo.png';
		}
	} else {
		$logo_url = BAHAR_SHOP_URI . '/assets/images/logo.png';
	}

	wp_localize_script(
		'bahar-shop-main',
		'baharShop',
		array(
			'logoUrl' => $logo_url,
		)
	);
}

add_action( 'wp_head', 'bahar_shop_favicon', 1 );

/**
 * Output favicon from theme logo.
 */
function bahar_shop_favicon() {
	$logo_path = BAHAR_SHOP_DIR . '/assets/images/logo.png';
	if ( file_exists( $logo_path ) ) {
		$icon = BAHAR_SHOP_URI . '/assets/images/logo.png';
	} elseif ( file_exists( WP_CONTENT_DIR . '/uploads/2026/06/baharrshopp-logo.png' ) ) {
		$icon = content_url( 'uploads/2026/06/baharrshopp-logo.png' );
	} else {
		$icon = 'https://baharrshopp.ir/wp-content/uploads/2026/06/baharrshopp-logo.png';
	}

	echo '<link rel="icon" href="' . esc_url( $icon ) . '" sizes="32x32" />' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( $icon ) . '" />' . "\n";
}

/**
 * Get page URL by exact title.
 *
 * @param string $title Page title.
 * @return string
 */
function bahar_get_page_url_by_title( $title ) {
	$slug_map = array(
		'سیاست حفظ حریم خصوصی'     => 'privacy-policy',
		'نحوه ارسال'               => 'shipping-info',
		'سیاست تعویض و مرجوعی کالا' => 'returns-policy',
	);

	if ( isset( $slug_map[ $title ] ) && function_exists( 'bahar_shop_info_page_url' ) ) {
		return bahar_shop_info_page_url( $slug_map[ $title ] );
	}

	$page = get_page_by_title( $title );

	if ( ! $page && isset( $slug_map[ $title ] ) ) {
		$page = get_page_by_path( $slug_map[ $title ] );
	}

	return $page ? get_permalink( $page ) : home_url( '/' );
}

/**
 * Get logo URL.
 *
 * @return string
 */
function bahar_shop_logo_url() {
	if ( file_exists( BAHAR_SHOP_DIR . '/assets/images/logo.png' ) ) {
		return BAHAR_SHOP_URI . '/assets/images/logo.png';
	}
	if ( file_exists( WP_CONTENT_DIR . '/uploads/2026/06/baharrshopp-logo.png' ) ) {
		return content_url( 'uploads/2026/06/baharrshopp-logo.png' );
	}
	return 'https://baharrshopp.ir/wp-content/uploads/2026/06/baharrshopp-logo.png';
}

/**
 * Fetch homepage product categories.
 *
 * @return array<int, WP_Term>|WP_Error
 */
function bahar_shop_get_home_categories() {
	$parent = get_term_by( 'slug', 'all', 'product_cat' );
	if ( ! $parent ) {
		$parent = get_term_by( 'name', 'پوشاک دخترانه', 'product_cat' );
	}

	$args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'orderby'    => 'meta_value_num',
		'meta_key'   => 'order',
		'order'      => 'ASC',
	);

	if ( $parent && ! is_wp_error( $parent ) ) {
		$args['parent'] = (int) $parent->term_id;
	} else {
		$args['parent'] = 0;
	}

	$terms = get_terms( $args );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'exclude'    => $parent ? array( (int) $parent->term_id ) : array(),
			)
		);
	}

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return $terms;
	}

	$unique = array();
	$seen   = array();

	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$key = sanitize_title( $term->slug );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$unique[]     = $term;
	}

	return $unique;
}

/**
 * Fallback menu when no menu assigned.
 */
function bahar_shop_fallback_menu() {
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	echo '<ul class="primary-nav__list">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'صفحه اصلی', 'bahar-shop' ) . '</a></li>';
	echo '<li><a href="' . esc_url( $shop_url ) . '">' . esc_html__( 'فروشگاه', 'bahar-shop' ) . '</a></li>';
	if ( function_exists( 'wc_get_cart_url' ) ) {
		echo '<li><a href="' . esc_url( wc_get_cart_url() ) . '">' . esc_html__( 'سبد خرید', 'bahar-shop' ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Remove emoji/symbol characters from the start of a string.
 *
 * @param string $text Raw title.
 * @return string Clean title.
 */
function bahar_shop_strip_leading_emoji( $text ) {
	$clean = preg_replace(
		'/^[\x{1F000}-\x{1FAFF}\x{2190}-\x{21FF}\x{2300}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}\x{2000}-\x{206F}\x{00A0}\s]+/u',
		'',
		$text
	);

	return null === $clean ? $text : trim( $clean );
}

/**
 * Pick a cute emoji icon for a menu/category label.
 *
 * @param string $label Clean menu label.
 * @return string Emoji.
 */
function bahar_shop_menu_icon_for( $label ) {
	$map = array(
		'صفحه اصلی' => '🏠',
		'خانه'      => '🏠',
		'فروشگاه'   => '🛍️',
		'همه'       => '🛍️',
		'سبد'       => '🛒',
		'حساب'      => '👤',
		'تماس'      => '☎️',
		'درباره'    => '🌸',
		'بلاگ'      => '📖',
		'وبلاگ'     => '📖',
		'تیشرت'     => '👕',
		'تی شرت'    => '👕',
		'تی‌شرت'    => '👕',
		'تاپ'       => '🎽',
		'کراپ'      => '👚',
		'بلوز'      => '👚',
		'شومیز'     => '👚',
		'مانتو'     => '🧥',
		'کت'        => '🧥',
		'سویشرت'    => '🧥',
		'هودی'      => '🧥',
		'شلوارک'    => '🩳',
		'شورت'      => '🩳',
		'شلوار'     => '👖',
		'پیراهن'    => '👗',
		'لباس'      => '👗',
		'ماکسی'     => '👗',
		'دامن'      => '👗',
		'سارافون'   => '👗',
		'ست'        => '🎀',
		'سرهمی'     => '🧸',
		'بچگانه'    => '🧸',
		'نوزاد'     => '🍼',
		'کفش'       => '👟',
		'صندل'      => '🩴',
		'جوراب'     => '🧦',
		'کلاه'      => '🧢',
		'کیف'       => '👜',
		'عینک'      => '🕶️',
		'اکسسوری'   => '💍',
		'زیورآلات'  => '💍',
		'حراج'      => '🏷️',
		'تخفیف'     => '🏷️',
		'جدید'      => '✨',
		'پرفروش'    => '🔥',
	);

	foreach ( $map as $needle => $emoji ) {
		if ( '' !== $needle && false !== mb_strpos( $label, $needle ) ) {
			return $emoji;
		}
	}

	return '🌸';
}

/**
 * Replace manual emojis in primary menu items with styled neon/glass icons.
 *
 * @param string   $title Menu item title.
 * @param WP_Post  $item  Menu item.
 * @param stdClass $args  wp_nav_menu args.
 * @param int      $depth Depth.
 * @return string
 */
function bahar_shop_menu_neon_icon( $title, $item, $args, $depth ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $title;
	}

	if ( $depth > 0 ) {
		return bahar_shop_strip_leading_emoji( $title );
	}

	$clean = bahar_shop_strip_leading_emoji( $title );
	$icon  = bahar_shop_menu_icon_for( $clean );

	return '<span class="nav-emoji" aria-hidden="true">' . $icon . '</span><span class="nav-label">' . esc_html( $clean ) . '</span>';
}
add_filter( 'nav_menu_item_title', 'bahar_shop_menu_neon_icon', 20, 4 );

/**
 * Inline SVG for the motion hero (bows, butterflies, etc).
 *
 * @param string $name Icon key.
 * @return string
 */
function bahar_shop_hero_svg( $name ) {
	$svgs = array(
		'bow'        => '<svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><path d="M10 28c0-10 10-16 18-10 3 2 5 5 6 8 1-3 3-6 6-8 8-6 18 0 18 10 0 8-8 12-16 10l-8 14-8-14C18 40 10 36 10 28Z" fill="currentColor"/><circle cx="32" cy="30" r="5" fill="#e84a9a"/></svg>',
		'heart'      => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21s-7.2-4.4-9.3-8.3C.8 9.4 2.4 5.8 6 5.2c2-.3 3.7.7 4.7 2.2 1-1.5 2.7-2.5 4.7-2.2 3.6.6 5.2 4.2 3.3 7.5C19.2 16.6 12 21 12 21Z"/></svg>',
		'flower'     => '<svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><circle cx="16" cy="8" r="5" fill="currentColor"/><circle cx="24" cy="16" r="5" fill="currentColor" opacity=".85"/><circle cx="16" cy="24" r="5" fill="currentColor"/><circle cx="8" cy="16" r="5" fill="currentColor" opacity=".85"/><circle cx="16" cy="16" r="4" fill="#e84a9a"/></svg>',
		'spark'      => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1.5 13.8 9 21 12l-7.2 3L12 22.5 10.2 15 3 12l7.2-3L12 1.5Z"/></svg>',
		'star'       => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 7.2H22l-6 4.4 2.3 7.1L12 16.8 5.7 20.7 8 13.6 2 9.2h7.6L12 2Z"/></svg>',
		'clip'       => '<svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M6 10c8 0 14 4 20 12" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><circle cx="8" cy="10" r="3" fill="#ff8ec7"/><circle cx="14" cy="12" r="2.4" fill="#ff8ec7"/></svg>',
		'butterfly'  => '<svg class="hero-bf" viewBox="0 0 64 64" fill="none" aria-hidden="true"><g class="hero-bf__wing hero-bf__wing--l"><ellipse cx="18" cy="28" rx="16" ry="12" fill="#ff8ec7"/><ellipse cx="16" cy="42" rx="11" ry="8" fill="#ffb4dc"/></g><g class="hero-bf__wing hero-bf__wing--r"><ellipse cx="46" cy="28" rx="16" ry="12" fill="#ff8ec7"/><ellipse cx="48" cy="42" rx="11" ry="8" fill="#ffb4dc"/></g><ellipse cx="32" cy="34" rx="3.2" ry="16" fill="#1b2a4a"/><circle cx="32" cy="18" r="2.4" fill="#1b2a4a"/><path d="M32 16c-4-6-8-8-10-8" stroke="#1b2a4a" stroke-width="1.6" stroke-linecap="round"/><path d="M32 16c4-6 8-8 10-8" stroke="#1b2a4a" stroke-width="1.6" stroke-linecap="round"/></svg>',
	);

	return isset( $svgs[ $name ] ) ? $svgs[ $name ] : '';
}
