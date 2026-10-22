/* NovaServe – ticket page JS: toggle the new-ticket form, file preview. */
(function () {
  'use strict';
  var openBtn = document.getElementById('openNewTicket');
  var form = document.getElementById('newTicket');
  var closeBtn = document.getElementById('closeNewTicket');

  if (openBtn && form) {
    openBtn.addEventListener('click', function (e) {
      e.preventDefault();
      form.classList.remove('d-none');
      setTimeout(function () { form.scrollIntoView({ behavior: 'smooth' }); }, 50);
    });
  }
  if (closeBtn && form) {
    closeBtn.addEventListener('click', function (e) {
      e.preventDefault();
      form.classList.add('d-none');
    });
  }

  // File preview on the attachment input
  var fileInput = document.getElementById('attachFile');
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var f = fileInput.files[0];
      if (f && f.size > 5 * 1024 * 1024) {
        alert('File exceeds the 5 MB limit.');
        fileInput.value = '';
      }
    });
  }
})();
