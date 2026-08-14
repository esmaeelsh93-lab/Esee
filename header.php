<?php
/**
 * Site header.
 *
 * @package ParisaCrop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_url  = parisacrop_shop_url();
$categories = taxonomy_exists( 'product_cat' )
	? get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
			'number'     => 12,
		)
	)
	: array();

if ( is_wp_error( $categories ) ) {
	$categories = array();
}
?>
<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'رفتن به محتوای اصلی', 'parisacrop' ); ?></a>

<header class="site-header" data-header>
	<div class="site-header__inner pc-container">
		<a class="site-brand" href="<?php echo esc_url( $shop_url ); ?>" aria-label="<?php esc_attr_e( 'فروشگاه پریسا کراپ', 'parisacrop' ); ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php echo wp_kses_post( get_custom_logo() ); ?>
			<?php else : ?>
				<img
					class="site-brand__logo"
					src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/parisa-crop-logo.svg' ); ?>"
					alt="<?php esc_attr_e( 'Parisa Crop', 'parisacrop' ); ?>"
					width="108"
					height="108"
				>
			<?php endif; ?>
		</a>

		<nav class="desktop-nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'parisacrop' ); ?>">
			<a class="desktop-nav__link" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'خانه', 'parisacrop' ); ?></a>
			<div class="desktop-nav__dropdown">
				<button class="desktop-nav__link desktop-nav__trigger" type="button" aria-expanded="false">
					<?php esc_html_e( 'دسته‌بندی‌ها', 'parisacrop' ); ?>
					<svg aria-hidden="true" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg>
				</button>
				<div class="desktop-nav__menu">
					<?php if ( $categories ) : ?>
						<?php foreach ( $categories as $category ) : ?>
							<a href="<?php echo esc_url( get_term_link( $category ) ); ?>">
								<?php echo esc_html( $category->name ); ?>
								<span><?php echo esc_html( number_format_i18n( $category->count ) ); ?></span>
							</a>
						<?php endforeach; ?>
					<?php else : ?>
						<a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'همه محصولات', 'parisacrop' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</nav>

		<div class="header-actions">
			<a class="social-button social-button--instagram" href="#" aria-label="<?php esc_attr_e( 'اینستاگرام پریسا کراپ؛ لینک به‌زودی اضافه می‌شود', 'parisacrop' ); ?>">
				<svg aria-hidden="true" viewBox="0 0 24 24">
					<rect x="3" y="3" width="18" height="18" rx="5"/>
					<circle cx="12" cy="12" r="4"/>
					<circle class="fill-dot" cx="17.4" cy="6.6" r="1"/>
				</svg>
			</a>
			<a class="social-button social-button--whatsapp" href="#" aria-label="<?php esc_attr_e( 'واتساپ پریسا کراپ؛ لینک به‌زودی اضافه می‌شود', 'parisacrop' ); ?>">
				<svg aria-hidden="true" viewBox="0 0 24 24">
					<path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.7a8.5 8.5 0 1 1 16.2-4.1Z"/>
					<path d="M9 8.2c.2-.5.4-.5.7-.5h.5c.2 0 .3.1.4.4l.7 1.7c.1.3.1.4-.1.6l-.5.6c-.2.2-.2.3 0 .6.5.9 1.2 1.7 2.1 2.2.3.2.5.2.7 0l.7-.9c.2-.2.4-.3.6-.2l1.8.8c.3.1.4.3.4.5 0 .3-.2 1.4-.9 1.9-.5.4-1.2.7-2 .5-1.1-.2-2.6-.8-4.2-2.2-1.9-1.7-3-3.7-3.1-4.8-.1-.6.1-1.2.5-1.6.5-.5 1-.6 1.2-.6Z"/>
				</svg>
			</a>
			<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="<?php esc_attr_e( 'باز کردن منو', 'parisacrop' ); ?>">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>

	<div class="mobile-menu" id="mobile-menu" aria-hidden="true">
		<nav aria-label="<?php esc_attr_e( 'منوی موبایل', 'parisacrop' ); ?>">
			<a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'خانه', 'parisacrop' ); ?></a>
			<p><?php esc_html_e( 'دسته‌بندی‌ها', 'parisacrop' ); ?></p>
			<?php foreach ( $categories as $category ) : ?>
				<a class="mobile-menu__category" href="<?php echo esc_url( get_term_link( $category ) ); ?>">
					<?php echo esc_html( $category->name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</header>
