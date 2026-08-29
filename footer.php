<?php
/**
 * Site footer.
 *
 * @package ParisaCrop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$footer_logo_id = get_theme_mod( 'custom_logo' );
$footer_links   = parisacrop_get_theme_links( 'footer_links' );
$phone_display  = parisacrop_get_setting( 'phone_display' );
$phone_url      = parisacrop_get_setting( 'phone_url' );
$instagram_url  = parisacrop_get_setting( 'instagram_url' );
$whatsapp_url   = parisacrop_get_setting( 'whatsapp_url' );
$telegram_url   = parisacrop_get_setting( 'telegram_url' );
$email          = parisacrop_get_setting( 'email' );
$address        = parisacrop_get_setting( 'address' );
?>
<footer class="site-footer">
	<div class="site-footer__glow" aria-hidden="true"></div>
	<div class="site-footer__inner pc-container">
		<div class="site-footer__brand">
			<?php if ( $footer_logo_id ) : ?>
				<?php
				echo wp_kses_post(
					wp_get_attachment_image(
						$footer_logo_id,
						'full',
						false,
						array(
							'class' => 'site-footer__logo',
							'alt'   => get_bloginfo( 'name' ),
						)
					)
				);
				?>
			<?php else : ?>
				<img class="site-footer__logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/parisa-crop-logo.svg' ); ?>" alt="<?php esc_attr_e( 'Parisa Crop', 'parisacrop' ); ?>" width="72" height="72">
			<?php endif; ?>
			<div>
				<p class="site-footer__eyebrow">Parisa Crop</p>
				<h2><?php echo esc_html( parisacrop_get_setting( 'footer_tagline' ) ); ?></h2>
			</div>
			<p><?php echo esc_html( parisacrop_get_setting( 'footer_description' ) ); ?></p>
		</div>

		<div class="site-footer__column">
			<h3><?php esc_html_e( 'دسترسی سریع', 'parisacrop' ); ?></h3>
			<?php foreach ( $footer_links as $footer_link ) : ?>
				<a href="<?php echo esc_url( $footer_link['url'] ); ?>"><?php echo esc_html( $footer_link['label'] ); ?></a>
			<?php endforeach; ?>
		</div>

		<div class="site-footer__column site-footer__contact">
			<h3><?php esc_html_e( 'با ما در ارتباط باش', 'parisacrop' ); ?></h3>
			<?php if ( $phone_display && parisacrop_get_setting( 'show_phone_footer' ) ) : ?>
				<p>
					<?php esc_html_e( 'شماره تماس:', 'parisacrop' ); ?>
					<a href="<?php echo esc_url( $phone_url ); ?>" dir="ltr"><?php echo esc_html( $phone_display ); ?></a>
				</p>
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
					<?php if ( $telegram_url ) : ?><a href="<?php echo esc_url( $telegram_url ); ?>" target="_blank" rel="noopener noreferrer">Telegram</a><?php endif; ?>
				</div>
			<?php endif; ?>
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
