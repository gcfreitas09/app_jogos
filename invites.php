<?php
declare(strict_types=1);

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'explore.php';
if (is_string($query) && $query !== '') {
    $target .= '?' . $query;
}

require __DIR__ . '/bootstrap.php';
redirect($target);
