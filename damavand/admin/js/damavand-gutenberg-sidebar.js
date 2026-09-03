(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.data) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;

	function readMeta(key, fallback) {
		try {
			var meta = wp.data.select('core/editor').getEditedPostAttribute('meta') || {};
			if (meta[key] != null && String(meta[key]).length) {
				return String(meta[key]);
			}
		} catch (e) {}
		return fallback || '';
	}

	function writeMeta(key, value) {
		try {
			var patch = {};
			patch[key] = value;
			wp.data.dispatch('core/editor').editPost({ meta: patch });
		} catch (e) {}
	}

	function SidebarPanel() {
		var cfg = window.damavandGbSeo || {};
		var meta = cfg.meta || {};
		var postId = wp.data.useSelect(function (select) {
			return select('core/editor').getCurrentPostId();
		}, []);

		var _title = useState(function () {
			return readMeta(meta.title, '');
		});
		var title = _title[0];
		var setTitle = _title[1];

		var _desc = useState(function () {
			return readMeta(meta.desc, '');
		});
		var desc = _desc[0];
		var setDesc = _desc[1];

		var _focus = useState(function () {
			return readMeta(meta.focus, '');
		});
		var focus = _focus[0];
		var setFocus = _focus[1];

		var _score = useState(0);
		var score = _score[0];
		var setScore = _score[1];

		var _tone = useState('bad');
		var tone = _tone[0];
		var setTone = _tone[1];

		var _read = useState(null);
		var readability = _read[0];
		var setReadability = _read[1];

		var _hint = useState(0);
		var hint = _hint[0];
		var setHint = _hint[1];

		var _busy = useState(false);
		var busy = _busy[0];
		var setBusy = _busy[1];

		var _schema = useState('');
		var schemaText = _schema[0];
		var setSchemaText = _schema[1];

		function livePayload() {
			var ed = wp.data.select('core/editor');
			return {
				action: 'damavand_seo_score_live',
				nonce: cfg.nonce,
				live: 1,
				post_id: postId,
				title: title,
				desc: desc,
				focus: focus,
				slug: ed.getEditedPostAttribute('slug') || '',
				content: (ed.getEditedPostContent() || '').slice(0, 20000)
			};
		}

		function refreshScore() {
			if (!postId || !cfg.ajaxUrl) {
				return;
			}
			setBusy(true);
			window.jQuery
				.post(cfg.ajaxUrl, livePayload())
				.done(function (res) {
					if (res && res.success && res.data) {
						setScore(parseInt(res.data.score, 10) || 0);
						setTone(res.data.tone || 'bad');
						setReadability(res.data.readability || null);
						setHint(parseInt(res.data.advisory_hint, 10) || 0);
					}
				})
				.always(function () {
					setBusy(false);
				});
		}

		useEffect(function () {
			var t = window.setTimeout(refreshScore, 1200);
			return function () {
				window.clearTimeout(t);
			};
		}, [title, desc, focus, postId]);

		function loadSchema() {
			if (!postId) {
				return;
			}
			window.jQuery
				.post(cfg.ajaxUrl, {
					action: 'damavand_schema_preview',
					nonce: cfg.nonce,
					post_id: postId
				})
				.done(function (res) {
					if (!res || !res.success) {
						setSchemaText((res && res.data && res.data.message) || cfg.i18n.error);
						return;
					}
					var lines = [];
					(res.data.blocks || []).forEach(function (b) {
						lines.push('/* ' + (b.kind || 'schema') + ' */');
						lines.push(b.json || '');
					});
					setSchemaText(lines.length ? lines.join('\n\n') : (res.data.message || ''));
				});
		}

		return el(
			'div',
			{ className: 'dm-gb-sidebar', dir: 'rtl' },
			el(
				PanelBody,
				{ title: cfg.i18n.tabBasic || 'سئو پایه', initialOpen: true },
				el(TextControl, {
					label: cfg.i18n.seoTitle,
					value: title,
					onChange: function (v) {
						setTitle(v);
						writeMeta(meta.title, v);
					}
				}),
				el(TextareaControl, {
					label: cfg.i18n.seoDesc,
					value: desc,
					onChange: function (v) {
						setDesc(v);
						writeMeta(meta.desc, v);
					}
				}),
				el(TextControl, {
					label: cfg.i18n.focus,
					value: focus,
					onChange: function (v) {
						setFocus(v);
						writeMeta(meta.focus, v);
					}
				})
			),
			el(
				PanelBody,
				{ title: cfg.i18n.tabAnalysis || 'تحلیل', initialOpen: true },
				el(
					'div',
					{ className: 'dm-score__gauge dm-score__gauge--' + tone, style: { marginBottom: '12px' } },
					el('span', { className: 'dm-score__num' }, String(score)),
					el('span', { className: 'dm-score__label' }, cfg.i18n.score || ''),
					busy ? el(Spinner) : null
				),
				hint > 0
					? el(
							'p',
							{ className: 'description' },
							'پیشنهاد بهبود: ~' + hint + ' امتیاز بالقوه'
					  )
					: null,
				readability && readability.sentence_count
					? el(
							'dl',
							{ className: 'dm-score__read-dl' },
							el('dt', null, 'میانگین کلمات در جمله'),
							el('dd', { dir: 'ltr' }, String(readability.avg_sentence)),
							el('dt', null, 'جملات بلند'),
							el('dd', { dir: 'ltr' }, String(readability.long_pct) + '%'),
							el('dt', null, 'جملات مجهول'),
							el('dd', { dir: 'ltr' }, String(readability.passive_count))
					  )
					: null,
				el(
					Button,
					{ variant: 'secondary', onClick: loadSchema },
					cfg.i18n.schemaLoad || 'JSON-LD'
				),
				schemaText
					? el('pre', { dir: 'ltr', style: { fontSize: '11px', overflow: 'auto', maxHeight: '240px' } }, schemaText)
					: null
			)
		);
	}

	registerPlugin('damavand-seo-sidebar', {
		render: function () {
			var cfg = window.damavandGbSeo || {};
			return el(
				wp.element.Fragment,
				null,
				el(PluginSidebarMoreMenuItem, { target: 'damavand-seo-panel' }, cfg.i18n.title || 'Damavand SEO'),
				el(
					PluginSidebar,
					{ name: 'damavand-seo-panel', title: cfg.i18n.title || 'Damavand SEO', icon: 'chart-line' },
					el(SidebarPanel)
				)
			);
		}
	});
})(window.wp);
