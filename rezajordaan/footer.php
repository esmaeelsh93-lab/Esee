<?php
/**
 * Site footer.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$footer_links   = rezajordaan_get_theme_links( 'footer_links' );
$phone_display  = rezajordaan_get_setting( 'phone_display' );
$phone_url      = rezajordaan_get_setting( 'phone_url' );
$instagram_url  = rezajordaan_get_setting( 'instagram_url' );
$whatsapp_url   = rezajordaan_get_setting( 'whatsapp_url' );
$rubika_url     = rezajordaan_get_setting( 'rubika_url' );
$email          = rezajordaan_get_setting( 'email' );
$address        = rezajordaan_get_setting( 'address' );
$enamad_url     = rezajordaan_get_setting( 'enamad_url' );
$enamad_logo    = rezajordaan_get_setting( 'enamad_logo_url' );
?>
<footer class="site-footer">
	<div class="site-footer__glow" aria-hidden="true"></div>
	<div class="site-footer__inner rj-container">
		<div class="site-footer__brand">
			<div>
				<p class="site-footer__eyebrow">Reza Jordaan</p>
				<h2><?php echo esc_html( rezajordaan_get_setting( 'footer_tagline' ) ); ?></h2>
			</div>
			<p><?php echo esc_html( rezajordaan_get_setting( 'footer_description' ) ); ?></p>
		</div>

		<div class="site-footer__column">
			<h3><?php esc_html_e( 'لینک‌های مفید', 'rezajordaan' ); ?></h3>
			<?php foreach ( $footer_links as $footer_link ) : ?>
				<a href="<?php echo esc_url( $footer_link['url'] ); ?>"><?php echo esc_html( $footer_link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>

		<div class="site-footer__column site-footer__contact">
			<h3><?php esc_html_e( 'راه‌های ارتباطی', 'rezajordaan' ); ?></h3>
			<div class="site-footer__contact-grid">
				<?php if ( $instagram_url && rezajordaan_get_setting( 'show_social_footer' ) ) : ?>
					<a class="footer-contact-card footer-contact-card--instagram" href="<?php echo esc_url( $instagram_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="footer-contact-card__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle class="fill-dot" cx="17.3" cy="6.7" r="1"/></svg>
						</span>
						<span><strong><?php esc_html_e( 'اینستاگرام', 'rezajordaan' ); ?></strong><small dir="ltr">@rezajordaan</small></span>
					</a>
				<?php endif; ?>
				<?php if ( $whatsapp_url && rezajordaan_get_setting( 'show_social_footer' ) ) : ?>
					<a class="footer-contact-card footer-contact-card--whatsapp" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="footer-contact-card__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.7a8.5 8.5 0 1 1 16.2-4.1Z"/><path d="M9 8.2c.2-.5.4-.5.7-.5h.5c.2 0 .3.1.4.4l.7 1.7c.1.3.1.4-.1.6l-.5.6c-.2.2-.2.3 0 .6.5.9 1.2 1.7 2.1 2.2.3.2.5.2.7 0l.7-.9c.2-.2.4-.3.6-.2l1.8.8"/></svg>
						</span>
						<span><strong><?php esc_html_e( 'واتساپ', 'rezajordaan' ); ?></strong><small dir="ltr"><?php echo esc_html( $phone_display ); ?></small></span>
					</a>
				<?php endif; ?>
				<?php if ( $phone_display && rezajordaan_get_setting( 'show_phone_footer' ) ) : ?>
					<a class="footer-contact-card footer-contact-card--phone" href="<?php echo esc_url( $phone_url ); ?>">
						<span class="footer-contact-card__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24"><path d="M7.2 3.7 10 7.2 8.3 9.4c1.2 2.6 3.2 4.6 5.8 5.8l2.2-1.7 3.5 2.8-.7 3.3c-.2.8-.9 1.3-1.7 1.3C9.5 20.3 3.7 14.5 3.1 6.6c-.1-.8.5-1.5 1.3-1.7l2.8-.7Z"/></svg>
						</span>
						<span><strong><?php esc_html_e( 'تماس مستقیم', 'rezajordaan' ); ?></strong><small dir="ltr"><?php echo esc_html( $phone_display ); ?></small></span>
					</a>
				<?php endif; ?>
				<?php if ( $rubika_url && rezajordaan_get_setting( 'show_social_footer' ) ) : ?>
					<a class="footer-contact-card footer-contact-card--rubika" href="<?php echo esc_url( $rubika_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="footer-contact-card__icon" aria-hidden="true">
							<svg viewBox="0 0 24 24"><path d="M7.3 4.3h9.4a3 3 0 0 1 3 3v6.1a3 3 0 0 1-3 3h-4.1L8.3 20v-3.6h-1a3 3 0 0 1-3-3V7.3a3 3 0 0 1 3-3Z"/><path d="M8.5 9.2h7m-7 3.1h4.5"/></svg>
						</span>
						<span><strong><?php esc_html_e( 'روبیکا', 'rezajordaan' ); ?></strong><small>rezajordaan</small></span>
					</a>
				<?php endif; ?>
			</div>
			<?php if ( $email ) : ?>
				<p class="site-footer__email"><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>" dir="ltr"><?php echo esc_html( antispambot( $email ) ); ?></a></p>
			<?php endif; ?>
			<?php if ( $address ) : ?>
				<p class="site-footer__address"><?php echo wp_kses_post( nl2br( esc_html( $address ) ) ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $enamad_url && $enamad_logo && rezajordaan_get_setting( 'show_enamad_footer' ) ) : ?>
			<div class="site-footer__trust">
				<h3><?php esc_html_e( 'خرید مطمئن', 'rezajordaan' ); ?></h3>
				<a href="<?php echo esc_url( $enamad_url ); ?>" target="_blank" rel="noopener noreferrer" referrerpolicy="origin">
					<img src="<?php echo esc_url( $enamad_logo ); ?>" alt="<?php esc_attr_e( 'نماد اعتماد الکترونیکی رضا جردن', 'rezajordaan' ); ?>" loading="lazy" referrerpolicy="origin" width="125" height="136">
				</a>
			</div>
		<?php endif; ?>
	</div>

	<div class="site-footer__credit">
		<p>
			<?php esc_html_e( 'طراحی سایت اسماعیل شجاعی', 'rezajordaan' ); ?>
			<span aria-hidden="true">|</span>
			<a href="tel:+989036994450" dir="ltr">09036994450</a>
		</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
