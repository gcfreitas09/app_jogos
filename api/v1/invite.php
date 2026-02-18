<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão inválida.']);
    exit;
}

$inviteId = (int) ($_GET['id'] ?? 0);

$lat = null;
$lng = null;
$rawLat = trim((string) ($_GET['lat'] ?? ''));
$rawLng = trim((string) ($_GET['lng'] ?? ''));
if ($rawLat !== '' && $rawLng !== '') {
    $rawLat = str_replace(',', '.', $rawLat);
    $rawLng = str_replace(',', '.', $rawLng);
    if (is_numeric($rawLat) && is_numeric($rawLng)) {
        $latValue = (float) $rawLat;
        $lngValue = (float) $rawLng;
        if ($latValue >= -90 && $latValue <= 90 && $lngValue >= -180 && $lngValue <= 180) {
            $lat = $latValue;
            $lng = $lngValue;
        }
    }
}

$invite = $inviteService->getInviteDetail($inviteId, (int) $currentUser['id'], $lat, $lng);
if ($invite === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Convite não encontrado.']);
    exit;
}

echo json_encode([
    'data' => $invite,
], JSON_UNESCAPED_UNICODE);
