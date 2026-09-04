<?php
/**
 * SEO tools — schema validator + duplicate title/desc scan.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="shojaei-card" dir="rtl">
	<h2><?php esc_html_e( 'ابزار سئو', 'shojaei-seo-for-woo' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'اعتبارسنجی Schema محصول و یافتن عنوان/توضیح تکراری — بدون وابستگی به API گوگل.', 'shojaei-seo-for-woo' ); ?>
	</p>
</div>

<div class="shojaei-card" dir="rtl" id="damavand-schema-validator">
	<h3><?php esc_html_e( 'اعتبارسنج Schema محصول', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Graph واقعی Damavand را برای یک محصول می‌سازد و خطاهای رایج Google (IRT، نام سئو به‌جای نام محصول، ItemList روی محصول، Offer خالی) را گزارش می‌دهد.', 'shojaei-seo-for-woo' ); ?></p>
	<p>
		<label for="dm-tools-product-id"><?php esc_html_e( 'شناسه محصول', 'shojaei-seo-for-woo' ); ?></label>
		<input type="number" min="1" id="dm-tools-product-id" class="regular-text" style="width:140px;margin-inline:8px;" />
		<button type="button" class="button button-primary" id="dm-tools-validate-schema"><?php esc_html_e( 'اعتبارسنجی', 'shojaei-seo-for-woo' ); ?></button>
	</p>
	<div id="dm-tools-schema-result" style="margin-top:12px;" aria-live="polite"></div>
</div>

<div class="shojaei-card" dir="rtl" id="damavand-duplicate-scan">
	<h3><?php esc_html_e( 'اسکن عنوان و توضیح تکراری', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="description"><?php esc_html_e( 'تا ۵۰۰ محصول منتشرشده را برای SEO title / meta description تکراری بررسی می‌کند. تکرار = سیگنال cannibalization ضعیف.', 'shojaei-seo-for-woo' ); ?></p>
	<p>
		<button type="button" class="button button-primary" id="dm-tools-dup-scan"><?php esc_html_e( 'شروع اسکن', 'shojaei-seo-for-woo' ); ?></button>
	</p>
	<div id="dm-tools-dup-result" style="margin-top:12px;" aria-live="polite"></div>
</div>

<script>
(function($){
	function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);});}
	$('#dm-tools-validate-schema').on('click', function(){
		var id = parseInt($('#dm-tools-product-id').val(), 10) || 0;
		var $out = $('#dm-tools-schema-result').html('<p>…</p>');
		$.post(shojaeiSeoAdmin.ajaxUrl, {
			action: 'shojaei_seo_tools',
			nonce: shojaeiSeoAdmin.nonce,
			tools_action: 'validate_schema',
			product_id: id
		}).done(function(res){
			if(!res || !res.success){
				$out.html('<div class="notice notice-error inline"><p>'+esc((res&&res.data&&res.data.message)||'خطا')+'</p></div>');
				return;
			}
			var d = res.data || {};
			var html = '<div class="notice notice-'+(d.ok?'success':'error')+' inline"><p><strong>'+(d.ok?'سالم':'دارای خطا')+'</strong> — types: '+esc((d.types||[]).join(', '))+'</p></div>';
			if((d.errors||[]).length){
				html += '<ul style="color:#b32d2e;">'+(d.errors.map(function(e){return '<li>'+esc(e)+'</li>';}).join(''))+'</ul>';
			}
			if((d.warnings||[]).length){
				html += '<ul style="color:#996800;">'+(d.warnings.map(function(e){return '<li>'+esc(e)+'</li>';}).join(''))+'</ul>';
			}
			if(d.json){
				html += '<details style="margin-top:10px;"><summary>JSON-LD</summary><pre style="max-height:320px;overflow:auto;direction:ltr;text-align:left;white-space:pre-wrap;background:#0b1020;color:#eef2ff;padding:12px;border-radius:8px;">'+esc(d.json)+'</pre></details>';
			}
			$out.html(html);
		}).fail(function(){
			$out.html('<div class="notice notice-error inline"><p>خطای شبکه</p></div>');
		});
	});
	$('#dm-tools-dup-scan').on('click', function(){
		var $out = $('#dm-tools-dup-result').html('<p>در حال اسکن…</p>');
		$.post(shojaeiSeoAdmin.ajaxUrl, {
			action: 'shojaei_seo_tools',
			nonce: shojaeiSeoAdmin.nonce,
			tools_action: 'duplicate_scan',
			limit: 500
		}).done(function(res){
			if(!res || !res.success){
				$out.html('<div class="notice notice-error inline"><p>'+esc((res&&res.data&&res.data.message)||'خطا')+'</p></div>');
				return;
			}
			var d = res.data || {};
			var html = '<p>اسکن‌شده: <strong>'+esc(d.scanned)+'</strong> — عنوان تکراری: <strong>'+esc((d.titles||[]).length)+'</strong> — توضیح تکراری: <strong>'+esc((d.descriptions||[]).length)+'</strong></p>';
			function renderGroup(list, labelKey){
				if(!list || !list.length){ return '<p class="description">موردی یافت نشد.</p>'; }
				var h = '';
				list.slice(0, 40).forEach(function(g){
					h += '<div style="margin:10px 0;padding:10px;border:1px solid #dcdcde;border-radius:8px;">';
					h += '<strong>'+esc(g.count)+'×</strong> <code>'+esc((g.items[0]&&(g.items[0][labelKey]||g.key))||g.key)+'</code><ul>';
					(g.items||[]).forEach(function(it){
						h += '<li>#'+esc(it.id)+(it.edit?' — <a href="'+esc(it.edit)+'">ویرایش</a>':'')+'</li>';
					});
					h += '</ul></div>';
				});
				return h;
			}
			html += '<h4>عناوین تکراری</h4>'+renderGroup(d.titles,'title');
			html += '<h4>توضیحات تکراری</h4>'+renderGroup(d.descriptions,'desc');
			$out.html(html);
		}).fail(function(){
			$out.html('<div class="notice notice-error inline"><p>خطای شبکه</p></div>');
		});
	});
})(jQuery);
</script>
