(function () {
  var STORAGE_KEY = 'bahar-theme';
  var root = document.documentElement;

  function currentTheme() {
    return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    root.style.colorScheme = theme;

    document.querySelectorAll('.theme-toggle').forEach(function (btn) {
      var isDark = theme === 'dark';
      btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
      btn.setAttribute(
        'aria-label',
        isDark ? 'فعال‌سازی تم روشن' : 'فعال‌سازی تم تاریک'
      );
      btn.setAttribute('title', isDark ? 'تم روشن' : 'تم تاریک');
    });
  }

  function toggleTheme() {
    var next = currentTheme() === 'dark' ? 'light' : 'dark';

    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch (e) {}

    applyTheme(next);
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyTheme(currentTheme());

    document.querySelectorAll('.theme-toggle').forEach(function (btn) {
      btn.addEventListener('click', toggleTheme);
    });
  });
})();
