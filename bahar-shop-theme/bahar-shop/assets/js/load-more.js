/**
 * Load more products on shop / category archives.
 */
(function () {
  'use strict';
  if (!window.baharLoadMore) return;

  var cfg = window.baharLoadMore;
  var wrap = document.querySelector('[data-bahar-load-more]');
  if (!wrap) return;
  var btn = wrap.querySelector('.bahar-load-more__btn');
  if (!btn) return;

  var page = parseInt(cfg.page, 10) || 1;
  var maxPages = parseInt(cfg.maxPages, 10) || 1;
  if (page >= maxPages) {
    wrap.style.display = 'none';
    return;
  }

  btn.addEventListener('click', function () {
    if (btn.disabled) return;
    btn.disabled = true;
    var prev = btn.textContent;
    btn.textContent = cfg.loading || '...';

    var body = new FormData();
    body.append('action', 'bahar_load_more_products');
    body.append('nonce', cfg.nonce);
    body.append('page', String(page + 1));
    body.append('taxonomy', cfg.query.taxonomy || '');
    body.append('term_id', String(cfg.query.term_id || 0));
    body.append('orderby', cfg.query.orderby || '');
    body.append('min_price', cfg.query.min_price || '');
    body.append('max_price', cfg.query.max_price || '');
    body.append('s', cfg.query.s || '');

    fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json || !json.success || !json.data) throw new Error('bad');
        var list = document.querySelector('ul.products');
        if (list && json.data.html) {
          var tmp = document.createElement('div');
          tmp.innerHTML = '<ul>' + json.data.html + '</ul>';
          var items = tmp.querySelectorAll('li');
          items.forEach(function (li) { list.appendChild(li); });
        }
        page = json.data.page;
        maxPages = json.data.maxPages;
        if (!json.data.hasMore || page >= maxPages) {
          wrap.style.display = 'none';
        } else {
          btn.disabled = false;
          btn.textContent = cfg.label || prev;
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = prev;
      });
  });
})();
