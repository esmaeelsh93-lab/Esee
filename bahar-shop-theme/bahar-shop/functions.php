<?php
/**
 * Bahar Shop theme bootstrap.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bahar_shop_theme_data = wp_get_theme();
define( 'BAHAR_SHOP_VERSION', $bahar_shop_theme_data->get( 'Version' ) );
define( 'BAHAR_SHOP_DIR', get_template_directory() );
define( 'BAHAR_SHOP_URI', get_template_directory_uri() );

require_once BAHAR_SHOP_DIR . '/inc/setup.php';
require_once BAHAR_SHOP_DIR . '/inc/icons.php';
require_once BAHAR_SHOP_DIR . '/inc/mega-menu.php';
require_once BAHAR_SHOP_DIR . '/inc/category-design.php';
require_once BAHAR_SHOP_DIR . '/inc/category-icons.php';
require_once BAHAR_SHOP_DIR . '/inc/woocommerce.php';
require_once BAHAR_SHOP_DIR . '/inc/cart-checkout.php';
require_once BAHAR_SHOP_DIR . '/inc/pages-content.php';
require_once BAHAR_SHOP_DIR . '/inc/variations-ui.php';
require_once BAHAR_SHOP_DIR . '/inc/sticky-cart.php';
require_once BAHAR_SHOP_DIR . '/inc/product-quantity.php';
require_once BAHAR_SHOP_DIR . '/inc/product-ux.php';
require_once BAHAR_SHOP_DIR . '/inc/theme-mode.php';
require_once BAHAR_SHOP_DIR . '/inc/search.php';
require_once BAHAR_SHOP_DIR . '/inc/performance.php';
require_once BAHAR_SHOP_DIR . '/inc/wishlist.php';
require_once BAHAR_SHOP_DIR . '/inc/bottom-nav.php';
require_once BAHAR_SHOP_DIR . '/inc/sale-slider.php';
require_once BAHAR_SHOP_DIR . '/inc/hero-settings.php';
require_once BAHAR_SHOP_DIR . '/inc/why-us.php';
