<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'address' => '',
        'error' => 'Metodo nao permitido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$latRaw = trim((string) ($_GET['lat'] ?? ''));
$lngRaw = trim((string) ($_GET['lng'] ?? ''));
$latRaw = str_replace(',', '.', $latRaw);
$lngRaw = str_replace(',', '.', $lngRaw);

if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'address' => '',
        'error' => 'Latitude/longitude invalidas.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$lat = (float) $latRaw;
$lng = (float) $lngRaw;
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'address' => '',
        'error' => 'Latitude/longitude fora do intervalo.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'address' => '',
        'error' => 'cURL indisponivel no servidor.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
    'format' => 'jsonv2',
    'lat' => number_format($lat, 7, '.', ''),
    'lon' => number_format($lng, 7, '.', ''),
    'zoom' => 18,
    'addressdetails' => 1,
], '', '&', PHP_QUERY_RFC3986);

$ch = curl_init($url);
if ($ch === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'address' => '',
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
        'address' => '',
        'error' => $curlError !== '' ? $curlError : 'Falha ao consultar servico de geocodificacao reversa.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($rawResponse, true);
$address = is_array($decoded) ? trim((string) ($decoded['display_name'] ?? '')) : '';
if ($address === '') {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'address' => '',
        'error' => 'Resposta invalida do servico de geocodificacao reversa.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_encode([
    'success' => true,
    'address' => $address,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'address' => '',
        'error' => 'Falha ao serializar resposta.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo $payload;
