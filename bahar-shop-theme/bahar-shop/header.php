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
$hs                 = function_exists( 'bahar_shop_header_settings' ) ? bahar_shop_header_settings() : array();
$bahar_phone_tel    = ! empty( $hs['phone_url'] ) ? $hs['phone_url'] : 'tel:+989035233046';
$bahar_whatsapp_url = ! empty( $hs['whatsapp_url'] ) ? $hs['whatsapp_url'] : 'https://wa.me/989035233046';
$bahar_insta_url    = ! empty( $hs['instagram_url'] ) ? $hs['instagram_url'] : 'https://instagram.com/baharcollectionss';
$bahar_tg_url       = ! empty( $hs['telegram_url'] ) ? $hs['telegram_url'] : '';
$show_insta         = ! isset( $hs['show_instagram'] ) || ! empty( $hs['show_instagram'] );
$show_wa            = ! isset( $hs['show_whatsapp'] ) || ! empty( $hs['show_whatsapp'] );
$show_tg            = ! empty( $hs['show_telegram'] );
$show_call          = ! empty( $hs['show_call'] );
$show_cart          = ! isset( $hs['show_cart'] ) || ! empty( $hs['show_cart'] );
$show_account       = ! isset( $hs['show_account'] ) || ! empty( $hs['show_account'] );
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
					<?php if ( $show_insta && $bahar_insta_url ) : ?>
					<a href="<?php echo esc_url( $bahar_insta_url ); ?>" class="main-header__meta-link main-header__meta-link--icon" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'اینستاگرام بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
					</a>
					<?php endif; ?>
					<?php if ( $show_wa && $bahar_whatsapp_url ) : ?>
					<a href="<?php echo esc_url( $bahar_whatsapp_url ); ?>" class="main-header__meta-link main-header__meta-link--icon" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'واتساپ بهار شاپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.23 8.23 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23-1.48 0-2.93-.39-4.19-1.15l-.3-.17-3.12.82.83-3.04-.2-.32a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24m4.52 10.4c-.25-.12-1.47-.72-1.7-.8-.22-.09-.39-.12-.55.12-.16.25-.64.8-.78.96-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.55-1.33-.76-1.82-.2-.48-.4-.41-.55-.42h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.88 2.4 1 2.56.12.17 1.75 2.67 4.23 3.74 2.49 1.08 2.49.72 2.94.67.45-.05 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
					</a>
					<?php endif; ?>
				</div>
				<p class="main-header__meta-tagline"><?php esc_html_e( 'پوشاک دخترانه شیک و روزمره', 'bahar-shop' ); ?></p>
			</div>
			<?php endif; ?>

			<div class="main-header__inner">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-logo site-logo--desktop" aria-label="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>">
					<img src="<?php echo esc_url( bahar_shop_logo_url() ); ?>" alt="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>" width="140" height="48" decoding="async" />
				</a>

				<!-- موبایل: سمت راست تصویر = همبرگر + سوشیال -->
				<div class="header-mobile-tools">
					<button class="nav-toggle" type="button" aria-label="<?php esc_attr_e( 'باز کردن منوی اصلی', 'bahar-shop' ); ?>" aria-expanded="false" aria-controls="primary-nav">
						<?php
						if ( function_exists( 'bahar_shop_the_icon' ) ) {
							bahar_shop_the_icon( 'menu', array( 'class' => 'nav-toggle__svg' ) );
							bahar_shop_the_icon( 'x', array( 'class' => 'nav-toggle__svg' ) );
						} else {
							echo '<span class="nav-toggle__bars" aria-hidden="true"><span class="nav-toggle__icon"></span><span class="nav-toggle__icon"></span><span class="nav-toggle__icon"></span></span>';
						}
						?>
					</button>
					<?php if ( $show_wa && $bahar_whatsapp_url ) : ?>
					<a href="<?php echo esc_url( $bahar_whatsapp_url ); ?>" class="header-whatsapp" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'واتساپ', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2m.01 1.67c2.2 0 4.26.86 5.82 2.42a8.23 8.23 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.24 8.23-1.48 0-2.93-.39-4.19-1.15l-.3-.17-3.12.82.83-3.04-.2-.32a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24m4.52 10.4c-.25-.12-1.47-.72-1.7-.8-.22-.09-.39-.12-.55.12-.16.25-.64.8-.78.96-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.55-1.33-.76-1.82-.2-.48-.4-.41-.55-.42h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.88 2.4 1 2.56.12.17 1.75 2.67 4.23 3.74 2.49 1.08 2.49.72 2.94.67.45-.05 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.23-.17-.48-.29z"/></svg>
					</a>
					<?php endif; ?>
					<?php if ( $show_insta && $bahar_insta_url ) : ?>
					<a href="<?php echo esc_url( $bahar_insta_url ); ?>" class="header-insta" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'اینستاگرام', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg>
					</a>
					<?php endif; ?>
					<?php if ( $show_tg && $bahar_tg_url ) : ?>
					<a href="<?php echo esc_url( $bahar_tg_url ); ?>" class="header-telegram" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'تلگرام', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m3 11 18-7-4 17-6-5-4 3 1-5 9-7-11 6Z"/></svg>
					</a>
					<?php endif; ?>
					<?php if ( $show_call && $bahar_phone_tel ) : ?>
					<a href="<?php echo esc_url( $bahar_phone_tel ); ?>" class="header-call" aria-label="<?php esc_attr_e( 'تماس', 'bahar-shop' ); ?>">
						<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8c1.5 2.9 3.7 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
					</a>
					<?php endif; ?>
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
								<ul class="sub-menu shj-dynamic-mega"><?php echo $shj_mega_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></ul>
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

				<!-- موبایل: سمت چپ تصویر = سبد + حساب -->
				<div class="header-actions header-actions--cluster" data-bahar-tools>
					<?php if ( $show_account ) : ?>
					<a href="<?php echo esc_url( $bahar_account_url ); ?>" class="header-actions__cluster-btn" aria-label="<?php esc_attr_e( 'حساب کاربری', 'bahar-shop' ); ?>">
						<?php bahar_shop_the_icon( 'circle-user-round', array( 'class' => 'icon' ) ); ?>
					</a>
					<?php endif; ?>
					<?php if ( $show_cart ) : ?>
					<a href="<?php echo esc_url( $bahar_cart_url ); ?>" class="header-actions__cluster-btn header-actions__cart" aria-label="<?php esc_attr_e( 'سبد خرید', 'bahar-shop' ); ?>">
						<?php bahar_shop_the_icon( 'shopping-bag', array( 'class' => 'icon' ) ); ?>
						<span class="cart-count<?php echo $bahar_cart_count > 0 ? ' is-visible' : ''; ?>" data-bahar-cart-count><?php echo esc_html( $bahar_cart_count > 0 ? (string) $bahar_cart_count : '' ); ?></span>
					</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</header>

<main id="main-content" class="site-main">
