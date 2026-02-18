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

$data = $inviteService->getMyGames((int) $currentUser['id']);

echo json_encode([
    'data' => $data,
], JSON_UNESCAPED_UNICODE);
