<?php
/**
 * Admin settings UI helpers (tab sections).
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render align select.
 *
 * @param string $name  Field name.
 * @param string $value Current.
 */
function bahar_shop_render_align_select( $name, $value ) {
	$opts = array(
		'center'                      => 'وسط',
		'top-center'                  => 'بالا وسط',
		'top-left'                    => 'بالا چپ',
		'top-right'                   => 'بالا راست',
		'bottom-left'                 => 'پایین چپ',
		'bottom-right'                => 'پایین راست',
		'bottom-center'               => 'پایین وسط',
		'top-center-cta-bottom-left'  => 'متن بالا وسط + دکمه پایین چپ (موبایل پیشنهادی)',
	);
	echo '<select name="' . esc_attr( $name ) . '">';
	foreach ( $opts as $key => $label ) {
		printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
	}
	echo '</select>';
}

/**
 * Media upload field with preview.
 *
 * @param string $slot     desktop|mobile.
 * @param array  $hero     Hero settings.
 * @param string $help_html Helper description HTML (escaped by caller).
 */
function bahar_shop_render_hero_image_field( $slot, $hero, $help_html ) {
	$id_key  = 'img_' . $slot . '_id';
	$url_key = 'img_' . $slot;
	$id      = absint( $hero[ $id_key ] ?? 0 );
	$url     = ! empty( $hero[ $url_key ] ) ? (string) $hero[ $url_key ] : '';
	$preview = $url;
	if ( $id ) {
		$thumb = wp_get_attachment_image_url( $id, 'medium' );
		if ( $thumb ) {
			$preview = $thumb;
		}
	}
	$label = 'desktop' === $slot ? __( 'تصویر هیرو — دسکتاپ', 'bahar-shop' ) : __( 'تصویر هیرو — موبایل', 'bahar-shop' );
	?>
	<tr>
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td>
			<div class="bahar-media-field" data-bahar-media data-slot="<?php echo esc_attr( $slot ); ?>">
				<input type="hidden" class="bahar-media-field__id" name="bahar_shop_hero[<?php echo esc_attr( $id_key ); ?>]" value="<?php echo esc_attr( (string) $id ); ?>" />
				<input type="url" class="large-text bahar-media-field__url" name="bahar_shop_hero[<?php echo esc_attr( $url_key ); ?>]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://" />
				<p style="margin:.55rem 0;">
					<button type="button" class="button bahar-media-field__upload"><?php esc_html_e( 'آپلود / انتخاب از کتابخانه', 'bahar-shop' ); ?></button>
					<button type="button" class="button-link-delete bahar-media-field__remove" <?php disabled( ! $id && ! $url ); ?>><?php esc_html_e( 'حذف تصویر', 'bahar-shop' ); ?></button>
				</p>
				<div class="bahar-media-field__preview" style="margin-top:.5rem;<?php echo $preview ? '' : 'display:none;'; ?>">
					<?php if ( $preview ) : ?>
						<img src="<?php echo esc_url( $preview ); ?>" alt="" style="max-width:280px;height:auto;border-radius:12px;border:1px solid #f1dce8;background:#fff9fc;" />
					<?php endif; ?>
				</div>
				<p class="description" style="margin-top:.65rem;"><?php echo $help_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			</div>
		</td>
	</tr>
	<?php
}

/**
 * Hero settings fields.
 */
function bahar_shop_render_hero_fields() {
	$h = bahar_shop_hero_settings();
	$desk_help = esc_html__( 'ابعاد پیشنهادی: ۱۰۰۰×۱۲۵۰ یا ۱۲۰۰×۱۵۰۰ پیکسل — نسبت ۴:۵ — فرمت WebP — حداکثر ۳۵۰ کیلوبایت', 'bahar-shop' );
	$mob_help  = esc_html__( 'ابعاد پیشنهادی: ۱۰۸۰×۱۳۵۰ پیکسل — نسبت ۴:۵ — فرمت WebP — حداکثر ۲۵۰ کیلوبایت', 'bahar-shop' );
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'عنوان برند', 'bahar-shop' ); ?></th><td><input type="text" class="regular-text" name="bahar_shop_hero[brand]" value="<?php echo esc_attr( $h['brand'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'شعار', 'bahar-shop' ); ?></th><td><input type="text" class="regular-text" name="bahar_shop_hero[tagline]" value="<?php echo esc_attr( $h['tagline'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'متن دکمه', 'bahar-shop' ); ?></th><td><input type="text" class="regular-text" name="bahar_shop_hero[cta_text]" value="<?php echo esc_attr( $h['cta_text'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'لینک دکمه', 'bahar-shop' ); ?></th><td><input type="url" class="regular-text" name="bahar_shop_hero[cta_url]" value="<?php echo esc_attr( $h['cta_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/#bahar-newest' ) ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'متن جایگزین تصویر (alt)', 'bahar-shop' ); ?></th><td><input type="text" class="regular-text" name="bahar_shop_hero[img_alt]" value="<?php echo esc_attr( $h['img_alt'] ?? $h['brand'] ); ?>" /></td></tr>
		<?php bahar_shop_render_hero_image_field( 'desktop', $h, $desk_help ); ?>
		<?php bahar_shop_render_hero_image_field( 'mobile', $h, $mob_help ); ?>
		<tr><th><?php esc_html_e( 'رنگ عنوان', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_hero[brand_color]" value="<?php echo esc_attr( $h['brand_color'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'رنگ شعار', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_hero[tagline_color]" value="<?php echo esc_attr( $h['tagline_color'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'رنگ متن دکمه', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_hero[cta_color]" value="<?php echo esc_attr( $h['cta_color'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'رنگ پس‌زمینه دکمه (اختیاری)', 'bahar-shop' ); ?></th><td><input type="text" name="bahar_shop_hero[cta_bg]" value="<?php echo esc_attr( $h['cta_bg'] ); ?>" placeholder="#F973A8" /></td></tr>
		<tr><th><?php esc_html_e( 'جای متن دسکتاپ', 'bahar-shop' ); ?></th><td><?php bahar_shop_render_align_select( 'bahar_shop_hero[align_desktop]', $h['align_desktop'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'جای متن موبایل', 'bahar-shop' ); ?></th><td><?php bahar_shop_render_align_select( 'bahar_shop_hero[align_mobile]', $h['align_mobile'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'متن اضافه', 'bahar-shop' ); ?></th><td><input type="text" class="regular-text" name="bahar_shop_hero[extra_text]" value="<?php echo esc_attr( $h['extra_text'] ); ?>" /><p class="description"><?php esc_html_e( 'اختیاری — زیر شعار نمایش داده می‌شود.', 'bahar-shop' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'رنگ متن اضافه', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_hero[extra_text_color]" value="<?php echo esc_attr( $h['extra_text_color'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'دکمه اضافه — متن', 'bahar-shop' ); ?></th><td><input type="text" class="regular-text" name="bahar_shop_hero[extra_btn_text]" value="<?php echo esc_attr( $h['extra_btn_text'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'دکمه اضافه — لینک', 'bahar-shop' ); ?></th><td><input type="url" class="regular-text" name="bahar_shop_hero[extra_btn_url]" value="<?php echo esc_attr( $h['extra_btn_url'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'CSS اختصاصی هیرو', 'bahar-shop' ); ?></th><td><textarea class="large-text code" rows="6" name="bahar_shop_hero[custom_css]" placeholder=".hero--photo .hero__brand { font-size: 2rem; }"><?php echo esc_textarea( $h['custom_css'] ); ?></textarea></td></tr>
	</table>
	<?php
}

/**
 * Header settings fields.
 */
function bahar_shop_render_header_fields() {
	$h = bahar_shop_header_settings();
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'رنگ پس‌زمینه هدر', 'bahar-shop' ); ?></th><td><input type="text" name="bahar_shop_header[bg]" value="<?php echo esc_attr( $h['bg'] ); ?>" placeholder="#FFFFFF" /> <p class="description"><?php esc_html_e( 'خالی = پیش‌فرض تم', 'bahar-shop' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'رنگ متن / آیکون هدر', 'bahar-shop' ); ?></th><td><input type="text" name="bahar_shop_header[text_color]" value="<?php echo esc_attr( $h['text_color'] ); ?>" placeholder="#211827" /></td></tr>
		<tr><th><?php esc_html_e( 'المان‌های خوشه چپ موبایل', 'bahar-shop' ); ?></th>
			<td>
				<label><input type="checkbox" name="bahar_shop_header[show_cart]" value="1" <?php checked( $h['show_cart'] ); ?> /> <?php esc_html_e( 'سبد خرید', 'bahar-shop' ); ?></label><br />
				<label><input type="checkbox" name="bahar_shop_header[show_account]" value="1" <?php checked( $h['show_account'] ); ?> /> <?php esc_html_e( 'حساب کاربری', 'bahar-shop' ); ?></label>
			</td>
		</tr>
		<tr><th><?php esc_html_e( 'شبکه‌های اجتماعی هدر', 'bahar-shop' ); ?></th>
			<td>
				<label><input type="checkbox" name="bahar_shop_header[show_instagram]" value="1" <?php checked( $h['show_instagram'] ); ?> /> Instagram</label>
				<input type="url" class="regular-text" name="bahar_shop_header[instagram_url]" value="<?php echo esc_attr( $h['instagram_url'] ); ?>" /><br /><br />
				<label><input type="checkbox" name="bahar_shop_header[show_whatsapp]" value="1" <?php checked( $h['show_whatsapp'] ); ?> /> WhatsApp</label>
				<input type="url" class="regular-text" name="bahar_shop_header[whatsapp_url]" value="<?php echo esc_attr( $h['whatsapp_url'] ); ?>" /><br /><br />
				<label><input type="checkbox" name="bahar_shop_header[show_telegram]" value="1" <?php checked( $h['show_telegram'] ); ?> /> Telegram</label>
				<input type="url" class="regular-text" name="bahar_shop_header[telegram_url]" value="<?php echo esc_attr( $h['telegram_url'] ); ?>" /><br /><br />
				<label><input type="checkbox" name="bahar_shop_header[show_call]" value="1" <?php checked( $h['show_call'] ); ?> /> <?php esc_html_e( 'تماس', 'bahar-shop' ); ?></label>
				<input type="text" class="regular-text" name="bahar_shop_header[phone_url]" value="<?php echo esc_attr( $h['phone_url'] ); ?>" />
			</td>
		</tr>
		<tr><th><?php esc_html_e( 'CSS اختصاصی هدر', 'bahar-shop' ); ?></th><td><textarea class="large-text code" rows="5" name="bahar_shop_header[custom_css]"><?php echo esc_textarea( $h['custom_css'] ); ?></textarea></td></tr>
	</table>
	<?php
}

/**
 * Footer settings fields.
 */
function bahar_shop_render_footer_fields() {
	$f = bahar_shop_footer_settings();
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'توضیح برند فوتر', 'bahar-shop' ); ?></th><td><textarea class="large-text" rows="3" name="bahar_shop_footer[description]"><?php echo esc_textarea( $f['description'] ); ?></textarea></td></tr>
		<tr><th><?php esc_html_e( 'عنوان بلوک اضافه', 'bahar-shop' ); ?></th><td><input type="text" class="regular-text" name="bahar_shop_footer[extra_heading]" value="<?php echo esc_attr( $f['extra_heading'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'متن بلوک اضافه', 'bahar-shop' ); ?></th><td><textarea class="large-text" rows="3" name="bahar_shop_footer[extra_text]"><?php echo esc_textarea( $f['extra_text'] ); ?></textarea></td></tr>
		<tr><th><?php esc_html_e( 'دکمه ۱', 'bahar-shop' ); ?></th><td>
			<input type="text" name="bahar_shop_footer[btn1_text]" value="<?php echo esc_attr( $f['btn1_text'] ); ?>" placeholder="<?php esc_attr_e( 'متن', 'bahar-shop' ); ?>" />
			<input type="url" class="regular-text" name="bahar_shop_footer[btn1_url]" value="<?php echo esc_attr( $f['btn1_url'] ); ?>" placeholder="https://" />
		</td></tr>
		<tr><th><?php esc_html_e( 'دکمه ۲', 'bahar-shop' ); ?></th><td>
			<input type="text" name="bahar_shop_footer[btn2_text]" value="<?php echo esc_attr( $f['btn2_text'] ); ?>" placeholder="<?php esc_attr_e( 'متن', 'bahar-shop' ); ?>" />
			<input type="url" class="regular-text" name="bahar_shop_footer[btn2_url]" value="<?php echo esc_attr( $f['btn2_url'] ); ?>" placeholder="https://" />
		</td></tr>
		<tr><th><?php esc_html_e( 'آیکون/لینک اضافه ۱', 'bahar-shop' ); ?></th><td>
			<input type="text" name="bahar_shop_footer[icon1_label]" value="<?php echo esc_attr( $f['icon1_label'] ); ?>" placeholder="<?php esc_attr_e( 'برچسب', 'bahar-shop' ); ?>" />
			<input type="url" class="regular-text" name="bahar_shop_footer[icon1_url]" value="<?php echo esc_attr( $f['icon1_url'] ); ?>" />
		</td></tr>
		<tr><th><?php esc_html_e( 'آیکون/لینک اضافه ۲', 'bahar-shop' ); ?></th><td>
			<input type="text" name="bahar_shop_footer[icon2_label]" value="<?php echo esc_attr( $f['icon2_label'] ); ?>" placeholder="<?php esc_attr_e( 'برچسب', 'bahar-shop' ); ?>" />
			<input type="url" class="regular-text" name="bahar_shop_footer[icon2_url]" value="<?php echo esc_attr( $f['icon2_url'] ); ?>" />
		</td></tr>
		<tr><th><?php esc_html_e( 'CSS اختصاصی فوتر', 'bahar-shop' ); ?></th><td><textarea class="large-text code" rows="5" name="bahar_shop_footer[custom_css]"><?php echo esc_textarea( $f['custom_css'] ); ?></textarea></td></tr>
	</table>
	<?php
}

/**
 * Button settings fields.
 */
function bahar_shop_render_button_fields() {
	$b = bahar_shop_button_settings();
	?>
	<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'دکمه دیدن محصول — پس‌زمینه', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_buttons[visit_bg]" value="<?php echo esc_attr( $b['visit_bg'] ?: '#FFF1F7' ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'دکمه دیدن محصول — متن', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_buttons[visit_color]" value="<?php echo esc_attr( $b['visit_color'] ?: '#7C3AED' ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'دکمه دیدن محصول — حاشیه', 'bahar-shop' ); ?></th><td><input type="color" name="bahar_shop_buttons[visit_border]" value="<?php echo esc_attr( $b['visit_border'] ?: '#F1DCE8' ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'انتخاب گزینه‌ها — پس‌زمینه', 'bahar-shop' ); ?></th><td><input type="text" name="bahar_shop_buttons[variations_bg]" value="<?php echo esc_attr( $b['variations_bg'] ); ?>" placeholder="#7C3AED" /></td></tr>
		<tr><th><?php esc_html_e( 'انتخاب گزینه‌ها — متن', 'bahar-shop' ); ?></th><td><input type="text" name="bahar_shop_buttons[variations_color]" value="<?php echo esc_attr( $b['variations_color'] ); ?>" placeholder="#FFFFFF" /></td></tr>
		<tr><th><?php esc_html_e( 'افزودن به سبد — پس‌زمینه', 'bahar-shop' ); ?></th><td><input type="text" name="bahar_shop_buttons[add_cart_bg]" value="<?php echo esc_attr( $b['add_cart_bg'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'افزودن به سبد — متن', 'bahar-shop' ); ?></th><td><input type="text" name="bahar_shop_buttons[add_cart_color]" value="<?php echo esc_attr( $b['add_cart_color'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'گردی گوشه دکمه‌ها (px)', 'bahar-shop' ); ?></th><td><input type="number" min="0" max="40" name="bahar_shop_buttons[radius]" value="<?php echo esc_attr( (int) $b['radius'] ); ?>" /></td></tr>
		<tr><th><?php esc_html_e( 'CSS اختصاصی دکمه‌ها', 'bahar-shop' ); ?></th><td><textarea class="large-text code" rows="5" name="bahar_shop_buttons[custom_css]"><?php echo esc_textarea( $b['custom_css'] ); ?></textarea></td></tr>
	</table>
	<?php
}
