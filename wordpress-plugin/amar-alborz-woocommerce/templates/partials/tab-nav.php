<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * نوار تب‌های فشرده و دسته‌بندی‌شده (کم‌عرض و بدون شلوغی)، مشترک بین چند صفحه.
 * انتظار متغیرها: $tab_groups (آرایه‌ی گروه‌بندی‌شده)، $tab (تب فعلی)، $tab_page (اسلاگ صفحه).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aaw-tabnav">
	<?php foreach ( $tab_groups as $group_label => $tabs ) : ?>
		<div class="aaw-tabnav-group">
			<?php if ( ! empty( $group_label ) ) : ?>
				<span class="aaw-tabnav-group-label"><?php echo esc_html( $group_label ); ?></span>
			<?php endif; ?>
			<div class="aaw-tabnav-row">
				<?php foreach ( $tabs as $tab_key => $tab_info ) : ?>
					<a class="aaw-tab-pill <?php echo $tab === $tab_key ? 'is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( array( 'page' => $tab_page, 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>">
						<span aria-hidden="true"><?php echo esc_html( $tab_info['icon'] ); ?></span>
						<?php echo esc_html( $tab_info['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
