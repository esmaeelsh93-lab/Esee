<?php
/**
 * Site header.
 *
 * @package Bahar_Shop
 */

$bahar_account_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
$bahar_cart_url     = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
$bahar_wishlist_url = function_exists( 'bahar_shop_wishlist_url' ) ? bahar_shop_wishlist_url() : home_url( '/wishlist/' );
$bahar_cart_count   = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="main-header glass-bar">
		<div class="container">
			<?php if ( is_front_page() ) : ?>
			<div class="main-header__meta">
				<div class="main-header__meta-social">
					<a href="https://instagram.com/baharcollectionss" class="main-header__meta-link main-header__meta-link--icon" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
					</a>
					<span class="main-header__meta-sep" aria-hidden="true"></span>
					<a href="tel:+989035233046" class="main-header__meta-link" aria-label="<?php esc_attr_e( 'تماس با بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8c1.5 2.9 3.7 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
						<span>۰۹۰۳۵۲۳۳۰۴۶</span>
					</a>
				</div>
				<p class="main-header__meta-tagline"><?php esc_html_e( 'پوشاک دخترانه شیک و روزمره', 'bahar-shop' ); ?></p>
			</div>
			<?php endif; ?>
			<div class="main-header__top">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo site-logo--mobile" aria-label="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>">
					<img src="<?php echo esc_url( bahar_shop_logo_url() ); ?>" alt="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>" width="140" height="48" decoding="async" />
				</a>
				<a href="tel:+989035233046" class="header-phone" aria-label="<?php esc_attr_e( 'تماس با بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8c1.5 2.9 3.7 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
					<span>۰۹۰۳۵۲۳۳۰۴۶</span>
				</a>
			</div>

			<div class="main-header__inner">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo site-logo--desktop" aria-label="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>">
					<img src="<?php echo esc_url( bahar_shop_logo_url() ); ?>" alt="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>" width="140" height="48" decoding="async" />
				</a>

				<button
					class="nav-toggle"
					type="button"
					aria-label="<?php esc_attr_e( 'باز کردن منوی اصلی', 'bahar-shop' ); ?>"
					aria-expanded="false"
					aria-controls="primary-nav"
				>
					<?php
					if ( function_exists( 'bahar_shop_the_icon' ) ) {
						bahar_shop_the_icon( 'menu', array( 'class' => 'nav-toggle__svg' ) );
						bahar_shop_the_icon( 'x', array( 'class' => 'nav-toggle__svg' ) );
					} else {
						?>
						<span class="nav-toggle__bars" aria-hidden="true">
							<span class="nav-toggle__icon"></span>
							<span class="nav-toggle__icon"></span>
							<span class="nav-toggle__icon"></span>
						</span>
						<?php
					}
					?>
				</button>

				<a href="https://instagram.com/baharcollectionss" class="header-insta" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
				</a>

				<nav id="primary-nav" class="primary-nav shj-main-navigation" role="navigation" aria-label="<?php esc_attr_e( 'منوی اصلی', 'bahar-shop' ); ?>">
					<ul class="shj-nav-list">
						<li class="menu-item<?php echo is_front_page() ? ' current-menu-item' : ''; ?>">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
								<?php bahar_shop_the_icon( 'house-heart', array( 'class' => 'nav-icon' ) ); ?>
								<span class="nav-label"><?php esc_html_e( 'خانه', 'bahar-shop' ); ?></span>
							</a>
						</li>

						<?php
						$shj_shop_url    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
						$shj_is_shop_ctx = function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product() );
						$shj_mega_html   = class_exists( 'SHJ_Dynamic_Mega_Menu' ) ? SHJ_Dynamic_Mega_Menu::render_shop_mega_menu() : '';
						?>
						<li class="menu-item menu-item-has-children shj-mega-trigger<?php echo $shj_is_shop_ctx ? ' current-menu-item' : ''; ?>">
							<a href="<?php echo esc_url( $shj_shop_url ); ?>">
								<?php bahar_shop_the_icon( 'shirt', array( 'class' => 'nav-icon' ) ); ?>
								<span class="nav-label"><?php esc_html_e( 'فروشگاه', 'bahar-shop' ); ?></span>
							</a>
							<?php if ( $shj_mega_html ) : ?>
								<ul class="sub-menu shj-dynamic-mega">
									<?php echo $shj_mega_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</ul>
							<?php endif; ?>
						</li>

						<?php
						$shj_blog_page_id = get_option( 'page_for_posts' );
						$shj_blog_url     = $shj_blog_page_id ? get_permalink( $shj_blog_page_id ) : home_url( '/blog' );
						$shj_is_blog      = is_home() || ( is_single() && 'post' === get_post_type() );
						?>
						<li class="menu-item<?php echo $shj_is_blog ? ' current-menu-item' : ''; ?>">
							<a href="<?php echo esc_url( $shj_blog_url ); ?>">
								<?php bahar_shop_the_icon( 'sparkles', array( 'class' => 'nav-icon' ) ); ?>
								<span class="nav-label"><?php esc_html_e( 'بلاگ بهار شاپ', 'bahar-shop' ); ?></span>
							</a>
						</li>

						<li class="menu-item shj-my-account-link<?php echo ( function_exists( 'is_account_page' ) && is_account_page() ) ? ' current-menu-item' : ''; ?>">
							<a href="<?php echo esc_url( $bahar_account_url ); ?>">
								<?php bahar_shop_the_icon( 'circle-user-round', array( 'class' => 'nav-icon' ) ); ?>
								<span class="nav-label"><?php echo is_user_logged_in() ? esc_html__( 'پنل کاربری', 'bahar-shop' ) : esc_html__( 'ورود / ثبت‌نام', 'bahar-shop' ); ?></span>
							</a>
						</li>
					</ul>
				</nav>

				<div class="header-actions header-actions--cluster" data-bahar-tools>
					<button type="button" class="header-actions__cluster-btn theme-toggle" aria-pressed="false" aria-label="<?php esc_attr_e( 'فعال‌سازی تم تاریک', 'bahar-shop' ); ?>" title="<?php esc_attr_e( 'تم', 'bahar-shop' ); ?>">
						<svg class="icon theme-toggle__icon theme-toggle__icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
						<?php bahar_shop_the_icon( 'sparkles', array( 'class' => 'theme-toggle__icon theme-toggle__icon--sun' ) ); ?>
					</button>
					<a href="<?php echo esc_url( $bahar_account_url ); ?>" class="header-actions__cluster-btn" aria-label="<?php esc_attr_e( 'حساب کاربری', 'bahar-shop' ); ?>">
						<?php bahar_shop_the_icon( 'circle-user-round', array( 'class' => 'icon' ) ); ?>
					</a>
					<a href="<?php echo esc_url( $bahar_cart_url ); ?>" class="header-actions__cluster-btn header-actions__cart" aria-label="<?php esc_attr_e( 'سبد خرید', 'bahar-shop' ); ?>">
						<?php bahar_shop_the_icon( 'shopping-bag', array( 'class' => 'icon' ) ); ?>
						<?php if ( $bahar_cart_count > 0 ) : ?>
							<span class="cart-count"><?php echo esc_html( $bahar_cart_count ); ?></span>
						<?php endif; ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</header>

<main id="main-content" class="site-main">
