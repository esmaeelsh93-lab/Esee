<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * هدر Hero مشترک تمام صفحات افزونه: نام برند «آمار البرز» + تگ‌لاین + دکمه تغییر پوسته.
 * انتظار متغیرهای اختیاری: $page_subtitle، $header_actions (آرایه‌ای از ['label','url','class']).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aaw_subtitle = isset( $page_subtitle ) ? $page_subtitle : 'تحلیل هوشمند فروشگاه ووکامرسی شما';
$aaw_actions  = isset( $header_actions ) ? $header_actions : array();
?>
<div class="aaw-hero">
	<div class="aaw-hero-brand">
		<span class="aaw-hero-logo" aria-hidden="true">📊</span>
		<div class="aaw-hero-text">
			<h1 class="aaw-hero-title">آمار البرز</h1>
			<p class="aaw-hero-tagline"><?php echo esc_html( $aaw_subtitle ); ?></p>
		</div>
	</div>
	<div class="aaw-hero-actions">
		<?php foreach ( $aaw_actions as $action ) : ?>
			<a class="aaw-btn <?php echo esc_attr( isset( $action['class'] ) ? $action['class'] : '' ); ?>" href="<?php echo esc_url( $action['url'] ); ?>">
				<?php echo esc_html( $action['label'] ); ?>
			</a>
		<?php endforeach; ?>
		<button type="button" class="aaw-theme-toggle" id="aawThemeToggle" aria-label="تغییر پوسته روشن/تاریک" title="تغییر پوسته روشن/تاریک">
			<span class="aaw-theme-icon-dark" aria-hidden="true">🌙</span><span class="aaw-theme-icon-light" aria-hidden="true">☀️</span>
		</button>
	</div>
</div>
