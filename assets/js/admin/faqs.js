/* NovaServe – FAQ admin: modal editing + drag reorder.
   Reads NS_FAQS global array.
*/
(function () {
  'use strict';
  var faqs = window.NS_FAQS || [];
  if (!faqs.length) return;

  var modalEl = document.getElementById('editFaqModal');
  var modal = modalEl ? new bootstrap.Modal(modalEl) : null;

  document.querySelectorAll('[data-faq-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = parseInt(btn.getAttribute('data-faq-id'), 10);
      var item = faqs.find(function (f) { return f.id === id; });
      if (!item) return;
      document.getElementById('editFaqId').value = item.id;
      document.getElementById('editFaqQ').value = item.question;
      document.getElementById('editFaqA').value = item.answer;
      document.getElementById('editFaqCat').value = item.cat || '';
      document.getElementById('editFaqActive').checked = !!item.active;
      if (modal) modal.show();
    });
  });

  // ---- Drag-to-reorder (updates sort_order via a hidden form) ----
  var tbody = document.querySelector('.ns-admin-table tbody');
  var form = document.createElement('form');
  form.method = 'post';
  form.innerHTML = '';
  form.appendChild(document.createElement('input')).name = 'csrf';
  document.querySelector('.ns-admin-table').closest('.ns-card').appendChild(form);

  var dragId = null;
  tbody.querySelectorAll('tr').forEach(function (row) {
    var handle = row.querySelector('.ns-drag-handle');
    if (handle) {
      handle.setAttribute('draggable', 'true');
      handle.addEventListener('dragstart', function (e) {
        dragId = row.querySelector('td a, td button') ? row.querySelector('[data-faq-id]').getAttribute('data-faq-id') : null;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragId);
        row.style.opacity = '.4';
      });
      handle.addEventListener('dragend', function () { row.style.opacity = '1'; dragId = null; commitReorder(); });
    }
    row.addEventListener('dragover', function (e) { e.preventDefault(); });
    row.addEventListener('drop', function (e) {
      e.preventDefault();
      var from = e.dataTransfer.getData('text/plain');
      if (!from) return;
      var fromRow = Array.prototype.find.call(tbody.querySelectorAll('tr'), function (r) { return r.querySelector('[data-faq-id]').getAttribute('data-faq-id') === from; });
      if (fromRow && fromRow !== row) {
        if (fromRow.compareDocumentPosition(row) & Node.DOCUMENT_POSITION_PRECEDING) row.after(fromRow);
        else row.before(fromRow);
      }
      commitReorder();
    });
  });

  function commitReorder() {
    var order = {};
    var i = 0;
    tbody.querySelectorAll('[data-faq-id]').forEach(function (el) { order[el.getAttribute('data-faq-id')] = i++; });
    var f = new FormData();
    f.append('action', 'reorder');
    f.append('csrf', (window.NS_CHAT && window.NS_CHAT.csrf) || getCsrf());
    Object.keys(order).forEach(function (id) { f.append('sort[' + id + ']', order[id]); });
    fetch(window.location.pathname, { method: 'POST', body: f })
      .then(function (r) { location.reload(); })
      .catch(function () { alert('Could not save order. Please use the sort field instead.'); });
  }

  function getCsrf() {
    var inp = document.querySelector('input[name=csrf]');
    return inp ? inp.value : '';
  }
})();
