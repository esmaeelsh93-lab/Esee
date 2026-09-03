<?php
/**
 * Status-first operations dashboard — what to do now.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$board = class_exists( 'Shojaei_SEO_Status' )
	? Shojaei_SEO_Status::snapshot()
	: array();

$overall   = $board['overall'] ?? array( 'tone' => 'safe', 'label' => '' );
$cards     = $board['cards'] ?? array();
$next      = $board['next_steps'] ?? array();
$errors    = $board['errors']['items'] ?? array();
$undos     = $board['undo_batches']['items'] ?? array();
$dry_run   = ! empty( $board['dry_run'] );
$tone      = (string) ( $overall['tone'] ?? 'safe' );

// Keep lightweight analytics below the fold.
global $wpdb;
if ( class_exists( 'Shojaei_SEO_Analytics' ) ) {
	$analytics = new Shojaei_SEO_Analytics();
	$analytics->snapshot_today();
}
$weekly = class_exists( 'Shojaei_SEO_Analytics' ) ? Shojaei_SEO_Analytics::get_or_build_weekly_summary() : array();
?>

<div class="shojaei-status-banner shojaei-tone-<?php echo esc_attr( $tone ); ?>">
	<div class="shojaei-status-banner-main">
		<span class="shojaei-status-chip shojaei-tone-<?php echo esc_attr( $tone ); ?>">
			<?php echo esc_html( Shojaei_SEO_Status::tone_label( $tone ) ); ?>
		</span>
		<h2><?php echo esc_html( $overall['label'] ?: __( 'مرکز وضعیت عملیات', 'shojaei-seo-for-woo' ) ); ?></h2>
		<p><?php esc_html_e( 'به‌جای فرم‌های شلوغ: ببینید الآن چه وضعیتی هست و چه کاری باید بکنید.', 'shojaei-seo-for-woo' ); ?></p>
	</div>
	<div class="shojaei-status-banner-meta">
		<span class="shojaei-ops-pill">
			<span class="dashicons <?php echo $dry_run ? 'dashicons-visibility' : 'dashicons-warning'; ?>"></span>
			<?php echo $dry_run ? esc_html__( 'Dry-Run فعال', 'shojaei-seo-for-woo' ) : esc_html__( 'اتوماسیون واقعی', 'shojaei-seo-for-woo' ); ?>
		</span>
		<span class="shojaei-ops-pill">
			<span class="dashicons dashicons-controls-repeat"></span>
			<?php echo ! empty( $board['event_driven'] ) ? esc_html__( 'Event-driven', 'shojaei-seo-for-woo' ) : esc_html__( 'اسکن‌محور', 'shojaei-seo-for-woo' ); ?>
		</span>
		<span class="shojaei-ops-pill">
			<span class="dashicons dashicons-database-view"></span>
			<?php
			printf(
				/* translators: %d: active jobs */
				esc_html__( 'Job فعال: %d', 'shojaei-seo-for-woo' ),
				(int) ( $board['jobs_active'] ?? 0 )
			);
			?>
		</span>
	</div>
</div>

<?php if ( ! empty( $next ) ) : ?>
	<div class="shojaei-next-steps">
		<h3><?php esc_html_e( 'الان چه کار کنید', 'shojaei-seo-for-woo' ); ?></h3>
		<ol>
			<?php foreach ( $next as $step ) : ?>
				<li>
					<a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['text'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
<?php endif; ?>

<div class="shojaei-action-cards">
	<?php foreach ( $cards as $card ) : ?>
		<article class="shojaei-action-card shojaei-tone-<?php echo esc_attr( (string) ( $card['tone'] ?? 'safe' ) ); ?>">
			<header>
				<span class="dashicons <?php echo esc_attr( (string) ( $card['icon'] ?? 'dashicons-admin-generic' ) ); ?>"></span>
				<span class="shojaei-status-chip shojaei-tone-<?php echo esc_attr( (string) ( $card['tone'] ?? 'safe' ) ); ?>">
					<?php echo esc_html( Shojaei_SEO_Status::tone_label( (string) ( $card['tone'] ?? 'safe' ) ) ); ?>
				</span>
			</header>
			<div class="shojaei-action-count"><?php echo esc_html( (string) ( $card['count'] ?? '0' ) ); ?></div>
			<h3><?php echo esc_html( (string) ( $card['title'] ?? '' ) ); ?></h3>
			<p><?php echo esc_html( (string) ( $card['description'] ?? '' ) ); ?></p>
			<small><?php echo esc_html( (string) ( $card['meta'] ?? '' ) ); ?></small>
			<a class="button button-primary" href="<?php echo esc_url( (string) ( $card['cta_url'] ?? '#' ) ); ?>">
				<?php echo esc_html( (string) ( $card['cta_label'] ?? __( 'مشاهده', 'shojaei-seo-for-woo' ) ) ); ?>
			</a>
		</article>
	<?php endforeach; ?>
</div>

<div class="shojaei-status-split">
	<div class="shojaei-card">
		<h3><?php esc_html_e( 'آخرین خطاهای پردازش', 'shojaei-seo-for-woo' ); ?></h3>
		<?php if ( empty( $errors ) ) : ?>
			<p class="shojaei-desc"><?php esc_html_e( 'خطای Job در هفته اخیر ثبت نشده است.', 'shojaei-seo-for-woo' ); ?></p>
		<?php else : ?>
			<ul class="shojaei-status-list">
				<?php foreach ( $errors as $err ) : ?>
					<li>
						<strong><?php echo esc_html( (string) ( $err['title'] ?? '' ) ); ?></strong>
						<span><?php echo esc_html( (string) ( $err['message'] ?? '' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<div class="shojaei-card">
		<h3><?php esc_html_e( 'آخرین batchهای قابل Undo', 'shojaei-seo-for-woo' ); ?></h3>
		<?php if ( empty( $undos ) ) : ?>
			<p class="shojaei-desc"><?php esc_html_e( 'هنوز دسته اعمال‌شده‌ای برای بازگشت نیست. بعد از Dry-Run → اجرا اینجا ظاهر می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
		<?php else : ?>
			<ul class="shojaei-status-list">
				<?php foreach ( $undos as $u ) : ?>
					<li>
						<strong><?php echo esc_html( (string) ( $u['action'] ?? '' ) ); ?></strong>
						<span>
							<?php
							printf(
								/* translators: 1: count, 2: batch short */
								esc_html__( '%1$d مورد · batch %2$s', 'shojaei-seo-for-woo' ),
								(int) ( $u['count'] ?? 0 ),
								esc_html( substr( (string) ( $u['batch_id'] ?? '' ), 0, 8 ) )
							);
							?>
						</span>
						<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ) ); ?>">
							<?php esc_html_e( 'Undo', 'shojaei-seo-for-woo' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>

<?php
$scan_prog = class_exists( 'Shojaei_SEO_Queue' ) ? Shojaei_SEO_Queue::get_scan_progress() : array();
?>
<div class="shojaei-card shojaei-quick-actions">
	<h3><?php esc_html_e( 'میان‌برهای عملیاتی', 'shojaei-seo-for-woo' ); ?></h3>
	<div class="shojaei-quick-grid">
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=oos' ) ); ?>"><?php esc_html_e( 'عملیات → موجودی', 'shojaei-seo-for-woo' ); ?></a>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ) ); ?>"><?php esc_html_e( 'عملیات → Dry-Run', 'shojaei-seo-for-woo' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=test' ) ); ?>"><?php esc_html_e( 'عملیات → تست محصول', 'shojaei-seo-for-woo' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=slugs' ) ); ?>"><?php esc_html_e( 'عملیات → نامک', 'shojaei-seo-for-woo' ); ?></a>
		<button type="button" class="button" id="shojaei-force-rescan"><?php esc_html_e( 'اسکن مجدد موجودی', 'shojaei-seo-for-woo' ); ?></button>
	</div>

	<div id="shojaei-scan-progress" class="shojaei-scan-progress" style="margin-top:16px;" data-running="<?php echo ! empty( $scan_prog['running'] ) ? '1' : '0'; ?>">
		<div class="shojaei-scan-progress-meta">
			<strong id="shojaei-scan-progress-label"><?php echo esc_html( (string) ( $scan_prog['label'] ?? __( 'آماده برای شروع اسکن', 'shojaei-seo-for-woo' ) ) ); ?></strong>
			<span id="shojaei-scan-progress-pct"><?php echo esc_html( (string) (int) ( $scan_prog['percent'] ?? 0 ) ); ?>%</span>
		</div>
		<div class="shojaei-scan-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) (int) ( $scan_prog['percent'] ?? 0 ) ); ?>">
			<span id="shojaei-scan-progress-bar" style="width:<?php echo esc_attr( (string) (int) ( $scan_prog['percent'] ?? 0 ) ); ?>%"></span>
		</div>
		<p class="description" id="shojaei-scan-progress-detail">
			<?php
			printf(
				/* translators: 1: processed 2: total 3: pending */
				esc_html__( 'پردازش‌شده: %1$d · کل: %2$d · باقی‌مانده: %3$d', 'shojaei-seo-for-woo' ),
				(int) ( $scan_prog['processed'] ?? 0 ),
				(int) ( $scan_prog['total'] ?? 0 ),
				(int) ( $scan_prog['pending'] ?? 0 )
			);
			?>
		</p>
	</div>
</div>

<details class="shojaei-card shojaei-secondary-analytics">
	<summary>
		<strong><?php esc_html_e( 'آمار هفتگی و جزئیات بیشتر', 'shojaei-seo-for-woo' ); ?></strong>
		<span class="description"><?php esc_html_e( 'اختیاری — تمرکز اصلی روی وضعیت و اقدام است', 'shojaei-seo-for-woo' ); ?></span>
	</summary>
	<div class="shojaei-weekly-grid" style="margin-top:16px;">
		<div class="shojaei-weekly-item">
			<span class="shojaei-weekly-num"><?php echo esc_html( (int) ( $weekly['avg_oos'] ?? 0 ) ); ?></span>
			<span class="shojaei-weekly-label"><?php esc_html_e( 'میانگین ناموجود', 'shojaei-seo-for-woo' ); ?></span>
		</div>
		<div class="shojaei-weekly-item">
			<span class="shojaei-weekly-num"><?php echo esc_html( (int) ( $weekly['redirects'] ?? 0 ) ); ?></span>
			<span class="shojaei-weekly-label"><?php esc_html_e( 'ریدایرکت هفته', 'shojaei-seo-for-woo' ); ?></span>
		</div>
		<div class="shojaei-weekly-item">
			<span class="shojaei-weekly-num"><?php echo esc_html( (int) ( $weekly['gone_410'] ?? 0 ) ); ?></span>
			<span class="shojaei-weekly-label"><?php esc_html_e( '410 هفته', 'shojaei-seo-for-woo' ); ?></span>
		</div>
		<div class="shojaei-weekly-item">
			<span class="shojaei-weekly-num"><?php echo esc_html( (int) ( $weekly['links_built'] ?? 0 ) ); ?></span>
			<span class="shojaei-weekly-label"><?php esc_html_e( 'لینک هفته', 'shojaei-seo-for-woo' ); ?></span>
		</div>
	</div>
</details>
