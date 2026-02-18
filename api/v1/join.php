<?php
declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
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

$payload = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (is_string($contentType) && str_contains($contentType, 'application/json')) {
    $rawBody = file_get_contents('php://input');
    $decoded = json_decode((string) $rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$inviteId = (int) ($payload['invite_id'] ?? 0);
$result = $inviteService->joinInvite($inviteId, (int) $currentUser['id']);

http_response_code($result['success'] ? 200 : 422);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
