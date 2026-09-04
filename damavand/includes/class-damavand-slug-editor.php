<?php
/**
 * Slug metabox, live_preview AJAX, admin assets, maybe_transliterate_new_slug.
 *
 * Extracted from Shojaei_SEO_Slug (Task 5). Facade wrappers remain on Shojaei_SEO_Slug.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Slug_Editor
 */
class Damavand_Slug_Editor {
	/**
	 * Auto-Finglish for new products (or Persian slug).
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Raw.
	 * @return array
	 */
	public function maybe_transliterate_new_slug( array $data, array $postarr ): array {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_finglish', 'yes' ) ) {
			return $data;
		}
		$post_type = (string) ( $data['post_type'] ?? '' );
		if ( ! Damavand_Slug_Finglish::is_supported_type( $post_type ) ) {
			return $data;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}

		// Merchant opted out: keep Persian/current slug intentionally.
		if ( ! empty( $_POST['shojaei_seo_keep_slug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $data;
		}

		$status = $data['post_status'] ?? '';
		if ( in_array( $status, array( 'trash', 'auto-draft' ), true ) ) {
			return $data;
		}

		$post_id = (int) ( $postarr['ID'] ?? 0 );
		$slug    = (string) ( $data['post_name'] ?? '' );
		$title   = (string) ( $data['post_title'] ?? '' );

		if ( '' === $title ) {
			return $data;
		}

		// Respect a clean Latin/Finglish slug the merchant already chose.
		if ( Damavand_Slug_Finglish::is_clean_latin_slug( $slug ) ) {
			return $data;
		}

		// Published: only auto-fix when current slug is still Persian/invalid.
		if ( $post_id > 0 ) {
			$existing = get_post( $post_id );
			if ( $existing && 'publish' === $existing->post_status ) {
				$current = $slug !== '' ? $slug : (string) $existing->post_name;
				if ( Damavand_Slug_Finglish::is_clean_latin_slug( $current ) ) {
					return $data;
				}
			}
		}

		$latin = Damavand_Slug_Finglish::transliterate( $title );
		if ( '' === $latin || Damavand_Slug_Finglish::has_persian( $latin ) ) {
			return $data;
		}

		$data['post_name'] = Damavand_Slug_Finglish::uniquify_slug(
			$latin,
			$post_id,
			$post_type,
			(string) $status,
			(int) ( $data['post_parent'] ?? 0 )
		);

		return $data;
	}

	/**
	 * Product editor metabox.
	 */
	public function register_metabox(): void {
		foreach ( Damavand_Slug_Finglish::supported_post_types() as $post_type ) {
			add_meta_box(
				'shojaei_seo_slug_box',
				__( 'نامک سئو (دماوند)', 'shojaei-seo-for-woo' ),
				array( $this, 'render_metabox' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Live preview payload for product editor (title + current slug).
	 *
	 * @param string $title Title.
	 * @param string $slug  Current slug (may be empty).
	 * @return array{score:int,tone:string,tips:string[],suggest:string,slug:string,based_on:string}
	 */
	public static function live_preview( string $title, string $slug = '', int $post_id = 0 ): array {
		$title   = Damavand_Slug_Finglish::normalize_fa( $title );
		$slug    = trim( rawurldecode( $slug ), '/' );
		$base    = $title ? Damavand_Slug_Finglish::transliterate( $title ) : '';
		$suggest = $base;

		// Never surface a Persian "suggestion" — only Latin Finglish.
		if ( $suggest && Damavand_Slug_Finglish::has_persian( $suggest ) ) {
			$suggest = Damavand_Slug_Finglish::transliterate( $suggest );
		}
		if ( $suggest && ! preg_match( '/^[a-z0-9\-]+$/', $suggest ) ) {
			$suggest = preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $suggest ) ) );
			$suggest = trim( (string) $suggest, '-' );
			$suggest = Damavand_Slug_Finglish::strip_slug_stopwords( (string) $suggest );
		}

		$unique_note = false;
		if ( $suggest && $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post && Damavand_Slug_Finglish::is_supported_type( (string) $post->post_type ) ) {
				$unique = Damavand_Slug_Finglish::uniquify_slug(
					$suggest,
					$post_id,
					(string) $post->post_type,
					(string) $post->post_status,
					(int) $post->post_parent
				);
				if ( $unique && $unique !== $suggest ) {
					$unique_note = true;
					$suggest     = $unique;
				}
			}
		}

		// Prefer real slug when set; otherwise score the Finglish suggestion so new products aren't stuck at 0.
		$based_on = '' !== $slug ? $slug : $suggest;
		$score    = Damavand_Slug_Finglish::score_slug( $based_on );
		$tone     = $score['score'] >= 75 ? 'safe' : ( $score['score'] >= 45 ? 'warning' : 'error' );

		$needs_fix = ( '' === $slug ) || Damavand_Slug_Finglish::has_persian( $slug ) || ! Damavand_Slug_Finglish::is_clean_latin_slug( $slug );

		if ( '' === $slug && $suggest ) {
			array_unshift(
				$score['tips'],
				__( 'هنوز نامک ذخیره نشده — امتیاز بر اساس پیشنهاد فینگلیش از عنوان است.', 'shojaei-seo-for-woo' )
			);
		} elseif ( $needs_fix && $suggest ) {
			array_unshift(
				$score['tips'],
				__( 'نامک فعلی فارسی/نامعتبر است؛ می‌توانید فینگلیش را اعمال کنید یا «بدون تغییر نامک» را بزنید.', 'shojaei-seo-for-woo' )
			);
		}
		if ( $unique_note ) {
			$score['tips'][] = __( 'نامک پایه تکراری بود؛ مدل/رنگ/برند/SKU به نامک اضافه شد (و در صورت نیاز پسوند عددی) تا محصول دیگری بازنویسی نشود.', 'shojaei-seo-for-woo' );
		}
		if ( $title && preg_match( '/(?:^|[\s\x{200C}])(از|به|با|در|برای|و|یا|که|این|را)(?:$|[\s\x{200C}])/u', $title ) ) {
			$score['tips'][] = __( 'کلمات ربط رایج فارسی (از، با، در، برای، …) از نامک فینگلیش حذف می‌شوند.', 'shojaei-seo-for-woo' );
		}

		return array(
			'score'      => (int) $score['score'],
			'tone'       => $tone,
			'tips'       => $score['tips'],
			'suggest'    => $suggest,
			'slug'       => $slug,
			'based_on'   => $based_on,
			'needs_fix' => $needs_fix && $suggest && $suggest !== $slug,
		);
	}

	/**
	 * AJAX: live slug score + Finglish suggestion while editing product.
	 */
	public function ajax_live_preview(): void {
		check_ajax_referer( 'shojaei_seo_slug_live', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$slug    = '';
		if ( isset( $_POST['slug'] ) ) {
			$slug = sanitize_text_field( rawurldecode( (string) wp_unslash( $_POST['slug'] ) ) );
			$slug = trim( $slug, '/' );
		}

		wp_send_json_success( self::live_preview( $title, $slug, $post_id ) );
	}

	/**
	 * Metabox UI.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_metabox( $post ): void {
		$slug      = (string) $post->post_name;
		$title     = (string) $post->post_title;
		$preview   = self::live_preview( $title, $slug, (int) $post->ID );
		$published = 'publish' === $post->post_status;
		$keep_label = $published
			? __( 'بروزرسانی بدون تغییر نامک', 'shojaei-seo-for-woo' )
			: __( 'انتشار بدون تغییر نامک', 'shojaei-seo-for-woo' );
		?>
		<div
			class="shojaei-slug-box"
			dir="rtl"
			data-post-id="<?php echo esc_attr( (string) (int) $post->ID ); ?>"
			data-original-slug="<?php echo esc_attr( $slug ); ?>"
			data-was-published="<?php echo $published ? '1' : '0'; ?>"
			id="shojaei-slug-box"
		>
			<p>
				<strong><?php esc_html_e( 'امتیاز خوانایی نامک:', 'shojaei-seo-for-woo' ); ?></strong>
				<span class="shojaei-slug-score shojaei-tone-<?php echo esc_attr( $preview['tone'] ); ?>" id="shojaei-slug-score">
					<?php echo esc_html( (string) $preview['score'] ); ?>/100
				</span>
			</p>
			<ul class="shojaei-slug-tips" id="shojaei-slug-tips">
				<?php foreach ( $preview['tips'] as $tip ) : ?>
					<li><?php echo esc_html( $tip ); ?></li>
				<?php endforeach; ?>
			</ul>
			<div id="shojaei-slug-suggest-wrap" class="shojaei-slug-suggest-wrap" <?php echo $preview['suggest'] ? '' : 'hidden'; ?>>
				<p class="description" style="margin-bottom:6px;">
					<?php esc_html_e( 'پیشنهاد فینگلیش (لاتین) از عنوان:', 'shojaei-seo-for-woo' ); ?>
					<code dir="ltr" id="shojaei-slug-suggest"><?php echo esc_html( $preview['suggest'] ); ?></code>
				</p>
				<button type="button" class="button button-small button-primary" id="shojaei-slug-apply-suggest">
					<?php esc_html_e( 'اعمال فینگلیش روی نامک', 'shojaei-seo-for-woo' ); ?>
				</button>
			</div>
			<p class="shojaei-slug-keep-wrap" style="margin-top:12px;padding-top:10px;border-top:1px solid #dcdcde;">
				<label for="shojaei-seo-keep-slug" style="display:flex;gap:8px;align-items:flex-start;cursor:pointer;">
					<input type="checkbox" name="shojaei_seo_keep_slug" id="shojaei-seo-keep-slug" value="1" style="margin-top:2px;" />
					<span>
						<strong><?php echo esc_html( $keep_label ); ?></strong><br />
						<span class="description"><?php esc_html_e( 'اگر عمداً می‌خواهید نامک فارسی/فعلی حفظ شود، این گزینه را بزنید.', 'shojaei-seo-for-woo' ); ?></span>
					</span>
				</label>
			</p>
			<p class="description" id="shojaei-slug-live-hint" style="margin-top:8px;">
				<?php esc_html_e( 'پیش‌فرض: پیشنهاد فینگلیش. اگر نامک لاتینِ تمیز باشد دست نمی‌زنیم. با تغییر واقعی نامکِ منتشرشده، ۳۰۱ ساخته می‌شود.', 'shojaei-seo-for-woo' ); ?>
			</p>
			<?php if ( $published ) : ?>
				<div class="notice notice-warning inline shojaei-slug-warn" style="margin:8px 0;padding:8px;">
					<p style="margin:0;">
						<?php esc_html_e( 'اگر نامک محتوای منتشرشده را عوض کنید، افزونه در صورت فعال بودن ریدایرکت ۳۰۱ خودکار می‌سازد.', 'shojaei-seo-for-woo' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Admin JS for live slug score/suggest + publish warning.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! Damavand_Slug_Finglish::is_supported_type( (string) $screen->post_type ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_register_script( 'shojaei-seo-slug-live', false, array( 'jquery' ), DAMAVAND_SEO_VERSION, true );
		wp_enqueue_script( 'shojaei-seo-slug-live' );
		wp_localize_script(
			'shojaei-seo-slug-live',
			'shojaeiSlugLive',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shojaei_seo_slug_live' ),
				'i18n'    => array(
					'loading'       => __( 'در حال محاسبه…', 'shojaei-seo-for-woo' ),
					'submit_once'   => __( 'نامک به فینگلیش لاتین عوض می‌شود و برای محتوای منتشرشده ریدایرکت ۳۰۱ ساخته می‌شود. ادامه؟', 'shojaei-seo-for-woo' ),
					'submit_change' => __( 'نامک عوض می‌شود و لینک قدیم با ۳۰۱ حفظ می‌شود. ادامه؟', 'shojaei-seo-for-woo' ),
				),
			)
		);

		$slug_js = <<<'JS'
(function($){$(function(){
	var $box=$('#shojaei-slug-box');
	if(!$box.length){return;}
	var orig=String($box.data('original-slug')||'');
	var published=String($box.data('was-published'))==='1';
	var timer=null;
	var lastKey='';
	var applying=false;
	var confirmedOnce=false;

	function keepSlug(){ return $('#shojaei-seo-keep-slug').is(':checked'); }
	function isPersian(s){
		s=String(s||'');
		if(/[\u0600-\u06FF\u0750-\u077F]/.test(s)){return true;}
		if(s.indexOf('%')!==-1){
			try{
				var d=decodeURIComponent(s.replace(/\+/g,' '));
				if(d!==s && /[\u0600-\u06FF\u0750-\u077F]/.test(d)){return true;}
			}catch(e){}
		}
		return false;
	}
	function isCleanLatin(s){
		s=String(s||'').replace(/^\/+|\/+$/g,'');
		try{ s=decodeURIComponent(s); }catch(e){}
		return !!s && !isPersian(s) && /^[a-z0-9\-]+$/.test(s);
	}
	function readTitle(){
		var t=($('#title').val()||'').toString();
		if(!t && window.wp && wp.data && wp.data.select){
			try{
				var ed=wp.data.select('core/editor');
				if(ed && ed.getEditedPostAttribute){ t=ed.getEditedPostAttribute('title')||''; }
			}catch(e){}
		}
		return t;
	}
	function readSlug(){
		var s=($('#post_name').val()||'').toString();
		if(!s){ s=($('#editable-post-name-full').text()||$('#editable-post-name').text()||'').toString(); }
		if(!s && window.wp && wp.data && wp.data.select){
			try{
				var ed=wp.data.select('core/editor');
				if(ed && ed.getEditedPostAttribute){ s=ed.getEditedPostAttribute('slug')||''; }
			}catch(e){}
		}
		return String(s||'').replace(/^\/+|\/+$/g,'');
	}
	function setEditorSlug(slug){
		if(!slug || !/^[a-z0-9\-]+$/.test(slug)){return;}
		applying=true;
		var $pn=$('#post_name');
		if($pn.length){ $pn.val(slug); }
		if(window.wp && wp.data && wp.data.dispatch){
			try{ wp.data.dispatch('core/editor').editPost({slug:slug}); }catch(e){}
		}
		var $full=$('#editable-post-name-full');
		var $short=$('#editable-post-name');
		if($full.length){ $full.text(slug); }
		if($short.length){ $short.text(slug); }
		var $field=$('#new-post-slug');
		if($field.length){
			$field.val(slug);
		} else {
			var $edit=$('#edit-slug-buttons .edit-slug, #edit-slug-box .edit-slug').first();
			if($edit.length){
				$edit.trigger('click');
				window.setTimeout(function(){
					$('#new-post-slug').val(slug);
					var $ok=$('#edit-slug-buttons .save, #edit-slug-box .save').first();
					if($ok.length){ $ok.trigger('click'); }
					applying=false;
				}, 60);
				return;
			}
		}
		var $ok=$('#edit-slug-buttons .save, #edit-slug-box .save').first();
		if($ok.length && $field.length){ $ok.trigger('click'); }
		window.setTimeout(function(){ applying=false; }, 120);
	}
	function render(data){
		if(!data){return;}
		var $score=$('#shojaei-slug-score');
		$score.removeClass('shojaei-tone-safe shojaei-tone-warning shojaei-tone-error')
			.addClass('shojaei-tone-'+(data.tone||'error'))
			.text((data.score!=null?data.score:0)+'/100');
		var tips=data.tips||[];
		var $tips=$('#shojaei-slug-tips').empty();
		tips.forEach(function(t){ $tips.append($('<li/>').text(t)); });
		var suggest=String(data.suggest||'');
		if(suggest && !/^[a-z0-9\-]+$/.test(suggest)){ suggest=''; }
		var $wrap=$('#shojaei-slug-suggest-wrap');
		$('#shojaei-slug-suggest').text(suggest);
		if(suggest){ $wrap.prop('hidden',false); } else { $wrap.prop('hidden',true); }
	}
	function refresh(force){
		var title=readTitle();
		var slug=readSlug();
		var key=title+'|'+slug;
		if(!force && key===lastKey){return;}
		lastKey=key;
		$.post(shojaeiSlugLive.ajaxUrl,{
			action:'shojaei_seo_slug_live',
			nonce:shojaeiSlugLive.nonce,
			title:title,
			slug:slug,
			post_id: parseInt($box.data('post-id'),10)||0
		}).done(function(res){
			if(res && res.success && res.data){ render(res.data); }
		});
	}
	function schedule(){
		if(timer){ clearTimeout(timer); }
		timer=setTimeout(function(){ refresh(false); }, 350);
	}

	$(document).on('input keyup change', '#title, #post_name, #new-post-slug', schedule);
	$(document).on('change', '#shojaei-seo-keep-slug', function(){
		$('#shojaei-slug-apply-suggest').prop('disabled', keepSlug());
	});
	$(document).on('ajaxComplete', function(ev, xhr, settings){
		if(applying){return;}
		var u=(settings && settings.url)||'';
		var d=(settings && settings.data)||'';
		if((typeof d==='string' && d.indexOf('sample-permalink')!==-1) || (typeof u==='string' && u.indexOf('sample-permalink')!==-1)){
			schedule();
		}
	});
	var permalink=document.getElementById('edit-slug-box');
	if(permalink && window.MutationObserver){
		var mo=new MutationObserver(function(){ if(!applying){ schedule(); } });
		mo.observe(permalink,{childList:true,subtree:true,characterData:true});
	}

	$('#shojaei-slug-apply-suggest').on('click', function(e){
		e.preventDefault();
		if(keepSlug()){return;}
		var suggest=($('#shojaei-slug-suggest').text()||'').trim();
		if(!suggest || !/^[a-z0-9\-]+$/.test(suggest)){return;}
		setEditorSlug(suggest);
		refresh(true);
	});

	$('#post').on('submit', function(e){
		if(confirmedOnce || keepSlug() || !published){return;}
		var slug=readSlug();
		if(orig && slug && slug!==orig){
			var msg=isPersian(orig)
				? (shojaeiSlugLive.i18n.submit_once||'')
				: (shojaeiSlugLive.i18n.submit_change||'');
			if(msg && !window.confirm(msg)){
				e.preventDefault();
				return false;
			}
			confirmedOnce=true;
		}
	});

	refresh(true);
	setTimeout(function(){ refresh(true); }, 600);
});})(jQuery);
JS;
		wp_add_inline_script( 'shojaei-seo-slug-live', $slug_js );

		wp_enqueue_style(
			'damavand-fonts',
			DAMAVAND_SEO_URL . 'admin/css/damavand-fonts.css',
			array(),
			DAMAVAND_SEO_VERSION
		);
		wp_register_style( 'shojaei-seo-slug-admin', false, array( 'damavand-fonts' ), DAMAVAND_SEO_VERSION );
		wp_enqueue_style( 'shojaei-seo-slug-admin' );
		wp_add_inline_style(
			'shojaei-seo-slug-admin',
			'.shojaei-slug-score{font-weight:700;padding:2px 8px;border-radius:4px;display:inline-block}'
			. '.shojaei-slug-score.shojaei-tone-safe{background:#e8f5e9;color:#2e7d32}'
			. '.shojaei-slug-score.shojaei-tone-warning{background:#fff8e1;color:#ef6c00}'
			. '.shojaei-slug-score.shojaei-tone-error{background:#ffebee;color:#c62828}'
			. '.shojaei-slug-tips{margin:8px 0 0;padding-right:18px}'
			. '.shojaei-slug-tips li{margin:0 0 4px}'
			. '.shojaei-slug-suggest-wrap{margin-top:8px}'
			. '.shojaei-slug-suggest-wrap code{display:inline-block;margin-top:2px;word-break:break-all}'
		);
	}

}
