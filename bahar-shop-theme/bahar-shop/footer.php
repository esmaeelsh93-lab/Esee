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

$fs           = function_exists( 'bahar_shop_footer_settings' ) ? bahar_shop_footer_settings() : array();
$hs           = function_exists( 'bahar_shop_header_settings' ) ? bahar_shop_header_settings() : array();
$footer_desc  = ! empty( $fs['description'] ) ? $fs['description'] : __( 'فروشگاه آنلاین پوشاک دخترانه — استایل‌های کیوت و لباس‌های روزمره و ترند.', 'bahar-shop' );
$insta_url    = ! empty( $hs['instagram_url'] ) ? $hs['instagram_url'] : 'https://instagram.com/baharcollectionss';
$wa_url       = ! empty( $hs['whatsapp_url'] ) ? $hs['whatsapp_url'] : 'https://wa.me/989035233046';
$phone_url    = ! empty( $hs['phone_url'] ) ? $hs['phone_url'] : 'tel:+989035233046';
$show_insta   = ! isset( $hs['show_instagram'] ) || ! empty( $hs['show_instagram'] );
$show_wa      = ! isset( $hs['show_whatsapp'] ) || ! empty( $hs['show_whatsapp'] );
?>

<footer class="site-footer">
	<div class="container footer-grid">
		<div class="footer-brand glass-card">
			<img src="<?php echo esc_url( bahar_shop_logo_url() ); ?>" alt="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>" class="footer-logo" width="120" height="42" loading="lazy" />
			<p class="footer-desc"><?php echo esc_html( $footer_desc ); ?></p>
			<?php if ( ! empty( $fs['extra_heading'] ) || ! empty( $fs['extra_text'] ) || ! empty( $fs['btn1_text'] ) || ! empty( $fs['btn2_text'] ) || ! empty( $fs['icon1_url'] ) || ! empty( $fs['icon2_url'] ) ) : ?>
			<div class="footer-extras">
				<?php if ( ! empty( $fs['extra_heading'] ) ) : ?>
					<h4 class="footer-extras__heading"><?php echo esc_html( $fs['extra_heading'] ); ?></h4>
				<?php endif; ?>
				<?php if ( ! empty( $fs['extra_text'] ) ) : ?>
					<p class="footer-extras__text"><?php echo esc_html( $fs['extra_text'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $fs['btn1_text'] ) && ! empty( $fs['btn1_url'] ) ) : ?>
					<a class="footer-extras__btn" href="<?php echo esc_url( $fs['btn1_url'] ); ?>"><?php echo esc_html( $fs['btn1_text'] ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $fs['btn2_text'] ) && ! empty( $fs['btn2_url'] ) ) : ?>
					<a class="footer-extras__btn footer-extras__btn--secondary" href="<?php echo esc_url( $fs['btn2_url'] ); ?>"><?php echo esc_html( $fs['btn2_text'] ); ?></a>
				<?php endif; ?>
				<?php if ( ( ! empty( $fs['icon1_label'] ) && ! empty( $fs['icon1_url'] ) ) || ( ! empty( $fs['icon2_label'] ) && ! empty( $fs['icon2_url'] ) ) ) : ?>
				<p class="footer-extras__icons">
					<?php if ( ! empty( $fs['icon1_label'] ) && ! empty( $fs['icon1_url'] ) ) : ?>
						<a href="<?php echo esc_url( $fs['icon1_url'] ); ?>" class="footer-extras__icon-link" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $fs['icon1_label'] ); ?></a>
					<?php endif; ?>
					<?php if ( ! empty( $fs['icon2_label'] ) && ! empty( $fs['icon2_url'] ) ) : ?>
						<a href="<?php echo esc_url( $fs['icon2_url'] ); ?>" class="footer-extras__icon-link" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $fs['icon2_label'] ); ?></a>
					<?php endif; ?>
				</p>
				<?php endif; ?>
			</div>
			<?php endif; ?>
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
			<p class="footer-social">
				<?php if ( $show_insta && $insta_url ) : ?>
				<a href="<?php echo esc_url( $insta_url ); ?>" class="footer-social__link" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
				</a>
				<?php endif; ?>
				<?php if ( $show_wa && $wa_url ) : ?>
				<a href="<?php echo esc_url( $wa_url ); ?>" class="footer-social__link" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'واتساپ بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.23 8.23 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23-1.48 0-2.93-.39-4.19-1.15l-.3-.17-3.12.82.83-3.04-.2-.32a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24m4.52 10.4c-.25-.12-1.47-.72-1.7-.8-.22-.09-.39-.12-.55.12-.16.25-.64.8-.78.96-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.55-1.33-.76-1.82-.2-.48-.4-.41-.55-.42h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.88 2.4 1 2.56.12.17 1.75 2.67 4.23 3.74 2.49 1.08 2.49.72 2.94.67.45-.05 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
				</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $phone_url ); ?>" class="footer-social__link" aria-label="<?php esc_attr_e( 'تماس با بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8c1.5 2.9 3.7 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
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
			<p class="footer-designer"><?php esc_html_e( 'طراح سایت | اسماعیل شجاعی | 09036994450', 'bahar-shop' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
