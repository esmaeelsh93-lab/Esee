<?php
/**
 * UI ماژول نقشه سایت هوشمند + دیباگ GSC.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/** @var SEO_Core_Sitemap $sitemap */
$sitemap = $modules['sitemap'] ?? null;
if ( ! $sitemap instanceof SEO_Core_Sitemap ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول نقشه سایت در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$index_url = $sitemap->public_url( 'index' );
$can_emit  = $sitemap->can_emit();
$stats     = $sitemap->get_stats();
$fallback  = (int) get_option( 'seo_core_sitemap_fallback_hits', 0 );
$maps = array(
	'posts'         => __( 'نوشته‌ها', 'shojaei-seo-for-woo' ),
	'pages'         => __( 'برگه‌ها (+ خانه)', 'shojaei-seo-for-woo' ),
	'products'      => __( 'محصولات (+ تصویر/گالری)', 'shojaei-seo-for-woo' ),
	'categories'    => __( 'دسته نوشته', 'shojaei-seo-for-woo' ),
	'product-cats'  => __( 'دسته محصول', 'shojaei-seo-for-woo' ),
	'product-tags'  => __( 'برچسب محصول', 'shojaei-seo-for-woo' ),
);
$type_labels = array_merge(
	array( 'index' => __( 'ایندکس', 'shojaei-seo-for-woo' ) ),
	$maps
);

$alias_url = home_url( '/sitemap.xml' );
$opt = static function ( string $key, string $default = 'yes' ): bool {
	return 'yes' === (string) get_option( $key, $default );
};

$health_report = null;
if ( class_exists( 'SEO_Core_Sitemap_Health' ) && $can_emit ) {
	$health_report = SEO_Core_Sitemap_Health::get_report( $sitemap, false );
}
?>

<div class="shojaei-card" dir="rtl">
	<h3 style="margin-top:0;"><?php esc_html_e( 'نقشه سایت هوشمند (جایگزین قوی‌تر از wp-sitemap)', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $sitemap->get_description() ); ?></p>

	<?php if ( $can_emit ) : ?>
		<div class="notice notice-success inline">
			<p>
				<?php esc_html_e( 'اندپوینت فعال است. هسته wp-sitemap وردپرس هنگام فعال بودن این ماژول خاموش می‌شود تا تداخل نداشته باشد.', 'shojaei-seo-for-woo' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'در Search Console همین آدرس را ثبت کنید (نه sitemap_index.xml رنک‌مث). robots.txt هم باید به Damavand اشاره کند.', 'shojaei-seo-for-woo' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'ماژول خاموش است.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<div class="shojaei-card" dir="rtl">
	<h4 style="margin-top:0;"><?php esc_html_e( 'محتوای نقشه (برای همه فروشگاه‌ها)', 'shojaei-seo-for-woo' ); ?></h4>
	<div class="shojaei-meta-checks" id="shojaei-sitemap-settings">
		<label><input type="checkbox" name="seo_core_sitemap_include_posts" value="1" <?php checked( $opt( 'seo_core_sitemap_include_posts' ) ); ?> /> <?php esc_html_e( 'نوشته‌ها', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_include_pages" value="1" <?php checked( $opt( 'seo_core_sitemap_include_pages' ) ); ?> /> <?php esc_html_e( 'برگه‌ها + صفحه خانه', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_include_products" value="1" <?php checked( $opt( 'seo_core_sitemap_include_products' ) ); ?> /> <?php esc_html_e( 'محصولات', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_include_categories" value="1" <?php checked( $opt( 'seo_core_sitemap_include_categories' ) ); ?> /> <?php esc_html_e( 'دسته نوشته', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_include_product_cats" value="1" <?php checked( $opt( 'seo_core_sitemap_include_product_cats' ) ); ?> /> <?php esc_html_e( 'دسته محصول', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_include_product_tags" value="1" <?php checked( $opt( 'seo_core_sitemap_include_product_tags' ) ); ?> /> <?php esc_html_e( 'برچسب محصول', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_product_gallery" value="1" <?php checked( $opt( 'seo_core_sitemap_product_gallery' ) ); ?> /> <?php esc_html_e( 'تصاویر گالری محصول در XML', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_alias_xml" value="1" <?php checked( $opt( 'seo_core_sitemap_alias_xml' ) ); ?> /> <?php esc_html_e( 'alias عمومی /sitemap.xml', 'shojaei-seo-for-woo' ); ?></label>
		<label><input type="checkbox" name="seo_core_sitemap_claim_robots" value="1" <?php checked( $opt( 'seo_core_sitemap_claim_robots' ) ); ?> /> <?php esc_html_e( 'ثبت خودکار در robots.txt (جایگزین خط مرده Rank Math)', 'shojaei-seo-for-woo' ); ?></label>
	</div>
	<p style="margin-top:12px;">
		<button type="button" class="button button-primary" id="shojaei-sitemap-save-settings"><?php esc_html_e( 'ذخیره تنظیمات نقشه', 'shojaei-seo-for-woo' ); ?></button>
	</p>
</div>

<div class="shojaei-card" dir="rtl">
	<label for="shojaei-sitemap-url"><strong><?php esc_html_e( 'آدرس ایندکس برای Google Search Console', 'shojaei-seo-for-woo' ); ?></strong></label>
	<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;align-items:center;">
		<input type="text" id="shojaei-sitemap-url" class="regular-text" readonly value="<?php echo esc_attr( $index_url ); ?>" style="min-width:min(100%,420px);" />
		<button type="button" class="button button-primary" id="shojaei-sitemap-copy"><?php esc_html_e( 'کپی URL', 'shojaei-seo-for-woo' ); ?></button>
		<?php if ( $can_emit ) : ?>
			<a class="button" href="<?php echo esc_url( $index_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'مشاهده XML', 'shojaei-seo-for-woo' ); ?></a>
			<a class="button" href="<?php echo esc_url( $alias_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'alias /sitemap.xml', 'shojaei-seo-for-woo' ); ?></a>
		<?php endif; ?>
		<button type="button" class="button" id="shojaei-sitemap-flush"><?php esc_html_e( 'پاک کردن کش + فلاش rewrite', 'shojaei-seo-for-woo' ); ?></button>
		<button type="button" class="button" id="shojaei-sitemap-rebuild"><?php esc_html_e( 'بازتولید ایندکس', 'shojaei-seo-for-woo' ); ?></button>
	</div>
	<p class="description" dir="ltr"><?php echo esc_html( $alias_url ); ?></p>
	<p id="shojaei-sitemap-status" class="description" aria-live="polite"></p>
</div>

<div class="shojaei-card" dir="rtl">
	<h4 style="margin-top:0;"><?php esc_html_e( 'ساب‌مپ‌ها', 'shojaei-seo-for-woo' ); ?></h4>
	<table class="widefat striped shojaei-table shojaei-sitemap-table dm-responsive-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'آدرس نمونه', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'URLها', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'صفحات', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'آخرین lastmod', 'shojaei-seo-for-woo' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td data-label="<?php esc_attr_e( 'نوع', 'shojaei-seo-for-woo' ); ?>"><?php esc_html_e( 'ایندکس', 'shojaei-seo-for-woo' ); ?></td>
				<td data-label="<?php esc_attr_e( 'آدرس نمونه', 'shojaei-seo-for-woo' ); ?>">
					<code dir="ltr"><?php echo esc_html( $index_url ); ?></code>
					<?php if ( $can_emit ) : ?>
						— <a href="<?php echo esc_url( $index_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'باز', 'shojaei-seo-for-woo' ); ?></a>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e( 'URLها', 'shojaei-seo-for-woo' ); ?>"><?php echo isset( $stats['index']['urls'] ) ? esc_html( (string) (int) $stats['index']['urls'] ) : '—'; ?></td>
				<td data-label="<?php esc_attr_e( 'صفحات', 'shojaei-seo-for-woo' ); ?>">1</td>
				<td data-label="<?php esc_attr_e( 'آخرین lastmod', 'shojaei-seo-for-woo' ); ?>" dir="ltr"><?php echo isset( $stats['index']['lastmod'] ) ? esc_html( (string) $stats['index']['lastmod'] ) : '—'; ?></td>
			</tr>
			<?php foreach ( $maps as $key => $label ) : ?>
				<?php
				$row   = isset( $stats[ $key ] ) && is_array( $stats[ $key ] ) ? $stats[ $key ] : array();
				$pages = max( 1, (int) ( $row['pages'] ?? 1 ) );
				$url   = $sitemap->public_url( $key, 1 );
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'نوع', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_html( $label ); ?></td>
					<td data-label="<?php esc_attr_e( 'آدرس نمونه', 'shojaei-seo-for-woo' ); ?>">
						<code dir="ltr"><?php echo esc_html( $url ); ?></code>
						<?php if ( $can_emit ) : ?>
							— <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'باز', 'shojaei-seo-for-woo' ); ?></a>
						<?php endif; ?>
						<?php if ( $pages > 1 ) : ?>
							<br /><span class="description"><?php echo esc_html( sprintf( /* translators: %d: pages */ __( 'صفحه‌بندی تا %d فایل', 'shojaei-seo-for-woo' ), $pages ) ); ?></span>
						<?php endif; ?>
					</td>
					<td data-label="<?php esc_attr_e( 'URLها', 'shojaei-seo-for-woo' ); ?>"><?php echo isset( $row['urls_total'] ) ? esc_html( (string) (int) $row['urls_total'] ) : '—'; ?></td>
					<td data-label="<?php esc_attr_e( 'صفحات', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_html( (string) $pages ); ?></td>
					<td data-label="<?php esc_attr_e( 'آخرین lastmod', 'shojaei-seo-for-woo' ); ?>" dir="ltr"><?php echo ! empty( $row['lastmod'] ) ? esc_html( (string) $row['lastmod'] ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div class="shojaei-card" dir="rtl" id="shojaei-sitemap-health">
	<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
		<h4 style="margin:0;"><?php esc_html_e( 'دیباگ / سلامت نقشه سایت', 'shojaei-seo-for-woo' ); ?></h4>
		<button type="button" class="button button-secondary" id="shojaei-sitemap-health-run" <?php disabled( ! $can_emit ); ?>>
			<?php esc_html_e( 'اجرای مجدد تست کامل', 'shojaei-seo-for-woo' ); ?>
		</button>
	</div>
	<p id="shojaei-sitemap-health-status" class="description" aria-live="polite" style="margin-top:8px;">
		<?php
		if ( is_array( $health_report ) ) {
			$when = ! empty( $health_report['generated_at'] ) ? wp_date( 'Y-m-d H:i:s', (int) $health_report['generated_at'] ) : '—';
			echo esc_html(
				sprintf(
					/* translators: 1: datetime, 2: cache note */
					__( 'آخرین تست: %1$s — %2$s', 'shojaei-seo-for-woo' ),
					$when,
					! empty( $health_report['from_cache'] )
						? __( 'از کش سلامت (۵ دقیقه)', 'shojaei-seo-for-woo' )
						: __( 'زنده', 'shojaei-seo-for-woo' )
				)
			);
		} else {
			esc_html_e( 'تست سلامت در دسترس نیست (ماژول خاموش یا کلاس سلامت بارگذاری نشده).', 'shojaei-seo-for-woo' );
		}
		?>
	</p>

	<?php if ( is_array( $health_report ) && ! empty( $health_report['maps'] ) ) : ?>
		<?php
		$ex  = isset( $health_report['exclusions'] ) && is_array( $health_report['exclusions'] ) ? $health_report['exclusions'] : array();
		$rob = isset( $health_report['robots'] ) && is_array( $health_report['robots'] ) ? $health_report['robots'] : array();
		?>
		<table class="widefat striped shojaei-table dm-responsive-table" style="margin-top:12px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ساب‌مپ', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'HTTP', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'تعداد URL', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'XML', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'کش / TTL', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'lastmod', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'جزئیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $type_labels as $type_key => $type_label ) : ?>
					<?php
					$row = isset( $health_report['maps'][ $type_key ] ) && is_array( $health_report['maps'][ $type_key ] )
						? $health_report['maps'][ $type_key ]
						: array();
					$code_ok   = ! empty( $row['http_ok'] );
					$xml_ok    = ! empty( $row['xml_ok'] );
					$via_fb    = ! empty( $row['via_fallback'] );
					$near      = ! empty( $row['near_limit'] );
					$code      = isset( $row['http_code'] ) ? (int) $row['http_code'] : 0;
					$count     = isset( $row['item_count'] ) ? (int) $row['item_count'] : 0;
					$gen       = ! empty( $row['cache_generated'] ) ? wp_date( 'Y-m-d H:i', (int) $row['cache_generated'] ) : '—';
					$remain    = isset( $row['cache_remaining'] ) ? (int) $row['cache_remaining'] : 0;
					$remain_h  = $remain > 0 ? human_time_diff( time(), time() + $remain ) : '—';
					$lm_new    = ! empty( $row['lastmod_newest'] ) ? (string) $row['lastmod_newest'] : '—';
					$lm_old    = ! empty( $row['lastmod_oldest'] ) ? (string) $row['lastmod_oldest'] : '—';
					$http_style = $code_ok && ! $via_fb ? 'color:#008a20;font-weight:600;' : 'color:#b32d2e;font-weight:600;';
					$xml_style  = $xml_ok ? 'color:#008a20;' : 'color:#b32d2e;';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'ساب‌مپ', 'shojaei-seo-for-woo' ); ?>">
							<strong><?php echo esc_html( $type_label ); ?></strong>
							<?php if ( ! empty( $row['url'] ) ) : ?>
								<br /><code dir="ltr" style="font-size:11px;"><?php echo esc_html( (string) $row['url'] ); ?></code>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'HTTP', 'shojaei-seo-for-woo' ); ?>" style="<?php echo esc_attr( $http_style ); ?>" dir="ltr">
							<?php echo esc_html( (string) $code ); ?>
							<?php if ( $via_fb ) : ?>
								<br /><span style="color:#b32d2e;font-size:12px;"><?php esc_html_e( 'از طریق فالبک سرو شد', 'shojaei-seo-for-woo' ); ?></span>
							<?php elseif ( $code_ok ) : ?>
								<br /><span style="color:#008a20;font-size:12px;">rewrite</span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'تعداد URL', 'shojaei-seo-for-woo' ); ?>">
							<?php echo esc_html( (string) $count ); ?>
							<?php if ( $near ) : ?>
								<br /><span style="color:#996800;font-weight:600;"><?php esc_html_e( 'نزدیک سقف ۵۰٬۰۰۰', 'shojaei-seo-for-woo' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'XML', 'shojaei-seo-for-woo' ); ?>" style="<?php echo esc_attr( $xml_style ); ?>">
							<?php echo $xml_ok ? esc_html__( 'معتبر', 'shojaei-seo-for-woo' ) : esc_html__( 'نامعتبر', 'shojaei-seo-for-woo' ); ?>
							<?php if ( ! empty( $row['xml_error'] ) ) : ?>
								<br /><span style="color:#b32d2e;font-size:12px;"><?php echo esc_html( (string) $row['xml_error'] ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'کش / TTL', 'shojaei-seo-for-woo' ); ?>">
							<span dir="ltr"><?php echo esc_html( $gen ); ?></span>
							<br /><span class="description"><?php echo esc_html( sprintf( /* translators: %s: human duration */ __( 'باقی‌مانده: %s', 'shojaei-seo-for-woo' ), $remain_h ) ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'lastmod', 'shojaei-seo-for-woo' ); ?>" dir="ltr" style="font-size:12px;">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: newest, 2: oldest */
									__( 'جدید: %1$s', 'shojaei-seo-for-woo' ),
									$lm_new
								)
							);
							?>
							<br />
							<?php
							echo esc_html(
								sprintf(
									__( 'قدیم: %s', 'shojaei-seo-for-woo' ),
									$lm_old
								)
							);
							?>
						</td>
						<td data-label="<?php esc_attr_e( 'جزئیات', 'shojaei-seo-for-woo' ); ?>">
							<?php if ( 'products' === $type_key ) : ?>
								<?php
								$imgs  = (int) ( $row['images'] ?? 0 );
								$total = (int) ( $row['images_total'] ?? $count );
								$miss  = max( 0, $total - $imgs );
								echo esc_html(
									sprintf(
										/* translators: 1: with image, 2: total, 3: missing */
										__( 'تصویر: %1$d از %2$d (بدون تصویر: %3$d)', 'shojaei-seo-for-woo' ),
										$imgs,
										$total,
										$miss
									)
								);
								?>
							<?php endif; ?>
							<?php if ( ! empty( $row['warning'] ) ) : ?>
								<br /><span style="color:<?php echo $via_fb || ! $code_ok || ! $xml_ok ? '#b32d2e' : '#996800'; ?>;font-size:12px;"><?php echo esc_html( (string) $row['warning'] ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div style="margin-top:16px;display:grid;gap:12px;">
			<div>
				<strong><?php esc_html_e( 'حذف‌شده‌ها از نقشه (در برابر منتشرشده)', 'shojaei-seo-for-woo' ); ?></strong>
				<ul style="margin:6px 0 0;padding-inline-start:1.2em;">
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: published, 2: noindex, 3: 410 */
								__( 'محصولات منتشر: %1$d — حذف noindex: %2$d — حذف ۴۱۰: %3$d', 'shojaei-seo-for-woo' ),
								(int) ( $ex['products_published'] ?? 0 ),
								(int) ( $ex['products_noindex'] ?? 0 ),
								(int) ( $ex['products_410'] ?? 0 )
							)
						);
						?>
					</li>
					<li>
						<?php
						echo esc_html(
							sprintf(
								__( 'نوشته‌ها منتشر: %1$d — حذف noindex: %2$d', 'shojaei-seo-for-woo' ),
								(int) ( $ex['posts_published'] ?? 0 ),
								(int) ( $ex['posts_noindex'] ?? 0 )
							)
						);
						?>
					</li>
					<li>
						<?php
						echo esc_html(
							sprintf(
								__( 'برگه‌ها منتشر: %1$d — حذف noindex: %2$d', 'shojaei-seo-for-woo' ),
								(int) ( $ex['pages_published'] ?? 0 ),
								(int) ( $ex['pages_noindex'] ?? 0 )
							)
						);
						?>
					</li>
					<li class="description">
						<?php esc_html_e( 'تعداد داخل ساب‌مپ ≈ منتشر − noindex − ۴۱۰ (با درنظر گرفتن صفحه‌بندی). اگر اختلاف زیاد بود، چیزی به‌اشتباه حذف شده.', 'shojaei-seo-for-woo' ); ?>
					</li>
				</ul>
			</div>

			<div>
				<strong><?php esc_html_e( 'robots.txt زنده', 'shojaei-seo-for-woo' ); ?></strong>
				<?php if ( ! empty( $rob['ok'] ) && ! empty( $rob['has_sitemap'] ) ) : ?>
					<p style="margin:6px 0;color:#008a20;">
						<?php
						echo ! empty( $rob['has_damavand'] )
							? esc_html__( 'خط Sitemap برای نقشه دماوند/شجاعی پیدا شد.', 'shojaei-seo-for-woo' )
							: esc_html__( 'خط Sitemap هست، ولی به shojaei-sitemap اشاره نمی‌کند (احتمالاً افزونه دیگر).', 'shojaei-seo-for-woo' );
						?>
					</p>
					<?php foreach ( (array) ( $rob['lines'] ?? array() ) as $sm_line ) : ?>
						<code dir="ltr" style="display:block;margin:2px 0;"><?php echo esc_html( (string) $sm_line ); ?></code>
					<?php endforeach; ?>
					<?php if ( ! empty( $rob['tip'] ) ) : ?>
						<p class="description" style="margin-top:8px;"><?php echo esc_html( (string) $rob['tip'] ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p style="margin:6px 0;color:#b32d2e;">
						<?php
						echo esc_html(
							! empty( $rob['error'] )
								? (string) $rob['error']
								: __( 'خط Sitemap: در robots.txt واقعی سایت دیده نشد.', 'shojaei-seo-for-woo' )
						);
						?>
					</p>
					<?php if ( ! empty( $rob['url'] ) ) : ?>
						<code dir="ltr"><?php echo esc_html( (string) $rob['url'] ); ?></code>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div>
				<strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: total fallback hits */
							__( 'لاگ فالبک (آخرین ۱۰) — کل دفعات: %d', 'shojaei-seo-for-woo' ),
							(int) ( $health_report['fallback_hits'] ?? $fallback )
						)
					);
					?>
				</strong>
				<?php
				$flog = isset( $health_report['fallback_log'] ) && is_array( $health_report['fallback_log'] )
					? $health_report['fallback_log']
					: array();
				?>
				<?php if ( empty( $flog ) ) : ?>
					<p class="description" style="margin:6px 0 0;"><?php esc_html_e( 'هنوز فالبکی ثبت نشده — rewrite سالم به‌نظر می‌رسد.', 'shojaei-seo-for-woo' ); ?></p>
				<?php else : ?>
					<ol style="margin:6px 0 0;padding-inline-start:1.2em;font-size:13px;">
						<?php foreach ( $flog as $ev ) : ?>
							<li dir="ltr">
								<?php
								$t = ! empty( $ev['time'] ) ? wp_date( 'Y-m-d H:i:s', (int) $ev['time'] ) : '—';
								echo esc_html( $t . ' — ' . (string) ( $ev['token'] ?? '' ) . ' — ' . (string) ( $ev['uri'] ?? '' ) );
								?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<div class="shojaei-edu-tip" dir="rtl">
	<span class="dashicons dashicons-google"></span>
	<strong><?php esc_html_e( 'Search Console', 'shojaei-seo-for-woo' ); ?></strong>
	<ol style="margin:8px 0 0 1.2em;">
		<li><?php esc_html_e( 'فقط shojaei-sitemap.xml را در GSC ثبت کنید (نه تک‌تک ساب‌مپ‌ها).', 'shojaei-seo-for-woo' ); ?></li>
		<li><?php esc_html_e( 'اگر هنوز Rank Math روشن است، sitemap_index.xml او جداست؛ بعد از تایید این نقشه، sitemap رنک‌مث را خاموش کنید.', 'shojaei-seo-for-woo' ); ?></li>
		<li><?php esc_html_e( 'WP Rocket: مسیرهای *.xml را از کش صفحه Exclude کنید.', 'shojaei-seo-for-woo' ); ?></li>
	</ol>
</div>
