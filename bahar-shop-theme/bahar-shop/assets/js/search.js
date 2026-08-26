(function () {
  var input = document.getElementById('bahar-home-search');
  var results = document.getElementById('bahar-search-results');

  if (!input || !results || typeof baharSearch === 'undefined') {
    return;
  }

  var timer = null;
  var lastTerm = '';

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderItems(data) {
    if (!data.items || !data.items.length) {
      results.innerHTML = '<p class="home-search__empty">' + escapeHtml('محصولی پیدا نشد — عبارت دیگری امتحان کنید') + '</p>';
      results.hidden = false;
      return;
    }

    var html = '<ul class="home-search__list">';

    data.items.forEach(function (item) {
      var stock = item.stock === 'out'
        ? '<span class="home-search__stock home-search__stock--out">ناموجود</span>'
        : '';
      html += '<li><a class="home-search__item" href="' + escapeHtml(item.url) + '">';
      html += '<img src="' + escapeHtml(item.image) + '" alt="" width="52" height="52" loading="lazy" />';
      html += '<span class="home-search__item-body">';
      html += '<span class="home-search__item-title">' + escapeHtml(item.title) + '</span>';
      html += '<span class="home-search__item-price">' + item.price + stock + '</span>';
      html += '</span></a></li>';
    });

    if (data.shop) {
      html += '<li class="home-search__more"><a href="' + escapeHtml(data.shop) + '">مشاهده همه نتایج</a></li>';
    }

    html += '</ul>';
    results.innerHTML = html;
    results.hidden = false;
  }

  function fetchResults(term) {
    var url = baharSearch.ajaxUrl + '?action=bahar_product_search&nonce=' + encodeURIComponent(baharSearch.nonce) + '&term=' + encodeURIComponent(term);

    fetch(url, { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (payload) {
        if (payload && payload.success) {
          renderItems(payload.data);
        }
      })
      .catch(function () {
        results.hidden = true;
      });
  }

  input.addEventListener('input', function () {
    var term = input.value.trim();
    if (term === lastTerm) {
      return;
    }
    lastTerm = term;

    clearTimeout(timer);

    if (term.length < 2) {
      results.hidden = true;
      results.innerHTML = '';
      return;
    }

    timer = setTimeout(function () {
      fetchResults(term);
    }, 280);
  });

  document.addEventListener('click', function (event) {
    if (!event.target.closest('.home-search__box')) {
      results.hidden = true;
    }
  });
})();
