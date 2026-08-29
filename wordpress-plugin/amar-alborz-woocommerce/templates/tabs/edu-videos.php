<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * لینک ویدئوهای آموزشی؛ در صورت افزودن، از فیلتر aaw_education_videos قابل تکمیل است
 * (هر آیتم: title, url). در حال حاضر خالی است تا لینک نادرست/ساختگی نمایش داده نشود.
 */
$videos = apply_filters( 'aaw_education_videos', array() );
?>
<div class="aaw-panel aaw-edu-article">
	<h2>ویدئوهای آموزشی</h2>
	<p>ویدئوهای آموزشی کوتاه برای هر بخش از افزونه، به‌محض آماده شدن، در همین صفحه اضافه خواهند شد.</p>

	<?php if ( empty( $videos ) ) : ?>
		<div class="aaw-video-grid">
			<?php foreach ( array( 'شروع سریع', 'قیف فروش', 'ابزار حرفه‌ای' ) as $placeholder ) : ?>
				<div class="aaw-video-card is-placeholder">
					<div class="aaw-video-thumb" aria-hidden="true">🎥</div>
					<div class="aaw-video-title"><?php echo esc_html( $placeholder ); ?></div>
					<div class="aaw-video-status">به‌زودی</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="aaw-video-grid">
			<?php foreach ( $videos as $video ) : ?>
				<a class="aaw-video-card" href="<?php echo esc_url( $video['url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<div class="aaw-video-thumb" aria-hidden="true">▶</div>
					<div class="aaw-video-title"><?php echo esc_html( $video['title'] ); ?></div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
