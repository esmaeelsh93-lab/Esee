<?php
/**
 * Mobile bottom navigation + admin color options.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default bottom-nav colors.
 *
 * @return array<string,string>
 */
function bahar_shop_bottom_nav_defaults() {
	return array(
		'bg'     => '#ffffff',
		'text'   => '#5f566c',
		'active' => '#6b9fd4',
		'border' => '#a2d2ff',
	);
}

/**
 * Saved bottom-nav colors.
 *
 * @return array<string,string>
 */
function bahar_shop_bottom_nav_colors() {
	$saved = get_option( 'bahar_shop_bottom_nav', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_bottom_nav_defaults() );
}

add_action( 'admin_menu', 'bahar_shop_register_settings_page' );
add_action( 'admin_init', 'bahar_shop_register_settings' );
add_action( 'wp_footer', 'bahar_shop_render_bottom_nav', 5 );
add_action( 'wp_head', 'bahar_shop_bottom_nav_css_vars', 20 );
add_filter( 'body_class', 'bahar_shop_bottom_nav_body_class' );

/**
 * Mark pages that show bottom nav.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function bahar_shop_bottom_nav_body_class( $classes ) {
	if ( ! is_admin() && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		$classes[] = 'bahar-has-bottom-nav';
	}
	return $classes;
}

/**
 * Settings page under Appearance.
 */
function bahar_shop_register_settings_page() {
	add_theme_page(
		__( 'تنظیمات بهار شاپ', 'bahar-shop' ),
		__( 'تنظیمات بهار شاپ', 'bahar-shop' ),
		'edit_theme_options',
		'bahar-shop-settings',
		'bahar_shop_render_settings_page'
	);
}

/**
 * Register options.
 */
function bahar_shop_register_settings() {
	register_setting(
		'bahar_shop_settings',
		'bahar_shop_bottom_nav',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bahar_shop_sanitize_bottom_nav',
			'default'           => bahar_shop_bottom_nav_defaults(),
		)
	);
}

/**
 * Sanitize color options.
 *
 * @param mixed $input Raw.
 * @return array<string,string>
 */
function bahar_shop_sanitize_bottom_nav( $input ) {
	$out = bahar_shop_bottom_nav_defaults();
	if ( ! is_array( $input ) ) {
		return $out;
	}
	foreach ( array( 'bg', 'text', 'active', 'border' ) as $key ) {
		if ( ! empty( $input[ $key ] ) ) {
			$color = sanitize_hex_color( $input[ $key ] );
			if ( $color ) {
				$out[ $key ] = $color;
			}
		}
	}
	return $out;
}

/**
 * Admin UI.
 */
function bahar_shop_render_settings_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$c    = bahar_shop_bottom_nav_colors();
	$sale = bahar_shop_sale_slider_settings();
	?>
	<div class="wrap" dir="rtl">
		<h1><?php esc_html_e( 'تنظیمات بهار شاپ', 'bahar-shop' ); ?></h1>
		<p><?php esc_html_e( 'پالت برند در فایل COLORS.md ریشه پروژه ثبت شده. رنگ نوار پایین موبایل و اسلایدر تخفیفات را اینجا تنظیم کن.', 'bahar-shop' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'bahar_shop_settings' ); ?>

			<h2><?php esc_html_e( 'نوار پایین موبایل', 'bahar-shop' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bahar_nav_bg"><?php esc_html_e( 'پس‌زمینه نوار', 'bahar-shop' ); ?></label></th>
					<td><input type="color" id="bahar_nav_bg" name="bahar_shop_bottom_nav[bg]" value="<?php echo esc_attr( $c['bg'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bahar_nav_text"><?php esc_html_e( 'رنگ متن / آیکون', 'bahar-shop' ); ?></label></th>
					<td><input type="color" id="bahar_nav_text" name="bahar_shop_bottom_nav[text]" value="<?php echo esc_attr( $c['text'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bahar_nav_active"><?php esc_html_e( 'رنگ فعال', 'bahar-shop' ); ?></label></th>
					<td><input type="color" id="bahar_nav_active" name="bahar_shop_bottom_nav[active]" value="<?php echo esc_attr( $c['active'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="bahar_nav_border"><?php esc_html_e( 'رنگ حاشیه', 'bahar-shop' ); ?></label></th>
					<td><input type="color" id="bahar_nav_border" name="bahar_shop_bottom_nav[border]" value="<?php echo esc_attr( $c['border'] ); ?>" /></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'اسلایدر محصولات تخفیفی', 'bahar-shop' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'نمایش اسلایدر', 'bahar-shop' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bahar_shop_sale_slider[enabled]" value="1" <?php checked( ! empty( $sale['enabled'] ) ); ?> />
							<?php esc_html_e( 'اسلایدر تخفیف‌ها در صفحه اصلی فعال باشد', 'bahar-shop' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bahar_sale_speed"><?php esc_html_e( 'سرعت اسلایدر (ثانیه)', 'bahar-shop' ); ?></label></th>
					<td>
						<input type="number" id="bahar_sale_speed" name="bahar_shop_sale_slider[speed]" value="<?php echo esc_attr( (int) $sale['speed'] ); ?>" min="10" max="120" step="1" />
						<p class="description"><?php esc_html_e( 'عدد کمتر = سریع‌تر. پیشنهاد: ۲۵ تا ۴۵ ثانیه.', 'bahar-shop' ); ?></p>
					</td>
				</tr>
			</table>

			<?php
			if ( function_exists( 'bahar_shop_render_hero_settings_fields' ) ) {
				bahar_shop_render_hero_settings_fields();
			}
			?>

			<?php submit_button( __( 'ذخیره تنظیمات', 'bahar-shop' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Inline CSS vars from admin colors.
 */
function bahar_shop_bottom_nav_css_vars() {
	$c = bahar_shop_bottom_nav_colors();
	echo '<style id="bahar-bottom-nav-vars">:root{--nav-bottom-bg:' . esc_attr( $c['bg'] ) . ';--nav-bottom-text:' . esc_attr( $c['text'] ) . ';--nav-bottom-active:' . esc_attr( $c['active'] ) . ';--nav-bottom-border:' . esc_attr( $c['border'] ) . ';}</style>' . "\n";
}

/**
 * Render sticky bottom nav (mobile).
 */
function bahar_shop_render_bottom_nav() {
	if ( is_admin() || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		return;
	}

	$home     = home_url( '/' );
	$shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : $home;
	$wish     = bahar_shop_wishlist_url();
	$cart     = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $home;
	$count    = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
	$is_home  = is_front_page();
	$is_shop  = function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_product() );
	$is_wish  = is_page( 'wishlist' );
	$is_cart  = function_exists( 'is_cart' ) && is_cart();
	?>
	<nav class="bahar-bottom-nav" aria-label="<?php esc_attr_e( 'ناوبری پایین موبایل', 'bahar-shop' ); ?>">
		<a href="<?php echo esc_url( $home ); ?>" class="bahar-bottom-nav__item<?php echo $is_home ? ' is-active' : ''; ?>">
			<?php bahar_shop_the_icon( 'house-heart' ); ?>
			<span><?php esc_html_e( 'خانه', 'bahar-shop' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $wish ); ?>" class="bahar-bottom-nav__item<?php echo $is_wish ? ' is-active' : ''; ?>">
			<?php bahar_shop_the_icon( 'heart' ); ?>
			<span><?php esc_html_e( 'علاقه‌مندی', 'bahar-shop' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $shop ); ?>" class="bahar-bottom-nav__item<?php echo $is_shop ? ' is-active' : ''; ?>">
			<?php bahar_shop_the_icon( 'shirt' ); ?>
			<span><?php esc_html_e( 'فروشگاه', 'bahar-shop' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $cart ); ?>" class="bahar-bottom-nav__item<?php echo $is_cart ? ' is-active' : ''; ?>">
			<?php bahar_shop_render_bottom_nav_cart_badge( $count ); ?>
			<span><?php esc_html_e( 'سبد', 'bahar-shop' ); ?></span>
		</a>
	</nav>
	<?php
}

/**
 * Bottom-nav cart icon + count badge (fragment-friendly).
 *
 * @param int $count Cart item count.
 */
function bahar_shop_render_bottom_nav_cart_badge( $count = null ) {
	if ( null === $count && function_exists( 'WC' ) && WC()->cart ) {
		$count = (int) WC()->cart->get_cart_contents_count();
	}
	$count = max( 0, (int) $count );
	?>
	<span class="bahar-bottom-nav__badge-wrap" id="bahar-nav-cart-badge">
		<?php bahar_shop_the_icon( 'shopping-bag' ); ?>
		<?php if ( $count > 0 ) : ?>
			<em class="bahar-bottom-nav__badge"><?php echo esc_html( $count ); ?></em>
		<?php endif; ?>
	</span>
	<?php
}

add_filter( 'woocommerce_add_to_cart_fragments', 'bahar_shop_cart_nav_fragments' );

/**
 * Refresh bottom-nav cart badge after AJAX add-to-cart.
 *
 * @param array<string,string> $fragments Fragments.
 * @return array<string,string>
 */
function bahar_shop_cart_nav_fragments( $fragments ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $fragments;
	}

	ob_start();
	bahar_shop_render_bottom_nav_cart_badge( (int) WC()->cart->get_cart_contents_count() );
	$fragments['#bahar-nav-cart-badge'] = ob_get_clean();

	$count = (int) WC()->cart->get_cart_contents_count();
	ob_start();
	if ( $count > 0 ) {
		echo '<span class="cart-count">' . esc_html( (string) $count ) . '</span>';
	}
	$fragments['#bahar-header-cart-count'] = ob_get_clean();

	return $fragments;
}
