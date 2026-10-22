// NovaServe – customer-side JS for the FAQ page.
(function () {
  'use strict';
  // Expand/collapse long answers
  document.querySelectorAll('.ns-faq-card').forEach(function (card) {
    var answer = card.querySelector('.ns-faq-a');
    if (!answer) return;
    if (answer.scrollHeight > 120) {
      answer.style.maxHeight = '60px';
      answer.style.overflow = 'hidden';
      answer.style.position = 'relative';
      answer.style.cursor = 'pointer';
      var fade = document.createElement('div');
      fade.style.cssText = 'position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(transparent, #fff);';
      answer.appendChild(fade);
      answer.addEventListener('click', function () {
        var open = answer.getAttribute('data-open') === '1';
        if (open) {
          answer.style.maxHeight = '60px';
          answer.setAttribute('data-open', '0');
        } else {
          answer.style.maxHeight = answer.scrollHeight + 'px';
          answer.setAttribute('data-open', '1');
        }
        setTimeout(function(){ if (fade.parentNode === answer) fade.style.display = open ? 'block' : 'none'; }, 200);
      });
    }
  });
})();
