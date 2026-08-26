<?php
/**
 * Why Us pre-footer strip.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render «چرا ما» icons before footer.
 */
function bahar_shop_render_why_us() {
	$items = array(
		array(
			'icon'  => 'truck',
			'title' => __( 'ارسال سریع', 'bahar-shop' ),
			'text'  => __( 'ارسال ۲ تا ۴ روزه به سراسر کشور', 'bahar-shop' ),
		),
		array(
			'icon'  => 'shield-check',
			'title' => __( 'خرید امن', 'bahar-shop' ),
			'text'  => __( 'پرداخت مطمئن و نماد اعتماد', 'bahar-shop' ),
		),
		array(
			'icon'  => 'rotate-ccw',
			'title' => __( 'ضمانت تعویض', 'bahar-shop' ),
			'text'  => __( 'امکان تعویض آسان طبق قوانین', 'bahar-shop' ),
		),
		array(
			'icon'  => 'shirt',
			'title' => __( 'تنوع بالا', 'bahar-shop' ),
			'text'  => __( 'پوشاک دخترانه روزمره و ترند', 'bahar-shop' ),
		),
		array(
			'icon'  => 'sparkles',
			'title' => __( 'کیفیت انتخابی', 'bahar-shop' ),
			'text'  => __( 'انتخاب با وسواس برای استایل شما', 'bahar-shop' ),
		),
		array(
			'icon'  => 'message-circle-heart',
			'title' => __( 'پشتیبانی دوستانه', 'bahar-shop' ),
			'text'  => __( 'همراهی قبل و بعد از خرید', 'bahar-shop' ),
		),
	);
	?>
	<section class="bahar-why-us" aria-labelledby="bahar-why-us-title">
		<div class="container">
			<header class="bahar-why-us__head">
				<h2 id="bahar-why-us-title"><?php esc_html_e( 'چرا ما', 'bahar-shop' ); ?></h2>
				<p><?php esc_html_e( 'دلایلی که خرید از بهار شاپ را ساده و مطمئن می‌کند', 'bahar-shop' ); ?></p>
			</header>
			<ul class="bahar-why-us__grid">
				<?php foreach ( $items as $item ) : ?>
					<li class="bahar-why-us__item">
						<span class="bahar-why-us__icon" aria-hidden="true">
							<?php
							if ( function_exists( 'bahar_shop_the_icon' ) ) {
								bahar_shop_the_icon( $item['icon'] );
							}
							?>
						</span>
						<h3><?php echo esc_html( $item['title'] ); ?></h3>
						<p><?php echo esc_html( $item['text'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php
}
