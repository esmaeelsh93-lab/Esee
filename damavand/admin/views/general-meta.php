<?php
/**
 * General Meta settings — متای عمومی (Persian).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$enabled     = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_enabled', 'no' );
$force       = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_force_with_competitors', 'no' );
$competitors = class_exists( 'Shojaei_SEO_General_Meta' ) ? Shojaei_SEO_General_Meta::competitor_names() : array();
$has_comp    = ! empty( $competitors );
$sep         = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_separator', '-' );
$sep_custom  = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_separator_custom', '' );
$og_id       = absint( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_og_image_id', 0 ) );
$og_url      = $og_id ? wp_get_attachment_image_url( $og_id, 'medium' ) : '';
$noindex     = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_noindex', 'no' );
$index_on    = ( 'yes' !== $noindex );
?>

<form method="post" action="" class="shojaei-settings-form shojaei-general-meta-form">
	<?php wp_nonce_field( 'shojaei_seo_save_settings', 'shojaei_seo_settings_nonce' ); ?>
	<input type="hidden" name="shojaei_seo_save_general_meta" value="1" />

	<div class="shojaei-card">
		<h3><?php esc_html_e( 'متای عمومی', 'shojaei-seo-for-woo' ); ?></h3>
		<p class="shojaei-desc">
			<?php esc_html_e( 'مقادیر پیش‌فرض ربات‌ها، جداکننده عنوان و تصویر اشتراک‌گذاری. این‌ها را می‌توان بعداً برای هر نوشته یا دسته‌بندی جداگانه تغییر داد.', 'shojaei-seo-for-woo' ); ?>
		</p>

		<?php if ( $has_comp ) : ?>
			<div class="notice notice-warning inline" style="margin:12px 0;padding:12px 14px;">
				<p style="margin:0 0 8px;">
					<strong><?php esc_html_e( 'تداخل احتمالی متا', 'shojaei-seo-for-woo' ); ?></strong>
					—
					<?php
					printf(
						/* translators: %s: plugin names */
						esc_html__( 'افزونهٔ %s هم‌اکنون فعال است و معمولاً خودش متا ربات و OpenGraph چاپ می‌کند. برای جلوگیری از دو متا، یکی را انتخاب کنید:', 'shojaei-seo-for-woo' ),
						esc_html( implode( ' / ', $competitors ) )
					);
					?>
				</p>
				<ul style="margin:0 1.2em 8px;">
					<li><?php esc_html_e( 'پیشنهاد: متای عمومی همان افزونه را خاموش کنید و خروجی متای دماوند را روشن کنید — یا برعکس.', 'shojaei-seo-for-woo' ); ?></li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'مدیریت افزونه‌ها', 'shojaei-seo-for-woo' ); ?></a>
						<?php if ( Shojaei_SEO_Helpers::is_rank_math_active() ) : ?>
							· <a href="<?php echo esc_url( admin_url( 'admin.php?page=rank-math-options-titles' ) ); ?>"><?php esc_html_e( 'تنظیمات عنوان Rank Math', 'shojaei-seo-for-woo' ); ?></a>
						<?php endif; ?>
						<?php if ( Shojaei_SEO_Helpers::is_yoast_active() ) : ?>
							· <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpseo_titles' ) ); ?>"><?php esc_html_e( 'تنظیمات عنوان Yoast', 'shojaei-seo-for-woo' ); ?></a>
						<?php endif; ?>
					</li>
				</ul>
				<label class="shojaei-setting-item" style="display:block;margin-top:6px;">
					<input type="checkbox" name="shojaei_seo_meta_force_with_competitors" value="yes" <?php checked( $force, 'yes' ); ?> />
					<span><?php esc_html_e( 'می‌دانم تداخل ممکن است؛ خروجی متای دماوند را همزمان فعال کن', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
		<?php endif; ?>

		<label class="shojaei-setting-item" style="display:flex;gap:10px;align-items:flex-start;padding:12px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:8px;">
			<input type="checkbox" name="shojaei_seo_meta_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?> style="margin-top:3px;" />
			<span>
				<strong><?php esc_html_e( 'فعال‌سازی خروجی متای عمومی دماوند', 'shojaei-seo-for-woo' ); ?></strong><br />
				<span class="description"><?php esc_html_e( 'با خاموش بودن این گزینه، تنظیمات زیر ذخیره می‌شوند ولی در سایت اعمال نمی‌شوند.', 'shojaei-seo-for-woo' ); ?></span>
			</span>
		</label>
	</div>

	<div class="shojaei-card shojaei-meta-section">
		<h3><?php esc_html_e( 'متا ربات‌ها', 'shojaei-seo-for-woo' ); ?></h3>
		<p class="description"><?php esc_html_e( 'مقادیر پیش‌فرض ربات‌های متاتگ؛ برای نوشته‌ها و طبقه‌بندی‌های خاص می‌توان جداگانه تغییر داد.', 'shojaei-seo-for-woo' ); ?></p>
		<div class="shojaei-meta-checks">
			<label><input type="checkbox" name="shojaei_seo_meta_robots_index" value="yes" <?php checked( $index_on ); ?> /> <?php esc_html_e( 'نمایه‌سازی (index)', 'shojaei-seo-for-woo' ); ?></label>
			<label><input type="checkbox" name="shojaei_seo_meta_robots_noindex" value="yes" <?php checked( $noindex, 'yes' ); ?> /> <?php esc_html_e( 'بدون نمایه (noindex)', 'shojaei-seo-for-woo' ); ?></label>
			<label><input type="checkbox" name="shojaei_seo_meta_robots_nofollow" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_nofollow', 'no' ), 'yes' ); ?> /> <?php esc_html_e( 'بدون دنبال‌کردن لینک (nofollow)', 'shojaei-seo-for-woo' ); ?></label>
			<label><input type="checkbox" name="shojaei_seo_meta_robots_noarchive" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_noarchive', 'no' ), 'yes' ); ?> /> <?php esc_html_e( 'بدون بایگانی (noarchive)', 'shojaei-seo-for-woo' ); ?></label>
			<label><input type="checkbox" name="shojaei_seo_meta_robots_noimageindex" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_noimageindex', 'no' ), 'yes' ); ?> /> <?php esc_html_e( 'بدون نمایه تصویر (noimageindex)', 'shojaei-seo-for-woo' ); ?></label>
			<label><input type="checkbox" name="shojaei_seo_meta_robots_nosnippet" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_nosnippet', 'no' ), 'yes' ); ?> /> <?php esc_html_e( 'بدون اسنیپت (nosnippet)', 'shojaei-seo-for-woo' ); ?></label>
		</div>
	</div>

	<div class="shojaei-card shojaei-meta-section">
		<h3><?php esc_html_e( 'متا ربات‌های پیشرفته', 'shojaei-seo-for-woo' ); ?></h3>
		<div class="shojaei-meta-advanced">
			<label class="shojaei-meta-adv-row">
				<input type="checkbox" name="shojaei_seo_meta_adv_snippet" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_adv_snippet', 'no' ), 'yes' ); ?> />
				<span><?php esc_html_e( 'اسنیپت (max-snippet)', 'shojaei-seo-for-woo' ); ?></span>
				<input type="number" name="shojaei_seo_meta_max_snippet" value="<?php echo esc_attr( (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_max_snippet', -1 ) ); ?>" min="-1" step="1" />
				<span class="description"><?php esc_html_e( '‎-1 = بدون محدودیت', 'shojaei-seo-for-woo' ); ?></span>
			</label>
			<label class="shojaei-meta-adv-row">
				<input type="checkbox" name="shojaei_seo_meta_adv_video" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_adv_video', 'no' ), 'yes' ); ?> />
				<span><?php esc_html_e( 'پیش‌نمایش ویدئو (max-video-preview)', 'shojaei-seo-for-woo' ); ?></span>
				<input type="number" name="shojaei_seo_meta_max_video_preview" value="<?php echo esc_attr( (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_max_video_preview', -1 ) ); ?>" min="-1" step="1" />
			</label>
			<label class="shojaei-meta-adv-row">
				<input type="checkbox" name="shojaei_seo_meta_adv_image" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_adv_image', 'yes' ), 'yes' ); ?> />
				<span><?php esc_html_e( 'پیش‌نمایش تصویر (max-image-preview)', 'shojaei-seo-for-woo' ); ?></span>
				<select name="shojaei_seo_meta_max_image_preview">
					<?php
					$img_prev = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_max_image_preview', 'large' );
					$opts     = array(
						'large'    => __( 'بزرگ', 'shojaei-seo-for-woo' ),
						'standard' => __( 'استاندارد', 'shojaei-seo-for-woo' ),
						'none'     => __( 'هیچ', 'shojaei-seo-for-woo' ),
					);
					foreach ( $opts as $val => $label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $val ),
							selected( $img_prev, $val, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
			</label>
		</div>
	</div>

	<div class="shojaei-card shojaei-meta-section">
		<h3><?php esc_html_e( 'نمایه نکردن دسته و برچسب خالی', 'shojaei-seo-for-woo' ); ?></h3>
		<label class="shojaei-setting-item" style="display:flex;gap:10px;align-items:flex-start;">
			<input type="checkbox" name="shojaei_seo_meta_noindex_empty_tax" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_noindex_empty_tax', 'yes' ), 'yes' ); ?> style="margin-top:3px;" />
			<span>
				<strong><?php esc_html_e( 'بایگانی‌های خالی را noindex کن', 'shojaei-seo-for-woo' ); ?></strong><br />
				<span class="description"><?php esc_html_e( 'برای جلوگیری از نمایه شدن صفحات کم‌محتوا مفید است. به‌محض افزودن نوشته، صفحه دوباره قابل ایندکس می‌شود.', 'shojaei-seo-for-woo' ); ?></span>
			</span>
		</label>
	</div>

	<div class="shojaei-card shojaei-meta-section">
		<h3><?php esc_html_e( 'بودجه خزش (Crawl Budget) — ووکامرس ایران', 'shojaei-seo-for-woo' ); ?></h3>
		<p class="description"><?php esc_html_e( 'این قواعد حتی اگر «متای عمومی» خاموش باشد اعمال می‌شوند، مگر Rank Math/Yoast مالک متا باشد. پیشنهاد حرفه‌ای: همه روشن بمانند.', 'shojaei-seo-for-woo' ); ?></p>
		<label class="shojaei-setting-item" style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px;">
			<input type="checkbox" name="shojaei_seo_meta_noindex_wc_system" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_noindex_wc_system', 'yes' ), 'yes' ); ?> style="margin-top:3px;" />
			<span>
				<strong><?php esc_html_e( 'سبد خرید، تسویه، حساب کاربری و endpointهای ووکامرس', 'shojaei-seo-for-woo' ); ?></strong><br />
				<span class="description"><?php esc_html_e( 'صفحات تراکنشی ارزش ایندکس ندارند؛ noindex,follow.', 'shojaei-seo-for-woo' ); ?></span>
			</span>
		</label>
		<label class="shojaei-setting-item" style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px;">
			<input type="checkbox" name="shojaei_seo_meta_noindex_facets" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_noindex_facets', 'yes' ), 'yes' ); ?> style="margin-top:3px;" />
			<span>
				<strong><?php esc_html_e( 'فیلتر/مرتب‌سازی/قیمت (Faceted URLs)', 'shojaei-seo-for-woo' ); ?></strong><br />
				<span class="description"><?php esc_html_e( 'URLهای ?orderby، ?min_price، filter_* و مشابه — جلوگیری از ایندکس هزاران ترکیب فیلتر.', 'shojaei-seo-for-woo' ); ?></span>
			</span>
		</label>
		<label class="shojaei-setting-item" style="display:flex;gap:10px;align-items:flex-start;">
			<input type="checkbox" name="shojaei_seo_meta_noindex_author_date" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_noindex_author_date', 'yes' ), 'yes' ); ?> style="margin-top:3px;" />
			<span>
				<strong><?php esc_html_e( 'بایگانی نویسنده و تاریخ', 'shojaei-seo-for-woo' ); ?></strong><br />
				<span class="description"><?php esc_html_e( 'برای فروشگاه‌های ایرانی تقریباً همیشه thin content است.', 'shojaei-seo-for-woo' ); ?></span>
			</span>
		</label>
		<p class="description" style="margin-top:12px;">
			<?php esc_html_e( 'جستجوی داخلی و صفحات ۴۰۴ همیشه noindex می‌شوند (بدون سوئیچ).', 'shojaei-seo-for-woo' ); ?>
		</p>
	</div>

	<div class="shojaei-card shojaei-meta-section">
		<h3><?php esc_html_e( 'قالب عنوان و توضیح (SERP)', 'shojaei-seo-for-woo' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'اگر برای محصول/نوشته عنوان یا توضیح اختصاصی خالی باشد، این قالب‌ها استفاده می‌شوند. در فیلدهای اختصاصی هم می‌توانید از همین توکن‌ها استفاده کنید.', 'shojaei-seo-for-woo' ); ?>
		</p>
		<?php if ( class_exists( 'Damavand_SEO_Templates' ) ) : ?>
			<?php
			$tpl_rows = array(
				array(
					'label' => __( 'محصول', 'shojaei-seo-for-woo' ),
					'title' => Damavand_SEO_Templates::OPT_PRODUCT_TITLE,
					'desc'  => Damavand_SEO_Templates::OPT_PRODUCT_DESC,
				),
				array(
					'label' => __( 'نوشته', 'shojaei-seo-for-woo' ),
					'title' => Damavand_SEO_Templates::OPT_POST_TITLE,
					'desc'  => Damavand_SEO_Templates::OPT_POST_DESC,
				),
				array(
					'label' => __( 'برگه', 'shojaei-seo-for-woo' ),
					'title' => Damavand_SEO_Templates::OPT_PAGE_TITLE,
					'desc'  => Damavand_SEO_Templates::OPT_PAGE_DESC,
				),
			);
			?>
			<div class="shojaei-meta-tpl-grid">
				<?php foreach ( $tpl_rows as $row ) : ?>
					<div class="shojaei-meta-tpl-block">
						<strong class="shojaei-meta-tpl-type"><?php echo esc_html( $row['label'] ); ?></strong>
						<label>
							<span><?php esc_html_e( 'قالب عنوان', 'shojaei-seo-for-woo' ); ?></span>
							<input type="text" class="widefat" dir="rtl" name="<?php echo esc_attr( $row['title'] ); ?>" value="<?php echo esc_attr( Damavand_SEO_Templates::get_template( $row['title'] ) ); ?>" />
						</label>
						<label>
							<span><?php esc_html_e( 'قالب توضیح', 'shojaei-seo-for-woo' ); ?></span>
							<textarea class="widefat" rows="2" dir="rtl" name="<?php echo esc_attr( $row['desc'] ); ?>"><?php echo esc_textarea( Damavand_SEO_Templates::get_template( $row['desc'] ) ); ?></textarea>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
			<details class="shojaei-meta-tpl-tokens" style="margin-top:12px;">
				<summary><?php esc_html_e( 'توکن‌های قابل استفاده', 'shojaei-seo-for-woo' ); ?></summary>
				<ul style="margin:8px 1.2em 0;columns:2;gap:16px;">
					<?php foreach ( Damavand_SEO_Templates::token_help() as $token => $help ) : ?>
						<li><code dir="ltr"><?php echo esc_html( $token ); ?></code> — <?php echo esc_html( $help ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>
	</div>

	<div class="shojaei-card shojaei-meta-section">
		<h3><?php esc_html_e( 'کاراکتر جداکننده', 'shojaei-seo-for-woo' ); ?></h3>
		<p class="description"><?php esc_html_e( 'با وارد کردن %separator% یا %sep% در قالب‌های عنوان می‌توانید از این کاراکتر استفاده کنید. پیش‌فرض: خط تیره (-).', 'shojaei-seo-for-woo' ); ?></p>
		<div class="shojaei-meta-sep-group" role="group">
			<?php
			$choices = class_exists( 'Shojaei_SEO_General_Meta' ) ? Shojaei_SEO_General_Meta::separator_choices() : array( '-' );
			foreach ( $choices as $choice ) :
				?>
				<label class="shojaei-meta-sep-chip<?php echo ( $sep === $choice ) ? ' is-active' : ''; ?>">
					<input type="radio" name="shojaei_seo_meta_separator" value="<?php echo esc_attr( $choice ); ?>" <?php checked( $sep, $choice ); ?> />
					<span><?php echo esc_html( $choice ); ?></span>
				</label>
			<?php endforeach; ?>
			<label class="shojaei-meta-sep-chip<?php echo ( 'custom' === $sep ) ? ' is-active' : ''; ?>">
				<input type="radio" name="shojaei_seo_meta_separator" value="custom" <?php checked( $sep, 'custom' ); ?> />
				<span><?php esc_html_e( 'سفارشی', 'shojaei-seo-for-woo' ); ?></span>
			</label>
		</div>
		<p id="shojaei-meta-sep-custom-wrap" style="<?php echo ( 'custom' === $sep ) ? '' : 'display:none;'; ?>margin-top:10px;">
			<label>
				<?php esc_html_e( 'جداکننده سفارشی', 'shojaei-seo-for-woo' ); ?>
				<input type="text" name="shojaei_seo_meta_separator_custom" value="<?php echo esc_attr( $sep_custom ); ?>" maxlength="3" class="small-text" dir="ltr" />
			</label>
		</p>
	</div>

	<div class="shojaei-card shojaei-meta-section">
		<h3><?php esc_html_e( 'تصویر بندانگشتی OpenGraph', 'shojaei-seo-for-woo' ); ?></h3>
		<p class="description"><?php esc_html_e( 'وقتی تصویر شاخص یا OpenGraph جداگانه برای نوشته/برگه تنظیم نشده باشد، این تصویر استفاده می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
		<input type="hidden" name="shojaei_seo_meta_og_image_id" id="shojaei_seo_meta_og_image_id" value="<?php echo esc_attr( (string) $og_id ); ?>" />
		<div id="shojaei-meta-og-preview" style="<?php echo $og_url ? '' : 'display:none;'; ?>margin-bottom:10px;">
			<?php if ( $og_url ) : ?>
				<img src="<?php echo esc_url( $og_url ); ?>" alt="" style="max-width:180px;height:auto;border-radius:6px;" />
			<?php endif; ?>
		</div>
		<p>
			<button type="button" class="button" id="shojaei-meta-og-upload"><?php esc_html_e( 'آپلود / انتخاب فایل', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button-link-delete" id="shojaei-meta-og-remove" <?php echo $og_id ? '' : 'hidden'; ?>><?php esc_html_e( 'حذف تصویر', 'shojaei-seo-for-woo' ); ?></button>
		</p>
	</div>

	<p>
		<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'ذخیره متای عمومی', 'shojaei-seo-for-woo' ); ?></button>
	</p>
</form>
