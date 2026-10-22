/* NovaServe – shared admin console behaviour. */
(function () {
  'use strict';

  // Confirm destructive link actions (those with onclick already confirm).
  // Provide a global helper.
  window.nsConfirm = function (msg, onYes) {
    if (window.confirm(msg)) onYes();
  };

  // Auto-dismiss flash alerts after 5s
  document.querySelectorAll('.alert-dismissible').forEach(function (a) {
    setTimeout(function () {
      var b = a.querySelector('.btn-close');
      if (b) b.click();
    }, 6000);
  });

  // Simple confirmation for data-confirm attributes
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    var orig = el.onclick;
    el.addEventListener('click', function (ev) {
      if (!window.confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
    });
  });
})();
