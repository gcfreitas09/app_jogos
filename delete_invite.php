<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_auth();

$currentUser = $authService->currentUser();
if ($currentUser === null) {
    $authService->logout();
    set_flash('error', 'Sua sessao expirou. Faca login novamente.');
    redirect('login.php');
}

$redirectTo = sanitize_internal_redirect($_POST['redirect_to'] ?? 'my_games.php', 'my_games.php');

if (!is_post()) {
    redirect($redirectTo);
}

if (!verify_csrf()) {
    set_flash('error', 'Token CSRF invalido.');
    redirect($redirectTo);
}

$inviteId = (int) ($_POST['invite_id'] ?? 0);
$result = $inviteService->deleteInvite($inviteId, (int) $currentUser['id']);

set_flash($result['success'] ? 'success' : 'error', $result['message']);
redirect($redirectTo);
