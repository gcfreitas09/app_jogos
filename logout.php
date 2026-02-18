<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$authService->logout();
set_flash('success', 'Sessão encerrada.');
redirect('login.php');
