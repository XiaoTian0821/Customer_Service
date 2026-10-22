/* NovaServe – admin conversations page interactions.
   The takeover form is a native POST; this script wires any client-side niceties.
*/
(function () {
  'use strict';
  // Highlight escalation rows that are pending
  document.querySelectorAll('.ns-esc-item').forEach(function (el) {
    if (/Pending/i.test(el.innerText)) el.style.background = '#fff7ed';
  });
})();
