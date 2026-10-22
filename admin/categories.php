<?php
/** admin/categories.php – manage categories (content_mgr + super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','content_mgr');

$pageTitle = 'Categories';
$pdo = db();

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_category') {
    verify_csrf();
    $name = trim($_POST['name'] ?? ''); $scope = in_array($_POST['scope'] ?? '', ['faq','knowledge','both'], true) ? $_POST['scope'] : 'both';
    if (mb_strlen($name) < 2) flash('error', 'Category name required.');
    else {
        try {
            $pdo->prepare('INSERT INTO categories (name, scope) VALUES (:n, :s)')->execute([':n' => $name, ':s' => $scope]);
            log_activity(current_user()['id'], 'category_created', "Category created: {$name}", client_ip());
            flash('success', 'Category added.');
        } catch (Throwable $e) { flash('error', 'A category with that name already exists.'); }
    }
    redirect('admin/categories.php');
}

// Delete
if (($_GET['delete'] ?? '') !== '') {
    $cid = (int) $_GET['delete'];
    $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $cid]);
    log_activity(current_user()['id'], 'category_deleted', "Category {$cid} deleted", client_ip(), $cid);
    flash('success', 'Category deleted. Linked items now have no category.');
    redirect('admin/categories.php');
}

$categories = $pdo->query('SELECT c.*,
    (SELECT COUNT(*) FROM faq_items f WHERE f.category_id = c.id) AS faq_count,
    (SELECT COUNT(*) FROM knowledge_articles a WHERE a.category_id = c.id) AS kb_count
    FROM categories c ORDER BY c.sort, c.name')->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-tags me-2"></i>Categories</h5></div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th>Name</th><th>Scope</th><th>FAQs</th><th>Articles</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?></strong></td>
            <td><span class="ns-tag-mute"><?= e(ucfirst($c['scope'])) ?></span></td>
            <td><small class="text-muted"><?= $c['faq_count'] ?></small></td>
            <td><small class="text-muted"><?= $c['kb_count'] ?></small></td>
            <td class="text-end"><a class="ns-admin-act" href="?delete=<?= $c['id'] ?>" onclick="return confirm('Delete this category? Items will be uncategorised.')"><i class="fa-solid fa-trash text-danger"></i></a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="ns-card mt-4">
  <div class="ns-card-head"><h5><i class="fa-solid fa-plus me-2"></i>Add Category</h5></div>
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_category">
    <div class="col-md-6"><label class="form-label small">Name</label><input name="name" class="form-control" required></div>
    <div class="col-md-3"><label class="form-label small">Scope</label>
      <select name="scope" class="form-select"><option value="both">Both</option><option value="faq">FAQ</option><option value="knowledge">Knowledge</option></select></div>
    <div class="col-md-3"><button class="ns-btn-cta" type="submit">Add category</button></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
