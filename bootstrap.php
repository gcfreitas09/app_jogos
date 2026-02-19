<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Mailer;
use App\Repositories\InviteMemberRepository;
use App\Repositories\InviteRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\InviteService;
use App\Services\ProfileService;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

$config = require __DIR__ . '/config/app.php';

date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', rtrim((string) $config['base_url'], '/'));
}

if (!defined('APP_NAME')) {
    define('APP_NAME', (string) $config['app_name']);
}

$pdo = Database::connect($config['db']);

$userRepository = new UserRepository($pdo);
$inviteRepository = new InviteRepository($pdo);
$inviteMemberRepository = new InviteMemberRepository($pdo);
$profileRepository = new ProfileRepository($pdo);
$mailer = new Mailer($config['mail'], __DIR__ . '/storage/logs/mail.log');

$authService = new AuthService($userRepository);
$inviteService = new InviteService($pdo, $inviteRepository, $inviteMemberRepository, $mailer);
$profileService = new ProfileService($profileRepository, $userRepository);

require_once __DIR__ . '/src/Core/helpers.php';
