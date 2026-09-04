<?php
/**
 * UI ماژول robots.txt.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/** @var SEO_Core_Robots_Module $robots */
$robots = $modules['robots'] ?? null;
if ( ! $robots instanceof SEO_Core_Robots_Module ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول robots در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$mode     = (string) get_option( SEO_Core_Robots_Module::OPTION_MODE, 'append' );
$extra    = (string) get_option( SEO_Core_Robots_Module::OPTION_EXTRA, SEO_Core_Robots_Module::default_extra() );
$add_sm   = 'yes' === (string) get_option( SEO_Core_Robots_Module::OPTION_SITEMAP, 'yes' );
$passive  = $robots->is_passive();
$can_emit = $robots->can_emit();
$preview  = SEO_Core_Robots_Module::preview_output();
$public   = ( '1' === (string) get_option( 'blog_public', '1' ) );
?>

<div class="shojaei-card">
	<h3 style="margin-top:0;"><?php echo esc_html( $robots->get_label() ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $robots->get_description() ); ?></p>

	<?php if ( ! $public ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'تنظیم «موتورهای جستجو را تشویق به ایندکس کردن نکن» در وردپرس فعال است — robots.txt کل سایت را Disallow می‌کند.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $passive ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'حالت کمکی: خروجی robots.txt دماوند اعمال نمی‌شود تا با ویرایشگر Rank Math/Yoast تداخل نداشته باشد. برای اعمال، Override را روشن کنید.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php elseif ( $can_emit ) : ?>
		<div class="notice notice-success inline">
			<p><?php esc_html_e( 'خروجی فعال است و روی /robots.txt اعمال می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'ویرایش محتوا', 'shojaei-seo-for-woo' ); ?></h4>
	<p>
		<label>
			<?php esc_html_e( 'حالت:', 'shojaei-seo-for-woo' ); ?>
			<select id="shojaei-robots-mode">
				<option value="append" <?php selected( $mode, 'append' ); ?>><?php esc_html_e( 'افزودن به خروجی وردپرس', 'shojaei-seo-for-woo' ); ?></option>
				<option value="replace" <?php selected( $mode, 'replace' ); ?>><?php esc_html_e( 'جایگزینی کامل', 'shojaei-seo-for-woo' ); ?></option>
			</select>
		</label>
	</p>
	<p>
		<label for="shojaei-robots-extra"><strong><?php esc_html_e( 'قوانین سفارشی', 'shojaei-seo-for-woo' ); ?></strong></label>
		<textarea id="shojaei-robots-extra" class="large-text code" rows="10" dir="ltr" style="width:100%;"><?php echo esc_textarea( $extra ); ?></textarea>
	</p>
	<p>
		<label style="display:flex;align-items:center;gap:8px;">
			<input type="checkbox" id="shojaei-robots-sitemap" <?php checked( $add_sm ); ?> />
			<?php esc_html_e( 'افزودن خودکار خط Sitemap', 'shojaei-seo-for-woo' ); ?>
			<code dir="ltr"><?php echo esc_html( SEO_Core_Robots_Module::preferred_sitemap_url() ); ?></code>
		</label>
	</p>
	<p>
		<button type="button" class="button button-primary" id="shojaei-robots-save"><?php esc_html_e( 'ذخیره', 'shojaei-seo-for-woo' ); ?></button>
		<a class="button" href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'مشاهده robots.txt', 'shojaei-seo-for-woo' ); ?></a>
		<span id="shojaei-robots-status" class="description" aria-live="polite"></span>
	</p>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'پیش‌نمایش تقریبی', 'shojaei-seo-for-woo' ); ?></h4>
	<pre id="shojaei-robots-preview" dir="ltr" style="background:#f6f7f7;padding:12px;overflow:auto;max-height:280px;"><?php echo esc_html( $preview ); ?></pre>
</div>

<div class="shojaei-edu-tip">
	<span class="dashicons dashicons-lightbulb"></span>
	<?php esc_html_e( 'متای robots صفحات (noindex و …) جداست:', 'shojaei-seo-for-woo' ); ?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=general-meta' ) ); ?>"><?php esc_html_e( 'متای عمومی ←', 'shojaei-seo-for-woo' ); ?></a>
</div>
