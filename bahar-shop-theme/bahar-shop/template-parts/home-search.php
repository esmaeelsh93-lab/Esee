<?php
/**
 * Homepage smart search box.
 *
 * @package Bahar_Shop
 */

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>
<section class="home-search" aria-label="<?php esc_attr_e( 'جستجوی محصولات', 'bahar-shop' ); ?>">
	<div class="container">
		<div class="home-search__box glass-card">
			<form class="home-search__form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
				<input type="hidden" name="post_type" value="product" />
				<label class="screen-reader-text" for="bahar-home-search"><?php esc_html_e( 'جستجوی محصول', 'bahar-shop' ); ?></label>
				<div class="home-search__field home-search__field--shimmer">
					<span class="home-search__shine" aria-hidden="true"></span>
					<svg class="home-search__icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16a6.471 6.471 0 0 0 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
					<input
						id="bahar-home-search"
						class="home-search__input"
						type="search"
						name="s"
						placeholder="<?php esc_attr_e( 'دنبال چی می‌گردی؟ کراپ، تیشرت یا...', 'bahar-shop' ); ?>"
						autocomplete="off"
						inputmode="search"
					/>
					<button type="submit" class="home-search__submit bahar-btn bahar-btn--small"><?php esc_html_e( 'جستجو', 'bahar-shop' ); ?></button>
				</div>
			</form>
			<div class="home-search__results" id="bahar-search-results" hidden></div>
		</div>
	</div>
</section>
