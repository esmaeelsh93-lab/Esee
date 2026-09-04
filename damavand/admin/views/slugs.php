<?php
/**
 * Slug redirects + health report.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shojaei_SEO_Slug' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول نامک در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$redirects = Shojaei_SEO_Slug::list_redirects( 100 );
$active_n  = Shojaei_SEO_Slug::count_active_redirects();
$health_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$health    = Shojaei_SEO_Slug::get_health_report( 400, 100, $health_page );
$stored    = Shojaei_SEO_Slug::get_stored_full_report();
$scan_busy = class_exists( 'Shojaei_SEO_Jobs' ) && Shojaei_SEO_Jobs::has_active( 'slug_health_scan' );
$section   = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'redirects'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $section, array( 'redirects', 'health' ), true ) ) {
	$section = 'redirects';
}
$redirects_url = admin_url( 'admin.php?page=shojaei-seo&tab=slugs&section=redirects' );
$health_base   = admin_url( 'admin.php?page=shojaei-seo&tab=slugs&section=health' );
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'نامک و ریدایرکت اسلاگ', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php esc_html_e( 'ریدایرکت‌های خودکار بعد از تغییر نامک را اینجا ببینید. سلامت نامک پیشنهاد می‌دهد — می‌توانید تک‌به‌تک یا حداکثر ۲۰ مورد را با Dry-Run سپس اعمال کنید.', 'shojaei-seo-for-woo' ); ?>
	</p>
	<div class="shojaei-slug-subnav" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
		<a class="button <?php echo 'redirects' === $section ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $redirects_url ); ?>">
			<?php
			printf(
				/* translators: %d: active count */
				esc_html__( 'ریدایرکت‌ها (%d فعال)', 'shojaei-seo-for-woo' ),
				(int) $active_n
			);
			?>
		</a>
		<a class="button <?php echo 'health' === $section ? 'button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=slugs&section=health' ) ); ?>">
			<?php
			printf(
				/* translators: %d: issue count */
				esc_html__( 'سلامت نامک (%d مورد)', 'shojaei-seo-for-woo' ),
				(int) $health['issues']
			);
			?>
		</a>
	</div>
</div>

<?php if ( 'redirects' === $section ) : ?>
	<div class="shojaei-card">
	<h3><?php esc_html_e( 'ریدایرکت‌های نامک', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'اگر نامک را اشتباه اعمال کردید، Undo نامک قدیم را برمی‌گرداند و ۳۰۱ را خاموش می‌کند.', 'shojaei-seo-for-woo' ); ?></p>
	<p>
		<label class="screen-reader-text" for="shojaei-slug-redirect-search"><?php esc_html_e( 'جستجو در ریدایرکت‌ها', 'shojaei-seo-for-woo' ); ?></label>
		<input
			type="search"
			id="shojaei-slug-redirect-search"
			class="shojaei-slug-product-search"
			placeholder="<?php esc_attr_e( 'جستجو در مسیر، مقصد یا نام محصول…', 'shojaei-seo-for-woo' ); ?>"
			autocomplete="off"
			style="max-width:360px;width:100%;"
		/>
	</p>
	<div id="shojaei-slug-redirect-result" class="shojaei-test-result" style="display:none;margin-bottom:12px;" aria-live="polite"></div>
	<?php if ( empty( $redirects ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-randomize"></span>
			<p><?php esc_html_e( 'هنوز ریدایرکت نامکی ثبت نشده.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" id="shojaei-slug-redirects-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'مسیر قدیم', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مقصد', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'محصول', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'فعال', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'تاریخ', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $redirects as $row ) : ?>
					<?php
					$pid   = (int) $row->product_id;
					$title = $pid ? get_the_title( $pid ) : '';
					?>
					<tr data-id="<?php echo esc_attr( (string) $row->id ); ?>">
						<td dir="ltr"><code><?php echo esc_html( (string) $row->old_path ); ?></code></td>
						<td dir="ltr"><a href="<?php echo esc_url( (string) $row->new_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_parse_url( (string) $row->new_url, PHP_URL_PATH ) ?: (string) $row->new_url ); ?></a></td>
						<td>
							<?php if ( $pid ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php echo esc_html( $title ?: ( '#' . $pid ) ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) $row->redirect_type ); ?></td>
						<td>
							<label class="shojaei-switch">
								<input type="checkbox" class="shojaei-slug-redirect-toggle" data-id="<?php echo esc_attr( (string) $row->id ); ?>" <?php checked( (int) $row->is_active, 1 ); ?> />
							</label>
						</td>
						<td><?php echo esc_html( (string) $row->created_at ); ?></td>
						<td style="white-space:nowrap;">
							<?php if ( (int) $row->is_active && $pid && ! empty( $row->old_slug ) ) : ?>
								<button type="button" class="button button-small shojaei-slug-undo" data-id="<?php echo esc_attr( (string) $row->id ); ?>">
									<?php esc_html_e( 'Undo', 'shojaei-seo-for-woo' ); ?>
								</button>
							<?php endif; ?>
							<button type="button" class="button button-small shojaei-slug-redirect-delete" data-id="<?php echo esc_attr( (string) $row->id ); ?>">
								<?php esc_html_e( 'حذف', 'shojaei-seo-for-woo' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
<?php endif; ?>

<?php if ( 'health' === $section ) : ?>
<div class="shojaei-card">
	<h3><?php esc_html_e( 'گزارش سلامت نامک', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php
		if ( ! empty( $health['source'] ) && 'full' === $health['source'] ) {
			printf(
				/* translators: 1: scanned, 2: issues, 3: finished_at */
				esc_html__( 'اسکن کامل: از %1$d محصول، %2$d مورد نیازمند توجه است.%3$s فیلتر کنید، حداکثر ۲۰ مورد انتخاب کنید، اول Dry-Run بزنید بعد اعمال.', 'shojaei-seo-for-woo' ),
				(int) $health['scanned'],
				(int) $health['issues'],
				! empty( $health['finished_at'] )
					? ' (' . esc_html( (string) $health['finished_at'] ) . ')'
					: ''
			);
		} else {
			printf(
				/* translators: 1: scanned, 2: issues */
				esc_html__( 'نمونه سریع: از میان %1$d محصول اخیر، %2$d مورد نیازمند توجه است. برای کل کاتالوگ «اسکن کامل» بزنید.', 'shojaei-seo-for-woo' ),
				(int) $health['scanned'],
				(int) $health['issues']
			);
		}
		?>
		<?php if ( ! empty( $health['skipped_410'] ) ) : ?>
			<br />
			<?php
			printf(
				/* translators: %d: skipped 410 count */
				esc_html__( 'محصولات با ریدایرکت ۴۱۰ Gone (%d مورد) از این لیست حذف شده‌اند تا اشتباهی ۳۰۱ نشوند.', 'shojaei-seo-for-woo' ),
				(int) $health['skipped_410']
			);
			?>
		<?php endif; ?>
	</p>

	<div class="shojaei-slug-full-scan-bar" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0;">
		<button type="button" class="button button-secondary" id="shojaei-slug-full-scan" <?php disabled( $scan_busy ); ?>>
			<?php echo $scan_busy ? esc_html__( 'اسکن نامک در حال اجرا…', 'shojaei-seo-for-woo' ) : esc_html__( 'اسکن کامل همه محصولات', 'shojaei-seo-for-woo' ); ?>
		</button>
		<span class="description" id="shojaei-slug-full-scan-status">
			<?php
			if ( $scan_busy && ! empty( $stored['total'] ) ) {
				printf(
					/* translators: 1: scanned, 2: total */
					esc_html__( 'پیشرفت: %1$d / %2$d', 'shojaei-seo-for-woo' ),
					(int) ( $stored['scanned'] ?? 0 ),
					(int) $stored['total']
				);
			} elseif ( ! empty( $health['source'] ) && 'full' === $health['source'] ) {
				esc_html_e( 'گزارش فعلی از اسکن کامل است.', 'shojaei-seo-for-woo' );
			} else {
				esc_html_e( 'الان نمونهٔ سریع (~۴۰۰ محصول) نمایش داده می‌شود.', 'shojaei-seo-for-woo' );
			}
			?>
		</span>
	</div>
	<div id="shojaei-slug-scan-progress" class="shojaei-test-result" style="display:none;margin:8px 0;" aria-live="polite"></div>

	<div class="shojaei-slug-score-help-box" id="shojaei-slug-score-help-box">
		<button type="button" class="button-link shojaei-slug-score-help-toggle" id="shojaei-slug-score-help-toggle" aria-expanded="false" aria-controls="shojaei-slug-score-help-body">
			<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
			<?php esc_html_e( 'امتیاز خوانایی نامک چیست؟', 'shojaei-seo-for-woo' ); ?>
		</button>
		<div id="shojaei-slug-score-help-body" class="shojaei-slug-score-help-body" hidden>
			<p><?php esc_html_e( 'عدد ۰ تا ۱۰۰ برای کیفیت URL محصول است — نه رتبه گوگل. هرچه بالاتر، نامک تمیزتر و مناسب‌تر برای سئو فنی.', 'shojaei-seo-for-woo' ); ?></p>
			<ul>
				<li><?php esc_html_e( '۷۵–۱۰۰: خوب (لاتین کوتاه و خوانا)', 'shojaei-seo-for-woo' ); ?></li>
				<li><?php esc_html_e( '۴۵–۷۴: متوسط — بهتر است بررسی شود', 'shojaei-seo-for-woo' ); ?></li>
				<li><?php esc_html_e( 'زیر ۴۵: ضعیف — معمولاً فارسی، خیلی طولانی یا کاراکتر نامعتبر', 'shojaei-seo-for-woo' ); ?></li>
			</ul>
			<p class="description"><?php esc_html_e( 'اعمال پیشنهاد = تبدیل به فینگلیش لاتین + ساخت ریدایرکت ۳۰۱ از آدرس قدیم.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	</div>

	<div class="shojaei-slug-health-toolbar">
		<div class="shojaei-slug-search-wrap">
			<label class="screen-reader-text" for="shojaei-slug-product-search"><?php esc_html_e( 'جستجوی محصول', 'shojaei-seo-for-woo' ); ?></label>
			<input
				type="search"
				id="shojaei-slug-product-search"
				class="shojaei-slug-product-search"
				placeholder="<?php esc_attr_e( 'جستجوی محصول (نام، نامک یا ID)…', 'shojaei-seo-for-woo' ); ?>"
				autocomplete="off"
			/>
			<button type="button" class="button" id="shojaei-slug-product-search-btn"><?php esc_html_e( 'جستجو', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button-link" id="shojaei-slug-product-search-clear" hidden><?php esc_html_e( 'پاک کردن', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<div class="shojaei-slug-filters" role="group" aria-label="<?php esc_attr_e( 'فیلتر', 'shojaei-seo-for-woo' ); ?>">
			<button type="button" class="button button-small shojaei-slug-filter is-active" data-filter="all"><?php esc_html_e( 'همه', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-small shojaei-slug-filter" data-filter="persian"><?php esc_html_e( 'فارسی', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-small shojaei-slug-filter" data-filter="long"><?php esc_html_e( 'خیلی طولانی', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-small shojaei-slug-filter" data-filter="low"><?php esc_html_e( 'امتیاز < ۵۰', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<div class="shojaei-slug-batch-actions">
			<span class="shojaei-slug-selected-count" id="shojaei-slug-selected-count">0 / 20</span>
			<button type="button" class="button" id="shojaei-slug-batch-dry"><?php esc_html_e( 'Dry-Run انتخاب‌ها', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-primary" id="shojaei-slug-batch-apply"><?php esc_html_e( 'اعمال انتخاب‌ها (حداکثر ۲۰)', 'shojaei-seo-for-woo' ); ?></button>
		</div>
	</div>
	<p class="description shojaei-slug-filter-count" id="shojaei-slug-filter-count" aria-live="polite"></p>
	<div id="shojaei-slug-search-status" class="description" style="margin:6px 0;" aria-live="polite"></div>

	<div id="shojaei-slug-apply-result" class="shojaei-test-result" style="display:none;margin:12px 0;" aria-live="polite"></div>

	<?php if ( empty( $health['rows'] ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-yes-alt"></span>
			<p><?php esc_html_e( 'در نمونه اسکن‌شده مشکل جدی دیده نشد.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table shojaei-slug-health-table" id="shojaei-slug-health-table">
			<thead>
				<tr>
					<th class="check-column">
						<input type="checkbox" id="shojaei-slug-check-all" title="<?php esc_attr_e( 'انتخاب همه ردیف‌های نمایش‌داده‌شده', 'shojaei-seo-for-woo' ); ?>" />
					</th>
					<th><?php esc_html_e( 'محصول', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نامک فعلی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'پیشنهاد فینگلیش', 'shojaei-seo-for-woo' ); ?></th>
					<th class="shojaei-slug-col-score-head">
						<span><?php esc_html_e( 'امتیاز', 'shojaei-seo-for-woo' ); ?></span>
						<button type="button" class="button-link shojaei-slug-score-help-toggle" aria-label="<?php esc_attr_e( 'توضیح امتیاز', 'shojaei-seo-for-woo' ); ?>" title="<?php esc_attr_e( 'توضیح امتیاز خوانایی', 'shojaei-seo-for-woo' ); ?>">
							<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
						</button>
					</th>
					<th><?php esc_html_e( 'دلیل', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $health['rows'] as $item ) : ?>
					<?php
					$tone         = $item['score'] >= 75 ? 'safe' : ( $item['score'] >= 45 ? 'warning' : 'error' );
					$slug_raw     = (string) $item['slug'];
					$slug_display = rawurldecode( $slug_raw );
					if ( ! is_string( $slug_display ) || '' === $slug_display ) {
						$slug_display = $slug_raw;
					}
					$reasons_attr = implode( ',', $item['reasons'] );
					$has_persian  = ! empty( $item['has_persian'] ) ? '1' : ( in_array( 'persian', $item['reasons'], true ) ? '1' : '0' );
					$has_long     = ! empty( $item['has_long'] ) ? '1' : ( in_array( 'long', $item['reasons'], true ) ? '1' : '0' );
					?>
					<tr
						class="shojaei-slug-health-row"
						data-product-id="<?php echo esc_attr( (string) $item['product_id'] ); ?>"
						data-old-slug="<?php echo esc_attr( $slug_raw ); ?>"
						data-new-slug="<?php echo esc_attr( (string) $item['suggest'] ); ?>"
						data-score="<?php echo esc_attr( (string) $item['score'] ); ?>"
						data-reasons="<?php echo esc_attr( $reasons_attr ); ?>"
						data-has-persian="<?php echo esc_attr( $has_persian ); ?>"
						data-has-long="<?php echo esc_attr( $has_long ); ?>"
					>
						<th class="check-column">
							<input type="checkbox" class="shojaei-slug-row-check" value="<?php echo esc_attr( (string) $item['product_id'] ); ?>" />
						</th>
						<td class="shojaei-slug-col-title">
							<a href="<?php echo esc_url( (string) $item['edit_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
						</td>
						<td class="shojaei-slug-col-slug" dir="auto" title="<?php echo esc_attr( $slug_raw ); ?>">
							<code class="shojaei-slug-code"><?php echo esc_html( $slug_display ); ?></code>
						</td>
						<td class="shojaei-slug-col-suggest" dir="ltr">
							<code class="shojaei-slug-code"><?php echo esc_html( $item['suggest'] ); ?></code>
						</td>
						<td>
							<span class="shojaei-slug-score shojaei-tone-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( (string) $item['score'] ); ?></span>
						</td>
						<td class="shojaei-slug-col-reason">
							<?php
							$labels = array();
							foreach ( $item['reasons'] as $code ) {
								$labels[] = Shojaei_SEO_Slug::reason_label( $code );
							}
							echo esc_html( implode( ' · ', $labels ) );
							?>
						</td>
						<td class="shojaei-slug-col-actions">
							<button type="button" class="button button-small button-primary shojaei-slug-apply" data-id="<?php echo esc_attr( (string) $item['product_id'] ); ?>">
								<?php esc_html_e( 'اعمال + ۳۰۱', 'shojaei-seo-for-woo' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = max( 1, (int) ( $health['pages'] ?? 1 ) );
		$page  = max( 1, (int) ( $health['page'] ?? 1 ) );
		if ( $pages > 1 ) :
			?>
			<div class="tablenav bottom" style="margin-top:12px;">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: 1: page, 2: pages, 3: issues */
							esc_html__( 'صفحه %1$d از %2$d — %3$d مورد', 'shojaei-seo-for-woo' ),
							$page,
							$pages,
							(int) $health['issues']
						);
						?>
					</span>
					<span class="pagination-links">
						<?php if ( $page > 1 ) : ?>
							<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $health_base ) ); ?>">&laquo;</a>
						<?php endif; ?>
						<?php if ( $page < $pages ) : ?>
							<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $health_base ) ); ?>">&raquo;</a>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
		<p class="description">
			<a href="<?php echo esc_url( $redirects_url ); ?>"><?php esc_html_e( 'مشاهده ریدایرکت‌های ۳۰۱ و Undo ←', 'shojaei-seo-for-woo' ); ?></a>
			&nbsp;·&nbsp;
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=settings' ) ); ?>"><?php esc_html_e( 'ویرایش دیکشنری فینگلیش در تنظیمات', 'shojaei-seo-for-woo' ); ?></a>
		</p>
	<?php endif; ?>
</div>
<?php endif; ?>
