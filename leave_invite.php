<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_auth();

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    $authService->logout();
    set_flash('error', 'Sua sessão expirou. Faça login novamente.');
    redirect('login.php');
}

$redirectTo = sanitize_internal_redirect($_POST['redirect_to'] ?? 'explore.php', 'explore.php');

if (!is_post()) {
    redirect($redirectTo);
}

if (!verify_csrf()) {
    set_flash('error', 'Token CSRF inválido.');
    redirect($redirectTo);
}

$inviteId = (int) ($_POST['invite_id'] ?? 0);
$result = $inviteService->leaveInvite($inviteId, (int) $currentUser['id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect($redirectTo);
