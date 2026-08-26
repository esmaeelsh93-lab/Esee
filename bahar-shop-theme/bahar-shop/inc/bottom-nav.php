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
		'bg'         => '#fff9fc',
		'text'       => '#5f566c',
		'active'     => '#c277a7',
		'border'     => '#ffc8dd',
		'custom_css' => '',
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
	if ( isset( $input['custom_css'] ) && function_exists( 'bahar_shop_sanitize_custom_css' ) ) {
		$out['custom_css'] = bahar_shop_sanitize_custom_css( $input['custom_css'] );
	}
	return $out;
}

/**
 * Admin UI (tabbed).
 */
function bahar_shop_render_settings_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tabs  = array(
		'general'  => __( 'عمومی', 'bahar-shop' ),
		'hero'     => __( 'هیرو', 'bahar-shop' ),
		'header'   => __( 'هدر', 'bahar-shop' ),
		'footer'   => __( 'فوتر', 'bahar-shop' ),
		'buttons'  => __( 'دکمه‌ها', 'bahar-shop' ),
		'images'   => __( 'عکس‌ها', 'bahar-shop' ),
		'nav'      => __( 'نوار پایین', 'bahar-shop' ),
	);
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'general';
	}
	$c     = bahar_shop_bottom_nav_colors();
	$sale  = bahar_shop_sale_slider_settings();
	$image = function_exists( 'bahar_shop_image_settings' ) ? bahar_shop_image_settings() : array();
	$lm    = function_exists( 'bahar_shop_load_more_settings' ) ? bahar_shop_load_more_settings() : array( 'enabled' => 1, 'label' => 'بارگذاری بیشتر' );
	$base  = admin_url( 'themes.php?page=bahar-shop-settings' );
	?>
	<div class="wrap" dir="rtl">
		<h1><?php esc_html_e( 'تنظیمات بهار شاپ', 'bahar-shop' ); ?></h1>
		<nav class="nav-tab-wrapper" style="margin-bottom:1rem;">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $key, $base ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<form method="post" action="options.php">
			<?php settings_fields( 'bahar_shop_settings' ); ?>

			<?php if ( 'general' === $tab ) : ?>
				<h2><?php esc_html_e( 'اسلایدر تخفیف و بارگذاری بیشتر', 'bahar-shop' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'اسلایدر تخفیف', 'bahar-shop' ); ?></th>
						<td>
							<label><input type="checkbox" name="bahar_shop_sale_slider[enabled]" value="1" <?php checked( ! empty( $sale['enabled'] ) ); ?> /> <?php esc_html_e( 'فعال در صفحه اصلی', 'bahar-shop' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="bahar_sale_speed"><?php esc_html_e( 'سرعت اسلایدر (ثانیه)', 'bahar-shop' ); ?></label></th>
						<td><input type="number" id="bahar_sale_speed" name="bahar_shop_sale_slider[speed]" value="<?php echo esc_attr( (int) $sale['speed'] ); ?>" min="10" max="120" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'بارگذاری بیشتر محصولات', 'bahar-shop' ); ?></th>
						<td>
							<label><input type="checkbox" name="bahar_shop_load_more[enabled]" value="1" <?php checked( ! empty( $lm['enabled'] ) ); ?> /> <?php esc_html_e( 'در فروشگاه و دسته‌بندی‌ها به‌جای صفحه‌بندی کلاسیک', 'bahar-shop' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'متن دکمه بارگذاری', 'bahar-shop' ); ?></th>
						<td><input type="text" class="regular-text" name="bahar_shop_load_more[label]" value="<?php echo esc_attr( $lm['label'] ?? 'بارگذاری بیشتر' ); ?>" /></td>
					</tr>
				</table>
			<?php elseif ( 'hero' === $tab && function_exists( 'bahar_shop_render_hero_fields' ) ) : ?>
				<?php bahar_shop_render_hero_fields(); ?>
			<?php elseif ( 'header' === $tab && function_exists( 'bahar_shop_render_header_fields' ) ) : ?>
				<?php bahar_shop_render_header_fields(); ?>
			<?php elseif ( 'footer' === $tab && function_exists( 'bahar_shop_render_footer_fields' ) ) : ?>
				<?php bahar_shop_render_footer_fields(); ?>
			<?php elseif ( 'buttons' === $tab && function_exists( 'bahar_shop_render_button_fields' ) ) : ?>
				<?php bahar_shop_render_button_fields(); ?>
			<?php elseif ( 'images' === $tab ) : ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'پر کردن قاب کارت', 'bahar-shop' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:.4rem;"><input type="radio" name="bahar_shop_image_settings[fit_mode]" value="cover" <?php checked( ( $image['fit_mode'] ?? 'cover' ), 'cover' ); ?> /> <?php esc_html_e( 'پر کردن قاب (ممکن است کمی برش بخورد)', 'bahar-shop' ); ?></label>
							<label style="display:block;"><input type="radio" name="bahar_shop_image_settings[fit_mode]" value="contain" <?php checked( ( $image['fit_mode'] ?? '' ), 'contain' ); ?> /> <?php esc_html_e( 'حالت امن — عکس کامل بدون برش', 'bahar-shop' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'گالری صفحه محصول', 'bahar-shop' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:.4rem;"><input type="radio" name="bahar_shop_image_settings[gallery_fit]" value="cover" <?php checked( ( $image['gallery_fit'] ?? 'cover' ), 'cover' ); ?> /> <?php esc_html_e( 'پر کردن قاب', 'bahar-shop' ); ?></label>
							<label style="display:block;"><input type="radio" name="bahar_shop_image_settings[gallery_fit]" value="contain" <?php checked( ( $image['gallery_fit'] ?? '' ), 'contain' ); ?> /> <?php esc_html_e( 'حالت امن', 'bahar-shop' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ارتفاع عکس کارت دسکتاپ', 'bahar-shop' ); ?></th>
						<td><input type="number" name="bahar_shop_image_settings[card_height_desktop]" value="<?php echo esc_attr( (int) ( $image['card_height_desktop'] ?? 360 ) ); ?>" min="180" max="640" step="10" /> px</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ارتفاع عکس کارت موبایل', 'bahar-shop' ); ?></th>
						<td><input type="number" name="bahar_shop_image_settings[card_height_mobile]" value="<?php echo esc_attr( (int) ( $image['card_height_mobile'] ?? 280 ) ); ?>" min="140" max="480" step="10" /> px</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'حداکثر عرض عکس کارت دسکتاپ', 'bahar-shop' ); ?></th>
						<td><input type="number" name="bahar_shop_image_settings[card_width_desktop]" value="<?php echo esc_attr( (int) ( $image['card_width_desktop'] ?? 0 ) ); ?>" min="0" max="800" step="10" /> px <span class="description"><?php esc_html_e( '۰ = خودکار', 'bahar-shop' ); ?></span></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'حداکثر عرض عکس کارت موبایل', 'bahar-shop' ); ?></th>
						<td><input type="number" name="bahar_shop_image_settings[card_width_mobile]" value="<?php echo esc_attr( (int) ( $image['card_width_mobile'] ?? 0 ) ); ?>" min="0" max="500" step="10" /> px <span class="description"><?php esc_html_e( '۰ = خودکار', 'bahar-shop' ); ?></span></td>
					</tr>
				</table>
			<?php elseif ( 'nav' === $tab ) : ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'پس‌زمینه نوار', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_bottom_nav[bg]" value="<?php echo esc_attr( $c['bg'] ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'رنگ متن / آیکون', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_bottom_nav[text]" value="<?php echo esc_attr( $c['text'] ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'رنگ فعال', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_bottom_nav[active]" value="<?php echo esc_attr( $c['active'] ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'رنگ حاشیه', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_bottom_nav[border]" value="<?php echo esc_attr( $c['border'] ); ?>" /></td></tr>
					<tr><th><?php esc_html_e( 'CSS اختصاصی نوار پایین', 'bahar-shop' ); ?></th><td><textarea class="large-text code" rows="6" name="bahar_shop_bottom_nav[custom_css]" placeholder=".bahar-bottom-nav { box-shadow: none; }"><?php echo esc_textarea( $c['custom_css'] ?? '' ); ?></textarea></td></tr>
				</table>
			<?php endif; ?>

			<?php
			// Keep other option groups present with hidden defaults so saving one tab doesn't wipe others.
			if ( 'general' !== $tab ) {
				echo '<input type="hidden" name="bahar_shop_sale_slider[enabled]" value="' . esc_attr( ! empty( $sale['enabled'] ) ? '1' : '0' ) . '" />';
				echo '<input type="hidden" name="bahar_shop_sale_slider[speed]" value="' . esc_attr( (int) $sale['speed'] ) . '" />';
				echo '<input type="hidden" name="bahar_shop_load_more[enabled]" value="' . esc_attr( ! empty( $lm['enabled'] ) ? '1' : '0' ) . '" />';
				echo '<input type="hidden" name="bahar_shop_load_more[label]" value="' . esc_attr( $lm['label'] ?? 'بارگذاری بیشتر' ) . '" />';
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
			<span class="bahar-bottom-nav__badge-wrap">
				<?php bahar_shop_the_icon( 'shopping-bag' ); ?>
				<span class="bahar-bottom-nav__badge<?php echo $count > 0 ? ' is-visible' : ''; ?>" data-bahar-cart-count><?php echo $count > 0 ? esc_html( (string) $count ) : ''; ?></span>
			</span>
			<span><?php esc_html_e( 'سبد', 'bahar-shop' ); ?></span>
		</a>
	</nav>
	<?php
}
