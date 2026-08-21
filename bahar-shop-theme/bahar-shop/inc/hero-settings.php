<?php
/**
 * Hero image settings — «پریسا» panel section.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default hero settings (current theme behaviour).
 *
 * @return array<string,mixed>
 */
function bahar_shop_hero_defaults() {
	return array(
		'mobile_full_bleed'   => 1,
		'mobile_object_fit' => 'cover',
		'mobile_min_height' => 420,
		'desktop_object_fit' => 'contain',
		'desktop_min_height' => 460,
		'mobile_image_url'  => 'https://baharrshopp.ir/wp-content/uploads/2026/08/baharshopp-product-1787322561-619.webp',
		'desktop_image_url' => 'https://baharrshopp.ir/wp-content/uploads/2026/08/baharshopp-product-1787322566-279.webp',
	);
}

/**
 * Saved hero settings merged with defaults.
 *
 * @return array<string,mixed>
 */
function bahar_shop_hero_settings() {
	$saved = get_option( 'bahar_shop_hero', array() );
	$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_hero_defaults() );

	$out['mobile_full_bleed']    = ! empty( $out['mobile_full_bleed'] ) ? 1 : 0;
	$out['mobile_min_height']    = max( 280, min( 720, (int) $out['mobile_min_height'] ) );
	$out['desktop_min_height']   = max( 280, min( 720, (int) $out['desktop_min_height'] ) );
	$out['mobile_object_fit']    = in_array( $out['mobile_object_fit'], array( 'cover', 'contain' ), true ) ? $out['mobile_object_fit'] : 'cover';
	$out['desktop_object_fit']   = in_array( $out['desktop_object_fit'], array( 'cover', 'contain' ), true ) ? $out['desktop_object_fit'] : 'contain';
	$out['mobile_image_url']     = esc_url_raw( (string) $out['mobile_image_url'] );
	$out['desktop_image_url']    = esc_url_raw( (string) $out['desktop_image_url'] );

	return $out;
}

add_action( 'admin_init', 'bahar_shop_register_hero_settings' );
add_action( 'wp_head', 'bahar_shop_hero_css_vars', 25 );

/**
 * Register hero option.
 */
function bahar_shop_register_hero_settings() {
	register_setting(
		'bahar_shop_settings',
		'bahar_shop_hero',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bahar_shop_sanitize_hero_settings',
			'default'           => bahar_shop_hero_defaults(),
		)
	);
}

/**
 * Sanitize hero settings.
 *
 * @param mixed $input Raw input.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_hero_settings( $input ) {
	$defaults = bahar_shop_hero_defaults();
	if ( ! is_array( $input ) ) {
		return $defaults;
	}

	if ( ! empty( $input['reset_defaults'] ) ) {
		return $defaults;
	}

	$out = bahar_shop_hero_settings();
	if ( isset( $input['mobile_full_bleed'] ) ) {
		$out['mobile_full_bleed'] = ! empty( $input['mobile_full_bleed'] ) ? 1 : 0;
	} else {
		$out['mobile_full_bleed'] = 0;
	}

	if ( isset( $input['mobile_object_fit'] ) ) {
		$out['mobile_object_fit'] = in_array( $input['mobile_object_fit'], array( 'cover', 'contain' ), true ) ? $input['mobile_object_fit'] : 'cover';
	}
	if ( isset( $input['desktop_object_fit'] ) ) {
		$out['desktop_object_fit'] = in_array( $input['desktop_object_fit'], array( 'cover', 'contain' ), true ) ? $input['desktop_object_fit'] : 'contain';
	}
	if ( isset( $input['mobile_min_height'] ) ) {
		$out['mobile_min_height'] = max( 280, min( 720, (int) $input['mobile_min_height'] ) );
	}
	if ( isset( $input['desktop_min_height'] ) ) {
		$out['desktop_min_height'] = max( 280, min( 720, (int) $input['desktop_min_height'] ) );
	}
	if ( ! empty( $input['mobile_image_url'] ) ) {
		$out['mobile_image_url'] = esc_url_raw( (string) $input['mobile_image_url'] );
	}
	if ( ! empty( $input['desktop_image_url'] ) ) {
		$out['desktop_image_url'] = esc_url_raw( (string) $input['desktop_image_url'] );
	}

	return $out;
}

/**
 * Output CSS variables for hero sizing from admin.
 */
function bahar_shop_hero_css_vars() {
	if ( ! is_front_page() ) {
		return;
	}
	$h = bahar_shop_hero_settings();
	echo '<style id="bahar-hero-vars">:root{'
		. '--bahar-hero-mobile-min-h:' . (int) $h['mobile_min_height'] . 'px;'
		. '--bahar-hero-desktop-min-h:' . (int) $h['desktop_min_height'] . 'px;'
		. '--bahar-hero-mobile-fit:' . esc_attr( $h['mobile_object_fit'] ) . ';'
		. '--bahar-hero-desktop-fit:' . esc_attr( $h['desktop_object_fit'] ) . ';'
		. '}</style>' . "\n";
}

/**
 * Render admin fields for hero settings.
 */
function bahar_shop_render_hero_settings_fields() {
	$h = bahar_shop_hero_settings();
	?>
	<h2><?php esc_html_e( 'پریسا — تنظیمات عکس هیرو (صفحه اصلی)', 'bahar-shop' ); ?></h2>
	<p class="description"><?php esc_html_e( 'اگر ظاهر به‌هم ریخت، تیک «بازگشت به حالت پیش‌فرض» را بزن و ذخیره کن.', 'bahar-shop' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'بازگشت به پیش‌فرض', 'bahar-shop' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="bahar_shop_hero[reset_defaults]" value="1" />
					<?php esc_html_e( 'برگرداندن همه تنظیمات پریسا به حالت فعلی/پیش‌فرض تم', 'bahar-shop' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'موبایل تمام‌عرض', 'bahar-shop' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="bahar_shop_hero[mobile_full_bleed]" value="1" <?php checked( ! empty( $h['mobile_full_bleed'] ) ); ?> />
					<?php esc_html_e( 'عکس موبایل بدون قاب و تمام عرض صفحه', 'bahar-shop' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="bahar_hero_mobile_fit"><?php esc_html_e( 'نحوه نمایش عکس موبایل', 'bahar-shop' ); ?></label></th>
			<td>
				<select id="bahar_hero_mobile_fit" name="bahar_shop_hero[mobile_object_fit]">
					<option value="cover" <?php selected( $h['mobile_object_fit'], 'cover' ); ?>><?php esc_html_e( 'پر کردن قاب (cover)', 'bahar-shop' ); ?></option>
					<option value="contain" <?php selected( $h['mobile_object_fit'], 'contain' ); ?>><?php esc_html_e( 'کامل بدون برش (contain)', 'bahar-shop' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="bahar_hero_desktop_fit"><?php esc_html_e( 'نحوه نمایش عکس دسکتاپ', 'bahar-shop' ); ?></label></th>
			<td>
				<select id="bahar_hero_desktop_fit" name="bahar_shop_hero[desktop_object_fit]">
					<option value="contain" <?php selected( $h['desktop_object_fit'], 'contain' ); ?>><?php esc_html_e( 'کامل بدون برش (contain)', 'bahar-shop' ); ?></option>
					<option value="cover" <?php selected( $h['desktop_object_fit'], 'cover' ); ?>><?php esc_html_e( 'پر کردن قاب (cover)', 'bahar-shop' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="bahar_hero_mobile_h"><?php esc_html_e( 'حداقل ارتفاع موبایل (px)', 'bahar-shop' ); ?></label></th>
			<td><input type="number" id="bahar_hero_mobile_h" name="bahar_shop_hero[mobile_min_height]" value="<?php echo esc_attr( (int) $h['mobile_min_height'] ); ?>" min="280" max="720" step="10" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="bahar_hero_desktop_h"><?php esc_html_e( 'حداقل ارتفاع دسکتاپ (px)', 'bahar-shop' ); ?></label></th>
			<td><input type="number" id="bahar_hero_desktop_h" name="bahar_shop_hero[desktop_min_height]" value="<?php echo esc_attr( (int) $h['desktop_min_height'] ); ?>" min="280" max="720" step="10" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="bahar_hero_mobile_url"><?php esc_html_e( 'آدرس عکس موبایل', 'bahar-shop' ); ?></label></th>
			<td><input type="url" class="large-text" id="bahar_hero_mobile_url" name="bahar_shop_hero[mobile_image_url]" value="<?php echo esc_url( $h['mobile_image_url'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="bahar_hero_desktop_url"><?php esc_html_e( 'آدرس عکس دسکتاپ', 'bahar-shop' ); ?></label></th>
			<td><input type="url" class="large-text" id="bahar_hero_desktop_url" name="bahar_shop_hero[desktop_image_url]" value="<?php echo esc_url( $h['desktop_image_url'] ); ?>" /></td>
		</tr>
	</table>
	<?php
}
