<?php
/**
 * Impact & stats report tab.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$report = class_exists( 'Shojaei_SEO_Impact' ) ? Shojaei_SEO_Impact::get_report() : array();
$health = $report['health'] ?? array();
$counts = $health['counts'] ?? array();
$donut  = $report['donut'] ?? array( '301' => 0, '302' => 0, '410' => 0 );
$trend  = $report['trend'] ?? array();
$gsc    = $report['gsc'] ?? array();
$before = $report['before'] ?? null;
$after  = (int) ( $report['after'] ?? ( $health['score'] ?? 0 ) );
$delta  = $report['delta'] ?? null;
$tone   = (string) ( $health['tone'] ?? 'safe' );
?>

<div class="shojaei-ops-hero shojaei-impact-hero">
	<h2><?php esc_html_e( 'اثر و آمار عملیات', 'shojaei-seo-for-woo' ); ?></h2>
	<p><?php echo esc_html( $report['story'] ?? '' ); ?></p>
	<p class="shojaei-impact-disclaimer"><?php echo esc_html( $report['disclaimer'] ?? '' ); ?></p>
</div>

<div class="shojaei-impact-health shojaei-card">
	<h3><?php esc_html_e( 'سلامت عملیات موجودی / ایندکس‌پذیری', 'shojaei-seo-for-woo' ); ?></h3>
	<div class="shojaei-impact-health-grid">
		<div class="shojaei-impact-health-main shojaei-tone-<?php echo esc_attr( $tone ); ?>">
			<div class="shojaei-impact-score-big"><?php echo esc_html( (string) $after ); ?><small>%</small></div>
			<p><?php echo esc_html( $health['summary'] ?? '' ); ?></p>
			<?php if ( null !== $before && null !== $delta ) : ?>
				<div class="shojaei-impact-delta <?php echo $delta >= 0 ? 'is-up' : 'is-down'; ?>">
					<?php
					printf(
						/* translators: 1: before 2: after 3: delta */
						esc_html__( 'قبل از نصب (تخمین): %1$d٪ → الان: %2$d٪ (%3$s%4$d)', 'shojaei-seo-for-woo' ),
						(int) $before,
						(int) $after,
						$delta >= 0 ? '+' : '',
						(int) $delta
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<div class="shojaei-impact-bars">
			<?php
			if ( null !== $before && class_exists( 'Shojaei_SEO_Impact' ) ) {
				echo Shojaei_SEO_Impact::render_score_bar( (int) $before, __( 'قبل از نصب', 'shojaei-seo-for-woo' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			if ( class_exists( 'Shojaei_SEO_Impact' ) ) {
				echo Shojaei_SEO_Impact::render_score_bar( $after, __( 'وضعیت فعلی', 'shojaei-seo-for-woo' ), $tone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>
	</div>
	<?php if ( ! empty( $health['factors'] ) ) : ?>
		<ul class="shojaei-impact-factors">
			<?php foreach ( $health['factors'] as $factor ) : ?>
				<li class="<?php echo (int) ( $factor['delta'] ?? 0 ) >= 0 ? 'is-plus' : 'is-minus'; ?>">
					<strong><?php echo esc_html( (string) ( $factor['label'] ?? '' ) ); ?></strong>
					<span><?php echo esc_html( ( (int) ( $factor['delta'] ?? 0 ) >= 0 ? '+' : '' ) . (int) ( $factor['delta'] ?? 0 ) ); ?></span>
					<em><?php echo esc_html( (string) ( $factor['detail'] ?? '' ) ); ?></em>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

<div class="shojaei-stats-grid shojaei-impact-stats">
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon blue"><span class="dashicons dashicons-migrate"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['active_301'] ?? $counts['redirect_301_log'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'ریدایرکت ۳۰۱ فعال', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon green"><span class="dashicons dashicons-randomize"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['active_302'] ?? $counts['redirect_302_log'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'ریدایرکت ۳۰۲ فعال', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon orange"><span class="dashicons dashicons-dismiss"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['active_410'] ?? $counts['redirect_410_log'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'وضعیت ۴۱۰', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon purple"><span class="dashicons dashicons-hidden"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['noindex'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'noindex اعمال‌شده', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon blue"><span class="dashicons dashicons-archive"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['oos_open'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'ناموجود بدون ریدایرکت', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon green"><span class="dashicons dashicons-admin-links"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['links_built'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'لینک داخلی ساخته‌شده', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon orange"><span class="dashicons dashicons-google"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['gsc_indexed'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'درخواست ایندکس GSC', 'shojaei-seo-for-woo' ); ?></div>
	</div>
	<div class="shojaei-stat-card">
		<div class="shojaei-stat-icon purple"><span class="dashicons dashicons-warning"></span></div>
		<div class="shojaei-stat-number"><?php echo esc_html( (string) ( $counts['over_threshold'] ?? 0 ) ); ?></div>
		<div class="shojaei-stat-label"><?php esc_html_e( 'نیازمند اقدام (آستانه)', 'shojaei-seo-for-woo' ); ?></div>
	</div>
</div>

<div class="shojaei-impact-charts">
	<div class="shojaei-card">
		<h3><?php esc_html_e( 'تفکیک ریدایرکت‌ها', 'shojaei-seo-for-woo' ); ?></h3>
		<div class="shojaei-donut-wrap">
			<?php
			if ( class_exists( 'Shojaei_SEO_Impact' ) ) {
				echo Shojaei_SEO_Impact::render_donut( $donut ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<ul class="shojaei-donut-legend">
				<li><span class="dot d301"></span> ۳۰۱: <strong><?php echo esc_html( (string) ( $donut['301'] ?? 0 ) ); ?></strong></li>
				<li><span class="dot d302"></span> ۳۰۲: <strong><?php echo esc_html( (string) ( $donut['302'] ?? 0 ) ); ?></strong></li>
				<li><span class="dot d410"></span> ۴۱۰: <strong><?php echo esc_html( (string) ( $donut['410'] ?? 0 ) ); ?></strong></li>
			</ul>
		</div>
	</div>
	<div class="shojaei-card">
		<h3><?php esc_html_e( 'روند ۳۰ روزه (محصولات ناموجود)', 'shojaei-seo-for-woo' ); ?></h3>
		<?php
		if ( class_exists( 'Shojaei_SEO_Analytics' ) && ! empty( $trend['oos'] ) ) {
			echo Shojaei_SEO_Analytics::render_svg_line( $trend['oos'], $trend['labels'], '#1e88e5', 560, 160 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<p class="description">' . esc_html__( 'هنوز داده روند روزانه کافی نیست — پس از چند روز اسکن پر می‌شود.', 'shojaei-seo-for-woo' ) . '</p>';
		}
		?>
	</div>
	<div class="shojaei-card">
		<h3><?php esc_html_e( 'روند ریدایرکت روزانه', 'shojaei-seo-for-woo' ); ?></h3>
		<?php
		if ( class_exists( 'Shojaei_SEO_Analytics' ) && ! empty( $trend['redirects'] ) ) {
			echo Shojaei_SEO_Analytics::render_svg_line( $trend['redirects'], $trend['labels'], '#43a047', 560, 160 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div style="margin-top:8px;"></div>';
			echo Shojaei_SEO_Analytics::render_svg_line( $trend['gone_410'], $trend['labels'], '#ef6c00', 560, 120 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<p class="description">' . esc_html__( 'سبز: ریدایرکت ۳۰۱/۳۰۲ · نارنجی: ۴۱۰', 'shojaei-seo-for-woo' ) . '</p>';
		}
		?>
	</div>
</div>

<div class="shojaei-card" id="shojaei-impact-gsc">
	<h3><?php esc_html_e( 'اثر ایندکس (GSC)', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $gsc['message'] ?? '' ); ?></p>
	<?php if ( empty( $gsc['connected'] ) ) : ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-gsc' ) ); ?>">
				<?php esc_html_e( 'اتصال Search Console', 'shojaei-seo-for-woo' ); ?>
			</a>
		</p>
	<?php elseif ( ! empty( $gsc['rows'] ) ) : ?>
		<table class="shojaei-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'محصول', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $gsc['rows'] as $row ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( (string) ( $row['url'] ?? '#' ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( (string) ( $row['title'] ?? '' ) ); ?>
							</a>
						</td>
						<td><?php echo esc_html( (string) ( $row['redirect_type'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'هنوز ریدایرکت فعالی برای نمایش نیست.', 'shojaei-seo-for-woo' ); ?></p>
	<?php endif; ?>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'پروفایل فروشگاهی', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'آستانه‌های زمانی را با یک کلیک برای نوع فروشگاه تنظیم کنید (Dry-Run روشن می‌ماند).', 'shojaei-seo-for-woo' ); ?></p>
	<?php
	$profiles = class_exists( 'Shojaei_SEO_Impact' ) ? Shojaei_SEO_Impact::profiles() : array();
	$current  = class_exists( 'Shojaei_SEO_Impact' ) ? Shojaei_SEO_Impact::current_profile() : 'general';
	$groups   = array(
		'basic'   => __( 'پایه', 'shojaei-seo-for-woo' ),
		'retail'  => __( 'خرده‌فروشی', 'shojaei-seo-for-woo' ),
		'tech'    => __( 'فناوری', 'shojaei-seo-for-woo' ),
		'digital' => __( 'دیجیتال', 'shojaei-seo-for-woo' ),
	);
	?>
	<?php foreach ( $groups as $gid => $glabel ) :
		$in_group = array_filter(
			$profiles,
			static function ( $p ) use ( $gid ) {
				return ( $p['group'] ?? 'basic' ) === $gid;
			}
		);
		if ( empty( $in_group ) ) {
			continue;
		}
		?>
		<h4 class="shojaei-profile-group-title"><?php echo esc_html( $glabel ); ?></h4>
		<div class="shojaei-action-cards shojaei-profile-cards">
			<?php foreach ( $in_group as $pid => $profile ) : ?>
				<article class="shojaei-action-card <?php echo $current === $pid ? 'shojaei-tone-safe' : 'shojaei-tone-warning'; ?>">
					<header>
						<span class="dashicons dashicons-store"></span>
						<?php if ( $current === $pid ) : ?>
							<span class="shojaei-status-chip shojaei-tone-safe"><?php esc_html_e( 'فعال', 'shojaei-seo-for-woo' ); ?></span>
						<?php endif; ?>
					</header>
					<h3><?php echo esc_html( $profile['label'] ); ?></h3>
					<p><?php echo esc_html( $profile['description'] ); ?></p>
					<form method="post" action="">
						<?php wp_nonce_field( 'shojaei_seo_apply_profile', 'shojaei_seo_profile_nonce' ); ?>
						<input type="hidden" name="shojaei_seo_store_profile" value="<?php echo esc_attr( $pid ); ?>" />
						<button type="submit" class="button <?php echo $current === $pid ? '' : 'button-primary'; ?>" <?php disabled( $current === $pid ); ?>>
							<?php echo $current === $pid ? esc_html__( 'اعمال شده', 'shojaei-seo-for-woo' ) : esc_html__( 'اعمال پروفایل', 'shojaei-seo-for-woo' ); ?>
						</button>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</div>
