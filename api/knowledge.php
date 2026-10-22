<?php
/**
 * api/knowledge.php – search / list knowledge articles.
 * GET  ?q=&category=
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Authentication required.'], 401);

$pdo = db();
$q   = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['category'] ?? 0);

$sql = "SELECT a.id, a.title, a.summary, c.name AS category, a.view_count
        FROM knowledge_articles a LEFT JOIN categories c ON c.id = a.category_id
        WHERE a.status = 'published'";
$params = [];
if ($q !== '') {
    $sql .= " AND (a.title LIKE :q OR a.content LIKE :q OR a.tags LIKE :q)";
    $params[':q'] = "%{$q}%";
}
if ($cat > 0) { $sql .= " AND a.category_id = :c"; $params[':c'] = $cat; }
$sql .= " ORDER BY a.view_count DESC, a.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$arts = $stmt->fetchAll();
json_out(['ok' => true, 'data' => $arts]);
