<?php
/**
 * Site footer.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$footer_links   = parisacrop_get_theme_links( 'footer_links' );
$phone_display  = parisacrop_get_setting( 'phone_display' );
$phone_url      = parisacrop_get_setting( 'phone_url' );
$instagram_url  = parisacrop_get_setting( 'instagram_url' );
$whatsapp_url   = parisacrop_get_setting( 'whatsapp_url' );
$rubika_url     = parisacrop_get_setting( 'rubika_url' );
$telegram_url   = parisacrop_get_setting( 'telegram_url' );
$email          = parisacrop_get_setting( 'email' );
$address        = parisacrop_get_setting( 'address' );
$enamad_url     = parisacrop_get_setting( 'enamad_url' );
$enamad_logo    = parisacrop_get_setting( 'enamad_logo_url' );
?>
<footer class="site-footer">
	<div class="site-footer__glow" aria-hidden="true"></div>
	<div class="site-footer__inner pc-container">
		<div class="site-footer__brand">
			<div>
				<p class="site-footer__eyebrow">Reza Jordaan</p>
				<h2><?php echo esc_html( parisacrop_get_setting( 'footer_tagline' ) ); ?></h2>
			</div>
			<p><?php echo esc_html( parisacrop_get_setting( 'footer_description' ) ); ?></p>
		</div>

		<div class="site-footer__column">
			<h3><?php esc_html_e( 'لینک‌های مفید', 'parisacrop' ); ?></h3>
			<?php foreach ( $footer_links as $footer_link ) : ?>
				<a href="<?php echo esc_url( $footer_link['url'] ); ?>"><?php echo esc_html( $footer_link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>

		<div class="site-footer__column site-footer__contact">
			<h3><?php esc_html_e( 'راه‌های ارتباطی', 'parisacrop' ); ?></h3>
			<?php if ( $instagram_url ) : ?>
				<p>
					<?php esc_html_e( 'اینستاگرام:', 'parisacrop' ); ?>
					<a href="<?php echo esc_url( $instagram_url ); ?>" target="_blank" rel="noopener noreferrer" dir="ltr">@rezajordaan</a>
				</p>
			<?php endif; ?>
			<?php if ( $phone_display && parisacrop_get_setting( 'show_phone_footer' ) ) : ?>
				<p>
					<?php esc_html_e( 'تماس:', 'parisacrop' ); ?>
					<a href="<?php echo esc_url( $phone_url ); ?>" dir="ltr"><?php echo esc_html( $phone_display ); ?></a>
				</p>
			<?php endif; ?>
			<?php if ( $whatsapp_url ) : ?>
				<p>
					<?php esc_html_e( 'واتساپ:', 'parisacrop' ); ?>
					<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" dir="ltr"><?php echo esc_html( $phone_display ); ?></a>
				</p>
			<?php endif; ?>
			<?php if ( $rubika_url ) : ?>
				<p><a href="<?php echo esc_url( $rubika_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'روبیکا رضا جردن', 'parisacrop' ); ?></a></p>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<p><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>" dir="ltr"><?php echo esc_html( antispambot( $email ) ); ?></a></p>
			<?php endif; ?>
			<?php if ( $address ) : ?>
				<p><?php echo wp_kses_post( nl2br( esc_html( $address ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( parisacrop_get_setting( 'show_social_footer' ) ) : ?>
				<div class="site-footer__socials">
					<?php if ( $instagram_url ) : ?><a href="<?php echo esc_url( $instagram_url ); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
					<?php if ( $whatsapp_url ) : ?><a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
					<?php if ( $rubika_url ) : ?><a href="<?php echo esc_url( $rubika_url ); ?>" target="_blank" rel="noopener noreferrer">Rubika</a><?php endif; ?>
					<?php if ( $telegram_url ) : ?><a href="<?php echo esc_url( $telegram_url ); ?>" target="_blank" rel="noopener noreferrer">Telegram</a><?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $enamad_url && $enamad_logo && parisacrop_get_setting( 'show_enamad_footer' ) ) : ?>
			<div class="site-footer__trust">
				<h3><?php esc_html_e( 'خرید مطمئن', 'parisacrop' ); ?></h3>
				<a href="<?php echo esc_url( $enamad_url ); ?>" target="_blank" rel="noopener noreferrer" referrerpolicy="origin">
					<img src="<?php echo esc_url( $enamad_logo ); ?>" alt="<?php esc_attr_e( 'نماد اعتماد الکترونیکی رضا جردن', 'parisacrop' ); ?>" loading="lazy" referrerpolicy="origin" width="125" height="136">
				</a>
			</div>
		<?php endif; ?>
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
