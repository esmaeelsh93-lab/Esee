<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * نوار انتخاب بازه‌ی زمانی (مشترک بین صفحات گزارش). انتظار متغیرها: $range, $from, $to,
 * $range_page (اسلاگ صفحه‌ی جاری) و در صورت وجود $range_tab (برای حفظ تب فعلی).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aaw_range_args = array( 'page' => $range_page );
if ( ! empty( $range_tab ) ) {
	$aaw_range_args['tab'] = $range_tab;
}
?>
<div class="aaw-filterbar">
	<?php foreach ( AAW_Admin::get_range_options() as $key => $label ) : ?>
		<a class="aaw-pill <?php echo $range === $key ? 'is-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( array_merge( $aaw_range_args, array( 'range' => $key ) ), admin_url( 'admin.php' ) ) ); ?>">
			<?php echo esc_html( $label ); ?>
		</a>
	<?php endforeach; ?>

	<?php if ( 'custom' === $range ) : ?>
		<span class="aaw-filterbar-spacer"></span>
		<form class="aaw-custom-range" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( $range_page ); ?>" />
			<?php if ( ! empty( $range_tab ) ) : ?>
				<input type="hidden" name="tab" value="<?php echo esc_attr( $range_tab ); ?>" />
			<?php endif; ?>
			<input type="hidden" name="range" value="custom" />
			<label>از <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" /></label>
			<label>تا <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" /></label>
			<button type="submit" class="aaw-btn aaw-btn-primary">اعمال</button>
		</form>
	<?php endif; ?>
</div>
