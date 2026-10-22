<?php
/** admin/analytics.php – AI performance + activity reporting (staff + super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','staff');

$pageTitle = 'Analytics';
$pdo = db();

// Date range filter
$days = (int) ($_GET['days'] ?? 30);
$days = in_array($days, [7, 14, 30, 90], true) ? $days : 30;
$since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

function agg(PDO $pdo, string $sql, array $params = []): array {
    $s = $pdo->prepare($sql); $s->execute($params);
    return $s->fetchAll();
}

$sinceParam = [':since' => $since];

$aiRequests   = (int) agg($pdo, "SELECT COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai' AND created_at >= :since", $sinceParam)[0]['c'];
$aiPrimary    = (int) agg($pdo, "SELECT COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai' AND ai_provider = 'Primary AI' AND created_at >= :since", $sinceParam)[0]['c'] ?? 0;
$aiFallback   = (int) agg($pdo, "SELECT COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai' AND ai_provider = 'Fallback AI' AND created_at >= :since", $sinceParam)[0]['c'] ?? 0;
$aiLocal      = (int) agg($pdo, "SELECT COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai' AND ai_provider = 'Local Knowledge' AND created_at >= :since", $sinceParam)[0]['c'] ?? 0;
$aiFailed     = max(0, $aiRequests - $aiPrimary - $aiFallback - $aiLocal); // fallback/local = degraded
$escalations  = (int) agg($pdo, "SELECT COUNT(*) c FROM escalations WHERE requested_at >= :since", $sinceParam)[0]['c'];
$avgConf      = agg($pdo, "SELECT ROUND(AVG(confidence)*100,0) a FROM conversation_messages WHERE sender_type = 'ai' AND confidence IS NOT NULL AND created_at >= :since", $sinceParam)[0]['a'] ?? 0;

$helpful  = (int) agg($pdo, "SELECT COUNT(*) c FROM conversation_feedback cf JOIN conversation_messages m ON m.id = cf.message_id WHERE cf.rating = 'helpful' AND m.created_at >= :since", $sinceParam)[0]['c'];
$notHelp  = (int) agg($pdo, "SELECT COUNT(*) c FROM conversation_feedback cf JOIN conversation_messages m ON m.id = cf.message_id WHERE cf.rating = 'not_helpful' AND m.created_at >= :since", $sinceParam)[0]['c'];
$fbTotal = $helpful + $notHelp;
$helpPct = $fbTotal > 0 ? round(100 * $helpful / $fbTotal) : 0;

$resolvedAi = (int) agg($pdo, "SELECT COUNT(*) c FROM conversations WHERE status = 'ai_resolved' AND updated_at >= :since", $sinceParam)[0]['c'];
$resolvedHm = (int) agg($pdo, "SELECT COUNT(*) c FROM conversations WHERE status = 'human_resolved' AND updated_at >= :since", $sinceParam)[0]['c'];
$allConv = (int) agg($pdo, "SELECT COUNT(*) c FROM conversations WHERE updated_at >= :since", $sinceParam)[0]['c'];
$aiRate = $allConv > 0 ? round(100 * $resolvedAi / $allConv) : 0;

// Daily trend (last 14 days)
$trend = agg($pdo, "SELECT DATE(created_at) d, COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai' AND created_at >= :since GROUP BY DATE(created_at) ORDER BY d", $sinceParam);
$labels = array_map(fn($r) => $r['d'], $trend);
$vals   = array_map(fn($r) => (int)$r['c'], $trend);

// Intent + sentiment breakdown in range
$intentRows  = agg($pdo, "SELECT COALESCE(intent,'general') intent, COUNT(*) c FROM conversations WHERE updated_at >= :since GROUP BY COALESCE(intent,'general') ORDER BY c DESC", $sinceParam);
$sentRows    = agg($pdo, "SELECT COALESCE(sentiment,'neutral') s, COUNT(*) c FROM conversations WHERE updated_at >= :since GROUP BY COALESCE(sentiment,'neutral') ORDER BY c DESC", $sinceParam);

$nsAdminScripts = ['analytics.js'];
$nsInlineScript = '
const NS_AN = { labels: ' . json_encode($labels) . ', values: ' . json_encode($vals) . ' };
';

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="fa-solid fa-filter me-2"></i>Time range</h5>
    <div class="d-flex gap-2">
      <?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days', 90 => '90 days'] as $d => $lbl): ?>
        <a class="ns-filter-chip <?= $days === $d ? 'is-active' : '' ?>" href="<?= url('admin/analytics.php?days=' . $d) ?>"><?= e($lbl) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="ns-kpi-grid mt-3">
  <?php
  $cards = [
    ['AI Requests', $aiRequests, 'fa-microchip', 'var(--ns-indigo)'],
    ['Primary AI', $aiPrimary, 'fa-bolt', 'var(--ns-green)'],
    ['Fallback AI', $aiFallback, 'fa-shield-halved', 'var(--ns-amber)'],
    ['Local Knowledge', $aiLocal, 'fa-book', 'var(--ns-cyan)'],
    ['Escalations', $escalations, 'fa-headset', 'var(--ns-rose)'],
    ['Avg Confidence', $avgConf . '%', 'fa-gauge-high', 'var(--ns-violet)'],
    ['Helpful Rate', $helpPct . '%', 'fa-thumbs-up', 'var(--ns-green)'],
    ['AI Resolution', $aiRate . '%', 'fa-robot', 'var(--ns-indigo)'],
  ];
  foreach ($cards as [$label, $val, $ico, $color]): ?>
    <div class="ns-kpi-card">
      <div class="ns-kpi-ico" style="--c:<?= $color ?>"><i class="fa-solid <?= $ico ?>"></i></div>
      <div class="ns-kpi-val"><?= e((string)$val) ?></div>
      <div class="ns-kpi-label"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="ns-admin-row mt-4">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-chart-line me-2"></i>AI Activity (daily)</h5></div>
    <canvas id="aiTrend" height="240"></canvas>
  </div>
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-diagram-project me-2"></i>Intents (range)</h5></div>
    <canvas id="intentTrend" height="240"></canvas>
  </div>
</div>

<div class="ns-admin-row mt-4">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-face-smile me-2"></i>Sentiment (range)</h5></div>
    <div class="ns-table-wrap">
      <table class="table ns-admin-table mb-0">
        <thead><tr><th>Sentiment</th><th class="text-end">Count</th><th class="text-end">Share</th></tr></thead>
        <tbody>
          <?php $tot = array_sum(array_map(fn($r) => (int)$r['c'], $sentRows)) ?: 1; ?>
          <?php foreach ($sentRows as $r): ?>
          <tr><td><span class="ns-sent-<?= e($r['s']) ?>"><?= e(ucfirst($r['s'])) ?></span></td>
              <td class="text-end"><?= $r['c'] ?></td>
              <td class="text-end"><?= round(100 * (int)$r['c'] / $tot) ?>%</td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-robot me-2"></i>Resolution Mix</h5></div>
    <div class="ns-ai-perf">
      <div class="ns-ai-row"><span>AI-resolved conversations</span><strong><?= $resolvedAi ?></strong></div>
      <div class="ns-ai-row"><span>Human-resolved conversations</span><strong><?= $resolvedHm ?></strong></div>
      <div class="ns-ai-row"><span>Feedback (helpful)</span><strong><?= $helpful ?> / <?= $fbTotal ?></strong></div>
      <div class="ns-ai-row"><span>AI resolution rate</span><strong><?= $aiRate ?>%</strong></div>
    </div>
  </div>
</div>

<script>
const NS_INTENTS = <?= json_encode(array_map(fn($r)=>['label'=>ucwords(str_replace('_',' ',$r['intent'])),'value'=>(int)$r['c']],$intentRows), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
