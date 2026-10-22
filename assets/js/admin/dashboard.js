/* NovaServe – dashboard charts (Chart.js). Reads NS_DASH global. */
(function () {
  'use strict';
  if (!window.NS_DASH || !window.Chart) return;

  function doughnut(id, data, colors) {
    var el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
      type: 'doughnut',
      data: {
        labels: data.map(function (d) { return d.label; }),
        datasets: [{
          data: data.map(function (d) { return d.value; }),
          backgroundColor: colors || data.map(function (d) { return d.color; }),
          borderWidth: 2, borderColor: '#fff'
        }]
      },
      options: {
        cutout: '62%',
        plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } }
      }
    });
  }

  // Intent: bar
  var intentEl = document.getElementById('intentChart');
  if (intentEl) {
    new Chart(intentEl, {
      type: 'bar',
      data: {
        labels: NS_DASH.intents.map(function (d) { return d.label; }),
        datasets: [{ label: 'Conversations', data: NS_DASH.intents.map(function (d) { return d.value; }),
          backgroundColor: '#6366f1', borderRadius: 8, maxBarThickness: 42 }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  }

  // Sentiment: doughnut
  doughnut('sentimentChart', NS_DASH.sentiments);

  // Ticket status: doughnut
  doughnut('ticketChart', NS_DASH.ticketStatus);
})();
