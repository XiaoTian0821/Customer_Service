<?php
/** knowledge.php – search & browse knowledge-base articles. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Knowledge Base';
$q   = trim($_GET['q'] ?? '');
$cat = (int) ($_GET['category'] ?? 0);
$articleId = (int) ($_GET['article'] ?? 0);

$categories = db()->query("SELECT * FROM categories WHERE scope IN ('knowledge','both') AND is_active = 1 ORDER BY sort")->fetchAll();

if ($articleId > 0) {
    // Article detail
    $s = db()->prepare("SELECT a.*, c.name AS category_name FROM knowledge_articles a LEFT JOIN categories c ON c.id = a.category_id WHERE a.id = :id LIMIT 1");
    $s->execute([':id' => $articleId]);
    $article = $s->fetch();
    if ($article) {
        db()->prepare('UPDATE knowledge_articles SET view_count = view_count + 1 WHERE id = :id')->execute([':id' => $articleId]);
        // Related: same category, different id
        $rel = db()->prepare("SELECT a.id, a.title, a.summary FROM knowledge_articles a WHERE a.category_id = :c AND a.id != :id AND a.status = 'published' LIMIT 4");
        $rel->execute([':c' => $article['category_id'] ?: 0, ':id' => $articleId]);
        $related = $rel->fetchAll();
    } else {
        $article = null;
        $related = [];
    }
} else {
    $article = null; $related = [];
}

if (!$article) {
    $where = ["a.status = 'published'"];
    $params = [];
    if ($q !== '') { $where[] = '(a.title LIKE :q OR a.content LIKE :q OR a.tags LIKE :q)'; $params[':q'] = "%{$q}%"; }
    if ($cat > 0)   { $where[] = 'a.category_id = :c'; $params[':c'] = $cat; }
    $stmt = db()->prepare('SELECT a.*, c.name AS category_name FROM knowledge_articles a LEFT JOIN categories c ON c.id = a.category_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY a.view_count DESC, a.id ASC');
    $stmt->execute($params);
    $articles = $stmt->fetchAll();
} else {
    $articles = [];
}

$nsScripts = ['knowledge.js'];
require __DIR__ . '/includes/header.php';

if ($article): ?>
<div class="container ns-page">
  <nav class="ns-breadcrumb" aria-label="breadcrumb">
    <a href="<?= url('knowledge.php') ?>">Knowledge Base</a>
    <i class="fa-solid fa-chevron-right"></i>
    <span><?= e($article['title']) ?></span>
  </nav>
  <article class="ns-article">
    <div class="ns-article-meta">
      <?php if ($article['category_name']): ?><span class="ns-tag-mute"><?= e($article['category_name']) ?></span><?php endif; ?>
      <span class="text-muted"><i class="fa-regular fa-eye me-1"></i><?= $article['view_count'] ?> views</span>
    </div>
    <h1 class="ns-h1"><?= e($article['title']) ?></h1>
    <?php if ($article['summary']): ?><p class="ns-lead"><?= e($article['summary']) ?></p><?php endif; ?>
    <div class="ns-article-body">
      <?php // Render Markdown-lite: headers, bold, lists, links
        $content = (string) $article['content'];
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/^### (.*)/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^## (.*)/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^# (.*)/m', '<h2>$1</h2>', $content);
        echo $content; // internal content authored by staff
      ?>
    </div>
    <?php if ($article['tags']): ?>
      <div class="ns-tags d-flex flex-wrap gap-2 mt-3">
        <?php foreach (explode(',', $article['tags']) as $tag): if (trim($tag)): ?><span class="ns-chip-static"><?= e(trim($tag)) ?></span><?php endif; endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
  <?php if ($related): ?>
    <div class="ns-related mt-4">
      <h4 class="ns-h3">Related articles</h4>
      <div class="row g-3">
        <?php foreach ($related as $r): ?>
          <div class="col-md-6 col-lg-4"><a class="ns-related-card" href="<?= url('knowledge.php?article=' . $r['id']) ?>">
            <strong><?= e($r['title']) ?></strong><span><?= e($r['summary']) ?></span></a></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="container ns-page">
  <div class="ns-page-head">
    <div><h1 class="ns-h1">Knowledge Base</h1>
      <p class="ns-lead">Guides, policies, and troubleshooting steps. Search by keyword or browse by category.</p></div>
    <form class="ns-faq-search" method="get" action="">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search articles…" class="form-control" aria-label="Search knowledge base">
      <button class="ns-btn-cta" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
  </div>
  <div class="ns-cat-row">
    <a class="ns-cat-chip <?= $cat === 0 ? 'is-active' : '' ?>" href="<?= url('knowledge.php') ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a class="ns-cat-chip <?= $cat == $c['id'] ? 'is-active' : '' ?>" href="<?= url('knowledge.php?category=' . $c['id']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$articles): ?>
    <div class="ns-empty text-center py-5"><i class="fa-solid fa-box-open ns-empty-ico"></i>
      <p>No articles matched<?= $q ? ' "' . e($q) . '"' : '' ?>. Try a broader search.</p></div>
  <?php else: ?>
    <div class="ns-kg-grid">
      <?php foreach ($articles as $a): ?>
        <a class="ns-kg-card" href="<?= url('knowledge.php?article=' . $a['id']) ?>">
          <span class="ns-kg-tag"><?= e($a['category_name'] ?? 'General') ?></span>
          <strong class="ns-kg-title"><?= e($a['title']) ?></strong>
          <span class="ns-kg-summary"><?= e($a['summary']) ?></span>
          <small class="text-muted"><i class="fa-regular fa-eye me-1"></i><?= $a['view_count'] ?> views</small>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
