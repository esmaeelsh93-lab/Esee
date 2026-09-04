<?php
/**
 * Keyword Maps — نابغه لینک.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول نابغه لینک در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$maps = Shojaei_SEO_Link_Genius::list_maps( 200 );
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'نقشه کلمات کلیدی', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'قوانین لینک‌سازی خودکار: نام، کلمات کلیدی مشابه، و آدرس مقصد. پس از ذخیره، در موتور لینک‌ساز فعال می‌شوند.', 'shojaei-seo-for-woo' ); ?></p>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'افزودن نقشه جدید', 'shojaei-seo-for-woo' ); ?></h3>
	<form id="shojaei-lg-map-form" class="shojaei-lg-map-form">
		<input type="hidden" name="map_id" id="shojaei-lg-map-id" value="0" />
		<div class="shojaei-form-grid" style="display:grid;gap:12px;grid-template-columns:1fr 1fr;">
			<label>
				<?php esc_html_e( 'نام نقشه', 'shojaei-seo-for-woo' ); ?>
				<input type="text" name="name" id="shojaei-lg-map-name" class="regular-text" required style="width:100%;" />
			</label>
			<label>
				<?php esc_html_e( 'آدرس مقصد', 'shojaei-seo-for-woo' ); ?>
				<input type="url" name="target_url" id="shojaei-lg-map-url" class="regular-text" required dir="ltr" style="width:100%;" placeholder="https://…" />
			</label>
		</div>
		<label style="display:block;margin-top:12px;">
			<?php esc_html_e( 'کلمات کلیدی و عبارت‌های مشابه (هر خط یک مورد)', 'shojaei-seo-for-woo' ); ?>
			<textarea name="keywords" id="shojaei-lg-map-keywords" rows="4" class="large-text" required placeholder="<?php esc_attr_e( "ساعت هوشمند\nساعت شیائومی\nsmart watch", 'shojaei-seo-for-woo' ); ?>"></textarea>
		</label>
		<div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;align-items:center;">
			<label>
				<?php esc_html_e( 'حداکثر لینک در هر نوشته', 'shojaei-seo-for-woo' ); ?>
				<input type="number" name="max_per_post" id="shojaei-lg-map-max" value="3" min="1" max="20" class="small-text" />
			</label>
			<label>
				<input type="checkbox" name="case_sensitive" id="shojaei-lg-map-case" value="1" />
				<?php esc_html_e( 'حساسیت به حروف کوچک/بزرگ', 'shojaei-seo-for-woo' ); ?>
			</label>
			<label>
				<input type="checkbox" name="is_active" id="shojaei-lg-map-active" value="1" checked />
				<?php esc_html_e( 'فعال', 'shojaei-seo-for-woo' ); ?>
			</label>
		</div>
		<p style="margin-top:14px;">
			<button type="submit" class="button button-primary" id="shojaei-lg-map-save"><?php esc_html_e( 'ذخیره نقشه', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button" id="shojaei-lg-map-reset"><?php esc_html_e( 'پاک کردن فرم', 'shojaei-seo-for-woo' ); ?></button>
		</p>
		<div id="shojaei-lg-map-result" class="shojaei-test-result" style="display:none;" aria-live="polite"></div>
	</form>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'قوانین ذخیره‌شده', 'shojaei-seo-for-woo' ); ?></h3>
	<?php if ( empty( $maps ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-networking"></span>
			<p><?php esc_html_e( 'هنوز نقشه‌ای تعریف نشده.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" id="shojaei-lg-maps-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'نام', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'کلمات کلیدی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مقصد', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'سقف', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'فعال', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $maps as $map ) : ?>
					<?php
					$kws = Shojaei_SEO_Link_Genius::parse_keywords( (string) $map->keywords );
					?>
					<tr
						data-id="<?php echo esc_attr( (string) $map->id ); ?>"
						data-name="<?php echo esc_attr( (string) $map->name ); ?>"
						data-url="<?php echo esc_attr( (string) $map->target_url ); ?>"
						data-keywords="<?php echo esc_attr( (string) $map->keywords ); ?>"
						data-max="<?php echo esc_attr( (string) $map->max_per_post ); ?>"
						data-case="<?php echo esc_attr( (string) (int) $map->case_sensitive ); ?>"
						data-active="<?php echo esc_attr( (string) (int) $map->is_active ); ?>"
					>
						<td><strong><?php echo esc_html( (string) $map->name ); ?></strong></td>
						<td><?php echo esc_html( implode( ' · ', array_slice( $kws, 0, 5 ) ) ); ?><?php echo count( $kws ) > 5 ? '…' : ''; ?></td>
						<td dir="ltr"><a href="<?php echo esc_url( (string) $map->target_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_parse_url( (string) $map->target_url, PHP_URL_PATH ) ?: (string) $map->target_url ); ?></a></td>
						<td><?php echo esc_html( (string) (int) $map->max_per_post ); ?></td>
						<td>
							<label class="shojaei-switch">
								<input type="checkbox" class="shojaei-lg-map-toggle" data-id="<?php echo esc_attr( (string) $map->id ); ?>" <?php checked( (int) $map->is_active, 1 ); ?> />
							</label>
						</td>
						<td>
							<button type="button" class="button button-small shojaei-lg-map-edit"><?php esc_html_e( 'ویرایش', 'shojaei-seo-for-woo' ); ?></button>
							<button type="button" class="button button-small shojaei-lg-map-delete" data-id="<?php echo esc_attr( (string) $map->id ); ?>"><?php esc_html_e( 'حذف', 'shojaei-seo-for-woo' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
