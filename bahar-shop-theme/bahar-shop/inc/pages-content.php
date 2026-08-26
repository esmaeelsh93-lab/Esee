<?php
/**
 * Built-in content for policy and shipping pages.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'bahar_shop_ensure_info_pages', 20 );
add_filter( 'the_content', 'bahar_shop_inject_page_content', 12 );

/**
 * Info page definitions.
 *
 * @return array<string, array{title:string, slug:string, callback:string}>
 */
function bahar_shop_info_pages() {
	return array(
		'privacy-policy' => array(
			'title'    => 'سیاست حفظ حریم خصوصی',
			'slug'     => 'privacy-policy',
			'callback' => 'bahar_shop_privacy_content',
		),
		'shipping-info' => array(
			'title'    => 'نحوه ارسال',
			'slug'     => 'shipping-info',
			'callback' => 'bahar_shop_shipping_content',
		),
		'returns-policy' => array(
			'title'    => 'سیاست تعویض و مرجوعی کالا',
			'slug'     => 'returns-policy',
			'callback' => 'bahar_shop_returns_content',
		),
	);
}

/**
 * Create or fix policy/shipping pages.
 */
function bahar_shop_ensure_info_pages() {
	if ( get_option( 'bahar_shop_info_pages_v3' ) ) {
		return;
	}

	foreach ( bahar_shop_info_pages() as $config ) {
		$page = get_page_by_path( $config['slug'] );

		if ( ! $page ) {
			$page = get_page_by_title( $config['title'] );
		}

		if ( $page ) {
			if ( $page->post_name !== $config['slug'] ) {
				wp_update_post(
					array(
						'ID'        => $page->ID,
						'post_name' => $config['slug'],
					)
				);
			}
			continue;
		}

		wp_insert_post(
			array(
				'post_title'   => $config['title'],
				'post_name'    => $config['slug'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			)
		);
	}

	update_option( 'bahar_shop_info_pages_v3', 1, false );
	flush_rewrite_rules( false );
}

/**
 * Get URL for built-in info page.
 *
 * @param string $slug Page slug.
 * @return string
 */
function bahar_shop_info_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		return get_permalink( $page );
	}

	return home_url( '/' . $slug . '/' );
}

/**
 * Inject theme content into info pages.
 *
 * @param string $content Post content.
 * @return string
 */
function bahar_shop_inject_page_content( $content ) {
	if ( ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$slug = get_post_field( 'post_name', get_the_ID() );
	$map  = bahar_shop_info_pages();

	if ( ! isset( $map[ $slug ] ) ) {
		$title_map = array(
			'سیاست حفظ حریم خصوصی'     => 'privacy-policy',
			'نحوه ارسال'               => 'shipping-info',
			'سیاست تعویض و مرجوعی کالا' => 'returns-policy',
		);
		$title = get_the_title();
		if ( isset( $title_map[ $title ] ) ) {
			$slug = $title_map[ $title ];
		} else {
			return $content;
		}
	}

	$callback = $map[ $slug ]['callback'];
	if ( is_callable( $callback ) ) {
		return call_user_func( $callback );
	}

	return $content;
}

/**
 * Privacy policy HTML.
 *
 * @return string
 */
function bahar_shop_privacy_content() {
	ob_start();
	?>
	<div class="bahar-page-content">
		<p class="bahar-page-lead">در <strong>بهار شاپ</strong> حریم خصوصی شما برایمان مهم است. این صفحه توضیح می‌دهد چه اطلاعاتی جمع‌آوری می‌شود و چگونه از آن محافظت می‌کنیم.</p>
		<ul class="bahar-page-list">
			<li><strong>اطلاعات سفارش:</strong> نام، شماره تماس، آدرس و جزئیات خرید فقط برای پردازش سفارش، ارسال کالا و پشتیبانی استفاده می‌شود.</li>
			<li><strong>اطلاعات پرداخت:</strong> پرداخت‌ها از طریق درگاه امن <strong>زرین‌پال</strong> انجام می‌شود. اطلاعات کارت بانکی در سایت ما ذخیره نمی‌شود.</li>
			<li><strong>کوکی و کش:</strong> برای سرعت سایت و تجربه بهتر خرید از کوکی و ابزارهای کش (مثل WP Rocket) استفاده می‌شود.</li>
			<li><strong>عدم اشتراک‌گذاری:</strong> اطلاعات شخصی شما به اشخاص ثالث فروخته یا واگذار نمی‌شود؛ مگر برای ارسال مرسوله با شرکت‌های پستی و پیک.</li>
			<li><strong>امنیت:</strong> دسترسی به پنل مدیریت و داده‌های سفارش محدود به تیم فروشگاه است.</li>
			<li><strong>حذف اطلاعات:</strong> برای مشاهده، اصلاح یا درخواست حذف اطلاعات با شماره <a href="tel:+989035233046">۰۹۰۳۵۲۳۳۰۴۶</a> تماس بگیرید.</li>
		</ul>
		<p class="bahar-page-note">با ادامه خرید از بهار شاپ، شما با این سیاست موافقت می‌کنید. در صورت تغییر قوانین، این صفحه به‌روزرسانی می‌شود.</p>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Shipping info HTML.
 *
 * @return string
 */
function bahar_shop_shipping_content() {
	ob_start();
	?>
	<div class="bahar-page-content">
		<p class="bahar-page-lead">سفارش‌های بهار شاپ با روش‌های زیر ارسال می‌شوند. قبل از ارسال، هماهنگی تلفنی انجام می‌شود تا بهترین روش برای شما انتخاب شود.</p>
		<ul class="bahar-page-list">
			<li><strong>پست پیشتاز و عادی:</strong> ارسال سراسر کشور با کد رهگیری پستی.</li>
			<li><strong>تیپاکس:</strong> ارسال سریع به شهرهای دارای نمایندگی.</li>
			<li><strong>چاپار:</strong> ارسال اکسپرس با رهگیری آنلاین.</li>
			<li><strong>اسنپ و پیک:</strong> برای شهر کرج و اطراف، با <strong>هماهنگی قبلی</strong> قابل انجام است.</li>
			<li><strong>تحویل حضوری:</strong> تحویل از فروشگاه جردن کرج با هماهنگی تلفنی امکان‌پذیر است.</li>
			<li><strong>کد رهگیری:</strong> پس از ارسال، کد رهگیری برای شما ارسال می‌شود. اگر پیامک یا پیام دریافت نکردید، به شماره <a href="tel:+989035233046">۰۹۰۳۵۲۳۳۰۴۶</a> پیام دهید تا سریع پیگیری کنیم.</li>
			<li><strong>بسته‌بندی:</strong> تمام سفارش‌ها با بسته‌بندی تمیز و مطمئن ارسال می‌شوند.</li>
		</ul>
		<p class="bahar-page-note">آدرس فروشگاه: استان البرز – کرج – بلوار بهشتی، بعد از خیابان ۴۵ متری ولیعصر (بازرگانی)، بعد از چهارراه دوم — فروشگاه جردن</p>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Returns & exchange policy HTML.
 *
 * @return string
 */
function bahar_shop_returns_content() {
	ob_start();
	?>
	<div class="bahar-page-content">
		<p class="bahar-page-lead">در <strong>بهار شاپ</strong> هدف ما رضایت کامل شماست و تلاش می‌کنیم بهترین کیفیت را به دستتان برسانیم. لطفاً پیش از ثبت سفارش، قوانین زیر را با دقت بخوانید.</p>

		<h2 class="bahar-page-title">۱) مرجوعی نداریم</h2>
		<ul class="bahar-page-list">
			<li><strong>بدون مرجوعی:</strong> تمام مشخصات هر کالا شامل <strong>سایز، اندازه و سانت</strong> به‌طور کامل در صفحه‌ی همان محصول نوشته شده است؛ بنابراین خرید بر اساس همین اطلاعات انجام می‌شود و کالا مرجوع نمی‌گردد.</li>
			<li><strong>مسئولیت انتخاب با خریدار است:</strong> لطفاً هنگام سفارش، سایز و اندازه را با دقت بررسی کنید و در صورت هرگونه ابهام، پیش از خرید با ما در تماس باشید تا راهنمایی‌تان کنیم.</li>
		</ul>

		<h2 class="bahar-page-title">۲) کنترل کیفیت پیش از ارسال</h2>
		<ul class="bahar-page-list">
			<li>همه‌ی محصولات <strong>پیش از ارسال از نظر کیفیت و سلامت بررسی می‌شوند</strong> و در صورت وجود ایراد، اصلاً ارسال نمی‌شوند.</li>
			<li>بسته‌بندی هر سفارش با دقت انجام می‌شود تا کالا سالم و تمیز به دست شما برسد.</li>
		</ul>

		<h2 class="bahar-page-title">۳) شرایط تعویض کالا</h2>
		<ul class="bahar-page-list">
			<li><strong>تعویض به‌جای مرجوع:</strong> اگر پس از دریافت، خرابی یا ایرادی در کالا دیدید، با شماره‌ی <a href="tel:+989035233046">۰۹۰۳۵۲۳۳۰۴۶</a> هماهنگ کنید. در صورتی که ایراد منطقی و مورد تأیید باشد، کالا برایتان <strong>تعویض</strong> می‌شود (نه مرجوع).</li>
			<li><strong>اعلام به‌موقع:</strong> لطفاً ایراد را در اولین فرصت و حداکثر تا <strong>۲ روز پس از دریافت</strong> اطلاع دهید و از استفاده یا شست‌وشوی کالای ایراددار خودداری کنید تا امکان تعویض وجود داشته باشد.</li>
		</ul>

		<h2 class="bahar-page-title">۴) هزینه‌ی ارسال در تعویض</h2>
		<ul class="bahar-page-list">
			<li><strong>اگر مشکل از سمت خریدار باشد</strong> (مثلاً انتخاب سایز یا مدل اشتباه): هزینه‌ی ارسال رفت و برگشت کالا بر عهده‌ی <strong>خریدار</strong> است.</li>
			<li><strong>اگر مشکل از سمت ما یا تأمین‌کننده باشد</strong> (مثلاً ایراد کیفی یا ارسال اشتباه): تمام هزینه‌های ارسال رفت و برگشت بر عهده‌ی <strong>بهار شاپ</strong> است.</li>
		</ul>

		<p class="bahar-page-note">برای هماهنگی تعویض یا هر پرسشی درباره‌ی سفارش، با شماره‌ی <a href="tel:+989035233046">۰۹۰۳۵۲۳۳۰۴۶</a> تماس بگیرید یا در واتساپ پیام دهید. ما کنار شما هستیم تا خریدی مطمئن و خاطره‌ای خوب داشته باشید. 🩷</p>
	</div>
	<?php
	return ob_get_clean();
}
