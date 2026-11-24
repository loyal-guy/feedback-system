<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$status = get_filter('status', FEEDBACK_STATUSES);
$category = get_filter('category', category_ids());
$q = get_filter('q');

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(f.subject LIKE ? OR f.message LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') { $where[] = 'f.status = ?'; $params[] = $status; }
if ($category !== '') { $where[] = 'f.category_id = ?'; $params[] = (int) $category; }

$stmt = db()->prepare('SELECT f.id, f.created_at, u.full_name, u.email, c.name AS category, f.subject, f.rating, f.status, f.message
    FROM feedback f JOIN users u ON u.id = f.user_id JOIN categories c ON c.id = f.category_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY f.created_at DESC');
$stmt->execute($params);

/** Prevent CSV/formula injection: cells starting with = + - @ are treated as text in Excel. */
function csv_safe($value): string
{
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

audit('export', 'Exported feedback report');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="feedback-report-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows characters correctly
fputcsv($out, ['ID', 'Date', 'Customer', 'Email', 'Category', 'Subject', 'Rating', 'Status', 'Message']);
while ($row = $stmt->fetch()) {
    fputcsv($out, array_map('csv_safe', $row));
}
fclose($out);
