/* NovaServe – analytics charts (Chart.js). Reads NS_AN + NS_INTENTS globals. */
(function () {
  'use strict';
  if (!window.Chart) return;

  if (window.NS_AN) {
    var trendEl = document.getElementById('aiTrend');
    if (trendEl) {
      new Chart(trendEl, {
        type: 'line',
        data: {
          labels: NS_AN.labels,
          datasets: [{
            label: 'AI responses',
            data: NS_AN.values,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,.15)',
            fill: true,
            tension: .35,
            pointRadius: 3,
            pointBackgroundColor: '#6366f1'
          }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
      });
    }
  }

  if (window.NS_INTENTS) {
    var intentEl = document.getElementById('intentTrend');
    if (intentEl) {
      new Chart(intentEl, {
        type: 'doughnut',
        data: {
          labels: NS_INTENTS.map(function (d) { return d.label; }),
          datasets: [{
            data: NS_INTENTS.map(function (d) { return d.value; }),
            backgroundColor: ['#6366f1','#06b6d4','#8b5cf6','#f59e0b','#10b981','#f43f5e','#94a3b8'],
            borderWidth: 2, borderColor: '#fff'
          }]
        },
        options: { cutout: '60%', plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
      });
    }
  }
})();
