<?php
/**
 * SEO Pulse dashboard — نبض سئو.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shojaei_SEO_Pulse' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول نبض سئو در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

if ( ! Shojaei_SEO_Pulse::is_ready() ) {
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'زیرساخت نبض سئو آماده نیست. از «هسته سئو → اجرای خودترمیمی» استفاده کنید.', 'shojaei-seo-for-woo' ) . '</p></div>';
}

$stats  = Shojaei_SEO_Pulse::dashboard_stats();
$filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$list   = Shojaei_SEO_Pulse::query_results(
	array(
		'filter' => $filter,
		'q'      => $q,
		'page'   => $page,
	)
);
$avg    = (int) $stats['avg_score'];
$tone   = $avg >= 75 ? 'safe' : ( $avg >= 50 ? 'warning' : 'error' );
$rm_on  = class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::is_rank_math_active();
?>

<?php if ( $rm_on ) : ?>
	<div class="notice notice-info inline" style="margin:0 0 12px;">
		<p>
			<?php esc_html_e( 'Rank Math فعال است. نبض سئو جایگزین امتیاز Rank Math نیست — تحلیل قانون‌محور مکمل برای اقدامات عملیاتی است و متای عنوان/توضیح را از Rank Math می‌خواند.', 'shojaei-seo-for-woo' ); ?>
		</p>
	</div>
<?php endif; ?>

<div class="shojaei-card">
	<div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-start;">
		<div>
			<h3 style="margin:0 0 6px;"><?php esc_html_e( 'نبض سئو', 'shojaei-seo-for-woo' ); ?></h3>
			<p class="shojaei-desc" style="margin:0;"><?php esc_html_e( 'تحلیل قانون‌محور محلی — بدون API خارجی. اسکن در پس‌زمینه اجرا می‌شود تا ادمین کند نشود.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
		<button type="button" class="button button-primary" id="shojaei-pulse-scan" <?php disabled( ! empty( $stats['scanning'] ) ); ?>>
			<?php echo ! empty( $stats['scanning'] ) ? esc_html__( 'در حال اسکن پس‌زمینه…', 'shojaei-seo-for-woo' ) : esc_html__( 'شروع اسکن پس‌زمینه', 'shojaei-seo-for-woo' ); ?>
		</button>
	</div>
	<p id="shojaei-pulse-status" class="description" style="margin-top:10px;" aria-live="polite"></p>
</div>

<div class="shojaei-pulse-score-hero shojaei-card">
	<div class="shojaei-pulse-score-ring shojaei-tone-<?php echo esc_attr( $tone ); ?>">
		<span class="shojaei-pulse-score-num"><?php echo esc_html( (string) $avg ); ?></span>
		<span class="shojaei-pulse-score-label">
			<?php
			$status = $stats['status'] ?? Shojaei_SEO_Pulse::status_from_score( $avg );
			echo esc_html( (string) ( $status['label'] ?? __( 'سلامت کلی', 'shojaei-seo-for-woo' ) ) );
			?>
		</span>
	</div>
	<div class="shojaei-pulse-score-bar" aria-hidden="true">
		<span style="width:<?php echo esc_attr( (string) $avg ); ?>%;"></span>
	</div>
</div>

<div class="shojaei-pulse-stats">
	<div class="shojaei-card shojaei-pulse-stat">
		<div class="shojaei-pulse-stat-n"><?php echo esc_html( (string) $stats['total'] ); ?></div>
		<div class="shojaei-pulse-stat-l"><?php esc_html_e( 'صفحه تحلیل‌شده', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-card shojaei-pulse-stat">
		<div class="shojaei-pulse-stat-n"><?php echo esc_html( (string) $stats['orphan'] ); ?></div>
		<div class="shojaei-pulse-stat-l"><?php esc_html_e( 'صفحه یتیم', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-card shojaei-pulse-stat">
		<div class="shojaei-pulse-stat-n"><?php echo esc_html( (string) $stats['critical'] ); ?></div>
		<div class="shojaei-pulse-stat-l"><?php esc_html_e( 'خطای بحرانی', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-card shojaei-pulse-stat">
		<div class="shojaei-pulse-stat-n"><?php echo esc_html( (string) $stats['broken'] ); ?></div>
		<div class="shojaei-pulse-stat-l"><?php esc_html_e( 'لینک شکسته', 'shojaei-seo-for-woo' ); ?></div>
	</div>
</div>

<div class="shojaei-card">
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
		<input type="hidden" name="page" value="shojaei-seo" />
		<input type="hidden" name="tab" value="seo-pulse" />
		<select name="filter">
			<option value="all" <?php selected( $filter, 'all' ); ?>><?php esc_html_e( 'همه', 'shojaei-seo-for-woo' ); ?></option>
			<option value="critical" <?php selected( $filter, 'critical' ); ?>><?php esc_html_e( 'بحرانی', 'shojaei-seo-for-woo' ); ?></option>
			<option value="orphan" <?php selected( $filter, 'orphan' ); ?>><?php esc_html_e( 'یتیم', 'shojaei-seo-for-woo' ); ?></option>
			<option value="low" <?php selected( $filter, 'low' ); ?>><?php esc_html_e( 'امتیاز < ۵۰', 'shojaei-seo-for-woo' ); ?></option>
			<option value="good" <?php selected( $filter, 'good' ); ?>><?php esc_html_e( 'خوب (≥۷۵)', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'جستجوی عنوان…', 'shojaei-seo-for-woo' ); ?>" style="min-width:200px;" />
		<button type="submit" class="button"><?php esc_html_e( 'فیلتر', 'shojaei-seo-for-woo' ); ?></button>
	</form>

	<?php if ( empty( $list['rows'] ) ) : ?>
		<div class="shojaei-empty-state" style="margin-top:16px;">
			<span class="dashicons dashicons-heart"></span>
			<p><?php esc_html_e( 'هنوز تحلیلی نیست. «شروع اسکن پس‌زمینه» را بزنید.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" style="margin-top:14px;" id="shojaei-pulse-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'صفحه', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'امتیاز', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'لایه‌ها', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مسائل', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'اولویت اقدام', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'اقدام', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $list['rows'] as $row ) : ?>
					<?php
					$pid    = (int) $row->post_id;
					$title  = get_the_title( $pid );
					$st     = (int) $row->score;
					$status = Shojaei_SEO_Pulse::status_from_score( $st );
					$stone  = 'good' === $status['key'] ? 'safe' : ( 'needs_improvement' === $status['key'] ? 'warning' : 'error' );
					$issues = json_decode( (string) $row->issues, true );
					if ( ! is_array( $issues ) ) {
						$issues = array();
					}
					$problems = array_values(
						array_filter(
							$issues,
							static function ( $i ) {
								return in_array( ( $i['severity'] ?? '' ), array( 'error', 'warning', 'critical' ), true );
							}
						)
					);
					$top = $problems[0] ?? null;
					?>
					<tr data-post-id="<?php echo esc_attr( (string) $pid ); ?>">
						<td>
							<strong><?php echo esc_html( $title ?: ( '#' . $pid ) ); ?></strong>
							<?php if ( ! empty( $row->is_orphan ) ) : ?>
								<span class="shojaei-slug-score shojaei-tone-error" style="margin-right:6px;"><?php esc_html_e( 'یتیم', 'shojaei-seo-for-woo' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) $row->post_type ); ?></td>
						<td>
							<span class="shojaei-slug-score shojaei-tone-<?php echo esc_attr( $stone ); ?>"><?php echo esc_html( (string) $st ); ?></span>
							<div class="shojaei-pulse-mini-bar"><span style="width:<?php echo esc_attr( (string) $st ); ?>%;"></span></div>
						</td>
						<td class="description" style="font-size:12px;line-height:1.6;">
							<?php
							printf(
								'صفحه %d · محتوا %d · فنی %d · لینک %d',
								(int) $row->score_onpage,
								(int) $row->score_content,
								(int) $row->score_technical,
								(int) $row->score_links
							);
							if ( 'product' === (string) $row->post_type && class_exists( 'Damavand_Content_Analyzer' ) ) {
								$rel = Damavand_Content_Analyzer::normalize_related_input(
									(string) get_post_meta( $pid, Damavand_Content_Analyzer::META_RELATED, true )
								);
								if ( '' !== $rel ) {
									echo '<br /><span style="opacity:.85;">' . esc_html__( 'مرتبط:', 'shojaei-seo-for-woo' ) . ' ' . esc_html( $rel ) . '</span>';
								}
							}
							?>
						</td>
						<td><span class="shojaei-slug-score shojaei-tone-<?php echo esc_attr( $stone ); ?>"><?php echo esc_html( $status['label'] ); ?></span></td>
						<td>
							<?php
							printf(
								/* translators: 1: critical 2: warning */
								esc_html__( '%1$d خطا · %2$d هشدار', 'shojaei-seo-for-woo' ),
								(int) $row->critical_count,
								(int) $row->warning_count
							);
							?>
						</td>
						<td style="max-width:280px;">
							<?php if ( $top ) : ?>
								<strong><?php echo esc_html( (string) ( $top['title'] ?? '' ) ); ?></strong>
								<?php if ( ! empty( $top['code'] ) ) : ?>
									<br /><code style="font-size:11px;"><?php echo esc_html( (string) $top['code'] ); ?></code>
								<?php endif; ?>
								<br /><span class="description"><?php echo esc_html( (string) ( $top['action'] ?? '' ) ); ?></span>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td style="white-space:nowrap;">
							<?php if ( ! empty( $row->is_orphan ) && class_exists( 'Shojaei_SEO_Link_Genius' ) ) : ?>
								<button type="button" class="button button-small button-primary shojaei-orphan-fix" data-post-id="<?php echo esc_attr( (string) $pid ); ?>"><?php esc_html_e( 'پیشنهاد لینک ورودی', 'shojaei-seo-for-woo' ); ?></button>
							<?php endif; ?>
							<button type="button" class="button button-small shojaei-pulse-reanalyze" data-post-id="<?php echo esc_attr( (string) $pid ); ?>"><?php esc_html_e( 'تحلیل مجدد', 'shojaei-seo-for-woo' ); ?></button>
							<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php esc_html_e( 'ویرایش', 'shojaei-seo-for-woo' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div id="shojaei-orphan-modal" class="shojaei-orphan-modal" hidden>
	<div class="shojaei-orphan-modal__backdrop" data-orphan-close="1"></div>
	<div class="shojaei-orphan-modal__panel" role="dialog" aria-modal="true" aria-labelledby="shojaei-orphan-modal-title">
		<div class="shojaei-orphan-modal__head">
			<h3 id="shojaei-orphan-modal-title"><?php esc_html_e( 'بهبود صفحه یتیم', 'shojaei-seo-for-woo' ); ?></h3>
			<button type="button" class="button-link shojaei-orphan-modal__x" data-orphan-close="1" aria-label="<?php esc_attr_e( 'بستن', 'shojaei-seo-for-woo' ); ?>">&times;</button>
		</div>
		<p class="description" id="shojaei-orphan-modal-target"></p>
		<label for="shojaei-orphan-keywords"><strong><?php esc_html_e( 'کلمات کلیدی لنگر', 'shojaei-seo-for-woo' ); ?></strong></label>
		<textarea id="shojaei-orphan-keywords" rows="3" class="large-text" style="width:100%;margin:6px 0 12px;"></textarea>
		<p class="description"><?php esc_html_e( 'پس از تأیید: نقشه کلمات ساخته می‌شود و یک لینک ورودی در مبدأهای انتخاب‌شده درج می‌گردد (فقط با تأیید شما).', 'shojaei-seo-for-woo' ); ?></p>
		<div id="shojaei-orphan-suggestions"></div>
		<p id="shojaei-orphan-modal-status" class="description" aria-live="polite"></p>
		<div class="shojaei-orphan-modal__actions">
			<button type="button" class="button" data-orphan-close="1"><?php esc_html_e( 'انصراف', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-primary" id="shojaei-orphan-apply"><?php esc_html_e( 'تأیید و ساخت نقشه', 'shojaei-seo-for-woo' ); ?></button>
		</div>
	</div>
</div>
