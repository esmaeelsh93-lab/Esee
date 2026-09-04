<?php
/**
 * Internal link builder view.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$links = $wpdb->get_results(
	"SELECT * FROM " . Shojaei_SEO_Helpers::links_table() . " ORDER BY id DESC LIMIT 100"
);

$preview_posts = get_posts( array(
	'post_type'      => array( 'post', 'product' ),
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'لینک‌سازی بازیابی / داخلی', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'بخشی از لایه عملیاتی: هدایت اعتبار و کشف داخلی به صفحات زنده — نه ابزار پراکنده «چند Rule ساده». قبل از اعمال، نتیجه را ببینید. لینک در هدینگ، دکمه، منو و نواحی حساس درج نمی‌شود.', 'shojaei-seo-for-woo' ); ?></p>

	<div class="shojaei-preview-form">
		<select id="shojaei-preview-post">
			<option value=""><?php esc_html_e( '— انتخاب نوشته/محصول —', 'shojaei-seo-for-woo' ); ?></option>
			<?php foreach ( $preview_posts as $preview_post ) : ?>
				<option value="<?php echo esc_attr( $preview_post->ID ); ?>">
					<?php echo esc_html( $preview_post->post_title ); ?> (<?php echo esc_html( $preview_post->post_type ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button button-primary" id="shojaei-run-preview"><?php esc_html_e( 'پیش‌نمایش', 'shojaei-seo-for-woo' ); ?></button>
	</div>

	<textarea id="shojaei-preview-content" rows="6" placeholder="<?php esc_attr_e( 'یا متن HTML را اینجا وارد کنید...', 'shojaei-seo-for-woo' ); ?>"></textarea>

	<div id="shojaei-preview-result" class="shojaei-preview-result" style="display:none;">
		<div class="shojaei-preview-meta">
			<div class="shojaei-preview-stat">
				<strong><?php esc_html_e( 'لینک تازه‌اضافه‌شده توسط موتور:', 'shojaei-seo-for-woo' ); ?></strong>
				<span id="shojaei-preview-count">0</span>
				<span class="shojaei-preview-cap" id="shojaei-preview-cap"></span>
			</div>
			<div class="shojaei-preview-stat">
				<strong><?php esc_html_e( 'لینک از قبل در محتوا:', 'shojaei-seo-for-woo' ); ?></strong>
				<span id="shojaei-preview-existing">0</span>
			</div>
		</div>
		<p id="shojaei-preview-explain" class="shojaei-desc" style="display:none;"></p>
		<ul id="shojaei-preview-details" class="shojaei-preview-details"></ul>
		<ul id="shojaei-preview-existing-list" class="shojaei-preview-details" style="opacity:.9;"></ul>
		<ul id="shojaei-preview-skipped" class="shojaei-preview-details" style="opacity:.85;"></ul>
		<p class="description"><?php esc_html_e( 'لینک‌های تازه‌درج‌شده با پس‌زمینه سبز مشخص می‌شوند؛ لینک‌های آبی معمولی از قبل در محتوا بوده‌اند.', 'shojaei-seo-for-woo' ); ?></p>
		<div id="shojaei-preview-output" class="shojaei-preview-output"></div>
	</div>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'افزودن کلمه کلیدی جدید', 'shojaei-seo-for-woo' ); ?></h3>
	<form id="shojaei-add-link-form" class="shojaei-inline-form">
		<input type="text" name="keyword" placeholder="<?php esc_attr_e( 'کلمه کلیدی (مثال: ساعت هوشمند شیائومی)', 'shojaei-seo-for-woo' ); ?>" required />
		<input type="url" name="target_url" placeholder="<?php esc_attr_e( 'آدرس مقصد', 'shojaei-seo-for-woo' ); ?>" required />
		<button type="submit" class="button button-primary"><?php esc_html_e( 'افزودن', 'shojaei-seo-for-woo' ); ?></button>
	</form>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'قوانین لینک‌سازی (محافظه‌کار / Rule-based)', 'shojaei-seo-for-woo' ); ?></h3>
	<div class="shojaei-rules-grid">
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-shield"></span>
			<p><?php printf( esc_html__( 'سقف سخت: حداکثر %d لینک در هر صفحه', 'shojaei-seo-for-woo' ), (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_max_per_page', 5 ) ); ?></p>
		</div>
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-chart-area"></span>
			<p><?php printf( esc_html__( 'تراکم: حداکثر %d لینک در هر ۱۰۰۰ کلمه', 'shojaei-seo-for-woo' ), (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_max_per_1000', 3 ) ); ?></p>
		</div>
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-leftright"></span>
			<p><?php printf( esc_html__( 'حداقل %d کلمه فاصله بین لینک‌ها', 'shojaei-seo-for-woo' ), (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_min_word_gap', 200 ) ); ?></p>
		</div>
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-dismiss"></span>
			<p><?php esc_html_e( 'بدون لینک تکراری به یک URL یا یک anchor در همان صفحه', 'shojaei-seo-for-woo' ); ?></p>
		</div>
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-hidden"></span>
			<p><?php esc_html_e( 'بدون لینک به صفحات noindex یا ریدایرکت‌شده', 'shojaei-seo-for-woo' ); ?></p>
		</div>
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-sort"></span>
			<p><?php esc_html_e( 'اولویت: دسته، برند، ویژگی، upsell/جایگزین واقعی', 'shojaei-seo-for-woo' ); ?></p>
		</div>
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-yes-alt"></span>
			<p><?php esc_html_e( 'Whitelist / Blacklist قابل کنترل + حالت انحصاری', 'shojaei-seo-for-woo' ); ?></p>
		</div>
		<div class="shojaei-rule-item">
			<span class="dashicons dashicons-heading"></span>
			<p><?php esc_html_e( 'بدون لینک در H1–H6، دکمه، منو و style', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	</div>
	<p class="shojaei-desc" style="margin-top:1rem;">
		<?php esc_html_e( 'تنظیمات سقف، لیست‌ها و فاصله در تب «تنظیمات» → لینک‌ساز.', 'shojaei-seo-for-woo' ); ?>
	</p>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'کلمات کلیدی تعریف‌شده', 'shojaei-seo-for-woo' ); ?></h3>
	<?php if ( empty( $links ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-admin-links"></span>
			<p><?php esc_html_e( 'هنوز کلمه کلیدی تعریف نشده است.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="shojaei-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'کلمه کلیدی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'آدرس مقصد', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $links as $link ) : ?>
					<tr data-link-id="<?php echo esc_attr( $link->id ); ?>">
						<td><strong><?php echo esc_html( $link->keyword ); ?></strong></td>
						<td><a href="<?php echo esc_url( $link->target_url ); ?>" target="_blank"><?php echo esc_html( $link->target_url ); ?></a></td>
						<td>
							<label class="shojaei-toggle">
								<input type="checkbox" class="shojaei-link-toggle" data-id="<?php echo esc_attr( $link->id ); ?>" <?php checked( $link->is_active, 1 ); ?> />
								<span class="shojaei-toggle-slider"></span>
							</label>
						</td>
						<td>
							<button class="button shojaei-link-delete" data-id="<?php echo esc_attr( $link->id ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
