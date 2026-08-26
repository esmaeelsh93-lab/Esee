(function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('primary-nav');

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var isOpen = nav.classList.toggle('is-open');
      toggle.classList.toggle('is-open', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      toggle.setAttribute(
        'aria-label',
        isOpen ? 'بستن منوی اصلی' : 'باز کردن منوی اصلی'
      );
    });
  }

  var toolbarToggle = document.querySelector('.bahar-toolbar-toggle');
  var toolbar = document.getElementById('bahar-shop-toolbar');

  if (toolbarToggle && toolbar) {
    var mq = window.matchMedia('(max-width: 900px)');

    function syncToolbar() {
      if (mq.matches) {
        toolbar.classList.add('is-collapsed');
        toolbarToggle.setAttribute('aria-expanded', 'false');
      } else {
        toolbar.classList.remove('is-collapsed');
        toolbarToggle.setAttribute('aria-expanded', 'true');
      }
    }

    toolbarToggle.addEventListener('click', function () {
      var isOpen = !toolbar.classList.contains('is-collapsed');
      toolbar.classList.toggle('is-collapsed', isOpen);
      toolbarToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    });

    syncToolbar();
    if (mq.addEventListener) {
      mq.addEventListener('change', syncToolbar);
    } else if (mq.addListener) {
      mq.addListener(syncToolbar);
    }
  }
})();
