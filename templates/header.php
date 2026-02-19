<?php
declare(strict_types=1);
$title = $title ?? APP_NAME;
$flashMessages = pull_flash_messages();
$currentUser = $currentUser ?? null;
$pageClass = isset($pageClass) && is_string($pageClass) ? trim($pageClass) : '';

$currentPath = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
if ($currentPath === '') {
    $currentPath = 'index.php';
}
$isActivePage = static fn (string $page): string => $currentPath === $page ? 'is-active' : '';

$headerUserName = $currentUser !== null ? trim((string) ($currentUser['name'] ?? '')) : '';
$headerAvatarUrl = '';
if (
    $currentUser !== null
    && isset($profileService)
    && is_object($profileService)
    && method_exists($profileService, 'getProfile')
) {
    $headerProfile = $profileService->getProfile((int) $currentUser['id']);
    if (is_array($headerProfile)) {
        $storedAvatar = trim((string) ($headerProfile['avatar_url'] ?? ''));
        if ($storedAvatar !== '') {
            if (preg_match('#^https?://#i', $storedAvatar) === 1) {
                $headerAvatarUrl = $storedAvatar;
            } else {
                $headerAvatarUrl = url($storedAvatar);
            }
        }
    }
}

$headerUserInitial = 'U';
if ($headerUserName !== '') {
    $headerUserInitial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($headerUserName, 0, 1))
        : strtoupper(substr($headerUserName, 0, 1));
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?> - <?php echo e(APP_NAME); ?></title>
    <script>
        (function () {
            try {
                var savedTheme = localStorage.getItem('app_jogos_theme');
                if (savedTheme === 'dark' || savedTheme === 'light') {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
            } catch (error) {
                // ignore
            }
        })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Archivo:wght@600;700&display=swap');
        :root {
            --layout-max: 1240px;
            --bg: #eef6f8;
            --bg-mesh: radial-gradient(circle at 15% 20%, #d2f4ea 0, transparent 45%),
                       radial-gradient(circle at 85% 0, #ffe4cc 0, transparent 36%),
                       linear-gradient(160deg, #edf5ff 0%, #f8fafc 60%, #f5fdf8 100%);
            --card: #ffffff;
            --text: #162531;
            --muted: #5c6f7b;
            --primary: #0b7f6f;
            --primary-dark: #06665a;
            --accent: #f97316;
            --success-bg: #e8faf5;
            --error-bg: #ffeaee;
            --border: #d6e1e7;
            --shadow: 0 12px 40px rgba(12, 42, 56, 0.12);
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: "Space Grotesk", "Trebuchet MS", sans-serif;
            background: var(--bg-mesh);
            color: var(--text);
            min-height: 100vh;
            transition: background-color 0.25s ease, color 0.25s ease;
        }
        img, video, iframe {
            max-width: 100%;
        }
        .container {
            width: min(100%, var(--layout-max));
            margin: 0 auto;
            padding: 24px 20px 30px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
        }
        .card-soft {
            background: linear-gradient(180deg, #ffffff 0%, #fdfefe 100%);
        }
        h1, h2, h3 {
            font-family: "Archivo", "Verdana", sans-serif;
            letter-spacing: -0.02em;
            margin: 0 0 8px;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(166, 188, 201, 0.35);
        }
        .topbar-inner {
            width: min(100%, var(--layout-max));
            margin: 0 auto;
            padding: 11px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .app-brand {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 26px;
            letter-spacing: -0.01em;
            white-space: nowrap;
            max-width: 145px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-left: 8px;
            min-width: 0;
            max-width: 320px;
        }
        .user-chip-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            object-fit: cover;
            border: 2px solid #cddfec;
            background: #eef5fb;
            flex: 0 0 36px;
        }
        .user-avatar-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #1f4f72;
            background: linear-gradient(145deg, #d6ecff 0%, #e7f5ff 100%);
        }
        .nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            min-width: 0;
            margin-left: auto;
        }
        .theme-switch {
            display: inline-grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: center;
            gap: 0;
            padding: 3px;
            border-radius: 999px;
            border: 1px solid #c7d8e2;
            background: #fff;
            overflow: hidden;
            width: 92px;
            min-width: 92px;
        }
        .theme-choice {
            width: 100% !important;
            min-width: 0;
            height: 34px;
            padding: 0 !important;
            border-radius: 999px !important;
            border: 1px solid transparent !important;
            background: transparent !important;
            color: #31536a !important;
            font-size: 18px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none !important;
            transform: none !important;
            cursor: pointer;
        }
        .theme-choice:hover {
            background: #eaf5fa !important;
        }
        .theme-choice.is-selected {
            background: #e4f6f0 !important;
            color: #0b6d5f !important;
            border-color: #9dd3c4 !important;
        }
        .nav a {
            margin-left: 0;
            padding: 8px 10px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--primary-dark);
            font-weight: 600;
            transition: background 0.2s ease;
            white-space: nowrap;
        }
        .nav a.is-active {
            background: #e4f6f0;
            color: #085c52;
        }
        .nav a.is-active {
            background: #e4f6f0;
            color: #085c52;
        }
        .nav a:hover {
            background: #eaf3f2;
        }
        .muted {
            color: var(--muted);
        }
        .flash {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 10px;
            font-size: 15px;
        }
        .flash.success {
            background: var(--success-bg);
            color: #0f5e46;
            border: 1px solid #bce8d8;
        }
        .flash.error {
            background: var(--error-bg);
            color: #8c2733;
            border: 1px solid #f4c8cf;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        input, select, textarea, button {
            width: 100%;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid #c9d8df;
            font-size: 15px;
            font-family: inherit;
            background: #fff;
        }
        textarea {
            min-height: 96px;
            resize: vertical;
        }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid rgba(11, 127, 111, 0.35);
            border-color: var(--primary);
        }
        .grid {
            display: grid;
            gap: 14px;
        }
        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        button, .btn {
            background: var(--primary);
            color: #fff;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        button:hover, .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(6, 102, 90, 0.25);
        }
        .btn-outline {
            background: #fff;
            color: var(--primary-dark);
            border: 1px solid var(--primary);
        }
        .btn-outline:hover {
            background: #eef9f7;
            box-shadow: none;
        }
        .btn-lg {
            padding: 12px 18px;
            font-size: 15px;
            width: auto;
        }
        .btn-danger {
            background: #be3d4e;
            width: auto;
        }
        .btn-danger:hover {
            background: #a33343;
            box-shadow: 0 8px 18px rgba(190, 61, 78, 0.28);
        }
        .btn-disabled {
            background: #cfd9df;
            color: #64717d;
            width: auto;
            cursor: not-allowed;
        }
        .error-list {
            margin: 0 0 16px;
            padding-left: 20px;
            color: #b93045;
        }
        .hero {
            background: linear-gradient(130deg, #0d7f6d 0%, #0d5f98 55%, #174c74 100%);
            border-radius: 20px;
            color: #fff;
            padding: 24px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 16px 30px rgba(16, 71, 101, 0.28);
        }
        .hero h1 {
            margin: 0;
            font-size: clamp(26px, 4vw, 34px);
        }
        .hero p {
            margin: 8px 0 0;
            color: rgba(235, 245, 255, 0.88);
            max-width: 560px;
        }
        .hero-content {
            max-width: 700px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 12px;
            align-items: end;
        }
        .filter-check label {
            margin-bottom: 8px;
        }
        .filter-check input {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: var(--accent);
            padding: 0;
            border: none;
            background: transparent;
        }
        .filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .section-block {
            margin-top: 14px;
        }
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .pill-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            background: #e7f2f9;
            color: #27587e;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
            border: 1px solid #c8deec;
        }
        .empty-state {
            padding: 18px;
            border-radius: 14px;
            color: #4d6476;
            border: 1px dashed #bfd1dc;
        }
        .invite-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
        }
        .invite-card {
            background: #fff;
            border: 1px solid #d4e1e7;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 8px 22px rgba(34, 62, 83, 0.08);
        }
        .invite-card-past {
            opacity: 0.74;
            filter: grayscale(0.12);
        }
        .invite-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge.sport {
            background: #fff4e8;
            color: #bf4d04;
        }
        .badge.status {
            border: 1px solid transparent;
        }
        .badge.status.open {
            background: #e8faf5;
            color: #0b664e;
            border-color: #bce8d8;
        }
        .badge.status.full {
            background: #ffecef;
            color: #9e2e3f;
            border-color: #efbec6;
        }
        .badge.status.ended {
            background: #eef1f4;
            color: #586678;
            border-color: #d3dce5;
        }
        .slots-row {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #e2eaef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }
        .actions-row {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .inline-form {
            display: inline-block;
            min-width: 120px;
            width: auto;
        }
        .inline-form button {
            width: auto;
        }
        .hint {
            color: #6b7d8d;
            font-size: 13px;
            font-weight: 600;
        }
        .context-rails {
            display: none;
        }
        .context-rail {
            position: fixed;
            top: 96px;
            width: 220px;
            z-index: 6;
            max-height: calc(100vh - 118px);
            overflow: auto;
        }
        .context-rail-left {
            left: max(12px, calc((100vw - 980px) / 2 - 236px));
        }
        .context-rail-right {
            right: max(12px, calc((100vw - 980px) / 2 - 236px));
        }
        .context-card {
            border: 1px solid #d4e1e8;
            border-radius: 14px;
            padding: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
            box-shadow: 0 10px 22px rgba(18, 41, 57, 0.1);
            margin-bottom: 12px;
        }
        .context-card h3 {
            margin: 0 0 9px;
            font-size: 18px;
        }
        .context-links {
            display: grid;
            gap: 8px;
        }
        .context-link {
            display: block;
            text-decoration: none;
            border: 1px solid #d1dfe8;
            border-radius: 11px;
            background: #fff;
            color: #1e435b;
            font-weight: 700;
            padding: 8px 10px;
            font-size: 14px;
        }
        .context-link:hover {
            background: #ecf7fb;
        }
        .context-link.is-active {
            border-color: #8ec6b7;
            background: #e8f8f3;
            color: #065f54;
        }
        .context-note {
            font-size: 13px;
            color: #5e7688;
            margin: 0;
            line-height: 1.4;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .detail-label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }
        .participant-list {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }
        .participant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border: 1px solid #dbe6ec;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fcfefe;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            border-bottom: 1px solid #e3ebf0;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .text-right {
            text-align: right;
        }
        html[data-theme='dark'] body {
            background: radial-gradient(circle at 15% 20%, #0a2530 0, transparent 45%),
                        radial-gradient(circle at 85% 0, #2a1f16 0, transparent 40%),
                        linear-gradient(160deg, #071a2e 0%, #0d1b2b 55%, #0c2131 100%);
            color: #dde9f5;
        }
        html[data-theme='dark'] .topbar {
            background: rgba(6, 19, 33, 0.9);
            border-bottom-color: #274258;
        }
        html[data-theme='dark'] .app-brand,
        html[data-theme='dark'] .user-chip-name,
        html[data-theme='dark'] .nav a {
            color: #dff0ff;
        }
        html[data-theme='dark'] .theme-switch {
            background: #12263a;
            border-color: #2f4d64;
        }
        html[data-theme='dark'] .theme-choice {
            color: #c1d9ee !important;
        }
        html[data-theme='dark'] .theme-choice:hover {
            background: #1f3b53 !important;
        }
        html[data-theme='dark'] .theme-choice.is-selected {
            background: #174666 !important;
            border-color: #3d7092 !important;
            color: #ffffff !important;
        }
        html[data-theme='dark'] .nav a.is-active {
            background: #1a3f5a;
            color: #ffffff;
        }
        html[data-theme='dark'] .card,
        html[data-theme='dark'] .card-soft,
        html[data-theme='dark'] .invite-card,
        html[data-theme='dark'] table,
        html[data-theme='dark'] tr {
            background: #13273a !important;
            border-color: #294860 !important;
            color: #e7f2fb !important;
            box-shadow: 0 10px 24px rgba(1, 8, 14, 0.35);
        }
        html[data-theme='dark'] .empty-state,
        html[data-theme='dark'] .participant-item {
            background: #102235 !important;
            border-color: #27455d !important;
            color: #d5e6f4 !important;
        }
        html[data-theme='dark'] input,
        html[data-theme='dark'] select,
        html[data-theme='dark'] textarea {
            background: #0f2235;
            border-color: #35536a;
            color: #e8f5ff;
        }
        html[data-theme='dark'] .btn-outline {
            background: #132a40;
            border-color: #3a6583;
            color: #deefff;
        }
        html[data-theme='dark'] .muted,
        html[data-theme='dark'] .hint,
        html[data-theme='dark'] th,
        html[data-theme='dark'] .detail-label {
            color: #a5bdd0 !important;
        }
        html[data-theme='dark'] .pill-count {
            background: #18354b;
            border-color: #2f5b78;
            color: #cbe6fb;
        }
        html[data-theme='dark'] .user-avatar {
            border-color: #3c5f79;
        }
        @media (max-width: 1180px) {
            .user-chip {
                max-width: 220px;
            }
            .user-chip-name {
                max-width: 150px;
                font-size: 16px;
            }
        }
        @media (max-width: 920px) {
            .user-chip {
                display: none;
            }
            .filter-grid {
                grid-template-columns: repeat(2, minmax(150px, 1fr));
            }
        }
        @media (max-width: 760px) {
            .hero {
                padding: 18px;
                border-radius: 16px;
                flex-direction: column;
                align-items: flex-start;
            }
            .hero h1 {
                font-size: clamp(22px, 7vw, 30px);
            }
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                grid-template-columns: 1fr;
            }
            .participant-item {
                flex-direction: column;
                align-items: flex-start;
            }
            label,
            .muted,
            .hint {
                font-size: 13px;
            }
            input,
            select,
            textarea,
            button {
                font-size: 15px;
            }
            .actions-row .btn,
            .actions-row .inline-form,
            .actions-row .inline-form button {
                width: 100%;
            }
            .btn-lg {
                width: 100%;
            }
            .context-rails {
                display: none !important;
            }
        }
        @media (max-width: 560px) {
            body {
                font-size: 13px;
            }
            .app-brand {
                max-width: 120px;
                font-size: 24px;
            }
            .topbar-inner {
                padding: 10px 12px;
            }
            .nav a {
                font-size: 12px;
                padding: 6px 8px;
            }
            .theme-switch {
                width: 76px;
                min-width: 76px;
            }
            h1 {
                font-size: 1.55rem;
            }
            h2 {
                font-size: 1.3rem;
            }
            h3 {
                font-size: 1.1rem;
            }
            .theme-choice {
                width: 100% !important;
                min-width: 0;
                height: 28px;
                font-size: 15px;
            }
        }
        @media (max-width: 680px) {
            table, thead, tbody, tr, th, td {
                display: block;
                width: 100%;
            }
            tr {
                border: 1px solid #dce7ed;
                border-radius: 8px;
                margin-bottom: 10px;
                padding: 8px;
                background: #fff;
            }
            th {
                display: none;
            }
            td {
                border: none;
                padding: 6px 0;
            }
        }
        @media (min-width: 1460px) {
            .context-rails {
                display: block;
            }
        }
    </style>
</head>
<body<?php echo $pageClass !== '' ? ' class="' . e($pageClass) . '"' : ''; ?>>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="app-brand" href="<?php echo e(url('explore.php')); ?>"><?php echo e(APP_NAME); ?></a>
            <?php if ($currentUser !== null): ?>
                <div class="user-chip">
                    <?php if ($headerAvatarUrl !== ''): ?>
                        <img src="<?php echo e($headerAvatarUrl); ?>" alt="Foto de perfil" class="user-avatar">
                    <?php else: ?>
                        <span class="user-avatar user-avatar-fallback"><?php echo e($headerUserInitial); ?></span>
                    <?php endif; ?>
                    <span class="user-chip-name"><?php echo e((string) $currentUser['name']); ?></span>
                </div>
            <?php endif; ?>
            <nav class="nav">
                <?php if ($currentUser !== null): ?>
                    <div class="theme-switch" role="group" aria-label="Tema">
                        <button type="button" class="theme-choice" data-theme-choice="light" title="Tema claro">&#127774;</button>
                        <button type="button" class="theme-choice" data-theme-choice="dark" title="Tema escuro">&#127769;</button>
                    </div>
                    <a class="<?php echo e($isActivePage('explore.php')); ?>" href="<?php echo e(url('explore.php')); ?>">Explorar</a>
                    <a class="<?php echo e($isActivePage('my_games.php')); ?>" href="<?php echo e(url('my_games.php')); ?>">Meus Jogos</a>
                    <a class="<?php echo e($isActivePage('create_invite.php')); ?>" href="<?php echo e(url('create_invite.php')); ?>">Criar Convite</a>
                    <a class="<?php echo e($isActivePage('profile.php')); ?>" href="<?php echo e(url('profile.php')); ?>">Perfil</a>
                    <a href="<?php echo e(url('logout.php')); ?>">Sair</a>
                <?php else: ?>
                    <a href="<?php echo e(url('login.php')); ?>">Entrar</a>
                    <a href="<?php echo e(url('register.php')); ?>">Cadastrar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <script>
        (function () {
            var root = document.documentElement;
            var themeButtons = document.querySelectorAll('[data-theme-choice]');
            var storageKey = 'app_jogos_theme';

            function applyTheme(theme) {
                root.setAttribute('data-theme', theme);
                try {
                    localStorage.setItem(storageKey, theme);
                } catch (error) {
                    // ignore
                }

                themeButtons.forEach(function (button) {
                    var isSelected = button.getAttribute('data-theme-choice') === theme;
                    button.classList.toggle('is-selected', isSelected);
                    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                });
            }

            if (themeButtons.length === 0) {
                return;
            }

            var activeTheme = root.getAttribute('data-theme');
            if (activeTheme !== 'dark' && activeTheme !== 'light') {
                activeTheme = 'light';
            }
            applyTheme(activeTheme);

            themeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var nextTheme = button.getAttribute('data-theme-choice');
                    if (nextTheme === 'dark' || nextTheme === 'light') {
                        applyTheme(nextTheme);
                    }
                });
            });
        })();
    </script>
    <main class="container">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="flash <?php echo e((string) $flash['type']); ?>">
                <?php echo e((string) $flash['message']); ?>
            </div>
        <?php endforeach; ?>

