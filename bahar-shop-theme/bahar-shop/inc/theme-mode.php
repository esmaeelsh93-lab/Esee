<?php
/**
 * Light / dark theme — «رز نیمه‌شب» palette.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'bahar_shop_theme_init_script', 0 );

/**
 * Apply saved theme before paint to avoid flash.
 */
function bahar_shop_theme_init_script() {
	?>
	<script>
	(function () {
		var theme = 'light';
		try {
			var saved = localStorage.getItem('bahar-theme');
			if (saved === 'dark' || saved === 'light') {
				theme = saved;
			} else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
				theme = 'dark';
			}
		} catch (e) {}
		document.documentElement.setAttribute('data-theme', theme);
		document.documentElement.style.colorScheme = theme;
	})();
	</script>
	<?php
}

add_action( 'wp_enqueue_scripts', 'bahar_shop_enqueue_theme_mode', 15 );

/**
 * Theme toggle script + dark-mode overrides.
 */
function bahar_shop_enqueue_theme_mode() {
	wp_enqueue_style(
		'bahar-shop-dark-mode',
		bahar_shop_asset_uri( 'assets/css/dark-mode.css' ),
		array( 'bahar-shop-main' ),
		BAHAR_SHOP_VERSION
	);

	wp_enqueue_script(
		'bahar-shop-theme-toggle',
		bahar_shop_asset_uri( 'assets/js/theme-toggle.js' ),
		array(),
		BAHAR_SHOP_VERSION,
		true
	);
}
