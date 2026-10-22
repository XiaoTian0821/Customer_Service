<?php
/** faq.php – browse / search / filter FAQs. */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Frequently Asked Questions';
$q  = trim($_GET['q'] ?? '');
$cat = (int) ($_GET['category'] ?? 0);

$categories = db()->query("SELECT * FROM categories WHERE scope IN ('faq','both') AND is_active = 1 ORDER BY sort")->fetchAll();

// Build query
$where = ['f.is_active = 1'];
$params = [];
if ($q !== '') { $where[] = '(f.question LIKE :q OR f.answer LIKE :q)'; $params[':q'] = "%{$q}%"; }
if ($cat > 0)   { $where[] = 'f.category_id = :c'; $params[':c'] = $cat; }
$sql = 'SELECT f.*, c.name AS category_name FROM faq_items f LEFT JOIN categories c ON c.id = f.category_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY f.sort_order ASC, f.id ASC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$faqs = $stmt->fetchAll();

// Mark views for the first item if it's a detail view
if (!empty($_GET['view'])) {
    db()->prepare('UPDATE faq_items SET view_count = view_count + 1 WHERE id = :id')
       ->execute([':id' => (int) $_GET['view']]);
}

$nsScripts = ['faq.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="container ns-page">
  <div class="ns-page-head">
    <div>
      <h1 class="ns-h1">Frequently Asked Questions</h1>
      <p class="ns-lead">Quick answers to the questions our customers ask most. Search or filter by category.</p>
    </div>
    <form class="ns-faq-search" method="get" action="">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search FAQs…" class="form-control" aria-label="Search FAQs">
      <button class="ns-btn-cta" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
  </div>

  <div class="ns-cat-row">
    <a class="ns-cat-chip <?= $cat === 0 ? 'is-active' : '' ?>" href="<?= url('faq.php') ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a class="ns-cat-chip <?= $cat == $c['id'] ? 'is-active' : '' ?>" href="<?= url('faq.php?category=' . $c['id']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$faqs): ?>
    <div class="ns-empty text-center py-5"><i class="fa-solid fa-magnifying-glass-slash ns-empty-ico"></i>
      <p>No FAQs matched<?= $q ? ' "' . e($q) . '"' : '' ?>. Try a different search term.</p></div>
  <?php else: ?>
    <div class="ns-faq-grid">
      <?php foreach ($faqs as $f): ?>
        <div class="ns-faq-card" data-faq="<?= $f['id'] ?>">
          <div class="ns-faq-q d-flex gap-2">
            <i class="fa-solid fa-circle-question ns-faq-ico"></i>
            <strong><?= e($f['question']) ?></strong>
          </div>
          <div class="ns-faq-a"><?= e($f['answer']) ?></div>
          <div class="ns-faq-foot">
            <?php if ($f['category_name']): ?><span class="ns-tag-mute"><?= e($f['category_name']) ?></span><?php endif; ?>
            <small class="text-muted ms-auto"><?= $f['view_count'] ?> views</small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
