/* NovaServe – shared front-end behaviour (customer portal + auth pages). */
(function () {
  'use strict';

  // ---- Language selector: persist to session via the server on next page
  // load. We store the preference in localStorage so it survives reloads and
  // is picked up by PHP via a hidden form field on the next POST, OR we send
  // it with an AJAX call if a language endpoint exists.
  var langSel = document.getElementById('langSelect');
  if (langSel) {
    var saved = localStorage.getItem('ns_lang') || 'en';
    langSel.value = saved;
    langSel.addEventListener('change', function () {
      localStorage.setItem('ns_lang', langSel.value);
      // Persist server-side (best effort). Reload to apply.
      fetch(NS_BASE + 'api/lang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': (window.NS_CHAT ? window.NS_CHAT.csrf : '') },
        body: 'language=' + encodeURIComponent(langSel.value) + (window.NS_CHAT ? '&csrf=' + encodeURIComponent(window.NS_CHAT.csrf) : '')
      }).then(function (r) { return r.json(); }).then(function (j) {
        if (j && j.ok) window.location.reload();
      }).catch(function () { /* ignore; preference still saved locally */ });
    });
  }

  // ---- Password show/hide toggles
  document.querySelectorAll('.ns-pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var inp = btn.parentElement.querySelector('input');
      var eye = btn.querySelector('i');
      if (inp.type === 'password') { inp.type = 'text'; eye.className = 'fa-solid fa-eye-slash'; }
      else { inp.type = 'password'; eye.className = 'fa-solid fa-eye'; }
    });
  });

  // ---- Animate KPI counters on the home page
  function animateCount(el) {
    var target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
    if (!target) return;
    var start = 0; var step = Math.max(1, Math.round(target / 40));
    var timer = setInterval(function () {
      start += step;
      if (start >= target) { start = target; clearInterval(timer); }
      el.textContent = start + (target > 99 ? '+' : '');
    }, 20);
  }
  document.querySelectorAll('[data-count-to]').forEach(animateCount);
})();
