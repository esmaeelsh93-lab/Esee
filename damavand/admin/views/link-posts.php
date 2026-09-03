<?php
/**
 * Post link status — بررسی نوشته‌ها.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول نابغه لینک در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$orphan = ! empty( $_GET['orphan'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$result = Shojaei_SEO_Link_Genius::query_post_stats(
	array(
		'q'           => $q,
		'orphan_only' => $orphan,
		'page'        => $page,
	)
);
$base = admin_url( 'admin.php?page=shojaei-seo&tab=link-posts' );
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'بررسی نوشته‌ها', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'وضعیت لینک‌سازی هر نوشته: ورودی، خروجی، یتیم بودن و امتیاز سادهٔ لینک. برای دیتای دقیق ابتدا در «بررسی لینک‌ها» اسکن کنید.', 'shojaei-seo-for-woo' ); ?></p>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
		<input type="hidden" name="page" value="shojaei-seo" />
		<input type="hidden" name="tab" value="link-posts" />
		<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'جستجوی عنوان…', 'shojaei-seo-for-woo' ); ?>" style="min-width:220px;" />
		<label>
			<input type="checkbox" name="orphan" value="1" <?php checked( $orphan ); ?> />
			<?php esc_html_e( 'فقط نوشته‌های یتیم (بدون لینک ورودی)', 'shojaei-seo-for-woo' ); ?>
		</label>
		<button type="submit" class="button"><?php esc_html_e( 'اعمال', 'shojaei-seo-for-woo' ); ?></button>
	</form>
</div>

<div class="shojaei-card">
	<?php if ( empty( $result['rows'] ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-media-text"></span>
			<p><?php esc_html_e( 'نتیجه‌ای نیست.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'عنوان', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'داخلی خروجی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'خارجی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'ورودی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'امتیاز', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'اقدام', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['rows'] as $row ) : ?>
					<?php
					$tone = $row['seo_score'] >= 70 ? 'safe' : ( $row['seo_score'] >= 40 ? 'warning' : 'error' );
					?>
					<tr>
						<td><?php echo esc_html( $row['title'] ); ?></td>
						<td><?php echo esc_html( $row['post_type'] ); ?></td>
						<td><?php echo esc_html( (string) $row['internal_out'] ); ?></td>
						<td><?php echo esc_html( (string) $row['external_out'] ); ?></td>
						<td><?php echo esc_html( (string) $row['incoming'] ); ?></td>
						<td>
							<?php if ( ! empty( $row['is_orphan'] ) ) : ?>
								<span class="shojaei-slug-score shojaei-tone-error"><?php esc_html_e( 'یتیم', 'shojaei-seo-for-woo' ); ?></span>
							<?php else : ?>
								<span class="shojaei-slug-score shojaei-tone-safe"><?php esc_html_e( 'متصل', 'shojaei-seo-for-woo' ); ?></span>
							<?php endif; ?>
						</td>
						<td><span class="shojaei-slug-score shojaei-tone-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( (string) $row['seo_score'] ); ?></span></td>
						<td style="white-space:nowrap;">
							<?php if ( ! empty( $row['is_orphan'] ) ) : ?>
								<button type="button" class="button button-small button-primary shojaei-orphan-fix" data-post-id="<?php echo esc_attr( (string) $row['post_id'] ); ?>"><?php esc_html_e( 'پیشنهاد لینک ورودی', 'shojaei-seo-for-woo' ); ?></button>
							<?php endif; ?>
							<a class="button button-small" href="<?php echo esc_url( (string) $row['edit_url'] ); ?>"><?php esc_html_e( 'ویرایش', 'shojaei-seo-for-woo' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div id="shojaei-orphan-modal" class="shojaei-orphan-modal" hidden>
	<div class="shojaei-orphan-modal__backdrop" data-orphan-close="1"></div>
	<div class="shojaei-orphan-modal__panel" role="dialog" aria-modal="true" aria-labelledby="shojaei-orphan-modal-title-lp">
		<div class="shojaei-orphan-modal__head">
			<h3 id="shojaei-orphan-modal-title-lp"><?php esc_html_e( 'بهبود صفحه یتیم', 'shojaei-seo-for-woo' ); ?></h3>
			<button type="button" class="button-link shojaei-orphan-modal__x" data-orphan-close="1" aria-label="<?php esc_attr_e( 'بستن', 'shojaei-seo-for-woo' ); ?>">&times;</button>
		</div>
		<p class="description" id="shojaei-orphan-modal-target"></p>
		<label for="shojaei-orphan-keywords"><strong><?php esc_html_e( 'کلمات کلیدی لنگر', 'shojaei-seo-for-woo' ); ?></strong></label>
		<textarea id="shojaei-orphan-keywords" rows="3" class="large-text" style="width:100%;margin:6px 0 12px;"></textarea>
		<p class="description"><?php esc_html_e( 'پس از تأیید: نقشه کلمات + یک لینک ورودی در مبدأهای انتخاب‌شده (با تأیید شما).', 'shojaei-seo-for-woo' ); ?></p>
		<div id="shojaei-orphan-suggestions"></div>
		<p id="shojaei-orphan-modal-status" class="description" aria-live="polite"></p>
		<div class="shojaei-orphan-modal__actions">
			<button type="button" class="button" data-orphan-close="1"><?php esc_html_e( 'انصراف', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-primary" id="shojaei-orphan-apply"><?php esc_html_e( 'تأیید و ساخت نقشه', 'shojaei-seo-for-woo' ); ?></button>
		</div>
	</div>
</div>
