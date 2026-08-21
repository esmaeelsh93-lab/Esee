<?php
/**
 * Site footer.
 *
 * @package Bahar_Shop
 */
?>
</main>

<?php
if ( function_exists( 'bahar_shop_render_why_us' ) ) {
	bahar_shop_render_why_us();
}
?>

<footer class="site-footer">
	<div class="container footer-grid">
		<div class="footer-brand glass-card">
			<img src="<?php echo esc_url( bahar_shop_logo_url() ); ?>" alt="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>" class="footer-logo" width="120" height="42" loading="lazy" />
			<p class="footer-desc"><?php esc_html_e( 'فروشگاه آنلاین پوشاک دخترانه — استایل‌های کیوت و لباس‌های روزمره و ترند.', 'bahar-shop' ); ?></p>
		</div>

		<div class="footer-links glass-card">
			<h3><?php esc_html_e( 'راهنما', 'bahar-shop' ); ?></h3>
			<ul>
				<li><a href="<?php echo esc_url( bahar_shop_info_page_url( 'shipping-info' ) ); ?>"><?php esc_html_e( 'نحوه ارسال', 'bahar-shop' ); ?></a></li>
				<li><a href="<?php echo esc_url( bahar_shop_info_page_url( 'privacy-policy' ) ); ?>"><?php esc_html_e( 'سیاست حفظ حریم خصوصی', 'bahar-shop' ); ?></a></li>
				<li><a href="<?php echo esc_url( bahar_shop_info_page_url( 'returns-policy' ) ); ?>"><?php esc_html_e( 'سیاست تعویض و مرجوعی کالا', 'bahar-shop' ); ?></a></li>
			</ul>
		</div>

		<div class="footer-contact glass-card">
			<h3><?php esc_html_e( 'تماس با ما', 'bahar-shop' ); ?></h3>
			<p class="footer-address">
				<?php esc_html_e( 'استان البرز – کرج – بلوار بهشتی - بعد از خ ۴۵ متری خ ولیعصر (بازرگانی) - بعد از چهار راه دوم فروشگاه جردن', 'bahar-shop' ); ?>
			</p>
			<p>
				<a href="tel:+989035233046"><?php esc_html_e( 'تلفن هماهنگی: ۰۹۰۳۵۲۳۳۰۴۶', 'bahar-shop' ); ?></a>
			</p>
			<p class="footer-social">
				<a href="https://instagram.com/baharcollectionss" class="footer-social__link" target="_blank" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
				</a>
			</p>
		</div>

		<div class="footer-trust glass-card">
			<h3><?php esc_html_e( 'نماد اعتماد', 'bahar-shop' ); ?></h3>
			<div class="enamad-wrap">
				<a referrerpolicy="origin" target="_blank" href="https://trustseal.enamad.ir/?id=555069&Code=B80b2NbS4f7GHb8PcK6wXevfI9HceDXO">
					<img referrerpolicy="origin" src="https://trustseal.enamad.ir/logo.aspx?id=555069&Code=B80b2NbS4f7GHb8PcK6wXevfI9HceDXO" alt="<?php esc_attr_e( 'نماد اعتماد الکترونیکی', 'bahar-shop' ); ?>" style="cursor:pointer" code="B80b2NbS4f7GHb8PcK6wXevfI9HceDXO" loading="lazy" />
				</a>
			</div>
		</div>
	</div>

	<div class="footer-bottom">
		<div class="container">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> — <?php esc_html_e( 'همه حقوق محفوظ است', 'bahar-shop' ); ?></p>
			<p class="footer-designer"><?php esc_html_e( 'طراح سایت :| اسماعیل شجاعی | 09036994450', 'bahar-shop' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
