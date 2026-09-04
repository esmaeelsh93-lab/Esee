=== افزونه سئو حرفه‌ای دماوند (Damavand) ===
Contributors: shojaei
Tags: seo, woocommerce, redirect, inventory, out-of-stock, internal-links
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.59.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Third-party fonts: Vazirmatn (SIL Open Font License 1.1) by Saber Rastikerdar —
bundled self-hosted under assets/fonts/vazirmatn/ (see OFL.txt). https://github.com/rastikerdar/vazirmatn

لایه عملیاتی سئو برای ووکامرس ایرانی — Inventory-aware SEO، Schema، متا و crawl budget. توسعه: اسماعیل شجاعی.

== Description ==

Shojaei SEO for Woo روی **SEO Operations** تمرکز دارد: مدیریت رفتار سئو در چرخه عمر محصول ووکامرس — مخصوصاً وقتی موجودی، حذف، جایگزینی و ریدایرکت دائمی است.

**هسته مزیت رقابتی: Inventory-aware SEO**
تصمیم سئویی بر اساس وضعیت موجودی، طول مدت ناموجودی، مشابه‌ها، دسته‌بندی و رفتار صفحه — از طریق **Rule Engine** سبک و معماری **Event-driven**.

= چیزی که نیست =
نسخه ضعیف‌تر Yoast یا Rank Math با چند گزینه متای پراکنده و بدون جریان تصمیم.

= چیزی که هست =
یک لایه عملیاتی روی WooCommerce برای وضعیت صفحات محصول، ریدایرکت، لینک‌سازی و بازیابی.

قابلیت‌های عملیاتی:

* **Event-driven** — تصمیم با تغییر موجودی/قیمت/انتشار/دسته، نه اسکن کور
* **Rule Engine** — تصمیم مرکزی به‌جای if/else پراکنده
* **Job Queue اختصاصی** — جدول دیتابیس + batch + retry (بدون Redis/SaaS)
* **مرکز وضعیت** — Action Cards: نیازمند اقدام، پیشنهادی، خطا، Undo
* **Setup Wizard** — شروع ساده با پیش‌فرض‌های امن
* **Dry-Run اعتمادساز** — شمارش، ریسک، CSV، اجرای واقعی از پیش‌نمایش
* **Undo واقعی** — batch_id + قبل/بعد + پیش‌نمایش + Rollback دسته‌ای
* **عملیات موجودی** — چرخه چندسطحی ناموجودی تا تصمیم ریدایرکت/نگهداری/۴۱۰
* **پیام اختصاصی سه‌فاز** — متن و CSS جعبه ناموجودی قابل شخصی‌سازی
* **Page Value** — محافظت صفحات ارزشمند از ریدایرکت بی‌ملاحظه
* **موتور ریدایرکت** — ضد حلقه/زنجیره + امتیاز شباهت
* **لینک‌سازی بازیابی** — هدایت داخلی کنترل‌شده
* **نقشه سایت هوشمند** — دیباگ سلامت + GSC-ready
* **IndexNow و GSC** — بازیابی ایندکس پس از تغییرات ساختاری
* **اسکیما مکمل** — پشتیبان Rank Math، نه جایگزین آن

== Installation ==

1. فایل‌های افزونه را در `/wp-content/plugins/shojaei-seo-for-woo` آپلود کنید
2. افزونه را از منوی افزونه‌ها فعال کنید
3. به منوی Shojaei SEO در پیشخوان بروید

== Frequently Asked Questions ==

= آیا جایگزین Rank Math یا Yoast است؟ =
خیر. آن‌ها برای متا و محتوا؛ این افزونه برای عملیات موجودی، ریدایرکت و بازیابی است. می‌توانند کنار هم کار کنند.

= هسته محصول چیست؟ =
Inventory-aware SEO — تصمیم‌گیری سئو بر اساس موجودی و چرخه عمر محصول، از طریق Rule Engine رویدادمحور.

= واحد پولی چیست؟ =
از تنظیمات پیشرفته می‌توانید IRT/IRR/USD/EUR/AED را انتخاب کنید (پیش‌فرض تومان / IRT). این تنظیم برای اسکیما و برچسب‌های داخل افزونه است؛ ارز ووکامرس جدا می‌ماند.

= اتصال سرچ کنسول چگونه است؟ =
با آپلود فایل JSON مربوط به Service Account — بدون OAuth و بدون Refresh Token.

== Changelog ==

= 1.59.1 =
* Gemini: پیش‌فرض و لیست مدل‌ها به نسل 3.x (gemini-3.6-flash) — رفع 404 مدل‌های منسوخ 2.0/1.5
* مهاجرت خودکار gemini-2.0-flash و مدل‌های قدیمی هنگام فعال‌سازی/بارگذاری افزونه

= 1.59.0 =
* Provider Gemini: اتصال مستقیم به Gemini API (Google AI Studio) با Free Tier، مدل‌های قابل انتخاب، تست اتصال و پیام خطای فارسی
* Groq از UI حذف شد (مهاجرت خودکار به OpenRouter) — OpenRouter بدون تغییر رفتار
* کلید API فقط سمت سرور ذخیره می‌شود؛ لینک «دریافت API Key» برای Gemini

= 1.58.0 =
* صف Job: لینک «مشاهده صف Job» پنل عملکرد را باز می‌کند (نه سرور محتوا) + دکمه پاک‌سازی هشدار جاب ناموفق / تیک دستی / لغو گیرکرده
* فونت ادمین: Vazirmatn variable خودمیزبان (بدون CDN) جایگزین Tahoma + لایسنس OFL
* همگام‌سازی Stable tag با Version هدر افزونه (جلوگیری از گیج شدن آپدیت)
* حذف کامل Cross-Sell تسویه‌حساب و بلوک محصولات مکمل (خارج از حوزه SEO) + پاکسازی آپشن‌های یتیم
* حذف کامل جدول سایزبندی Damavand (فرانت/متاباکس/AI persist) + پاکسازی postmeta یتیم
* شکستن OOS Manager به Damavand_OOS_{Order_Lookup,Notifier,Detector,Admin} + facade
* شکستن Slug به Damavand_Slug_{Finglish,Redirects,Health,Editor} + facade
* admin-style.css: تبدیل left/right فیزیکی به logical properties (۱ مورد left:50% برای gauge باقی)
* مستند قوانین فینگلیش: docs/finglish-rules.md (منبع واحد؛ includes/data فقط رفرنس) + لینک از finglish-builtin-words.php
* Snapshot واقعی baharshop.ir (canonical/robots/schema) در docs/task-4-snapshot-baharshop.md
* یکپارچه‌سازی Canonical: Resolver واحد در seo-core (حذف Damavand_Canonical + Shojaei_SEO_Canonical موازی)
* Robots/Schema: مالکیت موازی مستند شد (robots.txt ≠ meta robots؛ Schema Generator ≠ seo-core gate)
* احترام به `_shojaei_seo_noindex` داخل Damavand_Robots::apply_to_robots
* اسکریپت snapshot هد SEO برای baseline/diff روی استیجینگ (`scripts/snapshot-seo-head.php`)
* Crawl budget: noindex برای سبد/تسویه/حساب/جستجو/۴۰۴/فیلتر و بایگانی نویسنده/تاریخ
* اعمال متای `_damavand_seo_robots` روی فرانت + کنترل دستی در متاباکس پیشرفته
* Schema روی صفحات noindex چاپ نمی‌شود؛ اسکیمای موازی ووکامرس وقتی Damavand مالک Product است سرکوب می‌شود
* Canonical pagination فروشگاه (`is_shop`) حفظ می‌شود
* ابزار ادمین: اعتبارسنج Schema محصول + اسکن عنوان/توضیح تکراری
* OOS noindex دیگر Rule Engine را روی فرانت صدا نمی‌زند (بهبود TTFB)
* Product Graph یکپارچه، Product.name واقعی، ItemList فقط روی آرشیو (از سری ۱.۵۷.x)

= 1.50.1 =
* رفع گیر کردن اسپینر ووکامرس هنگام bulk متغیر/ویژگی/قیمت — defer هوک موجودی + توقف AJAX امتیاز زنده

= 1.50.0 =
* پیشنهاد لینک هوشمندتر: حذف کلمات عمومی (کتونی، خرید)، شباهت توضیحات، کلمات مرتبط
* فیلد «کلمات مرتبط» فقط در ویرایش محصول — نرمال‌سازی، dedupe، حداکثر ۱۵ عبارت، اختیاری
* FAQ: برچسب محصول بدون «خرید» و بدون تکرار نوع کلی (کتونی)
* نبض سئو: نمایش عبارات مرتبط در گزارش محصول

= 1.49.1 =
* امنیت: اعتبارسنجی محصول (منتشرشده / قابل خرید / موجود) در AJAX افزودن به سبد checkout
* مهاجرت: کلیدهای صحیح WP Meta SEO (`_metaseo_*`) شامل OG/Twitter و robots
* آیکون‌های migrate UI (`layers`, `minimize-2`)
* یکسان‌سازی Stable tag و مستندات با نسخه افزونه

= 1.49.0 =
* آیکون رسمی افزونه در هدر و منوی ادمین
* FAQ: لینک/دکمه شرایط تعویض و مرجوعی (تشخیص خودکار + تنظیم دستی)
* مهاجرت گسترش‌یافته: SmartCrawl، WP Meta SEO، SEO Ultimate، Premium SEO Pack

= 1.36.0 =
* پیام و پیشنهاد ناموجود روی قالب‌های Elementor/سفارشی (چسبیدن به HTML موجودی ووکامرس)
* تنظیم جداگانه «تعداد پیشنهاد جایگزین ناموجود» (پیش‌فرض ۴) — جدا از محصولات مکمل

= 1.35.0 =
* نقشه سایت قوی‌تر برای همه فروشگاه‌ها: دسته/برچسب محصول جدا، گالری تصویر، lastmod دسته، صفحه خانه
* ثبت خودکار در robots.txt (جایگزین خط مرده sitemap_index.xml رنک‌مث) + alias /sitemap.xml
* تنظیمات روشن/خاموش نوع محتوا در هسته سئو

= 1.34.0 =
* پولیش امتیاز فارسی: تطبیق فینگلیش کلمه کلیدی با نامک، اولویت اقدام بعدی، گالری/دسته محصول، دعوت به اقدام در توضیح
* چک‌لیست آمادگی Rank Math گسترده‌تر + دکمه بررسی مجدد و لینک رفع
* متاباکس «موجودی و سئو» روی محصول + لینک دسته وقتی پیشنهاد جایگزین خالی است

= 1.33.0 =
* قالب عنوان/توضیح SERP برای محصول، نوشته و برگه (توکن‌های %title% %sep% %sitename% %excerpt% %focus% %sku% …)
* اعمال قالب از متاباکس سئو + تنظیم در «متای عمومی»

= 1.32.0 =
* متاباکس یکپارچه RTL سئو: عنوان، توضیح، کلمه کلیدی، امتیاز فارسی و پیش‌نمایش زنده SERP
* به‌روزرسانی فوری پیش‌نمایش گوگل هنگام تایپ + بازخورد AJAX امتیاز

= 1.31.9 =
* ویزارد ۴ مرحله‌ای فارسی: مهاجرت → فعال‌سازی Damavand → چک‌لیست خاموش کردن Rank Math
* دکمه فعال‌سازی یک‌جا برای متا/اسکیما/نقشه سایت/نامک فینگلیش
* گسترش stopwords نامک (حروف اضافه فارسی)

= 1.31.8 =
* مسیر جایگزینی: Open Graph + Twitter از عنوان/توضیح/تصویر Damavand (وقتی رقیب خاموش است)
* جلوگیری از دوبل پیشنهاد روی صفحه ناموجود (complementary در برابر بلوک OOS)
* پاک‌سازی کش JSON-LD محصول هنگام تغییر موجودی/وضعیت سئو

= 1.31.7 =
* هسته seo-core: لود نرم فایل‌های غایب (بدون سفید شدن سایت) + اعلان ادمین
* نامک: قبل از -2، مدل/رنگ/برند/SKU متمایزکننده اضافه می‌شود
* پکیج ZIP با ریشهٔ صحیح shojaei-seo-for-woo

= 1.31.6 =
* امنیت: جلوگیری از XSS با breakout در JSON-LD (`</script>`)
* امنیت: خنثی‌سازی CSV injection در خروجی اکسپورت
* یکدست‌سازی escape پیام‌ها در admin JS
* تنظیم واحد پولی در پیشخوان (پیشرفته)
* به‌روزرسانی readme و اسکلت PHPUnit در tests/

= 1.31.5 =
* پیام اختصاصی سه‌فاز + CSS اختصاصی جعبه ناموجودی

= 1.31.4 =
* سه فاز پیام ناموجودی از روی چرخه + ۵ پیشنهاد شباهت/دسته + خبرم کن (ایمیل)

= 1.31.3 =
* دیباگ / سلامت نقشه سایت (HTTP زنده، XML، robots، لاگ فالبک)

= 1.31.2 =
* رفع خطای rewrite null در نقشه سایت + فلاش امن

= 1.18.2 =
* Ops → سلامت ریدایرکت: Redirect Loop scan (A→B→A / A→A) + break-loop action

= 1.18.1 =
* Ops → سلامت ریدایرکت: Redirect Chain scan + flatten to final destination
* Clean install ZIP packaging notes (folder slug must be shojaei-seo-for-woo)

= 1.18.0 =
* Ops → سلامت ریدایرکت: Broken Redirect audit for active OOS + slug redirects
* Detect empty / missing / unpublished / trashed / 410 / unresolved internal targets
* One-click disable (slug) or undo (OOS) from the report

= 1.17.2 =
* Uninstall UX: radio keep (recommended) vs full wipe + save confirmation
* Plugins screen: confirm before Deactivate/Delete + link to safe uninstall policy
* Setup wizard: educate on deactivate vs delete data behavior

= 1.17.1 =
* Uninstall safety: by default KEEP redirects/tables/settings when plugin is deleted (opt-in full wipe in Settings → پیشرفته)
* Slug health: pagination (100/page) + lighter full-scan storage (cap 2000 rows)

= 1.17.0 =
* Product editor: live slug score + Finglish suggestion while typing title (no save needed)
* «اعمال روی نامک» button in metabox
* Full-catalog slug health scan (background job) on عملیات → نامک → سلامت

= 1.16.0 =
* Custom Finglish dictionary in Settings → نامک (فارسی = latin); overrides built-in words
* Used by new-product auto-slug and slug health suggestions

= 1.15.2 =
* Slug health: exclude products with active 410 Gone (list + apply blocked)
* Fix slug health display filters (persian / long / score < 50)
* Score readability help (lightbulb + explainer above the table)

= 1.15.1 =
* Fix: disabling slug metabox no longer disables existing 301 redirects
* Fix: health apply always creates 301 even if auto-301 setting is off
* Fix: Persian old-slug 404 lookup (encoded/decoded path candidates)
* Fix: Finglish dictionary uses whole-token match (short keys no longer corrupt longer words)
* Fix: batch select hard-capped at 20 in UI; clearer IndexNow / 301 feedback

= 1.15.0 =
* Slug health: filters (فارسی / طولانی / امتیاز < ۵۰), multi-select, Dry-Run + batch apply (max 20)
* Clear 301 + IndexNow feedback after apply; Undo restores old slug and disables 301
* Loop/chain detection merges slug redirects with OOS redirects before apply
* Finglish word dictionary (fashion/shop terms) for better suggestions than letter-only

= 1.14.0 =
* Admin IA: 5 primary hubs (وضعیت · عملیات · آمار · تنظیمات · راهنما) instead of 11 top tabs
* Ops / Guide secondary pill nav — old deep links still work
* Settings regrouped into numbered accordions + collapsible panels + sticky save bar
* Cleaner primary tab labels with short hints

= 1.13.0 =
* New admin tab «نامک»: list / activate / deactivate / delete slug redirects
* Slug health report: Persian/long/low-score candidates with Finglish suggestion
* Per-product preview + apply (creates 301 + IndexNow notify) — no mass rewrite

= 1.12.0 =
* Slug tools: Finglish auto-slug for new products (Persian title kept)
* Published slug change: warning in editor + automatic 301 redirect table
* Slug readability score metabox on product edit
* Complementary products block on single product (crawl depth / time on site)
* Settings toggles for slug tools and complementary mode/limit

= 1.11.0 =
* Variation canonical: attribute URLs / variation permalinks point to parent product
* Dashboard card for variation canonical + clearer GSC/IndexNow guidance
* Product layers: toggle for variation canonical (default on)
* Keeps Rank Math / Yoast coexistence (filters their canonical when present)

= 1.10.16 =
* Settings: accordion sections (product layers + lifecycle/links) to reduce clutter
* Education: step-by-step Search Console connection guide (Persian, for non-technical users)
* GSC/activity logs: RTL-friendly Persian messages; lighter settings page load

= 1.10.15 =
* GSC: reject service-account email pasted into property field (auto-heal to home_url)
* Detect Google HTML 403 as NETWORK_BLOCKED (server cannot reach indexing.googleapis.com)

= 1.10.14 =
* GSC test: capture real Google raw_body/google_message (fix empty debug loop)
* Layer C now tests publish first; metadata is only an enrichment probe

= 1.10.13 =
* GSC: distinguish Search Console API verify failure from real Owner permission denied
* Preserve raw Google error payload in Indexing test (no hardcoded 403 remapping)

= 1.10.12 =
* GSC indexing test: structured admin message + technical debug log (toggle view)
* Added ErrorMapper with categories: AUTH_ERROR / PERMISSION_DENIED / API_NOT_ENABLED / INVALID_REQUEST
* Added preflight checks + last 3 failed attempts log for production diagnostics

= 1.10.11 =
* GSC commercial: normalize_gsc_property (domain / URL-prefix / home_url fallback)
* Non-blocking sites.list + direct sites.get; Indexing metadata soft-check
* Layered diagnostics: JSON / Auth / Property / Auto-list / Indexing (success|warning|fail)

= 1.10.10 =
* GSC: تشخیص سه‌لایه (Token / خاصیت دستی / Indexing) — sites.list دیگر مانع اتصال نیست
* خاصیت دستی authoritative؛ UI وضعیت هر لایه را جدا نشان می‌دهد

= 1.10.9 =
* GSC: پیام خطای HTML خام گوگل حذف شد؛ راهنمای فارسی برای ۴۰۳
* خاصیت دستی (sc-domain:…) وقتی فهرست سایت‌ها ۴۰۳ می‌دهد
* تشخیص بهتر «API فعال نیست» در برابر «Owner اضافه نشده»

= 1.10.8 =
* سرعت ادمین: حذف اسکن شباهت ۵۰ محصول از لود داشبورد (علت اصلی کندی ~۴۰ثانیه)
* کش نقشه ریدایرکت در هر درخواست + کش سلامت عملیات + اسنپ‌شات روزانه فقط یک‌بار
* تب موجودی: تعمیر تاریخ در تکه‌های ۵۰تایی؛ تب لینک: سقف ۱۰۰ ردیف

= 1.10.7 =
* تنظیمات چرخه OOS: با انتخاب روز پیام، دائم و کاندید پیشنهاد بهینه می‌گیرند (قابل ویرایش)
* میانبرهای ۷ / ۱۰ / ۱۵ / ۲۰ / ۳۰ روز + دکمه «اعمال پیشنهاد بهینه»

= 1.10.6 =
* اعلان‌ها: حذف تکرار «اسکن کامل شد» و دکمه بی‌فایده «مشاهده»
* CTA واضح: «تست این محصول» یا «لیست ناموجودها» فقط وقتی مقصد واقعی دارد

= 1.10.5 =
* لینک‌سازی: استفاده از توضیح کوتاه ووکامرس وقتی محتوا خالی است
* پیشنهاد خودکار از محصولات مشابه همان دسته (شباهت عنوان فارسی)
* بلوک «محصولات مرتبط» وقتی در متن کلمه کلیدی پیدا نشود
* رفع مسدود شدن مقاصد به‌خاطر deprioritized بعد از باگ روزهای ناموجودی
* بهبود شباهت عنوان (Dice + نرمال‌سازی فارسی) و نمایش واقعی در تست محصول

= 1.10.4 =
* پیش‌نمایش لینک: تفکیک «لینک از قبل در محتوا» و «لینک تازه‌درج‌شده توسط موتور»
* رفع سقف مجاز ۰ روی توضیحات کوتاه محصول (حداقل ۱ لینک)
* هایلایت سبز برای لینک‌های تازه‌درج‌شده در پیش‌نمایش

= 1.10.3 =
* رفع باگ «۲۲۶ هزار روز ناموجود» (ذخیره تاریخ شمسی به‌جای میلادی)
* تعمیر خودکار تاریخ‌های خراب + محاسبه امن روزها
* فیلتر عملیات موجودی سبک‌تر با صفحه‌بندی (بدون تایم‌اوت)
* چک‌باکس انتخاب گروهی برای ریدایرکت ۳۰۲/۳۰۱/۴۱۰ روی لیست ناموجودها

= 1.10.2 =
* رفع تایم‌اوت / علامت ممنوع روی «اسکن مجدد موجودی» + نوار پیشرفت در داشبورد
* تست محصول / «مشاهده» سریع‌تر (بدون اسکن سنگین مشابهت)
* بازطراحی نوار تب‌ها با چینش راست‌چین مرتب
* حذف عبارات مرتبط با AI از متن‌های محصول و آموزش

= 1.10.1 =
* نوار پیشرفت واقعی برای اسکن موجودی در ویزارد
* رفع باگ «مشاهده» که به مرحله ۱ ویزارد برمی‌گرداند
* اعلان‌ها به تب جدا؛ لینک مشاهده → تست محصول
* پریست بیشتر: مد و پوشاک، آرایشی، کالای دیجیتال، فایل/کتاب
* پیش‌فرض‌های ویزارد قابل ویرایش با هشدار مبتدی
* برآورد تاریخ ناموجودی قبلی (آخرین ویرایش) نه فقط روز نصب

= 1.10.0 =
* تب «اثر و آمار»: شمارش ۳۰۱/۳۰۲/۴۱۰، noindex، لینک، GSC + نمودار SVG
* امتیاز «سلامت عملیات» با baseline قبل از نصب و عوامل شفاف (نه رتبه گوگل)
* پروفایل فروشگاهی آماده: عمومی / مد / الکترونیک (ویزارد + تب اثر)
* بنر تداخل اسکیما در فرانت برای مدیران + خاموش‌کردن یک‌کلیکه اسکیمای ووکامرس

= 1.9.9 =
* سیاست یکپارچگی روشن با Yoast / Rank Math (و SEOPress / AIOSEO)
* Meta Title/Description غیرفعال پیش‌فرض — بدون جنگ خروجی متا
* واگذاری خودکار Product/Breadcrumb وقتی افزونه SEO فعال است (قابل خاموش‌کردن)
* جدول تفکیک نقش‌ها در تنظیمات + هشدار همزیستی در پنل
* Schema Detector با پیشنهادهای آگاه از Yoast و Rank Math

= 1.9.8 =
* لینک‌سازی داخلی قانون‌محور و محافظه‌کار
* سقف سخت لینک در هر صفحه + تراکم در ۱۰۰۰ کلمه + فاصله حداقل
* مسدودسازی مقصدهای noindex / ریدایرکت‌شده + جلوگیری از anchor تکراری
* اولویت دسته، برند، ویژگی، upsell و جایگزین واقعی OOS
* Whitelist / Blacklist کلمات و URL + حالت انحصاری قابل کنترل

= 1.9.7 =
* UX وضعیت‌محور: داشبورد Action Cards به‌جای تمرکز روی فرم تنظیمات
* کارت‌ها: نیازمند اقدام، ریدایرکت پیشنهادی، خطاهای Job، Undo
* Setup Wizard ساده برای شروع اولیه با پیش‌فرض‌های امن
* وضعیت‌های قابل فهم: امن / هشدار / نیازمند اقدام / خطا

= 1.9.6 =
* Dry-Run به‌عنوان پایه UX اعتماد: شمارش آیتم‌ها، نوع تغییر، ریسک/هشدار
* خروجی CSV از گزارش شبیه‌سازی
* اجرای واقعی از روی همان پیش‌نمایش (با ثبت Undo)
* تب Dry-Run / Undo برای بازار ایران بازطراحی شد

= 1.9.5 =
* Undo واقعی سطح‌یک: ثبت batch_id + قبل/بعد برای ریدایرکت، noindex، لینک، sitemap
* پیش‌نمایش اثر Undo قبل از اعمال (تکی و دسته‌ای)
* قفل اصل: عملیات بدون Undo در اتوماسیون انبوه اعمال نمی‌شود
* متای `_shojaei_seo_noindex` برای بازگردانی دقیق indexability

= 1.9.4 =
* صف Job اختصاصی با جدول `shojaei_seo_jobs` (pending/running/done/failed)
* اجرای batch کوچک + retry محدود و قابل‌ردیابی
* رانر ترکیبی: Action Scheduler + Ajax/REST drain + cron داخلی (نه اتکای کامل به WP-Cron)
* انواع جاب: Bulk Redirect، روزانه OOS، بازسازی لینک، بازتولید اسکیما

= 1.9.3 =
* معماری Event-driven: شنود موجودی، قیمت، انتشار/حذف، دسته/برچسب، به‌روزرسانی جایگزین
* جاب روزانه سبک‌تر (فقط reconcile روزشمار + عبور آستانه) وقتی رویدادمحور فعال است
* debounce و صف AS برای جلوگیری از طوفان رویداد در ایمپورت

= 1.9.2 =
* Rule Engine سبک: ورودی موجودی/روز/جایگزین/Page Value → خروجی تصمیم متمرکز
* اتصال noindex، اتوماسیون ریدایرکت، صف روزانه و لینک‌سازی به موتور قوانین
* ردپای قوانین در تب تست محصول

= 1.9.1 =
* جهت‌گیری محصول: تمرکز صریح روی SEO Operations و Inventory-aware SEO
* بازطراحی پیام‌رسانی پنل (مرکز عملیات، عملیات موجودی، لایه‌های هسته/پشتیبان)
* تمایز واضح از ابزارهای متا مثل Yoast/Rank Math

= 1.9.0 =
* اتصال آسان به Google Search Console با فایل کلید Service Account
* تایید دسترسی دامنه در پس‌زمینه + چراغ وضعیت سبز
* اتوماسیون Request Indexing / URL Inspection هنگام ناموجودی و ریدایرکت

= 1.8.2 =
* بهینه‌سازی Performance: پردازش سنگین با WP-Cron / Action Scheduler به‌صورت Batch (پیش‌فرض ۵۰ محصول در هر اجرا)
* بررسی روزانه ناموجودی، عملیات گروهی و شبیه‌سازی بزرگ در صف پس‌زمینه
* تنظیم اندازه دسته + نمایش وضعیت جاب‌ها در تنظیمات
* کش موقت نقشه ریدایرکت و صفحه‌بندی کوئری‌های اسکن موجودی

= 1.8.1 =
* تشخیص تداخل اسکیما (application/ld+json موازی) در فرانت‌اند
* هشدار مدیر + اسکن دستی URL
* گزینه غیرفعال‌سازی اسکیمای پیش‌فرض ووکامرس و بخش‌های Product/Breadcrumb/FAQ افزونه

= 1.8.0 =
* تب شبیه‌سازی / Rollback: Dry-Run ریدایرکت انبوه و لینک‌سازی
* جدول تاریخچه تغییرات (Revert Log) با بازگردانی تکی و دسته‌ای
* ثبت before/after برای ریدایرکت، نگهداری صفحه و لینک‌سازی

= 1.7.2 =
* لینک‌سازی: نرمال‌سازی فارسی (نیم‌فاصله، ی/ک عربی، پسوندها)
* عدم درج لینک در H1–H6، دکمه، منو، nav و style
* محدودیت تراکم سخت‌گیرانه‌تر (حداکثر ۳ لینک در ۱۰۰۰ کلمه)

= 1.7.1 =
* جلوگیری از حلقه و زنجیره ریدایرکت قبل از ذخیره (دستی و خودکار)
* امتیاز شباهت واقعی: عنوان + برچسب + ویژگی + قیمت (نه اولین محصول همدسته)
* اگر شباهت کافی نباشد، مقصد دسته انتخاب می‌شود تا Soft 404 کم شود

= 1.7.0 =
* چرخه چندسطحی OOS: ناموجود موقت / دائم (به‌جای منطق دودویی)
* State Machine با تاریخ ناموجودی در جدول + postmeta
* پیش‌فرض زمانی: روز ۱۵ تغییر پیام، روز ۳۰ دائم، روز ۴۵ ریدایرکت ۳۰۲
* Page Value محلی قبل از ریدایرکت/۴۱۰ — صفحات ارزشمند فقط با تایید دستی

= 1.6.0 =
* حالت Dry-Run برای ریدایرکت خودکار (پیش‌فرض فعال؛ فقط پیشنهاد)
* صفحه تست محصول در پنل (تشخیص فاز، noindex، پیشنهاد ریدایرکت)
* لاگ محلی اقدامات مدیر (ریدایرکت، اسکن، تنظیمات، لینک‌ها)

= 1.5.0 =
* دکمه اجرای مجدد اسکن موجودی در داشبورد
* مقایسه این هفته با هفته قبل
* فیلتر دسته و نوع محصول (ساده/متغیر) در لیست ناموجود

= 1.4.0 =
* Action Scheduler برای پردازش پس‌زمینه (با fallback به WP-Cron)
* سازگاری Cache (LiteSpeed / WP Rocket / W3TC / Super Cache)
* uninstall.php برای پاکسازی کامل هنگام حذف
* اسکن اولیه محصولات ناموجود هنگام فعال‌سازی

= 1.3.0 =
* داشبورد تحلیلی محلی (نمودار ۷/۳۰ روزه بدون CDN/API)
* Export CSV لاگ ریدایرکت، OOS و آمار روزانه
* خلاصه هفتگی داخل پیشخوان (کاملاً مستقل)

= 1.2.0 =
* پشتیبانی محصول متغیر (Variable Product)
* گزینه 410 Gone برای محصولات حذف‌شده
* مرکز اعلان داخل پیشخوان افزونه

= 1.1.0 =
* noindex برای محصولات ناموجود فاز ۲/۳
* پیش‌نمایش لینک‌سازی داخلی + پردازش امن HTML
* Bulk Action و Undo ریدایرکت
* فیلتر و جستجو در لیست ناموجود

= 1.0.0 =
* نسخه اولیه
