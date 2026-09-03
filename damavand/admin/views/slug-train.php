<?php
/**
 * Finglish slug training — teach store-specific words.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shojaei_SEO_Slug' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول نامک در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$custom  = Shojaei_SEO_Slug::custom_word_map();
$builtin = count( Shojaei_SEO_Slug::builtin_word_map() );
$sample  = 'کتونی نیوبالانس ۵۳۰ مردانه مشکی سفید';
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'آموزش نامک', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php esc_html_e( 'به فروشگاه یاد بده هر واژهٔ فارسی در نامک چطور نوشته شود. مثال: نیوبالانس = new-balance — بعد از ذخیره، پیشنهاد فینگلیش همان را به‌کار می‌برد.', 'shojaei-seo-for-woo' ); ?>
	</p>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'واژه جدید', 'shojaei-seo-for-woo' ); ?></h4>
	<div class="shojaei-filter-form" style="align-items:flex-end;">
		<label style="flex:1 1 180px;">
			<span class="description"><?php esc_html_e( 'فارسی (در عنوان محصول)', 'shojaei-seo-for-woo' ); ?></span><br />
			<input type="text" id="shojaei-slug-train-fa" placeholder="<?php esc_attr_e( 'نیوبالانس', 'shojaei-seo-for-woo' ); ?>" />
		</label>
		<label style="flex:1 1 180px;">
			<span class="description"><?php esc_html_e( 'لاتین نامک', 'shojaei-seo-for-woo' ); ?></span><br />
			<input type="text" id="shojaei-slug-train-en" dir="ltr" placeholder="new-balance" />
		</label>
		<div class="shojaei-filter-actions">
			<button type="button" class="button" id="shojaei-slug-train-preview"><?php esc_html_e( 'پیش‌نمایش', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-primary" id="shojaei-slug-train-save"><?php esc_html_e( 'یاد بده و ذخیره', 'shojaei-seo-for-woo' ); ?></button>
		</div>
	</div>
	<p id="shojaei-slug-train-status" class="description" aria-live="polite" style="margin-top:10px;"></p>
	<p class="description" style="margin-top:8px;">
		<?php esc_html_e( 'نمونه عنوان:', 'shojaei-seo-for-woo' ); ?>
		<code><?php echo esc_html( $sample ); ?></code>
		→
		<code dir="ltr" id="shojaei-slug-train-sample"><?php echo esc_html( Shojaei_SEO_Slug::transliterate( $sample ) ); ?></code>
	</p>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;">
		<?php
		printf(
			/* translators: 1: custom count 2: builtin count */
			esc_html__( 'واژه‌های سفارشی این فروشگاه (%1$d) — دیکشنری داخلی %2$d واژه', 'shojaei-seo-for-woo' ),
			count( $custom ),
			(int) $builtin
		);
		?>
	</h4>
	<?php if ( empty( $custom ) ) : ?>
		<p class="description"><?php esc_html_e( 'هنوز واژه‌ای اضافه نشده. از فرم بالا شروع کنید.', 'shojaei-seo-for-woo' ); ?></p>
	<?php else : ?>
		<table class="widefat striped shojaei-table" id="shojaei-slug-train-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'فارسی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نامک', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'حذف', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $custom as $fa => $en ) : ?>
					<tr>
						<td><?php echo esc_html( $fa ); ?></td>
						<td dir="ltr"><code><?php echo esc_html( $en ); ?></code></td>
						<td>
							<button type="button" class="button button-small shojaei-slug-train-del" data-fa="<?php echo esc_attr( $fa ); ?>"><?php esc_html_e( 'حذف', 'shojaei-seo-for-woo' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
