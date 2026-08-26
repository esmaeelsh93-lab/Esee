(function () {
  if (typeof baharWishlist === 'undefined') return;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-bahar-wish]');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    var id = btn.getAttribute('data-bahar-wish');
    var body = new FormData();
    body.append('action', 'bahar_wishlist_toggle');
    body.append('nonce', baharWishlist.nonce);
    body.append('product_id', id);

    btn.disabled = true;

    fetch(baharWishlist.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (!res || !res.success) return;
        var added = !!res.data.added;
        btn.classList.toggle('is-active', added);
        btn.setAttribute('aria-pressed', added ? 'true' : 'false');
      })
      .finally(function () {
        btn.disabled = false;
      });
  });
})();
