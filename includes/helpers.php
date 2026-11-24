<?php
/** Shared helpers for feedback data and display. */

const FEEDBACK_STATUSES = ['Pending', 'In Review', 'Resolved'];

function categories(): array
{
    static $cats = null;
    return $cats ??= db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
}

function category_ids(): array
{
    return array_map(fn($c) => (string) $c['id'], categories());
}

function stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return '<span class="stars" aria-label="' . $rating . ' out of 5 stars">'
        . str_repeat('★', $rating) . '<span class="stars-off">' . str_repeat('★', 5 - $rating) . '</span></span>';
}

function status_badge(string $status): string
{
    $class = match ($status) {
        'Resolved' => 'badge-green',
        'In Review' => 'badge-blue',
        default => 'badge-amber',
    };
    return '<span class="badge ' . $class . '">' . e($status) . '</span>';
}

function fmt_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '—';
    }
    return date($withTime ? 'j M Y, g:i a' : 'j M Y', strtotime($date));
}

function excerpt(string $text, int $len = 90): string
{
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}

/** Read a GET filter safely: only accept values from an allowed list. */
function get_filter(string $key, ?array $allowed = null): string
{
    $v = $_GET[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(mb_substr($v, 0, 100));
    if ($allowed !== null && !in_array($v, array_map('strval', $allowed), true)) {
        return '';
    }
    return $v;
}

/** Build a query string that keeps current filters (used by pagination links). */
function query_with(array $changes): string
{
    $params = array_merge($_GET, $changes);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && is_scalar($v));
    return '?' . http_build_query($params);
}

function pagination(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    if ($page > 1) {
        $html .= '<a href="' . e(query_with(['page' => $page - 1])) . '">&larr; Prev</a>';
    }
    $html .= '<span>Page ' . $page . ' of ' . $totalPages . '</span>';
    if ($page < $totalPages) {
        $html .= '<a href="' . e(query_with(['page' => $page + 1])) . '">Next &rarr;</a>';
    }
    return $html . '</nav>';
}
