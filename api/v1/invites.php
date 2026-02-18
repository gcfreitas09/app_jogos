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

$sport = trim((string) ($_GET['sport'] ?? ''));
$period = trim((string) ($_GET['period'] ?? 'all'));
$onlyWithSlots = ((string) ($_GET['only_with_slots'] ?? '0')) === '1';
$radiusKm = (int) ($_GET['radius_km'] ?? 0);
if (!in_array($radiusKm, App\Services\InviteService::allowedRadii(), true)) {
    $radiusKm = null;
}

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

$data = $inviteService->listInvites(
    (int) $currentUser['id'],
    $sport !== '' ? $sport : null,
    $period,
    $onlyWithSlots,
    $lat,
    $lng,
    ($lat !== null && $lng !== null) ? $radiusKm : null
);

$upcoming = array_values(array_filter(
    $data,
    static fn (array $invite): bool => !$invite['is_past']
));

$past = array_values(array_filter(
    $data,
    static fn (array $invite): bool => $invite['is_past']
));

echo json_encode([
    'data' => $data,
    'upcoming' => $upcoming,
    'past' => $past,
    'meta' => [
        'sports' => App\Services\InviteService::allowedSports(),
        'periods' => App\Services\InviteService::allowedPeriods(),
        'radii' => App\Services\InviteService::allowedRadii(),
    ],
], JSON_UNESCAPED_UNICODE);
