/* NovaServe – home page interactions (FAQ accordions, search autocomplete). */
(function () {
  'use strict';
  // Smooth scroll for CTA links
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (ev) {
      var href = a.getAttribute('href');
      if (!href || href === '#') return;
      var target = document.getElementById(href.slice(1));
      if (target) { ev.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
    });
  });
})();
