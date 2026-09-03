<?php
/**
 * Education tab with accordion redirect guides.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$accordion_items = array(
	array(
		'id'    => 'migrate-seo',
		'icon'  => 'arrow-left-right',
		'color' => 'blue',
		'title' => __( 'مهاجرت از Rank Math / Yoast / AIOSEO', 'shojaei-seo-for-woo' ),
		'open'  => true,
		'content' => '
			<p>' . esc_html__( 'از تب «مهاجرت» می‌توانید متای عنوان، توضیح، کنونیکال، کلمه کلیدی و ریدایرکت‌ها را دسته‌ای به Damavand منتقل کنید.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'مهاجرت کامل، فقط متا، یا فقط ریدایرکت', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'دسته‌های ۱۰۰تایی برای فشار کمتر روی هاست', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'کلیدهای مقصد: _damavand_seo_* — نبض سئو اول همین‌ها را می‌خواند', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'قبل از حذف افزونه قبلی، یک‌بار مهاجرت کامل را اجرا و در تب عملیات چند محصول را تست کنید.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'damavand-meta',
		'icon'  => 'tags',
		'color' => 'green',
		'title' => __( 'متای Damavand-first چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'اولویت خواندن متا: Damavand → Rank Math → Yoast → AIOSEO → پیش‌فرض وردپرس.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'اگر افزونه رقیب فعال باشد، معمولاً مالکیت title/desc با اوست مگر مهاجرت کرده باشید', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'پس از مهاجرت، فرانت و نبض سئو اول کلیدهای Damavand را می‌بینند', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
		',
	),
	array(
		'id'    => 'analytics-hub',
		'icon'  => 'sparkles',
		'color' => 'purple',
		'title' => __( 'آمار پیشرفته، GA4 و Search Console', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'در هسته سئو → ماژول Advanced Analytics می‌توانید Measurement ID گای‌فور را بگذارید و از GSC برای نقشه سایت و Search Analytics استفاده کنید.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'ارسال/زمان‌بندی sitemap به Search Console', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'پیشنهاد کلمه کلیدی از Google Suggest (با محدودیت نرخ)', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'اگر OpenSSL یا پیش‌نیازها نباشد، ماژول Passive/غیرفعال نرم می‌شود نه fatal', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
		',
	),
	array(
		'id'    => 'seo-core-modules',
		'icon'  => 'network',
		'color' => 'blue',
		'title' => __( 'هسته سئو و ماژول‌ها', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'تب هسته سئو: مانیتور ۴۰۴، ریدایرکت، لینک، robots، canonical، schema، sitemap، IndexNow، نبض و آمار پیشرفته.', 'shojaei-seo-for-woo' ) . '</p>
			<p class="shojaei-edu-tip">' . esc_html__( 'حالت Passive یعنی داشبورد فعال است ولی خروجی رقابتی موازی (مثل XML تکراری) صادر نمی‌شود تا با Rank Math تداخل نکند.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'security-saas',
		'icon'  => 'shield',
		'color' => 'orange',
		'title' => __( 'امنیت و پوسته SaaS', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<ul>
				<li>' . esc_html__( 'محافظت SSRF برای اسکن اسکیما و پروب لینک', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'آپلود GSC فقط برای مدیر، با کنترل حجم و نوع فایل', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'ریدایرکت امن (wp_safe_redirect) و محدودیت نرخ پیشنهاد کلمه', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'رابط ادمین فشرده با آیکون‌های Lucide — بدون فونت آیکون پولی', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
		',
	),
	array(
		'id'    => 'gsc-connect',
		'icon'  => 'bar-chart-3',
		'color' => 'blue',
		'title' => __( 'آموزش اتصال به سرچ‌کنسول', 'shojaei-seo-for-woo' ),
		'open'  => true,
		'content' => '
			<p>' . esc_html__( 'اگر فروشگاه ایرانی دارید و می‌خواهید درخواست ایندکس از افزونه برود، این مسیر را یک‌بار از اول تا آخر طی کنید. عجله نکنید؛ بیشتر خطاها از جا‌به‌جا نوشتن آدرس سایت یا بسته بودن خروجی سرور است.', 'shojaei-seo-for-woo' ) . '</p>
			<ol>
				<li><strong>' . esc_html__( 'ورود به Google Cloud', 'shojaei-seo-for-woo' ) . '</strong> — ' . esc_html__( 'با همان جیمیل مالک سایت بروید console.cloud.google.com و یک پروژه بسازید (مثلاً نام فروشگاه خودتان).', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'روشن کردن دو سرویس', 'shojaei-seo-for-woo' ) . '</strong> — ' . esc_html__( 'از منوی APIs & Services → Library این دو را Enable کنید: Google Search Console API و Web Search Indexing API.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'ساخت Service Account', 'shojaei-seo-for-woo' ) . '</strong> — ' . esc_html__( 'IAM → Service Accounts → Create. یک نام ساده بگذارید. بعد Keys → Add key → JSON. فایل را امن نگه دارید.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'اضافه کردن به سرچ‌کنسول', 'shojaei-seo-for-woo' ) . '</strong> — ' . esc_html__( 'ایمیل داخل JSON (چیزی شبیه name@project.iam.gserviceaccount.com) را در Search Console → Users با نقش Owner اضافه کنید. نقش Full کافی نیست.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'آدرس خاصیت را درست بنویسید', 'shojaei-seo-for-woo' ) . '</strong> — ' . esc_html__( 'در افزونه، فیلد خاصیت باید دقیقاً همان چیزی باشد که در سرچ‌کنسول می‌بینید؛ مثلاً https://yoursite.com/ یا sc-domain:yoursite.com. هرگز ایمیل اکانت را آنجا ننویسید.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'آپلود JSON و تست', 'shojaei-seo-for-woo' ) . '</strong> — ' . esc_html__( 'در تنظیمات افزونه JSON را آپلود کنید، «بررسی لایه‌ای» بزنید، بعد تست ایندکس.', 'shojaei-seo-for-woo' ) . '</li>
			</ol>
			<div class="shojaei-edu-example">
				<strong>' . esc_html__( 'اگر تست ایندکس قرمز شد ولی ورود سبز بود:', 'shojaei-seo-for-woo' ) . '</strong>
				' . esc_html__( 'معمولاً سرور هاست به indexing.googleapis.com راه ندارد. شکن روی ویندوز شما کمکی نمی‌کند؛ از پشتیبانی هاست بخواهید خروجی سرور به گوگل باز شود یا DNS سرور را درست کنند. تا آن موقع IndexNow را روشن نگه دارید.', 'shojaei-seo-for-woo' ) . '
			</div>
			<p class="shojaei-edu-tip">' . esc_html__( 'متن آماده برای هاست: «لطفاً دسترسی خروجی سرور وردپرس به indexing.googleapis.com و oauth2.googleapis.com را بررسی کنید؛ افزونه از PHP به این آدرس‌ها درخواست می‌زند.»', 'shojaei-seo-for-woo' ) . '</p>
			<p><strong>' . esc_html__( 'از نسخه ۱.۱۱:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'Canonical محصولات متغیر به‌صورت پیش‌فرض فعال است — گوگل حالت‌های رنگ/سایز را جدا ایندکس نمی‌کند و به صفحه والد ارجاع می‌دهد.', 'shojaei-seo-for-woo' ) . '</p>
			<p><strong>' . esc_html__( 'از نسخه ۱.۱۲:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'نامک فینگلیش برای محصولات جدید، هشدار تغییر نامک، ریدایرکت ۳۰۱ خودکار، و بلوک محصولات مکمل برای عمق خزش.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'slug-tools',
		'icon'  => 'code-2',
		'color' => 'green',
		'title' => __( 'نامک محصول و ریدایرکت اسلاگ', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<ul>
				<li><strong>' . esc_html__( 'محصولات جدید:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'عنوان فارسی می‌ماند؛ نامک به‌صورت فینگلیش لاتین ساخته می‌شود (مثلاً kafsh-nike).', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'محصولات قدیمی:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'انبوه‌بازنویسی نمی‌شوند. فقط اگر خودتان نامک را عوض کنید، ۳۰۱ ذخیره می‌شود.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'هشدار ویرایشگر:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'در سایدبار محصول امتیاز خوانایی و هشدار ۴۰۴ می‌بینید.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'اگر افزونه ریدایرکت را خاموش کرده‌اید، قبل از تغییر نامک محصول پرفروش، دستی ۳۰۱ بگذارید.', 'shojaei-seo-for-woo' ) . '</p>
			<p><strong>' . esc_html__( 'از نسخه ۱.۱۵:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'سلامت نامک با فیلتر، Dry-Run، اعمال گروهی، Undo و حذف ۴۱۰ از پیشنهادها.', 'shojaei-seo-for-woo' ) . '</p>
			<p><strong>' . esc_html__( 'از نسخه ۱.۱۷:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'پیشنهاد نامک زنده در ویرایشگر + اسکن کامل کاتالوگ.', 'shojaei-seo-for-woo' ) . '</p>
			<p class="shojaei-edu-tip">' . esc_html__( 'غیرفعال‌سازی افزونه = ریدایرکت‌ها موقتاً اجرا نمی‌شوند (داده می‌ماند). حذف افزونه به‌صورت پیش‌فرض داده را پاک نمی‌کند مگر در تنظیمات → پیشرفته گزینه پاک‌سازی را روشن کرده باشید.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'product-direction',
		'icon'  => 'network',
		'color' => 'blue',
		'title' => __( 'این افزونه چیست؟ (جهت‌گیری محصول)', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p><strong>' . esc_html__( 'SEO Operations برای ووکامرس', 'shojaei-seo-for-woo' ) . '</strong> — ' . esc_html__( 'مدیریت رفتار سئو در طول چرخه عمر محصول، نه تنظیم پراکنده متا.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li><strong>' . esc_html__( 'هسته مزیت:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'Inventory-aware SEO — تصمیم بر اساس موجودی، مدت ناموجودی، مشابه‌ها، دسته و رفتار صفحه.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'چیزی که نیست:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'نسخه ضعیف‌تر Yoast یا Rank Math با چند گزینه متای پراکنده.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'چیزی که هست:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'لایه عملیاتی روی WooCommerce برای وضعیت صفحات محصول، ریدایرکت، لینک‌سازی و بازیابی.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'Rank Math/Yoast را برای متا نگه دارید؛ این افزونه جریان تصمیم موجودی و بازیابی را مدیریت می‌کند.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'rule-engine',
		'icon'  => 'route',
		'color' => 'purple',
		'title' => __( 'موتور قوانین (Rule Engine) چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'به‌جای شرط‌های پراکنده در فایل‌های مختلف، یک لایه مرکزی ورودی می‌گیرد و خروجی تصمیم می‌دهد.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li><strong>' . esc_html__( 'ورودی:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'موجودی، روزهای ناموجودی، جایگزین، Page Value، ایندکس، ریدایرکت قبلی، دسته/برند.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'خروجی:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'حفظ صفحه، پیشنهاد جایگزین، کاهش اولویت لینک، noindex، کاندید ریدایرکت، Dry-Run یا اعمال، لاگ/Undo.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<div class="shojaei-edu-example">
				<strong>' . esc_html__( 'مثال:', 'shojaei-seo-for-woo' ) . '</strong>
				' . esc_html__( 'اگر ناموجود و کمتر از آستانه پیام باشد → ایندکس حفظ + پیشنهاد جایگزین. اگر ≥ آستانه دائم و جایگزین باشد → کاندید ریدایرکت.', 'shojaei-seo-for-woo' ) . '
			</div>
			<p class="shojaei-edu-tip">' . esc_html__( 'در تب «تست محصول» ردپای قوانین را برای هر محصول ببینید.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'event-driven',
		'icon'  => 'refresh-cw',
		'color' => 'blue',
		'title' => __( 'حالت رویدادمحور چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'به‌جای اسکن‌های سنگین و تکراری، وقتی اتفاق مهمی می‌افتد Rule Engine اجرا می‌شود.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'تغییر موجودی محصول', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'تغییر قیمت یا وضعیت انتشار', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'انتشار / حذف / انتقال به زباله‌دان', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'ویرایش دسته، برچسب یا برند', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'به‌روزرسانی محصول جایگزین (حداکثر چند هم‌دسته، نه کل کاتالوگ)', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'جاب روزانه فقط روزشمار و عبور از آستانه‌ها را سبک همگام می‌کند — نه جستجوی سنگین جایگزین برای همه.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'job-queue',
		'icon'  => 'database',
		'color' => 'green',
		'title' => __( 'صف Job اختصاصی چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'WP-Cron به‌تنهایی برای Bulk و کاتالوگ بزرگ کافی نیست (وابسته به ترافیک، تایم‌اوت، کنترل ضعیف retry).', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'هر عملیات Bulk یک Job در جدول اختصاصی است', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'وضعیت: pending / running / done / failed', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'batch کوچک + retry محدود و قابل‌ردیابی', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'بدون Redis یا سرویس پولی — Ajax/REST + cron داخلی + Action Scheduler در صورت وجود', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
		',
	),
	array(
		'id'    => 'real-undo',
		'icon'  => 'undo-2',
		'color' => 'orange',
		'title' => __( 'Undo واقعی چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'اگر افزونه روی URL، indexability، لینک داخلی یا ریدایرکت اثر بگذارد، باید بازگردانی دقیق داشته باشد.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'هر عملیات با batch_id ثبت می‌شود', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'قبل و بعد هر تغییر ذخیره می‌شود', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'بازگردانی تکی و دسته‌ای + پیش‌نمایش قبل از Undo', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'ریدایرکت، noindex، لینک‌سازی، حذف از sitemap — همه Undo دارند', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'قابلیتی که Undo ندارد نباید در عملیات انبوه به‌صورت خودکار و بی‌قید فعال شود.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'smart-internal-links',
		'icon'  => 'link-2',
		'color' => 'blue',
		'title' => __( 'لینک‌سازی داخلی محدود و کنترل‌شده', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'لینک داخلی بدون سقف و منطق، سریع به اسپم داخلی تبدیل می‌شود. این افزونه بر اساس قوانین قابل تنظیم کار می‌کند.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'سقف سخت در هر صفحه + تراکم در ۱۰۰۰ کلمه + فاصله حداقل بین لینک‌ها', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'بدون لینک به صفحات noindex یا ریدایرکت‌شده', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'بدون تکرار همان anchor یا همان URL در یک صفحه', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'اولویت بر اساس دسته، برند، ویژگی، upsell و جایگزین واقعی', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'Whitelist / Blacklist قابل کنترل + حالت انحصاری', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'قوانین سبک برای فروشگاه ایرانی سریع و قابل‌کنترل‌اند — تنظیمات در تب تنظیمات → لینک‌ساز.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'seo-integration',
		'icon'  => 'plug',
		'color' => 'purple',
		'title' => __( 'همزیستی با Yoast و Rank Math', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'افزونه وارد جنگ مستقیم با Yoast یا Rank Math نمی‌شود. سیاست یکپارچگی روشن است:', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li><strong>' . esc_html__( 'Meta Title/Description:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'غیرفعال پیش‌فرض — مالکیت با افزونه SEO', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'Schema Product:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'با تشخیص افزونه SEO واگذار می‌شود؛ خروجی تکراری ممنوع', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'Redirect Logic:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'هسته این افزونه — مزیت رقابتی', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'Out-of-stock SEO:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'هسته اصلی محصول — Inventory-aware', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'جدول نقش‌ها را در تنظیمات → یکپارچگی ببینید. Schema Detector تداخل موازی را هشدار می‌دهد.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'impact-stats',
		'icon'  => 'chart-pie',
		'color' => 'green',
		'title' => __( 'اثر و آمار و سلامت عملیات', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'تب اثر و آمار نشان می‌دهد افزونه واقعاً چه کرده — نه ادعاهای مبهم.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'شمارش ریدایرکت ۳۰۱ / ۳۰۲ / ۴۱۰، noindex، لینک داخلی، درخواست GSC', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'نمودار روند ۳۰ روزه و دونات تفکیک ریدایرکت', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'درصد سلامت عملیات قبل از نصب → الان (رتبه گوگل نیست)', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'پروفایل فروشگاهی: عمومی، مد/فصلی، الکترونیک', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'اگر Product اسکیما دوبل شد، بنر مدیر روی فرانت دکمه خاموش‌کردن اسکیمای ووکامرس می‌دهد.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'redirect-301',
		'icon'  => 'circle-check',
		'color' => 'green',
		'title' => __( 'ریدایرکت ۳۰۱ چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p><strong>' . esc_html__( 'ریدایرکت ۳۰۱ (Permanent Redirect)', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'یک انتقال دائمی از یک آدرس به آدرس دیگر است.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'به موتورهای جستجو می‌گوید: «این صفحه برای همیشه به آدرس جدید منتقل شده است.»', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'قدرت سئوی (Link Juice) صفحه قدیمی به صفحه جدید منتقل می‌شود.', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'گوگل پس از مدتی صفحه قدیمی را از ایندکس حذف و صفحه جدید را جایگزین می‌کند.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<div class="shojaei-edu-example">
				<strong>' . esc_html__( 'مثال:', 'shojaei-seo-for-woo' ) . '</strong>
				' . esc_html__( 'محصول «کفش ورزشی مدل آیرون» بیش از ۹۰ روز ناموجود بوده و محصول مشابه «کفش ورزشی مدل آیرون پرو» موجود است → ریدایرکت ۳۰۱ به محصول جدید.', 'shojaei-seo-for-woo' ) . '
			</div>
			<p class="shojaei-edu-tip">' . esc_html__( 'وقتی مطمئن هستید محصول دیگر برنمی‌گردد، از ۳۰۱ استفاده کنید.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'redirect-302',
		'icon'  => 'route',
		'color' => 'orange',
		'title' => __( 'ریدایرکت ۳۰۲ چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p><strong>' . esc_html__( 'ریدایرکت ۳۰۲ (Temporary Redirect)', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'یک انتقال موقت است.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'به موتورهای جستجو می‌گوید: «این صفحه موقتاً به جای دیگری رفته، اما برمی‌گردد.»', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'قدرت سئوی صفحه اصلی حفظ می‌شود و منتقل نمی‌شود.', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'گوگل همچنان صفحه اصلی را در ایندکس نگه می‌دارد.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<div class="shojaei-edu-example">
				<strong>' . esc_html__( 'مثال:', 'shojaei-seo-for-woo' ) . '</strong>
				' . esc_html__( 'محصول «ساعت هوشمند شیائومی» موقتاً ناموجود است ولی احتمال تأمین مجدد وجود دارد → ریدایرکت ۳۰۲ به دسته «ساعت هوشمند».', 'shojaei-seo-for-woo' ) . '
			</div>
			<p class="shojaei-edu-tip">' . esc_html__( 'وقتی امید دارید محصول دوباره موجود شود، از ۳۰۲ استفاده کنید.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'redirect-diff',
		'icon'  => 'circle-help',
		'color' => 'purple',
		'title' => __( 'تفاوت ۳۰۱ و ۳۰۲ در سئو؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<table class="shojaei-edu-table">
				<thead>
					<tr>
						<th>' . esc_html__( 'ویژگی', 'shojaei-seo-for-woo' ) . '</th>
						<th>' . esc_html__( 'ریدایرکت ۳۰۱', 'shojaei-seo-for-woo' ) . '</th>
						<th>' . esc_html__( 'ریدایرکت ۳۰۲', 'shojaei-seo-for-woo' ) . '</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>' . esc_html__( 'نوع', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'دائمی', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'موقت', 'shojaei-seo-for-woo' ) . '</td>
					</tr>
					<tr>
						<td>' . esc_html__( 'انتقال سئو', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'بله — کامل', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'خیر — حفظ می‌شود', 'shojaei-seo-for-woo' ) . '</td>
					</tr>
					<tr>
						<td>' . esc_html__( 'حذف از ایندکس', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'بله — پس از مدتی', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'خیر', 'shojaei-seo-for-woo' ) . '</td>
					</tr>
					<tr>
						<td>' . esc_html__( 'مناسب برای', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'محصول حذف‌شده / جایگزین شده', 'shojaei-seo-for-woo' ) . '</td>
						<td>' . esc_html__( 'محصول موقتاً ناموجود', 'shojaei-seo-for-woo' ) . '</td>
					</tr>
				</tbody>
			</table>
		',
	),
	array(
		'id'    => 'redirect-when-not',
		'icon'  => 'circle-x',
		'color' => 'red',
		'title' => __( 'چه زمانی ریدایرکت نکنیم؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<ul>
				<li><strong>' . esc_html__( 'محصول کمتر از ۶۰ روز ناموجود:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'صفحه را نگه دارید. گوگل هنوز آن را ایندکس دارد و ممکن است محصول برگردد.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'محصول پرفروش با بک‌لینک زیاد:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'حذف یا ریدایرکت نادرست باعث از دست رفتن ترافیک ارگانیک می‌شود.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'محصول فصلی:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'مثلاً لباس زمستانی — صفحه را نگه دارید تا فصل بعد برگردد.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>' . esc_html__( 'بدون مقصد مناسب:', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'اگر محصول یا دسته مشابهی وجود ندارد، ریدایرکت به صفحه اصلی اشتباه است.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip shojaei-edu-warn">' . esc_html__( 'در تب «محصولات ناموجود» می‌توانید با دکمه «نگهداری صفحه» از ریدایرکت خودکار جلوگیری کنید.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'redirect-auto',
		'icon'  => 'settings',
		'color' => 'blue',
		'title' => __( 'ریدایرکت خودکار سیستم چطور کار می‌کند؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'افزونه Shojaei SEO چرخه عمر محصولات ناموجود را به صورت خودکار مدیریت می‌کند:', 'shojaei-seo-for-woo' ) . '</p>
			<div class="shojaei-edu-phases">
				<div class="shojaei-edu-phase">
					<span class="shojaei-phase-num">۱</span>
					<div>
						<strong>' . esc_html__( 'روز ۱ تا ۳۰ — ناموجود تازه', 'shojaei-seo-for-woo' ) . '</strong>
						<p>' . esc_html__( 'صفحه فعال می‌ماند. دکمه خرید حذف و دکمه «مشاهده محصولات مشابه» نمایش داده می‌شود.', 'shojaei-seo-for-woo' ) . '</p>
					</div>
				</div>
				<div class="shojaei-edu-phase">
					<span class="shojaei-phase-num">۲</span>
					<div>
						<strong>' . esc_html__( 'روز ۳۱ تا ۶۰ — ناموجود میان‌مدت', 'shojaei-seo-for-woo' ) . '</strong>
						<p>' . esc_html__( 'پیام هشدار واقع‌گرایانه نمایش داده می‌شود و محصولات مشابه همان دسته پیشنهاد می‌شوند.', 'shojaei-seo-for-woo' ) . '</p>
					</div>
				</div>
				<div class="shojaei-edu-phase">
					<span class="shojaei-phase-num">۳</span>
					<div>
						<strong>' . esc_html__( 'روز ۶۱ تا ۹۰ — کاندید ریدایرکت', 'shojaei-seo-for-woo' ) . '</strong>
						<p>' . esc_html__( 'محصول در لیست کاندیداها قرار می‌گیرد. شما می‌توانید دستی ریدایرکت ۳۰۱، ۳۰۲ یا نگهداری صفحه را انتخاب کنید.', 'shojaei-seo-for-woo' ) . '</p>
					</div>
				</div>
				<div class="shojaei-edu-phase">
					<span class="shojaei-phase-num">۴</span>
					<div>
						<strong>' . esc_html__( 'بیش از ۹۰ روز — ریدایرکت خودکار', 'shojaei-seo-for-woo' ) . '</strong>
						<p>' . esc_html__( 'سیستم بهترین محصول مشابه (بر اساس شباهت عنوان) یا دسته اصلی را پیدا کرده و ریدایرکت ۳۰۱ اعمال می‌کند.', 'shojaei-seo-for-woo' ) . '</p>
					</div>
				</div>
			</div>
		',
	),
	array(
		'id'    => 'redirect-410',
		'icon'  => 'circle-x',
		'color' => 'red',
		'title' => __( '410 Gone چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p><strong>' . esc_html__( '410 Gone', 'shojaei-seo-for-woo' ) . '</strong> ' . esc_html__( 'یعنی «این صفحه برای همیشه حذف شده و دیگر برنمی‌گردد.»', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'برخلاف ۳۰۱، هیچ مقصدی وجود ندارد — صفحه واقعاً مرده است.', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'گوگل سریع‌تر از ایندکس حذفش می‌کند.', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'بهتر از ریدایرکت اشتباه به صفحه اصلی یا دسته نامرتبط.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<div class="shojaei-edu-example">
				<strong>' . esc_html__( 'کی استفاده کنیم؟', 'shojaei-seo-for-woo' ) . '</strong>
				' . esc_html__( 'محصولی که دیگر تولید نمی‌شود، جایگزین ندارد و برنمی‌گردد — مثلاً مدل قدیمی یک گوشی که دیگر عرضه نمی‌شود.', 'shojaei-seo-for-woo' ) . '
			</div>
			<p class="shojaei-edu-tip shojaei-edu-warn">' . esc_html__( '410 فقط دستی اعمال می‌شود — سیستم خودکار از 410 استفاده نمی‌کند.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'noindex-oos',
		'icon'  => 'eye',
		'color' => 'purple',
		'title' => __( 'noindex برای محصولات ناموجود چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'وقتی محصولی مدت زیادی ناموجود است، افزونه به‌صورت خودکار تگ noindex, follow اضافه می‌کند.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li><strong>noindex:</strong> ' . esc_html__( 'به گوگل می‌گوید این صفحه را در نتایج جستجو نشان نده.', 'shojaei-seo-for-woo' ) . '</li>
				<li><strong>follow:</strong> ' . esc_html__( 'لینک‌های داخل صفحه همچنان دنبال می‌شوند — ساختار سایت حفظ می‌شود.', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'این کار از فاز ۲ (پیش‌فرض) فعال می‌شود و در تنظیمات قابل تغییر است.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
	array(
		'id'    => 'status-ux',
		'icon'  => 'layout-dashboard',
		'color' => 'green',
		'title' => __( 'مرکز وضعیت چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => false,
		'content' => '
			<p>' . esc_html__( 'پنل تنظیمات‌محور نیست — اول وضعیت را می‌بینید، بعد اقدام.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'نیازمند اقدام / امن / هشدار / خطا', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'Action Cards با دکمه مشخص', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'Setup Wizard برای شروع با پیش‌فرض امن', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
		',
	),
	array(
		'id'    => 'dry-run',
		'icon'  => 'eye',
		'color' => 'blue',
		'title' => __( 'Dry-Run اعتمادساز چیست؟', 'shojaei-seo-for-woo' ),
		'open'  => true,
		'content' => '
			<p>' . esc_html__( 'Dry-Run فقط یک چک‌باکس نیست — پایه اعتماد برای تغییرات انبوه روی فروشگاه ایرانی است.', 'shojaei-seo-for-woo' ) . '</p>
			<ul>
				<li>' . esc_html__( 'تعداد آیتم‌های متاثر و مسدود', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'نوع تغییر و ریسک هر آیتم', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'خروجی CSV از همان پیش‌نمایش', 'shojaei-seo-for-woo' ) . '</li>
				<li>' . esc_html__( 'اجرای واقعی از روی همان گزارش (با Undo)', 'shojaei-seo-for-woo' ) . '</li>
			</ul>
			<p class="shojaei-edu-tip">' . esc_html__( 'از تب Dry-Run / Undo: پیش‌نمایش → CSV → اجرای واقعی. تا گزارش را ندیده‌اید چیزی اعمال نکنید.', 'shojaei-seo-for-woo' ) . '</p>
		',
	),
);
?>

<div class="shojaei-edu-banner">
	<span class="shojaei-edu-banner__ico" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'graduation-cap', 22 ); ?></span>
	<div>
		<h2><?php esc_html_e( 'راهنمای کار با افزونه', 'shojaei-seo-for-woo' ); ?></h2>
		<p><?php esc_html_e( 'از مهاجرت و متای Damavand تا هسته سئو، GA4/GSC، ریدایرکت و Dry-Run — برای فروشگاه ووکامرسی.', 'shojaei-seo-for-woo' ); ?></p>
	</div>
</div>

<div class="shojaei-accordion">
	<?php foreach ( $accordion_items as $item ) : ?>
		<div class="shojaei-accordion-item <?php echo $item['open'] ? 'is-open' : ''; ?>" data-accordion="<?php echo esc_attr( $item['id'] ); ?>">
			<button class="shojaei-accordion-header" type="button" aria-expanded="<?php echo $item['open'] ? 'true' : 'false'; ?>">
				<span class="shojaei-accordion-icon shojaei-icon-<?php echo esc_attr( $item['color'] ); ?>" aria-hidden="true">
					<?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( (string) $item['icon'], 16 ); ?>
				</span>
				<span class="shojaei-accordion-title"><?php echo esc_html( $item['title'] ); ?></span>
				<span class="shojaei-accordion-chevron" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'chevron-down', 16 ); ?></span>
			</button>
			<div class="shojaei-accordion-body" <?php echo $item['open'] ? 'style="display:block"' : ''; ?>>
				<div class="shojaei-accordion-content">
					<?php echo $item['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
