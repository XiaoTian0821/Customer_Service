<?php
/** admin/index.php – admin dashboard with KPIs + charts. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','staff','content_mgr');

$pageTitle = 'Dashboard';
$pdo = db();

// ---- KPIs ----
$kpis = [];
$kpis['total_customers']    = (int) $pdo->query('SELECT COUNT(*) c FROM users WHERE role = "customer"')->fetch()['c'];
$kpis['active_convs']       = (int) $pdo->query('SELECT COUNT(*) c FROM conversations WHERE status = "active"')->fetch()['c'];
$kpis['open_tickets']       = (int) $pdo->query('SELECT COUNT(*) c FROM support_tickets WHERE status IN ("open","pending","in_progress")')->fetch()['c'];
$kpis['escalated']          = (int) $pdo->query('SELECT COUNT(*) c FROM escalations WHERE status IN ("pending","taken_over")')->fetch()['c'];
$kpis['ai_resolved']        = (int) $pdo->query('SELECT COUNT(*) c FROM conversations WHERE status = "ai_resolved"')->fetch()['c'];
$kpis['human_resolved']     = (int) $pdo->query('SELECT COUNT(*) c FROM conversations WHERE status = "human_resolved"')->fetch()['c'];
$kpis['kb_articles']        = (int) $pdo->query("SELECT COUNT(*) c FROM knowledge_articles WHERE status = 'published'")->fetch()['c'];
$kpis['faq_items']          = (int) $pdo->query('SELECT COUNT(*) c FROM faq_items WHERE is_active = 1')->fetch()['c'];

// ---- Intent distribution ----
$intents = $pdo->query('SELECT COALESCE(intent,"general") intent, COUNT(*) c FROM conversations GROUP BY COALESCE(intent,"general") ORDER BY c DESC')->fetchAll();
$intentData = [];
foreach ($intents as $i) $intentData[] = ['label' => ucwords(str_replace('_',' ',$i['intent'])), 'value' => (int)$i['c']];

// ---- Sentiment distribution ----
$sents = $pdo->query('SELECT COALESCE(sentiment,"neutral") s, COUNT(*) c FROM conversations GROUP BY COALESCE(sentiment,"neutral") ORDER BY c DESC')->fetchAll();
$sentData = [];
$colors = ['positive'=>'#4ade80','neutral'=>'#94a3b8','negative'=>'#fbbf24','angry'=>'#f87171'];
foreach ($sents as $s) $sentData[] = ['label' => ucfirst($s['s']), 'value' => (int)$s['c'], 'color' => $colors[$s['s']] ?? '#94a3b8'];

// ---- Ticket status ----
$tstatus = $pdo->query('SELECT status, COUNT(*) c FROM support_tickets GROUP BY status')->fetchAll();
$tsData = [];
$tsColors = ['open'=>'#6366f1','pending'=>'#fbbf24','in_progress'=>'#38bdf8','resolved'=>'#4ade80','closed'=>'#94a3b8'];
foreach ($tstatus as $t) $tsData[] = ['label' => ucfirst(str_replace('_',' ',$t['status'])), 'value' => (int)$t['c'], 'color' => $tsColors[$t['status']] ?? '#94a3b8'];

// ---- AI performance ----
$aiPerf = [];
$aiPerf['total']    = (int) $pdo->query("SELECT COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai'")->fetch()['c'];
$aiPerf['fallback'] = (int) $pdo->query("SELECT COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai' AND ai_provider LIKE '%Fallback%'")->fetch()['c'];
$aiPerf['local']    = (int) $pdo->query("SELECT COUNT(*) c FROM conversation_messages WHERE sender_type = 'ai' AND ai_provider = 'Local Knowledge'")->fetch()['c'];
$aiPerf['avg_conf'] = $pdo->query("SELECT ROUND(AVG(confidence),2) a FROM conversation_messages WHERE sender_type = 'ai' AND confidence IS NOT NULL")->fetch()['a'] ?: '—';
$help = $pdo->query('SELECT COUNT(*) c FROM conversation_feedback WHERE rating = "helpful"')->fetch()['c'];
$notH = $pdo->query('SELECT COUNT(*) c FROM conversation_feedback WHERE rating = "not_helpful"')->fetch()['c'];
$totalFb = (int)$help + (int)$notH;
$aiPerf['helpful_pct'] = $totalFb > 0 ? round(100 * (int)$help / $totalFb) : 0;
$aiPerf['esc'] = $kpis['escalated'];

$nsAdminScripts = ['dashboard.js'];
$nsInlineScript = '
const NS_DASH = ' . json_encode([
    'intents' => $intentData,
    'sentiments' => $sentData,
    'ticketStatus' => $tsData,
], JSON_UNESCAPED_UNICODE) . ';
';

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-kpi-grid">
  <?php
  $kpiCards = [
    ['total_customers','Total Customers','fa-user','var(--ns-indigo)'],
    ['active_convs','Active Conversations','fa-message','var(--ns-cyan)'],
    ['open_tickets','Open Tickets','fa-ticket','var(--ns-amber)'],
    ['escalated','Escalated','fa-headset','var(--ns-rose)'],
    ['ai_resolved','AI Resolved','fa-robot','var(--ns-green)'],
    ['human_resolved','Human Resolved','fa-user-tie','var(--ns-violet)'],
    ['kb_articles','Knowledge Articles','fa-book','var(--ns-indigo)'],
    ['faq_items','FAQ Articles','fa-circle-question','var(--ns-cyan)'],
  ];
  foreach ($kpiCards as [$key,$label,$ico,$color]): ?>
    <div class="ns-kpi-card">
      <div class="ns-kpi-ico" style="--c:<?= $color ?>"><i class="fa-solid <?= $ico ?>"></i></div>
      <div class="ns-kpi-val"><?= $kpis[$key] ?></div>
      <div class="ns-kpi-label"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="ns-admin-row mt-4">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-diagram-project me-2"></i>Intent Distribution</h5></div>
    <canvas id="intentChart" height="220"></canvas>
  </div>
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-face-smile me-2"></i>Sentiment Distribution</h5></div>
    <canvas id="sentimentChart" height="220"></canvas>
  </div>
</div>

<div class="ns-admin-row mt-4">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-list-check me-2"></i>Ticket Status</h5></div>
    <canvas id="ticketChart" height="220"></canvas>
  </div>
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-microchip me-2"></i>AI Performance</h5></div>
    <div class="ns-ai-perf">
      <div class="ns-ai-row"><span>Total AI responses</span><strong><?= $aiPerf['total'] ?></strong></div>
      <div class="ns-ai-row"><span>Fallback AI</span><strong><?= $aiPerf['fallback'] ?></strong></div>
      <div class="ns-ai-row"><span>Local Knowledge</span><strong><?= $aiPerf['local'] ?></strong></div>
      <div class="ns-ai-row"><span>Avg confidence</span><strong><?= e($aiPerf['avg_conf']) ?>%</strong></div>
      <div class="ns-ai-row"><span>Helpful responses</span><strong><?= $aiPerf['helpful_pct'] ?>%</strong></div>
      <div class="ns-ai-row"><span>Escalations</span><strong><?= $aiPerf['esc'] ?></strong></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
