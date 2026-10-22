<?php
/** admin/knowledge.php – manage knowledge-base articles (content_mgr + super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','content_mgr');

$pageTitle = 'Knowledge Base';
$pdo = db();
$categories = $pdo->query("SELECT * FROM categories WHERE scope IN ('knowledge','both') AND is_active = 1 ORDER BY sort")->fetchAll();

// Detail / edit view
$viewId = (int) ($_GET['view'] ?? 0);

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_article') {
    verify_csrf();
    $title = trim($_POST['title'] ?? ''); $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? ''); $cat = (int) ($_POST['category_id'] ?? 0);
    $tags = trim($_POST['tags'] ?? ''); $status = in_array($_POST['status'] ?? '', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
    if (mb_strlen($title) < 3 || mb_strlen($content) < 10) flash('error', 'Title and content are required.');
    else {
        $pdo->prepare('INSERT INTO knowledge_articles (category_id, title, summary, content, tags, status, author_id)
                       VALUES (:c, :t, :s, :ct, :tg, :st, :a)')
            ->execute([':c' => $cat ?: null, ':t' => $title, ':s' => $summary, ':ct' => $content, ':tg' => $tags, ':st' => $status, ':a' => current_user()['id']]);
        log_activity(current_user()['id'], 'kb_created', 'Article created: ' . $title, client_ip());
        flash('success', 'Article created.');
    }
    redirect('admin/knowledge.php');
}

// Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_article') {
    verify_csrf();
    $aid = (int) ($_POST['article_id'] ?? 0);
    $title = trim($_POST['title'] ?? ''); $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? ''); $cat = (int) ($_POST['category_id'] ?? 0);
    $tags = trim($_POST['tags'] ?? ''); $status = in_array($_POST['status'] ?? '', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
    $pdo->prepare('UPDATE knowledge_articles SET category_id = :c, title = :t, summary = :s, content = :ct, tags = :tg, status = :st WHERE id = :id')
        ->execute([':c' => $cat ?: null, ':t' => $title, ':s' => $summary, ':ct' => $content, ':tg' => $tags, ':st' => $status, ':id' => $aid]);
    log_activity(current_user()['id'], 'kb_updated', "Article {$aid} updated", client_ip(), $aid);
    flash('success', 'Article updated.');
    redirect('admin/knowledge.php?view=' . $aid);
}

// Delete
if (($_GET['delete'] ?? '') !== '') {
    $aid = (int) $_GET['delete'];
    $pdo->prepare('DELETE FROM knowledge_articles WHERE id = :id')->execute([':id' => $aid]);
    log_activity(current_user()['id'], 'kb_deleted', "Article {$aid} deleted", client_ip(), $aid);
    flash('success', 'Article deleted.');
    redirect('admin/knowledge.php');
}

// Edit form data
$editArticle = null;
if ($viewId > 0) {
    $s = $pdo->prepare('SELECT * FROM knowledge_articles WHERE id = :id');
    $s->execute([':id' => $viewId]);
    $editArticle = $s->fetch() ?: null;
    if (!$editArticle) { $viewId = 0; }
}

$listSql = "SELECT a.*, c.name AS category_name FROM knowledge_articles a LEFT JOIN categories c ON c.id = a.category_id";
if ($viewId > 0) { $listSql .= " WHERE a.id != " . $viewId; }
$listSql .= " ORDER BY a.updated_at DESC LIMIT 200";
$articles = $pdo->query($listSql)->fetchAll();

require __DIR__ . '/../includes/admin_header.php';

if ($editArticle): ?>
<div class="ns-admin-row">
  <nav class="ns-breadcrumb"><a href="<?= url('admin/knowledge.php') ?>">Knowledge Base</a><i class="fa-solid fa-chevron-right"></i><span><?= e($editArticle['title']) ?></span></nav>
  <div class="col-12 col-xl-8">
    <div class="ns-card">
      <div class="ns-card-head"><h5><i class="fa-solid fa-pen me-2"></i>Edit Article</h5>
        <span class="ns-status-badge ms-auto"><?= e($editArticle['status']) ?></span></div>
      <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_article"><input type="hidden" name="article_id" value="<?= $editArticle['id'] ?>">
        <div class="col-12"><label class="form-label small">Title</label><input name="title" class="form-control" value="<?= e($editArticle['title']) ?>" required></div>
        <div class="col-12"><label class="form-label small">Summary</label><input name="summary" class="form-control" value="<?= e($editArticle['summary']) ?>"></div>
        <div class="col-md-4"><label class="form-label small">Category</label>
          <select name="category_id" class="form-select"><option value="">General</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$editArticle['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label small">Tags (comma)</label><input name="tags" class="form-control" value="<?= e($editArticle['tags']) ?>"></div>
        <div class="col-md-4"><label class="form-label small">Status</label>
          <select name="status" class="form-select"><option value="draft" <?= $editArticle['status']==='draft'?'selected':'' ?>>Draft</option><option value="published" <?= $editArticle['status']==='published'?'selected':'' ?>>Published</option><option value="archived" <?= $editArticle['status']==='archived'?'selected':'' ?>>Archived</option></select></div>
        <div class="col-12"><label class="form-label small">Content (Markdown-lite: **bold**, #/##/### headers)</label>
          <textarea name="content" rows="10" class="form-control" style="font-family:monospace;font-size:.85rem"><?= e($editArticle['content']) ?></textarea></div>
        <div class="col-12"><button class="ns-btn-cta" type="submit">Save article</button></div>
      </form>
    </div>
  </div>
</div>
<?php else: ?>
<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-book me-2"></i>Articles</h5></div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($articles as $a): ?>
          <tr>
            <td><strong><?= e(mb_substr($a['title'], 0, 50)) ?><?= mb_strlen($a['title'])>50?'…':'' ?></strong></td>
            <td><span class="ns-tag-mute"><?= e($a['category_name'] ?: 'General') ?></span></td>
            <td><span class="ns-status-badge"><?= e($a['status']) ?></span></td>
            <td><small class="text-muted"><?= $a['view_count'] ?></small></td>
            <td><small class="text-muted"><?= time_ago($a['updated_at']) ?></small></td>
            <td class="text-end">
              <a class="ns-admin-act" href="?view=<?= $a['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
              <a class="ns-admin-act" href="?delete=<?= $a['id'] ?>" onclick="return confirm('Delete this article?')" title="Delete"><i class="fa-solid fa-trash text-danger"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="ns-card mt-4">
  <div class="ns-card-head"><h5><i class="fa-solid fa-plus me-2"></i>New Article</h5></div>
  <form method="post" class="row g-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_article">
    <div class="col-md-6"><label class="form-label small">Title</label><input name="title" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label small">Category</label>
      <select name="category_id" class="form-select"><option value="">General</option>
      <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small">Status</label>
      <select name="status" class="form-select"><option value="draft">Draft</option><option value="published">Published</option></select></div>
    <div class="col-md-6"><label class="form-label small">Summary</label><input name="summary" class="form-control"></div>
    <div class="col-md-6"><label class="form-label small">Tags</label><input name="tags" class="form-control" placeholder="router, billing"></div>
    <div class="col-12"><label class="form-label small">Content</label><textarea name="content" rows="4" class="form-control" required></textarea></div>
    <div class="col-12"><button class="ns-btn-cta" type="submit">Create article</button></div>
  </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
