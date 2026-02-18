<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(APP_BASE_URL, '/');
    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    $target = str_starts_with($path, 'http') ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }

    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}

function is_authenticated(): bool
{
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        set_flash('error', 'Faça login para continuar.');
        redirect('login.php');
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $inputToken = $_POST['_token'] ?? '';

    if (!is_string($sessionToken) || !is_string($inputToken)) {
        return false;
    }

    return hash_equals($sessionToken, $inputToken);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function sanitize_internal_redirect(?string $path, string $fallback = 'explore.php'): string
{
    $target = trim((string) $path);
    if ($target === '') {
        return $fallback;
    }

    if (str_contains($target, '://') || str_starts_with($target, '//')) {
        return $fallback;
    }

    if (!preg_match('/^[a-zA-Z0-9_\\-\\/\\.\\?=&]+$/', $target)) {
        return $fallback;
    }

    return $target;
}
