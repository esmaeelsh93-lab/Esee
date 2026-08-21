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
			<p class="footer-social footer-social--row">
				<a href="https://instagram.com/baharcollectionss" class="footer-social__link" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
				</a>
				<a href="https://wa.me/989035233046" class="footer-social__link footer-social__link--wa" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'واتساپ بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2m4.84 14.13c-.21.6-1.22 1.16-1.68 1.23-.43.06-.98.09-1.58-.1-.36-.12-.84-.28-1.45-.55-2.55-1.1-4.22-3.67-4.35-3.84-.13-.17-1.04-1.38-1.04-2.63 0-1.25.66-1.86.89-2.12.22-.25.49-.32.65-.32.16 0 .33 0 .47.01.15 0 .35-.06.55.42.21.49.71 1.74.77 1.87.06.13.1.28.02.45-.08.17-.12.28-.25.43-.13.15-.27.33-.39.45-.13.12-.27.25-.12.49.15.24.67 1.1 1.44 1.78.99.88 1.82 1.15 2.08 1.28.26.13.41.11.56-.07.15-.18.64-.75.81-1.01.17-.26.34-.22.58-.13.24.09 1.52.72 1.78.85.26.13.43.19.49.3.06.11.06.64-.15 1.24z"/></svg>
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
			<p class="footer-designer"><?php esc_html_e( 'طراح سایت اسماعیل شجاعی 09036994450', 'bahar-shop' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
