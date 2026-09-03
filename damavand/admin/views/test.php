<?php
/**
 * Product test / diagnose view.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$preselect_id = absint( $_GET['product_id'] ?? 0 );

$products = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 100,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

if ( $preselect_id && ! in_array( $preselect_id, wp_list_pluck( $products, 'ID' ), true ) ) {
	$extra = get_post( $preselect_id );
	if ( $extra && 'product' === $extra->post_type ) {
		array_unshift( $products, $extra );
	}
}

$dry_run = 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' );
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'تست سریع محصول', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php esc_html_e( 'وضعیت ردیابی OOS، فاز، noindex، پیشنهاد ریدایرکت و حالت اسکیما را برای یک محصول ببینید — بدون تغییر واقعی.', 'shojaei-seo-for-woo' ); ?>
	</p>

	<?php if ( $dry_run ) : ?>
		<div class="shojaei-edu-tip" style="margin-bottom:16px;">
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'حالت Dry-Run فعال است: ریدایرکت خودکار فقط پیشنهاد می‌شود و اعمال نمی‌شود.', 'shojaei-seo-for-woo' ); ?>
		</div>
	<?php else : ?>
		<div class="shojaei-edu-tip shojaei-edu-warn" style="margin-bottom:16px;">
			<span class="dashicons dashicons-warning"></span>
			<?php esc_html_e( 'Dry-Run خاموش است: ریدایرکت خودکار واقعاً اعمال می‌شود.', 'shojaei-seo-for-woo' ); ?>
		</div>
	<?php endif; ?>

	<div class="shojaei-preview-form">
		<select id="shojaei-test-product">
			<option value=""><?php esc_html_e( '— انتخاب محصول —', 'shojaei-seo-for-woo' ); ?></option>
			<?php foreach ( $products as $p ) : ?>
				<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $preselect_id, (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?> (#<?php echo esc_html( (string) $p->ID ); ?>)</option>
			<?php endforeach; ?>
		</select>
		<input type="number" id="shojaei-test-product-id" min="1" value="<?php echo $preselect_id ? esc_attr( (string) $preselect_id ) : ''; ?>" placeholder="<?php esc_attr_e( 'یا ID محصول', 'shojaei-seo-for-woo' ); ?>" style="width:140px;" />
		<button type="button" class="button button-primary" id="shojaei-run-product-test"><?php esc_html_e( 'اجرای تست', 'shojaei-seo-for-woo' ); ?></button>
	</div>

	<div id="shojaei-test-result" class="shojaei-test-result" style="display:none;"></div>
</div>
