/* NovaServe – AI chat + voice interaction.
   Talks to api/chat.php. Implements voice input (Web Speech API) and
   voice output (SpeechSynthesis). Browser support varies; we degrade
   gracefully with a visible notice.
*/
(function () {
  'use strict';
  if (!window.NS_CHAT) return;

  var base = window.NS_CHAT.base || '';
  var csrf = window.NS_CHAT.csrf || '';
  var conversationId = window.NS_CHAT.conversationId || 0;

  var transcript = document.getElementById('chatTranscript');
  var input = document.getElementById('chatInput');
  var sendBtn = document.getElementById('sendBtn');
  var micBtn = document.getElementById('micBtn');
  var talkBtn = document.getElementById('talkHumanBtn');
  var clearBtn = document.getElementById('clearChatBtn');
  var newChatBtn = document.getElementById('newChatBtn');
  var convStatus = document.getElementById('convStatus');
  var playBtn = document.getElementById('playBtn');
  var voiceSelect = document.getElementById('voiceSelect');

  var supportsSpeech = ('speechSynthesis' in window);
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  var recognition = null;

  // ---- Voice output setup ----
  function loadVoices() {
    if (!supportsSpeech || !voiceSelect) return;
    var vs = speechSynthesis.getVoices();
    if (!vs.length) return;
    voiceSelect.innerHTML = '';
    var sel = new Option('Default voice', '');
    voiceSelect.add(sel);
    vs.forEach(function (v, i) {
      voiceSelect.add(new Option(v.name + ' (' + v.lang + ')', i));
    });
  }
  if (supportsSpeech) {
    loadVoices();
    speechSynthesis.onvoiceschanged = loadVoices;
  }

  function speak(text) {
    if (!supportsSpeech) { alert('Voice output is not supported in this browser.'); return; }
    speechSynthesis.cancel();
    var utter = new SpeechSynthesisUtterance(text);
    var idx = voiceSelect ? parseInt(voiceSelect.value, 10) : 0;
    var voices = speechSynthesis.getVoices();
    if (idx > 0 && voices[idx]) utter.voice = voices[idx];
    utter.rate = 1.0;
    utter.onend = function () { playBtn.disabled = true; };
    speechSynthesis.speak(utter);
    playBtn.disabled = false;
  }

  if (playBtn) {
    playBtn.addEventListener('click', function () {
      // Play the last AI message
      var lastAi = null;
      transcript.querySelectorAll('.ns-msgrow.is-ai .ns-msg').forEach(function (m) { lastAi = m; });
      if (lastAi) speak(lastAi.innerText);
    });
    playBtn.addEventListener('click', function () { if (!playBtn.disabled) playBtn.disabled = true; });
  }

  // ---- Voice input ----
  if (SR) {
    recognition = new SR();
    recognition.lang = (localStorage.getItem('ns_lang') || 'en') === 'ms' ? 'ms-MY' : ((localStorage.getItem('ns_lang') === 'zh') ? 'zh-CN' : 'en-US');
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;

    micBtn.addEventListener('click', function () {
      if (recognition && recognition.pending) { recognition.stop(); return; }
      recognition.onstart = function () { micBtn.classList.add('is-listening'); };
      recognition.onend = function () { micBtn.classList.remove('is-listening'); };
      recognition.onerror = function (e) {
        micBtn.classList.remove('is-listening');
        console.warn('Speech recognition error:', e.error);
      };
      recognition.onresult = function (ev) {
        var text = '';
        for (var i = ev.resultIndex; i < ev.results.length; i++) text += ev.results[i][0].transcript;
        input.value = text;
        autoResize();
      };
      recognition.start();
    });
  } else {
    if (micBtn) { micBtn.disabled = true; micBtn.title = 'Voice input not supported in this browser'; }
  }

  // ---- Auto-resize the input textarea ----
  function autoResize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 140) + 'px';
  }
  input.addEventListener('input', autoResize);
  input.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); sendMessage(); }
  });
  sendBtn.addEventListener('click', sendMessage);

  // ---- Suggestion chips ----
  transcript.querySelectorAll('.ns-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      input.value = chip.getAttribute('data-q') || chip.textContent;
      autoResize();
      sendMessage();
    });
  });

  // ---- Feedback buttons ----
  function bindFeedback(btn) {
    btn.addEventListener('click', function () {
      if (btn.classList.contains('is-on')) return;
      var msgId = btn.getAttribute('data-msg');
      var rating = btn.getAttribute('data-fb');
      fetch(base + '/api/feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
        body: 'message_id=' + encodeURIComponent(msgId) + '&rating=' + encodeURIComponent(rating) + '&csrf=' + encodeURIComponent(csrf)
      }).then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) {
            // mark all feedback buttons in this message
            var row = btn.closest('.ns-msgrow') || btn.closest('.ns-msg');
            row.querySelectorAll('.ns-fb').forEach(function (b) { b.classList.remove('is-on'); });
            btn.classList.add('is-on');
            if (rating === 'helpful') btn.innerHTML = '<i class="fa-solid fa-check"></i> Thanks!';
            else btn.innerHTML = '<i class="fa-solid fa-check"></i> Got it.';
          } else {
            alert('Could not record feedback: ' + (j.error || 'unknown error'));
          }
        })
        .catch(function () { alert('Network error. Try again.'); });
    });
  }
  document.querySelectorAll('.ns-fb').forEach(bindFeedback);

  // ---- Send message ----
  function sendMessage() {
    var text = input.value.trim();
    if (!text) return;
    input.value = '';
    autoResize();

    appendMessage('customer', text, null, null, false);
    var typing = appendTyping();

    var fd = new FormData();
    fd.append('message', text);
    fd.append('conversation_id', conversationId);
    fd.append('language', localStorage.getItem('ns_lang') || 'auto');
    fd.append('csrf', csrf);

    fetch(base + '/api/chat.php', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        typing.remove();
        if (!j.ok) {
          appendSystem('Something went wrong. Please try again.');
          return;
        }
        conversationId = j.conversation_id;
        var rep = j.reply;
        window._lastMsgId = j.message_id;
        var srcTitles = (rep.sources || []).map(function (s) { return s.title; }).filter(Boolean).join(' · ');
        appendMessage('ai', rep.answer, rep.confidence, rep.provider, rep.escalated, srcTitles, rep.suggestions, rep.intent);
        if (rep.escalated) {
          convStatus.textContent = '● Awaiting human support';
          convStatus.style.color = 'var(--ns-amber, #f59e0b)';
        }
        // enable play button
        if (playBtn) playBtn.disabled = false;
        setTimeout(function () { speakLast(); }, 50);
      })
      .catch(function () { typing.remove(); appendSystem('Network error. Please check your connection and try again.'); });
  }

  var lastAnswerEl = null;
  function speakLast() {
    if (lastAnswerEl && supportsSpeech) speak(lastAnswerEl.innerText);
  }

  function appendMessage(type, text, conf, provider, escalated, srcTitles, suggestions, intent) {
    var row = document.createElement('div');
    row.className = 'ns-msgrow ' + (type === 'customer' ? 'is-customer' : 'is-ai');

    var msg = document.createElement('div');
    msg.className = 'ns-msg ns-msg-' + (type === 'customer' ? 'customer' : 'ai');

    if (type === 'customer') {
      msg.textContent = text;
    } else {
      msg.innerHTML = formatAnswer(text);
      if (srcTitles) {
        var src = document.createElement('div');
        src.className = 'ns-src';
        src.innerHTML = '<i class="fa-solid fa-book-open me-1"></i> ' + escapeHtml(srcTitles);
        msg.appendChild(src);
      }
      var meta = document.createElement('div');
      meta.className = 'ns-msg-meta';
      meta.innerHTML = '<span class="ns-conf"><i class="fa-solid fa-gauge-simple"></i> Confidence ' + Math.round((conf || 0) * 100) + '%</span>'
        + (provider ? '<span class="ns-prov">' + escapeHtml(provider) + '</span>' : '');
      msg.appendChild(meta);

      var fb = document.createElement('div');
      fb.className = 'ns-feedback d-flex gap-2';
      fb.innerHTML = '<button class="ns-fb" data-fb="helpful"><i class="fa-solid fa-thumbs-up"></i> Helpful</button>'
        + '<button class="ns-fb" data-fb="not_helpful"><i class="fa-solid fa-thumbs-down"></i> Not helpful</button>';
      msg.appendChild(fb);
      bindFeedback(fb.children[0]); bindFeedback(fb.children[1]);

      if (escalated) {
        var esc = document.createElement('div');
        esc.className = 'ns-escalate-note';
        esc.innerHTML = '<i class="fa-solid fa-headset me-1"></i> We recommend a human. You can click "Talk to a human" above, or a support agent will join this chat.';
        esc.style.cssText = 'margin-top:.6rem;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:.6rem .8rem;border-radius:10px;font-size:.85rem;';
        msg.appendChild(esc);
      }

      lastAnswerEl = msg;
    }
    row.appendChild(msg);

    var time = document.createElement('small');
    time.className = 'ns-msg-time';
    time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    row.appendChild(time);

    // Attach the message-id to the AI feedback buttons for the server call
    if (type === 'ai' && window._lastMsgId) {
      row.querySelectorAll('.ns-fb').forEach(function (b) { b.setAttribute('data-msg', window._lastMsgId); });
    }

    transcript.appendChild(row);
    transcript.scrollTop = transcript.scrollHeight;
    return row;
  }

  function appendTyping() {
    var row = document.createElement('div');
    row.className = 'ns-msgrow is-ai';
    row.innerHTML = '<div class="ns-msg ns-msg-ai ns-typing"><span></span><span></span><span></span></div>';
    transcript.appendChild(row);
    transcript.scrollTop = transcript.scrollHeight;
    return row;
  }

  function appendSystem(text) {
    var row = document.createElement('div');
    row.className = 'ns-msgrow is-ai';
    row.innerHTML = '<div class="ns-msg ns-msg-ai" style="background:#fee2e2;color:#991b1b;border-color:#fca5a5;">' + escapeHtml(text) + '</div>';
    transcript.appendChild(row);
  }

  // ---- "Talk to a human" ----
  if (talkBtn) {
    talkBtn.addEventListener('click', function () {
      if (!conversationId) { sendMessage(); return; }
      var reason = prompt('Why do you want to talk to a human? (optional)');
      if (reason === null) return; // cancelled
      fetch(base + '/api/escalation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
        body: 'conversation_id=' + encodeURIComponent(conversationId) + '&reason=' + encodeURIComponent(reason || 'Customer requested human support.') + '&csrf=' + encodeURIComponent(csrf)
      }).then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) {
            appendSystem(j.message || 'We have notified our support team. A representative will take over shortly.');
            convStatus.textContent = '● Human requested';
            convStatus.style.color = 'var(--ns-amber, #f59e0b)';
            talkBtn.disabled = true;
            talkBtn.style.opacity = '.5';
          } else {
            alert('Could not escalate: ' + (j.error || 'unknown error'));
          }
        }).catch(function () { alert('Network error. Please try again.'); });
    });
  }

  // ---- Clear conversation / new chat ----
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (confirm('Clear this conversation and start a new one?')) window.location.href = base + '/chat.php';
    });
  }
  if (newChatBtn) {
    newChatBtn.addEventListener('click', function () { window.location.href = base + '/chat.php'; });
  }
  document.querySelectorAll('.ns-chat-side-item').forEach(function (item) {
    item.addEventListener('click', function () {
      var id = item.getAttribute('data-id');
      if (id) window.location.href = base + '/chat.php?id=' + id;
    });
  });

  // ---- Helpers ----
  function escapeHtml(s) {
    var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML;
  }
  function formatAnswer(text) {
    // Convert **bold** and newlines; keep it safe
    var safe = escapeHtml(text);
    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/\n/g, '<br>');
    return safe;
  }
})();
