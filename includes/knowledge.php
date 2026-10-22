<?php
/**
 * includes/knowledge.php
 * RAG-style retrieval over the FAQ and knowledge-base tables.
 *
 * This is intentionally a lightweight, dependency-free ranker (keyword
 * overlap + scoring). It is fully isolated so it can be swapped for a real
 * vector-DB backend later without touching the rest of the app.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

class Knowledge
{
    /**
     * Search FAQs by keyword overlap.
     * @return array of rows: {id,title,category,question,answer,score}
     */
    public static function searchFaqs(PDO $pdo, string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') return [];

        $rows = $pdo->query(
            'SELECT f.id, f.category_id, f.question AS title, f.answer, c.name AS category_name
             FROM faq_items f LEFT JOIN categories c ON c.id = f.category_id
             WHERE f.is_active = 1'
        )->fetchAll();

        $q = mb_strtolower($query);
        $tokens = self::tokenize($q);
        $scored = [];
        foreach ($rows as $r) {
            $hay = mb_strtolower($r['title'] . ' ' . $r['answer']);
            $score = 0;
            foreach ($tokens as $tok) {
                if (strlen($tok) >= 3 && str_contains($hay, $tok)) {
                    $score += (str_contains($r['title'], $tok) ? 3 : 1);
                }
            }
            // boost for category match
            if ($r['category_name'] && str_contains(mb_strtolower($r['category_name']), $q)) $score += 2;
            if ($score > 0) $scored[] = ['row' => $r, 'score' => $score];
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $s) {
            $r = $s['row'];
            $out[] = [
                'id' => (int) $r['id'],
                'title' => $r['title'],
                'category' => $r['category_name'],
                'question' => $r['title'],
                'answer' => $r['answer'],
                'score' => $s['score'],
            ];
        }
        return $out;
    }

    /**
     * Search knowledge-base articles by keyword overlap.
     * @return array of rows: {id,title,category,summary,content,score}
     */
    public static function searchArticles(PDO $pdo, string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') return [];

        $rows = $pdo->query(
            "SELECT a.id, a.title, a.summary, a.content, c.name AS category_name
             FROM knowledge_articles a LEFT JOIN categories c ON c.id = a.category_id
             WHERE a.status = 'published'"
        )->fetchAll();

        $q = mb_strtolower($query);
        $tokens = self::tokenize($q);
        $scored = [];
        foreach ($rows as $r) {
            $hay = mb_strtolower($r['title'] . ' ' . $r['summary'] . ' ' . $r['content']);
            $score = 0;
            foreach ($tokens as $tok) {
                if (strlen($tok) >= 3 && str_contains($hay, $tok)) {
                    $score += (str_contains(mb_strtolower($r['title']), $tok) ? 4 : 1);
                }
            }
            if ($score > 0) $scored[] = ['row' => $r, 'score' => $score];
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as $s) {
            $r = $s['row'];
            $out[] = [
                'id' => (int) $r['id'],
                'title' => $r['title'],
                'category' => $r['category_name'],
                'summary' => $r['summary'],
                'content' => $r['content'],
                'score' => $s['score'],
            ];
        }
        return $out;
    }

    /**
     * Build a grounded context block for the AI prompt.
     * @return string '' when nothing relevant found
     */
    public static function buildContext(array $faqs, array $articles): string
    {
        $parts = [];
        if ($faqs) {
            $parts[] = "FAQ:\n" . implode("\n", array_map(
                fn($f) => "- Q: " . $f['question'] . "\n  A: " . $f['answer'],
                $faqs
            ));
        }
        if ($articles) {
            $parts[] = "Knowledge Articles:\n" . implode("\n", array_map(
                fn($a) => "- " . $a['title'] . " (" . ($a['category'] ?? 'General') . "): " .
                    mb_substr($a['summary'], 0, 220),
                $articles
            ));
        }
        return implode("\n\n", $parts);
    }

    /** Tokenise a lowercased query, dropping common stopwords. */
    private static function tokenize(string $q): array
    {
        $stop = ['the','and','for','with','this','that','what','when','where','who','how','why','a','an','i','my','me','do','does','can','you','your','is','are','in','on','to','of','was','were','or','but'];
        $words = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '' || in_array($w, $stop, true)) continue;
            $out[] = $w;
        }
        return $out;
    }
}
