<?php
/** admin/faqs.php – manage FAQ items (content_mgr + super_admin). */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_role('super_admin','content_mgr');

$pageTitle = 'FAQ Management';
$pdo = db();
$categories = $pdo->query("SELECT * FROM categories WHERE scope IN ('faq','both') AND is_active = 1 ORDER BY sort")->fetchAll();

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_faq') {
    verify_csrf();
    $q = trim($_POST['question'] ?? ''); $a = trim($_POST['answer'] ?? '');
    $cat = (int) ($_POST['category_id'] ?? 0);
    if (mb_strlen($q) < 5 || mb_strlen($a) < 5) flash('error', 'Question and answer are both required.');
    else {
        $pdo->prepare('INSERT INTO faq_items (category_id, question, answer, is_active) VALUES (:c, :q, :a, 1)')
            ->execute([':c' => $cat ?: null, ':q' => $q, ':a' => $a]);
        log_activity(current_user()['id'], 'faq_created', 'FAQ created: ' . $q, client_ip());
        flash('success', 'FAQ added.');
    }
    redirect('admin/faqs.php');
}

// Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_faq') {
    verify_csrf();
    $fid = (int) ($_POST['faq_id'] ?? 0);
    $q = trim($_POST['question'] ?? ''); $a = trim($_POST['answer'] ?? '');
    $cat = (int) ($_POST['category_id'] ?? 0); $active = !empty($_POST['is_active']) ? 1 : 0;
    $pdo->prepare('UPDATE faq_items SET category_id = :c, question = :q, answer = :a, is_active = :s WHERE id = :id')
        ->execute([':c' => $cat ?: null, ':q' => $q, ':a' => $a, ':s' => $active, ':id' => $fid]);
    log_activity(current_user()['id'], 'faq_updated', "FAQ {$fid} updated", client_ip(), $fid);
    flash('success', 'FAQ updated.');
    redirect('admin/faqs.php');
}

// Delete
if (($_GET['delete'] ?? '') !== '') {
    $fid = (int) $_GET['delete'];
    $pdo->prepare('DELETE FROM faq_items WHERE id = :id')->execute([':id' => $fid]);
    log_activity(current_user()['id'], 'faq_deleted', "FAQ {$fid} deleted", client_ip(), $fid);
    flash('success', 'FAQ deleted.');
    redirect('admin/faqs.php');
}

// Reorder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    verify_csrf();
    foreach ($_POST['sort'] as $fid => $order) {
        $pdo->prepare('UPDATE faq_items SET sort_order = :o WHERE id = :id')
            ->execute([':o' => (int)$order, ':id' => (int)$fid]);
    }
    flash('success', 'Order updated.');
    redirect('admin/faqs.php');
}

$faqs = $pdo->query('SELECT f.*, c.name AS category_name FROM faq_items f LEFT JOIN categories c ON c.id = f.category_id ORDER BY f.sort_order, f.id')->fetchAll();

require __DIR__ . '/../includes/admin_header.php';
?>

<div class="ns-admin-row">
  <div class="ns-card flex-fill">
    <div class="ns-card-head"><h5><i class="fa-solid fa-circle-question me-2"></i>FAQ Items</h5></div>
    <div class="table-responsive">
      <table class="table ns-admin-table">
        <thead><tr><th style="width:56px">Order</th><th>Question</th><th>Category</th><th>Views</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($faqs as $f): ?>
          <tr>
            <td class="ns-drag-handle"><i class="fa-solid fa-grip-vertical"></i></td>
            <td><strong><?= e(mb_substr($f['question'], 0, 60)) ?><?= mb_strlen($f['question'])>60?'…':'' ?></strong></td>
            <td><span class="ns-tag-mute"><?= e($f['category_name'] ?: 'General') ?></span></td>
            <td><small class="text-muted"><?= $f['view_count'] ?></small></td>
            <td><span class="ns-status-badge <?= $f['is_active'] ? 'is-ok' : '' ?>"><?= $f['is_active'] ? 'Active' : 'Hidden' ?></span></td>
            <td class="text-end">
              <button class="ns-admin-act" data-faq-id="<?= $f['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></button>
              <a class="ns-admin-act" href="?delete=<?= $f['id'] ?>" onclick="return confirm('Delete this FAQ?')" title="Delete"><i class="fa-solid fa-trash text-danger"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="ns-card mt-4">
  <div class="ns-card-head"><h5><i class="fa-solid fa-plus me-2"></i>Add FAQ</h5></div>
  <form method="post" class="row g-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_faq">
    <div class="col-md-6"><label class="form-label small">Question</label><input name="question" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label small">Category</label>
      <select name="category_id" class="form-select"><option value="">General</option>
      <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2 align-self-end"><button class="ns-btn-cta w-100" type="submit">Add</button></div>
    <div class="col-12"><label class="form-label small">Answer</label><textarea name="answer" rows="2" class="form-control" required></textarea></div>
  </form>
</div>

<!-- Edit modal -->
<div class="modal fade" id="editFaqModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Edit FAQ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="post" id="editFaqForm">
    <div class="modal-body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_faq">
      <input type="hidden" name="faq_id" id="editFaqId">
      <label class="form-label">Question</label><input name="question" id="editFaqQ" class="form-control" required>
      <label class="form-label d-block mt-2">Answer</label><textarea name="answer" id="editFaqA" rows="3" class="form-control" required></textarea>
      <div class="row g-2 mt-2">
        <div class="col-md-7"><label class="form-label small">Category</label>
          <select name="category_id" id="editFaqCat" class="form-select"><option value="">General</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-5 d-flex align-items-end"><label class="form-check-label"><input type="checkbox" name="is_active" id="editFaqActive" class="form-check-input me-1"> Active</label></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="ns-btn-ghost" data-bs-dismiss="modal">Cancel</button>
      <button class="ns-btn-cta" type="submit">Save changes</button></div>
  </form>
</div></div></div>

<!-- Reorder form (hidden, submitted when dragging) -->
<script>
const NS_FAQS = <?= json_encode(array_map(fn($f)=>['id'=>$f['id'],'question'=>$f['question'],'answer'=>$f['answer'],'cat'=>$f['category_id'],'active'=>(int)$f['is_active']],$faqs), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
