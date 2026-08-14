<?php
/**
 * Site footer.
 *
 * @package ParisaCrop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="site-footer">
	<div class="site-footer__glow" aria-hidden="true"></div>
	<div class="site-footer__inner pc-container">
		<div class="site-footer__brand">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/parisa-crop-mark.svg' ); ?>" alt="" width="72" height="72">
			<div>
				<p class="site-footer__eyebrow">Parisa Crop</p>
				<h2><?php esc_html_e( 'خاص مثل تو', 'parisacrop' ); ?></h2>
			</div>
			<p><?php esc_html_e( 'انتخاب‌های تازه و دوست‌داشتنی برای استایلی که فقط مال توست.', 'parisacrop' ); ?></p>
		</div>

		<div class="site-footer__column">
			<h3><?php esc_html_e( 'دسترسی سریع', 'parisacrop' ); ?></h3>
			<a href="<?php echo esc_url( parisacrop_shop_url() ); ?>"><?php esc_html_e( 'فروشگاه', 'parisacrop' ); ?></a>
			<a href="<?php echo esc_url( parisacrop_shop_url() . '#categories' ); ?>"><?php esc_html_e( 'دسته‌بندی‌ها', 'parisacrop' ); ?></a>
			<a href="<?php echo esc_url( parisacrop_shop_url() . '#new-arrivals' ); ?>"><?php esc_html_e( 'جدیدترین‌ها', 'parisacrop' ); ?></a>
			<a href="<?php echo esc_url( parisacrop_shop_url() . '#why-us' ); ?>"><?php esc_html_e( 'چرا ما؟', 'parisacrop' ); ?></a>
		</div>

		<div class="site-footer__column site-footer__contact">
			<h3><?php esc_html_e( 'با ما در ارتباط باش', 'parisacrop' ); ?></h3>
			<p><?php esc_html_e( 'شماره تماس فروشگاه به‌زودی اضافه می‌شود.', 'parisacrop' ); ?></p>
			<div class="site-footer__socials">
				<a href="#" aria-label="<?php esc_attr_e( 'اینستاگرام؛ لینک به‌زودی', 'parisacrop' ); ?>">Instagram</a>
				<a href="#" aria-label="<?php esc_attr_e( 'واتساپ؛ لینک به‌زودی', 'parisacrop' ); ?>">WhatsApp</a>
			</div>
		</div>
	</div>

	<div class="site-footer__credit">
		<p>
			<?php esc_html_e( 'طراحی سایت اسماعیل شجاعی', 'parisacrop' ); ?>
			<span aria-hidden="true">|</span>
			<a href="tel:+989036994450" dir="ltr">09036994450</a>
		</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
