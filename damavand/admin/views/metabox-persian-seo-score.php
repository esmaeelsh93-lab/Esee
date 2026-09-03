<?php
/**
 * Metabox: سئوی فارسی Damavand — عنوان، توضیح، کلمه کلیدی، امتیاز، پیش‌نمایش SERP.
 *
 * @var WP_Post $post
 * @var array   $analysis
 * @var string  $title
 * @var string  $desc
 * @var string  $focus
 * @var string  $serp_url
 * @var string  $site_name
 * @var int     $faq_count
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$tone      = (string) ( $analysis['tone'] ?? 'bad' );
$serp_title = $title !== '' ? $title : (string) $post->post_title;
$serp_desc  = $desc !== '' ? $desc : '';
$serp_url   = isset( $serp_url ) ? (string) $serp_url : (string) get_permalink( $post );
$site_name  = isset( $site_name ) ? (string) $site_name : wp_strip_all_tags( get_bloginfo( 'name' ) );
?>
<div class="dm-score" id="damavand-seo-score-box" dir="rtl" data-post-id="<?php echo esc_attr( (string) (int) $post->ID ); ?>">
	<div class="dm-score__layout">
		<div class="dm-score__main">
			<div class="dm-score__serp" id="dm-score-serp" aria-label="<?php esc_attr_e( 'پیش‌نمایش نتیجه گوگل', 'shojaei-seo-for-woo' ); ?>">
				<p class="dm-score__serp-crumb">
					<span class="dm-score__serp-site" id="dm-serp-site"><?php echo esc_html( $site_name ); ?></span>
					<span class="dm-score__serp-url" id="dm-serp-url" dir="ltr"><?php echo esc_html( $serp_url ); ?></span>
				</p>
				<p class="dm-score__serp-title" id="dm-serp-title"><?php echo esc_html( $serp_title ); ?></p>
				<p class="dm-score__serp-desc" id="dm-serp-desc"><?php echo esc_html( $serp_desc !== '' ? $serp_desc : __( 'توضیح متا اینجا نمایش داده می‌شود…', 'shojaei-seo-for-woo' ) ); ?></p>
			</div>

			<label class="dm-score__field">
				<span><?php esc_html_e( 'عنوان سئو', 'shojaei-seo-for-woo' ); ?></span>
				<input type="text" name="damavand_seo_score_title" id="dm-score-title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( (string) $post->post_title ); ?>" />
				<em class="dm-score__meta" id="dm-score-title-w"><?php echo esc_html( sprintf( /* translators: %s: weighted len */ __( 'طول وزنی فارسی: %s — هدف حدود ۳۸ تا ۶۸', 'shojaei-seo-for-woo' ), (string) ( $analysis['title_weighted'] ?? 0 ) ) ); ?></em>
			</label>
			<label class="dm-score__field">
				<span><?php esc_html_e( 'توضیح متا', 'shojaei-seo-for-woo' ); ?></span>
				<textarea name="damavand_seo_score_desc" id="dm-score-desc" rows="3" placeholder="<?php esc_attr_e( 'یک یا دو جمله فارسی که کاربر را به کلیک دعوت کند', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
				<em class="dm-score__meta" id="dm-score-desc-w"><?php echo esc_html( sprintf( /* translators: %s: weighted len */ __( 'طول وزنی فارسی: %s — هدف حدود ۹۵ تا ۱۶۵', 'shojaei-seo-for-woo' ), (string) ( $analysis['desc_weighted'] ?? 0 ) ) ); ?></em>
			</label>
			<label class="dm-score__field">
				<span><?php esc_html_e( 'کلمه کلیدی اصلی', 'shojaei-seo-for-woo' ); ?></span>
				<input type="text" name="damavand_seo_score_focus" id="dm-score-focus" value="<?php echo esc_attr( $focus ); ?>" placeholder="<?php esc_attr_e( 'مثلاً کفش ورزشی مردانه', 'shojaei-seo-for-woo' ); ?>" />
			</label>
			<?php if ( 'product' === $post->post_type ) : ?>
			<label class="dm-score__field">
				<span><?php esc_html_e( 'کلمات مرتبط', 'shojaei-seo-for-woo' ); ?></span>
				<input type="text" name="damavand_seo_score_related" id="dm-score-related" value="<?php echo esc_attr( isset( $related ) ? (string) $related : '' ); ?>" placeholder="<?php esc_attr_e( 'با ویرگول جدا کنید — مثلاً: ونس، چرم، سفید، مردانه', 'shojaei-seo-for-woo' ); ?>" />
				<em class="dm-score__meta"><?php esc_html_e( 'فقط در مدیریت — در فرانت چاپ نمی‌شود. برای FAQ، متا، لینک و پیشنهاد محتوا (حداکثر ۱۵ عبارت، اختیاری).', 'shojaei-seo-for-woo' ); ?></em>
			</label>
			<?php endif; ?>

			<?php if ( 'product' === $post->post_type && class_exists( 'Shojaei_SEO_AI_Client' ) && Shojaei_SEO_AI_Client::is_enabled() ) : ?>
			<div class="dm-score__ollama" id="dm-ollama-panel">
				<h4 class="dm-score__links-title"><?php esc_html_e( 'تولید محتوای سئو', 'shojaei-seo-for-woo' ); ?></h4>
				<p class="description"><?php esc_html_e( 'توضیح کامل = دو فاز (طرح + مقاله). بسته کامل = متا + توضیحات دو فاز + Alt. عنوان متا با «خرید».', 'shojaei-seo-for-woo' ); ?></p>
				<label class="dm-score__field">
					<span><?php esc_html_e( 'اطلاعات تکمیلی محصول', 'shojaei-seo-for-woo' ); ?></span>
					<textarea id="dm-ollama-extra" rows="3" placeholder="<?php esc_attr_e( 'جنس، برند، کاربرد، نکات فروش…', 'shojaei-seo-for-woo' ); ?>"></textarea>
				</label>
				<label class="dm-score__field">
					<span><?php esc_html_e( 'سایزبندی (هر خط: سایز|قد|دور سینه یا با Tab)', 'shojaei-seo-for-woo' ); ?></span>
					<textarea id="dm-ollama-sizes" rows="4" placeholder="<?php echo esc_attr( "سایز\tقد\tدور سینه\nM\t170\t96\nL\t175\t100" ); ?>"></textarea>
				</label>
				<p class="dm-score__ollama-actions">
					<button type="button" class="button button-primary" id="dm-auto-seo-run"><?php esc_html_e( 'سئو خودکار محصول', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="keywords"><?php esc_html_e( 'پیشنهاد کلمه کلیدی', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="meta_titles"><?php esc_html_e( 'عنوان‌های سئو', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="meta_desc"><?php esc_html_e( 'توضیح متا', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="short_desc"><?php esc_html_e( 'توضیح کوتاه', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="long_desc"><?php esc_html_e( 'توضیح کامل', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="faq"><?php esc_html_e( 'FAQ + Schema', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="alt_texts"><?php esc_html_e( 'Alt تصاویر', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="slug"><?php esc_html_e( 'نامک فینگلیش', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" data-ai="itemlist"><?php esc_html_e( 'ItemList Schema', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button button-primary" data-ai="full_pack"><?php esc_html_e( 'بسته کامل', 'shojaei-seo-for-woo' ); ?></button>
				</p>
				<div id="dm-auto-seo-progress" class="dm-score__auto-seo" hidden>
					<div class="dm-score__auto-seo-bar" aria-hidden="true"><span id="dm-auto-seo-bar-fill"></span></div>
					<p class="description" id="dm-auto-seo-step"></p>
					<ul id="dm-auto-seo-checklist" class="dm-score__auto-checklist"></ul>
				</div>
				<div id="dm-ollama-titles" class="dm-score__ollama-titles" hidden></div>
				<pre id="dm-ollama-schema-pre" class="dm-score__schema-pre" dir="ltr" hidden></pre>
				<p class="description" id="dm-ollama-status" aria-live="polite"></p>
			</div>
			<?php endif; ?>

			<p class="dm-score__tpl-row">
				<button type="button" class="button" id="dm-score-apply-tpl"><?php esc_html_e( 'اعمال قالب پیش‌فرض', 'shojaei-seo-for-woo' ); ?></button>
				<span class="description"><?php esc_html_e( 'عنوان و توضیح خالی را از قالب نوع محتوا پر می‌کند (توکن‌ها مثل %title% و %sep%).', 'shojaei-seo-for-woo' ); ?></span>
			</p>

			<?php if ( ! empty( $analysis['has_fa_slug'] ) ) : ?>
				<div class="dm-score__alert">
					<p><?php esc_html_e( 'نامک فارسی است — برای لینک و اشتراک‌گذاری، فینگلیش لاتین بهتر است (حروف اضافه مثل از/با/در حذف می‌شوند).', 'shojaei-seo-for-woo' ); ?></p>
					<button type="button" class="button button-primary" id="dm-score-finglish"><?php esc_html_e( 'تولید نامک فینگلیش', 'shojaei-seo-for-woo' ); ?></button>
					<code class="dm-score__finglish" dir="ltr" id="dm-score-finglish-preview"><?php echo esc_html( (string) ( $analysis['finglish'] ?? '' ) ); ?></code>
				</div>
			<?php else : ?>
				<p class="dm-score__slug-row">
					<button type="button" class="button" id="dm-score-finglish"><?php esc_html_e( 'تولید نامک فینگلیش', 'shojaei-seo-for-woo' ); ?></button>
					<code class="dm-score__finglish" dir="ltr" id="dm-score-finglish-preview"><?php echo esc_html( (string) ( $analysis['finglish'] ?? $post->post_name ) ); ?></code>
				</p>
			<?php endif; ?>

			<p class="dm-score__status" id="dm-score-status" aria-live="polite"></p>
			<p class="dm-score__extras-row">
				<button type="button" class="button button-small" id="dm-score-load-extras"><?php esc_html_e( 'بارگذاری پیشنهاد لینک و FAQ', 'shojaei-seo-for-woo' ); ?></button>
				<span class="description"><?php esc_html_e( 'برای سبک‌تر شدن ویرایش ویژگی/متغیر، پیشنهادها خودکار بارگذاری نمی‌شوند.', 'shojaei-seo-for-woo' ); ?></span>
			</p>

			<div class="dm-score__links" id="dm-score-links" hidden>
				<h4 class="dm-score__links-title"><?php esc_html_e( 'پیشنهاد لینک داخلی', 'shojaei-seo-for-woo' ); ?></h4>
				<p class="description dm-score__links-desc"><?php esc_html_e( 'پیشنهاد خودکار یا جستجوی دستی — لینک در ویرایشگر درج می‌شود (با «به‌روزرسانی» ذخیره کنید).', 'shojaei-seo-for-woo' ); ?></p>
				<ul class="dm-score__link-list" id="dm-score-link-list"></ul>
				<div class="dm-score__link-search" style="margin:10px 0;">
					<label for="dm-score-link-search-input" class="screen-reader-text"><?php esc_html_e( 'جستجوی محصول یا نوشته', 'shojaei-seo-for-woo' ); ?></label>
					<input type="search" id="dm-score-link-search-input" class="regular-text" placeholder="<?php esc_attr_e( 'جستجوی محصول، نوشته یا برگه…', 'shojaei-seo-for-woo' ); ?>" style="width:100%;max-width:420px;" />
					<ul class="dm-score__link-list" id="dm-score-link-search-results" style="margin-top:8px;"></ul>
				</div>
				<p class="dm-score__links-actions">
					<button type="button" class="button button-primary" id="dm-score-link-inject"><?php esc_html_e( 'درج در محتوا', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" id="dm-score-link-refresh"><?php esc_html_e( 'بروزرسانی پیشنهادها', 'shojaei-seo-for-woo' ); ?></button>
				</p>
			</div>
			<div class="dm-score__ai-draft" id="dm-ai-draft-panel" hidden>
				<p class="description" id="dm-ai-draft-msg"></p>
				<button type="button" class="button button-primary" id="dm-ai-draft-apply"><?php esc_html_e( 'اعمال پیش‌نویس', 'shojaei-seo-for-woo' ); ?></button>
				<button type="button" class="button" id="dm-ai-draft-discard"><?php esc_html_e( 'رد پیش‌نویس', 'shojaei-seo-for-woo' ); ?></button>
			</div>

			<div class="dm-score__at-risk" id="dm-score-at-risk" hidden>
				<h4 class="dm-score__links-title"><?php esc_html_e( 'لینک‌های در خطر', 'shojaei-seo-for-woo' ); ?></h4>
				<ul class="dm-score__link-list dm-score__link-list--risk" id="dm-score-at-risk-list"></ul>
			</div>

			<div class="dm-score__faq" id="dm-score-faq">
				<h4 class="dm-score__links-title"><?php esc_html_e( 'FAQ گوگل (Schema)', 'shojaei-seo-for-woo' ); ?></h4>
				<p class="description dm-score__faq-desc">
					<?php esc_html_e( 'سؤال و جواب در محتوا + JSON-LD FAQPage — گوگل Rich Result می‌گیرد. پیشنهادها را ویرایش کنید و درج بزنید.', 'shojaei-seo-for-woo' ); ?>
				</p>
				<p class="dm-score__faq-badge description" id="dm-score-faq-badge" <?php echo empty( $faq_count ) ? 'hidden' : ''; ?>>
					<?php
					printf(
						/* translators: %d: faq count */
						esc_html__( 'FAQ ذخیره‌شده: %d سؤال (Schema فعال)', 'shojaei-seo-for-woo' ),
						(int) ( $faq_count ?? 0 )
					);
					?>
				</p>
				<div id="dm-score-faq-list" class="dm-score__faq-list"></div>
				<p class="dm-score__links-actions">
					<button type="button" class="button button-primary" id="dm-score-faq-inject"><?php esc_html_e( 'درج FAQ در محتوا', 'shojaei-seo-for-woo' ); ?></button>
				</p>
				<p class="description" id="dm-score-faq-status" aria-live="polite"></p>
			</div>
		</div>

		<aside class="dm-score__side">
			<div class="dm-score__gauge dm-score__gauge--<?php echo esc_attr( $tone ); ?>">
				<span class="dm-score__num" id="dm-score-num"><?php echo esc_html( (string) (int) ( $analysis['score'] ?? 0 ) ); ?></span>
				<span class="dm-score__label"><?php esc_html_e( 'امتیاز فارسی', 'shojaei-seo-for-woo' ); ?></span>
			</div>
			<p class="dm-score__next" id="dm-score-next" <?php echo empty( $analysis['next_tip'] ) ? 'hidden' : ''; ?>>
				<?php echo esc_html( (string) ( $analysis['next_tip'] ?? '' ) ); ?>
			</p>
			<p class="dm-score__hint"><?php esc_html_e( 'بر اساس طول عنوان/توضیح فارسی، کلمه کلیدی، نامک و تصویر — آفلاین.', 'shojaei-seo-for-woo' ); ?></p>
			<ul class="dm-score__checks" id="dm-score-checks">
				<?php foreach ( (array) ( $analysis['checks'] ?? array() ) as $check ) : ?>
					<li class="<?php echo ! empty( $check['ok'] ) ? 'is-ok' : 'is-bad'; ?>">
						<strong><?php echo esc_html( (string) ( $check['label'] ?? '' ) ); ?></strong>
						<span dir="ltr"><?php echo esc_html( (int) ( $check['points'] ?? 0 ) . '/' . (int) ( $check['max'] ?? 0 ) ); ?></span>
						<em><?php echo esc_html( (string) ( $check['tip'] ?? '' ) ); ?></em>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! empty( $analysis['detailed_checks'] ) ) : ?>
				<details class="dm-score__detailed" open>
					<summary><?php esc_html_e( 'چک‌لیست تکمیلی (فاز ۲)', 'shojaei-seo-for-woo' ); ?></summary>
					<ul class="dm-score__checks dm-score__checks--detailed" id="dm-score-detailed-checks">
						<?php foreach ( (array) $analysis['detailed_checks'] as $dcheck ) : ?>
							<?php
							$st = (string) ( $dcheck['status'] ?? 'warning' );
							$cls = 'is-warn';
							if ( 'pass' === $st ) {
								$cls = 'is-ok';
							} elseif ( 'fail' === $st ) {
								$cls = 'is-bad';
							}
							?>
							<li class="<?php echo esc_attr( $cls ); ?>">
								<strong><?php echo esc_html( (string) ( $dcheck['label'] ?? '' ) ); ?></strong>
								<em><?php echo esc_html( (string) ( $dcheck['message'] ?? '' ) ); ?></em>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
			<?php
			$read = (array) ( $analysis['readability'] ?? array() );
			if ( ! empty( $read['sentence_count'] ) ) :
				?>
				<details class="dm-score__readability" open>
					<summary><?php esc_html_e( 'خوانایی محتوا', 'shojaei-seo-for-woo' ); ?></summary>
					<dl class="dm-score__read-dl" id="dm-score-readability">
						<dt><?php esc_html_e( 'میانگین کلمات در جمله', 'shojaei-seo-for-woo' ); ?></dt>
						<dd dir="ltr"><?php echo esc_html( (string) ( $read['avg_sentence'] ?? 0 ) ); ?></dd>
						<dt><?php esc_html_e( 'جملات بلند (>۲۵ کلمه)', 'shojaei-seo-for-woo' ); ?></dt>
						<dd dir="ltr"><?php echo esc_html( (string) ( $read['long_pct'] ?? 0 ) ); ?>%</dd>
						<dt><?php esc_html_e( 'جملات مجهول (تخمینی)', 'shojaei-seo-for-woo' ); ?></dt>
						<dd dir="ltr"><?php echo esc_html( (string) ( $read['passive_count'] ?? 0 ) ); ?></dd>
						<dt><?php esc_html_e( 'پاراگراف بدون زیرعنوان نزدیک', 'shojaei-seo-for-woo' ); ?></dt>
						<dd dir="ltr"><?php echo esc_html( (string) ( $read['para_no_h_pct'] ?? 0 ) ); ?>%</dd>
					</dl>
				</details>
			<?php endif; ?>
			<?php if ( ! empty( $analysis['advisory_hint'] ) ) : ?>
				<p class="dm-score__advisory-hint description" id="dm-score-advisory-hint">
					<?php
					printf(
						/* translators: %d: potential points */
						esc_html__( 'پیشنهاد بهبود از چک‌لیست تکمیلی: ~%d امتیاز بالقوه (فقط راهنما)', 'shojaei-seo-for-woo' ),
						(int) $analysis['advisory_hint']
					);
					?>
				</p>
			<?php endif; ?>
			<details class="dm-score__schema-preview">
				<summary><?php esc_html_e( 'پیش‌نمایش JSON-LD', 'shojaei-seo-for-woo' ); ?></summary>
				<p>
					<button type="button" class="button button-small" id="dm-score-schema-load"><?php esc_html_e( 'بارگذاری خروجی Damavand', 'shojaei-seo-for-woo' ); ?></button>
					<a class="button button-small" id="dm-score-schema-rrt" href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Rich Results Test', 'shojaei-seo-for-woo' ); ?></a>
				</p>
				<pre class="dm-score__schema-pre" id="dm-score-schema-pre" dir="ltr" hidden></pre>
				<p class="description" id="dm-score-schema-status" aria-live="polite"></p>
			</details>
		</aside>
	</div>
</div>
