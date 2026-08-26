<?php
/**
 * Site header.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home_url       = home_url( '/' );
$shop_url       = rezajordaan_shop_url();
$header_links   = rezajordaan_get_theme_links( 'header_links' );
$instagram_url  = rezajordaan_get_setting( 'instagram_url' );
$whatsapp_url   = rezajordaan_get_setting( 'whatsapp_url' );
$telegram_url   = rezajordaan_get_setting( 'telegram_url' );
$show_inner_search = ! is_front_page();
$categories     = taxonomy_exists( 'product_cat' )
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
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'رفتن به محتوای اصلی', 'rezajordaan' ); ?></a>

<header class="site-header" data-header>
	<div class="site-header__inner rj-container<?php echo $show_inner_search ? ' has-inner-search' : ''; ?>">
		<a class="site-brand" href="<?php echo esc_url( $home_url ); ?>" aria-label="<?php esc_attr_e( 'صفحه اصلی رضا جردن', 'rezajordaan' ); ?>">
			<span class="site-brand__wordmark">
				<strong><?php esc_html_e( 'رضا جردن', 'rezajordaan' ); ?></strong>
				<small>REZA JORDAAN</small>
			</span>
		</a>

		<nav class="desktop-nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'rezajordaan' ); ?>">
			<a class="desktop-nav__link" href="<?php echo esc_url( $home_url ); ?>"><?php esc_html_e( 'خانه', 'rezajordaan' ); ?></a>
			<?php foreach ( $header_links as $header_link ) : ?>
				<a class="desktop-nav__link" href="<?php echo esc_url( $header_link['url'] ); ?>"><?php echo esc_html( $header_link['label'] ); ?></a>
			<?php endforeach; ?>
			<div class="desktop-nav__dropdown">
				<button class="desktop-nav__link desktop-nav__trigger" type="button" aria-expanded="false">
					<?php esc_html_e( 'دسته‌بندی‌ها', 'rezajordaan' ); ?>
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
						<a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'همه محصولات', 'rezajordaan' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</nav>

		<?php if ( $show_inner_search ) : ?>
			<form class="header-search" role="search" method="get" action="<?php echo esc_url( $home_url ); ?>">
				<label class="screen-reader-text" for="header-product-search"><?php esc_html_e( 'جستجوی محصول', 'rezajordaan' ); ?></label>
				<svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
				<input id="header-product-search" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'جستجوی محصول...', 'rezajordaan' ); ?>" autocomplete="off">
				<input type="hidden" name="post_type" value="product">
			</form>
		<?php endif; ?>

		<div class="header-actions">
			<?php if ( $instagram_url && rezajordaan_get_setting( 'show_instagram_header' ) ) : ?>
				<a class="social-button social-button--instagram" href="<?php echo esc_url( $instagram_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'اینستاگرام رضا جردن', 'rezajordaan' ); ?>">
					<svg aria-hidden="true" viewBox="0 0 24 24">
						<rect x="3" y="3" width="18" height="18" rx="5"/>
						<circle cx="12" cy="12" r="4"/>
						<circle class="fill-dot" cx="17.4" cy="6.6" r="1"/>
					</svg>
				</a>
			<?php endif; ?>
			<?php if ( $whatsapp_url && rezajordaan_get_setting( 'show_whatsapp_header' ) ) : ?>
				<a class="social-button social-button--whatsapp" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'واتساپ رضا جردن', 'rezajordaan' ); ?>">
					<svg aria-hidden="true" viewBox="0 0 24 24">
						<path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.7a8.5 8.5 0 1 1 16.2-4.1Z"/>
						<path d="M9 8.2c.2-.5.4-.5.7-.5h.5c.2 0 .3.1.4.4l.7 1.7c.1.3.1.4-.1.6l-.5.6c-.2.2-.2.3 0 .6.5.9 1.2 1.7 2.1 2.2.3.2.5.2.7 0l.7-.9c.2-.2.4-.3.6-.2l1.8.8c.3.1.4.3.4.5 0 .3-.2 1.4-.9 1.9-.5.4-1.2.7-2 .5-1.1-.2-2.6-.8-4.2-2.2-1.9-1.7-3-3.7-3.1-4.8-.1-.6.1-1.2.5-1.6.5-.5 1-.6 1.2-.6Z"/>
					</svg>
				</a>
			<?php endif; ?>
			<?php if ( $telegram_url && rezajordaan_get_setting( 'show_telegram_header' ) ) : ?>
				<a class="social-button social-button--telegram" href="<?php echo esc_url( $telegram_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'تلگرام رضا جردن', 'rezajordaan' ); ?>">
					<svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 11 18-7-4 17-6-5-4 3 1-5 9-7-11 6Z"/></svg>
				</a>
			<?php endif; ?>
			<?php if ( function_exists( 'rezajordaan_render_header_wishlist_link' ) ) : ?>
				<?php rezajordaan_render_header_wishlist_link(); ?>
			<?php endif; ?>
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<?php rezajordaan_render_header_cart_link(); ?>
			<?php endif; ?>
			<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="<?php esc_attr_e( 'باز کردن منو', 'rezajordaan' ); ?>">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>

	<div class="mobile-menu" id="mobile-menu" aria-hidden="true">
		<nav aria-label="<?php esc_attr_e( 'منوی موبایل', 'rezajordaan' ); ?>">
			<a href="<?php echo esc_url( $home_url ); ?>"><?php esc_html_e( 'خانه', 'rezajordaan' ); ?></a>
			<?php if ( function_exists( 'rezajordaan_wishlist_url' ) ) : ?>
				<a href="<?php echo esc_url( rezajordaan_wishlist_url() ); ?>"><?php esc_html_e( 'علاقه‌مندی‌ها', 'rezajordaan' ); ?></a>
			<?php endif; ?>
			<?php foreach ( $header_links as $header_link ) : ?>
				<a href="<?php echo esc_url( $header_link['url'] ); ?>"><?php echo esc_html( $header_link['label'] ); ?></a>
			<?php endforeach; ?>
			<p><?php esc_html_e( 'دسته‌بندی‌ها', 'rezajordaan' ); ?></p>
			<?php foreach ( $categories as $category ) : ?>
				<a class="mobile-menu__category" href="<?php echo esc_url( get_term_link( $category ) ); ?>">
					<?php echo esc_html( $category->name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</header>
