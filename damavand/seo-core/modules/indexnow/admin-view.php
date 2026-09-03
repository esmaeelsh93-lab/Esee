<?php
/**
 * UI نمایه‌سازی فوری — پیشنهاد با تأیید + ارسال دستی.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'suggest'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $view, array( 'suggest', 'submit', 'settings', 'history' ), true ) ) {
	$view = 'suggest';
}

$base = admin_url( 'admin.php?page=shojaei-seo&tab=seo-core&module=indexnow' );
$key  = class_exists( 'SEO_Core_Installer' )
	? SEO_Core_Installer::get_indexnow_key()
	: (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_indexnow_key', '' );
$on   = 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_indexnow_enabled', 'yes' );
$key_url = $key ? home_url( '/' . $key . '.txt' ) : '';
$hist = class_exists( 'Shojaei_SEO_IndexNow' ) ? Shojaei_SEO_IndexNow::get_history() : array();
$pending = class_exists( 'Shojaei_SEO_IndexNow' ) ? Shojaei_SEO_IndexNow::get_pending() : array();
$mod  = $modules['indexnow'] ?? null;
$passive = ( $mod instanceof SEO_Core_Module ) ? $mod->is_passive() : false;
?>

<div class="shojaei-card">
	<h3 style="margin:0 0 6px;"><?php esc_html_e( 'نمایه‌سازی فوری (IndexNow)', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc" style="margin:0;">
		<?php esc_html_e( 'تغییر نامک ابتدا پیشنهاد می‌شود؛ بعد از تأیید شما، آدرس قدیم و جدید جداگانه به IndexNow می‌روند.', 'shojaei-seo-for-woo' ); ?>
		<a href="https://www.indexnow.org/index" target="_blank" rel="noopener"><?php esc_html_e( 'بیشتر بدانید', 'shojaei-seo-for-woo' ); ?></a>
	</p>
</div>

<?php if ( $passive ) : ?>
	<div class="notice notice-info inline" style="margin:0 0 12px;">
		<p><?php esc_html_e( 'حالت کمکی: ارسال خودکار هنگام ذخیره محصول خاموش است (رقیب SEO تشخیص داده شد). پیشنهادها و ارسال دستی از این صفحه همچنان کار می‌کنند.', 'shojaei-seo-for-woo' ); ?></p>
	</div>
<?php endif; ?>

<nav class="shojaei-subnav" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;" aria-label="<?php esc_attr_e( 'نمایه‌سازی فوری', 'shojaei-seo-for-woo' ); ?>">
	<a class="button <?php echo 'suggest' === $view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base . '&view=suggest' ); ?>">
		<?php
		printf(
			/* translators: %d: pending count */
			esc_html__( 'پیشنهادها (%d)', 'shojaei-seo-for-woo' ),
			count( $pending )
		);
		?>
	</a>
	<a class="button <?php echo 'submit' === $view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base . '&view=submit' ); ?>"><?php esc_html_e( 'ارسال دستی', 'shojaei-seo-for-woo' ); ?></a>
	<a class="button <?php echo 'settings' === $view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base . '&view=settings' ); ?>"><?php esc_html_e( 'تنظیمات', 'shojaei-seo-for-woo' ); ?></a>
	<a class="button <?php echo 'history' === $view ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base . '&view=history' ); ?>"><?php esc_html_e( 'تاریخچه', 'shojaei-seo-for-woo' ); ?></a>
</nav>

<?php if ( 'suggest' === $view ) : ?>
	<div class="shojaei-card">
		<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
			<div>
				<h4 style="margin:0 0 6px;"><?php esc_html_e( 'صف تأیید IndexNow', 'shojaei-seo-for-woo' ); ?></h4>
				<p class="description" style="margin:0;"><?php esc_html_e( 'هر ردیف یک تغییر آدرس است. ستون قدیم و جدید جدا هستند؛ با تأیید هر دو URL ارسال می‌شوند.', 'shojaei-seo-for-woo' ); ?></p>
			</div>
			<button type="button" class="button" id="shojaei-indexnow-scan-suggest"><?php esc_html_e( 'پیشنهاد از ریدایرکت‌های نامک', 'shojaei-seo-for-woo' ); ?></button>
		</div>

		<?php if ( empty( $pending ) ) : ?>
			<p class="description" style="margin-top:14px;"><?php esc_html_e( 'صف خالی است. با اعمال نامک فینگلیش یا دکمهٔ پیشنهاد از ریدایرکت‌ها پر می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
		<?php else : ?>
			<table class="widefat striped shojaei-table dm-responsive-table" style="margin-top:14px;" id="shojaei-indexnow-pending-table">
				<thead>
					<tr>
						<th style="width:36px;"><input type="checkbox" id="shojaei-indexnow-check-all" /></th>
						<th><?php esc_html_e( 'عنوان', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'آدرس قدیم', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'آدرس جدید', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'دلیل', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'زمان', 'shojaei-seo-for-woo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pending as $row ) : ?>
						<tr>
							<td><input type="checkbox" class="shojaei-indexnow-pick" value="<?php echo esc_attr( (string) ( $row['id'] ?? '' ) ); ?>" checked /></td>
							<td>
								<?php
								$pid = (int) ( $row['post_id'] ?? 0 );
								$ttl = (string) ( $row['title'] ?? '' );
								if ( $pid ) {
									echo '<a href="' . esc_url( get_edit_post_link( $pid, 'raw' ) ) . '">' . esc_html( $ttl ?: ( '#' . $pid ) ) . '</a>';
								} else {
									echo esc_html( $ttl ?: '—' );
								}
								?>
							</td>
							<td dir="ltr" style="font-size:12px;word-break:break-all;">
								<?php
								$old = (string) ( $row['old_url'] ?? '' );
								echo $old ? esc_html( $old ) : '—';
								?>
							</td>
							<td dir="ltr" style="font-size:12px;word-break:break-all;">
								<?php
								$new = (string) ( $row['new_url'] ?? '' );
								if ( $new ) {
									echo '<a href="' . esc_url( $new ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $new ) . '</a>';
								} else {
									echo '—';
								}
								?>
							</td>
							<td class="description"><?php echo esc_html( (string) ( $row['reason'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['at'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;">
				<button type="button" class="button button-primary" id="shojaei-indexnow-confirm"><?php esc_html_e( 'تأیید و ارسال انتخاب‌شده‌ها', 'shojaei-seo-for-woo' ); ?></button>
				<button type="button" class="button" id="shojaei-indexnow-dismiss"><?php esc_html_e( 'حذف از صف', 'shojaei-seo-for-woo' ); ?></button>
			</p>
		<?php endif; ?>
		<p id="shojaei-indexnow-suggest-status" class="description" aria-live="polite"></p>
	</div>

<?php elseif ( 'submit' === $view ) : ?>
	<div class="shojaei-card">
		<label for="shojaei-indexnow-urls">
			<strong><?php esc_html_e( 'نشانی‌های اینترنتی را برای ارسال به IndexNow API درج کنید (یکی در هر خط، تا ۱۰۰۰۰):', 'shojaei-seo-for-woo' ); ?></strong>
		</label>
		<textarea id="shojaei-indexnow-urls" class="large-text code" rows="12" dir="ltr" placeholder="https://example.com/product/sample&#10;https://example.com/blog/post" style="margin-top:8px;width:100%;"><?php echo esc_textarea( home_url( '/' ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'فقط URLهای همین دامنه پذیرفته می‌شوند. برای تغییر نامک از تب پیشنهادها استفاده کنید تا قدیم/جدید جدا ثبت شود.', 'shojaei-seo-for-woo' ); ?></p>
		<p style="margin-top:12px;">
			<button type="button" class="button button-primary button-hero" id="shojaei-indexnow-submit"><?php esc_html_e( 'ارسال پیوندها', 'shojaei-seo-for-woo' ); ?></button>
		</p>
		<p id="shojaei-indexnow-status" class="description" aria-live="polite"></p>
	</div>

<?php elseif ( 'settings' === $view ) : ?>
	<div class="shojaei-card">
		<label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
			<input type="checkbox" id="shojaei-indexnow-enabled" value="1" <?php checked( $on ); ?> />
			<?php esc_html_e( 'فعال‌سازی IndexNow خودکار (محصولات) + صف پیشنهاد برای تغییر نامک', 'shojaei-seo-for-woo' ); ?>
		</label>
		<label for="shojaei-indexnow-key"><strong><?php esc_html_e( 'کلید API — IndexNow', 'shojaei-seo-for-woo' ); ?></strong></label>
		<input type="text" id="shojaei-indexnow-key" class="regular-text" dir="ltr" value="<?php echo esc_attr( $key ); ?>" style="margin-top:6px;display:block;max-width:420px;" />
		<?php if ( $key_url ) : ?>
			<p class="description" style="margin-top:8px;">
				<?php esc_html_e( 'فایل تأیید کلید:', 'shojaei-seo-for-woo' ); ?>
				<code dir="ltr"><?php echo esc_html( $key_url ); ?></code>
			</p>
		<?php endif; ?>
		<p style="margin-top:14px;">
			<button type="button" class="button button-primary" id="shojaei-indexnow-save"><?php esc_html_e( 'ذخیره تنظیمات', 'shojaei-seo-for-woo' ); ?></button>
		</p>
		<p id="shojaei-indexnow-settings-status" class="description" aria-live="polite"></p>
	</div>

<?php else : ?>
	<div class="shojaei-card">
		<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
			<h4 style="margin:0;"><?php esc_html_e( 'تاریخچه ارسال‌ها', 'shojaei-seo-for-woo' ); ?></h4>
			<button type="button" class="button" id="shojaei-indexnow-clear-hist"><?php esc_html_e( 'پاک کردن تاریخچه', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<?php if ( empty( $hist ) ) : ?>
			<p class="description" style="margin-top:12px;"><?php esc_html_e( 'هنوز ارسالی ثبت نشده.', 'shojaei-seo-for-woo' ); ?></p>
		<?php else : ?>
			<table class="widefat striped shojaei-table dm-responsive-table" style="margin-top:12px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'زمان', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'آدرس قدیم', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'آدرس جدید', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'تعداد', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'منبع', 'shojaei-seo-for-woo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $hist as $row ) : ?>
						<?php
						$old_h = (string) ( $row['old_url'] ?? '' );
						$new_h = (string) ( $row['new_url'] ?? '' );
						if ( '' === $old_h && '' === $new_h && ! empty( $row['pairs'][0] ) && is_array( $row['pairs'][0] ) ) {
							$old_h = (string) ( $row['pairs'][0]['old_url'] ?? '' );
							$new_h = (string) ( $row['pairs'][0]['new_url'] ?? '' );
						}
						if ( '' === $new_h && ! empty( $row['sample'][0] ) ) {
							$new_h = (string) $row['sample'][0];
						}
						$src = (string) ( $row['source'] ?? 'manual' );
						$src_label = 'confirm' === $src
							? __( 'تأیید پیشنهاد', 'shojaei-seo-for-woo' )
							: ( 'manual' === $src ? __( 'دستی', 'shojaei-seo-for-woo' ) : $src );
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $row['at'] ?? '' ) ); ?></td>
							<td dir="ltr" style="font-size:12px;word-break:break-all;"><?php echo $old_h ? esc_html( $old_h ) : '—'; ?></td>
							<td dir="ltr" style="font-size:12px;word-break:break-all;"><?php echo $new_h ? esc_html( $new_h ) : '—'; ?></td>
							<td><?php echo esc_html( (string) (int) ( $row['count'] ?? 0 ) ); ?></td>
							<td>
								<?php if ( ! empty( $row['ok'] ) ) : ?>
									<span class="shojaei-slug-score shojaei-tone-safe"><?php esc_html_e( 'موفق', 'shojaei-seo-for-woo' ); ?></span>
								<?php else : ?>
									<span class="shojaei-slug-score shojaei-tone-error"><?php esc_html_e( 'ناموفق', 'shojaei-seo-for-woo' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="description"><?php echo esc_html( $src_label ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<p id="shojaei-indexnow-hist-status" class="description" aria-live="polite"></p>
	</div>
<?php endif; ?>
