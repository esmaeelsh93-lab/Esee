<?php
/**
 * Reza Jordaan — Immersive mobile single-product UI
 *
 * ظاهر صفحه محصول تکی روی موبایل نزدیک به mockup (هیرو تمام‌صفحه، کارت سفید شناور،
 * سلکتور تعداد عمودی، نوار پایین افزودن به سبد) — بدون تغییر فایل‌های قالب.
 *
 * نصب (یکی از دو روش):
 * 1) پلاگین Code Snippets → Add New → PHP → محتوا را بچسبانید
 *    (اگر ادیتور خودش <?php می‌گذارد، خط اول <?php را حذف کنید)
 *    Run snippet: «Only run on site front-end»
 * 2) کپی این فایل به: wp-content/mu-plugins/rj-single-product-immersive.php
 *
 * حذف: اسنیپت را Inactive/Delete کنید یا فایل mu-plugin را پاک کنید.
 * قالب Reza Jordaan دست‌نخورده می‌ماند.
 *
 * @package RezaJordaanSnippets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RJ_Immersive_Single_Product {

	const BODY_CLASS = 'rj-immersive-product';
	const VERSION    = '1.0.0';

	public static function init() {
		add_action( 'wp', array( __CLASS__, 'setup' ), 20 );
	}

	public static function setup() {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		// Rebuild summary order so title/meta/price/qty sit inside one floating card.
		remove_action( 'woocommerce_before_single_product_summary', 'rezajordaan_single_product_heading', 1 );

		if ( function_exists( 'rezajordaan_single_purchase_box_open' ) ) {
			remove_action( 'woocommerce_single_product_summary', 'rezajordaan_single_purchase_box_open', 4 );
			remove_action( 'woocommerce_single_product_summary', 'rezajordaan_single_purchase_box_close', 11 );
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

		$open  = function_exists( 'rezajordaan_single_purchase_box_open' ) ? 'rezajordaan_single_purchase_box_open' : array( __CLASS__, 'purchase_open' );
		$close = function_exists( 'rezajordaan_single_purchase_box_close' ) ? 'rezajordaan_single_purchase_box_close' : array( __CLASS__, 'purchase_close' );

		add_action( 'woocommerce_single_product_summary', $open, 3 );
		add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 4 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_meta_line' ), 5 );
		add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 6 );
		add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 10 );
		add_action( 'woocommerce_single_product_summary', $close, 12 );

		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'woocommerce_before_single_product', array( __CLASS__, 'render_chrome' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 40 );
	}

	public static function purchase_open() {
		echo '<div class="single-product-purchase">';
	}

	public static function purchase_close() {
		echo '</div>';
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes[] = self::BODY_CLASS;
		return $classes;
	}

	public static function render_meta_line() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$bits = array();

		$weight = $product->get_weight();
		if ( $weight ) {
			$unit = get_option( 'woocommerce_weight_unit', 'kg' );
			$bits[] = wc_format_localized_decimal( $weight ) . ' ' . $unit;
		}

		$stock = $product->is_in_stock()
			? __( 'موجود', 'rezajordaan' )
			: __( 'ناموجود', 'rezajordaan' );
		$bits[] = $stock;

		$short = wp_strip_all_tags( $product->get_short_description() );
		if ( $short && count( $bits ) < 3 ) {
			$bits[] = wp_html_excerpt( $short, 42, '…' );
		}

		if ( ! $bits ) {
			return;
		}

		echo '<p class="rj-immersive__meta">' . esc_html( implode( ' | ', $bits ) ) . '</p>';
	}

	public static function render_chrome() {
		$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		$back_url = wp_get_referer() ? wp_get_referer() : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) );
		$count    = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		?>
		<div class="rj-immersive-chrome" aria-hidden="false">
			<a class="rj-immersive-fab rj-immersive-fab--back" href="<?php echo esc_url( $back_url ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 6.5 9 12l5.5 5.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				<span><?php esc_html_e( 'بازگشت', 'rezajordaan' ); ?></span>
			</a>
			<div class="rj-immersive-fab-stack">
				<a class="rj-immersive-fab" href="<?php echo esc_url( $cart_url ); ?>" aria-label="<?php esc_attr_e( 'سبد خرید', 'rezajordaan' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h15l-1.5 9h-12z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M6 7 5 3H2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/></svg>
					<?php if ( $count > 0 ) : ?>
						<em class="rj-immersive-fab__badge"><?php echo esc_html( number_format_i18n( $count ) ); ?></em>
					<?php endif; ?>
				</a>
				<button type="button" class="rj-immersive-fab" data-rj-immersive-scroll="summary" aria-label="<?php esc_attr_e( 'جزئیات محصول', 'rezajordaan' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s6.5-4.2 6.5-9.2A4.3 4.3 0 0 0 12 7.2 4.3 4.3 0 0 0 5.5 11.3c0 5 6.5 9.2 6.5 9.2z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
				</button>
				<button type="button" class="rj-immersive-fab" data-rj-immersive-scroll="gallery" aria-label="<?php esc_attr_e( 'گالری تصاویر', 'rezajordaan' ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="11" r="1.6"/><path d="m8 16 3.2-3.2a1.2 1.2 0 0 1 1.6 0L17.5 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
				</button>
			</div>
		</div>
		<div class="rj-immersive-dock" data-rj-immersive-dock>
			<button type="button" class="rj-immersive-dock__cta" data-rj-immersive-add>
				<span><?php esc_html_e( 'افزودن به سبد', 'rezajordaan' ); ?></span>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 6.5 8.5 12 14 17.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<div class="rj-immersive-dock__chevrons" aria-hidden="true">›››</div>
			<a class="rj-immersive-dock__cart" href="<?php echo esc_url( $cart_url ); ?>" aria-label="<?php esc_attr_e( 'سبد خرید', 'rezajordaan' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h15l-1.5 9h-12z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M6 7 5 3H2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="10" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/></svg>
			</a>
		</div>
		<?php
	}

	public static function enqueue() {
		$css = self::css();
		$js  = self::js();

		wp_register_style( 'rj-immersive-product', false, array(), self::VERSION );
		wp_enqueue_style( 'rj-immersive-product' );
		wp_add_inline_style( 'rj-immersive-product', $css );

		wp_register_script( 'rj-immersive-product', false, array(), self::VERSION, true );
		wp_enqueue_script( 'rj-immersive-product' );
		wp_add_inline_script( 'rj-immersive-product', $js );
	}

	private static function css() {
		return <<<'CSS'
/* RJ Immersive single product — mobile only. Desktop keeps theme layout. */
@media (max-width: 900px) {
	body.rj-immersive-product {
		--rj-imm-ink: #111111;
		--rj-imm-muted: #8b8b8b;
		--rj-imm-glass: rgba(255, 255, 255, 0.42);
		--rj-imm-card: #ffffff;
		--rj-imm-dock-h: 78px;
	}

	body.rj-immersive-product .site-header,
	body.rj-immersive-product .site-footer,
	body.rj-immersive-product .woocommerce-breadcrumb,
	body.rj-immersive-product .single-product-heading,
	body.rj-immersive-product .woocommerce-tabs,
	body.rj-immersive-product .related.products,
	body.rj-immersive-product .upsells.products {
		display: none !important;
	}

	body.rj-immersive-product .content-page.rj-section,
	body.rj-immersive-product .woocommerce-page {
		padding: 0 !important;
		margin: 0 !important;
	}

	body.rj-immersive-product .rj-container.content-page__inner,
	body.rj-immersive-product .woocommerce,
	body.rj-immersive-product .woocommerce-page .rj-container {
		max-width: none !important;
		padding: 0 !important;
		width: 100% !important;
	}

	body.rj-immersive-product .woocommerce div.product {
		display: block !important;
		position: relative;
		min-height: 100svh;
		padding: 0 !important;
		margin: 0 !important;
		background:
			radial-gradient(120% 80% at 50% 18%, rgba(255, 255, 255, 0.28), transparent 58%),
			linear-gradient(180deg, #f4f1ec 0%, #ebe4db 42%, #e7ddd2 100%);
	}

	body.rj-immersive-product .woocommerce div.product div.images {
		position: relative;
		z-index: 1;
		float: none !important;
		width: 100% !important;
		margin: 0 !important;
		padding: 0 !important;
		border: 0 !important;
		border-radius: 0 !important;
		box-shadow: none !important;
		background: transparent !important;
		overflow: visible !important;
		min-height: 62svh;
	}

	body.rj-immersive-product .woocommerce-product-gallery {
		margin: 0 !important;
	}

	body.rj-immersive-product .woocommerce-product-gallery__wrapper {
		display: block !important;
		overflow: visible !important;
	}

	body.rj-immersive-product .woocommerce-product-gallery__image,
	body.rj-immersive-product .woocommerce-product-gallery__image:first-child {
		flex: none !important;
		width: 100% !important;
	}

	body.rj-immersive-product .woocommerce-product-gallery__image img,
	body.rj-immersive-product .flex-viewport .woocommerce-product-gallery__image img {
		display: block;
		width: min(78%, 340px) !important;
		max-width: 340px;
		height: min(58svh, 460px) !important;
		margin: 72px auto 0 !important;
		object-fit: contain !important;
		border-radius: 0 !important;
		filter: drop-shadow(0 28px 40px rgba(20, 12, 8, 0.28));
		background: transparent !important;
	}

	body.rj-immersive-product .flex-control-thumbs,
	body.rj-immersive-product ol.flex-control-thumbs {
		display: none !important;
	}

	body.rj-immersive-product .woocommerce div.product div.summary {
		position: relative;
		z-index: 4;
		float: none !important;
		width: calc(100% - 28px) !important;
		margin: -42px 14px 0 !important;
		padding: 0 !important;
		border: 0 !important;
		border-radius: 0 !important;
		box-shadow: none !important;
		background: transparent !important;
		padding-bottom: calc(var(--rj-imm-dock-h) + 28px) !important;
	}

	body.rj-immersive-product .single-product-purchase {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto;
		align-items: center;
		column-gap: 14px;
		row-gap: 4px;
		background: var(--rj-imm-card);
		border-radius: 28px;
		padding: 22px 18px 20px;
		box-shadow: 0 18px 50px rgba(17, 17, 17, 0.14);
		border: 0;
		margin: 0;
	}

	body.rj-immersive-product .single-product-purchase .product_title {
		grid-column: 1;
		grid-row: 1;
		margin: 0;
		font-size: clamp(26px, 7vw, 34px);
		line-height: 1.15;
		font-weight: 800;
		color: var(--rj-imm-ink);
		letter-spacing: -0.02em;
	}

	body.rj-immersive-product .rj-immersive__meta {
		grid-column: 1;
		grid-row: 2;
		margin: 0;
		color: var(--rj-imm-muted);
		font-size: 13px;
		font-weight: 600;
	}

	body.rj-immersive-product .single-product-purchase p.price {
		grid-column: 1;
		grid-row: 3;
		margin: 10px 0 0;
		color: var(--rj-imm-ink);
		font-size: clamp(24px, 6vw, 30px);
		font-weight: 800;
	}

	body.rj-immersive-product .single-product-purchase p.price del {
		opacity: 0.45;
		font-size: 0.62em;
		margin-inline-end: 6px;
	}

	body.rj-immersive-product .single-product-purchase form.cart {
		grid-column: 2;
		grid-row: 1 / span 3;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		margin: 0;
		align-self: stretch;
		gap: 8px;
	}

	body.rj-immersive-product .single-product-purchase form.cart .variations {
		display: none; /* shown in block below card for room */
	}

	body.rj-immersive-product .rj-immersive-variations {
		margin-top: 14px;
		padding: 12px 14px;
		border-radius: 18px;
		background: rgba(255, 255, 255, 0.78);
		border: 1px solid rgba(0, 0, 0, 0.06);
	}

	body.rj-immersive-product .rj-immersive-qty {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: space-between;
		min-width: 54px;
		min-height: 118px;
		padding: 8px 0;
		border-radius: 18px;
		background: #f3f3f3;
	}

	body.rj-immersive-product .rj-immersive-qty__btn {
		appearance: none;
		border: 0;
		background: transparent;
		width: 40px;
		height: 36px;
		border-radius: 12px;
		font-size: 22px;
		line-height: 1;
		color: var(--rj-imm-ink);
		cursor: pointer;
	}

	body.rj-immersive-product .rj-immersive-qty__btn:active {
		background: rgba(0, 0, 0, 0.06);
	}

	body.rj-immersive-product .rj-immersive-qty .quantity {
		margin: 0;
	}

	body.rj-immersive-product .rj-immersive-qty .qty {
		width: 42px !important;
		min-height: 0 !important;
		border: 0 !important;
		background: transparent !important;
		box-shadow: none !important;
		text-align: center;
		font-size: 18px;
		font-weight: 800;
		color: var(--rj-imm-ink);
		padding: 0;
		-moz-appearance: textfield;
	}

	body.rj-immersive-product .rj-immersive-qty .qty::-webkit-outer-spin-button,
	body.rj-immersive-product .rj-immersive-qty .qty::-webkit-inner-spin-button {
		-webkit-appearance: none;
		margin: 0;
	}

	body.rj-immersive-product .single-product-purchase .single_add_to_cart_button {
		position: absolute !important;
		width: 1px !important;
		height: 1px !important;
		padding: 0 !important;
		margin: -1px !important;
		overflow: hidden !important;
		clip: rect(0, 0, 0, 0) !important;
		border: 0 !important;
	}

	body.rj-immersive-product .woocommerce-product-details__short-description,
	body.rj-immersive-product .product_meta {
		margin-top: 18px;
		padding: 0 4px;
		border: 0;
		color: #5c5650;
		font-size: 14px;
		line-height: 1.9;
	}

	/* Floating glass chrome */
	body.rj-immersive-product .rj-immersive-chrome {
		display: block;
		position: fixed;
		inset: 0 0 auto 0;
		z-index: 60;
		pointer-events: none;
		padding: calc(10px + env(safe-area-inset-top, 0px)) 14px 0;
	}

	body.rj-immersive-product .rj-immersive-fab {
		pointer-events: auto;
		position: relative;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 6px;
		min-width: 46px;
		height: 46px;
		padding: 0 14px;
		border: 0;
		border-radius: 999px;
		background: var(--rj-imm-glass);
		backdrop-filter: blur(14px);
		-webkit-backdrop-filter: blur(14px);
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
		color: var(--rj-imm-ink);
		text-decoration: none;
		cursor: pointer;
	}

	body.rj-immersive-product .rj-immersive-fab svg {
		width: 20px;
		height: 20px;
		display: block;
		fill: currentColor;
	}

	body.rj-immersive-product .rj-immersive-fab--back {
		position: absolute;
		top: calc(10px + env(safe-area-inset-top, 0px));
		right: 14px;
		font-size: 13px;
		font-weight: 700;
	}

	body.rj-immersive-product .rj-immersive-fab-stack {
		position: absolute;
		top: calc(10px + env(safe-area-inset-top, 0px));
		left: 14px;
		display: flex;
		flex-direction: column;
		gap: 10px;
		pointer-events: none;
	}

	body.rj-immersive-product .rj-immersive-fab-stack .rj-immersive-fab {
		pointer-events: auto;
		padding: 0;
		width: 46px;
	}

	body.rj-immersive-product .rj-immersive-fab__badge {
		position: absolute;
		top: -4px;
		left: -4px;
		min-width: 18px;
		height: 18px;
		padding: 0 5px;
		border-radius: 999px;
		background: #111;
		color: #fff;
		font-size: 10px;
		font-style: normal;
		font-weight: 800;
		line-height: 18px;
		text-align: center;
	}

	/* Bottom dock */
	body.rj-immersive-product .rj-immersive-dock {
		display: flex;
		align-items: center;
		gap: 12px;
		position: fixed;
		right: 14px;
		left: 14px;
		bottom: calc(14px + env(safe-area-inset-bottom, 0px));
		z-index: 70;
		height: var(--rj-imm-dock-h);
		padding: 10px;
		border-radius: 999px;
		background: rgba(255, 255, 255, 0.72);
		backdrop-filter: blur(16px);
		-webkit-backdrop-filter: blur(16px);
		box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
	}

	body.rj-immersive-product .rj-immersive-dock__cta {
		flex: 1;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		height: 54px;
		border: 0;
		border-radius: 999px;
		background: #111;
		color: #fff;
		font-size: 15px;
		font-weight: 800;
		cursor: pointer;
	}

	body.rj-immersive-product .rj-immersive-dock__cta svg {
		width: 18px;
		height: 18px;
		transform: scaleX(-1);
	}

	body.rj-immersive-product .rj-immersive-dock__chevrons {
		color: #c2c2c2;
		font-size: 20px;
		letter-spacing: -2px;
		padding-inline: 2px;
		user-select: none;
	}

	body.rj-immersive-product .rj-immersive-dock__cart {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 54px;
		height: 54px;
		border-radius: 50%;
		background: #111;
		color: #fff;
		text-decoration: none;
		flex: 0 0 auto;
	}

	body.rj-immersive-product .rj-immersive-dock__cart svg {
		width: 22px;
		height: 22px;
		fill: currentColor;
	}

	body.rj-immersive-product.admin-bar .rj-immersive-chrome,
	body.rj-immersive-product.admin-bar .rj-immersive-fab--back,
	body.rj-immersive-product.admin-bar .rj-immersive-fab-stack {
		top: 46px;
	}
}

@media (min-width: 901px) {
	body.rj-immersive-product .rj-immersive-chrome,
	body.rj-immersive-product .rj-immersive-dock {
		display: none !important;
	}
}
CSS;
	}

	private static function js() {
		return <<<'JS'
(function () {
	if (!document.body.classList.contains('rj-immersive-product')) return;
	if (!window.matchMedia('(max-width: 900px)').matches) return;

	function wrapQuantity(form) {
		var qtyWrap = form.querySelector('.quantity');
		if (!qtyWrap || qtyWrap.closest('.rj-immersive-qty')) return;

		var shell = document.createElement('div');
		shell.className = 'rj-immersive-qty';

		var plus = document.createElement('button');
		plus.type = 'button';
		plus.className = 'rj-immersive-qty__btn';
		plus.setAttribute('aria-label', 'افزایش تعداد');
		plus.textContent = '+';

		var minus = document.createElement('button');
		minus.type = 'button';
		minus.className = 'rj-immersive-qty__btn';
		minus.setAttribute('aria-label', 'کاهش تعداد');
		minus.textContent = '−';

		qtyWrap.parentNode.insertBefore(shell, qtyWrap);
		shell.appendChild(plus);
		shell.appendChild(qtyWrap);
		shell.appendChild(minus);

		function step(delta) {
			var input = qtyWrap.querySelector('.qty');
			if (!input) return;
			var min = parseFloat(input.getAttribute('min') || '1') || 1;
			var maxAttr = input.getAttribute('max');
			var max = maxAttr ? parseFloat(maxAttr) : Infinity;
			var val = parseFloat(input.value || min) || min;
			val = Math.min(max, Math.max(min, val + delta));
			input.value = String(val);
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}

		plus.addEventListener('click', function (e) { e.preventDefault(); step(1); });
		minus.addEventListener('click', function (e) { e.preventDefault(); step(-1); });
	}

	function relocateVariations() {
		var form = document.querySelector('.single-product-purchase form.cart');
		var table = form && form.querySelector('.variations');
		var box = document.querySelector('.single-product-purchase');
		if (!table || !box || document.querySelector('.rj-immersive-variations')) return;
		var host = document.createElement('div');
		host.className = 'rj-immersive-variations';
		box.insertAdjacentElement('afterend', host);
		host.appendChild(table);
	}

	function bindDock() {
		var dockBtn = document.querySelector('[data-rj-immersive-add]');
		if (!dockBtn) return;
		dockBtn.addEventListener('click', function (e) {
			e.preventDefault();
			var real = document.querySelector(
				'.single-product-purchase .single_add_to_cart_button, form.cart .single_add_to_cart_button'
			);
			if (real) real.click();
		});
	}

	function bindChromeScroll() {
		document.querySelectorAll('[data-rj-immersive-scroll]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var target = btn.getAttribute('data-rj-immersive-scroll');
				var el = target === 'gallery'
					? document.querySelector('.woocommerce-product-gallery')
					: document.querySelector('.single-product-purchase, .summary');
				if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		});
	}

	function boot() {
		document.querySelectorAll('form.cart').forEach(wrapQuantity);
		relocateVariations();
		bindDock();
		bindChromeScroll();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
JS;
	}
}

RJ_Immersive_Single_Product::init();
