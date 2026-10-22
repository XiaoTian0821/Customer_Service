<?php
/**
 * api/faq.php – search / list FAQs.
 * GET  ?q=&category=
 * POST JSON { q, category, limit }
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Authentication required.'], 401);

$pdo = db();
$q   = trim((string) ($_GET['q'] ?? ($_POST['q'] ?? '')));
$cat = (int) ($_GET['category'] ?? ($_POST['category'] ?? 0));

$sql = "SELECT f.id, f.question, f.answer, c.name AS category, f.view_count
        FROM faq_items f LEFT JOIN categories c ON c.id = f.category_id
        WHERE f.is_active = 1";
$params = [];
if ($q !== '') {
    $sql .= " AND (f.question LIKE :q OR f.answer LIKE :q)";
    $params[':q'] = "%{$q}%";
}
if ($cat > 0) {
    $sql .= " AND f.category_id = :c";
    $params[':c'] = $cat;
}
$sql .= " ORDER BY f.sort_order, f.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$faqs = $stmt->fetchAll();

// Bump view count for the top match when a query was used
if ($q !== '' && $faqs) {
    $pdo->prepare('UPDATE faq_items SET view_count = view_count + 1 WHERE id = :id')
        ->execute([':id' => (int) $faqs[0]['id']]);
}

json_out(['ok' => true, 'data' => $faqs]);
