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
$bahar_insta_url    = 'https://instagram.com/baharcollectionss';
$bahar_wa_url       = 'https://wa.me/989035233046';
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
				<div class="main-header__meta-social header-social-icons">
					<a href="<?php echo esc_url( $bahar_insta_url ); ?>" class="header-social-icons__link" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
					</a>
					<a href="<?php echo esc_url( $bahar_wa_url ); ?>" class="header-social-icons__link header-social-icons__link--wa" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'واتساپ بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2m4.84 14.13c-.21.6-1.22 1.16-1.68 1.23-.43.06-.98.09-1.58-.1-.36-.12-.84-.28-1.45-.55-2.55-1.1-4.22-3.67-4.35-3.84-.13-.17-1.04-1.38-1.04-2.63 0-1.25.66-1.86.89-2.12.22-.25.49-.32.65-.32.16 0 .33 0 .47.01.15 0 .35-.06.55.42.21.49.71 1.74.77 1.87.06.13.1.28.02.45-.08.17-.12.28-.25.43-.13.15-.27.33-.39.45-.13.12-.27.25-.12.49.15.24.67 1.1 1.44 1.78.99.88 1.82 1.15 2.08 1.28.26.13.41.11.56-.07.15-.18.64-.75.81-1.01.17-.26.34-.22.58-.13.24.09 1.52.72 1.78.85.26.13.43.19.49.3.06.11.06.64-.15 1.24z"/></svg>
					</a>
				</div>
				<p class="main-header__meta-tagline"><?php esc_html_e( 'پوشاک دخترانه شیک و روزمره', 'bahar-shop' ); ?></p>
			</div>
			<?php endif; ?>

			<div class="main-header__inner">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo site-logo--desktop" aria-label="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>">
					<img src="<?php echo esc_url( bahar_shop_logo_url() ); ?>" alt="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>" width="140" height="48" decoding="async" />
				</a>

				<div class="header-mobile-tools">
					<a href="<?php echo esc_url( $bahar_insta_url ); ?>" class="header-insta" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
					</a>
					<a href="<?php echo esc_url( $bahar_wa_url ); ?>" class="header-wa" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'واتساپ بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2m4.84 14.13c-.21.6-1.22 1.16-1.68 1.23-.43.06-.98.09-1.58-.1-.36-.12-.84-.28-1.45-.55-2.55-1.1-4.22-3.67-4.35-3.84-.13-.17-1.04-1.38-1.04-2.63 0-1.25.66-1.86.89-2.12.22-.25.49-.32.65-.32.16 0 .33 0 .47.01.15 0 .35-.06.55.42.21.49.71 1.74.77 1.87.06.13.1.28.02.45-.08.17-.12.28-.25.43-.13.15-.27.33-.39.45-.13.12-.27.25-.12.49.15.24.67 1.1 1.44 1.78.99.88 1.82 1.15 2.08 1.28.26.13.41.11.56-.07.15-.18.64-.75.81-1.01.17-.26.34-.22.58-.13.24.09 1.52.72 1.78.85.26.13.43.19.49.3.06.11.06.64-.15 1.24z"/></svg>
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
				</div>

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
						<span id="bahar-header-cart-count">
							<?php if ( $bahar_cart_count > 0 ) : ?>
								<span class="cart-count"><?php echo esc_html( $bahar_cart_count ); ?></span>
							<?php endif; ?>
						</span>
					</a>
				</div>
			</div>
		</div>
	</div>
</header>

<main id="main-content" class="site-main">
