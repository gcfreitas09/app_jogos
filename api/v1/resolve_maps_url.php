<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão inválida.']);
    exit;
}

$rawUrl = trim((string) ($_GET['url'] ?? ''));
if ($rawUrl === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Link não informado.']);
    exit;
}

if (strlen($rawUrl) > 2048) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Link muito grande.']);
    exit;
}

$normalizedUrl = normalize_maps_input_url($rawUrl);
if ($normalizedUrl === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Link inválido.']);
    exit;
}

$sourceHost = strtolower((string) parse_url($normalizedUrl, PHP_URL_HOST));
if (!is_google_maps_host($sourceHost)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Host não permitido.']);
    exit;
}

$resolvedUrl = resolve_maps_url($normalizedUrl);
$finalUrl = $resolvedUrl ?? $normalizedUrl;
$finalHost = strtolower((string) parse_url($finalUrl, PHP_URL_HOST));
if ($finalHost !== '' && !is_google_maps_host($finalHost)) {
    $finalUrl = $normalizedUrl;
    $resolvedUrl = null;
}

echo json_encode([
    'success' => true,
    'source_url' => $normalizedUrl,
    'final_url' => $finalUrl,
    'resolved' => $resolvedUrl !== null,
], JSON_UNESCAPED_UNICODE);

function normalize_maps_input_url(string $raw): ?string
{
    $value = trim($raw);
    if ($value === '') {
        return null;
    }

    if (
        !preg_match('~^https?://~i', $value)
        && preg_match('~^[a-z0-9.-]+\.[a-z]{2,}(?:/|$)~i', $value)
    ) {
        $value = 'https://' . $value;
    }

    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return null;
    }

    return $value;
}

function is_google_maps_host(string $host): bool
{
    if ($host === '') {
        return false;
    }

    $allowedExact = [
        'maps.app.goo.gl',
        'goo.gl',
        'google.com',
        'www.google.com',
        'maps.google.com',
        'www.maps.google.com',
    ];
    if (in_array($host, $allowedExact, true)) {
        return true;
    }

    return str_ends_with($host, '.google.com')
        || str_ends_with($host, '.maps.app.goo.gl')
        || str_ends_with($host, '.goo.gl');
}

function resolve_maps_url(string $url): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $resolved = resolve_with_curl($url, true);
    if ($resolved !== null) {
        return $resolved;
    }

    return resolve_with_curl($url, false);
}

function resolve_with_curl(string $url, bool $headRequest): ?string
{
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'app_jogos/1.0',
        CURLOPT_HEADER => false,
    ];
    if ($headRequest) {
        $options[CURLOPT_NOBODY] = true;
    }

    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);

    if ($result === false) {
        curl_close($ch);
        return null;
    }

    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($effectiveUrl === '') {
        return null;
    }

    if ($statusCode < 200 || $statusCode >= 400) {
        return null;
    }

    return $effectiveUrl;
}
