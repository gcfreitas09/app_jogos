<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'items' => [],
        'error' => 'Metodo nao permitido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$query = preg_replace('/[\x00-\x1F\x7F]/', '', $query) ?? '';
if (function_exists('mb_substr')) {
    $query = mb_substr($query, 0, 120);
    $queryLength = mb_strlen($query);
} else {
    $query = substr($query, 0, 120);
    $queryLength = strlen($query);
}

if ($queryLength < 3) {
    echo json_encode([
        'success' => true,
        'items' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'items' => [],
        'error' => 'cURL indisponivel no servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'format' => 'json',
    'limit' => 6,
    'countrycodes' => 'br',
    'q' => $query,
], '', '&', PHP_QUERY_RFC3986);

$ch = curl_init($url);
if ($ch === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'items' => [],
        'error' => 'Falha ao iniciar requisicao.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 4,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-Language: pt-BR',
        'User-Agent: AppJogos/1.0 (contato@seudominio.com)',
    ],
]);

$rawResponse = curl_exec($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($rawResponse === false || $statusCode < 200 || $statusCode >= 300) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'items' => [],
        'error' => $curlError !== '' ? $curlError : 'Falha ao consultar servico de geocodificacao.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($rawResponse, true);
if (!is_array($decoded)) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'items' => [],
        'error' => 'Resposta invalida do servico de geocodificacao.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = [];
foreach ($decoded as $row) {
    if (!is_array($row)) {
        continue;
    }

    $displayName = trim((string) ($row['display_name'] ?? ''));
    $lat = trim((string) ($row['lat'] ?? ''));
    $lon = trim((string) ($row['lon'] ?? ''));
    if ($displayName === '' || $lat === '' || $lon === '') {
        continue;
    }

    $items[] = [
        'display_name' => $displayName,
        'lat' => $lat,
        'lon' => $lon,
    ];

    if (count($items) >= 6) {
        break;
    }
}

$payload = json_encode([
    'success' => true,
    'items' => $items,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'items' => [],
        'error' => 'Falha ao serializar resposta.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo $payload;
